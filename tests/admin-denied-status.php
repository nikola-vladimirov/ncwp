<?php
/**
 * Offline test: an admin-post action refused for a missing capability answers 403, a bad export request 400.
 * Run: php tests/admin-denied-status.php
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Export\Builder {
	/**
	 * Builder.
	 */
	class Builder {
		/**
		 * Normalize.
		 *
		 * @param mixed $raw Raw.
		 */
		public static function normalize( $raw ) {
			return array(
				'columns' => array(),
				'format'  => 'csv',
			); }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	/**
	 * Died.
	 */
	class Died extends \RuntimeException {}
	/**
	 * Current user can.
	 *
	 * @param mixed $cap Cap.
	 */
	function current_user_can( $cap ) {
		return ! empty( $GLOBALS['caps'][ $cap ] ); }
	/**
	 * Check admin referer.
	 */
	function check_admin_referer() {
		return true; }
	/**
	 * Add action.
	 */
	function add_action() {}
	/**
	 * Add filter.
	 */
	function add_filter() {}
	/**
	 * .
	 *
	 * @param mixed $message Message.
	 */
	function __( $message ) {
		return $message; }
	/**
	 * Esc html.
	 *
	 * @param mixed $message Message.
	 */
	function esc_html__( $message ) {
		return $message; }
	/**
	 * Wp die.
	 *
	 * @param mixed $message Message.
	 * @param mixed $title Title.
	 * @param mixed $args Args.
	 * @throws Died Always, with the status code.
	 */
	function wp_die( $message = '', $title = '', $args = array() ) {
		$code = is_int( $title ) ? $title : ( is_int( $args ) ? $args : ( $args['response'] ?? 500 ) );
		throw new Died( (string) $code );
	}

	require __DIR__ . '/../app/Admin/Abandoned_Carts_Page.php';
	require __DIR__ . '/../app/Admin/Checkout_Fields_Import.php';
	require __DIR__ . '/../app/Admin/Export_Builder.php';
	require __DIR__ . '/../app/Admin/Import_Sources.php';
	require __DIR__ . '/../app/Admin/ImpossibleVatPrice.php';
	require __DIR__ . '/../app/Admin/Migration.php';
	require __DIR__ . '/../app/Admin/Recommended_Settings.php';

	$checks = 0;
	/**
	 * Status of.
	 *
	 * @param mixed $call Call.
	 */
	function status_of( $call ) {
		try {
			$call();
		} catch ( Died $e ) {
			return (int) $e->getMessage(); }
		return 0;
	}
	/**
	 * Expect.
	 *
	 * @param mixed $expected Expected.
	 * @param mixed $actual Actual.
	 * @param mixed $message Message.
	 */
	function expect( $expected, $actual, $message ) {
		++$GLOBALS['checks'];
		if ( $expected !== $actual ) {
			fwrite( STDERR, "FAIL: $message (wanted $expected, got $actual)\n" );
			exit( 1 ); }
	}

	$GLOBALS['caps'] = array();
	$denied          = array(
		'Abandoned_Carts_Page::handle_bulk'     => array( 'Nashenski\Admin\Abandoned_Carts_Page', 'handle_bulk' ),
		'Abandoned_Carts_Page::handle_remind'   => array( 'Nashenski\Admin\Abandoned_Carts_Page', 'handle_remind' ),
		'Abandoned_Carts_Page::handle_delete'   => array( 'Nashenski\Admin\Abandoned_Carts_Page', 'handle_delete' ),
		'Abandoned_Carts_Page::handle_update'   => array( 'Nashenski\Admin\Abandoned_Carts_Page', 'handle_update' ),
		'Checkout_Fields_Import::handle'        => array( 'Nashenski\Admin\Checkout_Fields_Import', 'handle' ),
		'Export_Builder::download'              => array( 'Nashenski\Admin\Export_Builder', 'download' ),
		'Import_Sources::handle'                => array( 'Nashenski\Admin\Import_Sources', 'handle' ),
		'ImpossibleVatPrice::render_admin_page' => array( 'Nashenski\Admin\ImpossibleVatPrice', 'render_admin_page' ),
		'Migration::handle'                     => array( 'Nashenski\Admin\Migration', 'handle' ),
		'Recommended_Settings::handle'          => array( 'Nashenski\Admin\Recommended_Settings', 'handle' ),
	);
	foreach ( $denied as $name => $call ) {
		expect( 403, status_of( $call ), "$name without the capability" );
	}
	expect(
		403,
		status_of(
			function () {
				\Nashenski\Admin\Import_Sources::switch_off( array() );
			}
		),
		'Import_Sources::switch_off without the capability'
	);

	$GLOBALS['caps'] = array( 'manage_woocommerce' => true );
	expect( 400, status_of( array( 'Nashenski\Admin\Export_Builder', 'download' ) ), 'Export_Builder::download with no columns' );

	echo 'PASS: ' . $checks . " admin denied status assertions\n";
}
