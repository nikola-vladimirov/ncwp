<?php
/**
 * Plugin.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin.
 */
class Plugin {
	const VERSION = '0.21.10';

	/** Cron hook: the one-off 0.21.8 job that turns "Remove delivery from documents" into "Is ... courier?". */
	const REMOVE_SHIPPING_JOB = 'nashenski/upgrade/remove_shipping';

	/**
	 * Courier slugs, in display order (names: nashenski_couriers()).
	 */
	const COURIERS = array( 'econt', 'speedy', 'boxnow', 'pigeon' );

	/**
	 * Instance.
	 *
	 * @var mixed
	 */
	protected static $instance;

	/**
	 * Container.
	 *
	 * @var \Pimple\Container
	 */
	protected $container = null;

	/**
	 * Constructor.
	 *
	 * @param \Pimple\Container $container Dependency container.
	 */
	public function __construct( \Pimple\Container $container ) {
		$this->container = $container;
	}

	/**
	 * Init.
	 */
	public function init() {
		require_once __DIR__ . '/functions.php';
		add_action( 'woocommerce_checkout_update_order_review', 'nashenski_keep_order_review_form', -1 );
		add_action( self::REMOVE_SHIPPING_JOB, array( __CLASS__, 'upgrade_remove_shipping' ) );
		$this->maybe_upgrade();
		$this->load_default_options();
		$this->load_classes();
		$this->container->register( new Container\Client() );

		add_filter( 'plugin_action_links_' . plugin_basename( nashenski()->plugin_dir_path() . 'nashenski-commerce.php' ), array( __CLASS__, 'plugin_action_links' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 99, 1 );
		add_filter( 'rest_attachment_query', array( __CLASS__, 'exclude_pdf_from_rest' ), 10, 1 );
	}

	/**
	 * One-time clean-ups when the plugin version changes.
	 */
	private function maybe_upgrade() {
		if ( get_option( 'nashenski_version' ) === self::VERSION ) {
			return;
		}

		global $wpdb;

		// 0.2.0: the BGN/EUR dual price feature was removed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup of options by prefix on upgrade.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name = %s",
				$wpdb->esc_like( 'nashenski_settings_multi_currency_' ) . '%',
				'nashenski_settings_apis_enable_multi_currency'
			)
		);

