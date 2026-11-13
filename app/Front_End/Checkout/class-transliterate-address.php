<?php
/**
 * Address transliteration.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Front_End\Checkout;

use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

/**
 * Transliterates the customer's addresses when an order is placed (classic and block checkout)
 * and from the order screen ("Transliterate addresses" order action).
 *
 * Modes (Nashenski > Shipping > Transliteration):
 * - cyrillic: Bulgarian addresses typed in Latin become Cyrillic.
 * - auto:     Bulgarian addresses become Cyrillic, addresses in other countries become Latin.
 */
class Transliterate_Address {
	const ORDER_ACTION = 'nashenski_transliterate_addresses';

	/**
	 * Registers the hooks.
	 */
	public function __construct() {
		if ( in_array( self::mode(), array( 'cyrillic', 'auto' ), true ) ) {
			add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'apply' ), 20 );
			add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( __CLASS__, 'apply' ), 20 );
		}

		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'add_order_action' ) );
		add_action( 'woocommerce_order_action_' . self::ORDER_ACTION, array( __CLASS__, 'run_order_action' ) );
	}

	/**
	 * Transliteration mode from the settings.
	 *
	 * @return string off, cyrillic or auto.
	 */
	public static function mode() {
		$mode = nashenski_get_option( 'checkout', 'transliteration' );

		return $mode ? $mode : 'off';
	}

	/**
	 * Address fields to transliterate.
	 *
	 * @return array
	 */
	private static function fields() {
		// Not the company: the invoice prints its name as the customer typed it.
		$fields = array( 'address_1', 'address_2', 'city' );

		if ( 'yes' === nashenski_get_option( 'checkout', 'transliterate_names' ) ) {
			$fields = array_merge( array( 'first_name', 'last_name' ), $fields );
		}

		return \Nashenski\Hooks::filter( 'nashenski_transliteration_fields', 'nashenski/transliteration/fields', $fields );
	}

	/**
	 * Transliterates the billing and shipping address of an order.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $mode  Mode to use; the setting when empty.
	 * @return bool Whether anything changed.
	 */
	public static function apply( $order, $mode = '' ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		$mode    = $mode ? $mode : self::mode();
		$changed = false;

		foreach ( array( 'billing', 'shipping' ) as $type ) {
			$country  = $order->{"get_{$type}_country"}();
			$to_latin = 'auto' === $mode && $country && 'BG' !== $country;

			foreach ( self::fields() as $field ) {
				$getter = "get_{$type}_{$field}";
				$setter = "set_{$type}_{$field}";

				if ( ! is_callable( array( $order, $getter ) ) ) {
					continue;
				}

				$value = (string) $order->$getter();

				if ( $to_latin && Transliteration::has_cyrillic( $value ) ) {
					$new = Transliteration::to_latin( $value );
				} elseif ( ! $to_latin && Transliteration::has_latin( $value ) && ! Transliteration::has_cyrillic( $value ) ) {
					$new = Transliteration::to_cyrillic( $value );
				} else {
					continue;
				}

				if ( $new !== $value ) {
					$order->$setter( $new );
					$changed = true;
				}
			}
		}

		return $changed;
	}

	/**
	 * Adds the order action.
	 *
	 * @param array $actions Order actions.
	 * @return array
	 */
	public static function add_order_action( $actions ) {
		$actions[ self::ORDER_ACTION ] = __( 'Transliterate addresses (Cyrillic in Bulgaria, Latin abroad)', 'nashenski-commerce' );

		return $actions;
	}

	/**
	 * Runs the order action.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function run_order_action( $order ) {
		if ( self::apply( $order, 'auto' ) ) {
			$order->add_order_note( __( 'Addresses transliterated.', 'nashenski-commerce' ), false, true );
			$order->save();
		}
	}
}
