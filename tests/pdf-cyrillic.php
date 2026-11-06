<?php
/** Offline: a large order PDF keeps its Bulgarian text (dompdf must not guess ASCII). Run: php tests/pdf-cyrillic.php */
namespace Nashenski\Invoice\PDF {
	class Style {
		public static function get() {
			return array( 'paper' => 'A4' ); }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	function apply_filters( $hook, $value ) {
		return $value; }
	// Read the parsed document during layout; dompdf frees it after rendering.
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
	require __DIR__ . '/../vendor/autoload.php';
	require __DIR__ . '/../app/Invoice/PDF/PDF.php';

	$word     = 'Доставка до офис на Еконт';
	$template = tempnam( sys_get_temp_dir(), 'nash' );
	// About 1 MB of ASCII first (a logo data URI does this in real documents), then Cyrillic: PHP 8.3+ "auto" detection then says ASCII.
	file_put_contents( $template, '<html><body><!-- ' . str_repeat( 'A', 1200000 ) . ' --><p>' . $word . '</p></body></html>' );

	$pdf = new Nashenski\Invoice\PDF\PDF( null );
	$pdf->set_template( $template );
	$out = $pdf->generate();
	unlink( $template );

	$text = $GLOBALS['text'] ?? '';
	if ( '%PDF' !== substr( $out, 0, 4 ) ) {
		throw new RuntimeException( 'No PDF output.' ); }
	if ( false === strpos( $text, $word ) ) {
		throw new RuntimeException( 'Cyrillic text was lost while loading the HTML.' ); }
	echo "OK pdf-cyrillic: 2 checks\n";
}
