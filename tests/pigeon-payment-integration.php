<?php // phpcs:ignore WordPress.Files.FileName -- offline test script that declares stub classes, not a plugin class file.
/**
 * Offline test: Pigeon payment handling against fixture orders and a stubbed courier client.
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed, Generic.Files.OneObjectStructurePerFile.MultipleFound -- this offline test stubs classes and helper functions in one file.
require __DIR__ . '/courier-pdf.php';
use Nashenski\Admin\Pigeon;
use Nashenski\Shipping\Pigeon\Payment;
use Nashenski\Container\Client;
$payment_start = $checks;
$test_order    = wc_create_order();
$test_order->set_payment_method( 'cod' );
$product = new WC_Order_Item_Product();
$product->set_name( 'Product' );
$product->set_quantity( 1 );
$product->set_total( 100 );
$product->set_subtotal( 100 );
$test_order->add_item( $product );
$shipping = new WC_Order_Item_Shipping();
$shipping->set_method_id( 'nashenski_pigeon' );
$shipping->set_total( 5 );
$test_order->add_item( $shipping );
$test_order->calculate_totals();
$test_order->save();
$_REQUEST = array(
	'nonce'          => wp_create_nonce( 'nashenski_admin_label' ),
	'paymentVersion' => 1,
	'paymentBy'      => array( 'id' => 'fixed' ),
	'cookie_data'    => array( 'fixed_price' => '-7.25' ),
	'fixedPrice'     => '-7.25',
);
$method   = new ReflectionMethod( Pigeon::class, 'update_payment_by' );
if ( PHP_VERSION_ID < 80100 ) {
	$method->setAccessible( true ); }
$rejected = false;
try {
	$method->invoke( null, array( 'service_codes' => array( 'cod_amount' => 100 ) ), $test_order );
} catch ( InvalidArgumentException $error ) {
	$rejected = true; }
pdf_check( $rejected && 5.0 === (float) wc_get_order( $test_order->get_id() )->get_shipping_total(), 'Negative fixed amount must fail before changing saved shipping' );
$test_order->delete( true );
/**
 * Payment fixture client.
 */
class PaymentFixtureClient extends PdfFixtureApi {
	const CREATE_LABEL_ENDPOINT = '/create';
	/**
	 * Api call.
	 *
	 * @param mixed  $endpoint Endpoint.
	 * @param mixed  $body     Body.
	 * @param string $method   Method.
	 * @return mixed
	 */
	public function api_call( $endpoint, $body, $method = '' ) {
		$GLOBALS['payment_calls'][] = $body;
		return $GLOBALS['payment_response']; }
	/**
	 * Get filtered cities.
	 *
	 * @return mixed
	 */
	public function get_filtered_cities() {
		return array(
			'city_key'            => false,
			'cities_search_names' => array(),
		); }
}
/**
 * Payment fixture session.
 */
class PaymentFixtureSession {
	/**
	 * Values.
	 *
	 * @var array
	 */
	private $values = array();
	/**
	 * Get.
	 *
	 * @param mixed $key Key.
	 * @return mixed
	 */
	public function get( $key ) {
		return $this->values[ $key ] ?? null; }
	/**
	 * Set.
	 *
	 * @param mixed $key   Key.
	 * @param mixed $value Value.
	 */
	public function set( $key, $value ) {
		$this->values[ $key ] = $value; }
	/**
	 * Unset.
	 *
	 * @param mixed $key Key.
	 */
	public function __unset( $key ) {
		unset( $this->values[ $key ] ); }
}
$payment_client = new PaymentFixtureClient();
foreach ( array( Client::PIGEON, Client::PIGEON_CITIES, Client::PIGEON_OFFICES, Client::PIGEON_LOCKERS ) as $key ) {
	$pdf_app->clients[ $key ] = $payment_client; }
