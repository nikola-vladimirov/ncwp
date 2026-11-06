<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloader maps the class name to the file name.
/**
 * PDF text extractor for courier price lists.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal PDF text extractor for courier price lists.
 *
 * Reads the page content streams (FlateDecode), decodes text shown with Tj / TJ through each
 * font's ToUnicode map, and returns lines in reading order (top to bottom, left to right).
 * Enough for the table-like PDFs the couriers publish; not a general PDF reader.
 */
class Pdf_Text {
	/**
	 * Object number => raw object body.
	 *
	 * @var array<int, string>
	 */
	private $objects = array();

	/**
	 * Text lines of a PDF in reading order.
	 *
	 * @param string $pdf PDF data.
	 * @return array
	 */
	public static function lines( $pdf ) {
		$reader = new self();

		return $reader->extract( (string) $pdf );
	}

	/**
	 * Reads the objects and the text of every page.
	 *
	 * @param string $pdf PDF data.
	 * @return array
	 */
	private function extract( $pdf ) {
		preg_match_all( '/(\d+)\s+0\s+obj(.*?)endobj/s', $pdf, $m, PREG_SET_ORDER );

		foreach ( $m as $object ) {
			$this->objects[ (int) $object[1] ] = $object[2];
		}

		$lines = array();

		foreach ( $this->objects as $body ) {
			if ( ! preg_match( '#/Type\s*/Page[^s]#', $body ) ) {
				continue;
			}

			$fonts = $this->page_fonts( $body );

			foreach ( $this->page_contents( $body ) as $content ) {
				$lines = array_merge( $lines, $this->text_lines( $content, $fonts ) );
			}
		}

		return $lines;
	}

