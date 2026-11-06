<?php
namespace Nashenski\Invoice\PDF;

use Dompdf\Dompdf;
use Dompdf\Options;
use Dompdf\Adapter\CPDF;

defined( 'ABSPATH' ) || exit;

class PDF {
	private $template;
	public $document;

	function __construct( $document ) {
		$this->document   = $document;
		$default_template = apply_filters( 'nashenski/invoice/pdf/default-template', $this->get_default_template(), $this );

		$this->set_template( $default_template );
	}

	public function get_default_template() {
		return __DIR__ . DIRECTORY_SEPARATOR . 'view.php';
	}

	public function set_template( $file ) {
		$this->template = $file;
	}

	public function get_template() {
		return $this->template;
	}

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

		do_action( 'nashenski/invoice/pdf/dompdf', $dompdf, $options );

		ob_start();
		$this->render_template();
		$html = ob_get_clean();
		// Pass the encoding: for large HTML on PHP 8.3+ dompdf's guess is ASCII and Cyrillic prints as "?".
		$dompdf->loadHtml( $html, 'UTF-8' );

		$dompdf->render();
		self::inject_page_count( $dompdf );

		return $dompdf->output();
	}

	private function render_template() {
		if ( ! is_readable( $this->get_template() ) ) {
			return;
		}

		include $this->get_template();
	}

	public static function inject_page_count( $dompdf ) {
		$canvas = $dompdf->getCanvas();
		assert( $canvas instanceof CPDF );
		$search  = self::insert_null_byte_before_each_character( '%PC%' );
		$replace = self::insert_null_byte_before_each_character( (string) $canvas->get_page_count() );

		foreach ( $canvas->get_cpdf()->objects as &$object ) {
			if ( $object['t'] === 'contents' ) {
				$object['c'] = str_replace( $search, $replace, $object['c'] );
			}
		}
	}

	private static function insert_null_byte_before_each_character( $string ) {
		return "\u{0000}" . substr( chunk_split( $string, 1, "\u{0000}" ), 0, -1 );
	}
}
