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
			// Pigeon Express prices (October 2026) for parcels handed in at a Pigeon office: to an APS,
			// to an office, to an address; lockers take up to 30 kg (heavier rows use the office price);
			// fuel surcharge 17% for October 2026 (changes monthly); COD 1.5%, at least 0.40.
			'pigeon' => array(
				'valid_from'   => '2026-10-01',
				'vat_included' => true,
				'surcharge'    => 17,
				'bands'        => array(
					array(
						'to'      => 1,
						'office'  => 2.21,
						'locker'  => 1.43,
						'address' => 2.97,
					),
					array(
						'to'      => 2,
						'office'  => 2.21,
						'locker'  => 1.43,
						'address' => 2.97,
					),
					array(
						'to'      => 3,
						'office'  => 2.21,
						'locker'  => 1.43,
						'address' => 2.97,
					),
					array(
						'to'      => 5,
						'office'  => 3.01,
						'locker'  => 2.63,
						'address' => 5.13,
					),
					array(
						'to'      => 6,
						'office'  => 3.01,
						'locker'  => 3.57,
						'address' => 5.13,
					),
					array(
						'to'      => 10,
						'office'  => 3.74,
						'locker'  => 3.57,
						'address' => 5.82,
					),
					array(
						'to'      => 15,
						'office'  => 6.54,
						'locker'  => 4.07,
						'address' => 11.32,
					),
					array(
						'to'      => 20,
						'office'  => 7.04,
						'locker'  => 6.07,
						'address' => 11.32,
					),
					array(
						'to'      => 25,
						'office'  => 8.25,
						'locker'  => 7.03,
						'address' => 14.83,
					),
					array(
						'to'      => 30,
						'office'  => 9.50,
						'locker'  => 7.03,
						'address' => 16.33,
					),
					array(
						'to'      => 35,
						'office'  => 10.75,
						'locker'  => 10.75,
						'address' => 17.83,
					),
					array(
						'to'      => 40,
						'office'  => 12.25,
						'locker'  => 12.25,
						'address' => 19.33,
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
	 * @return mixed
	 */
	public static function clean_amounts( $settings ) {
		if ( ! is_array( $settings ) ) {
			return $settings;
		}

		foreach ( array( 'free_shipping_over', 'fixed_price' ) as $key ) {
			if ( isset( $settings[ $key ] ) ) {
				$settings[ $key ] = self::non_negative( $settings[ $key ] );
			}
		}

		return $settings;
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
