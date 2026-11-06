<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.
/**
 * Origins.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client\BoxNow;

use Nashenski\Container\Client;
use Nashenski\Document_Storage;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * Origins.
 */
class Origins {
	const ORIGINS_ENDPOINT = '/api/v1/origins';

	/**
	 * Origins.
	 *
	 * @var array
	 */
	private $origins = array();
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
	 * Sender addresses: kept in the private documents folder (random name, deny rules), not the public cache folder.
	 *
	 * @return string
	 */
	private static function file() {
		return Document_Storage::path() . '/boxnow-origins.json';
	}

	/**
	 * Clear.
	 */
	public static function clear() {
		if ( file_exists( self::file() ) ) {
			wp_delete_file( self::file() );
		}
	}

	/**
	 * Load origins.
	 *
	 * @return mixed
	 */
	protected function load_origins() {
		$origins_file    = self::file();
		$old_public_file = $this->container[ Client::BOXNOW ]::CACHE_FOLDER . 'origins.json';

		if ( file_exists( $old_public_file ) ) {
			wp_delete_file( $old_public_file );
		}

		$origins = File::get_file( $origins_file );

		$failed_key = 'nashenski_boxnow_origins_failed';

		// A recent failed fetch is not retried on every request.
		if ( ! $origins && ! get_transient( $failed_key ) ) {
			$api_call = $this->container[ Client::BOXNOW ]->api_call( self::ORIGINS_ENDPOINT, array(), 'GET' );

			if ( is_array( $api_call ) && ! empty( $api_call['data'] ) ) {
				$origins = wp_json_encode( $api_call['data'] );

				File::put_to_file( $origins_file, $origins );
				unset( $api_call );
			} else {
				set_transient( $failed_key, 1, 5 * MINUTE_IN_SECONDS );
			}
		}

		$origins = json_decode( $origins, 1 );

		$this->set_origins( $origins );

		return $origins;
	}

	// Getters.

	/**
	 * Get origins.
	 *
	 * @return mixed
	 */
	public function get_origins() {
		if ( empty( $this->origins ) ) {
			$this->load_origins();
		}

		return $this->origins;
	}

	// Setters.

	/**
	 * Set origins.
	 *
	 * @param mixed $origins Origins.
	 */
	private function set_origins( $origins ) {
		$this->origins = $origins;
	}

	/**
	 * Get formatted origins.
	 *
	 * @return mixed
	 */
	public function get_formatted_origins() {
		$origins = $this->get_origins();
		$data    = array();

		if ( ! empty( $origins ) ) {
			foreach ( $origins as $origin ) {
				$address_line_2                      = ! empty( $origin['addressLine2'] ) ? str_replace( ' ', '', $origin['addressLine2'] ) : '';
				$data[ 'originID-' . $origin['id'] ] = array(
					'id'    => 'originID-' . $origin['id'],
					'type'  => $origin['type'] ?? '',
					'label' => $origin['name'] . ' (' . $address_line_2 . ' ' . $origin['addressLine1'] . ')',
				);
			}
		}

		$this->origins = array();
		unset( $origins );

		return $data;
	}
}
