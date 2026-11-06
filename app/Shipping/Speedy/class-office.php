<?php
/**
 * Office.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Speedy;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Office.
 */
class Office {
	/**
	 * Container.
	 *
	 * @var mixed
	 */
	private static $container = null;

	/**
	 * Constructor.
	 */
	public function __construct() {

		add_action( 'wp_ajax_nashenski_speedy_load_offices', array( __CLASS__, 'load_offices' ) );
		add_action( 'wp_ajax_nopriv_nashenski_speedy_load_offices', array( __CLASS__, 'load_offices' ) );
	}

	/**
	 * Load offices.
	 *
	 * @return mixed
	 */
	public static function load_offices() {
		nashenski_check_picker_request();

		self::$container = nashenski()->container();
		$args            = array();
		$raw_state       = nashenski_picker_request( 'state' );
		$raw_city        = nashenski_picker_request( 'city' );
		$delivery_type   = nashenski_picker_request( 'delivery_type', 'office' );
		$country_id      = self::$container[ Client::SPEEDY_COUNTRIES ]->get_country_id( nashenski_picker_request( 'country' ) );
		$states          = self::$container[ Client::SPEEDY_CITIES ]->get_regions( $country_id );
		$state           = $states[ $raw_state ] ?? '';
		$cities_data     = self::$container[ Client::SPEEDY_CITIES ]->get_filtered_cities( $raw_city, $raw_state, $country_id );
		if ( 'automat' === $delivery_type ) {
			/* translators: %s: town */
			$not_found_message = __( 'No automats were found at %s.', 'nashenski-commerce' );
		} else {
			/* translators: %s: town */
			$not_found_message = __( 'No offices were found at %s.', 'nashenski-commerce' );
		}

		if ( ( '300' !== $country_id && ! in_array( $cities_data['city'], $cities_data['cities_only_names'], true ) ) || ! $raw_city ) {
			$args['status'] = 'invalid-city';

			if ( ! $state ) {
				$args['error'] = __( 'Please select region.', 'nashenski-commerce' );
			} elseif ( empty( $raw_city ) ) {
				$args['error'] = sprintf( __( 'Please enter a city.', 'nashenski-commerce' ), $raw_city, $state );
			} else {
				/* translators: %1$s is the city and %2$s is the state */
				$args['error'] = sprintf( __( '%1$s is not found in %2$s region.', 'nashenski-commerce' ), $raw_city, $state );
			}
		} else {
			$city_id = $cities_data['cities'][ $cities_data['city_key'] ]['id'];

			if ( '300' === $country_id ) {
				$city_id = 0;
			}

			$offices = self::$container[ Client::SPEEDY_OFFICES ]->get_offices( $city_id, $country_id );

			if ( empty( $offices ) ) {
				$offices = array();
				/* translators: %s is the city */
				$args['error'] = sprintf( $not_found_message, $raw_city );
			} else {
				$offices = $offices['offices'];

				if ( 'automat' === $delivery_type ) {
					$offices = array_values(
						array_filter(
							$offices,
							function ( $office ) {
								return ( 'APT' === $office['type'] );
							}
						)
					);
				} elseif ( 'office' === $delivery_type ) {
					$offices = array_values(
						array_filter(
							$offices,
							function ( $office ) {
								return ( 'APT' !== $office['type'] );
							}
						)
					);
				}

				if ( empty( $offices ) ) {
					/* translators: %s is the city */
					$args['error'] = sprintf( $not_found_message, $raw_city );
				}
			}

			$args['offices'] = \Nashenski\Hooks::filter( 'nashenski_shipping_method_speedy_offices', 'nashenski/shipping_method/speedy/offices', $offices );
			$args['status']  = 'valid-city';
			$args['cityId']  = $cities_data['cities'][ $cities_data['city_key'] ]['id'];
		}

		wp_send_json_success( $args );

		wp_die();
	}
}
