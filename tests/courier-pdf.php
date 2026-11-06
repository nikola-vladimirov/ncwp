<?php
/**
 * Real WordPress/WooCommerce, mocked courier responses. Dedicated test site only.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName, Generic.Files.OneObjectStructurePerFile.MultipleFound, Universal.Files.SeparateFunctionsFromOO.Mixed -- a standalone test script: its fixtures live in this one file.

$pdf_wp = getenv( 'NASHENSKI_NUMBERING_WP' );
if ( ! $pdf_wp || ! is_file( $pdf_wp . '/wp-load.php' ) ) {
	fwrite( STDERR, "Set NASHENSKI_NUMBERING_WP to the disposable Numbering tests installation.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI error before WordPress is loaded.
	exit( 1 );
}
define( 'DOING_AJAX', true );
require $pdf_wp . '/wp-load.php';
if ( 'Numbering tests' !== get_option( 'blogname' ) ) {
	throw new RuntimeException( 'Dedicated test site required.' ); }
wp_set_current_user( get_user_by( 'login', 'numbering-test' )->ID );
require dirname( __DIR__ ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/app/functions.php';

use Nashenski\Admin\Courier_Pdf;
use Nashenski\Container\Client;

/**
 * Courier API client that answers with the prepared response.
 */
class PdfFixtureApi {
	const CREATE_LABELS_ENDPOINT   = 'create';
	const SHIPMENT_STATUS_ENDPOINT = 'status';

	/**
	 * Record the operation and answer with the prepared response.
	 *
	 * @param string $operation   Operation.
	 * @param array  $body        Request body.
	 * @param mixed  $destination Destination.
	 * @return mixed
	 */
	public function label_request( $operation, $body, $destination = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the mock keeps the real client's signature.
		$GLOBALS['pdf_operations'][] = $operation;
		return $GLOBALS['pdf_response']; }

	/**
	 * The prepared response, wrapped like a status answer for the status endpoint.
	 *
	 * @param string $endpoint Endpoint.
	 * @param array  $body     Request body.
	 * @return mixed
	 */
	public function api_call( $endpoint, $body ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the mock keeps the real client's signature.
		return 'status' === $endpoint ? array( array( array( 'status' => $GLOBALS['pdf_response'] ) ) ) : $GLOBALS['pdf_response']; }

	/**
	 * One region.
	 *
	 * @return array
	 */
	public function get_regions() {
		return array( 'S' => 'Region' ); }

	/**
	 * No cities.
	 *
	 * @return array
	 */
	public function get_filtered_cities() {
		return array(
			'city_key' => false,
			'cities'   => array(),
		); }

	/**
	 * Any other call: an empty answer.
	 *
	 * @param string $method Method.
	 * @param array  $args   Arguments.
	 * @return array
	 */
	public function __call( $method, $args ) {
		return array(); }
}

/**
 * Plugin instance with the mocked clients.
 */
class PdfFixtureApp {
	/**
	 * Client container.
	 *
	 * @var array
	 */
	public $clients;

	/**
	 * The client container.
	 *
	 * @return array
	 */
	public function container() {
		return $this->clients; }

	/**
	 * Plugin folder.
	 *
	 * @return string
	 */
	public function plugin_dir_path() {
		return dirname( __DIR__ ) . '/'; }

	/**
	 * Plugin URL.
	 *
	 * @return string
	 */
	public function plugin_dir_url() {
		return 'https://numbering.invalid/plugins/nashenski/'; }
}

/**
 * The mocked plugin instance.
 *
 * @return PdfFixtureApp
 */
function nashenski() {
	return $GLOBALS['pdf_app']; }
$pdf_app          = new PdfFixtureApp();
$api              = new PdfFixtureApi();
$pdf_app->clients = array_fill_keys( array( Client::ECONT, Client::ECONT_PROFILE, Client::ECONT_CITIES, Client::ECONT_OFFICES ), $api );
$checks           = 0;

/**
 * Count a check and fail when it does not hold.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @throws RuntimeException When the condition is false.
 */
