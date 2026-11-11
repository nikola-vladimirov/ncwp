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
 * Option stub.
 *
 * @param string $name Option name.
 */
function delete_option( $name ) {
	unset( $GLOBALS['options'][ $name ] ); }
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
	/**
	 * Update meta.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value; }
	/**
	 * Delete meta.
	 *
	 * @param string $key Key.
	 */
	public function delete_meta_data( $key ) {
		unset( $this->meta[ $key ] ); }
	/**
	 * Instance ID.
	 *
	 * @return int
	 */
	public function get_instance_id() {
		return 3; }
	/**
	 * Line total.
	 *
	 * @var float
	 */
	public $total = 0.0;
	/**
	 * Set the line total.
	 *
	 * @param float $total Total.
	 */
	public function set_total( $total ) {
		$this->total = $total; }
	/**
	 * Set the taxes.
	 *
	 * @param mixed $taxes Taxes.
	 */
	public function set_taxes( $taxes ) {}
	/**
	 * Save.
	 */
	public function save() {}
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
	 * Order meta: the document number once documents exist.
	 *
	 * @var array
	 */
	public $meta = array();
	/**
	 * Where the order was made.
	 *
	 * @var string
	 */
	public $via = 'admin';
	/**
	 * Delivery country.
	 *
	 * @var string
	 */
	public $country = 'BG';
	/**
	 * Meta.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? ''; }
	/**
	 * Shipping country.
	 *
	 * @return string
	 */
	public function get_shipping_country() {
		return $this->country; }
	/**
	 * Billing country.
	 *
	 * @return string
	 */
	public function get_billing_country() {
		return 'BG'; }
	/**
	 * Created via.
	 *
	 * @return string
	 */
	public function get_created_via() {
		return $this->via; }
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
		return 'shipping' === $type ? $this->lines : ( is_array( $type ) ? $this->goods : array() ); }
	/**
	 * Currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return 'EUR'; }
	/**
	 * Product and fee lines (goods with tax).
	 *
	 * @var array
	 */
	public $goods = array();
	/**
	 * Coupon codes.
	 *
	 * @var array
	 */
	public $coupons = array();
	/**
	 * Coupon codes.
	 *
	 * @return array
	 */
	public function get_coupon_codes() {
		return $this->coupons; }
	/**
	 * Order meta write.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value; }
	/**
	 * Order meta delete.
	 *
	 * @param string $key Key.
	 */
	public function delete_meta_data( $key ) {
		unset( $this->meta[ $key ] ); }
	/**
	 * Save meta.
	 */
	public function save_meta_data() {}
	/**
	 * Totals were calculated again.
	 *
	 * @var int
	 */
	public $recalculated = 0;
	/**
	 * Calculate totals.
	 */
	public function calculate_totals() {
		++$this->recalculated; }
	/**
	 * Save.
	 */
	public function save() {}
	/**
	 * Shipping total.
	 *
	 * @var float
	 */
	public $shipping_total = 0.0;
	/**
	 * Shipping total.
	 *
	 * @return float
	 */
	public function get_shipping_total() {
		return $this->shipping_total; }
}
/**
 * Product line stub: a total with tax.
 */
class FakeGoods {
	/**
	 * Constructor.
	 *
	 * @param float $total Total.
	 */
	public function __construct( $total ) {
		$this->total = $total; }
	/**
	 * Total.
	 *
	 * @var float
	 */
	public $total;
	/**
	 * Total.
	 *
	 * @return float
	 */
	public function get_total() {
		return $this->total; }
	/**
	 * Tax.
	 *
	 * @return float
	 */
	public function get_total_tax() {
		return 0; }
}
/**
 * Coupon stub: FREE gives free shipping.
 */
class WC_Coupon {
	/**
	 * Constructor.
	 *
	 * @param string $code Code.
	 */
	public function __construct( $code ) {
		$this->code = $code; }
	/**
	 * Code.
	 *
	 * @var string
	 */
	public $code;
	/**
	 * Free shipping.
	 *
	 * @return bool
	 */
	public function get_free_shipping() {
		return 'FREE' === $this->code; }
}
/**
 * WooCommerce order class stub.
 */
