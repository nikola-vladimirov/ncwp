<?php
/**
 * Import Sources.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * One-click import from the other Bulgarian shipping and N-18 plugins, then the owner is asked to
 * deactivate the old plugin. Bulgarisation for WooCommerce has its own import (Migration).
 *
 * Taken over: courier logins that work with Nashenski Commerce, sender name and phone, the
 * waybill numbers of old orders (so tracking, status checks and courier requests work),
 * shipping zone methods (one Nashenski method per delivery type, the old one is turned off),
 * the mreja.net free shipping totals, and from the N-18 plugin the company details and the
 * document numbers.
 */
class Import_Sources {
	const ACTION         = 'nashenski_import_source';
	const DONE_OPTION    = 'nashenski_imported_sources';
	const DISMISS        = 'nashenski_import_dismiss';
	const DISMISS_OPTION = 'nashenski_import_notice_dismissed';

	/**
	 * Autoloaded, so document paths read it for free. "first": the N-18 plugin left documents a new number
	 * could clash with, or is active, and its import is not done; "again": it was active again after its import, which must run again;
	 * "done": imported; "none": nothing to fear from it. Set by n18_refresh() and by the import.
	 */
	const N18_STATE = 'nashenski_n18_state';

	/** The N-18 plugin's document types by the sequence of ours that continues them (its document_number()). */
	const N18_SEQUENCES = array(
		'purchase_order'        => 'next_invoice_number',
		'purchase_order_cancel' => 'next_invoice_number',
		'invoice'               => 'next_invoice_separate_number',
		'credit_note'           => 'next_invoice_separate_number',
		'debit_note'            => 'next_invoice_separate_number',
	);

	/** Order data of the old plugin kept on each order, shown in "From earlier plugins". */
	const LEGACY_META = '_nashenski_legacy';

	/** What each plugin leaves on orders: meta key prefixes and its own tables (with an order_id column). */
	const KEEP = array(
		'mreja_econt'     => array(
			'meta'   => array( 'Econt_' ),
			'tables' => array( 'econt_loading', 'econt_order' ),
		),
		'mreja_boxnow'    => array( 'meta' => array( '_boxnow_', 'boxnow_' ) ),
		'mreja_n18'       => array( 'tables' => array( 'notasupto_documents', 'notasupto_document_products' ) ),
		'drusoft_econt'   => array( 'meta' => array( '_drushfe_' ) ),
		'drusoft_speedy'  => array( 'meta' => array( '_drushfo_' ) ),
		'econt_delivery'  => array( 'meta' => array( '_order_waybill_id', '_customer_info_id', '_sync_error', 'price_cod' ) ),
		'speedy_official' => array( 'meta' => array( 'shipping_speedy_', '_speedy_' ) ),
	);

	/** Plugin name in its header => source. */
	const PLUGINS = array(
		'Econt Express WooCommerce shipping method'      => 'mreja_econt',
		'BOX NOW Delivery'                               => 'mreja_boxnow',
		'WooCommerce Наредба Н-18 облекчен режим плъгин' => 'mreja_n18',
		'Change Speedy and Econt Shipping Methods v2'    => 'mreja_buttons',
		'MrejaNet Checkout'                              => 'mreja_buttons', // The same plugin from 0.1.7 on.
		'Drusoft Shipping for Econt'                     => 'drusoft_econt',
		'Drusoft Shipping for Speedy'                    => 'drusoft_speedy',
		'Econt Delivery'                                 => 'econt_delivery',
		'Econt Delivery OneCheckout'                     => 'econt_delivery',
		'Speedy Shipping WooCommerce Plugin'             => 'speedy_official',
		'WooCommerce Speedy Offices'                     => 'speedy_offices',
	);

	/** Settings a source leaves behind, so its data is found after the plugin was deleted. */
	const DATA = array(
		'mreja_econt'     => 'woocommerce_econt_shipping_method_settings',
		'mreja_boxnow'    => 'boxnowoptions',
		'mreja_n18'       => 'notasuptooptions',
		'mreja_speedy'    => 'woocommerce_speedy_shipping_method_settings',
		'mreja_pigeon'    => 'woocommerce_pigeon_express_shipping_settings',
		'econt_delivery'  => 'delivery-with-econt',
		'speedy_official' => 'woocommerce_speedy_shipping_settings',
	);

