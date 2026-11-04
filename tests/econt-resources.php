<?php
/** Offline regressions: php -d memory_limit=64M tests/econt-resources.php */
namespace Nashenski\Pricing {
	class Price_Lists {
		public static function get( $courier ) {
			$GLOBALS['public_quotes']++;
			return array( 'bands' => array( array( 'to' => 1000000, 'office' => 9, 'locker' => 9, 'address' => 9 ) ), 'surcharge' => 0, 'vat_included' => true, 'cod' => array( 'percent' => 0 ) );
		}
	}
}
namespace {
define( 'ABSPATH', __DIR__ . '/fixtures/' );
class WC_Shipping_Method {
	public $title = 'Econt';
	public $id = 'nashenski_econt';
	public $instance_id = 1;
	public $rates = array();
	public function add_rate( $rate ) { $this->rates[] = $rate; }
}
require __DIR__ . '/../vendor/autoload.php';
set_error_handler( function( $severity, $message, $file, $line ) { throw new \ErrorException( $message, 0, $severity, $file, $line ); } );

use Nashenski\Shipping\Packer\Packing_Budget;
use Nashenski\Shipping\Packer\Packing_Limit_Exceeded;
use Nashenski\Shipping\Econt\APSBoxes;

$options = $filters = array(); $checks = 0;
function nashenski_get_option( $group, $key ) { return $GLOBALS['options'][ $group . '/' . $key ] ?? ''; }
function wc_string_to_bool( $value ) { return in_array( $value, array( true, 1, '1', 'yes' ), true ); }
function __( $value, $domain = '' ) { return $value; }
function esc_html( $value ) { return $value; }
function get_option( $key, $default = '' ) { return $key === 'woocommerce_dimension_unit' ? 'cm' : $default; }
function wc_get_weight( $value, $to ) { return (float) $value; }
function wc_get_dimension( $value, $to, $from = 'cm' ) { return $from === $to ? (float) $value : (float) $value * ( $to === 'mm' ? 10 : 0.1 ); }
function nashenski_normalize_text_for_label( $value, $limit = 0 ) { return $limit ? substr( $value, 0, $limit ) : $value; }
function nashenski_courier_cookie( $key ) { return $GLOBALS['cookie']; }
function nashenski_has_free_shipping_coupon_in_cart() { return $GLOBALS['free_coupon']; }
function nashenski_get_package_total() { return 100; }
function nashenski_tax_based_price( $value, $tax = 20 ) { return $value; }
function nashenski_format_phone( $value, $country = '' ) { return $value; }
function get_woocommerce_currency() { return 'EUR'; }
function wc_tax_enabled() { return false; }
function do_action( $hook, ...$args ) {}
function WC() { return $GLOBALS['wc']; }
function apply_filters( $hook, $value, ...$args ) {
	foreach ( $GLOBALS['filters'][ $hook ] ?? array() as $callback ) { $value = $callback( $value, ...$args ); }
	return $value;
}
function expect( $value, $message ) { $GLOBALS['checks']++; if ( ! $value ) { throw new \RuntimeException( $message ); } }
function invoke( $object, $method, ...$args ) {
	$r = new \ReflectionMethod( $object, $method );
	if ( PHP_VERSION_ID < 80100 ) { $r->setAccessible( true ); }
	return $r->invoke( is_object( $object ) ? $object : null, ...$args );
}
function rejected( $callback ) {
	try { $callback(); } catch ( Packing_Limit_Exceeded $e ) { return true; }
	return false;
}
class WC_Product {
	private $weight, $dimensions;
	public $dimension_reads = 0;
	public $test_read_limit = PHP_INT_MAX;
	public function __construct( $weight = 1, array $dimensions = array( 1, 1, 1 ) ) { $this->weight = $weight; $this->dimensions = $dimensions; }
	public function get_weight() { return $this->weight; }
	public function get_length() {
		if ( ++$this->dimension_reads > $this->test_read_limit ) { throw new \RuntimeException( 'Test stopped unsafe per-unit expansion before resource exhaustion.' ); }
		return $this->dimensions[0];
	}
	public function get_width() { return $this->dimensions[1]; }
	public function get_height() { return $this->dimensions[2]; }
	public function get_name() { return 'Product'; }
}
class EcontSession {
	public $data = array( 'chosen_shipping_methods' => array( 'nashenski_econt:1' ) );
	public function get( $key ) { return $this->data[ $key ] ?? null; }
	public function set( $key, $value ) { $this->data[ $key ] = $value; }
}
class EcontMethod extends \Nashenski\Shipping\Econt\Method {
	public function __construct( $type = 'office' ) {
		$this->delivery_type = $type; $this->test = 'no'; $this->sms = 'no';
		$this->container = array( 'client.econt' => new EcontCourier(), 'client.econt.profile' => new EcontProfile() );
	}
}
class EcontProfile {
	public function get_profile_data() { return array( 'client' => array( 'name' => 'Sender' ) ); }
	public function get_sender_payment_method() { return 'cash'; }
}
class EcontCourier {
	public function label_request( $mode, $request, $destination = array() ) {
		$GLOBALS['label_calls']++; $GLOBALS['last_request'] = $request;
		if ( ! $GLOBALS['connected'] ) { return null; }
		$GLOBALS['api_calls']++;
		return $GLOBALS['api_error'] ? array( 'type' => 'ExException', 'message' => 'Offline fixture' ) : array( 'label' => array( 'receiverDueAmount' => 12, 'senderDueAmount' => 12 ) );
	}
}
function package( $quantity, $weight = 1, array $dimensions = array( 1, 1, 1 ) ) {
	return array( 'contents' => array( array( 'quantity' => $quantity, 'data' => new WC_Product( $weight, $dimensions ) ) ), 'destination' => array( 'country' => 'BG' ) );
}
function fixture( $auto = false ) {
	$GLOBALS['options'] = $GLOBALS['filters'] = array();
	$GLOBALS['options']['econt/auto_size'] = $auto ? 'yes' : 'no';
	$GLOBALS['public_quotes'] = $GLOBALS['api_calls'] = $GLOBALS['label_calls'] = 0;
	$GLOBALS['free_coupon'] = false; $GLOBALS['api_error'] = false;
	$GLOBALS['connected'] = true; $GLOBALS['last_request'] = null;
	$GLOBALS['cookie'] = array( 'type' => 'office', 'selectedOffice' => 'officeID-1', 'payment' => 'card', 'country' => 'BG', 'receiver' => 'Test', 'phone' => '0888000000' );
	$GLOBALS['wc'] = (object) array( 'session' => new EcontSession() );
	if ( $auto ) { $GLOBALS['filters']['nashenski/econt/calculate_label'][] = array( APSBoxes::class, 'calculate_label' ); }
	$GLOBALS['filters']['nashenski/econt/calculate_label'][] = array( 'Nashenski\\Admin\\Econt', 'set_min_pack_weight' );
}
fixture(); $options['econt/auto_size'] = 'yes';
$m = new EcontMethod(); $m->cookie_data = $cookie; $m->package = package( 2000 );
// Abort the vulnerable implementation after 100 reads instead of exhausting the test process.
$m->package['contents'][0]['data']->test_read_limit = 100;
expect( rejected( function() use ( $m ) { invoke( $m, 'generate_cart_data' ); } ), 'Large quantities must be rejected before any per-unit expansion' );

fixture();
$m = new EcontMethod(); $m->calculate_shipping( package( 3, 0.5 ) );
expect( 1 === count( $m->rates ) && 12 === $m->rates[0]['cost'], 'Normal connected quote must remain available' );
expect( 1 === $api_calls && 0 === $public_quotes, 'Normal quote must reach the courier once without public fallback' );
$normal_label = $last_request['label'];
expect( array( array( 'width' => 17, 'height' => 17, 'length' => 17, 'weight' => 1.5 ) ) === $normal_label['packs'], 'Default dimensions and aggregate weight must remain unchanged' );
expect( 1 === $normal_label['packCount'] && 'Product' === $normal_label['shipmentDescription'], 'Default pack count and description must remain unchanged' );

foreach ( array( false, true ) as $disconnected ) {
	fixture(); $connected = ! $disconnected; $api_error = ! $disconnected;
	$m = new EcontMethod(); $m->calculate_shipping( package( 1 ) );
	expect( 1 === count( $m->rates ) && 9.0 === $m->rates[0]['cost'] && 1 === $public_quotes, 'Ordinary API/login failures must retain public-price fallback' );
}

foreach ( array( false, true ) as $auto ) {
	foreach ( array( 'office', 'automat' ) as $type ) {
		if ( ! $auto && $type === 'office' ) { continue; } // No quantity-dependent packing in this mode.
		fixture( $auto ); $cookie['type'] = $type;
		$filters['nashenski/econt/rate/disable_aps'][] = function() { return false; };
		$m = new EcontMethod( $type ); $m->fixed_price = 5; $m->free_shipping = true; $free_coupon = true;
		$wc->session->set( 'nashenski-econt-label', array( 'stale' => true ) );
		if ( function_exists( 'memory_reset_peak_usage' ) ) { memory_reset_peak_usage(); }
		$memory = memory_get_usage(); $start = microtime( true );
		$m->calculate_shipping( package( '1e300' ) );
		expect( ! $m->rates && ! $label_calls && ! $public_quotes, 'Huge packing quantities must not reach API or free/fixed/public fallback' );
		expect( null === $wc->session->get( 'nashenski-econt-label' ), 'Rejected preparation must clear stale session labels' );
		expect( memory_get_peak_usage() - $memory < 2 * 1024 * 1024 && microtime( true ) - $start < 2, 'Huge quantities must be rejected before memory/time amplification' );
		expect( rejected( function() use ( $m ) { $m->calculate_shipping_price_from_api(); } ), 'A rejected instance must remain rejected for direct API preparation' );
		$m->rates = array(); $m->free_shipping = false; $m->fixed_price = 0; $free_coupon = false;
		$m->calculate_shipping( package( 1 ) );
		expect( 1 === count( $m->rates ), 'A fresh calculation with a normal cart must reset the rejected state' );
	}
}

fixture(); $cookie = array(); $wc->session->data['chosen_shipping_methods'] = array();
$m = new EcontMethod( 'automat' ); $m->calculate_shipping( package( PHP_INT_MAX ) );
expect( ! $m->rates && ! $label_calls, 'Locker expansion must be bounded even before selecting a destination or enabling auto sizing' );

foreach ( array( 'office', 'address', 'automat' ) as $type ) {
	fixture( true ); $cookie['type'] = $type;
	$m = new EcontMethod( $type ); $m->cookie_data = $cookie; $m->package = package( 3, 0.5, array( 2, 3, 4 ) );
	$data = invoke( $m, 'generate_cart_data' );
	if ( $type !== 'automat' ) {
		$products = array_fill( 0, 3, new \Nashenski\Shipping\Packer\Product( 'Product', new \Nashenski\Shipping\Packer\Size( 20, 30, 40 ) ) );
		$expected = ( new \Nashenski\Shipping\Packer\Carton_Packer() )->find_best_carton( $products );
		expect( $data['packs'][0]['width'] === wc_get_dimension( $expected->W, 'cm', 'mm' ) && $data['packs'][0]['height'] === wc_get_dimension( $expected->H, 'cm', 'mm' ) && $data['packs'][0]['length'] === wc_get_dimension( $expected->L, 'cm', 'mm' ), 'Budget must preserve normal office/address carton dimensions' );
	} else {
		expect( 17 === $data['packs'][0]['width'], 'Locker cart data must retain its existing fallback dimensions before the APS filter' );
	}
	expect( 1.5 === $data['weight'] && 1 === $data['packCount'], 'Normal automatic cart weight/count must be preserved' );
}

fixture( true ); $cookie['type'] = 'automat';
$m = new EcontMethod( 'automat' ); $m->calculate_shipping( package( 3, 0.5, array( 2, 3, 4 ) ) );
expect( 1 === count( $m->rates ) && $last_request['label']['packs'][0]['width'] !== 17, 'Normal locker quote must retain its APS-filtered dimensions' );
expect( 1.5 === $last_request['label']['weight'], 'Normal locker packs must retain aggregate weight' );

fixture( true );
$m = new EcontMethod(); $m->calculate_shipping( package( 50, 0.01 ) );
expect( 1 === count( $m->rates ), 'A normal fifty-unit homogeneous cart must still quote' );

foreach ( array( 'office', 'automat' ) as $type ) {
	fixture( true ); $cookie['type'] = $type;
	$p = package( 600, 0.001 ); $p['contents'][] = $p['contents'][0];
	$m = new EcontMethod( $type ); $m->calculate_shipping( $p );
	expect( ! $m->rates && ! $label_calls, 'Expanded-unit limits must be cumulative across cart lines' );
}

fixture( true );
$p = package( 1 ); $p['contents'] = array();
for ( $i = 0; $i < 1000; $i++ ) { $p['contents'][] = array( 'quantity' => 1, 'data' => new WC_Product( 0.001, array( 1 + $i % 7, 2 + $i % 11, 3 + $i % 13 ) ) ); }
$m = new EcontMethod(); $start = microtime( true ); $m->calculate_shipping( $p );
expect( ! $m->rates && ! $label_calls && microtime( true ) - $start < 5, 'Mixed-product candidate/search work must stop before an unbounded quote calculation' );

fixture( true ); $m = new EcontMethod( 'automat' ); $m->package = package( PHP_INT_MAX );
expect( rejected( function() use ( $m ) { APSBoxes::calculate_label( array( 'label' => array( 'packCount' => 1 ) ), $m ); } ), 'The second APS pass must enforce quantity limits and propagate rejection' );
$other_method = (object) array( 'delivery_type' => 'automat', 'package' => package( PHP_INT_MAX ) );
expect( rejected( function() use ( $other_method ) { APSBoxes::calculate_label( array( 'label' => array( 'packCount' => 1 ) ), $other_method ); } ), 'Direct APS filters must be bounded without an Econt Method context' );

fixture( true ); $m = new EcontMethod( 'automat' ); $m->package = package( 3 );
$property = new \ReflectionProperty( 'Nashenski\\Shipping\\Econt\\Method', 'packing_budget' );
if ( PHP_VERSION_ID < 80100 ) { $property->setAccessible( true ); }
$property->setValue( $m, new Packing_Budget( 1 ) );
expect( rejected( function() use ( $m ) { APSBoxes::calculate_label( array( 'label' => array( 'packCount' => 1 ) ), $m ); } ), 'The second APS pass must use the existing calculation budget, not reset it' );

fixture( true ); $m = new EcontMethod( 'automat' ); $m->package = package( 1, 1, array( 100, 100, 100 ) );
$request = array( 'label' => array( 'packCount' => 1, 'packs' => array( array( 'width' => 17 ) ) ) );
expect( $request === APSBoxes::calculate_label( $request, $m ), 'Ordinary non-resource APS packing failures must keep the existing label fallback' );

fixture( true );
$filters['nashenski/econt/rate'][] = function( $rate, $method ) {
	try { $method->packing_budget()->spend( Packing_Budget::MAX_WORK + 1 ); } catch ( Packing_Limit_Exceeded $e ) {}
	return $rate;
};
$m = new EcontMethod(); $m->calculate_shipping( package( 1 ) );
expect( ! $m->rates && null === $wc->session->get( 'nashenski-econt-label' ), 'Swallowing a budget exception in a rate filter must not restore availability or a stale label' );

fixture( true );
array_unshift( $filters['nashenski/econt/calculate_label'], function( $body, $method ) {
	try { $method->packing_budget()->spend( Packing_Budget::MAX_WORK + 1 ); } catch ( Packing_Limit_Exceeded $e ) {}
	return $body;
} );
$m = new EcontMethod(); $m->fixed_price = 3; $m->free_shipping = true; $free_coupon = true;
$m->calculate_shipping( package( 1 ) );
expect( ! $m->rates && ! $label_calls && ! $public_quotes, 'A swallowed label-filter rejection must stop before client or price fallback' );

fixture( true ); $connected = false;
$m = new EcontMethod(); $m->cookie_data = $cookie; $m->package = package( PHP_INT_MAX );
expect( rejected( function() use ( $m ) { $m->calculate_shipping_price_from_api(); } ) && ! $label_calls, 'Direct API preparation must initialize and enforce a budget before the disconnected client' );

fixture( true );
foreach ( array( INF, NAN, '1e999', 'invalid' ) as $quantity ) {
	$m = new EcontMethod(); $m->calculate_shipping( package( $quantity, 0 ) );
	expect( ! $m->rates && ! $label_calls, 'Malformed/nonfinite quantities must not reach packing allocation' );
}
fixture(); $cookie['type'] = 'automat';
$m = new EcontMethod( 'automat' ); $m->calculate_shipping( package( NAN, 0 ) );
expect( ! $m->rates && ! $label_calls, 'The early APS path must reject NaN before integer or boolean coercion' );
fixture(); $m = new EcontMethod(); $m->calculate_shipping( package( 2, INF, array( 0, 0, 0 ) ) );
expect( ! $m->rates && ! $label_calls && ! $public_quotes, 'Nonfinite aggregate weight must not reach the courier or fallback' );

fixture( true ); $p = package( 3, 0.5 );
$boxes = array( array( 'length' => 44, 'width' => 61, 'height' => 37, 'max_weight' => 30.5 ) );
expect( \Nashenski\Shipping\Packer\APSPackage::pack_package( $p, $boxes ) === \Nashenski\Shipping\Packer\APSPackage::pack_package( $p, $boxes, null, new Packing_Budget() ), 'The optional helper argument must not change normal existing callers' );

foreach ( array( false, true ) as $auto ) {
	fixture( $auto );
	$m = new EcontMethod(); $m->calculate_shipping( package( 1000000000, 1e-12, array( 0, 0, 0 ) ) );
	expect( 1 === count( $m->rates ), 'Huge quantities without per-unit sizing must not be rejected by an arbitrary order-quantity cap' );
}
echo 'PASS: ' . $checks . " Econt assertions\n";
}