$payment_response = array(
	'success' => true,
	'data'    => array(
		'reference_number' => '123',
		'label_pdf'        => 'fixture',
	),
);
$payment_calls    = array();
$payment_orders   = array();
nashenski_set_option( 'pigeon', 'send_from', 'office' );
nashenski_set_option( 'pigeon_send_from', 'office', 'officeID-1' );
update_option( 'woocommerce_calc_taxes', 'no' );
/**
 * Payment order.
 *
 * @param string $payment Payment.
 * @param string $payer   Payer.
 * @return mixed
 */
function payment_order( $payment = 'cod', $payer = 'receiver' ) {
	$order = wc_create_order();
	$order->set_payment_method( $payment );
	$order->set_billing_country( 'BG' );
	$order->set_billing_state( 'BG-22' );
	$order->set_billing_city( 'Sofia' );
	$product = new WC_Order_Item_Product();
	$product->set_name( 'Product' );
	$product->set_quantity( 1 );
	$product->set_total( 100 );
	$product->set_subtotal( 100 );
	$order->add_item( $product );
	$shipping = new WC_Order_Item_Shipping();
	$shipping->set_method_id( 'nashenski_pigeon' );
	$shipping->set_total( 5 );
	$shipping->add_meta_data(
		'cookie_data',
		array(
			'type'           => 'office',
			'selectedOffice' => 1,
			'fixed_price'    => '-500',
		)
	);
	$order->add_item( $shipping );
	$order->update_meta_data(
		'nashenski_pigeon_cookie_data',
		array(
			'type'           => 'office',
			'selectedOffice' => 1,
			'fixed_price'    => '-500',
			'payment'        => $payment,
		)
	);
	$order->update_meta_data(
		'nashenski_pigeon_label',
		array(
			'who_pays'         => $payer,
			'sms_notification' => false,
			'service_codes'    => array( 'cod_amount' => '-500' ),
			'packages'         => array( array( 'weight' => 1 ) ),
		)
	);
	$order->calculate_totals();
	$order->save();
	$GLOBALS['payment_orders'][] = $order;
	return $order;
}
/**
 * Payment rpc.
 *
 * @param mixed $order Order.
 * @param array $extra Extra.
 * @return mixed
 */
function payment_rpc( $order, $extra = array() ) {
	$_REQUEST = array_merge(
		array(
			'nonce'          => wp_create_nonce( 'nashenski_admin_label' ),
			'paymentVersion' => 1,
			'orderId'        => $order->get_id(),
			'paymentBy'      => array( 'id' => 'fixed' ),
			'fixedPrice'     => '5',
			'label_data'     => wc_get_order( $order->get_id() )->get_meta( 'nashenski_pigeon_label' ),
			'type'           => array( 'id' => 'office' ),
			'office'         => array( 'id' => 'officeID-1' ),
			'testOption'     => 'no',
			'declaredValue'  => '',
			'cookie_data'    => array( 'fixed_price' => '-500' ),
		),
		$extra
	);
	ob_start();
	try {
		Pigeon::generate_label();
	} catch ( PdfJsonDone $error ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- the handler ends with this exception on purpose.
		// The handler ends with this exception after printing its JSON; the output is read below.
	}
	return json_decode( ob_get_clean(), true );
}
$test_order = payment_order();
$mutations  = 0;
$track      = function () {
	++$GLOBALS['mutations'];
};
add_action( 'woocommerce_before_order_object_save', $track );
add_action( 'woocommerce_before_order_item_object_save', $track );
foreach ( array( '-1', '-0.01', 'NaN', 'INF', '1e309', '1e300', '90071992547409.90', '', 'bad', array( 5 ), true, false, null ) as $value ) {
	$before        = $mutations;
	$payment_calls = array();
	$result        = payment_rpc( $test_order, array( 'fixedPrice' => $value ) );
	pdf_check( false === $result['success'] && $mutations === $before && array() === $payment_calls, 'Invalid fixed amount rejected before writes and API' );
	pdf_check( 5.0 === (float) wc_get_order( $test_order->get_id() )->get_shipping_total(), 'Invalid fixed amount leaves saved shipping unchanged' );
}
foreach ( array( '-1', 'NaN', 'INF', '1e309', array( 1 ), true, false, '' ) as $value ) {
	$before        = $mutations;
	$payment_calls = array();
	$result        = payment_rpc(
		$test_order,
		array(
			'fixedPrice'  => 9,
			'codOverride' => $value,
		)
	);
	pdf_check( false === $result['success'] && $mutations === $before && array() === $payment_calls, 'Invalid final COD rejected before even a valid fixed adjustment is saved' );
}
remove_action( 'woocommerce_before_order_object_save', $track );
remove_action( 'woocommerce_before_order_item_object_save', $track );
$payment_calls = array();
$result        = payment_rpc( $test_order, array( 'paymentVersion' => null ) );
pdf_check( false === $result['success'] && array() === $payment_calls && 5.0 === (float) wc_get_order( $test_order->get_id() )->get_shipping_total(), 'Stale forms require reload rather than silently discarding staff COD intent' );
foreach ( array( -INF, INF, NAN, true, false, array(), new stdClass(), -1, '1e300' ) as $value ) {
	$failed = false;
	try {
		Payment::amount( $value );
	} catch ( InvalidArgumentException $error ) {
		$failed = true; }
	pdf_check( $failed, 'Money parser rejects unsafe native representations' );
}
foreach ( array( 0, '0.00', '5.25', 5.25, '1e2' ) as $value ) {
	pdf_check( is_numeric( Payment::amount( $value ) ), 'Valid zero/decimal amounts retained' ); }

