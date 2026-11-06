<?php
/**
 * The monthly NRA audit file: picks the orders and refunds of the month and builds the XML.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Export\Nra;

use Nashenski\Admin\Order\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * The monthly NRA audit file: picks the orders and refunds of the month and builds the XML.
 */
class Export {
	/**
	 * Payment method types for the NRA file.
	 *
	 * @var array
	 */
	public $payment_methods;

	/**
	 * Numbers of the orders left out because their payment method has no NRA type.
	 *
	 * @var array|null
	 */
	public $not_included_orders;

	/**
	 * IDs of the paid orders of the month.
	 *
	 * @var array
	 */
	public $completed_orders_ids;

	/**
	 * IDs of the orders refunded in the month.
	 *
	 * @var array
	 */
	public $refunded_orders_ids;

	/**
	 * Whether the order documents are generated.
	 *
	 * @var bool
	 */
	public $generate_files;

	/**
	 * Month of the file, as Y-m.
	 *
	 * @var string
	 */
	public $date;

	/**
	 * Shop part of the file.
	 *
	 * @var mixed
	 */
	public $xml_shop;

	/**
	 * Orders part of the file.
	 *
	 * @var mixed
	 */
	public $xml_orders;

	/**
	 * Picks the orders of the month.
	 *
	 * @param string $date           Month, as Y-m.
	 * @param bool   $generate_files Whether the order documents are generated.
	 */
	public function __construct( $date, $generate_files ) {
		$this->payment_methods = nashenski_get_payment_types_for_meta();
		$this->generate_files  = $generate_files;
		$this->date            = $date;
		$this->load_woo_orders();
	}

	/**
	 * Reads the paid and the refunded orders of the month into the ID lists.
	 *
	 * @return void
	 */
	protected function load_woo_orders() {
		$timespan = strtotime( 'first day of ' . $this->date . ' ' . wp_timezone_string() ) . '...' . strtotime( 'last day of ' . $this->date . ' 23:59:59 ' . wp_timezone_string() );

		$order_args          = array(
			'date_paid' => $timespan,
			'status'    => \Nashenski\Hooks::filter( 'nashenski_admin_export_orders_statuses', 'nashenski/admin/export/orders_statuses', array( 'wc-completed', 'wc-processing' ) ),
		);
		$refunded_order_args = array(
			'date_created' => $timespan,
			'status'       => \Nashenski\Hooks::filter( 'nashenski_admin_export_refunded_orders_statuses', 'nashenski/admin/export/refunded_orders_statuses', array( 'wc-completed', 'wc-refunded' ) ),
		);

		$this->completed_orders_ids = array();
		$this->refunded_orders_ids  = array();

		$this->walk_orders(
			$order_args,
			'orders',
			function ( $order ) {
				if ( is_a( $order, 'Automattic\WooCommerce\Admin\Overrides\OrderRefund' ) ||
				is_a( $order, 'WC_Order_Refund' )
				) {
					return;
				} elseif ( ! Documents::own_invoice( $order ) ) {
					$this->completed_orders_ids[] = $order->get_id();
				}
			}
		);

		$this->walk_orders(
			$refunded_order_args,
			'refunded',
			function ( $order ) {
				if ( is_a( $order, 'Automattic\WooCommerce\Admin\Overrides\OrderRefund' ) ||
				is_a( $order, 'WC_Order_Refund' )
				) {
					if ( $order->get_parent_id() ) {
						$parent_order = wc_get_order( $order->get_parent_id() );
					} else {
						$parent_order = $order;
					}

					// Invoiced in the shop's own program: left out of the NRA audit file (setting Invoice numbering).
					if ( ! $parent_order || Documents::own_invoice( $parent_order ) ) {
						return;
					}

					$this->refunded_orders_ids[] = $parent_order->get_id();

					if ( $parent_order->get_date_paid() && wp_date( 'Y-m', $parent_order->get_date_paid()->getTimestamp() ) === $this->date ) {
						$this->completed_orders_ids[] = $parent_order->get_id();
					}
				}
			}
		);

		$this->refunded_orders_ids  = array_reverse( array_unique( $this->refunded_orders_ids ) );
		$this->completed_orders_ids = array_reverse( array_unique( $this->completed_orders_ids ) );
	}

	/**
	 * Orders of the month after the "nashenski_admin_export_orders" or "nashenski_admin_export_refunded_orders" filter.
	 *
	 * @param array  $args Order query arguments.
	 * @param string $type "refunded" or "orders".
	 * @return array
	 */
	protected function filtered_orders( $args, $type ) {
		$orders = wc_get_orders( array_merge( $args, array( 'limit' => -1 ) ) );

		if ( 'refunded' === $type ) {
			return \Nashenski\Hooks::filter( 'nashenski_admin_export_refunded_orders', 'nashenski/admin/export/refunded_orders', $orders, $this );
		}

		return \Nashenski\Hooks::filter( 'nashenski_admin_export_orders', 'nashenski/admin/export/orders', $orders, $this );
	}

