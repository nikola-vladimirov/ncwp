<?php
namespace Nashenski;

defined( 'ABSPATH' ) || exit;

class Plugin {
	const VERSION = '0.16.2';

	/**
	 * Courier slugs, in display order (names: nashenski_couriers()).
	 */
	const COURIERS = array( 'econt', 'speedy', 'boxnow', 'pigeon', 'cvc' );
	// Couriers whose label box is readable JS (assets/js/label-<courier>.js). See docs/plans/dist-rewrite.md.
	const READABLE_BOXES = array( 'cvc', 'boxnow', 'pigeon', 'speedy', 'econt' );

	protected static $_instance;

	/** @var \Pimple\Container */
	protected $container = null;

	/**
	 * @param \Pimple\Container $container
	 */
	public function __construct( \Pimple\Container $container ) {
		$this->container = $container;
	}

	public function init() {
		require_once __DIR__ . '/functions.php';
		$this->maybe_upgrade();
		$this->load_default_options();
		$this->load_classes();
		$this->container->register( new Container\Client() );

		add_filter( 'plugin_action_links_' . plugin_basename( nashenski()->plugin_dir_path() . 'nashenski-commerce.php' ), array( __CLASS__, 'plugin_action_links' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'admin_footer', array( __CLASS__, 'set_webpack_path' ), 0);
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 99, 2 );
		add_filter( 'rest_attachment_query', array( __CLASS__, 'exclude_pdf_from_rest' ), 10, 2);
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
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name = %s",
			$wpdb->esc_like( 'nashenski_settings_multi_currency_' ) . '%',
			'nashenski_settings_apis_enable_multi_currency'
		) );

		update_option( 'nashenski_version', self::VERSION );
	}

	private function load_default_options() {
		if ( ! nashenski_get_option( 'apis', 'enable_documents' ) ) {
			$order_documents_trigger = nashenski_get_option( 'invoice', 'trigger' );

			if ( $order_documents_trigger !== 'disabled' ) {
				nashenski_set_option( 'apis', 'enable_documents', 'yes' );
				nashenski_set_option( 'invoice', 'nra_n18', 'yes' );
				nashenski_set_option( 'invoice', 'trigger', $order_documents_trigger );
			} else {
				nashenski_set_option( 'apis', 'enable_documents', 'no' );
				nashenski_set_option( 'invoice', 'nra_n18', 'no' );
			}

			nashenski_set_option( 'checkout', 'alternative_shipping_table', nashenski_get_option( 'nap', 'alternative_shipping_table' ) );

			add_action( 'admin_notices', function() {
				/* translators: %s: link to the settings */
				$message = sprintf( __( 'Nashenski Commerce - Please review the options and save them again from "%s".', 'nashenski-commerce' ), sprintf( '<a href="%s">%s</a>', \Nashenski\Admin\Settings::url(), __( 'Settings', 'nashenski-commerce' ) ) );

				echo wp_kses_post( sprintf( '<div class="error">%s</div>', wpautop( $message ) ) );
			} );
		}

		if ( nashenski_get_option( 'reports', 'enable_connectix' ) && !nashenski_get_option( 'connectix', 'level_of_warning' )  ) {
			nashenski_set_option( 'connectix', 'level_of_warning', 'high' );
		}

		if ( nashenski_get_option( 'reports', 'enable_nepostop' ) && !nashenski_get_option( 'nepostop', 'level_of_warning' )  ) {
			nashenski_set_option( 'nepostop', 'level_of_warning', '20' );
		}

		if ( !nashenski_get_option( 'shippings', 'nashenski_econt_is_courier' ) ) {
			nashenski_set_option( 'shippings', 'nashenski_econt_is_courier', 'yes' );
			nashenski_set_option( 'shippings', 'nashenski_speedy_is_courier', 'yes' );
			nashenski_set_option( 'shippings', 'nashenski_cvc_is_courier', 'yes' );
			nashenski_set_option( 'shippings', 'nashenski_boxnow_is_courier', 'yes' );
		}

	}

	private function load_classes() {
		do_action( 'nashenski/before-init-classes', $this, $this->container );

		new Admin\Admin_Menus();
		new Admin\Order\Columns();
		// Documents made by an earlier N-18 plugin stay on the customer's order page after the import.
		add_action( 'woocommerce_order_details_after_customer_details', array( 'Nashenski\Admin\Import_Sources', 'customer_documents' ), 20 );

		if ( is_admin() ) {
			new Admin\Migration();
			new Admin\Import_Sources();
			new Admin\Setting_Help();
			new Admin\Changelog();
			new Admin\Abandoned_Carts_Page();
			new Admin\Recommended_Settings();
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
		}
		new Abandoned_Carts\Tracker();
		new Pricing\Updater();

		new Reports\Reports();
		
		do_action( 'nashenski/init-classes' );
	}

	public function container() {
		return $this->container;
	}

	/**
	 * @return string The URL for the plugin's root directory, with a trailing slash
	 */
	public function plugin_dir_url() {
		return plugin_dir_url( $this->container()[ 'plugin_file' ] );
	}

	/**
	 * @return string The file system path for the plugin's root directory, with a trailing slash
	 */
	public function plugin_dir_path() {
		return plugin_dir_path( $this->container()[ 'plugin_file' ] );
	}

	/**
	 * @param null|\ArrayAccess $container
	 *
	 * @return self
	 * @throws \Exception
	 */
	public static function instance( $container = null ) {
		if ( ! isset( self::$_instance ) ) {
			if ( empty( $container ) ) {
				throw new \Exception( 'You need to provide a Pimple container' );
			}

			$className       = __CLASS__;
			self::$_instance = new $className( $container );
		}

		return self::$_instance;
	}

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
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only.
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || 'wc-settings' !== $_GET['page'] || 'email' !== $_GET['tab'] ) {
			return;
		}

		if ( ! function_exists( 'wp_add_inline_script' ) ) {
			return;
		}

		$classic_url = admin_url( 'admin.php?page=wc-settings&tab=email&section=nashenski_abandoned_cart' );

		wp_register_script( 'nashenski-email-edit', false, array( 'wp-api-fetch' ), self::VERSION, true );
		wp_enqueue_script( 'nashenski-email-edit' );
		wp_add_inline_script( 'nashenski-email-edit', '(function(){
			if(!window.wp||!wp.apiFetch||!wp.apiFetch.use){return;}
			var url=' . wp_json_encode( $classic_url ) . ';
			wp.apiFetch.use(function(options,next){
				var path=options&&options.path?String(options.path):"";
				if(path.indexOf("recreate-email-post")!==-1&&options.data&&options.data.email_id==="nashenski_abandoned_cart"){
					window.location.href=url;
					return new Promise(function(){});
				}
				return next(options);
			});
		})();' );
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
			wp_localize_script( 'nashenski-settings', 'nashenskiSettings', array(
				'downloaded' => __( 'The file was downloaded.', 'nashenski-commerce' ),
				'failed' => __( 'Something went wrong. Please try again.', 'nashenski-commerce' ),
			'removeRow' => __( 'Remove row', 'nashenski-commerce' ),
			'restored' => __( 'Built-in prices restored. Save to keep them.', 'nashenski-commerce' ),
			'pricesSame' => __( 'Your prices match the courier website.', 'nashenski-commerce' ),
			'pricesChanged' => __( 'The courier website has different prices:', 'nashenski-commerce' ),
			'now' => __( 'Now', 'nashenski-commerce' ),
			'website' => __( 'Website', 'nashenski-commerce' ),
			'useThese' => __( 'Use these prices', 'nashenski-commerce' ),
			'filled' => __( 'New prices filled in. Check them and save.', 'nashenski-commerce' ),
			) );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only.
			if ( isset( $_GET['section'] ) && 'exports' === $_GET['section'] ) {
				wp_enqueue_script( 'nashenski-export-builder', nashenski()->plugin_dir_url() . 'assets/js/export-builder.js', array(), self::VERSION, true );
				wp_localize_script( 'nashenski-export-builder', 'nashenskiExportBuilder', Admin\Export_Builder::script_data() );
			}

			return;
		}

		// The order screens, the product screens (impossible price notice) and its scan page only.
		$screen = get_current_screen();
		$id = $screen ? $screen->id : '';

		if ( ! in_array( $id, array( 'shop_order', 'edit-shop_order', 'shop_subscription', 'edit-shop_subscription', 'product', 'edit-product', 'product_page_' . Admin\ImpossibleVatPrice::PAGE_SLUG ), true ) && 0 !== strpos( $id, 'woocommerce_page_wc-orders' ) ) {
			return;
		}

		wp_enqueue_style( 'nashenski-orders', nashenski()->plugin_dir_url() . 'assets/css/orders.css', array(), self::VERSION );

		// The order list label button, notice dismiss and impossible price notice. The compiled courier
		// label boxes below print their data (nashenski_<courier>) on this handle.
		wp_enqueue_script( 'nashenski-js-admin', nashenski()->plugin_dir_url() . 'assets/js/admin.js', array( 'jquery' ), self::VERSION, true );
		wp_localize_script( 'nashenski-js-admin', 'nashenskiAdmin', array( 'dismissNonce' => wp_create_nonce( 'nashenski_dismiss_notice' ) ) );
		// "Make invoice" in the PDF Files box saves the order with the invoice request.
		wp_add_inline_script( 'nashenski-js-admin', 'document.addEventListener("click",function(e){var b=e.target.closest(".nashenski-make-invoice__button");if(b){b.form.elements.nashenski_make_invoice.value="1";b.form.requestSubmit();}});' );

		wp_enqueue_style(
			'nashenski-css-bundle',
			nashenski()->plugin_dir_url() . nashenski_assets_bundle( 'admin.css' ),
			array(),
			\Nashenski\Plugin::VERSION
		);

		// The label boxes of the couriers that are turned on. Couriers in READABLE_BOXES use assets/js/label-<courier>.js
		// on the shared label-box.js; the rest still use the compiled bundle until they are rewritten.
		wp_register_script( 'nashenski-label-box', nashenski()->plugin_dir_url() . 'assets/js/label-box.js', array( 'jquery', 'nashenski-js-admin' ), self::VERSION, true );
		wp_localize_script( 'nashenski-label-box', 'nashenskiLabelBoxText', array( 'error' => __( 'The request failed. Reload the page and try again.', 'nashenski-commerce' ) ) );

		foreach ( self::COURIERS as $courier ) {
			if ( 'yes' === nashenski_get_option( 'apis', 'enable_' . $courier ) ) {
				if ( in_array( $courier, self::READABLE_BOXES, true ) ) {
					wp_enqueue_script( 'nashenski-js-' . $courier, nashenski()->plugin_dir_url() . 'assets/js/label-' . $courier . '.js', array( 'nashenski-label-box' ), self::VERSION, true );
				} else {
					wp_enqueue_script( 'nashenski-js-' . $courier, nashenski()->plugin_dir_url() . nashenski_assets_bundle( $courier . '-admin.js' ), array( 'jquery' ), self::VERSION, true );
				}

				wp_enqueue_style( 'nashenski-css-' . $courier, nashenski()->plugin_dir_url() . nashenski_assets_bundle( $courier . '-admin.css' ), array(), self::VERSION );
			}
		}
	}

	public static function set_webpack_path() {
		if ( ! wp_script_is( 'nashenski-js-admin', 'enqueued' ) ) {
			return;
		}

		$dir = nashenski()->plugin_dir_url() . 'dist/';
		?>
		<script type="text/javascript">
			window.__webpack_public_path__ = '<?php echo esc_url( $dir ) ?>';
		</script>
		<?php
	}

	public static function robots_txt( $output, $public ) {
		$plugin_dir_url = str_replace( site_url(), '', nashenski()->plugin_dir_url() );
		$upload_dir = wp_upload_dir();
		$upload_dir = str_replace( site_url(), '', $upload_dir['baseurl'] );

		$output .= "\n";
		$output .= "Disallow: " . $plugin_dir_url . "\n";
		$output .= "Disallow: " . $upload_dir . "/nashenski/\n";
	
		return $output;
	}

	public static function exclude_pdf_from_rest( $args, $request ) {
		$unsupported_mimes = array( 'application/pdf', 'application/xml', 'text/plain' );
		$all_mimes = get_allowed_mime_types();
		$accepted_mimes = array_diff( $all_mimes, $unsupported_mimes );
		$args[ 'post_mime_type' ] = $accepted_mimes;

		return $args;
	}
}