$payment_calls = array();
$result        = payment_rpc( $test_order );
pdf_check( true === $result['success'] && 105.0 === (float) end( $payment_calls )['service_codes']['cod_amount'], 'Ordinary Generate ignores poisoned cookie and label COD, using saved order' );
payment_rpc( $test_order );
pdf_check( 105.0 === (float) end( $payment_calls )['service_codes']['cod_amount'] && 105.0 === (float) wc_get_order( $test_order->get_id() )->get_total(), 'Repeated Generate does not add fixed shipping twice' );
payment_rpc( $test_order, array( 'fixedPrice' => '10' ) );
pdf_check( 110.0 === (float) end( $payment_calls )['service_codes']['cod_amount'], 'Changed fixed shipping is counted once' );
payment_rpc( $test_order, array( 'fixedPrice' => '0' ) );
pdf_check( 100.0 === (float) end( $payment_calls )['service_codes']['cod_amount'] && 0.0 === (float) wc_get_order( $test_order->get_id() )->get_shipping_total(), 'Zero fixed adjustment remains supported' );
payment_rpc(
	$test_order,
	array(
		'fixedPrice'  => '5',
		'codOverride' => '75',
	)
);
pdf_check( 75.0 === (float) end( $payment_calls )['service_codes']['cod_amount'], 'Explicit staff COD is final, without another shipping addition' );
$fresh = wc_get_order( $test_order->get_id() );
$fresh->read_meta_data( true );
pdf_check( $fresh->get_meta( Payment::COD_OVERRIDE_META ) === '75.00', 'Successful explicit override stored as staff-owned state' );
payment_rpc( $test_order );
pdf_check( 75.0 === (float) end( $payment_calls )['service_codes']['cod_amount'], 'Repeat retains the saved explicit staff override' );
payment_rpc( $test_order, array( 'codOverride' => '0' ) );
pdf_check( 0.0 === (float) end( $payment_calls )['service_codes']['cod_amount'], 'Explicit zero COD remains an override' );

