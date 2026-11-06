<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloader maps the class name to the file name.
/**
 * Amounts in Bulgarian words.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Invoice;

defined( 'ABSPATH' ) || exit;

/**
 * Amounts in Bulgarian words for documents ("Словом").
 *
 * Euro amounts: "двадесет и две евро и петдесет евроцента" (евро is neuter, евроцент masculine,
 * as in the Bulgarian Euro Introduction Act). Older orders in leva: "двадесет и два лева и
 * петдесет стотинки".
 */
class CurrencyToString {
	const UNITS = array(
		'EUR' => array(
			'one'         => 'евро',
			'many'        => 'евро',
			'gender'      => 'n',
			'cent_one'    => 'евроцент',
			'cent_many'   => 'евроцента',
			'cent_gender' => 'm',
		),
		'BGN' => array(
			'one'         => 'лев',
			'many'        => 'лева',
			'gender'      => 'm',
			'cent_one'    => 'стотинка',
			'cent_many'   => 'стотинки',
			'cent_gender' => 'f',
		),
	);

	/**
	 * Whether the currency has Bulgarian names here.
	 *
	 * @param string $currency Currency code.
	 * @return bool
	 */
	public static function supports( $currency ) {
		return isset( self::UNITS[ $currency ] );
	}

	/**
	 * A number from 0 to 999 999 999 in words. $gender sets the form of 1 and 2: m (един, два),
	 * f (една, две) or n (едно, две).
	 *
	 * @param int    $number Number.
	 * @param string $gender m, f or n.
	 * @return string
	 */
	public static function number_to_text( $number, $gender = 'm' ) {
		$number = (int) $number;

		if ( 0 === $number ) {
			return 'нула';
		}

		if ( $number >= 1000000 ) {
			$millions = intdiv( $number, 1000000 );
			$rest     = $number % 1000000;
			$text     = 1 === $millions ? 'един милион' : self::number_to_text( $millions, 'm' ) . ' милиона';

			return $text . self::join_rest( $rest, $gender );
		}

		if ( $number >= 1000 ) {
			$thousands = intdiv( $number, 1000 );
			$rest      = $number % 1000;

			if ( 1 === $thousands ) {
				$text = 'хиляда';
			} else {
				$text = self::number_to_text( $thousands, 'f' ) . ' хиляди';
			}

			return $text . self::join_rest( $rest, $gender );
		}

		return self::below_thousand( $number, $gender );
	}

	/**
	 * Words for the rest after millions or thousands. "и" goes before the last word only:
	 * 1 005 = хиляда и пет, 1 120 = хиляда сто и двадесет.
	 *
	 * @param int    $rest   Rest below the group.
	 * @param string $gender m, f or n.
	 * @return string
	 */
	private static function join_rest( $rest, $gender ) {
		if ( 0 === $rest ) {
			return '';
		}

		return ( self::is_one_word( $rest ) ? ' и ' : ' ' ) . self::number_to_text( $rest, $gender );
	}

	/**
	 * Numbers said as one group: 7, 40, 300, 5 000, 300 000.
	 *
	 * @param int $number Number.
	 * @return bool
	 */
	private static function is_one_word( $number ) {
		if ( $number < 20 ) {
			return true;
		}

		if ( $number < 100 ) {
			return 0 === $number % 10;
		}

		if ( $number < 1000 ) {
			return 0 === $number % 100;
		}

		return 0 === $number % 1000 && $number < 1000000 && self::is_one_word( intdiv( $number, 1000 ) );
	}

	/**
	 * A number from 0 to 999 in words.
	 *
	 * @param int    $number Number.
	 * @param string $gender m, f or n.
	 * @return string
	 */
	private static function below_thousand( $number, $gender ) {
		$ones     = array( '', 'един', 'два', 'три', 'четири', 'пет', 'шест', 'седем', 'осем', 'девет', 'десет', 'единадесет', 'дванадесет', 'тринадесет', 'четиринадесет', 'петнадесет', 'шестнадесет', 'седемнадесет', 'осемнадесет', 'деветнадесет' );
		$tens     = array( '', '', 'двадесет', 'тридесет', 'четиридесет', 'петдесет', 'шестдесет', 'седемдесет', 'осемдесет', 'деветдесет' );
		$hundreds = array( '', 'сто', 'двеста', 'триста', 'четиристотин', 'петстотин', 'шестстотин', 'седемстотин', 'осемстотин', 'деветстотин' );

		$h     = intdiv( $number, 100 );
		$rest  = $number % 100;
		$words = array();

		if ( $h ) {
			$words[] = $hundreds[ $h ];
		}

		if ( $rest ) {
			if ( $rest < 20 ) {
				$part = self::gendered( $ones[ $rest ], $rest, $gender );
			} else {
				$part = $tens[ intdiv( $rest, 10 ) ];

				if ( $rest % 10 ) {
					$part .= ' и ' . self::gendered( $ones[ $rest % 10 ], $rest % 10, $gender );
				}
			}

			// "и" joins the hundreds to a last word: сто и пет, сто и двадесет, but сто двадесет и пет.
			if ( $h && ( $rest < 20 || 0 === $rest % 10 ) ) {
				$words[] = 'и';
			}

			$words[] = $part;
		}

		return implode( ' ', $words );
	}

	/**
	 * The form of 1 or 2 for the gender; other digits as they are.
	 *
	 * @param string $word   Word of the digit.
	 * @param int    $digit  Digit.
	 * @param string $gender m, f or n.
	 * @return string
	 */
	private static function gendered( $word, $digit, $gender ) {
		if ( 1 === $digit ) {
			return 'f' === $gender ? 'една' : ( 'n' === $gender ? 'едно' : 'един' );
		}

		if ( 2 === $digit ) {
			return 'm' === $gender ? 'два' : 'две';
		}

		return $word;
	}

	/**
	 * The amount in words in the order currency, or '' when the currency has no Bulgarian name here.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public static function amount_in_words( $amount, $currency = 'EUR' ) {
		if ( ! self::supports( $currency ) ) {
			return '';
		}

		$unit                  = self::UNITS[ $currency ];
		list( $whole, $cents ) = array_map( 'intval', explode( '.', number_format( abs( (float) $amount ), 2, '.', '' ) ) );

		if ( $whole >= 1000000000 ) {
			return '';
		}

		$text = self::number_to_text( $whole, $unit['gender'] ) . ' ' . ( 1 === $whole ? $unit['one'] : $unit['many'] );

		if ( $cents ) {
			$text .= ' и ' . self::number_to_text( $cents, $unit['cent_gender'] ) . ' ' . ( 1 === $cents ? $unit['cent_one'] : $unit['cent_many'] );
		}

		return $text;
	}
}
