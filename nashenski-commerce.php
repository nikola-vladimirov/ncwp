<?php
/**
 * Nashenski Commerce main file: constants, bundled translations and plugin start.
 *
 * @package Nashenski_Commerce
 */

/*
Plugin Name:  Nashenski Commerce
Plugin URI:   https://nashenski.com/manual/
Description:  WooCommerce for Bulgaria: Econt, Speedy, BOX NOW and Pigeon shipping, invoices and Ordinance N-18 documents.
Requires Plugins: woocommerce
Author:       PristaDigital
Author URI:   https://pristadigital.com/
Version:      0.21.10
Requires at least: 6.5
Requires PHP: 8.2
WC requires at least: 9.4.0
WC tested up to: 11.3
Text Domain:  nashenski-commerce
Domain Path:  /languages
License:      GPLv3 or later
License URI:  https://www.gnu.org/licenses/gpl-3.0.html
*/

/*
 * Nashenski Commerce is a fork of Bulgarisation for WooCommerce 4.0.13
 * by Autopolis.bg (https://autopolis.bg/), released under GPLv3 or later.
 * Original work copyright its authors; changes copyright PristaDigital.
 */

defined( 'ABSPATH' ) || exit;
define( 'NASHENSKI_PHP_MINIMUM_VERSION', '8.2' );
define( 'NASHENSKI_WP_MINIMUM_VERSION', '6.5' );

// Stop every scheduled job of the plugin on deactivation; no data is deleted (see uninstall.php). The jobs are scheduled again when the plugin is turned on and loads.
register_deactivation_hook(
	__FILE__,
	function () {
		foreach ( array(
			'nashenski/boxnow/clear_cache_folder',
			'nashenski/econt/clear_cache_folder',
			'nashenski/pigeon/clear_cache_folder',
			'nashenski/speedy/clear_cache_folder',
			'nashenski/cvc/clear_cache_folder',
			'nashenski/prices/check',
			'nashenski_delete_export_files',
			'nashenski/upgrade/remove_shipping',
		) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}

		// Action Scheduler jobs would fail with "no callbacks are registered" while the plugin is off.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), 'nashenski' );
		}

		// The flags that make the carts tracker and the documents migration schedule their jobs again on the next load.
		delete_option( 'nashenski_carts_db_version' );
		delete_option( 'nashenski_documents_migration_version' );
	}
);

// Deactivation clears the one-off 0.21.8 background step too. While it is still due (its retired option exists), turning the plugin on schedules it again.
register_activation_hook(
	__FILE__,
	function () {
		$due = false !== get_option( 'nashenski_settings_invoice_remove_shipping' ) && version_compare( (string) get_option( 'nashenski_version' ), '0.21.8', '>=' );

		if ( $due && ! wp_next_scheduled( 'nashenski/upgrade/remove_shipping' ) ) {
			wp_schedule_single_event( time(), 'nashenski/upgrade/remove_shipping' );
		}
	}
);

// Start the plugin.
add_action( 'plugins_loaded', 'nashenski_init', 1, 0 );
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Load the plugin's texts for the current (or a given) locale: a WordPress.org language pack
 * when there is one, else the translation shipped in /languages. Documents use it to print in
 * Bulgarian and English whatever the site language. Bulgarian also gets the texts printed on
 * documents when no translation (the WordPress.org zip before its language pack) or an older one lacks them.
 *
 * @param string|null $locale Locale, for example from the change_locale action.
 */
function nashenski_load_bundled_translations( $locale = null ) {
	$locale = is_string( $locale ) && '' !== $locale ? $locale : determine_locale();
	$pack   = WP_LANG_DIR . '/plugins/nashenski-commerce-' . $locale;
	$file   = file_exists( $pack . '.mo' ) || file_exists( $pack . '.l10n.php' ) ? $pack . '.mo' : __DIR__ . '/languages/nashenski-commerce-' . $locale . '.mo';

	unload_textdomain( 'nashenski-commerce' );
	load_textdomain( 'nashenski-commerce', $file, $locale );

	// Ordinance N-18 documents must be in Bulgarian whatever the site language, also for texts a language pack lacks.
	remove_filter( 'gettext_nashenski-commerce', 'nashenski_document_text_bg' );
	if ( 'bg_BG' === $locale ) {
		add_filter( 'gettext_nashenski-commerce', 'nashenski_document_text_bg', 10, 2 );
	}
}

/**
 * Bulgarian for an untranslated document text (app/Invoice/document-texts-bg.php).
 *
 * @param string $translation Translated text.
 * @param string $text        Original text.
 * @return string
 */
