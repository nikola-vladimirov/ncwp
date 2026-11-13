<?php
/**
 * Speedy address picker.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Speedy;

use Nashenski\Container\Client;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

/**
 * AJAX search of the Speedy streets and quarters of a town.
 */
class Address {
	/**
	 * Plugin container.
	 *
	 * @var mixed
	 */
	private static $container = null;

	/**
	 * Registers the AJAX hooks.
	 */
	public function __construct() {

		add_action( 'wp_ajax_nashenski_speedy_load_streets', array( __CLASS__, 'load_streets' ) );
		add_action( 'wp_ajax_nopriv_nashenski_speedy_load_streets', array( __CLASS__, 'load_streets' ) );

		add_action( 'wp_ajax_nashenski_speedy_search_address', array( __CLASS__, 'search_address' ) );
		add_action( 'wp_ajax_nopriv_nashenski_speedy_search_address', array( __CLASS__, 'search_address' ) );
	}

	/**
	 * AJAX: streets and quarters that match the typed text, or the towns when the town is unknown.
	 */
	public static function search_address() {
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
		$args            = array();
		$country_id      = self::$container[ Client::SPEEDY_COUNTRIES ]->get_country_id( nashenski_picker_request( 'country' ) );
		$query           = self::normalize_api_query( nashenski_picker_request( 'query' ), '100' === (string) $country_id );

		$raw_city    = nashenski_picker_request( 'city' );
		$raw_state   = nashenski_picker_request( 'state' );
		$cities_data = self::$container[ Client::SPEEDY_CITIES ]->get_filtered_cities( $raw_city, $raw_state, $country_id );

		if ( in_array( $cities_data['city'], $cities_data['cities_only_names'], true ) ) {
			$city_id = $cities_data['cities'][ $cities_data['city_key'] ]['id'];

			$streets  = array();
			$streets  = nashenski_return_array_for_select( self::get_streets_for_query( $city_id, $query ), 0, array( 'type' => 'streets' ) );
			$quarters = nashenski_return_array_for_select( self::get_quarters_for_query( $city_id, $query ), 0, array( 'type' => 'quarters' ) );

			$args['streets'] = self::merge_options( $streets, $quarters );
			$args['has_any'] = ! empty( $streets ) || ! empty( $quarters );
		} else {
			$args['cities'] = nashenski_return_array_for_select( $cities_data['cities_only_names'], 1, array( 'type' => 'city' ) );
		}

		wp_send_json_success( $args );
		wp_die();
	}

	/**
	 * AJAX: all streets and quarters of the town.
	 */
	public static function load_streets() {
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
		$args            = array();
		$raw_city        = nashenski_picker_request( 'city' );
		$raw_state       = nashenski_picker_request( 'state' );
		$country_id      = self::$container[ Client::SPEEDY_COUNTRIES ]->get_country_id( nashenski_picker_request( 'country' ) );
		$cities_data     = self::$container[ Client::SPEEDY_CITIES ]->get_filtered_cities( $raw_city, $raw_state, $country_id );

		if ( ! in_array( $cities_data['city'], $cities_data['cities_only_names'], true ) ) {
			$args['cities'] = nashenski_return_array_for_select( $cities_data['cities_only_names_dropdowns'], 1, array( 'type' => 'city' ) );
			$args['status'] = 'invalid-city';
		} else {
			$city_id = $cities_data['cities'][ $cities_data['city_key'] ]['id'];

			$streets  = nashenski_return_array_for_select( self::get_streets_for_query( $city_id, ' ' ), 1, array( 'type' => 'streets' ) );
			$quarters = nashenski_return_array_for_select( self::get_quarters_for_query( $city_id, ' ' ), 1, array( 'type' => 'quarters' ) );

			$args['streets'] = self::merge_options( $streets, $quarters );
			$args['has_any'] = ! empty( $streets ) || ! empty( $quarters );
			$args['status']  = 'valid-city';
		}

		wp_send_json_success( $args );

		wp_die();
	}