$test_order    = payment_order( 'card', 'receiver' );
$payment_calls = array();
payment_rpc( $test_order );
pdf_check( ! isset( end( $payment_calls )['service_codes']['cod_amount'] ) && 'sender' === end( $payment_calls )['who_pays'], 'Prepaid order never inherits a stale COD collection' );
$test_order    = payment_order( 'card', 'sender' );
$payment_calls = array();
payment_rpc(
	$test_order,
	array(
		'paymentBy'  => array( 'id' => 'receiver' ),
		'label_data' => array(
			'who_pays' => 'receiver',
			'packages' => array( array( 'weight' => 1 ) ),
		),
	)
);
pdf_check( 'receiver' === end( $payment_calls )['who_pays'] && ! isset( end( $payment_calls )['service_codes']['cod_amount'] ), 'Staff may choose the receiver as payer on a prepaid order' );
$test_order = payment_order();
$before     = $test_order->get_total();
$other      = new WC_Order_Item_Shipping();
$other->set_method_id( 'flat_rate' );
$other->set_total( 7 );
$test_order->add_item( $other );
$test_order->calculate_totals();
$test_order->save();
payment_rpc( $test_order, array( 'fixedPrice' => '6' ) );
$other->read_meta_data( true );
pdf_check( (float) wc_get_order( $test_order->get_id() )->get_items( 'shipping' )[ $other->get_id() ]->get_total() === 7.0, 'Fixed adjustment leaves another courier shipping line unchanged' );
pdf_check( 106.0 === (float) end( $payment_calls )['service_codes']['cod_amount'], 'Pigeon COD includes its shipping charge once' );

// Old saved cookies cannot seed the staff form; actual price and COD come from the order.
$test_order          = payment_order();
$GLOBALS['theorder'] = $test_order;
$_REQUEST            = array();
ob_start();
Pigeon::meta_box();
ob_end_clean();
preg_match( '/var nashenski_pigeon = ([^\n]+);/', wp_scripts()->get_data( 'nashenski-js-admin', 'data' ), $localized );
$localized = json_decode( $localized[1], true );
pdf_check( ! isset( $localized['cookie_data']['fixed_price'] ) && empty( $localized['fixedPriceEnabled'] ), 'Legacy negative guest price does not become a staff default' );
pdf_check( 5.0 === (float) $localized['fixedPrice'] && 100.0 === (float) $localized['label']['service_codes']['cod_amount'], 'Staff defaults derive from saved Pigeon charge and authoritative COD baseline' );
WC()->session = new PaymentFixtureSession();
WC()->session->set( 'nashenski-pigeon-label', $test_order->get_meta( 'nashenski_pigeon_label' ) );
nashenski_save_checkout_label( $test_order->get_id(), 'pigeon' );
$saved = wc_get_order( $test_order->get_id() );
$saved->read_meta_data( true );
pdf_check( ! isset( $saved->get_meta( 'nashenski_pigeon_cookie_data' )['fixed_price'] ), 'Checkout copy also drops poisoned legacy rate metadata' );
$_REQUEST      = array();
$payment_calls = array();
Pigeon::generate_label_after_order_generated( $test_order->get_id() );
pdf_check( 100.0 === (float) end( $payment_calls )['service_codes']['cod_amount'], 'Automatic generation repairs a poisoned saved COD without request values' );
$test_order    = payment_order( 'cod', 'sender' );
$payment_calls = array();
Pigeon::generate_label_after_order_generated( $test_order->get_id() );
pdf_check( 105.0 === (float) end( $payment_calls )['service_codes']['cod_amount'], 'Automatic sender-paid shipping is included once' );

