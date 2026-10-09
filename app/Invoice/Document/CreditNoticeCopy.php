<?php
namespace Nashenski\Invoice\Document;

defined( 'ABSPATH' ) || exit;

/**
 * The credit notice copy as its own PDF (when copies are kept as separate documents).
 */
class CreditNoticeCopy extends CreditNotice {
	function __construct( $refund_id ) {
		parent::__construct( $refund_id );

		$this->set_title( apply_filters( 'nashenski/admin/invoice/credit_notice_title_copy', __( 'Credit Notice - Copy', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_refunded_invoice_copy_document';
	}

	protected function build_file() {
		$this->save_pdf( $this->pdf->generate() );
	}

	public function get_document_number() {
		return $this->woo_order->get_meta( $this->document_number_meta );
	}

	public function get_document_date() {
		return $this->woo_order->get_meta( 'nashenski_credit_notice_document_date' ) ?: $this->woo_order->get_date_created();
	}
}
