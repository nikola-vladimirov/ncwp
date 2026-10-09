<?php
namespace Nashenski\Shipping\CVC;
use Nashenski\Container\Client;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

class Address {
	private static $container = null;

	public function __construct() {

		add_action( 'wp_ajax_nashenski_cvc_load_streets', array( __CLASS__, 'load_streets' ) );
		add_action( 'wp_ajax_nopriv_nashenski_cvc_load_streets', array( __CLASS__, 'load_streets' ) );

		add_action( 'wp_ajax_nashenski_cvc_search_address', array( __CLASS__, 'search_address' ) );
		add_action( 'wp_ajax_nopriv_nashenski_cvc_search_address', array( __CLASS__, 'search_address' ) );
	}

	public static function search_address() {
		nashenski_check_picker_request();

		self::$container = nashenski()->container();
		$args = [];
		$query = Transliteration::latin2cyrillic( nashenski_picker_request( 'query' ) );
		$country = nashenski_picker_request( 'country' );
		$country_id = self::$container[ Client::CVC_COUNTRIES ]->get_country_id( $country );
		$raw_city = nashenski_picker_request( 'city' );
		$state_id = self::$container[ Client::CVC_CITIES ]->get_state_id( nashenski_picker_request( 'state' ), $country_id );
		$cities_data = self::$container[ Client::CVC_CITIES ]->get_filtered_cities( $raw_city, $state_id, $country_id );

		if ( in_array( $cities_data['city'], $cities_data['cities_only_names'] ) ) {
			$city_id = $cities_data['cities'][ $cities_data['city_key'] ][ 'id' ];

			$streets = [];
			$streets = nashenski_return_array_for_select( self::get_streets_for_query( $city_id, $query ), 0, array( 'type' => 'streets' ) );
			$quarters = nashenski_return_array_for_select( self::get_quarters_for_query( $city_id, $query ), 0, array( 'type' => 'quarters' ) );

			$args[ 'streets' ] = self::merge_options( $streets, $quarters );
		} else {
			$args[ 'cities' ] = nashenski_return_array_for_select( $cities_data['cities_only_names'], 1, array( 'type' => 'city' ) );
		}

		wp_send_json_success( $args );

		wp_die();
	}

	public static function load_streets() {
		nashenski_check_picker_request();

		self::$container = nashenski()->container();
		$args = [];
		$country = nashenski_picker_request( 'country' );
		$country_id = self::$container[ Client::CVC_COUNTRIES ]->get_country_id( $country );
		$raw_city = nashenski_picker_request( 'city' );
		$state_id = self::$container[ Client::CVC_CITIES ]->get_state_id( nashenski_picker_request( 'state' ), $country_id );
		$cities_data = self::$container[ Client::CVC_CITIES ]->get_filtered_cities( $raw_city, $state_id, $country_id );

		if ( !in_array( $cities_data['city'], $cities_data['cities_only_names'] ) ) {
			$args[ 'cities' ] = nashenski_return_array_for_select( $cities_data['cities_only_names_dropdowns'], 1, array( 'type'=>'city' ) );
			$args[ 'status' ] = 'invalid-city';
		} else {
			$city_id = $cities_data['cities'][ $cities_data['city_key'] ][ 'id' ];

			$streets = nashenski_return_array_for_select( self::get_streets_for_query( $city_id, ' ' ), 1, array( 'type' => 'streets' ) );
			$quarters = nashenski_return_array_for_select( self::get_quarters_for_query( $city_id, ' ' ), 1, array( 'type' => 'quarters' ) );

			$args[ 'streets' ] = self::merge_options( $streets, $quarters );
			$args[ 'status' ] = 'valid-city';
		}

		wp_send_json_success( $args );

		wp_die();
	}

	public static function get_streets_for_query( $city_id, $query ) {
		if ( !self::$container ) {
			self::$container = nashenski()->container();
		}
		
		$streets = self::$container[ Client::CVC_STREETS ]->get_streets_by_city( $city_id, $query );

		if ( !empty( $streets['streets'] ) ) {
			$streets = self::$container[ Client::CVC_STREETS ]->format_streets( $streets['streets'] );
		}

		return $streets;
	}

	public static function get_quarters_for_query( $city_id, $query ) {
		if ( !self::$container ) {
			self::$container = nashenski()->container();
		}
		
		$quarters = self::$container[ Client::CVC_QUARTERS ]->get_quarters_by_city( $city_id, $query );
		
		if ( !empty( $quarters['qts'] ) ) {
			$quarters = self::$container[ Client::CVC_QUARTERS ]->format_quarters( $quarters['qts'] );
		}

		return $quarters;
	}

	public static function merge_options( $streets, $quarters ) {
		$test_streets = array_map( function( $street ) {
  			return mb_strtolower( str_replace( ' ', '', $street['label'] ) );
		}, $streets );

		foreach ( $quarters as $quarter ) {
			$test_label = mb_strtolower( str_replace( ' ', '', $quarter['label'] ) );

			if ( in_array( $test_label, $test_streets ) ) {				
				$key = array_search( $test_label, $test_streets );
				$streets[ $key ] = $quarter;
			} else {
				$streets[] = $quarter;
			}
		}

		return $streets;
	}
}
