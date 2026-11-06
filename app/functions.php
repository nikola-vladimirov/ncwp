<?php
/**
 * Global helper functions of the plugin.
 *
 * @package Nashenski
 */

defined( 'ABSPATH' ) || exit;

/**
 * A plugin setting.
 *
 * @param string $group Settings group.
 * @param string $name  Setting name.
 * @return mixed
 */
function nashenski_get_option( $group, $name ) {
	return get_option( 'nashenski_settings_' . $group . '_' . $name );
}

/**
 * A select setting: the saved choice, or the first choice when none is saved.
 *
 * @param string $group   Settings group.
 * @param string $name    Setting name.
 * @param array  $choices Choice key => label.
 * @return mixed
 */
function nashenski_get_choice( $group, $name, $choices ) {
	$value = nashenski_get_option( $group, $name );

	return $value && isset( $choices[ $value ] ) ? $value : array_key_first( $choices );
}

/**
 * Rows of a CSV file whose first row is the header, as header => value arrays. Rows shorter
 * than the header are skipped, extra columns dropped.
 *
 * @param string $path File path.
 * @return array
 */
function nashenski_read_csv( $path ) {
	$file = new SplFileObject( $path );
	$file->setFlags( SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE );
	$head = null;
	$rows = array();

	foreach ( $file as $row ) {
		if ( null === $head ) {
			$head = $row;
		} elseif ( count( $row ) >= count( $head ) ) {
			$rows[] = array_combine( $head, array_slice( $row, 0, count( $head ) ) );
		}
	}

	return $rows;
}

/**
 * Run $callback with the plugin's texts in $locale (documents are made in bg_BG and en_US
 * whatever the site or user language). WordPress texts and month names follow when that
 * language is installed.
 *
 * @param string   $locale   Locale, such as bg_BG.
 * @param callable $callback Code to run.
 * @return mixed
 */
function nashenski_in_locale( $locale, $callback ) {
	$switched = switch_to_locale( $locale );
	nashenski_load_bundled_translations( $locale );

	try {
		return $callback();
	} finally {
		if ( $switched ) {
			restore_previous_locale();
		}

		nashenski_load_bundled_translations();
	}
}

/**
 * Save a plugin setting.
 *
 * @param string $group Settings group.
 * @param string $name  Setting name.
 * @param mixed  $value Value.
 * @return bool
 */
function nashenski_set_option( $group, $name, $value ) {
	return update_option( 'nashenski_settings_' . $group . '_' . $name, $value );
}

/**
 * VAT group letter => rate in percent.
 *
 * @return array
 */
function nashenski_get_vat_groups() {
	return array(
		'a' => '0',
		'b' => '20',
		'g' => '9',
	);
}

/**
 * Refund methods, by id.
 *
 * @return array
 */
function nashenski_get_return_methods() {
	return array(
		1 => array(
			'id'    => 1,
			'label' => __( 'On a payment account', 'nashenski-commerce' ),
		),
		2 => array(
			'id'    => 2,
			'label' => __( 'Card', 'nashenski-commerce' ),
		),
		3 => array(
			'id'    => 3,
			'label' => __( 'Cash', 'nashenski-commerce' ),
		),
		4 => array(
			'id'    => 4,
			'label' => __( 'Other', 'nashenski-commerce' ),
		),
	);
}

/**
 * Payment types of the audit file, by id.
 *
 * @return array
 */
function nashenski_get_payment_types() {
	return array(
		1 => array(
			'id'    => 1,
			'label' => __( 'Released under Art. 3 payment without PPP', 'nashenski-commerce' ),
		),
		2 => array(
			'id'    => 2,
			'label' => __( 'Virtual POS terminal', 'nashenski-commerce' ),
		),
		3 => array(
			'id'    => 3,
			'label' => __( 'Cash on delivery with PPP', 'nashenski-commerce' ),
		),
		4 => array(
			'id'    => 4,
			'label' => __( 'Payment service provider', 'nashenski-commerce' ),
		),
		5 => array(
			'id'    => 5,
			'label' => __( 'Other', 'nashenski-commerce' ),
		),
		6 => array(
			'id'    => 6,
			'label' => __( 'Reflected with receipt', 'nashenski-commerce' ),
		),
	);
}

/**
 * Payment type id => name stored in the order meta.
 *
 * @return array
 */
function nashenski_get_payment_types_for_meta() {
	return array(
		'1' => 'WithoutPostPayment',
		'2' => 'VirtualPOSTerminal',
		'3' => 'WithPostPayment',
		'4' => 'PaymentService',
		'5' => 'Other',
		'6' => 'ReflectedWithReceipt',
	);
}

/**
 * Tax classes for a select, standard first.
 *
 * @return array
 */
function nashenski_get_tax_classes() {
	$tax_classes = WC_Tax::get_tax_classes();

	$classes_options = array(
		'standard' => array(
			'id'    => 'standard',
			'label' => __( 'Standard', 'nashenski-commerce' ),
		),
	);

	foreach ( $tax_classes as $class ) {
		$slug                     = sanitize_title( $class );
		$classes_options[ $slug ] = array(
			'id'    => $slug,
			'label' => esc_html( $class ),
		);
	}

	return $classes_options;
}

