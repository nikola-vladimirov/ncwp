<?php
/**
 * Loads the Pigeon lockers of a city for the checkout picker.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Pigeon;

use Nashenski\Container\Client;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the Pigeon lockers of a city for the checkout picker.
 */
class Locker {
	/**
	 * Dependency container.
	 *
	 * @var object|null
	 */
	private static $container = null;

	/**
	 * Hooks the locker request for guests and logged-in shoppers.
	 */
	public function __construct() {

		add_action( 'wp_ajax_nashenski_pigeon_load_lockers', array( __CLASS__, 'load_lockers' ) );
		add_action( 'wp_ajax_nopriv_nashenski_pigeon_load_lockers', array( __CLASS__, 'load_lockers' ) );
	}

	/**
	 * Answers the picker request with the lockers of the city, or the reason there are none.
	 *
	 * @return void
	 */
	public static function load_lockers() {
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

		// Without a Pigeon login, the public list.
		if ( ! \Nashenski\Front_End\Checkout\Pickers::connected( 'pigeon' ) ) {
			wp_send_json_success(
				array(
					'lockers' => \Nashenski\Hooks::filter( 'nashenski_shipping_method_pigeon_lockers', 'nashenski/shipping_method/pigeon/lockers', Office::public_list( 'locker', nashenski_picker_request( 'city' ) ) ),
					'status'  => 'valid-city',
				)
			);
		}

		self::$container = nashenski()->container();
		$args            = array();
		$raw_state       = nashenski_picker_request( 'state' );
		$states          = self::$container[ Client::PIGEON_CITIES ]->get_regions();
		$state           = $states[ $raw_state ] ?? '';
		$raw_city        = Transliteration::latin2cyrillic( nashenski_picker_request( 'city' ) );
		$cities_data     = self::$container[ Client::PIGEON_CITIES ]->get_filtered_cities( $raw_city, $raw_state );

		if ( ! in_array( $cities_data['city'], $cities_data['cities_only_names'], true ) || ! $raw_city ) {
			$args['status'] = 'invalid-city';

			if ( ! $state ) {
				$args['error'] = __( 'Please select region.', 'nashenski-commerce' );
			} elseif ( empty( $raw_city ) ) {
				$args['error'] = sprintf( __( 'Please enter a city.', 'nashenski-commerce' ), $raw_city, $state );
			} else {
				/* translators: 1: town, 2: region */
				$args['error'] = sprintf( __( '%1$s is not found in %2$s region.', 'nashenski-commerce' ), $raw_city, $state );
			}
		} else {
			$lockers = self::$container[ Client::PIGEON_LOCKERS ]->get_lockers( $cities_data['cities_search_names'][ $cities_data['city_key'] ]['id'] );

			if ( empty( $lockers ) ) {
				$lockers = array();
				/* translators: %s: town */
				$args['error'] = sprintf( __( 'No lockers were found at %s.', 'nashenski-commerce' ), $raw_city );
			} else {
				$lockers = $lockers;
			}

			$args['lockers'] = \Nashenski\Hooks::filter( 'nashenski_shipping_method_pigeon_lockers', 'nashenski/shipping_method/pigeon/lockers', $lockers );
			$args['status']  = 'valid-city';
		}

		wp_send_json_success( $args );

		wp_die();
	}
}
