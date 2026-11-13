<?php
/**
 * Offline: a large order PDF keeps its Bulgarian text (dompdf must not guess ASCII) in the bundled
 * DejaVu font, a custom template's @font-face font is installed in uploads, not in the plugin
 * folder, and the logo is read from its file only once its folder is allowed. Run: php tests/pdf-cyrillic.php
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
	if ( false === strpos( $out, 'DejaVuSans' ) ) {
		throw new RuntimeException( 'The bundled DejaVu font was not used.' ); }
	$cache = glob( Nashenski\File::cache_folder( 'fonts' ) . '*.json' );
	if ( ! $cache ) {
		throw new RuntimeException( 'The font metrics cache is not in uploads.' ); }
	if ( glob( __DIR__ . '/../vendor/dompdf/dompdf/lib/fonts/*.[au]fm.json' ) ) {
		throw new RuntimeException( 'The font metrics cache was written into the plugin folder.' ); }

	// A custom template with its own font: Dompdf installs it (installed-fonts.json and the font files) in the font folder.
	$font = str_replace( '\\', '/', realpath( __DIR__ . '/../vendor/dompdf/dompdf/lib/fonts/DejaVuSerif.ttf' ) );
	file_put_contents( $template, '<html><head><style>@font-face{font-family:"nashtest";src:url("' . $font . '")} p{font-family:nashtest}</style></head><body><p>' . $word . '</p></body></html>' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- offline test, WordPress is not loaded.
	$pdf->set_template( $template );
	$out = $pdf->generate();
	unlink( $template ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- offline test, WordPress is not loaded.
	if ( ! is_file( Nashenski\File::cache_folder( 'fonts' ) . 'installed-fonts.json' ) ) {
		throw new RuntimeException( 'The custom font was not installed in uploads.' ); }
	if ( is_file( __DIR__ . '/../vendor/dompdf/dompdf/lib/fonts/installed-fonts.json' ) ) {
		throw new RuntimeException( 'The custom font was installed into the plugin folder.' ); }
	// The built-in families (Helvetica, Times, Courier, the generic names) print in DejaVu: their metric files are not shipped, and they have no Cyrillic.
	$html = '<html><body><p style="font-family:Helvetica">' . $word . '</p><p style="font-family:serif"><b>' . $word . '</b></p><pre>' . $word . '</pre><p style="font-family:Courier"><i>' . $word . '</i></p></body></html>';
	file_put_contents( $template, $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- offline test, WordPress is not loaded.
	$pdf->set_template( $template );
	$out = $pdf->generate();
	if ( preg_match( '~/BaseFont\s*/(Helvetica|Times|Courier|Symbol|ZapfDingbats)~', $out ) ) {
		throw new RuntimeException( 'A built-in font was used: its metric file is not in the release.' ); }
	if ( false === strpos( $out, 'DejaVuSerif-Bold' ) || false === strpos( $out, 'DejaVuSansMono' ) ) {
		throw new RuntimeException( 'The built-in families did not print in DejaVu.' ); }
	$aliases = json_decode( (string) file_get_contents( Nashenski\File::cache_folder( 'fonts' ) . 'installed-fonts.json' ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- offline test, a local file.
	foreach ( array_keys( Nashenski\Invoice\PDF\PDF::FONT_ALIASES ) as $family ) {
		if ( false === strpos( (string) ( $aliases[ $family ]['normal'] ?? '' ), 'DejaVu' ) ) {
			throw new RuntimeException( 'The family ' . $family . ' is not saved as DejaVu.' ); } // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- offline test, printed on the command line.
	}
	foreach ( glob( __DIR__ . '/../vendor/dompdf/dompdf/lib/fonts/*.afm' ) as $afm ) {
		if ( false === strpos( (string) file_get_contents( __DIR__ . '/../.gitattributes' ), 'vendor/dompdf/dompdf/lib/fonts/*.afm export-ignore' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- offline test, a local file.
			throw new RuntimeException( 'The built-in font metrics ship in the release: ' . basename( $afm ) ); } // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- offline test, printed on the command line.
	}

	// The logo is a file on the disk, not a data URI: Dompdf may open it only after its folder is allowed.
	$logo = str_replace( '\\', '/', realpath( __DIR__ . '/../assets/images/logo.png' ) );
	$html = '<html><body><img src="' . $logo . '" width="40" height="40" alt=""><p>' . $word . '</p></body></html>';
	file_put_contents( $template, $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- offline test, WordPress is not loaded.
	$pdf->set_template( $template );
	if ( false !== strpos( $pdf->generate(), '/Subtype /Image' ) ) {
		throw new RuntimeException( 'A file outside the allowed folders was put in the PDF.' ); }
	Nashenski\Invoice\PDF\PDF::allow_folder( dirname( $logo ) );
	$out = $pdf->generate();
	unlink( $template ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- offline test, WordPress is not loaded.
	if ( false === strpos( $out, '/Subtype /Image' ) ) {
		throw new RuntimeException( 'The logo file is not in the PDF.' ); }
	// Dompdf keeps the font file open until it is freed (Windows cannot remove the folder before).
	unset( $pdf );
	gc_collect_cycles();
	array_map( 'unlink', glob( Nashenski\File::cache_folder( 'fonts' ) . '*' ) );
	// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- offline test, WordPress is not loaded.
	rmdir( Nashenski\File::cache_folder( 'fonts' ) );
	rmdir( Nashenski\File::cache_folder() );
	rmdir( wp_upload_dir()['basedir'] );
	// phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	echo "OK pdf-cyrillic: 13 checks\n";
}
