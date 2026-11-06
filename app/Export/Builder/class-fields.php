<?php
/**
 * Columns of the custom export.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.NamingConventions.ValidHookName -- the plugin's public hooks use "nashenski/area/name".

namespace Nashenski\Export\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * The columns a custom export can have, and how each value is read.
 *
 * A row is array( 'order' => WC_Order, 'object' => WC_Order|WC_Order_Refund (the document's own
 * record), 'item' => WC_Order_Item_Product|null, 'doc' => array( type, number, date )|null ).
 */
class Fields {
	const SOURCES = array( 'orders', 'lines', 'documents' );

	/**
	 * Field ID => array( label, group, kind (text, money, number, date), sources ).
	 *
	 * @return array
	 */
	public static function all() {
		$all_sources = self::SOURCES;
		$fields      = array();

		$add = function ( $group, $labels, $sources = null, $kind = 'text' ) use ( &$fields, $all_sources ) {
			foreach ( $labels as $id => $label ) {
				$fields[ $id ]          = array(
					'label'   => $label,
					'group'   => $group,
					'kind'    => is_array( $label ) ? $label[1] : $kind,
					'sources' => $sources ? $sources : $all_sources,
				);
				$fields[ $id ]['label'] = is_array( $label ) ? $label[0] : $label;
			}
		};

		$add(
			__( 'Order', 'nashenski-commerce' ),
			array(
				'order_id'       => __( 'Order ID', 'nashenski-commerce' ),
				'order_number'   => __( 'Order number', 'nashenski-commerce' ),
				'status'         => __( 'Status', 'nashenski-commerce' ),
				'date_created'   => array( __( 'Order date', 'nashenski-commerce' ), 'date' ),
				'date_paid'      => array( __( 'Paid on', 'nashenski-commerce' ), 'date' ),
				'date_completed' => array( __( 'Completed on', 'nashenski-commerce' ), 'date' ),
				'currency'       => __( 'Currency', 'nashenski-commerce' ),
				'customer_note'  => __( 'Customer note', 'nashenski-commerce' ),
				'created_via'    => __( 'Created via', 'nashenski-commerce' ),
			)
		);

		$add(
			__( 'Customer', 'nashenski-commerce' ),
			array(
				'billing_full_name'  => __( 'Customer name', 'nashenski-commerce' ),
				'billing_first_name' => __( 'First name', 'nashenski-commerce' ),
				'billing_last_name'  => __( 'Last name', 'nashenski-commerce' ),
				'billing_email'      => __( 'Email', 'nashenski-commerce' ),
				'billing_phone'      => __( 'Phone', 'nashenski-commerce' ),
				'customer_id'        => __( 'Customer account ID', 'nashenski-commerce' ),
				'billing_address_1'  => __( 'Billing street', 'nashenski-commerce' ),
				'billing_address_2'  => __( 'Billing address line 2', 'nashenski-commerce' ),
				'billing_city'       => __( 'Billing town', 'nashenski-commerce' ),
				'billing_state'      => __( 'Billing region', 'nashenski-commerce' ),
				'billing_postcode'   => __( 'Billing postcode', 'nashenski-commerce' ),
				'billing_country'    => __( 'Billing country', 'nashenski-commerce' ),
			)
		);

		$add(
			__( 'Company invoice', 'nashenski-commerce' ),
			array(
				'to_company'      => __( 'Company invoice (yes/no)', 'nashenski-commerce' ),
				'company'         => __( 'Company name', 'nashenski-commerce' ),
				'company_eik'     => __( 'EIK', 'nashenski-commerce' ),
				'company_vat'     => __( 'VAT number', 'nashenski-commerce' ),
				'company_mol'     => __( 'MOL', 'nashenski-commerce' ),
				'company_town'    => __( 'Company town', 'nashenski-commerce' ),
				'company_address' => __( 'Company address', 'nashenski-commerce' ),
				'buyer'           => __( 'Buyer (company or person)', 'nashenski-commerce' ),
			)
		);

		$add(
			__( 'Delivery', 'nashenski-commerce' ),
			array(
				'shipping_full_name' => __( 'Recipient', 'nashenski-commerce' ),
				'shipping_phone'     => __( 'Recipient phone', 'nashenski-commerce' ),
				'shipping_address_1' => __( 'Delivery street or office', 'nashenski-commerce' ),
				'shipping_address_2' => __( 'Delivery address line 2', 'nashenski-commerce' ),
				'shipping_city'      => __( 'Delivery town', 'nashenski-commerce' ),
				'shipping_state'     => __( 'Delivery region', 'nashenski-commerce' ),
				'shipping_postcode'  => __( 'Delivery postcode', 'nashenski-commerce' ),
				'shipping_country'   => __( 'Delivery country', 'nashenski-commerce' ),
				'shipping_method'    => __( 'Shipping method', 'nashenski-commerce' ),
				'courier'            => __( 'Courier', 'nashenski-commerce' ),
				'delivery_type'      => __( 'Delivery type', 'nashenski-commerce' ),
				'waybill'            => __( 'Waybill number', 'nashenski-commerce' ),
			)
		);

		$add(
			__( 'Payment', 'nashenski-commerce' ),
			array(
				'payment_method'    => __( 'Payment method', 'nashenski-commerce' ),
				'payment_method_id' => __( 'Payment method ID', 'nashenski-commerce' ),
				'transaction_id'    => __( 'Transaction ID', 'nashenski-commerce' ),
				'is_paid'           => __( 'Paid (yes/no)', 'nashenski-commerce' ),
			)
		);

		$add(
			__( 'Amounts', 'nashenski-commerce' ),
			array(
				'total'             => array( __( 'Total', 'nashenski-commerce' ), 'money' ),
				'total_without_vat' => array( __( 'Total without VAT', 'nashenski-commerce' ), 'money' ),
				'vat'               => array( __( 'VAT', 'nashenski-commerce' ), 'money' ),
				'subtotal'          => array( __( 'Products subtotal', 'nashenski-commerce' ), 'money' ),
				'discount'          => array( __( 'Discount', 'nashenski-commerce' ), 'money' ),
				'shipping_total'    => array( __( 'Shipping', 'nashenski-commerce' ), 'money' ),
				'fees'              => array( __( 'Fees', 'nashenski-commerce' ), 'money' ),
				'refunded'          => array( __( 'Refunded', 'nashenski-commerce' ), 'money' ),
				'items_count'       => array( __( 'Number of items', 'nashenski-commerce' ), 'number' ),
				'products'          => __( 'Products (names)', 'nashenski-commerce' ),
			),
			array( 'orders', 'documents' )
		);

		$add(
			__( 'Documents', 'nashenski-commerce' ),
			array(
				'document_type'   => __( 'Document type', 'nashenski-commerce' ),
				'document_number' => __( 'Document number', 'nashenski-commerce' ),
				'document_date'   => array( __( 'Document date', 'nashenski-commerce' ), 'date' ),
			),
			array( 'documents' )
		);

		$add(
			__( 'Documents', 'nashenski-commerce' ),
			array(
				'invoice_number' => __( 'Invoice number', 'nashenski-commerce' ),
				'invoice_date'   => array( __( 'Invoice date', 'nashenski-commerce' ), 'date' ),
			)
		);

		$add(
			__( 'Product line', 'nashenski-commerce' ),
			array(
				'item_name'         => __( 'Product', 'nashenski-commerce' ),
				'item_sku'          => __( 'SKU', 'nashenski-commerce' ),
				'item_product_id'   => __( 'Product ID', 'nashenski-commerce' ),
				'item_variation_id' => __( 'Variation ID', 'nashenski-commerce' ),
				'item_quantity'     => array( __( 'Quantity', 'nashenski-commerce' ), 'number' ),
				'item_unit_price'   => array( __( 'Unit price', 'nashenski-commerce' ), 'money' ),
				'item_subtotal'     => array( __( 'Line subtotal', 'nashenski-commerce' ), 'money' ),
				'item_total'        => array( __( 'Line total', 'nashenski-commerce' ), 'money' ),
				'item_tax'          => array( __( 'Line VAT', 'nashenski-commerce' ), 'money' ),
				'item_tax_rate'     => array( __( 'VAT rate %', 'nashenski-commerce' ), 'number' ),
			),
			array( 'lines' )
		);

		return apply_filters( 'nashenski/export/builder/fields', $fields );
	}

