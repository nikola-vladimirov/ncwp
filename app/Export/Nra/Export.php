<?php
namespace Nashenski\Export\Nra;

defined( 'ABSPATH' ) || exit;

class Export {
	public $payment_methods, $not_included_orders, $completed_orders_ids, $refunded_orders_ids, $generate_files, $date, $xml_shop, $xml_orders;

	function __construct( $date, $generate_files ) {
		$this->payment_methods = nashenski_get_payment_types_for_meta();
		$this->generate_files = $generate_files;
		$this->date = $date;
		$this->load_woo_orders();
	}

	protected function load_woo_orders() {
		$timespan = strtotime( 'first day of ' . $this->date . ' ' . wp_timezone_string() ) . '...' . strtotime( 'last day of ' . $this->date . ' 23:59:59 ' . wp_timezone_string() );

		$order_args = array(
			'date_paid' => $timespan,
			'status' => apply_filters( 'nashenski/admin/export/orders_statuses', array( 'wc-completed', 'wc-processing' ) ),
		);
		$refunded_order_args = array(
			'date_created' => $timespan,
			'status' => apply_filters( 'nashenski/admin/export/refunded_orders_statuses', array( 'wc-completed', 'wc-refunded' ) ),
		);

		$this->completed_orders_ids = array();
		$this->refunded_orders_ids = array();

		$this->walk_orders( $order_args, 'orders', function( $order ) {
			if ( 
				is_a( $order, 'Automattic\WooCommerce\Admin\Overrides\OrderRefund' ) || 
				is_a( $order, 'WC_Order_Refund' )
			) {
				return;
			} else {
				$this->completed_orders_ids[] = $order->get_id();
			}
		} );

		$this->walk_orders( $refunded_order_args, 'refunded', function( $order ) {
			if ( 
				is_a( $order, 'Automattic\WooCommerce\Admin\Overrides\OrderRefund' ) || 
				is_a( $order, 'WC_Order_Refund' )
			) {
				if ( $order->get_parent_id() ) {
					$parent_order = wc_get_order( $order->get_parent_id() );
				} else {
					$parent_order = $order;
				}

				$this->refunded_orders_ids[] = $parent_order->get_id();

				if ( $parent_order->get_date_paid() && date_i18n( 'Y-m', strtotime( $parent_order->get_date_paid()->__toString() ) ) === $this->date  ) {
					$this->completed_orders_ids[] = $parent_order->get_id();
				}
			}
		} );

		$this->refunded_orders_ids = array_reverse( array_unique( $this->refunded_orders_ids ) );
		$this->completed_orders_ids = array_reverse( array_unique( $this->completed_orders_ids ) );
	}

	/**
	 * Orders of the month after the "nashenski/admin/export/orders" or "nashenski/admin/export/refunded_orders" filter.
	 */
	protected function filtered_orders( $args, $type ) {
		$orders = wc_get_orders( array_merge( $args, array( 'limit' => -1 ) ) );

		if ( 'refunded' === $type ) {
			return apply_filters( 'nashenski/admin/export/refunded_orders', $orders, $this );
		}

		return apply_filters( 'nashenski/admin/export/orders', $orders, $this );
	}

	protected function walk_orders( $args, $type, $callback ) {
		if ( has_filter( 'refunded' === $type ? 'nashenski/admin/export/refunded_orders' : 'nashenski/admin/export/orders' ) ) {
			$orders = $this->filtered_orders( $args, $type );

			foreach ( $orders as $order ) {
				$callback( $order );
			}

			return;
		}

		$page = 1;
		$limit = (int) apply_filters( 'nashenski/admin/export/query_batch_size', 100, $this );
		$limit = max( 1, $limit );

		do {
			$order_ids = wc_get_orders( array_merge( $args, array(
				'limit'  => $limit,
				'page'   => $page,
				'return' => 'ids',
			) ) );

			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );

				if ( $order ) {
					$callback( $order );
				}

				unset( $order );
			}