class WC_Order {
	/**
	 * Order taxes.
	 *
	 * @return array
	 */
	public function get_taxes() {
		return array(); }
}
/**
 * WooCommerce tax class stub.
 */
class WC_Tax {}
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

// Orders without a decision (before 0.21.8, quick and admin orders) keep the "Is ... courier?" rule; the retired option counts no more (R5).
$options['nashenski_settings_invoice_remove_shipping'] = 'yes';
expect( false === Courier_Fee::shop_collects( new FakeOrder( 'cod', $econt() ) ), 'Old order: a COD order of a method marked as a courier drops delivery' );
expect( true === Courier_Fee::shop_collects( new FakeOrder( 'cod', array( new FakeShipping( 'local_pickup' ) ) ) ), 'Old order: a COD order of a method not marked keeps delivery, whatever the retired option says' );
expect( true === Courier_Fee::shop_collects( new FakeOrder( 'bacs', $econt() ) ) && true === Courier_Fee::shop_collects( new FakeOrder( 'stripe', $econt() ) ), 'Old order: other payment methods keep delivery' );
$options['nashenski_settings_shippings_local_pickup_is_courier'] = 'yes';
expect( false === Courier_Fee::shop_collects( new FakeOrder( 'cod', array( new FakeShipping( 'local_pickup' ) ) ) ), 'Old order: a COD order of another method marked as a courier drops delivery' );
$parent = new FakeOrder( 'cod', $econt() );
expect( false === Courier_Fee::shop_collects( new WC_Order_Refund() ), 'A refund of an old COD courier order drops delivery' );
$parent = false;
expect( true === Courier_Fee::shop_collects( new WC_Order_Refund() ), 'A refund without its order: delivery stays' );
unset( $options['nashenski_settings_invoice_remove_shipping'], $options['nashenski_settings_shippings_local_pickup_is_courier'] );

