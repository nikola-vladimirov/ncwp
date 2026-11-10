<?php // phpcs:disable WordPress.Files.FileName, Universal.Files.SeparateFunctionsFromOO, Generic.Files.OneObjectStructurePerFile, WordPress.Security, WordPress.WP.GlobalVariablesOverride, Generic.CodeAnalysis.UnusedFunctionParameter, WordPress.NamingConventions, Squiz.Commenting -- offline test: WordPress and WooCommerce stubs and the test share one file.
/**
 * Offline test: Setup_Wizard saves VAT, company, shipping and checkout choices without duplicates. Run: php tests/setup-wizard.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );

class PermissionDenied extends RuntimeException {}
class Redirected extends RuntimeException {}
class WP_Error {
	public function get_error_message() {
		return 'error'; }
}

$GLOBALS['options']  = array();
$GLOBALS['caps']     = array();
$GLOBALS['nonce']    = true;
$GLOBALS['filters']  = array();
$GLOBALS['zones']    = array();
$GLOBALS['instance'] = 0;

function __( $text ) {
	return $text; }
function _n( $one, $many, $n ) {
	return 1 === $n ? $one : $many; }
function esc_html__( $text ) {
	return $text; }
function esc_html( $text ) {
	return htmlspecialchars( (string) $text ); }
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) ); }
function wc_price( $value ) {
	return '<span>' . number_format( (float) $value, 2, ',', '' ) . '&nbsp;&euro;</span>'; }
function wp_strip_all_tags( $text ) {
	return trim( strip_tags( (string) $text ) ); }
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function sanitize_email( $value ) {
	return filter_var( $value, FILTER_SANITIZE_EMAIL ); }
function is_email( $value ) {
	return false !== filter_var( $value, FILTER_VALIDATE_EMAIL ); }
function wp_unslash( $value ) {
	return $value; }
function get_option( $key, $fallback = false ) {
	return $GLOBALS['options'][ $key ] ?? $fallback; }
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['options'][ $key ] = $value;
	return true; }
function nashenski_get_option( $group, $name ) {
	return get_option( 'nashenski_settings_' . $group . '_' . $name ); }
function nashenski_set_option( $group, $name, $value ) {
	return update_option( 'nashenski_settings_' . $group . '_' . $name, $value ); }
function current_user_can( $cap ) {
	return ! empty( $GLOBALS['caps'][ $cap ] ); }
function wp_die( $message ) {
	throw new PermissionDenied( $message ); }
function check_admin_referer( $action, $name = '' ) {
	$GLOBALS['nonce_action'] = $action;
	if ( ! $GLOBALS['nonce'] ) {
		throw new PermissionDenied( 'nonce' ); }
	return true; }
function wp_safe_redirect( $url ) {
	throw new Redirected( $url ); }
function add_query_arg( $key, $value, $url ) {
	return $url . '&' . $key . '=' . $value; }
function admin_url( $path = '' ) {
	return '/wp-admin/' . $path; }
function add_action() {}
function add_filter( $hook, $callback ) {
	$GLOBALS['filters'][ $hook ][] = $callback; }
function apply_filters( $hook, $value ) {
	foreach ( $GLOBALS['filters'][ $hook ] ?? array() as $callback ) {
		$value = $callback( $value ); }
	return $value; }
function apply_filters_deprecated( $hook, $args ) {
	return $args[0]; }
function wp_list_pluck( $list, $field ) {
	return array_map(
		function ( $item ) use ( $field ) {
			return ( (array) $item )[ $field ];
		},
		$list
	); }
function is_wp_error( $thing ) {
	return $thing instanceof WP_Error; }
function wc_format_decimal( $value ) {
	return (string) ( 0 + $value ); }

class WC_Cache_Helper {
	public static function get_transient_version() {}
}

class WC_Tax {
	public static $rates   = array();
	public static $classes = array();
	public static function get_rates_for_tax_class( $tax_class ) {
		$tax_class = self::get_tax_class_by( 'slug', $tax_class ) ? $tax_class : '';
		return array_filter(
			self::$rates,
			function ( $rate ) use ( $tax_class ) {
				return $rate->tax_rate_class === $tax_class;
			}
		); }
	public static function get_tax_class_by( $field, $item ) {
		foreach ( self::$classes as $tax_class ) {
			if ( $tax_class[ $field ] === $item ) {
				return $tax_class; }
		}
		return false; }
	public static function create_tax_class( $name, $slug ) {
		if ( self::get_tax_class_by( 'name', $name ) ) {
			return new WP_Error();
		}
		self::$classes[] = array(
			'name' => $name,
			'slug' => $slug,
		);
		return end( self::$classes ); }
	public static function _insert_tax_rate( $rate ) {
		self::$rates[] = (object) $rate;
		return count( self::$rates ); }
}

/** A zone method: what WooCommerce builds from a registered class. */
class Zone_Method {
	public $id;
	public $instance;
	public function __construct( $id, $instance ) {
		$this->id       = $id;
		$this->instance = $instance; }
	public function get_option( $key ) {
		return get_option( "woocommerce_{$this->id}_{$this->instance}_settings", array() )[ $key ] ?? ''; }
	public function get_title() {
		return $this->get_option( 'title' ); }
}

