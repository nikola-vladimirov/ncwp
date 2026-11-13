<?php
/**
 * Export file writer.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Export\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Turns rows into a CSV, Excel (XLSX), JSON or XML file. The file is written to a temporary path
 * and sent to the browser; nothing is kept on the server.
 */
class Writer {
	/**
	 * Writes the rows to a temporary file in the chosen format.
	 *
	 * @param array $config Normalized config.
	 * @param array $rows   Raw values in column order.
	 * @return string Path of the temporary file.
	 */
	public static function write( $config, $rows ) {
		$kinds  = Fields::kinds( $config );
		$labels = wp_list_pluck( $config['columns'], 'label' );

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$path = wp_tempnam( 'nashenski-export' );

		switch ( $config['format'] ) {
			case 'xlsx':
				self::xlsx( $path, $config, $labels, $kinds, $rows );
				break;
			case 'json':
				self::json( $path, $config, $labels, $kinds, $rows );
				break;
			case 'xml':
				self::xml( $path, $config, $labels, $kinds, $rows );
				break;
			default:
				self::csv( $path, $config, $labels, $kinds, $rows );
		}

		return $path;
	}

	/**
	 * MIME type of an export format.
	 *
	 * @param string $format csv, xlsx, json or xml.
	 * @return string
	 */
	public static function mime( $format ) {
		$types = array(
			'csv'  => 'text/csv',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'json' => 'application/json',
			'xml'  => 'application/xml',
		);

		return $types[ $format ] ?? 'application/octet-stream';
	}

	/**
	 * Writes a CSV file.
	 *
	 * @param string $path   Temporary file path.
	 * @param array  $config Normalized config.
	 * @param array  $labels Column labels.
	 * @param array  $kinds  Column kinds.
	 * @param array  $rows   Raw values in column order.
	 */
	private static function csv( $path, $config, $labels, $kinds, $rows ) {
		// The rows are written one by one into the temporary file, so a large export is never held in memory; WP_Filesystem can only write a whole file at once.
		$handle    = fopen( $path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streams the rows into a temporary file; WP_Filesystem has no streaming write.
		$separator = 'tab' === $config['separator'] ? "\t" : $config['separator'];
		$convert   = function ( $text ) use ( $config ) {
			return 'cp1251' === $config['encoding'] && function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $text, 'Windows-1251', 'UTF-8' ) : $text;
		};

		if ( 'utf8bom' === $config['encoding'] ) {
			fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- the byte order mark at the start of the stream opened above.
		}

		if ( $config['header'] ) {
			fputcsv( $handle, array_map( $convert, $labels ), $separator, '"', '' );
		}

		foreach ( $rows as $row ) {
			$line = array();

			foreach ( $row as $i => $value ) {
				$line[] = $convert( self::safe_cell( Builder::format( $value, $kinds[ $i ], $config ), $value, $kinds[ $i ] ) );
			}

			fputcsv( $handle, $line, $separator, '"', '' );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- closes the stream opened above.
	}

	/**
	 * Spreadsheets run text that starts with = + - @ (also tab, line breaks and the full-width
	 * ＝ ＋ － ＠) as a formula; such text gets a leading quote (OWASP CSV injection). Only a money or
	 * number column holding a real number keeps its minus sign: format() made it a plain number.
	 *
	 * @param string $text Formatted cell.
	 * @param mixed  $raw  Value before formatting.
	 * @param string $kind Column kind (text, money, number, date).
	 */
	private static function safe_cell( $text, $raw, $kind ) {
		if ( in_array( $kind, array( 'money', 'number' ), true ) && is_numeric( $raw ) ) {
			return $text;
		}

		// false: not valid UTF-8, so it cannot be checked; quote it to be safe.
		$match = preg_match( '/^[=+\-@\t\r\n＝＋－＠]/u', $text );

		return 0 === $match ? $text : "'" . $text;
	}

	/**
	 * Writes a JSON file.
	 *
	 * @param string $path   Temporary file path.
	 * @param array  $config Normalized config.
	 * @param array  $labels Column labels.
	 * @param array  $kinds  Column kinds.
	 * @param array  $rows   Raw values in column order.
	 */
	private static function json( $path, $config, $labels, $kinds, $rows ) {
		$keys = array_map(
			function ( $column ) {
				return $column['field'];
			},
			$config['columns']
		);
		$data = array();

		foreach ( $rows as $row ) {
			$item = array();

			foreach ( $row as $i => $value ) {
				$item[ $config['header'] ? $labels[ $i ] : $keys[ $i ] ] = in_array( $kinds[ $i ], array( 'money', 'number' ), true ) && is_numeric( $value ) ? 0 + round( (float) $value, 2 ) : Builder::format( $value, $kinds[ $i ], $config );
			}

			$data[] = $item;
		}

		\Nashenski\File::filesystem()->put_contents( $path, (string) wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ), 0600 );
	}