	/**
	 * Label and kind of a column ID, including "meta:" columns (order meta of the store's choice).
	 *
	 * @param string $id Column ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();

		if ( isset( $all[ $id ] ) ) {
			return $all[ $id ];
		}

		if ( 0 === strpos( $id, 'meta:' ) ) {
			return array(
				'label'   => substr( $id, 5 ),
				'group'   => __( 'Order meta', 'nashenski-commerce' ),
				'kind'    => 'text',
				'sources' => self::SOURCES,
			);
		}

		return null;
	}

	/**
	 * Kind of each column of a config (text, money, number or date), in column order.
	 *
	 * @param array $config Export config.
	 * @return array
	 */
	public static function kinds( $config ) {
		return array_map(
			function ( $column ) {
				$field = self::get( $column['field'] );

				return $field ? $field['kind'] : 'text';
			},
			$config['columns']
		);
	}

	/**
	 * Name of a state, or its code when there is no name.
	 *
	 * @param string $country Country code.
	 * @param string $state   State code.
	 * @return string
	 */
	private static function region( $country, $state ) {
		$states = WC()->countries->get_states( $country );
		$states = $states ? $states : array();

		return $states[ $state ] ?? $state;
	}

	/**
	 * Name of a country, or its code when there is no name.
	 *
	 * @param string $code Country code.
	 * @return string
	 */
	private static function country( $code ) {
		$countries = WC()->countries->get_countries();

		return $countries[ $code ] ?? $code;
	}

