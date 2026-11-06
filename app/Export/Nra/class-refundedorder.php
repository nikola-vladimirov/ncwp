<?php
/**
 * Refunded order row of the NRA export.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Export\Nra;

defined( 'ABSPATH' ) || exit;

/**
 * The refunds of one order in the export month.
 */
class RefundedOrder {
	/**
	 * Return methods.
	 *
	 * @var mixed
	 */
	public $return_methods;

	/**
	 * The order.
	 *
	 * @var \WC_Order
	 */
	public $woo_order;

	/**
	 * Export month, Y-m.
	 *
	 * @var string
	 */
	public $export_date;

	/**
	 * Sets up the row.
	 *
	 * @param \WC_Order $woo_order   The order.
	 * @param string    $export_date Export month, Y-m.
	 */
	public function __construct( $woo_order, $export_date ) {
		$this->woo_order   = $woo_order;
		$this->export_date = $export_date;
	}

	/**
	 * Refund total, date and return method for the export month.
	 *
	 * @return array
	 */
	public function get_order_data() {
		$total       = 0;
		$refund_date = '';
		$refunds     = $this->woo_order->get_refunds();

		foreach ( $refunds as $refund ) {
			if ( wp_date( 'Y-m', $refund->get_date_created()->getTimestamp() ) === $this->export_date ) {
				$refund_date = $refund->get_date_created()->format( 'Y-m-d' );
				$total      += \Nashenski\Hooks::filter( 'nashenski_admin_export_refunded_total', 'nashenski/admin/export/refunded_total', $refund->get_amount(), $refund );
			}
		}

		return array(
			'order_id'      => \Nashenski\Hooks::filter( 'nashenski_admin_export_refunded_order_id', 'nashenski/admin/export/refunded_order_id', $this->woo_order->get_order_number(), $this->woo_order ),
			'total'         => abs( $total ),
			'date'          => $refund_date,
			'return_method' => nashenski_get_choice( 'shop', 'return_method', nashenski_get_return_methods() ),
		);
	}
}
