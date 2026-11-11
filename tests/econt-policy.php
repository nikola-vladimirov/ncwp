<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName, Generic.Classes.DuplicateClassName, Generic.Files.OneObjectStructurePerFile.MultipleFound, Universal.Files.SeparateFunctionsFromOO.Mixed, Generic.CodeAnalysis.UnusedFunctionParameter -- standalone offline test: its stubs live in this file and keep the signatures of what they stand in for.
/**
 * Offline test: Econt service policy. Reuses the offline courier fixture and its packing regression checks.
 * Run: php -d memory_limit=64M tests/econt-policy.php
 *
 * @package Nashenski_Commerce
 */

require __DIR__ . '/econt-resources.php';
$policy_start = $checks;
fixture();
$options['econt/declared_value'] = 'yes';
$cookie['payment']               = 'cod'; // Declared value is a cash on delivery service.
$cookie['selectedOfficeIsAPS']   = true;
$cookie['city']                  = 'Sofia';
$method                          = new EcontMethod();
$method->cookie_data             = $cookie;
$method->package                 = package( 1 );
$method->test                    = 'test';
$cart                            = invoke( $method, 'generate_cart_data' );
expect( isset( $cart['services']['declaredValueAmount'] ), 'No catalogue clients plus a guest APS flag keeps declared value (the flag never changes services)' );
$other = invoke( $method, 'generate_other_data' );
expect( ! empty( $other['payAfterAccept'] ) && ! empty( $other['payAfterTest'] ), 'No catalogue clients plus a guest APS flag keeps review/test' );
$cookie['selectedOfficeIsAPS'] = false;
$method                        = new EcontMethod();
$method->cookie_data           = $cookie;
$method->package               = package( 1 );
$method->test                  = 'test';
expect( isset( invoke( $method, 'generate_cart_data' )['services']['declaredValueAmount'] ) && ! empty( invoke( $method, 'generate_other_data' )['payAfterTest'] ), 'No catalogue clients without a guest APS flag keeps configured services' );
$cookie['selectedOfficeIsAPS'] = true;
/**
 * Econt towns client stub: one town, ID 42.
 */
class EcontPolicyCities {
	/**
	 * Town key the search returns; false for no match.
	 *
	 * @var int|false
	 */
	public $key = 0;

	/**
	 * Regions.
	 *
	 * @param string $country Country code.
	 * @return array
	 */
	public function get_regions( $country ) {
		return array( 'BG-22' => 'Sofia' ); }

	/**
	 * Town search.
	 *
	 * @param string $city    Town.
	 * @param string $state   Region.
	 * @param string $country Country code.
	 * @return array
	 */
	public function get_filtered_cities( $city, $state, $country ) {
		return array(
			'city_key' => $this->key,
			'cities'   => array( array( 'id' => 42 ) ),
		); }
}

/**
 * Econt offices client stub: office 100 is a shop, 200 an automat.
 */
class EcontPolicyOffices {
	/**
	 * Number of office lookups.
	 *
	 * @var int
	 */
	public $calls = 0;

	/**
	 * Office records.
	 *
	 * @var array
	 */
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

	/**
	 * Offices of a town.
	 *
	 * @param int    $city    Town ID.
	 * @param string $country Country code.
	 * @return array
	 */
	public function get_offices( $city, $country ) {
		++$this->calls;
		return array( 'offices' => $this->records ); }

	/**
	 * Offices split into automats and shops.
	 *
	 * @param string $city Town.
	 * @return array
	 */
	public function get_formatted_offices( $city ) {
		return array(
			'aps'   => array( 'officeID-200' => array() ),
			'shops' => array( 'officeID-100' => array() ),
		); }
}

