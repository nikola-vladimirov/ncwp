<?php
/** Offline BOX NOW capacity/rate regressions. Run: php -d memory_limit=64M tests/boxnow-volume.php */
define( 'ABSPATH', __DIR__ . '/fixtures/' );

class WC_Shipping_Method {
	public $title = 'BOX NOW';
	public $rates = array();
	public function add_rate( $rate ) { $this->rates[] = $rate; }
}

require __DIR__ . '/../app/Shipping/BoxNow/Method.php';

class BoxNowProduct {
	private $height, $width, $length;
	public function __construct( $height = '', $width = '', $length = '' ) {
		$this->height = $height; $this->width = $width; $this->length = $length;
	}
	public function get_height() { return $this->height; }
	public function get_width() { return $this->width; }
	public function get_length() { return $this->length; }
}

class BoxNowMethod extends \Nashenski\Shipping\BoxNow\Method {
	public $capacity_checks = 0;
	public function __construct() {}
	public function get_total_weight() { return $GLOBALS['weight']; }
	public function has_more_than_one_box() { $this->capacity_checks++; return parent::has_more_than_one_box(); }
}

class BoxNowCart {
	public function get_cart_contents() { return $GLOBALS['oversize'] ? array( line( 1, 100, 100, 100 ) ) : array(); }
}
function WC() { return (object) array( 'cart' => new BoxNowCart() ); }
function nashenski_courier_cookie( $name ) { return $GLOBALS['cookie']; }
function wc_get_dimension( $value, $to, $from ) { return (float) $value; }
function get_option( $key ) { return 'cm'; }
function nashenski_get_option( $group, $name ) { return $name === 'hide_over' ? $GLOBALS['hide_over'] : ''; }
function nashenski_get_package_total() { return $GLOBALS['cart_total']; }
function do_action( $name, ...$args ) {}
function apply_filters( $name, $value, ...$args ) { return $value; }

$checks = 0;
function expect( $condition, $message ) {
	$GLOBALS['checks']++;
	if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function line( $quantity, $height = '', $width = '', $length = '' ) {
	return array( 'quantity' => $quantity, 'data' => new BoxNowProduct( $height, $width, $length ) );
}
function method( array $contents ) {
	$m = new BoxNowMethod(); $m->package = array( 'contents' => $contents ); return $m;
}
// A bounded version of the previous calculation, used only for small compatibility fixtures.
function previous_capacity( array $contents ) {
	$volume = 0;
	foreach ( $contents as $item ) {
		for ( $i = 0; $i < $item['quantity']; $i++ ) {
			$p = $item['data'];
			$height = $p->get_height() ? (float) $p->get_height() : 7;
			$width = $p->get_height() ? (float) $p->get_width() : 44;
			$length = $p->get_height() ? (float) $p->get_width() : 58;
			$size = \Nashenski\Shipping\BoxNow\Method::determine_item_size( $height, $width, $length );
			$volume += $size['volume'];
			if ( $size['oversize'] || $size['volume'] > 89320 || $volume > 89320 ) { return true; }
		}
	}
	return false;
}

foreach ( array(
	array( 'empty cart', array(), false ),
	array( 'single default-sized item', array( line( 1 ) ), false ),
	array( 'exact capacity', array( line( 5 ) ), false ),
	array( 'one unit over capacity', array( line( 6 ) ), true ),
	array( 'combined cart lines at capacity', array( line( 2 ), line( 3 ) ), false ),
	array( 'combined cart lines over capacity', array( line( 3 ), line( 3 ) ), true ),
	array( 'fractional positive quantity', array( line( 4.5 ) ), false ),
	array( 'fractional quantity over capacity', array( line( 5.1 ) ), true ),
	array( 'half unit retains old loop semantics', array( line( 0.5 ) ), false ),
	array( 'numeric string', array( line( '6' ) ), true ),
	array( 'scientific notation', array( line( '6e0' ) ), true ),
	array( 'zero quantity oversize line ignored', array( line( 0, 100, 100, 100 ) ), false ),
	array( 'negative quantity line ignored', array( line( -2, 100, 100, 100 ) ), false ),
	array( 'oversize product', array( line( 1, 100, 100, 100 ) ), true ),
	array( 'dimension fallback', array( line( 3, 1, 0, 2 ) ), true ),
	array( 'custom dimensions', array( line( 10, 2, 3, 90 ) ), false ),
) as list( $name, $contents, $expected ) ) {
	expect( $expected === previous_capacity( $contents ), 'Legacy fixture mismatch: ' . $name );
	expect( $expected === method( $contents )->has_more_than_one_box(), $name );
}

// Reproduce the original allocation safely: 200,000 entries fit in the test process,
// but the fixed check must need less than 512 KiB of additional memory.
if ( function_exists( 'memory_reset_peak_usage' ) ) { memory_reset_peak_usage(); }
$memory = memory_get_usage();
expect( method( array( line( 200000 ) ) )->has_more_than_one_box(), 'Large quantity must exceed capacity' );
expect( memory_get_peak_usage() - $memory < 512 * 1024, 'Capacity check allocated memory proportional to quantity' );

foreach ( array( '1000000000000', '1e300', PHP_INT_MAX ) as $quantity ) {
	expect( method( array( line( $quantity ) ) )->has_more_than_one_box(), 'Enormous finite quantity must be bounded' );
}
foreach ( array( INF, NAN, '1e999', 'invalid', array( 1 ) ) as $quantity ) {
	expect( method( array( line( $quantity ) ) )->has_more_than_one_box(), 'Malformed quantity must not bypass capacity' );
}
expect( ! method( array( line( 1000000000, 0.000001, 1, 1 ) ) )->has_more_than_one_box(), 'Large quantities of tiny products must not be rejected by an arbitrary unit cap' );
expect( ! method( array( line( 893200, 0.1, 1, 1 ) ) )->has_more_than_one_box(), 'Fractional item volume at mathematical capacity must fit' );

function calculate( $contents, $payment = 'cod', $weight = 1, $oversize = false, $hide_over = '', $total = 0 ) {
	$GLOBALS['cookie'] = array( 'payment' => $payment ); $GLOBALS['weight'] = $weight;
	$GLOBALS['oversize'] = $oversize; $GLOBALS['hide_over'] = $hide_over; $GLOBALS['cart_total'] = $total;
	$m = method( $contents );
	$m->calculate_shipping( array( 'contents' => $contents, 'destination' => array( 'country' => 'BG' ) ) );
	return $m;
}
foreach ( array(
	array( 21, false, '', 0 ), array( 1, true, '', 0 ), array( 1, false, 100, 100 ),
) as list( $weight, $oversize, $hide_over, $total ) ) {
	$m = calculate( array( line( 6 ) ), 'cod', $weight, $oversize, $hide_over, $total );
	expect( 0 === $m->capacity_checks && ! $m->rates, 'Cheap rejection must precede COD capacity calculation' );
}
$m = calculate( array( line( 5 ) ) );
expect( 1 === $m->capacity_checks && 1 === count( $m->rates ), 'Normal one-box COD cart must retain its rate' );
$m = calculate( array( line( '1e300' ) ) );
expect( 1 === $m->capacity_checks && ! $m->rates, 'Oversize-quantity COD cart must not get a rate' );
$m = calculate( array( line( 6 ) ), 'card' );
expect( 0 === $m->capacity_checks && 1 === count( $m->rates ), 'Prepaid multi-box behavior must remain unchanged' );
echo 'PASS: ' . $checks . " BOX NOW assertions\n";