	/**
	 * The first shipping line of an order.
	 *
	 * @param \WC_Order $order Order.
	 * @return \WC_Order_Item_Shipping|null
	 */
	private static function shipping_item( $order ) {
		$items = $order->get_items( 'shipping' );

		return $items ? reset( $items ) : null;
	}

	/**
	 * The courier of an order, empty when none.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	public static function courier( $order ) {
		$item = self::shipping_item( $order );

		return $item ? \Nashenski\Front_End\Checkout\Pickers::courier_of( $item->get_method_id() ) : '';
	}

	/**
	 * Waybill number(s), as the orders list shows them.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	public static function waybill( $order ) {
		$label = nashenski_get_order_label( $order->get_id() );

		return $label['number'] ?? implode( ', ', wp_list_pluck( $label['items'] ?? array(), 'number' ) );
	}

	/**
	 * Number of a document of an order or refund.
	 *
	 * @param \WC_Order|\WC_Order_Refund $record Order or refund.
	 * @param string                     $kind   Document kind.
	 * @return mixed
	 */
	private static function document_number( $record, $kind ) {
		if ( 'refund_document' === $kind && $record->get_meta( 'nashenski_refunded_order_number' ) ) {
			return $record->get_meta( 'nashenski_refunded_order_number' );
		}

		if ( in_array( $kind, array( 'invoice', 'credit_note' ), true ) && $record->get_meta( 'nashenski_order_invoice_number' ) ) {
			return $record->get_meta( 'nashenski_order_invoice_number' );
		}

		return $record->get_meta( 'nashenski_order_number' );
	}

	/**
	 * Documents of an order or refund: array of array( type, number, date ).
	 *
	 * @param \WC_Order|\WC_Order_Refund $record Order or refund.
	 * @return array
	 */
	public static function documents( $record ) {
		$docs    = array();
		$created = $record->get_date_created();

		if ( $record instanceof \WC_Order_Refund ) {
			if ( $record->get_meta( 'nashenski_refunded_invoice_document' ) ) {
				$date   = $record->get_meta( 'nashenski_credit_notice_document_date' );
				$docs[] = array( 'credit_note', self::document_number( $record, 'credit_note' ), $date ? $date : $created );
			}

			if ( $record->get_meta( 'nashenski_refunded_order_document' ) ) {
				$docs[] = array( 'refund_document', self::document_number( $record, 'refund_document' ), $created );
			}

			return $docs;
		}

		// An order document whose PDF the cleanup deleted keeps its number.
		if ( $record->get_meta( 'nashenski_order_document' ) || $record->get_meta( \Nashenski\Document_Storage::RECEIPT_DELETED_META ) ) {
			$docs[] = array( 'order_document', self::document_number( $record, 'order_document' ), $created );
		}

		if ( $record->get_meta( 'nashenski_invoice_document' ) ) {
			$date   = $record->get_meta( 'nashenski_invoice_document_date' );
			$docs[] = array( 'invoice', self::document_number( $record, 'invoice' ), $date ? $date : $created );
		}

		return $docs;
	}

