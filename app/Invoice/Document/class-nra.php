<?php
/**
 * The N-18 order document.
 *
 * @package Nashenski
 */

namespace Nashenski\Invoice\Document;

use Nashenski\Image_Uploader;
use chillerlan\QRCode\QRCode;

defined( 'ABSPATH' ) || exit;

/**
 * The order document of Ordinance N-18, with its QR code.
 */
class NRA extends BaseDocument {
	/**
	 * Set the title, the file meta key and the QR code.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function __construct( $order ) {
		parent::__construct( $order );

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks add-ons.
		$this->set_title( apply_filters( 'nashenski/admin/invoice/order_document_title', __( 'Order - Original', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_order_document';
		$this->generate_QR_code();
	}

	/**
	 * Make the QR code of the order (NRA number, order number, transaction, date, time, total).
	 */
	public function generate_QR_code() {
		$qr_code_pieces = array(
			$this->nap_number,
			$this->woo_order->get_order_number(),
			$this->woo_order->get_transaction_id(),
			$this->woo_order->get_date_created()->date_i18n( 'Y-m-d' ),
			$this->woo_order->get_date_created()->date_i18n( 'G:i:s' ),
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