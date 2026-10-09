<?php
namespace Nashenski\Shipping\Speedy;
use Nashenski\Container\Client;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

class Address {
	private static $container = null;

	public function __construct() {

		add_action( 'wp_ajax_nashenski_speedy_load_streets', array( __CLASS__, 'load_streets' ) );
		add_action( 'wp_ajax_nopriv_nashenski_speedy_load_streets', array( __CLASS__, 'load_streets' ) );

		add_action( 'wp_ajax_nashenski_speedy_search_address', array( __CLASS__, 'search_address' ) );
		add_action( 'wp_ajax_nopriv_nashenski_speedy_search_address', array( __CLASS__, 'search_address' ) );
	}

	public static function search_address() {
		nashenski_check_picker_request();

		self::$container = nashenski()->container();
		$args = [];
		$country_id = self::$container[ Client::SPEEDY_COUNTRIES ]->get_country_id( nashenski_picker_request( 'country' ) );
		$query = self::normalize_api_query( nashenski_picker_request( 'query' ), (string) $country_id === '100' );

		$raw_city = nashenski_picker_request( 'city' );
		$raw_state = nashenski_picker_request( 'state' );
		$cities_data = self::$container[ Client::SPEEDY_CITIES ]->get_filtered_cities( $raw_city, $raw_state, $country_id );

		if ( in_array( $cities_data['city'], $cities_data['cities_only_names'] ) ) {
			$city_id = $cities_data['cities'][ $cities_data['city_key'] ][ 'id' ];

			$streets = [];
			$streets = nashenski_return_array_for_select( self::get_streets_for_query( $city_id, $query ), 0, array( 'type' => 'streets' ) );
			$quarters = nashenski_return_array_for_select( self::get_quarters_for_query( $city_id, $query ), 0, array( 'type' => 'quarters' ) );

			$args[ 'streets' ] = self::merge_options( $streets, $quarters );
			$args[ 'has_any' ] = !empty( $streets ) || !empty( $quarters );
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
		$raw_city = nashenski_picker_request( 'city' );
		$raw_state = nashenski_picker_request( 'state' );
		$country_id = self::$container[ Client::SPEEDY_COUNTRIES ]->get_country_id( nashenski_picker_request( 'country' ) );
		$cities_data = self::$container[ Client::SPEEDY_CITIES ]->get_filtered_cities( $raw_city, $raw_state, $country_id );

		if ( !in_array( $cities_data['city'], $cities_data['cities_only_names'] ) ) {
			$args[ 'cities' ] = nashenski_return_array_for_select( $cities_data['cities_only_names_dropdowns'], 1, array( 'type'=>'city' ) );
			$args[ 'status' ] = 'invalid-city';
		} else {
			$city_id = $cities_data['cities'][ $cities_data['city_key'] ][ 'id' ];

			$streets = nashenski_return_array_for_select( self::get_streets_for_query( $city_id, ' ' ), 1, array( 'type' => 'streets' ) );
			$quarters = nashenski_return_array_for_select( self::get_quarters_for_query( $city_id, ' ' ), 1, array( 'type' => 'quarters' ) );

			$args[ 'streets' ] = self::merge_options( $streets, $quarters );
			$args[ 'has_any' ] = !empty( $streets ) || !empty( $quarters );
			$args[ 'status' ] = 'valid-city';
		}

		wp_send_json_success( $args );

		wp_die();
	}

	public static function get_streets_for_query( $city_id, $query ) {
		if ( !self::$container ) {
			self::$container = nashenski()->container();
		}
		
		$streets = self::$container[ Client::SPEEDY_STREETS ]->get_streets_by_city( $city_id, $query );

		if ( !empty( $streets ) ) {
			$streets = self::$container[ Client::SPEEDY_STREETS ]->format_streets( $streets, $city_id, $query );
			$streets = self::rank_names( $streets, $query );
		} else {
			$streets = [];
		}

		return $streets;
	}

	public static function get_quarters_for_query( $city_id, $query  ) {
		if ( !self::$container ) {
			self::$container = nashenski()->container();
		}
		
		$quarters = self::$container[ Client::SPEEDY_QUARTERS ]->get_quarters_by_city( $city_id, $query );
		
		if ( !empty( $quarters ) ) {
			$quarters = self::$container[ Client::SPEEDY_QUARTERS ]->format_quarters( $quarters, $city_id, $query );
			$quarters = self::rank_names( $quarters, $query );
		} else {
			$quarters = [];
		}

		return $quarters;
	}

	public static function merge_options( $streets, $quarters ) {
		$test_streets = array_map( function( $street ) {
			return self::normalize_match_text( $street['label'] );
		}, $streets );

		foreach ( $quarters as $quarter ) {
			$test_label = self::normalize_match_text( $quarter['label'] );

			if ( in_array( $test_label, $test_streets ) ) {				
				$key = array_search( $test_label, $test_streets );
				$streets[ $key ] = $quarter;
			} else {
				$streets[] = $quarter;
			}
		}

		return $streets;
	}

	private static function normalize_api_query( $query, $transliterate ) {
		$query = nashenski_strip_street_prefix( trim( (string) $query ) );

		if ( $transliterate ) {
			$query = Transliteration::latin2cyrillic( $query );
		}

		return trim( preg_replace( '/[\s\p{Zs}]+/u', ' ', $query ) );
	}

	private static function normalize_match_text( $text ) {
		$text = nashenski_strip_street_prefix( trim( (string) $text ) );
		$text = mb_strtolower( $text );
		$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );

		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	private static function rank_names( $names, $query ) {
		$query = self::normalize_match_text( $query );

		if ( $query === '' ) {
			return $names;
		}

		$ranked = [];
		$order = 0;

		foreach ( $names as $key => $name ) {
			$normalized_name = self::normalize_match_text( $name );
			$position = mb_strpos( $normalized_name, $query );
			$rank = $normalized_name === $query ? 0 : ( $position === 0 ? 1 : ( $position !== false ? 2 : 3 ) );
			$ranked[] = [ 'key' => $key, 'name' => $name, 'rank' => $rank, 'order' => $order++ ];
		}

		usort( $ranked, function( $a, $b ) {
			return $a['rank'] === $b['rank'] ? $a['order'] <=> $b['order'] : $a['rank'] <=> $b['rank'];
		} );

		$sorted = [];
		foreach ( $ranked as $match ) {
			$sorted[ $match['key'] ] = $match['name'];
		}

		return $sorted;
	}
}
