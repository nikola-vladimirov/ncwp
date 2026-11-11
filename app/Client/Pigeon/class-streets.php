<?php
/**
 * Pigeon Express streets.
 *
 * @package Nashenski
 */

namespace Nashenski\Client\Pigeon;

use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * Pigeon Express streets of a city, cached in files.
 */
class Streets {
	const STREETS_ENDPOINT = '/cities/{cityID}/streets';

	/**
	 * Streets by city id and query hash.
	 *
	 * @var array
	 */
	private $streets = array();

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
	 * Load the streets of a city matching the query, from the cache or the API.
	 *
	 * @param int|string $city_id City id.
	 * @param string     $query   Query.
	 * @return array|null
	 */
	protected function load_streets_by_city( $city_id, $query = '' ) {
		if ( ! is_dir( $this->container[ Client::PIGEON ]::cache_folder() ) ) {
			wp_mkdir_p( $this->container[ Client::PIGEON ]::cache_folder() );
		}

		$query        = mb_substr( (string) $query, 0, 64 );
		$hash         = md5( $query );
		$streets_file = $this->container[ Client::PIGEON ]::cache_folder() . 'streets-' . $city_id . '-' . $hash . '.json';
		$streets      = File::get_file( $streets_file );

		$miss_key = 'nashenski_miss_' . md5( $streets_file );

		// An empty answer is remembered for 5 minutes, so the same lookup does not call the courier again.
		if ( ! $streets && ! get_transient( $miss_key ) ) {
			$all_streets = array();
			$complete    = false;

			// Cap the pages; cache only when every page answered.
			for ( $page = 1; $page <= 200; $page++ ) {
				$data = $this->get_page( $city_id, $page, $query );

				if ( empty( $data['success'] ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
					break;
				}

				$all_streets = array_merge( $all_streets, $data['data'] );

				if ( $page >= (int) ( $data['meta']['last_page'] ?? 1 ) ) {
					$complete = true;
					break;
				}
			}

			if ( ! empty( $all_streets ) ) {
				$streets = wp_json_encode( $all_streets );

				if ( $complete ) {
					File::put_to_file( $streets_file, $streets );
				}
				unset( $all_streets, $data );
			} else {
				set_transient( $miss_key, 1, 5 * MINUTE_IN_SECONDS );
			}
		}

		$streets = json_decode( $streets, 1 );

		$this->set_streets( $streets, $city_id, $hash );

		return $streets;
	}

	/**
	 * One page of a city's streets from the API.
	 *
	 * @param int|string $city_id City id.
	 * @param int        $page    Page.
	 * @param string     $query   Query.
	 * @return array
	 */
	public function get_page( $city_id, $page = 1, $query = '' ) {
		$endpoint = str_replace( '{cityID}', $city_id, self::STREETS_ENDPOINT );
		$args     = array(
			'city_id' => $city_id,
			'page'    => $page,
		);

		if ( ! empty( $query ) ) {
			$args['name'] = $query;
		}

		return $this->container[ Client::PIGEON ]->api_call( $endpoint, $args );
	}

	/**
	 * Streets of a city matching the query.
	 *
	 * @param int|string $city_id City id.
	 * @param string     $query   Query.
	 * @return array|null
	 */
	public function get_streets_by_city( $city_id, $query ) {
		$query = mb_substr( (string) $query, 0, 64 );
		$hash  = md5( $query );

		if ( empty( $this->streets[ $city_id ][ $hash ] ) ) {
			$this->load_streets_by_city( $city_id, $query );
		}

		return $this->streets[ $city_id ][ $hash ];
	}

	/**
	 * Keep the streets of a city and query.
	 *
	 * @param array|null $streets Streets.
	 * @param int|string $city_id City id.
	 * @param string     $hash    Query hash.
	 */
	private function set_streets( $streets, $city_id, $hash ) {
		$this->streets[ $city_id ][ $hash ] = $streets;
	}

	/**
	 * Streets as "street-id" => "type name".
	 *
	 * @param array $streets Streets.
	 * @return array
	 */
	public function format_streets( $streets ) {
		$formatted = array();

		foreach ( $streets as $street ) {
			$formatted[ 'street-' . $street['id'] ] = self::format_type( $street['type'] ) . ' ' . nashenski_title_case_bg( $street['name'] );
		}

		return $formatted;
	}

	/**
	 * The street type shortened: ул., бул. and so on.
	 *
	 * @param string $type Street type.
	 * @return string
	 */
	public static function format_type( $type ) {
		if ( ! $type ) {
			return '';
		}

		return str_replace(
			array( 'улица', 'булевард', 'площад', 'жилищен комплекс', 'квартал', 'район', 'курортен комплекс' ),
			array( 'ул.', 'бул.', 'пл.', 'ж.к.', 'кв.', 'р-н', 'к.к.' ),
			$type
		);
	}
}
