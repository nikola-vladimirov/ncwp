<?php
namespace Nashenski\Client\Speedy;

use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

class Countries {
	const COUNTRIES_ENDPOINT = 'location/country/csv/';

	private $countries = array();
	private $container;

	public function __construct( $container ) {
		$this->container = $container;
	}

	protected function load_countries( $name_en ) {
		if ( ! is_dir( $this->container[ Client::SPEEDY ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::SPEEDY ]::CACHE_FOLDER );
		}

		$countries_file = $this->container[ Client::SPEEDY ]::CACHE_FOLDER . 'countries.csv';

		$failed_key = 'nashenski_speedy_countries_failed';

		if ( ! is_file( $countries_file ) || ! filesize( $countries_file ) ) {
			// A recent failed fetch is not retried on every request.
			if ( get_transient( $failed_key ) ) {
				return array();
			}

			$body = $this->container[ Client::SPEEDY ]->api_call( self::COUNTRIES_ENDPOINT, array(), 1 );

			if ( is_string( $body ) && '' !== trim( $body ) ) {
				File::put_to_file( $countries_file, $body );
			}

			clearstatcache();
		}

		if ( ! is_file( $countries_file ) || ! filesize( $countries_file ) ) {
			if ( is_file( $countries_file ) ) {
				wp_delete_file( $countries_file );
			}

			set_transient( $failed_key, 1, 5 * MINUTE_IN_SECONDS );

			return array();
		}

		$countries = array_filter(
			nashenski_read_csv( $countries_file ),
			function ( $country ) use ( $name_en ) {
				return $country['isoAlpha2'] === $name_en;
			}
		);

		$this->set_countries( $countries );

		return $countries;
	}

	// Getters
	public function get_countries_by_name_en( $country ) {
		if ( empty( $this->countries ) ) {
			$this->load_countries( $country );
		}

		return $this->countries;
	}

	public function get_country_id( $key ) {
		$this->get_countries_by_name_en( $key );

		// Null when Speedy did not answer (no login yet).
		return array_values( (array) $this->countries )[0]['id'] ?? null;
	}

	// Setters
	private function set_countries( $countries_by_name_en ) {
		$this->countries = $countries_by_name_en;
	}
}
