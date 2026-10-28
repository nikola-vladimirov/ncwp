<?php
namespace Nashenski\Admin\Order;
use Nashenski\Invoice\Document;

defined( 'ABSPATH' ) || exit;

class Documents {
	/**
	 * Invoices and the other documents (Overview > Invoices and N-18). The order document is made
	 * for every order either way.
	 */
	public static function documents_on() {
		return 'yes' === nashenski_get_option( 'apis', 'enable_documents' );
	}

	/**
	 * Checkout > Company invoice > Invoice numbering is "Own invoicing program": invoices get the
	 * number typed in on the order screen and stay out of the NRA audit file.
	 */
	public static function own_numbering() {
		return 'own' === nashenski_get_option( 'company', 'invoice_numbering' );
	}

	/**
	 * Per order, so switching the setting later does not move invoices made before in or out of
	 * the NRA audit file.
	 */
	public static function own_invoice( $order ) {
		return 'yes' === $order->get_meta( 'nashenski_invoice_own_program' );
	}

	public static function generate_documents( $order_id ) {
		$order = wc_get_order( $order_id );

		( new Document\NRA( $order ) )->generate_file();

		if ( self::documents_on() && self::maybe_generate_invoice( $order ) ) {
			( new Document\Invoice( $order ) )->generate_file();
			
			if ( $order->get_payment_method() === 'bacs' ) {
				( new Document\Proforma( $order ) )->generate_file();
			}

			self::maybe_generate_invoice_copy( $order );
		}

		if ( $refunds = $order->get_refunds() ) {
			foreach ( $refunds as $refund ) {
				self::generate_refunded_documents( $refund->get_id(), $refund->get_parent_id() );
			}
		}
	}

	public static function generate_proforma( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( self::documents_on() && self::maybe_generate_proforma( $order ) && $order->get_payment_method() === 'bacs' ) {
			( new Document\Proforma( $order ) )->generate_file();
		}
	}

	public static function maybe_generate_invoice( $order ) {
		$invoice_generation = nashenski_get_option( 'invoice', 'invoices' );

		$generate_invoice = (
			$order->get_meta( 'nashenski_invoice_document' ) ||
			// Own invoicing program: no automatic invoice, the shop makes it on the order screen with its own number.
			( ! self::own_numbering() && (
				$invoice_generation === 'always' ||
				( $invoice_generation === 'only_for_company' && $order->get_meta('_billing_to_company') )
			) )
		);

		return apply_filters( 'nashenski/admin/order/maybe_generate_invoice', $generate_invoice, $order );
	}

	public static function maybe_generate_proforma( $order ) {
		return apply_filters( 'nashenski/admin/order/maybe_generate_proforma', self::maybe_generate_invoice( $order ), $order );
	}

	public static function generate_refunded_documents( $refund_id, $order_id ) {
		$order = wc_get_order( $order_id );

		( new Document\NRARefunded( $refund_id ) )->generate_file();

		if ( self::documents_on() && self::maybe_generate_invoice( $order ) ) {
			( new Document\CreditNotice( $refund_id ) )->generate_file();

			self::maybe_generate_credit_notice_copy( $refund_id, $order_id );
		}
	}

	public static function maybe_generate_invoice_copy( $order ) {
		if ( nashenski_get_option( 'invoice', 'separate_copy_documents' ) !== 'yes' ) {
			return;
		}

		$copy = new Document\InvoiceCopy( $order );
		$copy->generate_file();

		self::send_accountant_email( $order->get_id(), $copy );
	}

	public static function maybe_generate_credit_notice_copy( $refund_id, $order_id ) {
		if ( nashenski_get_option( 'invoice', 'separate_copy_documents' ) !== 'yes' ) {
			return;
		}

		$copy = new Document\CreditNoticeCopy( $refund_id );
		$copy->generate_file();

		self::send_accountant_email( $order_id, $copy );
	}

	public static function send_accountant_email( $order_id, $document ) {
		$accountant_email = nashenski_get_option( 'invoice', 'accountant_email' );

		if ( ! $accountant_email ) {
			return;
		}

		$attach_id = $document->woo_order->get_meta( $document->meta );

		if ( ! $attach_id ) {
			return;
		}

		$file_path = get_attached_file( $attach_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		WC()->mailer();
		$email = new Accountant_Email();
		$email->trigger( $order_id, array( $file_path ) );
	}

	/**
	 * PDF files of an order and its refunds: meta, name, file_url (Bulgarian) and file_url_en.
	 */
	public static function get_order_documents( $main_order ) {
		$names = array(
			'nashenski_order_document' => __( 'Order', 'nashenski-commerce' ),
			'nashenski_invoice_document' => __( 'Invoice', 'nashenski-commerce' ),
			'nashenski_invoice_copy_document' => __( 'Invoice Copy', 'nashenski-commerce' ),
			'nashenski_refunded_order_document' => __( 'Refunded Order', 'nashenski-commerce' ),
			'nashenski_refunded_invoice_document' => __( 'Refunded Invoice', 'nashenski-commerce' ),
			'nashenski_refunded_invoice_copy_document' => __( 'Refunded Invoice Copy', 'nashenski-commerce' ),
			'nashenski_proform_document' => __( 'Pro forma', 'nashenski-commerce' ),
		);
		$files = array();

		foreach ( array_merge( array( $main_order ), $main_order->get_refunds() ) as $order ) {
			foreach ( $names as $meta => $name ) {
				if ( $order->get_meta( $meta ) ) {
					$files[] = array(
						'meta' => $meta,
						'name' => $name,
						'file_url' => \Nashenski\Document_Storage::order_document_url( $order, $meta ),
						'file_url_en' => $order->get_meta( $meta . '_en' ) ? \Nashenski\Document_Storage::order_document_url( $order, $meta . '_en' ) : '',
					);
				}
			}
		}

		return apply_filters( 'nashenski/admin/order/documents', $files, $main_order );
	}
}
