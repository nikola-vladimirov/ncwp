<?php
namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One-click import of settings, shipping zones and order data saved by
 * Bulgarisation for WooCommerce (the plugin Nashenski is forked from).
 *
 * Old data uses the "woo_bg" prefix; ours uses "nashenski". Settings and order
 * meta are copied (the old rows stay). Shipping zone methods and the method ID
 * stored on old orders' shipping lines are switched to our IDs, so existing
 * zones and labels keep working.
 */
class Migration {
	const OLD = 'woo_bg';
	const NEW = 'nashenski';
	const DONE_OPTION = 'nashenski_migrated_from_woo_bg';
	const ACTION = 'nashenski_import_woo_bg';

	public function __construct() {
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	public static function has_old_data() {
		global $wpdb;

		$found = wp_cache_get( 'has_old_data', 'nashenski' );

		if ( false === $found ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- options by name prefix; no WordPress API lists them.
			$found = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1", $wpdb->esc_like( self::OLD . '_settings_' ) . '%' ) ) ? 'yes' : 'no';
			wp_cache_set( 'has_old_data', $found, 'nashenski', HOUR_IN_SECONDS );
		}

		return 'yes' === $found;
	}

	public static function notice() {
		// WooCommerce hides admin notices on its own pages, so our settings page renders this inside its content.
		if ( Settings::is_current() ) {
			return;
		}

		self::render();
	}

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$done = get_option( self::DONE_OPTION );

		if ( $done && nashenski_query_arg( 'nashenski_imported' ) && ! empty( $done['report'] ) ) {
			$r = $done['report'];
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html( sprintf(
						/* translators: 1: settings, 2: shipping method settings, 3: shipping zone methods, 4: order shipping lines, 5: order data rows */
						__( 'Import finished: %1$d settings, %2$d shipping method settings, %3$d shipping zone methods, %4$d order shipping lines and %5$d order data rows.', 'nashenski-commerce' ),
						$r['settings'] ?? 0,
						$r['shipping_options'] ?? 0,
						$r['zone_methods'] ?? 0,
						$r['order_shipping_lines'] ?? 0,
						$r['order_meta'] ?? 0
					) );

					foreach ( (array) ( $r['switched_off'] ?? array() ) as $line ) {
						echo '<br>' . esc_html( $line );
					}
					?>
				</p>
			</div>
			<?php
			return;
		}

		// Data still to import is listed in the import notice (Import_Sources) and on the Help page.
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ) );
		}

		check_admin_referer( self::ACTION );

		$report = self::run();
		$report['switched_off'] = Import_Sources::switch_off( self::plugin_files() );
		update_option( self::DONE_OPTION, array( 'time' => time(), 'report' => $report ), false );

		$back = wp_get_referer() ? wp_get_referer() : Settings::url();
		wp_safe_redirect( add_query_arg( 'nashenski_imported', 1, $back ) );
		exit;
	}

	/**
	 * Bulgarisation for WooCommerce and its Pro add-on, wherever they are installed.
	 */
	private static function plugin_files() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return array_keys( array_filter( get_plugins(), function ( $plugin ) {
			return in_array( $plugin['Name'], array( 'Bulgarisation for WooCommerce', 'Bulgarisation for WooCommerce - Pro' ), true );
		} ) );
	}

	/**
	 * @return array Counts of what was imported.
	 */
	public static function run() {
		global $wpdb;

		$report = array(
			'settings' => self::copy_options( self::OLD . '_settings_' ),
			'shipping_options' => self::copy_options( 'woocommerce_' . self::OLD . '_' ),
		);

		// Shipping zone methods: woo_bg_econt -> nashenski_econt.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import of the old plugin data; no WordPress API for it.
		$report['zone_methods'] = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}woocommerce_shipping_zone_methods SET method_id = REPLACE( method_id, %s, %s ) WHERE method_id LIKE %s",
			self::OLD . '_', self::NEW . '_', $wpdb->esc_like( self::OLD . '_' ) . '%'
		) );

		// Shipping lines of existing orders, so labels can still be made for them.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import of the old plugin data; no WordPress API for it.
		$report['order_shipping_lines'] = (int) $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}woocommerce_order_itemmeta SET meta_value = REPLACE( meta_value, %s, %s ) WHERE meta_key = 'method_id' AND meta_value LIKE %s",
			self::OLD . '_', self::NEW . '_', $wpdb->esc_like( self::OLD . '_' ) . '%'
		) );

		// Order meta in both storages (HPOS table and posts, when sync is on).
		$report['order_meta'] = 0;
		$hpos_table = $wpdb->prefix . 'wc_orders_meta';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import of the old plugin data; no WordPress API for it.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_table ) ) === $hpos_table ) {
			$report['order_meta'] += self::copy_meta( $hpos_table, 'order_id' );
		}

		$report['order_meta'] += self::copy_meta( $wpdb->postmeta, 'post_id' );

		wp_cache_flush();

		return $report;
	}

	/**
	 * Copy every option starting with $old_prefix to the same name with our prefix,
	 * replacing the old prefix inside string values too. Imported values win over the
	 * defaults Nashenski writes on its first start.
	 */
	private static function copy_options( $old_prefix ) {
		global $wpdb;

		// Settings of features Nashenski does not have (usage stats, paid licence).
		$skip = array( self::OLD . '_settings_apis_enable_stats', self::OLD . '_settings_pro_license_key' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import of the old plugin data; no WordPress API for it.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $old_prefix ) . '%' ) );
		$count = 0;

		foreach ( $names as $name ) {
			// Also skip the removed BGN/EUR dual price settings.
			if ( in_array( $name, $skip, true ) || false !== strpos( $name, '_settings_multi_currency_' ) || self::OLD . '_settings_apis_enable_multi_currency' === $name ) {
				continue;
			}

			$value = self::replace_prefix( get_option( $name ) );

			// Shipping method titles customers see: "Woo BG - Econt" becomes "Econt".
			if ( is_array( $value ) && isset( $value['title'] ) && is_string( $value['title'] ) && 0 === strpos( $value['title'], 'Woo BG - ' ) ) {
				$value['title'] = substr( $value['title'], strlen( 'Woo BG - ' ) );
			}

			update_option( str_replace( self::OLD, self::NEW, $name ), $value );
			$count++;
		}

		return $count;
	}

	private static function replace_prefix( $value ) {
		if ( is_string( $value ) ) {
			return str_replace( self::OLD . '_', self::NEW . '_', $value );
		}

		if ( is_array( $value ) ) {
			$out = array();

			foreach ( $value as $key => $item ) {
				$out[ is_string( $key ) ? str_replace( self::OLD . '_', self::NEW . '_', $key ) : $key ] = self::replace_prefix( $item );
			}

			return $out;
		}

		return $value;
	}

	/**
	 * Copy "woo_bg_*" and "_woo_bg_*" meta rows to our keys, skipping rows that already exist.
	 */
	private static function copy_meta( $table, $id_column ) {
		global $wpdb;

		$count = 0;

		foreach ( array( '', '_' ) as $lead ) {
			$old = $lead . self::OLD . '_';
			$new = $lead . self::NEW . '_';

			// One-time bulk copy of the old plugin's meta rows; there is no WordPress API for this.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import; caches are flushed afterwards.
			$count += (int) $wpdb->query( $wpdb->prepare(
				'INSERT INTO %i ( %i, meta_key, meta_value )
				SELECT o.%i, CONCAT( %s, SUBSTRING( o.meta_key, %d ) ), o.meta_value
				FROM %i o
				WHERE o.meta_key LIKE %s
				AND NOT EXISTS (
					SELECT 1 FROM %i n
					WHERE n.%i = o.%i
					AND n.meta_key = CONCAT( %s, SUBSTRING( o.meta_key, %d ) )
				)',
				$table, $id_column, $id_column, $new, strlen( $old ) + 1, $table, $wpdb->esc_like( $old ) . '%', $table, $id_column, $id_column, $new, strlen( $old ) + 1
			) );
		}

		return $count;
	}
}
