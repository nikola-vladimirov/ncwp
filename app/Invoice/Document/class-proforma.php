<?php
/**
 * Proforma.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Invoice\Document;

defined( 'ABSPATH' ) || exit;

/**
 * Proforma.
 */
class Proforma extends BaseDocument {
	/**
	 * Constructor.
	 *
	 * @param mixed $order Order.
	 */
	public function __construct( $order ) {
		parent::__construct( $order );

		$this->set_title( \Nashenski\Hooks::filter( 'nashenski_admin_invoice_order_document_title', 'nashenski/admin/invoice/order_document_title', __( 'Pro forma', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_proform_document';

		if ( nashenski_get_option( 'invoice', 'next_invoice_separate_number' ) ) {
			$this->document_number_meta   = 'nashenski_order_invoice_number';
			$this->document_number_option = 'next_invoice_separate_number';
		}
	}

	/**
	 * Get document number.
	 */
	public function get_document_number() {
		// A pro forma carries no document number.
	}
}
