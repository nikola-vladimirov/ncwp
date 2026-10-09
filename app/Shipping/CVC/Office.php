<?php
namespace Nashenski\Shipping\CVC;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

class Office {
	private static $container = null;
	
	public function __construct() {

		add_action( 'wp_ajax_nashenski_cvc_load_offices', array( __CLASS__, 'load_offices' ) );
		add_action( 'wp_ajax_nopriv_nashenski_cvc_load_offices', array( __CLASS__, 'load_offices' ) );
	}

	public static function load_offices() {
		nashenski_check_picker_request();

		self::$container = nashenski()->container();
		$args = [];
		$country = nashenski_picker_request( 'country' );
		$country_id = self::$container[ Client::CVC_COUNTRIES ]->get_country_id( $country );
		$args[ 'offices' ] = apply_filters( 'nashenski/shipping_method/cvc/offices', self::$container[ Client::CVC_OFFICES ]->get_all_offices( $country_id ) );
		$args[ 'status' ] = 'valid-city';

		wp_send_json_success( $args );

		wp_die();
	}
}
