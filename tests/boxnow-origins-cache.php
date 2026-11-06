<?php
/** Offline BOX NOW origins cache location. Run: php -d memory_limit=64M tests/boxnow-origins-cache.php */
namespace {
	$root = sys_get_temp_dir() . '/nashenski-origins-' . bin2hex( random_bytes( 4 ) );
	mkdir( $root . '/uploads', 0777, true );
	mkdir( $root . '/content/cache/nashenski/boxnow', 0777, true );
	define( 'ABSPATH', $root . '/' );
	define( 'WP_CONTENT_DIR', $root . '/content' );
	define( 'FS_CHMOD_FILE', 0644 );
	const MINUTE_IN_SECONDS = 60;

	$GLOBALS['root'] = $root;
	$GLOBALS['opts'] = array();
	function get_option( $k ) {
		return $GLOBALS['opts'][ $k ] ?? false; }
	function update_option( $k, $v, $a = null ) {
		$GLOBALS['opts'][ $k ] = $v; }
	function wp_upload_dir( $t = null, $c = true ) {
		return array( 'basedir' => $GLOBALS['root'] . '/uploads' ); }
	function trailingslashit( $s ) {
		return rtrim( $s, '/\\' ) . '/'; }
	function wp_mkdir_p( $d ) {
		return is_dir( $d ) || mkdir( $d, 0777, true ); }
	function wp_generate_password( $n = 8, $s = true ) {
		return bin2hex( random_bytes( 4 ) ); }
	function wp_json_encode( $d ) {
		return json_encode( $d ); }
	function wp_delete_file( $f ) {
		unlink( $f ); }
	function get_transient( $k ) {
		return false; }
	function set_transient( $k, $v, $t ) {}
	$GLOBALS['wp_filesystem'] = new class() {
		public function put_contents( $f, $d, $m = null ) {
			return false !== file_put_contents( $f, $d ); }
		public function get_contents( $f ) {
			return file_get_contents( $f ); }
		public function delete( $f ) {
			return unlink( $f ); }
	};
}

namespace Nashenski\Container {
	class Client {
		const BOXNOW = 'client.boxnow';
	}
}

namespace Nashenski\Test {
	class Api {
		const CACHE_FOLDER = WP_CONTENT_DIR . '/cache/nashenski/boxnow/';
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
	require __DIR__ . '/../app/File.php';
	require __DIR__ . '/../app/Document_Storage.php';
	require __DIR__ . '/../app/Client/BoxNow/Origins.php';

	$checks = 0;
	function expect( $condition, $message ) {
		++$GLOBALS['checks'];
		if ( ! $condition ) {
			fwrite( STDERR, 'FAIL: ' . $message . "\n" );
			exit( 1 ); }
	}

	$public = WP_CONTENT_DIR . '/cache/nashenski/boxnow/origins.json';
	file_put_contents( $public, '[]' ); // Old public cache from before the fix.

	$origins = new Client\BoxNow\Origins( array( Container\Client::BOXNOW => new Test\Api() ) );
	expect( 1 === count( $origins->get_origins() ), 'Origins still load' );
	expect( ! file_exists( $public ), 'Old public origins.json is removed' );

	$dir = $GLOBALS['root'] . '/uploads/' . Document_Storage::subdir();
	expect( 1 === preg_match( '/nashenski-documents-[a-f0-9]{32}$/', $dir ), 'Cache lives in the random private folder' );
	expect( file_exists( $dir . '/boxnow-origins.json' ), 'Cache file is in the private folder' );
	expect( file_exists( $dir . '/.htaccess' ) && file_exists( $dir . '/index.php' ), 'Folder has deny rule and index guard' );
	expect( ! glob( WP_CONTENT_DIR . '/cache/nashenski/boxnow/*' ), 'Nothing is written to the public cache folder' );
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
