<?php
/**
 * Price Lists.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Public price lists of the couriers, used when a courier has no working login.
 *
 * One list per courier, stored in the option nashenski_settings_prices_{courier}:
 * - bands: rows of { to (kg), office, locker, address } prices, from light to heavy;
 * - over:  above the last band, { base: {office, locker, address}, per_kg: {office, locker, address} };
 * - vat_included: whether the prices include VAT; surcharge: extra % on top (fuel, toll);
 * - cod: cash on delivery fee { percent, min } on the amount collected;
 * - valid_from, source: where the prices come from.
 *
 * The defaults are the couriers' published prices for shops that hand parcels in at an office.
 */
class Price_Lists {
	const COURIERS = array( 'econt', 'speedy', 'pigeon' );

	/**
	 * Sources.
	 */
	public static function sources() {
		return array(
			'econt'  => 'https://admin-ee.econt.com/doc_templates/tariffs/courier-bg-bg.pdf',
			'speedy' => 'https://services.speedy.bg/pricelist/',
			'pigeon' => 'https://pigeonexpress.com/prices-and-taxes/',
		);
	}

	/**
	 * Defaults.
	 *
	 * @param mixed $courier Courier.
	 */
	public static function defaults( $courier ) {
		$lists = array(
			// Econt tariff valid from 01.10.2026: tariff code 2 (office or Econtomat to office or Econtomat),
			// code 3 (office to the customer's door), Econtomat 10% off, COD 1.65% paid to a bank account.
			'econt'  => array(
				'valid_from'   => '2026-10-01',
				'vat_included' => true,
				'surcharge'    => 0,
				'bands'        => array(
					array(
						'to'      => 1,
						'office'  => 4.10,
						'locker'  => 3.69,
						'address' => 5.41,
					),
					array(
						'to'      => 2,
						'office'  => 4.49,
						'locker'  => 4.04,
						'address' => 6.70,
					),
					array(
						'to'      => 5,
						'office'  => 4.90,
						'locker'  => 4.41,
						'address' => 8.49,
					),
					array(
						'to'      => 10,
						'office'  => 7.84,
						'locker'  => 7.06,
						'address' => 12.62,
					),
					array(
						'to'      => 15,
						'office'  => 8.82,
						'locker'  => 7.94,
						'address' => 17.00,
					),
					array(
						'to'      => 20,
						'office'  => 10.79,
						'locker'  => 9.71,
						'address' => 19.82,
					),
				),
				'over'         => array(
					'base'   => array(
						'office'  => 10.79,
						'locker'  => 9.71,
						'address' => 19.82,
					),
					'per_kg' => array(
						'office'  => 0.43,
						'locker'  => 0.39,
						'address' => 0.52,
					),
				),
				'cod'          => array(
					'percent' => 1.65,
					'min'     => 0,
				),
			),
			// Speedy price list valid from 01.10.2026, Standard 24 hours: base price between offices
			// and automats, plus the add-on for delivery to an address; automat promo 1.52 up to 3 kg
			// (until 01.01.2027); COD 2.4%, at least 0.61.
			'speedy' => array(
				'valid_from'   => '2026-10-01',
				'vat_included' => true,
				'surcharge'    => 0,
				'bands'        => array(
					array(
						'to'      => 3,
						'office'  => 2.69,
						'locker'  => 1.52,
						'address' => 5.28,
					),
					array(
						'to'      => 6,
						'office'  => 3.74,
						'locker'  => 3.74,
						'address' => 8.01,
					),
					array(
						'to'      => 10,
						'office'  => 4.62,
						'locker'  => 4.62,
						'address' => 8.89,
					),
					array(
						'to'      => 20,
						'office'  => 8.23,
						'locker'  => 8.23,
						'address' => 14.22,
					),
				),
				'over'         => array(
					'base'   => array(
						'office'  => 8.23,
						'locker'  => 8.23,
						'address' => 16.75,
					),
					'per_kg' => array(
						'office'  => 0.36,
						'locker'  => 0.36,
						'address' => 0.36,
					),
				),
				'cod'          => array(
					'percent' => 2.4,
					'min'     => 0.61,
				),
			),
			// Pigeon Express public prices, source: the published Pigeon page, dated 06.11.2026 (to an APS, to an office,
			// to an address); no price above 30 kg, so heavier parcels get no rate (Public_Price); fuel surcharge 17%
			// (changes monthly); COD 1.5%, at least 0.40.
			'pigeon' => array(
				'valid_from'   => '2026-11-06',
				'vat_included' => true,
				'surcharge'    => 17,
				'bands'        => array(
					array(
						'to'      => 1,
						'office'  => 1.20,
						'locker'  => 1.20,
						'address' => 2.70,
					),
					array(
						'to'      => 2,
						'office'  => 1.20,
						'locker'  => 1.20,
						'address' => 2.70,
					),
					array(
						'to'      => 3,
						'office'  => 1.20,
						'locker'  => 1.20,
						'address' => 2.70,
					),
					array(
						'to'      => 5,
						'office'  => 1.20,
						'locker'  => 1.20,
						'address' => 2.70,
					),
					array(
						'to'      => 10,
						'office'  => 1.50,
						'locker'  => 1.50,
						'address' => 4.00,
					),
					array(
						'to'      => 15,
						'office'  => 2.50,
						'locker'  => 2.50,
						'address' => 6.00,
					),
					array(
						'to'      => 20,
						'office'  => 2.50,
						'locker'  => 2.50,
						'address' => 6.00,
					),
					array(
						'to'      => 30,
						'office'  => 3.50,
						'locker'  => 3.50,
						'address' => 8.00,
					),
				),
				'over'         => array(
					'base'   => array(
						'office'  => 12.25,
						'locker'  => 12.25,
						'address' => 19.63,
					),
					'per_kg' => array(
						'office'  => 0.30,
						'locker'  => 0.30,
						'address' => 0.30,
					),
				),
				'cod'          => array(
					'percent' => 1.5,
					'min'     => 0.40,
				),
			),
		);

		$list = $lists[ $courier ] ?? array();

		if ( $list ) {
			$list['source'] = self::sources()[ $courier ];
		}

		return apply_filters( 'nashenski/price_list/defaults', $list, $courier );
	}

