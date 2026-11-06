<?php
/**
 * Pigeon Express offices lookup.
 *
 * @package Nashenski
 */

namespace Nashenski\Client\Pigeon;

use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the Pigeon Express offices of a city and keeps the answers in a cache file.
 */
class Offices {
	const OFFICES_ENDPOINT = '/offices';

	/**
	 * Loaded offices by city.
	 *
	 * @var array
	 */
	private $offices = array();

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
	 * Loads the offices of a city from the cache file or from Pigeon Express.
	 *
	 * @param string|int $city_id      City id.
	 * @param string     $country_code Country code.
	 * @return array
	 */
	protected function load_offices( $city_id, $country_code = 'BG' ) {
		if ( ! is_dir( $this->container[ Client::PIGEON ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::PIGEON ]::CACHE_FOLDER );
		}

		$offices_file = $this->container[ Client::PIGEON ]::CACHE_FOLDER . 'offices-' . $city_id . '.json';
		$offices      = File::get_file( $offices_file );

		if ( ! $offices ) {
			$args = \Nashenski\Hooks::filter(
				'nashenski_pigeon_offices_api_call_args',
				'nashenski/pigeon/offices/api_call_args',
				array(
					'city_id' => $city_id,
					'type'    => 'office',
				),
				$city_id,
				$country_code
			);

			$all_offices = array();
			$complete    = false;

			// Cap the pages; cache only when every page answered.
			for ( $page = 1; $page <= 200; $page++ ) {
				$data = $this->get_page( $page, $args );

				if ( empty( $data['success'] ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
					break;
				}

				$all_offices = array_merge( $all_offices, $data['data'] );

				if ( $page >= (int) ( $data['meta']['last_page'] ?? 1 ) ) {
					$complete = true;
					break;
				}
			}

			if ( ! empty( $all_offices ) ) {
				$offices = wp_json_encode( $all_offices );

				if ( $complete ) {
					File::put_to_file( $offices_file, $offices );
				}
				unset( $all_offices, $data );
			}
		}

		$offices = json_decode( $offices, 1 );

		$this->set_offices( $city_id, $offices );

		return $offices;
	}

	/**
	 * Asks Pigeon Express for one page of offices.
	 *
	 * @param int   $page Page number.
	 * @param array $args Query arguments.
	 * @return array|null
	 */
	public function get_page( $page = 1, $args = array() ) {
		return $this->container[ Client::PIGEON ]->api_call(
			self::OFFICES_ENDPOINT,
			array_merge(
				$args,
				array(
					'page' => $page,
				)
			)
		);
	}

	/**
	 * Gets the offices of a city.
	 *
	 * @param string|int $city_id      City id.
	 * @param string     $country_code Country code.
	 * @return array
	 */
	public function get_offices( $city_id, $country_code = 'BG' ) {
		if ( empty( $this->offices[ $city_id ] ) ) {
			$this->load_offices( $city_id, $country_code );
		}

		return $this->offices[ $city_id ];
	}

	/**
	 * Remembers the offices of a city.
	 *
	 * @param string|int $city_id City id.
	 * @param array      $offices Offices.
	 */
	private function set_offices( $city_id, $offices ) {
		$this->offices[ $city_id ] = $offices;
	}

	/**
	 * Gets the offices of a city as id and label.
	 *
	 * @param string $city         City value, cityID-123.
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_formatted_offices( $city, $country_code = 'BG' ) {
		$city_id = str_replace( 'cityID-', '', $city );
		$offices = $this->get_offices( $city_id, $country_code );
		$data    = array();

		if ( ! empty( $offices ) ) {
			foreach ( $offices as $office ) {
				$data[ 'officeID-' . $office['id'] ] = array(
					'id'    => 'officeID-' . $office['id'],
					'label' => $office['name'] . ' (' . $office['address'] . ')',
				);
			}
		}

		unset( $this->offices[ $city_id ], $offices );

		return $data;
	}
}