// The list on the method decides per payment method.
expect( Courier_Fee::customer_pays( array( 'cod', 'bacs' ), 'cod' ) && Courier_Fee::customer_pays( array( 'cod', 'bacs' ), 'bacs' ), 'Listed payment methods: the customer pays the courier' );
expect( ! Courier_Fee::customer_pays( array( 'cod' ), 'stripe' ) && ! Courier_Fee::customer_pays( array(), 'cod' ) && ! Courier_Fee::customer_pays( '', 'cod' ), 'Other payment methods and an empty list: the shop collects' );
expect( ! Courier_Fee::customer_pays( array( '' ), '' ), 'No payment method chosen yet: the shop collects' );
$options['woocommerce_nashenski_speedy_4_settings'] = array( 'customer_pays' => array( 'cod' ) );
expect( array( 'cod' ) === Courier_Fee::listed( 'nashenski_speedy', 4 ), 'listed() reads the saved list of a method instance' );
expect( array( 'cod' ) === Courier_Fee::listed( 'nashenski_speedy', 9 ), 'A method never saved has the default list: cash on delivery (M1)' );
$options['woocommerce_nashenski_speedy_8_settings'] = array( 'customer_pays' => '' );
expect( array( '' ) === Courier_Fee::listed( 'nashenski_speedy', 8 ) && ! Courier_Fee::customer_pays( Courier_Fee::listed( 'nashenski_speedy', 8 ), 'cod' ), 'A list saved empty stays empty' );

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
$line = new FakeShipping( 'nashenski_boxnow' );
Courier_Fee::stamp( $line );
expect( 'shop' === $line->get_meta( Courier_Fee::PAYER_META ), 'A BOX NOW line is stamped "shop"' );
$line = new FakeShipping( 'flat_rate' );
Courier_Fee::stamp( $line );
expect( '' === $line->get_meta( Courier_Fee::PAYER_META ), 'A method of another plugin is not stamped: it keeps the "Is ... courier?" rule (H1)' );
$options['nashenski_settings_shippings_flat_rate_is_courier'] = 'yes';
$options['nashenski_settings_invoice_remove_shipping']        = 'no';
expect( false === Courier_Fee::shop_collects( new FakeOrder( 'cod', array( $line ) ) ) && true === Courier_Fee::shop_collects( new FakeOrder( 'bacs', array( $line ) ) ), 'Another plugin\'s method marked as a courier: cash on delivery leaves the delivery out, other payments keep it' );
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
	 * Every method in a zone or on an order.
	 *
	 * @return array
	 */
	public function get_col() {
		return array( 'nashenski_econt', 'nashenski_speedy', 'nashenski_pigeon', 'flat_rate', 'local_pickup' ); }
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
Nashenski\Plugin::upgrade_fee_payer();
expect(
	array(
		'title'         => 'Econt',
		'customer_pays' => array( 'bacs', 'cod' ),
	) === $options['woocommerce_nashenski_econt_3_settings'],
	'"Remove delivery from documents" off: an Econt courier method still gets COD added to its list'
);
expect( array( 'customer_pays' => array( 'cod' ) ) === $options['woocommerce_nashenski_speedy_4_settings'], 'A Speedy courier method without settings gets COD' );
expect( array( 'customer_pays' => array( 'cod' ) ) === $options['woocommerce_nashenski_pigeon_5_settings'], 'Every Pigeon Express method gets COD, marked as a courier or not (its COD waybills were always paid by the receiver)' );
Nashenski\Plugin::upgrade_fee_payer();
expect( array( 'bacs', 'cod' ) === $options['woocommerce_nashenski_econt_3_settings']['customer_pays'], 'Running the upgrade again adds nothing twice' );
$options['nashenski_settings_invoice_remove_shipping'] = 'yes';
unset( $options['woocommerce_nashenski_speedy_4_settings'] );
Nashenski\Plugin::upgrade_fee_payer();
expect( array( 'customer_pays' => array( 'cod' ) ) === $options['woocommerce_nashenski_speedy_4_settings'], '"Remove delivery from documents" on: the same' );
expect( ! isset( $options['nashenski_settings_invoice_remove_shipping'] ) && 'yes' === $options['nashenski_settings_shippings_flat_rate_is_courier'] && 'yes' === $options['nashenski_settings_shippings_local_pickup_is_courier'], 'R5: "Remove delivery from documents" on becomes "Is ... courier?" of every method in a zone or on an order, and the option goes' );
$options['nashenski_settings_invoice_remove_shipping']               = 'yes';
$options['nashenski_settings_shippings_nashenski_speedy_is_courier'] = 'no';
unset( $options['woocommerce_nashenski_speedy_4_settings'] );
Nashenski\Plugin::upgrade_fee_payer();
expect( array( 'customer_pays' => array() ) === $options['woocommerce_nashenski_speedy_4_settings'] && 'yes' === $options['nashenski_settings_shippings_nashenski_speedy_is_courier'], 'R5: a Speedy method not marked as a courier still gets an empty list; its switch is turned on after, for its old orders' );
$options['nashenski_settings_shippings_nashenski_speedy_is_courier'] = 'no';
unset( $options['woocommerce_nashenski_speedy_4_settings'] );
Nashenski\Plugin::upgrade_fee_payer();
expect( array( 'customer_pays' => array() ) === $options['woocommerce_nashenski_speedy_4_settings'], 'An Econt or Speedy method not marked as a courier gets an empty list written, not the new methods\' default' );

// Quick and admin orders (D10): decided on the order screen from the method's list and the payment method.
$options = array(
	'woocommerce_nashenski_econt_3_settings'  => array( 'customer_pays' => array( 'cod' ) ),
	'woocommerce_nashenski_speedy_3_settings' => array(
		'customer_pays' => array( 'cod' ),
		'fixed_price'   => '5',
	),
);
expect( 'customer' === Courier_Fee::decision( 'nashenski_econt', 3, 'cod', 'BG' ) && 'shop' === Courier_Fee::decision( 'nashenski_econt', 3, 'bacs', 'BG' ), 'decision(): the list and the payment method decide' );
expect( 'shop' === Courier_Fee::decision( 'nashenski_econt', 3, 'cod', 'RO' ), 'decision(): abroad the shop collects' );
expect( 'shop' === Courier_Fee::decision( 'nashenski_speedy', 3, 'cod', 'BG' ), 'decision(): Speedy with a fixed price, the shop collects' );
expect( 'shop' === Courier_Fee::decision( 'nashenski_boxnow', 3, 'cod', 'BG' ) && 'shop' === Courier_Fee::decision( 'flat_rate', 3, 'cod', 'BG' ), 'decision(): BOX NOW and other methods, the shop collects' );

