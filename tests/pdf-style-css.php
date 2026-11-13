<?php
/**
 * Offline: the invoice appearance CSS (Invoice\PDF\Style::css()) has no HTML special characters, so
 * the template can print it through esc_html() unchanged, and Dompdf still applies its font and
 * colours. Run: php tests/pdf-style-css.php
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Universal.Files.SeparateFunctionsFromOO.Mixed, Squiz.Commenting, Generic.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- an offline test script: it stubs a plugin class and WordPress functions in one file.

namespace Nashenski {
	class Hooks {
		public static function filter( $hook, $old, $value ) {
			return $value; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	$GLOBALS['opt'] = array();

	function nashenski_get_option( $group, $key ) {
		return $GLOBALS['opt'][ $key ] ?? false; }
	function __( $text ) {
		return $text; }
	// As WordPress: the five HTML special characters become entities.
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }

	require __DIR__ . '/../vendor/autoload.php';
	require __DIR__ . '/../app/Invoice/PDF/class-style.php';

	use Nashenski\Invoice\PDF\Style;

	$checks = 0;
	$check  = function ( $ok, $what ) use ( &$checks ) {
		if ( ! $ok ) {
			throw new RuntimeException( 'FAIL: ' . $what ); }
		++$checks;
	};

	$sets = array(
		'defaults'     => array(),
		'user options' => array(
			'title_color'  => '#AA0000',
			'accent_color' => '#00aa00',
			'text_color'   => '#336699',
			'table_color'  => '#ffeecc',
			'font'         => 'dejavu serif',
			'font_size'    => 'large',
			'density'      => 'spacious',
		),
		'bad values'   => array(
			'title_color' => '"><script>',
			'font'        => '"</style>',
			'font_size'   => '<b>',
			'density'     => "a'b&c",
		),
	);

	foreach ( $sets as $name => $options ) {
		$GLOBALS['opt'] = $options;
		$css            = Style::css();
		$check( esc_html( $css ) === $css, 'esc_html() leaves the CSS as it is: ' . $name );
		$check( ! preg_match( '/[<>&"\']/', $css ), 'no HTML special characters: ' . $name );
	}

	// A real render: the unquoted font name and the colours reach the PDF.
	$GLOBALS['opt'] = $sets['user options'];
	$cache          = sys_get_temp_dir() . '/nashenski-style-test-' . getmypid();
	mkdir( $cache );
	$options = new Dompdf\Options();
	$options->set( 'defaultFont', 'dejavu sans' );
	$options->set( 'fontCache', $cache );
	$options->set( 'fontDir', $cache );
	$dompdf = new Dompdf\Dompdf( $options );
	$dompdf->loadHtml( '<html><head><meta charset="utf-8"><style>' . esc_html( Style::css() ) . '</style></head><body><h1>Фактура</h1><p>Доставка до офис на Еконт</p></body></html>', 'UTF-8' );
	$dompdf->render();
	$out = $dompdf->output( array( 'compress' => 0 ) );

	$check( '%PDF' === substr( $out, 0, 4 ), 'a PDF comes out' );
	$check( false !== strpos( $out, 'DejaVuSerif' ), 'the chosen font (DejaVu Serif, unquoted in the CSS) is used' );
	$check( false !== strpos( $out, '0.667 0.000 0.000 rg' ), 'the title colour #AA0000 is used' );
	$check( false !== strpos( $out, '0.200 0.400 0.600 rg' ), 'the text colour #336699 is used' );

	unset( $dompdf );
	gc_collect_cycles();
	array_map( 'unlink', glob( $cache . '/*' ) );
	rmdir( $cache );

	echo "OK pdf-style-css: $checks checks\n";
}
