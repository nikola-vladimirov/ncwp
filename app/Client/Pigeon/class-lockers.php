<?php
/**
 * The Pigeon lockers of a city, cached in a file per city.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client\Pigeon;

use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * The Pigeon lockers of a city, cached in a file per city.
 */
class Lockers {
	const LOCKERS_ENDPOINT = '/offices';

	/**
	 * Lockers by city ID, loaded on first use.
	 *
	 * @var array
	 */
	private $lockers = array();

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
	 * Reads the lockers of a city from the cache file, or from Pigeon when there is none.
	 *
	 * @param string $city_id      Pigeon city ID.
	 * @param string $country_code Country code.
	 * @return array
	 */
	protected function load_lockers( $city_id, $country_code = 'BG' ) {
		if ( ! is_dir( $this->container[ Client::PIGEON ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::PIGEON ]::CACHE_FOLDER );
		}

		$lockers_file = $this->container[ Client::PIGEON ]::CACHE_FOLDER . 'lockers-' . $city_id . '.json';
		$lockers      = File::get_file( $lockers_file );

		if ( ! $lockers ) {
			$args = apply_filters(
				'nashenski/pigeon/lockers/api_call_args', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-separated hook names are the plugin's public API.
				array(
					'city_id' => $city_id,
					'type'    => 'locker',
				),
				$city_id,
				$country_code
			);

			$all_lockers = array();
			$complete    = false;

			// Cap the pages; cache only when every page answered.
			for ( $page = 1; $page <= 200; $page++ ) {
				$data = $this->get_page( $page, $args );

				if ( empty( $data['success'] ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
					break;
				}

				$all_lockers = array_merge( $all_lockers, $data['data'] );

				if ( $page >= (int) ( $data['meta']['last_page'] ?? 1 ) ) {
					$complete = true;
					break;
				}
			}

			if ( ! empty( $all_lockers ) ) {
				$lockers = wp_json_encode( $all_lockers );

				if ( $complete ) {
					File::put_to_file( $lockers_file, $lockers );
				}
				unset( $all_lockers, $data );
			}
		}

		$lockers = json_decode( $lockers, 1 );

		$this->set_lockers( $city_id, $lockers );

		return $lockers;
	}

	/**
	 * One page of lockers from Pigeon.
	 *
	 * @param int   $page Page number.
	 * @param array $args Request arguments.
	 * @return mixed
	 */
	public function get_page( $page = 1, $args = array() ) {
		return $this->container[ Client::PIGEON ]->api_call(
			self::LOCKERS_ENDPOINT,
			array_merge(
				$args,
				array(
					'page' => $page,
				)
			)
		);
	}

	/**
	 * The lockers of a city.
	 *
	 * @param string $city_id      Pigeon city ID.
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_lockers( $city_id, $country_code = 'BG' ) {
		if ( empty( $this->lockers[ $city_id ] ) ) {
			$this->load_lockers( $city_id, $country_code );
		}

		return $this->lockers[ $city_id ];
	}

	/**
	 * Keeps the lockers of a city.
	 *
	 * @param string $city_id Pigeon city ID.
	 * @param array  $lockers Lockers.
	 * @return void
	 */
	private function set_lockers( $city_id, $lockers ) {
		$this->lockers[ $city_id ] = $lockers;
	}

	/**
	 * The lockers of a city as picker choices.
	 *
	 * @param string $city         City value as "cityID-<ID>".
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_formatted_lockers( $city, $country_code = 'BG' ) {
		$city_id = str_replace( 'cityID-', '', $city );
		$lockers = $this->get_lockers( $city_id, $country_code );
		$data    = array();

		if ( ! empty( $lockers ) ) {
			foreach ( $lockers as $locker ) {
				$data[ 'lockerID-' . $locker['id'] ] = array(
					'id'    => 'lockerID-' . $locker['id'],
					'label' => $locker['name'] . ' (' . $locker['address'] . ')',
				);
			}
		}

		unset( $this->lockers[ $city_id ], $lockers );

		return $data;
	}
}
