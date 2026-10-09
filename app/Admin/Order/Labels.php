<?php
namespace Nashenski\Admin\Order;

use Nashenski\Admin\Speedy;
use Nashenski\Admin\Econt;
use Nashenski\Admin\Pigeon;
use Nashenski\Admin\CVC;
use Nashenski\Admin\BoxNow;
use Nashenski\Admin\Admin_Menus;

defined( 'ABSPATH' ) || exit;

class Labels {
	public static function generate_label_from_listing_page_callback() {
		nashenski_check_admin_label_actions();

		$order_id = nashenski_label_request( 'orderId' );
		$method = nashenski_label_request( 'method' );
		$order = wc_get_order( $order_id );

		switch ( $method ) {
			case 'nashenski_speedy':
				Speedy::generate_label_after_order_generated( $order_id );

				break;
			case 'nashenski_econt':
				Econt::generate_label_after_order_generated( $order_id );

				break;
			case 'nashenski_cvc':
				CVC::generate_label_after_order_generated( $order_id );

				break;
			case 'nashenski_boxnow':
				BoxNow::generate_label_after_order_generated( $order_id );

				break;
			case 'nashenski_pigeon':
				Pigeon::generate_label_after_order_generated( $order_id );

				break;
		}

		$data = nashenski_get_order_label( $order_id );
		$html = '';

		if ( !empty( $data ) ) {
			ob_start();
			Admin_Menus::render_fragment( 'label-column', [ 
				'data' => $data,
				'order' => $order,
			] );
			$html = ob_get_clean();
		}

		wp_send_json( [ 'html' => $html ] );
		wp_die;
	}
}