	/**
	 * Option name.
	 *
	 * @param mixed $courier Courier.
	 */
	public static function option_name( $courier ) {
		return 'nashenski_settings_prices_' . $courier;
	}

	/**
	 * Get.
	 *
	 * @param mixed $courier Courier.
	 */
	public static function get( $courier ) {
		$saved = get_option( self::option_name( $courier ) );
		$saved = is_string( $saved ) ? json_decode( $saved, true ) : $saved;

		return self::normalize( is_array( $saved ) && ! empty( $saved['bands'] ) ? $saved : self::defaults( $courier ), $courier );
	}

	/**
	 * A money setting as typed when it is a number that is not negative, or an empty string.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function non_negative( $value ) {
		return is_numeric( $value ) && (float) $value >= 0 ? $value : '';
	}

	/**
	 * A money setting as typed when it is a number above zero, or an empty string: a price of 0 or 0.00 means "not set".
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function positive( $value ) {
		return is_numeric( $value ) && (float) $value > 0 ? $value : '';
	}

	/**
	 * The amounts of a zone shipping method as saved: a negative or non-numeric value becomes empty.
	 * Hooked to woocommerce_shipping_{id}_instance_settings_values, which the form save and the REST API both run.
	 *
	 * @param mixed $settings Instance settings.
	 * @param mixed $method   The shipping method being saved; a pair that is not saved is shown as its error.
	 * @return mixed
	 */
	public static function clean_amounts( $settings, $method = null ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		foreach ( array( 'free_shipping_over', 'fixed_price' ) as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$settings[ $key ] = self::non_negative( $settings[ $key ] );
			}
		}

		if ( isset( $settings['free_shipping_by_country'] ) ) {
			$pairs    = array();
			$rejected = array();

			foreach ( self::free_over_list( $settings['free_shipping_by_country'], $rejected ) as $country => $amount ) {
				$pairs[] = $country . '=' . $amount;
			}

			$settings['free_shipping_by_country'] = implode( ', ', $pairs );

			if ( $rejected && is_object( $method ) && method_exists( $method, 'add_error' ) ) {
				$method->add_error(
					esc_html(
						sprintf(
							/* translators: %s: the country=amount pairs that were not saved, e.g. GR=ABC, BG=50. */
							__( 'Free shipping over, abroad: not saved: %s. Write a country code other than BG and an amount, for example GR=150.50.', 'nashenski-commerce' ),
							implode( ', ', $rejected )
						)
					)
				);
			}
		}

