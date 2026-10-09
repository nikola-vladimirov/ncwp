<?php
namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

class Ajax {
	public function __construct() {

		add_action( 'wp_ajax_nashenski_export_nap', array( 'Nashenski\Admin\Tabs\Export_Tab', 'nashenski_export_nap_callback' ) );
		add_action( 'wp_ajax_nashenski_export_microinvest', array( 'Nashenski\Admin\Tabs\Export_Tab', 'nashenski_export_microinvest_callback' ) );
		add_action( 'wp_ajax_nashenski_export_invoice_archive', array( 'Nashenski\Admin\Tabs\Export_Tab', 'nashenski_export_invoice_archive_callback' ) );

		add_action( 'wp_ajax_nashenski_generate_label_from_listing_page', array( 'Nashenski\Admin\Order\Labels', 'generate_label_from_listing_page_callback') );
		add_action( 'wp_ajax_nashenski_boxnow_message_dismiss', array( 'Nashenski\Admin\BoxNow', 'message_dismiss_callback') );

		add_action( 'wp_ajax_nashenski_pigeon_express_message_dismiss', array( 'Nashenski\Admin\Pigeon', 'message_dismiss_callback') );

		add_action( 'wp_ajax_nashenski_scan_impossible_prices_batch', array( 'Nashenski\Admin\ImpossibleVatPrice', 'ajax_scan_batch' ) );
		add_action( 'wp_ajax_nashenski_fix_impossible_price', array( 'Nashenski\Admin\ImpossibleVatPrice', 'ajax_fix_price' ) );
	}
}
