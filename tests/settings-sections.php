<?php // phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO -- offline test: WordPress stubs and the test share one file.
/**
 * Offline test: the Documents section leaves the settings menu when documents are off, and the
 * after-save hook is spelled the same by its caller and its listeners.
 * Run: php tests/settings-sections.php
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski {
	/**
	 * Plugin fixture.
	 */
	class Plugin {
		const COURIERS = array( 'econt', 'speedy', 'pigeon', 'boxnow' );
	}
}

namespace Nashenski\Front_End\Checkout {
	/**
	 * Company fields fixture.
	 */
	class Company_Fields {
		const GROUP = 'company';
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );

	/**
	 * WooCommerce settings page fixture.
	 */
	class WC_Settings_Page {}

	/**
	 * .
	 *
	 * @param mixed $text Text.
	 */
	function __( $text ) {
		return $text;
	}
	/**
	 * Sanitize key.
	 *
	 * @param mixed $key Key.
	 */
	function sanitize_key( $key ) {
		return $key;
	}

	/**
	 * Do action: counts the calls per hook.
	 *
	 * @param string $name Hook.
	 */
	function do_action( $name ) {
		$GLOBALS['fired'][ $name ] = ( $GLOBALS['fired'][ $name ] ?? 0 ) + 1;
	}
	/**
	 * Do action deprecated: an add-on listens on the old name, so it fires.
	 *
	 * @param string $name Old hook.
	 */
	function do_action_deprecated( $name ) {
		$GLOBALS['fired'][ $name ] = ( $GLOBALS['fired'][ $name ] ?? 0 ) + 1;
	}
	/**
	 * Admin url.
	 *
	 * @param string $path Path.
	 */
	function admin_url( $path ) {
		return 'https://example.test/wp-admin/' . $path;
	}
	/**
	 * Esc url.
	 *
	 * @param string $url Url.
	 */
	function esc_url( $url ) {
		return $url;
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
	 * Nashenski get option: documents on or off.
	 */
	function nashenski_get_option() {
		return $GLOBALS['documents_on'] ? 'yes' : 'no';
	}

	require_once __DIR__ . '/fixtures/hooks.php';
	require __DIR__ . '/../app/Admin/class-settings.php';
	require __DIR__ . '/../app/Admin/class-settings-page.php';
	require __DIR__ . '/../app/Admin/class-help-page.php';
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
			fwrite( STDERR, "FAIL: $message\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- offline test.
			exit( 1 );
		}
	}

	$settings_page = ( new ReflectionClass( 'Nashenski\Admin\Settings_Page' ) )->newInstanceWithoutConstructor();
	$tabs_prop     = new ReflectionProperty( $settings_page, 'tabs' );
	$layout        = new ReflectionMethod( $settings_page, 'layout' );
	if ( PHP_VERSION_ID < 80100 ) {
		$tabs_prop->setAccessible( true );
		$layout->setAccessible( true );
	}

	// Documents on: the tab exists. Documents off: only the always-present checkout fields tab.
	$tabs_prop->setValue(
		$settings_page,
		array(
			'settings'  => 1,
			'documents' => 1,
			'fields'    => 1,
			'shipping'  => 1,
		)
	);
	expect( isset( $layout->invoke( $settings_page )['documents'] ), 'the Documents section is in the menu when documents are on' );
	$tabs_prop->setValue(
		$settings_page,
		array(
			'settings' => 1,
			'fields'   => 1,
			'shipping' => 1,
		)
	);
	expect( ! isset( $layout->invoke( $settings_page )['documents'] ), 'the Documents section is not in the menu when documents are off' );
	expect( isset( $layout->invoke( $settings_page )['checkout'] ), 'the Checkout section stays when documents are off' );
	$excluded = function ( $page_layout ) {
		foreach ( $page_layout['checkout']['parts'] as $part ) {
			if ( 'fields' === $part[0] ) {
				return $part[3] ?? array();
			}
		}
		return null;
	};
	expect( array() === $excluded( $layout->invoke( $settings_page ) ), 'with documents off the company invoice switch stays in Checkout' );
	$tabs_prop->setValue(
		$settings_page,
		array(
			'settings'  => 1,
			'documents' => 1,
			'fields'    => 1,
			'shipping'  => 1,
		)
	);
	expect( array( 'company' ) === $excluded( $layout->invoke( $settings_page ) ), 'with documents on the company group moves to Documents' );

