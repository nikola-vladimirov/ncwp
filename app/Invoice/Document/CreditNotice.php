<?php
namespace Nashenski\Invoice\Document;
use Nashenski\Invoice\Order\Order;
use \Automattic\WooCommerce\Admin\Overrides\OrderRefund;

defined( 'ABSPATH' ) || exit;

class CreditNotice extends NRARefunded {
	public $title, $parent_order, $return_methods, $return_method;

	function __construct( $refund_id ) {
		parent::__construct( $refund_id );

		$this->set_title( apply_filters( 'nashenski/admin/invoice/credit_notice_title', __( 'Credit Notice - Original', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_refunded_invoice_document';
		
		if ( nashenski_get_option( 'invoice', 'next_invoice_separate_number' ) ) {
			$this->document_number_meta = 'nashenski_order_invoice_number';
			$this->document_number_option = 'next_invoice_separate_number';
		}
	}

	public function get_head_items() {
		return apply_filters( 'nashenski/invoice/head_items', array(
			'parent_reference' => array(
				'label' => __('To invoice №', 'nashenski-commerce'),
				'value' => $this->get_parent_reference(),
			),
			'reference' => array(
				'label' => '№',
				'value' => str_pad( $this->get_document_number(), 10, '0', STR_PAD_LEFT ),
			),
			'date' => array(
				'label' => __( 'Order date', 'nashenski-commerce' ),
				'value' => $this->format_date( $this->woo_order->get_date_created() ),
			),
			'date-of-tax' => array(
				'label' => __( 'Date of tax. event', 'nashenski-commerce' ),
				'value' => $this->format_date( $this->woo_order->get_date_created() ),
			),
			'due-date' => array(
				'label' => __( 'Due date', 'nashenski-commerce' ),
				'value' => $this->format_date( $this->woo_order->get_date_created() ),
			),
			'address' => array(
				'label' => __( 'Place of transaction', 'nashenski-commerce' ),
				'value' => $this->city,
			),
			'order-number' => array(
				'label' => __( 'Order Number', 'nashenski-commerce' ),
				'value' => $this->parent_order->get_order_number(),
			),
		), $this );
	}

	protected function build_file() {
		$this->generate_original_and_copy( apply_filters( 'nashenski/admin/invoice/credit_notice_title_copy', __( 'Credit Notice - Copy', 'nashenski-commerce' ) ) );
	}

	public function get_parent_reference() {
		// The invoice's own number (separate numbering or typed in), else the shared order number.
		$number = $this->parent_order->get_meta( 'nashenski_order_invoice_number' ) ?: $this->parent_order->get_meta( 'nashenski_order_number' );

		return str_pad( $number, 10, '0', STR_PAD_LEFT );
	}

	public function get_document_date() {
		$date = $this->woo_order->get_meta( 'nashenski_credit_notice_document_date' );

		if ( !$date ) {
			$date = current_time( 'Y-m-d' );
			$this->woo_order->update_meta_data( 'nashenski_credit_notice_document_date', $date );
		}

		return $date;
	}
}