<?php
namespace Nashenski\Invoice\Document;

defined( 'ABSPATH' ) || exit;

class Proforma extends BaseDocument {
	function __construct( $order ) {
		parent::__construct( $order );

		$this->set_title( apply_filters( 'nashenski/admin/invoice/order_document_title', __( 'Pro forma', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_proform_document';

		if ( nashenski_get_option( 'invoice', 'next_invoice_separate_number' ) ) {
			$this->document_number_meta = 'nashenski_order_invoice_number';
			$this->document_number_option = 'next_invoice_separate_number';
		}
	}

	public function get_document_number() {
		return;
	}
}