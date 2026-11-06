<?php
/**
 * Offline test: a weight band with no price for the parcel type gives no price, never a negative one.
 * Run: php tests/public-price-band-gap.php
 *
 * @package Nashenski_Commerce
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );

	$GLOBALS['saved_list'] = null;

	/**
	 * Get option: the saved price list of the test.
	 *
	 * @param mixed $name Name.
	 */
	function get_option( $name ) {
		unset( $name );
		return $GLOBALS['saved_list'];
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

	require __DIR__ . '/../app/Pricing/Price_Lists.php';
	require __DIR__ . '/../app/Pricing/Public_Price.php';

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
			fwrite( STDERR, 'FAIL: ' . $message . "\n" );
			exit( 1 );
		}
	}

	// Band up to 2 kg has an office price, the band up to 20 kg has none for office or address.
	$GLOBALS['saved_list'] = array(
		'vat_included' => true,
		'bands'        => array(
			array(
				'to'     => 2,
				'office' => '2.62',
			),
			array(
				'to'     => 20,
				'locker' => '5',
			),
		),
		'over'         => array(
			'base'   => array( 'address' => '2' ),
			'per_kg' => array( 'address' => '1' ),
		),
	);

	$price = \Nashenski\Pricing\Public_Price::calculate( 'econt', 'office', 1 );
	expect( 2.62 === $price, 'a band with a price gives that price, got ' . ( null === $price ? 'null' : $price ) );

	$price = \Nashenski\Pricing\Public_Price::calculate( 'econt', 'office', 3 );
	expect( null === $price, 'a weight inside a band with no price for the parcel type gives no price, got ' . ( null === $price ? 'null' : $price ) );

	$price = \Nashenski\Pricing\Public_Price::calculate( 'econt', 'address', 10 );
	expect( null === $price, 'a weight inside a band with no address price gives no price, got ' . ( null === $price ? 'null' : $price ) );

	// Above the last band the over-band formula is unchanged: base 2 + 1 per started kg over 20.
	$price = \Nashenski\Pricing\Public_Price::calculate( 'econt', 'office', 22.5 );
	expect( 5.0 === $price, 'a weight above the last band uses the over-band formula, got ' . ( null === $price ? 'null' : $price ) );

	// A negative price stored by an older version is cleaned to empty and must not turn into a negative rate.
	$GLOBALS['saved_list']['bands'][0]['office'] = '-3';
	$price                                       = \Nashenski\Pricing\Public_Price::calculate( 'econt', 'office', 1 );
	expect( null === $price, 'an old negative band price gives no price, got ' . ( null === $price ? 'null' : $price ) );

	echo 'PASS: ' . $checks . " public price band assertions\n";
}
