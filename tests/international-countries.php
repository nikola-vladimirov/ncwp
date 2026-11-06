<?php
/**
 * Offline test: Econt and Speedy accept Romania and Greece (and Speedy only Greece) only when "Ship abroad" is on. Run: php tests/international-countries.php
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
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function get_option( $key, $default = '' ) {
		return self::$options[ $key ] ?? $default;
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

require __DIR__ . '/../app/Shipping/Econt/Method.php';
require __DIR__ . '/../app/Shipping/Speedy/Method.php';

/**
 * Assert helper.
 *
 * @param bool   $ok    Result.
 * @param string $label Label.
 */
function nashenski_intl_assert( $ok, $label ) {
	if ( ! $ok ) {
		echo "FAIL: $label\n";
		exit( 1 );
	}
	echo "ok: $label\n";
}

/**
 * Whether a courier accepts a country with the given setting.
 *
 * @param string $class   Method class.
 * @param string $setting Ship abroad value.
 * @param string $country ISO code.
 * @return bool
 */
function nashenski_intl_accepts( $class, $setting, $country ) {
	WC_Shipping_Method::$options = array( 'international' => $setting );
	$method = ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor();
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
