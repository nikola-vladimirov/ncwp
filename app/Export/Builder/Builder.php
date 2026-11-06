<?php
/**
 * Custom export builder.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.

namespace Nashenski\Export\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Custom export: which records, which columns, which file.
 *
 * A config is an array (saved exports keep it as is):
 * - source: orders (a row per order), lines (a row per product line) or documents (a row per
 *   order document, invoice, credit note or refund document);
 * - date_field (created, paid, completed), from, to (Y-m-d), statuses, payments, couriers,
 *   doc_types, company_only;
 * - columns: list of array( field, label );
 * - format (csv, xlsx, json, xml), separator, encoding (utf8, utf8bom, cp1251), header,
 *   decimal, date_format, filename, xml_root, xml_row.
 */
class Builder {
	const TEMPLATES_OPTION = 'nashenski_export_templates';

	/**
	 * The settings of a new export.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'name'         => '',
			'source'       => 'orders',
			'date_field'   => 'created',
			'from'         => gmdate( 'Y-m-01', strtotime( 'first day of last month' ) ),
			'to'           => gmdate( 'Y-m-t', strtotime( 'first day of last month' ) ),
			'statuses'     => array(),
			'payments'     => array(),
			'couriers'     => array(),
			'doc_types'    => array(),
			'company_only' => false,
			'columns'      => array(),
			'format'       => 'csv',
			'separator'    => ';',
			'encoding'     => 'utf8bom',
			'header'       => true,
			'decimal'      => ',',
			'date_format'  => 'd.m.Y',
			'filename'     => '{source}-{from}-{to}',
			'xml_root'     => 'export',
			'xml_row'      => 'row',
		);
	}

	/**
	 * Ready-made starting points shown in the builder.
	 *
	 * @return array
	 */
	public static function presets() {
		$col = function ( $ids ) {
			return array_map(
				function ( $id ) {
					$field = Fields::get( $id );

					return array(
						'field' => $id,
						'label' => $field ? $field['label'] : $id,
					);
				},
				$ids
			);
		};

		return array(
			'accounting' => array_merge(
				self::defaults(),
				array(
					'name'    => __( 'Sales for the accountant', 'nashenski-commerce' ),
					'source'  => 'documents',
					'columns' => $col( array( 'document_type', 'document_number', 'document_date', 'order_number', 'buyer', 'company_eik', 'company_vat', 'payment_method', 'total_without_vat', 'vat', 'total' ) ),
				)
			),
			'products'   => array_merge(
				self::defaults(),
				array(
					'name'     => __( 'Products sold', 'nashenski-commerce' ),
					'source'   => 'lines',
					'statuses' => array( 'wc-processing', 'wc-completed' ),
					'columns'  => $col( array( 'order_number', 'date_created', 'item_name', 'item_sku', 'item_quantity', 'item_unit_price', 'item_total' ) ),
				)
			),
			'shipments'  => array_merge(
				self::defaults(),
				array(
					'name'     => __( 'Shipments', 'nashenski-commerce' ),
					'source'   => 'orders',
					'statuses' => array( 'wc-processing', 'wc-completed' ),
					'columns'  => $col( array( 'order_number', 'date_created', 'shipping_full_name', 'shipping_phone', 'courier', 'delivery_type', 'shipping_city', 'shipping_address_1', 'waybill', 'payment_method', 'total' ) ),
				)
			),
		);
	}

