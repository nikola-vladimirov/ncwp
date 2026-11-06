<?php // phpcs:disable WordPress.Files.FileName, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, Generic.Classes.DuplicateClassName -- offline test: WordPress stubs stand in for the real classes and share one file with the test.
/**
 * Offline BOX NOW auto-pack unit cap. Run: php -d memory_limit=64M tests/boxnow-unit-cap.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * Shipping method fixture.
 */
class WC_Shipping_Method {}
/**
 * Translate.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text; }
/**
 * Nashenski normalize text for label.
 *
 * @param string $text Text.
 * @return string
 */
function nashenski_normalize_text_for_label( $text ) {
	return $text; }
/**
 * Wc get weight.
 *
 * @param mixed $weight Weight.
 * @return float
 */
function wc_get_weight( $weight ) {
	return (float) $weight; }
/**
 * Nashenski get option.
 *
 * @return string
 */
function nashenski_get_option() {
	return ''; }
/**
 * Wc get dimension.
 *
 * @param mixed $value Dimension.
 * @return float
 */
function wc_get_dimension( $value ) {
	return (float) $value; }
/**
 * Get option.
 *
 * @return string
 */
function get_option() {
	return 'cm'; }

require __DIR__ . '/../app/Shipping/Packer/class-packing-limit-exceeded.php';
require __DIR__ . '/../app/Shipping/BoxNow/class-method.php';
require __DIR__ . '/../app/Admin/class-boxnow.php';

use Nashenski\Admin\BoxNow;

/**
 * Product fixture: 1 x 1 x 1, weight 1.
 */
class CapProduct {
	/**
	 * Height.
	 *
	 * @return int
	 */
	public function get_height() {
		return 1; }
	/**
	 * Width.
	 *
	 * @return int
	 */
	public function get_width() {
		return 1; }
	/**
	 * Length.
	 *
	 * @return int
	 */
	public function get_length() {
		return 1; }
	/**
	 * Weight.
	 *
	 * @return int
	 */
	public function get_weight() {
		return 1; }
}
/**
 * Order item fixture: every key reads as the quantity.
 */
class CapItem implements ArrayAccess {
	/**
	 * Quantity.
	 *
	 * @var int
	 */
	private $q;
	/**
	 * Sets the quantity.
	 *
	 * @param int $q Quantity.
	 */
	public function __construct( $q ) {
		$this->q = $q; }
	/**
	 * Offset exists.
	 *
	 * @param mixed $o Offset.
	 * @return bool
	 */
	public function offsetExists( $o ): bool {
		return true; }
	/**
	 * Offset get.
	 *
	 * @param mixed $o Offset.
	 * @return int
	 */
	#[\ReturnTypeWillChange] public function offsetGet( $o ) {
		return $this->q; }
	/**
	 * Offset set.
	 *
	 * @param mixed $o Offset.
	 * @param mixed $v Value.
	 */
	public function offsetSet( $o, $v ): void {}
	/**
	 * Offset unset.
	 *
	 * @param mixed $o Offset.
	 */
	public function offsetUnset( $o ): void {}
	/**
	 * Product.
	 *
	 * @return CapProduct
	 */
	public function get_product() {
		return new CapProduct(); }
	/**
	 * Name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'Item'; }
}
/**
 * Order fixture with one item.
 */
class CapOrder {
	/**
	 * Quantity.
	 *
	 * @var int
	 */
	private $q;
	/**
	 * Sets the quantity.
	 *
	 * @param int $q Quantity.
	 */
	public function __construct( $q ) {
		$this->q = $q; }
	/**
	 * Items.
	 *
	 * @return array
	 */
	public function get_items() {
		return array( new CapItem( $this->q ) ); }
}

$checks = 0;
/**
 * Expect.
 *
 * @param bool   $condition Condition.
 * @param string $message   Message.
 */
function expect( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- offline test.
		exit( 1 ); }
}

expect( 200 === BoxNow::MAX_AUTO_PACK_UNITS, 'Limit is 200 units' );
expect( ! empty( BoxNow::generate_items( new CapOrder( 200 ) ) ), 'An order at the limit is still packed' );
expect( array() === BoxNow::generate_items( new CapOrder( 201 ) ), 'An order above the limit gets no boxes' );
expect( array() === BoxNow::generate_items( new CapOrder( 1000000000 ) ), 'A huge quantity is refused without looping' );
expect( false !== strpos( BoxNow::too_many_units_message(), '200' ), 'The refusal message names the limit' );
echo 'PASS: ' . (int) $checks . " BOX NOW unit cap assertions\n";
