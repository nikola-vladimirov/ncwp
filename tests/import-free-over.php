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
				'country_id'             => 'GRC',
			),
			array(
				'order_amount'           => '20',
				'receiver_amount'        => '0',
				'receiver_amount_office' => '0',
				'type'                   => 'incod',
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
	'a free row from 0 is free for every order; a missing locker price is the office price',
	$free_over->invoke(
		null,
		$none,
		'',
		'',
		array(
			array(
				'order_amount'           => '',
				'receiver_amount'        => '4',
				'receiver_amount_office' => '0',
			),
		),
		$keys
	),
	array(
		'office'  => array( 'free_shipping_over' => '0.01' ),
		'automat' => array( 'free_shipping_over' => '0.01' ),
		'address' => array(),
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

// Legacy rows stored serialized: read as plain data only. A damaged row, a string length no text can have, nesting past the limit and any object give false.
/**
 * Stub: as WordPress, only the look of the value.
 *
 * @param mixed $value Value.
 * @return bool
 */
function is_serialized( $value ) {
	return is_string( $value ) && (bool) preg_match( '/^(N;|[bid]:.*;|s:\d+:".*";|[aOC]:\d+:.*[;}])$/s', trim( $value ) );
}

$read = new ReflectionMethod( 'Nashenski\Admin\Import_Sources', 'safe_unserialize' );
$row  = array(
	'name'    => 'Офис "Център"; a:1:{s:1:"x";}',
	'count'   => 12,
	'minus'   => -3,
	'price'   => 5.9,
	'on'      => true,
	'off'     => false,
	'none'    => null,
	'empty'   => '',
	7         => array( 'nested', array( 'deep' => 'да' ) ),
	'numeric' => array( '15' => 'fifteen' ),
);
$warn = array();
set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- the test fails on any PHP notice or warning.
	function ( $level, $message ) use ( &$warn ) {
		$warn[] = $message;

		return true;
	}
);
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- the test makes the rows the old plugins stored.
$check( 'serialized row: the same data back', $read->invoke( null, serialize( $row ) ), $row );
$check( 'not serialized, with text after a value: as it is', $read->invoke( null, 'a:1:{i:0;i:1;}x' ), 'a:1:{i:0;i:1;}x' );
$check( 'serialized row with spaces around it', $read->invoke( null, '  ' . serialize( array( 'a' => 1 ) ) . "\n" ), array( 'a' => 1 ) );

foreach ( array( 'text', 0, 5.5, true, false, null, array(), 'b:0;' ) as $plain ) {
	$check( 'serialized ' . wp_json( $plain ), $read->invoke( null, serialize( $plain ) ), $plain );
}

$check( 'not serialized: as it is', $read->invoke( null, 'plain text' ), 'plain text' );
$check( 'not a string: as it is', $read->invoke( null, array( 1 ) ), array( 1 ) );
$check( 'normal rows: no PHP notice or warning', $warn, array() );

$check( 'damaged row (a string cut short): false', $read->invoke( null, 'a:2:{s:4:"name";s:10:"short";s:1:"b";i:1;}' ), false );
$check( 'damaged row (fewer pairs than it says): false', $read->invoke( null, 'a:3:{i:0;i:1;}' ), false );
$check( 'damaged number: false', $read->invoke( null, 'i:12a;' ), false );
$check( 'a string length no text can have: false, no crash', $read->invoke( null, 'a:1:{i:0;s:99999999999999999999999:"x";}' ), false );
$check( 'nesting past the limit: false', $read->invoke( null, str_repeat( 'a:1:{i:0;', 80 ) . 'i:1;' . str_repeat( '}', 80 ) ), false );
$check( 'nesting within the limit: read', is_array( $read->invoke( null, str_repeat( 'a:1:{i:0;', 30 ) . 'i:1;' . str_repeat( '}', 30 ) ) ), true );
$check( 'an object inside the data: false', $read->invoke( null, serialize( array( 'deep' => array( 'o' => new ArrayObject( array( 1 ) ) ) ) ) ), false );
$check( 'an object row: false', $read->invoke( null, 'O:8:"stdClass":1:{s:1:"a";i:1;}' ), false );
// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
restore_error_handler();

// Order meta comes from WooCommerce already unserialized: only plain data is copied.
$plain = new ReflectionMethod( 'Nashenski\Admin\Import_Sources', 'plain' );
$check( 'plain data stays as it is', $plain->invoke( null, $row ), $row );
$check( 'a value with an object in it is dropped', $plain->invoke( null, array( 'a' => array( 'b' => new stdClass() ) ) ), false );
$check( 'a class PHP may not build is dropped', $plain->invoke( null, array( unserialize( 'O:7:"Missing":0:{}', array( 'allowed_classes' => false ) ) ) ), false ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- the test makes the placeholder object on purpose.

echo $fails ? "$fails FAILED\n" : "All passed\n";
exit( $fails ? 1 : 0 );