		return $settings;
	}

	/**
	 * The owner's "RO=150, GR=200" list as country => amount. Malformed pairs and BG (it has its own field) are skipped.
	 *
	 * @param mixed $value    Saved text.
	 * @param array $rejected Filled with the non-empty pairs that were skipped.
	 * @return array
	 */
	public static function free_over_list( $value, &$rejected = array() ) {
		$list = array();

		// A comma before a digit stays inside the amount, as in normalize(): before exactly 3 digits it separates thousands
		// (1,000 = 1000), before 1 or 2 final digits it is the decimal point (150,50 = 150.50); any other comma rejects the pair.
		foreach ( preg_split( '/[,;\n]+(?!\s*\d)/', strtoupper( is_string( $value ) ? $value : '' ) ) as $pair ) {
			$typed = preg_replace( '/\s+/', '', $pair );
			$pair  = preg_replace( '/(?<=\d),(?=\d{1,2}$)/', '.', preg_replace( '/(?<=\d),(?=\d{3}(?!\d))/', '', $typed ) );
			if ( preg_match( '/^([A-Z]{2})=(\d+(?:\.\d+)?)$/', $pair, $m ) && 'BG' !== $m[1] ) {
				$list[ $m[1] ] = $m[2];
			} elseif ( '' !== $typed ) {
				$rejected[] = $typed;
			}
		}

		return $list;
	}

	/**
	 * Free shipping threshold for a country: Bulgaria uses the main field, other countries only the owner's list.
	 *
	 * @param string $country    ISO code.
	 * @param mixed  $bulgaria   The "Free shipping over" value.
	 * @param mixed  $by_country The "Free shipping over, abroad" text.
	 * @return mixed The amount, or '' when none is set.
	 */
	public static function free_over( $country, $bulgaria, $by_country ) {
		return 'BG' === $country ? $bulgaria : ( self::free_over_list( $by_country )[ $country ] ?? '' );
	}

	/**
	 * Clean a list (from the form, the updater or storage).
	 *
	 * @param mixed $prices Prices.
	 * @param mixed $courier Courier.
	 */
	public static function normalize( $prices, $courier ) {
		$kinds  = array( 'office', 'locker', 'address' );
		$number = function ( $value ) {
			$value = str_replace( ',', '.', (string) $value );

			// A negative price, surcharge or fee would give a negative shipping rate, so it counts as not filled in.
			return '' !== self::non_negative( $value ) ? round( (float) $value, 4 ) : null;
		};
		$bands  = array();

		foreach ( (array) ( $prices['bands'] ?? array() ) as $band ) {
			$to = $number( $band['to'] ?? '' );

			if ( null === $to || $to <= 0 ) {
				continue;
			}

			$row = array( 'to' => $to );

			foreach ( $kinds as $kind ) {
				$row[ $kind ] = $number( $band[ $kind ] ?? '' );
			}

			$bands[] = $row;
		}

		usort(
			$bands,
			function ( $a, $b ) {
				return $a['to'] <=> $b['to'];
			}
		);

		$over = array(
			'base'   => array(),
			'per_kg' => array(),
		);

		foreach ( $kinds as $kind ) {
			$over['base'][ $kind ]   = $number( $prices['over']['base'][ $kind ] ?? '' );
			$over['per_kg'][ $kind ] = $number( $prices['over']['per_kg'][ $kind ] ?? '' );
		}

		return array(
			'valid_from'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $prices['valid_from'] ?? '' ) ) ? $prices['valid_from'] : '',
			'source'       => esc_url_raw( (string) ( $prices['source'] ?? self::sources()[ $courier ] ?? '' ) ),
			'vat_included' => ! empty( $prices['vat_included'] ),
			'surcharge'    => (float) ( $number( $prices['surcharge'] ?? 0 ) ?? 0 ),
			'bands'        => $bands,
			'over'         => $over,
			'cod'          => array(
				'percent' => (float) ( $number( $prices['cod']['percent'] ?? 0 ) ?? 0 ),
				'min'     => (float) ( $number( $prices['cod']['min'] ?? 0 ) ?? 0 ),
			),
		);
	}

	/**
	 * Save.
	 *
	 * @param mixed $courier Courier.
	 * @param mixed $prices Prices.
	 */
	public static function save( $courier, $prices ) {
		update_option( self::option_name( $courier ), wp_json_encode( self::normalize( $prices, $courier ) ) );
	}
}
