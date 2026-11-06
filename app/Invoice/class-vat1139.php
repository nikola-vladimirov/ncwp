<?php
/**
 * VAT Act Article 113(9) invoices.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Invoice;

defined( 'ABSPATH' ) || exit;

/**
 * Invoices issued under Article 113(9) of the VAT Act: no VAT column and the legal text under the table.
 */
class Vat1139 {
	/**
	 * Registers the hooks.
	 */
	public function __construct() {
		add_filter( 'nashenski_invoice_cart_headers', array( __CLASS__, 'filter_cart_headers' ), 10, 2 );
		add_action( 'nashenski_invoice_pdf_default_template_additional_css', array( __CLASS__, 'add_filter_for_order_items' ), 10, 2 );
		add_action( 'nashenski_invoice_pdf_default_template_after_table', array( __CLASS__, 'action_after_table' ), 15, 2 );
	}

	/**
	 * Adds the item and total filters for the document being printed.
	 *
	 * @param object $pdf PDF being printed.
	 */
	public static function add_filter_for_order_items( $pdf ) {
		if ( self::should_hide_vat( $pdf->document ) ) {
			add_filter( 'nashenski_invoice_order_items', array( __CLASS__, 'filter_order_items' ), 10, 2 );
		}

		if ( self::is_invoice( $pdf->document ) ) {
			add_filter( 'nashenski_invoice_order_total_items', array( __CLASS__, 'filter_order_totals' ), 10, 2 );
		}
	}

	/**
	 * Removes the VAT column header.
	 *
	 * @param array  $items Column headers.
	 * @param object $doc   Document.
	 * @return array
	 */
	public static function filter_cart_headers( $items, $doc ) {
		if ( self::should_hide_vat( $doc ) ) {
			unset( $items[2] );
		}

		return $items;
	}

	/**
	 * Removes the VAT rate of each item.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	public static function filter_order_items( $items ) {
		foreach ( $items as &$item ) {
			unset( $item['vat_rate'] );
		}

		return $items;
	}

	/**
	 * Renames the total rows.
	 *
	 * @param array $totals Total rows.
	 * @return array
	 */
	public static function filter_order_totals( $totals ) {
		$totals['subtotal']['label'] = __( 'Total amount', 'nashenski-commerce' ) . ':';
		$totals['total']['label']    = '<strong>' . __( 'Amount due', 'nashenski-commerce' ) . '</strong>:';

		return $totals;
	}

	/**
	 * Prints the Article 113(9) text and removes the filters.
	 *
	 * @param \WC_Order $order Order.
	 * @param object    $pdf   PDF being printed.
	 */
	public static function action_after_table( $order, $pdf ) {
		if ( self::is_invoice( $pdf->document ) ) {
			$text = \Nashenski\Hooks::filter( 'nashenski_invoice_vat_113_9_additional_text', 'nashenski/invoice/vat_113_9_additional_text', __( 'The invoice has been issued on the basis of Article 113, paragraph 9 of the Bulgarian VAT Act.', 'nashenski-commerce' ) );
			echo wp_kses_post( '<p class="fz-12">' . $text . '</p>' );

			remove_filter( 'nashenski_invoice_order_total_items', array( __CLASS__, 'filter_order_totals' ), 10, 2 );
		}

		if ( self::should_hide_vat( $pdf->document ) ) {
			remove_filter( 'nashenski_invoice_order_items', array( __CLASS__, 'filter_order_items' ), 10, 2 );
		}
	}

	/**
	 * The proforma and credit notes have no VAT column either; the Article 113(9) text stays on the invoice only.
	 * shortcut: credit notes follow today's 113(9) setting, not the setting the original invoice had, upgrade if a store switches mid-year.
	 *
	 * @param object $document Document.
	 * @return bool
	 */
	private static function should_hide_vat( $document ) {
		return self::is_invoice( $document ) || $document instanceof Document\Proforma || $document instanceof Document\CreditNotice;
	}

	/**
	 * The separate copy (Documents > Generate separate copy documents) must print like the original.
	 *
	 * @param object $document Document.
	 * @return bool
	 */
	private static function is_invoice( $document ) {
		return $document instanceof Document\Invoice || $document instanceof Document\InvoiceCopy;
	}
}