	/**
	 * Stream data of an object, inflated when compressed.
	 *
	 * @param string $body Object body.
	 * @return string
	 */
	private function stream( $body ) {
		if ( ! preg_match( '/stream\r?\n(.*)endstream/s', $body, $m ) ) {
			return '';
		}

		$data = rtrim( $m[1], "\r\n" );

		if ( false !== strpos( $body, '/FlateDecode' ) ) {
			$inflated = @gzuncompress( $data, 20 * 1024 * 1024 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- try both zlib formats.

			if ( false === $inflated ) {
				$inflated = @gzinflate( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			$data = false === $inflated ? '' : $inflated;
		}

		return $data;
	}

	/**
	 * Object number of the first reference in the text.
	 *
	 * @param string $text Text with an "N 0 R" reference.
	 * @return int
	 */
	private function ref( $text ) {
		return preg_match( '/(\d+)\s+0\s+R/', $text, $m ) ? (int) $m[1] : 0;
	}

	/**
	 * Content streams of a page.
	 *
	 * @param string $page Page object body.
	 * @return array
	 */
	private function page_contents( $page ) {
		$contents = array();

		if ( preg_match( '#/Contents\s*\[([^\]]*)\]#', $page, $m ) ) {
			preg_match_all( '/(\d+)\s+0\s+R/', $m[1], $refs );
			$ids = $refs[1];
		} else {
			$ids = array( $this->ref( preg_match( '#/Contents\s*(\d+\s+0\s+R)#', $page, $c ) ? $c[1] : '' ) );
		}

		foreach ( $ids as $id ) {
			if ( isset( $this->objects[ (int) $id ] ) ) {
				$contents[] = $this->stream( $this->objects[ (int) $id ] );
			}
		}

		return $contents;
	}

	/**
	 * Font resource name => glyph code (hex) => text.
	 *
	 * @param string $page Page object body.
	 * @return array
	 */
	private function page_fonts( $page ) {
		$fonts     = array();
		$resources = $page;

		if ( preg_match( '#/Resources\s*(\d+)\s+0\s+R#', $page, $m ) && isset( $this->objects[ (int) $m[1] ] ) ) {
			$resources = $this->objects[ (int) $m[1] ];
		}

		$font_dict = '';

		if ( preg_match( '#/Font\s*<<(.*?)>>#s', $resources, $m ) ) {
			$font_dict = $m[1];
		} elseif ( preg_match( '#/Font\s*(\d+)\s+0\s+R#', $resources, $m ) && isset( $this->objects[ (int) $m[1] ] ) ) {
			$font_dict = $this->objects[ (int) $m[1] ];
		}

		preg_match_all( '#/([A-Za-z0-9_.+-]+)\s+(\d+)\s+0\s+R#', $font_dict, $entries, PREG_SET_ORDER );

		foreach ( $entries as $entry ) {
			$font = $this->objects[ (int) $entry[2] ] ?? '';
			$map  = array();

			if ( preg_match( '#/ToUnicode\s*(\d+)\s+0\s+R#', $font, $t ) && isset( $this->objects[ (int) $t[1] ] ) ) {
				$map = $this->cmap( $this->stream( $this->objects[ (int) $t[1] ] ) );
			}

			$fonts[ $entry[1] ] = $map;
		}

		return $fonts;
	}

	/**
	 * UTF-16BE hex as UTF-8 text.
	 *
	 * @param string $hex Hex digits.
	 * @return string
	 */
	private static function utf16_hex( $hex ) {
		$bytes = pack( 'H*', $hex );

		return function_exists( 'mb_convert_encoding' ) ? mb_convert_encoding( $bytes, 'UTF-8', 'UTF-16BE' ) : $bytes;
	}

	/**
	 * Glyph code (hex) => text from a ToUnicode map.
	 *
	 * @param string $cmap ToUnicode stream.
	 * @return array
	 */
	private function cmap( $cmap ) {
		$map = array();

		if ( preg_match_all( '/beginbfchar(.*?)endbfchar/s', $cmap, $blocks ) ) {
			foreach ( $blocks[1] as $block ) {
				preg_match_all( '/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER );

				foreach ( $pairs as $pair ) {
					$map[ strtoupper( $pair[1] ) ] = self::utf16_hex( $pair[2] );
				}
			}
		}

		if ( preg_match_all( '/beginbfrange(.*?)endbfrange/s', $cmap, $blocks ) ) {
			foreach ( $blocks[1] as $block ) {
				// Array form: <start> <end> [<u1> <u2> ...], one target per code.
				preg_match_all( '/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[([^\]]*)\]/', $block, $arrays, PREG_SET_ORDER );

				foreach ( $arrays as $array ) {
					$width = strlen( $array[1] );
					$code  = hexdec( $array[1] );
					preg_match_all( '/<([0-9A-Fa-f]+)>/', $array[3], $targets );

					foreach ( $targets[1] as $target ) {
						$map[ strtoupper( str_pad( dechex( $code ), $width, '0', STR_PAD_LEFT ) ) ] = self::utf16_hex( $target );
						++$code;
					}
				}

				$block = preg_replace( '/<[0-9A-Fa-f]+>\s*<[0-9A-Fa-f]+>\s*\[[^\]]*\]/', '', $block );

				// Simple form: <start> <end> <first target>.
				preg_match_all( '/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $ranges, PREG_SET_ORDER );

				foreach ( $ranges as $range ) {
					$width  = strlen( $range[1] );
					$start  = hexdec( $range[1] );
					$end    = hexdec( $range[2] );
					$target = hexdec( $range[3] );

					for ( $code = $start; $code <= $end && $code - $start < 2048; $code++ ) {
						$map[ strtoupper( str_pad( dechex( $code ), $width, '0', STR_PAD_LEFT ) ) ] = self::utf16_hex( str_pad( dechex( $target + $code - $start ), 4, '0', STR_PAD_LEFT ) );
					}
				}
			}
		}

		return $map;
	}

	/**
	 * Text of a hex or literal PDF string.
	 *
	 * @param string $token PDF string, <hex> or (literal).
	 * @param array  $map   Glyph code => text.
	 * @return string
	 */
	private function decode( $token, $map ) {
		if ( '<' === $token[0] ) {
			$hex  = strtoupper( preg_replace( '/[^0-9A-Fa-f]/', '', $token ) );
			$out  = '';
			$step = $map && 4 === strlen( (string) array_key_first( $map ) ) ? 4 : 2;
			$len  = strlen( $hex );

			for ( $i = 0; $i < $len; $i += $step ) {
				$code = substr( $hex, $i, $step );
				$out .= $map[ $code ] ?? '';
			}

			return $out;
		}

		// Literal string (...): unescape and map byte by byte when there is a map.
		$raw = stripcslashes( substr( $token, 1, -1 ) );

		if ( ! $map ) {
			return $raw;
		}

		$out = '';

		foreach ( str_split( $raw ) as $byte ) {
			$out .= $map[ strtoupper( bin2hex( $byte ) ) ] ?? $byte;
		}

		return $out;
	}

	/**
	 * Text pieces with their position, grouped into lines.
	 *
	 * @param string $content Content stream.
	 * @param array  $fonts   Font maps of the page.
	 * @return array
	 */
	private function text_lines( $content, $fonts ) {
		$pieces = array();
		$font   = '';
		$x      = 0;
		$y      = 0;

		$pattern = '/\/([A-Za-z0-9_.+-]+)\s+[\d.]+\s+Tf|([-\d.]+)\s+([-\d.]+)\s+Td|([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+([-\d.]+)\s+Tm|(<[0-9A-Fa-f\s]*>|\((?:\\\\.|[^\\\\)])*\))\s*Tj|\[((?:[^\]\\\\]|\\\\.)*)\]\s*TJ/s';

		preg_match_all( $pattern, $content, $ops, PREG_SET_ORDER );

		foreach ( $ops as $op ) {
			if ( '' !== ( $op[1] ?? '' ) ) {
				$font = $op[1];
			} elseif ( '' !== ( $op[2] ?? '' ) ) {
				$x += (float) $op[2];
				$y += (float) $op[3];
			} elseif ( '' !== ( $op[4] ?? '' ) ) {
				$x = (float) $op[8];
				$y = (float) $op[9];
			} elseif ( '' !== ( $op[10] ?? '' ) ) {
				$pieces[] = array( $x, $y, $this->decode( $op[10], $fonts[ $font ] ?? array() ) );
			} elseif ( isset( $op[11] ) ) {
				preg_match_all( '/<[0-9A-Fa-f\s]*>|\((?:\\\\.|[^\\\\)])*\)/', $op[11], $parts );
				$text = '';

				foreach ( $parts[0] as $part ) {
					$text .= $this->decode( $part, $fonts[ $font ] ?? array() );
				}

				$pieces[] = array( $x, $y, $text );
			}
		}

		// Group by row (same y within half a point), top to bottom, then left to right.
		usort(
			$pieces,
			function ( $a, $b ) {
				return abs( $a[1] - $b[1] ) < 0.5 ? $a[0] <=> $b[0] : $b[1] <=> $a[1];
			}
		);

		$lines   = array();
		$row     = null;
		$current = array();

		foreach ( $pieces as $piece ) {
			if ( null !== $row && abs( $piece[1] - $row ) >= 0.5 ) {
				$lines[] = trim( implode( ' ', $current ) );
				$current = array();
			}

			$row       = $piece[1];
			$current[] = trim( $piece[2] );
		}

		if ( $current ) {
			$lines[] = trim( implode( ' ', $current ) );
		}

		return array_values( array_filter( $lines, 'strlen' ) );
	}
}
