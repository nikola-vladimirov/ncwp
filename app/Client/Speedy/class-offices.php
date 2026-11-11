<?php
/**
 * Speedy offices.
 *
 * @package Nashenski
 */

namespace Nashenski\Client\Speedy;

use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * Speedy offices and automats of a city, cached in files.
 */
class Offices {
	const OFFICES_ENDPOINT = 'location/office/';

	/**
	 * Offices by city id.
	 *
	 * @var array
	 */
	private $offices = array();

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
	 * Load the offices of a city, from the cache or the API.
	 *
	 * @param int|string $city_id    City id.
	 * @param int        $country_id Country id.
	 * @return array|null
	 */
	protected function load_offices( $city_id, $country_id = 100 ) {
		if ( ! is_dir( $this->container[ Client::SPEEDY ]::cache_folder() ) ) {
			wp_mkdir_p( $this->container[ Client::SPEEDY ]::cache_folder() );
		}

		$offices_file = $this->container[ Client::SPEEDY ]::cache_folder() . 'offices-' . $city_id . '.json';
		$offices      = File::get_file( $offices_file );
		if ( ! $offices ) {
			$api_call = $this->container[ Client::SPEEDY ]->api_call(
				self::OFFICES_ENDPOINT,
				array(
					'siteId'    => $city_id,
					'countryId' => $country_id,
				)
			);

			if ( is_array( $api_call ) ) {
				if ( $this->container[ Client::SPEEDY ]::validate_access( $api_call ) ) {
					if ( ! empty( $api_call['offices'] ) ) {
						$offices = wp_json_encode( $api_call );

						File::put_to_file( $offices_file, $offices );
						unset( $api_call );
					}
				}
			}
		}

		$offices = json_decode( $offices, 1 );

		$this->set_offices( $city_id, $offices );

		return $offices;
	}

	/**
	 * The offices of a city.
	 *
	 * @param int|string $city_id    City id.
	 * @param int        $country_id Country id.
	 * @return array|null
	 */
	public function get_offices( $city_id, $country_id = 100 ) {
		if ( empty( $this->offices[ $city_id ] ) ) {
			$this->load_offices( $city_id, $country_id );
		}

		return $this->offices[ $city_id ];
	}

	/**
	 * Keep the offices of a city.
	 *
	 * @param int|string $city_id City id.
	 * @param array|null $offices Offices.
	 */
	private function set_offices( $city_id, $offices ) {
		$this->offices[ $city_id ] = $offices;
	}

	/**
	 * The offices (shops) and automats (aps) of a city for the picker.
	 *
	 * @param string $city       City id, as cityID-123.
	 * @param int    $country_id Country id.
	 * @return array
	 */
	public function get_formatted_offices( $city, $country_id = 100 ) {
		$city_id = str_replace( 'cityID-', '', $city );
		$offices = $this->get_offices( $city_id, $country_id );
		$shops   = array();
		$aps     = array();

		if ( ! empty( $offices['offices'] ) ) {
			foreach ( $offices['offices'] as $office ) {
				$data = array(
					'id'    => 'officeID-' . $office['id'],
					'label' => $office['name'] . ' (' . $office['address']['fullAddressString'] . ')',
				);

				if ( 'APT' === $office['type'] ) {
					$aps[ 'officeID-' . $office['id'] ] = $data;
				} else {
					$shops[ 'officeID-' . $office['id'] ] = $data;
				}
			}
		}

		unset( $this->offices[ $city_id ], $offices );

		return array(
			'shops' => $shops,
			'aps'   => $aps,
		);
	}
}
