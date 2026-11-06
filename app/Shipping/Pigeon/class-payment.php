<?php
/**
 * Pigeon Express payer, shipping price and cash on delivery amounts.
 *
 * @package Nashenski
 */

namespace Nashenski\Shipping\Pigeon;

defined( 'ABSPATH' ) || exit;

/** Keep guest address state separate from authenticated monetary instructions. */
class Payment {
	const COD_OVERRIDE_META = '_nashenski_pigeon_cod_override';

	/**
	 * The checkout cookie data, keeping only the address keys.
	 *
	 * @param mixed $data Cookie data.
	 * @return array
	 */
	public static function cookie( $data ) {
		if ( ! is_array( $data ) ) {
			return array(); }
		$keys = array( 'type', 'receiver', 'phone', 'state', 'city', 'postcode', 'country', 'payment', 'selectedLabel', 'selectedOffice', 'selectedLocker', 'selectedAddress', 'streetNumber', 'other', 'otherField', 'blockNumber', 'entranceNumber', 'floorNumber', 'apartmentNumber', 'billing_to_company', 'billing_company_mol', 'billing_company', 'billing_vat_number' );
		$data = array_intersect_key( $data, array_flip( $keys ) );
		if ( isset( $data['selectedAddress'] ) && is_array( $data['selectedAddress'] ) ) {
			$data['selectedAddress'] = array_intersect_key( $data['selectedAddress'], array_flip( array( 'id', 'orig_key', 'label', 'type', 'name' ) ) );
		}
		return $data;
	}

