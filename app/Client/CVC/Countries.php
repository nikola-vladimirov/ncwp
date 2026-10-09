<?php
namespace Nashenski\Client\CVC;
use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

class Countries {
	const COUNTRIES_ENDPOINT = 'get_countries';

	private $countries = [];
	private $container;

	public function __construct( $container ) {
		$this->container = $container;
	}

	protected function load_countries() {
		if ( ! is_dir( $this->container[ Client::CVC ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::CVC ]::CACHE_FOLDER );
		}

		$countries_file = $this->container[ Client::CVC ]::CACHE_FOLDER . 'countries.json';
		$countries = File::get_file( $countries_file );

		if ( !$countries ) {
			$api_call = $this->container[ Client::CVC ]->api_call( self::COUNTRIES_ENDPOINT, array( 'request' => '' ) );

			if ( is_array( $api_call ) ) {
				if ( $this->container[ Client::CVC ]::validate_access( $api_call ) ) {
					$countries = wp_json_encode( $api_call );
					
					File::put_to_file( $countries_file, $countries );
				}
			}
		}

		$countries = json_decode( $countries, 1 );

		$this->set_countries( $countries );

		return $countries;
	}

	//Getters
	public function get_countries() {
		if ( empty( $this->countries ) ) {
			$this->load_countries();
		}

		return $this->countries;
	}

	public function get_country_id( $country ) {
		// Null when CVC did not answer or does not serve the country (not the first country of the list).
		$countries = (array) ( $this->get_countries()['countries'] ?? array() );
		$key = array_search( $country, array_column( $countries, 'code' ) );

		return false === $key ? null : $countries[ $key ]['id'];
	}

	//Setters
	private function set_countries( $countries ) {
		$this->countries = $countries;
	}
}
