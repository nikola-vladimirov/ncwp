<?php
/**
 * Offline: the document download links go to admin-post with a nonce, a missing or bad nonce is
 * refused before anything is read, and the signature or the capability decides. Run: php tests/document-storage-download.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Universal.Files.SeparateFunctionsFromOO.Mixed, Generic.Files.OneObjectStructurePerFile.MultipleFound, Generic.CodeAnalysis.UnusedFunctionParameter, Squiz.Commenting, Generic.Commenting, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions, Generic.Formatting.DisallowMultipleStatements, Squiz.Functions.MultiLineFunctionDeclaration, Generic.Functions.OpeningFunctionBraceKernighanRitchie, WordPress.Security.NonceVerification -- an offline test script: it stubs WooCommerce classes and WordPress functions in one file.

namespace Nashenski {
	class Hooks {
		public static function filter( $hook, $old, $value ) {
			return $value; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	define( 'DAY_IN_SECONDS', 86400 );

	$GLOBALS['can']     = false;   // Shop staff.
	$GLOBALS['fetched'] = array(); // Attachment ids the handler went on to read.

	class WC_Order {
		public function get_id() {
			return 7; }
		public function get_customer_id() {
			return 0; }
		public function get_meta( $k ) {
			return 55; }
	}
	class WC_Order_Refund extends WC_Order {}
	class Died extends Exception {}

	function admin_url( $path ) {
		return 'https://shop.test/wp-admin/' . $path; }
	function wp_create_nonce( $action ) {
		return 'nonce-' . $action; }
	function wp_verify_nonce( $nonce, $action ) {
		return 'nonce-' . $action === $nonce; }
	function wp_unslash( $v ) {
		return $v; }
	function add_query_arg( $args, $value, $url = null ) {
		return is_array( $args ) ? $value . '?' . http_build_query( $args ) : $url . '?' . http_build_query( array( $args => $value ) ); }
	function wp_salt() {
		return 'salt'; }
	function absint( $v ) {
		return abs( (int) $v ); }
	function sanitize_key( $v ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) ); }
	function sanitize_text_field( $v ) {
		return trim( (string) $v ); }
	function wc_get_order( $id ) {
		return 7 === $id ? new WC_Order() : false; }
	function current_user_can() {
		return $GLOBALS['can']; }
	function get_attached_file( $id ) {
		$GLOBALS['fetched'][] = $id;
		return ''; }
	function esc_html__( $t ) {
		return $t; }
	function wp_die() {
		throw new Died(); }

	function check( $ok, $what ) {
		if ( ! $ok ) {
			fwrite( STDERR, "FAIL: $what\n" );
			exit( 1 );
		} echo "ok - $what\n"; }

	// Runs the handler for a link (or for no link) and returns the attachment ids it tried to send.
	function request( $url ) {
		$GLOBALS['fetched'] = array();
		parse_str( (string) wp_parse_url_query( $url ), $_GET );

		try {
			Nashenski\Document_Storage::download();
		} catch ( Died $e ) {
			$GLOBALS['fetched'][] = 'died';
		}

		return $GLOBALS['fetched'];
	}
	function wp_parse_url_query( $url ) {
		return parse_url( $url, PHP_URL_QUERY ); }

	require __DIR__ . '/../app/class-document-storage.php';

	$doc  = Nashenski\Document_Storage::order_document_url( new WC_Order(), 'nashenski_invoice_document' );
	$file = Nashenski\Document_Storage::export_url( 9 );

	check( 0 === strpos( $doc, 'https://shop.test/wp-admin/admin-post.php?action=nashenski_document&' ), 'the link goes to admin-post' );
	check( array( 55, 'died' ) === request( $doc ), 'guest order: a good nonce and signature reach the document' );
	check( array( 'died' ) === request( str_replace( '_wpnonce=', '_wpnonce=0', $doc ) ), 'guest order: a bad nonce gets nothing' );
	check( array( 'died' ) === request( preg_replace( '/&_wpnonce=[^&]*/', '', $doc ) ), 'guest order: a missing nonce gets nothing' );
	check( array( 'died' ) === request( str_replace( '&token=', '&token=0', $doc ) ), 'guest order: a changed signature gets nothing' );
	check( array( 'died' ) === request( preg_replace( '/&expires=\d+/', '&expires=1', $doc ) ), 'guest order: an expired link gets nothing' );
	check( array( 'died' ) === request( $file ), 'export file: a visitor gets nothing' );

	$GLOBALS['can'] = true;
	check( array( 9, 'died' ) === request( $file ), 'export file: shop staff reach it' );
	check( array( 'died' ) === request( preg_replace( '/&_wpnonce=[^&]*/', '', $file ) ), 'export file: staff without the nonce get nothing' );
	echo "done\n";
}
