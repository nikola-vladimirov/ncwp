<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.
/**
 * Courier info.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Order;

use Nashenski\Front_End\Checkout\Pickers;

defined( 'ABSPATH' ) || exit;

/**
 * A small read-only box on every order screen: the courier, the delivery type and, when the checkout
 * saved it, the chosen office, locker or address. Orders without a courier say "No courier".
 */
class Courier_Info {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 20 );
	}

	/**
	 * Courier name, delivery type and place of an order, or an empty array when no shipping line is a courier.
	 *
	 * @param mixed $order Order.
	 */
	public static function summary( $order ) {
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			$courier = Pickers::courier_of( $item->get_method_id() );

			if ( ! $courier ) {
				continue;
			}

			// BOX NOW only has lockers and keeps no delivery type on the line.
			$type  = 'boxnow' === $courier ? 'apm' : (string) $item->get_meta( 'delivery_type' );
			$types = array(
				'office'  => __( 'To an office', 'nashenski-commerce' ),
				'automat' => __( 'To a parcel locker', 'nashenski-commerce' ),
				'locker'  => __( 'To a parcel locker', 'nashenski-commerce' ),
				'apm'     => __( 'To a parcel locker', 'nashenski-commerce' ),
				'address' => __( 'To an address', 'nashenski-commerce' ),
			);
			$names = nashenski_couriers();

			return array(
				'courier' => $names[ $courier ],
				'type'    => $types[ $type ] ?? '',
				'place'   => Pickers::describe_choice( $item->get_meta( 'cookie_data' ), $type ),
			);
		}

		return array();
	}

	/**
	 * Add meta box.
	 */
	public static function add_meta_box() {
		$order = Delivery_Box::order();

		// Refunds are not WC_Order objects.
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$screen = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		add_meta_box( 'nashenski_courier_info', __( 'Courier choice', 'nashenski-commerce' ), array( __CLASS__, 'render' ), $screen, 'side', 'high' );
	}

	/**
	 * Render.
	 */
	public static function render() {
		$order = Delivery_Box::order();
		$info  = $order instanceof \WC_Order ? self::summary( $order ) : array();

		if ( ! $info ) {
			echo '<p>' . esc_html__( 'No courier', 'nashenski-commerce' ) . '</p>';

			return;
		}

		echo '<p><strong>' . esc_html( $info['courier'] ) . '</strong>';

		foreach ( array( 'type', 'place' ) as $key ) {
			if ( '' !== $info[ $key ] ) {
				echo '<br>' . esc_html( $info[ $key ] );
			}
		}

		echo '</p>';
	}
}
