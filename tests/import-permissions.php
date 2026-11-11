<?php // phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO -- offline test: WordPress stubs and the test share one file.
/**
 * Offline regression tests for import/deactivation authorization. Run: php tests/import-permissions.php
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin {
	/**
	 * Settings fixture.
	 */
	class Settings {
		/**
		 * Settings URL.
		 *
		 * @return string
		 */
		public static function url() {
			return '/settings'; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	define( 'WP_PLUGIN_DIR', '/plugins' );
	define( 'HOUR_IN_SECONDS', 3600 );
	/**
	 * Thrown by the wp_die() stub.
	 */
	class PermissionDenied extends \RuntimeException {}
	/**
	 * Thrown by the wp_safe_redirect() stub.
	 */
	class Redirected extends \RuntimeException {}
	/**
	 * Database fixture that counts writes.
	 */
	class ImportDatabase {
		/**
		 * Options table.
		 *
		 * @var string
		 */
		public $options = 'options';

		/**
		 * Postmeta table.
		 *
		 * @var string
		 */
		public $postmeta = 'postmeta';

		/**
		 * Table prefix.
		 *
		 * @var string
		 */
		public $prefix = 'wp_';

		/**
		 * Number of write queries.
		 *
		 * @var int
		 */
		public $writes = 0;

		/**
		 * Prepare.
		 *
		 * @param string $query Query.
		 * @return string
		 */
		public function prepare( $query ) {
			return $query; }
		/**
		 * Esc like.
		 *
		 * @param string $value Value.
		 * @return string
		 */
		public function esc_like( $value ) {
			return $value; }
		/**
		 * Get col.
		 *
		 * @return array
		 */
		public function get_col() {
			return array(); }
		/**
		 * Get results.
		 *
		 * @return array
		 */
		public function get_results() {
			return array(); }
		/**
		 * Get var.
		 *
		 * @return string
		 */
		public function get_var() {
			return ''; }
		/**
		 * Query: counts the write.
		 *
		 * @return int
		 */
		public function query() {
			++$this->writes;
			return 0; }
	}
	/**
	 * Current user can.
	 *
	 * @param string $cap Capability.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		return ! empty( $GLOBALS['caps'][ $cap ] ); }
	/**
	 * Wp die.
	 *
	 * @param string $message Message.
	 * @throws PermissionDenied Always.
	 */
	function wp_die( $message ) {
		throw new PermissionDenied( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test stub, the message is never printed as HTML.
	}
	/**
	 * Translate.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	function __( $message ) {
		return $message; }
	/**
	 * Esc html translate.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	function esc_html__( $message ) {
		return esc_html( $message ); }
	/**
	 * Esc html.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	function esc_html( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
	/**
	 * Esc html echo.
	 *
	 * @param string $message Message.
	 */
	function esc_html_e( $message ) {
		echo esc_html( $message ); }
	/**
	 * Esc url.
	 *
	 * @param string $value URL.
	 * @return string
	 */
	function esc_url( $value ) {
		return esc_html( $value ); }
	/**
	 * Check admin referer: records the action, fails when the nonce is set to invalid.
	 *
	 * @param string $action Nonce action.
	 * @throws PermissionDenied When the nonce is invalid.
	 */
	function check_admin_referer( $action ) {
		$GLOBALS['nonce_checks'][] = $action;
		if ( ! $GLOBALS['valid_nonce'] ) {
			throw new PermissionDenied( 'Invalid nonce' ); }
	}
	/**
	 * Get plugins.
	 *
	 * @return array
	 */
	function get_plugins() {
		return array(
			'woo-bg/main.php'     => array( 'Name' => 'Bulgarisation for WooCommerce' ),
			'woo-bg-pro/main.php' => array( 'Name' => 'Bulgarisation for WooCommerce - Pro' ),
			'econt/main.php'      => array( 'Name' => 'Econt Delivery' ),
			'unrelated/main.php'  => array( 'Name' => 'Unrelated Plugin' ),
		);
	}
	/**
	 * Is plugin active.
	 *
	 * @param string $file Plugin file.
	 * @return bool
	 */
	function is_plugin_active( $file ) {
		return ! in_array( $file, $GLOBALS['deactivated'], true ); }
	/**
	 * Deactivate plugins: records the file.
	 *
	 * @param string $file Plugin file.
	 */
	function deactivate_plugins( $file ) {
		$GLOBALS['deactivated'][] = $file; }
	/**
	 * Get plugin data.
	 *
	 * @param string $path Plugin path.
	 * @return array
	 */
	function get_plugin_data( $path ) {
		return get_plugins()[ substr( $path, strlen( WP_PLUGIN_DIR ) + 1 ) ];
	}
	/**
	 * Get option.
	 *
	 * @param string $key           Option name.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	function get_option( $key, $default_value = false ) {
		return $GLOBALS['options'][ $key ] ?? $default_value; }
	/**
	 * Update option.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Value.
	 */
	function update_option( $key, $value ) {
		$GLOBALS['options'][ $key ] = $value; }
	/**
	 * Wp cache flush.
	 */
	function wp_cache_flush() {}
	/**
	 * Wp cache get.
	 *
	 * @return string
	 */
	function wp_cache_get() {
		return 'yes'; }
	/**
	 * Wp cache set.
	 */
	function wp_cache_set() {}
	/**
	 * Wp get referer.
	 *
	 * @return false
	 */
	function wp_get_referer() {
		return false; }
	/**
	 * Wp safe redirect.
	 *
	 * @param string $url URL.
	 * @throws Redirected Always.
	 */
	function wp_safe_redirect( $url ) {
		throw new Redirected( $url ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- test stub, the message is never printed as HTML.
	}
	/**
	 * Add query arg.
	 *
	 * @param string $key   Key.
	 * @param string $value Value.
	 * @param string $url   URL.
	 * @return string
	 */
	function add_query_arg( $key, $value, $url ) {
		return $url . '?' . $key . '=' . $value; }
	/**
	 * Admin url.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return '/admin/' . $path; }
	/**
	 * Wp nonce url: records the action.
	 *
	 * @param string $url    URL.
	 * @param string $action Nonce action.
	 * @return string
	 */
	function wp_nonce_url( $url, $action ) {
		$GLOBALS['nonce_urls'][] = $action;
		return $url . '&nonce=valid'; }
	/**
	 * Nashenski query arg.
	 *
	 * @return string
	 */
	function nashenski_query_arg() {
		return ''; }
	/**
	 * Sanitize key.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function sanitize_key( $value ) {
		return $value; }
	/**
	 * Multisite stub: set $GLOBALS['network'] to a network-activated plugin file.
	 *
	 * @return bool
	 */
	function is_multisite() {
		return ! empty( $GLOBALS['network'] ); }
	/**
	 * Network activation stub.
	 *
	 * @param string $file Plugin file.
	 * @return bool
	 */
	function is_plugin_active_for_network( $file ) {
		return ( $GLOBALS['network'] ?? '' ) === $file; }
	/**
	 * Network admin URL stub.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function network_admin_url( $path = '' ) {
		return '/network/' . $path; }
	/**
	 * Wp date.
	 *
	 * @param string $format    Format (ignored).
	 * @param int    $timestamp Timestamp.
	 * @return string
	 */
	function wp_date( $format, $timestamp ) {
		return gmdate( 'Y-m-d', $timestamp ); }

	// Main's Migration::handle() calls Document_Storage::schedule_migration(); without Action Scheduler it returns early.
	require __DIR__ . '/../app/class-document-storage.php';
	require __DIR__ . '/../app/Admin/class-migration.php';
	require __DIR__ . '/../app/Admin/class-import-sources.php';
	require __DIR__ . '/../app/Admin/class-help-page.php';

	/**
	 * Resets the fixture state.
	 *
	 * @param array $caps        Capabilities of the user.
	 * @param bool  $valid_nonce Whether nonces pass.
	 */
	function reset_fixture( $caps, $valid_nonce = true ) {
		$GLOBALS['caps']         = array_fill_keys( $caps, true );
		$GLOBALS['valid_nonce']  = $valid_nonce;
		$GLOBALS['deactivated']  = array();
		$GLOBALS['options']      = array();
		$GLOBALS['nonce_checks'] = array();
		$GLOBALS['nonce_urls']   = array();
		$GLOBALS['wpdb']         = new ImportDatabase(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- offline test replaces the database with a fixture.
	}
	$checks = 0;
	/**
	 * Expect.
	 *
	 * @param bool   $condition Condition.
	 * @param string $message   Message.
	 * @throws \RuntimeException When the condition is false.
	 */
	function expect( $condition, $message ) {
		++$GLOBALS['checks'];
		if ( ! $condition ) {
			throw new \RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI test failure message, not HTML.
		}
	}
	/**
	 * Whether the call was refused.
	 *
	 * @param callable $call Handler to call.
	 * @return bool
	 */
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
	foreach ( array( false, true ) as $authorized ) {
		reset_fixture( $authorized ? array( 'manage_woocommerce', 'activate_plugins' ) : array( 'manage_woocommerce' ) );
		ob_start();
		\Nashenski\Admin\Import_Sources::ask_to_deactivate( array( 'woo-bg/main.php' => 'Bulgarisation for WooCommerce' ) );
		$html = ob_get_clean();
		expect( ( false !== strpos( $html, '/admin/plugins.php' ) ) === $authorized, 'Only users who manage plugins are asked to deactivate the old one' );
		expect( ! $GLOBALS['deactivated'], 'Asking never deactivates a plugin' );
	}
	reset_fixture( array( 'manage_woocommerce', 'activate_plugins' ), false );
	expect( denied( array( 'Nashenski\\Admin\\Migration', 'handle' ) ), 'Administrator still needs a valid nonce' );
	expect( ! $GLOBALS['deactivated'] && 0 === $GLOBALS['wpdb']->writes, 'Invalid nonce must not mutate data or plugin state' );
	reset_fixture( array( 'manage_woocommerce', 'activate_plugins' ) );
	try {
		\Nashenski\Admin\Migration::handle();
	} catch ( Redirected $e ) {
		// The handler ends with a redirect; the checks below read what it did.
		unset( $e );
	}
	expect( ! $GLOBALS['deactivated'], 'The migration never deactivates another plugin (WordPress.org rule)' );
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
		expect( ( false !== strpos( $html, 'Import</a>' ) ) === $authorized, 'Import actions must match handler authorization' );
		expect( ! empty( $GLOBALS['nonce_urls'] ) === $authorized, 'Unauthorized viewers must not be issued import nonce URLs' );
	}
	reset_fixture( array( 'manage_woocommerce' ) );
	$GLOBALS['options'][ \Nashenski\Admin\Migration::DONE_OPTION ] = array( 'time' => 1704067200 );
	ob_start();
	$render->invoke( null );
	$html = ob_get_clean();
	expect( false !== strpos( $html, 'Imported on 2024-01-01' ), 'Restricted viewers must retain completed-import status' );
	expect( ! $GLOBALS['nonce_urls'], 'Reading completion status must not issue import nonce URLs' );

	// After the Bulgarisation import, only its Pro add-on can still be active: with the base plugin active, the
	// guard in nashenski-commerce.php stops Nashenski Commerce before this code loads. The reminder for Pro stays.
	reset_fixture( array( 'manage_woocommerce', 'activate_plugins' ) );
	$GLOBALS['deactivated']                                        = array( 'woo-bg/main.php' );
	$GLOBALS['options'][ \Nashenski\Admin\Migration::DONE_OPTION ] = array( 'time' => 1704067200 );
	ob_start();
	\Nashenski\Admin\Import_Sources::render();
	$html = ob_get_clean();
	expect( false !== strpos( $html, 'deactivate Bulgarisation for WooCommerce - Pro:' ) && false !== strpos( $html, '/admin/plugins.php' ), 'The reminder for the Bulgarisation Pro add-on persists after the import' );
	$GLOBALS['network'] = 'woo-bg-pro/main.php';
	ob_start();
	\Nashenski\Admin\Import_Sources::render();
	$html = ob_get_clean();
	unset( $GLOBALS['network'] );
	expect( false !== strpos( $html, '/network/plugins.php' ), 'A network-activated plugin links to the network Plugins screen' );
	$GLOBALS['deactivated'] = array( 'woo-bg/main.php', 'woo-bg-pro/main.php' );
	ob_start();
	\Nashenski\Admin\Import_Sources::render();
	$html = ob_get_clean();
	expect( false === strpos( $html, 'plugins.php' ), 'No reminder once the old plugins are deactivated' );
	echo 'PASS: ' . (int) $checks . " import permission assertions\n";
}
