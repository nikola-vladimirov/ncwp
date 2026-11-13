<?php
/**
 * Offline: the request readers sanitize where they read and still keep the data. Cyrillic, quotes
 * and the characters < > % survive in the courier cookie (JSON, sanitized as text before it is
 * decoded) and in the checkout form of "update order review" (a query string, taken from
 * WooCommerce's action); tags are still stripped from every value; a request value is read only
 * after one of its nonces checks out. Run: php tests/request-readers.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- an offline test script: it stubs WordPress functions and fills the request by hand.

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * Stub with the steps of WordPress's sanitize_text_field() that change a value: invalid UTF-8 is
 * emptied, tags are stripped, white space is joined and percent-encoded octets are removed.
 *
 * @param mixed $text Text.
 * @return string
 */
function sanitize_text_field( $text ) {
	if ( is_array( $text ) || is_object( $text ) ) {
		return '';
	}

	$text = mb_check_encoding( (string) $text, 'UTF-8' ) ? (string) $text : '';

	if ( false !== strpos( $text, '<' ) ) {
		// As wp_pre_kses_less_than(): a "<" that opens no tag becomes an entity.
		$text = preg_replace_callback(
			'%<[^>]*?((?=<)|>|$)%',
			function ( $found ) {
				return false === strpos( $found[0], '>' ) ? htmlspecialchars( $found[0], ENT_QUOTES ) : $found[0];
			},
			$text
		);
		$text = strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text ) );
	}

	$text = trim( preg_replace( '/[\r\n\t ]+/', ' ', $text ) );

	while ( preg_match( '/%[a-f0-9]{2}/i', $text, $octet ) ) {
		$text = trim( preg_replace( '/ +/', ' ', str_replace( $octet[0], '', $text ) ) );
	}

	return $text;
}

/**
 * Stub.
 *
 * @param mixed $value Value.
 * @return mixed
 */
function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : ( is_string( $value ) ? stripslashes( $value ) : $value );
}

/**
 * Stub.
 *
 * @param mixed    $value    Value.
 * @param callable $callback Callback for every leaf.
 * @return mixed
 */
function map_deep( $value, $callback ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			$value[ $key ] = map_deep( $item, $callback );
		}

		return $value;
	}

	return call_user_func( $callback, $value );
}

/**
 * Stub: the nonce "good" is valid for the action "second".
 *
 * @param string $nonce  Nonce.
 * @param string $action Action.
 * @return bool
 */
function wp_verify_nonce( $nonce, $action ) {
	$GLOBALS['verified'][] = $action;

	return 'good' === $nonce && 'second' === $action;
}

require __DIR__ . '/../app/functions.php';

$nashenski_pass = 0;

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
 * JSON as assets/js/pickers.js and export-builder.js write it (safeJson()): < > % as JSON escapes.
 *
 * @param mixed $value Value.
 * @return string
 */
function nashenski_safe_json( $value ) {
	return strtr(
		json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		array(
			'<' => '\u003c',
			'>' => '\u003e',
			'%' => '\u0025',
		)
	);
}

// The courier cookie. PHP has URL-decoded it; WordPress added slashes.
$nashenski_choice = array(
	'type'           => 'address',
	'city'           => 'София',
	'selectedOffice' => 'Офис "Младост 1" \\ бл. 5',
	'other'          => 'вход Б, 50% <b>отстъпка</b>, a<b и c>d',
	'streetNumber'   => "12\tА",
	'office'         => array( 'name' => 'Пловдив Тракия' ),
);

$_COOKIE['nashenski--econt-address'] = addslashes( nashenski_safe_json( $nashenski_choice ) );
$nashenski_read                      = nashenski_courier_cookie( 'nashenski--econt-address' );

nashenski_check( is_array( $nashenski_read ) && 'София' === $nashenski_read['city'] && 'Пловдив Тракия' === $nashenski_read['office']['name'], 'the cookie keeps Cyrillic, nested values too' );
nashenski_check( 'Офис "Младост 1" \\ бл. 5' === $nashenski_read['selectedOffice'], 'the cookie keeps quotes and a backslash' );
nashenski_check( map_deep( $nashenski_choice, 'sanitize_text_field' ) === $nashenski_read, 'every value of the cookie is the sanitized value, nothing else is lost' );
nashenski_check( false === strpos( $nashenski_read['other'], '<b>' ) && false !== strpos( $nashenski_read['other'], 'отстъпка' ) && '12 А' === $nashenski_read['streetNumber'], 'tags are stripped from a value and white space is joined' );