	// The Help page links to the Documents section only while it is in the menu.
	$help_link = new ReflectionMethod( 'Nashenski\Admin\Help_Page', 'link' );
	if ( PHP_VERSION_ID < 80100 ) {
		$help_link->setAccessible( true );
	}
	$GLOBALS['documents_on'] = false;
	expect( 'Documents' === $help_link->invoke( null, 'documents', 'Documents' ), 'with documents off the Help page shows the plain label' );
	expect( false !== strpos( $help_link->invoke( null, 'checkout', 'Checkout' ), 'section=checkout' ), 'other Help links are unchanged' );
	$GLOBALS['documents_on'] = true;
	expect( false !== strpos( $help_link->invoke( null, 'documents', 'Documents' ), 'section=documents' ), 'with documents on the Help page links to Documents' );

	// After a save both hook names fire, once each.
	$GLOBALS['fired'] = array();
	\Nashenski\Admin\Settings_Page::run_after_save_fields( 'Nashenski\Admin\Tabs\Econt_Tab' );
	expect( 1 === ( $GLOBALS['fired']['nashenski_admin_settings_after_save_fields'] ?? 0 ), 'the hook fires once, under one name for every tab' );
	expect( 1 === ( $GLOBALS['fired']['nashenski_admin_settings_after_save_fields_Nashenski\Admin\Tabs\Econt_Tab'] ?? 0 ), 'the older name with the tab class in it fires once for add-ons on it' );
	expect( 1 === ( $GLOBALS['fired']['nashenski/admin/settings/after_save_fields/Nashenski\Admin\Tabs\Econt_Tab'] ?? 0 ), 'the old slash hook fires once for add-ons on it' );
	expect( 1 === ( $GLOBALS['fired']['nashenski/admin/settings/afte_save_fields/Nashenski\Admin\Tabs\Econt_Tab'] ?? 0 ), 'the old misspelt hook fires once for add-ons on it' );
	// The three callers (the page, and two WP-CLI commands) go through the helper; the helper holds the only do_action of the name.
	foreach ( array(
		'app/Admin/class-settings-page.php'  => 'self::',
		'app/CLI/class-courier-command.php'  => '\Nashenski\Admin\Settings_Page::',
		'app/CLI/class-settings-command.php' => 'Settings_Page::',
	) as $caller => $prefix ) {
		expect( false !== strpos( file_get_contents( __DIR__ . '/../' . $caller ), $prefix . 'run_after_save_fields(' ), "$caller runs both hook names through run_after_save_fields" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- offline test.
	}
	// One spelling of the hook everywhere.
	$wrong = 0;
	$right = 0;
	$raw   = 0;
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../app', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( 'php' !== $file->getExtension() ) {
			continue;
		}
		$text = file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- offline test.
		// Raw calls of the hook anywhere in app/: only the helper may have one.
		$raw += preg_match_all( '/\bHooks::action\(\s*\'nashenski_admin_settings_after_save_fields\'/', $text );
		// The one do_action_deprecated line keeps the old spelling on purpose.
		$text   = preg_replace( '/^.*do_action_deprecated.*$/m', '', $text );
		$wrong += substr_count( $text, 'afte_save_fields' );
		$right += substr_count( $text, "settings_after_save_fields'" );
	}
	expect( 1 === $raw, 'exactly one Hooks::action of the after-save hook in app/ (the helper), so a caller cannot skip the old name' );
	expect( 0 === $wrong, 'no file spells the hook afte_save_fields' );
	expect( $right >= 4, 'the caller and the listeners use after_save_fields' );

	echo 'PASS: ' . (int) $checks . " settings section assertions\n";
}
