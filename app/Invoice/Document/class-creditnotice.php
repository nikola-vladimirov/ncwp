<?php
/**
 * Credit notice document.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Invoice\Document;

use Nashenski\Invoice\Order\Order;
use Automattic\WooCommerce\Admin\Overrides\OrderRefund;

defined( 'ABSPATH' ) || exit;

/**
 * Credit notice for a refund of an invoiced order.
 */
class CreditNotice extends NRARefunded {
	/**
	 * Document title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * The refunded order.
	 *
	 * @var \WC_Order|false
	 */
	public $parent_order;

	/**
	 * Return methods.
	 *
	 * @var array
	 */
	public $return_methods;

	/**
	 * Return method from the settings.
	 *
	 * @var string
	 */
	public $return_method;

	/**
	 * Sets up the credit notice.
	 *
	 * @param int $refund_id Refund ID.
	 */
	public function __construct( $refund_id ) {
		parent::__construct( $refund_id );

		$this->set_title( \Nashenski\Hooks::filter( 'nashenski_admin_invoice_credit_notice_title', 'nashenski/admin/invoice/credit_notice_title', __( 'Credit Notice - Original', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_refunded_invoice_document';

		if ( nashenski_get_option( 'invoice', 'next_invoice_separate_number' ) ) {
			$this->document_number_meta   = 'nashenski_order_invoice_number';
			$this->document_number_option = 'next_invoice_separate_number';
		}
	}

	/**
	 * Rows of the document head.
	 *
	 * @return array
	 */
	public function get_head_items() {
		return \Nashenski\Hooks::filter(
			'nashenski_invoice_head_items',
			'nashenski/invoice/head_items',
			array(
				'parent_reference' => array(
					'label' => __( 'To invoice №', 'nashenski-commerce' ),
					'value' => $this->get_parent_reference(),
				),
				'reference'        => array(
					'label' => '№',
					'value' => str_pad( $this->get_document_number(), 10, '0', STR_PAD_LEFT ),
				),
				'date'             => array(
					'label' => __( 'Order date', 'nashenski-commerce' ),
					'value' => $this->format_date( $this->woo_order->get_date_created() ),
				),
				'date-of-tax'      => array(
					'label' => __( 'Date of tax. event', 'nashenski-commerce' ),
					'value' => $this->format_date( $this->woo_order->get_date_created() ),
				),
				'due-date'         => array(
					'label' => __( 'Due date', 'nashenski-commerce' ),
					'value' => $this->format_date( $this->woo_order->get_date_created() ),
				),
				'address'          => array(
					'label' => __( 'Place of transaction', 'nashenski-commerce' ),
					'value' => $this->city,
				),
				'order-number'     => array(
					'label' => __( 'Order Number', 'nashenski-commerce' ),
					'value' => $this->parent_order->get_order_number(),
				),
			),
			$this
		);
	}

	/**
	 * Builds the original and the copy.
	 */
	protected function build_file() {
		$this->generate_original_and_copy( \Nashenski\Hooks::filter( 'nashenski_admin_invoice_credit_notice_title_copy', 'nashenski/admin/invoice/credit_notice_title_copy', __( 'Credit Notice - Copy', 'nashenski-commerce' ) ) );
	}

	/**
	 * Number of the invoice this credit notice is for.
	 *
	 * @return string
	 */
	public function get_parent_reference() {
		// The invoice's own number (separate numbering or typed in), else the shared order number.
		$number = $this->parent_order->get_meta( 'nashenski_order_invoice_number' );

		if ( ! $number ) {
			$number = $this->parent_order->get_meta( 'nashenski_order_number' );
		}

		return str_pad( $number, 10, '0', STR_PAD_LEFT );
	}

	/**
	 * Date of the credit notice, saved the first time it is made.
	 *
	 * @return string
	 */
	public function get_document_date() {
		$date = $this->woo_order->get_meta( 'nashenski_credit_notice_document_date' );

		if ( ! $date ) {
			$date = current_time( 'Y-m-d' );
			$this->woo_order->update_meta_data( 'nashenski_credit_notice_document_date', $date );
		}

		return $date;
	}
}
