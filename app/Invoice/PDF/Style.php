<?php
namespace Nashenski\Invoice\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * How the PDF documents look: colours, font, size, paper and logo size.
 *
 * Values come from Documents > PDF appearance. When the TokenForge plugin is active and
 * "Follow TokenForge colours" is on, the colours come from its palette instead, so documents
 * match the website; its values are also the defaults shown in the settings.
 */
class Style {
	const GROUP = 'pdf';

	public static function defaults() {
		return array(
			'title_color'   => '#1d2327',
			'accent_color'  => '#2271b1',
			'text_color'    => '#2c3338',
			'table_color'   => '#f4f4f4',
			'font'          => 'dejavu sans',
			'font_size'     => 'normal',
			'paper'         => 'A4',
			'logo_size'     => 'medium',
			'density'       => 'compact',
			'date_format'   => 'd.m.Y',
			'show_contacts' => 'yes',
			'show_received' => 'yes',
		);
	}

	public static function tokenforge_active() {
		return class_exists( '\TokenForge\Palette' );
	}

	/**
	 * Colours from the TokenForge palette, as hex (the PDF engine does not read OKLCH).
	 *
	 * @return array Only the colours TokenForge has.
	 */
	public static function tokenforge_colors() {
		if ( ! self::tokenforge_active() ) {
			return array();
		}

		$settings = \TokenForge\Palette::settings();
		$families = isset( $settings['families'] ) && is_array( $settings['families'] ) ? $settings['families'] : array();
		$hex      = function ( $name ) use ( $families ) {
			$value = $families[ $name ]['hex'] ?? '';

			return preg_match( '/^#[0-9a-f]{6}$/i', (string) $value ) ? strtolower( $value ) : '';
		};

		// The text colour is the colour scheme's "text dark" (for example "var(--black)").
		$text = '';

		if ( class_exists( '\TokenForge\Scheme' ) ) {
			$scheme = \TokenForge\Scheme::settings();
			$text   = self::resolve_tokenforge_color( (string) ( $scheme['text_dark'] ?? '' ), $hex );
		}

		$colors = array_filter(
			array(
				'title_color'  => $hex( 'secondary' ) ? $hex( 'secondary' ) : $text,
				'accent_color' => $hex( 'primary' ),
				'text_color'   => $text,
			)
		);

		if ( ! empty( $colors['accent_color'] ) ) {
			$colors['table_color'] = self::tint( $colors['accent_color'], 0.9 );
		}

		return $colors;
	}

	/**
	 * "#123456", "var(--black)", "var(--primary-dark)" or "var(--name, #fallback)" to hex, or ''.
	 *
	 * @param callable $family Returns a palette family's hex by name ('' when unknown).
	 */
	private static function resolve_tokenforge_color( $value, $family ) {
		$value = trim( $value );

		if ( preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
			return strtolower( $value );
		}

		if ( ! preg_match( '/^var\(\s*--([a-z0-9-]+)\s*(?:,\s*(#[0-9a-f]{6}))?\s*\)$/i', $value, $m ) ) {
			return '';
		}

		$name  = strtolower( $m[1] );
		$fixed = array(
			'black' => '#000000',
			'white' => '#ffffff',
		);

		if ( isset( $fixed[ $name ] ) ) {
			return $fixed[ $name ];
		}

		// Shades: mix the family colour with black or white.
		$shades = array(
			'-ultra-dark'  => -0.6,
			'-dark'        => -0.35,
			'-semi-dark'   => -0.2,
			'-ultra-light' => 0.9,
			'-light'       => 0.6,
			'-semi-light'  => 0.3,
		);

		foreach ( $shades as $suffix => $amount ) {
			if ( substr( $name, -strlen( $suffix ) ) === $suffix ) {
				$base = $family( substr( $name, 0, -strlen( $suffix ) ) );

				if ( $base ) {
					return $amount > 0 ? self::tint( $base, $amount ) : self::shade( $base, -$amount );
				}
			}
		}

		$hex = $family( $name );

		return $hex ? $hex : ( isset( $m[2] ) ? strtolower( $m[2] ) : '' );
	}

	/**
	 * Mix a hex colour with black. $amount 0 = colour, 1 = black.
	 */
	public static function shade( $hex, $amount ) {
		$hex = ltrim( $hex, '#' );
		$out = '#';

		for ( $i = 0; $i < 3; $i++ ) {
			$out .= str_pad( dechex( (int) round( hexdec( substr( $hex, $i * 2, 2 ) ) * ( 1 - $amount ) ) ), 2, '0', STR_PAD_LEFT );
		}

		return $out;
	}

