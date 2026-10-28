<?php
namespace Nashenski\Shipping;

defined( 'ABSPATH' ) || exit;

class CheckoutLayout {
	public function __construct() {
		add_filter('woocommerce_shipping_package_name', array( __CLASS__, 'change_shipping_package_name' ), 10, 2);
	}

	public static function change_shipping_package_name( $name, $i ) {
		return __('Shipping', 'nashenski-commerce');
	}
}
