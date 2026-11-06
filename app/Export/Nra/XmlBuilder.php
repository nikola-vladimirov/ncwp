<?php
namespace Nashenski\Export\Nra;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the NRA standardised audit file (Ordinance N-18, Annex 38, dex_audit.xsd)
 * on the site itself, for any number of orders.
 *
 * Input is the array Export::get_xml_file() collects:
 * - shop: eik, nap_number, domain, year, month
 * - orders[]: order_id, date_created, order_document_number, total_discount (without VAT),
 *   payment_method_type (1-6), pos_number, transaction_id, identifier,
 *   items[]: name, qty, total (line total with VAT, before discount), vat_rate, line_tax (optional, after discount)
 * - refunded_orders[]: order_id, total, date, return_method (1-4)
 *
 * Item amounts are before discount (art_price is "unit price without discount and without VAT");
 * the discount is reported once per order in ord_disc, and ord_vat / ord_total2 are after discount.
 */
class XmlBuilder {
	const ENCODING = 'windows-1251';

	/**
	 * @return array { content: string, totals: array }
	 */
	public static function build( array $args ) {
		$shop   = $args['shop'];
		$totals = array(
			'orders_total'          => 0,
			'orders_total_vat'      => 0,
			'returned_orders_total' => 0,
		);

		$xml = new \XMLWriter();
		$xml->openMemory();
		$xml->setIndent( true );
		$xml->startDocument( '1.0', self::ENCODING );
		$xml->startElement( 'audit' );
		$xml->writeElement( 'eik', (string) $shop['eik'] );
		$xml->writeElement( 'e_shop_n', (string) $shop['nap_number'] );
		$xml->writeElement( 'domain_name', (string) $shop['domain'] );
		$xml->writeElement( 'creation_date', wp_date( 'Y-m-d' ) );
		$xml->writeElement( 'mon', str_pad( (string) (int) $shop['month'], 2, '0', STR_PAD_LEFT ) );
		$xml->writeElement( 'god', (string) (int) $shop['year'] );
		$xml->writeElement( 'e_shop_type', '1' );

		$xml->startElement( 'order' );

		foreach ( $args['orders'] as $order ) {
			$order_totals                = self::write_order( $xml, $order );
			$totals['orders_total']     += $order_totals['total'];
			$totals['orders_total_vat'] += $order_totals['vat'];
		}

		$xml->endElement();

		$returned = $args['refunded_orders'] ?? array();

		foreach ( $returned as $refund ) {
			$totals['returned_orders_total'] += self::money( $refund['total'] );
		}

		$xml->writeElement( 'r_ord', (string) count( $returned ) );
		$xml->writeElement( 'r_total', self::format( $totals['returned_orders_total'] ) );

		if ( $returned ) {
			$xml->startElement( 'rorder' );

			foreach ( $returned as $refund ) {
				$xml->startElement( 'rorderenum' );
				$xml->writeElement( 'r_ord_n', (string) $refund['order_id'] );
				$xml->writeElement( 'r_amount', self::format( $refund['total'] ) );
				$xml->writeElement( 'r_date', (string) $refund['date'] );
				$xml->writeElement( 'r_paym', (string) (int) $refund['return_method'] );
				$xml->endElement();
			}

			$xml->endElement();
		}

		$xml->endElement();
		$xml->endDocument();

		return array(
			'content' => $xml->outputMemory( true ),
			'totals'  => array_map( array( __CLASS__, 'money' ), $totals ),
		);
	}

	/**
	 * @return array { total: float, vat: float } after discount.
	 */
	protected static function write_order( \XMLWriter $xml, array $order ) {
		$net = 0;
		$vat = 0;

		$xml->startElement( 'orderenum' );
		$xml->writeElement( 'ord_n', (string) $order['order_id'] );
		$xml->writeElement( 'ord_d', (string) $order['date_created'] );
		$xml->writeElement( 'doc_n', (string) $order['order_document_number'] );
		$xml->writeElement( 'doc_date', (string) $order['date_created'] );

		$xml->startElement( 'art' );

		foreach ( $order['items'] as $item ) {
			$line = self::item_amounts( $item );
			$net += $line['net'];
			$vat += $line['vat_after_discount'];

			$xml->startElement( 'artenum' );
			$xml->writeElement( 'art_name', html_entity_decode( (string) $item['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); // Product names are stored HTML-encoded; XMLWriter escapes again.
			$xml->writeElement( 'art_quant', self::format( $item['qty'] ) );
			$xml->writeElement( 'art_price', self::format( $line['unit_net'] ) );
			$xml->writeElement( 'art_vat_rate', (string) (int) $item['vat_rate'] );
			$xml->writeElement( 'art_vat', self::format( $line['vat'] ) );
			$xml->writeElement( 'art_sum', self::format( $line['gross'] ) );
			$xml->endElement();
		}

		$xml->endElement();

		$net      = self::money( $net );
		$vat      = self::money( $vat );
		$discount = self::money( $order['total_discount'] );
		$total    = self::money( $net - $discount + $vat );

		$xml->writeElement( 'ord_total1', self::format( $net ) );
		$xml->writeElement( 'ord_disc', self::format( $discount ) );
		$xml->writeElement( 'ord_vat', self::format( $vat ) );
		$xml->writeElement( 'ord_total2', self::format( $total ) );
		$xml->writeElement( 'paym', (string) (int) $order['payment_method_type'] );

		foreach ( array(
			'pos_number'     => 'pos_n',
			'transaction_id' => 'trans_n',
			'identifier'     => 'proc_id',
		) as $key => $tag ) {
			if ( ! empty( $order[ $key ] ) ) {
				$xml->writeElement( $tag, (string) $order[ $key ] );
			}
		}

		$xml->endElement();

		return array(
			'total' => $total,
			'vat'   => $vat,
		);
	}

	/**
	 * Split a line total with VAT into its parts.
	 */
	protected static function item_amounts( array $item ) {
		$qty   = (float) $item['qty'] ?: 1;
		$rate  = (float) $item['vat_rate'];
		$gross = self::money( $item['total'] );
		$net   = $rate ? self::money( $gross / ( 1 + $rate / 100 ) ) : $gross;
		$vat   = self::money( $gross - $net );

		return array(
			'gross'              => $gross,
			'net'                => $net,
			'unit_net'           => self::money( $net / $qty ),
			'vat'                => $vat,
			'vat_after_discount' => isset( $item['line_tax'] ) && '' !== $item['line_tax'] ? self::money( $item['line_tax'] ) : $vat,
		);
	}

	public static function money( $value ) {
		return round( (float) $value, 2 );
	}

	protected static function format( $value ) {
		return number_format( (float) $value, 2, '.', '' );
	}
}
