<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Removes scheduled jobs, the abandoned carts table (customer emails and phones), transients and the courier cache,
 * on every site of a network. Settings, order data and documents stay: invoices and store data are legal records.
 *
 * @package Nashenski_Commerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$nashenski_sites = is_multisite() ? get_sites(
	array(
		'fields' => 'ids',
		'number' => 0,
	)
) : array( 0 );

// Jobs and data first, on every site: a timeout in the file removal below cannot leave customer data behind.
foreach ( $nashenski_sites as $nashenski_site ) {
	if ( $nashenski_site ) {
		switch_to_blog( $nashenski_site );
	}

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

	delete_option( 'nashenski_shipments_cursor' );

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

	if ( $nashenski_site ) {
		restore_current_blog();
	}
}

// The courier cache: its folder in the uploads of every site, and the shared folder used before 0.21.7. Direct
// access: these are local files, and WP_Filesystem() can fail on hosts set to FTP.
if ( ! class_exists( 'WP_Filesystem_Direct' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
}
$nashenski_filesystem = new WP_Filesystem_Direct( null );

foreach ( $nashenski_sites as $nashenski_site ) {
	if ( $nashenski_site ) {
		switch_to_blog( $nashenski_site );
	}
	$nashenski_filesystem->rmdir( trailingslashit( wp_upload_dir( null, false )['basedir'] ) . 'nashenski-cache', true );
	if ( $nashenski_site ) {
		restore_current_blog();
	}
}
$nashenski_filesystem->rmdir( WP_CONTENT_DIR . '/cache/nashenski', true );
