<?php // phpcs:disable WordPress.Files.FileName, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO -- offline test: fixtures and the test share one file.
/**
 * Econt service policy integration test. Reuses the dedicated WordPress/WooCommerce site and mocked courier setup.
 *
 * @package Nashenski_Commerce
 */

require __DIR__ . '/courier-pdf.php';
use Nashenski\Container\Client;
use Nashenski\Shipping\Econt\Service_Policy;
use Nashenski\Shipping\Econt\Method;
use Nashenski\Admin\Econt;
$policy_start = $checks;
/**
 * Econt API fixture that records the label requests.
 */
class PolicyIntegrationApi extends PdfFixtureApi {
	/**
	 * Records the request, then answers like the PDF fixture.
	 *
	 * @param string     $operation   Operation.
	 * @param array      $body        Request body.
	 * @param mixed|null $destination Destination.
	 * @return mixed
	 */
	public function label_request( $operation, $body, $destination = null ) {
		$GLOBALS['policy_sent'][] = array( $operation, $body );
		return parent::label_request( $operation, $body, $destination ); }
	/**
	 * Profile data.
	 *
	 * @return array
	 */
	public function get_profile_data() {
		return array(
			'client'    => array( 'name' => 'Sender' ),
			'addresses' => array(),
		); }
	/**
	 * Sender payment method.
	 *
	 * @return string
	 */
	public function get_sender_payment_method() {
		return 'cash'; }
	/**
	 * Regions.
	 *
	 * @return array
	 */
	public function get_regions() {
		return array( 'BG-22' => 'Sofia' ); }
	/**
	 * Filtered cities.
	 *
	 * @return array
	 */
	public function get_filtered_cities() {
		return array(
			'city_key' => 0,
			'cities'   => array( array( 'id' => 42 ) ),
		); }
	/**
	 * Offices: one office and one parcel locker.
	 *
	 * @return array
	 */
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
	/**
	 * Formatted offices.
	 *
	 * @return array
	 */
	public function get_formatted_offices() {
		return array(
			'aps'   => array( 'officeID-200' => array() ),
			'shops' => array( 'officeID-100' => array() ),
		); }
}
/**
 * WooCommerce session fixture.
 */
