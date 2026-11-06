<?php
/**
 * The credit notice copy as its own PDF (when copies are kept as separate documents).
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Invoice\Document;

defined( 'ABSPATH' ) || exit;

/**
 * The credit notice copy as its own PDF (when copies are kept as separate documents).
 */
class CreditNoticeCopy extends CreditNotice {
	/**
	 * Builds the copy of a credit notice.
	 *
	 * @param int $refund_id Refund ID.
	 */
	public function __construct( $refund_id ) {
		parent::__construct( $refund_id );

		$this->set_title( apply_filters( 'nashenski/admin/invoice/credit_notice_title_copy', __( 'Credit Notice - Copy', 'nashenski-commerce' ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-separated hook names are the plugin's public API.
		$this->meta = 'nashenski_refunded_invoice_copy_document';
	}

	/**
	 * Saves the PDF of the copy.
	 *
	 * @return void
	 */
	protected function build_file() {
		$this->save_pdf( $this->pdf->generate() );
	}

	/**
	 * The number of the credit notice (the copy has the same number).
	 *
	 * @return mixed
	 */
	public function get_document_number() {
		return $this->woo_order->get_meta( $this->document_number_meta );
	}

	/**
	 * The date of the credit notice, or when the refund was created if none is saved.
	 *
	 * @return mixed
	 */
	public function get_document_date() {
		$date = $this->woo_order->get_meta( 'nashenski_credit_notice_document_date' );

		return $date ? $date : $this->woo_order->get_date_created();
	}
}
