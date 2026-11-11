<?php
/**
 * Order data for invoice documents.
 *
 * @package Nashenski
 */

namespace Nashenski\Invoice\Order;

use Nashenski\Invoice\CurrencyToString;

defined( 'ABSPATH' ) || exit;

/**
 * The lines and totals of an order, as the invoice prints them.
 */
class Order {
	/**
	 * The WooCommerce order or refund.
	 *
	 * @var \WC_Order|\WC_Order_Refund
	 */
	public $woo_order;

	/**
	 * VAT group chosen in the settings.
	 *
	 * @var string
	 */
	private $vat_group;

	/**
	 * VAT percentage of each VAT group.
	 *
	 * @var array
	 */
	private $vat_percentages;

	/**
	 * Tax calculator.
	 *
	 * @var \WC_Tax
	 */
	private $wc_tax;

	/**
	 * VAT percentage of the chosen group.
	 *
	 * @var float|int|null
	 */
	private $vat;

	/**
	 * Taxes of the order.
	 *
	 * @var array
	 */
	private $order_taxes;

	/**
	 * Whether delivery is left out of the documents (yes or no).
	 *
	 * @var string
	 */
	private $remove_shipping;

	/**
	 * Takes the order and reads its VAT settings.
	 *
	 * @param \WC_Order|\WC_Order_Refund $order Order or refund.
	 */
	public function __construct( $order ) {
		$this->woo_order = $order;

		$this->set_vat_and_taxes();
		$this->set_remove_shipping();

		if ( 'yes' === nashenski_get_option( 'invoice', 'total_text' ) ) {
			add_action( 'nashenski_invoice_pdf_default_template_after_table', array( __CLASS__, 'print_total_in_words' ), 10, 2 );
		}
	}

	/**
	 * Shipping methods whose price counts as taxable by default.
	 *
	 * @return string[]
	 */
	public static function get_default_taxable_shipping_rates() {
		return array( 'local_pickup', 'flat_rate' );
	}

	/**
	 * Reads the VAT group, its percentage and the taxes of the order.
	 */
	private function set_vat_and_taxes() {
		$this->vat_group       = nashenski_get_option( 'shop', 'vat_group' );
		$this->vat_percentages = nashenski_get_vat_groups();
		$this->wc_tax          = new \WC_Tax();
		// No VAT group chosen yet (Settings > Shop): same null as before, without the PHP warning.
		$this->vat         = $this->vat_percentages[ $this->vat_group ] ?? null;
		$this->order_taxes = $this->woo_order->get_taxes();
	}

	/**
	 * Reads whether delivery is left out of the documents: when the shop did not collect it.
	 */
	private function set_remove_shipping() {
		$this->remove_shipping = \Nashenski\Shipping\Courier_Fee::shop_collects( $this->woo_order ) ? 'no' : 'yes';
	}