class WC_Shipping_Zone {
	private $id = 0;
	private $name = '';
	private $locations = array();
	public function __construct( $id = null ) {
		if ( $id ) {
			$this->id        = $id;
			$this->name      = $GLOBALS['zones'][ $id ]['name'];
			$this->locations = $GLOBALS['zones'][ $id ]['locations'];
		} }
	public function get_id() {
		return $this->id; }
	public function get_zone_name() {
		return $this->name; }
	public function set_zone_name( $name ) {
		$this->name = $name; }
	public function add_location( $code, $type ) {
		// Like WooCommerce: an unsaved zone (id 0) takes no locations.
		if ( 0 !== $this->id ) {
			$this->locations[] = $type . ':' . $code; } }
	public function save() {
		$this->id                    = $this->id ? $this->id : count( $GLOBALS['zones'] ) + 1;
		$GLOBALS['zones'][ $this->id ] = array(
			'name'      => $this->name,
			'locations' => $this->locations,
			'methods'   => $GLOBALS['zones'][ $this->id ]['methods'] ?? array(),
		); }
	public function get_shipping_methods() {
		$allowed = apply_filters( 'woocommerce_shipping_methods', array() );
		$methods = array();
		foreach ( $GLOBALS['zones'][ $this->id ]['methods'] ?? array() as $instance => $method_id ) {
			if ( isset( $allowed[ $method_id ] ) ) {
				$methods[ $instance ] = new Zone_Method( $method_id, $instance ); }
		}
		return $methods; }
	public function add_shipping_method( $type ) {
		// Like WooCommerce: only registered methods are added.
		if ( ! isset( apply_filters( 'woocommerce_shipping_methods', array() )[ $type ] ) ) {
			return 0; }
		$instance                                         = ++$GLOBALS['instance'];
		$GLOBALS['zones'][ $this->id ]['methods'][ $instance ] = $type;
		return $instance; }
}

class WC_Shipping_Zones {
	public static function get_zone_matching_package( $package ) {
		foreach ( $GLOBALS['zones'] as $id => $zone ) {
			if ( in_array( 'country:' . $package['destination']['country'], $zone['locations'], true ) ) {
				return new WC_Shipping_Zone( $id ); }
		}
		return new WC_Shipping_Zone( 0 ); }
}

require dirname( __DIR__ ) . '/app/autoload.php';

use Nashenski\Admin\Setup_Wizard;

