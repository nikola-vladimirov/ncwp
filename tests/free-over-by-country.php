<?php // phpcs:disable Generic.Classes.DuplicateClassName, Universal.Files.SeparateFunctionsFromOO, WordPress.NamingConventions.ValidFunctionName, WordPress.WP.AlternativeFunctions -- offline CLI test script: no WordPress, so stubs, unused stub parameters, filesystem calls and plain-text output are on purpose.
/**
 * Offline test: free shipping over a total per country (R63), no price for a foreign order without a widget
 * address (R64), no Pigeon public price above 30 kg (R65). Run: php tests/free-over-by-country.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * WC shipping method stub: options and the rates added.
 */
class WC_Shipping_Method {
	/**
	 * Id.
	 *
	 * @var string
	 */
	public $id = 'nashenski_econt';
	/**
	 * Instance id.
	 *
	 * @var int
	 */
	public $instance_id = 1;
	/**
	 * Title.
	 *
	 * @var string
	 */
	public $title;
	/**
	 * Rates added.
	 *
	 * @var array
	 */
	public $rates = array();

	/**
	 * Get option.
	 *
	 * @param string $key      Key.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	public function get_option( $key, $fallback = '' ) {
		return $fallback;
	}

	/**
	 * Errors added on save.
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * Add error.
	 *
	 * @param string $error Error.
	 */
	public function add_error( $error ) {
		$this->errors[] = $error;
	}

	/**
	 * Add rate.
	 *
	 * @param array $rate Rate.
	 */
	public function add_rate( $rate ) {
		$this->rates[] = $rate;
	}
}

/**
 * Filter stub.
 *
 * @param string $tag   Hook.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $tag, $value ) {
	return $value;
}

/**
 * Translate stub.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text;
}

/**
 * Escape stub.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( $text );
}

/**
 * Action stub.
 */
function do_action() {}

/**
 * Get option: no saved price list, so the defaults are used.
 *
 * @return bool
 */
function get_option() {
	return false;
}

/**
 * Esc url raw.
 *
 * @param string $url Url.
 * @return string
 */
function esc_url_raw( $url ) {
	return $url;
}

/**
 * No widget cookie.
 *
 * @return null
 */
function nashenski_courier_cookie() {
	return null;
}

/**
 * WC stub with a session.
 *
 * @return object
 */
function WC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return (object) array(
		'session' => new class() {
			/**
			 * Get.
			 *
			 * @return array
			 */
			public function get() {
				return array( 'nashenski_econt:1' );
			}

			/**
			 * Set.
			 */
			public function set() {}
		},
	);
}

/**
 * No free shipping coupon.
 *
 * @return bool
 */
function nashenski_has_free_shipping_coupon_in_cart() {
	return false;
}

/**
 * Cart total the test sets.
 *
 * @return float
 */
function nashenski_get_package_total() {
	return $GLOBALS['package_total'];
}

/**
 * Plugin option stub: nothing set.
 *
 * @return string
 */
function nashenski_get_option() {
	return '';
}

require __DIR__ . '/../app/Shipping/Packer/class-packing-limit-exceeded.php';
require __DIR__ . '/../app/Shipping/Packer/class-packing-budget.php';
require __DIR__ . '/../app/Pricing/class-price-lists.php';
require __DIR__ . '/../app/Pricing/class-public-price.php';
require __DIR__ . '/../app/Shipping/Econt/class-method.php';
require __DIR__ . '/../app/Shipping/Speedy/class-method.php';

use Nashenski\Pricing\Price_Lists;

$checks = 0;
/**
 * Expect.
 *
 * @param bool   $condition Condition.
 * @param string $message   Message.
 */
function expect( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		exit( 1 );
	}
}

// R63: parsing.
$parsed = Price_Lists::free_over_list( 'ro=150, GR = 200.5' );
expect( 2 === count( $parsed ) && '150' === $parsed['RO'] && '200.5' === $parsed['GR'], 'valid pairs parse, any case and spacing' );
expect( array( 'GR' => '200' ) === Price_Lists::free_over_list( "RO=abc;GR=200\nDE=-5,XYZ=10,=7,HU,BG=50" ), 'malformed pairs and BG are skipped' );
$thousands = array(
	'GR' => '1000',
	'RO' => '1500000.5',
);
expect( Price_Lists::free_over_list( 'GR=1,000, RO = 1,500,000.5' ) === $thousands, 'a thousands comma stays in the amount' );
$rejected = array();
expect( array( 'HU' => '9' ) === Price_Lists::free_over_list( 'GR=1,0000, RO=1,5,5;HU=9', $rejected ), 'any other comma inside an amount rejects that pair' );
expect( array( 'GR=1,0000', 'RO=1,5,5' ) === $rejected, 'the rejected pairs are reported' );
$decimal = array(
	'GR' => '150.50',
	'RO' => '1000',
	'HU' => '1000.5',
	'DE' => '1.5',
);
expect( Price_Lists::free_over_list( 'GR=150,50, RO=1,000; HU=1000.5, DE=1,5' ) === $decimal, 'a comma before 1 or 2 final digits is the decimal point, before 3 a thousands separator' );
expect( array() === Price_Lists::free_over_list( '' ) && array() === Price_Lists::free_over_list( null ) && array() === Price_Lists::free_over_list( array( 'RO' ) ), 'empty or non-text gives no list' );
$saving = new WC_Shipping_Method();
expect( 'RO=150, GR=200' === Price_Lists::clean_amounts( array( 'free_shipping_by_country' => ' ro=150 ,bad, GR=200 ' ), $saving )['free_shipping_by_country'], 'the saved text is cleaned to valid pairs' );
expect( 1 === count( $saving->errors ) && false !== strpos( $saving->errors[0], 'BAD' ), 'a rejected pair is named in the save error' );
$saving->errors = array();
expect( 'GR=150.50' === Price_Lists::clean_amounts( array( 'free_shipping_by_country' => 'GR=150,50' ), $saving )['free_shipping_by_country'] && array() === $saving->errors, 'GR=150,50 saves as 150.50 with no error' );
expect( 'GR=200' === Price_Lists::clean_amounts( array( 'free_shipping_by_country' => 'BG=50, GR=200' ), $saving )['free_shipping_by_country'] && false !== strpos( $saving->errors[0], 'BG=50' ), 'BG in the abroad list is reported too' );
expect( 'RO=150' === Price_Lists::clean_amounts( array( 'free_shipping_by_country' => 'RO=150, ,' ) )['free_shipping_by_country'], 'no method (REST) and empty pairs: still saves' );

