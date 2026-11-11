<?php // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput -- offline CLI test script: no WordPress, so stubs, unused stub parameters and plain-text output are on purpose.
/**
 * Offline: who pays the courier (docs/plans/courier-fee-payer.md). The one rule for documents and
 * the NRA file, the rate the checkout shows, the checkout stamp, the order text and the 0.21.8
 * upgrade of "Remove delivery from documents". Run: php tests/courier-fee-payer.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
$options = array();
/**
 * Option stub.
 *
 * @param string $name          Option name.
 * @param mixed  $default_value Default.
 * @return mixed
 */
function get_option( $name, $default_value = '' ) {
	return $GLOBALS['options'][ $name ] ?? $default_value; }
/**
 * Option stub.
 *
 * @param string $name  Option name.
 * @param mixed  $value Value.
 */
function update_option( $name, $value ) {
	$GLOBALS['options'][ $name ] = $value; }
/**
 * Translation stub.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text; }
/**
 * Price stub.
 *
 * @param mixed $amount Amount.
 * @param array $args   Arguments.
 * @return string
 */
function wc_price( $amount, $args = array() ) {
	return number_format( (float) $amount, 2, ',', '' ) . ' ' . ( '' !== ( $args['currency'] ?? '' ) ? $args['currency'] : 'EUR' ); }
/**
 * Decimal stub.
 *
 * @param mixed $number Number.
 * @param int   $dp     Decimals.
 * @return string
 */
function wc_format_decimal( $number, $dp ) {
	return number_format( (float) $number, $dp, '.', '' ); }
/**
 * Decimals stub.
 *
 * @return int
 */
function wc_get_price_decimals() {
	return 2; }
/**
 * Order lookup stub: always the parent order.
 *
 * @param int $id Order ID.
 * @return object
 */
function wc_get_order( $id ) {
	return $GLOBALS['parent']; }
/**
 * Hook stubs.
 */
function add_action() {}
/**
 * Hook stubs.
 */
function add_filter() {}
require __DIR__ . '/../app/functions.php';
require __DIR__ . '/../app/Shipping/class-courier-fee.php';
require __DIR__ . '/../app/class-plugin.php';

use Nashenski\Shipping\Courier_Fee;

/**
 * Shipping line stub.
 */
