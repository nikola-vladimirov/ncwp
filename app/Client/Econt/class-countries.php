<?php
/**
 * The Econt countries, cached in a file.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client\Econt;

use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * The Econt countries, cached in a file.
 */
class Countries {
	const COUNTRIES_ENDPOINT = 'Nomenclatures/NomenclaturesService.getCountries.json';

	/**
	 * Countries, loaded on first use.
	 *
	 * @var array
	 */
	private $countries = array();

	/**
	 * Dependency container.
	 *
	 * @var object
	 */
	private $container;

	/**
	 * Keeps the container.
	 *
	 * @param object $container Dependency container.
	 */
	public function __construct( $container ) {
		$this->container = $container;
	}

	/**
	 * Reads the countries from the cache file, or from Econt when there is none.
	 *
	 * @return array
	 */
	protected function load_countries() {
		if ( ! is_dir( $this->container[ Client::ECONT ]::cache_folder() ) ) {
			wp_mkdir_p( $this->container[ Client::ECONT ]::cache_folder() );
		}

		$countries_file = $this->container[ Client::ECONT ]::cache_folder() . 'countries.json';
		$countries      = File::get_file( $countries_file );

		if ( ! $countries ) {
			$api_call = $this->container[ Client::ECONT ]->api_call( self::COUNTRIES_ENDPOINT, array( 'GetCountriesRequest' => '' ) );

			if ( is_array( $api_call ) ) {
				if ( $this->container[ Client::ECONT ]::validate_access( $api_call ) ) {
					$countries = wp_json_encode( $api_call );

					File::put_to_file( $countries_file, $countries );
				}
			}
		}

		$countries = json_decode( $countries, 1 );

		$this->set_countries( $countries );

		return $countries;
	}

	/**
	 * The countries.
	 *
	 * @return array
	 */
	public function get_countries() {
		if ( empty( $this->countries ) ) {
			$this->load_countries();
		}

		return $this->countries;
	}

	/**
	 * Keeps the countries.
	 *
	 * @param array $countries Countries.
	 * @return void
	 */
	private function set_countries( $countries ) {
		$this->countries = $countries;
	}
}
