<?php
/**
 * Refunded order document.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Invoice\Document;

use Nashenski\Invoice\Order\Order;
use Automattic\WooCommerce\Admin\Overrides\OrderRefund;

defined( 'ABSPATH' ) || exit;

/**
 * Document for a refund (Ordinance N-18).
 */
class NRARefunded extends BaseDocument {
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
	 * Sets up the document.
	 *
	 * @param int $refund_id Refund ID.
	 */
	public function __construct( $refund_id ) {
		$order = new OrderRefund( $refund_id );
		parent::__construct( $order );
		$this->parent_order   = wc_get_order( $this->woo_order->get_parent_id() );
		$this->order          = new Order( $order );
		$this->return_methods = nashenski_get_return_methods();
		$this->return_method  = nashenski_get_option( 'shop', 'return_method' );
		$this->payment_method = $this->return_methods[ $this->return_method ]['label'] ?? '';

		$this->set_title( \Nashenski\Hooks::filter( 'nashenski_admin_invoice_order_document_title_refunded', 'nashenski/admin/invoice/order_document_title_refunded', __( 'Refunded Order - Original', 'nashenski-commerce' ) ) );
		$this->meta = 'nashenski_refunded_order_document';

		$this->document_number_meta = 'nashenski_refunded_order_number';
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
