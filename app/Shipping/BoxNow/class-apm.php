<?php
/**
 * BOX NOW parcel locker list for the checkout.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\BoxNow;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the BOX NOW parcel lockers to the checkout picker.
 */
class Apm {
	/**
	 * Plugin service container.
	 *
	 * @var \Pimple\Container|null
	 */
	private static $container = null;

	/**
	 * Hooks the AJAX action.
	 */
	public function __construct() {

		add_action( 'wp_ajax_nashenski_boxnow_load_apms', array( __CLASS__, 'load_apms' ) );
		add_action( 'wp_ajax_nopriv_nashenski_boxnow_load_apms', array( __CLASS__, 'load_apms' ) );
	}

	/**
	 * AJAX: parcel lockers of the chosen country that fit the cart.
	 */
	public static function load_apms() {
		// The checkout pickers send their own nonce, the order screen sends the label one.
		$nonce    = isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '';
		$verified = false;

		foreach ( array( 'nashenski_pickers', 'nashenski_admin_label' ) as $action ) {
			if ( wp_verify_nonce( $nonce, $action ) ) {
				$verified = true;
				break;
			}
		}

		if ( ! $verified ) {
			wp_send_json_error( array( 'error' => __( 'This page has expired. Reload it and try again.', 'nashenski-commerce' ) ), 403 );
		}

		self::$container = nashenski()->container();
		$apm_size        = Method::get_allowed_apm_size();
		$apms            = self::$container[ Client::BOXNOW_DESTINATIONS ]->get_destinations( $apm_size['max_size'] );
		$country         = nashenski_picker_request( 'country', '' );

		// No list when BOX NOW is not connected or did not answer.
		if ( ! is_array( $apms ) ) {
			$apms = array();
		}

		$apms = array_filter(
			$apms,
			function ( $apm ) use ( $country ) {
				return ( $apm['country'] === $country );
			}
		);

		$apms = array_values( $apms );

		$args['apms'] = \Nashenski\Hooks::filter( 'nashenski_shipping_method_boxnow_apms', 'nashenski/shipping_method/boxnow/apms', $apms );

		wp_send_json_success( $args );

		wp_die();
	}
}
