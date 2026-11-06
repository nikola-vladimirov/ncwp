<?php
/**
 * Offline test: a price list never keeps a negative price, surcharge or fee.
 * Run: php tests/price-list-negative.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
/**
 * Esc url raw.
 *
 * @param mixed $url Url.
 */
function esc_url_raw( $url ) {
	return $url; }

require __DIR__ . '/../app/Pricing/class-price-lists.php';

$checks = 0;
/**
 * Expect.
 *
 * @param mixed $condition Condition.
 * @param mixed $message Message.
 */
function expect( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI test writes its failure to standard error.
		exit( 1 ); }
}

$list = \Nashenski\Pricing\Price_Lists::normalize(
	array(
		'surcharge' => '-5',
		'cod'       => array(
			'percent' => '-1',
			'min'     => '-2',
		),
		'bands'     => array(
			array(
				'to'      => '1',
				'office'  => '-5',
				'locker'  => '3,5',
				'address' => '0',
			),
			array(
				'to'      => '-3',
				'office'  => '1',
				'locker'  => '1',
				'address' => '1',
			),
		),
		'over'      => array(
			'base'   => array(
				'office'  => '-9',
				'address' => '7',
			),
			'per_kg' => array(
				'locker'  => '-0.1',
				'address' => '0.5',
			),
		),
	),
	'econt'
);

expect( null === $list['bands'][0]['office'], 'a negative band price is left empty' );
expect( 3.5 === $list['bands'][0]['locker'], 'a comma decimal price is kept' );
expect( 0.0 === $list['bands'][0]['address'], 'a zero price is kept' );
expect( 1 === count( $list['bands'] ), 'a band with a negative weight limit is dropped' );
expect( null === $list['over']['base']['office'] && 7.0 === $list['over']['base']['address'], 'a negative base price is left empty, a good one is kept' );
expect( null === $list['over']['per_kg']['locker'] && 0.5 === $list['over']['per_kg']['address'], 'a negative price per kg is left empty, a good one is kept' );
expect( 0.0 === $list['surcharge'], 'a negative surcharge becomes 0' );
expect( 0.0 === $list['cod']['percent'] && 0.0 === $list['cod']['min'], 'a negative cash on delivery fee becomes 0' );

echo 'PASS: ' . (int) $checks . " price list assertions\n";
