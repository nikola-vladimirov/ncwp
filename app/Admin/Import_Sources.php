<?php
namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One-click import from the other Bulgarian shipping and N-18 plugins, then the plugin is
 * switched off. Bulgarisation for WooCommerce has its own import (Migration).
 *
 * Taken over: courier logins that work with Nashenski Commerce, sender name and phone, the
 * waybill numbers of old orders (so tracking, status checks and courier requests work),
 * shipping zone methods (one Nashenski method per delivery type, the old one is turned off),
 * and from the N-18 plugin the company details and the document numbers.
 */
class Import_Sources {
	const ACTION = 'nashenski_import_source';
	const DONE_OPTION = 'nashenski_imported_sources';
	const DISMISS = 'nashenski_import_dismiss';
	const DISMISS_OPTION = 'nashenski_import_notice_dismissed';

	/** Order data of the old plugin kept on each order, shown in "From earlier plugins". */
	const LEGACY_META = '_nashenski_legacy';

	/** What each plugin leaves on orders: meta key prefixes and its own tables (with an order_id column). */
	const KEEP = array(
		'mreja_econt' => array( 'meta' => array( 'Econt_' ), 'tables' => array( 'econt_loading', 'econt_order' ) ),
		'mreja_boxnow' => array( 'meta' => array( '_boxnow_', 'boxnow_' ) ),
		'mreja_n18' => array( 'tables' => array( 'notasupto_documents', 'notasupto_document_products' ) ),
		'drusoft_econt' => array( 'meta' => array( '_drushfe_' ) ),
		'drusoft_speedy' => array( 'meta' => array( '_drushfo_' ) ),
		'econt_delivery' => array( 'meta' => array( '_order_waybill_id', '_customer_info_id', '_sync_error', 'price_cod' ) ),
		'speedy_official' => array( 'meta' => array( 'shipping_speedy_', '_speedy_' ) ),
	);

	/** Plugin name in its header => source. */
	const PLUGINS = array(
		'Econt Express WooCommerce shipping method' => 'mreja_econt',
		'BOX NOW Delivery' => 'mreja_boxnow',
		'WooCommerce Наредба Н-18 облекчен режим плъгин' => 'mreja_n18',
		'Change Speedy and Econt Shipping Methods v2' => 'mreja_buttons',
		'Drusoft Shipping for Econt' => 'drusoft_econt',
		'Drusoft Shipping for Speedy' => 'drusoft_speedy',
		'Econt Delivery' => 'econt_delivery',
		'Econt Delivery OneCheckout' => 'econt_delivery',
		'Speedy Shipping WooCommerce Plugin' => 'speedy_official',
		'WooCommerce Speedy Offices' => 'speedy_offices',
	);

	/** Settings a source leaves behind, so its data is found after the plugin was deleted. */
	const DATA = array(
		'mreja_econt' => 'woocommerce_econt_shipping_method_settings',
		'mreja_boxnow' => 'boxnowoptions',
		'mreja_n18' => 'notasuptooptions',
		'mreja_speedy' => 'woocommerce_speedy_shipping_method_settings',
		'mreja_pigeon' => 'woocommerce_pigeon_express_shipping_settings',
		'econt_delivery' => 'delivery-with-econt',
		'speedy_official' => 'woocommerce_speedy_shipping_settings',
	);

