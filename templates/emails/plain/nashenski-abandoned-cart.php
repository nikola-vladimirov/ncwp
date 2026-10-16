<?php
/**
 * Abandoned cart reminder email, plain text.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/nashenski-abandoned-cart.php.
 *
 * @package Nashenski\Templates\Emails\Plain
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

// Turn <a href="url">text</a> into "text (url)" so the plain email keeps the links readable.
$nashenski_message_plain = preg_replace(
	'/<a\b[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is',
	'$2 ($1)',
	(string) $message
);

echo esc_html( wp_strip_all_tags( $nashenski_message_plain ) );

echo "\n\n----------------------------------------\n\n";

/*
 * Show user-defined additional content - this is set in the email settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo esc_html( wp_strip_all_tags( wptexturize( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own email hook.
