<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloader maps the class name to the file name.
/**
 * Upload folder for plugin images.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski;

defined( 'ABSPATH' ) || exit;

/**
 * Moves plugin image uploads into their own folder.
 */
class Image_Uploader {
	/**
	 * Points the upload folder to uploads/nashenski.
	 *
	 * @param array $args Upload folder data from the upload_dir filter.
	 * @return array
	 */
	public static function change_upload_dir( $args ) {
		$new_folder = '/nashenski';

		$args['path']   = $args['basedir'] . $new_folder;
		$args['url']    = $args['baseurl'] . $new_folder;
		$args['subdir'] = $new_folder;

		return $args;
	}
}
