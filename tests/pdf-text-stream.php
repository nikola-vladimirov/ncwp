<?php
/**
 * Offline: the price list PDF reader inflates a compressed stream whose last byte is a line break
 * (part of the checksum, not the end of the line), with or without a line end after it, and
 * raises no PHP warning. Run: php tests/pdf-text-stream.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions -- an offline test script that prints to a terminal.

define( 'ABSPATH', __DIR__ . '/fixtures/' );
require __DIR__ . '/../app/Pricing/class-pdf-text.php';

$nashenski_stream = new ReflectionMethod( 'Nashenski\Pricing\Pdf_Text', 'stream' );
$nashenski_reader = ( new ReflectionClass( 'Nashenski\Pricing\Pdf_Text' ) )->newInstance();
$nashenski_warn   = array();
$nashenski_pass   = 0;

set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- the test fails on any PHP notice or warning.
	function ( $level, $message ) use ( &$nashenski_warn ) {
		$nashenski_warn[] = $message;

		return true;
	}
);

/**
 * One check.
 *
 * @param bool   $ok   Result.
 * @param string $what What was checked.
 */
function nashenski_check( $ok, $what ) {
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL: $what\n" );
		exit( 1 );
	}

	++$GLOBALS['nashenski_pass'];
}

/**
 * A text whose compressed form ends in the given byte.
 *
 * @param callable $compress gzcompress (zlib stream) or gzdeflate (bare deflate stream).
 * @param string   $last     The last byte wanted.
 * @return string
 */
function nashenski_text_ending( $compress, $last ) {
	for ( $i = 0; $i < 200000; $i++ ) {
		// Bytes that do not compress, so every last byte turns up.
		$text = md5( 'a' . $i, true ) . md5( 'b' . $i, true );

		if ( substr( $compress( $text ), -1 ) === $last ) {
			return $text;
		}
	}

	return '';
}

foreach ( array( 'gzcompress', 'gzdeflate' ) as $nashenski_compress ) {
	foreach ( array( "\n", "\r" ) as $nashenski_last ) {
		$nashenski_text = nashenski_text_ending( $nashenski_compress, $nashenski_last );
		$nashenski_data = $nashenski_compress( $nashenski_text );
		$nashenski_name = $nashenski_compress . ', last byte 0x' . bin2hex( $nashenski_last );

		nashenski_check( '' !== $nashenski_text, 'a stream for ' . $nashenski_name . ' was made' );

		foreach ( array( '', "\n", "\r\n" ) as $nashenski_eol ) {
			$nashenski_body = '<< /Filter /FlateDecode /Length ' . strlen( $nashenski_data ) . " >>\nstream\n" . $nashenski_data . $nashenski_eol . "endstream\n";

			nashenski_check( $nashenski_text === $nashenski_stream->invoke( $nashenski_reader, $nashenski_body ), $nashenski_name . ', line end 0x' . bin2hex( $nashenski_eol ) . ': the text is whole' );
		}
	}
}

// A stream that is not compressed loses only its line end.
nashenski_check( "BT (A)\nTj ET" === $nashenski_stream->invoke( $nashenski_reader, "<< /Length 12 >>\nstream\nBT (A)\nTj ET\r\nendstream\n" ), 'plain stream: the line end is trimmed' );

restore_error_handler();
nashenski_check( array() === $nashenski_warn, 'no PHP notice or warning: ' . implode( '; ', $nashenski_warn ) );

// Sanity: the damaged case does warn, so the check above means something. Cutting the last byte off is what the reader did before.
$nashenski_text = nashenski_text_ending( 'gzcompress', "\n" );
nashenski_check( false === @gzuncompress( rtrim( gzcompress( $nashenski_text ), "\r\n" ) ), 'a zlib stream with its last byte cut off does not inflate' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the test makes the failure on purpose.

echo 'PASS: ' . $nashenski_pass . " PDF stream checks\n";
