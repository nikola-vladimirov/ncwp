<?php // phpcs:disable WordPress.Security.EscapeOutput -- offline CLI test script: no WordPress, plain-text output on purpose.
/**
 * Offline test: Pigeon payment data from the guest cookie cannot set money fields. Run: php tests/pigeon-payment.php
 *
 * @package Nashenski_Commerce
 */

require __DIR__ . '/pigeon-resources.php';
$payment_start = $checks;
fixture();
$cookie['fixed_price'] = '-7.25';
$cookie['cod_amount']  = '-100';
$cookie['unknown']     = 'untrusted';
$clean                 = \Nashenski\Shipping\Pigeon\Method::get_cookie_data();
expect( ! isset( $clean['fixed_price'] ) && ! isset( $clean['cod_amount'] ), 'Guest monetary fields must be discarded before rate persistence' );
expect( 1 === $clean['selectedOffice'] && 'card' === $clean['payment'], 'Legitimate picker fields remain' );
$method = new PigeonMethod();
$method->calculate_shipping( package( 1 ) );
expect( ! isset( $method->rates[0]['meta_data']['cookie_data']['fixed_price'] ) && ! isset( $method->rates[0]['meta_data']['cookie_data']['unknown'] ), 'Actual rate metadata contains only allowed picker fields' );
fixture();
$cookie['payment']     = 'cod';
$cookie['fixed_price'] = '-50';
$method                = new PigeonMethod();
$method->fixed_price   = 5;
$method->calculate_shipping( package( 1 ) );
expect( 'sender' === $last_request['who_pays'] && 105.0 === (float) $last_request['service_codes']['cod_amount'], 'Server fixed shipping is still added once to checkout COD' );
expect( 5 === $method->rates[0]['cost'], 'Merchant fixed checkout price preserved' );
fixture();
$cookie['payment']     = 'cod';
$cookie['fixed_price'] = '-50';
$free_coupon           = true;
$method                = new PigeonMethod();
$method->fixed_price   = 5;
$method->calculate_shipping( package( 1 ) );
expect( 'sender' === $last_request['who_pays'] && 100.0 === (float) $last_request['service_codes']['cod_amount'] && 0 === $method->rates[0]['cost'], 'Free shipping still excludes fixed shipping from COD' );
$clean = \Nashenski\Shipping\Pigeon\Payment::cookie(
	array(
		'selectedAddress' => array(
			'orig_key'    => 'street-1',
			'label'       => 'Street',
			'fixed_price' => -99,
		),
		'fixed_price'     => -99,
	)
);
expect(
	array(
		'orig_key' => 'street-1',
		'label'    => 'Street',
	) === $clean['selectedAddress'],
	'Nested address data cannot retain arbitrary monetary fields'
);
expect( array() === \Nashenski\Shipping\Pigeon\Payment::cookie( 'not an object' ), 'Malformed cookie has no authoritative fields' );
echo 'PASS: ' . ( $checks - $payment_start ) . " Pigeon payment assertions\n";
