<?php
namespace Nashenski\Shipping\Pigeon;
use Nashenski\Container\Client;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

class Office {
	/** The offices and lockers Pigeon Express publishes on its website (its location finder). */
	const PUBLIC_URL = 'https://pigeonexpress.com/api/locations';

	private static $container = null;
	
	public function __construct() {

		add_action( 'wp_ajax_nashenski_pigeon_load_offices', array( __CLASS__, 'load_offices' ) );
		add_action( 'wp_ajax_nopriv_nashenski_pigeon_load_offices', array( __CLASS__, 'load_offices' ) );
	}

	public static function load_offices() {
		nashenski_check_picker_request();

		// Without a Pigeon login (it has no public test login), the public list.
		if ( ! \Nashenski\Front_End\Checkout\Pickers::connected( 'pigeon' ) ) {
			wp_send_json_success( array(
				'offices' => apply_filters( 'nashenski/shipping_method/pigeon/offices', self::public_list( 'office', nashenski_picker_request( 'city' ) ) ),
				'status' => 'valid-city',
			) );
		}

		self::$container = nashenski()->container();
		$args = [];
		$raw_state = nashenski_picker_request( 'state' );
		$states = self::$container[ Client::PIGEON_CITIES ]->get_regions();
		$state = $states[ $raw_state ] ?? '';
		$raw_city = Transliteration::latin2cyrillic( nashenski_picker_request( 'city' ) );
		$cities_data = self::$container[ Client::PIGEON_CITIES ]->get_filtered_cities( $raw_city, $raw_state );

		if ( !in_array( $cities_data['city'], $cities_data['cities_only_names'] ) || !$raw_city ) {
			$args[ 'status' ] = 'invalid-city';
			
			if ( !$state ) {
				$args[ 'error' ] = __( 'Please select region.', 'nashenski-commerce' );
			} elseif ( empty( $raw_city ) ) {
				$args[ 'error' ] = sprintf( __( 'Please enter a city.', 'nashenski-commerce' ), $raw_city, $state );
			} else {
				/* translators: 1: town, 2: region */
				$args[ 'error' ] = sprintf( __( '%1$s is not found in %2$s region.', 'nashenski-commerce' ), $raw_city, $state );
			}
		} else {
			$offices = self::$container[ Client::PIGEON_OFFICES ]->get_offices( $cities_data['cities_search_names'][ $cities_data['city_key'] ]['id'] );

			if ( empty( $offices ) ) {
				$offices = [];
				/* translators: %s: town */
				$args[ 'error' ] = sprintf( __( 'No offices were found at %s.', 'nashenski-commerce' ), $raw_city );
			} else {
				$offices = $offices;
			}

			$args[ 'offices' ] = apply_filters( 'nashenski/shipping_method/pigeon/offices', $offices );
			$args[ 'status' ] = 'valid-city';
		}

		wp_send_json_success( $args );

		wp_die();
	}

	/**
	 * Offices or lockers ( $type office|locker ) in a town from Pigeon Express's public list,
	 * kept in the cache folder until the weekly clean-up.
	 */
	public static function public_list( $type, $city ) {
		$folder = nashenski()->container()[ Client::PIGEON ]::CACHE_FOLDER;
		$file = $folder . 'public-locations.json';
		$list = json_decode( (string) \Nashenski\File::get_file( $file ), true );

		// A recent failed fetch is not retried on every request.
		if ( ! $list && ! get_transient( 'nashenski_pigeon_public_failed' ) ) {
			$response = wp_remote_get( self::PUBLIC_URL, array( 'timeout' => 20, 'headers' => array( 'Accept' => 'application/json' ) ) );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$list = is_array( $body ) && ! empty( $body['locations'] ) ? $body['locations'] : array();

			if ( $list ) {
				wp_mkdir_p( $folder );
				\Nashenski\File::put_to_file( $file, wp_json_encode( $list ) );
			} else {
				set_transient( 'nashenski_pigeon_public_failed', 1, 5 * MINUTE_IN_SECONDS );
			}
		}

		$city = mb_strtolower( trim( nashenski_remove_city_prefix( Transliteration::latin2cyrillic( (string) $city ) ) ) );

		return array_values( array_filter( (array) $list, function ( $place ) use ( $type, $city ) {
			return $type === ( $place['type'] ?? '' ) && '' !== $city && mb_strtolower( trim( (string) ( $place['city'] ?? '' ) ) ) === $city;
		} ) );
	}
}