			$this->flush_runtime_cache();
			$page++;
		} while ( count( $order_ids ) === $limit );
	}

	protected function flush_runtime_cache() {
		wp_cache_flush_runtime();
	}

	protected function get_shop_data() {
		return [
			'eik' => nashenski_get_option( 'nap', 'eik' ) ?: '',
			'nap_number' => nashenski_get_option( 'nap', 'nap_number' ) ?: '',
			'domain' => nashenski_get_option( 'nap', 'domain' ) ?: '',
			'year' => gmdate('Y', strtotime( $this->date ) ), 
			'month' => gmdate('m', strtotime( $this->date ) )
		];
	}

	public function get_xml_file() {
		if ( empty( $this->completed_orders_ids ) && empty( $this->refunded_orders_ids ) ) {
			return;
		}

		$args = [
			'tax_rounding_mode' => wc_get_tax_rounding_mode(),
			'shop' => $this->get_shop_data(),
			'orders' => [],
			'refunded_orders' => [],
		];

		foreach ( $this->completed_orders_ids as $index => $order_id ) {
			$woo_order = wc_get_order( $order_id );

			if ( ! $woo_order ) {
				continue;
			}

			$order = new Order( $woo_order, $this->generate_files );

			if ( ! $order->payment_method_type ) {
				$this->not_included_orders[] = $order->order_id_to_show;
				unset( $order, $woo_order );
				$this->maybe_flush_runtime_cache( $index );
				continue;
			}

			$order_data = $order->get_order_data();

			if ( empty( $order_data['items'] ) ) {
				unset( $order, $woo_order, $order_data );
				$this->maybe_flush_runtime_cache( $index );
				continue;
			}
			
			$args['orders'][] = $order_data;

			unset( $order, $woo_order, $order_data );

			$this->maybe_flush_runtime_cache( $index );
		}

		foreach ( $this->refunded_orders_ids as $index => $order_id ) {
			$order = wc_get_order( $order_id );
			
			if ( $order && method_exists( $order, 'get_refunds' ) ) {
				$refunded_order = new RefundedOrder( $order, $this->date );

				$args['refunded_orders'][] = $refunded_order->get_order_data();
			}

			unset( $order, $refunded_order );

			$this->maybe_flush_runtime_cache( $index );
		}

		return $this->upload_xml( $args );
	}

	protected function maybe_flush_runtime_cache( $index ) {
		if ( 0 === ( ( $index + 1 ) % 100 ) ) {
			$this->flush_runtime_cache();
		}
	}

	protected function upload_xml( $args ) {
		$data = self::generate_xml_file( $args );
		$errors = '';
		$totals = '';

		if ( !empty( $data['content'] ) ) {
			$attach_id = \Nashenski\Document_Storage::save( uniqid( wp_rand(), true ), 'xml', $data['content'] );

			if ( ! $attach_id ) {
				return;
			}

			if( !empty( $data['totals'] ) ) {
				$totals  = sprintf( 
					/* translators: 1: total, 2: VAT, 3: refunded total */
					__( 'Total: %1$s | Total vat: %2$s | Returned Total: %3$s', 'nashenski-commerce' ), 
					wc_price( $data['totals']['orders_total'] ), 
					wc_price( $data['totals']['orders_total_vat'] ),
					wc_price( $data['totals']['returned_orders_total'] ) 
				);
			}

			$errors = $this->get_file_errors( $attach_id );
		} else {
			$errors = $data['error'] ?? 'Unknown error';
		}

		return array(
			'file' => ( isset($attach_id) ) ? \Nashenski\Document_Storage::export_url( $attach_id ) : '',
			'attachment_id' => isset( $attach_id ) ? $attach_id : 0,
			'not_included_orders' => $this->not_included_orders,
			'totals' => $totals,
			'errors' => $errors,
		);
	}

	protected function get_file_errors( $attach_id ) {
		if ( !empty( $this->completed_orders_ids ) || !empty( $this->refunded_orders_ids ) ) {
			libxml_use_internal_errors(true);
			$doc = new \DOMDocument();
			$doc->load( get_attached_file( $attach_id ) );

			if ( !$doc->schemaValidate( __DIR__ . '/dex_audit.xsd' ) ) {
				return wp_list_pluck( libxml_get_errors(), 'message' );
			}
		}
	}

	protected function generate_xml_file( $args ) {
		// Built on the site, no order limit.
		if ( ! class_exists( '\XMLWriter' ) ) {
			return [ 'error' => __( 'The PHP XMLWriter extension is required to create the NRA XML file.', 'nashenski-commerce' ) ];
		}

		return XmlBuilder::build( $args );
	}
}
