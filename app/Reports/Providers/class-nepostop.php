<?php
/**
 * Nepostop.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Reports\Providers;

defined( 'ABSPATH' ) || exit;

use Nashenski\Container\Client;

/**
 * Nepo stop.
 */
class NepoStop extends Provider_Base {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_name( __( 'NepoStop', 'nashenski-commerce' ) );
	}

	/**
	 * Get all reports.
	 *
	 * @param mixed $order Order.
	 * @param bool  $force Force.
	 * @return mixed
	 */
	public static function get_all_reports( $order, $force = false ) {
		$container = nashenski()->container();
		$phone     = ( $order->get_shipping_phone() ) ? $order->get_shipping_phone() : $order->get_billing_phone();
		$meta_key  = 'nashenski_reports-nepostop_phone_' . md5( $phone );
		$meta      = $order->get_meta( $meta_key );
		$treshold  = nashenski_get_option( 'nepostop', 'level_of_warning' );

		$args = array(
			'reports' => array(),
		);

		if ( $meta && ! $force ) {
			$result = $meta;
		} else {
			$result = $container[ Client::NEPOSTOP ]->api_call( $container[ Client::NEPOSTOP ]::CHECK_ENDPOINT, array( 'phone_number' => nashenski_format_phone( $phone ) ) );

			$order->update_meta_data( $meta_key, $result );
			$order->save();
		}

		$args['result'] = $result;

		if ( isset( $result['found'], $result['stats']['undelivered_percentage'] ) && ( (float) $result['stats']['undelivered_percentage'] >= (float) $treshold ) ) {
			/* translators: %s: percentage */
			$args['reports'] = array( sprintf( __( 'This client has %s%% undelivered shipments.', 'nashenski-commerce' ), $result['stats']['undelivered_percentage'] ) );
		}

		return $args;
	}

	/**
	 * Get all reports from meta.
	 *
	 * @param mixed $order Order.
	 * @return mixed
	 */
	public static function get_all_reports_from_meta( $order ) {
		$phone    = ( $order->get_shipping_phone() ) ? $order->get_shipping_phone() : $order->get_billing_phone();
		$meta_key = 'nashenski_reports-nepostop_phone_' . md5( $phone );
		$args     = array( 'reports' => array() );
		$result   = $order->get_meta( $meta_key );
		$treshold = nashenski_get_option( 'nepostop', 'level_of_warning' );

		if ( ! empty( $result ) && isset( $result['found'], $result['stats']['undelivered_percentage'] ) && $result['found'] && ( (float) $result['stats']['undelivered_percentage'] >= (float) $treshold ) ) {
			/* translators: %s: percentage */
			$args['reports'] = array( sprintf( __( 'This client has %s%% undelivered shipments.', 'nashenski-commerce' ), $result['stats']['undelivered_percentage'] ) );
		}

		return $args;
	}

	/**
	 * Print reports html.
	 *
	 * @param mixed $order Order.
	 */
	public function print_reports_html( $order ) {
		$reports     = self::get_all_reports( $order );
		$description = '';
		$treshold    = nashenski_get_option( 'nepostop', 'level_of_warning' );

		if ( isset( $reports['result']['found'], $reports['result']['stats']['undelivered_percentage'] ) && ( (float) $reports['result']['stats']['undelivered_percentage'] >= (float) $treshold ) ) {
			/* translators: %s: percentage */
			$title = sprintf( __( 'This client has %s%% undelivered shipments.', 'nashenski-commerce' ), $reports['result']['stats']['undelivered_percentage'] );
		} elseif ( ! isset( $reports['result']['found'], $reports['result']['stats']['undelivered_percentage'] ) || ! $reports['result']['found'] ) {
			$title = __( 'No data found for this phone number.', 'nashenski-commerce' );
		} else {
			/* translators: %s: percentage */
			$title = sprintf( __( 'This client has %s%% undelivered shipments, which is below the set threshold.', 'nashenski-commerce' ), $reports['result']['stats']['undelivered_percentage'] );
		}

		if ( isset( $reports['result']['found'], $reports['result']['stats']['total'] ) ) {
			/* translators: %s: number of shipments */
			$description = sprintf( __( 'Total shipments: %s', 'nashenski-commerce' ), $reports['result']['stats']['total'] );
			/* translators: 1: number of shipments, 2: percentage */
			$description .= '<br>' . sprintf( __( 'Delivered shipments: %1$s ( %2$s%% )', 'nashenski-commerce' ), $reports['result']['stats']['delivered'], $reports['result']['stats']['delivered_percentage'] );
			/* translators: 1: number of shipments, 2: percentage */
			$description .= '<br>' . sprintf( __( 'Undelivered shipments: %1$s ( %2$s%% )', 'nashenski-commerce' ), $reports['result']['stats']['undelivered'], $reports['result']['stats']['undelivered_percentage'] );
		}
		?>
		<div class="order_data_column_container">
			<h4><?php echo esc_html( $title ); ?></h4>

			<?php
			if ( $description ) {
				echo wp_kses_post( wpautop( $description ) );
			}
			?>
		</div><!-- /.order_data_column_container -->
		<?php
	}

	/**
	 * Check for reports on checkout.
	 *
	 * @param mixed $phone Phone.
	 * @return mixed
	 */
	public static function check_for_reports_on_checkout( $phone ) {
		$container = nashenski()->container();
		$result    = $container[ Client::NEPOSTOP ]->api_call( $container[ Client::NEPOSTOP ]::CHECK_ENDPOINT, array( 'phone_number' => nashenski_format_phone( $phone ) ) );
		$treshold  = nashenski_get_option( 'nepostop', 'level_of_warning' );
		$reports   = array();

		if ( $result['found'] && ( $result['stats']['undelivered_percentage'] >= $treshold ) ) {
			/* translators: %s: percentage */
			$reports = array( sprintf( __( 'This client has %s%% undelivered shipments.', 'nashenski-commerce' ), $result['stats']['undelivered_percentage'] ) );
		}

		return $reports;
	}
}