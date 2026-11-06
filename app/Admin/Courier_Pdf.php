<?php
namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/** Validate external courier preview URLs without guessing provider/CDN hostnames. */
class Courier_Pdf {
	public static function url( $value, $size = null ) {
		if ( ! is_string( $value ) || preg_match( '/[\x00-\x20\x7f\\\\]/', $value ) || ! preg_match( '~^https?://[a-z0-9.-]+(?::[0-9]+)?(?:[/?#]|$)~i', $value ) ) {
			return '';
		}
		$parts      = wp_parse_url( $value );
		$admin_host = wp_parse_url( admin_url(), PHP_URL_HOST );
		if ( ! $parts || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || strtolower( rtrim( $parts['host'], '.' ) ) === strtolower( rtrim( (string) $admin_host, '.' ) ) ) {
			return '';
		}
		$url = 'https://' . strtolower( $parts['host'] );
		if ( isset( $parts['port'] ) && $parts['port'] !== 443 && ! ( strtolower( $parts['scheme'] ) === 'http' && $parts['port'] === 80 ) ) {
			$url .= ':' . $parts['port'];
		}
		$url .= $parts['path'] ?? '/';
		$url .= isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$url .= isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
		return is_string( $size ) ? add_query_arg( 'label', $size, $url ) : $url;
	}

	public static function status( $courier, $status ) {
		if ( ! is_array( $status ) ) {
			return $status;
		}
		if ( 'econt' === $courier && isset( $status['label'] ) && is_array( $status['label'] ) ) {
			$status['label']['pdfURL'] = self::url( $status['label']['pdfURL'] ?? null );
		}
		return $status;
	}
}
