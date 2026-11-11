<?php
/**
 * Speedy quarters (complexes) lookup.
 *
 * @package Nashenski
 */

namespace Nashenski\Client\Speedy;

use Nashenski\Container\Client;
use Nashenski\File;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the quarters of a Speedy city and keeps the answers in a cache file.
 */
class Quarters {
	const QUARTERS_ENDPOINT = 'location/complex/';

	/**
	 * Loaded quarters by city and query hash.
	 *
	 * @var array
	 */
	private $quarters = array();

	/**
	 * Service container.
	 *
	 * @var \Pimple\Container
	 */
	private $container;

	/**
	 * Keeps the container.
	 *
	 * @param \Pimple\Container $container Service container.
	 */
	public function __construct( $container ) {
		$this->container = $container;
	}

	/**
	 * Loads the quarters of a city that match a query, from the cache file or from Speedy.
	 *
	 * @param int    $city_id City id.
	 * @param string $query   Name typed by the shopper.
	 * @return array
	 */
	protected function load_quarters_by_city( $city_id, $query ) {
		if ( ! is_dir( $this->container[ Client::SPEEDY ]::cache_folder() ) ) {
			wp_mkdir_p( $this->container[ Client::SPEEDY ]::cache_folder() );
		}

		$query         = mb_substr( (string) $query, 0, 64 );
		$hash          = md5( $query );
		$quarters_file = $this->container[ Client::SPEEDY ]::cache_folder() . 'quarters-' . $city_id . '-' . $hash . '.json';
		$quarters      = File::get_file( $quarters_file );

		$miss_key = 'nashenski_miss_' . md5( $quarters_file );

		// An empty answer is remembered for 5 minutes, so the same lookup does not call the courier again.
		if ( ! $quarters && ! get_transient( $miss_key ) ) {
			// Speedy matches names in Latin (nameEn), so a Cyrillic query finds nothing.
			$api_call = $this->container[ Client::SPEEDY ]->api_call(
				self::QUARTERS_ENDPOINT,
				array(
					'siteId' => $city_id,
					'name'   => Transliteration::to_latin( $query ),
				)
			);

			if ( is_array( $api_call ) && $this->container[ Client::SPEEDY ]::validate_access( $api_call ) && ! empty( $api_call['complexes'] ) ) {
				$quarters = wp_json_encode( $api_call['complexes'] );

				File::put_to_file( $quarters_file, $quarters );
				unset( $api_call );
			} else {
				set_transient( $miss_key, 1, 5 * MINUTE_IN_SECONDS );
			}
		}

		$quarters = json_decode( $quarters, 1 );

		$this->set_quarters( $quarters, $city_id, $hash );

		return $quarters;
	}

	/**
	 * Gets the quarters of a city that match a query.
	 *
	 * @param int    $city_id City id.
	 * @param string $query   Name typed by the shopper.
	 * @return array
	 */
	public function get_quarters_by_city( $city_id, $query ) {
		$query = mb_substr( (string) $query, 0, 64 );
		$hash  = md5( $query );

		if ( empty( $this->quarters[ $city_id ][ $hash ] ) ) {
			$this->load_quarters_by_city( $city_id, $query );
		}

		return $this->quarters[ $city_id ][ $hash ];
	}

	/**
	 * Remembers the quarters of a city and query.
	 *
	 * @param array  $quarters Quarters.
	 * @param int    $city_id  City id.
	 * @param string $hash     Query hash.
	 */
	private function set_quarters( $quarters, $city_id, $hash ) {
		$this->quarters[ $city_id ][ $hash ] = $quarters;
	}

	/**
	 * Formats quarters as id => label.
	 *
	 * @param array    $quarters Quarters from Speedy.
	 * @param int|null $city_id  City to forget after formatting.
	 * @param string   $query    Query used to find them.
	 * @return array
	 */
	public function format_quarters( $quarters, $city_id = null, $query = '' ) {
		$formatted = array();

		foreach ( $quarters as $qtr ) {
			$formatted[ 'qtr-' . $qtr['id'] ] = $qtr['type'] . ' ' . nashenski_title_case_bg( $qtr['name'] );
		}

		if ( null !== $city_id ) {
			unset( $this->quarters[ $city_id ][ md5( $query ) ] );
		}

		return $formatted;
	}
}
