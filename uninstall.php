<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Removes scheduled jobs, the abandoned carts table (customer emails and phones), transients, the courier cache
 * and the options that only remember the state of a screen, on every site of a network.
 *
 * What stays, because invoices and store data are legal records:
 * - the settings (nashenski_settings_*, the shipping method settings WooCommerce keeps, nashenski_export_templates);
 * - order data and the document files, with nashenski_documents_dir, the name of their folder;
 * - document numbering: the next numbers in the settings and nashenski_n18_state, so no number repeats;
 * - nashenski_version and the flags of what was already migrated or imported from another plugin
 *   (nashenski_documents_migration*, nashenski_migrated_from_woo_bg, nashenski_imported_sources,
 *   nashenski_imported_checkout_fields), so a reinstall does not migrate or import twice.
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
		'nashenski/cvc/clear_cache_folder',
		'nashenski/prices/check',
		'nashenski_delete_export_files',
		'nashenski/upgrade/remove_shipping',
	) as $nashenski_hook ) {
		wp_clear_scheduled_hook( $nashenski_hook );
	}

	// Every Action Scheduler action of the plugin (carts, documents, NRA export, automation) is in this group.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( '', array(), 'nashenski' );
	}

	// The state of screens and notices, and the flag of the dropped table. No record depends on them.
	foreach ( array(
		'nashenski_shipments_cursor',
		'nashenski_carts_db_version',
		'nashenski_price_updates',
		'nashenski_price_updates_seen',
		'nashenski_setup_done',
		'nashenski_setup_dismissed',
		'nashenski_import_notice_dismissed',
		'nashenski_boxnow_message_dismiss',
		'nashenski_pigeon_express_message_dismiss',
		// Hashes of unsubscribed reminder emails: they belong to the dropped carts table.
		'nashenski_carts_unsubscribed',
	) as $nashenski_option ) {
		delete_option( $nashenski_option );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstall drops the plugin's own table; WordPress has no API for that.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . 'nashenski_carts' ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall deletes the plugin's transients by prefix; delete_transient() needs each name.
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
			$wpdb->options,
			$wpdb->esc_like( '_transient_nashenski' ) . '%',
			$wpdb->esc_like( '_transient_timeout_nashenski' ) . '%'
		)
	);

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
$nashenski_filesystem->rmdir( $nashenski_filesystem->wp_content_dir() . 'cache/nashenski', true );