	/**
	 * The lines of the document: products, fees and delivery. Delivery lines are taken out and put back when the shop did not collect the delivery.
	 *
	 * @return array
	 */
	public function get_items() {
		$shipping_items = $this->woo_order->get_items( 'shipping' );

		if ( count( $shipping_items ) > 0 && 'yes' === $this->remove_shipping ) {
			foreach ( $shipping_items as $item_id => $item ) {
				$this->woo_order->remove_item( $item_id );
			}

			$this->woo_order->calculate_totals( true );
		}

		$items       = array();
		$order_items = array_merge( $this->woo_order->get_items(), $this->woo_order->get_items( 'fee' ) );

		if ( empty( $order_items ) ) {
			$parent_order = wc_get_order( $this->woo_order->get_parent_id() );

			if ( $parent_order && abs( $parent_order->get_total() ) === abs( $this->woo_order->get_total() ) ) {
				$this->woo_order   = $parent_order;
				$order_items       = array_merge( $parent_order->get_items(), $parent_order->get_items( 'fee' ) );
				$this->order_taxes = $this->woo_order->get_taxes();
			} else {
				$items[] = array(
					'name'     => \Nashenski\Hooks::filter( 'nashenski_invoice_order_item_name', 'nashenski/invoice/order/item_name', ( method_exists( $this->woo_order, 'get_reason' ) ? $this->woo_order->get_reason() : '' ), null ),
					'quantity' => 1,
					'vat_rate' => '',
					'price'    => wc_price( abs( $this->woo_order->get_total() ), array( 'currency' => $this->woo_order->get_currency() ) ),
					'total'    => wc_price( abs( $this->woo_order->get_total() ), array( 'currency' => $this->woo_order->get_currency() ) ),
				);
			}
		}

		if ( ! empty( $order_items ) ) {
			foreach ( $order_items as $key => $item ) {
				$qty = ( abs( $item->get_quantity() ) ) ? abs( $item->get_quantity() ) : 1;

				$subtotal = ( method_exists( $item, 'get_subtotal' ) ) ? $item->get_subtotal() : $item->get_total();

				$items[] = \Nashenski\Hooks::filter(
					'nashenski_invoice_order_item',
					'nashenski/invoice/order/item',
					array(
						'name'     => \Nashenski\Hooks::filter( 'nashenski_invoice_order_item_name', 'nashenski/invoice/order/item_name', $item->get_name(), $item ),
						'quantity' => $qty,
						'vat_rate' => nashenski_get_order_item_vat_rate( $item, $this->woo_order, 1 ) . '%',
						'price'    => wc_price( abs( $subtotal / $qty ), array( 'currency' => $this->woo_order->get_currency() ) ),
						'total'    => wc_price( abs( $subtotal ), array( 'currency' => $this->woo_order->get_currency() ) ),
					),
					$item,
					$this
				);
			}
		}

		if ( count( $this->woo_order->get_items( 'shipping' ) ) > 0 && 'yes' !== $this->remove_shipping ) {
			$shipping_vat = nashenski_get_order_shipping_vat( $this->woo_order );

			foreach ( $this->woo_order->get_items( 'shipping' ) as $item ) {
				if ( ! $item->get_total() ) {
					$item_price = 0;
				} else {
					$item_price = $item->get_total() / absint( $item->get_quantity() );
				}

				$item_vat        = $this->vat;
				$item_tax        = $item->get_total_tax();
				$item_tax_status = 'taxable';

				if ( in_array( $item->get_method_id(), self::get_default_taxable_shipping_rates(), true ) ) {
					$instance = \WC_Shipping_Zones::get_shipping_method( $item->get_instance_id() );

					if ( $instance ) {
						$item_tax_status = $instance->get_instance_option( 'tax_status' );

						if ( 'none' === $item_tax_status ) {
							$item_vat = 0;
						}
					}
				}

				if ( wc_tax_enabled() && 'taxable' === $item_tax_status ) {
					if ( $item->get_total_tax() ) {
						$item_vat = $shipping_vat;
					} elseif ( nashenski_shipping_has_no_vat( $item, $this->woo_order ) ) {
						$item_vat = 0;
					} else {
						$item_tax   = $this->wc_tax::calc_tax(
							$item_price,
							array(
								array(
									'compound' => 'yes',
									'rate'     => $this->vat,
								),
							),
							true
						)[0];
						$item_price = $item_price - $item_tax;
					}
				}

				$item_total = $item_price * $item->get_quantity();

				$items[] = \Nashenski\Hooks::filter(
					'nashenski_invoice_order_item',
					'nashenski/invoice/order/item',
					array(
						/* translators: %s: shipping method name */
						'name'     => \Nashenski\Hooks::filter( 'nashenski_invoice_order_item_name', 'nashenski/invoice/order/item_name', sprintf( __( 'Shipping: %s', 'nashenski-commerce' ), $item->get_name() ), $item ),
						'quantity' => abs( $item->get_quantity() ),
						'vat_rate' => nashenski_maybe_add_rate_group( $item_vat ) . '%',
						'price'    => wc_price( abs( $item_price ), array( 'currency' => $this->woo_order->get_currency() ) ),
						'total'    => wc_price( abs( $item_total ), array( 'currency' => $this->woo_order->get_currency() ) ),
					),
					$item,
					$this
				);
			}
		}

		if ( count( $shipping_items ) > 0 && 'yes' === $this->remove_shipping ) {
			foreach ( $shipping_items as $item_id => $item ) {
				$metas = $item->get_meta_data();

				if ( $metas ) {
					foreach ( $metas as $meta ) {
						$data = $meta->get_data();

						$item->add_meta_data( $data['key'], $data['value'], true );
					}
				}

				$this->woo_order->add_item( $item );
			}

			$this->woo_order->calculate_totals( true );
		}

		return \Nashenski\Hooks::filter( 'nashenski_invoice_order_items', 'nashenski/invoice/order/items', $items, $this );
	}

