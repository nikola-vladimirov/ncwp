<?php
/**
 * Actions.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Order;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Nashenski\Invoice\Document;
use Nashenski\Admin\Import_Sources;

defined( 'ABSPATH' ) || exit;

/**
 * Actions.
 */
class Actions {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$order_documents_trigger = nashenski_get_option( 'invoice', 'trigger' );

		add_action( 'woocommerce_checkout_order_processed', array( '\Nashenski\Admin\Order\Documents', 'generate_proforma' ), 100 );

		if ( ! $order_documents_trigger || 'order_created' === $order_documents_trigger ) {
			add_action( 'woocommerce_checkout_order_processed', array( '\Nashenski\Admin\Order\Documents', 'generate_documents' ), 100 );

			// Bank transfer invoices wait for the payment. The processing and completed hooks fire before WooCommerce sends the email, so the mail carries the invoice; woocommerce_order_status_changed fires after it and only adds custom paid statuses (the callback tests is_paid() and the invoice meta, so one invoice). With the completed trigger, completed already counts as paid.
			foreach ( array( 'woocommerce_order_status_processing', 'woocommerce_order_status_completed', 'woocommerce_payment_complete', 'woocommerce_order_status_changed' ) as $paid_hook ) {
				add_action( $paid_hook, array( '\Nashenski\Admin\Order\Documents', 'generate_invoice_when_paid' ), 100, 1 );
			}
		} elseif ( 'order_completed' === $order_documents_trigger ) {
			add_action( 'woocommerce_order_status_completed_notification', array( '\Nashenski\Admin\Order\Documents', 'generate_documents' ), 5 );
		}

		add_action( 'woocommerce_order_actions', array( __CLASS__, 'add_order_meta_box_actions' ) );
		add_action( 'woocommerce_order_action_nashenski_regenerate_pdfs', array( __CLASS__, 'process_order_meta_box_actions' ) );
		add_action( 'woocommerce_order_action_nashenski_generate_invoice', array( __CLASS__, 'generate_invoice_action' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'make_invoice' ), 60 );

		add_action( 'woocommerce_order_partially_refunded', array( __CLASS__, 'create_refund_action' ), 5, 2 );
		add_action( 'woocommerce_order_fully_refunded', array( __CLASS__, 'create_refund_action' ), 5, 2 );

