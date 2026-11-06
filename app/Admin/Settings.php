<?php // phpcs:disable WordPress.Files.FileName -- class files are named after the class and autoloaded by namespace.
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
	 * Whether the current request is the Nashenski settings tab.
	 *
	 * @return bool
	 */
	public static function is_current() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen detection only.
		return is_admin() && isset( $_GET['page'], $_GET['tab'] ) && 'wc-settings' === $_GET['page'] && self::TAB === $_GET['tab'];
	}
}