	/**
	 * Runs the callback on every order of the query, in batches unless a filter supplies the orders.
	 *
	 * @param array    $args     Order query arguments.
	 * @param string   $type     "refunded" or "orders".
	 * @param callable $callback Called with each order.
	 * @return void
	 */
	protected function walk_orders( $args, $type, $callback ) {
		$hooks = 'refunded' === $type ? array( 'nashenski_admin_export_refunded_orders', 'nashenski/admin/export/refunded_orders' ) : array( 'nashenski_admin_export_orders', 'nashenski/admin/export/orders' );
		if ( has_filter( $hooks[0] ) || has_filter( $hooks[1] ) ) {
			$orders = $this->filtered_orders( $args, $type );

			foreach ( $orders as $order ) {
				$callback( $order );
			}

			return;
		}

		$page    = 1;
		$fetched = 0;
		$limit   = (int) \Nashenski\Hooks::filter( 'nashenski_admin_export_query_batch_size', 'nashenski/admin/export/query_batch_size', 100, $this );
		$limit   = max( 1, $limit );

		do {
			$order_ids = wc_get_orders(
				array_merge(
					$args,
					array(
						'limit'  => $limit,
						'page'   => $page,
						'return' => 'ids',
					)
				)
			);

			foreach ( $order_ids as $order_id ) {
				$order = wc_get_order( $order_id );

				if ( $order ) {
					$callback( $order );
				}

				unset( $order );
			}

			$fetched = count( $order_ids );

			$this->flush_runtime_cache();
			++$page;
		} while ( $fetched === $limit );
	}

	/**
	 * Empties the object cache of this request.
	 *
	 * @return void
	 */
	protected function flush_runtime_cache() {
		wp_cache_flush_runtime();
	}

	/**
	 * Shop part of the file: tax IDs, domain, year and month.
	 *
	 * @return array
	 */
	protected function get_shop_data() {
		$eik        = nashenski_get_option( 'nap', 'eik' );
		$nap_number = nashenski_get_option( 'nap', 'nap_number' );
		$domain     = nashenski_get_option( 'nap', 'domain' );

		return array(
			'eik'        => $eik ? $eik : '',
			'nap_number' => $nap_number ? $nap_number : '',
			'domain'     => $domain ? $domain : '',
			'year'       => gmdate( 'Y', strtotime( $this->date ) ),
			'month'      => gmdate( 'm', strtotime( $this->date ) ),
		);
	}

	/**
	 * Builds the file and returns its details.
	 *
	 * @return array|null Null when the month has no orders.
	 */
	public function get_xml_file() {
		if ( empty( $this->completed_orders_ids ) && empty( $this->refunded_orders_ids ) ) {
			return;
		}

		$args = array(
			'tax_rounding_mode' => wc_get_tax_rounding_mode(),
			'shop'              => $this->get_shop_data(),
			'orders'            => array(),
			'refunded_orders'   => array(),
		);

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

	/**
	 * Empties the object cache after every 100th order.
	 *
	 * @param int $index Position in the list.
	 * @return void
	 */
	protected function maybe_flush_runtime_cache( $index ) {
		if ( 0 === ( ( $index + 1 ) % 100 ) ) {
			$this->flush_runtime_cache();
		}
	}

	/**
	 * Builds the XML, saves it and checks it against the schema.
	 *
	 * @param array $args Shop data, orders and refunded orders.
	 * @return array|null
	 */
	protected function upload_xml( $args ) {
		$data   = self::generate_xml_file( $args );
		$errors = '';
		$totals = '';

		if ( ! empty( $data['content'] ) ) {
			$attach_id = \Nashenski\Document_Storage::save( uniqid( wp_rand(), true ), 'xml', $data['content'] );

			if ( ! $attach_id ) {
				return;
			}

			if ( ! empty( $data['totals'] ) ) {
				$totals = sprintf(
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
			'file'                => ( isset( $attach_id ) ) ? \Nashenski\Document_Storage::export_url( $attach_id ) : '',
			'attachment_id'       => isset( $attach_id ) ? $attach_id : 0,
			'not_included_orders' => $this->not_included_orders,
			'totals'              => $totals,
			'errors'              => $errors,
		);
	}

	/**
	 * Schema errors of the saved file.
	 *
	 * @param int $attach_id File ID.
	 * @return array|null Null when the file is valid.
	 */
	protected function get_file_errors( $attach_id ) {
		if ( ! empty( $this->completed_orders_ids ) || ! empty( $this->refunded_orders_ids ) ) {
			libxml_use_internal_errors( true );
			$doc = new \DOMDocument();
			$doc->load( get_attached_file( $attach_id ) );

			if ( ! $doc->schemaValidate( __DIR__ . '/dex_audit.xsd' ) ) {
				return wp_list_pluck( libxml_get_errors(), 'message' );
			}
		}
	}

	/**
	 * Builds the XML.
	 *
	 * @param array $args Shop data, orders and refunded orders.
	 * @return array The content and totals, or an error.
	 */
	protected function generate_xml_file( $args ) {
		// Built on the site, no order limit.
		if ( ! class_exists( '\XMLWriter' ) ) {
			return array( 'error' => __( 'The PHP XMLWriter extension is required to create the NRA XML file.', 'nashenski-commerce' ) );
		}

		return XmlBuilder::build( $args );
	}
}