	public function __construct() {
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::DISMISS, array( __CLASS__, 'dismiss' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_legacy_box' ), 10, 2 );
	}

	private static function names() {
		return array(
			'mreja_econt' => __( 'Econt Express shipping method (mreja.net)', 'nashenski-commerce' ),
			'mreja_boxnow' => __( 'BOX NOW Delivery (mreja.net)', 'nashenski-commerce' ),
			'mreja_n18' => __( 'Ordinance N-18 light regime (mreja.net)', 'nashenski-commerce' ),
			'mreja_speedy' => __( 'Speedy shipping method (mreja.net)', 'nashenski-commerce' ),
			'mreja_pigeon' => __( 'Pigeon Express shipping (mreja.net)', 'nashenski-commerce' ),
			'mreja_buttons' => __( 'Change Speedy and Econt Shipping Methods (mreja.net)', 'nashenski-commerce' ),
			'drusoft_econt' => __( 'Drusoft Shipping for Econt', 'nashenski-commerce' ),
			'drusoft_speedy' => __( 'Drusoft Shipping for Speedy', 'nashenski-commerce' ),
			'econt_delivery' => __( 'Econt Delivery (Econt)', 'nashenski-commerce' ),
			'speedy_official' => __( 'Speedy Shipping (Speedy)', 'nashenski-commerce' ),
			'speedy_offices' => __( 'WooCommerce Speedy Offices', 'nashenski-commerce' ),
		);
	}

	/**
	 * Sources on this site: slug => array( name, plugins (file => name), done ).
	 */
	public static function sources() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$found = array();

		foreach ( get_plugins() as $file => $plugin ) {
			if ( isset( self::PLUGINS[ $plugin['Name'] ] ) ) {
				$found[ self::PLUGINS[ $plugin['Name'] ] ][ $file ] = $plugin['Name'];
			}
		}

		foreach ( self::DATA as $slug => $option ) {
			if ( get_option( $option ) && ! isset( $found[ $slug ] ) ) {
				$found[ $slug ] = array();
			}
		}

		$done = (array) get_option( self::DONE_OPTION, array() );
		$names = self::names();
		$sources = array();

		foreach ( $found as $slug => $plugins ) {
			$sources[ $slug ] = array(
				'name' => $names[ $slug ],
				'plugins' => $plugins,
				'done' => $done[ $slug ] ?? null,
			);
		}

		return $sources;
	}

	/* ---------- screens ---------- */

	public static function notice() {
		// Only where you look after plugins; the settings pages and Help show it themselves.
		$screen = get_current_screen();

		if ( $screen && in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			self::render();
		}
	}

