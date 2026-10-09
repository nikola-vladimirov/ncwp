<?php
namespace Nashenski\Shipping\Pigeon;
use Nashenski\Container\Client;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

class Address {
	private static $container = null;

	public function __construct() {

		add_action( 'wp_ajax_nashenski_pigeon_load_streets', array( __CLASS__, 'load_streets' ) );
		add_action( 'wp_ajax_nopriv_nashenski_pigeon_load_streets', array( __CLASS__, 'load_streets' ) );

		add_action( 'wp_ajax_nashenski_pigeon_search_address', array( __CLASS__, 'search_address' ) );
		add_action( 'wp_ajax_nopriv_nashenski_pigeon_search_address', array( __CLASS__, 'search_address' ) );
	}

	public static function search_address() {
		nashenski_check_picker_request();

		self::$container = nashenski()->container();
		$args = [];
		$query = self::normalize_api_query( nashenski_picker_request( 'query' ) );

		$raw_city = Transliteration::latin2cyrillic( nashenski_picker_request( 'city' ) );
		$raw_state = nashenski_picker_request( 'state' );
		$cities_data = self::$container[ Client::PIGEON_CITIES ]->get_filtered_cities( $raw_city, $raw_state );

		if ( in_array( $cities_data['city'], $cities_data['cities_only_names'] ) ) {
			$city_id = $cities_data['cities_search_names'][ $cities_data['city_key'] ][ 'id' ];
			$args[ 'streets' ] = nashenski_return_array_for_select( self::get_streets_for_query( $city_id, $query ), 0, array( 'type' => 'streets' ) );
			$args[ 'has_any' ] = !empty( self::get_streets_for_query( $city_id, '' ) );
			$args[ 'status' ] = 'valid-city';
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

		$raw_city = Transliteration::latin2cyrillic( nashenski_picker_request( 'city' ) );
		$raw_state = nashenski_picker_request( 'state' );
		$cities_data = self::$container[ Client::PIGEON_CITIES ]->get_filtered_cities( $raw_city, $raw_state) ;

		if ( !in_array( $cities_data['city'], $cities_data['cities_only_names'] ) ) {
			$args[ 'cities' ] = nashenski_return_array_for_select( $cities_data['cities_only_names_dropdowns'], 1, array( 'type'=>'city' ) );
			$args[ 'status' ] = 'invalid-city';
		} else {
			$city_id = $cities_data['cities_search_names'][ $cities_data['city_key'] ][ 'id' ];

			$args[ 'streets' ] = nashenski_return_array_for_select( self::get_streets_for_query( $city_id, '' ), 1, array( 'type' => 'streets' ) );
			$args[ 'has_any' ] = !empty( self::get_streets_for_query( $city_id, '' ) );
			$args[ 'status' ] = 'valid-city';
		}

		wp_send_json_success( $args );

		wp_die();
	}

	public static function get_streets_for_query( $city_id, $query ) {
		if ( !self::$container ) {
			self::$container = nashenski()->container();
		}
		
		$streets = self::$container[ Client::PIGEON_STREETS ]->get_streets_by_city( $city_id, $query );

		if ( !empty( $streets ) ) {
			$streets = self::$container[ Client::PIGEON_STREETS ]->format_streets( $streets );
			$streets = self::rank_names( $streets, $query );
		} else {
			$streets = [];
		}

		return $streets;
	}

	private static function normalize_api_query( $query ) {
		$query = nashenski_strip_street_prefix( trim( (string) $query ) );
		$query = Transliteration::latin2cyrillic( $query );

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

		$tokens = explode( ' ', $query );
		$ranked = [];
		$order = 0;

		foreach ( $names as $key => $name ) {
			$normalized_name = self::normalize_match_text( $name );
			$position = mb_strpos( $normalized_name, $query );
			$all_tokens_match = true;

			foreach ( $tokens as $token ) {
				if ( mb_strpos( $normalized_name, $token ) === false ) {
					$all_tokens_match = false;
					break;
				}
			}

			$rank = $normalized_name === $query ? 0 : ( $position === 0 ? 1 : ( $position !== false ? 2 : ( $all_tokens_match ? 3 : 4 ) ) );
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
