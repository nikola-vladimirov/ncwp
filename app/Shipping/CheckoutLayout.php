<?php // phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the class name as the file name.
/**
 * Checkout layout.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout layout changes for the shipping section.
 */
class CheckoutLayout {
	/**
	 * Registers the hooks.
	 */
	public function __construct() {
		add_filter( 'woocommerce_shipping_package_name', array( __CLASS__, 'change_shipping_package_name' ), 10, 2 );
	}

	/**
	 * Names every shipping package "Shipping".
	 *
	 * @param string $name Package name.
	 * @param int    $i    Package index.
	 * @return string
	 */
	public static function change_shipping_package_name( $name, $i ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- filter callback signature.
		return __( 'Shipping', 'nashenski-commerce' );
	}
}
