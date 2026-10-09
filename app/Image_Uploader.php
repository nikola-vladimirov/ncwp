<?php
namespace Nashenski;

defined( 'ABSPATH' ) || exit;

class Image_Uploader {
	public static function change_upload_dir( $args ) {
		$new_folder = "/nashenski";

		$args[ 'path' ] = $args['basedir'] . $new_folder;
		$args[ 'url' ] = $args['baseurl'] . $new_folder;
		$args[ 'subdir' ] = $new_folder;

		return $args;
	}
}
