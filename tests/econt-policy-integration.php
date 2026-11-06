<?php
/** Reuse the dedicated WordPress/WooCommerce site and mocked courier setup. */
require __DIR__ . '/courier-pdf.php';
use Nashenski\Container\Client;
use Nashenski\Shipping\Econt\Service_Policy;
use Nashenski\Shipping\Econt\Method;
use Nashenski\Admin\Econt;
$policy_start = $checks;
class PolicyIntegrationApi extends PdfFixtureApi {
	public function label_request( $operation, $body, $destination = null ) {
		$GLOBALS['policy_sent'][] = array( $operation, $body );
		return parent::label_request( $operation, $body, $destination ); }
	public function get_profile_data() {
		return array(
			'client'    => array( 'name' => 'Sender' ),
			'addresses' => array(),
		); }
	public function get_sender_payment_method() {
		return 'cash'; }
	public function get_regions() {
		return array( 'BG-22' => 'Sofia' ); }
	public function get_filtered_cities() {
		return array(
			'city_key' => 0,
			'cities'   => array( array( 'id' => 42 ) ),
		); }
	public function get_offices() {
		return array(
			'offices' => array(
				array(
					'id'    => 200,
					'code'  => '100',
					'isAPS' => false,
				),
				array(
					'id'    => 100,
					'code'  => '200',
					'isAPS' => true,
				),
			),
		); }
	public function get_formatted_offices() {
		return array(
			'aps'   => array( 'officeID-200' => array() ),
			'shops' => array( 'officeID-100' => array() ),
		); }
}
class PolicyIntegrationSession {
	private $data = array();
	public function get( $key ) {
		return $this->data[ $key ] ?? null; }
	public function set( $key, $value ) {
		$this->data[ $key ] = $value; }
	public function __unset( $key ) {
		unset( $this->data[ $key ] ); }
}
$api = new PolicyIntegrationApi();
foreach ( array( Client::ECONT, Client::ECONT_PROFILE, Client::ECONT_CITIES, Client::ECONT_OFFICES ) as $key ) {
	$pdf_app->clients[ $key ] = $api; }