// R63: Bulgaria keeps its own field; other countries only the list.
expect( '100' === Price_Lists::free_over( 'BG', '100', 'BG=5, RO=150' ), 'Bulgaria uses the main field' );
expect( '150' === Price_Lists::free_over( 'RO', '100', 'RO=150' ), 'Romania uses its listed amount' );
expect( '' === Price_Lists::free_over( 'GR', '100', 'RO=150' ), 'an unlisted country gets no free shipping, not the Bulgarian amount' );
expect( '' === Price_Lists::free_over( 'GR', '100', '' ), 'an empty list gives no free shipping abroad' );

// The threshold follows the label's delivery country: the widget country when set, else the package country.
foreach ( array( 'Econt', 'Speedy' ) as $courier ) {
	foreach (
		array(
			array( 'BG', 'BG', 250, true, 'a Bulgarian order over the Bulgarian field is free' ),
			array( 'BG', 'BG', 160, false, 'a Bulgarian order below the Bulgarian field is not free' ),
			array( null, 'BG', 250, true, 'a Bulgarian package with no widget keeps the Bulgarian field' ),
			array( 'BG', 'RO', 160, false, 'a Bulgarian office with a Romanian package uses the Bulgarian field, not RO=150' ),
			array( 'BG', 'RO', 250, true, 'a Bulgarian office with a Romanian package over the Bulgarian field is free' ),
			array( 'RO', 'BG', 120, false, 'a Romanian widget below the Romanian amount is not free' ),
			array( 'RO', 'BG', 160, true, 'a Romanian widget uses the Romanian amount, not the Bulgarian one' ),
			array( '', 'RO', 160, true, 'an empty widget country falls back to the package country' ),
		) as list( $widget, $package, $total, $free, $label )
	) {
		if ( 'Econt' === $courier && $free ) {
			continue; // The Econt free path needs the API container; the not-free cases still prove the country it reads.
		}
		$courier_method                           = ( new ReflectionClass( "Nashenski\\Shipping\\$courier\\Method" ) )->newInstanceWithoutConstructor();
		$courier_method->cookie_data              = null === $widget ? null : array( 'country' => $widget );
		$courier_method->package                  = array( 'destination' => array( 'country' => $package ) );
		$courier_method->free_shipping_over       = '200';
		$courier_method->free_shipping_by_country = 'RO=150';
		$courier_method->fixed_price              = '';
		$GLOBALS['package_total']                 = $total;
		( new ReflectionMethod( $courier_method, 'generate_payment_by_data' ) )->invoke( $courier_method );
		expect( $free === $courier_method->free_shipping, "$courier: $label" );
	}
}

// R65: Pigeon has no price above its 30 kg band; the bands below stay priced with the 17% fuel surcharge.
expect( null === \Nashenski\Pricing\Public_Price::calculate( 'pigeon', 'office', 31 ), 'Pigeon above 30 kg gives no rate' );
expect( round( 3.50 * 1.17, 2 ) === \Nashenski\Pricing\Public_Price::calculate( 'pigeon', 'office', 30 ), 'Pigeon at 30 kg keeps its band price with 17%' );
expect( null !== \Nashenski\Pricing\Public_Price::calculate( 'econt', 'office', 31 ), 'Econt above its last band still uses the formula' );

// R64: no widget address for a foreign order gives no price and flags the missing address.
$method                = ( new ReflectionClass( 'Nashenski\Shipping\Econt\Method' ) )->newInstanceWithoutConstructor();
$method->delivery_type = 'address';
$method->title         = 'Econt';
$method->calculate_shipping(
	array(
		'contents'    => array(),
		'destination' => array( 'country' => 'RO' ),
	)
);
$rate = $method->rates[0] ?? array();
expect( empty( $rate['cost'] ) && empty( $rate['meta_data']['validated'] ) && ! empty( $rate['meta_data']['address_missing'] ), 'a Romanian order without a widget address gets no price and asks for the address' );

$method->rates = array();
$method->calculate_shipping(
	array(
		'contents'    => array(),
		'destination' => array( 'country' => 'BG' ),
	)
);
expect( empty( $method->rates[0]['meta_data']['address_missing'] ), 'a Bulgarian order without a widget address keeps the old message' );

echo 'PASS: ' . (int) $checks . " free shipping by country, foreign address and Pigeon weight assertions\n";
