<?php
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

	require __DIR__ . '/../app/Admin/Settings.php';
	require __DIR__ . '/../app/Admin/Settings_Page.php';

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

	$page   = ( new ReflectionClass( 'Nashenski\Admin\Settings_Page' ) )->newInstanceWithoutConstructor();
	$tabs   = new ReflectionProperty( $page, 'tabs' );
	$layout = new ReflectionMethod( $page, 'layout' );
	if ( PHP_VERSION_ID < 80100 ) {
		$tabs->setAccessible( true );
		$layout->setAccessible( true );
	}

	// Documents on: the tab exists. Documents off: only the always-present checkout fields tab.
	$tabs->setValue(
		$page,
		array(
			'settings'  => 1,
			'documents' => 1,
			'fields'    => 1,
			'shipping'  => 1,
		)
	);
	expect( isset( $layout->invoke( $page )['documents'] ), 'the Documents section is in the menu when documents are on' );
	$tabs->setValue(
		$page,
		array(
			'settings' => 1,
			'fields'   => 1,
			'shipping' => 1,
		)
	);
	expect( ! isset( $layout->invoke( $page )['documents'] ), 'the Documents section is not in the menu when documents are off' );
	expect( isset( $layout->invoke( $page )['checkout'] ), 'the Checkout section stays when documents are off' );

	// One spelling of the hook everywhere.
	$wrong = 0;
	$right = 0;
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../app', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( 'php' !== $file->getExtension() ) {
			continue;
		}
		$text   = file_get_contents( $file->getPathname() );
		$wrong += substr_count( $text, 'afte_save_fields' );
		$right += substr_count( $text, 'settings/after_save_fields/' );
	}
	expect( 0 === $wrong, 'no file spells the hook afte_save_fields' );
	expect( $right >= 6, 'the caller and the listeners use after_save_fields' );

	echo 'PASS: ' . $checks . " settings section assertions\n";
}