/**
 * The VAT number on an order, or ''.
 *
 * @param WC_Order|false|null $order Order.
 * @return string
 */
function nashenski_get_vat_from_order( $order ) {
	if ( ! $order ) {
		return '';
	}

	$vat = $order->get_meta( '_billing_vat_number' );

	if ( ! $vat ) {
		$vat = $order->get_meta( '_vat_number' );
	}

	return $vat ? $vat : '';
}

/**
 * A key => label array as a list of select items.
 *
 * @param array $items                 Key => label.
 * @param bool  $user_value_for_id     Use the label as the id.
 * @param array $additional_attributes Extra keys for every item.
 * @return array
 */
function nashenski_return_array_for_select( $items, $user_value_for_id = false, $additional_attributes = array() ) {
	$new_array = array();

	if ( ! empty( $items ) ) {
		foreach ( $items as $key => $value ) {
			$id = $key;

			if ( $user_value_for_id ) {
				$id = $value;
			}

			$item = array(
				'id'       => $id,
				'orig_key' => $key,
				'label'    => $value,
			);

			if ( ! empty( $additional_attributes ) ) {
				$item = array_merge( $item, $additional_attributes );
			}

			$new_array[] = $item;
		}
	}

	return $new_array;
}

/**
 * Bulgarian provinces, by WooCommerce state code.
 *
 * @return array
 */
function nashenski_return_bg_states() {
	return array(
		''      => '',
		'BG-01' => 'Благоевград',
		'BG-02' => 'Бургас',
		'BG-03' => 'Варна',
		'BG-04' => 'Велико Търново',
		'BG-05' => 'Видин',
		'BG-06' => 'Враца',
		'BG-07' => 'Габрово',
		'BG-08' => 'Добрич',
		'BG-09' => 'Кърджали',
		'BG-10' => 'Кюстендил',
		'BG-11' => 'Ловеч',
		'BG-12' => 'Монтана',
		'BG-13' => 'Пазарджик',
		'BG-14' => 'Перник',
		'BG-15' => 'Плевен',
		'BG-16' => 'Пловдив',
		'BG-17' => 'Разград',
		'BG-18' => 'Русе',
		'BG-19' => 'Силистра',
		'BG-20' => 'Сливен',
		'BG-21' => 'Смолян',
		'BG-22' => 'София',
		'BG-23' => 'София Област',
		'BG-24' => 'Стара Загора',
		'BG-25' => 'Търговище',
		'BG-26' => 'Хасково',
		'BG-27' => 'Шумен',
		'BG-28' => 'Ямбол',
	);
}

/**
 * Choices of the "review and test" shipping setting.
 *
 * @return array
 */
function nashenski_get_shipping_tests_options() {
	return array(
		'no'     => __( 'No', 'nashenski-commerce' ),
		'review' => __( 'Review only', 'nashenski-commerce' ),
		'test'   => __( 'Review and test', 'nashenski-commerce' ),
	);
}

/**
 * VAT rate of an order's shipping.
 *
 * @param WC_Order $order      Order.
 * @param bool     $show_group Prefix the VAT group letter.
 * @return mixed
 */
function nashenski_get_order_shipping_vat( $order, $show_group = false ) {
	$shipping_vat = 0;

	if ( wc_tax_enabled() && ! empty( $order->get_items( 'tax' ) ) ) {
		foreach ( $order->get_items( 'tax' ) as $tax_item ) {
			if ( $tax_item->get_shipping_tax_total() ) {
				$shipping_vat = $tax_item->get_rate_percent();
				break;
			}
		}
	}

	if ( $show_group ) {
		$shipping_vat = nashenski_maybe_add_rate_group( $shipping_vat );
	}

	return \Nashenski\Hooks::filter( 'nashenski_order_item_shipping_vat_rate', 'nashenski/order_item/shipping_vat_rate', $shipping_vat, $order );
}

/**
 * VAT rate of an order line.
 *
 * @param WC_Order_Item $item         Order line.
 * @param WC_Order      $order        Order.
 * @param bool          $show_group   Prefix the VAT group letter.
 * @param mixed         $default_rate Rate to start from, false for the shop's VAT group.
 * @return mixed
 */
function nashenski_get_order_item_vat_rate( $item, $order, $show_group = false, $default_rate = false ) {
	$tax_data = wc_tax_enabled() ? $item->get_taxes() : false;

	if ( false !== $default_rate ) {
		$rate = $default_rate;
	} else {
		$vat_group       = nashenski_get_option( 'shop', 'vat_group' );
		$vat_percentages = nashenski_get_vat_groups();
		$rate            = ( isset( $vat_percentages[ $vat_group ] ) ) ? $vat_percentages[ $vat_group ] : 0;
	}

	if ( $tax_data ) {
		$founded     = false;
		$order_taxes = $order->get_taxes();

		foreach ( $order_taxes as $tax_item ) {
			$tax_item_id    = $tax_item->get_rate_id();
			$tax_item_total = isset( $tax_data['total'][ $tax_item_id ] ) ? $tax_data['total'][ $tax_item_id ] : '';

			// A line discounted to zero has no tax after discount; its rate is in the tax before discount.
			if ( ! ( $tax_item_total > 0 ) && ! empty( $tax_data['subtotal'][ $tax_item_id ] ) ) {
				$tax_item_total = $tax_data['subtotal'][ $tax_item_id ];
			}

			if ( '' !== $tax_item_total && $tax_item_total > 0 ) {
				$rate    = $tax_item->get_rate_percent();
				$founded = true;
				break;
			}
		}

		if ( ! $founded ) {
			$rate = 0;
		}
	}

	if ( $show_group ) {
		$rate = nashenski_maybe_add_rate_group( $rate );
	}

	return \Nashenski\Hooks::filter( 'nashenski_order_item_vat_rate', 'nashenski/order_item/vat_rate', $rate, $item, $order );
}