	/**
	 * Names of the document types.
	 *
	 * @return array
	 */
	public static function document_types() {
		return array(
			'order_document'  => __( 'Order document', 'nashenski-commerce' ),
			'invoice'         => __( 'Invoice', 'nashenski-commerce' ),
			'credit_note'     => __( 'Credit note', 'nashenski-commerce' ),
			'refund_document' => __( 'Refund document', 'nashenski-commerce' ),
		);
	}

	/**
	 * The raw value of a column for a row: a string, a number, a date (WC_DateTime or string) or ''.
	 *
	 * @param string $id  Column ID.
	 * @param array  $row Row: order, object, item and doc.
	 * @return mixed
	 */
	public static function value( $id, $row ) {
		$order      = $row['order'];
		$object     = $row['object'] ?? $order;
		$item       = $row['item'] ?? null;
		$doc        = $row['doc'] ?? null;
		$is_company = '1' === (string) $order->get_meta( '_billing_to_company' );

		if ( 0 === strpos( $id, 'meta:' ) ) {
			$value = $order->get_meta( substr( $id, 5 ) );

			return is_scalar( $value ) ? $value : wp_json_encode( $value );
		}

		switch ( $id ) {
			case 'order_id':
				return $order->get_id();
			case 'order_number':
				return $order->get_order_number();
			case 'status':
				return wc_get_order_status_name( $order->get_status() );
			case 'date_created':
				return $order->get_date_created();
			case 'date_paid':
				return $order->get_date_paid();
			case 'date_completed':
				return $order->get_date_completed();
			case 'currency':
				return $order->get_currency();
			case 'customer_note':
				return $order->get_customer_note();
			case 'created_via':
				return $order->get_created_via();
			case 'billing_full_name':
				return trim( $order->get_formatted_billing_full_name() );
			case 'shipping_full_name':
				$name = trim( $order->get_formatted_shipping_full_name() );
				return $name ? $name : trim( $order->get_formatted_billing_full_name() );
			case 'billing_state':
				return self::region( $order->get_billing_country(), $order->get_billing_state() );
			case 'shipping_state':
				return self::region( $order->get_shipping_country(), $order->get_shipping_state() );
			case 'billing_country':
				return self::country( $order->get_billing_country() );
			case 'shipping_country':
				return self::country( $order->get_shipping_country() );
			case 'shipping_phone':
				$phone = $order->get_shipping_phone();
				return $phone ? $phone : $order->get_billing_phone();
			case 'customer_id':
				$customer_id = $order->get_customer_id();
				return $customer_id ? $customer_id : '';
			case 'to_company':
				return $is_company ? __( 'yes', 'nashenski-commerce' ) : __( 'no', 'nashenski-commerce' );
			case 'company':
				return $order->get_billing_company();
			case 'company_eik':
				return $order->get_meta( '_billing_company_eik' );
			case 'company_vat':
				return $order->get_meta( '_billing_vat_number' );
			case 'company_mol':
				return $order->get_meta( '_billing_company_mol' );
			case 'company_town':
				return $order->get_meta( '_billing_company_settlement' );
			case 'company_address':
				return $order->get_meta( '_billing_company_address' );
			case 'buyer':
				return $is_company && $order->get_billing_company() ? $order->get_billing_company() : trim( $order->get_formatted_billing_full_name() );
			case 'shipping_method':
				return $order->get_shipping_method();
			case 'courier':
				return ucfirst( self::courier( $order ) );
			case 'delivery_type':
				$shipping = self::shipping_item( $order );
				$type     = $shipping ? (string) $shipping->get_meta( 'delivery_type' ) : '';
				$types    = array(
					'office'  => __( 'Office', 'nashenski-commerce' ),
					'automat' => __( 'Parcel locker', 'nashenski-commerce' ),
					'locker'  => __( 'Parcel locker', 'nashenski-commerce' ),
					'address' => __( 'Address', 'nashenski-commerce' ),
				);
				return 'boxnow' === self::courier( $order ) ? $types['locker'] : ( $types[ $type ] ?? $type );
			case 'waybill':
				return self::waybill( $order );
			case 'payment_method':
				return $order->get_payment_method_title();
			case 'payment_method_id':
				return $order->get_payment_method();
			case 'transaction_id':
				return $order->get_transaction_id();
			case 'is_paid':
				return $order->is_paid() ? __( 'yes', 'nashenski-commerce' ) : __( 'no', 'nashenski-commerce' );
			case 'total':
				return (float) $object->get_total();
			case 'vat':
				return (float) $object->get_total_tax();
			case 'total_without_vat':
				return (float) $object->get_total() - (float) $object->get_total_tax();
			case 'subtotal':
				return (float) $object->get_subtotal();
			case 'discount':
				return (float) $object->get_total_discount();
			case 'shipping_total':
				return (float) $object->get_shipping_total() + (float) $object->get_shipping_tax();
			case 'fees':
				$fees = 0;
				foreach ( $object->get_fees() as $fee ) {
					$fees += (float) $fee->get_total() + (float) $fee->get_total_tax();
				}
				return $fees;
			case 'refunded':
				return $object instanceof \WC_Order_Refund ? 0 : (float) $order->get_total_refunded();
			case 'items_count':
				return $object->get_item_count();
			case 'products':
				return implode(
					', ',
					array_map(
						function ( $line ) {
							return $line->get_name() . ' x ' . $line->get_quantity();
						},
						array_values( $object->get_items() )
					)
				);
			case 'document_type':
				$types = self::document_types();
				return $doc ? ( $types[ $doc[0] ] ?? $doc[0] ) : '';
			case 'document_number':
				return $doc ? $doc[1] : '';
			case 'document_date':
				return $doc ? $doc[2] : '';
			case 'invoice_number':
				return $order->get_meta( 'nashenski_invoice_document' ) ? self::document_number( $order, 'invoice' ) : '';
			case 'invoice_date':
				if ( ! $order->get_meta( 'nashenski_invoice_document' ) ) {
					return '';
				}
				$date = $order->get_meta( 'nashenski_invoice_document_date' );
				return $date ? $date : $order->get_date_created();
		}

		// Plain address fields: billing_city, shipping_address_1 and so on. Delivery fields fall back
		// to billing when the order ships to the billing address.
		if ( preg_match( '/^(billing|shipping)_[a-z0-9_]+$/', $id ) && is_callable( array( $order, 'get_' . $id ) ) ) {
			$value = $order->{'get_' . $id}();

			if ( '' === (string) $value && 0 === strpos( $id, 'shipping_' ) ) {
				$billing = 'get_billing_' . substr( $id, 9 );
				$value   = is_callable( array( $order, $billing ) ) ? $order->$billing() : '';
			}

			return $value;
		}

		if ( $item && 0 === strpos( $id, 'item_' ) ) {
			$product  = $item->get_product();
			$quantity = max( 1, (float) $item->get_quantity() );

			switch ( $id ) {
				case 'item_name':
					return $item->get_name();
				case 'item_sku':
					return $product ? $product->get_sku() : '';
				case 'item_product_id':
					return $item->get_product_id();
				case 'item_variation_id':
					$variation_id = $item->get_variation_id();
					return $variation_id ? $variation_id : '';
				case 'item_quantity':
					return (float) $item->get_quantity();
				case 'item_unit_price':
					return ( (float) $item->get_total() + (float) $item->get_total_tax() ) / $quantity;
				case 'item_subtotal':
					return (float) $item->get_subtotal() + (float) $item->get_subtotal_tax();
				case 'item_total':
					return (float) $item->get_total() + (float) $item->get_total_tax();
				case 'item_tax':
					return (float) $item->get_total_tax();
				case 'item_tax_rate':
					return (float) $item->get_total() > 0 ? round( (float) $item->get_total_tax() / (float) $item->get_total() * 100, 2 ) : 0;
			}
		}

		return apply_filters( 'nashenski/export/builder/value', '', $id, $row );
	}
}
