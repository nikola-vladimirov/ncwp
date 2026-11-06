<?php
/**
 * Courier registration and checkout shipping checks.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the file name to match the class name.

namespace Nashenski\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the turned-on couriers and checks the checkout shipping choice.
 */
class Register {
	/**
	 * Per courier: class folder, the classes that serve its checkout lists, the settings group of
	 * "Generate label after checkout", and the priority that saves the label data of a new order.
	 */
	const COURIERS = array(
		'econt'  => array( 'Econt', array( 'Address', 'Office', 'APSBoxes' ), 'econt', 200 ),
		'speedy' => array( 'Speedy', array( 'Address', 'Office' ), 'speedy', 200 ),
		'boxnow' => array( 'BoxNow', array( 'Apm' ), 'boxnow_send_from', 200 ),
		'pigeon' => array( 'Pigeon', array( 'Address', 'Office', 'Locker', 'APSBoxes', 'PackageBoxes' ), 'pigeon', 200 ),
	);

	/**
	 * Register the turned-on couriers and the checkout hooks.
	 */
	public function __construct() {
		foreach ( self::COURIERS as $slug => list( $name, $parts, $label_group, $save_priority ) ) {
			if ( 'yes' === nashenski_get_option( 'apis', 'enable_' . $slug ) ) {
				self::register_courier( $slug, $name, $parts, $label_group, $save_priority );
			}
		}

		if ( nashenski_is_shipping_enabled() ) {
			add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'update_order_review' ), 0 );
			add_action( 'woocommerce_order_item_shipping_after_calculate_taxes', array( __CLASS__, 'set_shipping_rate_taxes_for_recalculation' ), 10, 2 );
			add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_cod_choice' ), 1, 2 );
			add_action( 'woocommerce_checkout_process', array( __CLASS__, 'remember_shown_prices' ) );
			add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_shown_prices' ), 2, 2 );
		}
	}

	/**
	 * Package key => courier price the customer saw (null while it said "Complete the fields").
	 *
	 * @var array
	 */
	private static $shown = array();

	/**
	 * Classic checkout: the courier prices on screen, before the order prices the choice again (an
	 * office or address chosen right before "Place order" was not priced on screen yet). The block
	 * checkout has WooCommerce's own "order total changed" check.
	 */
	public static function remember_shown_prices() {
		foreach ( (array) WC()->session->get( 'chosen_shipping_methods' ) as $key => $chosen ) {
			$rate = WC()->session->get( 'shipping_for_package_' . $key )['rates'][ $chosen ] ?? null;

			if ( $rate && 0 === strpos( (string) $chosen, 'nashenski_' ) ) {
				self::$shown[ $key ] = \Nashenski\Front_End\Checkout\Pickers::is_pending( $rate ) ? null : (float) $rate->get_cost();
			}
		}
	}

	/**
	 * Stop the order when a courier price changed from the one on screen.
	 *
	 * @param array     $data   Posted checkout data.
	 * @param \WP_Error $errors Checkout errors.
	 */
	public static function validate_shown_prices( $data, $errors ) {
		foreach ( self::$shown as $key => $cost ) {
			$rate = nashenski_chosen_rate( $key, ( (array) WC()->session->get( 'chosen_shipping_methods' ) )[ $key ] ?? '' );

			// Still not priced: the courier's own validation asks for the choice.
			if ( ! $rate || \Nashenski\Front_End\Checkout\Pickers::is_pending( $rate ) || $cost === (float) $rate->get_cost() ) {
				continue;
			}

			WC()->session->set( 'refresh_totals', true );
			$errors->add( 'validation', __( 'The shipping price was calculated for your choice. Please check the updated total and place the order again.', 'nashenski-commerce' ) );

			return;
		}
	}

	/**
	 * The courier price and waybill (cash on delivery amount included or not) were calculated with
	 * the payment method saved in the courier cookie. The order must be placed with a payment method
	 * of the same kind, or the waybill would not collect the cash (or the price would be wrong).
	 * On a mismatch the rates are calculated again and the customer is asked to check the total.
	 *
	 * @param array     $data   Posted checkout data.
	 * @param \WP_Error $errors Checkout errors.
	 */
	public static function validate_cod_choice( $data, $errors ) {
		if ( ! WC()->cart || ! WC()->session || ! WC()->cart->needs_shipping() ) {
			return;
		}

		$cod      = 'cod' === ( $data['payment_method'] ?? '' );
		$mismatch = false;

		foreach ( (array) WC()->session->get( 'chosen_shipping_methods' ) as $key => $chosen ) {
			if ( 0 !== strpos( (string) $chosen, 'nashenski_' ) ) {
				continue;
			}

			$rate = nashenski_chosen_rate( $key, $chosen );
			$meta = $rate ? $rate->get_meta_data() : array();

			// No calculated choice yet: the courier's own validation asks for one.
			if ( empty( $meta['cookie_data'] ) || ! is_array( $meta['cookie_data'] ) ) {
				continue;
			}

			if ( ( 'cod' === ( $meta['cookie_data']['payment'] ?? '' ) ) !== $cod ) {
				$mismatch = true;
				WC()->session->__unset( 'shipping_for_package_' . $key );
			}
		}

		if ( $mismatch ) {
			WC()->session->set( 'refresh_totals', true );
			$errors->add( 'validation', __( 'The payment method changed after the shipping price was calculated. Please check the updated total and place the order again.', 'nashenski-commerce' ) );
		}
	}

	/**
	 * Load a courier's classes and hook its shipping method.
	 *
	 * @param string $slug          Courier slug.
	 * @param string $name          Class folder.
	 * @param array  $parts         Classes of the checkout lists.
	 * @param string $label_group   Settings group of "Generate label after checkout".
	 * @param int    $save_priority Priority that saves the label data of a new order.
	 */
	private static function register_courier( $slug, $name, $parts, $label_group, $save_priority ) {
		$method = __NAMESPACE__ . '\\' . $name . '\\Method';
		$admin  = 'Nashenski\\Admin\\' . $name;
		$cron   = 'Nashenski\\Cron\\' . $name;

		new $cron();

		foreach ( $parts as $part ) {
			$class = __NAMESPACE__ . '\\' . $name . '\\' . $part;
			new $class();
		}

		new $admin();

		add_filter(
			'woocommerce_shipping_methods',
			function ( $methods ) use ( $method ) {
				$methods[ $method::METHOD_ID ] = $method;

				return $methods;
			}
		);
		add_action( 'woocommerce_after_checkout_validation', array( $method, 'validate_' . $slug . '_method' ), 20, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( $method, 'save_label_data_to_order' ), $save_priority, 2 );

		if ( 'yes' === nashenski_get_option( $label_group, 'label_after_checkout' ) ) {
			add_action( 'woocommerce_checkout_order_processed', array( $admin, 'generate_label_after_order_generated' ), 250 );
		}

		add_action( 'woocommerce_email_order_details', array( $method, 'add_label_number_to_email' ), 1, 4 );
	}

	/**
	 * Classic checkout "update order review": save the posted address and price the shipping again.
	 */
	public static function update_order_review() {
		self::update_session();

		$packages = WC()->cart->get_shipping_packages();

		foreach ( $packages as $key => $value ) {
			$shipping_session = "shipping_for_package_$key";
			unset( WC()->session->$shipping_session );
		}

		WC()->cart->calculate_shipping();
	}

	/**
	 * Courier shipping taxes when an order is recalculated.
	 *
	 * @param \WC_Order_Item_Shipping $method            Shipping line.
	 * @param array                   $calculate_tax_for Location.
	 */
	public static function set_shipping_rate_taxes_for_recalculation( $method, $calculate_tax_for ) {
		if ( strpos( $method['method_id'], 'nashenski' ) === false ) {
			return;
		}

		if ( wc_tax_enabled() ) {
			$country = $calculate_tax_for['country'] ?? 'BG';
			$method->set_taxes( nashenski_get_shipping_rate_taxes( $method->get_total(), $country ) );
		}
	}

	/**
	 * Save the posted payment method, shipping methods and address in the session and customer.
	 */
	private static function update_session() {
		$wc = WC();

		if ( ! $wc || ! $wc->session || ! $wc->customer ) {
			return;
		}

		$post_data = self::get_checkout_post_data();

		if ( empty( $post_data ) ) {
			return;
		}

		if ( isset( $post_data['payment_method'] ) && is_string( $post_data['payment_method'] ) ) {
			$wc->session->set( 'chosen_payment_method', $post_data['payment_method'] );
		}

		self::update_chosen_shipping_methods( $post_data['shipping_method'] ?? array() );

		$customer_props = self::get_customer_address_props( $post_data, 'billing' );
		$shipping_from  = ( wc_ship_to_billing_address_only() || empty( $post_data['ship_to_different_address'] ) ) ? 'billing' : 'shipping';

		$customer_props = array_merge(
			$customer_props,
			self::get_customer_address_props( $post_data, $shipping_from, 'shipping' )
		);

		$should_save_customer = ! empty( $customer_props );

		if ( $customer_props ) {
			$wc->customer->set_props( $customer_props );
		}

		if ( array_key_exists( 'has_full_address', $post_data ) && is_scalar( $post_data['has_full_address'] ) ) {
			$wc->customer->set_calculated_shipping( wc_string_to_bool( $post_data['has_full_address'] ) );
			$should_save_customer = true;
		}

		if ( $should_save_customer ) {
			$wc->customer->save();
		}
	}

	/**
	 * The checkout form sent with "update order review" plus the fields WooCommerce posts beside it
	 * (country, s_country, shipping_method and so on). WooCommerce checked its nonce before.
	 */
	private static function get_checkout_post_data() {
		$posted = array();

		foreach ( $_POST as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( 'post_data' !== $key ) {
				$posted[ $key ] = wc_clean( wp_unslash( $value ) );
			}
		}

		return self::normalize_checkout_post_data( array_replace_recursive( nashenski_order_review_data(), $posted ) );
	}

	/**
	 * Fill the billing_ and shipping_ address keys from their short aliases.
	 *
	 * @param array $post_data Posted data.
	 * @return array
	 */
	private static function normalize_checkout_post_data( $post_data ) {
		$aliases = array(
			'billing_country'    => 'country',
			'billing_state'      => 'state',
			'billing_postcode'   => 'postcode',
			'billing_city'       => 'city',
			'billing_address_1'  => 'address',
			'billing_address_2'  => 'address_2',
			'shipping_country'   => 's_country',
			'shipping_state'     => 's_state',
			'shipping_postcode'  => 's_postcode',
			'shipping_city'      => 's_city',
			'shipping_address_1' => 's_address',
			'shipping_address_2' => 's_address_2',
		);

		foreach ( $aliases as $canonical_key => $alias_key ) {
			if (
				( ! array_key_exists( $canonical_key, $post_data ) || null === $post_data[ $canonical_key ] )
				&& array_key_exists( $alias_key, $post_data )
			) {
				$post_data[ $canonical_key ] = $post_data[ $alias_key ];
			}
		}

		return $post_data;
	}

	/**
	 * Save the posted shipping methods as the chosen ones.
	 *
	 * @param mixed $posted_shipping_methods Posted shipping methods.
	 */
	private static function update_chosen_shipping_methods( $posted_shipping_methods ) {
		if ( is_string( $posted_shipping_methods ) ) {
			$posted_shipping_methods = array( 0 => $posted_shipping_methods );
		}

		if ( ! is_array( $posted_shipping_methods ) ) {
			return;
		}

		$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods', array() );
		$updated                 = false;

		if ( ! is_array( $chosen_shipping_methods ) ) {
			$chosen_shipping_methods = array();
		}

		foreach ( $posted_shipping_methods as $index => $method_id ) {
			if ( ! is_string( $method_id ) ) {
				continue;
			}

			if (
				! array_key_exists( $index, $chosen_shipping_methods )
				|| $chosen_shipping_methods[ $index ] !== $method_id
			) {
				$chosen_shipping_methods[ $index ] = $method_id;
				$updated                           = true;
			}
		}

		if ( $updated ) {
			WC()->session->set( 'chosen_shipping_methods', $chosen_shipping_methods );
		}
	}

	/**
	 * Customer address props from the posted fields.
	 *
	 * @param array       $post_data     Posted data.
	 * @param string      $source_prefix billing or shipping, in the posted data.
	 * @param string|null $target_prefix billing or shipping, on the customer; the source prefix when empty.
	 * @return array
	 */
	private static function get_customer_address_props( $post_data, $source_prefix, $target_prefix = null ) {
		$target_prefix = $target_prefix ? $target_prefix : $source_prefix;
		$props         = array();
		$fields        = array( 'country', 'state', 'postcode', 'city', 'address_1', 'address_2' );

		foreach ( $fields as $field ) {
			$source_key = "{$source_prefix}_{$field}";

			if ( ! array_key_exists( $source_key, $post_data ) ) {
				continue;
			}

			$value = $post_data[ $source_key ];

			if ( null !== $value && is_scalar( $value ) ) {
				$props[ "{$target_prefix}_{$field}" ] = $value;
			}
		}

		return $props;
	}
}
