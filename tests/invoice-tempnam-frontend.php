<?php
/**
 * Offline: the combined original-and-copy invoice is made in a storefront request, where
 * wp-admin/includes/file.php (wp_tempnam) is not loaded. php tests/invoice-tempnam-frontend.php
 *
 * @package Nashenski_Commerce
 */

namespace {
	// A fake WordPress root whose wp-admin/includes/file.php is the only place wp_tempnam() exists.
	$root = sys_get_temp_dir() . '/nashenski-tempnam-' . uniqid() . '/';
	mkdir( $root . 'wp-admin/includes', 0777, true );
	file_put_contents( $root . 'wp-admin/includes/file.php', '<?php function wp_tempnam( $name ) { $GLOBALS["temp_files"][] = $f = tempnam( sys_get_temp_dir(), "nsk" ); return $f; }' );
	define( 'ABSPATH', $root );
	define( 'FS_CHMOD_FILE', 0644 );
	error_reporting( E_ALL & ~E_DEPRECATED ); // dompdf 3.0 deprecations on PHP 8.4+.
	require __DIR__ . '/../vendor/autoload.php';

	$temp_files = array();
	function nashenski_get_option( $group, $key ) {
		return '';
	}
	function wp_generate_password( $length ) {
		return 'abcdefgh';
	}
	function wp_delete_file( $file ) {
		@unlink( $file ); // phpcs:ignore
	}
	/** Filesystem fixture. */
	class FakeFilesystem {
		public function put_contents( $file, $data ) {
			return false !== file_put_contents( $file, $data );
		}
		public function delete( $file ) {
			return @unlink( $file ); // phpcs:ignore
		}
	}
	$wp_filesystem = new FakeFilesystem();

	/** PDF fixture: a real one-page PDF per call, so the merge runs for real. */
	class FakePdf {
		public $titles = array();
		public $document;
		public function generate() {
			$this->titles[] = $this->document->title;
			$dompdf         = new \Dompdf\Dompdf();
			$dompdf->loadHtml( '<p>' . $this->document->title . '</p>' );
			$dompdf->render();
			return $dompdf->output();
		}
	}
	/** Document under test, skipping the order-loading constructor and the storage step. */
	class TestDocument extends \Nashenski\Invoice\Document\BaseDocument {
		public $saved;
		public function __construct() {}
		protected function save_pdf( $contents ) {
			$this->saved = $contents;
		}
		public function run() {
			$this->generate_original_and_copy( 'Copy' );
		}
	}

	$checks = 0;
	function expect( $condition, $message ) {
		++$GLOBALS['checks'];
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}

	expect( ! function_exists( 'wp_tempnam' ), 'wp_tempnam is not loaded before the invoice is made, as on the storefront' );
	$document                = new TestDocument();
	$document->title         = 'Original';
	$document->pdf           = new FakePdf();
	$document->pdf->document = $document;
	$document->run();

	expect( function_exists( 'wp_tempnam' ), 'The admin file helpers were loaded for the temp files' );
	expect( array( 'Original', 'Copy' ) === $document->pdf->titles, 'The original and the copy were both generated' );
	expect( 0 === strpos( (string) $document->saved, '%PDF' ), 'The merged PDF was saved' );
	expect( 2 === count( $temp_files ) && ! file_exists( $temp_files[0] ) && ! file_exists( $temp_files[1] ), 'Both temp files were removed' );

	unlink( $root . 'wp-admin/includes/file.php' );
	rmdir( $root . 'wp-admin/includes' );
	rmdir( $root . 'wp-admin' );
	rmdir( $root );
	echo 'PASS: ' . $checks . " storefront invoice temp file assertions\n";
}
