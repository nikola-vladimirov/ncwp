<?php // phpcs:disable Universal.Files.SeparateFunctionsFromOO, WordPress.WP.AlternativeFunctions -- offline CLI test script: no WordPress, so stubs and plain-text output are on purpose.
/**
 * Offline test: what the cart still lacks for free shipping (the checkout hint and progress bar),
 * and that the figures stay off the order. Run: php tests/free-shipping-left.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * Price decimals of the store.
 *
 * @return int
 */
function wc_get_price_decimals() {
	return 2;
}

/**
 * Same result as WooCommerce for plain numbers with decimals.
 *
 * @param mixed $number Number.
 * @param int   $dp     Decimals.
 * @return string
 */
function wc_format_decimal( $number, $dp ) {
	return number_format( (float) $number, $dp, '.', '' );
}

require __DIR__ . '/../app/Front_End/Checkout/class-pickers.php';

use Nashenski\Front_End\Checkout\Pickers;

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
		fwrite( STDERR, 'FAIL: ' . $message . "\n" );
		exit( 1 );
	}
}

expect( '23.40' === Pickers::free_left( '100', 76.6, false ), 'below the total: the difference, in money decimals' );
expect( '0.00' === Pickers::free_left( '100', 100, false ), 'at the total: nothing left' );
expect( '0.00' === Pickers::free_left( '100', 250.5, false ), 'over the total: nothing left, never negative' );
expect( '0.00' === Pickers::free_left( '100', 10, true ), 'an option that is already free: nothing left' );
expect( '0.01' === Pickers::free_left( '50.00', '49.99', false ), 'a cent below: one cent left' );

$item = new class() {
	/**
	 * Meta keys deleted.
	 *
	 * @var array
	 */
	public $deleted = array();

	/**
	 * Delete meta data.
	 *
	 * @param string $key Key.
	 */
	public function delete_meta_data( $key ) {
		$this->deleted[] = $key;
	}
};
Pickers::drop_free_meta( $item );
expect( array( 'nashenski_free_over', 'nashenski_free_left' ) === $item->deleted, 'the free shipping figures are not saved on the order' );

echo 'PASS: ' . (int) $checks . " free shipping left assertions\n";
