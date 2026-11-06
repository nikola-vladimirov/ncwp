<?php // phpcs:disable Generic.Classes.DuplicateClassName, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, Universal.Namespaces, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions -- offline CLI test script: no WordPress, so stubs, unused stub parameters, filesystem calls and plain-text output are on purpose.
/**
 * Offline test: the defaults shown in the settings match what the code does when nothing is
 * saved (courier environments demo, PDF logo medium, BOX NOW price type weight bands), and a
 * saved value wins over each default.
 * Run: php tests/settings-defaults.php
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Container {
	/**
	 * Container client names fixture.
	 */
	class Client {
		const BOXNOW = 'boxnow';
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );

	/**
	 * WooCommerce settings page fixture.
	 */
	class WC_Settings_Page {}

	/**
	 * Translate.
	 *
	 * @param string $text Text.
	 */
	function __( $text ) {
		return $text;
	}
	/**
	 * Esc html.
	 *
	 * @param string $text Text.
	 */
	function esc_html( $text ) {
		return $text;
	}
	/**
	 * Sanitize title with dashes.
	 *
	 * @param string $text Text.
	 */
	function sanitize_title_with_dashes( $text ) {
		return $text;
	}
	/**
	 * Wp kses post.
	 *
	 * @param string $text Text.
	 */
	function wp_kses_post( $text ) {
		return $text;
	}
	/**
	 * Wp strip all tags.
	 *
	 * @param string $text Text.
	 */
	function wp_strip_all_tags( $text ) {
		return $text;
	}
	/**
	 * Apply filters.
	 *
	 * @param string $name  Hook.
	 * @param mixed  $value Value.
	 */
	function apply_filters( $name, $value ) {
		return $value;
	}
	/**
	 * Currency symbol.
	 */
	function get_woocommerce_currency_symbol() {
		return 'EUR';
	}
	/**
	 * Saved options, false when not saved (as WordPress).
	 *
	 * @param string $name    Option.
	 * @param mixed  $fallback Default.
	 */
	function get_option( $name, $fallback = false ) {
		return $GLOBALS['saved'][ $name ] ?? $fallback;
	}

	require __DIR__ . '/../app/functions.php';
	foreach ( array( 'base-field', 'select-field', 'text-field', 'truefalse-field', 'image-field', 'color-field', 'multi-field', 'multi-two-field' ) as $class ) {
		require __DIR__ . "/../app/Admin/Fields/class-$class.php";
	}
	foreach ( array( 'base-tab', 'econt-tab', 'boxnow-tab', 'pigeon-tab', 'nra-tab' ) as $class ) {
		require __DIR__ . "/../app/Admin/Tabs/class-$class.php";
	}
	require __DIR__ . '/../app/Admin/class-settings.php';
	require __DIR__ . '/../app/Admin/class-settings-page.php';
	require __DIR__ . '/../app/Invoice/PDF/class-style.php';
	require __DIR__ . '/../app/Client/class-courier-client.php';

	$checks = 0;
	/**
	 * Expect.
	 *
	 * @param mixed  $condition Condition.
	 * @param string $message   Message.
	 */
	function expect( $condition, $message ) {
		++$GLOBALS['checks'];
		if ( ! $condition ) {
			fwrite( STDERR, "FAIL: $message\n" );
			exit( 1 );
		}
	}

	/**
	 * The value WooCommerce shows for a field: the saved value, else the setting default.
	 *
	 * @param string $group  Option group.
	 * @param array  $fields Fields.
	 * @param string $name   Field name.
	 */
	function shown( $group, $fields, $name ) {
		foreach ( $fields as $field ) {
			if ( $field->get_name() === $name ) {
				$setting = \Nashenski\Admin\Settings_Page::to_wc_setting( $group, $field );
				return get_option( $setting['id'], $setting['default'] ?? '' );
			}
		}
		return null;
	}

	/**
	 * A tab's fields, built without its constructor (no container).
	 *
	 * @param string $tab_class Tab class.
	 */
	function tab_fields( $tab_class ) {
		$tab = ( new ReflectionClass( "Nashenski\\Admin\\Tabs\\$tab_class" ) )->newInstanceWithoutConstructor();
		$tab->load_fields();
		return $tab->get_fields();
	}

	/**
	 * The BOX NOW price fields (not connected, so no locker locations are fetched).
	 */
	function boxnow_price_fields() {
		$tab       = ( new ReflectionClass( 'Nashenski\Admin\Tabs\BoxNow_Tab' ) )->newInstanceWithoutConstructor();
		$container = new ReflectionProperty( $tab, 'container' );
		if ( PHP_VERSION_ID < 80100 ) {
			$container->setAccessible( true );
		}
		$container->setValue(
			$tab,
			array(
				'boxnow' => new class() {
					/** No token: not connected. */
					public function get_access_token() {
						return '';
					}
				},
			)
		);
		return $tab->add_send_from_fields( array() )['boxnow_price'];
	}

	/**
	 * The endpoint a courier client uses for the saved environment.
	 *
	 * @param string $courier Courier group.
	 */
	function endpoint( $courier ) {
		// A courier client with only the endpoint logic of the shared trait.
		$client      = new class() {
			use \Nashenski\Client\Courier_Client;

			const LIVE_URL = 'live-url';
			const DEMO_URL = 'demo-url';

			/**
			 * Environment.
			 *
			 * @var string
			 */
			public $env;

			/**
			 * Endpoint.
			 *
			 * @var string
			 */
			public $endpoint;

			/** Get env. */
			public function get_env() {
				return $this->env;
			}

			/**
			 * Set base endpoint.
			 *
			 * @param string $endpoint Endpoint.
			 */
			public function set_base_endpoint( $endpoint ) {
				$this->endpoint = $endpoint;
			}
		};
		$client->env = nashenski_get_option( $courier, 'env' );
		$client->load_base_endpoint();
		return $client->endpoint;
	}

	// Courier environments: nothing saved shows the test system and uses it.
	$demo = array(
		'econt'  => 'demo',
		'boxnow' => 'demo',
		'pigeon' => 'sandbox', // Pigeon calls its test system Sandbox.
	);
	foreach ( array(
		'econt'  => 'Econt_Tab',
		'boxnow' => 'BoxNow_Tab',
		'pigeon' => 'Pigeon_Tab',
	) as $courier => $class ) {
		$GLOBALS['saved'] = array();
		expect( shown( $courier, tab_fields( $class )[ $courier ], 'env' ) === $demo[ $courier ], "$courier environment shows the test system when nothing is saved" );
		expect( 'demo-url' === endpoint( $courier ), "$courier uses the test system when nothing is saved" );

		$GLOBALS['saved'] = array( "nashenski_settings_{$courier}_env" => 'live' );
		expect( 'live' === shown( $courier, tab_fields( $class )[ $courier ], 'env' ), "$courier environment shows a saved Live" );
		expect( 'live-url' === endpoint( $courier ), "$courier uses a saved Live" );
	}

	// PDF logo size: shown and used default is medium; a saved size wins.
	$GLOBALS['saved'] = array();
	expect( 'medium' === shown( 'pdf', \Nashenski\Admin\Tabs\Nra_Tab::pdf_fields(), 'logo_size' ), 'PDF logo size shows Medium when nothing is saved' );
	expect( 'medium' === \Nashenski\Invoice\PDF\Style::get()['logo_size'], 'PDF logo size uses medium when nothing is saved' );
	$GLOBALS['saved'] = array( 'nashenski_settings_pdf_logo_size' => 'small' );
	expect( 'small' === shown( 'pdf', \Nashenski\Admin\Tabs\Nra_Tab::pdf_fields(), 'logo_size' ), 'PDF logo size shows a saved Small' );
	expect( 'small' === \Nashenski\Invoice\PDF\Style::get()['logo_size'], 'PDF logo size uses a saved small' );

	// BOX NOW price type: unsaved shows the weight bands and their table; a saved type wins.
	$GLOBALS['saved'] = array();
	$fields           = boxnow_price_fields();
	expect( 'from_to_kg' === shown( 'boxnow_price', $fields, 'price_type' ), 'BOX NOW price type shows weight bands when nothing is saved' );
	expect( null !== shown( 'boxnow_price', $fields, 'from_to_kg' ), 'BOX NOW shows the weight table when nothing is saved' );
	$GLOBALS['saved'] = array( 'nashenski_settings_boxnow_price_price_type' => 'from_to_order_total' );
	$fields           = boxnow_price_fields();
	expect( 'from_to_order_total' === shown( 'boxnow_price', $fields, 'price_type' ), 'BOX NOW price type shows a saved order total' );
	expect( null === shown( 'boxnow_price', $fields, 'from_to_kg' ) && null !== shown( 'boxnow_price', $fields, 'from_to_order_total' ), 'BOX NOW shows the table of the saved type only' );

	echo "PASS: {$checks} settings default assertions\n";
}
