<?php
namespace Nashenski\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Shared by the courier API clients: the demo or live endpoint (clients with DEMO_URL and
 * LIVE_URL), the courier's nested error messages and the cache folder.
 */
trait Courier_Client {
	public function load_base_endpoint() {
		$this->set_base_endpoint( 'live' == $this->get_env() ? self::LIVE_URL : self::DEMO_URL );
	}

	public static function add_error_message( $error ) {
		$message = [];

		if ( $error['message'] && $error['message'] !== ' ' ) {
			$message[] = $error['message'];
		}

		if ( !empty( $error['innerErrors'] ) ) {
			foreach ( $error['innerErrors'] as $error ) {
				$message = array_merge( $message, self::add_error_message( $error ) );
			}
		}

		return array_filter( $message );
	}

	/**
	 * Delete the cached cities, offices and so on. With $skip_cached_files, keep the files listed
	 * in the client's $skip_cached_files.
	 */
	public static function clear_cache_folder( $skip_cached_files = false ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		$keep = $skip_cached_files && isset( static::$skip_cached_files ) ? static::$skip_cached_files : array();

		foreach ( (array) $wp_filesystem->dirlist( self::CACHE_FOLDER ) as $file ) {
			if ( ! in_array( $file['name'], $keep ) ) {
				$wp_filesystem->delete( self::CACHE_FOLDER . DIRECTORY_SEPARATOR . $file['name'] );
			}
		}
	}
}
