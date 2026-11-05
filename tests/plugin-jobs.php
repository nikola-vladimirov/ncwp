<?php
/**
 * Offline: deactivation stops the plugin's Action Scheduler jobs and resets the flags that bring them back; the receipt cleanup returns when missing. Run: php tests/plugin-jobs.php
 *
 * @package Nashenski_Commerce
 */

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
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );

	$GLOBALS['options']     = array();
	$GLOBALS['unscheduled'] = array();
	$GLOBALS['scheduled']   = array(); // hooks with a pending action.
	$GLOBALS['created']     = array();
	$GLOBALS['cleared']     = array();
	$GLOBALS['deactivate']  = null;
	$GLOBALS['autoload']    = array();
	$GLOBALS['enqueued']    = array();

	/**
	 * Add action.
	 */
	function add_action() {}
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

	require __DIR__ . '/../app/Abandoned_Carts/Tracker.php';
	require __DIR__ . '/../app/Document_Storage.php';
	require __DIR__ . '/../nashenski-commerce.php';

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

	echo "PASS\n";
}
