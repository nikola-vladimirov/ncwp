<?php
/**
 * Offline check: the BOX NOW label packer reads each dimension from its own getter and converts it to cm.
 * Run: php -d memory_limit=64M tests/boxnow-admin-pack.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Generic.Classes.DuplicateClassName, Universal.Files.SeparateFunctionsFromOO, Generic.Files.OneObjectStructurePerFile, WordPress.Security.EscapeOutput, Generic.CodeAnalysis.UnusedFunctionParameter -- offline test script: it stubs WordPress in one file, keeps WordPress's own names and signatures, and prints to a terminal.

define( 'ABSPATH', __DIR__ . '/fixtures/' );

$GLOBALS['checks'] = 0;
$GLOBALS['unit']   = 'cm';

/**
 * WC shipping method stub.
 */
class WC_Shipping_Method {
	/**
	 * Title.
	 *
	 * @var string
	 */
	public $title = 'BOX NOW';
}

/**
 * Get option stub.
 *
 * @param string $key Option name.
 * @return mixed Value.
 */
function get_option( $key ) {
	return 'woocommerce_dimension_unit' === $key ? $GLOBALS['unit'] : '';
}

/**
 * Dimension conversion stub, same units as WooCommerce.
 *
 * @param float  $value Value.
 * @param string $to    Target unit.
 * @param string $from  Source unit.
 * @return float Converted value.
 */
function wc_get_dimension( $value, $to, $from ) {
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
	return $value < 0 ? 0 : $value;
}

/**
 * Weight stub.
 *
 * @param float  $value Value.
 * @param string $to    Unit.
 * @return float Value.
 */
function wc_get_weight( $value, $to ) {
	return (float) $value;
}

/**
 * Label text stub.
 *
 * @param string $text Text.
 * @return string Text.
 */
function nashenski_normalize_text_for_label( $text ) {
	return (string) $text;
}

/**
 * Expectation helper.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @throws RuntimeException When the condition fails.
 */
function expect( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

require __DIR__ . '/../app/Shipping/Packer/class-packing-limit-exceeded.php';
require __DIR__ . '/../app/Shipping/BoxNow/class-method.php';
require __DIR__ . '/../app/Admin/class-boxnow.php';

/**
 * Product stub.
 */
class Pack_Product {
	/**
	 * Height.
	 *
	 * @var mixed
	 */
	private $h;
	/**
	 * Width.
	 *
	 * @var mixed
	 */
	private $w;
	/**
	 * Length.
	 *
	 * @var mixed
	 */
	private $l;

	/**
	 * Constructor.
	 *
	 * @param mixed $h Height.
	 * @param mixed $w Width.
	 * @param mixed $l Length.
	 */
	public function __construct( $h, $w, $l ) {
		$this->h = $h;
		$this->w = $w;
		$this->l = $l;
	}
	/**
	 * Height.
	 *
	 * @return mixed Height.
	 */
	public function get_height() {
		return $this->h; }
	/**
	 * Width.
	 *
	 * @return mixed Width.
	 */
	public function get_width() {
		return $this->w; }
	/**
	 * Length.
	 *
	 * @return mixed Length.
	 */
	public function get_length() {
		return $this->l; }
	/**
	 * Weight.
	 *
	 * @return int Weight.
	 */
	public function get_weight() {
		return 1; }
}

/**
 * Order line stub.
 */
class Pack_Item implements ArrayAccess {
	/**
	 * Quantity.
	 *
	 * @var int
	 */
	private $quantity;
	/**
	 * Product.
	 *
	 * @var Pack_Product
	 */
	private $product;

	/**
	 * Constructor.
	 *
	 * @param int          $quantity Quantity.
	 * @param Pack_Product $product  Product.
	 */
	public function __construct( $quantity, $product ) {
		$this->quantity = $quantity;
		$this->product  = $product;
	}
	/**
	 * Product.
	 *
	 * @return Pack_Product Product.
	 */
	public function get_product() {
		return $this->product; }
	/**
	 * Name.
	 *
	 * @return string Name.
	 */
	public function get_name() {
		return 'item'; }
	/**
	 * Array access: whether the key exists.
	 *
	 * @param mixed $key Key.
	 * @return bool Exists.
	 */
	#[\ReturnTypeWillChange]
	public function offsetExists( $key ) {
		return 'quantity' === $key; }
	/**
	 * Array access: the value of a key.
	 *
	 * @param mixed $key Key.
	 * @return mixed Value.
	 */
	#[\ReturnTypeWillChange]
	public function offsetGet( $key ) {
		return $this->quantity; }
	/**
	 * Array access: sets a value.
	 *
	 * @param mixed $key   Key.
	 * @param mixed $value Value.
	 */
	#[\ReturnTypeWillChange]
	public function offsetSet( $key, $value ) {}
	/**
	 * Array access: removes a key.
	 *
	 * @param mixed $key Key.
	 */
	#[\ReturnTypeWillChange]
	public function offsetUnset( $key ) {}
}

/**
 * Order stub.
 */
class Pack_Order {
	/**
	 * Items.
	 *
	 * @var array
	 */
	private $items;

	/**
	 * Constructor.
	 *
	 * @param array $items Items.
	 */
	public function __construct( array $items ) {
		$this->items = $items;
	}
	/**
	 * Items.
	 *
	 * @return array Items.
	 */
	public function get_items() {
		return $this->items; }
}

/**
 * Pack two products with the given dimensions and return the label items.
 *
 * @param string $unit  Store dimension unit.
 * @param array  $size  Height, width, length in that unit.
 * @param int    $count Quantity.
 * @return array Packed items.
 */
function pack_items( $unit, array $size, $count ) {
	$GLOBALS['unit'] = $unit;
	$product         = new Pack_Product( $size[0], $size[1], $size[2] );
	$order           = new Pack_Order( array( new Pack_Item( $count, $product ) ) );
	return Nashenski\Admin\BoxNow::auto_generate_items_to_boxes( $order );
}

// Same physical product on a cm store and an mm store must pack the same way.
$cm = pack_items( 'cm', array( 30, 20, 50 ), 2 );
$mm = pack_items( 'mm', array( 300, 200, 500 ), 2 );
expect( $cm === $mm, 'Same product packs differently on cm and mm stores' );

// Two 30 x 20 x 50 cm items (60000 cm3) fit one box; the old width and length mix-up made each one oversize.
expect( count( $cm ) === 1, 'Two 30 x 20 x 50 cm items must share one box' );

// Non-cubic product: 10 x 50 x 20 cm must not be read as 10 x 50 x 50.
$odd_cm = pack_items( 'cm', array( 10, 50, 20 ), 4 );
$odd_mm = pack_items( 'mm', array( 100, 500, 200 ), 4 );
expect( $odd_cm === $odd_mm, 'Non-cubic product packs differently on cm and mm stores' );

// An empty dimension takes its default, not an error, on a non-cm store.
$empty = pack_items( 'mm', array( '', '', '' ), 1 );
expect( count( $empty ) === 1, 'Product without dimensions must get one default box' );

echo 'boxnow-admin-pack: ' . $GLOBALS['checks'] . " checks ok\n";
