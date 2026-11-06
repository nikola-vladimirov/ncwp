<?php
namespace Nashenski\Shipping\Econt;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

class Office {
	private static $container = null;

	public function __construct() {

		add_action( 'wp_ajax_nashenski_econt_load_offices', array( __CLASS__, 'load_offices' ) );
		add_action( 'wp_ajax_nopriv_nashenski_econt_load_offices', array( __CLASS__, 'load_offices' ) );
	}

	public static function load_offices() {
		nashenski_check_picker_request();

		self::$container = nashenski()->container();
		$args            = array();
		$delivery_type   = nashenski_picker_request( 'delivery_type', 'office' );
		$raw_state       = nashenski_picker_request( 'state' );
		$country         = nashenski_picker_request( 'country' );
		$states          = self::$container[ Client::ECONT_CITIES ]->get_regions( $country );
		$state           = $states[ $raw_state ] ?? '';
		$raw_city        = nashenski_picker_request( 'city' );
		$cities_data     = self::$container[ Client::ECONT_CITIES ]->get_filtered_cities( $raw_city, $state, $country );
		if ( 'automat' === $delivery_type ) {
			/* translators: %s: town */
			$not_found_message = __( 'No automats were found at %s.', 'nashenski-commerce' );
		} else {
			/* translators: %s: town */
			$not_found_message = __( 'No offices were found at %s.', 'nashenski-commerce' );
		}

		if ( ! in_array( $cities_data['city'], $cities_data['cities_only_names'] ) || ! $raw_city ) {
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
			$offices = self::$container[ Client::ECONT_OFFICES ]->get_offices( $cities_data['cities'][ $cities_data['city_key'] ]['id'], $country );

			if ( empty( $offices ) ) {
				$offices       = array();
				$args['error'] = sprintf( $not_found_message, $raw_city );
			} else {
				$offices = $offices['offices'];

				if ( $delivery_type === 'automat' ) {
					$offices = array_values(
						array_filter(
							$offices,
							function ( $office ) {
								return ( $office['isAPS'] === true );
							}
						)
					);
				} elseif ( $delivery_type === 'office' ) {
					$offices = array_values(
						array_filter(
							$offices,
							function ( $office ) {
								return ( $office['isAPS'] === false );
							}
						)
					);
				}

				if ( empty( $offices ) ) {
					$args['error'] = sprintf( $not_found_message, $raw_city );
				}
			}

			$args['offices'] = apply_filters( 'nashenski/shipping_method/econt/offices', $offices );
			$args['status']  = 'valid-city';
		}

		wp_send_json_success( $args );

		wp_die();
	}
}
