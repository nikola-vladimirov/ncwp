<?php
/**
 * The Econt offices of a city, cached in a file per city.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client\Econt;

use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * The Econt offices of a city, cached in a file per city.
 */
class Offices {
	const OFFICES_ENDPOINT = 'Nomenclatures/NomenclaturesService.getOffices.json';

	/**
	 * Offices by city ID, loaded on first use.
	 *
	 * @var array
	 */
	private $offices = array();

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
	 * Reads the offices of a city from the cache file, or from Econt when there is none.
	 *
	 * @param string $city_id      Econt city ID.
	 * @param string $country_code Country code.
	 * @return array
	 */
	protected function load_offices( $city_id, $country_code = 'BG' ) {
		if ( ! is_dir( $this->container[ Client::ECONT ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::ECONT ]::CACHE_FOLDER );
		}

		$offices_file = $this->container[ Client::ECONT ]::CACHE_FOLDER . 'offices-' . $city_id . '.json';
		$offices      = File::get_file( $offices_file );

		if ( ! $offices ) {
			$args = apply_filters(
				'nashenski/econt/offices/api_call_args', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-separated hook names are the plugin's public API.
				array(
					'countryCode' => 'BGR',
					'cityID'      => $city_id,
				),
				$city_id,
				$country_code
			);

			$api_call = $this->container[ Client::ECONT ]->api_call( self::OFFICES_ENDPOINT, $args );

			if ( is_array( $api_call ) ) {
				if ( $this->container[ Client::ECONT ]::validate_access( $api_call ) ) {
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
	 * @param string $city_id      Econt city ID.
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_offices( $city_id, $country_code = 'BG' ) {
		if ( empty( $this->offices[ $city_id ] ) ) {
			$this->load_offices( $city_id, $country_code );
		}

		return $this->offices[ $city_id ];
	}

	/**
	 * Keeps the offices of a city.
	 *
	 * @param string $city_id Econt city ID.
	 * @param array  $offices Offices.
	 * @return void
	 */
	private function set_offices( $city_id, $offices ) {
		$this->offices[ $city_id ] = $offices;
	}

	/**
	 * The offices of a city as picker choices: shops and automatic parcel stations (APS).
	 *
	 * @param string $city         City value as "cityID-<ID>".
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_formatted_offices( $city, $country_code = 'BG' ) {
		$city_id = str_replace( 'cityID-', '', $city );
		$offices = $this->get_offices( $city_id, $country_code );
		$shops   = array();
		$aps     = array();

		if ( ! empty( $offices['offices'] ) ) {
			foreach ( $offices['offices'] as $office ) {
				$data = array(
					'id'    => 'officeID-' . $office['code'],
					'label' => $office['name'] . ' (' . $office['address']['fullAddress'] . ')',
				);

				if ( $office['isAPS'] ) {
					$aps[ 'officeID-' . $office['code'] ] = $data;
				} else {
					$shops[ 'officeID-' . $office['code'] ] = $data;
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
