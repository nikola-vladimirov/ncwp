<?php
namespace Nashenski\Invoice\Document;

use Nashenski\Invoice\Order\Order;
use Automattic\WooCommerce\Admin\Overrides\OrderRefund;

defined( 'ABSPATH' ) || exit;

class NRARefunded extends BaseDocument {
	public $title, $parent_order, $return_methods, $return_method;

	function __construct( $refund_id ) {
		$order = new OrderRefund( $refund_id );
		parent::__construct( $order );
		$this->parent_order   = wc_get_order( $this->woo_order->get_parent_id() );
		$this->order          = new Order( $order );
		$this->return_methods = nashenski_get_return_methods();
		$this->return_method  = nashenski_get_option( 'shop', 'return_method' );
		$this->payment_method = $this->return_methods[ $this->return_method ]['label'];

		$this->set_title( apply_filters( 'nashenski/admin/invoice/order_document_title_refunded', __( 'Refunded Order - Original', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_refunded_order_document';

		$this->document_number_meta = 'nashenski_refunded_order_number';
	}

	public function get_head_items() {
		return apply_filters(
			'nashenski/invoice/head_items',
			array(
				'reference'    => array(
					'label' => '№',
					'value' => str_pad( $this->get_document_number(), 10, '0', STR_PAD_LEFT ),
				),
				'date'         => array(
					'label' => __( 'Order date', 'nashenski-commerce' ),
					'value' => $this->format_date( $this->woo_order->get_date_created() ),
				),
				'date-of-tax'  => array(
					'label' => __( 'Date of tax. event', 'nashenski-commerce' ),
					'value' => $this->format_date( $this->woo_order->get_date_created() ),
				),
				'due-date'     => array(
					'label' => __( 'Due date', 'nashenski-commerce' ),
					'value' => $this->format_date( $this->woo_order->get_date_created() ),
				),
				'address'      => array(
					'label' => __( 'Place of transaction', 'nashenski-commerce' ),
					'value' => $this->city,
				),
				'order-number' => array(
					'label' => __( 'Order Number', 'nashenski-commerce' ),
					'value' => $this->parent_order->get_order_number(),
				),
			),
			$this
		);
	}
}
