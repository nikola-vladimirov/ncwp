<?php
namespace Nashenski\Admin;

use Nashenski\Admin\Tabs;

/**
 * Setup menus in WP admin.
 *
 * @package Nashenski\Admin
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Admin_Menus {
	public function __construct() {
		add_action( 'wp_loaded', array( __CLASS__, 'after_setup_theme' ) );
		add_filter( 'woocommerce_get_settings_pages', array( __CLASS__, 'add_settings_page' ) );
		// WordPress refuses unknown admin pages before admin_init, so redirect just before that.
		add_action( 'admin_page_access_denied', array( __CLASS__, 'redirect_old_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'redirect_wrong_tab' ) );
		add_filter( 'upload_mimes', array( __CLASS__, 'allow_upload_xml' ) );
		add_filter( 'admin_footer_text', array( __CLASS__, 'admin_footer_text' ), 99 );
	}

	/**
	 * Settings live in WooCommerce > Settings > Nashenski.
	 */
	public static function add_settings_page( $pages ) {
		$pages[] = new Settings_Page();

		return $pages;
	}

	/**
	 * The settings used to have their own page (admin.php?page=nashenski). Send old links to the new tab.
	 */
	/**
	 * WooCommerce prints a PHP warning for an unknown settings tab. Send the wrong slug to the real one before that.
	 */
	public static function redirect_wrong_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect.
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || 'wc-settings' !== $_GET['page'] || 'nashenski_commerce' !== $_GET['tab'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : '';

		wp_safe_redirect( Settings::url( $section ) );
		exit;
	}

	public static function redirect_old_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect.
		if ( ! isset( $_GET['page'] ) || 'nashenski' !== $_GET['page'] ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$args    = array();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['nashenski_imported'] ) ) {
			$args['nashenski_imported'] = 1;
		}

		// Old tab slugs -> new sections.
		$map = array(
			'settings'   => '',
			'shipping'   => 'checkout',
			'fields'     => 'checkout',
			'export'     => 'exports',
			'connectix'  => 'checks',
			'nepostop'   => 'checks',
			'nekorekten' => 'checks',
		);

		if ( in_array( $section, \Nashenski\Plugin::COURIERS, true ) ) {
			$args['courier'] = $section;
			$section         = 'couriers';
		} elseif ( isset( $map[ $section ] ) ) {
			$section = $map[ $section ];
		}

		wp_safe_redirect( add_query_arg( $args, Settings::url( $section ) ) );
		exit;
	}

	public static function admin_footer_text( $footer_text ) {
		if ( Settings::is_current() ) {
			return sprintf(
				/* translators: 1: Nashenski Commerce, 2: five stars. */
				__( 'If you like %1$s please leave us a %2$s rating. A huge thanks in advance!', 'nashenski-commerce' ),
				sprintf( '<strong>%s</strong>', esc_html__( 'Nashenski Commerce', 'nashenski-commerce' ) ),
				'<a href="https://wordpress.org/support/plugin/nashenski-commerce/reviews?rate=5#new-post" target="_blank" rel="noopener noreferrer" class="nashenski-rating-link" aria-label="' . esc_attr__( 'five star', 'nashenski-commerce' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			);
		}

		return $footer_text;
	}

	public static function after_setup_theme() {
		new Ajax();

		if ( nashenski_get_option( 'connectix', 'enable_tracking' ) === 'yes' ) {
			new Connectix();
		}

		if ( nashenski_get_option( 'nepostop', 'upload_labels' ) === 'yes' ) {
			new NepoStop();
		}
	}

	/**
	 * Returns the definitions for the reports to show in admin.
	 *
	 * @return array
	 */
	public static function get_tabs() {
		$tabs = array(
			self::tab( Tabs\Settings_Tab::class ),
		);

		if ( nashenski_get_option( 'apis', 'enable_documents' ) === 'yes' ) {
			$tabs[] = self::tab( Tabs\Nra_Tab::class );
			$tabs[] = self::tab( Tabs\Export_Tab::class );
		}

		if ( nashenski_is_shipping_enabled() ) {
			$tabs[] = self::tab( Tabs\Shipping_Tab::class );
		}

		$tabs[] = self::tab( Tabs\Fields_Tab::class );

		// Courier and customer check settings, each while it is turned on.
		$optional = array(
			array( 'apis', 'enable_econt', Tabs\Econt_Tab::class ),
			array( 'apis', 'enable_speedy', Tabs\Speedy_Tab::class ),
			array( 'apis', 'enable_boxnow', Tabs\BoxNow_Tab::class ),
			array( 'apis', 'enable_pigeon', Tabs\Pigeon_Tab::class ),
			array( 'reports', 'enable_connectix', Tabs\Connectix_Tab::class ),
			array( 'reports', 'enable_nepostop', Tabs\NepoStop_Tab::class ),
			array( 'reports', 'enable_nekorekten', Tabs\Nekorekten_Tab::class ),
			array( 'apis', 'enable_quick_order', Tabs\Quick_Order_Tab::class ),
			array( 'apis', 'enable_abandoned_carts', Tabs\Abandoned_Carts_Tab::class ),
		);

		foreach ( $optional as list( $group, $name, $class ) ) {
			if ( 'yes' === nashenski_get_option( $group, $name ) ) {
				$tabs[] = self::tab( $class );
			}
		}

		return apply_filters( 'nashenski/admin/get_tabs_items', $tabs );
	}

	/**
	 * One object per tab and request: a tab adds its hooks when built, so a second one (the list is
	 * built again after saving) would show its added fields twice.
	 */
	private static function tab( $class ) {
		static $made = array();

		return $made[ $class ] ?? ( $made[ $class ] = new $class() );
	}

	public static function render_fragment( $fragment, $atts = array() ) {
		$fragment_dir = __DIR__ . DIRECTORY_SEPARATOR . 'fragments' . DIRECTORY_SEPARATOR . $fragment . '.php';

		if ( ! is_readable( $fragment_dir ) ) {
			return;
		}

		extract( $atts );

		include $fragment_dir;
	}

	public static function allow_upload_xml( $mimes ) {
		$mimes = array_merge( $mimes, array( 'xml' => 'application/xml' ) );

		return $mimes;
	}
}