	/**
	 * The saved exports.
	 *
	 * @return array
	 */
	public static function templates() {
		$saved = get_option( self::TEMPLATES_OPTION, array() );

		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Keep only known keys and safe values.
	 *
	 * @param mixed $raw Config as sent or saved.
	 * @return array
	 */
	public static function normalize( $raw ) {
		$raw    = is_array( $raw ) ? $raw : array();
		$config = self::defaults();
		$pick   = function ( $key, $allowed ) use ( $raw, &$config ) {
			if ( isset( $raw[ $key ] ) && in_array( $raw[ $key ], $allowed, true ) ) {
				$config[ $key ] = $raw[ $key ];
			}
		};

		$config['name'] = sanitize_text_field( (string) ( $raw['name'] ?? '' ) );
		$pick( 'source', Fields::SOURCES );
		$pick( 'date_field', array( 'created', 'paid', 'completed' ) );
		$pick( 'format', array( 'csv', 'xlsx', 'json', 'xml' ) );
		$pick( 'separator', array( ';', ',', 'tab', '|' ) );
		$pick( 'encoding', array( 'utf8', 'utf8bom', 'cp1251' ) );
		$pick( 'decimal', array( ',', '.' ) );

		foreach ( array( 'from', 'to' ) as $key ) {
			if ( isset( $raw[ $key ] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $raw[ $key ] ) ) {
				$config[ $key ] = $raw[ $key ];
			}
		}

		$statuses               = array_keys( wc_get_order_statuses() );
		$config['statuses']     = array_values( array_intersect( (array) ( $raw['statuses'] ?? array() ), $statuses ) );
		$config['payments']     = array_values( array_filter( array_map( 'sanitize_key', (array) ( $raw['payments'] ?? array() ) ) ) );
		$config['couriers']     = array_values( array_intersect( (array) ( $raw['couriers'] ?? array() ), array_merge( \Nashenski\Plugin::COURIERS, array( 'other' ) ) ) );
		$config['doc_types']    = array_values( array_intersect( (array) ( $raw['doc_types'] ?? array() ), array_keys( Fields::document_types() ) ) );
		$config['company_only'] = ! empty( $raw['company_only'] );
		$config['header']       = ! isset( $raw['header'] ) || ! empty( $raw['header'] );
		$config['date_format']  = preg_match( '/^[dDjlNSwzWFmMntLoYyaABgGhHisuveIOPTZcrU .\/:\-]{1,20}$/', (string) ( $raw['date_format'] ?? '' ) ) ? $raw['date_format'] : $config['date_format'];

		$filename           = preg_replace( '/[^a-zA-Z0-9_\-{}.]/', '-', (string) ( $raw['filename'] ?? $config['filename'] ) );
		$config['filename'] = $filename ? $filename : $config['filename'];

		foreach ( array( 'xml_root', 'xml_row' ) as $key ) {
			$name           = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) ( $raw[ $key ] ?? '' ) );
			$config[ $key ] = $name && ! preg_match( '/^[0-9\-]/', $name ) ? $name : $config[ $key ];
		}

		$config['columns'] = array();

		foreach ( (array) ( $raw['columns'] ?? array() ) as $column ) {
			$field = is_array( $column ) ? (string) ( $column['field'] ?? '' ) : '';

			if ( 0 === strpos( $field, 'meta:' ) ) {
				$field = 'meta:' . preg_replace( '/[^a-zA-Z0-9_\-]/', '', substr( $field, 5 ) );
			}

			$definition = Fields::get( $field );

			if ( ! $definition || 'meta:' === $field || ! in_array( $config['source'], $definition['sources'], true ) ) {
				continue;
			}

			$label               = sanitize_text_field( (string) ( $column['label'] ?? '' ) );
			$config['columns'][] = array(
				'field' => $field,
				'label' => '' !== $label ? $label : $definition['label'],
			);
		}