$admin            = fn( $meta = array() ) => $econt( $meta );
$fee_order        = new FakeOrder( '', $admin() );
$fee_order->goods = array( new FakeGoods( 20 ) );
Courier_Fee::decide( $fee_order );
expect( '' === $fee_order->lines[0]->get_meta( Courier_Fee::PAYER_META ), 'No payment method yet: nothing is decided (the old rule applies meanwhile)' );
$fee_order->payment = 'cod';
Courier_Fee::decide( $fee_order );
expect( 'customer' === Courier_Fee::payer( $fee_order ) && false === Courier_Fee::shop_collects( $fee_order ), 'Shipping line and payment method: a COD admin order is decided "customer"' );
$fee_order->payment = 'bacs';
Courier_Fee::decide( $fee_order );
expect( 'shop' === Courier_Fee::payer( $fee_order ), 'The payment method changed on the order screen: decided again' );
$options['woocommerce_nashenski_econt_3_settings'] = array( 'customer_pays' => array( 'cod', 'bacs' ) );
Courier_Fee::decide( $fee_order );
expect( 'shop' === Courier_Fee::payer( $fee_order ), 'Nothing changed on the order: a later setting change does not rewrite it' );
$fee_order->payment = 'cod';
$fee_order->meta    = array( 'nashenski_order_number' => '0000000007' );
Courier_Fee::decide( $fee_order );
expect( 'shop' === Courier_Fee::payer( $fee_order ), 'The order has documents: its decision stays' );

// The checkout rules apply on the order screen too (L1, M6).
$options['woocommerce_nashenski_econt_3_settings'] = array(
	'customer_pays'      => array( 'cod' ),
	'free_shipping_over' => '50',
);
expect( 'shop' === Courier_Fee::decision( 'nashenski_econt', 3, 'cod', 'BG', 60.0 ) && 'customer' === Courier_Fee::decision( 'nashenski_econt', 3, 'cod', 'BG', 40.0 ), 'decision(): free shipping over a total is the shop\'s' );
expect( 'shop' === Courier_Fee::decision( 'nashenski_econt', 3, 'cod', 'BG', 40.0, true ), 'decision(): a free shipping coupon makes it the shop\'s' );
expect( 'shop' === Courier_Fee::decision( 'nashenski_econt', 3, 'cod', 'BG', 0.0 ), 'decision(): goods that cost nothing, the shop collects (nothing to pay)' );
$fee_order          = new FakeOrder( 'cod', $admin() );
$fee_order->goods   = array( new FakeGoods( 40 ) );
$fee_order->coupons = array( 'FREE' );
Courier_Fee::decide( $fee_order );
expect( 'shop' === Courier_Fee::payer( $fee_order ), 'decide(): the order\'s free shipping coupon counts' );

// Methods of other plugins are left to the "Is ... courier?" rule.
$fee_order        = new FakeOrder( 'cod', array( new FakeShipping( 'flat_rate' ) ) );
$fee_order->goods = array( new FakeGoods( 20 ) );
Courier_Fee::decide( $fee_order );
expect( '' === Courier_Fee::payer( $fee_order ), 'decide(): another plugin\'s line is not stamped (H1)' );

