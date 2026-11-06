<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName, Generic.Classes.DuplicateClassName, Universal.Files.SeparateFunctionsFromOO.Mixed -- standalone offline test: its stubs live in this file.
/**
 * Offline test: Econt and Speedy accept foreign countries only when "Ship abroad" is on; the public price and Speedy's fixed price stay Bulgarian. Run: php tests/international-countries.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * WC shipping method stub with the option store the tests set.
 */
class WC_Shipping_Method {
	/**
	 * Option values.
	 *
	 * @var array
	 */
	public static $options = array();

	/**
	 * Get option.
	 *
	 * @param string $key           Key.
	 * @param mixed  $default_value Default.
	 * @return mixed
	 */
	public function get_option( $key, $default_value = '' ) {
		return self::$options[ $key ] ?? $default_value;
	}
}

/**
 * Filter stub: no filters are hooked in this test.
 *
 * @param string $tag   Hook name.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $tag, $value ) {
	return $value;
}

require __DIR__ . '/../app/Shipping/Econt/class-method.php';
require __DIR__ . '/../app/Shipping/Speedy/class-method.php';

/**
 * Assert helper.
 *
 * @param bool   $ok    Result.
 * @param string $label Label.
 */
function nashenski_intl_assert( $ok, $label ) {
	if ( ! $ok ) {
		echo "FAIL: $label\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
		exit( 1 );
	}
	echo "ok: $label\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, not HTML.
}

/**
 * Whether a courier accepts a country with the given setting.
 *
 * @param string $class_name Method class.
 * @param string $setting    Ship abroad value.
 * @param string $country    ISO code.
 * @return bool
 */
function nashenski_intl_accepts( $class_name, $setting, $country ) {
	WC_Shipping_Method::$options = array( 'international' => $setting );
	$method                      = ( new ReflectionClass( $class_name ) )->newInstanceWithoutConstructor();
	return $method->is_available( array( 'destination' => array( 'country' => $country ) ) );
}

$econt  = 'Nashenski\Shipping\Econt\Method';
$speedy = 'Nashenski\Shipping\Speedy\Method';

nashenski_intl_assert( nashenski_intl_accepts( $econt, 'no', 'BG' ), 'Econt: Bulgaria always accepted' );
nashenski_intl_assert( ! nashenski_intl_accepts( $econt, 'no', 'RO' ), 'Econt: Romania refused when off' );
nashenski_intl_assert( nashenski_intl_accepts( $econt, 'yes', 'RO' ), 'Econt: Romania accepted when on' );
nashenski_intl_assert( nashenski_intl_accepts( $econt, 'yes', 'GR' ), 'Econt: Greece accepted when on' );
nashenski_intl_assert( ! nashenski_intl_accepts( $econt, 'yes', 'DE' ), 'Econt: Germany refused even when on' );
nashenski_intl_assert( ! nashenski_intl_accepts( $speedy, 'no', 'GR' ), 'Speedy: Greece refused when off' );
nashenski_intl_assert( nashenski_intl_accepts( $speedy, 'yes', 'GR' ), 'Speedy: Greece accepted when on' );

foreach ( array( 'RO', 'CY', 'HU', 'CZ', 'HR', 'PL', 'SK', 'SI' ) as $c ) {
	nashenski_intl_assert( nashenski_intl_accepts( $speedy, 'yes', $c ), "Speedy: $c accepted when on" );
}
nashenski_intl_assert( ! nashenski_intl_accepts( $speedy, 'yes', 'DE' ), 'Speedy: Germany refused (not on the price list)' );

$no_country = ( new ReflectionClass( $speedy ) )->newInstanceWithoutConstructor();
nashenski_intl_assert( ! $no_country->is_available( array( 'destination' => array() ) ), 'Speedy: missing country refused without a warning' );

/**
 * A method with a widget country (or none) and a package country.
 *
 * @param string      $courier Method class.
 * @param string|null $widget  Widget country, null when no widget choice yet.
 * @param string      $country Package country.
 * @return object
 */
function nashenski_intl_method( $courier, $widget, $country ) {
	$method              = ( new ReflectionClass( $courier ) )->newInstanceWithoutConstructor();
	$method->cookie_data = null === $widget ? null : array( 'country' => $widget );
	$method->package     = array( 'destination' => array( 'country' => $country ) );
	$method->fixed_price = 5;
	return $method;
}

foreach ( array( $econt, $speedy ) as $class ) {
	$name = substr( $class, 19, -7 );
	nashenski_intl_assert( 'RO' === nashenski_intl_method( $class, 'RO', 'BG' )->courier_country(), "$name: Romanian widget with a Bulgarian package prices Romania (no public price)" );
	nashenski_intl_assert( 'BG' === nashenski_intl_method( $class, null, 'BG' )->courier_country(), "$name: no widget yet falls back to the package country" );
	nashenski_intl_assert( 'RO' === nashenski_intl_method( $class, 'BG', 'RO' )->courier_country(), "$name: a Bulgarian widget with a Romanian package is not Bulgarian" );
	nashenski_intl_assert( 'BG' === nashenski_intl_method( $class, 'BG', '' )->courier_country(), "$name: a Bulgarian widget with a blank package stays Bulgarian" );
	nashenski_intl_assert( 'BG' === nashenski_intl_method( $class, 'BG', 'BG' )->courier_country(), "$name: a Bulgarian widget with a Bulgarian package is Bulgarian" );
	// The label (who pays, Speedy's service) follows the widget, so a Bulgarian office with a Romanian package stays receiver-pays.
	nashenski_intl_assert( 'BG' === nashenski_intl_method( $class, 'BG', 'RO' )->label_country(), "$name: a Bulgarian widget labels as Bulgarian whatever the package" );
	nashenski_intl_assert( 'BG' === nashenski_intl_method( $class, 'BG', 'BG' )->label_country(), "$name: a domestic Bulgarian office labels as Bulgarian" );
	nashenski_intl_assert( 'RO' === nashenski_intl_method( $class, 'RO', 'BG' )->label_country(), "$name: a Romanian widget labels as Romanian" );
	nashenski_intl_assert( 'GR' === nashenski_intl_method( $class, null, 'GR' )->label_country(), "$name: no widget labels with the package country" );
	nashenski_intl_assert( 'GR' === nashenski_intl_method( $class, '', 'GR' )->label_country(), "$name: an empty widget country labels with the package country" );
	nashenski_intl_assert( 'BG' === nashenski_intl_method( $class, '', 'BG' )->label_country(), "$name: an empty widget country with a Bulgarian package labels as Bulgarian" );
}

nashenski_intl_assert( '' === nashenski_intl_method( $speedy, null, 'GR' )->applicable_fixed_price(), 'Speedy: fixed price 5 not used for Greece' );
nashenski_intl_assert( '' === nashenski_intl_method( $speedy, 'GR', 'BG' )->applicable_fixed_price(), 'Speedy: fixed price 5 not used for a Greek widget' );
nashenski_intl_assert( 5 === nashenski_intl_method( $speedy, 'BG', 'BG' )->applicable_fixed_price(), 'Speedy: fixed price 5 used for Bulgaria' );
nashenski_intl_assert( 5 === nashenski_intl_method( $speedy, 'BG', 'RO' )->applicable_fixed_price(), 'Speedy: fixed price 5 used for a Bulgarian office with a Romanian package, like Econt' );
