<?php
/**
 * Migration.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One-click import of settings, shipping zones and order data saved by
 * Bulgarisation for WooCommerce (the plugin Nashenski is forked from).
 *
 * Old data uses the "woo_bg" prefix (up to 4.x) or "bulgarisation" (5.0 on); ours uses "nashenski". Settings and order
 * meta are copied (the old rows stay). Shipping zone methods and the method ID
 * stored on old orders' shipping lines are switched to our IDs, so existing
 * zones and labels keep working.
 */
class Migration {
	const OLD         = array( 'woo_bg', 'bulgarisation' );
	const NEW         = 'nashenski';
	const DONE_OPTION = 'nashenski_migrated_from_woo_bg';
	const ACTION      = 'nashenski_import_woo_bg';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Has old data.
	 *
	 * @return string Result.
	 */
	public static function has_old_data() {
		global $wpdb;

		$found = wp_cache_get( 'has_old_data', 'nashenski' );

		if ( false === $found ) {
			$found = 'no';

			foreach ( self::OLD as $old ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- options by name prefix; no WordPress API lists them.
				if ( $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1", $wpdb->esc_like( $old . '_settings_' ) . '%' ) ) ) {
					$found = 'yes';
					break;
				}
			}

			wp_cache_set( 'has_old_data', $found, 'nashenski', HOUR_IN_SECONDS );
		}

		return 'yes' === $found;
	}

	/**
	 * Notice.
	 */
	public static function notice() {
		// WooCommerce hides admin notices on its own pages, so our settings page renders this inside its content.
		if ( Settings::is_current() ) {
			return;
		}

		self::render();
	}

	/**
	 * Render.
	 */
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
					echo esc_html(
						sprintf(
						/* translators: 1: settings, 2: shipping method settings, 3: shipping zone methods, 4: order shipping lines, 5: order data rows */
							__( 'Import finished: %1$d settings, %2$d shipping method settings, %3$d shipping zone methods, %4$d order shipping lines and %5$d order data rows.', 'nashenski-commerce' ),
							$r['settings'] ?? 0,
							$r['shipping_options'] ?? 0,
							$r['zone_methods'] ?? 0,
							$r['order_shipping_lines'] ?? 0,
							$r['order_meta'] ?? 0
						)
					);

					if ( ! empty( $r['skipped_numbers'] ) ) {
						echo '<br>' . esc_html(
							sprintf(
							/* translators: %d: number of document numbers */
								_n( '%d document number skipped (conflict)', '%d document numbers skipped (conflict)', $r['skipped_numbers'], 'nashenski-commerce' ),
								$r['skipped_numbers']
							)
						);
					}

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

	/**
	 * Handle.
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ) );
		}

		check_admin_referer( self::ACTION );

		// A second run would overwrite options such as the next invoice numbers.
		if ( ! get_option( self::DONE_OPTION ) ) {
			$report                 = self::run();
			$report['switched_off'] = Import_Sources::switch_off( self::plugin_files() );
			// The old plugin's documents sit in a public folder; move them into the private one.
			\Nashenski\Document_Storage::schedule_migration( true );
			update_option(
				self::DONE_OPTION,
				array(
					'time'   => time(),
					'report' => $report,
				),
				false
			);
		}

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

		return array_keys(
			array_filter(
				get_plugins(),
				function ( $plugin ) {
					return in_array( $plugin['Name'], array( 'Bulgarisation for WooCommerce', 'Bulgarisation for WooCommerce - Pro' ), true );
				}
			)
		);
	}

	/**
	 * Run the import.
	 *
	 * @return array Counts of what was imported.
	 */
	public static function run() {
		$report = array();

		foreach ( self::OLD as $old ) {
			foreach ( self::import_from( $old ) as $key => $count ) {
				$report[ $key ] = ( $report[ $key ] ?? 0 ) + $count;
			}
		}

		wp_cache_flush();

		return $report;
	}

