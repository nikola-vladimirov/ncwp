<?php // phpcs:disable WordPress.Security.EscapeOutput, Universal.Files.SeparateFunctionsFromOO, Generic.Files.OneObjectStructurePerFile --offline CLI test script: no WordPress, plain-text output on purpose.
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

// Who pays the courier: the method's list and the payment method in the cookie.
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

/**
 * Order line: a total and its taxes, as WooCommerce keeps them.
 */
class PigeonRestoreLine {
	/**
	 * Total.
	 *
	 * @var string
	 */
	public $total;
	/**
	 * Taxes.
	 *
	 * @var array
	 */
	public $taxes;
	/**
	 * Constructor.
	 *
	 * @param string $total Total.
	 * @param array  $taxes Taxes.
	 */
	public function __construct( $total, $taxes ) {
		$this->total = $total;
		$this->taxes = $taxes; }
	/**
	 * Total.
	 *
	 * @return string
	 */
	public function get_total() {
		return $this->total; }
	/**
	 * Taxes.
	 *
	 * @return array
	 */
	public function get_taxes() {
		return $this->taxes; }
	/**
	 * Set the total.
	 *
	 * @param string $total Total.
	 */
	public function set_total( $total ) {
		$this->total = $total; }
	/**
	 * Set the taxes.
	 *
	 * @param array $taxes Taxes.
	 */
	public function set_taxes( $taxes ) {
		$this->taxes = $taxes; }
	/**
	 * Save.
	 */
	public function save() {}
}
/**
 * Order with goods, a fee and a Pigeon line, and the totals WooCommerce keeps.
 */
class PigeonRestoreOrder {
	/**
	 * Lines by ID.
	 *
	 * @var array
	 */
	public $lines;
	/**
	 * Totals.
	 *
	 * @var array
	 */
	public $totals = array(
		'shipping_total' => '5.00',
		'shipping_tax'   => '1.00',
		'cart_tax'       => '20.00',
		'discount_total' => '0',
		'discount_tax'   => '0',
		'total'          => '126.00',
	);
	/**
	 * Tax lines were built again.
	 *
	 * @var int
	 */
	public $taxes_rebuilt = 0;
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->lines = array(
			11 => new PigeonRestoreLine( '100', array( 'total' => array( 1 => '20' ), 'subtotal' => array( 1 => '20' ) ) ), // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- one line per order line.
			12 => new PigeonRestoreLine( '-5', array( 'total' => array() ) ),
			13 => new PigeonRestoreLine( '5', array( 'total' => array( 1 => '1' ) ) ),
		); }
	/**
	 * Lines.
	 *
	 * @return array
	 */
	public function get_items() {
		return $this->lines; }
	/**
	 * Any total getter.
	 *
	 * @param string $name Method.
	 * @param array  $args Arguments.
	 * @return mixed
	 */
	public function __call( $name, $args ) {
		$key = substr( $name, 4 );
		if ( 0 === strpos( $name, 'set_' ) ) {
			$this->totals[ $key ] = $args[0];
			return null;
		}
		return $this->totals[ $key ]; }
	/**
	 * Tax lines from the lines.
	 */
	public function update_taxes() {
		++$this->taxes_rebuilt; }
	/**
	 * Save.
	 */
	public function save() {}
	/**
	 * What WooCommerce changes when the fixed price is written: the Pigeon line, every line's taxes and the totals.
	 */
	public function write_fixed_price() {
		$this->lines[13]->set_total( '9' );
		$this->lines[13]->set_taxes( array( 'total' => array( 1 => '1.8' ) ) );
		$this->lines[11]->set_taxes( array( 'total' => array( 1 => '19.99' ), 'subtotal' => array( 1 => '20' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- one line per order line.
		$this->lines[12]->set_total( '-4' );
		$this->totals = array_merge(
			$this->totals,
			array(
				'shipping_total' => '9.00',
				'shipping_tax'   => '1.80',
				'total'          => '130.79',
			)
		);
	}
	/**
	 * Every line's total and taxes, and the totals.
	 *
	 * @return array
	 */
	public function state() {
		return array(
			array_map(
				function ( $line ) {
					return array( $line->total, $line->taxes );
				},
				$this->lines
			),
			$this->totals,
		);
	}
}
use Nashenski\Shipping\Pigeon\Payment as PigeonPayment;

// A fixed price label Pigeon refuses: the order goes back exactly as it was.
$restore_order = new PigeonRestoreOrder();
$before        = $restore_order->state();
$data          = PigeonPayment::send(
	$restore_order,
	'fixed',
	function () use ( $restore_order ) {
		$restore_order->write_fixed_price();
		return array( 'message' => 'refused' );
	}
);
expect( array( 'message' => 'refused' ) === $data && $before === $restore_order->state() && 1 === $restore_order->taxes_rebuilt, 'Pigeon refuses a fixed price label: every line, its taxes and the order totals are back as they were' );

// Anything that throws after the price was written: put back, then the error goes on.
$restore_order = new PigeonRestoreOrder();
$thrown        = null;
try {
	PigeonPayment::send(
		$restore_order,
		'fixed',
		function () use ( $restore_order ) {
			$restore_order->write_fixed_price();
			throw new RuntimeException( 'no answer' );
		}
	);
} catch ( RuntimeException $error ) {
	$thrown = $error->getMessage();
}
expect( 'no answer' === $thrown && $before === $restore_order->state(), 'An exception after the fixed price was written: the order is back as it was and the error is not swallowed' );

// Accepted: the fixed price stays.
$restore_order = new PigeonRestoreOrder();
PigeonPayment::send(
	$restore_order,
	'fixed',
	function () use ( $restore_order ) {
		$restore_order->write_fixed_price();
		return array( 'shipmentStatus' => array( 'success' => true ) );
	}
);
expect( '9' === $restore_order->lines[13]->total && '130.79' === $restore_order->totals['total'] && 0 === $restore_order->taxes_rebuilt, 'Pigeon accepts the label: the fixed price stays on the order' );

// Sender or receiver labels write nothing before sending, so there is nothing to put back.
$restore_order = new PigeonRestoreOrder();
$data          = PigeonPayment::send(
	$restore_order,
	'receiver',
	function () {
		return array( 'message' => 'refused' );
	}
);
expect( array( 'message' => 'refused' ) === $data && 0 === $restore_order->taxes_rebuilt, 'A refused sender or receiver label touches nothing' );
echo 'PASS: ' . ( $checks - $payment_start ) . " Pigeon payment assertions\n";