/**
 * The rate with its VAT group letter in front, when it has one.
 *
 * @param mixed $rate Rate in percent.
 * @return mixed
 */
function nashenski_maybe_add_rate_group( $rate ) {
	$group = nashenski_get_vat_group_from_rate( $rate );

	if ( $group ) {
		$rate = $group . ' - ' . $rate;
	}

	return $rate;
}

/**
 * VAT group letter of a rate, or ''.
 *
 * @param mixed $rate Rate in percent.
 * @return string
 */
function nashenski_get_vat_group_from_rate( $rate ) {
	$groups = array(
		'0'  => 'А',
		'9'  => 'Г',
		'20' => 'Б',
	);

	return $groups[ (string) $rate ] ?? '';
}

/**
 * Price without VAT when taxes are on (or forced), with two decimals.
 *
 * @param float $price Price with VAT.
 * @param float $rate  VAT rate in percent.
 * @param bool  $force Take the VAT off even when taxes are off.
 * @return string
 */
function nashenski_tax_based_price( $price, $rate = 20, $force = false ) {
	if ( wc_tax_enabled() || $force ) {
		$price = $price - nashenski_calculate_vat_from_price( $price, $rate );
	}

	return number_format( round( $price, 2 ), 2, '.', '' );
}

/**
 * VAT included in a price, with two decimals.
 *
 * @param float $price Price with VAT.
 * @param float $rate  VAT rate in percent.
 * @return string
 */
function nashenski_calculate_vat_from_price( $price, $rate = 20 ) {
	$vat = ( new \WC_Tax() )::calc_tax(
		$price,
		array(
			array(
				'compound' => 'yes',
				'rate'     => $rate,
			),
		),
		true
	)[0];
	$vat = round( $vat, 3, PHP_ROUND_HALF_UP );
	$vat = number_format( $vat, 2, '.', '' );

	return $vat;
}

/**
 * Whether the documents leave out the delivery charge: 'yes' or 'no'.
 *
 * @param WC_Order|WC_Order_Refund $order Order or refund.
 * @return string
 */
function nashenski_maybe_remove_shipping( $order ) {
	$remove_shipping = nashenski_get_option( 'invoice', 'remove_shipping' );

	if ( is_a( $order, 'Automattic\WooCommerce\Admin\Overrides\OrderRefund' ) ||
		is_a( $order, 'WC_Order_Refund' )
	) {
		$order = wc_get_order( $order->get_parent_id() );
	}

	if ( ! $order ) {
		return $remove_shipping;
	}

	// Only a cash on delivery courier receipts the delivery charge; paid orders keep it.
	if ( 'cod' !== $order->get_payment_method() ) {
		return 'no';
	}

	$shipping_methods = $order->get_shipping_methods();

	if ( ! empty( $shipping_methods ) ) {
		$shipping_method    = reset( $shipping_methods );
		$shipping_method_id = $shipping_method['method_id'];

		if ( $order->get_payment_method() === 'cod' && nashenski_get_option( 'shippings', $shipping_method_id . '_is_courier' ) === 'yes' ) {
			$remove_shipping = 'yes';
		}
	}

	return $remove_shipping;
}

/**
 * Whether the cart has a valid free shipping coupon.
 *
 * @return bool
 */
function nashenski_has_free_shipping_coupon_in_cart() {
	$has_coupon = false;
	$coupons    = WC()->cart->get_coupons();

	if ( $coupons ) {
		foreach ( $coupons as $code => $coupon ) {
			if ( $coupon->is_valid() && $coupon->get_free_shipping() ) {
				$has_coupon = true;
				break;
			}
		}
	}

	return $has_coupon;
}

/**
 * A Bulgarian phone number as ten digits starting with 0.
 *
 * @param string $phone   Phone number.
 * @param string $country Country code.
 * @return string
 */
function nashenski_format_phone( $phone, $country = 'BG' ) {
	if ( 'BG' !== $country ) {
		return $phone;
	}

	$phone = str_replace( array( ' ', '+' ), '', $phone );

	if ( substr( $phone, 0, 3 ) === '359' ) {
		$phone = str_pad( substr( $phone, 3 ), 10, '0', STR_PAD_LEFT );
	}

	$phone = str_pad( $phone, 10, '0', STR_PAD_LEFT );

	return $phone;
}

