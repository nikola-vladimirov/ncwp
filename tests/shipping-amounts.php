<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName, Generic.Classes.DuplicateClassName, Universal.Files.SeparateFunctionsFromOO.Mixed -- standalone offline test: its stubs live in this file.
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
	 * Properties WooCommerce declares on the real class (the constructors set them).
	 *
	 * @var mixed
	 */
	public $enabled;
	/**
	 * Instance id.
	 *
	 * @var mixed
	 */
	public $instance_id;
	/**
	 * Method title.
	 *
	 * @var mixed
	 */
	public $method_title;
	/**
	 * Method description.
	 *
	 * @var mixed
	 */
	public $method_description;
	/**
	 * Supports.
	 *
	 * @var mixed
	 */
	public $supports;
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
 * Add filter: keeps the callbacks so the test can run them like WooCommerce does.
 *
 * @param string $name     Hook.
 * @param mixed  $callback Callback.
 */
function add_filter( $name, $callback ) {
	$GLOBALS['filters'][ $name ][] = $callback;
}
/**
 * Absint.
 *
 * @param mixed $value Value.
 */
function absint( $value ) {
	return abs( (int) $value );
}
/**
 * Nashenski.
 */
function nashenski() {
	return new class() {
		/**
		 * Container.
		 */
		public function container() {
			return array();
		}
	};
}
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
		fwrite( STDERR, "FAIL: $message\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI test writes its failure to standard error.
		exit( 1 );
	}
}

foreach ( array( 'Econt', 'Pigeon', 'Speedy' ) as $courier ) {
	$class  = 'Nashenski\\Shipping\\' . $courier . '\\Method';
	$method = ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();

	foreach ( array(
		'-1'   => '',
		'abc'  => '',
		''     => '',
		'0'    => '0',
		'12.5' => '12.5',
		' 7 '  => '7',
	) as $posted => $saved ) {
		expect( $saved === $method->validate_number_field( 'fixed_price', $posted ), "$courier saves '$posted' as '$saved'" );
	}

	foreach ( array( 'free_shipping_over', 'fixed_price' ) as $key ) {
		$method->saved = array( $key => '-1' );
		$method->init();
		expect( empty( $method->$key ), "$courier ignores a stored negative $key" );
		$method->saved = array( $key => '25' );
		$method->init();
		expect( '25' === $method->$key, "$courier keeps a good $key" );
	}

	// A fixed price of zero in any spelling means "not set", so the normal rate applies; a real price is kept.
	foreach ( array(
		'0.00' => '',
		'0'    => '',
		''     => '',
		'4.50' => '4.50',
	) as $stored => $read ) {
		$method->saved = array( 'fixed_price' => $stored );
		$method->init();
		expect( $read === $method->fixed_price, "$courier reads a stored fixed price '$stored' as '$read'" );
	}
}

// The REST API saves zone settings without the form's validators; the filter WooCommerce runs on every save cleans them.
foreach ( array( 'Econt', 'Pigeon', 'Speedy' ) as $courier ) {
	$class  = 'Nashenski\\Shipping\\' . $courier . '\\Method';
	$method = new $class( 7 );
	$hook   = 'woocommerce_shipping_' . $method->id . '_instance_settings_values';
	expect( ! empty( $GLOBALS['filters'][ $hook ] ), "$courier cleans its zone settings on every save" );
	$settings = array(
		'title'              => 'Econt',
		'free_shipping_over' => '-1',
		'fixed_price'        => 'abc',
	);
	foreach ( $GLOBALS['filters'][ $hook ] as $callback ) {
		$settings = call_user_func( $callback, $settings, $method );
	}
	expect( '' === $settings['free_shipping_over'] && '' === $settings['fixed_price'] && 'Econt' === $settings['title'], "$courier saves a REST -1 as empty and keeps the rest" );
	$settings = \Nashenski\Pricing\Price_Lists::clean_amounts(
		array(
			'free_shipping_over' => '30',
			'fixed_price'        => '0',
		)
	);
	expect( '30' === $settings['free_shipping_over'] && '0' === $settings['fixed_price'], "$courier keeps good amounts" );
}

$method                    = ( new ReflectionClass( 'Nashenski\Shipping\BoxNow\Method' ) )->newInstanceWithoutConstructor();
$GLOBALS['boxnow_options'] = array( 'free_shipping_over' => '-1' );
$method->init();
expect( empty( $method->free_shipping_over ), 'BOX NOW ignores a stored negative free shipping limit' );
$GLOBALS['boxnow_options'] = array( 'free_shipping_over' => '80' );
$method->init();
expect( '80' === $method->free_shipping_over, 'BOX NOW keeps a good free shipping limit' );

echo 'PASS: ' . (int) $checks . " shipping amount assertions\n";
