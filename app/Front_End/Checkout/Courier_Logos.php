<?php
namespace Nashenski\Front_End\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Courier logos and the cart and checkout page test. The logos are placed by assets/js/pickers.js.
 */
class Courier_Logos {
	/**
	 * Logo URL per courier slug (the part after "nashenski_" in the shipping method ID).
	 */
	public static function get_logos() {
		$dir = nashenski()->plugin_dir_path() . 'assets/images/couriers/';
		$url = nashenski()->plugin_dir_url() . 'assets/images/couriers/';
		$logos = array();

		foreach ( \Nashenski\Plugin::COURIERS as $courier ) {
			foreach ( array( 'svg', 'png', 'webp' ) as $ext ) {
				if ( file_exists( $dir . $courier . '.' . $ext ) ) {
					$logos[ $courier ] = $url . $courier . '.' . $ext;
					break;
				}
			}
		}

		return apply_filters( 'nashenski/courier_logos', $logos );
	}

	/**
	 * Cart and checkout pages, including Cart and Checkout blocks placed on any page.
	 */
	public static function is_cart_or_checkout() {
		if ( is_cart() || is_checkout() ) {
			return true;
		}

		return is_singular() && ( has_block( 'woocommerce/checkout' ) || has_block( 'woocommerce/cart' ) );
	}
}
