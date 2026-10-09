<?php
namespace Nashenski\Invoice\Document;

defined( 'ABSPATH' ) || exit;

class Invoice extends BaseDocument {
	public $title;

	function __construct( $order ) {
		parent::__construct( $order );

		$this->set_title( apply_filters( 'nashenski/admin/invoice/invoice_title', __( 'Sale Invoice - Original', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_invoice_document';

		// Separate invoice numbering, or a number typed in when the invoice was made by hand.
		if ( nashenski_get_option( 'invoice', 'next_invoice_separate_number' ) || $this->woo_order->get_meta( 'nashenski_order_invoice_number' ) ) {
			$this->document_number_meta = 'nashenski_order_invoice_number';
			$this->document_number_option = 'next_invoice_separate_number';
		}
	}

	protected function build_file() {
		$this->generate_original_and_copy( apply_filters( 'nashenski/admin/invoice/invoice_title_copy', __( 'Sale Invoice - Copy', 'nashenski-commerce' ) ) );
	}

	public function get_document_date() {
		$date = $this->woo_order->get_meta( 'nashenski_invoice_document_date' );

		if ( !$date ) {
			$date = date_i18n( 'd-m-Y', strtotime( 'today' ) );
			$this->woo_order->update_meta_data( 'nashenski_invoice_document_date', $date );
		}

		return $date;
	}

	public function get_document_due_date() {
		$date = $this->get_document_date();

		if ( $due_days = nashenski_get_option( 'invoice', 'due_days' ) ) {
			$date = date_i18n( 'd-m-Y', strtotime( $date . " + " . $due_days . " days" ) );
		}
		
		return $date;
	}
}