<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Removes scheduled jobs, the abandoned carts table (customer emails and phones) and transients.
 * Settings, order data and documents stay: invoices and store data are legal records.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

foreach ( array(
	'nashenski/boxnow/clear_cache_folder',
	'nashenski/econt/clear_cache_folder',
	'nashenski/pigeon/clear_cache_folder',
	'nashenski/speedy/clear_cache_folder',
	'nashenski/prices/check',
) as $nashenski_hook ) {
	wp_clear_scheduled_hook( $nashenski_hook );
}

// Every Action Scheduler action of the plugin (carts, documents, NRA export, automation) is in this group.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), 'nashenski' );
}

global $wpdb;

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall cleanup.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'nashenski_carts' ) );

$wpdb->query(
	$wpdb->prepare(
		'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
		$wpdb->options,
		$wpdb->esc_like( '_transient_nashenski' ) . '%',
		$wpdb->esc_like( '_transient_timeout_nashenski' ) . '%'
	)
);
// phpcs:enable