	public static function follows_tokenforge() {
		return self::tokenforge_active() && 'no' !== nashenski_get_option( self::GROUP, 'follow_tokenforge' );
	}

	/**
	 * Default values for the settings fields: TokenForge's colours when it is active.
	 */
	public static function field_defaults() {
		return array_merge( self::defaults(), self::tokenforge_colors() );
	}

	public static function get() {
		$style = self::field_defaults();

		foreach ( array_keys( $style ) as $key ) {
			$value = nashenski_get_option( self::GROUP, $key );

			if ( false !== $value && '' !== $value ) {
				$style[ $key ] = $value;
			}
		}

		if ( self::follows_tokenforge() ) {
			$style = array_merge( $style, self::tokenforge_colors() );
		}

		if ( ! isset( self::date_formats()[ $style['date_format'] ] ) ) {
			$style['date_format'] = self::defaults()['date_format'];
		}

		foreach ( array( 'title_color', 'accent_color', 'text_color', 'table_color' ) as $key ) {
			if ( ! preg_match( '/^#[0-9a-f]{6}$/i', (string) $style[ $key ] ) ) {
				$style[ $key ] = self::defaults()[ $key ];
			}
		}

		return apply_filters( 'nashenski/invoice/pdf/style', $style );
	}

	/**
	 * Mix a hex colour with white. $amount 0 = colour, 1 = white.
	 */
	public static function tint( $hex, $amount ) {
		$hex = ltrim( $hex, '#' );
		$out = '#';

		for ( $i = 0; $i < 3; $i++ ) {
			$channel = hexdec( substr( $hex, $i * 2, 2 ) );
			$out    .= str_pad( dechex( (int) round( $channel + ( 255 - $channel ) * $amount ) ), 2, '0', STR_PAD_LEFT );
		}

		return $out;
	}

	/**
	 * Date formats for the documents => example.
	 */
	public static function date_formats() {
		return array(
			'd.m.Y' => '02.10.2026',
			'Y-m-d' => '2026-10-02',
			'j F Y' => __( '2 October 2026 (month in the language of the document)', 'nashenski-commerce' ),
		);
	}

	/**
	 * Logo box in pixels: array( width, height ).
	 */
	public static function logo_box() {
		$sizes = array(
			'small'  => array( 160, 45 ),
			'medium' => array( 220, 70 ),
			'large'  => array( 280, 100 ),
		);

		return $sizes[ self::get()['logo_size'] ] ?? $sizes['medium'];
	}

	/**
	 * CSS printed after the template's own rules.
	 */
	public static function css() {
		$s    = self::get();
		$base = array(
			'small'  => 9,
			'normal' => 10,
			'large'  => 11.5,
		);
		$size = $base[ $s['font_size'] ] ?? 10;
		$font = in_array( $s['font'], array( 'dejavu sans', 'dejavu serif', 'dejavu sans mono' ), true ) ? $s['font'] : 'dejavu sans';
		// Spacing: table cell padding and the line height of the From and To boxes.
		$densities = array(
			'compact'  => array( '4px 6px', '1.3' ),
			'normal'   => array( '6px 8px', '1.45' ),
			'spacious' => array( '8px 10px', '1.7' ),
		);
		$density   = $densities[ $s['density'] ] ?? $densities['compact'];

		return sprintf(
			'body { font-family: "%1$s"; color: %2$s; }
			h1 { color: %3$s; }
			.add-detail { border-bottom: 2px solid %4$s; padding-bottom: 10px; }
			.site-details .site-details__name { color: %3$s; }
			table tr th { background: %5$s; color: %2$s; font-size: %7$spx; }
			table tr td { font-size: %6$spx; }
			.gray-color { color: %2$s; }
			footer { color: %2$s; border-top: 1px solid %4$s; padding-top: 4px; }
			table tr th, table tr td { padding: %8$s; }
			.box-text { line-height: %9$s; }',
			esc_attr( $font ),
			$s['text_color'],
			$s['title_color'],
			$s['accent_color'],
			$s['table_color'],
			$size,
			$size + 3,
			$density[0],
			$density[1]
		);
	}
}