/**
 * Econt method for a COD order to the given office, with the client stubs.
 *
 * @param string $code Office code.
 * @param string $type Delivery type.
 * @return EcontMethod
 */
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
	$method                                     = new EcontMethod( $type );
	$method->test                               = 'test';
	$method->container['client.econt.cities']   = new EcontPolicyCities();
	$method->container['client.econt.offices']  = new EcontPolicyOffices();
	$method->cookie_data                        = $GLOBALS['cookie'];
	$method->package                            = package( 1 );
	return $method;
}
foreach ( array( true, false, 'false', 'yes', null, array( true ) ) as $flag ) {
	foreach ( array( '100', 'officeID-100' ) as $code ) {
		$method                                     = policy_method( $code );
		$method->cookie_data['selectedOfficeIsAPS'] = $flag;
		$cart                                       = invoke( $method, 'generate_cart_data' );
		$other                                      = invoke( $method, 'generate_other_data' );
		expect( 100 === $cart['services']['declaredValueAmount'] && 'EUR' === $cart['services']['declaredValueCurrency'], 'Ordinary catalogue office retains protection for every cookie flag' );
		expect( true === $other['payAfterAccept'] && true === $other['payAfterTest'], 'Ordinary catalogue office retains review/test' );
	}
}
foreach ( array( '200', 'officeID-200' ) as $code ) {
	$method = policy_method( $code );
	unset( $method->cookie_data['selectedOfficeIsAPS'] );
	$cart  = invoke( $method, 'generate_cart_data' );
	$other = invoke( $method, 'generate_other_data' );
	expect( ! isset( $cart['services']['declaredValueAmount'] ) && array() === $other, 'Actual APS code suppresses unsupported services without a client APS flag' );
}
$method = policy_method( '200', 'address' );
$cart   = invoke( $method, 'generate_cart_data' );
$other  = invoke( $method, 'generate_other_data' );
expect( isset( $cart['services']['declaredValueAmount'] ) && ! empty( $other['payAfterTest'] ), 'Address delivery cannot become APS through stale office code/flag' );
expect( 0 === $method->container['client.econt.offices']->calls, 'Address policy requires no office lookup' );
$method = policy_method( '100', 'automat' );
expect( isset( invoke( $method, 'generate_cart_data' )['services']['declaredValueAmount'] ), 'Delivery-type name alone cannot authorize APS exemption' );
$method = policy_method( 'unknown' );
expect( isset( invoke( $method, 'generate_cart_data' )['services']['declaredValueAmount'] ), 'Unknown office cannot waive declared value' );
$method                                        = policy_method( '200' );
$method->container['client.econt.cities']->key = false;
foreach ( array( true, false ) as $flag ) {
	$method->cookie_data['selectedOfficeIsAPS'] = $flag;
	expect( isset( invoke( $method, 'generate_cart_data' )['services']['declaredValueAmount'] ) && ! empty( invoke( $method, 'generate_other_data' )['payAfterTest'] ), 'Failed city search keeps services whatever the guest APS flag says (a city matching nothing cannot waive protection)' );
}
$method->cookie_data['selectedOfficeIsAPS'] = false;
expect( 0 === $method->container['client.econt.offices']->calls, 'Failed city search does not look up a different city' );
$method = policy_method( '200' );
$method->container['client.econt.offices']->records = array();
foreach ( array( true, false ) as $flag ) {
	$method->cookie_data['selectedOfficeIsAPS'] = $flag;
	expect( isset( invoke( $method, 'generate_cart_data' )['services']['declaredValueAmount'] ) && ! empty( invoke( $method, 'generate_other_data' )['payAfterTest'] ), 'A readable city without an office keeps services whatever the guest APS flag says' );
}
// Malformed destination (array field) with the guest flag true or false: services kept either way.
foreach ( array( 'city', 'state', 'country', 'selectedOffice' ) as $field ) {
	foreach ( array( true, false ) as $flag ) {
		$method                                     = policy_method( '200' );
		$method->cookie_data[ $field ]              = array( 'untrusted' );
		$method->cookie_data['selectedOfficeIsAPS'] = $flag;
		expect( isset( invoke( $method, 'generate_cart_data' )['services']['declaredValueAmount'] ) && ! empty( invoke( $method, 'generate_other_data' )['payAfterTest'] ), 'Malformed destination field ' . $field . ' keeps services even with the guest APS flag set' );
	}
}
// Empty or missing city with the guest flag true: services kept.
foreach ( array( '', null ) as $city ) {
	$method                                     = policy_method( '200' );
	$method->cookie_data['city']                = $city;
	$method->cookie_data['selectedOfficeIsAPS'] = true;
	expect( isset( invoke( $method, 'generate_cart_data' )['services']['declaredValueAmount'] ) && ! empty( invoke( $method, 'generate_other_data' )['payAfterTest'] ), 'Empty city keeps services even with the guest APS flag set' );
}
$method                                 = policy_method( '100' );
$options['econt/send_from']             = 'office';
$options['econt_send_from/office_city'] = 'cityID-42';
$options['econt_send_from/office']      = 'officeID-200';
expect( ! isset( invoke( $method, 'generate_cart_data' )['services']['declaredValueAmount'] ), 'Actual sender APS still excludes declared value' );
expect( ! empty( invoke( $method, 'generate_other_data' )['payAfterTest'] ), 'Sender APS does not introduce a new review/test restriction' );
$options['econt_send_from/office'] = 'officeID-100';
expect( isset( invoke( $method, 'generate_cart_data' )['services']['declaredValueAmount'] ), 'Ordinary sender retains declared value' );
foreach ( array( 'no', 'review', 'test' ) as $test ) {
	$method       = policy_method();
	$method->test = $test;
	$data         = invoke( $method, 'generate_other_data' );
	expect( ! empty( $data['payAfterAccept'] ) === ( 'no' !== $test ) && ! empty( $data['payAfterTest'] ) === ( 'test' === $test ), 'Instance review/test choices remain distinct' );
}
foreach ( array( '100', '200' ) as $code ) {
	$method = policy_method( $code );
	$method->calculate_shipping( package( 1 ) );
	$prepared = $wc->session->get( 'nashenski-econt-label' )['label'];
	expect( isset( $prepared['services']['declaredValueAmount'] ) === ( '100' === $code ) && ! empty( $prepared['payAfterTest'] ) === ( '100' === $code ), 'Actual calculation/session label uses catalogue policy despite forged flag' );
	expect( count( $method->rates ) === 1 && 12 === $method->rates[0]['cost'], 'Normal and APS quote remain available after policy enforcement' );
}
$method                                       = policy_method();
$filters['nashenski/econt/calculate_label'][] = function ( $request ) {
	$request['label']['services']['declaredValueAmount'] = 77;
	return $request;
};
$method->calculate_shipping( package( 1 ) );
expect( 77 === $wc->session->get( 'nashenski-econt-label' )['label']['services']['declaredValueAmount'], 'Trusted calculation filter remains after default service generation' );
$method = policy_method();
$cookie[ \Nashenski\Shipping\Econt\Service_Policy::OVERRIDES ] = array( 'payAfterTest' => array( 'present' => false ) );
$method->calculate_shipping( package( 1 ) );
$prepared = $wc->session->get( 'nashenski-econt-label' );
expect( array() === $prepared[ \Nashenski\Shipping\Econt\Service_Policy::OVERRIDES ] && ! empty( $prepared['label']['payAfterTest'] ), 'A shopper cannot populate trusted override provenance through the cookie' );
$method                                       = policy_method();
$filters['nashenski/econt/calculate_label'][] = function ( $request ) {
	unset( $request['label']['services']['declaredValueAmount'], $request['label']['services']['declaredValueCurrency'], $request['label']['payAfterAccept'], $request['label']['payAfterTest'] );
	return $request;
};
$method->calculate_shipping( package( 1 ) );
$prepared = $wc->session->get( 'nashenski-econt-label' );
expect( count( $prepared[ \Nashenski\Shipping\Econt\Service_Policy::OVERRIDES ] ) === 4 && false === $prepared[ \Nashenski\Shipping\Econt\Service_Policy::OVERRIDES ]['payAfterTest']['present'], 'Actual calculation-filter omissions are recorded as trusted changes' );
expect( ! isset( $last_request[ \Nashenski\Shipping\Econt\Service_Policy::OVERRIDES ] ) && ! isset( $prepared['label']['services']['declaredValueAmount'] ), 'Private provenance is not sent to courier and filter omission remains on the quote' );
// E07: a card order gets no declared value on the checkout draft.
$method                         = policy_method( '100' );
$method->cookie_data['payment'] = 'card';
$cart                           = invoke( $method, 'generate_cart_data' );
expect( ! isset( $cart['services']['declaredValueAmount'] ) && ! isset( $cart['services']['declaredValueCurrency'] ) && ! isset( $cart['services']['cdAmount'] ), 'A card order draft carries no declared value' );
/**
 * Order stub for Service_Policy::for_order().
 */
