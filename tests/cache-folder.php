<?php // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, Universal.Namespaces, WordPress.Security.EscapeOutput, WordPress.WP.GlobalVariablesOverride -- offline CLI test script: no WordPress, so stubs, unused stub parameters and plain-text output are on purpose.
/**
 * Offline check: the courier cache lives in the uploads folder of the current site (0.21.7, WordPress.org
 * asks for writes there), each courier client gets its own subfolder, and clearing the cache and
 * uninstalling remove only the plugin's cache folders, after the customer data of every site.
 *
 * Run: php tests/cache-folder.php
 *
 * @package Nashenski_Commerce
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'WP_CONTENT_DIR', '/site/wp-content' );
	define( 'WP_UNINSTALL_PLUGIN', 'nashenski-commerce/nashenski-commerce.php' );
	$GLOBALS['basedir'] = '/site/wp-content/uploads';
	$GLOBALS['log']     = array();
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
	/**
	 * Filesystem fixture: the cache folder holds two files; every removal is logged.
	 */
	class WP_Filesystem_Direct {
		/**
		 * Constructor.
		 *
		 * @param mixed $arg Unused.
		 */
		public function __construct( $arg = null ) {}
		/**
		 * The content folder, with a trailing slash as WordPress returns it.
		 *
		 * @return string
		 */
		public function wp_content_dir() {
			return WP_CONTENT_DIR . '/'; }
		/**
		 * Folder listing.
		 *
		 * @param string $path Folder.
		 * @return array
		 */
		public function dirlist( $path ) {
			return array(
				'offices-1.json' => array( 'name' => 'offices-1.json' ),
				'countries.json' => array( 'name' => 'countries.json' ),
			);
		}
		/**
		 * Delete: logged.
		 *
		 * @param string $path File.
		 */
		public function delete( $path ) {
			$GLOBALS['log'][] = 'delete ' . $path;
		}
		/**
		 * Rmdir: logged.
		 *
		 * @param string $path Folder.
		 */
		public function rmdir( $path ) {
			$GLOBALS['log'][] = 'rmdir ' . $path;
		}
	}

	// Two sites: the second has its own uploads folder and table prefix.
	/**
	 * Multisite stub.
	 *
	 * @return bool
	 */
	function is_multisite() {
		return true;
	}
	/**
	 * Sites stub.
	 *
	 * @return array
	 */
	function get_sites() {
		return array( 1, 2 );
	}
	/**
	 * Switches the uploads folder and table prefix.
	 *
	 * @param int $id Site.
	 */
	function switch_to_blog( $id ) {
		$GLOBALS['basedir']       = 1 === $id ? '/site/wp-content/uploads' : '/site/wp-content/uploads/sites/' . $id;
		$GLOBALS['wpdb']->prefix  = 1 === $id ? 'wp_' : 'wp_' . $id . '_';
		$GLOBALS['wpdb']->options = $GLOBALS['wpdb']->prefix . 'options';
	}
	/**
	 * Back to the first site.
	 */
	function restore_current_blog() {
		switch_to_blog( 1 );
	}
	/**
	 * Cron stub.
	 */
	function wp_clear_scheduled_hook() {}
	/**
	 * Option stub.
	 */
	function delete_option() {}
	$GLOBALS['wpdb'] = new class() {
		/**
		 * Table prefix.
		 *
		 * @var string
		 */
		public $prefix = 'wp_';
		/**
		 * Options table.
		 *
		 * @var string
		 */
		public $options = 'wp_options';
		/**
		 * Prepare: the query with its first value.
		 *
		 * @param string $query Query.
		 * @param mixed  ...$args Values.
		 * @return string
		 */
		public function prepare( $query, ...$args ) {
			return strtok( $query, ' ' ) . ' ' . $args[0];
		}
		/**
		 * Like escape stub.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		public function esc_like( $text ) {
			return $text;
		}
		/**
		 * Query: logged.
		 *
		 * @param string $query Query.
		 */
		public function query( $query ) {
			$GLOBALS['log'][] = 'db ' . $query;
		}
	};

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

		/**
		 * Files the weekly clean-up keeps.
		 *
		 * @var array
		 */
		public static $skip_cached_files = array( 'countries.json' );
	}
}

namespace Nashenski {
	$fail = array();
	$ds   = DIRECTORY_SEPARATOR;

	$expected = '/site/wp-content/uploads/nashenski-cache' . $ds;
	if ( File::cache_folder() !== $expected ) {
		$fail[] = 'the cache folder is nashenski-cache in uploads: ' . File::cache_folder();
	}
	if ( false !== $GLOBALS['created'] ) {
		$fail[] = 'reading the path does not make the dated uploads folder';
	}
	$econt = File::cache_folder() . 'econt' . $ds;
	if ( Test\Courier::cache_folder() !== $econt ) {
		$fail[] = 'a courier client caches in its own subfolder: ' . Test\Courier::cache_folder();
	}

	// The weekly clean-up keeps the listed files; the full clean-up removes every file, only in the courier's folder.
	Test\Courier::clear_cache_folder( true );
	if ( array( 'delete ' . $econt . 'offices-1.json' ) !== $GLOBALS['log'] ) {
		$fail[] = 'the weekly clean-up deletes only the courier\'s other files: ' . implode( ', ', $GLOBALS['log'] );
	}
	$GLOBALS['log'] = array();
	Test\Courier::clear_cache_folder();
	if ( array( 'delete ' . $econt . 'offices-1.json', 'delete ' . $econt . 'countries.json' ) !== $GLOBALS['log'] ) {
		$fail[] = 'the full clean-up deletes the courier\'s files only: ' . implode( ', ', $GLOBALS['log'] );
	}

	$GLOBALS['basedir'] = '/site/wp-content/uploads/sites/2';
	if ( 0 !== strpos( Test\Courier::cache_folder(), '/site/wp-content/uploads/sites/2/nashenski-cache' ) ) {
		$fail[] = 'each site of a network has its own cache';
	}

	// Uninstall on a network of two sites: data of both sites first, then only the plugin's cache folders.
	$GLOBALS['basedir'] = '/site/wp-content/uploads';
	$GLOBALS['log']     = array();
	require __DIR__ . '/../uninstall.php';
	$expected = array(
		'db DROP wp_nashenski_carts',
		'db DELETE wp_options',
		'db DROP wp_2_nashenski_carts',
		'db DELETE wp_2_options',
		'rmdir /site/wp-content/uploads/nashenski-cache',
		'rmdir /site/wp-content/uploads/sites/2/nashenski-cache',
		'rmdir /site/wp-content/cache/nashenski',
	);
	if ( $GLOBALS['log'] !== $expected ) {
		$fail[] = 'uninstall removes the data of every site, then only the cache folders: ' . implode( ', ', $GLOBALS['log'] );
	}

	if ( $fail ) {
		fwrite( STDERR, 'FAIL: ' . implode( "\nFAIL: ", $fail ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
		exit( 1 );
	}
	echo "PASS: 7 cache folder checks\n";
}
