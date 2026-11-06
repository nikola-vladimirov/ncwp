<?php
namespace Nashenski;

defined( 'ABSPATH' ) || exit;

/**
 * Bulgarian Latin <-> Cyrillic transliteration.
 *
 * Cyrillic to Latin follows the Bulgarian Transliteration Act (Закон за транслитерацията):
 * щ = sht, ъ = a, ь = y, ю = yu, я = ya, and "ия" at the end of a word = "ia".
 * Latin to Cyrillic is the reverse with the common spellings people type (sht, zh, ts, ch, yu, ya, ...).
 */
class Transliteration {
	private static $to_latin = array(
		'А' => 'A',
		'Б' => 'B',
		'В' => 'V',
		'Г' => 'G',
		'Д' => 'D',
		'Е' => 'E',
		'Ж' => 'Zh',
		'З' => 'Z',
		'И' => 'I',
		'Й' => 'Y',
		'К' => 'K',
		'Л' => 'L',
		'М' => 'M',
		'Н' => 'N',
		'О' => 'O',
		'П' => 'P',
		'Р' => 'R',
		'С' => 'S',
		'Т' => 'T',
		'У' => 'U',
		'Ф' => 'F',
		'Х' => 'H',
		'Ц' => 'Ts',
		'Ч' => 'Ch',
		'Ш' => 'Sh',
		'Щ' => 'Sht',
		'Ъ' => 'A',
		'Ь' => 'Y',
		'Ю' => 'Yu',
		'Я' => 'Ya',
		'а' => 'a',
		'б' => 'b',
		'в' => 'v',
		'г' => 'g',
		'д' => 'd',
		'е' => 'e',
		'ж' => 'zh',
		'з' => 'z',
		'и' => 'i',
		'й' => 'y',
		'к' => 'k',
		'л' => 'l',
		'м' => 'm',
		'н' => 'n',
		'о' => 'o',
		'п' => 'p',
		'р' => 'r',
		'с' => 's',
		'т' => 't',
		'у' => 'u',
		'ф' => 'f',
		'х' => 'h',
		'ц' => 'ts',
		'ч' => 'ch',
		'ш' => 'sh',
		'щ' => 'sht',
		'ъ' => 'a',
		'ь' => 'y',
		'ю' => 'yu',
		'я' => 'ya',
	);

	// Longest Latin spellings first, so "sht" wins over "sh" and "s".
	private static $to_cyrillic = array(
		'sht' => 'щ',
		'zh'  => 'ж',
		'ts'  => 'ц',
		'ch'  => 'ч',
		'sh'  => 'ш',
		'yu'  => 'ю',
		'iu'  => 'ю',
		'ya'  => 'я',
		'ia'  => 'ия',
		'yo'  => 'йо',
		'a'   => 'а',
		'b'   => 'б',
		'v'   => 'в',
		'w'   => 'в',
		'g'   => 'г',
		'd'   => 'д',
		'e'   => 'е',
		'z'   => 'з',
		'i'   => 'и',
		'j'   => 'ж',
		'k'   => 'к',
		'l'   => 'л',
		'm'   => 'м',
		'n'   => 'н',
		'o'   => 'о',
		'p'   => 'п',
		'r'   => 'р',
		's'   => 'с',
		't'   => 'т',
		'u'   => 'у',
		'f'   => 'ф',
		'h'   => 'х',
		'c'   => 'ц',
		'q'   => 'я',
		'x'   => 'кс',
		'y'   => 'ъ',
	);

	public static function has_cyrillic( $text ) {
		return (bool) preg_match( '/\p{Cyrillic}/u', (string) $text );
	}

	public static function has_latin( $text ) {
		return (bool) preg_match( '/[A-Za-z]/', (string) $text );
	}

	public static function to_latin( $text ) {
		// The Act keeps the established spelling of the country name.
		$text = str_replace( array( 'България', 'БЪЛГАРИЯ' ), array( 'Bulgaria', 'BULGARIA' ), (string) $text );
		// "ия" at the end of a word is "ia" (София = Sofia, България = Bulgaria).
		$text = preg_replace_callback(
			'/ия(?=\P{L}|$)/iu',
			function ( $m ) {
				return 'Ия' === $m[0] || 'ИЯ' === $m[0] ? 'Ia' : 'ia';
			},
			$text
		);

		return strtr( $text, self::$to_latin );
	}

	public static function to_cyrillic( $text ) {
		$strange_words = apply_filters(
			'nashenski/transliteration/strange_words',
			array(
				'sofia'    => 'софия',
				'bulgaria' => 'българия',
				'tarnovo'  => 'търново',
				'turnovo'  => 'търново',
				'vratsa'   => 'враца',
				'dobrich'  => 'добрич',
			)
		);

		return preg_replace_callback(
			'/[A-Za-z]+/',
			function ( $m ) use ( $strange_words ) {
				$word  = $m[0];
				$lower = strtolower( $word );

				if ( isset( $strange_words[ $lower ] ) ) {
					$cyr = $strange_words[ $lower ];
				} else {
					$cyr = self::word_to_cyrillic( $lower );
				}

				return self::match_case( $word, $cyr );
			},
			(string) $text
		);
	}

	private static function word_to_cyrillic( $word ) {
		$out    = '';
		$length = strlen( $word );
		$vowels = 'aeiou';

		for ( $i = 0; $i < $length; ) {
			// "y" after a vowel and not before one is "й" (Nikolay = Николай); otherwise the longest match.
			if ( 'y' === $word[ $i ] && $i > 0 && false !== strpos( $vowels, $word[ $i - 1 ] ) && ( $i + 1 >= $length || false === strpos( $vowels, $word[ $i + 1 ] ) ) ) {
				$out .= 'й';
				++$i;
				continue;
			}

			// "ia" only at the end of a word is "ия"; inside a word it is "иа".
			foreach ( array( 3, 2, 1 ) as $size ) {
				$part = substr( $word, $i, $size );

				if ( 'ia' === $part && $i + 2 < $length ) {
					continue;
				}

				if ( strlen( $part ) === $size && isset( self::$to_cyrillic[ $part ] ) ) {
					$out .= self::$to_cyrillic[ $part ];
					$i   += $size;
					continue 2;
				}
			}

			$out .= $word[ $i ];
			++$i;
		}

		return $out;
	}

	private static function match_case( $original, $converted ) {
		if ( strtoupper( $original ) === $original && strlen( $original ) > 1 ) {
			return mb_strtoupper( $converted, 'UTF-8' );
		}

		if ( ctype_upper( $original[0] ) ) {
			return mb_strtoupper( mb_substr( $converted, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $converted, 1, null, 'UTF-8' );
		}

		return $converted;
	}

	/**
	 * Old name, still used for matching city names with the couriers.
	 */
	public static function latin2cyrillic( $word ) {
		return self::to_cyrillic( $word );
	}
}