$fails = 0;
$check = function ( $name, $ok ) use ( &$fails ) {
	$fails += $ok ? 0 : 1;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $name . "\n";
};
$run   = function ( $form ) {
	$_POST = array( 'nashenski_setup' => $form );
	try {
		Setup_Wizard::handle();
	} catch ( Redirected $e ) {
		return $e->getMessage();
	}
	return null;
};
$bg    = function ( $tax_class ) {
	return array_values(
		array_filter(
			WC_Tax::get_rates_for_tax_class( $tax_class ),
			function ( $rate ) {
				return 'BG' === $rate->tax_rate_country;
			}
		)
	);
};
$zone_methods = function ( $zone_id ) {
	$out = array();
	foreach ( $GLOBALS['zones'][ $zone_id ]['methods'] ?? array() as $instance => $method_id ) {
		$out[] = array( $method_id ) + get_option( "woocommerce_{$method_id}_{$instance}_settings", array() );
	}
	return $out;
};

// Permission and nonce.
$_POST = array( 'nashenski_setup' => array( 'vat' => 'none' ) );
try {
	Setup_Wizard::handle();
	$check( 'no capability is refused', false );
} catch ( PermissionDenied $e ) {
	$check( 'no capability is refused', ! get_option( Setup_Wizard::DONE_OPTION ) && ! get_option( 'woocommerce_calc_taxes' ) );
}

$GLOBALS['caps']['manage_woocommerce'] = true;
$GLOBALS['nonce']                      = false;
try {
	Setup_Wizard::handle();
	$check( 'bad nonce is refused', false );
} catch ( PermissionDenied $e ) {
	$check( 'bad nonce is refused', 'nashenski_setup' === $GLOBALS['nonce_action'] && ! get_option( Setup_Wizard::DONE_OPTION ) && ! get_option( 'woocommerce_calc_taxes' ) );
}
$GLOBALS['nonce'] = true;

// VAT 20%.
$vat20 = array(
	'vat'                => 'vat20',
	'prices_include_tax' => 'yes',
	'invoices'           => 'always',
	'nap'                => array(
		'company_name' => 'Firma <b>OOD</b>',
		'eik'          => '123 456 789',
		'dds_number'   => 'bg123456789',
		'email'        => 'shop@example.com',
	),
);
$url   = $run( $vat20 );
$o     = $GLOBALS['options'];
$check( 'redirects to the result screen', false !== strpos( (string) $url, 'nashenski_setup_saved=1' ) );
$check( 'vat20 turns taxes on with prices shown with VAT', 'yes' === $o['woocommerce_calc_taxes'] && 'yes' === $o['woocommerce_prices_include_tax'] && 'incl' === $o['woocommerce_tax_display_shop'] && 'incl' === $o['woocommerce_tax_display_cart'] );
$rate = $bg( '' );
$check( 'vat20 adds one BG 20% standard rate named VAT, shipping taxed, priority 1', 1 === count( $rate ) && '20' === $rate[0]->tax_rate && 'VAT' === $rate[0]->tax_rate_name && 1 === $rate[0]->tax_rate_shipping && 1 === $rate[0]->tax_rate_priority );
$check( 'company fields are sanitized and saved', 'Firma OOD' === $o['nashenski_settings_nap_company_name'] && '123456789' === $o['nashenski_settings_nap_eik'] && 'BG123456789' === $o['nashenski_settings_nap_dds_number'] && 'shop@example.com' === $o['nashenski_settings_nap_email'] );
$check( 'invoices always turns documents on', 'always' === $o['nashenski_settings_invoice_invoices'] && 'yes' === $o['nashenski_settings_apis_enable_documents'] );
$check( 'done flag is stored', is_array( $o[ Setup_Wizard::DONE_OPTION ] ) && ! $o[ Setup_Wizard::DONE_OPTION ]['reduced'] );

$run( $vat20 );
$check( 'rerun adds no second standard rate', 1 === count( WC_Tax::$rates ) );

