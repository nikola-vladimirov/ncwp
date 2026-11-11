<?php
/**
 * Documents.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Order;

use Nashenski\Invoice\Document;

defined( 'ABSPATH' ) || exit;

/**
 * Documents.
 */
class Documents {
	/** Set when an order got the note that its documents were paused, so it gets one note. */
	const PAUSED_META = '_nashenski_documents_paused';

	/** Set on an order whose documents wait for a busy numbering; retry() makes them. */
	const RETRY_META = '_nashenski_documents_retry';

	/** The scheduled event that makes the documents of the waiting orders. */
	const RETRY_HOOK = 'nashenski_documents_retry';

	/**
	 * Whether documents are paused for the old N-18 plugin (Import_Sources::n18_paused()). The order then
	 * gets a note, once, so the owner can find it and make its documents later. When numbering was only
	 * busy, the order waits for retry() instead, with one note.
	 *
	 * @param \WC_Order|false $order Order whose automatic documents are skipped.
	 * @return bool
	 */
	public static function paused( $order ) {
		if ( ! \Nashenski\Admin\Import_Sources::n18_paused() ) {
			return false;
		}

		if ( \Nashenski\Admin\Import_Sources::$busy ) {
			if ( $order && ! $order->get_meta( self::RETRY_META ) ) {
				$order->add_order_note( __( 'Document numbering was busy, so the documents were not made yet. They are made again automatically in a few minutes.', 'nashenski-commerce' ) );
				$order->update_meta_data( self::RETRY_META, 'yes' );
				$order->save();
			}
			if ( ! wp_next_scheduled( self::RETRY_HOOK ) ) {
				wp_schedule_single_event( time() + 2 * MINUTE_IN_SECONDS, self::RETRY_HOOK );
			}
			return true;
		}

		// shortcut: one note per order; a later pause on an order that got documents in between adds none, add a reset when that matters.
		if ( $order && ! $order->get_meta( self::PAUSED_META ) ) {
			$order->add_order_note( __( 'No documents were made: Nashenski Commerce paused them while the old N-18 plugin was active. If that plugin made documents for this order, the order keeps its numbers. If not, make them here with "Regenerate PDF\'s" once it is deactivated.', 'nashenski-commerce' ) );
			$order->update_meta_data( self::PAUSED_META, 'yes' );
			$order->save();
		}

		return true;
	}

	/**
	 * The scheduled retry: makes the documents of the orders that waited for a busy numbering, a batch
	 * at a time. While numbering is still busy it tries again a few minutes later. If documents are
	 * paused for the N-18 plugin by then, generate_documents() notes that on each order instead.
	 */
	public static function retry() {
		$batch = 20;

		if ( \Nashenski\Admin\Import_Sources::n18_paused() && \Nashenski\Admin\Import_Sources::$busy ) {
			wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::RETRY_HOOK );
			return;
		}

		$ids = wc_get_orders(
			array(
				'limit'        => $batch,
				'return'       => 'ids',
				'meta_key'     => self::RETRY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- only the few orders that waited.
				'meta_compare' => 'EXISTS',
			)
		);

		foreach ( $ids as $id ) {
			self::generate_documents( $id );

			// Busy again: paused() kept the order waiting and scheduled the next try.
			if ( \Nashenski\Admin\Import_Sources::$busy ) {
				return;
			}

			$order = wc_get_order( $id );
			$order->delete_meta_data( self::RETRY_META );
			$order->save();
		}

