<?php
namespace Nashenski\Shipping\Econt;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/** Shopper display flags cannot waive merchant-configured shipment services. */
class Service_Policy {
	const OVERRIDES = '_nashenski_econt_service_overrides';
	private static $fields = array( 'declaredValueAmount', 'declaredValueCurrency', 'payAfterAccept', 'payAfterTest' );

	/** Only server-side filter differences populate this session/order-only record. */
	public static function capture_overrides( $before, $after, $overrides = array() ) {
		foreach ( self::$fields as $field ) {
			$old = strpos( $field, 'declaredValue' ) === 0 ? ( $before['services'] ?? array() ) : $before;
			$new = strpos( $field, 'declaredValue' ) === 0 ? ( $after['services'] ?? array() ) : $after;
			$old_present = array_key_exists( $field, $old ); $new_present = array_key_exists( $field, $new );
			if ( $old_present !== $new_present || ( $old_present && $old[ $field ] !== $new[ $field ] ) ) {
				$overrides[ $field ] = array( 'present' => $new_present, 'value' => $new[ $field ] ?? null );
			}
		}
		return $overrides;
	}

	private static function apply_overrides( $label, $overrides ) {
		foreach ( self::$fields as $field ) {
			if ( ! isset( $overrides[ $field ] ) ) { continue; }
			if ( strpos( $field, 'declaredValue' ) === 0 ) {
				if ( $overrides[ $field ]['present'] ) { $label['services'][ $field ] = $overrides[ $field ]['value']; }
				else { unset( $label['services'][ $field ] ); }
			} else {
				if ( $overrides[ $field ]['present'] ) { $label[ $field ] = $overrides[ $field ]['value']; }
				else { unset( $label[ $field ] ); }
			}
		}
		return $label;
	}
	private static function office_code( $value ) {
		return is_string( $value ) || is_int( $value ) ? preg_replace( '/^officeID-/', '', (string) $value ) : '';
	}

	/**
	 * The catalogue decides. The shopper's own APS flag is never used: without a readable catalogue no service is waived.
	 */
	public static function recipient_is_aps( $container, $office, $destination ) {
		$code = self::office_code( $office );
		if ( $code === '' ) {
			return false;
		}
		$country = $destination['country'] ?? 'BG';
		$state = $destination['state'] ?? '';
		// A destination the shopper can make malformed never waives a service.
		if ( empty( $destination['city'] ) || ! is_string( $destination['city'] ) || ! is_string( $country ) || ! is_string( $state ) ) {
			return false;
		}
		if ( ! isset( $container[ Client::ECONT_CITIES ], $container[ Client::ECONT_OFFICES ] ) ) {
			return false;
		}
		$regions = $container[ Client::ECONT_CITIES ]->get_regions( $country );
		$state = $regions[ $state ] ?? $state;
		$cities = $container[ Client::ECONT_CITIES ]->get_filtered_cities( $destination['city'], $state, $country );
		$key = $cities['city_key'] ?? false;
		if ( $key === false || ! isset( $cities['cities'][ $key ]['id'] ) ) {
			return false;
		}
		$offices = $container[ Client::ECONT_OFFICES ]->get_offices( $cities['cities'][ $key ]['id'], $country );
		foreach ( $offices['offices'] ?? array() as $record ) {
			if ( isset( $record['code'] ) && ( is_string( $record['code'] ) || is_int( $record['code'] ) ) && (string) $record['code'] === $code ) {
				return is_scalar( $record['isAPS'] ?? false ) && wc_string_to_bool( $record['isAPS'] ?? false );
			}
		}
		// A readable catalogue without this office never authorizes an exemption.
		return false;
	}

	private static function sender_is_aps( $container, $label ) {
		$code = self::office_code( $label['senderOfficeCode'] ?? '' );
		if ( $code === '' || ! isset( $container[ Client::ECONT_OFFICES ] ) ) {
			return false;
		}
		$offices = $container[ Client::ECONT_OFFICES ]->get_formatted_offices( nashenski_get_option( 'econt_send_from', 'office_city' ) );
		return isset( $offices['aps'][ 'officeID-' . $code ] );
	}

	/** Repair old prepared labels using order data, without requiring a live cart. */
	public static function for_order( $label, $order, $container, $shipping = null, $overrides = array() ) {
		$destination = array(
			'country' => $order->get_shipping_country() ?: $order->get_billing_country(),
			'state' => $order->get_shipping_state() ?: $order->get_billing_state(),
			'city' => $order->get_shipping_city() ?: $order->get_billing_city(),
		);
		if ( null === $shipping ) {
			foreach ( $order->get_items( 'shipping' ) as $item ) {
				if ( $item->get_method_id() === Method::METHOD_ID ) { $shipping = $item; break; }
			}
		}
		$is_aps = self::recipient_is_aps( $container, $label['receiverOfficeCode'] ?? '', $destination );
		if ( $is_aps || self::sender_is_aps( $container, $label ) ) {
			unset( $label['services']['declaredValueAmount'], $label['services']['declaredValueCurrency'] );
		} elseif ( 'cod' === $order->get_payment_method() && wc_string_to_bool( nashenski_get_option( 'econt', 'declared_value' ) ) ) {
			if ( ! isset( $label['services']['declaredValueAmount'] ) ) {
				$amount = 0;
				$types = apply_filters( 'nashenski/shipping/package_total_includes_fees', true ) ? array( 'line_item', 'fee' ) : array( 'line_item' );
				foreach ( $order->get_items( $types ) as $item ) {
					$amount += (float) $item->get_total() + (float) $item->get_total_tax();
				}
				$label['services']['declaredValueAmount'] = wc_format_decimal( $amount, wc_get_price_decimals() );
			}
			if ( ! isset( $label['services']['declaredValueCurrency'] ) ) {
				$label['services']['declaredValueCurrency'] = $order->get_currency();
			}
		}
		if ( $is_aps ) {
			unset( $label['payAfterAccept'], $label['payAfterTest'] );
			return self::apply_overrides( $label, $overrides );
		}
		if ( $shipping ) {
			$settings = get_option( 'woocommerce_nashenski_econt_' . $shipping->get_instance_id() . '_settings', array() );
			$test = $settings['test'] ?? 'no';
			// Keep explicit staff/filter choices; restore fields omitted by an old cookie.
			if ( in_array( $test, array( 'review', 'test' ), true ) && ! array_key_exists( 'payAfterAccept', $label ) ) {
				$label['payAfterAccept'] = true;
			}
			if ( $test === 'test' && ! array_key_exists( 'payAfterTest', $label ) ) {
				$label['payAfterTest'] = true;
			}
		}
		return self::apply_overrides( $label, $overrides );
	}
}