	/**
	 * A valid XML element name from a field name.
	 *
	 * @param string $text     Field name.
	 * @param string $fallback Name used when none can be made.
	 * @return string
	 */
	private static function xml_name( $text, $fallback ) {
		$name = preg_replace( '/[^a-zA-Z0-9_\-]/', '_', remove_accents( (string) $text ) );

		return $name && ! preg_match( '/^[0-9\-_]/', $name ) ? $name : $fallback;
	}

	/**
	 * Writes an XML file.
	 *
	 * @param string $path   Temporary file path.
	 * @param array  $config Normalized config.
	 * @param array  $labels Column labels.
	 * @param array  $kinds  Column kinds.
	 * @param array  $rows   Raw values in column order.
	 */
	private static function xml( $path, $config, $labels, $kinds, $rows ) {
		$writer = new \XMLWriter();
		$writer->openUri( $path );
		$writer->setIndent( true );
		$writer->startDocument( '1.0', 'UTF-8' );
		$writer->startElement( $config['xml_root'] );

		foreach ( $rows as $row ) {
			$writer->startElement( $config['xml_row'] );

			foreach ( $row as $i => $value ) {
				$writer->startElement( self::xml_name( $config['columns'][ $i ]['field'], 'field' . $i ) );

				if ( $config['header'] ) {
					$writer->writeAttribute( 'label', $labels[ $i ] );
				}

				$writer->text( Builder::format( $value, $kinds[ $i ], array_merge( $config, array( 'decimal' => '.' ) ) ) );
				$writer->endElement();
			}

			$writer->endElement();
		}

		$writer->endElement();
		$writer->endDocument();
		$writer->flush();
	}

	/**
	 * Spreadsheet column letter of a zero-based index (0 is A).
	 *
	 * @param int $index Column index.
	 * @return string
	 */
	private static function col_letter( $index ) {
		$letter = '';

		for ( $n = $index + 1; $n > 0; $n = intdiv( $n - 1, 26 ) ) {
			$letter = chr( 65 + ( ( $n - 1 ) % 26 ) ) . $letter;
		}

		return $letter;
	}

	/**
	 * A minimal Excel workbook: one sheet, text as inline strings, amounts and counts as numbers.
	 *
	 * @param string $path   Temporary file path.
	 * @param array  $config Normalized config.
	 * @param array  $labels Column labels.
	 * @param array  $kinds  Column kinds.
	 * @param array  $rows   Raw values in column order.
	 */
	private static function xlsx( $path, $config, $labels, $kinds, $rows ) {
		$esc   = function ( $text ) {
			return htmlspecialchars( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $text ), ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		};
		$sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
		$r     = 0;
		$all   = $config['header'] ? array_merge( array( $labels ), $rows ) : $rows;

		foreach ( $all as $line_index => $row ) {
			++$r;
			$is_header = $config['header'] && 0 === $line_index;
			$sheet    .= '<row r="' . $r . '">';

			foreach ( array_values( $row ) as $i => $value ) {
				$ref  = self::col_letter( $i ) . $r;
				$kind = $kinds[ $i ] ?? 'text';

				if ( ! $is_header && in_array( $kind, array( 'money', 'number' ), true ) && is_numeric( $value ) ) {
					$sheet .= '<c r="' . $ref . '"' . ( 'money' === $kind ? ' s="1"' : '' ) . '><v>' . ( 0 + round( (float) $value, 4 ) ) . '</v></c>';
				} else {
					$text   = $is_header ? $value : Builder::format( $value, $kind, $config );
					$sheet .= '<c r="' . $ref . '" t="inlineStr"' . ( $is_header ? ' s="2"' : '' ) . '><is><t xml:space="preserve">' . $esc( $text ) . '</t></is></c>';
				}
			}

			$sheet .= '</row>';
		}

		$sheet .= '</sheetData></worksheet>';

		$zip = new \ZipArchive();
		$zip->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="1"><numFmt numFmtId="164" formatCode="0.00"/></numFmts><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>' );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet );
		$zip->close();
	}
}
