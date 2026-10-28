<?php
namespace Nashenski\Client\Speedy;
use Nashenski\Container\Client;
use Nashenski\File;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

class Streets {
	const STREETS_ENDPOINT = 'location/street';

	private $streets = [];
	private $container;

	public function __construct( $container ) {
		$this->container = $container;
	}

	protected function load_streets_by_city( $city_id, $query ) {
		if ( ! is_dir( $this->container[ Client::SPEEDY ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::SPEEDY ]::CACHE_FOLDER );
		}

		$query = mb_substr( (string) $query, 0, 64 );
		$hash = md5( $query );
		$streets_file = $this->container[ Client::SPEEDY ]::CACHE_FOLDER . 'streets-' . $city_id . '-' . $hash . '.json';
		$streets = File::get_file( $streets_file );

		$miss_key = 'nashenski_miss_' . md5( $streets_file );

		// An empty answer is remembered for 5 minutes, so the same lookup does not call the courier again.
		if ( !$streets && !get_transient( $miss_key ) ) {
			// Speedy matches names in Latin (nameEn), so a Cyrillic query finds nothing.
			$api_call = $this->container[ Client::SPEEDY ]->api_call( self::STREETS_ENDPOINT, array( 'siteId' => $city_id, 'name' => Transliteration::to_latin( $query ) ) );

			if ( is_array( $api_call ) && $this->container[ Client::SPEEDY ]::validate_access( $api_call ) && !empty( $api_call['streets'] ) ) {
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

	//Getters
	public function get_streets_by_city( $city_id, $query ) {
		$query = mb_substr( (string) $query, 0, 64 );
		$hash = md5( $query );

		if ( empty( $this->streets[ $city_id ][ $hash ] ) ) {
			$this->load_streets_by_city( $city_id, $query );
		}

		return $this->streets[ $city_id ][ $hash ];
	}

	//Setters
	private function set_streets( $streets, $city_id, $hash ) {
		$this->streets[ $city_id ][ $hash ] = $streets;
	}

	public function format_streets( $streets, $city_id = null, $query = '' ) {
		$formatted = [];

		foreach ( $streets as $street ) {
			$formatted[ 'street-' . $street['id'] ] = $street['type'] . " " . nashenski_title_case_bg( $street['name'] );
		}

		if ( null !== $city_id ) {
			unset( $this->streets[ $city_id ][ md5( $query ) ] );
		}
		
		return $formatted;
	}
}
