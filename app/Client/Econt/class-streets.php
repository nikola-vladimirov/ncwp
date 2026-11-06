<?php
/**
 * Streets.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client\Econt;

use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * Streets.
 */
class Streets {
	const STREETS_ENDPOINT = 'Nomenclatures/NomenclaturesService.getStreets.json';

	/**
	 * Streets.
	 *
	 * @var array
	 */
	private $streets = array();
	/**
	 * Container.
	 *
	 * @var mixed
	 */
	private $container;

	/**
	 * Constructor.
	 *
	 * @param mixed $container Container.
	 */
	public function __construct( $container ) {
		$this->container = $container;
	}

	/**
	 * Load streets by city.
	 *
	 * @param mixed $city_id City id.
	 * @return mixed
	 */
	protected function load_streets_by_city( $city_id ) {
		if ( ! is_dir( $this->container[ Client::ECONT ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::ECONT ]::CACHE_FOLDER );
		}

		$streets_file = $this->container[ Client::ECONT ]::CACHE_FOLDER . 'streets-' . $city_id . '.json';
		$streets      = File::get_file( $streets_file );

		if ( ! $streets ) {
			$api_call = $this->container[ Client::ECONT ]->api_call( self::STREETS_ENDPOINT, array( 'cityID' => $city_id ) );

			if ( is_array( $api_call ) ) {
				if ( $this->container[ Client::ECONT ]::validate_access( $api_call ) ) {
					if ( ! empty( $api_call['streets'] ) ) {
						$streets = wp_json_encode( $api_call );

						File::put_to_file( $streets_file, $streets );
						unset( $api_call );
					}
				}
			}
		}

		$streets = json_decode( $streets, 1 );

		$this->set_streets( $streets, $city_id );

		return $streets;
	}

	// Getters.

	/**
	 * Get streets by city.
	 *
	 * @param mixed $city_id City id.
	 * @return mixed
	 */
	public function get_streets_by_city( $city_id ) {
		if ( empty( $this->streets[ $city_id ] ) ) {
			$this->load_streets_by_city( $city_id );
		}

		return $this->streets[ $city_id ];
	}

	// Setters.

	/**
	 * Set streets.
	 *
	 * @param mixed $streets Streets.
	 * @param mixed $city_id City id.
	 */
	private function set_streets( $streets, $city_id ) {
		$this->streets[ $city_id ] = $streets;
	}

	/**
	 * Format streets.
	 *
	 * @param mixed $streets Streets.
	 * @param mixed $city_id City id.
	 * @return mixed
	 */
	public function format_streets( $streets, $city_id = null ) {
		$formatted = array();

		foreach ( $streets as $street ) {
			$formatted[ 'street-' . $street['id'] ] = $street['name'];
		}

		if ( null !== $city_id ) {
			unset( $this->streets[ $city_id ] );
		}

		return $formatted;
	}
}
