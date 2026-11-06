<?php
/** Offline regression tests for import/deactivation authorization. Run: php tests/import-permissions.php */
namespace Nashenski\Admin {
	class Settings {
		public static function url() {
			return '/settings'; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	define( 'WP_PLUGIN_DIR', '/plugins' );
	define( 'HOUR_IN_SECONDS', 3600 );
	class PermissionDenied extends \RuntimeException {}
	class Redirected extends \RuntimeException {}
	class ImportDatabase {
		public $options  = 'options';
		public $postmeta = 'postmeta';
		public $prefix   = 'wp_';
		public $writes   = 0;
		public function prepare( $query, ...$args ) {
			return $query; }
		public function esc_like( $value ) {
			return $value; }
		public function get_col() {
			return array(); }
		public function get_results() {
			return array(); }
		public function get_var() {
			return ''; }
		public function query() {
			++$this->writes;
			return 0; }
	}
	function current_user_can( $cap ) {
		return ! empty( $GLOBALS['caps'][ $cap ] ); }
	function wp_die( $message ) {
		throw new PermissionDenied( $message ); }
	function __( $message, $domain = '' ) {
		return $message; }
	function esc_html__( $message, $domain = '' ) {
		return esc_html( $message ); }
	function esc_html( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	function esc_html_e( $message, $domain = '' ) {
		echo esc_html( $message ); }
	function esc_url( $value ) {
		return esc_html( $value ); }
	function check_admin_referer( $action ) {
		$GLOBALS['nonce_checks'][] = $action;
		if ( ! $GLOBALS['valid_nonce'] ) {
			throw new PermissionDenied( 'Invalid nonce' ); }
	}
	function get_plugins() {
		return array(
			'woo-bg/main.php'     => array( 'Name' => 'Bulgarisation for WooCommerce' ),
			'woo-bg-pro/main.php' => array( 'Name' => 'Bulgarisation for WooCommerce - Pro' ),
			'econt/main.php'      => array( 'Name' => 'Econt Delivery' ),
			'unrelated/main.php'  => array( 'Name' => 'Unrelated Plugin' ),
		);
	}
	function is_plugin_active( $file ) {
		return ! in_array( $file, $GLOBALS['deactivated'], true ); }
	function deactivate_plugins( $file ) {
		$GLOBALS['deactivated'][] = $file; }
	function get_plugin_data( $path, $markup = false, $translate = false ) {
		return get_plugins()[ substr( $path, strlen( WP_PLUGIN_DIR ) + 1 ) ];
	}
	function get_option( $key, $default = false ) {
		return $GLOBALS['options'][ $key ] ?? $default; }
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['options'][ $key ] = $value; }
	function wp_cache_flush() {}
	function wp_cache_get() {
		return 'yes'; }
	function wp_cache_set() {}
	function wp_get_referer() {
		return false; }
	function wp_safe_redirect( $url ) {
		throw new Redirected( $url ); }
	function add_query_arg( $key, $value, $url ) {
		return $url . '?' . $key . '=' . $value; }
	function admin_url( $path = '' ) {
		return '/admin/' . $path; }
	function wp_nonce_url( $url, $action ) {
		$GLOBALS['nonce_urls'][] = $action;
		return $url . '&nonce=valid'; }
	function nashenski_query_arg( $key ) {
		return ''; }
	function sanitize_key( $value ) {
		return $value; }
	function wp_date( $format, $timestamp ) {
		return gmdate( 'Y-m-d', $timestamp ); }

	// Main's Migration::handle() calls Document_Storage::schedule_migration(); without Action Scheduler it returns early.
	require __DIR__ . '/../app/Document_Storage.php';
	require __DIR__ . '/../app/Admin/Migration.php';
	require __DIR__ . '/../app/Admin/Import_Sources.php';
	require __DIR__ . '/../app/Admin/Help_Page.php';

	function reset_fixture( $caps, $valid_nonce = true ) {
		$GLOBALS['caps']        = array_fill_keys( $caps, true );
		$GLOBALS['valid_nonce'] = $valid_nonce;
		$GLOBALS['deactivated'] = $GLOBALS['options'] = $GLOBALS['nonce_checks'] = $GLOBALS['nonce_urls'] = array();
		$GLOBALS['wpdb']        = new ImportDatabase();
	}
	$checks = 0;
	function expect( $condition, $message ) {
		++$GLOBALS['checks'];
		if ( ! $condition ) {
			throw new \RuntimeException( $message ); }
	}
	function denied( $call ) {
		try {
			$call();
		} catch ( PermissionDenied $e ) {
			return true;
		} catch ( Redirected $e ) {
			return false; }
		return false;
	}
	foreach ( array( array(), array( 'manage_woocommerce' ), array( 'activate_plugins' ) ) as $caps ) {
		foreach ( array( 'Migration', 'Import_Sources' ) as $class ) {
			reset_fixture( $caps );
			expect( denied( array( 'Nashenski\\Admin\\' . $class, 'handle' ) ), $class . ' must require both capabilities' );
			expect( ! $GLOBALS['deactivated'] && ! $GLOBALS['options'] && 0 === $GLOBALS['wpdb']->writes, 'Denied requests must not mutate data or plugin state' );
		}
	}
	reset_fixture( array( 'manage_woocommerce' ) );
	expect(
		denied(
			function () {
				\Nashenski\Admin\Import_Sources::switch_off( array( 'woo-bg/main.php' ) );
			}
		),
		'Shared deactivation boundary must enforce plugin-management permission'
	);
	expect( ! $GLOBALS['deactivated'], 'Direct helper call must not deactivate plugins' );
	reset_fixture( array( 'manage_woocommerce', 'activate_plugins' ), false );
	expect( denied( array( 'Nashenski\\Admin\\Migration', 'handle' ) ), 'Administrator still needs a valid nonce' );
	expect( ! $GLOBALS['deactivated'] && 0 === $GLOBALS['wpdb']->writes, 'Invalid nonce must not mutate data or plugin state' );
	reset_fixture( array( 'manage_woocommerce', 'activate_plugins' ) );
	try {
		\Nashenski\Admin\Migration::handle(); } catch ( Redirected $e ) {
		}
		expect( array( 'woo-bg/main.php', 'woo-bg-pro/main.php' ) === $GLOBALS['deactivated'], 'Authorized legacy migration must deactivate exactly its two legacy plugins' );
		expect( isset( $GLOBALS['options'][ \Nashenski\Admin\Migration::DONE_OPTION ]['report'] ), 'Authorized migration must save its completion report' );
		expect( array( \Nashenski\Admin\Migration::ACTION ) === $GLOBALS['nonce_checks'], 'Authorized migration must verify its own nonce' );
		$render = new \ReflectionMethod( 'Nashenski\\Admin\\Help_Page', 'output_import' );
		if ( PHP_VERSION_ID < 80100 ) {
			$render->setAccessible( true ); }
		foreach ( array( false, true ) as $authorized ) {
			reset_fixture( $authorized ? array( 'manage_woocommerce', 'activate_plugins' ) : array( 'manage_woocommerce' ) );
			ob_start();
			$render->invoke( null );
			$html = ob_get_clean();
			expect( false !== strpos( $html, 'Bulgarisation for WooCommerce' ), 'Read-only import information must remain visible' );
			expect( $authorized === ( false !== strpos( $html, 'Import and switch off</a>' ) ), 'Import actions must match handler authorization' );
			expect( $authorized === ! empty( $GLOBALS['nonce_urls'] ), 'Unauthorized viewers must not be issued import nonce URLs' );
		}
		reset_fixture( array( 'manage_woocommerce' ) );
		$GLOBALS['options'][ \Nashenski\Admin\Migration::DONE_OPTION ] = array( 'time' => 1704067200 );
		ob_start();
		$render->invoke( null );
		$html = ob_get_clean();
		expect( false !== strpos( $html, 'Imported on 2024-01-01' ), 'Restricted viewers must retain completed-import status' );
		expect( ! $GLOBALS['nonce_urls'], 'Reading completion status must not issue import nonce URLs' );
		echo 'PASS: ' . $checks . " import permission assertions\n";
}