	/**
	 * Construct.
	 */
	public function __construct() {
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_' . self::DISMISS, array( __CLASS__, 'dismiss' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_legacy_box' ), 10, 2 );
	}

	/**
	 * Names.
	 */
	private static function names() {
		return array(
			'mreja_econt'     => __( 'Econt Express shipping method (mreja.net)', 'nashenski-commerce' ),
			'mreja_boxnow'    => __( 'BOX NOW Delivery (mreja.net)', 'nashenski-commerce' ),
			'mreja_n18'       => __( 'Ordinance N-18 light regime (mreja.net)', 'nashenski-commerce' ),
			'mreja_speedy'    => __( 'Speedy shipping method (mreja.net)', 'nashenski-commerce' ),
			'mreja_pigeon'    => __( 'Pigeon Express shipping (mreja.net)', 'nashenski-commerce' ),
			'mreja_buttons'   => __( 'Change Speedy and Econt Shipping Methods (mreja.net)', 'nashenski-commerce' ),
			'drusoft_econt'   => __( 'Drusoft Shipping for Econt', 'nashenski-commerce' ),
			'drusoft_speedy'  => __( 'Drusoft Shipping for Speedy', 'nashenski-commerce' ),
			'econt_delivery'  => __( 'Econt Delivery (Econt)', 'nashenski-commerce' ),
			'speedy_official' => __( 'Speedy Shipping (Speedy)', 'nashenski-commerce' ),
			'speedy_offices'  => __( 'WooCommerce Speedy Offices', 'nashenski-commerce' ),
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

		// Its documents alone can be imported, also with its files and settings deleted.
		if ( ! isset( $found['mreja_n18'] ) && self::n18_has_data() ) {
			$found['mreja_n18'] = array();
		}

		$done    = (array) get_option( self::DONE_OPTION, array() );
		$names   = self::names();
		$sources = array();

		foreach ( $found as $slug => $plugins ) {
			$sources[ $slug ] = array(
				'name'    => $names[ $slug ],
				'plugins' => $plugins,
				'done'    => $done[ $slug ] ?? null,
			);
		}

		return $sources;
	}

	/**
	 * The notice on the dashboard and Plugins screens.
	 */
	public static function notice() {
		// Only where you look after plugins and orders; the settings pages and Help show it themselves.
		$screen = get_current_screen();

		if ( $screen && in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			self::render();
		} elseif ( $screen && in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order', 'shop_order' ), true ) ) {
			// The orders list and the order screen: where documents would be made.
			self::n18_refresh();
			self::n18_notice();
		}
	}

	/**
	 * The error while documents are paused for the N-18 plugin (n18_paused()), with a link to the Plugins screen.
	 */
	public static function n18_notice() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! self::n18_paused() ) {
			return;
		}

		if ( ! self::n18_active() ) {
			$action = '<a href="' . esc_url( Settings::url( 'help' ) . '#import' ) . '">' . esc_html__( 'Go to the import', 'nashenski-commerce' ) . '</a>';
		} elseif ( current_user_can( 'activate_plugins' ) ) {
			$action = '<a href="' . esc_url( self::n18_plugins_url() ) . '">' . esc_html__( 'Go to Plugins', 'nashenski-commerce' ) . '</a>';
		} else {
			$action = esc_html__( 'Ask an administrator to deactivate it.', 'nashenski-commerce' );
		}

		printf( '<div class="notice notice-error"><p>%s %s</p></div>', esc_html( self::paused_reason() ), $action ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $action is built from escaped parts above.
	}

	/**
	 * The notice: the report of the last import, or the sources still to import.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		self::n18_refresh();

		$sources  = self::sources();
		$imported = sanitize_key( (string) nashenski_query_arg( 'nashenski_source_imported' ) );

		if ( $imported && ! empty( $sources[ $imported ]['done']['report'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html(
				sprintf(
				/* translators: %s: plugin name */
					__( 'Imported from %s:', 'nashenski-commerce' ),
					$sources[ $imported ]['name']
				)
			) . '</strong><br>' . wp_kses_post( implode( '<br>', array_map( 'esc_html', $sources[ $imported ]['done']['report'] ) ) ) . '</p></div>';
		}

		// Until the owner deactivates them, the imported plugins would run beside this one. The N-18 plugin is
		// never imported while active; active again after its import, documents pause and n18_notice() says so.
		$old_plugins = get_option( Migration::DONE_OPTION ) ? Migration::plugin_files() : array();
		foreach ( $sources as $slug => $source ) {
			if ( $source['done'] && 'mreja_n18' !== $slug ) {
				$old_plugins += $source['plugins'];
			}
		}
		self::n18_notice();
		self::ask_to_deactivate( $old_plugins );

		$open = self::open_names();

		// Dismissed: back only when another plugin turns up.
		if ( ! array_diff( array_keys( $open ), (array) get_option( self::DISMISS_OPTION, array() ) ) ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p>
				<?php
				echo esc_html(
					sprintf(
					/* translators: %s: plugin names */
						__( 'Nashenski Commerce can take over the data of %s.', 'nashenski-commerce' ),
						implode( ', ', $open )
					)
				);
				if ( isset( $open['mreja_n18'] ) && self::n18_active() ) {
					echo ' ' . wp_kses_post( self::n18_first_html() );
				}
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
	public static function open_names() {
		$names = array();

		if ( Migration::has_old_data() && ! get_option( Migration::DONE_OPTION ) ) {
			$names['bulgarisation'] = __( 'Bulgarisation for WooCommerce', 'nashenski-commerce' );
		}

		foreach ( self::sources() as $slug => $source ) {
			// The N-18 plugin was active again after its import: a new run takes over its new numbers.
			if ( ! $source['done'] || ( 'mreja_n18' === $slug && 'again' === self::n18_state() ) ) {
				$names[ $slug ] = $source['name'];
			}
		}

		return $names;
	}

	/**
	 * Dismiss.
	 */
	public static function dismiss() {
		if ( current_user_can( 'manage_woocommerce' ) && check_admin_referer( self::DISMISS ) ) {
			update_option( self::DISMISS_OPTION, array_keys( self::open_names() ), false );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Url.
	 *
	 * @param mixed $slug Slug.
	 */
	public static function url( $slug ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION . '&source=' . $slug ), self::ACTION );
	}

	/**
	 * Handle.
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION );
		$slug = sanitize_key( (string) nashenski_query_arg( 'source' ) );

		// While the N-18 plugin is active, both would give out the same numbers after the import.
		if ( 'mreja_n18' === $slug && self::n18_active() ) {
			wp_die(
				wp_kses_post( self::n18_first_html() ),
				'',
				array(
					'response'  => 409,
					'back_link' => true,
				)
			);
		}

		if ( isset( self::sources()[ $slug ] ) ) {
			self::import( $slug );
		}

		$back = wp_get_referer() ? wp_get_referer() : Settings::url();
		wp_safe_redirect( add_query_arg( 'nashenski_source_imported', $slug, $back ) );
		exit;
	}

	/* ---------- import ---------- */

	/**
	 * Import one source. Returns the lines of the report.
	 *
	 * @param mixed $slug Slug.
	 */
	public static function import( $slug ) {
		if ( 'mreja_n18' === $slug && self::n18_active() ) {
			return array( __( 'Deactivate the N-18 plugin first, then import.', 'nashenski-commerce' ) );
		}

		$count  = array(
			'settings'  => 0,
			'waybills'  => 0,
			'zones'     => 0,
			'documents' => 0,
			'skipped'   => 0,
			'kept'      => 0,
		);
		$notes  = array();
		$source = self::sources()[ $slug ] ?? array( 'plugins' => array() );

		switch ( $slug ) {
			case 'mreja_econt':
				$s = (array) get_option( 'woocommerce_econt_shipping_method_settings', array() );
				self::login(
					'econt',
					array(
						'user'     => $s['username'] ?? '',
						'password' => $s['password'] ?? '',
						'env'      => '1' === (string) ( $s['live'] ?? '' ) ? 'live' : 'demo',
					),
					$count
				);
				self::fill( 'econt', 'name', ! empty( $s['name'] ) ? $s['name'] : ( $s['company'] ?? '' ), $count );
				self::fill( 'econt', 'phone', $s['phone'] ?? '', $count );

				foreach ( self::table_rows( 'econt_loading' ) as $row ) {
					self::waybill( $row['order_id'], 'econt', array( $row['loading_num'] ), $row['pdf_url'], array( 'econt_shipping_method' ), $count );
				}

				// From 1.9 the plugin can sit in shipping zones; before, it is one method for every zone.
				$zoned = ! empty( $s['shipping_zones_support'] );

				if ( $zoned || 'yes' === ( $s['enabled'] ?? '' ) ) {
					// Free shipping per delivery type from 1.9 (econt_free_shipping_v2); before, one sum for all types.
					$v2    = get_option( 'econt_free_shipping_v2' );
					$free  = self::free_over(
						array(
							'office'  => $v2 ? get_option( 'econt_free_shipping_sum_office' ) : '',
							'automat' => $v2 ? get_option( 'econt_free_shipping_sum_aps' ) : '',
							'address' => $v2 ? get_option( 'econt_free_shipping_sum_address' ) : '',
						),
						$v2 ? '' : ( get_option( 'econt_free_shipping_sum' ) ? get_option( 'econt_free_shipping_sum' ) : ( $s['free_shipping_sum'] ?? '' ) ),
						get_option( 'econt_free_shipping_sum_to_office' ),
						get_option( 'econt_shipping_payments' ),
						array(
							'office'  => 'receiver_amount_office',
							'automat' => 'receiver_amount_aps',
							'address' => 'receiver_amount',
						)
					);
					$types = array_keys(
						array_filter(
							array(
								'office'  => $s['send_to_office'] ?? '1',
								'automat' => $s['send_to_machine'] ?? '1',
								'address' => $s['send_to_door'] ?? '1',
							),
							function ( $on ) {
								return '0' !== (string) $on;
							}
						)
					);
					// Without zones Nashenski adds its methods to the zone of Bulgaria.
					self::zones( $zoned ? 'econt_shipping_method' : null, 'econt', $types, $count, $free );
				}
				break;

			case 'mreja_boxnow':
				$o = (array) get_option( 'boxnowoptions', array() );
				self::login(
					'boxnow',
					array(
						'client_id'     => $o['client_id'] ?? '',
						'client_secret' => $o['client_secret'] ?? '',
						'env'           => false !== strpos( (string) ( $o['api_url'] ?? '' ), 'stage' ) ? 'demo' : 'live',
					),
					$count
				);

				if ( ! empty( $o['warehouses'][0]['id'] ) ) {
					self::fill( 'boxnow_send_from', 'location', 'originID-' . $o['warehouses'][0]['id'], $count );
				}

				foreach ( self::orders_with( '_boxnow_parcel_ids' ) as $order ) {
					self::waybill( $order, 'boxnow', (array) $order->get_meta( '_boxnow_parcel_ids' ), '', array( 'boxnow_delivery' ), $count );
				}

				// A price row where the customer pays nothing from an order total up is free shipping over that total.
				$free = array_filter(
					array_map(
						function ( $row ) {
							return isset( $row['receiver_amount'] ) && '' !== $row['receiver_amount'] && ! (float) $row['receiver_amount'] ? max( 0.01, (float) ( $row['order_min_price'] ?? 0 ) ) : 0;
						},
						self::free_rows( $o['shipping_payments'] ?? array() )
					)
				);
				self::fill( 'boxnow_price', 'free_shipping_over', $free ? (string) min( $free ) : '', $count );

				self::zones( 'boxnow_delivery', 'boxnow', array( '' ), $count );
				break;

			case 'mreja_n18':
				// A rerun notes the clashes among the rows added since the last import. A first import notes them all
				// when documents were paused for it ("first": the plugin may have reused our numbers), else none (-1).
				$before = ( (array) get_option( self::DONE_OPTION, array() ) )['mreja_n18'] ?? null;
				$after  = null === $before ? ( 'first' === self::n18_state() ? 0 : -1 ) : (int) ( $before['last_id'] ?? 0 );
				self::n18( (array) get_option( 'notasuptooptions', array() ), $count, $notes, $after );
				break;

			case 'mreja_speedy':
				$s = (array) get_option( 'woocommerce_speedy_shipping_method_settings', array() );
				self::login(
					'speedy',
					array(
						'user'     => $s['api_username'] ?? '',
						'password' => $s['api_password'] ?? '',
					),
					$count
				);
				self::fill( 'speedy', 'name', $s['contact_name'] ?? '', $count );
				self::fill( 'speedy', 'phone', $s['phone1'] ?? '', $count );
				self::fill( 'speedy_send_from', 'office', ! empty( $s['dropoff_office_id'] ) ? 'officeID-' . $s['dropoff_office_id'] : '', $count );
				self::fill( 'speedy', 'send_from', strtolower( $s['send_from'] ?? '' ), $count );
				$o = (array) get_option( 'speedy_shipping_method_options', array() );
				self::zones(
					'speedy_shipping_method',
					'speedy',
					array( 'office', 'automat', 'address' ),
					$count,
					self::free_over(
						array(
							'office'  => get_option( 'speedy_free_shipping_sum_office' ),
							'automat' => get_option( 'speedy_free_shipping_sum_apt' ),
							'address' => get_option( 'speedy_free_shipping_sum_address' ),
						),
						get_option( 'speedy_free_shipping_sum' ),
						get_option( 'speedy_free_shipping_sum_to_office' ),
						$o['speedy_shipping_payments'] ?? ( $o['shipping_payments'] ?? array() ),
						array(
							'office'  => 'receiver_amount_office',
							'automat' => 'receiver_amount_apt',
							'address' => 'receiver_amount',
						)
					)
				);
				break;

			case 'mreja_pigeon':
				// Nashenski's Pigeon sender is an office or an address and has no name or phone field, so the contact details stay on the old plugin.
				$s = (array) get_option( 'woocommerce_pigeon_express_shipping_settings', array() );
				$o = (array) get_option( 'pigeon_express_options', array() );
				self::login(
					'pigeon',
					array(
						'api_key'    => $s['api_key'] ?? '',
						'api_secret' => $s['api_secret'] ?? '',
						'env'        => in_array( (string) ( $s['live'] ?? '' ), array( '1', 'yes' ), true ) ? 'live' : 'sandbox',
					),
					$count
				);
				self::fill( 'pigeon_send_from', 'office', ! empty( $o['pickup_office_id'] ) ? 'officeID-' . $o['pickup_office_id'] : '', $count );
				self::fill( 'pigeon', 'send_from', strtolower( $s['send_from'] ?? '' ), $count );
				self::zones(
					'pigeon_express_shipping',
					'pigeon',
					array( 'office', 'locker', 'address' ),
					$count,
					self::free_over(
						array(
							'office'  => $o['pigeon_free_shipping_sum_office'] ?? '',
							'locker'  => $o['pigeon_free_shipping_sum_locker'] ?? '',
							'address' => $o['pigeon_free_shipping_sum_address'] ?? '',
						),
						$o['pigeon_free_shipping_sum'] ?? '',
						$o['pigeon_free_shipping_sum_to_office'] ?? '',
						$o['pigeon_shipping_payments'] ?? array(),
						array(
							'office'  => 'receiver_amount_office',
							'locker'  => 'receiver_amount_locker',
							'address' => 'receiver_amount',
						)
					)
				);
				break;

			case 'drusoft_econt':
				foreach ( self::orders_with( '_drushfe_waybill_id' ) as $order ) {
					$response = (array) $order->get_meta( '_drushfe_waybill_response' );
					self::waybill( $order, 'econt', array( ! empty( $response['shipmentNumber'] ) ? $response['shipmentNumber'] : $order->get_meta( '_drushfe_waybill_id' ) ), $response['pdfURL'] ?? '', array( 'drushfe_econt' ), $count );
				}

				self::zones( 'drushfe_econt', 'econt', array( 'office', 'automat', 'address' ), $count );
				$notes[] = __( 'The Econt key of that plugin works only with Econt\'s "Deliver with Econt" service. Enter your Econt username and password in Couriers > Econt.', 'nashenski-commerce' );
				break;

			case 'drusoft_speedy':
				$s = self::instance_settings( 'drushfo_speedy' );
				self::login(
					'speedy',
					array(
						'user'     => $s['speedy_username'] ?? '',
						'password' => $s['speedy_password'] ?? '',
					),
					$count
				);
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
				self::login(
					'speedy',
					array(
						'user'     => $s['speedy_name'] ?? '',
						'password' => $s['speedy_password'] ?? '',
					),
					$count
				);

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

		$report = array_merge( $report, $notes );

		$done          = (array) get_option( self::DONE_OPTION, array() );
		$done[ $slug ] = array(
			'time'   => time(),
			'report' => $report,
		);
		if ( 'mreja_n18' === $slug ) {
			$done[ $slug ]['last_id'] = self::n18_last_id(); // n18_refresh() compares it: a larger table means the plugin ran again.
		}
		update_option( self::DONE_OPTION, $done, false );
		if ( 'mreja_n18' === $slug ) {
			self::n18_set_state( 'done' );
		}
		wp_cache_flush();

		return $report;
	}

	/**
	 * After an import, asks the owner to deactivate the old plugins that are still active, with a
	 * link to the Plugins screen. A plugin must not change the state of other plugins.
	 *
	 * @param array $plugins Plugin file => name.
	 */
	public static function ask_to_deactivate( $plugins ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$names   = array();
		$network = false;

		foreach ( $plugins as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$names[] = $name;
				// A network-activated plugin is deactivated on the network Plugins screen.
				$network = $network || ( is_multisite() && is_plugin_active_for_network( $file ) );
			}
		}

		if ( ! $names || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %s: plugin names */
					__( 'The import is done. Please deactivate %s: Nashenski Commerce does its work now.', 'nashenski-commerce' ),
					implode( ', ', $names )
				)
			),
			esc_url( $network ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' ) ),
			esc_html__( 'Go to Plugins', 'nashenski-commerce' )
		);
	}

	/**
	 * Company details and document numbers of the N-18 plugin.
	 *
	 * @param mixed $o     O.
	 * @param mixed $count Count.
	 * @param array $notes Lines added to the report.
	 * @param int   $after Rows after this id are new since the last import and get notes on a clash; -1 for none.
	 */
	private static function n18( $o, &$count, &$notes = array(), $after = -1 ) {
		$map = array(
			'nap'     => array(
				'company_name' => 'seller_company_name',
				'eik'          => 'seller_company_id',
				'dds_number'   => 'seller_company_vat_num',
				'mol'          => 'seller_company_mol',
				'address'      => 'seller_company_address',
				'city'         => 'seller_company_city',
				'email'        => 'seller_company_email',
				'phone_number' => 'seller_company_phone',
				'nap_number'   => 'shop_nap_id',
				'domain'       => 'shop_domain_name',
			),
			'invoice' => array(
				'prepared_by'      => 'invoice_doccreator',
				'footer_text'      => 'document_footer',
				'accountant_email' => 'notasupto_xml_send_emails',
			),
		);

		foreach ( $map as $group => $fields ) {
			foreach ( $fields as $ours => $theirs ) {
				self::fill( $group, $ours, is_string( $o[ $theirs ] ?? null ) ? $o[ $theirs ] : '', $count );
			}
		}

		// Order documents keep their numbers; the next numbers continue after the highest one.
		self::n18_numbers( self::table_rows( 'notasupto_documents' ), $count, $notes, $after );

		$count['settings'] += (int) \Nashenski\Invoice\Number_Allocator::continue_after(
			function () {
				return self::n18_highest();
			}
		);
	}

	/**
	 * Order documents and invoices of the N-18 plugin keep their numbers on their orders. An order
	 * that already has a number keeps it (import_number()), so running it twice is harmless.
	 *
	 * On a rerun, the plugin was active again and may have reused numbers Nashenski Commerce gave out
	 * meanwhile: each order whose number clashes gets a note, and the report lists them to correct.
	 *
	 * @param array $rows  Rows of its documents table.
	 * @param array $count Counters of the report.
	 * @param array $notes Lines added to the report.
	 * @param int   $after Rows after this id get a note on a clash (new since the last import); -1 for none.
	 */
	private static function n18_numbers( $rows, &$count, &$notes = array(), $after = -1 ) {
		$keys    = array(
			'purchase_order' => 'nashenski_order_number',
			'invoice'        => 'nashenski_order_invoice_number',
		);
		$clashes = array();

		foreach ( $rows as $row ) {
			$order = isset( $keys[ $row['type'] ] ) ? wc_get_order( $row['order_id'] ) : null;

			$option = 'purchase_order' === $row['type'] ? 'next_invoice_number' : 'next_invoice_separate_number';
			if ( ! $order ) {
				continue;
			}

			try {
				if ( \Nashenski\Invoice\Number_Allocator::import_number( $order, $keys[ $row['type'] ], $row['num'], $option ) ) {
					++$count['documents'];
					continue;
				}
				// The order already has a number: a clash only when it is another one.
				$clash = (int) $order->get_meta( $keys[ $row['type'] ] ) !== (int) $row['num'];
			} catch ( \RuntimeException $e ) {
				// A duplicate, empty or locked number: that order stays as it is and is reported.
				++$count['skipped'];
				$clash = true;
			}

			if ( $clash && $after >= 0 && (int) $row['id'] > $after ) {
				$order->add_order_note(
					sprintf(
						/* translators: %s: document number */
						__( 'The old N-18 plugin gave this order document number %s, which clashes with a number Nashenski Commerce already gave out. Correct the documents of this order.', 'nashenski-commerce' ),
						$row['num']
					)
				);
				$clashes[] = '#' . $order->get_order_number();
			}
		}

		if ( $clashes ) {
			/* translators: %s: order numbers */
			$notes[] = sprintf( __( 'Orders whose N-18 number clashes; correct their documents: %s', 'nashenski-commerce' ), implode( ', ', array_unique( $clashes ) ) );
		}
	}

	/**
	 * The N-18 plugin's documents table, or '' once it is gone.
	 *
	 * @return string
	 */
	private static function n18_table() {
		global $wpdb;

		$table = $wpdb->prefix . 'notasupto_documents';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- another plugin's own table; it can be dropped at any time.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ? $table : '';
	}

	/**
	 * The highest numbers the N-18 plugin gave out, by the sequence setting that continues them: one
	 * grouped query, so its documents are never loaded.
	 *
	 * @return array Sequence setting => highest number.
	 */
	private static function n18_highest() {
		global $wpdb;

		$table   = self::n18_table();
		$shared  = ! nashenski_get_option( 'invoice', 'next_invoice_separate_number' );
		$highest = array( 'next_invoice_number' => 0 );

		if ( ! $shared ) {
			$highest['next_invoice_separate_number'] = 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- another plugin's own table, read under the numbering lock.
		$rows = $table ? (array) $wpdb->get_results( $wpdb->prepare( 'SELECT type, MAX(CAST(num AS UNSIGNED)) AS num FROM %i GROUP BY type', $table ), ARRAY_A ) : array();

		foreach ( $rows as $row ) {
			if ( isset( self::N18_SEQUENCES[ $row['type'] ] ) ) {
				// An empty separate counter means shared numbering: setting it would switch the shop to separate
				// numbering, so the shared counter has to move past the invoice numbers instead.
				$option             = $shared ? 'next_invoice_number' : self::N18_SEQUENCES[ $row['type'] ];
				$highest[ $option ] = max( $highest[ $option ], (int) $row['num'] );
			}
		}

		return $highest;
	}

	/**
	 * Whether the mreja.net N-18 plugin is active. Its import is refused then: both plugins would give
	 * out the same document numbers.
	 *
	 * @return bool
	 */
	public static function n18_active() {
		return defined( 'NOTASUPTO_VERSION_NUM' );
	}

	/**
	 * Whether Nashenski Commerce makes no documents and takes no numbers because of the mreja.net N-18
	 * plugin: before its import ("first"), or after it was active again until its import runs again
	 * ("again"). Both would give out the same document numbers. Reads the autoloaded state only; while
	 * the plugin is active it also notices that by itself.
	 *
	 * @return bool
	 */
	public static function n18_paused() {
		// Right after an update the state is not stored yet: work it out once, on any request.
		if ( false === get_option( self::N18_STATE, false ) ) {
			self::n18_refresh();
		}

		$state = self::n18_state();

		if ( self::n18_active() && 'first' !== $state && 'again' !== $state ) {
			$done  = (array) get_option( self::DONE_OPTION, array() );
			$state = empty( $done['mreja_n18'] ) ? 'first' : 'again';
			self::n18_set_state( $state );
		}

		return 'first' === $state || 'again' === $state;
	}

	/**
	 * The N-18 state (N18_STATE).
	 *
	 * @return string
	 */
	public static function n18_state() {
		return (string) get_option( self::N18_STATE, '' );
	}

	/**
	 * Saves the N-18 state. With "again", the import notice comes back even when it was dismissed.
	 *
	 * @param string $state State.
	 */
	private static function n18_set_state( $state ) {
		if ( self::n18_state() !== $state ) {
			update_option( self::N18_STATE, $state, true );
		}

		$dismissed = (array) get_option( self::DISMISS_OPTION, array() );
		if ( 'again' === $state && in_array( 'mreja_n18', $dismissed, true ) ) {
			update_option( self::DISMISS_OPTION, array_values( array_diff( $dismissed, array( 'mreja_n18' ) ) ), false );
		}
	}

	/**
	 * Works out the N-18 state, so document paths only read the flag: on the screens with its notices,
	 * the settings pages, the setup and the first request after an update. A few cheap
	 * reads: whether its documents table has rows, and its last row id against the one the import kept.
	 */
	public static function n18_refresh() {
		$all    = (array) get_option( self::DONE_OPTION, array() );
		$done   = $all['mreja_n18'] ?? array();
		$active = self::n18_active();
		$data   = $active || self::n18_has_data(); // An active plugin makes its documents.

		// An import before 0.21.7 kept no last row: from now on, documents it makes are noticed.
		if ( $done && ! isset( $done['last_id'] ) ) {
			$done['last_id']  = self::n18_last_id();
			$all['mreja_n18'] = $done;
			update_option( self::DONE_OPTION, $all, false );
		}

		if ( ! $done ) {
			// Not imported: a pause only while a number could clash, so the import offer is enough otherwise. Once
			// paused (it was active, and may have reused our numbers) it stays paused until the import, while its
			// documents are there to import.
			$state = $active || ( $data && ( 'first' === self::n18_state() || self::n18_could_clash() ) ) ? 'first' : 'none';
		} elseif ( ! $data ) {
			$state = 'done'; // Its documents are gone, so a new import is impossible: no pause for ever.
		} elseif ( $active || 'again' === self::n18_state() ) {
			$state = 'again';
		} elseif ( self::n18_last_id() > (int) $done['last_id'] ) {
			$state = 'again'; // It made documents after the import, also when it was on and off without an admin page.
		} else {
			$state = 'done';
		}

		self::n18_set_state( $state );
	}

	/**
	 * Whether one of our counters has not passed the highest number the N-18 plugin gave out in its
	 * sequence (shared or separate): only then could a new number clash with one of its documents.
	 *
	 * @return bool
	 */
	private static function n18_could_clash() {
		foreach ( self::n18_highest() as $option => $highest ) {
			if ( $highest && (int) nashenski_get_option( 'invoice', $option ) <= $highest ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the N-18 plugin left documents to import: rows in its documents table. Its settings alone
	 * hold no numbers. Read on admin pages and once after an update.
	 *
	 * @return bool
	 */
	private static function n18_has_data() {
		global $wpdb;

		$table = self::n18_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- another plugin's own table, read on admin pages only.
		return $table && (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i LIMIT 1', $table ) );
	}

	/**
	 * The last row id of the N-18 plugin's documents table, 0 without it.
	 *
	 * @return int
	 */
	private static function n18_last_id() {
		global $wpdb;

		$table = self::n18_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one primary key lookup on another plugin's table.
		return $table ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i', $table ) ) : 0;
	}

	/**
	 * Why documents are paused, for notices, notes and the order screen.
	 *
	 * @return string
	 */
	public static function paused_reason() {
		if ( 'first' === self::n18_state() ) {
			return self::n18_active()
				? __( 'Nashenski Commerce makes no invoices or other documents yet: the old N-18 plugin has its own document numbers. Deactivate it and run its import from the Help page first; numbering then continues after its numbers. Then use "Regenerate PDF\'s" for orders without documents.', 'nashenski-commerce' )
				: __( 'Nashenski Commerce makes no invoices or other documents yet: the old N-18 plugin left its document numbers on this site. Run its import from the Help page first; numbering then continues after its numbers. Then use "Regenerate PDF\'s" for orders without documents.', 'nashenski-commerce' );
		}

		return self::n18_active()
			? __( 'The old N-18 plugin is active again and can reuse numbers Nashenski Commerce already gave out, so invoices and other documents are paused. Deactivate it at once, run its import again from the Help page and correct the orders it lists. Then use "Regenerate PDF\'s" for orders without documents.', 'nashenski-commerce' )
			: __( 'Invoices and other documents are paused until the import of the old N-18 plugin is run again from the Help page, because it was active again after its import. Then correct the orders the import lists and use "Regenerate PDF\'s" for orders without documents.', 'nashenski-commerce' );
	}

	/**
	 * Why the N-18 import waits, with a link to the Plugins screen (the network one when the plugin is
	 * network-active) for those who can deactivate it.
	 *
	 * @return string HTML.
	 */
	public static function n18_first_html() {
		$text = esc_html__( 'Deactivate the N-18 plugin first, then import.', 'nashenski-commerce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return $text . ' ' . esc_html__( 'Ask an administrator to deactivate it.', 'nashenski-commerce' );
		}

		return $text . ' <a href="' . esc_url( self::n18_plugins_url() ) . '">' . esc_html__( 'Go to Plugins', 'nashenski-commerce' ) . '</a>';
	}

	/**
	 * The Plugins screen where the N-18 plugin is deactivated: the network one when it is network-active.
	 *
	 * @return string
	 */
	private static function n18_plugins_url() {
		if ( is_multisite() ) {
			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			foreach ( array_keys( self::sources()['mreja_n18']['plugins'] ?? array() ) as $file ) {
				if ( is_plugin_active_for_network( $file ) ) {
					return network_admin_url( 'plugins.php' );
				}
			}
		}

		return admin_url( 'plugins.php' );
	}

	/* ---------- everything the old plugin saved on orders ---------- */

	/**
	 * Copy what the plugin saved on each order (its meta and its own table rows, with tracking
	 * events and document PDF files) to the order, so it stays visible once the plugin is off.
	 * Returns the number of orders.
	 *
	 * @param mixed $slug Slug.
	 */
	public static function keep( $slug ) {
		global $wpdb;

		$spec       = self::KEEP[ $slug ] ?? array();
		$data       = array();
		$hpos       = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
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

			$legacy          = (array) $order->get_meta( self::LEGACY_META );
			$legacy[ $slug ] = $values;
			$order->update_meta_data( self::LEGACY_META, $legacy );
			$order->save();
			++$count;
		}

		return $count;
	}

	/**
	 * The PDF files the N-18 plugin saved for a document (uploads/notasupto/Y/m/type-number*.pdf).
	 *
	 * @param mixed $row Row.
	 */
	private static function n18_files( $row ) {
		$upload = wp_upload_dir();
		// "type-number-hash.pdf" (Bulgarian) and "type-number_lang-hash.pdf", so invoice 12 never matches invoice 120.
		$base        = $upload['basedir'] . '/notasupto/*/*/' . str_replace( '/', '-', $row['type'] . '-' . $row['num'] );
		$dashed      = glob( $base . '-*.pdf' );
		$underscored = glob( $base . '_*.pdf' );
		$files       = array_merge( false === $dashed ? array() : $dashed, false === $underscored ? array() : $underscored );

		return array_map(
			function ( $file ) use ( $upload ) {
				return $upload['baseurl'] . str_replace( '\\', '/', substr( $file, strlen( $upload['basedir'] ) ) );
			},
			$files
		);
	}

	/**
	 * Add legacy box.
	 *
	 * @param mixed $type Type.
	 * @param mixed $source Order or post.
	 */
	public static function add_legacy_box( $type, $source = null ) {
		$order = $source instanceof \WC_Order ? $source : ( isset( $source->ID ) ? wc_get_order( $source->ID ) : null );

		if ( $order && $order->get_meta( self::LEGACY_META ) ) {
			add_meta_box( 'nashenski_legacy', __( 'From earlier plugins', 'nashenski-commerce' ), array( __CLASS__, 'legacy_box' ), $type, 'normal', 'low' );
		}
	}

	/**
	 * Legacy box.
	 *
	 * @param mixed $source Order or post.
	 */
	public static function legacy_box( $source ) {
		$order = $source instanceof \WC_Order ? $source : wc_get_order( $source->ID );
		$names = self::names();

		echo '<p class="description">' . esc_html__( 'What the earlier plugins saved on this order, kept when their data was imported.', 'nashenski-commerce' ) . '</p>';

		foreach ( (array) $order->get_meta( self::LEGACY_META ) as $slug => $values ) {
			echo '<h4>' . esc_html( $names[ $slug ] ?? $slug ) . '</h4>';
			echo wp_kses_post( self::table( $values ) );
		}
	}

	/**
	 * Nested key / value table; addresses become links.
	 *
	 * @param mixed $values Values.
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
	 *
	 * @param mixed $order Order.
	 */
	public static function customer_documents( $order ) {
		$legacy = (array) $order->get_meta( self::LEGACY_META );
		$types  = array(
			'purchase_order'        => __( 'Order document', 'nashenski-commerce' ),
			'invoice'               => __( 'Invoice', 'nashenski-commerce' ),
			'credit_note'           => __( 'Credit note', 'nashenski-commerce' ),
			'purchase_order_cancel' => __( 'Cancelled order document', 'nashenski-commerce' ),
		);
		$links  = array();

		foreach ( $legacy['mreja_n18']['notasupto_documents'] ?? array() as $document ) {
			foreach ( (array) ( $document['pdf'] ?? array() ) as $url ) {
				$label   = ( $types[ $document['type'] ] ?? $document['type'] ) . ' ' . $document['num'] . ( false !== strpos( basename( $url ), '_en' ) ? ' (EN)' : '' );
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
	 *
	 * @param mixed $group Group.
	 * @param mixed $name Name.
	 * @param mixed $value Value.
	 * @param mixed $count Count.
	 */
	private static function fill( $group, $name, $value, &$count ) {
		$value = trim( (string) $value );

		if ( '' !== $value && '' === trim( (string) nashenski_get_option( $group, $name ) ) ) {
			nashenski_set_option( $group, $name, $value );
			++$count['settings'];
		} elseif ( '' !== $value ) {
			++$count['kept'];
		}
	}

	/**
	 * A courier login, taken only when Nashenski has none yet; the environment goes with it.
	 *
	 * @param mixed $courier Courier.
	 * @param mixed $fields Fields.
	 * @param mixed $count Count.
	 */
	private static function login( $courier, $fields, &$count ) {
		$keys = array_diff( array_keys( $fields ), array( 'env' ) );

		foreach ( $keys as $key ) {
			if ( '' !== trim( (string) nashenski_get_option( $courier, $key ) ) ) {
				++$count['kept'];
				return;
			}

			if ( '' === trim( (string) $fields[ $key ] ) ) {
				return;
			}
		}

		foreach ( $fields as $key => $value ) {
			nashenski_set_option( $courier, $key, $value );
			++$count['settings'];
		}
	}

	/**
	 * Like maybe_unserialize(), but never builds objects from legacy rows.
	 *
	 * @param mixed $value Value.
	 */
	private static function safe_unserialize( $value ) {
		return is_serialized( $value ) ? @unserialize( trim( $value ), array( 'allowed_classes' => false ) ) : $value; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- legacy rows, classes are not allowed.
	}

	/**
	 * All rows of another plugin's own table (one-time import), or array() without the table.
	 *
	 * @param mixed $table Table.
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

	/**
	 * Orders with.
	 *
	 * @param mixed $meta_key Meta key.
	 */
	private static function orders_with( $meta_key ) {
		return wc_get_orders(
			array(
				'limit'    => -1,
				'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_compare' => 'EXISTS',
			)
		);
	}

	/**
	 * Settings of the first zone instance of a shipping method.
	 *
	 * @param mixed $method_id Method id.
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
	 *
	 * @param mixed $order Order.
	 * @param mixed $courier Courier.
	 * @param mixed $numbers Numbers.
	 * @param mixed $pdf Pdf.
	 * @param mixed $old_methods Old methods.
	 * @param mixed $count Count.
	 */
	private static function waybill( $order, $courier, $numbers, $pdf, $old_methods, &$count ) {
		$order   = is_object( $order ) ? $order : wc_get_order( $order );
		$numbers = array_values( array_filter( array_map( 'strval', (array) $numbers ), 'strlen' ) );

		if ( ! $order || ! $numbers ) {
			return;
		}

		$parcels = array_map(
			function ( $number ) {
				return array( 'id' => $number );
			},
			$numbers
		);
		$meta    = array(
			'econt'  => array(
				'nashenski_econt_label'           => array( 'label' => array( 'shipmentNumber' => $numbers[0] ) ),
				'nashenski_econt_shipment_status' => array(
					'label' => array(
						'shipmentNumber' => $numbers[0],
						'pdfURL'         => Courier_Pdf::url( $pdf ),
					),
				),
			),
			'speedy' => array(
				'nashenski_speedy_label'           => array( 'id' => $numbers[0] ),
				'nashenski_speedy_shipment_status' => array(
					'id'      => $numbers[0],
					'parcels' => $parcels,
				),
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
		++$count['waybills'];
	}

	/**
	 * The price rows of an old plugin that apply to every order in Bulgaria: no country other than
	 * Bulgaria, no shipping class, no weight limits, no top total, and shipping not added to the COD.
	 *
	 * @param mixed $rows Rows.
	 * @return array
	 */
	private static function free_rows( $rows ) {
		return array_filter(
			is_array( $rows ) ? $rows : array(),
			function ( $row ) {
				return is_array( $row )
					&& in_array( strtoupper( (string) ( $row['country_id'] ?? '' ) ), array( '', 'BG', 'BGR' ), true )
					&& in_array( (string) ( $row['type'] ?? '' ), array( '', 'shared' ), true )
					&& '' === (string) ( $row['class'] ?? '' )
					&& ! (float) ( $row['order_min_weight'] ?? 0 )
					&& ! (float) ( $row['order_max_weight'] ?? 0 )
					&& ! (float) ( $row['order_max_price'] ?? 0 );
			}
		);
	}

	/**
	 * Free shipping totals of a mreja.net courier as settings of the new methods, per delivery type.
	 * Its sum per type wins; with none, its one old sum counts for every type (not addresses when it
	 * was "to office only"). A price row where the customer pays nothing lowers the total of its type.
	 *
	 * @param array $sums Nashenski delivery type => the old plugin's sum for it.
	 * @param mixed $legacy The old single sum.
	 * @param mixed $office_only The old "to office only" flag.
	 * @param mixed $rows The old price rows: order_amount and the customer's price per type.
	 * @param array $row_keys Nashenski delivery type => the row key of the customer's price.
	 * @return array Type => array( 'free_shipping_over' => total ), or array() for no free shipping.
	 */
	private static function free_over( $sums, $legacy, $office_only, $rows, $row_keys ) {
		$positive = function ( $value ) {
			return max( 0.0, (float) $value );
		};
		$over     = array_map( $positive, $sums );

		if ( ! array_filter( $over ) && $positive( $legacy ) ) {
			$over = array_fill_keys( array_keys( $sums ), $positive( $legacy ) );

			if ( ! empty( $office_only ) ) {
				$over['address'] = 0.0;
			}
		}

		foreach ( self::free_rows( $rows ) as $row ) {
			// A free row from 0 is free for every order: 0.01 keeps that, as 0 means no free shipping.
			$amount = max( 0.01, $positive( $row['order_amount'] ?? 0 ) );

			foreach ( $row_keys as $type => $key ) {
				// A missing locker price is the office price, as in the old plugins.
				$price = $row[ $key ] ?? ( 'receiver_amount' === $key ? null : $row['receiver_amount_office'] ?? null );

				if ( null !== $price && '' !== $price && ! (float) $price && ( ! $over[ $type ] || $amount < $over[ $type ] ) ) {
					$over[ $type ] = $amount;
				}
			}
		}

		return array_map(
			function ( $total ) {
				return $total ? array( 'free_shipping_over' => (string) $total ) : array();
			},
			$over
		);
	}

	/**
	 * Add the Nashenski methods (one per delivery type) to every zone that has the old method,
	 * and turn the old one off. $old_method null: a method for all zones; the zone of Bulgaria gets them.
	 *
	 * @param mixed $old_method Old method.
	 * @param mixed $courier Courier.
	 * @param mixed $types Types.
	 * @param mixed $count Count.
	 * @param array $settings Type => more settings for its new method (free_over()).
	 */
	private static function zones( $old_method, $courier, $types, &$count, $settings = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'woocommerce_shipping_zone_methods';

		if ( null === $old_method ) {
			$rows = array(
				array(
					'zone_id'     => self::bulgaria_zone()->get_id(),
					'instance_id' => 0,
				),
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time import; no WordPress API lists zone methods by ID.
			$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT zone_id, instance_id FROM %i WHERE method_id = %s AND is_enabled = 1', $table, $old_method ), ARRAY_A );
		}

		foreach ( $rows as $row ) {
			$count['zones'] += count( self::add_methods( new \WC_Shipping_Zone( (int) $row['zone_id'] ), $courier, $types, $settings ) );

			if ( $row['instance_id'] ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the old method is turned off, not deleted.
				$wpdb->update( $table, array( 'is_enabled' => 0 ), array( 'instance_id' => (int) $row['instance_id'] ) );
			}
		}

		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	/**
	 * The shipping zone a customer in Bulgaria falls in (id 0 is "rest of the world").
	 *
	 * @return \WC_Shipping_Zone
	 */
	public static function bulgaria_zone() {
		return \WC_Shipping_Zones::get_zone_matching_package(
			array(
				'destination' => array(
					'country'  => 'BG',
					'state'    => '',
					'postcode' => '',
					'city'     => '',
					'address'  => '',
				),
			)
		);
	}

	/**
	 * Add one Nashenski method per delivery type the zone does not have yet. Returns the types added.
	 *
	 * @param \WC_Shipping_Zone $zone     Zone.
	 * @param string            $courier  Courier slug.
	 * @param array             $types    Delivery types ('' for BOX NOW).
	 * @param array             $settings Type => more settings for its new method; a title here replaces the default one.
	 * @return array
	 */
	public static function add_methods( $zone, $courier, $types, $settings = array() ) {
		$titles = array(
			'office'  => __( 'To an office', 'nashenski-commerce' ),
			'automat' => __( 'To a parcel locker', 'nashenski-commerce' ),
			'locker'  => __( 'To a parcel locker', 'nashenski-commerce' ),
			'address' => __( 'To an address', 'nashenski-commerce' ),
			''        => 'BOX NOW',
		);
		$have   = array();
		$added  = array();
		$class  = 'Nashenski\\Shipping\\' . \Nashenski\Shipping\Register::COURIERS[ $courier ][0] . '\\Method';

		// WooCommerce adds only registered methods, and a courier turned on just now is registered from the next request.
		nashenski_set_option( 'apis', 'enable_' . $courier, 'yes' );
		add_filter(
			'woocommerce_shipping_methods',
			function ( $methods ) use ( $courier, $class ) {
				return $methods + array( 'nashenski_' . $courier => $class );
			}
		);

		foreach ( $zone->get_shipping_methods() as $method ) {
			if ( 'nashenski_' . $courier === $method->id ) {
				$have[] = (string) $method->get_option( 'delivery_type' );
			}
		}

		foreach ( array_diff( $types, $have ) as $type ) {
			$instance = $zone->add_shipping_method( 'nashenski_' . $courier );

			if ( ! $instance ) {
				continue;
			}

			update_option(
				"woocommerce_nashenski_{$courier}_{$instance}_settings",
				array_filter(
					array_merge(
						array(
							'title'         => $titles[ $type ],
							'delivery_type' => $type,
						),
						$settings[ $type ] ?? array()
					),
					'strlen'
				)
			);
			$added[] = $type;
		}

		return $added;
	}
}
