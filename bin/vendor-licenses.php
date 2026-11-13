<?php
/**
 * Gives the license files in vendor/ a .txt name (LICENSE -> LICENSE.txt, LICENSE.LGPL ->
 * LICENSE-LGPL.txt). WordPress.org accepts only common file types in a plugin, and the license
 * texts must ship. Then makes the PDF library print its built-in font families in the bundled
 * DejaVu fonts (see the end of the file). Composer runs this after every install, update and
 * dump-autoload; running it again changes nothing.
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

/*
 * The PDF library's built-in font families (Helvetica, Times, Courier, Symbol, ZapfDingbats and the
 * generic names) print in the DejaVu fonts that come with it: every Dompdf made from this folder,
 * in an add-on too, then needs no metric file (.afm) of the built-in fonts, which are left out of
 * the release and have no Cyrillic. The library reads the families from this list.
 */
$nashenski_list  = __DIR__ . '/../vendor/dompdf/dompdf/lib/fonts/installed-fonts.dist.json';
$nashenski_fonts = is_readable( $nashenski_list ) ? json_decode( (string) file_get_contents( $nashenski_list ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file, read by a Composer script on the command line, without WordPress.

if ( is_array( $nashenski_fonts ) ) {
	foreach ( array(
		'sans-serif'   => 'dejavu sans',
		'helvetica'    => 'dejavu sans',
		'symbol'       => 'dejavu sans',
		'zapfdingbats' => 'dejavu sans',
		'serif'        => 'dejavu serif',
		'times'        => 'dejavu serif',
		'times-roman'  => 'dejavu serif',
		'monospace'    => 'dejavu sans mono',
		'fixed'        => 'dejavu sans mono',
		'courier'      => 'dejavu sans mono',
	) as $nashenski_family => $nashenski_dejavu ) {
		if ( isset( $nashenski_fonts[ $nashenski_family ], $nashenski_fonts[ $nashenski_dejavu ] ) ) {
			$nashenski_fonts[ $nashenski_family ] = $nashenski_fonts[ $nashenski_dejavu ];
		}
	}

	file_put_contents( $nashenski_list, json_encode( $nashenski_fonts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.json_encode_json_encode -- a Composer script on the command line, without WordPress.
}
