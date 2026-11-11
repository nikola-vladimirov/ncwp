<?php // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals -- offline CLI test script: no WordPress, so stubs, unused stub parameters and plain-text output are on purpose.
/**
 * Offline check (0.21.7): a courier delivery with no tax, on an order with no tax at all (goods at
 * 0%, paid by bank transfer), prints at 0% with its full price. Before, documents took 20% out of it
 * (5.25 printed as 4.38 at 20%) and the lines added up to less than the order. With other tax on the
 * order the old rule stays. A refund follows the order it refunds.
 *
 * Run: php tests/shipping-vat.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['options'] = array( 'nashenski_settings_shop_vat_group' => 'b' );

/**
 * Option stub.
 *
 * @param string $name Option.
 * @return mixed
 */
function get_option( $name ) {
	return $GLOBALS['options'][ $name ] ?? '';
}
/**
 * Hook stubs: nothing is hooked.
 *
 * @param string $hook  Hook.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	return $value;
}
/**
 * Deprecated filter stub.
 *
 * @param string $hook Hook.
 * @param array  $args Arguments.
 * @return mixed
 */
function apply_filters_deprecated( $hook, $args ) {
	return $args[0];
}
/**
 * Hook stub.
 */
function add_action() {}
/**
 * Taxes are on in WooCommerce.
 *
 * @return bool
 */
function wc_tax_enabled() {
	return true;
}
/**
 * Price stub.
 *
 * @param float $amount Amount.
 * @return string
 */
function wc_price( $amount ) {
	return number_format( $amount, 2 );
}
/**
 * Translation stub.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text;
}
/**
 * Absolute integer stub.
 *
 * @param mixed $value Value.
 * @return int
 */
function absint( $value ) {
	return abs( (int) $value );
}
/**
 * Order lookup: the refunded order.
 *
 * @return object|false
 */
function wc_get_order() {
	return $GLOBALS['parent'] ?? false;
}
/**
 * Tax calculation stub: the VAT inside a price that includes it.
 */
class WC_Tax {
	/**
	 * Taxes in a price.
	 *
	 * @param float $price Price with VAT.
	 * @param array $rates Rates.
	 * @return array
	 */
	public static function calc_tax( $price, $rates ) {
		return array( $price - $price / ( 1 + $rates[0]['rate'] / 100 ) );
	}
}
/**
 * Refund fixture.
 */
class WC_Order_Refund extends FakeOrder {
	/**
	 * Refunded order.
	 *
	 * @return int
	 */
	public function get_parent_id() {
		return 1;
	}
}
/**
 * Courier delivery line fixture.
 */
class FakeShipping {
	/**
	 * Price.
	 *
	 * @var float
	 */
	public $total = 5.25;
	/**
	 * Tax on the line.
	 *
	 * @var float
	 */
	public $tax = 0;
	/**
	 * Price.
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
		return $this->tax; }
	/**
	 * Quantity.
	 *
	 * @return int
	 */
	public function get_quantity() {
		return 1; }
	/**
	 * Method.
	 *
	 * @return string
	 */
	public function get_method_id() {
		return 'nashenski_econt'; }
	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'Econt'; }
	/**
	 * Meta: no checkout decision on these orders.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public function get_meta( $key ) {
		return ''; }
}
/**
 * Product line fixture: 20% VAT before the discount, none after a 100% coupon.
 */
class FakeProduct {
	/**
	 * Tax before the discount.
	 *
	 * @return float
	 */
	public function get_subtotal_tax() {
		return 4.00; }
	/**
	 * Tax after the discount.
	 *
	 * @return float
	 */
	public function get_total_tax() {
		return 0; }
	/**
	 * Price before the discount.
	 *
	 * @return float
	 */
	public function get_subtotal() {
		return 20.00; }
	/**
	 * Price after the discount.
	 *
	 * @return float
	 */
	public function get_total() {
		return 0; }
	/**
	 * Taxes.
	 *
	 * @return array
	 */
	public function get_taxes() {
		return array(); }
	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'Book'; }
	/**
	 * Quantity.
	 *
	 * @return int
	 */
	public function get_quantity() {
		return 1; }
}
/**
 * Order fixture: a bank transfer order whose only line is the delivery.
 */
