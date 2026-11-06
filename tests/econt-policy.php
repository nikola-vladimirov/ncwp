<?php
/** Reuse the existing offline courier fixture and its packing regression checks. */
require __DIR__ . '/econt-resources.php';
$policy_start = $checks;
fixture();
$options['econt/declared_value'] = 'yes';
$cookie['payment']               = 'cod'; // Declared value is a cash on delivery service.
$cookie['selectedOfficeIsAPS']   = true;
$cookie['city']                  = 'Sofia';
$m                               = new EcontMethod();
$m->cookie_data                  = $cookie;
$m->package                      = package( 1 );
$m->test                         = 'test';
$cart                            = invoke( $m, 'generate_cart_data' );
expect( isset( $cart['services']['declaredValueAmount'] ), 'No catalogue clients plus a guest APS flag keeps declared value (the flag never changes services)' );
$other = invoke( $m, 'generate_other_data' );
expect( ! empty( $other['payAfterAccept'] ) && ! empty( $other['payAfterTest'] ), 'No catalogue clients plus a guest APS flag keeps review/test' );
$cookie['selectedOfficeIsAPS'] = false;
$m                             = new EcontMethod();
$m->cookie_data                = $cookie;
$m->package                    = package( 1 );
$m->test                       = 'test';
expect( isset( invoke( $m, 'generate_cart_data' )['services']['declaredValueAmount'] ) && ! empty( invoke( $m, 'generate_other_data' )['payAfterTest'] ), 'No catalogue clients without a guest APS flag keeps configured services' );
$cookie['selectedOfficeIsAPS'] = true;
class EcontPolicyCities {
	public $key = 0;
	public function get_regions( $country ) {
		return array( 'BG-22' => 'Sofia' ); }
	public function get_filtered_cities( $city, $state, $country ) {
		return array(
			'city_key' => $this->key,
			'cities'   => array( array( 'id' => 42 ) ),
		); }
}
class EcontPolicyOffices {
	public $calls   = 0;
	public $records = array(
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
	);
	public function get_offices( $city, $country ) {
		++$this->calls;
		return array( 'offices' => $this->records ); }
	public function get_formatted_offices( $city ) {
		return array(
			'aps'   => array( 'officeID-200' => array() ),
			'shops' => array( 'officeID-100' => array() ),
		); }
}
function policy_method( $code = '100', $type = 'office' ) {
	fixture();
	$GLOBALS['options']['econt/declared_value'] = 'yes';
	$GLOBALS['cookie']                          = array_merge(
		$GLOBALS['cookie'],
		array(
			'payment'             => 'cod',
			'country'             => 'BG',
			'city'                => 'Sofia',
			'state'               => 'BG-22',
			'type'                => $type,
			'selectedOffice'      => $code,
			'selectedOfficeIsAPS' => true,
		)
	);
	$m                                    = new EcontMethod( $type );
	$m->test                              = 'test';
	$m->container['client.econt.cities']  = new EcontPolicyCities();
	$m->container['client.econt.offices'] = new EcontPolicyOffices();
	$m->cookie_data                       = $GLOBALS['cookie'];
	$m->package                           = package( 1 );
	return $m;
}
foreach ( array( true, false, 'false', 'yes', null, array( true ) ) as $flag ) {
	foreach ( array( '100', 'officeID-100' ) as $code ) {
		$m                                     = policy_method( $code );
		$m->cookie_data['selectedOfficeIsAPS'] = $flag;
		$cart                                  = invoke( $m, 'generate_cart_data' );
		$other                                 = invoke( $m, 'generate_other_data' );
		expect( $cart['services']['declaredValueAmount'] === 100 && $cart['services']['declaredValueCurrency'] === 'EUR', 'Ordinary catalogue office retains protection for every cookie flag' );
		expect( $other['payAfterAccept'] === true && $other['payAfterTest'] === true, 'Ordinary catalogue office retains review/test' );
	}
}
foreach ( array( '200', 'officeID-200' ) as $code ) {
	$m = policy_method( $code );
	unset( $m->cookie_data['selectedOfficeIsAPS'] );
	$cart  = invoke( $m, 'generate_cart_data' );
	$other = invoke( $m, 'generate_other_data' );
	expect( ! isset( $cart['services']['declaredValueAmount'] ) && $other === array(), 'Actual APS code suppresses unsupported services without a client APS flag' );
}
$m     = policy_method( '200', 'address' );
$cart  = invoke( $m, 'generate_cart_data' );
$other = invoke( $m, 'generate_other_data' );
expect( isset( $cart['services']['declaredValueAmount'] ) && ! empty( $other['payAfterTest'] ), 'Address delivery cannot become APS through stale office code/flag' );
expect( $m->container['client.econt.offices']->calls === 0, 'Address policy requires no office lookup' );
$m = policy_method( '100', 'automat' );
expect( isset( invoke( $m, 'generate_cart_data' )['services']['declaredValueAmount'] ), 'Delivery-type name alone cannot authorize APS exemption' );
$m = policy_method( 'unknown' );
expect( isset( invoke( $m, 'generate_cart_data' )['services']['declaredValueAmount'] ), 'Unknown office cannot waive declared value' );
$m                                        = policy_method( '200' );
$m->container['client.econt.cities']->key = false;
foreach ( array( true, false ) as $flag ) {
	$m->cookie_data['selectedOfficeIsAPS'] = $flag;
	expect( isset( invoke( $m, 'generate_cart_data' )['services']['declaredValueAmount'] ) && ! empty( invoke( $m, 'generate_other_data' )['payAfterTest'] ), 'Failed city search keeps services whatever the guest APS flag says (a city matching nothing cannot waive protection)' );
}
$m->cookie_data['selectedOfficeIsAPS'] = false;
expect( $m->container['client.econt.offices']->calls === 0, 'Failed city search does not look up a different city' );
$m = policy_method( '200' );
$m->container['client.econt.offices']->records = array();
foreach ( array( true, false ) as $flag ) {
	$m->cookie_data['selectedOfficeIsAPS'] = $flag;
	expect( isset( invoke( $m, 'generate_cart_data' )['services']['declaredValueAmount'] ) && ! empty( invoke( $m, 'generate_other_data' )['payAfterTest'] ), 'A readable city without an office keeps services whatever the guest APS flag says' );
}
// Malformed destination (array field) with the guest flag true or false: services kept either way.
foreach ( array( 'city', 'state', 'country', 'selectedOffice' ) as $field ) {
	foreach ( array( true, false ) as $flag ) {
		$m                                     = policy_method( '200' );
		$m->cookie_data[ $field ]              = array( 'untrusted' );
		$m->cookie_data['selectedOfficeIsAPS'] = $flag;
		expect( isset( invoke( $m, 'generate_cart_data' )['services']['declaredValueAmount'] ) && ! empty( invoke( $m, 'generate_other_data' )['payAfterTest'] ), 'Malformed destination field ' . $field . ' keeps services even with the guest APS flag set' );
	}
}
// Empty or missing city with the guest flag true: services kept.
foreach ( array( '', null ) as $city ) {
	$m                                     = policy_method( '200' );
	$m->cookie_data['city']                = $city;
	$m->cookie_data['selectedOfficeIsAPS'] = true;
	expect( isset( invoke( $m, 'generate_cart_data' )['services']['declaredValueAmount'] ) && ! empty( invoke( $m, 'generate_other_data' )['payAfterTest'] ), 'Empty city keeps services even with the guest APS flag set' );
}
$m                                      = policy_method( '100' );
$options['econt/send_from']             = 'office';
$options['econt_send_from/office_city'] = 'cityID-42';
$options['econt_send_from/office']      = 'officeID-200';
expect( ! isset( invoke( $m, 'generate_cart_data' )['services']['declaredValueAmount'] ), 'Actual sender APS still excludes declared value' );
expect( ! empty( invoke( $m, 'generate_other_data' )['payAfterTest'] ), 'Sender APS does not introduce a new review/test restriction' );
$options['econt_send_from/office'] = 'officeID-100';
expect( isset( invoke( $m, 'generate_cart_data' )['services']['declaredValueAmount'] ), 'Ordinary sender retains declared value' );
foreach ( array( 'no', 'review', 'test' ) as $test ) {
	$m       = policy_method();
	$m->test = $test;
	$data    = invoke( $m, 'generate_other_data' );
	expect( ! empty( $data['payAfterAccept'] ) === ( $test !== 'no' ) && ! empty( $data['payAfterTest'] ) === ( $test === 'test' ), 'Instance review/test choices remain distinct' );
}
foreach ( array( '100', '200' ) as $code ) {
	$m = policy_method( $code );
	$m->calculate_shipping( package( 1 ) );
	$prepared = $wc->session->get( 'nashenski-econt-label' )['label'];
	expect( isset( $prepared['services']['declaredValueAmount'] ) === ( $code === '100' ) && ! empty( $prepared['payAfterTest'] ) === ( $code === '100' ), 'Actual calculation/session label uses catalogue policy despite forged flag' );
	expect( count( $m->rates ) === 1 && $m->rates[0]['cost'] === 12, 'Normal and APS quote remain available after policy enforcement' );
}
$m = policy_method();
$filters['nashenski/econt/calculate_label'][] = function ( $request ) {
	$request['label']['services']['declaredValueAmount'] = 77;
	return $request;
};
$m->calculate_shipping( package( 1 ) );
expect( $wc->session->get( 'nashenski-econt-label' )['label']['services']['declaredValueAmount'] === 77, 'Trusted calculation filter remains after default service generation' );
$m = policy_method();
$cookie[ \Nashenski\Shipping\Econt\Service_Policy::OVERRIDES ] = array( 'payAfterTest' => array( 'present' => false ) );
$m->calculate_shipping( package( 1 ) );
$prepared = $wc->session->get( 'nashenski-econt-label' );
expect( $prepared[ \Nashenski\Shipping\Econt\Service_Policy::OVERRIDES ] === array() && ! empty( $prepared['label']['payAfterTest'] ), 'A shopper cannot populate trusted override provenance through the cookie' );
$m = policy_method();
$filters['nashenski/econt/calculate_label'][] = function ( $request ) {
	unset( $request['label']['services']['declaredValueAmount'], $request['label']['services']['declaredValueCurrency'], $request['label']['payAfterAccept'], $request['label']['payAfterTest'] );
	return $request;
};
$m->calculate_shipping( package( 1 ) );
$prepared = $wc->session->get( 'nashenski-econt-label' );
expect( count( $prepared[ \Nashenski\Shipping\Econt\Service_Policy::OVERRIDES ] ) === 4 && $prepared[ \Nashenski\Shipping\Econt\Service_Policy::OVERRIDES ]['payAfterTest']['present'] === false, 'Actual calculation-filter omissions are recorded as trusted changes' );
expect( ! isset( $last_request[ \Nashenski\Shipping\Econt\Service_Policy::OVERRIDES ] ) && ! isset( $prepared['label']['services']['declaredValueAmount'] ), 'Private provenance is not sent to courier and filter omission remains on the quote' );
// E07: a card order gets no declared value on the checkout draft.
$m                         = policy_method( '100' );
$m->cookie_data['payment'] = 'card';
$cart                      = invoke( $m, 'generate_cart_data' );
expect( ! isset( $cart['services']['declaredValueAmount'] ) && ! isset( $cart['services']['declaredValueCurrency'] ) && ! isset( $cart['services']['cdAmount'] ), 'A card order draft carries no declared value' );
class EcontPolicyOrder {
	public $payment;
	public function __construct( $payment ) {
		$this->payment = $payment; }
	public function get_payment_method() {
		return $this->payment; }
	public function get_shipping_country() {
		return 'BG'; }
	public function get_billing_country() {
		return 'BG'; }
	public function get_shipping_state() {
		return ''; }
	public function get_billing_state() {
		return ''; }
	public function get_shipping_city() {
		return 'Sofia'; }
	public function get_billing_city() {
		return 'Sofia'; }
	public function get_currency() {
		return 'EUR'; }
	public function get_items( $types ) {
		return array(); }
}
if ( ! function_exists( 'wc_format_decimal' ) ) {
	function wc_format_decimal( $number, $dp ) {
		return number_format( (float) $number, $dp, '.', '' ); }
	function wc_get_price_decimals() {
		return 2; }
}
$repaired = \Nashenski\Shipping\Econt\Service_Policy::for_order( array( 'services' => array() ), new EcontPolicyOrder( 'card' ), array() );
expect( ! isset( $repaired['services']['declaredValueAmount'] ), 'The order repair adds no declared value to a card order' );
$repaired = \Nashenski\Shipping\Econt\Service_Policy::for_order( array( 'services' => array() ), new EcontPolicyOrder( 'cod' ), array() );
expect( isset( $repaired['services']['declaredValueAmount'] ) && 'EUR' === $repaired['services']['declaredValueCurrency'], 'The order repair still adds declared value to a COD order' );
echo 'PASS: ' . ( $checks - $policy_start ) . " Econt service-policy assertions\n";
