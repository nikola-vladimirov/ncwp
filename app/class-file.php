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
	/**
	 * The courier cache folder in uploads (its own per site on multisite), or a subfolder of it.
	 * It holds public courier data only: countries, cities, quarters, streets, offices and lockers.
	 *
	 * @param string $sub Subfolder, for example the courier name.
	 * @return string Path with a trailing separator.
	 */
	public static function cache_folder( $sub = '' ) {
		$folder = trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'nashenski-cache' . DIRECTORY_SEPARATOR;

		return '' === $sub ? $folder : $folder . $sub . DIRECTORY_SEPARATOR;
	}

	/**
	 * WordPress's direct file access. The plugin only touches its own local files (uploads, temp
	 * files), and WP_Filesystem() fails on hosts set to FTP without FTP details, which left an
	 * unconnected object that ends in a TypeError.
	 *
	 * @return \WP_Filesystem_Direct
	 */
	public static function filesystem() {
		if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		}

		// WP_Filesystem() sets these only after it connects; the direct class reads them for new files.
		if ( ! defined( 'FS_CHMOD_DIR' ) ) {
			define( 'FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0755 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress's own constant, set the way WP_Filesystem() sets it.
		}
		if ( ! defined( 'FS_CHMOD_FILE' ) ) {
			define( 'FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0644 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress's own constant, set the way WP_Filesystem() sets it.
		}

		return new \WP_Filesystem_Direct( null );
	}

	/**
	 * Writes data to a file without leaving a half-written file behind.
	 *
	 * @param string $file Full path of the file.
	 * @param string $data Data to write.
	 * @return bool True on success.
	 */
	public static function put_to_file( $file, $data ) {
		$filesystem = self::filesystem();

		// Write beside the target, then rename, so a reader never sees a half-written file.
		$tmp = $file . '.' . wp_generate_password( 8, false ) . '.tmp';

		if ( ! $filesystem->put_contents( $tmp, $data, FS_CHMOD_FILE ) ) {
			return false;
		}

		if ( ! @rename( $tmp, $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- the rename replaces the file in one step; WP_Filesystem::move() deletes the target first, so a reader could see no file. Windows may refuse; the fallback below writes it directly.
			$filesystem->delete( $tmp );
			return $filesystem->put_contents( $file, $data, FS_CHMOD_FILE );
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
		return self::filesystem()->move( $from, $to, false );
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
			$data = self::filesystem()->get_contents( $file );
		}

		return $data;
	}
}