// VAT 9%: the class is missing and gets created.
$run( array( 'vat' => 'vat9' ) + $vat20 );
$check( 'vat9 creates the Reduced rate class once', 1 === count( WC_Tax::$classes ) && 'reduced-rate' === WC_Tax::$classes[0]['slug'] );
$reduced = $bg( 'reduced-rate' );
$check( 'vat9 adds one BG 9% rate in Reduced rate', 1 === count( $reduced ) && '9' === $reduced[0]->tax_rate && 1 === count( $bg( '' ) ) );
$check( 'vat9 asks to set the class on products', true === get_option( Setup_Wizard::DONE_OPTION )['reduced'] );
$run( array( 'vat' => 'vat9' ) + $vat20 );
$check( 'rerun of vat9 adds nothing', 2 === count( WC_Tax::$rates ) && 1 === count( WC_Tax::$classes ) );

// Not registered.
$run(
	array(
		'vat' => 'none',
		'nap' => array( 'dds_number' => 'BG999999999' ),
	)
);
$check( 'none turns taxes off and keeps the rates', 'no' === get_option( 'woocommerce_calc_taxes' ) && 2 === count( WC_Tax::$rates ) );
$check( 'none leaves the VAT number as it was', 'BG123456789' === get_option( 'nashenski_settings_nap_dds_number' ) );

// Invalid values are reported, not saved.
$run(
	array(
		'nap'       => array(
			'eik'   => 'abc',
			'email' => 'not-an-email',
		),
		'free_over' => '-5',
	)
);
$done = get_option( Setup_Wizard::DONE_OPTION );
$check( 'invalid EIK, email and free total are reported and not saved', 3 === count( $done['errors'] ) && '123456789' === get_option( 'nashenski_settings_nap_eik' ) && 'shop@example.com' === get_option( 'nashenski_settings_nap_email' ) );

// Shipping: no zone for Bulgaria yet, couriers not turned on.
$GLOBALS['options']['nashenski_settings_apis_enable_econt'] = 'no';
$ship = array(
	'couriers'        => array( 'econt', 'boxnow', 'bogus' ),
	'types'           => array( 'office', 'locker' ),
	'free_over'       => '50,5',
	'layout'          => 'buttons',
	'free_over_label' => 'yes',
);
$run( $ship );
$check( 'zone Bulgaria is created with country BG', 1 === count( $GLOBALS['zones'] ) && 'Bulgaria' === $GLOBALS['zones'][1]['name'] && array( 'country:BG' ) === $GLOBALS['zones'][1]['locations'] );
$check( 'chosen couriers are turned on', 'yes' === get_option( 'nashenski_settings_apis_enable_econt' ) && 'yes' === get_option( 'nashenski_settings_apis_enable_boxnow' ) && false === get_option( 'nashenski_settings_apis_enable_bogus' ) );
$check(
	'one method per courier and type, named, with the free total (not on BOX NOW)',
	array(
		array(
			'nashenski_econt',
			'title'              => 'Econt to an office',
			'delivery_type'      => 'office',
			'free_shipping_over' => '50.5',
		),
		array(
			'nashenski_econt',
			'title'              => 'Econt to a parcel locker',
			'delivery_type'      => 'automat',
			'free_shipping_over' => '50.5',
		),
		array(
			'nashenski_boxnow',
			'title' => 'BOX NOW to a parcel locker',
		),
	) === $zone_methods( 1 )
);
$check( 'BOX NOW free total goes to its global setting when empty', '50.5' === get_option( 'nashenski_settings_boxnow_price_free_shipping_over' ) );
$check( 'checkout look is saved', 'buttons' === get_option( 'nashenski_settings_appearance_layout' ) && 'yes' === get_option( 'nashenski_settings_appearance_free_over_label' ) && 'no' === get_option( 'nashenski_settings_appearance_free_progress' ) );

$GLOBALS['options']['nashenski_settings_boxnow_price_free_shipping_over'] = '30';
$run( array( 'free_over' => '80' ) + $ship );
$check( 'rerun adds no zone and no method', 1 === count( $GLOBALS['zones'] ) && 3 === count( $zone_methods( 1 ) ) );
$check( 'BOX NOW free total is kept when set', '30' === get_option( 'nashenski_settings_boxnow_price_free_shipping_over' ) );

