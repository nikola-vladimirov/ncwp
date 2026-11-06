<?php
/**
 * Offline test: saved logins and keys never go into the page, and the rules a field declares are checked on the server.
 * Run: php tests/settings-secrets-rules.php
 *
 * @package Nashenski_Commerce
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );

	/**
	 * WC Settings Page.
	 */
	class WC_Settings_Page {
		/**
		 * Construct.
		 */
		public function __construct() {}
	}

	/**
	 * WC Admin Settings.
	 */
	class WC_Admin_Settings {
		/**
		 * Errors added.
		 *
		 * @var array
		 */
		public static $errors = array();

		/**
		 * Add error.
		 *
		 * @param mixed $text Text.
		 */
		public static function add_error( $text ) {
			self::$errors[] = $text;
		}
	}

	/**
	 * Add action.
	 */
	function add_action() {}
	/**
	 * Add filter.
	 */
	function add_filter() {}
	/**
	 * .
	 *
	 * @param mixed $text Text.
	 */
	function __( $text ) {
		return $text;
	}
	/**
	 * Esc html.
	 *
	 * @param mixed $text Text.
	 */
	function esc_html( $text ) {
		return $text;
	}
	/**
	 * Wp kses post.
	 *
	 * @param mixed $text Text.
	 */
	function wp_kses_post( $text ) {
		return $text;
	}
	/**
	 * Sanitize title with dashes.
	 *
	 * @param mixed $text Text.
	 */
	function sanitize_title_with_dashes( $text ) {
		return strtolower( preg_replace( '/[^A-Za-z0-9_-]+/', '-', $text ) );
	}
	/**
	 * Is email.
	 *
	 * @param mixed $text Text.
	 */
	function is_email( $text ) {
		return (bool) preg_match( '/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $text );
	}
	/**
	 * Wc format decimal.
	 *
	 * @param mixed $value Value.
	 */
	function wc_format_decimal( $value ) {
		return trim( (string) $value );
	}
	/**
	 * Wp json encode.
	 *
	 * @param mixed $data Data.
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
	/**
	 * Get option.
	 *
	 * @param mixed $name Name.
	 * @param mixed $default Default.
	 */
	function get_option( $name, $default = false ) {
		return $GLOBALS['options'][ $name ] ?? $default;
	}

	require __DIR__ . '/../app/Admin/Settings.php';
	require __DIR__ . '/../app/Pricing/Price_Lists.php';
	require __DIR__ . '/../app/Admin/Fields/Base_Field.php';
	require __DIR__ . '/../app/Admin/Fields/Text_Field.php';
	require __DIR__ . '/../app/Admin/Settings_Page.php';

	use Nashenski\Admin\Fields\Text_Field;
	use Nashenski\Admin\Settings_Page;

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

	// 1. A saved secret is shown as the mask, never as itself.
	$GLOBALS['options'] = array(
		'nashenski_settings_econt_password' => 'hunter2',
		'nashenski_settings_econt_user'     => 'shop-user',
		'nashenski_settings_pigeon_api_key' => 'pk_live_123',
		'nashenski_settings_econt_name'     => 'Ivan',
	);
	foreach ( array( array( 'econt', 'password', 'password' ), array( 'econt', 'user', 'text' ), array( 'pigeon', 'api_key', 'text' ) ) as $case ) {
		$setting = Settings_Page::to_wc_setting( $case[0], new Text_Field( $case[1], 'Title', '', '', '', $case[2] ) );
		expect( Settings_Page::SECRET_MASK === ( $setting['value'] ?? null ), "{$case[0]} {$case[1]} is shown as the mask" );
		expect( false === strpos( json_encode( $setting ), 'hunter2' ) && false === strpos( json_encode( $setting ), 'shop-user' ) && false === strpos( json_encode( $setting ), 'pk_live_123' ), "{$case[1]} is not in the setting" );
	}
	$setting = Settings_Page::to_wc_setting( 'econt', new Text_Field( 'name', 'Name' ) );
	expect( ! isset( $setting['value'] ), 'an ordinary text setting is shown as it is' );
	$setting = Settings_Page::to_wc_setting( 'econt', new Text_Field( 'password', 'Password', '', '', '', 'password' ) );
	$GLOBALS['options']['nashenski_settings_econt_password'] = '';
	$setting = Settings_Page::to_wc_setting( 'econt', new Text_Field( 'password', 'Password', '', '', '', 'password' ) );
	expect( ! isset( $setting['value'] ), 'an empty secret is shown empty' );
	$GLOBALS['options']['nashenski_settings_econt_password'] = 'hunter2';

	// 2. Posting the mask back keeps the saved secret; a new value replaces it; empty clears it.
	$option = array(
		'id'   => 'nashenski_settings_econt_password',
		'type' => 'password',
	);
	expect( 'hunter2' === Settings_Page::keep_secret( '********', $option, '********' ), 'the mask keeps the saved secret' );
	expect( 'new' === Settings_Page::keep_secret( 'new', $option, 'new' ), 'a new secret is saved' );
	expect( '' === Settings_Page::keep_secret( '', $option, '' ), 'an empty value clears the secret' );
	expect(
		'********' === Settings_Page::keep_secret(
			'********',
			array(
				'id'   => 'nashenski_settings_econt_name',
				'type' => 'text',
			),
			'********'
		),
		'the mask means nothing on an ordinary setting'
	);

	// 3. Rules: required, email, digits, and a number is never negative.
	$check = function ( $rules, $type, $posted, $before = 'before' ) {
		$GLOBALS['options']['nashenski_settings_x_y'] = $before;
		WC_Admin_Settings::$errors                    = array();
		$value                                        = Settings_Page::sanitize_rules_field(
			$posted,
			array(
				'id'              => 'nashenski_settings_x_y',
				'title'           => 'Title',
				'type'            => $type,
				'nashenski_rules' => $rules,
			),
			$posted
		);
		return array( $value, count( WC_Admin_Settings::$errors ) );
	};
	expect( array( 'before', 1 ) === $check( 'required|email', 'email', 'not-an-email' ), 'a bad e-mail keeps the earlier value and says so' );
	expect( array( 'a@b.bg', 0 ) === $check( 'required|email', 'email', 'a@b.bg' ), 'a good e-mail is saved' );
	expect( array( 'before', 1 ) === $check( 'required', 'text', '   ' ), 'an empty required field keeps the earlier value' );
	expect( array( '', 0 ) === $check( '', 'text', '' ), 'an empty field without rules is saved empty' );
	expect( array( '', 0 ) === $check( 'required', 'text', '', '' ), 'a required field that was never filled in saves empty without a notice' );
	$secret = Settings_Page::sanitize_rules_field(
		'',
		array(
			'id'              => 'nashenski_settings_nepostop_api_key',
			'title'           => 'Key',
			'type'            => 'text',
			'nashenski_rules' => 'required',
		),
		''
	);
	expect( '' === $secret, 'a required login or key can be cleared on purpose' );
	expect( array( 'before', 1 ) === $check( 'required|digits', 'text', 'R28 plain text' ), 'a counter with letters is refused' );
	expect( array( '0000000100', 0 ) === $check( 'required|digits', 'text', '0000000100' ), 'a counter of digits is saved' );
	expect( array( '', 0 ) === $check( 'digits', 'text', '' ), 'an empty optional counter is saved' );
	expect( array( 'before', 1 ) === $check( '', 'number', '-1' ), 'a negative number is refused' );
	expect( array( 'before', 1 ) === $check( '', 'number', 'abc' ), 'a number field refuses letters' );
	expect( array( '12.5', 0 ) === $check( '', 'number', '12.5' ), 'a decimal number is saved' );
	expect( array( '0', 0 ) === $check( '', 'number', '0' ), 'zero is saved' );
	expect( array( '', 0 ) === $check( '', 'number', '' ), 'an empty number is saved empty' );

	// 4. The filters run on every WooCommerce settings page: another plugin's password, key or number field is left alone.
	$GLOBALS['options']['woocommerce_stripe_secret_key'] = 'sk_live_x';
	$foreign = array(
		'id'    => 'woocommerce_stripe_secret_key',
		'title' => 'Secret key',
		'type'  => 'password',
	);
	expect( ! Settings_Page::is_secret( 'woocommerce_stripe_secret_key', 'password' ), 'a secret of another plugin is not ours to mask' );
	expect( '********' === Settings_Page::keep_secret( '********', $foreign, '********' ), 'the mask is not swapped for another plugin\'s saved value' );
	WC_Admin_Settings::$errors = array();
	$number                    = array(
		'id'    => 'woocommerce_price_decimals',
		'title' => 'Number',
		'type'  => 'number',
	);
	expect( '-1' === Settings_Page::sanitize_rules_field( '-1', $number, '-1' ) && ! WC_Admin_Settings::$errors, 'a number field of another plugin is not checked' );
	expect( Settings_Page::is_secret( 'nashenski_settings_econt_password', 'password' ) && Settings_Page::is_secret( 'nashenski_settings_econt_user', 'text' ), 'our own logins and keys are still secrets' );

	// 5. WP-CLI hides the same secrets as the screen.
	require __DIR__ . '/../app/CLI/Settings_Command.php';
	$cli_secret = new ReflectionMethod( 'Nashenski\CLI\Settings_Command', 'is_secret' );
	if ( PHP_VERSION_ID < 80100 ) {
		$cli_secret->setAccessible( true );
	}
	foreach ( array(
		'nashenski_settings_econt_user'     => true,
		'nashenski_settings_pigeon_api_key' => true,
		'nashenski_settings_econt_name'     => false,
	) as $id => $hidden ) {
		expect(
			$hidden === $cli_secret->invoke(
				null,
				array(
					'id'   => $id,
					'type' => 'text',
				)
			),
			'the command line ' . ( $hidden ? 'hides' : 'shows' ) . " $id"
		);
	}
	expect(
		! $cli_secret->invoke(
			null,
			array(
				'id'   => 'nashenski_settings_econt_profile_key',
				'type' => 'select',
			)
		),
		'a choice that only ends in "key" (the profile) is shown'
	);

	// 6. BOX NOW price rows never keep a negative amount.
	$rows = json_decode(
		Settings_Page::sanitize_rows_field(
			'',
			array( 'type' => 'nashenski_rows' ),
			array(
				array(
					'from'  => '0',
					'to'    => '5',
					'price' => '-5',
				),
				array(
					'from'  => '5',
					'to'    => '10',
					'price' => '7.5',
				),
			)
		),
		true
	);
	expect( '' === $rows[0]['price'] && '7.5' === $rows[1]['price'], 'a negative price row is saved empty, a good one is kept' );

	echo 'PASS: ' . $checks . " settings secret and rule assertions\n";
}
