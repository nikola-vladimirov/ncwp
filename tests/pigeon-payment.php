<?php // phpcs:disable WordPress.Security.EscapeOutput, Universal.Files.SeparateFunctionsFromOO --offline CLI test script: no WordPress, plain-text output on purpose.
/**
 * Offline test: Pigeon payment data from the guest cookie cannot set money fields. Run: php tests/pigeon-payment.php
 *
 * @package Nashenski_Commerce
 */

require __DIR__ . '/pigeon-resources.php';
$payment_start = $checks;
fixture();
$cookie['fixed_price'] = '-7.25';
$cookie['cod_amount']  = '-100';
$cookie['unknown']     = 'untrusted';
$clean                 = \Nashenski\Shipping\Pigeon\Method::get_cookie_data();
expect( ! isset( $clean['fixed_price'] ) && ! isset( $clean['cod_amount'] ), 'Guest monetary fields must be discarded before rate persistence' );
expect( 1 === $clean['selectedOffice'] && 'card' === $clean['payment'], 'Legitimate picker fields remain' );
$method = new PigeonMethod();
$method->calculate_shipping( package( 1 ) );
expect( ! isset( $method->rates[0]['meta_data']['cookie_data']['fixed_price'] ) && ! isset( $method->rates[0]['meta_data']['cookie_data']['unknown'] ), 'Actual rate metadata contains only allowed picker fields' );
fixture();
$cookie['payment']     = 'cod';
$cookie['fixed_price'] = '-50';
$method                = new PigeonMethod();
$method->fixed_price   = 5;
$method->calculate_shipping( package( 1 ) );
expect( 'sender' === $last_request['who_pays'] && 105.0 === (float) $last_request['service_codes']['cod_amount'], 'Server fixed shipping is still added once to checkout COD' );
expect( 5 === $method->rates[0]['cost'], 'Merchant fixed checkout price preserved' );
fixture();
$cookie['payment']     = 'cod';
$cookie['fixed_price'] = '-50';
$free_coupon           = true;
$method                = new PigeonMethod();
$method->fixed_price   = 5;
$method->calculate_shipping( package( 1 ) );
expect( 'sender' === $last_request['who_pays'] && 100.0 === (float) $last_request['service_codes']['cod_amount'] && 0 === $method->rates[0]['cost'], 'Free shipping still excludes fixed shipping from COD' );
$clean = \Nashenski\Shipping\Pigeon\Payment::cookie(
	array(
		'selectedAddress' => array(
			'orig_key'    => 'street-1',
			'label'       => 'Street',
			'fixed_price' => -99,
		),
		'fixed_price'     => -99,
	)
);
expect(
	array(
		'orig_key' => 'street-1',
		'label'    => 'Street',
	) === $clean['selectedAddress'],
	'Nested address data cannot retain arbitrary monetary fields'
);
expect( array() === \Nashenski\Shipping\Pigeon\Payment::cookie( 'not an object' ), 'Malformed cookie has no authoritative fields' );

// Who pays the courier: the method's list and the payment method in the cookie (docs/plans/courier-fee-payer.md).
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
foreach ( array(
	'cod'  => array( 'receiver', 0, '12.00' ),
	'card' => array( 'sender', 12, null ),
) as $pay => list( $payer, $cost, $fee ) ) {
	fixture();
	$cookie['payment']     = $pay;
	$method                = new PigeonMethod();
	$method->customer_pays = array( 'cod' );
	$method->calculate_shipping( package( 1 ) );
	expect( $payer === $last_request['who_pays'], "Pigeon, COD listed, $pay: the label is paid by the $payer" );
	expect( $cost === $method->rates[0]['cost'] && ( $method->rates[0]['meta_data']['nashenski_courier_fee'] ?? null ) === $fee, "Pigeon, COD listed, $pay: the total gets $cost" );
}
fixture();
$cookie['payment'] = 'cod';
$method            = new PigeonMethod();
$method->calculate_shipping( package( 1 ) );
expect( 'sender' === $last_request['who_pays'] && 12 === $method->rates[0]['cost'], 'Pigeon, empty list, COD: the shop collects and the sender pays' );
fixture();
$cookie['payment']     = 'cod';
$free_coupon           = true;
$method                = new PigeonMethod();
$method->customer_pays = array( 'cod' );
$method->calculate_shipping( package( 1 ) );
expect( 'sender' === $last_request['who_pays'] && ! isset( $method->rates[0]['meta_data']['nashenski_courier_fee'] ), 'Pigeon free shipping: the shop pays, no fee to the courier' );

