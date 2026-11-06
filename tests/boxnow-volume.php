<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName, Generic.Classes.DuplicateClassName, Generic.Files.OneObjectStructurePerFile.MultipleFound, Universal.Files.SeparateFunctionsFromOO.Mixed, Generic.CodeAnalysis.UnusedFunctionParameter, WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- standalone offline test: its stubs live in this file and keep the names and signatures of what they stand in for.
/**
 * Offline BOX NOW capacity and rate regressions. Run: php -d memory_limit=64M tests/boxnow-volume.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * Wc shipping method.
 */
class WC_Shipping_Method {
	/**
	 * Title.
	 *
	 * @var string
	 */
	public $title = 'BOX NOW';
	/**
	 * Rates.
	 *
	 * @var array
	 */
	public $rates = array();
	/**
	 * Add rate.
	 *
	 * @param mixed $rate Rate.
	 */
	public function add_rate( $rate ) {
		$this->rates[] = $rate; }
}

require __DIR__ . '/../app/Shipping/BoxNow/class-method.php';

/**
 * Boxnowproduct.
 */
class BoxNowProduct {
	/**
	 * Height.
	 *
	 * @var mixed
	 */
	private $height;
	/**
	 * Width.
	 *
	 * @var mixed
	 */
	private $width;
	/**
	 * Length.
	 *
	 * @var mixed
	 */
	private $length;
	/**
	 * Constructor.
	 *
	 * @param string $height Height.
	 * @param string $width Width.
	 * @param string $length Length.
	 */
	public function __construct( $height = '', $width = '', $length = '' ) {
		$this->height = $height;
		$this->width  = $width;
		$this->length = $length;
	}
	/**
	 * Get height.
	 *
	 * @return mixed Result.
	 */
	public function get_height() {
		return $this->height; }
	/**
	 * Get width.
	 *
	 * @return mixed Result.
	 */
	public function get_width() {
		return $this->width; }
	/**
	 * Get length.
	 *
	 * @return mixed Result.
	 */
	public function get_length() {
		return $this->length; }
}

/**
 * Boxnowmethod.
 */
class BoxNowMethod extends \Nashenski\Shipping\BoxNow\Method {
	/**
	 * Capacity checks.
	 *
	 * @var int
	 */
	public $capacity_checks = 0;
	/**
	 * Constructor.
	 */
	public function __construct() {}
	/**
	 * Get total weight.
	 *
	 * @return mixed Result.
	 */
	public function get_total_weight() {
		return $GLOBALS['weight']; }
	/**
	 * Has more than one box.
	 *
	 * @return mixed Result.
	 */
	public function has_more_than_one_box() {
		++$this->capacity_checks;
		return parent::has_more_than_one_box(); }
}

/**
 * Boxnowcart.
 */
class BoxNowCart {
	/**
	 * Get cart contents.
	 *
	 * @return mixed Result.
	 */
	public function get_cart_contents() {
		if ( isset( $GLOBALS['cart_lines'] ) ) {
			return $GLOBALS['cart_lines']; }
		return $GLOBALS['oversize'] ? array( line( 1, 100, 100, 100 ) ) : array(); }
}
/**
 * Wc.
 *
 * @return mixed Result.
 */
function WC() {
	return (object) array( 'cart' => new BoxNowCart() ); }
/**
 * Nashenski courier cookie.
 *
 * @param mixed $name Name.
 * @return mixed Result.
 */
function nashenski_courier_cookie( $name ) {
	return $GLOBALS['cookie']; }
/**
 * Wc get dimension.
 *
 * @param mixed $value Value.
 * @param mixed $to To.
 * @param mixed $from From.
 * @return mixed Result.
 */
function wc_get_dimension( $value, $to, $from ) {
	// Same conversion as WooCommerce: a non-numeric value (an empty dimension) throws a TypeError on PHP 8 when the units differ.
	if ( $from !== $to ) {
		$factor = array(
			'in' => 2.54,
			'm'  => 100,
			'mm' => 0.1,
			'yd' => 91.44,
			'cm' => 1,
		);
		$value *= $factor[ $from ];
	}
	return $value < 0 ? 0 : $value; }
/**
 * Get option.
 *
 * @param mixed $key Key.
 * @return string Result.
 */
function get_option( $key ) {
	return $GLOBALS['dimension_unit'] ?? 'cm'; }
