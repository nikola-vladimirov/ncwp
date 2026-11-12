<?php
/**
 * Gives the license files in vendor/ a .txt name (LICENSE -> LICENSE.txt, LICENSE.LGPL ->
 * LICENSE-LGPL.txt). WordPress.org accepts only common file types in a plugin, and the license
 * texts must ship. Composer runs this after every install, update and dump-autoload.
 *
 * Run: php bin/vendor-licenses.php
 *
 * @package Nashenski_Commerce
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

$nashenski_vendor = dirname( __DIR__ ) . '/vendor';

$nashenski_files = array();

// One glob per pattern: GLOB_BRACE does not exist on every system, and glob() can return false.
foreach ( array( '/*/LICENSE*', '/*/NOTICE*', '/*/*/LICENSE*', '/*/*/NOTICE*' ) as $nashenski_pattern ) {
	$nashenski_found = glob( $nashenski_vendor . $nashenski_pattern );
	$nashenski_files = array_merge( $nashenski_files, $nashenski_found ? $nashenski_found : array() );
}

foreach ( $nashenski_files as $nashenski_file ) {
	$nashenski_name = basename( $nashenski_file );

	if ( ! preg_match( '/\.(txt|md)$/i', $nashenski_name ) ) {
		// A dot before a capital letter is a file type (LICENSE.LGPL), not a version (LICENSE-ASL-2.0).
		rename( $nashenski_file, dirname( $nashenski_file ) . '/' . preg_replace( '/\.(?=[A-Z])/', '-', $nashenski_name ) . '.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- a Composer script on the command line, without WordPress.
	}
}
