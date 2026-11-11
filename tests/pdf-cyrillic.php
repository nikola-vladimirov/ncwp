<?php
/**
 * Offline: a large order PDF keeps its Bulgarian text (dompdf must not guess ASCII). Run: php tests/pdf-cyrillic.php
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Universal.Files.SeparateFunctionsFromOO.Mixed -- an offline test script: it stubs a plugin class and WordPress functions in one file.

namespace Nashenski\Invoice\PDF {
	/**
	 * Stub of the PDF appearance settings.
	 */
	class Style {
		/**
		 * The settings: A4 paper.
		 *
		 * @return array
		 */
		public static function get() {
			return array( 'paper' => 'A4' ); }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );

	/**
	 * Stub: return the value unfiltered.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		return $value; }

	/**
	 * Read the parsed document during layout; dompdf frees it after rendering.
	 *
	 * @param string         $hook   Hook name.
	 * @param \Dompdf\Dompdf $dompdf PDF renderer.
	 */
	function do_action( $hook, $dompdf ) {
		$dompdf->setCallbacks(
			array(
				array(
					'event' => 'begin_page_reflow',
					'f'     => function () use ( $dompdf ) {
						$GLOBALS['text'] = $dompdf->getDom()->textContent;
					},
				),
			)
		);
	}
	/**
	 * Stub: uploads in a temp folder, so the font metrics cache lands there.
	 *
	 * @return array
	 */
	function wp_upload_dir() {
		return array( 'basedir' => sys_get_temp_dir() . '/nashenski-pdf-' . getmypid() ); }

	/**
	 * Stub.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function trailingslashit( $path ) {
		return rtrim( $path, '/\\' ) . '/'; }

	/**
	 * Stub.
	 *
	 * @param string $path Folder.
	 * @return bool
	 */
	function wp_mkdir_p( $path ) {
		return is_dir( $path ) || mkdir( $path, 0777, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- offline test, WordPress is not loaded.
	}
	require_once __DIR__ . '/fixtures/hooks.php';
	require __DIR__ . '/../vendor/autoload.php';
	require_once __DIR__ . '/../app/autoload.php';
	require __DIR__ . '/../app/Invoice/PDF/class-pdf.php';

	$word     = 'Доставка до офис на Еконт';
	$template = tempnam( sys_get_temp_dir(), 'nash' );
	// About 1 MB of ASCII first (a logo data URI does this in real documents), then Cyrillic: PHP 8.3+ "auto" detection then says ASCII.
	file_put_contents( $template, '<html><body><!-- ' . str_repeat( 'A', 1200000 ) . ' --><p>' . $word . '</p></body></html>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- offline test, WordPress is not loaded.

	$pdf = new Nashenski\Invoice\PDF\PDF( null );
	$pdf->set_template( $template );
	$out = $pdf->generate();
	unlink( $template ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- offline test, WordPress is not loaded.

	$text = $GLOBALS['text'] ?? '';
	if ( '%PDF' !== substr( $out, 0, 4 ) ) {
		throw new RuntimeException( 'No PDF output.' ); }
	if ( false === strpos( $text, $word ) ) {
		throw new RuntimeException( 'Cyrillic text was lost while loading the HTML.' ); }
	$cache = glob( Nashenski\File::cache_folder( 'fonts' ) . '*.json' );
	if ( ! $cache ) {
		throw new RuntimeException( 'The font metrics cache is not in uploads.' ); }
	if ( glob( __DIR__ . '/../vendor/dompdf/dompdf/lib/fonts/*.[au]fm.json' ) ) {
		throw new RuntimeException( 'The font metrics cache was written into the plugin folder.' ); }
	array_map( 'unlink', $cache );
	// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- offline test, WordPress is not loaded.
	rmdir( Nashenski\File::cache_folder( 'fonts' ) );
	rmdir( Nashenski\File::cache_folder() );
	rmdir( wp_upload_dir()['basedir'] );
	// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	echo "OK pdf-cyrillic: 4 checks\n";
}