	/**
	 * A finite, nonnegative amount with the store's decimals.
	 *
	 * @param mixed $value Amount.
	 * @return string
	 * @throws \InvalidArgumentException When the amount is not a finite, nonnegative number.
	 */
	public static function amount( $value ) {
		if ( ! ( is_string( $value ) || is_int( $value ) || is_float( $value ) ) || ! is_numeric( $value ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Enter a finite, nonnegative shipping or COD amount.', 'nashenski-commerce' ) );
		}
		$number = (float) $value;
		$minor  = $number * pow( 10, wc_get_price_decimals() );
		if ( ! is_finite( $number ) || $number < 0 || ! is_finite( $minor ) || $minor > min( PHP_INT_MAX, 9007199254740991 ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Enter a finite, nonnegative shipping or COD amount.', 'nashenski-commerce' ) );
		}
		return wc_format_decimal( $number, wc_get_price_decimals() );
	}

	/**
	 * The order's first Pigeon Express shipping line, or null.
	 *
	 * @param \WC_Order $order Order.
	 * @return \WC_Order_Item_Shipping|null
	 */
	public static function shipping_item( $order ) {
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( $item->get_method_id() === Method::METHOD_ID ) {
				return $item; }
		}
		return null;
	}

	/**
	 * Total of the order's Pigeon Express shipping lines, with tax.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	public static function shipping_total( $order ) {
		$total = 0;
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( $item->get_method_id() === Method::METHOD_ID ) {
				$total += (float) self::amount( $item->get_total() ) + (float) self::amount( $item->get_total_tax() );
			}
		}
		return self::amount( $total );
	}

	/**
	 * The cash on delivery amount, or null when the order is not cash on delivery.
	 *
	 * @param \WC_Order   $order    Order.
	 * @param string      $payer    sender or receiver.
	 * @param string|null $override Amount set by staff.
	 * @return string|null
	 */
	public static function cod( $order, $payer, $override = null ) {
		if ( $order->get_payment_method() !== 'cod' ) {
			return null; }
		if ( null !== $override ) {
			return self::amount( $override ); }
		if ( $order->meta_exists( self::COD_OVERRIDE_META ) ) {
			return self::amount( $order->get_meta( self::COD_OVERRIDE_META, true, 'edit' ) ); }
		$total = 0;
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks add-ons.
		$types = apply_filters( 'nashenski/shipping/package_total_includes_fees', true ) ? array( 'line_item', 'fee' ) : array( 'line_item' );
		foreach ( $order->get_items( $types ) as $item ) {
			$total += (float) $item->get_total() + (float) $item->get_total_tax();
		}
		if ( 'sender' === $payer ) {
			$total += (float) self::shipping_total( $order ); }
		return self::amount( $total );
	}

	/**
	 * $staff: the payer was chosen by staff on the order screen; checkout labels of prepaid orders are always paid by the sender.
	 *
	 * @param array       $label    Label data.
	 * @param \WC_Order   $order    Order.
	 * @param string|null $override Cash on delivery amount set by staff.
	 * @param bool        $staff    Whether staff chose the payer.
	 * @return array
	 * @throws \InvalidArgumentException When the payer is not valid.
	 */
	public static function label( $label, $order, $override = null, $staff = false ) {
		$payer = $staff || $order->get_payment_method() === 'cod' ? ( $label['who_pays'] ?? 'receiver' ) : 'sender';
		if ( ! in_array( $payer, array( 'sender', 'receiver' ), true ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Choose a valid shipping payer.', 'nashenski-commerce' ) );
		}
		$label['who_pays'] = $payer;
		$cod               = self::cod( $order, $payer, $override );
		if ( null === $cod ) {
			unset( $label['service_codes']['cod_amount'] );
		} else {
			$label['service_codes']['cod_amount'] = $cod;
		}
		return $label;
	}

	/**
	 * Label, fixed price and its switch for the order screen.
	 *
	 * @param \WC_Order $order Order.
	 * @param mixed     $label Saved label data.
	 * @return array
	 */
	public static function defaults( $order, $label ) {
		$label = self::label( is_array( $label ) ? $label : array(), $order );
		$item  = self::shipping_item( $order );
		$price = $item ? self::amount( $item->get_total() ) : '0';
		return array(
			'label'             => $label,
			'fixedPrice'        => $price,
			'fixedPriceEnabled' => $order->get_payment_method() === 'cod' && 'sender' === $label['who_pays'] && (float) $price > 0,
		);
	}

	/**
	 * Preview WooCommerce's recalculation on cloned items, before committing a price.
	 *
	 * @param \WC_Order   $order    Order.
	 * @param string      $price    Fixed shipping price.
	 * @param string|null $override Cash on delivery amount set by staff.
	 */
	private static function validate_fixed_total( $order, $price, $override ) {
		$location  = $order->get_taxable_location();
		$tax_class = get_option( 'woocommerce_shipping_tax_class' );
		if ( 'inherit' === $tax_class ) {
			$classes   = array_intersect( array_merge( array( '' ), \WC_Tax::get_tax_class_slugs() ), $order->get_items_tax_classes() );
			$tax_class = $classes ? current( $classes ) : ( count( $order->get_items() ) === 0 ? '' : false );
		}
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter, applied so the preview matches its recalculation
		$exempt   = apply_filters( 'woocommerce_order_is_vat_exempt', 'yes' === $order->get_meta( 'is_vat_exempt' ), $order );
		$first    = self::shipping_item( $order );
		$shipping = 0;
		foreach ( $order->get_items( 'shipping' ) as $original ) {
			if ( $original->get_method_id() !== Method::METHOD_ID ) {
				continue; }
			$item = clone $original;
			if ( $original->get_id() === $first->get_id() ) {
				$item->set_total( $price ); }
			if ( false !== $tax_class && ! $exempt ) {
				$item->calculate_taxes( array_merge( $location, array( 'tax_class' => $tax_class ) ) );
			} else {
				$item->set_taxes( false );
			}
			$shipping += (float) $item->get_total() + (float) $item->get_total_tax();
		}
		self::amount( $shipping );
		if ( $order->get_payment_method() !== 'cod' || null !== $override || $order->meta_exists( self::COD_OVERRIDE_META ) ) {
			return; }
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks add-ons.
		$types = apply_filters( 'nashenski/shipping/package_total_includes_fees', true ) ? array( 'line_item', 'fee' ) : array( 'line_item' );
		$total = $shipping;
		foreach ( $order->get_items( $types ) as $original ) {
			$item = clone $original;
			if ( ! $exempt ) {
				$item->calculate_taxes( $location );
			} else {
				$item->set_taxes( false );
			}
			$total += (float) $item->get_total() + (float) $item->get_total_tax();
		}
		self::amount( $total );
	}

	/**
	 * Validate every monetary instruction before any order/item write.
	 *
	 * @param \WC_Order $order Order.
	 * @return array
	 * @throws \InvalidArgumentException When the nonce, payer or an amount is not valid.
	 */
	public static function request( $order ) {
		// Staff label requests carry the order screen nonce; it is checked here, where the raw request is read.
		if ( ! wp_verify_nonce( isset( $_REQUEST['nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ) : '', 'nashenski_admin_label' ) ) {
			throw new \InvalidArgumentException( esc_html__( 'This page has expired. Reload it and try again.', 'nashenski-commerce' ) );
		}
		if ( (string) nashenski_label_request( 'paymentVersion', null ) !== '1' ) {
			throw new \InvalidArgumentException( esc_html__( 'Reload this order before generating a Pigeon label.', 'nashenski-commerce' ) );
		}
		$payer = nashenski_label_request( 'paymentBy', array() );
		$mode  = $payer['id'] ?? '';
		if ( ! in_array( $mode, array( 'fixed', 'sender', 'receiver' ), true ) ) {
			throw new \InvalidArgumentException( esc_html__( 'Choose a valid shipping payer.', 'nashenski-commerce' ) );
		}
		$price = null;
		if ( 'fixed' === $mode ) {
			if ( isset( $_REQUEST['fixedPrice'] ) && ( is_bool( $_REQUEST['fixedPrice'] ) || is_array( $_REQUEST['fixedPrice'] ) || is_object( $_REQUEST['fixedPrice'] ) ) ) {
				throw new \InvalidArgumentException( esc_html__( 'Enter a finite, nonnegative shipping or COD amount.', 'nashenski-commerce' ) );
			}
			$price = self::amount( nashenski_label_request( 'fixedPrice', null ) );
			if ( ! self::shipping_item( $order ) ) {
				throw new \InvalidArgumentException( esc_html__( 'This order has no Pigeon shipping charge.', 'nashenski-commerce' ) ); }
		}
		$override = nashenski_label_request( 'codOverride', null );
		if ( null !== $override ) {
			if ( isset( $_REQUEST['codOverride'] ) && ( is_bool( $_REQUEST['codOverride'] ) || is_array( $_REQUEST['codOverride'] ) || is_object( $_REQUEST['codOverride'] ) ) ) {
				throw new \InvalidArgumentException( esc_html__( 'Enter a finite, nonnegative shipping or COD amount.', 'nashenski-commerce' ) );
			}
			$override = self::amount( $override );
			if ( $order->get_payment_method() !== 'cod' ) {
				throw new \InvalidArgumentException( esc_html__( 'COD is available only for cash-on-delivery orders.', 'nashenski-commerce' ) ); }
		}
		self::cod( $order, 'fixed' === $mode ? 'sender' : $mode, $override );
		if ( 'fixed' === $mode ) {
			self::validate_fixed_total( $order, $price, $override ); }
		return array(
			'mode'     => $mode,
			'price'    => $price,
			'override' => $override,
		);
	}
}
