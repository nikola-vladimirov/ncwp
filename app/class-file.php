<?php
/**
 * Cache file helpers.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes files in the plugin cache folder.
 */
class File {
	const CACHE_FOLDER = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'nashenski' . DIRECTORY_SEPARATOR;

	/**
	 * Writes data to a file without leaving a half-written file behind.
	 *
	 * @param string $file Full path of the file.
	 * @param string $data Data to write.
	 * @return bool True on success.
	 */
	public static function put_to_file( $file, $data ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Write beside the target, then rename, so a reader never sees a half-written file.
		$tmp = $file . '.' . wp_generate_password( 8, false ) . '.tmp';

		if ( ! $wp_filesystem->put_contents( $tmp, $data, FS_CHMOD_FILE ) ) {
			return false;
		}

		if ( ! $wp_filesystem->move( $tmp, $file, true ) ) {
			$wp_filesystem->delete( $tmp );
			return $wp_filesystem->put_contents( $file, $data, FS_CHMOD_FILE );
		}

		return true;
	}

	/**
	 * Moves a file, leaving an existing target alone.
	 *
	 * @param string $from Full path of the file.
	 * @param string $to   Full path of the new place.
	 * @return bool Whether the file was moved.
	 */
	public static function move( $from, $to ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		return $wp_filesystem->move( $from, $to, false );
	}

	/**
	 * Reads a file.
	 *
	 * @param string $file Full path of the file.
	 * @return string|false File contents, or an empty string when the file is missing.
	 */
	public static function get_file( $file ) {
		$data = '';

		if ( file_exists( $file ) ) {
			global $wp_filesystem;

			if ( ! $wp_filesystem ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$data = $wp_filesystem->get_contents( $file );
		}

		return $data;
	}
}
