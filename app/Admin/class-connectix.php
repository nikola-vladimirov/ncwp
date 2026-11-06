<?php
/**
 * Connectix tracking codes.
 *
 * @package Nashenski
 */

namespace Nashenski\Admin;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the waybill numbers of new Econt and Speedy labels to Connectix.
 */
class Connectix {
	/**
	 * Hook the label actions.
	 */
	public function __construct() {
		add_action( 'nashenski_econt_after_send_label', array( __CLASS__, 'send_econt_tracking_code' ), 20, 2 );
		add_action( 'nashenski_speedy_after_send_label', array( __CLASS__, 'send_speedy_tracking_code' ), 20, 2 );
	}

	/**
	 * Send a waybill number to Connectix, when it has an integration token.
	 *
	 * @param string $tracking_code Waybill number.
	 */
	public static function send_tracking_code( $tracking_code ) {
		$container         = nashenski()->container();
		$integration_token = $container[ Client::CONNECTIX ]->get_integration_token();

		if ( $integration_token ) {
			$endpoint = add_query_arg( 'integration', $integration_token, $container[ Client::CONNECTIX ]::TRACKING_ENDPOINT );

			$container[ Client::CONNECTIX ]->api_call( $endpoint, array( 'trackingNumber' => $tracking_code ) );
		}
	}

	/**
	 * Send the waybill number of a new Econt label.
	 *
	 * @param array     $data  Label data.
	 * @param \WC_Order $order Order.
	 */
	public static function send_econt_tracking_code( $data, $order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- hook callback signature.
		$tracking_code = $data['label']['shipmentNumber'];

		if ( ! empty( $tracking_code ) ) {
			self::send_tracking_code( $tracking_code );
		}
	}

	/**
	 * Send the waybill number of a new Speedy label.
	 *
	 * @param array     $data  Label data.
	 * @param \WC_Order $order Order.
	 */
	public static function send_speedy_tracking_code( $data, $order ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- hook callback signature.
		$tracking_code = $data['shipmentStatus']['id'];

		if ( ! empty( $tracking_code ) ) {
			self::send_tracking_code( $tracking_code );
		}
	}
}
