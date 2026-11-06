<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.
/**
 * Quarters.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client\Econt;

use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * Quarters.
 */
class Quarters {
	const QUARTERS_ENDPOINT = 'Nomenclatures/NomenclaturesService.getQuarters.json';

	/**
	 * Quarters.
	 *
	 * @var array
	 */
	private $quarters = array();
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
	 * Load quarters by city.
	 *
	 * @param mixed $city_id City id.
	 * @return mixed
	 */
	protected function load_quarters_by_city( $city_id ) {
		if ( ! is_dir( $this->container[ Client::ECONT ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::ECONT ]::CACHE_FOLDER );
		}

		$quarters_file = $this->container[ Client::ECONT ]::CACHE_FOLDER . 'quarters-' . $city_id . '.json';
		$quarters      = File::get_file( $quarters_file );

		if ( ! $quarters ) {
			$api_call = $this->container[ Client::ECONT ]->api_call( self::QUARTERS_ENDPOINT, array( 'cityID' => $city_id ) );

			if ( is_array( $api_call ) ) {
				if ( $this->container[ Client::ECONT ]::validate_access( $api_call ) ) {
					if ( ! empty( $api_call['quarters'] ) ) {
						$quarters = wp_json_encode( $api_call );

						File::put_to_file( $quarters_file, $quarters );
						unset( $api_call );
					}
				}
			}
		}

		$quarters = json_decode( $quarters, 1 );

		$this->set_quarters( $quarters, $city_id );

		return $quarters;
	}

	// Getters.

	/**
	 * Get quarters by city.
	 *
	 * @param mixed $city_id City id.
	 * @return mixed
	 */
	public function get_quarters_by_city( $city_id ) {
		if ( empty( $this->quarters[ $city_id ] ) ) {
			$this->load_quarters_by_city( $city_id );
		}

		return $this->quarters[ $city_id ];
	}

	// Setters.

	/**
	 * Set quarters.
	 *
	 * @param mixed $quarters Quarters.
	 * @param mixed $city_id  City id.
	 */
	private function set_quarters( $quarters, $city_id ) {
		$this->quarters[ $city_id ] = $quarters;
	}

	/**
	 * Format quarters.
	 *
	 * @param mixed $quarters Quarters.
	 * @param mixed $city_id  City id.
	 * @return mixed
	 */
	public function format_quarters( $quarters, $city_id = null ) {
		$formatted = array();

		foreach ( $quarters as $qtr ) {
			$formatted[ 'qtr-' . $qtr['id'] ] = $qtr['name'];
		}

		if ( null !== $city_id ) {
			unset( $this->quarters[ $city_id ] );
		}

		return $formatted;
	}
}
