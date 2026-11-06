<?php
/**
 * Offline test: a BOX NOW band row with no valid price gives no rate, not a free one. Run: php tests/boxnow-band-empty-price.php
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

require __DIR__ . '/../app/Shipping/BoxNow/Method.php';

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
	return json_encode( $data ); }
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
		throw new RuntimeException( $message ); }
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
// An empty, text or negative price in a stored band is not a free rate. A typed 0 still is.
foreach ( array(
	'from_to_kg'                 => array(
		array( 'from' => '0', 'to' => '20', 'price' => '' ),
		array( 'from' => '0', 'to' => '20', 'price' => 'abc' ),
		array( 'from' => '0', 'to' => '20', 'price' => '-5' ),
	),
	'from_to_order_total'        => array(
		array( 'from' => '0', 'to' => '100', 'price' => '' ),
		array( 'from' => '0', 'to' => '100', 'price' => 'abc' ),
		array( 'from' => '0', 'to' => '100', 'price' => '-5' ),
	),
	'from_to_kg_and_order_total' => array(
		array( 'from' => '0', 'to' => '20', 'from_price' => '0', 'to_price' => '100', 'price' => '' ),
		array( 'from' => '0', 'to' => '20', 'from_price' => '0', 'to_price' => '100', 'price' => 'abc' ),
		array( 'from' => '0', 'to' => '20', 'from_price' => '0', 'to_price' => '100', 'price' => '-5' ),
	),
) as $type => $bad_rows ) {
	foreach ( $bad_rows as $row ) {
		$GLOBALS['boxnow_options'] = array(
			'price_type' => $type,
			$type        => wp_json_encode( array( $row ) ),
		);
		$rates                     = calculate( array( line( 1 ) ), 'card', 2, false, '', 30 )->rates;
		expect( array() === $rates, "BOX NOW $type: a band price of '{$row['price']}' gives no rate" );
	}
	$row['price']              = '0';
	$GLOBALS['boxnow_options'] = array(
		'price_type' => $type,
		$type        => wp_json_encode( array( $row ) ),
	);
	$rates                     = calculate( array( line( 1 ) ), 'card', 2, false, '', 30 )->rates;
	expect( 1 === count( $rates ) && 0 === $rates[0]['cost'], "BOX NOW $type: a typed price of 0 is still a free rate" );
}
// B04: price rows saved but no price type saved gives no rate; saving the type brings the rate back.
$GLOBALS['boxnow_options'] = array( 'from_to_kg' => '[{"from":"0","to":"20","price":"3"}]' );
expect( array() === calculate( array( line( 1 ) ), 'card', 2, false, '', 30 )->rates, 'BOX NOW with no saved price type gives no rate' );
$GLOBALS['boxnow_options']['price_type'] = 'from_to_kg';
expect( 1 === count( calculate( array( line( 1 ) ), 'card', 2, false, '', 30 )->rates ), 'BOX NOW with a saved price type gives a rate' );
echo 'PASS: ' . $checks . " BOX NOW band price assertions\n";
