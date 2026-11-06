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

	echo 'PASS: ' . $checks . " settings secret and rule assertions\n";
}