// Without the JSON escapes a "<" in one value and a ">" in a later one would cut everything between them out of the JSON: that is why the scripts write them.
$nashenski_two = array(
	'first'  => 'x<y',
	'second' => 'z>w',
);
nashenski_check( map_deep( $nashenski_two, 'sanitize_text_field' ) !== map_deep( json_decode( sanitize_text_field( json_encode( $nashenski_two ) ), true ), 'sanitize_text_field' ), 'plain JSON with < and > does not survive the sanitizer (the reason for the escapes)' );
nashenski_check( map_deep( $nashenski_two, 'sanitize_text_field' ) === map_deep( json_decode( sanitize_text_field( nashenski_safe_json( $nashenski_two ) ), true ), 'sanitize_text_field' ), 'the same values with the escapes do' );

$_COOKIE['nashenski--econt-address'] = 'not json';
nashenski_check( '' === nashenski_courier_cookie( 'nashenski--econt-address' ) && '' === nashenski_courier_cookie( 'nashenski--none' ), 'a broken or missing cookie is an empty choice' );

// The checkout form of "update order review": a query string with percent-encoded Cyrillic, handed over by WooCommerce's action.
nashenski_check( array() === nashenski_order_review_data( array( 'billing_city' ) ), 'before WooCommerce hands the form over there is nothing to read' );

$_POST['post_data'] = 'billing_city=FromTheRequest';

nashenski_keep_order_review_form( 'billing_city=' . rawurlencode( 'Велико Търново' ) . '&billing_phone=0888+123+456&billing_address_1=' . rawurlencode( 'ул. <i>Шипка</i> 5' ) . '&shipping_method%5B0%5D=nashenski_econt%3A3&secret=1' );

$nashenski_form = nashenski_order_review_data( array( 'billing_city', 'billing_phone', 'billing_address_1', 'shipping_method', 'missing' ) );

nashenski_check( 'Велико Търново' === $nashenski_form['billing_city'] && '0888 123 456' === $nashenski_form['billing_phone'], 'the form keeps percent-encoded Cyrillic' );
nashenski_check( 'ул. Шипка 5' === $nashenski_form['billing_address_1'] && array( 'nashenski_econt:3' ) === $nashenski_form['shipping_method'], 'each form value is sanitized after decoding, lists too' );
nashenski_check( ! isset( $nashenski_form['secret'] ) && ! isset( $nashenski_form['missing'] ) && 4 === count( $nashenski_form ), 'only the named fields come back, and never the request itself' );

nashenski_keep_order_review_form( array( 'not a string' ) );
nashenski_check( array() === nashenski_order_review_data( array( 'billing_city' ) ), 'a form that is not text leaves nothing behind' );

// A request value: read only after one of the accepted nonces checks out.
$_REQUEST = array( 'office' => array( 'name' => addslashes( 'Офис "Център" <script>x</script>' ) ) );
nashenski_check( 'fallback' === nashenski_verified_request( 'office', 'fallback', 'nonce', array( 'first', 'second' ) ), 'no nonce, no read' );

$_REQUEST['nonce'] = 'bad';
nashenski_check( 'fallback' === nashenski_verified_request( 'office', 'fallback', 'nonce', array( 'first', 'second' ) ), 'a wrong nonce, no read' );

$_REQUEST['nonce']   = 'good';
$GLOBALS['verified'] = array();
nashenski_check( array( 'name' => 'Офис "Център"' ) === nashenski_verified_request( 'office', 'fallback', 'nonce', array( 'first', 'second', 'third' ) ), 'a nonce of the second action reads the value, sanitized' );
nashenski_check( array( 'first', 'second' ) === $GLOBALS['verified'], 'the nonce check stops at the action that matches' );
nashenski_check( 'fallback' === nashenski_verified_request( 'missing', 'fallback', 'nonce', 'second' ), 'a missing key is the fallback' );

echo 'PASS: ' . $nashenski_pass . " request reader checks\n";
