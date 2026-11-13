<?php
/**
 * Invoice settings.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Invoice;

defined( 'ABSPATH' ) || exit;

/**
 * The two invoice settings of Documents > Invoices and documents: "Enable invoices" and "How to
 * issue invoices". Every reader of them asks here.
 */
class Mode {
	/**
	 * Order meta: the invoice of this order was made out of the NRA audit file, so the order stays
	 * out of the file. Set once, with the first invoice. Orders invoiced in the shop's own program
	 * (the "Invoice numbering" setting, 0.17.0 to 0.21.9) carry it too.
	 */
	const OUT_OF_NRA = 'nashenski_invoice_own_program';

	/**
	 * "Enable invoices": the checkout asks "I want an invoice for a company" and the website makes
	 * the invoice of every order where it is ticked. Never on while "Invoices and N-18" is off.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return 'yes' === nashenski_get_option( 'apis', 'enable_documents' ) && 'yes' === nashenski_get_option( 'invoice', 'enabled' );
	}

	/**
	 * "How to issue invoices" is "in the NRA audit file" (request_nra), not the default (request).
	 *
	 * @return bool
	 */
	public static function in_nra() {
		return 'request_nra' === nashenski_get_option( 'invoice', 'mode' );
	}

	/**
	 * Whether the order is left out of the NRA audit file.
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	public static function out_of_nra( $order ) {
		return 'yes' === $order->get_meta( self::OUT_OF_NRA );
	}

	/**
	 * Before the first invoice of an order: decided once, so changing "How to issue invoices" later
	 * does not move invoices already made in or out of the NRA audit file. The caller saves the order.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function mark( $order ) {
		if ( ! self::in_nra() && ! $order->get_meta( 'nashenski_invoice_document' ) ) {
			$order->update_meta_data( self::OUT_OF_NRA, 'yes' );
		}
	}

	/**
	 * The new pair from "When to generate invoices?" of 0.21.9 and earlier, and of Bulgarisation for
	 * WooCommerce: disable (or nothing saved) is off; only_fields, only_for_company and always are on.
	 *
	 * @param mixed $invoices The old choice.
	 * @param bool  $in_nra   Whether those invoices went into the NRA audit file.
	 * @return array enabled (yes or no) and mode (request or request_nra).
	 */
	public static function from_old( $invoices, $in_nra ) {
		$enabled = in_array( $invoices, array( 'only_fields', 'only_for_company', 'always' ), true );

		return array(
			'enabled' => $enabled ? 'yes' : 'no',
			'mode'    => $enabled && $in_nra ? 'request_nra' : 'request',
		);
	}

	/**
	 * Save the pair.
	 *
	 * @param array $pair From from_old().
	 */
	public static function save( $pair ) {
		nashenski_set_option( 'invoice', 'enabled', $pair['enabled'] );
		nashenski_set_option( 'invoice', 'mode', $pair['mode'] );
	}

	/**
	 * 0.21.10, once: "When to generate invoices?", "Invoice numbering" and "Company invoice fields"
	 * become the pair. "Own invoicing program" kept invoices out of the NRA audit file. The old
	 * options stay for one version, so a downgrade still works. Documents and numbers are not touched.
	 * A new store has nothing to map: the setup wizard or the settings set the pair.
	 *
	 * @param string|false $was The plugin version saved before this request (false on a new store).
	 */
	public static function upgrade( $was = false ) {
		if ( false !== get_option( 'nashenski_settings_invoice_enabled' ) ) {
			return;
		}

		$invoices = get_option( 'nashenski_settings_invoice_invoices' );
		$in_nra   = 'own' !== nashenski_get_option( 'company', 'invoice_numbering' );

		if ( false !== $invoices ) {
			self::save( self::from_old( $invoices, $in_nra ) );
		} elseif ( $was && version_compare( $was, '0.21.10', '<' ) ) {
			// The choice was never saved: the company box showed while documents were on, unless "Company invoice fields" was saved off. Keep it.
			$fields = get_option( 'nashenski_settings_company_fields' );
			$box    = in_array( $fields, array( 'yes', 'no' ), true ) ? 'yes' === $fields : 'yes' === nashenski_get_option( 'apis', 'enable_documents' );

			self::save( self::from_old( $box ? 'only_for_company' : 'disable', $in_nra ) );
		}
	}
}
