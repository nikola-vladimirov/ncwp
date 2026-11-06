<?php
/**
 * Speedy streets.
 *
 * @package Nashenski
 */

namespace Nashenski\Client\Speedy;

use Nashenski\Container\Client;
use Nashenski\File;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

/**
 * Speedy streets of a city, cached in files.
 */
class Streets {
	const STREETS_ENDPOINT = 'location/street';

	/**
	 * Streets by city id and query hash.
	 *
	 * @var array
	 */
	private $streets = array();

	/**
	 * Client container.
	 *
	 * @var \Pimple\Container
	 */
	private $container;

	/**
	 * Keep the client container.
	 *
	 * @param \Pimple\Container $container Client container.
	 */
	public function __construct( $container ) {
		$this->container = $container;
	}

	/**
	 * Load the streets of a city matching the query, from the cache or the API.
	 *
	 * @param int|string $city_id City id.
	 * @param string     $query   Query.
	 * @return array|null
	 */
	protected function load_streets_by_city( $city_id, $query ) {
		if ( ! is_dir( $this->container[ Client::SPEEDY ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::SPEEDY ]::CACHE_FOLDER );
		}

		$query        = mb_substr( (string) $query, 0, 64 );
		$hash         = md5( $query );
		$streets_file = $this->container[ Client::SPEEDY ]::CACHE_FOLDER . 'streets-' . $city_id . '-' . $hash . '.json';
		$streets      = File::get_file( $streets_file );

		$miss_key = 'nashenski_miss_' . md5( $streets_file );

		// An empty answer is remembered for 5 minutes, so the same lookup does not call the courier again.
		if ( ! $streets && ! get_transient( $miss_key ) ) {
			// Speedy matches names in Latin (nameEn), so a Cyrillic query finds nothing.
			$api_call = $this->container[ Client::SPEEDY ]->api_call(
				self::STREETS_ENDPOINT,
				array(
					'siteId' => $city_id,
					'name'   => Transliteration::to_latin( $query ),
				)
			);

			if ( is_array( $api_call ) && $this->container[ Client::SPEEDY ]::validate_access( $api_call ) && ! empty( $api_call['streets'] ) ) {
				$streets = wp_json_encode( $api_call['streets'] );

				File::put_to_file( $streets_file, $streets );
				unset( $api_call );
			} else {
				set_transient( $miss_key, 1, 5 * MINUTE_IN_SECONDS );
			}
		}

		$streets = json_decode( $streets, 1 );

		$this->set_streets( $streets, $city_id, $hash );

		return $streets;
	}

	/**
	 * Streets of a city matching the query.
	 *
	 * @param int|string $city_id City id.
	 * @param string     $query   Query.
	 * @return array|null
	 */
	public function get_streets_by_city( $city_id, $query ) {
		$query = mb_substr( (string) $query, 0, 64 );
		$hash  = md5( $query );

		if ( empty( $this->streets[ $city_id ][ $hash ] ) ) {
			$this->load_streets_by_city( $city_id, $query );
		}

		return $this->streets[ $city_id ][ $hash ];
	}

	/**
	 * Keep the streets of a city and query.
	 *
	 * @param array|null $streets Streets.
	 * @param int|string $city_id City id.
	 * @param string     $hash    Query hash.
	 */
	private function set_streets( $streets, $city_id, $hash ) {
		$this->streets[ $city_id ][ $hash ] = $streets;
	}

	/**
	 * Streets as "street-id" => "type name"; with a city, its kept copy is dropped.
	 *
	 * @param array           $streets Streets.
	 * @param int|string|null $city_id City id.
	 * @param string          $query   Query.
	 * @return array
	 */
	public function format_streets( $streets, $city_id = null, $query = '' ) {
		$formatted = array();

		foreach ( $streets as $street ) {
			$formatted[ 'street-' . $street['id'] ] = $street['type'] . ' ' . nashenski_title_case_bg( $street['name'] );
		}

		if ( null !== $city_id ) {
			unset( $this->streets[ $city_id ][ md5( $query ) ] );
		}

		return $formatted;
	}
}
