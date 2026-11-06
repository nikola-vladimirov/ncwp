<?php
require __DIR__ . '/pigeon-resources.php';
$payment_start = $checks;
fixture();
$cookie['fixed_price'] = '-7.25';
$cookie['cod_amount']  = '-100';
$cookie['unknown']     = 'untrusted';
$clean                 = \Nashenski\Shipping\Pigeon\Method::get_cookie_data();
expect( ! isset( $clean['fixed_price'] ) && ! isset( $clean['cod_amount'] ), 'Guest monetary fields must be discarded before rate persistence' );
expect( $clean['selectedOffice'] === 1 && $clean['payment'] === 'card', 'Legitimate picker fields remain' );
$m = new PigeonMethod();
$m->calculate_shipping( package( 1 ) );
expect( ! isset( $m->rates[0]['meta_data']['cookie_data']['fixed_price'] ) && ! isset( $m->rates[0]['meta_data']['cookie_data']['unknown'] ), 'Actual rate metadata contains only allowed picker fields' );
fixture();
$cookie['payment']     = 'cod';
$cookie['fixed_price'] = '-50';
$m                     = new PigeonMethod();
$m->fixed_price        = 5;
$m->calculate_shipping( package( 1 ) );
expect( $last_request['who_pays'] === 'sender' && (float) $last_request['service_codes']['cod_amount'] === 105.0, 'Server fixed shipping is still added once to checkout COD' );
expect( $m->rates[0]['cost'] === 5, 'Merchant fixed checkout price preserved' );
fixture();
$cookie['payment']     = 'cod';
$cookie['fixed_price'] = '-50';
$free_coupon           = true;
$m                     = new PigeonMethod();
$m->fixed_price        = 5;
$m->calculate_shipping( package( 1 ) );
expect( $last_request['who_pays'] === 'sender' && (float) $last_request['service_codes']['cod_amount'] === 100.0 && $m->rates[0]['cost'] === 0, 'Free shipping still excludes fixed shipping from COD' );
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
	$clean['selectedAddress'] === array(
		'orig_key' => 'street-1',
		'label'    => 'Street',
	),
	'Nested address data cannot retain arbitrary monetary fields'
);
expect( \Nashenski\Shipping\Pigeon\Payment::cookie( 'not an object' ) === array(), 'Malformed cookie has no authoritative fields' );
echo 'PASS: ' . ( $checks - $payment_start ) . " Pigeon payment assertions\n";
