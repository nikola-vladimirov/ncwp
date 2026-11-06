<?php
namespace Nashenski\Client\Speedy;

use Nashenski\Container\Client;
use Nashenski\File;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

class Quarters {
	const QUARTERS_ENDPOINT = 'location/complex/';

	private $quarters = array();
	private $container;

	public function __construct( $container ) {
		$this->container = $container;
	}

	protected function load_quarters_by_city( $city_id, $query ) {
		if ( ! is_dir( $this->container[ Client::SPEEDY ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::SPEEDY ]::CACHE_FOLDER );
		}

		$query         = mb_substr( (string) $query, 0, 64 );
		$hash          = md5( $query );
		$quarters_file = $this->container[ Client::SPEEDY ]::CACHE_FOLDER . 'quarters-' . $city_id . '-' . $hash . '.json';
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

	// Getters
	public function get_quarters_by_city( $city_id, $query ) {
		$query = mb_substr( (string) $query, 0, 64 );
		$hash  = md5( $query );

		if ( empty( $this->quarters[ $city_id ][ $hash ] ) ) {
			$this->load_quarters_by_city( $city_id, $query );
		}

		return $this->quarters[ $city_id ][ $hash ];
	}

	// Setters
	private function set_quarters( $quarters, $city_id, $hash ) {
		$this->quarters[ $city_id ][ $hash ] = $quarters;
	}

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
