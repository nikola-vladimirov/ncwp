<?php
/**
 * Offline: a logo kept outside the server (a stream path such as s3://, as media offload plugins
 * return) is copied once into the plugin's cache folder in uploads, and the PDF prints that local
 * copy. Run: php tests/pdf-logo-stream.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Universal.Files.SeparateFunctionsFromOO.Mixed, Generic.Files.OneObjectStructurePerFile.MultipleFound, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.ValidFunctionName, Generic.CodeAnalysis.UnusedFunctionParameter, Squiz.Commenting.FunctionComment.Missing -- an offline test script: it stubs plugin classes, WordPress functions and a stream wrapper in one file.

namespace Nashenski\Invoice\PDF {
	/**
	 * Stub of the PDF appearance settings.
	 */
	class Style {
		/**
		 * The settings: A4 paper.
		 *
		 * @return array
		 */
		public static function get() {
			return array( 'paper' => 'A4' ); }

		/**
		 * The logo box, in pixels.
		 *
		 * @return int[]
		 */
		public static function logo_box() {
			return array( 120, 60 ); }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );

	$GLOBALS['uploads'] = sys_get_temp_dir() . '/nashenski-logo-' . getmypid();
	$GLOBALS['opened']  = 0;

	/**
	 * A remote media store: nashlogo://bucket/logo.png answers with the plugin's own logo image.
	 */
	class Nashenski_Logo_Stream {
		/**
		 * Stream context, set by PHP.
		 *
		 * @var resource|null
		 */
		public $context;

		/**
		 * The local file behind the stream.
		 *
		 * @var resource
		 */
		private $handle;

		/**
		 * Open.
		 *
		 * @return bool
		 */
		public function stream_open() {
			++$GLOBALS['opened'];
			$this->handle = fopen( __DIR__ . '/../assets/images/logo.png', 'rb' );

			return (bool) $this->handle; }

		/**
		 * Read.
		 *
		 * @param int $count Bytes.
		 * @return string
		 */
		public function stream_read( $count ) {
			return (string) fread( $this->handle, $count ); }

		/**
		 * End of file.
		 *
		 * @return bool
		 */
		public function stream_eof() {
			return feof( $this->handle ); }

		/**
		 * Stat of the open file.
		 *
		 * @return array
		 */
		public function stream_stat() {
			return fstat( $this->handle ); }

		/**
		 * Stat of a path: the logo exists, nothing else does.
		 *
		 * @param string $path Path.
		 * @return array|false
		 */
		public function url_stat( $path ) {
			return 'nashlogo://bucket/2026/logo.png' === $path ? stat( __DIR__ . '/../assets/images/logo.png' ) : false; }
	}
	stream_wrapper_register( 'nashlogo', 'Nashenski_Logo_Stream' );

	/**
	 * WordPress's direct filesystem, as far as File uses it.
	 */
	class WP_Filesystem_Direct {
		/**
		 * Read a file.
		 *
		 * @param string $file Path.
		 * @return string|false
		 */
		public function get_contents( $file ) {
			return file_get_contents( $file ); }

		/**
		 * Write a file.
		 *
		 * @param string $file Path.
		 * @param string $data Data.
		 * @return bool
		 */
		public function put_contents( $file, $data ) {
			return false !== file_put_contents( $file, $data ); }

		/**
		 * Delete a file.
		 *
		 * @param string $file Path.
		 * @return bool
		 */
		public function delete( $file ) {
			return unlink( $file ); }

		/**
		 * Move a file over another, as WordPress does: the target is deleted first.
		 *
		 * @param string $from      Path.
		 * @param string $to        New path.
		 * @param bool   $overwrite Replace an existing file.
		 * @return bool
		 */
		public function move( $from, $to, $overwrite ) {
			if ( file_exists( $to ) && ( ! $overwrite || ! unlink( $to ) ) ) {
				return false;
			}

			return rename( $from, $to ); }
	}

	function apply_filters( $hook, $value ) {
		return $value; }
	function do_action() {}
	function wp_upload_dir() {
		return array( 'basedir' => $GLOBALS['uploads'] ); }
	function trailingslashit( $path ) {
		return rtrim( $path, '/\\' ) . '/'; }
	function wp_mkdir_p( $path ) {
		return is_dir( $path ) || mkdir( $path, 0777, true ); }
	function wp_generate_password() {
		return 'abcdefgh'; }
	function nashenski_get_option( $group, $name ) {
		return 'logo' === $name ? 5 : ''; }
	function absint( $value ) {
		return abs( (int) $value ); }
	function get_attached_file( $id ) {
		return 5 === $id ? $GLOBALS['logo'] : false; }
	function wp_is_stream( $path ) {
		return false !== strpos( $path, '://' ); }
	function wp_basename( $path ) {
		return basename( $path ); }
	function sanitize_file_name( $name ) {
		return preg_replace( '/[^A-Za-z0-9._-]/', '', $name ); }
	function wp_normalize_path( $path ) {
		return str_replace( '\\', '/', $path ); }
	function wp_check_filetype( $file ) {
		return array( 'type' => 'image/png' ); }
	function wp_getimagesize( $file ) {
		return @getimagesize( $file ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- as WordPress does it.
	function get_post_field( $field, $id ) {
		return $GLOBALS['modified']; }

	function check( $ok, $what ) {
		if ( ! $ok ) {
			fwrite( STDERR, "FAIL: $what\n" );
			exit( 1 );
		} echo "ok - $what\n"; }

	require_once __DIR__ . '/fixtures/hooks.php';
	require __DIR__ . '/../vendor/autoload.php';
	require_once __DIR__ . '/../app/autoload.php';

	use Nashenski\Invoice\Document\BaseDocument;
	use Nashenski\Invoice\PDF\PDF;

	// A logo on a stream: copied into uploads, once.
	$GLOBALS['logo']     = 'nashlogo://bucket/2026/logo.png';
	$GLOBALS['modified'] = '2026-11-01 10:00:00';
	$first               = BaseDocument::get_logo_img();
	$folder              = str_replace( '\\', '/', Nashenski\File::cache_folder( 'logo' ) );

	check( $first && 0 === strpos( $first['src'], $folder ) && is_file( $first['src'] ), 'the remote logo is copied into the logo cache folder in uploads' );
	check( file_get_contents( $first['src'] ) === file_get_contents( __DIR__ . '/../assets/images/logo.png' ), 'the copy has the bytes of the logo' );
	check( $first['width'] > 0 && $first['width'] <= 120 && $first['height'] > 0 && $first['height'] <= 60, 'the logo is measured from the copy and fits the logo box' );

	$before = $GLOBALS['opened'];
	$again  = BaseDocument::get_logo_img();
	check( $again === $first && $GLOBALS['opened'] === $before, 'the next document uses the copy and does not fetch the logo again' );

	// The PDF prints the copy: Dompdf may open that one folder.
	$template = tempnam( sys_get_temp_dir(), 'nash' );
	file_put_contents( $template, '<html><body><img src="' . $first['src'] . '" width="' . $first['width'] . '" height="' . $first['height'] . '" alt=""><p>Фактура</p></body></html>' );
	$pdf = new PDF( null );
	$pdf->set_template( $template );
	$out = $pdf->generate();
	unlink( $template );
	check( '%PDF' === substr( $out, 0, 4 ) && false !== strpos( $out, '/Subtype /Image' ), 'the PDF has the logo in it' );

	// A copy that is not an image (a download cut short) is fetched again.
	file_put_contents( $first['src'], 'half a file' );
	$before = $GLOBALS['opened'];
	$fixed  = BaseDocument::get_logo_img();
	check( $fixed === $first && $GLOBALS['opened'] === $before + 1 && false !== getimagesize( $fixed['src'] ), 'a damaged copy is fetched again' );

	// A logo replaced under the same path (the attachment changed) gets a new copy.
	$GLOBALS['modified'] = '2026-11-12 09:30:00';
	$before              = $GLOBALS['opened'];
	$newer               = BaseDocument::get_logo_img();
	check( $newer && $newer['src'] !== $first['src'] && is_file( $newer['src'] ) && $GLOBALS['opened'] === $before + 1, 'a changed attachment gets a new copy' );

	// A remote logo that cannot be read: no logo, and nothing is left in the folder for it.
	$GLOBALS['logo'] = 'nashlogo://bucket/2026/missing.png';
	check( array() === BaseDocument::get_logo_img() && 2 === count( glob( $folder . '*' ) ), 'a remote logo that cannot be read gives no logo' );

	// A local logo is used where it is.
	$GLOBALS['logo'] = __DIR__ . '/../assets/images/logo.png';
	$local           = BaseDocument::get_logo_img();
	check( $local && false === strpos( $local['src'], $folder ) && 2 === count( glob( $folder . '*' ) ), 'a local logo is not copied' );

	// Dompdf keeps its font files open until it is freed (Windows cannot remove the folders before).
	unset( $pdf );
	gc_collect_cycles();

	foreach ( array( 'logo', 'fonts' ) as $sub ) {
		array_map( 'unlink', glob( Nashenski\File::cache_folder( $sub ) . '*' ) );
		rmdir( Nashenski\File::cache_folder( $sub ) );
	}

	rmdir( Nashenski\File::cache_folder() );
	rmdir( $GLOBALS['uploads'] );
	echo "PASS\n";
}
