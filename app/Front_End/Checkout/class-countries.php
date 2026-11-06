<?php
/**
 * Countries customers can choose at checkout.
 *
 * @package Nashenski
 */

namespace Nashenski\Front_End\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Which countries customers can choose at checkout.
 *
 * - bg (default):  Bulgaria only. The country field is hidden and always "BG".
 * - bg_gr_ro:      Bulgaria, Greece and Romania (the couriers deliver there); the field is shown.
 * - woocommerce:   WooCommerce's own "Selling location(s)" settings.
 *
 * With one allowed country WooCommerce prints the country as a hidden "BG" input, which the
 * checkout scripts and shipping zones need, so the field is hidden with CSS, not removed.
 */
class Countries {
	/**
	 * Limits the allowed countries when a mode asks for it.
	 */
	public function __construct() {
		$countries = self::countries();

		if ( ! $countries ) {
			return;
		}

		$limit = function ( $all ) use ( $countries ) {
			$limited = array_intersect_key( (array) $all, array_flip( $countries ) );

			return $limited ? $limited : $all;
		};

		add_filter( 'woocommerce_countries_allowed_countries', $limit, 1000 );
		add_filter( 'woocommerce_countries_shipping_countries', $limit, 1000 );

		if ( array( 'BG' ) === $countries ) {
			add_filter( 'default_checkout_billing_country', array( __CLASS__, 'bulgaria' ), 1000 );
			add_filter( 'default_checkout_shipping_country', array( __CLASS__, 'bulgaria' ), 1000 );
			add_filter( 'woocommerce_default_address_fields', array( __CLASS__, 'hide_country_field' ), 1000 );
			add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'hide_country_locale' ), 1001 );
			add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'force_posted_country' ), 1000 );
		}
	}

	/**
	 * Gets the saved country mode.
	 *
	 * @return string bg, bg_gr_ro or woocommerce.
	 */
	public static function mode() {
		$mode = nashenski_get_option( 'checkout', 'countries' );

		return in_array( $mode, array( 'bg', 'bg_gr_ro', 'woocommerce' ), true ) ? $mode : 'bg';
	}

	/**
	 * Gets the allowed countries of the saved mode.
	 *
	 * @return string[] Allowed country codes, or an empty array to use WooCommerce's settings.
	 */
	public static function countries() {
		switch ( self::mode() ) {
			case 'bg':
				return array( 'BG' );
			case 'bg_gr_ro':
				return array( 'BG', 'GR', 'RO' );
			default:
				return array();
		}
	}

	/**
	 * Gives the default country.
	 *
	 * @return string
	 */
	public static function bulgaria() {
		return 'BG';
	}

	/**
	 * Hides the country field with CSS and defaults it to Bulgaria.
	 *
	 * @param array $fields Address fields.
	 * @return array
	 */
	public static function hide_country_field( $fields ) {
		if ( isset( $fields['country'] ) ) {
			$fields['country']['class']   = array_merge( (array) ( $fields['country']['class'] ?? array() ), array( 'nashenski-hidden-field' ) );
			$fields['country']['default'] = 'BG';
		}

		return $fields;
	}

	/**
	 * The block checkout reads "hidden" from the country locale.
	 *
	 * @param array $locales Country locales.
	 * @return array
	 */
	public static function hide_country_locale( $locales ) {
		$locales['BG']            = isset( $locales['BG'] ) && is_array( $locales['BG'] ) ? $locales['BG'] : array();
		$locales['BG']['country'] = array_merge(
			(array) ( $locales['BG']['country'] ?? array() ),
			array(
				'hidden'   => true,
				'required' => false,
			)
		);

		return $locales;
	}

	/**
	 * Builders and themes that drop the hidden input still end up with Bulgaria.
	 *
	 * @param array $data Posted checkout data.
	 * @return array
	 */
	public static function force_posted_country( $data ) {
		$data['billing_country'] = 'BG';

		if ( array_key_exists( 'shipping_country', $data ) ) {
			$data['shipping_country'] = 'BG';
		}

		return $data;
	}
}
