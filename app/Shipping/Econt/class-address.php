<?php
/**
 * Address.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Econt;

use Nashenski\Container\Client;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

/**
 * Address.
 */
class Address {
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

		add_action( 'wp_ajax_nashenski_econt_load_streets', array( __CLASS__, 'load_streets' ) );
		add_action( 'wp_ajax_nopriv_nashenski_econt_load_streets', array( __CLASS__, 'load_streets' ) );

		add_action( 'wp_ajax_nashenski_econt_search_address', array( __CLASS__, 'search_address' ) );
		add_action( 'wp_ajax_nopriv_nashenski_econt_search_address', array( __CLASS__, 'search_address' ) );
	}

	/**
	 * Format other.
	 *
	 * @param mixed $address_data     Address data.
	 * @param array $additional_parts Additional parts.
	 * @return mixed
	 */
	public static function format_other( $address_data, $additional_parts = array() ) {
		$labels = array(
			'blockNumber'     => __( 'Block', 'nashenski-commerce' ),
			'entranceNumber'  => __( 'Entrance', 'nashenski-commerce' ),
			'floorNumber'     => __( 'Floor', 'nashenski-commerce' ),
			'apartmentNumber' => __( 'Apartment', 'nashenski-commerce' ),
		);
		$parts  = array();

		foreach ( $labels as $field => $label ) {
			$value = isset( $address_data[ $field ] ) ? trim( (string) $address_data[ $field ] ) : '';

			if ( '' !== $value ) {
				$parts[] = $label . ' ' . sanitize_text_field( $value );
			}
		}

		// Preserve the old combined field for orders created before the separate fields existed.
		$legacy_other = isset( $address_data['other'] ) ? trim( (string) $address_data['other'] ) : '';
		if ( empty( $parts ) && '' !== $legacy_other ) {
			$parts[] = sanitize_text_field( $legacy_other );
		}

		foreach ( $additional_parts as $part ) {
			if ( is_scalar( $part ) && trim( (string) $part ) !== '' ) {
				$parts[] = sanitize_text_field( $part );
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Has structured details.
	 *
	 * @param mixed $address_data Address data.
	 * @return mixed
	 */
	public static function has_structured_details( $address_data ) {
		foreach ( array( 'blockNumber', 'entranceNumber', 'floorNumber', 'apartmentNumber' ) as $field ) {
			if ( isset( $address_data[ $field ] ) && trim( (string) $address_data[ $field ] ) !== '' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Search address.
	 */
	public static function search_address() {
		nashenski_check_picker_request();

		self::$container = nashenski()->container();
		$args            = array();
		$raw_state       = nashenski_picker_request( 'state' );
		$country         = nashenski_picker_request( 'country' );
		$query           = self::normalize_search_text( nashenski_picker_request( 'query' ), 'BG' === $country );
		$states          = self::$container[ Client::ECONT_CITIES ]->get_regions( $country );
		$state           = $states[ $raw_state ] ?? '';
		$raw_city        = nashenski_picker_request( 'city' );
		$cities_data     = self::$container[ Client::ECONT_CITIES ]->get_filtered_cities( $raw_city, $state, $country );

		if ( in_array( $cities_data['city'], $cities_data['cities_only_names'], true ) ) {
			$streets = nashenski_return_array_for_select(
				self::get_streets_for_query( $query, $cities_data['city_key'], $cities_data['cities'] ),
				1,
				array( 'type' => 'streets' )
			);

			$quarters = nashenski_return_array_for_select(
				self::get_quarters_for_query( $query, $cities_data['city_key'], $cities_data['cities'] ),
				1,
				array( 'type' => 'quarters' )
			);

			$args['streets'] = array_merge( $streets, $quarters );
		} else {
			$args['cities'] = nashenski_return_array_for_select( self::get_cities_for_query( $query, $cities_data['cities_only_names'] ), 1 );
		}

		wp_send_json_success( $args );

		wp_die();
	}

	/**
	 * Load streets.
	 */
	public static function load_streets() {
		nashenski_check_picker_request();

		self::$container = nashenski()->container();
		$args            = array();
		$raw_state       = nashenski_picker_request( 'state' );
		$country         = nashenski_picker_request( 'country' );
		$states          = self::$container[ Client::ECONT_CITIES ]->get_regions( $country );
		$state           = $states[ $raw_state ] ?? '';

		$raw_city    = nashenski_picker_request( 'city' );
		$cities_data = self::$container[ Client::ECONT_CITIES ]->get_filtered_cities( $raw_city, $state, $country );

		if ( ! in_array( $cities_data['city'], $cities_data['cities_only_names'], true ) ) {
			$args['cities'] = nashenski_return_array_for_select( $cities_data['cities_only_names_dropdowns'], 1, array( 'type' => 'city' ) );
			$args['status'] = 'invalid-city';
		} else {
			$streets = nashenski_return_array_for_select(
				self::get_streets_for_query( '', $cities_data['city_key'], $cities_data['cities'] ),
				1,
				array( 'type' => 'streets' )
			);

			$quarters = nashenski_return_array_for_select(
				self::get_quarters_for_query( '', $cities_data['city_key'], $cities_data['cities'] ),
				1,
				array( 'type' => 'quarters' )
			);

			$args['streets'] = array_merge( $streets, $quarters );
			$args['status']  = 'valid-city';
		}

		wp_send_json_success( $args );

		wp_die();
	}

	/**
	 * Get streets for query.
	 *
	 * @param mixed $query    Query.
	 * @param mixed $city_key City key.
	 * @param mixed $cities   Cities.
	 * @return mixed
	 */
	public static function get_streets_for_query( $query, $city_key, $cities ) {
		if ( ! self::$container ) {
			self::$container = nashenski()->container();
		}

		$city_id            = $cities[ $city_key ]['id'];
		$streets            = self::$container[ Client::ECONT_STREETS ]->get_streets_by_city( $city_id );
		$streets_only_names = ( ! empty( $streets['streets'] ) ) ? self::$container[ Client::ECONT_STREETS ]->format_streets( $streets['streets'], $city_id ) : array();
		unset( $streets );

		return self::filter_and_rank_names( $streets_only_names, $query );
	}

	/**
	 * Get quarters for query.
	 *
	 * @param mixed $query    Query.
	 * @param mixed $city_key City key.
	 * @param mixed $cities   Cities.
	 * @return mixed
	 */
	public static function get_quarters_for_query( $query, $city_key, $cities ) {
		if ( ! self::$container ) {
			self::$container = nashenski()->container();
		}

		$city_id             = $cities[ $city_key ]['id'];
		$quarters            = self::$container[ Client::ECONT_QUARTERS ]->get_quarters_by_city( $city_id );
		$quarters_only_names = ( ! empty( $quarters['quarters'] ) ) ? self::$container[ Client::ECONT_QUARTERS ]->format_quarters( $quarters['quarters'], $city_id ) : array();
		unset( $quarters );

		return self::filter_and_rank_names( $quarters_only_names, $query );
	}

	/**
	 * Get cities for query.
	 *
	 * @param mixed $query  Query.
	 * @param mixed $cities Cities.
	 * @return mixed
	 */
	public static function get_cities_for_query( $query, $cities ) {
		return array_values( self::filter_and_rank_names( $cities, $query ) );
	}

	/**
	 * Normalize search text.
	 *
	 * @param mixed $text          Text.
	 * @param bool  $transliterate Transliterate.
	 * @return mixed
	 */
	private static function normalize_search_text( $text, $transliterate = true ) {
		$text = nashenski_strip_street_prefix( trim( (string) $text ) );

		if ( $transliterate ) {
			$text = Transliteration::latin2cyrillic( $text );
		}

		$text = mb_strtolower( $text );
		$text = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );

		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Filter and rank names.
	 *
	 * @param mixed $names Names.
	 * @param mixed $query Query.
	 * @return mixed
	 */
	private static function filter_and_rank_names( $names, $query ) {
		$query = self::normalize_search_text( $query, false );

		if ( '' === $query ) {
			return $names;
		}

		$tokens = explode( ' ', $query );
		$ranked = array();
		$order  = 0;

		foreach ( $names as $key => $name ) {
			$normalized_name  = self::normalize_search_text( $name, false );
			$position         = mb_strpos( $normalized_name, $query );
			$all_tokens_match = true;

			foreach ( $tokens as $token ) {
				if ( mb_strpos( $normalized_name, $token ) === false ) {
					$all_tokens_match = false;
					break;
				}
			}

			if ( false === $position && ! $all_tokens_match ) {
				continue;
			}

			$rank     = $normalized_name === $query ? 0 : ( 0 === $position ? 1 : ( false !== $position ? 2 : 3 ) );
			$ranked[] = array(
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

		$filtered = array();
		foreach ( $ranked as $match ) {
			$filtered[ $match['key'] ] = $match['name'];
		}

		return $filtered;
	}
}
