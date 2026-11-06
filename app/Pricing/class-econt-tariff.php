<?php
/**
 * Econt Tariff.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Developer tool: turns the text of Econt's tariff PDF into the Econt list of Price_Lists (same keys as
 * Price_Lists::defaults( 'econt' ), without source). The plugin never calls it; nashenski-tools/econt-tariff.php does.
 *
 * Input: the output of `pdftotext -raw -enc UTF-8`, one table row per line, for example
 * "над 5 до 10 кг 12.62€ 7.84€ 12.62€ 14.15€" (tariff codes 1 city, 2 office or Econtomat, 3 door in a town
 * with an office, 4 door elsewhere). The parser is strict: when the parcels table does not have exactly
 * the expected rows and weight bands it returns the reason as a string, and the caller keeps the last good list.
 */
class Econt_Tariff {
	/** Upper weight in kg of each parcel band, in the order of the PDF. */
	const BANDS = array( 1, 2, 5, 10, 15, 20 );

	/** Econtomat price: 10% off the tariff. */
	const LOCKER = 0.9;

	/**
	 * Parse.
	 *
	 * @param mixed $text The pdftotext -raw -enc UTF-8 output of the whole PDF.
	 * @return array|string The price list, or why the text was not read.
	 */
	public static function parse( $text ) {
		// pdftotext starts each page with a form feed, which can land in front of a table row.
		$text = str_replace( array( "\r\n", "\f" ), "\n", (string) $text );

		if ( ! preg_match( '//u', $text ) ) {
			return 'The text is not UTF-8: run pdftotext with -enc UTF-8.';
		}

		if ( ! preg_match( '/^Колетни пратки$(.*?)^Непалетизирани пратки$/msu', $text, $m ) ) {
			return 'No "Колетни пратки" table (Cyrillic missing or the PDF changed).';
		}

		$table = $m[1];

		if ( false === strpos( $table, '(Тарифен код 1) (Тарифен код 2) (Тарифен код 3) (Тарифен код 4)' ) ) {
			return 'The tariff code columns 1 to 4 are not in the parcels table.';
		}

		// Every weight row, read or not, so a row with a changed layout is noticed instead of skipped.
		$rows = preg_match_all( '/^(?:над \d+ )?до \d+ кг/mu', $table );

		if ( count( self::BANDS ) + 1 !== $rows ) {
			return sprintf( 'The parcels table has %d weight rows, expected %d.', $rows, count( self::BANDS ) + 1 );
		}

		preg_match_all( '/^(?:над (\d+) )?до (\d+) кг((?: \d+\.\d{2}€)+)$/mu', $table, $lines, PREG_SET_ORDER );

		$bands = array();
		$codes = array();
		$from  = 0;

		foreach ( $lines as $line ) {
			preg_match_all( '/(\d+\.\d{2})€/u', $line[3], $p );
			$prices = array_map( 'floatval', $p[1] );

			if ( 4 !== count( $prices ) || (int) $line[1] !== $from ) {
				return sprintf( 'The row "%s" does not have 4 prices or does not follow the band before it.', trim( $line[0] ) );
			}

			$from    = (int) $line[2];
			$codes[] = $prices;
			$bands[] = array(
				'to'      => $from,
				'office'  => $prices[1],
				'locker'  => round( $prices[1] * self::LOCKER, 2 ),
				'address' => $prices[2],
			);
		}

		if ( array_column( $bands, 'to' ) !== self::BANDS ) {
			return 'The weight bands are ' . implode( ', ', array_column( $bands, 'to' ) ) . ' kg, expected ' . implode( ', ', self::BANDS ) . ' kg.';
		}

		$rows = count( $codes );

		for ( $i = 1; $i < $rows; $i++ ) {
			foreach ( $codes[ $i ] as $code => $price ) {
				if ( $price < $codes[ $i - 1 ][ $code ] ) {
					return sprintf( 'Tariff code %d is cheaper up to %d kg than the band before: columns may be shifted.', $code + 1, self::BANDS[ $i ] );
				}
			}
		}

		// Over 20 kg: one cell per code, split over several lines: "19.82€ + 0.52€ за / всеки кг над 20 кг до / 50 кг".
		if ( ! preg_match( '/^над 20 до 50 кг$(.*?)^Тарифно тегло/msu', $table, $m )
			|| 4 !== preg_match_all( '/(\d+\.\d{2})€\s*\+\s*(\d+\.\d{2})€\s+за\s+всеки\s+кг\s+над\s+20\s+кг\s+до\s+50\s+кг/u', $m[1], $over, PREG_SET_ORDER ) ) {
			return 'The "над 20 до 50 кг" row does not have 4 prices of the form "base€ + per kg€".';
		}

		$last = end( $codes );

		foreach ( $over as $code => $cell ) {
			// Econt's formula starts from the 20 kg price; another base means the split cells were read out of order.
			if ( (float) $cell[1] !== $last[ $code ] ) {
				return sprintf( 'Over 20 kg, tariff code %d starts at %s€, not at its 20 kg price %s€.', $code + 1, $cell[1], $last[ $code ] );
			}
		}

		if ( ! preg_match( '/Тарифата е валидна от (\d{2})\.(\d{2})\.(\d{4})/u', $table, $date ) ) {
			return 'No "Тарифата е валидна от" date under the parcels table.';
		}

		if ( false === strpos( $table, 'с включен ДДС' ) ) {
			return 'The parcels table no longer says the prices include VAT.';
		}

		// Cash on delivery, paid to the sender's bank account: the last column of the first fee row.
		if ( ! preg_match( '/^в офис на Еконт в брой (\d+(?:\.\d+)?) % (\d+(?:\.\d+)?) % (\d+(?:\.\d+)?) %$/mu', $text, $cod ) ) {
			return 'No cash on delivery row "в офис на Еконт в брой" with 3 percentages.';
		}

		return array(
			'valid_from'   => "$date[3]-$date[2]-$date[1]",
			'vat_included' => true,
			'surcharge'    => 0,
			'bands'        => $bands,
			'over'         => array(
				'base'   => array(
					'office'  => (float) $over[1][1],
					'locker'  => round( $over[1][1] * self::LOCKER, 2 ),
					'address' => (float) $over[2][1],
				),
				'per_kg' => array(
					'office'  => (float) $over[1][2],
					'locker'  => round( $over[1][2] * self::LOCKER, 2 ),
					'address' => (float) $over[2][2],
				),
			),
			'cod'          => array(
				'percent' => (float) $cod[3],
				'min'     => 0,
			),
		);
	}
}