/**
 * Nashenski get option.
 *
 * @param mixed $group Group.
 * @param mixed $name Name.
 * @return mixed Result.
 */
function nashenski_get_option( $group, $name ) {
	// Until a test sets its own options, the store has one weight band that holds every cart (a store with no price type gets no rate).
	$options = $GLOBALS['boxnow_options'] ?? array(
		'price_type' => 'from_to_kg',
		'from_to_kg' => '[{"from":"0","to":"20","price":"3"}]',
	);
	return 'hide_over' === $name ? $GLOBALS['hide_over'] : ( $options[ $name ] ?? '' ); }
/**
 * Wp json encode.
 *
 * @param mixed $data Data.
 * @return string Result.
 */
function wp_json_encode( $data ) {
	return json_encode( $data ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- stub of wp_json_encode itself.
/**
 * Wc tax enabled.
 */
function wc_tax_enabled() {
	return false; }
/**
 * Nashenski tax based price.
 *
 * @param mixed $price Price.
 * @return mixed Result.
 */
function nashenski_tax_based_price( $price ) {
	return $price; }
/**
 * Nashenski get package total.
 *
 * @return mixed Result.
 */
function nashenski_get_package_total() {
	return $GLOBALS['cart_total']; }
/**
 * Do action.
 *
 * @param mixed   $name Name.
 * @param mixed[] ...$args Args.
 */
function do_action( $name, ...$args ) {}
/**
 * Apply filters.
 *
 * @param mixed   $name Name.
 * @param mixed   $value Value.
 * @param mixed[] ...$args Args.
 * @return mixed Result.
 */
function apply_filters( $name, $value, ...$args ) {
	return $value; }

$checks = 0;
/**
 * Expect.
 *
 * @param mixed $condition Condition.
 * @param mixed $message Message.
 * @throws RuntimeException When the check fails.
 */
function expect( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		throw new RuntimeException( $message ); } // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI test: the message goes to the terminal, not to a page.
}
/**
 * Line.
 *
 * @param mixed  $quantity Quantity.
 * @param string $height Height.
 * @param string $width Width.
 * @param string $length Length.
 * @return array Result.
 */
function line( $quantity, $height = '', $width = '', $length = '' ) {
	return array(
		'quantity' => $quantity,
		'data'     => new BoxNowProduct( $height, $width, $length ),
	);
}
/**
 * Method.
 *
 * @param array $contents Contents.
 * @return mixed Result.
 */
function method( array $contents ) {
	$m          = new BoxNowMethod();
	$m->package = array( 'contents' => $contents );
	return $m;
}
/**
 * A bounded per-unit version of the capacity calculation (cm store), used only for small compatibility fixtures.
 *
 * @param array $contents Cart lines.
 * @return bool Whether the cart needs more than one box.
 */
function previous_capacity( array $contents ) {
	$volume = 0;
	foreach ( $contents as $item ) {
		for ( $i = 0; $i < $item['quantity']; $i++ ) {
			$p       = $item['data'];
			$height  = $p->get_height() ? (float) $p->get_height() : 7;
			$width   = $p->get_width() ? (float) $p->get_width() : 44;
			$length  = $p->get_length() ? (float) $p->get_length() : 58;
			$size    = \Nashenski\Shipping\BoxNow\Method::determine_item_size( $height, $width, $length );
			$volume += $size['volume'];
			if ( $size['oversize'] || $size['volume'] > 89320 || $volume > 89320 ) {
				return true; }
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
	array( 'dimension fallback, each dimension on its own', array( line( 3, 1, 0, 2 ) ), false ),
	array( 'height-only fallback fills width and length', array( line( 7, 1 ) ), false ),
	array( 'custom dimensions', array( line( 10, 2, 3, 50 ) ), false ),
	array( 'item longer than the box', array( line( 1, 2, 3, 90 ) ), true ),
) as list( $name, $contents, $expected ) ) {
	expect( previous_capacity( $contents ) === $expected, 'Legacy fixture mismatch: ' . $name );
	expect( method( $contents )->has_more_than_one_box() === $expected, $name );
}

// Reproduce the original allocation safely: 200,000 entries fit in the test process,
// but the fixed check must need less than 512 KiB of additional memory.
if ( function_exists( 'memory_reset_peak_usage' ) ) {
	memory_reset_peak_usage(); }
$memory = memory_get_usage();
expect( method( array( line( 200000 ) ) )->has_more_than_one_box(), 'Large quantity must exceed capacity' );
expect( memory_get_peak_usage() - $memory < 512 * 1024, 'Capacity check allocated memory proportional to quantity' );

foreach ( array( '1000000000000', '1e300', PHP_INT_MAX ) as $quantity ) {
	expect( method( array( line( $quantity ) ) )->has_more_than_one_box(), 'Enormous finite quantity must be bounded' );
}
foreach ( array( INF, NAN, '1e999', 'invalid', array( 1 ) ) as $quantity ) {
	expect( method( array( line( $quantity ) ) )->has_more_than_one_box(), 'Malformed quantity must not bypass capacity' );
}
// Each dimension is read from its own getter and converted to cm (BOX NOW box 44 x 58 cm, 89320 cm3).
foreach ( array( 'cm', 'mm' ) as $unit ) {
	$k                         = 'mm' === $unit ? 10 : 1;
	$GLOBALS['dimension_unit'] = $unit;
	expect( method( array( line( 3, (string) ( 30 * $k ), (string) ( 20 * $k ), (string) ( 50 * $k ) ) ) )->has_more_than_one_box(), 'Three 30 x 20 x 50 cm items need two boxes on a ' . $unit . ' store' );
	expect( ! method( array( line( 4, (string) ( 10 * $k ), (string) ( 50 * $k ), (string) ( 20 * $k ) ) ) )->has_more_than_one_box(), 'Four 10 x 50 x 20 cm items fit one box on a ' . $unit . ' store' );
	unset( $GLOBALS['dimension_unit'] );
}
expect( ! method( array( line( 1000000000, 0.000001, 1, 1 ) ) )->has_more_than_one_box(), 'Large quantities of tiny products must not be rejected by an arbitrary unit cap' );
expect( ! method( array( line( 893200, 0.1, 1, 1 ) ) )->has_more_than_one_box(), 'Fractional item volume at mathematical capacity must fit' );

/**
 * Calculate.
 *
 * @param mixed  $contents Contents.
 * @param string $payment Payment.
 * @param int    $weight Weight.
 * @param bool   $oversize Oversize.
 * @param string $hide_over Hide over.
 * @param int    $total Total.
 * @return mixed Result.
 */
function calculate( $contents, $payment = 'cod', $weight = 1, $oversize = false, $hide_over = '', $total = 0 ) {
	$GLOBALS['cookie']     = array( 'payment' => $payment );
	$GLOBALS['weight']     = $weight;
	$GLOBALS['oversize']   = $oversize;
	$GLOBALS['hide_over']  = $hide_over;
	$GLOBALS['cart_total'] = $total;
	$m                     = method( $contents );
	$m->calculate_shipping(
		array(
			'contents'    => $contents,
			'destination' => array( 'country' => 'BG' ),
		)
	);
	return $m;
}
foreach ( array(
	array( 21, false, '', 0 ),
	array( 1, true, '', 0 ),
	array( 1, false, 100, 100 ),
) as list( $weight, $oversize, $hide_over, $total ) ) {
	$method = calculate( array( line( 6 ) ), 'cod', $weight, $oversize, $hide_over, $total );
	expect( 0 === $method->capacity_checks && ! $method->rates, 'Cheap rejection must precede COD capacity calculation' );
}
$method = calculate( array( line( 5 ) ) );
expect( 1 === $method->capacity_checks && 1 === count( $method->rates ), 'Normal one-box COD cart must retain its rate' );
$method = calculate( array( line( '1e300' ) ) );
expect( 1 === $method->capacity_checks && ! $method->rates, 'Oversize-quantity COD cart must not get a rate' );
$method = calculate( array( line( 6 ) ), 'card' );
expect( 0 === $method->capacity_checks && 1 === count( $method->rates ), 'Prepaid multi-box behavior must remain unchanged' );
// A free shipping threshold that is zero in any spelling is no threshold: a cart of 99.99 does not ship free on it.
foreach ( array(
	'0.00' => false,
	'0'    => false,
	''     => false,
	'1'    => true,
	'100'  => false,
) as $threshold => $free ) {
	$GLOBALS['cookie']          = array( 'payment' => 'card' );
	$GLOBALS['weight']          = 1;
	$GLOBALS['oversize']        = false;
	$GLOBALS['hide_over']       = '';
	$GLOBALS['cart_total']      = 99.99;
	$method                     = method( array( line( 1 ) ) );
	$method->free_shipping_over = $threshold;
	$method->calculate_shipping(
		array(
			'contents'    => array( line( 1 ) ),
			'destination' => array( 'country' => 'BG' ),
		)
	);
	$shown_free = ! empty( $method->rates[0]['meta_data']['free_shipping'] );
	expect( $free === $shown_free, "BOX NOW free shipping at threshold '$threshold' must be " . var_export( $free, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- builds the failure message text.
}
// A stored price row below zero (saved before negatives were refused) gives no rate, not a free one (R41, the old assertion encoded the bug); a typed 0 is a free band.
foreach ( array(
	'-5' => null,
	'0'  => 0,
	'5'  => '5',
) as $stored => $cost ) {
	$GLOBALS['boxnow_options'] = array(
		'price_type' => 'from_to_kg',
		'from_to_kg' => wp_json_encode(
			array(
				array(
					'from'  => '0',
					'to'    => '20',
					'price' => (string) $stored,
				),
			)
		),
	);
	$method                    = calculate( array( line( 1 ) ) );
	expect( null === $cost ? array() === $method->rates : 1 === count( $method->rates ) && $cost === $method->rates[0]['cost'], "A stored price row of $stored gives " . ( null === $cost ? 'no rate' : "a rate cost of $cost" ) );
}
// A cart that no price band holds gets no BOX NOW rate; a band the owner typed with a price of 0 is a band and gives a free rate.
/**
 * Rates BOX NOW offers for a cart, with the given price rows.
 *
 * @param string $type   Price type.
 * @param array  $rows   Price rows as saved.
 * @param float  $weight Cart weight.
 * @param float  $total  Cart total.
 * @return array Rates.
 */
function band_rates( $type, array $rows, $weight, $total ) {
	$GLOBALS['boxnow_options'] = array(
		'price_type' => $type,
		$type        => wp_json_encode( $rows ),
	);
	return calculate( array( line( 1 ) ), 'card', $weight, false, '', $total )->rates;
}
$kg_rows       = array(
	array(
		'from'  => '1',
		'to'    => '5',
		'price' => '3',
	),
	array(
		'from'  => '5',
		'to'    => '10',
		'price' => '0',
	),
);
$total_rows    = array(
	array(
		'from'  => '20',
		'to'    => '50',
		'price' => '4',
	),
	array(
		'from'  => '50',
		'to'    => '100',
		'price' => '0',
	),
);
$combined_rows = array(
	array(
		'from'       => '1',
		'to'         => '5',
		'from_price' => '20',
		'to_price'   => '50',
		'price'      => '5',
	),
	array(
		'from'       => '5',
		'to'         => '10',
		'from_price' => '20',
		'to_price'   => '50',
		'price'      => '0',
	),
);
foreach ( array(
	'weight bands'   => array( 'from_to_kg', $kg_rows, array( 2, 30 ), array( 7, 30 ), array( array( 12, 30 ), array( 0.5, 30 ) ), '3' ),
	'total bands'    => array( 'from_to_order_total', $total_rows, array( 1, 30 ), array( 1, 60 ), array( array( 1, 150 ), array( 1, 10 ) ), '4' ),
	'combined bands' => array( 'from_to_kg_and_order_total', $combined_rows, array( 2, 30 ), array( 7, 30 ), array( array( 12, 30 ), array( 2, 60 ), array( 0.5, 30 ) ), '5' ),
) as $name => list( $price_type, $rows, $inside, $typed_zero, $outside, $price ) ) {
	$rates = band_rates( $price_type, $rows, $inside[0], $inside[1] );
	expect( 1 === count( $rates ) && $price === $rates[0]['cost'], "BOX NOW $name: a cart inside a band gets that band's price $price" );
	$rates = band_rates( $price_type, $rows, $typed_zero[0], $typed_zero[1] );
	expect( 1 === count( $rates ) && 0 === $rates[0]['cost'], "BOX NOW $name: a band typed with price 0 gives a free rate, not a missing band" );
	foreach ( $outside as $cart ) {
		expect( array() === band_rates( $price_type, $rows, $cart[0], $cart[1] ), "BOX NOW $name: a cart outside every band (weight {$cart[0]}, total {$cart[1]}) gets no rate" );
	}
}
// No price type saved: no rate, even with weight rows (B04). No rows: no rate, never a free one.
$GLOBALS['boxnow_options'] = array( 'from_to_kg' => wp_json_encode( $kg_rows ) );
expect( array() === calculate( array( line( 1 ) ), 'card', 2, false, '', 30 )->rates, 'BOX NOW with no price type saved gives no rate' );
$GLOBALS['boxnow_options']['price_type'] = 'from_to_order_total';
expect( array() === calculate( array( line( 1 ) ), 'card', 2, false, '', 30 )->rates, 'BOX NOW: a saved price type wins over the default (order total, no order total rows: no rate)' );
$GLOBALS['boxnow_options'] = array();
expect( array() === calculate( array( line( 1 ) ), 'card', 2, false, '', 30 )->rates, 'BOX NOW with nothing configured gets no rate' );
$rates = band_rates( 'from_to_kg', $kg_rows, 2, 30 );
expect( 1 === count( $rates ) && '3' === $rates[0]['cost'], 'BOX NOW with a chosen price type and its rows still gives its price' );
$GLOBALS['boxnow_options'] = array();
// A missing or zero dimension, whatever its type (int, float, numeric string, empty, text), takes the default size.
foreach ( array( 0, 0.0, '0', '0.0', '', null, 'abc' ) as $zero ) {
	foreach ( array( array( $zero, 10, 10 ), array( 10, $zero, 10 ), array( 10, 10, $zero ) ) as list( $h, $w, $l ) ) {
		$size = \Nashenski\Shipping\BoxNow\Method::determine_item_size( $h, $w, $l );
		expect( 2 === $size['size'] && 40832 === $size['volume'], 'A missing dimension takes the default size: ' . var_export( $zero, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- builds the failure message text.
	}
}
$size = \Nashenski\Shipping\BoxNow\Method::determine_item_size( 5.5, 10, 20 );
expect( 1 === $size['size'] && 1100.0 === (float) $size['volume'], 'A small item keeps its own size and volume' );
// get_allowed_apm_size on stores whose dimension unit is not cm: an empty dimension must not throw (TypeError in wc_get_dimension) and takes the default size.
/**
 * Allowed apm size.
 *
 * @param string $unit  Store dimension unit.
 * @param array  $lines Cart lines.
 * @return array Result.
 */
function allowed_size( $unit, array $lines ) {
	$GLOBALS['dimension_unit'] = $unit;
	$GLOBALS['cart_lines']     = $lines;
	$result                    = \Nashenski\Shipping\BoxNow\Method::get_allowed_apm_size();
	unset( $GLOBALS['dimension_unit'], $GLOBALS['cart_lines'] );
	return $result;
}
foreach ( array( 'cm', 'mm', 'm', 'in', 'yd' ) as $unit ) {
	foreach ( array( '', null, '0' ) as $empty ) {
		$r = allowed_size( $unit, array( line( 1, $empty, $empty, $empty ), line( 1, '', '12', '12' ) ) );
		expect( 2 === (int) $r['max_size'] && false === $r['has_oversize_item'], 'An empty dimension takes the default size on a ' . $unit . ' store: ' . var_export( $empty, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- builds the failure message text.
	}
}
// The same product, on a cm store and on an mm store holding the same size, gives the same box size and oversize flag.
foreach ( array(
	array( 'small item', 5.5, 10, 20, 1 ),
	array( 'medium item', 10, 40, 50, 2 ),
	array( 'large item', 30, 40, 50, 3 ),
) as list( $name, $h, $w, $l, $box ) ) {
	$cm = allowed_size( 'cm', array( line( 1, (string) $h, (string) $w, (string) $l ) ) );
	$mm = allowed_size( 'mm', array( line( 1, (string) ( $h * 10 ), (string) ( $w * 10 ), (string) ( $l * 10 ) ) ) );
	expect( (int) $cm['max_size'] === $box && $cm === $mm, 'Same product, same box on cm and mm stores: ' . $name );
}
$r = allowed_size( 'cm', array( line( 1, '100', '100', '100' ) ) );
expect( 3 === (int) $r['max_size'] && true === $r['has_oversize_item'], 'An oversize product stays oversize on a cm store' );
$r = allowed_size( 'mm', array( line( 1, '1000', '1000', '1000' ) ) );
expect( 3 === (int) $r['max_size'] && true === $r['has_oversize_item'], 'An oversize product stays oversize on an mm store' );
echo 'PASS: ' . (int) $checks . " BOX NOW assertions\n";