class FakeOrder {
	/**
	 * Tax of the whole order.
	 *
	 * @var float
	 */
	public $tax;
	/**
	 * Delivery line.
	 *
	 * @var FakeShipping
	 */
	public $shipping;
	/**
	 * Product lines.
	 *
	 * @var array
	 */
	public $products = array();
	/**
	 * Constructor.
	 *
	 * @param float $tax Tax of the whole order.
	 */
	public function __construct( $tax ) {
		$this->tax      = $tax;
		$this->shipping = new FakeShipping();
	}
	/**
	 * Lines of a type (or of several).
	 *
	 * @param string|array $type Type.
	 * @return array
	 */
	public function get_items( $type = 'line_item' ) {
		if ( 'shipping' === $type ) {
			return array( $this->shipping );
		}
		return is_array( $type ) || 'line_item' === $type ? $this->products : array();
	}
	/**
	 * Tax of the whole order.
	 *
	 * @return float
	 */
	public function get_total_tax() {
		return $this->tax; }
	/**
	 * Tax lines.
	 *
	 * @return array
	 */
	public function get_taxes() {
		return array(); }
	/**
	 * Payment method.
	 *
	 * @return string
	 */
	public function get_payment_method() {
		return 'bacs'; }
	/**
	 * Parent order.
	 *
	 * @return int
	 */
	public function get_parent_id() {
		return 0; }
	/**
	 * Total.
	 *
	 * @return float
	 */
	public function get_total() {
		return 5.25 + $this->tax; }
	/**
	 * Currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return 'EUR'; }
}

require __DIR__ . '/../app/class-hooks.php';
require __DIR__ . '/../app/functions.php';
require __DIR__ . '/../app/Shipping/class-courier-fee.php';
require __DIR__ . '/../app/Invoice/Order/class-order.php';
require __DIR__ . '/../app/Export/Nra/class-order.php';

$fail = array();

/**
 * The delivery line of the NRA audit file for an order.
 *
 * @param object $order Order.
 * @return array
 */
function nra_shipping_line( $order ) {
	$nra             = ( new ReflectionClass( Nashenski\Export\Nra\Order::class ) )->newInstanceWithoutConstructor();
	$nra->woo_order  = $order;
	$nra->vat_group  = 'b';
	$nra->vat_groups = nashenski_get_vat_groups();
	$items           = ( new ReflectionMethod( $nra, 'get_items_from_order' ) )->invoke( $nra );
	return end( $items );
}

/**
 * The delivery line a document prints for an order.
 *
 * @param object $order Order or refund.
 * @return array
 */
function shipping_line( $order ) {
	$items = ( new Nashenski\Invoice\Order\Order( $order ) )->get_items();
	return end( $items );
}

// The helper.
$no_tax   = new FakeOrder( 0 );
$with_tax = new FakeOrder( 4.00 );
if ( ! nashenski_shipping_has_no_vat( $no_tax->shipping, $no_tax ) ) {
	$fail[] = 'no tax on the line or the order: 0%';
}
if ( nashenski_shipping_has_no_vat( $with_tax->shipping, $with_tax ) ) {
	$fail[] = 'other tax on the order: the old rule stays';
}
$taxed_line                = new FakeOrder( 1.05 );
$taxed_line->tax           = 1.05;
$taxed_line->shipping->tax = 1.05;
if ( nashenski_shipping_has_no_vat( $taxed_line->shipping, $taxed_line ) ) {
	$fail[] = 'a taxed delivery is not 0%';
}
$GLOBALS['parent'] = $with_tax;
$refund            = new WC_Order_Refund( 0 );
if ( nashenski_shipping_has_no_vat( $refund->shipping, $refund ) ) {
	$fail[] = 'a refund of an order with tax follows the order, like its invoice';
}
$GLOBALS['parent'] = $no_tax;
if ( ! nashenski_shipping_has_no_vat( $refund->shipping, $refund ) ) {
	$fail[] = 'a refund of an order with no tax prints the delivery at 0%';
}
unset( $GLOBALS['parent'] );
$coupon             = new FakeOrder( 0 );
$coupon->products[] = new FakeProduct();
if ( nashenski_shipping_has_no_vat( $coupon->shipping, $coupon ) ) {
	$fail[] = 'goods at 20% with a 100% coupon: the delivery is not 0%';
}

// The line the NRA audit file lists.
if ( 0 !== nra_shipping_line( new FakeOrder( 0 ) )['vat_rate'] ) {
	$fail[] = 'NRA file, order with no tax: delivery at 0%';
}
if ( '20' !== (string) nra_shipping_line( $coupon )['vat_rate'] ) {
	$fail[] = 'NRA file, goods at 20% with a 100% coupon: delivery at the shop rate, got ' . nra_shipping_line( $coupon )['vat_rate'];
}

// The line a document prints.
$line = shipping_line( new FakeOrder( 0 ) );
if ( 'А - 0%' !== $line['vat_rate'] || '5.25' !== $line['price'] || '5.25' !== $line['total'] ) {
	$fail[] = 'order with no tax: delivery 5.25 at 0%, got ' . $line['price'] . ' at ' . $line['vat_rate'];
}
$line = shipping_line( new FakeOrder( 4.00 ) );
if ( 'Б - 20%' !== $line['vat_rate'] || '4.38' !== $line['price'] ) {
	$fail[] = 'order with other tax: delivery still 4.38 at 20%, got ' . $line['price'] . ' at ' . $line['vat_rate'];
}

if ( $fail ) {
	fwrite( STDERR, 'FAIL: ' . implode( "\nFAIL: ", $fail ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
	exit( 1 );
}
echo "PASS: 10 delivery VAT checks\n";