class EcontPolicyOrder {
	/**
	 * Payment method.
	 *
	 * @var string
	 */
	public $payment;

	/**
	 * Sets the payment method.
	 *
	 * @param string $payment Payment method.
	 */
	public function __construct( $payment ) {
		$this->payment = $payment; }

	/**
	 * Payment method.
	 *
	 * @return string
	 */
	public function get_payment_method() {
		return $this->payment; }

	/**
	 * Shipping country.
	 *
	 * @return string
	 */
	public function get_shipping_country() {
		return 'BG'; }

	/**
	 * Billing country.
	 *
	 * @return string
	 */
	public function get_billing_country() {
		return 'BG'; }

	/**
	 * Shipping state.
	 *
	 * @return string
	 */
	public function get_shipping_state() {
		return ''; }

	/**
	 * Billing state.
	 *
	 * @return string
	 */
	public function get_billing_state() {
		return ''; }

	/**
	 * Shipping city.
	 *
	 * @return string
	 */
	public function get_shipping_city() {
		return 'Sofia'; }

	/**
	 * Billing city.
	 *
	 * @return string
	 */
	public function get_billing_city() {
		return 'Sofia'; }

	/**
	 * Currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return 'EUR'; }

	/**
	 * Order items: none.
	 *
	 * @param array $types Item types.
	 * @return array
	 */
	public function get_items( $types ) {
		return array(); }
}
if ( ! function_exists( 'wc_format_decimal' ) ) {
	/**
	 * Stub of the WooCommerce function.
	 *
	 * @param mixed $number Number.
	 * @param int   $dp     Decimals.
	 * @return string
	 */
	function wc_format_decimal( $number, $dp ) {
		return number_format( (float) $number, $dp, '.', '' ); }

	/**
	 * Stub of the WooCommerce function.
	 *
	 * @return int
	 */
	function wc_get_price_decimals() {
		return 2; }
}
$repaired = \Nashenski\Shipping\Econt\Service_Policy::for_order( array( 'services' => array() ), new EcontPolicyOrder( 'card' ), array() );
expect( ! isset( $repaired['services']['declaredValueAmount'] ), 'The order repair adds no declared value to a card order' );
$repaired = \Nashenski\Shipping\Econt\Service_Policy::for_order( array( 'services' => array() ), new EcontPolicyOrder( 'cod' ), array() );
expect( isset( $repaired['services']['declaredValueAmount'] ) && 'EUR' === $repaired['services']['declaredValueCurrency'], 'The order repair still adds declared value to a COD order' );

