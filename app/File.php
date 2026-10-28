<?php
namespace Nashenski;

defined( 'ABSPATH' ) || exit;

class File {
	const CACHE_FOLDER = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'nashenski' . DIRECTORY_SEPARATOR;

	public static function put_to_file( $file, $data ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			WP_Filesystem();
		}

		// Write beside the target, then rename, so a reader never sees a half-written file.
		$tmp = $file . '.' . wp_generate_password( 8, false ) . '.tmp';

		if ( ! $wp_filesystem->put_contents( $tmp, $data, FS_CHMOD_FILE ) ) {
			return false;
		}

		if ( ! @rename( $tmp, $file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- same folder; Windows may refuse to overwrite.
			$wp_filesystem->delete( $tmp );
			return $wp_filesystem->put_contents( $file, $data, FS_CHMOD_FILE );
		}

		return true;
	}

	public static function get_file( $file ) {
		$data = '';

		if ( file_exists( $file ) ) {
			global $wp_filesystem;

			if ( ! $wp_filesystem ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
				WP_Filesystem();
			}

			$data = $wp_filesystem->get_contents( $file );
		}

		return $data;
	}
}