<?php
namespace Nashenski\Invoice\Document;

use Nashenski\Image_Uploader;
use chillerlan\QRCode\QRCode;

defined( 'ABSPATH' ) || exit;

class NRA extends BaseDocument {
	function __construct( $order ) {
		parent::__construct( $order );

		$this->set_title( apply_filters( 'nashenski/admin/invoice/order_document_title', __( 'Order - Original', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_order_document';
		$this->generate_QR_code();
	}

	public function generate_QR_code() {
		$qr_code_pieces = array(
			$this->nap_number,
			$this->woo_order->get_order_number(),
			$this->woo_order->get_transaction_id(),
			$this->woo_order->get_date_created()->date_i18n('Y-m-d'),
			$this->woo_order->get_date_created()->date_i18n('G:i:s'),
			$this->woo_order->get_total(),
		);

		$qr_code = new QRCode();
		ob_start();
		?>
		<img src="<?php echo esc_attr( $qr_code->render( implode( '*', $qr_code_pieces ) ) ); ?>" alt="">
		<?php

		$this->qr = ob_get_clean();
	}
}