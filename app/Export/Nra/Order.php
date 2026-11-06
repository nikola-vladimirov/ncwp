<?php
/**
 * One order of the NRA audit file.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the file name to match the class name.

namespace Nashenski\Export\Nra;

use Nashenski\Admin\Order\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * An order's data for the NRA audit file.
 */
class Order {
	/**
	 * Payment type id.
	 *
	 * @var mixed
	 */
	public $payment_method_type;

	/**
	 * Order number shown in the file.
	 *
	 * @var string
	 */
	public $order_id_to_show;

	/**
	 * WooCommerce order.
	 *
	 * @var \WC_Order
	 */
	public $woo_order;

	/**
	 * Order document number.
	 *
	 * @var string
	 */
	public $order_document_number;

	/**
	 * Saved payment method details of the order.
	 *
	 * @var mixed
	 */
	public $payment_method;

	/**
	 * Virtual POS terminal number.
	 *
	 * @var string|null
	 */
	public $pos_number;

	/**
	 * Payment provider identifier.
	 *
	 * @var string|null
	 */
	public $identifier;

	/**
	 * VAT group of the shop.
	 *
	 * @var string
	 */
	public $vat_group;

	/**
	 * VAT group letter => rate.
	 *
	 * @var array
	 */
	public $vat_groups;

	/**
	 * Tax helper.
	 *
	 * @var \WC_Tax
	 */
	public $tax;

	/**
	 * Load the order's data.
	 *
	 * @param \WC_Order $woo_order      Order.
	 * @param string    $generate_files 'true' to create missing documents.
	 */
	public function __construct( $woo_order, $generate_files ) {
		$this->woo_order = $woo_order;
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks add-ons.
		$this->order_id_to_show = apply_filters( 'nashenski/admin/export/order_id', $this->woo_order->get_order_number(), $this->woo_order );
		$this->load_order_number( $generate_files );
		$this->load_vat_groups();
		$this->load_payment_method();
	}

	/**
	 * Load the VAT groups.
	 */
	protected function load_vat_groups() {
		$this->vat_group  = nashenski_get_option( 'shop', 'vat_group' );
		$this->vat_groups = nashenski_get_vat_groups();
		$this->tax        = new \WC_Tax();
	}

	/**
	 * Load the order document number, creating the documents when asked.
	 *
	 * @param string $generate_files 'true' to create missing documents.
	 */
	protected function load_order_number( $generate_files ) {
		$order_document_number = $this->woo_order->get_meta( 'nashenski_order_number' );

		if ( 'true' === $generate_files && ! $order_document_number ) {
			Documents::generate_documents( $this->woo_order->get_id() );
			$this->woo_order       = wc_get_order( $this->woo_order->get_id() );
			$order_document_number = $this->woo_order->get_meta( 'nashenski_order_number' );
		}

		$this->order_document_number = $order_document_number;
	}

	/**
	 * Load the payment type, POS number and identifier.
	 */
	protected function load_payment_method() {
		$this->payment_method      = $this->woo_order->get_meta( 'nashenski_payment_method' );
		$this->payment_method_type = null;

		if ( empty( $this->payment_method ) ) {
			// The NRA settings of the order's payment method (Documents > payment methods), while that method is turned on.
			$gateway          = $this->woo_order->get_payment_method();
			$gateways         = WC()->payment_gateways->payment_gateways();
			$this->pos_number = null;
			$this->identifier = null;

			if ( isset( $gateways[ $gateway ] ) && wc_string_to_bool( $gateways[ $gateway ]->enabled ) ) {
				$pos_number                = nashenski_get_option( $gateway, 'virtual_pos_number' );
				$identifier                = nashenski_get_option( $gateway, 'identifier' );
				$this->pos_number          = $pos_number ? $pos_number : '';
				$this->identifier          = $identifier ? $identifier : '';
				$this->payment_method_type = nashenski_get_choice( $gateway, 'payment_type', nashenski_get_payment_types() );
			}
		} else {
			$this->pos_number          = $this->payment_method['pos_number'] ?? null;
			$this->identifier          = $this->payment_method['identifier'] ?? null;
			$this->payment_method_type = $this->payment_method['type'] ?? null;
		}
	}

