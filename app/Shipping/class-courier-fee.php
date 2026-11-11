<?php
/**
 * Who pays the courier: the shop, or the customer at delivery.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Who pays the courier (docs/plans/courier-fee-payer.md):
 * - each Econt, Speedy and Pigeon Express method lists the payment methods for which the customer
 *   pays the courier at delivery (BOX NOW cannot charge the customer, so it has no list);
 * - then the rate costs nothing and keeps the fee for information only, so the fee is not in the
 *   order total, on the documents or in the NRA file;
 * - the checkout stores the decision on the order's shipping line, so later setting changes do not
 *   change old documents.
 */
class Courier_Fee {
	/** Shipping line meta: "customer" or "shop", stored at checkout. */
	const PAYER_META = '_nashenski_fee_payer';

	/** Rate and shipping line meta: the fee the customer pays the courier, with VAT. */
	const FEE_META = 'nashenski_courier_fee';

	/** Shipping method setting: payment methods for which the customer pays the courier. */
	const FIELD = 'customer_pays';

	/**
	 * Payment gateways, once WooCommerce has loaded them.
	 *
	 * @var \WC_Payment_Gateways|null
	 */
	private static $gateways = null;

	/**
	 * Hooks the checkout stamp and the order display.
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_create_order_shipping_item', array( __CLASS__, 'stamp' ) );
		add_filter( 'woocommerce_order_shipping_to_display', array( __CLASS__, 'order_shipping' ), 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( __CLASS__, 'meta_key' ), 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_value', array( __CLASS__, 'meta_value' ), 10, 3 );
		add_action( 'wc_payment_gateways_initialized', array( __CLASS__, 'gateways_loaded' ) );

		// The zone screen and its requests show the setting: load the payment methods before the shipping methods are built.
		foreach ( array( 'woocommerce_settings_shipping', 'wp_ajax_woocommerce_shipping_zone_add_method', 'wp_ajax_woocommerce_shipping_zone_remove_method', 'wp_ajax_woocommerce_shipping_zone_methods_save_changes', 'wp_ajax_woocommerce_shipping_zone_methods_save_settings' ) as $hook ) {
			add_action( $hook, array( 'WC_Payment_Gateways', 'instance' ), 1 );
		}
	}

	/**
	 * Keeps the payment gateways WooCommerce has just loaded.
	 *
	 * @param \WC_Payment_Gateways $gateways Payment gateways.
	 */
	public static function gateways_loaded( $gateways ) {
		self::$gateways = $gateways;
	}

	/**
	 * The method setting (Econt, Speedy, Pigeon Express). Its help text comes from Setting_Help.
	 * The shipping methods are also built while payment gateways load (cash on delivery lists them),
	 * so the payment methods are only read once loaded, never loaded from here.
	 *
	 * @return array
	 */
	public static function field() {
		$options = array();

		foreach ( self::$gateways ? self::$gateways->payment_gateways() : array() as $id => $gateway ) {
			if ( wc_string_to_bool( $gateway->enabled ) ) {
				$options[ $id ] = $gateway->get_title() ? $gateway->get_title() : $id;
			}
		}

		return array(
			'title'   => __( 'Customer pays the courier', 'nashenski-commerce' ),
			'type'    => 'multiselect',
			'class'   => 'wc-enhanced-select',
			'css'     => 'width: 400px;',
			'default' => array(),
			'options' => $options,
		);
	}

	/**
	 * Whether the customer pays the courier with this payment method.
	 *
	 * @param mixed  $listed  The method's saved list.
	 * @param string $payment Payment method ID.
	 * @return bool
	 */
	public static function customer_pays( $listed, $payment ) {
		return '' !== (string) $payment && in_array( (string) $payment, (array) $listed, true );
	}

	/**
	 * The saved list of a method instance, read without loading the method.
	 *
	 * @param string $method_id   Method ID.
	 * @param int    $instance_id Instance ID.
	 * @return array
	 */
	public static function listed( $method_id, $instance_id ) {
		$settings = get_option( 'woocommerce_' . $method_id . '_' . $instance_id . '_settings', array() );

		return is_array( $settings ) ? (array) ( $settings[ self::FIELD ] ?? array() ) : array();
	}

	/**
	 * A rate the customer pays the courier for: it costs nothing and keeps the fee as information.
	 * A free or not yet priced rate stays as it is: there is nothing to pay the courier.
	 *
	 * @param array $rate Rate arguments for add_rate().
	 * @return array
	 */
	public static function rate( $rate ) {
		$fee = (float) ( $rate['cost'] ?? 0 ) + array_sum( array_map( 'floatval', (array) ( $rate['taxes'] ?? array() ) ) );

		if ( $fee <= 0 ) {
			return $rate;
		}

		$rate['meta_data'][ self::PAYER_META ] = 'customer';
		$rate['meta_data'][ self::FEE_META ]   = wc_format_decimal( $fee, wc_get_price_decimals() );
		$rate['cost']                          = 0;
		unset( $rate['taxes'] );

		return $rate;
	}