function nashenski_document_text_bg( $translation, $text ) {
	static $texts = null;

	if ( $translation !== $text ) {
		return $translation;
	}

	// shortcut: singular texts only (the file has no plural or context entries); add ngettext/gettext_with_context filters if it gets some.
	$texts = $texts ?? array_column( include __DIR__ . '/app/Invoice/document-texts-bg.php', 'translations', 'singular' );

	return isset( $texts[ $text ] ) ? $texts[ $text ][0] : $translation;
}

/**
 * Whether a notice about why the plugin did not start belongs here: for users who manage plugins, on
 * the Dashboard, the Plugins screen and the WooCommerce screens only.
 *
 * @return bool
 */
function nashenski_start_notice_here() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	return current_user_can( 'activate_plugins' ) && $screen && ( in_array( $screen->id, array( 'dashboard', 'plugins', 'edit-shop_order', 'shop_order' ), true ) || false !== strpos( $screen->id, 'woocommerce' ) );
}

/**
 * Start the plugin and return its instance.
 *
 * @return \Nashenski\Plugin
 */
function nashenski_init() {
	// Bundled translations (Bulgarian). A language pack from WordPress.org, when there is one, wins.
	add_action( 'init', 'nashenski_load_bundled_translations', 0 );
	add_action( 'change_locale', 'nashenski_load_bundled_translations' );

	if ( version_compare( PHP_VERSION, NASHENSKI_PHP_MINIMUM_VERSION, '<' ) || version_compare( get_bloginfo( 'version' ), NASHENSKI_WP_MINIMUM_VERSION, '<' ) ) {
		add_action(
			'admin_notices',
			function () {
				if ( ! nashenski_start_notice_here() ) {
					return;
				}
				/* translators: %1$s is minimum php version and %2$s is minimum WP version*/
				$message = sprintf( esc_html__( 'Nashenski Commerce requires PHP version %1$s+ and WP version %2$s+, plugin is currently NOT RUNNING.', 'nashenski-commerce' ), NASHENSKI_PHP_MINIMUM_VERSION, NASHENSKI_WP_MINIMUM_VERSION );
				echo wp_kses_post( sprintf( '<div class="error">%s</div>', wpautop( $message ) ) );
			}
		);

		return;
	}

	// The plugin we forked (woo_bg_init up to 4.x, bulgarisation_init from 5.0) registers the same checkout fields and
	// shipping methods and numbers the same documents; never run both.
	if ( function_exists( 'woo_bg_init' ) || function_exists( 'bulgarisation_init' ) ) {
		add_action(
			'admin_notices',
			function () {
				if ( ! nashenski_start_notice_here() ) {
					return;
				}
				$message = esc_html__( 'Nashenski Commerce is paused: please deactivate Bulgarisation for WooCommerce first. Your settings and orders can be imported afterwards.', 'nashenski-commerce' );
				echo wp_kses_post( sprintf( '<div class="error">%s</div>', wpautop( $message ) ) );
			}
		);

		return;
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				if ( ! nashenski_start_notice_here() ) {
					return;
				}
				$message = sprintf( esc_html__( 'Nashenski Commerce - Please enable WooCommerce first.', 'nashenski-commerce' ) );
				echo wp_kses_post( sprintf( '<div class="error">%s</div>', wpautop( $message ) ) );
			}
		);

		return;
	}

	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';
		require_once __DIR__ . '/app/autoload.php';
	} else {
		add_action(
			'admin_notices',
			function () {
				if ( ! nashenski_start_notice_here() ) {
					return;
				}
				$message = sprintf( esc_html__( 'Please run `composer install` in the plugin folder `nashenski-commerce`', 'nashenski-commerce' ) );
				echo wp_kses_post( sprintf( '<div class="error">%s</div>', wpautop( $message ) ) );
			}
		);
		return;
	}

	$container = new \Pimple\Container( array( 'plugin_file' => __FILE__ ) );
	$plugin    = \Nashenski\Plugin::instance( $container );
	$plugin->init();

	/**
	 * Fires after the plugin has initialized
	 *
	 * @param \Nashenski\Plugin $plugin    The global instance of the plugin controller
	 * @param \Pimple\Container   $container The plugin's dependency injection container
	 */
	\Nashenski\Hooks::action( 'nashenski_init', 'nashenski/init', $plugin, $container );

	return $plugin;
}

/**
 * Nashenski.
 *
 * @return mixed Result.
 */
function nashenski() {
	try {
		return \Nashenski\Plugin::instance();
	} catch ( \Exception $e ) {
		return nashenski_init();
	}
}