// Who pays the courier (docs/plans/courier-fee-payer.md): the rate follows the method's list and the payment method.
foreach ( array(
	'cod'  => array( 0, '12.00' ),
	'card' => array( 12, null ),
) as $pay => list( $cost, $fee ) ) {
	fixture();
	$cookie['payment']     = $pay;
	$method                = new EcontMethod();
	$method->customer_pays = array( 'cod' );
	$method->calculate_shipping( package( 1 ) );
	expect( 'cash' === $last_request['label']['paymentReceiverMethod'], "Econt, $pay: the calculation prices what the receiver would pay" );
	expect( $cost === $method->rates[0]['cost'] && ( $method->rates[0]['meta_data']['nashenski_courier_fee'] ?? null ) === $fee, "Econt, COD listed, $pay: the total gets $cost" );
}
fixture();
$cookie['payment']     = 'cod';
$free_coupon           = true;
$method                = new EcontMethod();
$method->customer_pays = array( 'cod' );
$method->calculate_shipping( package( 1 ) );
expect( 0 === $method->rates[0]['cost'] && ! isset( $method->rates[0]['meta_data']['nashenski_courier_fee'] ) && ! isset( $last_request['label']['paymentReceiverMethod'] ), 'Econt free shipping: the sender pays, no fee to the courier' );