/**
 * A request value once its nonce checks out, sanitized all the way down; $fallback when it is
 * missing or the nonce is wrong. With no key, the whole sanitized request. The nonce check and the
 * read stay in this one function on purpose: that is where Plugin Check looks for them.
 *
 * @param string|null     $key      Request key.
 * @param mixed           $fallback Value when missing or not verified.
 * @param string          $field    Request key of the nonce.
 * @param string|string[] $actions  Accepted nonce actions.
 * @return mixed
 */
function nashenski_verified_request( $key, $fallback, $field, $actions ) {
	$nonce    = isset( $_REQUEST[ $field ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $field ] ) ) : '';
	$verified = false;

	foreach ( (array) $actions as $action ) {
		$verified = $verified || wp_verify_nonce( $nonce, $action );
	}

	if ( ! $verified ) {
		return $fallback;
	}

	if ( null === $key ) {
		return map_deep( wp_unslash( $_REQUEST ), 'sanitize_text_field' );
	}

	return isset( $_REQUEST[ $key ] ) ? map_deep( wp_unslash( $_REQUEST[ $key ] ), 'sanitize_text_field' ) : $fallback;
}

/**
 * Order screen label actions: stop with a JSON error when the label nonce is wrong or the user
 * may not manage the store.
 */
function nashenski_check_admin_label_actions() {
	$errors = array();

	if ( null === nashenski_label_request( 'nonce', null ) ) {
		$errors[] = __( 'This page has expired. Reload it and try again.', 'nashenski-commerce' );
	}

	if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce registers this capability.
		$errors[] = __( 'You cannot create labels.', 'nashenski-commerce' );
	}

	if ( ! empty( $errors ) ) {
		wp_send_json_error(
			array(
				'message' => implode( ' ', $errors ),
			)
		);
	}
}

/**
 * A value sent by the order screen label tools (nonce nashenski_admin_label). Arrays are
 * sanitized all the way down. With no key, the whole sanitized request.
 *
 * @param string|null $key      Request key.
 * @param mixed       $fallback Value when missing or not verified.
 * @return mixed
 */
function nashenski_label_request( $key = null, $fallback = '' ) {
	$value = nashenski_verified_request( $key, $fallback, 'nonce', 'nashenski_admin_label' );

	// An empty choice (no office or locker picked) arrives as '': keep the array the caller expects.
	return is_array( $fallback ) && ! is_array( $value ) ? $fallback : $value;
}

/**
 * Office, locker and street lists for the checkout pickers: stop with a JSON error unless the
 * request carries the pickers nonce (or the label nonce of the order screen).
 */
function nashenski_check_picker_request() {
	if ( null === nashenski_picker_request( 'nonce', null ) ) {
		wp_send_json_error(
			array(
				'error' => __( 'This page has expired. Reload it and try again.', 'nashenski-commerce' ),
			),
			403
		);
	}
}

/**
 * A text value sent by the checkout pickers, after checking their nonce.
 *
 * @param string $key      Request key.
 * @param mixed  $fallback Value when missing or not verified.
 * @return mixed
 */
function nashenski_picker_request( $key, $fallback = '' ) {
	$value = nashenski_verified_request( $key, $fallback, 'nonce', array( 'nashenski_pickers', 'nashenski_admin_label' ) );

	return is_array( $value ) ? $fallback : $value;
}

/**
 * A read-only value from the address bar: which screen or notice to show. Never used to change data.
 *
 * @param string $key      Query key.
 * @param string $fallback Value when missing.
 * @return string
 */
function nashenski_query_arg( $key, $fallback = '' ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: chooses the screen or notice to show.
	return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $fallback;
}

/**
 * A text field of the classic checkout form while WooCommerce places the order. WooCommerce
 * checks its place-order nonce before; it is checked again here.
 *
 * @param string $key      Request key.
 * @param mixed  $fallback Value when missing or not verified.
 * @return mixed
 */
function nashenski_checkout_posted( $key, $fallback = '' ) {
	$value = nashenski_verified_request( $key, $fallback, 'woocommerce-process-checkout-nonce', 'woocommerce-process_checkout' );

	return is_array( $value ) ? $fallback : $value;
}

/**
 * The classic checkout form sent with "update order review" (WooCommerce checks its nonce
 * before; it is checked again here), as sanitized fields.
 *
 * @return array
 */
function nashenski_order_review_data() {
	$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';

	if ( ! isset( $_POST['post_data'] ) || ! wp_verify_nonce( $nonce, 'update-order-review' ) ) {
		return array();
	}

	$data = array();
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a query string; every value is sanitized below.
	parse_str( wp_unslash( $_POST['post_data'] ), $data );

	return map_deep( $data, 'sanitize_text_field' );
}
/**
 * Send a temporary file to the browser as a download, delete it and stop.
 *
 * @param string $path File path.
 * @param string $name File name for the browser.
 * @param string $type Content type.
 */
function nashenski_send_download( $path, $name, $type ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

	$filesystem = new WP_Filesystem_Direct( null );
	$contents   = (string) $filesystem->get_contents( $path );

	wp_delete_file( $path );
	nocache_headers();
	header( 'Content-Type: ' . $type );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $name ) . '"' );
	header( 'Content-Length: ' . strlen( $contents ) );
	echo $contents; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a file download (CSV, Excel, JSON, XML or ZIP), not HTML.
	exit;
}

