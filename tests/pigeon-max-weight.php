<?php // phpcs:disable WordPress.WP.AlternativeFunctions -- offline CLI test script: no WordPress, so stubs, unused stub parameters, filesystem calls and plain-text output are on purpose.
/**
 * Offline test: Pigeon gives no public price above 30 kg, even from an older saved list with bands to 40 kg.
 * Run: php tests/pigeon-max-weight.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * Get option: an older saved Pigeon list with a band up to 40 kg.
 *
 * @param mixed $name Name.
 */
function get_option( $name ) {
	unset( $name );
	return array(
		'vat_included' => true,
		'bands'        => array(
			array(
				'to'     => 30,
				'office' => 3.5,
			),
			array(
				'to'     => 40,
				'office' => 4.5,
			),
		),
	);
}

/**
 * Esc url raw.
 *
 * @param mixed $url Url.
 */
function esc_url_raw( $url ) {
	return $url;
}

/**
 * Apply filters: returns the value.
 *
 * @param mixed $hook  Hook.
 * @param mixed $value Value.
 */
function apply_filters( $hook, $value ) {
	unset( $hook );
	return $value;
}

require_once __DIR__ . '/fixtures/hooks.php';
require __DIR__ . '/../app/Pricing/class-price-lists.php';
require __DIR__ . '/../app/Pricing/class-public-price.php';

$cases = array(
	array( 'pigeon', 30, 3.5, 'Pigeon 30 kg keeps its price' ),
	array( 'pigeon', 31, null, 'Pigeon 31 kg gets no price from a saved 40 kg band' ),
	array( 'pigeon', 40, null, 'Pigeon 40 kg gets no price from a saved 40 kg band' ),
	array( 'econt', 35, 4.5, 'Econt keeps the saved 40 kg band' ),
);

foreach ( $cases as $case ) {
	$price = \Nashenski\Pricing\Public_Price::calculate( $case[0], 'office', $case[1] );
	if ( $case[2] !== $price ) {
		fwrite( STDERR, 'FAIL: ' . $case[3] . ', got ' . ( null === $price ? 'null' : $price ) . "\n" );
		exit( 1 );
	}
}

echo 'PASS: ' . count( $cases ) . " Pigeon weight cap assertions\n";