// Checkout orders keep their checkout decision; staff are told once when the payment method changed it (M3).
$options['woocommerce_nashenski_econt_3_settings'] = array( 'customer_pays' => array( 'cod' ) );
$fee_order        = new FakeOrder( 'cod', $econt( $customer + array( 'cookie_data' => array( 'payment' => 'cod' ) ) ) );
$fee_order->goods = array( new FakeGoods( 20 ) );
expect( '' === Courier_Fee::decide( $fee_order ) && 'customer' === Courier_Fee::payer( $fee_order ), 'A checkout order saved with the same payment method keeps its decision, no notice' );
$fee_order->payment = 'bacs';
$notice             = Courier_Fee::decide( $fee_order );
expect( 'customer' === Courier_Fee::payer( $fee_order ) && false !== strpos( $notice, 'check the delivery line and the waybill by hand' ), 'A checkout order whose payment method changed keeps its decision and staff get the notice' );
expect( '' === Courier_Fee::decide( $fee_order ), 'The notice is shown once for that payment method' );
$fee_order        = new FakeOrder( 'stripe', $econt( $shop + array( 'cookie_data' => array( 'payment' => 'bacs' ) ) ) );
$fee_order->goods = array( new FakeGoods( 20 ) );
expect( false !== strpos( Courier_Fee::decide( $fee_order ), 'check the delivery line' ), 'R6: bank transfer changed to card gives the same decision, but the payment method is not the checkout\'s: notice' );
$fee_order        = new FakeOrder( 'bacs', $econt( $shop + array( 'cookie_data' => array( 'payment' => 'bacs' ) ) ) );
$fee_order->goods = array( new FakeGoods( 20 ) );
expect( '' === Courier_Fee::decide( $fee_order ), 'R6: the checkout\'s payment method: no notice' );

// A waybill made on the order screen brings the delivery price (M4), stored in the store currency like the checkout fee (R7; the order here is in EUR).
$options['woocommerce_currency'] = 'BGN';
$fee_order                       = new FakeOrder( 'cod', $econt( $customer ) );
expect( true === Courier_Fee::keep_total( $fee_order, '7.20' ) && '7.20' === $fee_order->lines[0]->get_meta( Courier_Fee::FEE_META ) && 'BGN' === $fee_order->lines[0]->get_meta( Courier_Fee::CURRENCY_META ), 'Customer pays: the waybill price is kept as information in the store currency, the total stays' );
$fee_order = new FakeOrder( 'cod', $econt( $customer ) );
expect( true === Courier_Fee::keep_total( $fee_order, '0' ) && '' === $fee_order->lines[0]->get_meta( Courier_Fee::FEE_META ), 'A 0.00 price is never stored as paid to the courier' );
$fee_order = new FakeOrder( 'cod', $admin( $shop + array( Courier_Fee::BASIS_META => 'cod' ) ) );
expect( false === Courier_Fee::keep_total( $fee_order, '7.20' ) && false === Courier_Fee::from_checkout( $fee_order ), 'Quick or admin order the shop collects: the waybill price goes into the total, as before' );
$fee_order = new FakeOrder( 'cod', $econt( $shop ) );
expect( true === Courier_Fee::keep_total( $fee_order, '7.20' ) && Courier_Fee::from_checkout( $fee_order ), 'Checkout order the shop collected: its total stays' );
expect( false === Courier_Fee::keep_total( new FakeOrder( 'cod', $econt() ), '7.20' ), 'Order without a decision: the old behaviour' );

