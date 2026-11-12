<?php
/**
 * Offline: the combined original-and-copy invoice is made in a storefront request, where
 * wp-admin/includes/file.php (wp_tempnam) is not loaded. php tests/invoice-tempnam-frontend.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Universal.Files.SeparateFunctionsFromOO, Generic.Files.OneObjectStructurePerFile, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions, WordPress.WP.GlobalVariablesOverride, WordPress.PHP.DevelopmentFunctions, WordPress.PHP.IniSet, WordPress.PHP.DiscouragedPHPFunctions, Generic.CodeAnalysis.UnusedFunctionParameter -- offline test script: it stubs WordPress in one file, keeps WordPress's own names and signatures, and prints to a terminal.

namespace {
	// A fake WordPress root whose wp-admin/includes/file.php is the only place wp_tempnam() exists.
	$root = sys_get_temp_dir() . '/nashenski-tempnam-' . uniqid() . '/';
	mkdir( $root . 'wp-admin/includes', 0777, true );
	file_put_contents( $root . 'wp-admin/includes/file.php', '<?php function wp_tempnam( $name ) { $GLOBALS["temp_files"][] = $f = tempnam( sys_get_temp_dir(), "nsk" ); return $f; }' );
	define( 'ABSPATH', $root );
	error_reporting( E_ALL & ~E_DEPRECATED ); // dompdf 3.0 deprecations on PHP 8.4+.
	require __DIR__ . '/../vendor/autoload.php';
	require_once __DIR__ . '/../app/autoload.php';

	$temp_files = array();
	/**
	 * Option stub.
	 *
	 * @param string $group Group.
	 * @param string $key   Key.
	 * @return string
	 */
	function nashenski_get_option( $group, $key ) {
		return '';
	}
	/**
	 * Password stub.
	 *
	 * @param int $length Length.
	 * @return string
	 */
	function wp_generate_password( $length ) {
		return 'abcdefgh';
	}
	/**
	 * Delete stub.
	 *
	 * @param string $file Path.
	 */
	function wp_delete_file( $file ) {
		@unlink( $file ); // phpcs:ignore
	}
	/** Filesystem fixture: File::filesystem() makes WordPress's direct filesystem class; this one writes plain files. */
	class WP_Filesystem_Direct {
		/**
		 * Constructor.
		 *
		 * @param mixed $arg Unused.
		 */
		public function __construct( $arg = null ) {}
		/**
		 * Writes a file.
		 *
		 * @param string $file Path.
		 * @param string $data Contents.
		 * @return bool
		 */
		public function put_contents( $file, $data ) {
			return false !== file_put_contents( $file, $data );
		}
		/**
		 * Moves a file.
		 *
		 * @param string $from From.
		 * @param string $to   To.
		 * @param bool   $over Overwrite.
		 * @return bool
		 */
		public function move( $from, $to, $over = false ) {
			return ( $over || ! file_exists( $to ) ) && rename( $from, $to );
		}
		/**
		 * Deletes a file.
		 *
		 * @param string $file Path.
		 * @return bool
		 */
		public function delete( $file ) {
			return @unlink( $file ); // phpcs:ignore
		}
	}
	$wp_filesystem = new WP_Filesystem_Direct();

	/** PDF fixture: a real one-page PDF per call, so the merge runs for real. */
	class FakePdf {
		/**
		 * Titles generated so far.
		 *
		 * @var string[]
		 */
		public $titles = array();
		/**
		 * Document being made.
		 *
		 * @var object
		 */
		public $document;
		/**
		 * Makes a PDF of the document title.
		 *
		 * @return string
		 */
		public function generate() {
			$this->titles[] = $this->document->title;
			$dompdf         = new \Dompdf\Dompdf( array( 'fontCache' => sys_get_temp_dir() ) ); // Font metrics cache out of vendor/, as the plugin does.
			$dompdf->loadHtml( '<p>' . $this->document->title . '</p>' );
			$dompdf->render();
			return $dompdf->output();
		}
	}
	/** Document under test, skipping the order-loading constructor and the storage step. */
	class TestDocument extends \Nashenski\Invoice\Document\BaseDocument {
		/**
		 * Saved PDF contents.
		 *
		 * @var string
		 */
		public $saved;
		/**
		 * Skips the order-loading constructor.
		 */
		public function __construct() {}
		/**
		 * Keeps the PDF instead of storing it.
		 *
		 * @param string $contents PDF contents.
		 */
		protected function save_pdf( $contents ) {
			$this->saved = $contents;
		}
		/**
		 * Runs the combined original and copy.
		 */
		public function run() {
			$this->generate_original_and_copy( 'Copy' );
		}
	}

	$checks = 0;
	/**
	 * Throws when a condition is false.
	 *
	 * @param mixed  $condition Condition.
	 * @param string $message   Message.
	 * @throws RuntimeException When the condition is false.
	 */
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
