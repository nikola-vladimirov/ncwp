<?php
namespace Nashenski\Front_End\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce block checkout support.
 *
 * - The office, locker and address pickers (Pickers, assets/js/pickers-block.js) write the same
 *   cookies as in the classic checkout, so the shipping methods price and save orders the same way.
 * - "nashenski" cart update: the pickers call extensionCartUpdate() after a choice; this clears
 *   the cached shipping rates so they are calculated again with the new choice.
 * - Order validation and order processing: the block checkout does not run the classic checkout
 *   hooks, so Nashenski's own handlers on them run here too.
 */
class Block_Checkout {
	const NAMESPACE_ID = 'nashenski';

	public function __construct() {
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_update_callback' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'validate' ), 5, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'order_processed' ) );
	}

	public static function register_update_callback() {
		if ( ! function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			return;
		}

		woocommerce_store_api_register_update_callback( array(
			'namespace' => self::NAMESPACE_ID,
			'callback' => array( __CLASS__, 'refresh_shipping' ),
		) );
	}

	/**
	 * Forget the calculated shipping rates; the Store API calculates them again for its response.
	 */
	public static function refresh_shipping() {
		if ( ! WC()->cart || ! WC()->session ) {
			return;
		}

		foreach ( array_keys( WC()->cart->get_shipping_packages() ) as $key ) {
			WC()->session->__unset( 'shipping_for_package_' . $key );
		}

		WC()->cart->calculate_shipping();
	}

	/**
	 * Nashenski callbacks registered on a classic checkout hook, in priority order.
	 */
	private static function own_callbacks( $hook ) {
		global $wp_filter;

		$callbacks = array();

		if ( empty( $wp_filter[ $hook ] ) ) {
			return $callbacks;
		}

		foreach ( $wp_filter[ $hook ]->callbacks as $priority => $items ) {
			foreach ( $items as $item ) {
				$function = $item['function'];
				$class = is_array( $function ) ? ( is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0] ) : '';

				if ( 0 === strpos( ltrim( $class, '\\' ), 'Nashenski\\' ) ) {
					$callbacks[] = $function;
				}
			}
		}

		return $callbacks;
	}

	/**
	 * Run Nashenski's classic checkout validation (courier choice, address details, ...).
	 *
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When something is missing.
	 */
	public static function validate( $order, $request ) {
		$errors = new \WP_Error();
		$data = array(
			'billing_country' => $order->get_billing_country(),
			'billing_phone' => $order->get_billing_phone(),
			'billing_email' => $order->get_billing_email(),
			'payment_method' => $order->get_payment_method(),
			'shipping_method' => WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods' ) : array(),
		);

		foreach ( self::own_callbacks( 'woocommerce_after_checkout_validation' ) as $callback ) {
			call_user_func( $callback, $data, $errors );
		}

		if ( $errors->has_errors() ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'nashenski_checkout_invalid',
				esc_html( wp_strip_all_tags( implode( ' ', $errors->get_error_messages() ) ) ),
				400
			);
		}
	}

	/**
	 * Run Nashenski's classic "order processed" handlers (save the courier choice, labels, documents).
	 */
	public static function order_processed( $order ) {
		foreach ( self::own_callbacks( 'woocommerce_checkout_order_processed' ) as $callback ) {
			call_user_func( $callback, $order->get_id(), array(), $order );
		}
	}
}