	/**
	 * What one old prefix left behind: settings, shipping zones, order data.
	 *
	 * @param mixed $old Old.
	 */
	private static function import_from( $old ) {
		global $wpdb;

		$report = array(
			'settings'         => self::copy_options( $old . '_settings_', $old ),
			'shipping_options' => self::copy_options( 'woocommerce_' . $old . '_', $old ),
		);

		// Shipping zone methods: woo_bg_econt -> nashenski_econt.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import of the old plugin data; no WordPress API for it.
		$report['zone_methods'] = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}woocommerce_shipping_zone_methods SET method_id = REPLACE( method_id, %s, %s ) WHERE method_id LIKE %s",
				$old . '_',
				self::NEW . '_',
				$wpdb->esc_like( $old . '_' ) . '%'
			)
		);

		// Shipping lines of existing orders, so labels can still be made for them.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import of the old plugin data; no WordPress API for it.
		$report['order_shipping_lines'] = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}woocommerce_order_itemmeta SET meta_value = REPLACE( meta_value, %s, %s ) WHERE meta_key = 'method_id' AND meta_value LIKE %s",
				$old . '_',
				self::NEW . '_',
				$wpdb->esc_like( $old . '_' ) . '%'
			)
		);

		// Order meta in both storages (HPOS table and posts, when sync is on).
		$report['order_meta']      = 0;
		$report['skipped_numbers'] = 0;
		$hpos_table                = $wpdb->prefix . 'wc_orders_meta';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import of the old plugin data; no WordPress API for it.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_table ) ) === $hpos_table ) {
			$report['order_meta'] += self::copy_meta( $hpos_table, 'order_id', $old, $report['skipped_numbers'] );
		}

		$report['order_meta'] += self::copy_meta( $wpdb->postmeta, 'post_id', $old, $report['skipped_numbers'] );

		return $report;
	}

	/**
	 * Copy every option starting with $old_prefix to the same name with our prefix,
	 * replacing the old prefix inside string values too. Imported values win over the
	 * defaults Nashenski writes on its first start.
	 *
	 * @param mixed $old_prefix Old prefix.
	 * @param mixed $old Old.
	 */
	private static function copy_options( $old_prefix, $old ) {
		global $wpdb;

		// Settings of features Nashenski does not have (usage stats, paid licence).
		$skip = array( $old . '_settings_apis_enable_stats', $old . '_settings_pro_license_key' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import of the old plugin data; no WordPress API for it.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( $old_prefix ) . '%' ) );
		$count = 0;

		foreach ( $names as $name ) {
			// Also skip the removed BGN/EUR dual price settings.
			if ( in_array( $name, $skip, true ) || false !== strpos( $name, '_settings_multi_currency_' ) || $old . '_settings_apis_enable_multi_currency' === $name ) {
				continue;
			}

			$value = self::replace_prefix( get_option( $name ), $old );

			// Never lower a document number counter Nashenski already has: issued numbers would be reused.
			if ( preg_match( '/_next_invoice(_separate)?_number$/', $name ) ) {
				$current = get_option( str_replace( $old, self::NEW, $name ) );

				if ( is_numeric( $current ) && ( ! is_numeric( $value ) || (float) $current >= (float) $value ) ) {
					continue;
				}
			}

			// Shipping method titles customers see: "Woo BG - Econt" (4.x) or "Българизация - Еконт" (5.x) becomes "Econt".
			if ( is_array( $value ) && isset( $value['title'] ) && is_string( $value['title'] ) ) {
				$value['title'] = preg_replace( '/^(Woo BG|Българизация) - /u', '', $value['title'] );
			}

			update_option( str_replace( $old, self::NEW, $name ), $value );
			++$count;
		}

		return $count;
	}

	/**
	 * Replace prefix.
	 *
	 * @param mixed $value Value.
	 * @param mixed $old Old.
	 * @return mixed Result.
	 */
	private static function replace_prefix( $value, $old ) {
		if ( is_string( $value ) ) {
			return str_replace( $old . '_', self::NEW . '_', $value );
		}

		if ( is_array( $value ) ) {
			$out = array();

			foreach ( $value as $key => $item ) {
				$out[ is_string( $key ) ? str_replace( $old . '_', self::NEW . '_', $key ) : $key ] = self::replace_prefix( $item, $old );
			}

			return $out;
		}

		return $value;
	}

	/**
	 * Copy "woo_bg_*" and "_woo_bg_*" meta rows to our keys, skipping rows that already exist.
	 *
	 * @param mixed $table Table.
	 * @param mixed $id_column Id column.
	 * @param mixed $old_prefix Old prefix.
	 * @param &     $skipped Skipped.
	 */
	private static function copy_meta( $table, $id_column, $old_prefix, &$skipped = 0 ) {
		global $wpdb;

		$count         = 0;
		$number_fields = array(
			'order_number'          => 'next_invoice_number',
			'refunded_order_number' => 'next_invoice_number',
			'order_invoice_number'  => 'next_invoice_separate_number',
		);
		// Numbering goes through the same lock as checkout, not the bulk metadata copy.
		foreach ( $number_fields as $field => $option ) {
			// Orders that already have the number are left out here, so they are not loaded.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import of the old plugin data; no WordPress API for it.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT o.%i AS order_id, o.meta_value FROM %i o WHERE o.meta_key = %s AND NOT EXISTS ( SELECT 1 FROM %i n WHERE n.%i = o.%i AND n.meta_key = %s AND n.meta_value NOT IN ( '', '0' ) )",
					$id_column,
					$table,
					$old_prefix . '_' . $field,
					$table,
					$id_column,
					$id_column,
					self::NEW . '_' . $field
				)
			);
			// Only the authoritative table is imported; the other one is stale or mirrored by WooCommerce.
			if ( ! $rows || \Nashenski\Invoice\Number_Allocator::storage()[0] !== $table ) {
				continue;
			}
			foreach ( $rows as $row ) {
				$order = wc_get_order( $row->order_id );
				if ( ! $order ) {
					continue;
				}
				try {
					if ( \Nashenski\Invoice\Number_Allocator::import_number( $order, self::NEW . '_' . $field, $row->meta_value, $option ) ) {
						++$count;
					}
				} catch ( \RuntimeException $e ) {
					// A duplicate, empty or locked number: that order stays as it is and is reported.
					++$skipped;
				}
			}
		}

		foreach ( array( '', '_' ) as $lead ) {
			$old = $lead . $old_prefix . '_';
			$new = $lead . self::NEW . '_';

			// One-time bulk copy of the old plugin's meta rows; there is no WordPress API for this.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import; caches are flushed afterwards.
			$count += (int) $wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i ( %i, meta_key, meta_value )
				SELECT o.%i, CONCAT( %s, SUBSTRING( o.meta_key, %d ) ), o.meta_value
				FROM %i o
				WHERE o.meta_key LIKE %s
				AND o.meta_key NOT IN (%s, %s, %s)
				AND NOT EXISTS (
					SELECT 1 FROM %i n
					WHERE n.%i = o.%i
					AND n.meta_key = CONCAT( %s, SUBSTRING( o.meta_key, %d ) )
				)',
					$table,
					$id_column,
					$id_column,
					$new,
					strlen( $old ) + 1,
					$table,
					$wpdb->esc_like( $old ) . '%',
					$old_prefix . '_order_number',
					$old_prefix . '_refunded_order_number',
					$old_prefix . '_order_invoice_number',
					$table,
					$id_column,
					$id_column,
					$new,
					strlen( $old ) + 1
				)
			);
		}

		return $count;
	}
}