function pdf_check( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		throw new RuntimeException( esc_html( $message ) ); } }

/**
 * Thrown in place of wp_die() so the AJAX handler returns.
 */
class PdfJsonDone extends RuntimeException {}
add_filter(
	'wp_die_ajax_handler',
	function () {
		return function () {
			throw new PdfJsonDone();
		};
	}
);
$bad = 'javascript:parent.previewExploit=true;void(0)//';
foreach ( array( $bad, 'data:text/html,<script>alert(1)</script>', '//ee.econt.com/p.pdf', '/local.pdf', 'https://user@ee.econt.com/x', admin_url( 'evil.php' ), "https://ee.econt.com/\nfile.pdf", 'https:\\ee.econt.com\\x', null, array(), 12 ) as $url ) {
	pdf_check( Courier_Pdf::url( $url ) === '', 'Unsafe URL rejected' );
	foreach ( array( 'econt' ) as $courier ) { // Main removed CVC (0.17.0).
		$shipment_status = 'econt' === $courier ? array(
			'label' => array(
				'pdfURL'         => $url,
				'shipmentNumber' => '123',
			),
		) : array(
			'pdf' => $url,
			'wb'  => '123',
		);
		$clean           = Courier_Pdf::status( $courier, $shipment_status );
		pdf_check( ( 'econt' === $courier ? $clean['label']['pdfURL'] : $clean['pdf'] ) === '', 'Status PDF rejected' );
		pdf_check( ( 'econt' === $courier ? $clean['label']['shipmentNumber'] : $clean['wb'] ) === '123', 'Shipment identity retained' );
	}
}
pdf_check( Courier_Pdf::url( 'http://ee.econt.com/api_export.php?key=abc' ) === 'https://ee.econt.com/api_export.php?key=abc', 'Legacy HTTP URL upgraded' );
pdf_check( Courier_Pdf::url( 'https://courier-cdn.example/file.pdf' ) === 'https://courier-cdn.example/file.pdf', 'External PDF CDN preserved' );
$query = Courier_Pdf::url( 'https://ee.econt.com/file.pdf?key=a%2Bb&label=old#page=2', '10x9' );
parse_str( wp_parse_url( $query, PHP_URL_QUERY ), $params );
pdf_check(
	array(
		'key'   => 'a+b',
		'label' => '10x9',
	) === $params && wp_parse_url( $query, PHP_URL_FRAGMENT ) === 'page=2',
	'Size replaces query key and preserves token and fragment'
);