		// 0.17.0: the CVC courier was removed; its settings, schedule and cached offices go.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup of options by name on upgrade.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'nashenski_settings_' ) . '%cvc%'
			)
		);
		wp_clear_scheduled_hook( 'nashenski/cvc/clear_cache_folder' );

		// 0.21.7: the courier cache moved to File::cache_folder() in uploads. The old folder is only a cache (CVC files too), shared by all sites of a network.
		File::filesystem()->rmdir( File::filesystem()->wp_content_dir() . 'cache/nashenski', true ); // A missing folder is fine.

		// After 0.19.9: the documents migration flag is read on every request; sites that saved it outside the autoload set load it with the other options now.
		wp_set_option_autoload( 'nashenski_documents_migration_version', true );

		if ( version_compare( (string) get_option( 'nashenski_version' ), '0.21.8', '<' ) ) {
			self::upgrade_fee_payer();
		}

		update_option( 'nashenski_version', self::VERSION );
	}

	/**
	 * 0.21.8 (owner decision D6, keep today's money flow): cash on delivery goes into "Customer pays
	 * the courier" of every Econt and Speedy method marked as a courier ("Is ... courier?", on by
	 * default) and of every Pigeon Express method (its cash on delivery waybills were always paid by
	 * the receiver), whatever "Remove delivery from documents" was. BOX NOW cannot charge the
	 * customer. Orders made before keep their documents (Courier_Fee::shop_collects()) through
	 * "Is ... courier?", which takes over the retired option (upgrade_remove_shipping()).
	 */
	public static function upgrade_fee_payer() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time read on upgrade; the shipping methods are not registered yet.
		$methods = $wpdb->get_results( $wpdb->prepare( "SELECT method_id, instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id IN (%s, %s, %s)", 'nashenski_econt', 'nashenski_speedy', 'nashenski_pigeon' ) );

		foreach ( (array) $methods as $method ) {
			$key      = 'woocommerce_' . $method->method_id . '_' . $method->instance_id . '_settings';
			$settings = get_option( $key, array() );
			$settings = is_array( $settings ) ? $settings : array();
			$listed   = (array) ( $settings[ Shipping\Courier_Fee::FIELD ] ?? array() );
			$courier  = 'nashenski_pigeon' === $method->method_id || 'yes' === nashenski_get_option( 'shippings', $method->method_id . '_is_courier' );

			// Written for every method: a method left out keeps an empty list, not the new methods' default.
			$settings[ Shipping\Courier_Fee::FIELD ] = $courier ? array_values( array_unique( array_merge( $listed, array( 'cod' ) ) ) ) : $listed;
			update_option( $key, $settings );
		}

		// After the lists above: the retired option becomes "Is ... courier?" in a background job, so the first request after the update does not read every order line.
		if ( ! wp_next_scheduled( self::REMOVE_SHIPPING_JOB ) ) {
			wp_schedule_single_event( time(), self::REMOVE_SHIPPING_JOB );
		}
	}

	/**
	 * 0.21.8, once, in the background: "Remove delivery from documents" on left the delivery of
	 * every cash on delivery order out of the documents. It is retired: orders without a decision
	 * keep that through "Is ... courier?" of each method they used, then the option goes.
	 */
	public static function upgrade_remove_shipping() {
		global $wpdb;

		if ( 'yes' === nashenski_get_option( 'invoice', 'remove_shipping' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time read in a background job: every method in a zone or on an order.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT method_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods UNION SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = %s", 'method_id' ) );

			foreach ( (array) $ids as $id ) {
				if ( '' !== (string) $id && strlen( $id ) <= 100 ) {
					nashenski_set_option( 'shippings', $id . '_is_courier', 'yes' );
				}
			}
		}

		delete_option( 'nashenski_settings_invoice_remove_shipping' );
	}

	/**
	 * Load default options.
	 */
	private function load_default_options() {
		if ( ! nashenski_get_option( 'apis', 'enable_documents' ) ) {
			$order_documents_trigger = nashenski_get_option( 'invoice', 'trigger' );

			if ( 'disabled' !== $order_documents_trigger ) {
				nashenski_set_option( 'apis', 'enable_documents', 'yes' );
				nashenski_set_option( 'invoice', 'nra_n18', 'yes' );
				nashenski_set_option( 'invoice', 'trigger', $order_documents_trigger );
			} else {
				nashenski_set_option( 'apis', 'enable_documents', 'no' );
				nashenski_set_option( 'invoice', 'nra_n18', 'no' );
			}

			add_action(
				'admin_notices',
				function () {
					/* translators: %s: link to the settings */
					$message = sprintf( __( 'Nashenski Commerce - Please review the options and save them again from "%s".', 'nashenski-commerce' ), sprintf( '<a href="%s">%s</a>', \Nashenski\Admin\Settings::url(), __( 'Settings', 'nashenski-commerce' ) ) );

					echo wp_kses_post( sprintf( '<div class="error">%s</div>', wpautop( $message ) ) );
				}
			);
		}

		if ( nashenski_get_option( 'reports', 'enable_connectix' ) && ! nashenski_get_option( 'connectix', 'level_of_warning' ) ) {
			nashenski_set_option( 'connectix', 'level_of_warning', 'high' );
		}

		if ( nashenski_get_option( 'reports', 'enable_nepostop' ) && ! nashenski_get_option( 'nepostop', 'level_of_warning' ) ) {
			nashenski_set_option( 'nepostop', 'level_of_warning', '20' );
		}
	}

	/**
	 * Load classes.
	 */
	private function load_classes() {
		\Nashenski\Hooks::action( 'nashenski_before_init_classes', 'nashenski/before-init-classes', $this, $this->container );

		new Admin\Admin_Menus();
		new Admin\Order\Columns();
		// Documents made by an earlier N-18 plugin stay on the customer's order page after the import.
		add_action( 'woocommerce_order_details_after_customer_details', array( 'Nashenski\Admin\Import_Sources', 'customer_documents' ), 20 );

		if ( is_admin() ) {
			new Admin\Migration();
			new Admin\Import_Sources();
			new Admin\Checkout_Fields_Import();
			new Admin\Setting_Help();
			new Admin\Changelog();
			new Admin\Abandoned_Carts_Page();
			new Admin\Recommended_Settings();
			new Admin\Setup_Wizard();
			new Admin\Export_Builder();
		}

		// The order document is made for every order, so these load with documents off too.
		new Document_Storage();
		new Admin\Order\Actions();
		new Admin\Order\MetaBox();

		if ( nashenski_get_option( 'apis', 'enable_documents' ) === 'yes' ) {
			new Admin\Order\Emails();
			new Admin\Order\Subscriptions();

			if ( nashenski_get_option( 'nap', 'vat_113_9' ) === 'yes' && ! wc_tax_enabled() ) {
				new Invoice\Vat1139();
			}

			if ( nashenski_get_option( 'invoice', 'nra_n18' ) === 'yes' ) {
				new Admin\ImpossibleVatPrice();
			}
		}

		if ( Front_End\Checkout\Company_Fields::enabled() ) {
			new Front_End\Checkout\Company_Fields();

			if ( ! class_exists( 'WC_EU_VAT_Number_Init' ) ) {
				new Front_End\Checkout\EU_Vat();
				new Admin\EU_Vat();
			}
		}

		new Shipping\Register( $this->container );
		new Shipping\Courier_Fee();
		new Shipping\Automation();
		new Export\Nra\Monthly();
		new Shipping\CheckoutLayout();
		new Front_End\Checkout\Transliterate_Address();
		new Front_End\Checkout\Field_Editor();
		new Front_End\Checkout\Countries();
		new Front_End\Checkout\Block_Checkout();
		new Front_End\Checkout\Pickers();
		new Front_End\Withdrawal();
		new Front_End\Quick_Order();

		if ( is_admin() ) {
			new Admin\Order\Delivery_Box();
			new Admin\Order\Courier_Info();
		}
		new Abandoned_Carts\Tracker();
		new Pricing\Updater();

		new Reports\Reports();

		// WP-CLI: wp nashenski setting ... and wp nashenski courier ... (the settings page, from the command line).
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'nashenski setting', 'Nashenski\CLI\Settings_Command' );
			\WP_CLI::add_command( 'nashenski courier', 'Nashenski\CLI\Courier_Command' );
		}

		\Nashenski\Hooks::action( 'nashenski_init_classes', 'nashenski/init-classes' );
	}

	/**
	 * Container.
	 *
	 * @return mixed Result.
	 */
	public function container() {
		return $this->container;
	}

	/**
	 * Get the URL of the plugin's root directory.
	 *
	 * @return string The URL for the plugin's root directory, with a trailing slash
	 */
	public function plugin_dir_url() {
		return plugin_dir_url( $this->container()['plugin_file'] );
	}

	/**
	 * Get the file system path of the plugin's root directory.
	 *
	 * @return string The file system path for the plugin's root directory, with a trailing slash
	 */
	public function plugin_dir_path() {
		return plugin_dir_path( $this->container()['plugin_file'] );
	}

	/**
	 * Get the plugin instance, creating it on the first call.
	 *
	 * @param null|\ArrayAccess $container Dependency container, needed on the first call.
	 *
	 * @return self
	 * @throws \Exception When the first call has no container.
	 */
	public static function instance( $container = null ) {
		if ( ! isset( self::$instance ) ) {
			if ( empty( $container ) ) {
				throw new \Exception( 'You need to provide a Pimple container' );
			}

			$class_name     = __CLASS__;
			self::$instance = new $class_name( $container );
		}

		return self::$instance;
	}

	/**
	 * Plugin action links.
	 *
	 * @param mixed $actions Actions.
	 * @return mixed Result.
	 */
	public static function plugin_action_links( $actions ) {
		$custom_actions = array(
			'settings' => sprintf( '<a href="%s">%s</a>', \Nashenski\Admin\Settings::url(), __( 'Settings', 'nashenski-commerce' ) ),
		);
		return array_merge( $custom_actions, $actions );
	}

	/**
	 * The WooCommerce Emails listing can only edit block-based emails. Our reminder is a classic
	 * WC_Email, so its Edit button errors out. On that screen we make the button open the classic
	 * per-email settings page instead, which still renders and saves our form the normal way.
	 */
	private static function maybe_redirect_email_edit() {
		// WordPress's page and WooCommerce's settings tab of this screen.
		global $plugin_page, $current_tab;

		if ( 'wc-settings' !== $plugin_page || 'email' !== $current_tab ) {
			return;
		}

		wp_enqueue_script( 'nashenski-email-edit', nashenski()->plugin_dir_url() . 'assets/js/email-edit.js', array( 'wp-api-fetch' ), self::VERSION, true );
		wp_localize_script( 'nashenski-email-edit', 'nashenskiEmailEdit', array( 'url' => admin_url( 'admin.php?page=wc-settings&tab=email&section=nashenski_abandoned_cart' ) ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public static function admin_enqueue_scripts() {
		self::maybe_redirect_email_edit();

		// Our settings tab is plain WooCommerce settings: only our small readable files.
		if ( Admin\Settings::is_current() ) {
			wp_enqueue_media();
			wp_enqueue_style( 'nashenski-admin', nashenski()->plugin_dir_url() . 'assets/css/admin.css', array(), self::VERSION );
			wp_enqueue_script( 'nashenski-settings', nashenski()->plugin_dir_url() . 'assets/js/settings.js', array(), self::VERSION, true );
			wp_localize_script(
				'nashenski-settings',
				'nashenskiSettings',
				array(
					'downloaded'    => __( 'The file was downloaded.', 'nashenski-commerce' ),
					'failed'        => __( 'Something went wrong. Please try again.', 'nashenski-commerce' ),
					'removeRow'     => __( 'Remove row', 'nashenski-commerce' ),
					'restored'      => __( 'Built-in prices restored. Save to keep them.', 'nashenski-commerce' ),
					'pricesSame'    => __( 'Your prices match the courier website.', 'nashenski-commerce' ),
					'pricesChanged' => __( 'The courier website has different prices:', 'nashenski-commerce' ),
					'now'           => __( 'Now', 'nashenski-commerce' ),
					'website'       => __( 'Website', 'nashenski-commerce' ),
					'useThese'      => __( 'Use these prices', 'nashenski-commerce' ),
					'filled'        => __( 'New prices filled in. Check them and save.', 'nashenski-commerce' ),
				)
			);

			// WooCommerce's section of the settings screen.
			if ( 'exports' === $GLOBALS['current_section'] ) {
				wp_enqueue_script( 'nashenski-export-builder', nashenski()->plugin_dir_url() . 'assets/js/export-builder.js', array(), self::VERSION, true );
				wp_localize_script( 'nashenski-export-builder', 'nashenskiExportBuilder', Admin\Export_Builder::script_data() );
			}

			return;
		}

		// The order screens, the product screens (impossible price notice) and its scan page only.
		$screen = get_current_screen();
		$id     = $screen ? $screen->id : '';

		if ( ! in_array( $id, array( 'shop_order', 'edit-shop_order', 'shop_subscription', 'edit-shop_subscription', 'product', 'edit-product', 'product_page_' . Admin\ImpossibleVatPrice::PAGE_SLUG ), true ) && 0 !== strpos( $id, 'woocommerce_page_wc-orders' ) ) {
			return;
		}

		wp_enqueue_style( 'nashenski-orders', nashenski()->plugin_dir_url() . 'assets/css/orders.css', array(), self::VERSION );

		// The order list label button, notice dismiss and impossible price notice. The courier
		// label boxes below print their data (nashenski_<courier>) on this handle.
		wp_enqueue_script( 'nashenski-js-admin', nashenski()->plugin_dir_url() . 'assets/js/admin.js', array( 'jquery' ), self::VERSION, true );
		wp_localize_script( 'nashenski-js-admin', 'nashenskiAdmin', array( 'dismissNonce' => wp_create_nonce( 'nashenski_dismiss_notice' ) ) );
		// "Make invoice" in the PDF Files box saves the order with the invoice request.
		wp_add_inline_script( 'nashenski-js-admin', 'document.addEventListener("click",function(e){var b=e.target.closest(".nashenski-make-invoice__button");if(b){b.form.elements.nashenski_make_invoice.value="1";b.form.requestSubmit();}});' );

		wp_enqueue_style( 'nashenski-admin-screens', nashenski()->plugin_dir_url() . 'assets/css/admin-screens.css', array(), self::VERSION );

		// The label boxes of the couriers that are turned on: assets/js/label-<courier>.js on the shared label-box.js.
		wp_register_script( 'nashenski-label-box', nashenski()->plugin_dir_url() . 'assets/js/label-box.js', array( 'jquery', 'nashenski-js-admin' ), self::VERSION, true );
		wp_localize_script( 'nashenski-label-box', 'nashenskiLabelBoxText', array( 'error' => __( 'The request failed. Reload the page and try again.', 'nashenski-commerce' ) ) );

		wp_register_script( 'nashenski-courier-pdf', nashenski()->plugin_dir_url() . 'assets/js/courier-pdf.js', array(), self::VERSION, true );

		foreach ( self::COURIERS as $courier ) {
			if ( 'yes' === nashenski_get_option( 'apis', 'enable_' . $courier ) ) {
				// Econt opens courier PDFs through the validated preview helper.
				$deps = 'econt' === $courier ? array( 'nashenski-label-box', 'nashenski-courier-pdf' ) : array( 'nashenski-label-box' );
				wp_enqueue_script( 'nashenski-js-' . $courier, nashenski()->plugin_dir_url() . 'assets/js/label-' . $courier . '.js', $deps, self::VERSION, true );
			}
		}
	}

	/**
	 * Robots txt.
	 *
	 * @param mixed $output Output.
	 * @return mixed Result.
	 */
	public static function robots_txt( $output ) {
		$plugin_dir_url = str_replace( site_url(), '', nashenski()->plugin_dir_url() );
		$upload_dir     = wp_upload_dir();
		$upload_dir     = str_replace( site_url(), '', $upload_dir['baseurl'] );

		$output .= "\n";
		$output .= 'Disallow: ' . $plugin_dir_url . "\n";
		$output .= 'Disallow: ' . $upload_dir . "/nashenski/\n";

		return $output;
	}

	/**
	 * Exclude pdf from rest.
	 *
	 * @param mixed $args Args.
	 * @return mixed Result.
	 */
	public static function exclude_pdf_from_rest( $args ) {
		$unsupported_mimes      = array( 'application/pdf', 'application/xml', 'text/plain' );
		$all_mimes              = get_allowed_mime_types();
		$accepted_mimes         = array_diff( $all_mimes, $unsupported_mimes );
		$args['post_mime_type'] = $accepted_mimes;

		return $args;
	}
}
