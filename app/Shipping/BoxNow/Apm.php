<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloader maps the class name to the file name.
/**
 * BOX NOW parcel locker list for the checkout.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\BoxNow;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the BOX NOW parcel lockers to the checkout picker.
 */
class Apm {
	/**
	 * Plugin service container.
	 *
	 * @var \Pimple\Container|null
	 */
	private static $container = null;

	/**
	 * Hooks the AJAX action.
	 */
	public function __construct() {

		add_action( 'wp_ajax_nashenski_boxnow_load_apms', array( __CLASS__, 'load_apms' ) );
		add_action( 'wp_ajax_nopriv_nashenski_boxnow_load_apms', array( __CLASS__, 'load_apms' ) );
	}

	/**
	 * AJAX: parcel lockers of the chosen country that fit the cart.
	 */
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

		$args['apms'] = apply_filters( 'nashenski/shipping_method/boxnow/apms', $apms ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it would break existing callbacks.

		wp_send_json_success( $args );

		wp_die();
	}
}