$_REQUEST = array(); // No administrator form choices in automatic operation.
nashenski_set_option( 'econt', 'declared_value', 'yes' );
nashenski_set_option( 'econt', 'send_from', 'courier' );
nashenski_set_option( 'econt', 'pay_options', 'no' );
$pdf_response = array(
	'label' => array(
		'shipmentNumber' => '123',
		'pdfURL'         => 'https://ee.econt.com/test.pdf',
	),
);
$orders       = array();
function policy_order( $instance, $office = '100' ) {
	$order = wc_create_order();
	$order->set_payment_method( 'cod' );
	$order->set_currency( 'EUR' );
	$order->set_billing_country( 'BG' );
	$order->set_billing_state( 'BG-22' );
	$order->set_billing_city( 'Sofia' );
	$product = new WC_Order_Item_Product();
	$product->set_name( 'Test product' );
	$product->set_quantity( 1 );
	$product->set_total( 100 );
	$product->set_subtotal( 100 );
	$product->set_taxes(
		array(
			'total'    => array( 1 => '20' ),
			'subtotal' => array( 1 => '20' ),
		)
	);
	$order->add_item( $product );
	$fee = new WC_Order_Item_Fee();
	$fee->set_name( 'Fee' );
	$fee->set_total( 5 );
	$fee->set_taxes( array( 'total' => array( 1 => '1' ) ) );
	$order->add_item( $fee );
	$shipping = new WC_Order_Item_Shipping();
	$shipping->set_method_id( Method::METHOD_ID );
	$shipping->set_instance_id( $instance );
	$shipping->set_total( 10 );
	$shipping->set_taxes( array( 'total' => array( 1 => '2' ) ) );
	$shipping->add_meta_data(
		'cookie_data',
		array(
			'type'                => 'office',
			'selectedOfficeIsAPS' => true,
			'payment'             => 'card',
			'country'             => 'BG',
		)
	);
	$order->add_item( $shipping );
	$label = array(
		'weight'   => 1,
		'packs'    => array( array( 'weight' => 1 ) ),
		'services' => array( 'smsNotification' => true ),
	);
	if ( $office !== '' ) {
		$label['receiverOfficeCode'] = $office;
	} else {
		$label['receiverAddress'] = array( 'city' => array( 'id' => 42 ) ); }
	$order->update_meta_data( 'nashenski_econt_label', array( 'label' => $label ) );
	$order->calculate_totals( false );
	$order->save();
	$GLOBALS['orders'][] = $order;
	return $order;
}
foreach ( array(
	901 => 'no',
	902 => 'review',
	903 => 'test',
) as $instance => $test ) {
	update_option(
		'woocommerce_nashenski_econt_' . $instance . '_settings',
		array(
			'delivery_type' => 'office',
			'test'          => $test,
		)
	);
	$order = policy_order( $instance );
	$label = $order->get_meta( 'nashenski_econt_label' )['label'];
	$fixed = Service_Policy::for_order( $label, $order, $pdf_app->container() );
	pdf_check( (float) $fixed['services']['declaredValueAmount'] === 126.0, 'Order declared value includes product/fee tax and excludes shipping' );
	pdf_check( $fixed['services']['declaredValueCurrency'] === 'EUR' && $fixed['services']['smsNotification'] === true, 'Currency and unrelated services preserved' );
	pdf_check( ! empty( $fixed['payAfterAccept'] ) === ( $test !== 'no' ) && ! empty( $fixed['payAfterTest'] ) === ( $test === 'test' ), 'Actual shipping instance controls review/test' );
}
$order   = policy_order( 903 );
$no_fees = function () {
	return false;
};
add_filter( 'nashenski/shipping/package_total_includes_fees', $no_fees );
$label = $order->get_meta( 'nashenski_econt_label' )['label'];
pdf_check( (float) Service_Policy::for_order( $label, $order, $pdf_app->container() )['services']['declaredValueAmount'] === 120.0, 'Fee-exclusion filter retained' );
remove_filter( 'nashenski/shipping/package_total_includes_fees', $no_fees );
$old_cart    = WC()->cart;
WC()->cart   = null;
$policy_sent = array();
Econt::generate_label_after_order_generated( $order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( (float) $sent['services']['declaredValueAmount'] === 126.0 && ! empty( $sent['payAfterTest'] ), 'Actual automatic creation repairs an old poisoned saved label without a cart' );
pdf_check( end( $policy_sent )[0] === 'create', 'Automatic creation reaches courier create path' );
WC()->cart = $old_cart;

// A pre-fix quote already in the session must be repaired at the checkout save boundary.
$order        = policy_order( 903 );
WC()->session = new PolicyIntegrationSession();
WC()->session->set( 'nashenski-econt-label', $order->get_meta( 'nashenski_econt_label' ) );
Method::save_label_data_to_order( $order->get_id() );
$saved = wc_get_order( $order->get_id() );
$saved->read_meta_data( true );
$label = $saved->get_meta( 'nashenski_econt_label' )['label'];
pdf_check( (float) $label['services']['declaredValueAmount'] === 126.0 && ! empty( $label['payAfterAccept'] ) && ! empty( $label['payAfterTest'] ), 'Actual checkout save repairs poisoned session quote' );
pdf_check( WC()->session->get( 'nashenski-econt-label' ) === null, 'Checkout save still clears the prepared session label' );
foreach ( array( '200', 'officeID-200' ) as $code ) {
	$order       = policy_order( 903, $code );
	$policy_sent = array();
	Econt::generate_label_after_order_generated( $order->get_id() );
	$sent = end( $policy_sent )[1]['label'];
	pdf_check( ! isset( $sent['services']['declaredValueAmount'] ) && ! isset( $sent['payAfterAccept'] ) && ! isset( $sent['payAfterTest'] ), 'Actual automatic APS destination retains service exclusions for raw/prefixed codes' );
}
$order       = policy_order( 903, '' );
$policy_sent = array();
Econt::generate_label_after_order_generated( $order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( isset( $sent['services']['declaredValueAmount'] ) && ! empty( $sent['payAfterTest'] ), 'Automatic address delivery ignores forged APS metadata' );
nashenski_set_option( 'econt', 'send_from', 'office' );
nashenski_set_option( 'econt_send_from', 'office', 'officeID-200' );
nashenski_set_option( 'econt_send_from', 'office_city', 'cityID-42' );
$order       = policy_order( 903 );
$policy_sent = array();
Econt::generate_label_after_order_generated( $order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( ! isset( $sent['services']['declaredValueAmount'] ) && ! empty( $sent['payAfterTest'] ), 'Automatic sender APS excludes declared value without inventing review/test exclusion' );
nashenski_set_option( 'econt', 'send_from', 'courier' );

// Staff explicit choices remain on the manual/common send path and existing shipments.
$order                                      = policy_order( 903 );
$label                                      = $order->get_meta( 'nashenski_econt_label' )['label'];
$label['services']['declaredValueAmount']   = 77;
$label['services']['declaredValueCurrency'] = 'EUR';
$label['payAfterAccept']                    = false;
$label['payAfterTest']                      = false;
$policy_sent                                = array();
Econt::send_label_to_econt( $label, $order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( $sent['services']['declaredValueAmount'] === 77 && $sent['payAfterTest'] === false, 'Manual/common send preserves explicit staff overrides' );
$policy_sent = array();
Econt::generate_label_after_order_generated( $order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( $sent['services']['declaredValueAmount'] === 77 && $sent['payAfterTest'] === false && end( $policy_sent )[0] === 'update', 'Existing shipment update does not reset staff overrides' );
$order    = policy_order( 903 );
$override = function ( $request ) {
	$request['label']['services']['declaredValueAmount'] = 88;
	return $request;
};
add_filter( 'nashenski/econt/create_label', $override );
$policy_sent = array();
Econt::generate_label_after_order_generated( $order->get_id() );
remove_filter( 'nashenski/econt/create_label', $override );
pdf_check( end( $policy_sent )[1]['label']['services']['declaredValueAmount'] === 88, 'Trusted create-label filter remains after policy repair' );
$order         = policy_order( 903 );
$save_override = function ( $request ) {
	$request['label']['services']['declaredValueAmount'] = 77;
	$request['label']['payAfterTest']                    = false;
	return $request;
};
add_filter( 'nashenski/econt/label_before_save', $save_override );
WC()->session->set( 'nashenski-econt-label', $order->get_meta( 'nashenski_econt_label' ) );
Method::save_label_data_to_order( $order->get_id() );
remove_filter( 'nashenski/econt/label_before_save', $save_override );
$policy_sent = array();
Econt::generate_label_after_order_generated( $order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( $sent['services']['declaredValueAmount'] === 77 && $sent['payAfterTest'] === false, 'Explicit saved server-filter overrides survive automatic repair' );
$order = policy_order( 903 );
$omit  = function ( $request ) {
	unset( $request['label']['services']['declaredValueAmount'], $request['label']['services']['declaredValueCurrency'], $request['label']['payAfterAccept'], $request['label']['payAfterTest'] );
	return $request;
};
add_filter( 'nashenski/econt/label_before_save', $omit );
WC()->session->set( 'nashenski-econt-label', $order->get_meta( 'nashenski_econt_label' ) );
Method::save_label_data_to_order( $order->get_id() );
remove_filter( 'nashenski/econt/label_before_save', $omit );
$policy_sent = array();
Econt::generate_label_after_order_generated( $order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( ! isset( $sent['services']['declaredValueAmount'] ) && ! isset( $sent['payAfterAccept'] ) && ! isset( $sent['payAfterTest'] ), 'Trusted save-filter omissions survive automatic creation' );
$order                              = policy_order( 903 );
$before                             = Service_Policy::for_order( $order->get_meta( 'nashenski_econt_label' )['label'], $order, $pdf_app->container() );
$after                              = $omit( array( 'label' => $before ) );
$after[ Service_Policy::OVERRIDES ] = Service_Policy::capture_overrides( $before, $after['label'] );
WC()->session->set( 'nashenski-econt-label', $after );
Method::save_label_data_to_order( $order->get_id() );
$policy_sent = array();
Econt::generate_label_after_order_generated( $order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( ! isset( $sent['services']['declaredValueAmount'] ) && ! isset( $sent['payAfterTest'] ), 'Trusted calculation-filter provenance survives checkout and automatic creation' );
pdf_check( ! isset( end( $policy_sent )[1][ Service_Policy::OVERRIDES ] ) && ! isset( $sent[ Service_Policy::OVERRIDES ] ), 'Private provenance remains outside the create request' );
foreach ( $orders as $order ) {
	$order->delete( true ); }
echo 'PASS: ' . ( $checks - $policy_start ) . " Econt service-policy integration assertions\n";
