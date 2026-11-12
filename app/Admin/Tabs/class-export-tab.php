<?php
/**
 * Export tab.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Order\Documents;
use Nashenski\Export\Nra\Export as NraExport;
use Nashenski\Export\Delta\Export as DeltaExport;
use Nashenski\Export\InvoiceArchive\Export as InvoiceArchiveExport;

defined( 'ABSPATH' ) || exit;

/**
 * Export tab.
 */
class Export_Tab extends Base_Tab {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_name( __( 'Export', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'export' );
	}

	/**
	 * Nashenski export nap callback.
	 */
	public static function nashenski_export_nap_callback() {
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'nashenski_export_nap' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'nashenski-commerce' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'nashenski-commerce' ) ), 403 );
		}

		// The NRA rejects the file without the company EIK, so ask for it before building anything.
		if ( ! preg_match( '/^(\d{9}|\d{13})$/', str_replace( ' ', '', (string) nashenski_get_option( 'nap', 'eik' ) ) ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
					/* translators: %s: link to the Documents settings */
						__( 'Fill in the company EIK (9 or 13 digits) in %s and save, then create the file again.', 'nashenski-commerce' ),
						'<a href="' . esc_url( \Nashenski\Admin\Settings::url( 'documents' ) ) . '">' . esc_html__( 'Documents', 'nashenski-commerce' ) . '</a>'
					),
				)
			);
		}

		$export = new NraExport(
			sanitize_text_field( wp_unslash( $_REQUEST['year'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_REQUEST['generate_files'] ?? '' ) )
		);

		$generated_file = $export->get_xml_file();

		if ( ! $generated_file ) {
			wp_send_json_success(
				array(
					'message' => __( 'No orders found for this month.', 'nashenski-commerce' ),
				)
			);
		} elseif ( empty( $generated_file['file'] ) ) {
			wp_send_json_success(
				array(
					'message' => implode( '<br>', array_map( 'esc_html', (array) $generated_file['errors'] ) ),
				)
			);
		}

		$additional_message = '';

		if ( ! empty( $generated_file['not_included_orders'] ) ) {
			$additional_message = '. ' . sprintf(
				/* translators: %s: comma separated order IDs */
				__( 'Some of the orders was not included because the payment method options was not found or the payment method was disabled. List of orders id\'s: %s', 'nashenski-commerce' ),
				implode( ', ', $generated_file['not_included_orders'] )
			);
		}

		if ( ! empty( $generated_file['totals'] ) ) {
			$additional_message .= '<br><br>' . $generated_file['totals'];
		}

		if ( ! empty( $generated_file['errors'] ) ) {
			ob_start();
			?>
			<h2><?php esc_html_e( 'Found errors in file:', 'nashenski-commerce' ); ?></h2>
			<ul>
				<?php foreach ( $generated_file['errors'] as $error ) : ?>
					<li><?php echo esc_html( $error ); ?></li>
				<?php endforeach ?>
			</ul>
			<?php
			$additional_message .= '<br><br>' . ob_get_clean();
		}

		$additional_message = \Nashenski\Hooks::filter( 'nashenski_admin_export_nra_additional_message', 'nashenski/admin/export/nra/additional_message', $additional_message, $export );

		wp_send_json_success(
			array(
				/* translators: %s: file address */
				'message' => sprintf( __( 'File generated successfully! <a href="%s" download target="_blank">Download</a>', 'nashenski-commerce' ), $generated_file['file'] ) . $additional_message,
			)
		);
	}


	/**
	 * Nashenski export microinvest callback.
	 */
	public static function nashenski_export_microinvest_callback() {
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'nashenski_export_nap' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'nashenski-commerce' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'nashenski-commerce' ) ), 403 );
		}

		$export         = new DeltaExport( sanitize_text_field( wp_unslash( $_REQUEST['year'] ?? '' ) ) );
		$generated_file = $export->get_file();

		if ( ! $generated_file ) {
			wp_send_json_success(
				array(
					'message' => __( 'No orders found for this month.', 'nashenski-commerce' ),
				)
			);
		}

		wp_send_json_success(
			array(
				/* translators: %s: file address */
				'message' => sprintf( __( 'File generated successfully! <a href="%s" download target="_blank">Download</a>', 'nashenski-commerce' ), $generated_file['file'] ),
			)
		);
	}

	/**
	 * Nashenski export invoice archive callback.
	 */
	public static function nashenski_export_invoice_archive_callback() {
		if ( ! isset( $_REQUEST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'nashenski_export_nap' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'nashenski-commerce' ) ), 403 );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'nashenski-commerce' ) ), 403 );
		}

		$export         = new InvoiceArchiveExport( sanitize_text_field( wp_unslash( $_REQUEST['year'] ?? '' ) ) );
		$generated_file = $export->get_file();

		if ( is_wp_error( $generated_file ) ) {
			wp_send_json_error( $generated_file, 400 );
		} elseif ( empty( $generated_file['documents'] ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'No orders found for this month.', 'nashenski-commerce' ),
				)
			);
		}

		nashenski_send_download( $generated_file['temp_file'], $generated_file['file_name'], 'application/zip' );
	}
}