$orders = array();
foreach ( array( 'econt' ) as $courier ) { // Main removed CVC (0.17.0).
	$test_order = wc_create_order();
	$orders[]   = $test_order;
	$test_order->set_billing_country( 'BG' );
	$test_order->set_billing_state( 'S' );
	$item = new WC_Order_Item_Shipping();
	$item->set_method_id( 'nashenski_' . $courier );
	$test_order->add_item( $item );
	$test_order->save();
	$_REQUEST = array(
		'nonce'          => wp_create_nonce( 'nashenski_admin_label' ),
		'orderId'        => $test_order->get_id(),
		'shipmentStatus' => array( 'label' => array( 'shipmentNumber' => '123' ) ),
	);
	foreach ( array( $bad, 'https://ee.econt.com/file.pdf?key=test' ) as $value ) {
		$pdf_response = 'econt' === $courier ? array(
			'label' => array(
				'shipmentNumber' => '123',
				'pdfURL'         => $value,
			),
		) : array(
			'success' => true,
			'wb'      => '123',
			'pdf'     => $value,
		);
		$result       = 'econt' === $courier ? \Nashenski\Admin\Econt::send_label_to_econt( array( 'shipmentNumber' => '123' ), $test_order->get_id() ) : \Nashenski\Admin\CVC::send_label_to_cvc( array(), $test_order->get_id() );
		$expected     = Courier_Pdf::url( $value );
		$returned     = 'econt' === $courier ? $result['shipmentStatus']['label']['pdfURL'] : $result['shipmentStatus']['pdf'];
		pdf_check( $returned === $expected, 'Actual generate response normalized: ' . $courier );
		$saved = wc_get_order( $test_order->get_id() );
		$saved->read_meta_data( true );
		$shipment_status = $saved->get_meta( 'nashenski_' . $courier . '_shipment_status' );
		pdf_check( ( 'econt' === $courier ? $shipment_status['label']['pdfURL'] : $shipment_status['pdf'] ) === $expected, 'Actual persisted generate response normalized' );
	}
	// Simulate pre-fix/legacy metadata, then exercise the actual localization boundary.
	$test_order = wc_get_order( $test_order->get_id() );
	$test_order->update_meta_data(
		'nashenski_' . $courier . '_shipment_status',
		'econt' === $courier ? array(
			'label' => array(
				'shipmentNumber' => '123',
				'pdfURL'         => $bad,
			),
		) : array(
			'wb'  => '123',
			'pdf' => $bad,
		)
	);
	$test_order->save();
	$GLOBALS['theorder'] = $test_order;
	wp_register_script( 'nashenski-js-admin', 'https://numbering.invalid/admin.js', array(), '1', false );
	ob_start();
	call_user_func( array( 'Nashenski\\Admin\\' . ( 'econt' === $courier ? 'Econt' : 'CVC' ), 'meta_box' ) );
	ob_end_clean();
	$data = wp_scripts()->get_data( 'nashenski-js-admin', 'data' );
	pdf_check( strpos( $data, 'javascript:' ) === false, 'Actual stored metadata localization excludes executable URL' );
	$column = nashenski_get_order_label( $test_order->get_id() );
	pdf_check( ! isset( $column['link'] ) || strpos( $column['link'], 'javascript:' ) === false, 'Order-list link is safe' );
	if ( 'econt' === $courier ) {
		$pdf_response = array(
			'shipmentNumber' => '123',
			'pdfURL'         => $bad,
		);
		ob_start();
		try {
			\Nashenski\Admin\Econt::update_shipment_status();
		} catch ( PdfJsonDone $e ) {
			// The AJAX handler ends here in place of wp_die(); its output is checked below.
			unset( $e );
		} $json = json_decode( ob_get_clean(), true );
		pdf_check( '' === $json['data']['shipmentStatus']['pdfURL'], 'Actual status-refresh AJAX response normalized' );
		$updated = wc_get_order( $test_order->get_id() );
		$updated->read_meta_data( true );
		pdf_check( '' === $updated->get_meta( 'nashenski_econt_shipment_status' )['label']['pdfURL'], 'Actual status-refresh persistence normalized' );
	}
}
pdf_check( array( 'create', 'update' ) === $pdf_operations, 'Econt creation and update both exercised' );
$imported = wc_create_order();
$orders[] = $imported;
$count    = array( 'waybills' => 0 );
$method   = new ReflectionMethod( \Nashenski\Admin\Import_Sources::class, 'waybill' );
if ( PHP_VERSION_ID < 80100 ) {
	$method->setAccessible( true ); }
$args = array( $imported, 'econt', array( '123' ), $bad, array(), &$count );
$method->invokeArgs( null, $args );
pdf_check( '' === $imported->get_meta( 'nashenski_econt_shipment_status' )['label']['pdfURL'], 'Legacy waybill import excludes executable URL' );
nashenski_set_option( 'apis', 'enable_econt', 'yes' );
// Main enqueues label scripts only on order screens.
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
WP_Screen::get( 'shop_order' )->set_current_screen();
\Nashenski\Plugin::admin_enqueue_scripts();
foreach ( array( 'econt' ) as $courier ) { // Main removed CVC (0.17.0).
	pdf_check( in_array( 'nashenski-courier-pdf', wp_scripts()->registered[ 'nashenski-js-' . $courier ]->deps, true ), 'Helper loads before courier entry' );
}
foreach ( $orders as $test_order ) {
	$test_order->delete( true ); }
echo 'PASS: ' . (int) $checks . " courier PDF PHP integration assertions\n";