		add_action( 'woocommerce_order_details_after_customer_details', array( __CLASS__, 'add_invoice_to_customer_order' ) );

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'add_selections_to_bulk_action' ) );
			add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'regenerate_order_pdfs_bulk_action_process' ), 10, 3 );
		} else {
			add_filter( 'bulk_actions-edit-shop_order', array( __CLASS__, 'add_selections_to_bulk_action' ) );
			add_filter( 'handle_bulk_actions-edit-shop_order', array( __CLASS__, 'regenerate_order_pdfs_bulk_action_process' ), 10, 3 );
		}

		add_action( 'admin_notices', array( __CLASS__, 'pdf_regeneration_bulk_action_admin_notices' ) );
	}

	/**
	 * Add selections to bulk action.
	 *
	 * @param mixed $actions Actions.
	 * @return mixed Result.
	 */
	public static function add_selections_to_bulk_action( $actions ) {
		// Paused for the old N-18 plugin: the orders list shows why (Import_Sources::n18_notice()).
		if ( Import_Sources::n18_paused() ) {
			return $actions;
		}

		$actions['nashenski_regenerate_pdfs'] = __( 'Regenerate PDF\'s', 'nashenski-commerce' );

		return $actions;
	}

	/**
	 * Regenerate order pdfs bulk action process.
	 *
	 * @param mixed $redirect_to Redirect to.
	 * @param mixed $action Action.
	 * @param mixed $ids Ids.
	 * @return mixed Result.
	 */
	public static function regenerate_order_pdfs_bulk_action_process( $redirect_to, $action, $ids ) {
		if ( 'nashenski_regenerate_pdfs' !== $action ) {
			return $redirect_to;
		}

		// Documents paused for the old N-18 plugin: nothing is made, and the orders list says why.
		if ( Import_Sources::n18_paused() ) {
			return add_query_arg( 'nashenski_regenerate_paused', 1, $redirect_to );
		}

		foreach ( $ids as $order_id ) {
			Documents::generate_documents( $order_id );
		}

		return add_query_arg( 'nashenski_regenerated', count( $ids ), $redirect_to );
	}

	/**
	 * The notice after the bulk action, on the orders list only.
	 */
	public static function pdf_regeneration_bulk_action_admin_notices() {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order' ), true ) ) {
			return;
		}

		if ( '' !== nashenski_query_arg( 'nashenski_regenerate_paused' ) ) {
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( Import_Sources::paused_reason() ) );
			return;
		}

		if ( '' === nashenski_query_arg( 'nashenski_regenerated' ) ) {
			return;
		}

		$count = absint( nashenski_query_arg( 'nashenski_regenerated' ) );

		printf(
			/* translators: %s: number of orders */
			'<div class="notice notice-success is-dismissible">' . wp_kses_post( wpautop( _n( 'Documents of %s order were regenerated.', 'Documents of %s orders were regenerated.', $count, 'nashenski-commerce' ) ) ) . '</div>',
			esc_html( $count )
		);
	}

	/**
	 * Add invoice to customer order.
	 *
	 * @param mixed $order Order.
	 */
	public static function add_invoice_to_customer_order( $order ) {
		$labels = array(
			'nashenski_invoice_document'               => __( 'Invoice PDF', 'nashenski-commerce' ),
			'nashenski_invoice_copy_document'          => __( 'Invoice Copy PDF', 'nashenski-commerce' ),
			'nashenski_refunded_invoice_document'      => __( 'Refunded Invoice PDF', 'nashenski-commerce' ),
			'nashenski_refunded_invoice_copy_document' => __( 'Refunded Invoice Copy PDF', 'nashenski-commerce' ),
		);

		echo '<div class="nashenski--files">';

		foreach ( Documents::get_order_documents( $order ) as $file ) {
			if ( ! isset( $labels[ $file['meta'] ] ) ) {
				continue;
			}

			printf( '<p><a target="_blank" href="%1$s" class="nashenski button button--pdf">%2$s</a>', esc_url( $file['file_url'] ), esc_html( $labels[ $file['meta'] ] ) );

			if ( $file['file_url_en'] ) {
				printf( ' <a target="_blank" href="%1$s" class="nashenski button button--pdf" lang="en">%2$s</a>', esc_url( $file['file_url_en'] ), esc_html__( 'English', 'nashenski-commerce' ) );
			}

			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Add order meta box actions.
	 *
	 * @param mixed $actions Actions.
	 * @return mixed Result.
	 */
	public static function add_order_meta_box_actions( $actions ) {
		if ( OrderUtil::custom_orders_table_usage_is_enabled() && nashenski_query_arg( 'id' ) ) {
			$post = get_post( absint( nashenski_query_arg( 'id' ) ) );
		} else {
			global $post;
		}

		if ( get_post_type( $post ) !== 'shop_subscription' ) {
			if ( Documents::documents_on() ) {
				$actions['nashenski_generate_invoice'] = __( 'Generate Invoice', 'nashenski-commerce' );
			}

			$actions['nashenski_regenerate_pdfs'] = __( 'Regenerate PDF\'s', 'nashenski-commerce' );
		}

		return $actions;
	}

	/**
	 * Process order meta box actions.
	 *
	 * @param mixed $order Order.
	 */
	public static function process_order_meta_box_actions( $order ) {
		if ( ! self::paused_error() ) {
			Documents::generate_documents( $order->get_id() );
		}
	}

	/**
	 * On the order screen, says why the document buttons do nothing while documents are paused.
	 *
	 * @return bool Whether they are paused.
	 */
	private static function paused_error() {
		if ( ! Import_Sources::n18_paused() ) {
			return false;
		}

		\WC_Admin_Meta_Boxes::add_error( Import_Sources::paused_reason() );
		return true;
	}

	/**
	 * Generate invoice action.
	 *
	 * @param mixed $order Order.
	 */
	public static function generate_invoice_action( $order ) {
		if ( ! self::paused_error() ) {
			( new Document\Invoice( $order ) )->generate_file();
		}
	}

	/**
	 * "Make invoice" in the PDF Files box: the invoice with the number typed in. Runs after
	 * WooCommerce saved the order.
	 *
	 * @param mixed $order_id Order id.
	 */
	public static function make_invoice( $order_id ) {
		if ( empty( nashenski_verified_request( 'nashenski_make_invoice', '', 'nashenski_make_invoice_nonce', 'nashenski_make_invoice' ) ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		if ( self::paused_error() ) {
			return;
		}

		$order  = wc_get_order( $order_id );
		$number = nashenski_verified_request( 'nashenski_invoice_number', '', 'nashenski_make_invoice_nonce', 'nashenski_make_invoice' );
		$error  = self::use_invoice_number( $order, is_string( $number ) ? $number : '' );

		if ( $error ) {
			\WC_Admin_Meta_Boxes::add_error( $error );
			return;
		}

		self::generate_invoice_action( wc_get_order( $order_id ) );
	}

	/**
	 * Keep a typed invoice number on the order. Returns an error message, or '' when it was kept.
	 *
	 * @param mixed $order Order.
	 * @param mixed $number Number.
	 */
	public static function use_invoice_number( $order, $number ) {
		if ( ! preg_match( '/^\d{1,10}$/', $number ) || ! (int) $number ) {
			return __( 'The invoice number must be 1 to 10 digits.', 'nashenski-commerce' );
		}

		return \Nashenski\Invoice\Number_Allocator::manual( $order, $number, Documents::own_numbering() );
	}

	/**
	 * Create refund action.
	 *
	 * @param mixed $order_id Order id.
	 * @param mixed $refund_id Refund id.
	 */
	public static function create_refund_action( $order_id, $refund_id ) {
		// A numbering failure must not break the refund; "Regenerate PDF's" tries again.
		try {
			Documents::generate_refunded_documents( $refund_id, $order_id );
		} catch ( \Throwable $e ) { // WooCommerce deletes the refund on any exception or error thrown here.
			wc_get_logger()->error( 'Refund documents for order #' . $order_id . ' were not generated: ' . $e->getMessage(), array( 'source' => 'nashenski' ) );

			$order = wc_get_order( $order_id );
			if ( $order ) {
				try {
					/* translators: %s: the error message */
					$order->add_order_note( sprintf( __( 'Some documents were not generated: %s', 'nashenski-commerce' ), $e->getMessage() ) );
				} catch ( \Throwable $note_error ) {
					wc_get_logger()->error( 'Order note for order #' . $order_id . ' was not added: ' . $note_error->getMessage(), array( 'source' => 'nashenski' ) );
				}
			}
		}
	}
}
