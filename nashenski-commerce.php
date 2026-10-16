<?php
/*
Plugin Name:  Nashenski Commerce
Plugin URI:   https://pristadigital.com/
Description:  Everything a WooCommerce store needs to sell in Bulgaria: Econt, Speedy, BOX NOW, CVC and Pigeon shipping, invoices, and the light regime of Ordinance N-18.
Requires Plugins: woocommerce
Author:       PristaDigital
Author URI:   https://pristadigital.com/
Version:      0.16.0
Requires at least: 6.5
Requires PHP: 7.4
WC requires at least: 9.4.0
WC tested up to: 11.1
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
define( 'NASHENSKI_PHP_MINIMUM_VERSION', '7.4' );
define( 'NASHENSKI_WP_MINIMUM_VERSION', '4.8' );

// Start the plugin
add_action( 'plugins_loaded', 'nashenski_init', 1, 0 );
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Load the plugin's texts for the current (or a given) locale: a WordPress.org language pack
 * when there is one, else the translation shipped in /languages. Documents use it to print in
 * Bulgarian and English whatever the site language.
 *
 * @param string|null $locale Locale, for example from the change_locale action.
 */
function nashenski_load_bundled_translations( $locale = null ) {
	$locale = is_string( $locale ) && '' !== $locale ? $locale : determine_locale();
	$pack = WP_LANG_DIR . '/plugins/nashenski-commerce-' . $locale;
	$file = file_exists( $pack . '.mo' ) || file_exists( $pack . '.l10n.php' ) ? $pack . '.mo' : __DIR__ . '/languages/nashenski-commerce-' . $locale . '.mo';

	unload_textdomain( 'nashenski-commerce' );
	load_textdomain( 'nashenski-commerce', $file, $locale );
}

/**
 * @return \Nashenski\Plugin
 */
function nashenski_init() {
	// Bundled translations (Bulgarian). A language pack from WordPress.org, when there is one, wins.
	add_action( 'init', 'nashenski_load_bundled_translations', 0 );
	add_action( 'change_locale', 'nashenski_load_bundled_translations' );

	if ( version_compare( PHP_VERSION, NASHENSKI_PHP_MINIMUM_VERSION, '<' ) || version_compare( get_bloginfo( 'version' ), NASHENSKI_WP_MINIMUM_VERSION, '<' ) ) {
		add_action( 'admin_notices', function() {
			/* translators: %1$s is minimum php version and %2$s is minimum WP version*/
			$message = sprintf( esc_html__( 'Nashenski Commerce requires PHP version %1$s+ and WP version %2$s+, plugin is currently NOT RUNNING.', 'nashenski-commerce' ), NASHENSKI_PHP_MINIMUM_VERSION, NASHENSKI_WP_MINIMUM_VERSION );
			echo wp_kses_post( sprintf( '<div class="error">%s</div>', wpautop( $message ) ) );
		} );

		return;
	}

	// The plugin we forked registers the same checkout fields and shipping methods; never run both.
	if ( function_exists( 'woo_bg_init' ) ) {
		add_action( 'admin_notices', function() {
			$message = esc_html__( 'Nashenski Commerce is paused: please deactivate Bulgarisation for WooCommerce first. Your settings and orders can be imported afterwards.', 'nashenski-commerce' );
			echo wp_kses_post( sprintf( '<div class="error">%s</div>', wpautop( $message ) ) );
		} );

		return;
	}

	if ( !class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function() {
			$message = sprintf( esc_html__( 'Nashenski Commerce - Please enable WooCommerce first.', 'nashenski-commerce' ) );
			echo wp_kses_post( sprintf( '<div class="error">%s</div>', wpautop( $message ) ) );
		} );
		
		return;
	}

	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';
	} else {
		add_action( 'admin_notices', function() {
			$message = sprintf( esc_html__( 'Please run `composer install` in the plugin folder `nashenski-commerce`', 'nashenski-commerce' ) );
			echo wp_kses_post( sprintf( '<div class="error">%s</div>', wpautop( $message ) ) );
		} );
		return;
	}

	$container = new \Pimple\Container( [ 'plugin_file' => __FILE__ ] );
	$plugin    = \Nashenski\Plugin::instance( $container );
	$plugin->init();

	/**
	 * Fires after the plugin has initialized
	 *
	 * @param \Nashenski\Plugin $plugin    The global instance of the plugin controller
	 * @param \Pimple\Container   $container The plugin's dependency injection container
	 */
	do_action( 'nashenski/init', $plugin, $container );

	return $plugin;
}

function nashenski() {
	try {
		return \Nashenski\Plugin::instance();
	} catch ( \Exception $e ) {
		return nashenski_init();
	}
}