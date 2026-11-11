<?php
/**
 * Courier client.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client;

use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * Shared by the courier API clients: the demo or live endpoint (clients with DEMO_URL and
 * LIVE_URL), the courier's nested error messages and the cache folder.
 */
trait Courier_Client {
	/**
	 * Load base endpoint.
	 */
	public function load_base_endpoint() {
		$this->set_base_endpoint( 'live' === $this->get_env() ? self::LIVE_URL : self::DEMO_URL );
	}

	/**
	 * Add error message.
	 *
	 * @param mixed $error Error.
	 * @return mixed
	 */
	public static function add_error_message( $error ) {
		$message = array();

		if ( ! empty( $error['message'] ) && ' ' !== $error['message'] ) {
			$message[] = $error['message'];
		}

		if ( ! empty( $error['innerErrors'] ) ) {
			foreach ( $error['innerErrors'] as $error ) {
				$message = array_merge( $message, self::add_error_message( $error ) );
			}
		}

		return array_filter( $message );
	}

	/**
	 * The client's cache folder.
	 *
	 * @return string Path with a trailing separator.
	 */
	public static function cache_folder() {
		return File::cache_folder( static::CACHE_NAME );
	}

	/**
	 * Delete the cached cities, offices and so on. With $skip_cached_files, keep the files listed
	 * in the client's $skip_cached_files.
	 *
	 * @param bool $skip_cached_files Skip cached files.
	 */
	public static function clear_cache_folder( $skip_cached_files = false ) {
		$filesystem = File::filesystem();
		$keep       = $skip_cached_files && isset( static::$skip_cached_files ) ? static::$skip_cached_files : array();

		// dirlist() is false when the folder was never made (no data loaded yet).
		$listing = $filesystem->dirlist( static::cache_folder() );
		foreach ( $listing ? $listing : array() as $file ) {
			if ( ! in_array( $file['name'], $keep, true ) ) {
				$filesystem->delete( static::cache_folder() . $file['name'] );
			}
		}
	}
}
