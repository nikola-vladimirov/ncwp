<?php
/**
 * Offline test: "Free shipping over" and "Fixed price" never take a negative or non-numeric value.
 * Run: php tests/shipping-amounts.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * Wc shipping method.
 */
class WC_Shipping_Method {
	/**
	 * Saved settings.
	 *
	 * @var array
	 */
	public $saved = array();

	/**
	 * Id.
	 *
	 * @var string
	 */
	public $id = 'nashenski_test';

	/**
	 * Title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Tax status.
	 *
	 * @var string
	 */
	public $tax_status;

	/**
	 * Instance form fields.
	 *
	 * @var array
	 */
	public $instance_form_fields;

	/**
	 * Form fields.
	 *
	 * @var array
	 */
	public $form_fields;

	/**
	 * Get option.
	 *
	 * @param string $key Key.
	 */
	public function get_option( $key ) {
		return $this->saved[ $key ] ?? '';
	}

	/**
	 * Validate text field.
	 *
	 * @param string $key   Key.
	 * @param string $value Value.
	 */
	public function validate_text_field( $key, $value ) {
		return trim( $value );
	}

	/**
	 * Init settings.
	 */
	public function init_settings() {}
}

/**
 * .
 *
 * @param mixed $text Text.
 */
function __( $text ) {
	return $text;
}
/**
 * Add action.
 */
function add_action() {}
/**
 * Wc tax enabled.
 */
function wc_tax_enabled() {
	return true;
}
/**
 * Nashenski get shipping tests options.
 */
function nashenski_get_shipping_tests_options() {
	return array();
}
/**
 * Nashenski get option.
 *
 * @param mixed $group Group.
 * @param mixed $name Name.
 */
function nashenski_get_option( $group, $name ) {
	return $GLOBALS['boxnow_options'][ $name ] ?? false;
}

require __DIR__ . '/../app/Pricing/Price_Lists.php';
foreach ( array( 'BoxNow', 'Econt', 'Pigeon', 'Speedy' ) as $courier ) {
	require __DIR__ . "/../app/Shipping/$courier/Method.php";
}

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
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

foreach ( array( 'Econt', 'Pigeon', 'Speedy' ) as $courier ) {
	$class = 'Nashenski\\Shipping\\' . $courier . '\\Method';
	$m     = ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();

	foreach ( array(
		'-1'   => '',
		'abc'  => '',
		''     => '',
		'0'    => '0',
		'12.5' => '12.5',
		' 7 '  => '7',
	) as $posted => $saved ) {
		expect( $saved === $m->validate_number_field( 'fixed_price', $posted ), "$courier saves '$posted' as '$saved'" );
	}

	foreach ( array( 'free_shipping_over', 'fixed_price' ) as $key ) {
		$m->saved = array( $key => '-1' );
		$m->init();
		expect( empty( $m->$key ), "$courier ignores a stored negative $key" );
		$m->saved = array( $key => '25' );
		$m->init();
		expect( '25' === $m->$key, "$courier keeps a good $key" );
	}
}

$m                         = ( new ReflectionClass( 'Nashenski\Shipping\BoxNow\Method' ) )->newInstanceWithoutConstructor();
$GLOBALS['boxnow_options'] = array( 'free_shipping_over' => '-1' );
$m->init();
expect( empty( $m->free_shipping_over ), 'BOX NOW ignores a stored negative free shipping limit' );
$GLOBALS['boxnow_options'] = array( 'free_shipping_over' => '80' );
$m->init();
expect( '80' === $m->free_shipping_over, 'BOX NOW keeps a good free shipping limit' );

echo 'PASS: ' . $checks . " shipping amount assertions\n";
