<?php // phpcs:disable Universal.Files.SeparateFunctionsFromOO, WordPress.WP.AlternativeFunctions -- offline CLI test script: no WordPress, so stubs and plain-text output are on purpose.
/**
 * Offline test: text a shopper typed carries no web address into a mail of the shop, the waybill
 * line of a plain text email has no HTML, and an unsubscribed email stays unsubscribed without its
 * cart rows. Run: php tests/shopper-text-in-emails.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['stored'] = array();

/**
 * Stub.
 *
 * @param string $text Text.
 * @return string
 */
function wp_strip_all_tags( $text ) {
	return trim( strip_tags( $text ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- the stub of wp_strip_all_tags().
}

/**
 * Stub.
 *
 * @param string $name     Option name.
 * @param mixed  $fallback Value when missing.
 * @return mixed
 */
function get_option( $name, $fallback = false ) {
	return $GLOBALS['stored'][ $name ] ?? $fallback;
}

require __DIR__ . '/../app/functions.php';
require __DIR__ . '/../app/Abandoned_Carts/class-tracker.php';

use Nashenski\Abandoned_Carts\Tracker;

$checks = 0;
/**
 * Expect.
 *
 * @param bool   $condition Condition.
 * @param string $message   Message.
 * @throws RuntimeException When the check fails.
 */
function expect( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI test output.
	}
}

foreach ( array(
	'Иван'                                         => 'Иван',
	'Mary Ann'                                     => 'Mary Ann',
	'Your account is locked, see www.evil.example' => 'Your account is locked, see',
	'Pay at http://evil.example/x?y=1 now'         => 'Pay at  now',
	'see evil.example/login'                       => 'see',
	'mail me@evil.example'                         => 'mail',
	'www.evil.example'                             => '',
	'Д.Петров'                                     => 'Д.Петров',
	'Иван Д.Петров'                                => 'Иван Д.Петров',
	'Mr.Smith'                                     => 'Mr.Smith',
	'J.R.R. Tolkien'                               => 'J.R.R. Tolkien',
	'Ана-Мария'                                    => 'Ана-Мария',
	'Go to Evil.COM now'                           => 'Go to  now',
	'ME@Evil.Example'                              => '',
) as $typed => $kept ) {
	expect( nashenski_strip_urls( $typed ) === $kept, 'Name "' . $typed . '" must become "' . $kept . '"' );
}

expect(
	"Label number: 123. Track your order. https://track.example/?n=123\nLabel number: 456." === nashenski_plain_email_text( 'Label number: 123. <a href="https://track.example/?n=123" target="_blank">Track your order.</a><br>Label number: 456.' ),
	'The waybill line of a plain text email has no tags and keeps the tracking address'
);

expect( ! Tracker::is_unsubscribed( 'ivan@example.com' ), 'Nobody is unsubscribed at first' );
$GLOBALS['stored'][ Tracker::UNSUBSCRIBED_OPTION ] = array( hash( 'sha256', 'ivan@example.com' ) );
expect( Tracker::is_unsubscribed( ' Ivan@Example.com ' ), 'An unsubscribed email is found by its hash, whatever the letter case' );
expect( ! Tracker::is_unsubscribed( 'maria@example.com' ), 'Another email is not unsubscribed' );
expect( false === strpos( (string) json_encode( $GLOBALS['stored'] ), 'ivan@' ), 'The marker keeps no address' );

echo 'PASS: ' . (int) $checks . " shopper text in emails assertions\n";
