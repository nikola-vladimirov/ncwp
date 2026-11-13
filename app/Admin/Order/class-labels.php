<?php
/**
 * Labels.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Order;

use Nashenski\Admin\Speedy;
use Nashenski\Admin\Econt;
use Nashenski\Admin\Pigeon;
use Nashenski\Admin\BoxNow;
use Nashenski\Admin\Admin_Menus;

defined( 'ABSPATH' ) || exit;

/**
 * Labels.
 */
class Labels {
	/**
	 * Generate label from listing page callback.
	 */
	public static function generate_label_from_listing_page_callback() {
		// The order screen label tools: the nonce first, then the right to manage the store, each on its own.
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'nashenski_admin_label' ) ) {
			wp_send_json_error( array( 'message' => __( 'This page has expired. Reload it and try again.', 'nashenski-commerce' ) ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot create labels.', 'nashenski-commerce' ) ) );
		}

		$order    = nashenski_label_order();
		$order_id = $order->get_id();
		$method   = nashenski_label_request( 'method' );

		$result = null;

		switch ( $method ) {
			case 'nashenski_speedy':
				$result = Speedy::generate_label_after_order_generated( $order_id );

				break;
			case 'nashenski_econt':
				$result = Econt::generate_label_after_order_generated( $order_id );

				break;
			case 'nashenski_boxnow':
				$result = BoxNow::generate_label_after_order_generated( $order_id );

				break;
			case 'nashenski_pigeon':
				$result = Pigeon::generate_label_after_order_generated( $order_id );

				break;
		}

		$data = nashenski_get_order_label( $order_id );
		$html = '';

		if ( empty( $data['number'] ) && empty( $data['items'] ) ) {
			wp_send_json_error(
				array(
					'message' => ! empty( $result['message'] ) ? wp_strip_all_tags( $result['message'] ) : __( 'No label was made. Open the order and make the label in the courier box.', 'nashenski-commerce' ),
				)
			);
		}

		if ( ! empty( $data ) ) {
			ob_start();
			Admin_Menus::render_fragment(
				'label-column',
				array(
					'data'  => $data,
					'order' => $order,
				)
			);
			$html = ob_get_clean();
		}

		wp_send_json( array( 'html' => $html ) );
	}
}
