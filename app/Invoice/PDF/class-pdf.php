<?php
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
		$default_template = \Nashenski\Hooks::filter( 'nashenski_invoice_pdf_default_template', 'nashenski/invoice/pdf/default-template', $this->get_default_template(), $this );

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
	 * Folders Dompdf may open local files in, besides its own.
	 *
	 * @var string[]
	 */
	private static $folders = array();

	/**
	 * Lets the PDF open local files (the logo) in a folder.
	 *
	 * @param string $folder Folder path.
	 */
	public static function allow_folder( $folder ) {
		self::$folders[ $folder ] = $folder;
	}

	/**
	 * Nothing to do, kept for add-ons that call it. The font list of the bundled Dompdf
	 * (installed-fonts.dist.json, set by bin/vendor-licenses.php) already prints the built-in
	 * families (Helvetica, Times, Courier and the generic names) in the DejaVu fonts, for every
	 * Dompdf made from it: their own metric files are not in the plugin, and they have no Cyrillic.
	 * An add-on passes its Dompdf; it is not needed.
	 */
	public static function use_dejavu() {
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
		// The logo is read from its file in uploads and QR codes are embedded as data URIs; page numbers are added after rendering.
		$options->set( 'isPhpEnabled', false );
		$options->set( 'isRemoteEnabled', false );
		// Font metrics cache and fonts a custom template installs (@font-face, with installed-fonts.json) go to uploads, never the plugin folder. When that folder cannot be made, Dompdf keeps its own; the bundled DejaVu loads from the library folder either way.
		$font_cache = \Nashenski\File::cache_folder( 'fonts' );

		if ( wp_mkdir_p( $font_cache ) ) {
			$options->set( 'fontCache', $font_cache );
			$options->set( 'fontDir', $font_cache );
		}

		$dompdf = new Dompdf( $options );
		// A file named without a folder (the logo copy) is looked for in the plugin's logo folder in uploads.
		$dompdf->setBasePath( \Nashenski\File::cache_folder( 'logo' ) );
		$dompdf->setPaper( 'letter' === Style::get()['paper'] ? 'letter' : 'A4' );

		\Nashenski\Hooks::action( 'nashenski_invoice_pdf_dompdf', 'nashenski/invoice/pdf/dompdf', $dompdf, $options );

		ob_start();
		$this->render_template();
		$html = ob_get_clean();
		// Dompdf opens local files only inside its own folder and the folders the template asked for (the logo's).
		$options->setChroot( array_merge( $options->getChroot(), array_values( self::$folders ) ) );
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
	 * Joins PDF files into one, in the order given. Each page keeps its size and orientation.
	 *
	 * @param string[] $files PDF file paths.
	 * @return string PDF data.
	 */
	public static function merge( $files ) {
		$pdf = new \setasign\Fpdi\Fpdi();

		foreach ( $files as $file ) {
			$pages = $pdf->setSourceFile( $file );

			for ( $page = 1; $page <= $pages; $page++ ) {
				$template = $pdf->importPage( $page );
				$size     = $pdf->getTemplateSize( $template );

				$pdf->AddPage( $size['orientation'], array( $size['width'], $size['height'] ) );
				$pdf->useTemplate( $template );
			}
		}

		return $pdf->Output( 'S' );
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