// An existing zone is reused, and a method of a courier that is off still counts.
$GLOBALS['zones']    = array(
	7 => array(
		'name'      => 'Bulgaria, all',
		'locations' => array( 'country:BG' ),
		'methods'   => array( 100 => 'nashenski_speedy' ),
	),
);
$GLOBALS['instance'] = 100;
update_option( 'woocommerce_nashenski_speedy_100_settings', array( 'delivery_type' => 'office' ) );
$run(
	array(
		'couriers' => array( 'speedy' ),
		'types'    => array( 'office', 'address' ),
	)
);
$methods = $zone_methods( 7 );
$check( 'existing zone is reused, only the missing type is added', 1 === count( $GLOBALS['zones'] ) && 2 === count( $methods ) && 'address' === $methods[1]['delivery_type'] && 'Speedy to an address' === $methods[1]['title'] && ! isset( $methods[1]['free_shipping_over'] ) );

$run( array( 'couriers' => array( 'pigeon' ) ) );
$check( 'a courier without delivery types adds nothing and says so', 2 === count( $zone_methods( 7 ) ) && 1 === count( get_option( Setup_Wizard::DONE_OPTION )['errors'] ) );

// Recommended settings run first, the VAT choice wins over them.
$GLOBALS['options']['woocommerce_calc_taxes']         = 'yes';
$GLOBALS['options']['woocommerce_prices_include_tax'] = 'no';
$run(
	array(
		'vat'         => 'vat20',
		'recommended' => 'yes',
	)
);
$check( 'recommended settings are applied, the currency is not switched', 'required' === get_option( 'woocommerce_checkout_phone_field' ) && 'EUR' !== get_option( 'woocommerce_currency' ) );
$check( 'unticked "entered with VAT" wins over the recommended yes', 'no' === get_option( 'woocommerce_prices_include_tax' ) );

// A store that shows prices without VAT keeps that when it runs the setup again.
$GLOBALS['options']['woocommerce_tax_display_shop'] = 'excl';
$run( $vat20 );
$check( 'rerun with taxes on keeps the price display', 'excl' === get_option( 'woocommerce_tax_display_shop' ) );

// Documents turned off stay off when the invoice choice is the same as before.
$GLOBALS['options']['nashenski_settings_apis_enable_documents'] = 'no';
$run( $vat20 );
$check( 'same invoice choice does not turn documents back on', 'no' === get_option( 'nashenski_settings_apis_enable_documents' ) );

// A store installed in Bulgarian: the reduced class has a translated slug.
WC_Tax::$rates   = array();
WC_Tax::$classes = array(
	array(
		'name' => 'Reduced rate',
		'slug' => 'namalena-stavka',
	),
);
$run( array( 'vat' => 'vat9' ) + $vat20 );
$check( 'vat9 uses the existing reduced class by its name', 1 === count( WC_Tax::$classes ) && 1 === count( $bg( 'namalena-stavka' ) ) && '9' === $bg( 'namalena-stavka' )[0]->tax_rate && true === get_option( Setup_Wizard::DONE_OPTION )['reduced'] );

// The importer keeps its own titles and counts through the shared helper.
$GLOBALS['wpdb'] = (object) array( 'prefix' => 'wp_' );
$count           = array( 'zones' => 0 );
$import          = new ReflectionMethod( 'Nashenski\Admin\Import_Sources', 'zones' );
$args            = array( null, 'econt', array( 'office', 'automat' ), &$count, array( 'automat' => array( 'free_shipping_over' => '40' ) ) );
$import->invokeArgs( null, $args );
$methods = $zone_methods( 7 );
$check(
	'importer adds its methods to the zone of Bulgaria with its titles',
	2 === $count['zones'] && 4 === count( $methods ) && array(
		'nashenski_econt',
		'title'         => 'To an office',
		'delivery_type' => 'office',
	) === $methods[2] && array(
		'nashenski_econt',
		'title'              => 'To a parcel locker',
		'delivery_type'      => 'automat',
		'free_shipping_over' => '40',
	) === $methods[3]
);

echo $fails ? "$fails FAILED\n" : "All passed\n";
exit( $fails ? 1 : 0 );