	/**
	 * Checkout: every shipping line keeps who collects the delivery. Courier rates say "customer"
	 * themselves; anything else is collected by the shop.
	 *
	 * @param \WC_Order_Item_Shipping $item Order shipping line.
	 */
	public static function stamp( $item ) {
		if ( ! $item->get_meta( self::PAYER_META ) ) {
			$item->add_meta_data( self::PAYER_META, 'shop', true );
		}
	}

	/**
	 * The decision stored at checkout: "customer", "shop", or "" for an order without one (made
	 * before 0.21.8, or a quick or admin order). A refund follows its order.
	 *
	 * @param \WC_Order|\WC_Order_Refund|false $order Order or refund.
	 * @return string
	 */
	public static function payer( $order ) {
		if ( is_a( $order, 'WC_Order_Refund' ) ) {
			$order = wc_get_order( $order->get_parent_id() );
		}

		foreach ( $order ? $order->get_items( 'shipping' ) : array() as $item ) {
			$payer = (string) $item->get_meta( self::PAYER_META );

			if ( '' !== $payer ) {
				return $payer;
			}
		}

		return '';
	}

	/**
	 * The one rule for documents and the NRA file: did the shop collect the delivery on this order?
	 * A refund (credit note) follows its order.
	 *
	 * @param \WC_Order|\WC_Order_Refund|false $order Order or refund.
	 * @return bool
	 */
	public static function shop_collects( $order ) {
		$payer = self::payer( $order );

		if ( '' !== $payer ) {
			return 'customer' !== $payer;
		}

		// No decision on the order: the retired "Remove delivery from documents" rule, so documents and NRA files of these orders stay as they were.
		if ( is_a( $order, 'WC_Order_Refund' ) ) {
			$order = wc_get_order( $order->get_parent_id() );
		}

		if ( ! $order ) {
			return 'yes' !== nashenski_get_option( 'invoice', 'remove_shipping' );
		}

		if ( 'cod' !== $order->get_payment_method() ) {
			return true;
		}

		$methods = $order->get_items( 'shipping' );
		$method  = reset( $methods );

		return 'yes' !== nashenski_get_option( 'invoice', 'remove_shipping' )
			&& ! ( $method && 'yes' === nashenski_get_option( 'shippings', $method->get_method_id() . '_is_courier' ) );
	}

	/**
	 * "6,50 €, paid to the courier on delivery, not included in the total".
	 *
	 * @param mixed  $fee      Fee with VAT.
	 * @param string $currency Currency; the store's when empty.
	 * @return string HTML.
	 */
	public static function text( $fee, $currency = '' ) {
		/* translators: %s: delivery price, for example 6,50 € */
		return sprintf( __( '%s, paid to the courier on delivery, not included in the total', 'nashenski-commerce' ), wc_price( $fee, array( 'currency' => $currency ) ) );
	}

	/**
	 * The fee of an order the customer pays the courier for, or ''.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	private static function order_fee( $order ) {
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( 'customer' === $item->get_meta( self::PAYER_META ) ) {
				return (string) $item->get_meta( self::FEE_META );
			}
		}

		return '';
	}

	/**
	 * Emails, the thank-you page and My Account: "Econt: 6,50 €, paid to the courier on delivery, not included in the total".
	 *
	 * @param string    $shipping Shipping text.
	 * @param \WC_Order $order    Order.
	 * @return string
	 */
	public static function order_shipping( $shipping, $order ) {
		$fee = $order instanceof \WC_Order ? self::order_fee( $order ) : '';

		return '' === $fee ? $shipping : $shipping . ': ' . self::text( $fee, $order->get_currency() );
	}

	/**
	 * Order screen: the fee line of a shipping line reads "Delivery".
	 *
	 * @param string $key  Shown key.
	 * @param object $meta Meta.
	 * @return string
	 */
	public static function meta_key( $key, $meta ) {
		return self::FEE_META === ( $meta->key ?? '' ) ? __( 'Delivery', 'nashenski-commerce' ) : $key;
	}

	/**
	 * Order screen: the fee with its info text.
	 *
	 * @param string $value Shown value.
	 * @param object $meta  Meta.
	 * @param mixed  $item  Order item.
	 * @return string
	 */
	public static function meta_value( $value, $meta, $item = null ) {
		if ( self::FEE_META !== ( $meta->key ?? '' ) ) {
			return $value;
		}

		$order = is_object( $item ) && method_exists( $item, 'get_order' ) ? $item->get_order() : null;

		return self::text( $meta->value, $order ? $order->get_currency() : '' );
	}
}
