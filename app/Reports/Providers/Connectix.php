<?php
/**
 * Connectix customer check.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the file name to match the class name.

namespace Nashenski\Reports\Providers;

defined( 'ABSPATH' ) || exit;

use Nashenski\Container\Client;

/**
 * Connectix: the chance that the customer will not pick up the shipment.
 */
class Connectix extends Provider_Base {
	/**
	 * Set the provider name.
	 */
	public function __construct() {
		$this->set_name( __( 'Connectix', 'nashenski-commerce' ) );
	}

	/**
	 * The order's check, asked from Connectix unless saved on the order.
	 *
	 * @param \WC_Order $order Order.
	 * @param bool      $force Ask Connectix again.
	 * @return array
	 */
	public static function get_all_reports( $order, $force = false ) {
		$container = nashenski()->container();
		$phone     = ( $order->get_shipping_phone() ) ? $order->get_shipping_phone() : $order->get_billing_phone();
		$meta_key  = 'nashenski_reports-connectix_phone_' . md5( $phone );
		$meta      = $order->get_meta( $meta_key );
		$levels    = $container[ Client::CONNECTIX ]::get_levels_of_probability();
		$args      = array(
			'reports' => array(),
			'levels'  => $levels,
		);

		if ( $meta && ! $force ) {
			$result = $meta;
		} else {
			$result = $container[ Client::CONNECTIX ]->api_call( $container[ Client::CONNECTIX ]::CHECK_ENDPOINT, array( 'phone' => $phone ) );

			$order->update_meta_data( $meta_key, $result );
			$order->save();
		}

		$args['result'] = $result;
		$result_index   = array_search( $result, array_column( $levels, 'key' ), true );

		if ( false !== $result_index ) {
			$option_index = array_search( nashenski_get_option( 'connectix', 'level_of_warning' ), array_column( $levels, 'key' ), true );

			if ( false !== $option_index && $result_index >= $option_index ) {
				/* translators: %s: level, for example High */
				$args['reports'] = array( sprintf( __( 'Possibility that the shipment will not be picked up: %s', 'nashenski-commerce' ), $levels[ $result_index ]['label'] ) );
			}
		} else {
			if ( is_array( $result ) && isset( $result['message'] ) ) {
				$result = $result['message'];

			}

			if ( ! is_string( $result ) || '' === trim( $result ) ) {
				$result = __( 'Connectix did not answer. Check the token and the environment in Customer checks.', 'nashenski-commerce' );
			}

			/* translators: %s: error message */
			$args['error'] = sprintf( __( 'Error: %s', 'nashenski-commerce' ), $result );
		}

		return $args;
	}

	/**
	 * The order's saved check.
	 *
	 * @param \WC_Order $order Order.
	 * @return array
	 */
	public static function get_all_reports_from_meta( $order ) {
		$container    = nashenski()->container();
		$levels       = $container[ Client::CONNECTIX ]::get_levels_of_probability();
		$phone        = ( $order->get_shipping_phone() ) ? $order->get_shipping_phone() : $order->get_billing_phone();
		$meta_key     = 'nashenski_reports-connectix_phone_' . md5( $phone );
		$args         = array( 'reports' => array() );
		$result       = $order->get_meta( $meta_key );
		$result_index = array_search( $result, array_column( $levels, 'key' ), true );

		if ( false !== $result_index ) {
			$option_index = array_search( nashenski_get_option( 'connectix', 'level_of_warning' ), array_column( $levels, 'key' ), true );

			if ( false !== $option_index && $result_index >= $option_index ) {
				/* translators: %s: level, for example High */
				$args['reports'] = array( sprintf( __( 'Possibility that the shipment will not be picked up: %s', 'nashenski-commerce' ), $levels[ $result_index ]['label'] ) );
			}
		}

		return $args;
	}

	/**
	 * Print the order's check on the order screen.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function print_reports_html( $order ) {
		$reports      = self::get_all_reports( $order );
		$result_index = array_search( $reports['result'], array_column( $reports['levels'], 'key' ), true );

		if ( false !== $result_index ) {
			/* translators: %s: level, for example High */
			$title = sprintf( esc_html__( 'Possibility that the shipment will not be picked up: %s', 'nashenski-commerce' ), $reports['levels'][ $result_index ]['label'] );
		} else {
			$title = $reports['error'];
		}
		?>
		<div class="order_data_column_container">
			<h4><?php echo esc_html( $title ); ?></h4>
		</div><!-- /.order_data_column_container -->
		<?php
	}

	/**
	 * Warnings for a phone number at checkout.
	 *
	 * @param string $phone Phone number.
	 * @return array
	 */
	public static function check_for_reports_on_checkout( $phone ) {
		$container    = nashenski()->container();
		$levels       = $container[ Client::CONNECTIX ]::get_levels_of_probability();
		$result       = $container[ Client::CONNECTIX ]->api_call( $container[ Client::CONNECTIX ]::CHECK_ENDPOINT, array( 'phone' => $phone ) );
		$result_index = array_search( $result, array_column( $levels, 'key' ), true );
		$reports      = array();

		if ( false !== $result_index ) {
			$option_index = array_search( nashenski_get_option( 'connectix', 'level_of_warning' ), array_column( $levels, 'key' ), true );

			if ( false !== $option_index && $result_index >= $option_index ) {
				/* translators: %s: level, for example High */
				$reports[] = sprintf( __( 'Possibility that the shipment will not be picked up: %s', 'nashenski-commerce' ), $levels[ $result_index ]['label'] );
			}
		}

		return $reports;
	}
}