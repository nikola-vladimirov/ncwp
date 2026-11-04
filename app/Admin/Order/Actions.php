<?php
namespace Nashenski\Admin\Order;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Nashenski\Invoice\Document;

defined( 'ABSPATH' ) || exit;

class Actions {
	function __construct() {
		$order_documents_trigger = nashenski_get_option( 'invoice', 'trigger' );

		add_action( 'woocommerce_checkout_order_processed', array( '\Nashenski\Admin\Order\Documents', 'generate_proforma' ), 100 );
		
		if ( !$order_documents_trigger || $order_documents_trigger === "order_created" ) {
			add_action( 'woocommerce_checkout_order_processed', array( '\Nashenski\Admin\Order\Documents', 'generate_documents' ), 100 );
		} else if ( $order_documents_trigger === "order_completed" ) {
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

	public static function add_selections_to_bulk_action( $actions ) {
		$actions[ 'nashenski_regenerate_pdfs' ] = __( 'Regenerate PDF\'s', 'nashenski-commerce' );

		return $actions;
	}
	
	public static function regenerate_order_pdfs_bulk_action_process( $redirect_to, $action, $ids ) {
		if ( $action === 'nashenski_regenerate_pdfs' ) {
			foreach ( $ids as $order_id ) {
				Documents::generate_documents( $order_id );
			}
	
			// Adding the right query vars to the returned URL
			$redirect_to = add_query_arg( array(
				'processed_count' => count( $ids )
			), $redirect_to );
		}

		return $redirect_to;
	}
	
	public static function pdf_regeneration_bulk_action_admin_notices() {
		if ( '' !== nashenski_query_arg( 'processed_count' ) ) {
			$count = absint( nashenski_query_arg( 'processed_count' ) );

			printf(
				/* translators: %s: number of orders */
				'<div id="message" class="updated fade">' . wp_kses_post( wpautop( _n( '%s order documents were regenerated.', '%s orders documents were regenerated.', $count, 'nashenski-commerce' ) ) ) . '</div>',
				esc_html( $count )
			);
		}
	}

	public static function add_invoice_to_customer_order( $order ) {
		$labels = array(
			'nashenski_invoice_document' => __( 'Invoice PDF', 'nashenski-commerce' ),
			'nashenski_invoice_copy_document' => __( 'Invoice Copy PDF', 'nashenski-commerce' ),
			'nashenski_refunded_invoice_document' => __( 'Refunded Invoice PDF', 'nashenski-commerce' ),
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

	public static function add_order_meta_box_actions( $actions ) {
		if ( OrderUtil::custom_orders_table_usage_is_enabled() && nashenski_query_arg( 'id' ) ) {
			$post = get_post( absint( nashenski_query_arg( 'id' ) ) );
		} else {
			global $post;
		}

		if ( get_post_type( $post ) !== 'shop_subscription' ) {
			if ( Documents::documents_on() ) {
				$actions[ 'nashenski_generate_invoice' ] = __( 'Generate Invoice', 'nashenski-commerce' );
			}

			$actions[ 'nashenski_regenerate_pdfs' ] = __( 'Regenerate PDF\'s', 'nashenski-commerce' );
		}

		return $actions;
	}

	public static function process_order_meta_box_actions( $order ) {
		Documents::generate_documents( $order->get_id() );
	}

	public static function generate_invoice_action( $order ) {
		( new Document\Invoice( $order ) )->generate_file();
	}

	/**
	 * "Make invoice" in the PDF Files box: the invoice with the number typed in. Runs after
	 * WooCommerce saved the order.
	 */
	public static function make_invoice( $order_id ) {
		$request = nashenski_verified_request( null, array(), 'nashenski_make_invoice_nonce', 'nashenski_make_invoice' );

		if ( empty( $request['nashenski_make_invoice'] ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		$error = self::use_invoice_number( $order, (string) ( $request['nashenski_invoice_number'] ?? '' ) );

		if ( $error ) {
			\WC_Admin_Meta_Boxes::add_error( $error );
			return;
		}

		self::generate_invoice_action( wc_get_order( $order_id ) );
	}

	/**
	 * Keep a typed invoice number on the order. Returns an error message, or '' when it was kept.
	 */
	public static function use_invoice_number( $order, $number ) {
		if ( ! preg_match( '/^\d{1,10}$/', $number ) || ! (int) $number ) {
			return __( 'The invoice number must be 1 to 10 digits.', 'nashenski-commerce' );
		}

		return \Nashenski\Invoice\Number_Allocator::manual( $order, $number, Documents::own_numbering() );
	}

	public static function create_refund_action( $order_id, $refund_id ) {
		// A numbering failure must not break the refund; "Regenerate PDF's" tries again.
		try {
			Documents::generate_refunded_documents( $refund_id, $order_id );
		} catch ( \Exception $e ) { // WooCommerce deletes the refund on any exception thrown here.
			wc_get_logger()->error( 'Refund documents for order #' . $order_id . ' were not generated: ' . $e->getMessage(), array( 'source' => 'nashenski' ) );

			if ( $order = wc_get_order( $order_id ) ) {
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
