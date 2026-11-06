<?php // phpcs:ignore WordPress.Files.FileName -- offline test script that declares stub classes, not a plugin class file.
/**
 * Offline test: a BOX NOW zone method saved through the REST API stores no negative threshold or price.
 * Run: php tests/boxnow-zone-negative.php
 *
 * @package Nashenski_Commerce
 */
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed, Generic.Classes.DuplicateClassName -- this offline test stubs WordPress classes and functions in one file.

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * Wc shipping method.
 */
class WC_Shipping_Method {
	/**
	 * Id.
	 *
	 * @var string
	 */
	public $id = 'nashenski_test';

	/**
	 * Enabled.
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
	 * Title.
	 *
	 * @var mixed
	 */
	public $title;

	/**
	 * Tax status.
	 *
	 * @var mixed
	 */
	public $tax_status;

	/**
	 * Instance_form_fields.
	 *
	 * @var mixed
	 */
	public $instance_form_fields;

	/**
	 * Get option.
	 */
	public function get_option() {
		return '';
	}

	/**
	 * Init settings.
	 */
	public function init_settings() {}
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
 * .
 *
 * @param mixed $text Text.
 */
function __( $text ) {
	return $text;
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
 * Wc tax enabled.
 */
function wc_tax_enabled() {
	return true;
}
/**
 * Nashenski get option.
 */
function nashenski_get_option() {
	return false;
}

require __DIR__ . '/../app/Pricing/Price_Lists.php';
require __DIR__ . '/../app/Shipping/BoxNow/Method.php';

$method = new Nashenski\Shipping\BoxNow\Method( 7 );
$hook   = 'woocommerce_shipping_' . $method->id . '_instance_settings_values';
if ( empty( $GLOBALS['filters'][ $hook ] ) ) {
	fwrite( STDERR, "FAIL: BOX NOW does not clean its zone settings on save\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

$settings = array(
	'title'              => 'BOX NOW',
	'free_shipping_over' => '-1',
	'fixed_price'        => '-1',
);
foreach ( $GLOBALS['filters'][ $hook ] as $callback ) {
	$settings = call_user_func( $callback, $settings, $method );
}
if ( '' !== $settings['free_shipping_over'] || '' !== $settings['fixed_price'] || 'BOX NOW' !== $settings['title'] ) {
	fwrite( STDERR, "FAIL: BOX NOW kept a REST -1 or lost the title\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	exit( 1 );
}

echo "PASS: BOX NOW zone settings drop a negative amount\n";
