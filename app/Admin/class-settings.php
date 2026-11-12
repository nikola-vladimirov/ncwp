<?php
/**
 * Helpers for the WooCommerce > Settings > Nashenski tab that are safe on every screen.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Helpers for the WooCommerce > Settings > Nashenski tab that are safe on every screen.
 * (Settings_Page extends WC_Settings_Page, which WooCommerce loads only on its settings screens.)
 */
class Settings {
	const TAB = 'nashenski';

	/**
	 * Admin URL of the Nashenski settings tab, or of one of its sections.
	 *
	 * @param string $section Section slug, empty for the tab itself.
	 *
	 * @return string
	 */
	public static function url( $section = '' ) {
		return admin_url( 'admin.php?page=wc-settings&tab=' . self::TAB . ( $section ? '&section=' . $section : '' ) );
	}

	/**
	 * Link to one courier of the Couriers section. Its nonce lets the settings page take the courier
	 * from the address.
	 *
	 * @param string $courier Courier slug.
	 * @return string
	 */
	public static function courier_url( $courier ) {
		return add_query_arg(
			array(
				'courier'            => $courier,
				'_nashenski_courier' => wp_create_nonce( 'nashenski_courier' ),
			),
			self::url( 'couriers' )
		);
	}

	/**
	 * Whether the current request is the Nashenski settings tab. WordPress sets $plugin_page before
	 * admin_init and WooCommerce sets $current_tab on wp_loaded, so this works from admin_init on.
	 *
	 * @return bool
	 */
	public static function is_current() {
		global $plugin_page, $current_tab;

		return is_admin() && 'wc-settings' === $plugin_page && self::TAB === $current_tab;
	}
}