/**
 * Calculate the cart fees and their taxes now, so the fee totals are ready before the cart totals.
 *
 * @param WC_Cart $cart Cart.
 */
function nashenski_prime_cart_fee_totals( WC_Cart $cart ) {
	static $is_calculating = false;

	if ( $is_calculating ) {
		return;
	}

	$is_calculating = true;

	try {
		$fees_api      = $cart->fees_api();
		$customer      = $cart->get_customer();
		$calculate_tax = wc_tax_enabled() && ( ! $customer || ! $customer->get_is_vat_exempt() );

		$fees_api->remove_all_fees();
		$cart->calculate_fees();

		$fee_total     = 0.0;
		$fee_tax_total = 0.0;
		$fee_taxes     = array();

		foreach ( $cart->get_fees() as $fee ) {
			$amount    = isset( $fee->amount ) ? (float) $fee->amount : 0.0;
			$taxes     = array();
			$tax_total = 0.0;

			$fee_total += $amount;

			if ( $calculate_tax && 0 < $amount && ! empty( $fee->taxable ) ) {
				$tax_class = isset( $fee->tax_class ) ? (string) $fee->tax_class : '';
				$taxes     = WC_Tax::calc_tax( $amount, WC_Tax::get_rates( $tax_class, $customer ), false );

				foreach ( $taxes as $rate_id => $tax_amount ) {
					$taxes[ $rate_id ] = wc_round_tax_total( $tax_amount );
				}

				$tax_total      = array_sum( $taxes );
				$fee_tax_total += $tax_total;
				$fee_taxes      = wc_array_merge_recursive_numeric( $fee_taxes, $taxes );
			}

			$fee->total    = wc_format_decimal( $amount );
			$fee->tax      = wc_format_decimal( $tax_total );
			$fee->tax_data = $taxes;
		}

		$cart->set_fee_total( $fee_total );
		$cart->set_fee_tax( $fee_tax_total );
		$cart->set_fee_taxes( $fee_taxes );
	} finally {
		$is_calculating = false;
	}
}

/**
 * Cart total for the shipping rates: contents with tax, and fees unless filtered out.
 *
 * @return mixed
 */
function nashenski_get_package_total() {
	$includes_fees = \Nashenski\Hooks::filter( 'nashenski_shipping_package_total_includes_fees', 'nashenski/shipping/package_total_includes_fees', true );
	$total         = (float) WC()->cart->get_cart_contents_total() + (float) WC()->cart->get_cart_contents_tax();

	if ( $includes_fees ) {
		nashenski_prime_cart_fee_totals( WC()->cart );

		$total += (float) WC()->cart->get_fee_total() + (float) WC()->cart->get_fee_tax();
	}

	$total = wc_format_decimal( $total, wc_get_price_decimals() );

	return \Nashenski\Hooks::filter( 'nashenski_shipping_package_total', 'nashenski/shipping/package_total', $total );
}

/**
 * Whether any courier is turned on.
 *
 * @return bool
 */
