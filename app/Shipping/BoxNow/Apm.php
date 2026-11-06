<?php
namespace Nashenski\Shipping\BoxNow;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

class Apm {
	private static $container = null;

	public function __construct() {

		add_action( 'wp_ajax_nashenski_boxnow_load_apms', array( __CLASS__, 'load_apms' ) );
		add_action( 'wp_ajax_nopriv_nashenski_boxnow_load_apms', array( __CLASS__, 'load_apms' ) );
	}

	public static function load_apms() {
		nashenski_check_picker_request();

		self::$container = nashenski()->container();
		$apm_size        = Method::get_allowed_apm_size();
		$apms            = self::$container[ Client::BOXNOW_DESTINATIONS ]->get_destinations( $apm_size['max_size'] );
		$country         = nashenski_picker_request( 'country', '' );

		// No list when BOX NOW is not connected or did not answer.
		if ( ! is_array( $apms ) ) {
			$apms = array();
		}

		$apms = array_filter(
			$apms,
			function ( $apm ) use ( $country ) {
				return ( $apm['country'] === $country );
			}
		);

		$apms = array_values( $apms );

		$args['apms'] = apply_filters( 'nashenski/shipping_method/boxnow/apms', $apms );

		wp_send_json_success( $args );

		wp_die();
	}
}
