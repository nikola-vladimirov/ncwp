<?php
namespace Nashenski\Admin\Order;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

defined( 'ABSPATH' ) || exit;

class MetaBox {
	function __construct() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
	}

	public static function add_meta_boxes() {
		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? array( wc_get_page_screen_id( 'shop-order' ) )
		: array( 'shop_order' );

		add_meta_box( 'nashenski_pdf-box', __( 'PDF Files', 'nashenski-commerce' ), array( __CLASS__, 'pdf_actions_meta_box' ), $screen, 'side', 'high' );
	}

	public static function pdf_actions_meta_box( $post ) {
		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$order = $post;
			$ids = [ $order->get_id() ];
		} else {
			$ids = [ $post->ID ];
			$order = wc_get_order( $post->ID );
		}

		$files = Documents::get_order_documents( $order );
		?>
		<ul class="wpo_wcpdf-actions">
			<?php foreach ( $files as $file ): ?>
				<li>
					<a target="_blank" href="<?php echo esc_url( $file[ 'file_url' ] ); ?>" class="button">
						<div class="wp-menu-image dashicons-before dashicons-pdf"></div>
						
						<?php echo esc_html( $file['name'] ) ?>
					</a>
					<?php if ( $file['file_url_en'] ) : ?>
						<a target="_blank" href="<?php echo esc_url( $file['file_url_en'] ); ?>" class="button" lang="en"><?php esc_html_e( 'EN', 'nashenski-commerce' ); ?></a>
					<?php endif; ?>
				</li>
			<?php endforeach ?>
		</ul>
		<?php
		if ( Documents::documents_on() && ! $order->get_meta( 'nashenski_invoice_document' ) ) {
			self::make_invoice_form( $order );
		}
	}

	/**
	 * Make the invoice by hand, with the number the store chooses (filled in with the next one).
	 */
	private static function make_invoice_form( $order ) {
		$next = $order->get_meta( 'nashenski_order_invoice_number' )
			?: nashenski_get_option( 'invoice', 'next_invoice_separate_number' )
			?: $order->get_meta( 'nashenski_order_number' )
			?: nashenski_get_option( 'invoice', 'next_invoice_number' );
		?>
		<p class="nashenski-make-invoice">
			<label for="nashenski_invoice_number"><?php esc_html_e( 'Invoice number', 'nashenski-commerce' ); ?></label>
			<input type="text" id="nashenski_invoice_number" name="nashenski_invoice_number" value="<?php echo esc_attr( max( 1, (int) $next ) ); ?>" inputmode="numeric" pattern="[0-9]{1,10}" maxlength="10" class="short">
			<?php wp_nonce_field( 'nashenski_make_invoice', 'nashenski_make_invoice_nonce' ); ?>
			<input type="hidden" name="nashenski_make_invoice" value="">
			<?php // Not a submit button: Enter in any order field would press the first submit button of the form. ?>
			<button type="button" class="button nashenski-make-invoice__button"><?php esc_html_e( 'Make invoice', 'nashenski-commerce' ); ?></button>
			<span class="description"><?php esc_html_e( 'Uses the PDF look you set under Documents. Saves the order too.', 'nashenski-commerce' ); ?></span>
		</p>
		<?php
	}
}