		if ( count( $ids ) === $batch ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::RETRY_HOOK );
		}
	}

	/**
	 * Invoices and the other documents (Overview > Invoices and N-18). The order document is made
	 * for every order either way.
	 */
	public static function documents_on() {
		return 'yes' === nashenski_get_option( 'apis', 'enable_documents' );
	}

	/**
	 * Documents > Invoices and documents > Invoice numbering is "Own invoicing program": invoices get the
	 * number typed in on the order screen and stay out of the NRA audit file.
	 */
	public static function own_numbering() {
		return 'own' === nashenski_get_option( 'company', 'invoice_numbering' );
	}

	/**
	 * Per order, so switching the setting later does not move invoices made before in or out of
	 * the NRA audit file.
	 *
	 * @param mixed $order Order.
	 */
	public static function own_invoice( $order ) {
		return 'yes' === $order->get_meta( 'nashenski_invoice_own_program' );
	}

	/** The documents are made at checkout (the default), not when the order is completed. */
	public static function default_trigger() {
		$trigger = nashenski_get_option( 'invoice', 'trigger' );

		return ! $trigger || 'order_created' === $trigger;
	}

	/**
	 * Generate documents.
	 *
	 * @param mixed $order_id Order id.
	 */
	public static function generate_documents( $order_id ) {
		$order = wc_get_order( $order_id );

		// A numbering failure (lock timeout, database error) must not break checkout or the order status change.
		// The order stays without that document; "Regenerate PDF's" tries again.
		try {
			if ( self::paused( $order ) ) {
				return;
			}

			( new Document\NRA( $order ) )->generate_file();

			if ( self::documents_on() && self::maybe_generate_invoice( $order ) ) {
				// A bank transfer invoice waits for the payment (generate_invoice_when_paid); the proforma is made now.
				$bacs = $order->get_payment_method() === 'bacs';
				$wait = $bacs && ! $order->is_paid() && ! $order->get_meta( 'nashenski_invoice_document' );

				if ( ! $wait ) {
					( new Document\Invoice( $order ) )->generate_file();
				}

				if ( $bacs ) {
					( new Document\Proforma( $order ) )->generate_file();
				}

				if ( ! $wait ) {
					self::maybe_generate_invoice_copy( $order );
				}
			}

			$refunds = $order->get_refunds();
			if ( $refunds ) {
				foreach ( $refunds as $refund ) {
					self::generate_refunded_documents( $refund->get_id(), $refund->get_parent_id() );
				}
			}
		} catch ( \Exception $e ) {
			wc_get_logger()->error( 'Documents for order #' . $order_id . ' were not generated: ' . $e->getMessage(), array( 'source' => 'nashenski' ) );
			try { // The note must not throw out of the hook either.
				/* translators: %s: the error message */
				$order->add_order_note( sprintf( __( 'Some documents were not generated: %s', 'nashenski-commerce' ), $e->getMessage() ) );
			} catch ( \Throwable $note_error ) {
				wc_get_logger()->error( 'Order note for order #' . $order_id . ' was not added: ' . $note_error->getMessage(), array( 'source' => 'nashenski' ) );
			}
		}
	}

	/**
	 * Bank transfer orders get the invoice once they are paid (any paid status or payment complete).
	 *
	 * @param mixed $order_id Order id.
	 */
	public static function generate_invoice_when_paid( $order_id ) {
		$order = wc_get_order( $order_id );

		// Only bank transfer orders numbered at checkout (renewals have the number removed; the order document may be deleted later).
		if ( ! $order || $order->get_payment_method() !== 'bacs' || ! $order->get_meta( 'nashenski_order_number' ) || ! self::documents_on() || ! $order->is_paid() || $order->get_meta( 'nashenski_invoice_document' ) || ! self::maybe_generate_invoice( $order ) ) {
			return;
		}

		try {
			if ( self::paused( $order ) ) {
				return;
			}

			( new Document\Invoice( $order ) )->generate_file();
			self::maybe_generate_invoice_copy( $order );

			// Refunds made before the payment had no credit notice (it needs the invoice); make them now, as generate_documents does.
			foreach ( $order->get_refunds() as $refund ) {
				self::generate_refunded_documents( $refund->get_id(), $refund->get_parent_id() );
			}
		} catch ( \Throwable $e ) {
			wc_get_logger()->error( 'Invoice for order #' . $order_id . ' was not generated: ' . $e->getMessage(), array( 'source' => 'nashenski' ) );
			try { // The note must not throw out of the hook either.
				/* translators: %s: the error message */
				$order->add_order_note( sprintf( __( 'Some documents were not generated: %s', 'nashenski-commerce' ), $e->getMessage() ) );
			} catch ( \Throwable $note_error ) {
				wc_get_logger()->error( 'Order note for order #' . $order_id . ' was not added: ' . $note_error->getMessage(), array( 'source' => 'nashenski' ) );
			}
		}
	}

	/**
	 * Generate proforma.
	 *
	 * @param mixed $order_id Order id.
	 */
	public static function generate_proforma( $order_id ) {
		$order = wc_get_order( $order_id );

		// A numbering failure must not break checkout; the order stays without the proforma.
		try {
			if ( self::documents_on() && self::maybe_generate_proforma( $order ) && $order->get_payment_method() === 'bacs' && ! self::paused( $order ) ) {
				( new Document\Proforma( $order ) )->generate_file();
			}
		} catch ( \Exception $e ) {
			wc_get_logger()->error( 'Proforma for order #' . $order_id . ' was not generated: ' . $e->getMessage(), array( 'source' => 'nashenski' ) );
			try { // The note must not throw out of the hook either.
				/* translators: %s: the error message */
				$order->add_order_note( sprintf( __( 'Some documents were not generated: %s', 'nashenski-commerce' ), $e->getMessage() ) );
			} catch ( \Throwable $note_error ) {
				wc_get_logger()->error( 'Order note for order #' . $order_id . ' was not added: ' . $note_error->getMessage(), array( 'source' => 'nashenski' ) );
			}
		}
	}

	/**
	 * Maybe generate invoice.
	 *
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	public static function maybe_generate_invoice( $order ) {
		$invoice_generation = nashenski_get_option( 'invoice', 'invoices' );

		$generate_invoice = (
			$order->get_meta( 'nashenski_invoice_document' ) ||
			// Own invoicing program: no automatic invoice, the shop makes it on the order screen with its own number.
			( ! self::own_numbering() && (
				'always' === $invoice_generation ||
				( 'only_for_company' === $invoice_generation && $order->get_meta( '_billing_to_company' ) )
			) )
		);

		return \Nashenski\Hooks::filter( 'nashenski_admin_order_maybe_generate_invoice', 'nashenski/admin/order/maybe_generate_invoice', $generate_invoice, $order );
	}

	/**
	 * Maybe generate proforma.
	 *
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	public static function maybe_generate_proforma( $order ) {
		return \Nashenski\Hooks::filter( 'nashenski_admin_order_maybe_generate_proforma', 'nashenski/admin/order/maybe_generate_proforma', self::maybe_generate_invoice( $order ), $order );
	}

	/**
	 * Generate refunded documents.
	 *
	 * @param mixed $refund_id Refund id.
	 * @param mixed $order_id Order id.
	 */
	public static function generate_refunded_documents( $refund_id, $order_id ) {
		$order = wc_get_order( $order_id );

		if ( self::paused( $order ) ) {
			return;
		}

		( new Document\NRARefunded( $refund_id ) )->generate_file();

		// With the default trigger a bank transfer order gets a credit notice only once its invoice exists.
		if ( self::documents_on() && self::maybe_generate_invoice( $order ) && ( $order->get_payment_method() !== 'bacs' || ! self::default_trigger() || $order->get_meta( 'nashenski_invoice_document' ) ) ) {
			( new Document\CreditNotice( $refund_id ) )->generate_file();

			self::maybe_generate_credit_notice_copy( $refund_id, $order_id );
		}
	}

	/**
	 * Maybe generate invoice copy.
	 *
	 * @param mixed $order Order.
	 */
	public static function maybe_generate_invoice_copy( $order ) {
		if ( nashenski_get_option( 'invoice', 'separate_copy_documents' ) !== 'yes' ) {
			return;
		}

		$copy = new Document\InvoiceCopy( $order );
		$copy->generate_file();

		self::send_accountant_email( $order->get_id(), $copy );
	}

	/**
	 * Maybe generate credit notice copy.
	 *
	 * @param mixed $refund_id Refund id.
	 * @param mixed $order_id Order id.
	 */
	public static function maybe_generate_credit_notice_copy( $refund_id, $order_id ) {
		if ( nashenski_get_option( 'invoice', 'separate_copy_documents' ) !== 'yes' ) {
			return;
		}

		$copy = new Document\CreditNoticeCopy( $refund_id );
		$copy->generate_file();

		self::send_accountant_email( $order_id, $copy );
	}

	/**
	 * Send accountant email.
	 *
	 * @param mixed $order_id Order id.
	 * @param mixed $document Document.
	 */
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
	 *
	 * @param mixed $main_order Main order.
	 */
	public static function get_order_documents( $main_order ) {
		$names = array(
			'nashenski_order_document'                 => __( 'Order', 'nashenski-commerce' ),
			'nashenski_invoice_document'               => __( 'Invoice', 'nashenski-commerce' ),
			'nashenski_invoice_copy_document'          => __( 'Invoice Copy', 'nashenski-commerce' ),
			'nashenski_refunded_order_document'        => __( 'Refunded Order', 'nashenski-commerce' ),
			'nashenski_refunded_invoice_document'      => __( 'Refunded Invoice', 'nashenski-commerce' ),
			'nashenski_refunded_invoice_copy_document' => __( 'Refunded Invoice Copy', 'nashenski-commerce' ),
			'nashenski_proform_document'               => __( 'Pro forma', 'nashenski-commerce' ),
		);
		$files = array();

		foreach ( array_merge( array( $main_order ), $main_order->get_refunds() ) as $order ) {
			foreach ( $names as $meta => $name ) {
				if ( $order->get_meta( $meta ) ) {
					$files[] = array(
						'meta'        => $meta,
						'name'        => $name,
						'file_url'    => \Nashenski\Document_Storage::order_document_url( $order, $meta ),
						'file_url_en' => $order->get_meta( $meta . '_en' ) ? \Nashenski\Document_Storage::order_document_url( $order, $meta . '_en' ) : '',
					);
				}
			}
		}

		return \Nashenski\Hooks::filter( 'nashenski_admin_order_documents', 'nashenski/admin/order/documents', $files, $main_order );
	}
}