/**
 * Plugin stub: the container with the Econt profile.
 *
 * @return object
 */
function nashenski() {
	return new class() {
		/**
		 * Container.
		 *
		 * @return array
		 */
		public function container() {
			return array( 'client.econt.profile' => new EcontProfile() ); }
	};
}
/**
 * Econt shipping line with its checkout decision, on an order of 112.
 */
class EcontPayerLine {
	/**
	 * Decision.
	 *
	 * @var string
	 */
	public $payer;
	/**
	 * Constructor.
	 *
	 * @param string $payer Decision.
	 */
	public function __construct( $payer ) {
		$this->payer = $payer; }
	/**
	 * Meta.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public function get_meta( $key ) {
		return '_nashenski_fee_payer' === $key ? $this->payer : ''; }
	/**
	 * Order total (the line doubles as its order here).
	 *
	 * @return float
	 */
	public function get_total() {
		return 112.0; }
}
$priced = array(
	'paymentReceiverMethod' => 'cash',
	'services'              => array( 'cdAmount' => '100.00' ),
);
$label  = \Nashenski\Shipping\Econt\Method::label_payer( $priced, new EcontPayerLine( 'shop' ), new EcontPayerLine( 'shop' ) );
expect( 'cash' === $label['paymentSenderMethod'] && ! isset( $label['paymentReceiverMethod'] ) && '112.00' === $label['services']['cdAmount'], 'Econt label, shop collects: the sender pays and cash on delivery collects the whole order total' );
expect( \Nashenski\Shipping\Econt\Method::label_payer( $priced, new EcontPayerLine( 'customer' ), new EcontPayerLine( 'customer' ) ) === $priced, 'Econt label, customer pays: the receiver pays as calculated' );
/**
 * Quick or admin COD order of 100 with an Econt shipping line and its order-screen decision.
 */
class EcontDraftOrder {
	/**
	 * Decision.
	 *
	 * @var string
	 */
	public $payer;
	/**
	 * Constructor.
	 *
	 * @param string $payer Decision.
	 */
	public function __construct( $payer ) {
		$this->payer = $payer; }
	/**
	 * Lines: the Econt line when asked for shipping, no products.
	 *
	 * @param string $type Type.
	 * @return array
	 */
	public function get_items( $type = 'line_item' ) {
		return 'shipping' === $type ? array( new EcontPayerLine( $this->payer ) ) : array(); }
	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_formatted_shipping_full_name() {
		return 'Test'; }
	/**
	 * Phone.
	 *
	 * @return string
	 */
	public function get_billing_phone() {
		return '0888000000'; }
	/**
	 * Payment method.
	 *
	 * @return string
	 */
	public function get_payment_method() {
		return 'cod'; }
	/**
	 * Total.
	 *
	 * @return float
	 */
	public function get_total() {
		return 100.0; }
	/**
	 * Shipping total.
	 *
	 * @return float
	 */
	public function get_shipping_total() {
		return 0.0; }
	/**
	 * Shipping tax.
	 *
	 * @return float
	 */
	public function get_shipping_tax() {
		return 0.0; }
	/**
	 * Currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return 'EUR'; }
}
$draft = new ReflectionMethod( '\Nashenski\Admin\Econt', 'draft_label' );
if ( PHP_VERSION_ID < 80100 ) {
	$draft->setAccessible( true );
}
$label = $draft->invoke( null, new EcontDraftOrder( 'shop' ), array() );
expect( 'cash' === $label['paymentSenderMethod'] && ! isset( $label['paymentReceiverMethod'] ), 'Econt box, quick or admin order the shop collects: the sender pays by default' );
$label = $draft->invoke( null, new EcontDraftOrder( 'customer' ), array() );
expect( 'cash' === $label['paymentReceiverMethod'] && ! isset( $label['paymentSenderMethod'] ) && '100.00' === $label['services']['cdAmount'], 'Econt box, customer pays: the receiver pays by default' );
echo 'PASS: ' . (int) ( $checks - $policy_start ) . " Econt service-policy assertions\n";
