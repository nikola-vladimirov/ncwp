<?php
/**
 * Sale invoice document.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Invoice\Document;

defined( 'ABSPATH' ) || exit;

/**
 * The sale invoice of an order, original and copy.
 */
class Invoice extends BaseDocument {
	/**
	 * Document title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Sets up the invoice.
	 *
	 * @param \WC_Order $order The order.
	 */
	public function __construct( $order ) {
		parent::__construct( $order );

		$this->set_title( \Nashenski\Hooks::filter( 'nashenski_admin_invoice_invoice_title', 'nashenski/admin/invoice/invoice_title', __( 'Sale Invoice - Original', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_invoice_document';

		// Separate invoice numbering, or a number typed in when the invoice was made by hand.
		if ( nashenski_get_option( 'invoice', 'next_invoice_separate_number' ) || $this->woo_order->get_meta( 'nashenski_order_invoice_number' ) ) {
			$this->document_number_meta   = 'nashenski_order_invoice_number';
			$this->document_number_option = 'next_invoice_separate_number';
		}
	}

	/**
	 * The first invoice of the order decides whether the order stays out of the NRA audit file
	 * ("How to issue invoices"); saved with the number or the file.
	 */
	public function generate_file() {
		\Nashenski\Invoice\Mode::mark( $this->woo_order );

		parent::generate_file();
	}

	/**
	 * Builds the PDF with the original and the copy.
	 */
	protected function build_file() {
		$this->generate_original_and_copy( \Nashenski\Hooks::filter( 'nashenski_admin_invoice_invoice_title_copy', 'nashenski/admin/invoice/invoice_title_copy', __( 'Sale Invoice - Copy', 'nashenski-commerce' ) ) );
	}

	/**
	 * Invoice date, saved on first use.
	 *
	 * @return string Date as d-m-Y.
	 */
	public function get_document_date() {
		$date = $this->woo_order->get_meta( 'nashenski_invoice_document_date' );

		if ( ! $date ) {
			$date = current_time( 'd-m-Y' );
			$this->woo_order->update_meta_data( 'nashenski_invoice_document_date', $date );
		}

		return $date;
	}

	/**
	 * Due date: the invoice date plus the due days setting.
	 *
	 * @return string Date as d-m-Y.
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