	/**
	 * Streets of a town that match the query, best matches first.
	 *
	 * @param int    $city_id Speedy town ID.
	 * @param string $query   Typed text.
	 * @return array
	 */
	public static function get_streets_for_query( $city_id, $query ) {
		if ( ! self::$container ) {
			self::$container = nashenski()->container();
		}

		$streets = self::$container[ Client::SPEEDY_STREETS ]->get_streets_by_city( $city_id, $query );

		if ( ! empty( $streets ) ) {
			$streets = self::$container[ Client::SPEEDY_STREETS ]->format_streets( $streets, $city_id, $query );
			$streets = self::rank_names( $streets, $query );
		} else {
			$streets = array();
		}

		return $streets;
	}

	/**
	 * Quarters of a town that match the query, best matches first.
	 *
	 * @param int    $city_id Speedy town ID.
	 * @param string $query   Typed text.
	 * @return array
	 */
	public static function get_quarters_for_query( $city_id, $query ) {
		if ( ! self::$container ) {
			self::$container = nashenski()->container();
		}

		$quarters = self::$container[ Client::SPEEDY_QUARTERS ]->get_quarters_by_city( $city_id, $query );

		if ( ! empty( $quarters ) ) {
			$quarters = self::$container[ Client::SPEEDY_QUARTERS ]->format_quarters( $quarters, $city_id, $query );
			$quarters = self::rank_names( $quarters, $query );
		} else {
			$quarters = array();
		}

		return $quarters;
	}

	/**
	 * Merges quarters into the streets; a quarter replaces a street of the same name.
	 *
	 * @param array $streets  Street options.
	 * @param array $quarters Quarter options.
	 * @return array
	 */
	public static function merge_options( $streets, $quarters ) {
		$test_streets = array_map(
			function ( $street ) {
				return self::normalize_match_text( $street['label'] );
			},
			$streets
		);

		foreach ( $quarters as $quarter ) {
			$test_label = self::normalize_match_text( $quarter['label'] );

			if ( in_array( $test_label, $test_streets, true ) ) {
				$key             = array_search( $test_label, $test_streets, true );
				$streets[ $key ] = $quarter;
			} else {
				$streets[] = $quarter;
			}
		}

		return $streets;
	}

	/**
	 * Query for the Speedy API: no street prefix, Cyrillic for Bulgaria, single spaces.
	 *
	 * @param string $query         Typed text.
	 * @param bool   $transliterate Whether to turn Latin into Cyrillic.
	 * @return string
	 */
	private static function normalize_api_query( $query, $transliterate ) {
		$query = nashenski_strip_street_prefix( trim( (string) $query ) );

		if ( $transliterate ) {
			$query = Transliteration::latin2cyrillic( $query );
		}

		return trim( preg_replace( '/[\s\p{Zs}]+/u', ' ', $query ) );
	}

	/**
	 * Text for matching: no street prefix, lower case, letters and digits only.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function normalize_match_text( $text ) {
		$text = nashenski_strip_street_prefix( trim( (string) $text ) );
		$text = mb_strtolower( $text );
		$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );

		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Sorts names: exact match, then starts with the query, then contains it, then the rest.
	 *
	 * @param array  $names Names, keyed.
	 * @param string $query Typed text.
	 * @return array
	 */
	private static function rank_names( $names, $query ) {
		$query = self::normalize_match_text( $query );

		if ( '' === $query ) {
			return $names;
		}

		$ranked = array();
		$order  = 0;

		foreach ( $names as $key => $name ) {
			$normalized_name = self::normalize_match_text( $name );
			$position        = mb_strpos( $normalized_name, $query );
			$rank            = $normalized_name === $query ? 0 : ( 0 === $position ? 1 : ( false !== $position ? 2 : 3 ) );
			$ranked[]        = array(
				'key'   => $key,
				'name'  => $name,
				'rank'  => $rank,
				'order' => $order++,
			);
		}

		usort(
			$ranked,
			function ( $a, $b ) {
				return $a['rank'] === $b['rank'] ? $a['order'] <=> $b['order'] : $a['rank'] <=> $b['rank'];
			}
		);

		$sorted = array();
		foreach ( $ranked as $match ) {
			$sorted[ $match['key'] ] = $match['name'];
		}

		return $sorted;
	}
}