/**
 * Prepaid order with a Pigeon shipping line and its checkout decision.
 */
class PigeonPayerOrder {
	/**
	 * Decision stored at checkout.
	 *
	 * @var string
	 */
	public $payer;
	/**
	 * Payment method.
	 *
	 * @var string
	 */
	public $method;
	/**
	 * Constructor.
	 *
	 * @param string $payer  Decision.
	 * @param string $method Payment method.
	 */
	public function __construct( $payer, $method = 'bacs' ) {
		$this->payer  = $payer;
		$this->method = $method; }
	/**
	 * Payment method.
	 *
	 * @return string
	 */
	public function get_payment_method() {
		return $this->method; }
	/**
	 * No staff cash on delivery amount.
	 *
	 * @return bool
	 */
	public function meta_exists() {
		return false; }
	/**
	 * Lines.
	 *
	 * @param mixed $type Type.
	 * @return array
	 */
	public function get_items( $type = 'line_item' ) {
		$payer = $this->payer;
		return 'shipping' === $type ? array(
			new class( $payer ) {
				/**
				 * Decision.
				 *
				 * @var string
				 */
				private $payer;
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
				 * Method.
				 *
				 * @return string
				 */
				public function get_method_id() {
					return 'nashenski_pigeon'; }
				/**
				 * Delivery price.
				 *
				 * @return string
				 */
				public function get_total() {
					return '5'; }
				/**
				 * Delivery tax.
				 *
				 * @return string
				 */
				public function get_total_tax() {
					return '0'; }
			},
		) : array();
	}
}
$label = \Nashenski\Shipping\Pigeon\Payment::label( array( 'who_pays' => 'receiver' ), new PigeonPayerOrder( 'customer' ) );
expect( 'receiver' === $label['who_pays'] && ! isset( $label['service_codes']['cod_amount'] ), 'Pigeon: a prepaid order whose customer pays the courier keeps the receiver as payer' );
$label = \Nashenski\Shipping\Pigeon\Payment::label( array( 'who_pays' => 'receiver' ), new PigeonPayerOrder( 'shop' ) );
expect( 'sender' === $label['who_pays'], 'Pigeon: a prepaid order the shop collected is paid by the sender' );
$label = \Nashenski\Shipping\Pigeon\Payment::label( array(), new PigeonPayerOrder( 'shop', 'cod' ) );
expect( 'sender' === $label['who_pays'] && '5.00' === $label['service_codes']['cod_amount'], 'Pigeon: a quick or admin COD order the shop collects starts with the sender as payer, the delivery in the cash on delivery amount' );
$label = \Nashenski\Shipping\Pigeon\Payment::label( array(), new PigeonPayerOrder( 'customer', 'cod' ) );
expect( 'receiver' === $label['who_pays'] && '0.00' === $label['service_codes']['cod_amount'], 'Pigeon: a quick or admin COD order whose customer pays the courier starts with the receiver' );
$label = \Nashenski\Shipping\Pigeon\Payment::label( array(), new PigeonPayerOrder( '', 'cod' ) );
expect( 'receiver' === $label['who_pays'], 'Pigeon: an old COD order without a decision keeps the receiver' );
echo 'PASS: ' . ( $checks - $payment_start ) . " Pigeon payment assertions\n";
