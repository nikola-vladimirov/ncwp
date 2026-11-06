<?php
/** Offline BOX NOW auto-pack unit cap. Run: php -d memory_limit=64M tests/boxnow-unit-cap.php */
define( 'ABSPATH', __DIR__ . '/fixtures/' );

class WC_Shipping_Method {}
function __( $text, $domain = '' ) {
	return $text; }
function nashenski_normalize_text_for_label( $text ) {
	return $text; }
function wc_get_weight( $weight, $unit ) {
	return (float) $weight; }
function nashenski_get_option( $group, $name ) {
	return ''; }
function wc_get_dimension( $value, $to, $from ) {
	return (float) $value; }
function get_option( $key ) {
	return 'cm'; }

require __DIR__ . '/../app/Shipping/Packer/Packing_Limit_Exceeded.php';
require __DIR__ . '/../app/Shipping/BoxNow/Method.php';
require __DIR__ . '/../app/Admin/BoxNow.php';

use Nashenski\Admin\BoxNow;

class CapProduct {
	public function get_height() {
		return 1; }
	public function get_width() {
		return 1; }
	public function get_length() {
		return 1; }
	public function get_weight() {
		return 1; }
}
class CapItem implements ArrayAccess {
	private $q;
	public function __construct( $q ) {
		$this->q = $q; }
	public function offsetExists( $o ): bool {
		return true; }
	#[\ReturnTypeWillChange] public function offsetGet( $o ) {
		return $this->q; }
	public function offsetSet( $o, $v ): void {}
	public function offsetUnset( $o ): void {}
	public function get_product() {
		return new CapProduct(); }
	public function get_name() {
		return 'Item'; }
}
class CapOrder {
	private $q;
	public function __construct( $q ) {
		$this->q = $q; }
	public function get_items() {
		return array( new CapItem( $this->q ) ); }
}

$checks = 0;
function expect( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . "\n" );
		exit( 1 ); }
}

expect( 200 === BoxNow::MAX_AUTO_PACK_UNITS, 'Limit is 200 units' );
expect( ! empty( BoxNow::generate_items( new CapOrder( 200 ) ) ), 'An order at the limit is still packed' );
expect( array() === BoxNow::generate_items( new CapOrder( 201 ) ), 'An order above the limit gets no boxes' );
expect( array() === BoxNow::generate_items( new CapOrder( 1000000000 ) ), 'A huge quantity is refused without looping' );
expect( false !== strpos( BoxNow::too_many_units_message(), '200' ), 'The refusal message names the limit' );
echo 'PASS: ' . $checks . " BOX NOW unit cap assertions\n";