	/**
	 * The notice: the report of the last import, or the sources still to import.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$sources = self::sources();
		$imported = sanitize_key( (string) nashenski_query_arg( 'nashenski_source_imported' ) );

		if ( $imported && ! empty( $sources[ $imported ]['done']['report'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html( sprintf(
				/* translators: %s: plugin name */
				__( 'Imported from %s:', 'nashenski-commerce' ),
				$sources[ $imported ]['name']
			) ) . '</strong><br>' . wp_kses_post( implode( '<br>', array_map( 'esc_html', $sources[ $imported ]['done']['report'] ) ) ) . '</p></div>';
		}

		$open = self::open_names();

		// Dismissed: back only when another plugin turns up.
		if ( ! array_diff( array_keys( $open ), (array) get_option( self::DISMISS_OPTION, array() ) ) ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p>
				<?php
				echo esc_html( sprintf(
					/* translators: %s: plugin names */
					__( 'Nashenski Commerce can take over the data of %s, then switch it off.', 'nashenski-commerce' ),
					implode( ', ', $open )
				) );
				?>
				<a href="<?php echo esc_url( Settings::url( 'help' ) . '#import' ); ?>"><?php esc_html_e( 'Review and import', 'nashenski-commerce' ); ?></a>
				|
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::DISMISS ), self::DISMISS ) ); ?>"><?php esc_html_e( 'Dismiss', 'nashenski-commerce' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugins still to import: slug => name (Bulgarisation included).
	 */
	private static function open_names() {
		$names = array();

		if ( Migration::has_old_data() && ! get_option( Migration::DONE_OPTION ) ) {
			$names['bulgarisation'] = __( 'Bulgarisation for WooCommerce', 'nashenski-commerce' );
		}

		foreach ( self::sources() as $slug => $source ) {
			if ( ! $source['done'] ) {
				$names[ $slug ] = $source['name'];
			}
		}

		return $names;
	}

	public static function dismiss() {
		if ( current_user_can( 'manage_woocommerce' ) && check_admin_referer( self::DISMISS ) ) {
			update_option( self::DISMISS_OPTION, array_keys( self::open_names() ), false );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	public static function url( $slug ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION . '&source=' . $slug ), self::ACTION );
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ) );
		}

		check_admin_referer( self::ACTION );
		$slug = sanitize_key( (string) nashenski_query_arg( 'source' ) );

		if ( isset( self::sources()[ $slug ] ) ) {
			self::import( $slug );
		}

		$back = wp_get_referer() ? wp_get_referer() : Settings::url();
		wp_safe_redirect( add_query_arg( 'nashenski_source_imported', $slug, $back ) );
		exit;
	}

	/* ---------- import ---------- */

	/**
	 * Import one source and switch its plugins off. Returns the lines of the report.
	 */
	public static function import( $slug ) {
		$count = array( 'settings' => 0, 'waybills' => 0, 'zones' => 0, 'documents' => 0, 'skipped' => 0, 'kept' => 0 );
		$notes = array();
		$source = self::sources()[ $slug ] ?? array( 'plugins' => array() );

		switch ( $slug ) {
			case 'mreja_econt':
				$s = (array) get_option( 'woocommerce_econt_shipping_method_settings', array() );
				self::login( 'econt', array( 'user' => $s['username'] ?? '', 'password' => $s['password'] ?? '', 'env' => '1' === (string) ( $s['live'] ?? '' ) ? 'live' : 'demo' ), $count );
				self::fill( 'econt', 'name', ( $s['name'] ?? '' ) ?: ( $s['company'] ?? '' ), $count );
				self::fill( 'econt', 'phone', $s['phone'] ?? '', $count );

				foreach ( self::table_rows( 'econt_loading' ) as $row ) {
					self::waybill( $row['order_id'], 'econt', array( $row['loading_num'] ), $row['pdf_url'], array( 'econt_shipping_method' ), $count );
				}

				if ( 'yes' === ( $s['enabled'] ?? '' ) ) {
					// A shipping method for every zone at once: Nashenski adds its methods to the zone of Bulgaria.
					$types = array_keys( array_filter( array( 'office' => $s['send_to_office'] ?? '1', 'automat' => $s['send_to_machine'] ?? '1', 'address' => $s['send_to_door'] ?? '1' ), function ( $on ) {
						return '0' !== (string) $on;
					} ) );
					self::zones( null, 'econt', $types, $count );
				}
				break;

			case 'mreja_boxnow':
				$o = (array) get_option( 'boxnowoptions', array() );
				self::login( 'boxnow', array( 'client_id' => $o['client_id'] ?? '', 'client_secret' => $o['client_secret'] ?? '', 'env' => false !== strpos( (string) ( $o['api_url'] ?? '' ), 'stage' ) ? 'demo' : 'live' ), $count );

				if ( ! empty( $o['warehouses'][0]['id'] ) ) {
					self::fill( 'boxnow_send_from', 'location', 'originID-' . $o['warehouses'][0]['id'], $count );
				}

				foreach ( self::orders_with( '_boxnow_parcel_ids' ) as $order ) {
					self::waybill( $order, 'boxnow', (array) $order->get_meta( '_boxnow_parcel_ids' ), '', array( 'boxnow_delivery' ), $count );
				}

				self::zones( 'boxnow_delivery', 'boxnow', array( '' ), $count );
				break;

			case 'mreja_n18':
				self::n18( (array) get_option( 'notasuptooptions', array() ), $count );
				break;

			case 'mreja_speedy':
				$s = (array) get_option( 'woocommerce_speedy_shipping_method_settings', array() );
				self::login( 'speedy', array( 'user' => $s['api_username'] ?? '', 'password' => $s['api_password'] ?? '' ), $count );
				self::fill( 'speedy', 'name', $s['contact_name'] ?? '', $count );
				self::fill( 'speedy', 'phone', $s['phone1'] ?? '', $count );
				self::fill( 'speedy_send_from', 'office', ! empty( $s['dropoff_office_id'] ) ? 'officeID-' . $s['dropoff_office_id'] : '', $count );
				self::fill( 'speedy', 'send_from', strtolower( $s['send_from'] ?? '' ), $count );
				self::zones( 'speedy_shipping_method', 'speedy', array( 'office', 'automat', 'address' ), $count );
				break;

			case 'mreja_pigeon':
				// Nashenski's Pigeon sender is an office or an address and has no name or phone field, so the contact details stay on the old plugin.
				$s = (array) get_option( 'woocommerce_pigeon_express_shipping_settings', array() );
				$o = (array) get_option( 'pigeon_express_options', array() );
				self::login( 'pigeon', array( 'api_key' => $s['api_key'] ?? '', 'api_secret' => $s['api_secret'] ?? '', 'env' => in_array( (string) ( $s['live'] ?? '' ), array( '1', 'yes' ), true ) ? 'live' : 'sandbox' ), $count );
				self::fill( 'pigeon_send_from', 'office', ! empty( $o['pickup_office_id'] ) ? 'officeID-' . $o['pickup_office_id'] : '', $count );
				self::fill( 'pigeon', 'send_from', strtolower( $s['send_from'] ?? '' ), $count );
				self::zones( 'pigeon_express_shipping', 'pigeon', array( 'office', 'locker', 'address' ), $count );
				break;

			case 'drusoft_econt':
				foreach ( self::orders_with( '_drushfe_waybill_id' ) as $order ) {
					$response = (array) $order->get_meta( '_drushfe_waybill_response' );
					self::waybill( $order, 'econt', array( ( $response['shipmentNumber'] ?? '' ) ?: $order->get_meta( '_drushfe_waybill_id' ) ), $response['pdfURL'] ?? '', array( 'drushfe_econt' ), $count );
				}

				self::zones( 'drushfe_econt', 'econt', array( 'office', 'automat', 'address' ), $count );
				$notes[] = __( 'The Econt key of that plugin works only with Econt\'s "Deliver with Econt" service. Enter your Econt username and password in Couriers > Econt.', 'nashenski-commerce' );
				break;

			case 'drusoft_speedy':
				$s = self::instance_settings( 'drushfo_speedy' );
				self::login( 'speedy', array( 'user' => $s['speedy_username'] ?? '', 'password' => $s['speedy_password'] ?? '' ), $count );
				self::fill( 'speedy', 'name', $s['sender_name'] ?? '', $count );
				self::fill( 'speedy', 'phone', $s['sender_phone'] ?? '', $count );

				foreach ( self::orders_with( '_drushfo_waybill_id' ) as $order ) {
					self::waybill( $order, 'speedy', array( $order->get_meta( '_drushfo_waybill_id' ) ), '', array( 'drushfo_speedy' ), $count );
				}

				self::zones( 'drushfo_speedy', 'speedy', array( 'office', 'automat', 'address' ), $count );
				break;

			case 'econt_delivery':
				foreach ( self::orders_with( '_order_waybill_id' ) as $order ) {
					self::waybill( $order, 'econt', array( $order->get_meta( '_order_waybill_id' ) ), '', array( 'delivery_with_econt' ), $count );
				}

				self::zones( 'delivery_with_econt', 'econt', array( 'office', 'automat', 'address' ), $count );
				$notes[] = __( 'The Econt key of that plugin works only with Econt\'s "Deliver with Econt" service. Enter your Econt username and password in Couriers > Econt.', 'nashenski-commerce' );
				break;

			case 'speedy_official':
				$s = (array) get_option( 'woocommerce_speedy_shipping_settings', array() );
				self::login( 'speedy', array( 'user' => $s['speedy_name'] ?? '', 'password' => $s['speedy_password'] ?? '' ), $count );

				foreach ( self::orders_with( 'shipping_speedy_waybill' ) as $order ) {
					$waybill = json_decode( (string) $order->get_meta( 'shipping_speedy_waybill' ), true );
					$parcels = wp_list_pluck( (array) ( $waybill['parcels'] ?? array() ), 'id' );
					self::waybill( $order, 'speedy', $parcels ? $parcels : array( $waybill['id'] ?? '' ), '', array( 'speedy_shipping' ), $count );
				}

				self::zones( 'speedy_shipping', 'speedy', array( 'office', 'automat', 'address' ), $count );
				break;
		}

		$report = array(
			/* translators: %d: number */
			sprintf( _n( '%d setting', '%d settings', $count['settings'], 'nashenski-commerce' ), $count['settings'] ),
			/* translators: %d: number */
			sprintf( _n( '%d order waybill', '%d order waybills', $count['waybills'], 'nashenski-commerce' ), $count['waybills'] ),
			/* translators: %d: number */
			sprintf( _n( '%d shipping zone method', '%d shipping zone methods', $count['zones'], 'nashenski-commerce' ), $count['zones'] ),
		);

		if ( $count['documents'] ) {
			/* translators: %d: number */
			$report[] = sprintf( _n( '%d document number', '%d document numbers', $count['documents'], 'nashenski-commerce' ), $count['documents'] );
		}

		if ( $count['skipped'] ) {
			/* translators: %d: number */
			$report[] = sprintf( _n( '%d document number skipped (conflict)', '%d document numbers skipped (conflict)', $count['skipped'], 'nashenski-commerce' ), $count['skipped'] );
		}

		if ( $count['kept'] ) {
			/* translators: %d: number of settings */
			$report[] = sprintf( __( 'Already set, kept as it is: %d', 'nashenski-commerce' ), $count['kept'] );
		}

		$kept = self::keep( $slug );

		if ( $kept ) {
			/* translators: %d: number of orders */
			$report[] = sprintf( _n( '%d order keeps everything the old plugin saved on it (see "From earlier plugins" on the order)', '%d orders keep everything the old plugin saved on them (see "From earlier plugins" on the order)', $kept, 'nashenski-commerce' ), $kept );
		}

		$report = array_merge( $report, $notes, self::switch_off( array_keys( $source['plugins'] ) ) );

		$done = (array) get_option( self::DONE_OPTION, array() );
		$done[ $slug ] = array( 'time' => time(), 'report' => $report );
		update_option( self::DONE_OPTION, $done, false );
		wp_cache_flush();

		return $report;
	}

	/**
	 * Switch plugins off. Returns one line per plugin that was on.
	 */
	public static function switch_off( $files ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ) );
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$lines = array();

		foreach ( $files as $file ) {
			if ( is_plugin_active( $file ) ) {
				deactivate_plugins( $file );
				$plugin = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
				/* translators: %s: plugin name */
				$lines[] = sprintf( __( '%s was switched off.', 'nashenski-commerce' ), $plugin['Name'] ?: $file );
			}
		}

		return $lines;
	}

	/**
	 * Company details and document numbers of the N-18 plugin.
	 */
	private static function n18( $o, &$count ) {
		$map = array(
			'nap' => array( 'company_name' => 'seller_company_name', 'eik' => 'seller_company_id', 'dds_number' => 'seller_company_vat_num', 'mol' => 'seller_company_mol', 'address' => 'seller_company_address', 'city' => 'seller_company_city', 'email' => 'seller_company_email', 'phone_number' => 'seller_company_phone', 'nap_number' => 'shop_nap_id', 'domain' => 'shop_domain_name' ),
			'invoice' => array( 'prepared_by' => 'invoice_doccreator', 'footer_text' => 'document_footer', 'accountant_email' => 'notasupto_xml_send_emails' ),
		);

		foreach ( $map as $group => $fields ) {
			foreach ( $fields as $ours => $theirs ) {
				self::fill( $group, $ours, is_string( $o[ $theirs ] ?? null ) ? $o[ $theirs ] : '', $count );
			}
		}

		// Order documents keep their numbers; the next numbers continue after the highest one.
		$keys = array( 'purchase_order' => 'nashenski_order_number', 'invoice' => 'nashenski_order_invoice_number' );
		$highest = array( 'purchase_order' => 0, 'invoice' => 0 );

		foreach ( self::table_rows( 'notasupto_documents' ) as $row ) {
			if ( ! in_array( $row['type'], array( 'purchase_order', 'invoice', 'credit_note' ), true ) ) {
				continue;
			}

			$type = 'credit_note' === $row['type'] ? 'invoice' : $row['type'];
			$highest[ $type ] = max( $highest[ $type ], (int) $row['num'] );
			$order = isset( $keys[ $row['type'] ] ) ? wc_get_order( $row['order_id'] ) : null;

			$option = 'purchase_order' === $row['type'] ? 'next_invoice_number' : 'next_invoice_separate_number';
			try {
				if ( $order && \Nashenski\Invoice\Number_Allocator::import_number( $order, $keys[ $row['type'] ], $row['num'], $option ) ) {
					$count['documents']++;
				}
			} catch ( \RuntimeException $e ) {
				// A duplicate, empty or locked number: that order stays as it is and is reported.
				$count['skipped']++;
			}
		}

		// An empty separate counter means shared numbering: setting it would switch the shop to separate numbering,
		// so the shared counter has to move past the imported invoice numbers instead.
		$shared = ! nashenski_get_option( 'invoice', 'next_invoice_separate_number' );
		if ( $shared ) {
			$highest['purchase_order'] = max( $highest['purchase_order'], $highest['invoice'] );
		}

		foreach ( array( 'purchase_order' => 'next_invoice_number', 'invoice' => 'next_invoice_separate_number' ) as $type => $option ) {
			if ( 'invoice' === $type && $shared ) {
				continue;
			}
			if ( $highest[ $type ] && $highest[ $type ] + 1 > (int) nashenski_get_option( 'invoice', $option ) ) {
				nashenski_set_option( 'invoice', $option, str_pad( $highest[ $type ] + 1, 10, '0', STR_PAD_LEFT ) );
				$count['settings']++;
			}
		}
	}

	/* ---------- everything the old plugin saved on orders ---------- */

	/**
	 * Copy what the plugin saved on each order (its meta and its own table rows, with tracking
	 * events and document PDF files) to the order, so it stays visible once the plugin is off.
	 * Returns the number of orders.
	 */
	public static function keep( $slug ) {
		global $wpdb;

		$spec = self::KEEP[ $slug ] ?? array();
		$data = array();
		$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		$meta_table = $hpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;

		foreach ( $spec['meta'] ?? array() as $prefix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import; no WordPress API finds meta by key prefix.
			$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT %i AS id, meta_key, meta_value FROM %i WHERE meta_key LIKE %s', $hpos ? 'order_id' : 'post_id', $meta_table, $wpdb->esc_like( $prefix ) . '%' ), ARRAY_A );

			foreach ( $rows as $row ) {
				$data[ $row['id'] ][ $row['meta_key'] ] = self::safe_unserialize( $row['meta_value'] );
			}
		}

		$tracking = array();

		foreach ( 'mreja_econt' === $slug ? self::table_rows( 'econt_loading_tracking' ) : array() as $event ) {
			$tracking[ $event['econt_loading_id'] ][] = $event;
		}

		foreach ( $spec['tables'] ?? array() as $table ) {
			foreach ( self::table_rows( $table ) as $row ) {
				if ( empty( $row['order_id'] ) ) {
					continue;
				}

				if ( 'econt_loading' === $table ) {
					$row['tracking'] = $tracking[ $row['econt_loading_id'] ] ?? array();
				} elseif ( 'notasupto_documents' === $table ) {
					$row['pdf'] = self::n18_files( $row );
				} elseif ( 'econt_order' === $table ) {
					$row['data'] = self::safe_unserialize( $row['data'] );
				}

				$data[ $row['order_id'] ][ $table ][] = $row;
			}
		}

		$count = 0;

		foreach ( $data as $order_id => $values ) {
			$order = wc_get_order( $order_id );

			// Meta of other posts (products) with the same prefix is not order data.
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			$legacy = (array) $order->get_meta( self::LEGACY_META );
			$legacy[ $slug ] = $values;
			$order->update_meta_data( self::LEGACY_META, $legacy );
			$order->save();
			$count++;
		}

		return $count;
	}

	/**
	 * The PDF files the N-18 plugin saved for a document (uploads/notasupto/Y/m/type-number*.pdf).
	 */
	private static function n18_files( $row ) {
		$upload = wp_upload_dir();
		// "type-number-hash.pdf" (Bulgarian) and "type-number_lang-hash.pdf", so invoice 12 never matches invoice 120.
		$base = $upload['basedir'] . '/notasupto/*/*/' . str_replace( '/', '-', $row['type'] . '-' . $row['num'] );
		$files = array_merge( glob( $base . '-*.pdf' ) ?: array(), glob( $base . '_*.pdf' ) ?: array() );

		return array_map( function ( $file ) use ( $upload ) {
			return $upload['baseurl'] . str_replace( '\\', '/', substr( $file, strlen( $upload['basedir'] ) ) );
		}, $files );
	}

	public static function add_legacy_box( $type, $object = null ) {
		$order = $object instanceof \WC_Order ? $object : ( isset( $object->ID ) ? wc_get_order( $object->ID ) : null );

		if ( $order && $order->get_meta( self::LEGACY_META ) ) {
			add_meta_box( 'nashenski_legacy', __( 'From earlier plugins', 'nashenski-commerce' ), array( __CLASS__, 'legacy_box' ), $type, 'normal', 'low' );
		}
	}

	public static function legacy_box( $object ) {
		$order = $object instanceof \WC_Order ? $object : wc_get_order( $object->ID );
		$names = self::names();

		echo '<p class="description">' . esc_html__( 'What the earlier plugins saved on this order, kept when their data was imported.', 'nashenski-commerce' ) . '</p>';

		foreach ( (array) $order->get_meta( self::LEGACY_META ) as $slug => $values ) {
			echo '<h4>' . esc_html( $names[ $slug ] ?? $slug ) . '</h4>';
			echo wp_kses_post( self::table( $values ) );
		}
	}

	/**
	 * Nested key / value table; addresses become links.
	 */
	private static function table( $values ) {
		$rows = '';

		foreach ( (array) $values as $key => $value ) {
			if ( null === $value || '' === $value || array() === $value ) {
				continue;
			}

			if ( is_array( $value ) || is_object( $value ) ) {
				$cell = self::table( (array) $value );
			} elseif ( is_string( $value ) && preg_match( '#^https?://#', $value ) ) {
				$cell = '<a href="' . esc_url( $value ) . '" target="_blank" rel="noopener">' . esc_html( $value ) . '</a>';
			} else {
				$cell = nl2br( esc_html( (string) $value ) );
			}

			$rows .= '<tr><th>' . esc_html( (string) $key ) . '</th><td>' . $cell . '</td></tr>';
		}

		return $rows ? '<table class="widefat striped nashenski-legacy"><tbody>' . $rows . '</tbody></table>' : '';
	}

	/**
	 * Customer order page: the documents the N-18 plugin made for this order.
	 */
	public static function customer_documents( $order ) {
		$legacy = (array) $order->get_meta( self::LEGACY_META );
		$types = array(
			'purchase_order' => __( 'Order document', 'nashenski-commerce' ),
			'invoice' => __( 'Invoice', 'nashenski-commerce' ),
			'credit_note' => __( 'Credit note', 'nashenski-commerce' ),
			'purchase_order_cancel' => __( 'Cancelled order document', 'nashenski-commerce' ),
		);
		$links = array();

		foreach ( $legacy['mreja_n18']['notasupto_documents'] ?? array() as $document ) {
			foreach ( (array) ( $document['pdf'] ?? array() ) as $url ) {
				$label = ( $types[ $document['type'] ] ?? $document['type'] ) . ' ' . $document['num'] . ( false !== strpos( basename( $url ), '_en' ) ? ' (EN)' : '' );
				$links[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>';
			}
		}

		if ( $links ) {
			echo '<p class="nashenski-legacy-documents">' . wp_kses_post( implode( ' | ', $links ) ) . '</p>';
		}
	}

	/* ---------- helpers ---------- */

	/**
	 * Fill an empty Nashenski setting.
	 */
	private static function fill( $group, $name, $value, &$count ) {
		$value = trim( (string) $value );

		if ( '' !== $value && '' === trim( (string) nashenski_get_option( $group, $name ) ) ) {
			nashenski_set_option( $group, $name, $value );
			$count['settings']++;
		} elseif ( '' !== $value ) {
			$count['kept']++;
		}
	}

	/**
	 * A courier login, taken only when Nashenski has none yet; the environment goes with it.
	 */
	private static function login( $courier, $fields, &$count ) {
		$keys = array_diff( array_keys( $fields ), array( 'env' ) );

		foreach ( $keys as $key ) {
			if ( '' !== trim( (string) nashenski_get_option( $courier, $key ) ) ) {
				$count['kept']++;
				return;
			}

			if ( '' === trim( (string) $fields[ $key ] ) ) {
				return;
			}
		}

		foreach ( $fields as $key => $value ) {
			nashenski_set_option( $courier, $key, $value );
			$count['settings']++;
		}
	}

	/**
	 * Like maybe_unserialize(), but never builds objects from legacy rows.
	 */
	private static function safe_unserialize( $value ) {
		return is_serialized( $value ) ? @unserialize( trim( $value ), array( 'allowed_classes' => false ) ) : $value; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- legacy rows, classes are not allowed.
	}

	/**
	 * All rows of another plugin's own table (one-time import), or array() without the table.
	 */
	private static function table_rows( $table ) {
		global $wpdb;

		$table = $wpdb->prefix . $table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import from another plugin's own table.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import from another plugin's own table.
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );
	}

	private static function orders_with( $meta_key ) {
		return wc_get_orders( array(
			'limit' => -1,
			'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_compare' => 'EXISTS',
		) );
	}

	/**
	 * Settings of the first zone instance of a shipping method.
	 */
	private static function instance_settings( $method_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import; no WordPress API lists zone methods by ID.
		$instance = $wpdb->get_var( $wpdb->prepare( "SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id = %s ORDER BY is_enabled DESC LIMIT 1", $method_id ) );

		return $instance ? (array) get_option( "woocommerce_{$method_id}_{$instance}_settings", array() ) : array();
	}

	/**
	 * Waybill numbers of an old order, in the form Nashenski reads, and its shipping line switched
	 * to the Nashenski method. Orders that already have a Nashenski waybill are left alone.
	 */
	private static function waybill( $order, $courier, $numbers, $pdf, $old_methods, &$count ) {
		$order = is_object( $order ) ? $order : wc_get_order( $order );
		$numbers = array_values( array_filter( array_map( 'strval', (array) $numbers ), 'strlen' ) );

		if ( ! $order || ! $numbers ) {
			return;
		}

		$parcels = array_map( function ( $number ) {
			return array( 'id' => $number );
		}, $numbers );
		$meta = array(
			'econt' => array(
				'nashenski_econt_label' => array( 'label' => array( 'shipmentNumber' => $numbers[0] ) ),
				'nashenski_econt_shipment_status' => array( 'label' => array( 'shipmentNumber' => $numbers[0], 'pdfURL' => Courier_Pdf::url( $pdf ) ) ),
			),
			'speedy' => array(
				'nashenski_speedy_label' => array( 'id' => $numbers[0] ),
				'nashenski_speedy_shipment_status' => array( 'id' => $numbers[0], 'parcels' => $parcels ),
			),
			'boxnow' => array(
				'nashenski_boxnow_shipment_status' => array( 'parcels' => $parcels ),
			),
		)[ $courier ];

		if ( $order->get_meta( array_key_first( $meta ) ) ) {
			return;
		}

		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( $key, $value );
		}

		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( in_array( $item->get_method_id(), $old_methods, true ) ) {
				$item->set_method_id( 'nashenski_' . $courier );
				$item->save();
			}
		}

		$order->save();
		$count['waybills']++;
	}

	/**
	 * Add the Nashenski methods (one per delivery type) to every zone that has the old method,
	 * and turn the old one off. $old_method null: a method for all zones; the zone of Bulgaria gets them.
	 */
	private static function zones( $old_method, $courier, $types, &$count ) {
		global $wpdb;

		$table = $wpdb->prefix . 'woocommerce_shipping_zone_methods';

		if ( null === $old_method ) {
			$zone = \WC_Shipping_Zones::get_zone_matching_package( array( 'destination' => array( 'country' => 'BG', 'state' => '', 'postcode' => '', 'city' => '', 'address' => '' ) ) );
			$rows = array( array( 'zone_id' => $zone->get_id(), 'instance_id' => 0 ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import; no WordPress API lists zone methods by ID.
			$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT zone_id, instance_id FROM %i WHERE method_id = %s AND is_enabled = 1', $table, $old_method ), ARRAY_A );
		}

		$titles = array(
			'office' => __( 'To an office', 'nashenski-commerce' ),
			'automat' => __( 'To a parcel locker', 'nashenski-commerce' ),
			'locker' => __( 'To a parcel locker', 'nashenski-commerce' ),
			'address' => __( 'To an address', 'nashenski-commerce' ),
			'' => 'BOX NOW',
		);

		foreach ( $rows as $row ) {
			$zone = new \WC_Shipping_Zone( (int) $row['zone_id'] );
			$have = array();

			foreach ( $zone->get_shipping_methods() as $method ) {
				if ( 'nashenski_' . $courier === $method->id ) {
					$have[] = (string) $method->get_option( 'delivery_type' );
				}
			}

			foreach ( array_diff( $types, $have ) as $type ) {
				$instance = $zone->add_shipping_method( 'nashenski_' . $courier );
				update_option( "woocommerce_nashenski_{$courier}_{$instance}_settings", array_filter( array( 'title' => $titles[ $type ], 'delivery_type' => $type ), 'strlen' ) );
				$count['zones']++;
			}

			if ( $row['instance_id'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the old method is turned off, not deleted.
				$wpdb->update( $table, array( 'is_enabled' => 0 ), array( 'instance_id' => (int) $row['instance_id'] ) );
			}
		}

		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}
}