		return $config;
	}

	/**
	 * IDs of the orders (and refunds, for documents) in the period and filters.
	 *
	 * @param array $config Export config.
	 * @return array
	 */
	private static function query_ids( $config ) {
		$from = strtotime( $config['from'] . ' 00:00:00 ' . wp_timezone_string() );
		$to   = strtotime( $config['to'] . ' 23:59:59 ' . wp_timezone_string() );
		$args = array(
			'limit'   => -1,
			'return'  => 'ids',
			'orderby' => 'date',
			'order'   => 'ASC',
			'type'    => 'documents' === $config['source'] ? array( 'shop_order', 'shop_order_refund' ) : 'shop_order',
		);

		// Documents are filtered on their own date in rows(); a document is never dated before its order.
		if ( 'documents' === $config['source'] ) {
			$args['date_created'] = '<=' . $to;
		} else {
			$args[ 'date_' . $config['date_field'] ] = $from . '...' . $to;
		}

		if ( $config['statuses'] && 'documents' !== $config['source'] ) {
			$args['status'] = $config['statuses'];
		}

		if ( $config['payments'] && 'documents' !== $config['source'] ) {
			$args['payment_method'] = $config['payments'];
		}

		return wc_get_orders( $args );
	}

	/**
	 * Tells whether an order matches the filters of the config.
	 *
	 * @param \WC_Order $order  Order.
	 * @param array     $config Export config.
	 * @return bool
	 */
	private static function order_passes( $order, $config ) {
		if ( $config['company_only'] && '1' !== (string) $order->get_meta( '_billing_to_company' ) ) {
			return false;
		}

		if ( $config['statuses'] && ! in_array( 'wc-' . $order->get_status(), $config['statuses'], true ) ) {
			return false;
		}

		if ( $config['payments'] && ! in_array( $order->get_payment_method(), $config['payments'], true ) ) {
			return false;
		}

		if ( $config['couriers'] ) {
			$courier = Fields::courier( $order );

			if ( ! in_array( $courier ? $courier : 'other', $config['couriers'], true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Rows as arrays of raw values, in column order. $limit stops early (for the preview).
	 *
	 * @param array $config Export config.
	 * @param int   $limit  Most rows to keep, 0 for all.
	 * @return array array( 'rows' => array, 'total' => int (rows before the limit, when known) )
	 */
	public static function rows( $config, $limit = 0 ) {
		$rows  = array();
		$count = 0;
		$from  = strtotime( $config['from'] . ' 00:00:00 ' . wp_timezone_string() );
		$to    = strtotime( $config['to'] . ' 23:59:59 ' . wp_timezone_string() );

		foreach ( self::query_ids( $config ) as $index => $id ) {
			$object = wc_get_order( $id );

			if ( ! $object ) {
				continue;
			}

			$order = $object instanceof \WC_Order_Refund ? wc_get_order( $object->get_parent_id() ) : $object;

			if ( ! $order || ! self::order_passes( $order, $config ) ) {
				continue;
			}

			$records = array();

			if ( 'lines' === $config['source'] ) {
				foreach ( $order->get_items() as $item ) {
					$records[] = array(
						'order'  => $order,
						'object' => $order,
						'item'   => $item,
						'doc'    => null,
					);
				}
			} elseif ( 'documents' === $config['source'] ) {
				foreach ( Fields::documents( $object ) as $doc ) {
					$doc_time = $doc[2] instanceof \WC_DateTime ? $doc[2]->getTimestamp() : strtotime( (string) $doc[2] );

					if ( $doc_time && ( $doc_time < $from || $doc_time > $to ) ) {
						continue;
					}

					if ( ! $config['doc_types'] || in_array( $doc[0], $config['doc_types'], true ) ) {
						$records[] = array(
							'order'  => $order,
							'object' => $object,
							'item'   => null,
							'doc'    => $doc,
						);
					}
				}
			} else {
				$records[] = array(
					'order'  => $order,
					'object' => $order,
					'item'   => null,
					'doc'    => null,
				);
			}

			foreach ( $records as $record ) {
				++$count;

				if ( $limit && count( $rows ) >= $limit ) {
					continue;
				}

				$row = array();

				foreach ( $config['columns'] as $column ) {
					$row[] = Fields::value( $column['field'], $record );
				}

				$rows[] = $row;
			}

			if ( 0 === ( ( $index + 1 ) % 100 ) ) {
				wp_cache_flush_runtime();
			}
		}

		return array(
			'rows'  => $rows,
			'total' => $count,
		);
	}

	/**
	 * A raw value as text for the file: dates in the chosen format, amounts with two decimals and
	 * the chosen decimal mark.
	 *
	 * @param mixed  $value  Raw value.
	 * @param string $kind   Kind of the field: date, money, number or text.
	 * @param array  $config Export config.
	 * @return string
	 */
	public static function format( $value, $kind, $config ) {
		if ( $value instanceof \WC_DateTime ) {
			return $value->date_i18n( $config['date_format'] );
		}

		if ( 'date' === $kind && is_string( $value ) && '' !== $value ) {
			$time = strtotime( $value );

			return $time ? wp_date( $config['date_format'], $time ) : $value;
		}

		if ( 'money' === $kind && is_numeric( $value ) ) {
			return number_format( (float) $value, 2, $config['decimal'], '' );
		}

		if ( 'number' === $kind && is_numeric( $value ) ) {
			return str_replace( '.', $config['decimal'], (string) ( 0 + $value ) );
		}

		return (string) $value;
	}

	/**
	 * The file name of an export, with its extension.
	 *
	 * @param array $config Export config.
	 * @return string
	 */
	public static function filename( $config ) {
		$name      = strtr(
			$config['filename'],
			array(
				'{from}'   => $config['from'],
				'{to}'     => $config['to'],
				'{source}' => $config['source'],
				'{today}'  => wp_date( 'Y-m-d' ),
			)
		);
		$name      = sanitize_file_name( $name );
		$name      = $name ? $name : 'export';
		$extension = array(
			'csv'  => 'csv',
			'xlsx' => 'xlsx',
			'json' => 'json',
			'xml'  => 'xml',
		);

		return preg_replace( '/\.(csv|xlsx|json|xml)$/i', '', $name ) . '.' . $extension[ $config['format'] ];
	}
}