class PolicyIntegrationSession {
	/**
	 * Session data.
	 *
	 * @var array
	 */
	private $data = array();
	/**
	 * Get.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get( $key ) {
		return $this->data[ $key ] ?? null; }
	/**
	 * Set.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function set( $key, $value ) {
		$this->data[ $key ] = $value; }
	/**
	 * Unset.
	 *
	 * @param string $key Key.
	 */
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
/**
 * Creates an Econt order with a product, a fee and a saved label.
 *
 * @param int    $instance Shipping method instance.
 * @param string $office   Receiver office code, or '' for an address.
 * @return WC_Order
 */
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
	if ( '' !== $office ) {
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
	$test_order = policy_order( $instance );
	$label      = $test_order->get_meta( 'nashenski_econt_label' )['label'];
	$fixed      = Service_Policy::for_order( $label, $test_order, $pdf_app->container() );
	pdf_check( 126.0 === (float) $fixed['services']['declaredValueAmount'], 'Order declared value includes product/fee tax and excludes shipping' );
	pdf_check( 'EUR' === $fixed['services']['declaredValueCurrency'] && true === $fixed['services']['smsNotification'], 'Currency and unrelated services preserved' );
	pdf_check( ! empty( $fixed['payAfterAccept'] ) === ( 'no' !== $test ) && ! empty( $fixed['payAfterTest'] ) === ( 'test' === $test ), 'Actual shipping instance controls review/test' );
}
$test_order = policy_order( 903 );
$no_fees    = function () {
	return false;
};
add_filter( 'nashenski/shipping/package_total_includes_fees', $no_fees );
$label = $test_order->get_meta( 'nashenski_econt_label' )['label'];
pdf_check( 120.0 === (float) Service_Policy::for_order( $label, $test_order, $pdf_app->container() )['services']['declaredValueAmount'], 'Fee-exclusion filter retained' );
remove_filter( 'nashenski/shipping/package_total_includes_fees', $no_fees );
$old_cart    = WC()->cart;
WC()->cart   = null;
$policy_sent = array();
Econt::generate_label_after_order_generated( $test_order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( 126.0 === (float) $sent['services']['declaredValueAmount'] && ! empty( $sent['payAfterTest'] ), 'Actual automatic creation repairs an old poisoned saved label without a cart' );
pdf_check( 'create' === end( $policy_sent )[0], 'Automatic creation reaches courier create path' );
WC()->cart = $old_cart;

// A pre-fix quote already in the session must be repaired at the checkout save boundary.
$test_order   = policy_order( 903 );
WC()->session = new PolicyIntegrationSession();
WC()->session->set( 'nashenski-econt-label', $test_order->get_meta( 'nashenski_econt_label' ) );
Method::save_label_data_to_order( $test_order->get_id() );
$saved = wc_get_order( $test_order->get_id() );
$saved->read_meta_data( true );
$label = $saved->get_meta( 'nashenski_econt_label' )['label'];
pdf_check( 126.0 === (float) $label['services']['declaredValueAmount'] && ! empty( $label['payAfterAccept'] ) && ! empty( $label['payAfterTest'] ), 'Actual checkout save repairs poisoned session quote' );
pdf_check( null === WC()->session->get( 'nashenski-econt-label' ), 'Checkout save still clears the prepared session label' );
foreach ( array( '200', 'officeID-200' ) as $code ) {
	$test_order  = policy_order( 903, $code );
	$policy_sent = array();
	Econt::generate_label_after_order_generated( $test_order->get_id() );
	$sent = end( $policy_sent )[1]['label'];
	pdf_check( ! isset( $sent['services']['declaredValueAmount'] ) && ! isset( $sent['payAfterAccept'] ) && ! isset( $sent['payAfterTest'] ), 'Actual automatic APS destination retains service exclusions for raw/prefixed codes' );
}
$test_order  = policy_order( 903, '' );
$policy_sent = array();
Econt::generate_label_after_order_generated( $test_order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( isset( $sent['services']['declaredValueAmount'] ) && ! empty( $sent['payAfterTest'] ), 'Automatic address delivery ignores forged APS metadata' );
nashenski_set_option( 'econt', 'send_from', 'office' );
nashenski_set_option( 'econt_send_from', 'office', 'officeID-200' );
nashenski_set_option( 'econt_send_from', 'office_city', 'cityID-42' );
$test_order  = policy_order( 903 );
$policy_sent = array();
Econt::generate_label_after_order_generated( $test_order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( ! isset( $sent['services']['declaredValueAmount'] ) && ! empty( $sent['payAfterTest'] ), 'Automatic sender APS excludes declared value without inventing review/test exclusion' );
nashenski_set_option( 'econt', 'send_from', 'courier' );

// Staff explicit choices remain on the manual/common send path and existing shipments.
$test_order                                 = policy_order( 903 );
$label                                      = $test_order->get_meta( 'nashenski_econt_label' )['label'];
$label['services']['declaredValueAmount']   = 77;
$label['services']['declaredValueCurrency'] = 'EUR';
$label['payAfterAccept']                    = false;
$label['payAfterTest']                      = false;
$policy_sent                                = array();
Econt::send_label_to_econt( $label, $test_order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( 77 === $sent['services']['declaredValueAmount'] && false === $sent['payAfterTest'], 'Manual/common send preserves explicit staff overrides' );
$policy_sent = array();
Econt::generate_label_after_order_generated( $test_order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( 77 === $sent['services']['declaredValueAmount'] && false === $sent['payAfterTest'] && 'update' === end( $policy_sent )[0], 'Existing shipment update does not reset staff overrides' );
$test_order = policy_order( 903 );
$override   = function ( $request ) {
	$request['label']['services']['declaredValueAmount'] = 88;
	return $request;
};
add_filter( 'nashenski/econt/create_label', $override );
$policy_sent = array();
Econt::generate_label_after_order_generated( $test_order->get_id() );
remove_filter( 'nashenski/econt/create_label', $override );
pdf_check( 88 === end( $policy_sent )[1]['label']['services']['declaredValueAmount'], 'Trusted create-label filter remains after policy repair' );
$test_order    = policy_order( 903 );
$save_override = function ( $request ) {
	$request['label']['services']['declaredValueAmount'] = 77;
	$request['label']['payAfterTest']                    = false;
	return $request;
};
add_filter( 'nashenski/econt/label_before_save', $save_override );
WC()->session->set( 'nashenski-econt-label', $test_order->get_meta( 'nashenski_econt_label' ) );
Method::save_label_data_to_order( $test_order->get_id() );
remove_filter( 'nashenski/econt/label_before_save', $save_override );
$policy_sent = array();
Econt::generate_label_after_order_generated( $test_order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( 77 === $sent['services']['declaredValueAmount'] && false === $sent['payAfterTest'], 'Explicit saved server-filter overrides survive automatic repair' );
$test_order = policy_order( 903 );
$omit       = function ( $request ) {
	unset( $request['label']['services']['declaredValueAmount'], $request['label']['services']['declaredValueCurrency'], $request['label']['payAfterAccept'], $request['label']['payAfterTest'] );
	return $request;
};
add_filter( 'nashenski/econt/label_before_save', $omit );
WC()->session->set( 'nashenski-econt-label', $test_order->get_meta( 'nashenski_econt_label' ) );
Method::save_label_data_to_order( $test_order->get_id() );
remove_filter( 'nashenski/econt/label_before_save', $omit );
$policy_sent = array();
Econt::generate_label_after_order_generated( $test_order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( ! isset( $sent['services']['declaredValueAmount'] ) && ! isset( $sent['payAfterAccept'] ) && ! isset( $sent['payAfterTest'] ), 'Trusted save-filter omissions survive automatic creation' );
$test_order                         = policy_order( 903 );
$before                             = Service_Policy::for_order( $test_order->get_meta( 'nashenski_econt_label' )['label'], $test_order, $pdf_app->container() );
$after                              = $omit( array( 'label' => $before ) );
$after[ Service_Policy::OVERRIDES ] = Service_Policy::capture_overrides( $before, $after['label'] );
WC()->session->set( 'nashenski-econt-label', $after );
Method::save_label_data_to_order( $test_order->get_id() );
$policy_sent = array();
Econt::generate_label_after_order_generated( $test_order->get_id() );
$sent = end( $policy_sent )[1]['label'];
pdf_check( ! isset( $sent['services']['declaredValueAmount'] ) && ! isset( $sent['payAfterTest'] ), 'Trusted calculation-filter provenance survives checkout and automatic creation' );
pdf_check( ! isset( end( $policy_sent )[1][ Service_Policy::OVERRIDES ] ) && ! isset( $sent[ Service_Policy::OVERRIDES ] ), 'Private provenance remains outside the create request' );
foreach ( $orders as $test_order ) {
	$test_order->delete( true ); }
echo 'PASS: ' . (int) ( $checks - $policy_start ) . " Econt service-policy integration assertions\n";
