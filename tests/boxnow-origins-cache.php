<?php // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, Universal.Namespaces, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions -- offline CLI test script: no WordPress, so stubs, unused stub parameters, filesystem calls and plain-text output are on purpose.
/** Offline BOX NOW origins cache location. Run: php -d memory_limit=64M tests/boxnow-origins-cache.php */
namespace {
	$root = sys_get_temp_dir() . '/nashenski-origins-' . bin2hex( random_bytes( 4 ) );
	mkdir( $root . '/uploads', 0777, true );
	define( 'ABSPATH', $root . '/' );
	define( 'FS_CHMOD_FILE', 0644 );
	define( 'FS_CHMOD_DIR', 0755 );
	const MINUTE_IN_SECONDS = 60;

	$GLOBALS['root'] = $root;
	$GLOBALS['opts'] = array();
	/**
	 * Option stub.
	 *
	 * @param string $k Option name.
	 *
	 * @return mixed
	 */
	function get_option( $k ) {
		return $GLOBALS['opts'][ $k ] ?? false; }
	/**
	 * Option update stub.
	 *
	 * @param string $k Option name.
	 * @param mixed  $v Value.
	 * @param mixed  $a Autoload flag, unused.
	 *
	 * @return void
	 */
	function update_option( $k, $v, $a = null ) {
		$GLOBALS['opts'][ $k ] = $v; }
	/**
	 * Upload directory stub.
	 *
	 * @param mixed $t Time, unused.
	 * @param bool  $c Create flag, unused.
	 *
	 * @return array
	 */
	function wp_upload_dir( $t = null, $c = true ) {
		return array( 'basedir' => $GLOBALS['root'] . '/uploads' ); }
	/**
	 * Trailing slash stub.
	 *
	 * @param string $s Path.
	 *
	 * @return string
	 */
	function trailingslashit( $s ) {
		return rtrim( $s, '/\\' ) . '/'; }
	/**
	 * Directory creation stub.
	 *
	 * @param string $d Directory.
	 *
	 * @return bool
	 */
	function wp_mkdir_p( $d ) {
		return is_dir( $d ) || mkdir( $d, 0777, true ); }
	/**
	 * Password stub.
	 *
	 * @param int  $n Length, unused.
	 * @param bool $s Special characters flag, unused.
	 *
	 * @return string
	 */
	function wp_generate_password( $n = 8, $s = true ) {
		return bin2hex( random_bytes( 4 ) ); }
	/**
	 * JSON stub.
	 *
	 * @param mixed $d Data.
	 *
	 * @return string|false
	 */
	function wp_json_encode( $d ) {
		return json_encode( $d ); }
	/**
	 * File deletion stub.
	 *
	 * @param string $f File.
	 *
	 * @return void
	 */
	function wp_delete_file( $f ) {
		unlink( $f ); }
	/**
	 * Transient read stub: nothing is cached.
	 *
	 * @param string $k Key, unused.
	 *
	 * @return false
	 */
	function get_transient( $k ) {
		return false; }
	/**
	 * Transient write stub: does nothing.
	 *
	 * @param string $k Key.
	 * @param mixed  $v Value.
	 * @param int    $t Lifetime.
	 *
	 * @return void
	 */
	function set_transient( $k, $v, $t ) {}
	/**
	 * Filesystem fixture: File::filesystem() makes WordPress's direct filesystem class; this one writes plain files.
	 */
	class WP_Filesystem_Direct {
		/**
		 * Constructor.
		 *
		 * @param mixed $a Unused.
		 */
		public function __construct( $a = null ) {}
		/**
		 * Writes a file.
		 *
		 * @param string $f File.
		 * @param string $d Data.
		 * @param mixed  $m Mode, unused.
		 *
		 * @return bool
		 */
		public function put_contents( $f, $d, $m = null ) {
			return false !== file_put_contents( $f, $d ); }
		/**
		 * Moves a file.
		 *
		 * @param string $a From.
		 * @param string $b To.
		 * @param bool   $o Overwrite.
		 *
		 * @return bool
		 */
		public function move( $a, $b, $o = false ) {
			return ( $o || ! file_exists( $b ) ) && rename( $a, $b ); }
		/**
		 * Reads a file.
		 *
		 * @param string $f File.
		 *
		 * @return string|false
		 */
		public function get_contents( $f ) {
			return file_get_contents( $f ); }
		/**
		 * Deletes a file.
		 *
		 * @param string $f File.
		 *
		 * @return bool
		 */
		public function delete( $f ) {
			return unlink( $f ); }
	}
}

namespace Nashenski\Container {
	/**
	 * Container client names fixture.
	 */
	class Client {
		const BOXNOW = 'client.boxnow';
	}
}

namespace Nashenski\Test {
	/**
	 * BOX NOW API fixture.
	 */
	class Api {
		/**
		 * One sender origin.
		 *
		 * @return array
		 */
		public function api_call() {
			return array(
				'data' => array(
					array(
						'id'   => '1',
						'name' => 'Sender',
					),
				),
			); }
	}
}

namespace Nashenski {
	require __DIR__ . '/../app/class-file.php';
	require __DIR__ . '/../app/class-document-storage.php';
	require __DIR__ . '/../app/Client/BoxNow/class-origins.php';

	$checks = 0;
	/**
	 * Assertion: prints the failure and stops when the condition fails.
	 *
	 * @param bool   $condition Condition.
	 * @param string $message   Failure message.
	 *
	 * @return void
	 */
	function expect( $condition, $message ) {
		++$GLOBALS['checks'];
		if ( ! $condition ) {
			fwrite( STDERR, 'FAIL: ' . $message . "\n" );
			exit( 1 ); }
	}

	$origins = new Client\BoxNow\Origins( array( Container\Client::BOXNOW => new Test\Api() ) );
	expect( 1 === count( $origins->get_origins() ), 'Origins still load' );

	$dir = $GLOBALS['root'] . '/uploads/' . Document_Storage::subdir();
	expect( 1 === preg_match( '/nashenski-documents-[a-f0-9]{32}$/', $dir ), 'Cache lives in the random private folder' );
	expect( file_exists( $dir . '/boxnow-origins.json' ), 'Cache file is in the private folder' );
	expect( file_exists( $dir . '/.htaccess' ) && file_exists( $dir . '/index.php' ), 'Folder has deny rule and index guard' );
	expect( ! file_exists( File::cache_folder() ), 'Nothing is written to the public cache folder' );
	Client\BoxNow\Origins::clear();
	expect( ! file_exists( $dir . '/boxnow-origins.json' ), 'Clearing the cache removes the origins file' );

	$root = $GLOBALS['root'];
	if ( 0 === strpos( $root, sys_get_temp_dir() ) ) {
		$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() ); }
		rmdir( $root );
	}
	echo 'PASS: ' . $checks . " BOX NOW origins cache assertions\n";
}