	/**
	 * Product, fee and shipping lines of the order.
	 *
	 * @return array
	 */
	protected function get_items_from_order() {
		$items        = array();
		$shipping_vat = nashenski_get_order_shipping_vat( $this->woo_order );
		$order_items  = array_merge( $this->woo_order->get_items(), $this->woo_order->get_items( 'fee' ) );

		foreach ( $order_items as $key => $item ) {
			// Product lines print the undiscounted subtotal, so a fully discounted line still counts.
			if ( ! ( is_a( $item, 'WC_Order_Item_Fee' ) ? $item->get_total() : $item->get_subtotal() ) ) {
				continue;
			}

			$item_vat_rate = apply_filters(
				'nashenski/admin/export/item_vat', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks add-ons.
				nashenski_get_order_item_vat_rate( $item, $this->woo_order ),
				$item,
				$this->woo_order
			);

			if ( is_a( $item, 'WC_Order_Item_Fee' ) ) {
				$total    = $item->get_total() + $item->get_total_tax();
				$line_tax = ( wc_tax_enabled() ) ? $item->get_total_tax() : nashenski_calculate_vat_from_price( $total, $item_vat_rate );

				$items[] = array(
					'name'     => $item->get_name(),
					'qty'      => $item->get_quantity(),
					'total'    => $total,
					'vat_rate' => $item_vat_rate,
					'line_tax' => $line_tax,
				);
			} else {
				$total    = round( $item->get_subtotal() + $item->get_subtotal_tax(), 2 );
				$line_tax = ( wc_tax_enabled() ) ? $item->get_total_tax() : nashenski_calculate_vat_from_price( $item->get_total(), $item_vat_rate );

				$items[] = array(
					'name'     => $item->get_name(),
					'qty'      => $item->get_quantity(),
					'total'    => $total,
					'vat_rate' => $item_vat_rate,
					'line_tax' => $line_tax,
				);
			}
		}

		foreach ( $this->woo_order->get_items( 'shipping' ) as $item ) {
			if ( ! $item->get_total() ) {
				continue;
			}

			$price    = $item->get_total() + $item->get_total_tax();
			$item_vat = $this->vat_groups[ $this->vat_group ] ?? 0;

			if ( wc_tax_enabled() ) {
				if ( $item->get_total_tax() ) {
					$item_vat = $shipping_vat;
				}
			}

			$price    = apply_filters( 'nashenski/admin/export/item_price', $price, $item, $this->woo_order ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks add-ons.
			$item_vat = apply_filters( 'nashenski/admin/export/item_vat', $item_vat, $item, $this->woo_order ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks add-ons.

			$items[] = array(
				/* translators: %s: shipping method name */
				'name'     => sprintf( __( 'Shipping: %s', 'nashenski-commerce' ), $item->get_name() ),
				'qty'      => $item->get_quantity(),
				'total'    => $price,
				'vat_rate' => $item_vat,
			);
		}

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks add-ons.
		return apply_filters( 'nashenski/admin/nra_export/items', $items, $this->woo_order );
	}

	/**
	 * The order's data for the audit file. Lines run without the shipping when the documents leave it out.
	 *
	 * @return array
	 */
	public function get_order_data() {
		$remove_shipping = nashenski_maybe_remove_shipping( $this->woo_order );
		$shipping_items  = $this->woo_order->get_items( 'shipping' );

		if ( count( $shipping_items ) > 0 && 'yes' === $remove_shipping ) {
			foreach ( $shipping_items as $item_id => $item ) {
				$this->woo_order->remove_item( $item_id );
			}

			$this->woo_order->calculate_totals( true );
		}

		$discount = ( wc_tax_enabled() ) ? $this->woo_order->get_total_discount() : nashenski_tax_based_price( $this->woo_order->get_total_discount(), $this->vat_groups[ $this->vat_group ], true );
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks add-ons.
		$discount = apply_filters( 'nashenski/admin/nra_export/order_total_discount', $discount, $this->woo_order );

		$data = array(
			'order_id'              => $this->order_id_to_show,
			'date_created'          => $this->woo_order->get_date_created()->format( 'Y-m-d' ),
			'order_document_number' => $this->order_document_number,
			'date_modified'         => $this->woo_order->get_date_modified()->format( 'Y-m-d' ),
			'total_discount'        => $discount,
			'payment_method_type'   => $this->payment_method_type,
			'pos_number'            => $this->pos_number,
			'transaction_id'        => $this->woo_order->get_transaction_id(),
			'identifier'            => $this->identifier,
			'items'                 => $this->get_items_from_order(),
		);

		if ( count( $shipping_items ) > 0 && 'yes' === $remove_shipping ) {
			foreach ( $shipping_items as $item_id => $item ) {
				$metas = $item->get_meta_data();

				if ( $metas ) {
					foreach ( $metas as $meta ) {
						$meta_data = $meta->get_data();

						$item->add_meta_data( $meta_data['key'], $meta_data['value'], true );
					}
				}

				$this->woo_order->add_item( $item );
			}

			$this->woo_order->calculate_totals();
		}

		return $data;
	}
}