// Label boxes (R2, R3): only a waybill where the customer pays the shop for the delivery ("fixed") is refused, and the order follows only after the courier accepted it.
$fee_order       = new FakeOrder( 'cod', $econt( $customer + array( Courier_Fee::FEE_META => '6.50' ) ) );
$fee_order->meta = array( 'nashenski_order_number' => '0000000008' );
expect( '' === Courier_Fee::label_refusal( $fee_order, 'sender' ) && '' === Courier_Fee::label_refusal( $fee_order, 'receiver' ), 'R2: documents made, sender or receiver pays: allowed' );
expect( false !== strpos( Courier_Fee::label_refusal( $fee_order, 'fixed' ), 'already has documents' ), 'R2: documents made, the customer would pay the shop for the delivery: refused' );
Courier_Fee::label_made( $fee_order, 'sender' );
expect( 'customer' === Courier_Fee::payer( $fee_order ) && '' === $fee_order->lines[0]->get_meta( Courier_Fee::FEE_META ), 'R2: the sender pays: the decision stays (the shop absorbs the fee), nothing is shown as paid to the courier' );
$fee_order = new FakeOrder( 'cod', $econt( $customer + array( Courier_Fee::FEE_META => '6.50' ) ) );
expect( '' === Courier_Fee::label_refusal( $fee_order, 'fixed' ) && 'customer' === Courier_Fee::payer( $fee_order ), 'R3: no documents: allowed, and nothing changes before the courier accepts' );
Courier_Fee::label_made( $fee_order, 'fixed' );
expect( 'shop' === Courier_Fee::payer( $fee_order ) && '' === $fee_order->lines[0]->get_meta( Courier_Fee::FEE_META ) && 'yes' === $fee_order->get_meta( Courier_Fee::SWITCHED_META ), 'R3: accepted: the order becomes one the shop collected' );
$fee_order->lines[0]->total = 5.0;
Courier_Fee::label_made( $fee_order, 'receiver' );
expect( 'customer' === Courier_Fee::payer( $fee_order ) && 0 === $fee_order->lines[0]->total && 1 === $fee_order->recalculated && '' === $fee_order->get_meta( Courier_Fee::SWITCHED_META ), 'R3: a later receiver-pays waybill puts the customer back, with the delivery out of the total' );
$fee_order = new FakeOrder( 'cod', $econt( $shop ) );
Courier_Fee::label_made( $fee_order, 'receiver' );
expect( 'shop' === Courier_Fee::payer( $fee_order ) && 0 === $fee_order->recalculated, 'R3: a receiver-pays waybill on an order the shop collected from the start changes nothing (the box warns)' );
$fee_order = new FakeOrder( 'cod', $econt( $customer ) );
Courier_Fee::label_made( $fee_order, '' );
expect( 'customer' === Courier_Fee::payer( $fee_order ), 'A label made without the box changes nothing' );

// L6: the warning for a receiver-pays label on an order whose delivery the shop collected.
$fee_order                 = new FakeOrder( 'cod', $econt( $shop ) );
$fee_order->shipping_total = 5.0;
expect( false !== strpos( Courier_Fee::receiver_notice( $fee_order ), 'pays the delivery twice' ) && '' === Courier_Fee::receiver_notice( new FakeOrder( 'cod', $econt( $customer ) ) ), 'Label boxes warn about the receiver paying only when the shop collected a delivery price' );

// L2: only a customer-pays line says "paid to the courier"; L4: printed in the fee's currency.
$meta = (object) array(
	'key'   => Courier_Fee::FEE_META,
	'value' => '6.50',
);
expect( '6.50' === Courier_Fee::meta_value( '6.50', $meta, new FakeShipping( 'nashenski_econt', $shop ) ), 'The order screen shows a fee on a shop line as a plain value' );
expect( '6,50 BGN, paid to the courier on delivery, not included in the total' === Courier_Fee::meta_value( '6.50', $meta, new FakeShipping( 'nashenski_econt', $customer + array( Courier_Fee::CURRENCY_META => 'BGN' ) ) ), 'The order screen prints the fee in the currency it was stored in' );

// R1: every document path decides first. The Bulgarian PDF builds its order before the number is allocated.
require __DIR__ . '/../app/Invoice/Order/class-order.php';
$options = array( 'woocommerce_nashenski_econt_3_settings' => array( 'customer_pays' => array( 'cod' ) ) );
$options['nashenski_settings_shippings_nashenski_econt_is_courier'] = 'no';
$fee_order        = new FakeOrder( 'cod', $admin() );
$fee_order->goods = array( new FakeGoods( 20 ) );
$document         = new Nashenski\Invoice\Order\Order( $fee_order );
$property         = new ReflectionProperty( $document, 'remove_shipping' );
expect( 'customer' === Courier_Fee::payer( $fee_order ) && 'yes' === $property->getValue( $document ), 'R1: a document of an undecided COD order decides first: the delivery leaves it, as on the NRA file' );

echo 'PASS: ' . $checks . " who pays the courier assertions\n";
