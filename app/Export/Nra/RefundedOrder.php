<?php
namespace Nashenski\Export\Nra;

defined( 'ABSPATH' ) || exit;

class RefundedOrder {
	public $return_methods, $woo_order, $export_date;

	function __construct( $woo_order, $export_date ) {
		$this->woo_order   = $woo_order;
		$this->export_date = $export_date;
	}

	public function get_order_data() {
		$total       = 0;
		$refund_date = '';
		$refunds     = $this->woo_order->get_refunds();

		foreach ( $refunds as $refund ) {
			if ( wp_date( 'Y-m', $refund->get_date_created()->getTimestamp() ) === $this->export_date ) {
				$refund_date = $refund->get_date_created()->format( 'Y-m-d' );
				$total      += apply_filters( 'nashenski/admin/export/refunded_total', $refund->get_amount(), $refund );
			}
		}

		return array(
			'order_id'      => apply_filters( 'nashenski/admin/export/refunded_order_id', $this->woo_order->get_order_number(), $this->woo_order ),
			'total'         => abs( $total ),
			'date'          => $refund_date,
			'return_method' => nashenski_get_choice( 'shop', 'return_method', nashenski_get_return_methods() ),
		);
	}
}
