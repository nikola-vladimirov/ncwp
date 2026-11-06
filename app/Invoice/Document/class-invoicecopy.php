<?php
/**
 * Invoice copy document.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Invoice\Document;

defined( 'ABSPATH' ) || exit;

/**
 * Separate copy of the invoice, with the invoice's number and date.
 */
class InvoiceCopy extends BaseDocument {
	/**
	 * Document title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Sets up the copy.
	 *
	 * @param \WC_Order $order Order.
	 */
	public function __construct( $order ) {
		parent::__construct( $order );

		$this->set_title( \Nashenski\Hooks::filter( 'nashenski_admin_invoice_invoice_title_copy', 'nashenski/admin/invoice/invoice_title_copy', __( 'Sale Invoice - Copy', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_invoice_copy_document';

		// Separate invoice numbering, or a number typed in when the invoice was made by hand.
		if ( nashenski_get_option( 'invoice', 'next_invoice_separate_number' ) || $this->woo_order->get_meta( 'nashenski_order_invoice_number' ) ) {
			$this->document_number_meta   = 'nashenski_order_invoice_number';
			$this->document_number_option = 'next_invoice_separate_number';
		}
	}

	/**
	 * Number of the invoice.
	 *
	 * @return string
	 */
	public function get_document_number() {
		return $this->woo_order->get_meta( $this->document_number_meta );
	}

	/**
	 * Date of the invoice, else the order date.
	 *
	 * @return mixed
	 */
	public function get_document_date() {
		$date = $this->woo_order->get_meta( 'nashenski_invoice_document_date' );

		return $date ? $date : $this->woo_order->get_date_created();
	}

	/**
	 * Due date: the document date plus the due days from the settings.
	 *
	 * @return mixed
	 */
	public function get_document_due_date() {
		$date     = $this->get_document_date();
		$due_days = nashenski_get_option( 'invoice', 'due_days' );

		if ( $due_days ) {
			$date = date_i18n( 'd-m-Y', strtotime( $date . ' + ' . $due_days . ' days' ) );
		}

		return $date;
	}
}
