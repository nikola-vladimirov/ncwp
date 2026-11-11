<?php // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals -- offline CLI test script: no WordPress, so stubs, unused stub parameters and plain-text output are on purpose.
/**
 * Offline check: Nashenski Commerce pauses while Bulgarisation for WooCommerce 5.x is active
 * (bulgarisation_init, 0.21.7), as it does for 4.x (woo_bg_init). Both would number the same documents.
 *
 * Run: php tests/bulgarisation-guard.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['notices'] = array();

/**
 * Hook stub: keeps the admin notices.
 *
 * @param string   $tag Hook.
 * @param callable $cb  Callback.
 */
function add_action( $tag, $cb ) {
	if ( 'admin_notices' === $tag ) {
		$GLOBALS['notices'][] = $cb;
	}
}
/**
 * Deactivation hook stub.
 */
function register_deactivation_hook() {}
/**
 * WordPress version stub.
 *
 * @return string
 */
function get_bloginfo() {
	return '6.8';
}
/**
 * Escaped translation stub.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html__( $text ) {
	return $text;
}
/**
 * Paragraph stub.
 *
 * @param string $text Text.
 * @return string
 */
function wpautop( $text ) {
	return $text;
}
/**
 * Kses stub.
 *
 * @param string $html HTML.
 * @return string
 */
function wp_kses_post( $html ) {
	return $html;
}
/**
 * Bulgarisation for WooCommerce 5.x is active.
 */
function bulgarisation_init() {}

require __DIR__ . '/../nashenski-commerce.php';

$instance = nashenski_init();
ob_start();
foreach ( $GLOBALS['notices'] as $notice ) {
	$notice();
}
$html = ob_get_clean();

if ( null !== $instance || false === strpos( $html, 'Nashenski Commerce is paused' ) || class_exists( 'Nashenski\Plugin', false ) ) {
	fwrite( STDERR, "FAIL: the plugin must pause and say so while Bulgarisation 5.x is active\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	exit( 1 );
}
echo "PASS: paused while Bulgarisation 5.x is active\n";
