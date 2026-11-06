<?php
/**
 * Delta (Microinvest) export.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Export\Delta;

defined( 'ABSPATH' ) || exit;

/**
 * Exports the invoices and credit notes of a month as a pipe-separated text file.
 */
class Export {
	/**
	 * Rows of the file.
	 *
	 * @var array
	 */
	public $documents = array();

	/**
	 * Orders passed to the documents filter.
	 *
	 * @var array
	 */
	public $woo_orders = array();

	/**
	 * Order IDs of the month.
	 *
	 * @var array
	 */
	public $order_ids = array();

	/**
	 * Month to export, as text strtotime() reads.
	 *
	 * @var string
	 */
	public $date;

	/**
	 * Loads the orders of the month.
	 *
	 * @param string $date Month to export.
	 */
	public function __construct( $date ) {
		$this->date = $date;
		$this->load_woo_orders();
	}

	/**
	 * Loads the order IDs created in the month.
	 */
	protected function load_woo_orders() {
		$this->order_ids = wc_get_orders(
			array(
				'date_created' => strtotime( 'first day of ' . $this->date . ' ' . wp_timezone_string() ) . '...' . strtotime( 'last day of ' . $this->date . ' 23:59:59 ' . wp_timezone_string() ),
				'limit'        => -1,
				'return'       => 'ids',
			)
		);
	}

	/**
	 * Builds the file.
	 *
	 * @return array|null File URL, or null when it could not be saved.
	 */
	public function get_file() {
		foreach ( $this->order_ids as $key => $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			$names = array();
			foreach ( $order->get_items() as $item ) {
				$names[] = $item->get_name();
			}

			$temp_order = $order;
			$company    = '';
			$eik        = '';
			$vat_number = '';
			$mol        = '';
			$address    = '';
			$city       = '';

			if ( is_a( $order, 'Automattic\WooCommerce\Admin\Overrides\OrderRefund' ) || is_a( $order, 'WC_Order_Refund' ) ) {
				$temp_order = wc_get_order( $order->get_parent_id() );

				if ( ! $temp_order ) {
					continue;
				}
			}

			if ( $temp_order->get_meta( '_billing_to_company' ) === '1' ) {
				$company    = $temp_order->get_billing_company();
				$eik        = $temp_order->get_meta( '_billing_company_eik' );
				$vat_number = $temp_order->get_meta( '_billing_vat_number' );
				$mol        = $temp_order->get_meta( '_billing_company_mol' );
				$city       = $temp_order->get_meta( '_billing_company_settlement' );
				$address    = $temp_order->get_meta( '_billing_company_address' );
			} else {
				$company = $temp_order->get_billing_first_name() . ' ' . $temp_order->get_billing_last_name();
				$address = $temp_order->get_billing_address_1() . ' ' . $temp_order->get_billing_address_2();
				$city    = $temp_order->get_billing_city();
			}

			$order_number = $order->get_meta( 'nashenski_order_invoice_number' );

			if ( ! $order_number ) {
				$order_number = $order->get_meta( 'nashenski_order_number' );
			}

			if ( $order->get_meta( 'nashenski_invoice_document' ) ) {
				$total_due = $order->get_total();

				$this->documents[] = \Nashenski\Hooks::filter(
					'nashenski_admin_export_microinvest-order',
					'nashenski/admin/export/microinvest-order',
					array(
						'2',
						date_i18n( 'd.m.Y', strtotime( $order->get_date_created() ) ),
						$order_number,
						'Ф-ра',
						$total_due,
						'16',
						$company,
						$mol,
						$city,
						$address,
						$vat_number,
						$eik,
						'',
						'Продажба',
						implode( ', ', $names ),
						'-1',
					),
					$order,
					$temp_order
				);
			}

			if ( $order->get_meta( 'nashenski_refunded_invoice_document' ) ) {
				$total_due         = $order->get_total();
				$this->documents[] = \Nashenski\Hooks::filter(
					'nashenski_admin_export_microinvest-order',
					'nashenski/admin/export/microinvest-order',
					array(
						'2',
						date_i18n( 'd.m.Y', strtotime( $order->get_date_created() ) ),
						$order_number,
						'КИ',
						$total_due,
						'16',
						$company,
						$mol,
						$city,
						$address,
						$vat_number,
						$eik,
						'',
						'Продажба',
						implode( ', ', $names ),
						'-1',
					),
					$order,
					$temp_order
				);
			}

			unset( $order, $temp_order );

			if ( 0 === ( ( $key + 1 ) % 100 ) && function_exists( 'wp_cache_flush_runtime' ) ) {
				wp_cache_flush_runtime();
			}
		}

		if ( has_filter( 'nashenski_admin_delta_export_documents' ) || has_filter( 'nashenski/admin/delta_export/documents' ) ) {
			$this->woo_orders = array_filter( array_map( 'wc_get_order', $this->order_ids ) );
			$this->documents  = \Nashenski\Hooks::filter( 'nashenski_admin_delta_export_documents', 'nashenski/admin/delta_export/documents', $this->documents, $this->woo_orders );
		}

		return $this->upload_file();
	}

	/**
	 * A field of the pipe and newline separated file: the separators become spaces.
	 *
	 * @param mixed $value Field value.
	 * @return string
	 */
	protected static function clean( $value ) {
		return str_replace( array( '|', "\r", "\n" ), ' ', (string) $value );
	}

	/**
	 * Saves the rows as a text file.
	 *
	 * @return array|null File URL, or null when it could not be saved.
	 */
	protected function upload_file() {
		$name    = uniqid( wp_rand(), true );
		$content = '';

		foreach ( $this->documents as $index => $document ) {
			if ( $index > 0 ) {
				$content .= "\n";
			}

			$content .= implode( '|', array_map( array( __CLASS__, 'clean' ), $document ) );
		}

		$attach_id = \Nashenski\Document_Storage::save( $name, 'txt', $content );
		unset( $content );

		if ( ! $attach_id ) {
			return;
		}

		return array(
			'file' => \Nashenski\Document_Storage::export_url( $attach_id ),
		);
	}
}
