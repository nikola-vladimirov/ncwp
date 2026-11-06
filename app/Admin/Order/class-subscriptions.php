<?php
/**
 * Order documents for WooCommerce Subscriptions.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Order;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps order numbers and documents right for subscription renewals.
 */
class Subscriptions {
	/**
	 * Hooks the subscription events.
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_subscription_created', array( __CLASS__, 'subscriptions_created' ), 20, 2 );
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( __CLASS__, 'subscriptions_created' ), 20, 2 );
		add_filter( 'wc_subscriptions_renewal_order_data', array( __CLASS__, 'drop_document_data' ) );
		add_filter( 'wc_subscriptions_subscription_data', array( __CLASS__, 'drop_document_data' ) );
		add_filter( 'wc_subscriptions_resubscribe_order_data', array( __CLASS__, 'drop_document_data' ) );
	}

	/**
	 * A renewal and its subscription must not copy the earlier order's number or order document:
	 * the renewal gets its own when its documents are made.
	 *
	 * @param array $data Order or subscription data to copy.
	 * @return array
	 */
	public static function drop_document_data( $data ) {
		if ( is_array( $data ) ) {
			unset( $data['nashenski_order_number'], $data['nashenski_order_document'] );
		}

		return $data;
	}

	/**
	 * Gives a renewal order its own number and documents.
	 *
	 * @param \WC_Subscription $subscription The subscription.
	 * @param \WC_Order        $order        The renewal or parent order.
	 */
	public static function subscriptions_created( $subscription, $order ) {
		// The checkout hook passes the parent order, which may already be numbered. Only renewals are renumbered.
		if ( ! function_exists( 'wcs_order_contains_renewal' ) || ! wcs_order_contains_renewal( $order ) ) {
			return;
		}

		// A numbering failure must not break the subscription. The number carried over from the earlier order is
		// removed and no document is made with it; "Regenerate PDF's" gives the renewal a fresh number later.
		try {
			self::increase_order_doc_number( $order );
		} catch ( \RuntimeException $e ) {
			wc_get_logger()->error( 'Order number for order #' . $order->get_id() . ' was not increased, so its documents were not generated: ' . $e->getMessage(), array( 'source' => 'nashenski' ) );
			try { // The note must not throw out of the hook either.
				/* translators: %s: the error message */
				$order->add_order_note( sprintf( __( 'Some documents were not generated: %s', 'nashenski-commerce' ), $e->getMessage() ) );
			} catch ( \Throwable $note_error ) {
				wc_get_logger()->error( 'Order note for order #' . $order->get_id() . ' was not added: ' . $note_error->getMessage(), array( 'source' => 'nashenski' ) );
			}
			$order->delete_meta_data( 'nashenski_order_number' );
			try {
				$order->save();
			} catch ( \Throwable $save_error ) {
				wc_get_logger()->error( 'Order #' . $order->get_id() . ' could not be saved after the numbering failure: ' . $save_error->getMessage(), array( 'source' => 'nashenski' ) );
			}
			return;
		}

		Documents::generate_documents( $order->get_id() );
	}

	/**
	 * Gives the order the next order document number.
	 *
	 * @param \WC_Order $order The order.
	 */
	public static function increase_order_doc_number( $order ) {
		\Nashenski\Invoice\Number_Allocator::allocate( $order, 'nashenski_order_number', 'next_invoice_number', true );
	}
}