// Standard tax calculation: staff fixed price retains WooCommerce net-line semantics.
update_option( 'woocommerce_calc_taxes', 'yes' );
update_option( 'woocommerce_tax_based_on', 'billing' );
update_option( 'woocommerce_shipping_tax_class', '' );
$rate       = WC_Tax::_insert_tax_rate(
	array(
		'tax_rate_country'  => 'BG',
		'tax_rate_state'    => '',
		'tax_rate'          => '20',
		'tax_rate_name'     => 'Payment fixture VAT',
		'tax_rate_priority' => 1,
		'tax_rate_compound' => 0,
		'tax_rate_shipping' => 1,
		'tax_rate_order'    => 0,
		'tax_rate_class'    => '',
	)
);
$test_order = payment_order( 'cod', 'sender' );
$before     = $mutations;
add_action( 'woocommerce_before_order_object_save', $track );
add_action( 'woocommerce_before_order_item_object_save', $track );
$payment_calls = array();
$invalid_taxed = payment_rpc( $test_order, array( 'fixedPrice' => '80000000000000' ) );
pdf_check( false === $invalid_taxed['success'] && $mutations === $before && array() === $payment_calls, 'Projected VAT overflow rejected before item/order writes' );
pdf_check( 5.0 === (float) wc_get_order( $test_order->get_id() )->get_shipping_total(), 'Projected VAT failure leaves saved shipping intact' );
remove_action( 'woocommerce_before_order_object_save', $track );
remove_action( 'woocommerce_before_order_item_object_save', $track );
payment_rpc( $test_order, array( 'fixedPrice' => '5' ) );
$saved = wc_get_order( $test_order->get_id() );
pdf_check( (float) $saved->get_shipping_total() === 5.0 && (float) $saved->get_shipping_tax() === 1.0, 'Fixed price remains a net shipping line with WooCommerce tax' );
pdf_check( (float) end( $payment_calls )['service_codes']['cod_amount'] === (float) $saved->get_total(), 'Default COD includes actual order shipping tax' );
payment_rpc( $test_order, array( 'codOverride' => '75' ) );
pdf_check( 75.0 === (float) end( $payment_calls )['service_codes']['cod_amount'], 'Explicit final COD is not increased by shipping VAT' );
update_option( 'woocommerce_shipping_tax_class', 'inherit' );
$test_order = payment_order( 'cod', 'sender' );
payment_rpc( $test_order, array( 'fixedPrice' => '6' ) );
$saved = wc_get_order( $test_order->get_id() );
pdf_check( (float) $saved->get_shipping_total() === 6.0 && (float) $saved->get_shipping_tax() === 1.2, 'Default inherited shipping tax class remains supported' );
pdf_check( (float) end( $payment_calls )['service_codes']['cod_amount'] === (float) $saved->get_total(), 'Projected and saved totals agree with inherited tax rates' );
$test_order = payment_order( 'cod', 'sender' );
$test_order->update_meta_data( 'is_vat_exempt', 'yes' );
$test_order->save();
payment_rpc( $test_order, array( 'fixedPrice' => '6' ) );
$saved = wc_get_order( $test_order->get_id() );
pdf_check( (float) $saved->get_shipping_tax() === 0.0 && (float) end( $payment_calls )['service_codes']['cod_amount'] === (float) $saved->get_total(), 'VAT-exempt order remains exempt in preflight and final COD' );
WC_Tax::_delete_tax_rate( $rate );
update_option( 'woocommerce_calc_taxes', 'no' );

$test_order       = payment_order();
$payment_response = array(
	'success' => false,
	'message' => 'Mocked courier rejection',
);
$result           = payment_rpc(
	$test_order,
	array(
		'fixedPrice'  => '6',
		'codOverride' => '75',
	)
);
$saved            = wc_get_order( $test_order->get_id() );
$saved->read_meta_data( true );
pdf_check( ! $saved->meta_exists( Payment::COD_OVERRIDE_META ) && ! empty( $result['data']['message'] ), 'Failed courier request does not persist a new staff COD override' );
$payment_response = array(
	'success' => true,
	'data'    => array( 'reference_number' => '123' ),
);
foreach ( $payment_orders as $test_order ) {
	$test_order->delete( true ); }
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, WordPress escaping does not apply.
echo 'PASS: ' . ( $checks - $payment_start ) . " Pigeon payment integration assertions\n";
