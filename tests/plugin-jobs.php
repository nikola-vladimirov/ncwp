<?php
/**
 * Offline: deactivation stops the plugin's Action Scheduler jobs and resets the flags that bring them back; the receipt cleanup returns when missing. Run: php tests/plugin-jobs.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Universal.Files.SeparateFunctionsFromOO, WordPress.NamingConventions.ValidFunctionName, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions, WordPress.WP.GlobalVariablesOverride -- offline test script: it stubs WordPress in one file, keeps WordPress's own names and signatures, and prints to a terminal.

namespace Automattic\WooCommerce\Utilities {
	/**
	 * Orderutil.
	 */
	class OrderUtil {
		/**
		 * Custom orders table usage is enabled.
		 *
		 * @return bool Result.
		 */
		public static function custom_orders_table_usage_is_enabled() {
			return false; }
	}
}

namespace {
	// A scratch WordPress folder: the upgrade step loads wp-admin/includes/file.php and clears the CVC cache folder under WP_CONTENT_DIR.
	$GLOBALS['scratch'] = sys_get_temp_dir() . '/nashenski-plugin-jobs-' . getmypid() . '/';
	mkdir( $GLOBALS['scratch'] . 'wp-admin/includes', 0777, true );
	file_put_contents(
		$GLOBALS['scratch'] . 'wp-admin/includes/file.php',
		'<?php
'
	);
	define( 'ABSPATH', $GLOBALS['scratch'] );
	// Removed on every exit, a failed check included.
	register_shutdown_function(
		function () {
			unlink( $GLOBALS['scratch'] . 'wp-admin/includes/file.php' );
			rmdir( $GLOBALS['scratch'] . 'wp-admin/includes' );
			rmdir( $GLOBALS['scratch'] . 'wp-admin' );
			rmdir( $GLOBALS['scratch'] );
		}
	);
	define( 'WP_CONTENT_DIR', $GLOBALS['scratch'] . 'wp-content' );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );

	$GLOBALS['options']      = array();
	$GLOBALS['unscheduled']  = array();
	$GLOBALS['scheduled']    = array(); // hooks with a pending action.
	$GLOBALS['created']      = array();
	$GLOBALS['cleared']      = array();
	$GLOBALS['deactivate']   = null;
	$GLOBALS['autoload']     = array();
	$GLOBALS['enqueued']     = array();
	$GLOBALS['actions']      = array();
	$GLOBALS['autoload_set'] = array();

	/**
	 * Add action.
	 *
	 * @param mixed $tag Tag.
	 * @param mixed $cb Cb.
	 */
	function add_action( $tag, $cb ) {
		$GLOBALS['actions'][] = array( $tag, $cb ); }
	/**
	 * Add filter.
	 */
	function add_filter() {}
	/**
	 * Register deactivation hook.
	 *
	 * @param mixed $file File.
	 * @param mixed $cb Cb.
	 */
	function register_deactivation_hook( $file, $cb ) {
		$GLOBALS['deactivate'] = $cb; }
	/**
	 * Wp clear scheduled hook.
	 *
	 * @param mixed $h H.
	 */
	function wp_clear_scheduled_hook( $h ) {
		$GLOBALS['cleared'][] = $h; }
	/**
	 * Get option.
	 *
	 * @param mixed $k K.
	 * @param bool  $d D.
	 * @return mixed Result.
	 */
	function get_option( $k, $d = false ) {
		return $GLOBALS['options'][ $k ] ?? $d; }
	/**
	 * Update option.
	 *
	 * @param mixed $k K.
	 * @param mixed $v V.
	 * @param mixed $autoload Autoload.
	 */
	function update_option( $k, $v, $autoload = null ) {
		$GLOBALS['options'][ $k ]  = $v;
		$GLOBALS['autoload'][ $k ] = $autoload; }
	/**
	 * Wp set option autoload.
	 *
	 * @param mixed $k K.
	 * @param mixed $autoload Autoload.
	 */
	function wp_set_option_autoload( $k, $autoload ) {
		$GLOBALS['autoload_set'][] = array( $k, $autoload ); }
	/**
	 * Wp delete file.
	 */
	function wp_delete_file() {}
	/**
	 * Wp filesystem.
	 */
	function WP_Filesystem() {}
	/**
	 * As enqueue async action.
	 *
	 * @param mixed $hook Hook.
	 */
	function as_enqueue_async_action( $hook ) {
		$GLOBALS['enqueued'][] = $hook; }
	/**
	 * Delete option.
	 *
	 * @param mixed $k K.
	 */
	function delete_option( $k ) {
		unset( $GLOBALS['options'][ $k ] ); }
	/**
	 * As unschedule all actions.
	 *
	 * @param string $hook Hook.
	 * @param array  $args Args.
	 * @param string $group Group.
	 */
	function as_unschedule_all_actions( $hook = '', $args = array(), $group = '' ) {
		$GLOBALS['unscheduled'][] = array( $hook, $group );
		$GLOBALS['scheduled']     = array(); }
	/**
	 * As has scheduled action.
	 *
	 * @param mixed $hook Hook.
	 * @return mixed Result.
	 */
	function as_has_scheduled_action( $hook ) {
		return in_array( $hook, $GLOBALS['scheduled'], true ); }
	/**
	 * As schedule recurring action.
	 *
	 * @param mixed $when When.
	 * @param mixed $every Every.
	 * @param mixed $hook Hook.
	 * @param mixed $args Args.
	 * @param mixed $group Group.
	 */
	function as_schedule_recurring_action( $when, $every, $hook, $args, $group ) {
		$GLOBALS['scheduled'][] = $hook;
		$GLOBALS['created'][]   = array( $hook, $group ); }

	require __DIR__ . '/../app/Abandoned_Carts/class-tracker.php';
	require __DIR__ . '/../app/class-document-storage.php';
	require __DIR__ . '/../nashenski-commerce.php';
	require __DIR__ . '/../app/class-file.php';
	require __DIR__ . '/../app/class-plugin.php';

	/**
	 * Check.
	 *
	 * @param mixed $ok Ok.
	 * @param mixed $what What.
	 */
	function check( $ok, $what ) {
		if ( ! $ok ) {
			fwrite( STDERR, "FAIL: $what\n" );
			exit( 1 );
		} echo "ok - $what\n"; }

	check( is_callable( $GLOBALS['deactivate'] ), 'the plugin registers a deactivation hook' );

	// A site with the abandoned carts tracker installed, the documents migration marked done and the daily receipt cleanup on.
	$GLOBALS['options']   = array(
		Nashenski\Abandoned_Carts\Tracker::DB_OPTION       => Nashenski\Abandoned_Carts\Tracker::DB_VERSION,
		Nashenski\Document_Storage::MIGRATE_VERSION_OPTION => Nashenski\Document_Storage::MIGRATE_VERSION,
		Nashenski\Document_Storage::CLEANUP_OPTION         => 30,
	);
	$GLOBALS['scheduled'] = array( Nashenski\Abandoned_Carts\Tracker::SEND_HOOK, Nashenski\Document_Storage::CLEANUP_HOOK );

	( $GLOBALS['deactivate'] )();

	check( in_array( array( '', 'nashenski' ), $GLOBALS['unscheduled'], true ), 'deactivation unschedules every action of the nashenski group' );
	check( array() === $GLOBALS['scheduled'], 'no job of the group is left pending' );
	check( Nashenski\Abandoned_Carts\Tracker::DB_VERSION !== get_option( Nashenski\Abandoned_Carts\Tracker::DB_OPTION ), 'the abandoned carts flag is reset, so install() schedules its jobs again' );
	check( Nashenski\Document_Storage::MIGRATE_VERSION > (int) get_option( Nashenski\Document_Storage::MIGRATE_VERSION_OPTION ), 'the documents migration flag is reset, so schedule_migration() runs again' );
	check( 30 === get_option( Nashenski\Document_Storage::CLEANUP_OPTION ), 'the settings stay' );

	// After the plugin is active again, the receipt cleanup is scheduled when it is missing, once.
	Nashenski\Document_Storage::restore_receipt_cleanup();
	check( array( array( Nashenski\Document_Storage::CLEANUP_HOOK, 'nashenski' ) ) === $GLOBALS['created'], 'the receipt cleanup is scheduled again' );
	Nashenski\Document_Storage::restore_receipt_cleanup();
	check( 1 === count( $GLOBALS['created'] ), 'a second call adds no second job' );

	// Cleanup off: nothing is scheduled.
	$GLOBALS['scheduled'] = array();
	$GLOBALS['created']   = array();
	$GLOBALS['options'][ Nashenski\Document_Storage::CLEANUP_OPTION ] = 0;
	Nashenski\Document_Storage::restore_receipt_cleanup();
	check( array() === $GLOBALS['created'], 'with the cleanup off nothing is scheduled' );

	// The migration runs again after the reset, and its flag is saved autoloaded, so the init check costs no query.
	$GLOBALS['scheduled'] = array();
	Nashenski\Document_Storage::schedule_migration();
	check( array( Nashenski\Document_Storage::MIGRATE_HOOK ) === $GLOBALS['enqueued'], 'the migration is queued again after the reset' );
	check( true === $GLOBALS['autoload'][ Nashenski\Document_Storage::MIGRATE_VERSION_OPTION ] && Nashenski\Document_Storage::MIGRATE_VERSION === get_option( Nashenski\Document_Storage::MIGRATE_VERSION_OPTION ), 'the migration flag is saved with autoload on' );

	// The upgrade step puts the documents migration flag in the autoload set, for sites that saved it outside it.
	$wpdb                     = new class() {
		/**
		 * Options table name.
		 *
		 * @var string
		 */
		public $options = 'wp_options';
		/**
		 * Prepare.
		 *
		 * @param mixed $q Q.
		 * @return mixed Result.
		 */
		public function prepare( $q ) {
			return $q; }
		/**
		 * Esc like.
		 *
		 * @param mixed $t T.
		 * @return mixed Result.
		 */
		public function esc_like( $t ) {
			return $t; }
		/**
		 * Query.
		 */
		public function query() {}
	};
	$GLOBALS['wpdb']          = $wpdb;
	$wp_filesystem            = new class() {
		/**
		 * Rmdir.
		 */
		public function rmdir() {}
	};
	$GLOBALS['wp_filesystem'] = $wp_filesystem;
	$plugin                   = ( new ReflectionClass( Nashenski\Plugin::class ) )->newInstanceWithoutConstructor();
	( new ReflectionMethod( $plugin, 'maybe_upgrade' ) )->invoke( $plugin );
	check( array( array( 'nashenski_documents_migration_version', true ) ) === $GLOBALS['autoload_set'], 'the upgrade step switches the documents migration flag to autoload' );
	check( Nashenski\Plugin::VERSION === get_option( 'nashenski_version' ), 'the upgrade step saves the new version' );

	// The receipt cleanup is checked again in the admin, so a site that lost the job gets it back.
	new Nashenski\Document_Storage();
	check( in_array( array( 'admin_init', array( Nashenski\Document_Storage::class, 'restore_receipt_cleanup' ) ), $GLOBALS['actions'], true ), 'admin_init restores the receipt cleanup job' );

	echo "PASS\n";
}