	/**
	 * The total rows of the document: subtotal, discount, taxes and the total due.
	 *
	 * @return array
	 */
	public function get_total_items() {
		$shipping_items = $this->woo_order->get_items( 'shipping' );
		$shipping_total = $this->woo_order->get_shipping_total();
		$subtotal       = abs( $this->woo_order->get_subtotal() + $shipping_total + $this->woo_order->get_total_fees() );

		if ( $this->woo_order instanceof \WC_Order_Refund ) {
			// get_order_item_totals() fires a third-party filter that fatals on refunds, so build the only two rows used below, as WooCommerce does.
			$order_total_items = array();

			if ( $this->woo_order->get_total_discount() > 0 ) {
				$order_total_items['cart_subtotal'] = array(
					'type'  => 'subtotal',
					'label' => __( 'Subtotal:', 'nashenski-commerce' ),
				);
				$order_total_items['discount']      = array();
			}
		} else {
			$order_total_items = $this->woo_order->get_order_item_totals();
		}

		if ( method_exists( $this->woo_order, 'get_reason' ) && $this->woo_order->get_reason() && 0.0 === (float) $subtotal ) {
			$subtotal = abs( $this->woo_order->get_total() );
		}

		foreach ( $shipping_items as $item ) {
			if ( in_array( $item->get_method_id(), self::get_default_taxable_shipping_rates(), true ) ) {
				$instance = \WC_Shipping_Zones::get_shipping_method( $item->get_instance_id() );

				if ( $instance ) {
					$item_tax_status = $instance->get_instance_option( 'tax_status' );

					if ( 'none' === $item_tax_status ) {
						$shipping_total = 0;
					}
				}
			}
		}

		if ( count( $shipping_items ) > 0 && 'yes' === $this->remove_shipping ) {
			foreach ( $shipping_items as $item_id => $item ) {
				$this->woo_order->remove_item( $item_id );
			}

			$this->woo_order->calculate_totals( true );

			$subtotal = abs( $this->woo_order->get_subtotal() + $this->woo_order->get_total_fees() );
		}

		if ( isset( $order_total_items['discount'] ) ) {
			$discount_amount = $this->woo_order->get_total_discount();

			$items['all']          = $order_total_items['cart_subtotal'];
			$items['all']['value'] = wc_price( $subtotal, array( 'currency' => $this->woo_order->get_currency() ) );

			$items['discount'] = array(
				'label' => __( 'Discount', 'nashenski-commerce' ) . ':',
				'value' => wc_price( $discount_amount, array( 'currency' => $this->woo_order->get_currency() ) ),
			);
			$items['subtotal'] = array(
				'label' => __( 'Total', 'nashenski-commerce' ) . ':',
				'value' => wc_price( $subtotal - $discount_amount, array( 'currency' => $this->woo_order->get_currency() ) ),
			);
		} else {
			$items['subtotal'] = array(
				'label' => __( 'Total', 'nashenski-commerce' ) . ':',
				'value' => wc_price( $subtotal, array( 'currency' => $this->woo_order->get_currency() ) ),
			);
		}

		if ( wc_tax_enabled() ) {
			foreach ( $this->woo_order->get_tax_totals() as $code => $tax_total ) {
				$items[ 'tax-' . sanitize_title_with_dashes( $code ) ] = array(
					'label' => esc_html( $tax_total->label ) . ':',
					'value' => wc_price( wc_round_tax_total( abs( $tax_total->amount ) ), array( 'currency' => $this->woo_order->get_currency() ) ),
				);
			}
		}

		$items['total'] = array(
			'label'        => __( 'Total due', 'nashenski-commerce' ) . ':',
			'value'        => wc_price( abs( $this->woo_order->get_total() ), array( 'currency' => $this->woo_order->get_currency() ) ),
			'value_number' => abs( $this->woo_order->get_total() ),
		);

		if ( count( $shipping_items ) > 0 && 'yes' === $this->remove_shipping ) {
			foreach ( $shipping_items as $item_id => $item ) {
				$metas = $item->get_meta_data();

				if ( $metas ) {
					foreach ( $metas as $meta ) {
						$data = $meta->get_data();

						$item->add_meta_data( $data['key'], $data['value'], true );
					}
				}

				$this->woo_order->add_item( $item );
			}

			$this->woo_order->calculate_totals( true );
		}

		return \Nashenski\Hooks::filter( 'nashenski_invoice_order_total_items', 'nashenski/invoice/order/total_items', $items, $this );
	}

	/**
	 * Prints the total due in words under the table.
	 *
	 * @param \WC_Order|\WC_Order_Refund $woo_order Order or refund.
	 * @param object                     $pdf       PDF being made.
	 */
	public static function print_total_in_words( $woo_order, $pdf ) {
		$total_items = $pdf->document->order->get_total_items();
		$words       = CurrencyToString::amount_in_words( $total_items['total']['value_number'], $woo_order->get_currency() );

		if ( $words ) {
			echo wp_kses_post( '<p class="fz-12"><strong>Словом</strong>: ' . esc_html( $words ) . '</p>' );
		}
	}
}
