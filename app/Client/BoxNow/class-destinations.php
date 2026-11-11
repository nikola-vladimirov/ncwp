<?php
/**
 * Destinations.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client\BoxNow;

use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * Destinations.
 */
class Destinations {
	const DESTINATIONS_ENDPOINT = '/api/v1/destinations';

	/** BOX NOW's public locker list (stage or production), no login needed. BOX NOW API manual 4.3.1. */
	const PUBLIC_URL = 'https://locationapi-%s.boxnow.bg/v1/apms_bg-BG.json';

	/**
	 * Destinations.
	 *
	 * @var array
	 */
	private $destinations = array();
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
	 * Load destinations.
	 *
	 * @param mixed $size Size.
	 * @return mixed
	 */
	protected function load_destinations( $size ) {
		$box = $this->container[ Client::BOXNOW ];

		if ( ! is_dir( $box::cache_folder() ) ) {
			wp_mkdir_p( $box::cache_folder() );
		}

		// Demo and live lockers differ (the demo has two test lockers), so each has its own file.
		$destinations_file = $box::cache_folder() . 'destinations-' . ( 'live' === $box->get_env() ? 'live' : 'demo' ) . '-' . $size . '.json';
		$destinations      = File::get_file( $destinations_file );

		$failed_key = 'nashenski_boxnow_dest_failed_' . md5( $destinations_file );

		// A recent failed fetch is not retried on every request.
		if ( ! $destinations && ! get_transient( $failed_key ) ) {
			$list = array();

			// Only BOX NOW's API filters by compartment size; the public list has no sizes.
			if ( $size && 'all' !== $size ) {
				$api_call = $box->api_call( self::DESTINATIONS_ENDPOINT, array( 'requiredSize' => $size ), 'GET' );
				$list     = is_array( $api_call ) && ! empty( $api_call['data'] ) ? $api_call['data'] : array();
			}

			// ponytail: when the API refuses the login, a sized cart also gets the full list; the label
			// request fails anyway until the login works.
			if ( ! $list ) {
				$response = wp_remote_get( sprintf( self::PUBLIC_URL, 'live' === $box->get_env() ? 'production' : 'stage' ), array( 'timeout' => 20 ) );
				$public   = json_decode( wp_remote_retrieve_body( $response ), true );
				$list     = is_array( $public ) && ! empty( $public['data'] ) ? $public['data'] : array();
			}

			if ( $list ) {
				$destinations = wp_json_encode( $list );
				File::put_to_file( $destinations_file, $destinations );
			} else {
				set_transient( $failed_key, 1, 5 * MINUTE_IN_SECONDS );
			}
		}

		$destinations = json_decode( $destinations, 1 );

		$this->set_destinations( $size, $destinations );

		return $destinations;
	}

	// Getters.

	/**
	 * Get destinations.
	 *
	 * @param string $size Size.
	 * @return mixed
	 */
	public function get_destinations( $size = '' ) {
		$size = ( ! $size ) ? 'all' : $size;

		if ( empty( $this->destinations[ $size ] ) ) {
			$this->load_destinations( $size );
		}

		return $this->destinations[ $size ];
	}

	// Setters.

	/**
	 * Set destinations.
	 *
	 * @param mixed $size         Size.
	 * @param mixed $destinations Destinations.
	 */
	private function set_destinations( $size, $destinations ) {
		$this->destinations[ $size ] = $destinations;
	}
}
