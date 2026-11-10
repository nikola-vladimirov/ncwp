<?php
/**
 * Offline test: Import_Sources::free_over() maps mreja.net free shipping to Nashenski methods. Run: php tests/import-free-over.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, WordPress.Security.EscapeOutput -- offline test script that prints to a terminal.

define( 'ABSPATH', __DIR__ . '/fixtures/' );
require dirname( __DIR__ ) . '/app/Admin/class-import-sources.php';

$free_over = new ReflectionMethod( 'Nashenski\Admin\Import_Sources', 'free_over' );
$keys      = array(
	'office'  => 'receiver_amount_office',
	'automat' => 'receiver_amount_aps',
	'address' => 'receiver_amount',
);
$none      = array(
	'office'  => '',
	'automat' => '',
	'address' => '',
);
$fails     = 0;
$check     = function ( $name, $got, $want ) use ( &$fails ) {
	$ok     = $got === $want;
	$fails += $ok ? 0 : 1;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . ( $ok ? '' : ': ' . wp_json( $got ) ) . "\n";
};

/**
 * JSON for a failure line.
 *
 * @param mixed $value Value.
 */
function wp_json( $value ) {
	return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- no WordPress offline.
}

$check(
	'per-type sums',
	$free_over->invoke(
		null,
		array(
			'office'  => '50',
			'automat' => '',
			'address' => '120.5',
		),
		'99',
		'',
		array(),
		$keys
	),
	array(
		'office'  => array( 'free_shipping_over' => '50' ),
		'automat' => array(),
		'address' => array( 'free_shipping_over' => '120.5' ),
	)
);
$check(
	'one old sum, to office only',
	$free_over->invoke( null, $none, '80', 'on', array(), $keys ),
	array(
		'office'  => array( 'free_shipping_over' => '80' ),
		'automat' => array( 'free_shipping_over' => '80' ),
		'address' => array(),
	)
);
$check(
	'price row where the customer pays nothing lowers one type',
	$free_over->invoke(
		null,
		$none,
		'100',
		'',
		array(
			array(
				'order_amount'           => '60',
				'receiver_amount'        => '5',
				'receiver_amount_office' => '0',
				'receiver_amount_aps'    => '',
			),
			array(
				'order_amount'           => '0',
				'receiver_amount'        => '0',
				'receiver_amount_office' => '0',
			),
		),
		$keys
	),
	array(
		'office'  => array( 'free_shipping_over' => '60' ),
		'automat' => array( 'free_shipping_over' => '100' ),
		'address' => array( 'free_shipping_over' => '100' ),
	)
);
$check(
	'nothing set, rows not an array',
	$free_over->invoke( null, $none, '', '', 'junk', $keys ),
	array(
		'office'  => array(),
		'automat' => array(),
		'address' => array(),
	)
);

echo $fails ? "$fails FAILED\n" : "All passed\n";
exit( $fails ? 1 : 0 );
