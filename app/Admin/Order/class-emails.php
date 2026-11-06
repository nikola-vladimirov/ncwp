<?php
/**
 * Attaches order documents to WooCommerce emails.
 *
 * @package Nashenski
 */

namespace Nashenski\Admin\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Adds invoices, proformas and refund documents to the emails of an order.
 */
class Emails {
	/**
	 * Registers the email hooks.
	 */
	public function __construct() {
		add_filter( 'woocommerce_email_attachments', array( __CLASS__, 'attach_invoice_to_mail' ), 10, 4 );
		add_filter( 'woocommerce_email_attachments', array( __CLASS__, 'attach_proform_to_mail' ), 10, 4 );
		add_filter( 'woocommerce_email_attachments', array( __CLASS__, 'attach_refund_pdfs_to_mail' ), 10, 4 );
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_accountant_email' ) );
	}

	/**
	 * Registers the accountant email class.
	 *
	 * @param array $email_classes WooCommerce email classes.
	 * @return array
	 */
	public static function register_accountant_email( $email_classes ) {
		$email_classes['WC_Email_Nashenski_Accountant'] = new Accountant_Email();

		return $email_classes;
	}

	// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the filter passes four arguments; $email stays in the signature.

	/**
	 * Attaches the order document and the invoice or proforma to the emails of the chosen trigger.
	 *
	 * @param array  $attachments Attachment paths.
	 * @param string $email_id    Email id.
	 * @param mixed  $order       Order.
	 * @param mixed  $email       Email object, not used.
	 * @return array
	 */
	public static function attach_invoice_to_mail( $attachments, $email_id, $order, $email ) {
		if ( ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return $attachments;
		}

		$order = wc_get_order( $order->get_id() );

		$order_documents_trigger = nashenski_get_option( 'invoice', 'trigger' );

		if ( ! $order_documents_trigger || 'order_created' === $order_documents_trigger ) {
			$email_ids = array( 'customer_processing_order', 'customer_on_hold_order', 'customer_processing_renewal_order' );
		} elseif ( 'order_completed' === $order_documents_trigger ) {
			$email_ids = array( 'customer_completed_order', 'customer_completed_renewal_order' );
		}

		$email_ids[] = 'customer_invoice';

		if ( in_array( $email_id, $email_ids, true ) ) {
			$order_doc_id = $order->get_meta( 'nashenski_order_document' );
			$proform_id   = $order->get_meta( 'nashenski_proform_document' );
			$invoice_id   = $order->get_meta( 'nashenski_invoice_document' );

			if ( $order_doc_id ) {
				$attachments[] = get_attached_file( $order_doc_id );
			}

			if ( 'customer_on_hold_order' === $email_id && $proform_id ) {
				$attachments[] = get_attached_file( $proform_id );
			} elseif ( $invoice_id ) {
				$attachments[] = get_attached_file( $invoice_id );
			}
		}

		return $attachments;
	}

	/**
	 * Attaches the refund documents to the refund and invoice emails.
	 *
	 * @param array  $attachments Attachment paths.
	 * @param string $email_id    Email id.
	 * @param mixed  $order       Order.
	 * @param mixed  $email       Email object, not used.
	 * @return array
	 */
	public static function attach_refund_pdfs_to_mail( $attachments, $email_id, $order, $email ) {
		if ( ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return $attachments;
		}

		$order = wc_get_order( $order->get_id() );

		$email_ids = array( 'customer_refunded_order', 'customer_invoice' );

		if ( in_array( $email_id, $email_ids, true ) ) {
			$ids = array( $order->get_id() );

			$refunds = $order->get_refunds();

			if ( $refunds ) {
				foreach ( $refunds as $refund ) {
					$ids[] = $refund->get_id();
				}
			}

			foreach ( $ids as $id ) {
				$order      = wc_get_order( $id );
				$invoice_id = $order->get_meta( 'nashenski_refunded_order_document' );
				if ( $invoice_id ) {
					$attachments[] = get_attached_file( $invoice_id );
				}

				$invoice_id = $order->get_meta( 'nashenski_refunded_invoice_document' );
				if ( $invoice_id ) {
					$attachments[] = get_attached_file( $invoice_id );
				}
			}
		}

		return $attachments;
	}

	/**
	 * Attaches the proforma to the on-hold email.
	 *
	 * @param array  $attachments Attachment paths.
	 * @param string $email_id    Email id.
	 * @param mixed  $order       Order.
	 * @param mixed  $email       Email object, not used.
	 * @return array
	 */
	public static function attach_proform_to_mail( $attachments, $email_id, $order, $email ) {
		if ( ! is_object( $order ) || ! is_a( $order, 'WC_Order' ) ) {
			return $attachments;
		}

		$email_ids = array( 'customer_on_hold_order' );

		if ( in_array( $email_id, $email_ids, true ) ) {
			$order      = wc_get_order( $order->get_id() );
			$proform_id = $order->get_meta( 'nashenski_proform_document' );

			// attach_invoice_to_mail() may have added the same proforma already.
			if ( $proform_id && ! in_array( get_attached_file( $proform_id ), $attachments, true ) ) {
				$attachments[] = get_attached_file( $proform_id );
			}
		}

		return $attachments;
	}

	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
}
