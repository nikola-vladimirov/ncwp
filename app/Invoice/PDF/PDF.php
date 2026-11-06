<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloader maps the class name to the file name.
/**
 * Order document PDF.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Invoice\PDF;

use Dompdf\Dompdf;
use Dompdf\Options;
use Dompdf\Adapter\CPDF;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a document template to PDF with dompdf.
 */
class PDF {
	/**
	 * Template file.
	 *
	 * @var string
	 */
	private $template;

	/**
	 * The document to render.
	 *
	 * @var object
	 */
	public $document;

	/**
	 * Sets up the PDF.
	 *
	 * @param object $document The document to render.
	 */
	public function __construct( $document ) {
		$this->document   = $document;
		$default_template = apply_filters( 'nashenski/invoice/pdf/default-template', $this->get_default_template(), $this ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it would break existing callbacks.

		$this->set_template( $default_template );
	}

	/**
	 * Path of the built-in template.
	 *
	 * @return string
	 */
	public function get_default_template() {
		return __DIR__ . DIRECTORY_SEPARATOR . 'view.php';
	}

	/**
	 * Sets the template file.
	 *
	 * @param string $file Template path.
	 */
	public function set_template( $file ) {
		$this->template = $file;
	}

	/**
	 * Template file.
	 *
	 * @return string
	 */
	public function get_template() {
		return $this->template;
	}

	/**
	 * Renders the PDF.
	 *
	 * @return string PDF data.
	 */
	public function generate() {
		$options = new Options();
		$options->set( 'defaultFont', 'dejavu sans' );
		$options->set( 'isHtml5ParserEnabled', true );
		// Documents print customer-entered text: never run PHP from them and never fetch remote files.
		// Logos and QR codes are embedded as data URIs; page numbers are added after rendering.
		$options->set( 'isPhpEnabled', false );
		$options->set( 'isRemoteEnabled', false );

		$dompdf = new Dompdf( $options );
		$dompdf->setPaper( 'letter' === Style::get()['paper'] ? 'letter' : 'A4' );

		do_action( 'nashenski/invoice/pdf/dompdf', $dompdf, $options ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it would break existing callbacks.

		ob_start();
		$this->render_template();
		$html = ob_get_clean();
		// Pass the encoding: for large HTML on PHP 8.3+ dompdf's guess is ASCII and Cyrillic prints as "?".
		$dompdf->loadHtml( $html, 'UTF-8' );

		$dompdf->render();
		self::inject_page_count( $dompdf );

		return $dompdf->output();
	}

	/**
	 * Prints the template HTML.
	 */
	private function render_template() {
		if ( ! is_readable( $this->get_template() ) ) {
			return;
		}

		include $this->get_template();
	}

	/**
	 * Replaces the %PC% placeholder with the page count.
	 *
	 * @param Dompdf $dompdf Rendered dompdf.
	 */
	public static function inject_page_count( $dompdf ) {
		$canvas = $dompdf->getCanvas();
		assert( $canvas instanceof CPDF );
		$search  = self::insert_null_byte_before_each_character( '%PC%' );
		$replace = self::insert_null_byte_before_each_character( (string) $canvas->get_page_count() );

		foreach ( $canvas->get_cpdf()->objects as &$object ) {
			if ( 'contents' === $object['t'] ) {
				$object['c'] = str_replace( $search, $replace, $object['c'] );
			}
		}
	}

	/**
	 * Puts a null byte before each character (UTF-16 text as dompdf writes it).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function insert_null_byte_before_each_character( $text ) {
		return "\u{0000}" . substr( chunk_split( $text, 1, "\u{0000}" ), 0, -1 );
	}
}