class FakeShipping {
	/**
	 * Method ID.
	 *
	 * @var string
	 */
	public $method;
	/**
	 * Meta.
	 *
	 * @var array
	 */
	public $meta = array();
	/**
	 * Constructor.
	 *
	 * @param string $method Method ID.
	 * @param array  $meta   Meta.
	 */
	public function __construct( $method, $meta = array() ) {
		$this->method = $method;
		$this->meta   = $meta; }
	/**
	 * Method ID.
	 *
	 * @return string
	 */
	public function get_method_id() {
		return $this->method; }
	/**
	 * Meta.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? ''; }
	/**
	 * Add meta.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function add_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value; }
}
/**
 * Order stub with a payment method and shipping lines.
 */
class FakeOrder extends WC_Order {
	/**
	 * Payment method.
	 *
	 * @var string
	 */
	public $payment;
	/**
	 * Shipping lines.
	 *
	 * @var array
	 */
	public $lines;
	/**
	 * Constructor.
	 *
	 * @param string $payment Payment method.
	 * @param array  $lines   Shipping lines.
	 */
	public function __construct( $payment, $lines ) {
		$this->payment = $payment;
		$this->lines   = $lines; }
	/**
	 * Payment method.
	 *
	 * @return string
	 */
	public function get_payment_method() {
		return $this->payment; }
	/**
	 * Lines of a type.
	 *
	 * @param string $type Type.
	 * @return array
	 */
	public function get_items( $type ) {
		return 'shipping' === $type ? $this->lines : array(); }
	/**
	 * Currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return 'EUR'; }
}
/**
 * WooCommerce order class stub.
 */
class WC_Order {}
/**
 * Refund stub: only the parent ID is read.
 */
class WC_Order_Refund {
	/**
	 * Parent order ID.
	 *
	 * @return int
	 */
	public function get_parent_id() {
		return 1; }
}

$checks = 0;
/**
 * Assertion: throws when the condition fails.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @throws RuntimeException When the condition is false.
 */
function expect( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
}
$econt    = fn( $meta = array() ) => array( new FakeShipping( 'nashenski_econt', $meta ) );
$customer = array( Courier_Fee::PAYER_META => 'customer' );
$shop     = array( Courier_Fee::PAYER_META => 'shop' );

// The decision stored at checkout wins, whatever the payment method or the old settings say.
$options['nashenski_settings_invoice_remove_shipping']              = 'yes';
$options['nashenski_settings_shippings_nashenski_econt_is_courier'] = 'yes';
expect( false === Courier_Fee::shop_collects( new FakeOrder( 'cod', $econt( $customer ) ) ), 'Customer paid the courier: delivery leaves the documents' );
expect( false === Courier_Fee::shop_collects( new FakeOrder( 'bacs', $econt( $customer ) ) ), 'Customer paid the courier with a bank transfer order: delivery leaves the documents' );
expect( true === Courier_Fee::shop_collects( new FakeOrder( 'cod', $econt( $shop ) ) ), 'Shop collected on a COD order: delivery stays, even with the old setting on' );
expect( true === Courier_Fee::shop_collects( new FakeOrder( 'stripe', $econt( $shop ) ) ), 'Shop collected on a card order: delivery stays' );
expect( true === Courier_Fee::shop_collects( new FakeOrder( 'cod', array( new FakeShipping( 'flat_rate', $shop ) ) ) ), 'Any other method stamped at checkout: the shop collected' );
expect( 'customer' === Courier_Fee::payer( new FakeOrder( 'cod', $econt( $customer ) ) ) && '' === Courier_Fee::payer( new FakeOrder( 'cod', $econt() ) ), 'payer() reads the stored decision, empty without one' );

// Credit notes follow their order.
$parent = new FakeOrder( 'cod', $econt( $customer ) );
expect( false === Courier_Fee::shop_collects( new WC_Order_Refund() ), 'A refund of a customer-pays order leaves delivery out' );
$parent = new FakeOrder( 'cod', $econt( $shop ) );
expect( true === Courier_Fee::shop_collects( new WC_Order_Refund() ), 'A refund of a shop-collects order keeps delivery' );

// Orders without a decision (before 0.21.8, quick and admin orders) keep the retired rule.
$options['nashenski_settings_invoice_remove_shipping'] = 'yes';
expect( false === Courier_Fee::shop_collects( new FakeOrder( 'cod', $econt() ) ), 'Old order, setting on: a COD courier order drops delivery' );
expect( false === Courier_Fee::shop_collects( new FakeOrder( 'cod', array( new FakeShipping( 'local_pickup' ) ) ) ), 'Old order, setting on: a COD order of another method drops delivery' );
expect( true === Courier_Fee::shop_collects( new FakeOrder( 'bacs', $econt() ) ), 'Old order, setting on: a bank transfer order keeps delivery' );
$options['nashenski_settings_invoice_remove_shipping'] = 'no';
expect( false === Courier_Fee::shop_collects( new FakeOrder( 'cod', $econt() ) ), 'Old order, setting off: a COD courier order still drops delivery' );
expect( true === Courier_Fee::shop_collects( new FakeOrder( 'cod', array( new FakeShipping( 'local_pickup' ) ) ) ), 'Old order, setting off: a COD order of another method keeps delivery' );
expect( true === Courier_Fee::shop_collects( new FakeOrder( 'stripe', $econt() ) ), 'Old order, setting off: a card order keeps delivery' );
$parent = new FakeOrder( 'cod', $econt() );
expect( false === Courier_Fee::shop_collects( new WC_Order_Refund() ), 'A refund of an old COD courier order drops delivery' );
$parent = false;
expect( true === Courier_Fee::shop_collects( new WC_Order_Refund() ), 'A refund without its order, setting off: delivery stays' );

// The list on the method decides per payment method.
expect( Courier_Fee::customer_pays( array( 'cod', 'bacs' ), 'cod' ) && Courier_Fee::customer_pays( array( 'cod', 'bacs' ), 'bacs' ), 'Listed payment methods: the customer pays the courier' );
expect( ! Courier_Fee::customer_pays( array( 'cod' ), 'stripe' ) && ! Courier_Fee::customer_pays( array(), 'cod' ) && ! Courier_Fee::customer_pays( '', 'cod' ), 'Other payment methods and an empty list: the shop collects' );
expect( ! Courier_Fee::customer_pays( array( '' ), '' ), 'No payment method chosen yet: the shop collects' );
$options['woocommerce_nashenski_speedy_4_settings'] = array( 'customer_pays' => array( 'cod' ) );
expect( array( 'cod' ) === Courier_Fee::listed( 'nashenski_speedy', 4 ) && array() === Courier_Fee::listed( 'nashenski_speedy', 9 ), 'listed() reads the saved list of a method instance' );

// The rate: the fee leaves the total and stays for information; free or unpriced rates stay as they are.
$rate = Courier_Fee::rate(
	array(
		'cost'      => 10,
		'taxes'     => array( 1 => 2 ),
		'meta_data' => array( 'delivery_type' => 'office' ),
	)
);
expect( 0 === $rate['cost'] && ! isset( $rate['taxes'] ), 'Customer pays: the rate adds nothing to the total' );
expect( '12.00' === $rate['meta_data'][ Courier_Fee::FEE_META ] && 'customer' === $rate['meta_data'][ Courier_Fee::PAYER_META ], 'Customer pays: the fee with VAT stays on the rate, with the decision' );
$free = array(
	'cost'      => 0,
	'meta_data' => array( 'free_shipping' => true ),
);
expect( Courier_Fee::rate( $free ) === $free, 'Free shipping: nothing to pay the courier, so no info text and the shop pays' );
$unpriced = array( 'meta_data' => array() );
expect( Courier_Fee::rate( $unpriced ) === $unpriced, 'Not priced yet: unchanged' );

// Checkout stamp: anything without a decision was collected by the shop.
$line = new FakeShipping( 'flat_rate' );
Courier_Fee::stamp( $line );
expect( 'shop' === $line->get_meta( Courier_Fee::PAYER_META ), 'A line without a decision is stamped "shop"' );
$line = new FakeShipping( 'nashenski_econt', $customer );
Courier_Fee::stamp( $line );
expect( 'customer' === $line->get_meta( Courier_Fee::PAYER_META ), 'A courier line keeps "customer"' );

// Emails, thank-you page and My Account.
$text = Courier_Fee::order_shipping( 'Econt', new FakeOrder( 'cod', $econt( $customer + array( Courier_Fee::FEE_META => '6.50' ) ) ) );
expect( 'Econt: 6,50 EUR, paid to the courier on delivery, not included in the total' === $text, 'The order shows the fee as paid to the courier: ' . $text );
expect( 'Econt' === Courier_Fee::order_shipping( 'Econt', new FakeOrder( 'cod', $econt( $shop ) ) ), 'A shop-collects order shows its shipping as before' );

// Upgrade (D6): "Remove delivery from documents" on puts COD into the list of courier methods.
$GLOBALS['wpdb'] = new class() { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- offline stand-in for the zone methods table.
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';
	/**
	 * Prepare.
	 *
	 * @param string $query Query.
	 * @return string
	 */
	public function prepare( $query ) {
		return $query; }
	/**
	 * Zone methods.
	 *
	 * @return array
	 */
	public function get_results() {
		return array(
			(object) array(
				'method_id'   => 'nashenski_econt',
				'instance_id' => 3,
			),
			(object) array(
				'method_id'   => 'nashenski_speedy',
				'instance_id' => 4,
			),
			(object) array(
				'method_id'   => 'nashenski_pigeon',
				'instance_id' => 5,
			),
		);
	}
};
$options         = array(
	'nashenski_settings_invoice_remove_shipping' => 'no',
	'nashenski_settings_shippings_nashenski_econt_is_courier' => 'yes',
	'nashenski_settings_shippings_nashenski_speedy_is_courier' => 'yes',
	'woocommerce_nashenski_econt_3_settings'     => array(
		'title'         => 'Econt',
		'customer_pays' => array( 'bacs' ),
	),
);
$before          = $options;
Nashenski\Plugin::upgrade_fee_payer();
expect( $before === $options, 'Setting off: the lists stay as they were (the shop collects everywhere)' );
$options['nashenski_settings_invoice_remove_shipping'] = 'yes';
Nashenski\Plugin::upgrade_fee_payer();
expect(
	array(
		'title'         => 'Econt',
		'customer_pays' => array( 'bacs', 'cod' ),
	) === $options['woocommerce_nashenski_econt_3_settings'],
	'Setting on: an Econt courier method gets COD added to its list'
);
expect( array( 'customer_pays' => array( 'cod' ) ) === $options['woocommerce_nashenski_speedy_4_settings'], 'Setting on: a Speedy courier method without settings gets COD' );
expect( ! isset( $options['woocommerce_nashenski_pigeon_5_settings'] ), 'A method not marked as a courier is left alone' );
Nashenski\Plugin::upgrade_fee_payer();
expect( array( 'bacs', 'cod' ) === $options['woocommerce_nashenski_econt_3_settings']['customer_pays'], 'Running the upgrade again adds nothing twice' );

echo 'PASS: ' . $checks . " who pays the courier assertions\n";
