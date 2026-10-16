<?php
namespace Nashenski\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Reads a courier's published price list (Econt PDF tariff, Pigeon Express and CVC price pages)
 * and turns it into a price list. Nothing is saved here: the settings page shows the changes and
 * the shop owner decides. A weekly check only records that new prices were found.
 *
 * The parsers are strict: when a page does not look as expected they return an error instead of
 * guessing, so a changed website never produces wrong prices.
 */
class Updater {
	const NOTICE_OPTION = 'nashenski_price_updates';
	/** Courier => the published price list that was already seen (saved or dismissed). */
	const SEEN_OPTION = 'nashenski_price_updates_seen';
	const CRON = 'nashenski/prices/check';

	public function __construct() {
		add_action( 'wp_ajax_nashenski_check_prices', array( __CLASS__, 'ajax_check' ) );
		add_action( self::CRON, array( __CLASS__, 'weekly_check' ) );
		add_action( 'admin_init', array( __CLASS__, 'cron_schedule' ) );
	}

	/** Self-healing schedule check, admin only so the front end skips it. */
	public static function cron_schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::CRON );
		}
	}

	/**
	 * @return array|\WP_Error The proposed price list.
	 */
	public static function fetch( $courier ) {
		$url = Price_Lists::sources()[ $courier ] ?? '';

		if ( ! $url ) {
			return new \WP_Error( 'nashenski_prices_source', __( 'There is no public price list for this courier.', 'nashenski-commerce' ) );
		}

		$response = wp_remote_get( $url, array( 'timeout' => 30 ) );

		// Econt's server sends the PDF without a length and closes the connection without the TLS
		// goodbye, which cURL with OpenSSL 3 reports as an error. PHP's own streams accept it.
		if ( is_wp_error( $response ) ) {
			$streams = function ( &$url, &$headers, &$data, &$type, &$options ) {
				$options['transport'] = \WpOrg\Requests\Transport\Fsockopen::class;
			};

			add_action( 'requests-requests.before_request', $streams, 10, 5 );
			$response = wp_remote_get( $url, array( 'timeout' => 30 ) );
			remove_action( 'requests-requests.before_request', $streams, 10 );
		}

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				'nashenski_prices_fetch',
				sprintf(
					/* translators: %s: courier website address */
					__( 'The courier website did not let Nashenski Commerce read the price list (%s). Open it, compare and edit the prices by hand.', 'nashenski-commerce' ),
					$url
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );

		switch ( $courier ) {
			case 'econt':
				$list = self::parse_econt( Pdf_Text::lines( $body ) );
				break;
			case 'speedy':
				$list = self::parse_speedy( self::html_text( $body ), self::html_tables( $body ) );
				break;
			case 'pigeon':
				$list = self::parse_pigeon( self::html_text( $body ), self::html_tables( $body ) );
				break;
			case 'cvc':
				$list = self::parse_cvc( self::html_text( $body ), self::html_tables( $body ) );
				break;
			default:
				$list = null;
		}

		if ( ! is_array( $list ) || empty( $list['bands'] ) ) {
			return new \WP_Error( 'nashenski_prices_parse', __( 'The price list on the courier website has changed and could not be read safely. Your prices were not changed.', 'nashenski-commerce' ) );
		}

		$list['source'] = $url;

		return Price_Lists::normalize( $list, $courier );
	}

	/**
	 * Table rows of an HTML page: array of tables, each an array of rows, each an array of cell texts.
	 */
	public static function html_tables( $html ) {
		if ( ! class_exists( '\DOMDocument' ) ) {
			return array();
		}

		$dom = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . (string) $html );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$tables = array();

		foreach ( $dom->getElementsByTagName( 'table' ) as $table ) {
			$rows = array();

			foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
				$cells = array();

				foreach ( $tr->childNodes as $cell ) {
					if ( in_array( strtolower( $cell->nodeName ), array( 'td', 'th' ), true ) ) {
						$cells[] = trim( preg_replace( '/\s+/u', ' ', $cell->textContent ) );
					}
				}

				if ( $cells ) {
					$rows[] = $cells;
				}
			}

			$tables[] = $rows;
		}

		return $tables;
	}

	private static function html_text( $html ) {
		$html = preg_replace( '#<(script|style|noscript)[^>]*>.*?</\1>#is', '', (string) $html );
		$html = preg_replace( '#<(br|tr|p|div|h\d|li|table|section)[^>]*>#i', "\n", $html );
		$html = preg_replace( '#</t[dh]>#i', ' | ', $html );
		$text = html_entity_decode( wp_strip_all_tags( $html, false ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/[ \t\x{00A0}]+/u', ' ', $text );

		return array_values( array_filter( array_map( 'trim', explode( "\n", $text ) ), 'strlen' ) );
	}

	private static function euros( $text ) {
		preg_match_all( '/(\d+[.,]\d{2})\s*€/u', $text, $m );

		return array_map( function ( $v ) {
			return (float) str_replace( ',', '.', $v );
		}, $m[1] );
	}

	private static function upper_kg( $line ) {
		return preg_match( '/до\s*(\d+(?:[.,]\d+)?)\s*кг/u', $line, $m ) ? (float) str_replace( ',', '.', $m[1] ) : null;
	}

	private static function valid_from( $text ) {
		return preg_match( '/(\d{2})\.(\d{2})\.(\d{4})/', $text, $m ) ? "$m[3]-$m[2]-$m[1]" : '';
	}

	/**
	 * Econt courier tariff PDF: parcels table with tariff codes 1-4 per weight band.
	 */
	public static function parse_econt( $raw_lines ) {
		// Qt prints each letter separately: join the letters of each word.
		$lines = array_map( function ( $line ) {
			return preg_replace( '/\s{2,}/', ' ', preg_replace( '/(?<=\S) (?=\S)/u', '', trim( $line ) ) );
		}, (array) $raw_lines );

		$text = implode( "\n", $lines );
		$start = array_search( 'Колетни пратки', $lines, true );

		if ( false === $start ) {
			return null;
		}

		$bands = array();
		$over = null;

		for ( $i = $start; $i < count( $lines ) && $i < $start + 80; $i++ ) {
			$line = $lines[ $i ];

			if ( 0 === strpos( $line, 'Посочените цени' ) && $bands && $over ) {
				break;
			}

			$prices = self::euros( $line );

			// "над 20 до 50 кг" row: four times "base€ + per_kg€ за".
			if ( 8 === count( $prices ) && false !== strpos( $line, '+' ) ) {
				$over = array(
					'base' => array( 'office' => $prices[2], 'locker' => round( $prices[2] * 0.9, 2 ), 'address' => $prices[4] ),
					'per_kg' => array( 'office' => $prices[3], 'locker' => round( $prices[3] * 0.9, 2 ), 'address' => $prices[5] ),
				);
				continue;
			}

			$to = self::upper_kg( $line );

			if ( null !== $to && 4 === count( $prices ) && $to <= 20 ) {
				// Codes: 1 city courier, 2 office/Econtomat both ends, 3 office and door, 4 door to door.
				$bands[] = array( 'to' => $to, 'office' => $prices[1], 'locker' => round( $prices[1] * 0.9, 2 ), 'address' => $prices[2] );
			}
		}

		if ( count( $bands ) < 5 || ! $over ) {
			return null;
		}

		$list = array(
			'vat_included' => false !== strpos( $text, 'включен ДДС' ),
			'surcharge' => 0,
			'bands' => $bands,
			'over' => $over,
			'cod' => array( 'percent' => 1.65, 'min' => 0 ),
		);

		if ( preg_match( '/Тарифата е валидна от (\d{2}\.\d{2}\.\d{4})/u', $text, $m ) ) {
			$list['valid_from'] = self::valid_from( $m[1] );
		}

		// Cash on delivery paid to a bank account: last column of the first fee row.
		if ( preg_match( '/в офис на Еконт в брой\s*([\d.,]+)\s*%\s*([\d.,]+)\s*%\s*([\d.,]+)\s*%/u', $text, $m ) ) {
			$list['cod']['percent'] = (float) str_replace( ',', '.', $m[3] );
		}

		return $list;
	}

	/**
	 * Euro amount in a table cell ("2.69 €", "1.43 € / 2.80 лв."), or null ("-").
	 */
	private static function cell_euro( $cell ) {
		$euros = self::euros( (string) $cell );

		return $euros ? $euros[0] : null;
	}

	/**
	 * "base € + per_kg €/кг" in a cell: array( base, per_kg ) or null.
	 */
	private static function cell_per_kg( $cell ) {
		return preg_match( '/([\d.,]+)\s*€\s*\+\s*([\d.,]+)\s*€\s*\/\s*кг/u', (string) $cell, $m )
			? array( (float) str_replace( ',', '.', $m[1] ), (float) str_replace( ',', '.', $m[2] ) )
			: null;
	}

	/**
	 * Speedy price list page, "Стандарт 24 часа": weight | base (office, automat) | pickup add-on | delivery add-on.
	 */
	public static function parse_speedy( $lines, $tables = array() ) {
		$bands = array();
		$over = null;
		$text = implode( "\n", $lines );

		foreach ( $tables as $rows ) {
			foreach ( $rows as $cells ) {
				if ( 4 !== count( $cells ) || null === ( $to = self::upper_kg( $cells[0] ) ) || $to > 31.5 ) {
					continue;
				}

				$base = self::cell_euro( $cells[1] );
				$delivery = self::cell_euro( $cells[3] );

				if ( null === $base || null === $delivery ) {
					continue;
				}

				if ( preg_match( '/\+\s*([\d.,]+)\s*€\s*за всеки/u', $cells[1], $m ) ) {
					$per_kg = (float) str_replace( ',', '.', $m[1] );
					$over = array(
						'base' => array( 'office' => $base, 'locker' => $base, 'address' => round( $base + $delivery, 2 ) ),
						'per_kg' => array( 'office' => $per_kg, 'locker' => $per_kg, 'address' => $per_kg ),
					);
				} elseif ( ! $over && $to <= 20 ) {
					$bands[] = array( 'to' => $to, 'office' => $base, 'locker' => $base, 'address' => round( $base + $delivery, 2 ) );
				}
			}

			if ( $bands && $over ) {
				break;
			}
		}

		if ( count( $bands ) < 4 || ! $over ) {
			return null;
		}

		// Promotion: office/automat to automat up to 3 kg.
		if ( preg_match( '/промоционална цена от ([\d.,]+)\s*€.*?до\s*(\d+)\s*кг/us', $text, $m ) && (float) $m[2] >= $bands[0]['to'] ) {
			$bands[0]['locker'] = (float) str_replace( ',', '.', $m[1] );
		}

		$list = array( 'vat_included' => true, 'surcharge' => 0, 'bands' => $bands, 'over' => $over, 'cod' => array( 'percent' => 2.4, 'min' => 0.61 ) );

		if ( preg_match( '/валидни към дата (\d{2}\.\d{2}\.\d{4})/u', $text, $m ) ) {
			$list['valid_from'] = self::valid_from( $m[1] );
		}

		if ( preg_match( '/банков път.*?([\d.,]+)%\s*от стойността на НП, но не по-малко от ([\d.,]+)\s*€/us', $text, $m ) ) {
			$list['cod'] = array( 'percent' => (float) str_replace( ',', '.', $m[1] ), 'min' => (float) str_replace( ',', '.', $m[2] ) );
		}

		return $list;
	}

	/**
	 * Pigeon Express prices page: one table per sender (APS, office, address), columns
	 * to APS | to office | to address. The shop's "send from" setting picks the table.
	 */
	public static function parse_pigeon( $lines, $tables = array() ) {
		$text = implode( "\n", $lines );
		$price_tables = array_values( array_filter( $tables, function ( $rows ) {
			return isset( $rows[0] ) && false !== strpos( implode( ' ', $rows[0] ), 'до АПС' );
		} ) );
		$sender = nashenski_get_option( 'pigeon', 'send_from' );
		$index = 'address' === $sender ? 2 : 1;

		if ( empty( $price_tables[ $index ] ) ) {
			return null;
		}

		$bands = array();
		$over = null;

		foreach ( $price_tables[ $index ] as $cells ) {
			if ( 4 !== count( $cells ) || null === ( $to = self::upper_kg( $cells[0] ) ) ) {
				continue;
			}

			$office_kg = self::cell_per_kg( $cells[2] );
			$address_kg = self::cell_per_kg( $cells[3] );

			if ( $office_kg || $address_kg ) {
				$office_kg = $office_kg ? $office_kg : array( self::cell_euro( $cells[2] ), 0 );
				$address_kg = $address_kg ? $address_kg : array( self::cell_euro( $cells[3] ), 0 );
				$over = array(
					'base' => array( 'office' => $office_kg[0], 'locker' => $office_kg[0], 'address' => $address_kg[0] ),
					'per_kg' => array( 'office' => $office_kg[1], 'locker' => $office_kg[1], 'address' => $address_kg[1] ),
				);
				break;
			}

			$office = self::cell_euro( $cells[2] );
			$locker = self::cell_euro( $cells[1] );
			$address = self::cell_euro( $cells[3] );

			if ( null === $office || null === $address ) {
				continue;
			}

			// Lockers take parcels up to 30 kg; heavier rows use the office price.
			$bands[] = array( 'to' => $to, 'office' => $office, 'locker' => null === $locker ? $office : $locker, 'address' => $address );
		}

		if ( count( $bands ) < 5 || ! $over ) {
			return null;
		}

		$list = array( 'vat_included' => true, 'surcharge' => 0, 'bands' => $bands, 'over' => $over, 'cod' => array( 'percent' => 1.5, 'min' => 0.40 ) );

		if ( preg_match( '/([\d.,]+)\s*%\s*Актуална такса гориво/u', $text, $m ) ) {
			$list['surcharge'] = (float) str_replace( ',', '.', $m[1] );
		}

		// The page has no date; the fuel surcharge names its month ("октомври 2026").
		$months = array( 'януари' => 1, 'февруари' => 2, 'март' => 3, 'април' => 4, 'май' => 5, 'юни' => 6, 'юли' => 7, 'август' => 8, 'септември' => 9, 'октомври' => 10, 'ноември' => 11, 'декември' => 12 );

		if ( preg_match( '/Актуална такса гориво\s*-\s*(\p{L}+)\s+(\d{4})/u', $text, $m ) && isset( $months[ mb_strtolower( $m[1] ) ] ) ) {
			$list['valid_from'] = sprintf( '%04d-%02d-01', $m[2], $months[ mb_strtolower( $m[1] ) ] );
		}

		return $list;
	}

	/**
	 * CVC price list page: weight | leva / euro (address to address, without VAT).
	 */
	public static function parse_cvc( $lines, $tables = array() ) {
		$bands = array();
		$over = null;
		$text = implode( "\n", $lines );

		foreach ( $tables as $rows ) {
			foreach ( $rows as $cells ) {
				$row = implode( ' | ', $cells );

				if ( 2 === count( $cells ) && preg_match( '/^до\s*(\d+(?:[.,]\d+)?)\s*кг/u', $cells[0], $m ) && preg_match( '/[\d.,]+\s*\/\s*([\d.,]+)/', $cells[1], $p ) ) {
					$price = (float) str_replace( ',', '.', $p[1] );
					$bands[] = array( 'to' => (float) str_replace( ',', '.', $m[1] ), 'office' => $price, 'locker' => $price, 'address' => $price );
				} elseif ( $bands && ! $over && preg_match( '/над 20 кг.*?\+\s*([\d.,]+)\s*евро/u', $row, $m ) ) {
					$last = end( $bands );
					$per_kg = (float) str_replace( ',', '.', $m[1] );
					$over = array(
						'base' => array( 'office' => $last['address'], 'locker' => $last['address'], 'address' => $last['address'] ),
						'per_kg' => array( 'office' => $per_kg, 'locker' => $per_kg, 'address' => $per_kg ),
					);
				}
			}

			if ( $bands && $over ) {
				break;
			}

			$bands = $over ? $bands : array();
		}

		if ( count( $bands ) < 5 || ! $over ) {
			return null;
		}

		$list = array( 'vat_included' => false, 'surcharge' => 0, 'bands' => $bands, 'over' => $over, 'cod' => array( 'percent' => 1.2, 'min' => 0.61 ) );

		if ( preg_match( '/Валидна от:\s*(\d{2}\.\d{2}\.\d{4})/u', $text, $m ) ) {
			$list['valid_from'] = self::valid_from( $m[1] );
		}

		return $list;
	}

	/**
	 * Values that differ between two lists: array of array( label, old, new ).
	 */
	public static function changes( $old, $new ) {
		$changes = array();
		$add = function ( $label, $a, $b ) use ( &$changes ) {
			if ( (string) $a !== (string) $b ) {
				$changes[] = array( $label, (string) $a, (string) $b );
			}
		};

		$add( __( 'Valid from', 'nashenski-commerce' ), $old['valid_from'], $new['valid_from'] );
		$add( __( 'Extra surcharge %', 'nashenski-commerce' ), $old['surcharge'], $new['surcharge'] );
		$add( __( 'Cash on delivery %', 'nashenski-commerce' ), $old['cod']['percent'], $new['cod']['percent'] );
		$add( __( 'Cash on delivery minimum', 'nashenski-commerce' ), $old['cod']['min'], $new['cod']['min'] );

		$old_bands = array();

		foreach ( $old['bands'] as $band ) {
			$old_bands[ (string) $band['to'] ] = $band;
		}

		foreach ( $new['bands'] as $band ) {
			foreach ( array( 'office', 'locker', 'address' ) as $kind ) {
				/* translators: 1: weight in kg, 2: office, locker or address */
				$add( sprintf( __( 'Up to %1$s kg, %2$s', 'nashenski-commerce' ), $band['to'], $kind ), $old_bands[ (string) $band['to'] ][ $kind ] ?? '', $band[ $kind ] );
			}
		}

		return $changes;
	}

	public static function ajax_check() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( 'nashenski_check_prices', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'nashenski-commerce' ) ), 403 );
		}

		$courier = isset( $_POST['courier'] ) ? sanitize_key( wp_unslash( $_POST['courier'] ) ) : '';

		if ( ! in_array( $courier, Price_Lists::COURIERS, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown courier.', 'nashenski-commerce' ) ), 400 );
		}

		$new = self::fetch( $courier );

		if ( is_wp_error( $new ) ) {
			wp_send_json_error( array( 'message' => $new->get_error_message() ) );
		}

		wp_send_json_success( array(
			'list' => $new,
			'changes' => self::changes( Price_Lists::get( $courier ), $new ),
		) );
	}

	/**
	 * Take couriers off the "new published prices" notice: one when its prices are saved, all when
	 * the notice is dismissed. The weekly check only brings a courier back for a newer price list.
	 */
	public static function forget( $courier = null ) {
		$found = (array) get_option( self::NOTICE_OPTION, array() );
		$seen = (array) get_option( self::SEEN_OPTION, array() );

		foreach ( null === $courier ? array_keys( $found ) : array( $courier ) as $key ) {
			if ( isset( $found[ $key ] ) ) {
				$seen[ $key ] = $found[ $key ];
				unset( $found[ $key ] );
			}
		}

		update_option( self::NOTICE_OPTION, $found, false );
		update_option( self::SEEN_OPTION, $seen, false );
	}

	/**
	 * Weekly: remember which couriers published prices different from the saved ones.
	 */
	public static function weekly_check() {
		$found = array();
		$seen = (array) get_option( self::SEEN_OPTION, array() );

		foreach ( Price_Lists::COURIERS as $courier ) {
			if ( 'yes' !== nashenski_get_option( 'apis', 'enable_' . $courier ) ) {
				continue;
			}

			$new = self::fetch( $courier );

			if ( is_wp_error( $new ) || ! self::changes( Price_Lists::get( $courier ), $new ) ) {
				continue;
			}

			// The price list's date, or its contents when the page has no date.
			$version = ! empty( $new['valid_from'] ) ? $new['valid_from'] : md5( wp_json_encode( $new ) );

			if ( ( $seen[ $courier ] ?? '' ) !== $version ) {
				$found[ $courier ] = $version;
			}
		}

		update_option( self::NOTICE_OPTION, $found, false );
	}
}
