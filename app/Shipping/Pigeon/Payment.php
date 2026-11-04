<?php
namespace Nashenski\Shipping\Pigeon;

defined( 'ABSPATH' ) || exit;

/** Keep guest address state separate from authenticated monetary instructions. */
class Payment {
	const COD_OVERRIDE_META = '_nashenski_pigeon_cod_override';

	public static function cookie( $data ) {
		if ( ! is_array( $data ) ) { return array(); }
		$keys = array( 'type', 'receiver', 'phone', 'state', 'city', 'postcode', 'country', 'payment', 'selectedLabel', 'selectedOffice', 'selectedLocker', 'selectedAddress', 'streetNumber', 'other', 'otherField', 'blockNumber', 'entranceNumber', 'floorNumber', 'apartmentNumber', 'billing_to_company', 'billing_company_mol', 'billing_company', 'billing_vat_number' );
		$data = array_intersect_key( $data, array_flip( $keys ) );
		if ( isset( $data['selectedAddress'] ) && is_array( $data['selectedAddress'] ) ) {
			$data['selectedAddress'] = array_intersect_key( $data['selectedAddress'], array_flip( array( 'id', 'orig_key', 'label', 'type', 'name' ) ) );
		}
		return $data;
	}

	public static function amount( $value ) {
		if ( ! ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) || ! is_numeric( $value ) ) {
			throw new \InvalidArgumentException( __( 'Enter a finite, nonnegative shipping or COD amount.', 'nashenski-commerce' ) );
		}
		$number = (float) $value;
		$minor = $number * pow( 10, wc_get_price_decimals() );
		if ( ! is_finite( $number ) || $number < 0 || ! is_finite( $minor ) || $minor > min( PHP_INT_MAX, 9007199254740991 ) ) {
			throw new \InvalidArgumentException( __( 'Enter a finite, nonnegative shipping or COD amount.', 'nashenski-commerce' ) );
		}
		return wc_format_decimal( $number, wc_get_price_decimals() );
	}

	public static function shipping_item( $order ) {
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( $item->get_method_id() === Method::METHOD_ID ) { return $item; }
		}
		return null;
	}

	public static function shipping_total( $order ) {
		$total = 0;
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( $item->get_method_id() === Method::METHOD_ID ) {
				$total += (float) self::amount( $item->get_total() ) + (float) self::amount( $item->get_total_tax() );
			}
		}
		return self::amount( $total );
	}

	public static function cod( $order, $payer, $override = null ) {
		if ( $order->get_payment_method() !== 'cod' ) { return null; }
		if ( $override !== null ) { return self::amount( $override ); }
		if ( $order->meta_exists( self::COD_OVERRIDE_META ) ) { return self::amount( $order->get_meta( self::COD_OVERRIDE_META, true, 'edit' ) ); }
		$total = 0;
		$types = apply_filters( 'nashenski/shipping/package_total_includes_fees', true ) ? array( 'line_item', 'fee' ) : array( 'line_item' );
		foreach ( $order->get_items( $types ) as $item ) {
			$total += (float) $item->get_total() + (float) $item->get_total_tax();
		}
		if ( $payer === 'sender' ) { $total += (float) self::shipping_total( $order ); }
		return self::amount( $total );
	}

	/** $staff: the payer was chosen by staff on the order screen; checkout labels of prepaid orders are always paid by the sender. */
	public static function label( $label, $order, $override = null, $staff = false ) {
		$payer = $staff || $order->get_payment_method() === 'cod' ? ( $label['who_pays'] ?? 'receiver' ) : 'sender';
		if ( ! in_array( $payer, array( 'sender', 'receiver' ), true ) ) {
			throw new \InvalidArgumentException( __( 'Choose a valid shipping payer.', 'nashenski-commerce' ) );
		}
		$label['who_pays'] = $payer;
		$cod = self::cod( $order, $payer, $override );
		if ( $cod === null ) { unset( $label['service_codes']['cod_amount'] ); }
		else { $label['service_codes']['cod_amount'] = $cod; }
		return $label;
	}

	public static function defaults( $order, $label ) {
		$label = self::label( is_array( $label ) ? $label : array(), $order );
		$item = self::shipping_item( $order );
		$price = $item ? self::amount( $item->get_total() ) : '0';
		return array( 'label' => $label, 'fixedPrice' => $price, 'fixedPriceEnabled' => $order->get_payment_method() === 'cod' && $label['who_pays'] === 'sender' && (float) $price > 0 );
	}

	/** Preview WooCommerce's recalculation on cloned items, before committing a price. */
	private static function validate_fixed_total( $order, $price, $override ) {
		$location = $order->get_taxable_location();
		$tax_class = get_option( 'woocommerce_shipping_tax_class' );
		if ( $tax_class === 'inherit' ) {
			$classes = array_intersect( array_merge( array( '' ), \WC_Tax::get_tax_class_slugs() ), $order->get_items_tax_classes() );
			$tax_class = $classes ? current( $classes ) : ( count( $order->get_items() ) === 0 ? '' : false );
		}
		$exempt = apply_filters( 'woocommerce_order_is_vat_exempt', 'yes' === $order->get_meta( 'is_vat_exempt' ), $order );
		$first = self::shipping_item( $order );
		$shipping = 0;
		foreach ( $order->get_items( 'shipping' ) as $original ) {
			if ( $original->get_method_id() !== Method::METHOD_ID ) { continue; }
			$item = clone $original;
			if ( $original->get_id() === $first->get_id() ) { $item->set_total( $price ); }
			if ( $tax_class !== false && ! $exempt ) { $item->calculate_taxes( array_merge( $location, array( 'tax_class' => $tax_class ) ) ); }
			else { $item->set_taxes( false ); }
			$shipping += (float) $item->get_total() + (float) $item->get_total_tax();
		}
		self::amount( $shipping );
		if ( $order->get_payment_method() !== 'cod' || $override !== null || $order->meta_exists( self::COD_OVERRIDE_META ) ) { return; }
		$types = apply_filters( 'nashenski/shipping/package_total_includes_fees', true ) ? array( 'line_item', 'fee' ) : array( 'line_item' );
		$total = $shipping;
		foreach ( $order->get_items( $types ) as $original ) {
			$item = clone $original;
			if ( ! $exempt ) { $item->calculate_taxes( $location ); }
			else { $item->set_taxes( false ); }
			$total += (float) $item->get_total() + (float) $item->get_total_tax();
		}
		self::amount( $total );
	}

	/** Validate every monetary instruction before any order/item write. */
	public static function request( $order ) {
		if ( (string) nashenski_label_request( 'paymentVersion', null ) !== '1' ) {
			throw new \InvalidArgumentException( __( 'Reload this order before generating a Pigeon label.', 'nashenski-commerce' ) );
		}
		$payer = nashenski_label_request( 'paymentBy', array() );
		$mode = $payer['id'] ?? '';
		if ( ! in_array( $mode, array( 'fixed', 'sender', 'receiver' ), true ) ) {
			throw new \InvalidArgumentException( __( 'Choose a valid shipping payer.', 'nashenski-commerce' ) );
		}
		$price = null;
		if ( $mode === 'fixed' ) {
			if ( isset( $_REQUEST['fixedPrice'] ) && ( is_bool( $_REQUEST['fixedPrice'] ) || is_array( $_REQUEST['fixedPrice'] ) || is_object( $_REQUEST['fixedPrice'] ) ) ) {
				throw new \InvalidArgumentException( __( 'Enter a finite, nonnegative shipping or COD amount.', 'nashenski-commerce' ) );
			}
			$price = self::amount( nashenski_label_request( 'fixedPrice', null ) );
			if ( ! self::shipping_item( $order ) ) { throw new \InvalidArgumentException( __( 'This order has no Pigeon shipping charge.', 'nashenski-commerce' ) ); }
		}
		$override = nashenski_label_request( 'codOverride', null );
		if ( $override !== null ) {
			if ( isset( $_REQUEST['codOverride'] ) && ( is_bool( $_REQUEST['codOverride'] ) || is_array( $_REQUEST['codOverride'] ) || is_object( $_REQUEST['codOverride'] ) ) ) {
				throw new \InvalidArgumentException( __( 'Enter a finite, nonnegative shipping or COD amount.', 'nashenski-commerce' ) );
			}
			$override = self::amount( $override );
			if ( $order->get_payment_method() !== 'cod' ) { throw new \InvalidArgumentException( __( 'COD is available only for cash-on-delivery orders.', 'nashenski-commerce' ) ); }
		}
		self::cod( $order, $mode === 'fixed' ? 'sender' : $mode, $override );
		if ( $mode === 'fixed' ) { self::validate_fixed_total( $order, $price, $override ); }
		return array( 'mode' => $mode, 'price' => $price, 'override' => $override );
	}
}
