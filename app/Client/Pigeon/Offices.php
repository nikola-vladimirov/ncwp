<?php
namespace Nashenski\Client\Pigeon;
use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

class Offices {
    const OFFICES_ENDPOINT = '/offices';

    private $offices = [];
    private $container;

	public function __construct( $container ) {
		$this->container = $container;
	}

	protected function load_offices( $city_id, $country_code = 'BG' ) {
		if ( ! is_dir( $this->container[ Client::PIGEON ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::PIGEON ]::CACHE_FOLDER );
		}

		$offices_file = $this->container[ Client::PIGEON ]::CACHE_FOLDER . 'offices-' . $city_id . '.json';
		$offices = File::get_file( $offices_file );

		if ( !$offices ) {
			$args = apply_filters( 'nashenski/pigeon/offices/api_call_args', array(
				'city_id' => $city_id,
				'type' => 'office',
			), $city_id, $country_code );
			
			$all_offices = [];
			$complete = false;

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

			if ( !empty( $all_offices ) ) {
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

	//Getters
	public function get_page( $page = 1, $args = array() ) {
		return $this->container[ Client::PIGEON ]->api_call( self::OFFICES_ENDPOINT, array_merge( $args, [
			'page' => $page,
		] ) );
	}

	public function get_offices( $city_id, $country_code = 'BG' ) {
		if ( empty( $this->offices[ $city_id ] ) ) {
			$this->load_offices( $city_id, $country_code );
		}

		return $this->offices[ $city_id ];
	}

	//Setters
	private function set_offices( $city_id, $offices ) {
		$this->offices[ $city_id ] = $offices;
	}

	public function get_formatted_offices( $city, $country_code = 'BG' ) {
		$city_id = str_replace( 'cityID-', '', $city );
		$offices = $this->get_offices( $city_id, $country_code );
		$data = [];

		if ( !empty( $offices ) ) {
			foreach ( $offices as $office ) {
				$data[ 'officeID-' . $office['id']] = [
					'id' => 'officeID-' . $office['id'],
					'label' => $office['name'] . ' (' . $office['address'] . ')',
				];
			}
		}

		unset( $this->offices[ $city_id ], $offices );

		return $data;
	}
}
