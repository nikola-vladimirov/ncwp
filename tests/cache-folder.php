<?php // phpcs:disable Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, Universal.Namespaces, WordPress.Security.EscapeOutput -- offline CLI test script: no WordPress, so stubs and plain-text output are on purpose.
/**
 * Offline check: the courier cache lives in the uploads folder of the current site (0.21.7, WordPress.org
 * asks for writes there), and each courier client gets its own subfolder of it.
 *
 * Run: php tests/cache-folder.php
 *
 * @package Nashenski_Commerce
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	$GLOBALS['basedir'] = '/site/wp-content/uploads';
	/**
	 * Upload directory stub: the current site's uploads folder.
	 *
	 * @param mixed $time   Unused.
	 * @param bool  $create Whether a missing folder is made.
	 * @return array
	 */
	function wp_upload_dir( $time = null, $create = true ) {
		$GLOBALS['created'] = $create;
		return array( 'basedir' => $GLOBALS['basedir'] );
	}
	/**
	 * Trailing slash stub.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function trailingslashit( $path ) {
		return rtrim( $path, '/\\' ) . '/';
	}

	require __DIR__ . '/../app/class-file.php';
	require __DIR__ . '/../app/Client/class-courier-client.php';
}

namespace Nashenski\Test {
	/**
	 * A courier client fixture with the shared client code.
	 */
	class Courier {
		use \Nashenski\Client\Courier_Client;

		const CACHE_NAME = 'econt';
	}
}

namespace Nashenski {
	$fail = array();
	$ds   = DIRECTORY_SEPARATOR;

	if ( '/site/wp-content/uploads/nashenski-cache' . $ds !== File::cache_folder() ) {
		$fail[] = 'the cache folder is nashenski-cache in uploads: ' . File::cache_folder();
	}
	if ( false !== $GLOBALS['created'] ) {
		$fail[] = 'reading the path does not make the dated uploads folder';
	}
	if ( File::cache_folder() . 'econt' . $ds !== Test\Courier::cache_folder() ) {
		$fail[] = 'a courier client caches in its own subfolder: ' . Test\Courier::cache_folder();
	}

	$GLOBALS['basedir'] = '/site/wp-content/uploads/sites/2';
	if ( 0 !== strpos( Test\Courier::cache_folder(), '/site/wp-content/uploads/sites/2/nashenski-cache' ) ) {
		$fail[] = 'each site of a network has its own cache';
	}

	if ( $fail ) {
		fwrite( STDERR, 'FAIL: ' . implode( "\nFAIL: ", $fail ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
		exit( 1 );
	}
	echo "PASS: 4 cache folder checks\n";
}