function nashenski_is_shipping_enabled() {
	foreach ( \Nashenski\Plugin::COURIERS as $courier ) {
		if ( 'yes' === nashenski_get_option( 'apis', 'enable_' . $courier ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Courier slug => name, in display order. Call it from hooks at or after init (names are translated).
 */
function nashenski_couriers() {
	return array_combine(
		\Nashenski\Plugin::COURIERS,
		array(
			__( 'Econt', 'nashenski-commerce' ),
			__( 'Speedy', 'nashenski-commerce' ),
			__( 'BOX NOW', 'nashenski-commerce' ),
			__( 'Pigeon Express', 'nashenski-commerce' ),
		)
	);
}

/**
 * The office, locker or address the checkout picker saved in a courier cookie, or ''.
 *
 * @param string $name Cookie name.
 * @return array|string
 */
function nashenski_courier_cookie( $name ) {
	if ( ! isset( $_COOKIE[ $name ] ) ) {
		return '';
	}

	// Decode first, then sanitize every value: sanitizing the raw JSON would corrupt it.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every decoded value is sanitized below.
	$data = json_decode( urldecode( wp_unslash( $_COOKIE[ $name ] ) ), true );

	return is_array( $data ) ? map_deep( $data, 'sanitize_text_field' ) : '';
}

/**
 * After checkout: keep the label priced at checkout (filter nashenski_{courier}_label_before_save)
 * and the customer's office or address choice on the order.
 *
 * @param int    $order_id Order id.
 * @param string $courier  Courier slug.
 */
function nashenski_save_checkout_label( $order_id, $courier ) {
	$order = wc_get_order( $order_id );

	foreach ( $order->get_items( 'shipping' ) as $shipping ) {
		if ( 'nashenski_' . $courier !== $shipping->get_method_id() ) {
			continue;
		}

		$label = WC()->session->get( "nashenski-{$courier}-label" );

		if ( $label ) {
			$order->update_meta_data( "nashenski_{$courier}_label", \Nashenski\Hooks::filter( "nashenski_{$courier}_label_before_save", "nashenski/{$courier}/label_before_save", $label, $order ) );
			WC()->session->__unset( "nashenski-{$courier}-label" );
		}

		if ( $shipping->get_meta( 'cookie_data' ) ) {
			$cookie_data = $shipping->get_meta( 'cookie_data' );
			if ( 'pigeon' === $courier ) {
				$cookie_data = \Nashenski\Shipping\Pigeon\Payment::cookie( $cookie_data ); }
			$order->update_meta_data( "nashenski_{$courier}_cookie_data", $cookie_data );
		}

		$order->save();

		return;
	}
}

/**
 * The courier's label box on the order screen, when the order ships with that courier.
 *
 * @param string   $slug     Courier slug.
 * @param string   $title    Box title.
 * @param callable $callback Box content.
 */
function nashenski_add_courier_meta_box( $slug, $title, $callback ) {
	global $post;
	$order = isset( $GLOBALS['theorder'] ) && is_object( $GLOBALS['theorder'] ) ? $GLOBALS['theorder'] : wc_get_order( $post->ID );

	foreach ( $order ? $order->get_items( 'shipping' ) : array() as $shipping ) {
		if ( 'nashenski_' . $slug === $shipping->get_method_id() ) {
			$screen = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
				? array( wc_get_page_screen_id( 'shop-order' ), wc_get_page_screen_id( 'shop_subscription' ) )
				: array( 'shop_order', 'shop_subscription' );

			add_meta_box( 'nashenski_' . $slug, $title, $callback, array_filter( $screen ), 'normal', 'default' );

			return;
		}
	}
}

/**
 * Order note for a created, updated or deleted label.
 *
 * @param WC_Order        $order        Order.
 * @param string          $courier      Courier name.
 * @param string          $action       created, updated or deleted.
 * @param string|string[] $label_number Waybill number or numbers.
 */
function nashenski_add_label_order_note( $order, $courier, $action, $label_number = '' ) {
	if ( ! $order instanceof WC_Order ) {
		return;
	}

	$actions = array(
		'created' => __( 'Label created.', 'nashenski-commerce' ),
		'updated' => __( 'Label updated.', 'nashenski-commerce' ),
		'deleted' => __( 'Label deleted.', 'nashenski-commerce' ),
	);

	if ( ! isset( $actions[ $action ] ) ) {
		return;
	}

	if ( is_array( $label_number ) ) {
		$label_number = implode( ', ', array_filter( array_map( 'sanitize_text_field', $label_number ) ) );
	} else {
		$label_number = sanitize_text_field( $label_number );
	}

	$note = sprintf( '%s: %s', sanitize_text_field( $courier ), $actions[ $action ] );

	if ( '' !== $label_number ) {
		/* translators: %s: waybill number */
		$note .= sprintf( ' ' . __( 'Label number: %s.', 'nashenski-commerce' ), $label_number );
	}

	$order->add_order_note( $note );
}

/**
 * Label number, print link and courier of an order, for the orders list column.
 *
 * @param int $order_id Order id.
 * @return array
 */
function nashenski_get_order_label( $order_id ) {
	$order      = wc_get_order( $order_id );
	$data       = array();
	$label_data = array();
	$method     = '';

	if ( ! empty( $order->get_items( 'shipping' ) ) ) {
		$items         = $order->get_items( 'shipping' );
		$method_object = array_shift( $items );
		$method        = $method_object['method_id'];

		$label_data = nashenski_get_label_data_for_shipping_method( $method_object, $order );

		foreach ( $order->get_items( 'shipping' ) as $shipping ) {
			$method = $shipping['method_id'];
		}
	}

	$print = admin_url( 'admin-ajax.php' ) . '?cache-buster=' . wp_rand() . '&nonce=' . wp_create_nonce( 'nashenski_admin_label' ) . '&action=';

	if ( ! empty( $label_data ) ) {
		switch ( $method ) {
			case 'nashenski_speedy':
				$shipment_status = $order->get_meta( 'nashenski_speedy_shipment_status' );

				if ( $shipment_status && $label_data['id'] ) {
					$data['number'] = $label_data['id'];
					$data['link']   = $print . 'nashenski_speedy_print_labels&parcels=' . implode( '|', wp_list_pluck( $shipment_status['parcels'], 'id' ) ) . '&size=A6';
				}
				break;
			case 'nashenski_econt':
				$shipment_status = $order->get_meta( 'nashenski_econt_shipment_status' );

				if ( $shipment_status && ! empty( $label_data['label'] ) && ! empty( $label_data['label']['shipmentNumber'] ) ) {
					$pdf = \Nashenski\Admin\Courier_Pdf::url( $shipment_status['label']['pdfURL'] ?? null, '10x9' );
					// Waybills imported from another plugin may have no PDF address: link to Econt's tracking page then.
					$data = array(
						'number' => $label_data['label']['shipmentNumber'],
						'link'   => $pdf ? $pdf : \Nashenski\Shipping\Automation::tracking_url( 'econt', $label_data['label']['shipmentNumber'] ),
					);
				}
				break;
			case 'nashenski_boxnow':
				$parcels      = isset( $label_data['parcels'] ) && is_array( $label_data['parcels'] ) ? $label_data['parcels'] : array();
				$other_labels = $order->get_meta( 'nashenski_boxnow_other_labels_data' );

				if ( $other_labels ) {
					$data['other_labels'] = $other_labels;

					foreach ( $other_labels as $other_label ) {
						$parcels = array_merge( $parcels, $other_label['shipmentStatus']['parcels'] );
					}
				}

				foreach ( $parcels as $parcel ) {
					$data['items'][] = array(
						'number' => $parcel['id'],
						'link'   => $print . 'nashenski_boxnow_print_label&parcel=' . $parcel['id'],
					);
				}

				break;
			case 'nashenski_pigeon':
				$shipment_status = $order->get_meta( 'nashenski_pigeon_shipment_status' );
				if ( $shipment_status && ! empty( $label_data['data']['reference_number'] ) ) {
					$data = array(
						'number' => $label_data['data']['reference_number'],
						'link'   => $print . 'nashenski_pigeon_print_labels&order-id=' . $order->get_id(),
					);
				}

				break;
		}
	}

	if ( $method ) {
		$names          = nashenski_couriers();
		$courier        = preg_replace( '/^nashenski_/', '', $method );
		$data['method'] = $method;

		if ( $courier !== $method && isset( $names[ $courier ] ) ) {
			$data['courier'] = $names[ $courier ];
		}
	}

	return \Nashenski\Hooks::filter( 'nashenski_column_order_shipment_status_data', 'nashenski/column/order/shipment_status_data', $data, $order );
}

/**
 * The saved label of an order's shipping line: the label data of Speedy and Econt, the
 * shipment status of BOX NOW and Pigeon Express. Null when there is none.
 *
 * @param WC_Order_Item_Shipping $shipping Shipping line.
 * @return mixed
 */
function nashenski_get_label_data_for_shipping_method( $shipping ) {
	$meta_keys = array(
		'nashenski_speedy' => 'nashenski_speedy_label',
		'nashenski_econt'  => 'nashenski_econt_label',
		'nashenski_boxnow' => 'nashenski_boxnow_shipment_status',
		'nashenski_pigeon' => 'nashenski_pigeon_shipment_status',
	);

	if ( ! isset( $meta_keys[ $shipping['method_id'] ] ) ) {
		return null;
	}

	$label = $shipping->get_order()->get_meta( $meta_keys[ $shipping['method_id'] ] );

	return $label ? $label : null;
}

/**
 * Taxes of a shipping price in a country (20% when the shop has no shipping rate).
 *
 * @param float  $price   Shipping price.
 * @param string $country Country code.
 * @return array
 */
function nashenski_get_shipping_rate_taxes( $price, $country = 'BG' ) {
	if ( ! wc_tax_enabled() ) {
		return array();
	}

	$tax_rates = \WC_Tax::find_shipping_rates( array( 'country' => $country ) );

	if ( empty( $tax_rates ) ) {
		$tax_rates = array(
			array(
				'rate'     => 20,
				'compound' => 'yes',
			),
		);
	}

	return \WC_Tax::calc_tax( $price, $tax_rates, false );
}

/**
 * Whether the shipping address differs from the billing address.
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function nashenski_is_different_shipping_address( $order ) {
	$billing_address  = $order->get_address();
	$shipping_address = $order->get_address( 'shipping' );

	if ( ! empty( $billing_address ) && ! empty( $shipping_address ) ) {
		foreach ( $billing_address as $billing_address_key => $billing_address_value ) {
			if ( isset( $shipping_address[ $billing_address_key ] ) ) {
				$shipping_address_value = $shipping_address[ $billing_address_key ];

				if ( ! empty( $billing_address_value ) && ! empty( $shipping_address_value ) && strcmp( $billing_address_value, $shipping_address_value ) !== 0 ) {
					return true;
				}
			}
		}
	}

	return false;
}

/**
 * A street without its "ул.", "бул." and similar prefix.
 *
 * @param string $street Street.
 * @return string
 */
function nashenski_strip_street_prefix( $street ) {
	$prefixes = array(
		// Cyrillic prefixes.
		'ул.',
		'ул ',
		'бул.',
		'бул ',
		'пл.',
		'пл ',
		'пр.',
		'пр ',
		'кв.',
		'кв ',
		'ж.к.',
		'ж.к',
		'жк.',
		'жк',
		// Latin prefixes (stripped before transliteration).
		'ul.',
		'ul ',
		'bul.',
		'bul ',
		'pl.',
		'pl ',
		'pr.',
		'pr ',
		'kv.',
		'kv ',
		'zh.k.',
		'zh.k',
		'zhk.',
		'zhk',
		'j.k.',
		'j.k',
		'jk.',
		'jk',
	);

	$street = preg_replace( '/\.(?=\S)/u', '. ', $street );

	foreach ( $prefixes as $prefix ) {
		if ( mb_stripos( $street, $prefix ) === 0 ) {
			$street = mb_substr( $street, mb_strlen( $prefix ) );
			$street = ltrim( $street );
			break;
		}
	}

	return $street;
}

/**
 * A city without its "гр.", "с." and similar prefix, in any of the supported languages.
 *
 * @param string $city City.
 * @return string
 */
function nashenski_remove_city_prefix( $city ) {
	$prefixes = array(
		'гр(?:ад)?',
		'с(?:ело)?',
		'к\s*\.?\s*к',
		'курортен\s+комплекс',
		'gr(?:ad)?',
		's(?:elo)?',
		'k\s*\.?\s*k',
		'kurorten\s+kompleks',
		'or(?:a(?:ș|ş|s)(?:ul)?)?',
		'mun(?:icipiu(?:l)?)?',
		'com(?:un(?:ă|a))?',
		'sat(?:ul)?',
		'loc(?:alitate(?:a)?)?',
		'sta(?:ți|ţi|ti)une(?:a)?',
		'πόλ(?:η|ις)',
		'πολ(?:η|ις)',
		'χωριό',
		'χωριο',
		'κωμόπολη',
		'κωμοπολη',
		'δήμος',
		'δημος',
		'κοινότητα',
		'κοινοτητα',
		'οικισμός',
		'οικισμος',
		'θέρετρο',
		'θερετρο',
		'poli(?:s)?',
		'(?:c|x)horio',
		'komopoli',
		'dimos',
		'koinotita',
		'oikismos',
		'theretro',
		'city',
		'town',
		'village',
		'municipality',
		'community',
		'settlement',
		'resort',
	);

	$city = trim( (string) $city );

	return preg_replace( '/^\s*(?:' . implode( '|', $prefixes ) . ')(?:\s*[.\-,:]\s*|\s+)/iu', '', $city );
}

/**
 * Bulgarian text in title case, with short joining words kept lower case after the first word.
 *
 * @param string $text     Text.
 * @param string $encoding Encoding.
 * @return string
 */
function nashenski_title_case_bg( string $text, string $encoding = 'UTF-8' ) {
	$small_words = array(
		'и',
		'или',
		'а',
		'но',
		'че',
		'в',
		'във',
		'с',
		'със',
		'у',
		'към',
		'на',
		'за',
		'от',
		'до',
		'по',
		'под',
		'над',
		'пред',
		'при',
		'през',
		'без',
		'след',
		'преди',
		'между',
		'сред',
		'чрез',
	);

	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	$text = mb_strtolower( $text, $encoding );

	$parts      = preg_split( '/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
	$result     = array();
	$word_index = 0;

	foreach ( $parts as $part ) {
		if ( preg_match( '/^\s+$/u', $part ) ) {
			$result[] = $part;
			continue;
		}

		$word = $part;
		$word = preg_replace_callback(
			'/^\p{L}/u',
			function ( $m ) use ( $encoding ) {
				return mb_strtoupper( $m[0], $encoding );
			},
			$word
		);

		$word = preg_replace_callback(
			'/(\.)(\p{L})/u',
			function ( $m ) use ( $encoding ) {
				return $m[1] . mb_strtoupper( $m[2], $encoding );
			},
			$word
		);

		if ( $word_index > 0 && in_array( $part, $small_words, true ) ) {
			$word = $part;
		}

		$result[] = $word;
		++$word_index;
	}

	return implode( '', $result );
}

/**
 * Total rate of a tax class, as a fraction (0.2 for 20%).
 *
 * @param string $tax_class Tax class slug.
 * @return float
 */
function nashenski_tax_class_rate( $tax_class ) {
	return array_sum( wp_list_pluck( WC_Tax::get_rates( $tax_class ), 'rate' ) ) / 100;
}

/**
 * Tax class slug => total rate as a fraction, for the impossible prices check.
 *
 * @return array
 */
function nashenski_impossible_prices_get_tax_classes_data() {
	$data = array( '' => nashenski_tax_class_rate( '' ) );

	foreach ( WC_Tax::get_tax_class_slugs() as $tax_class_slug ) {
		$data[ (string) $tax_class_slug ] = nashenski_tax_class_rate( $tax_class_slug );
	}

	return $data;
}

/**
 * Tax rate of the product being edited, as a fraction; 0 when it is not taxable.
 *
 * @return float
 */
function nashenski_impossible_prices_get_product_tax_rate() {
	global $post;

	$product = $post && 'product' === get_post_type( $post ) ? wc_get_product( $post->ID ) : null;

	if ( ! $product instanceof WC_Product || 'taxable' !== $product->get_tax_status() ) {
		return 0.0;
	}

	return nashenski_tax_class_rate( $product->get_tax_class() );
}

/**
 * Text for a courier label: letters, digits and spaces only, cut to a length.
 *
 * @param string $text       Text.
 * @param int    $max_length Longest length.
 * @return string
 */
function nashenski_normalize_text_for_label( $text, $max_length = 200 ) {
	$text = preg_replace( '/[^\p{Cyrillic}a-zA-Z0-9 ]/u', '', htmlspecialchars( $text, ENT_XML1 ) );

	return mb_substr( $text, 0, $max_length, 'UTF-8' );
}

/**
 * The chosen shipping rate $rate_id of package $key, or null. The session copy can be cleared during
 * checkout validation (see Register::validate_cod_choice), so the rates calculated in this request are the fallback.
 *
 * @param int|string $key     Package key.
 * @param string     $rate_id Rate id.
 * @return mixed
 */
function nashenski_chosen_rate( $key, $rate_id ) {
	$package = WC()->session ? WC()->session->get( 'shipping_for_package_' . $key ) : null;

	return $package['rates'][ $rate_id ] ?? WC()->shipping()->get_packages()[ $key ]['rates'][ $rate_id ] ?? null;
}
