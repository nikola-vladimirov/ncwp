<?php
/**
 * Sends the tracking code of a new Econt or Speedy shipment to NepoStop.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the tracking code of a new Econt or Speedy shipment to NepoStop.
 */
class NepoStop {
	/**
	 * Hooks the label events of the couriers.
	 */
	public function __construct() {
		add_action( 'nashenski/econt/after_send_label', array( __CLASS__, 'send_econt_tracking_code' ), 20, 2 );
		add_action( 'nashenski/speedy/after_send_label', array( __CLASS__, 'send_speedy_tracking_code' ), 20, 2 );
	}

	/**
	 * Sends one tracking code and the shopper phone to NepoStop.
	 *
	 * @param string $tracking_code Courier tracking code.
	 * @param string $phone         Shopper phone number.
	 *
	 * @return void
	 */
	public static function send_tracking_code( $tracking_code, $phone ) {
		$container = nashenski()->container();

		$container[ Client::NEPOSTOP ]->api_call(
			$container[ Client::NEPOSTOP ]::ADD_SHIPMENT_ENDPOINT,
			array(
				'shipping_number' => $tracking_code,
				'phone_number'    => nashenski_format_phone( $phone ),
			)
		);
	}

	/**
	 * Sends the Econt tracking code after a label is created.
	 *
	 * @param array    $data  Econt label response.
	 * @param WC_Order $order Order.
	 *
	 * @return void
	 */
	public static function send_econt_tracking_code( $data, $order ) {
		if ( empty( $data['label']['shipmentNumber'] ) ) {
			return;
		}

		$tracking_code = $data['label']['shipmentNumber'];
		$phone         = ( $order->get_shipping_phone() ) ? $order->get_shipping_phone() : $order->get_billing_phone();

		if ( ! empty( $tracking_code ) && ! empty( $phone ) ) {
			self::send_tracking_code( $tracking_code, $phone );
		}
	}

	/**
	 * Sends the Speedy tracking code after a label is created.
	 *
	 * @param array    $data  Speedy shipment response.
	 * @param WC_Order $order Order.
	 *
	 * @return void
	 */
	public static function send_speedy_tracking_code( $data, $order ) {
		if ( empty( $data['shipmentStatus']['id'] ) ) {
			return;
		}

		$tracking_code = $data['shipmentStatus']['id'];
		$phone         = ( $order->get_shipping_phone() ) ? $order->get_shipping_phone() : $order->get_billing_phone();

		if ( ! empty( $tracking_code ) && ! empty( $phone ) ) {
			self::send_tracking_code( $tracking_code, $phone );
		}
	}
}
