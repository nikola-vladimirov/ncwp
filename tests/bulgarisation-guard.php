<?php // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals -- offline CLI test script: no WordPress, so stubs, unused stub parameters and plain-text output are on purpose.
/**
 * Offline check: Nashenski Commerce pauses while Bulgarisation for WooCommerce 5.x is active
 * (bulgarisation_init, 0.21.7), as it does for 4.x (woo_bg_init). Both would number the same documents.
 * The notice shows only to users who manage plugins, on the Dashboard, Plugins and WooCommerce screens.
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
 * Screen stub: set $GLOBALS['screen'].
 *
 * @return object
 */
function get_current_screen() {
	return (object) array( 'id' => $GLOBALS['screen'] );
}
/**
 * Capability stub: set $GLOBALS['admin'].
 *
 * @return bool
 */
function current_user_can() {
	return $GLOBALS['admin'];
}
/**
 * Bulgarisation for WooCommerce 5.x is active.
 */
function bulgarisation_init() {}

require __DIR__ . '/../nashenski-commerce.php';

$instance = nashenski_init();

/**
 * The notices printed on a screen for a user.
 *
 * @param string $screen Screen id.
 * @param bool   $admin  Whether the user manages plugins.
 * @return string
 */
function notices_on( $screen, $admin ) {
	$GLOBALS['screen'] = $screen;
	$GLOBALS['admin']  = $admin;
	ob_start();
	foreach ( $GLOBALS['notices'] as $notice ) {
		$notice();
	}
	return ob_get_clean();
}

$fail = array();
if ( null !== $instance || class_exists( 'Nashenski\Plugin', false ) ) {
	$fail[] = 'the plugin must not start while Bulgarisation 5.x is active';
}
if ( false === strpos( notices_on( 'dashboard', true ), 'Nashenski Commerce is paused' ) || false === strpos( notices_on( 'woocommerce_page_wc-settings', true ), 'Nashenski Commerce is paused' ) ) {
	$fail[] = 'the Dashboard and WooCommerce screens say why';
}
if ( '' !== notices_on( 'edit-post', true ) || '' !== notices_on( 'dashboard', false ) ) {
	$fail[] = 'no notice on other screens, or to users who cannot manage plugins';
}
if ( $fail ) {
	fwrite( STDERR, 'FAIL: ' . implode( "\nFAIL: ", $fail ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	exit( 1 );
}
echo "PASS: 3 checks, paused while Bulgarisation 5.x is active\n";
