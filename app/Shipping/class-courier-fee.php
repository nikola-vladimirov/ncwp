<?php
/**
 * Who pays the courier: the shop, or the customer at delivery.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping;

defined( 'ABSPATH' ) || exit;

/**
 * Who pays the courier (docs/plans/courier-fee-payer.md):
 * - each Econt, Speedy and Pigeon Express method lists the payment methods for which the customer
 *   pays the courier at delivery (BOX NOW cannot charge the customer, so it has no list);
 * - then the rate costs nothing and keeps the fee for information only, so the fee is not in the
 *   order total, on the documents or in the NRA file;
 * - the checkout stores the decision on the order's shipping line, so later setting changes do not
 *   change old documents. Quick and admin orders get it on the order screen once they have a
 *   shipping line and a payment method, and again when either changes, until they have documents.
 */
class Courier_Fee {
	/** Shipping line meta: "customer" or "shop", stored at checkout or on the order screen. */
	const PAYER_META = '_nashenski_fee_payer';

	/** Shipping line meta: the payment method the order-screen decision was made for. */
	const BASIS_META = '_nashenski_fee_basis';

	/** Couriers that can charge the customer. BOX NOW cannot. */
	const METHODS = array( 'nashenski_econt', 'nashenski_speedy', 'nashenski_pigeon' );

	/** Rate and shipping line meta: the currency the fee was calculated in. */
	const CURRENCY_META = '_nashenski_fee_currency';

	/** Order meta: the payment method a "check the delivery by hand" notice was shown for. */
	const WARNED_META = '_nashenski_fee_warned';

	/** Order meta: a label box waybill made the customer pay the shop for the delivery. */
	const SWITCHED_META = '_nashenski_fee_switched';

	/** Rate and shipping line meta: the fee the customer pays the courier, with VAT. */
	const FEE_META = 'nashenski_courier_fee';

	/** Shipping method setting: payment methods for which the customer pays the courier. */
	const FIELD = 'customer_pays';

	/**
	 * Payment gateways, once WooCommerce has loaded them.
	 *
	 * @var \WC_Payment_Gateways|null
	 */
	private static $gateways = null;

	/**
	 * Hooks the checkout stamp and the order display.
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_create_order_shipping_item', array( __CLASS__, 'stamp' ) );
		add_filter( 'woocommerce_order_shipping_to_display', array( __CLASS__, 'order_shipping' ), 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( __CLASS__, 'meta_key' ), 10, 2 );
		add_filter( 'woocommerce_order_item_display_meta_value', array( __CLASS__, 'meta_value' ), 10, 3 );
		add_action( 'wc_payment_gateways_initialized', array( __CLASS__, 'gateways_loaded' ) );
		// After WooCommerce (40) and the quick order delivery box (60) saved the order.
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'decide_order' ), 70 );
		add_action( 'woocommerce_saved_order_items', array( __CLASS__, 'decide_order' ), 20 );

		// The zone screen and its requests show the setting: load the payment methods before the shipping methods are built.
		foreach ( array( 'woocommerce_settings_shipping', 'wp_ajax_woocommerce_shipping_zone_add_method', 'wp_ajax_woocommerce_shipping_zone_remove_method', 'wp_ajax_woocommerce_shipping_zone_methods_save_changes', 'wp_ajax_woocommerce_shipping_zone_methods_save_settings' ) as $hook ) {
			add_action( $hook, array( 'WC_Payment_Gateways', 'instance' ), 1 );
		}
	}

	/**
	 * Keeps the payment gateways WooCommerce has just loaded.
	 *
	 * @param \WC_Payment_Gateways $gateways Payment gateways.
	 */
	public static function gateways_loaded( $gateways ) {
		self::$gateways = $gateways;
	}

	/**
	 * The method setting (Econt, Speedy, Pigeon Express). Its help text comes from Setting_Help.
	 * The shipping methods are also built while payment gateways load (cash on delivery lists them),
	 * so the payment methods are only read once loaded, never loaded from here.
	 *
	 * @return array
	 */
	public static function field() {
		$options = array();

		foreach ( self::$gateways ? self::$gateways->payment_gateways() : array() as $id => $gateway ) {
			if ( wc_string_to_bool( $gateway->enabled ) ) {
				$options[ $id ] = $gateway->get_title() ? $gateway->get_title() : $id;
			}
		}

		return array(
			'title'   => __( 'Customer pays the courier', 'nashenski-commerce' ),
			'type'    => 'multiselect',
			'class'   => 'wc-enhanced-select',
			'css'     => 'width: 400px;',
			// New methods: cash on delivery customers pay the courier, as they always did.
			'default' => array( 'cod' ),
			'options' => $options,
		);
	}

	/**
	 * Whether the customer pays the courier with this payment method.
	 *
	 * @param mixed  $listed  The method's saved list.
	 * @param string $payment Payment method ID.
	 * @return bool
	 */
	public static function customer_pays( $listed, $payment ) {
		return '' !== (string) $payment && in_array( (string) $payment, (array) $listed, true );
	}

	/**
	 * The saved list of a method instance, read without loading the method.
	 *
	 * @param string $method_id   Method ID.
	 * @param int    $instance_id Instance ID.
	 * @return array
	 */
	public static function listed( $method_id, $instance_id ) {
		$settings = get_option( 'woocommerce_' . $method_id . '_' . $instance_id . '_settings', array() );

		// A method never saved since 0.21.8 has the setting's default, as WooCommerce reads it.
		return is_array( $settings ) && array_key_exists( self::FIELD, $settings ) ? (array) $settings[ self::FIELD ] : array( 'cod' );
	}

	/**
	 * Checkout: whether the customer pays the courier for this cart. A cart whose goods cost nothing
	 * (a free sample, a 100% coupon) cannot be paid, so the shop collects the delivery there.
	 *
	 * @param mixed  $listed  The method's saved list.
	 * @param string $payment Payment method ID.
	 * @return bool
	 */
	public static function customer_pays_cart( $listed, $payment ) {
		return self::customer_pays( $listed, $payment ) && (float) nashenski_get_package_total() > 0;
	}

	/**
	 * A rate the customer pays the courier for: it costs nothing and keeps the fee as information.
	 * A free or not yet priced rate stays as it is: there is nothing to pay the courier.
	 *
	 * @param array $rate Rate arguments for add_rate().
	 * @return array
	 */
	public static function rate( $rate ) {
		$fee = (float) ( $rate['cost'] ?? 0 ) + array_sum( array_map( 'floatval', (array) ( $rate['taxes'] ?? array() ) ) );

		if ( $fee <= 0 ) {
			return $rate;
		}

		$rate['meta_data'][ self::PAYER_META ] = 'customer';
		$rate['meta_data'][ self::FEE_META ]   = wc_format_decimal( $fee, wc_get_price_decimals() );
		// The courier priced it in the store currency; a currency switcher may show the order in another.
		$rate['meta_data'][ self::CURRENCY_META ] = get_option( 'woocommerce_currency' );
		$rate['cost']                             = 0;
		unset( $rate['taxes'] );

		return $rate;
	}

	/**
	 * Checkout: every Nashenski Commerce shipping line keeps who collects the delivery. Courier rates
	 * say "customer" themselves; BOX NOW and the rest are collected by the shop. Methods of other
	 * plugins are not stamped: they keep the "Is ... courier?" rule (owner decision H1).
	 *
	 * @param \WC_Order_Item_Shipping $item Order shipping line.
	 */
	public static function stamp( $item ) {
		if ( self::ours( $item ) && ! $item->get_meta( self::PAYER_META ) ) {
			$item->add_meta_data( self::PAYER_META, 'shop', true );
		}
	}

	/**
	 * Whether a shipping line is one of this plugin's methods.
	 *
	 * @param \WC_Order_Item_Shipping $item Shipping line.
	 * @return bool
	 */
	private static function ours( $item ) {
		return 0 === strpos( (string) $item->get_method_id(), 'nashenski_' );
	}

	/**
	 * Who collects the delivery of a shipping line, for an order made outside the checkout, by the
	 * checkout's rules: Bulgaria only; free shipping (free over a total, a free shipping coupon) and
	 * goods that cost nothing are the shop's; Speedy and Pigeon Express with a fixed price carry it
	 * in the cash on delivery amount, so the shop collects it.
	 *
	 * @param string $method_id   Method ID.
	 * @param int    $instance_id Instance ID.
	 * @param string $payment     Payment method ID.
	 * @param string $country     Delivery country.
	 * @param float  $goods       Goods and fees with tax.
	 * @param bool   $free_coupon Whether the order has a free shipping coupon.
	 * @return string "customer" or "shop".
	 */
	public static function decision( $method_id, $instance_id, $payment, $country, $goods = 1.0, $free_coupon = false ) {
		if ( 'BG' !== $country || ! in_array( $method_id, self::METHODS, true ) || (float) $goods <= 0 || $free_coupon ) {
			return 'shop';
		}

		$settings = get_option( 'woocommerce_' . $method_id . '_' . $instance_id . '_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		if ( 'nashenski_econt' !== $method_id && (float) ( $settings['fixed_price'] ?? 0 ) > 0 ) {
			return 'shop';
		}

		if ( (float) ( $settings['free_shipping_over'] ?? 0 ) > 0 && (float) $goods >= (float) $settings['free_shipping_over'] ) {
			return 'shop';
		}

		return self::customer_pays( self::listed( $method_id, $instance_id ), $payment ) ? 'customer' : 'shop';
	}

	/**
	 * Quick and admin orders (D10): store who collects the delivery of this plugin's shipping lines
	 * once the order has a shipping line and a payment method, and again when the payment method
	 * changed. Checkout orders keep their checkout decision (their amounts and waybill were made for
	 * it); when staff change their payment method so that the decision would differ, the "check by
	 * hand" text is returned once. An order with documents keeps its decision. Amounts are not
	 * changed. Runs on saving the order and before its first document number.
	 *
	 * @param \WC_Order|false $order Order.
	 * @return string The notice for staff, or ''.
	 */
	public static function decide( $order ) {
		if ( ! $order instanceof \WC_Order || $order->get_meta( 'nashenski_order_number' ) ) {
			return '';
		}

		$payment = (string) $order->get_payment_method();
		$country = $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country();
		$notice  = '';

		if ( '' === $payment ) {
			return '';
		}

		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( ! self::ours( $item ) ) {
				continue;
			}

			$stamped  = (string) $item->get_meta( self::PAYER_META );
			$basis    = (string) $item->get_meta( self::BASIS_META );
			$decision = self::decision( $item->get_method_id(), $item->get_instance_id(), $payment, $country, self::goods( $order ), self::free_coupon( $order ) );

			// A checkout stamp has no basis: never decided again here. Staff are told once when the payment method is no longer the checkout's.
			if ( '' !== $stamped && '' === $basis ) {
				$paid    = (string) ( ( (array) $item->get_meta( 'cookie_data' ) )['payment'] ?? '' );
				$changed = '' !== $paid ? $paid !== $payment : $decision !== $stamped;

				if ( $changed && $payment !== (string) $order->get_meta( self::WARNED_META ) ) {
					$order->update_meta_data( self::WARNED_META, $payment );
					$order->save_meta_data();
					$notice = __( 'The payment method changed after checkout. Who pays the courier stays as decided at checkout: check the delivery line and the waybill by hand.', 'nashenski-commerce' );
				}

				continue;
			}

			if ( '' !== $stamped && $basis === $payment ) {
				continue;
			}

			$item->update_meta_data( self::PAYER_META, $decision );
			$item->update_meta_data( self::BASIS_META, $payment );
			$item->save();
		}

		return $notice;
	}

	/**
	 * Goods and fees of an order, with tax: what free shipping over a total compares.
	 *
	 * @param \WC_Order $order Order.
	 * @return float
	 */
	private static function goods( $order ) {
		$total = 0;

		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
			$total += (float) $item->get_total() + (float) $item->get_total_tax();
		}

		return $total;
	}

	/**
	 * Whether an order has a free shipping coupon.
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	private static function free_coupon( $order ) {
		foreach ( $order->get_coupon_codes() as $code ) {
			if ( ( new \WC_Coupon( $code ) )->get_free_shipping() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Hook wrapper of decide(): the notice goes to the order screen.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function decide_order( $order_id ) {
		$notice = self::decide( wc_get_order( $order_id ) );

		if ( '' !== $notice && class_exists( '\WC_Admin_Meta_Boxes' ) ) {
			\WC_Admin_Meta_Boxes::add_error( $notice );
		}
	}

	/**
	 * Label boxes: the warning shown when staff choose the receiver as payer on an order whose
	 * delivery the shop collected, or ''.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	public static function receiver_notice( $order ) {
		return 'shop' === self::payer( $order ) && (float) $order->get_shipping_total() > 0
			? __( 'You collected the delivery on this order. If the receiver pays the courier too, the customer pays the delivery twice.', 'nashenski-commerce' )
			: '';
	}

	/**
	 * Before a label box sends its waybill: refuses one where the customer pays the shop for the
	 * delivery ($mode "fixed") on an order whose documents already leave the delivery out, because
	 * the customer pays the courier. Returns the refusal, or ''.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $mode  "sender", "receiver" or "fixed" (the customer pays the shop for the delivery).
	 * @return string
	 */
	public static function label_refusal( $order, $mode ) {
		if ( 'fixed' === $mode && 'customer' === self::payer( $order ) && $order->get_meta( 'nashenski_order_number' ) ) {
			return __( 'This order already has documents without the delivery, because the customer pays the courier. A waybill where you collect the delivery would not match them.', 'nashenski-commerce' );
		}

		return '';
	}

	/**
	 * After the courier accepted a waybill made in a label box. While the order has no documents,
	 * "fixed" (the customer pays the shop for the delivery) makes a customer-pays order one the shop
	 * collected, and a later "receiver" waybill puts it back. "sender" changes no decision: the shop
	 * absorbs the fee, so nothing is shown as paid to the courier.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $mode  "sender", "receiver", "fixed", or '' for a label not made in the box.
	 */
	public static function label_made( $order, $mode ) {
		$payer = self::payer( $order );
		$lines = array_filter( $order->get_items( 'shipping' ), array( __CLASS__, 'ours' ) );

		if ( 'sender' === $mode && 'customer' === $payer ) {
			foreach ( $lines as $item ) {
				$item->delete_meta_data( self::FEE_META );
				$item->save();
			}

			return;
		}

		if ( $order->get_meta( 'nashenski_order_number' ) ) {
			return;
		}

		if ( 'fixed' === $mode && 'customer' === $payer ) {
			foreach ( $lines as $item ) {
				$item->update_meta_data( self::PAYER_META, 'shop' );
				$item->delete_meta_data( self::FEE_META );
				$item->save();
			}

			$order->update_meta_data( self::SWITCHED_META, 'yes' );
			$order->save_meta_data();
		} elseif ( 'receiver' === $mode && 'shop' === $payer && 'yes' === $order->get_meta( self::SWITCHED_META ) ) {
			// The delivery leaves the total again, as at checkout.
			foreach ( $lines as $item ) {
				$item->update_meta_data( self::PAYER_META, 'customer' );
				$item->set_total( 0 );
				$item->set_taxes( false );
				$item->save();
			}

			$order->delete_meta_data( self::SWITCHED_META );
			$order->calculate_totals();
			$order->save();
		}
	}

	/**
	 * A waybill made on the order screen brings the delivery price. A customer-pays order keeps it as
	 * information; a checkout order the shop collected keeps its total. True when the order total
	 * must stay as it is.
	 *
	 * @param \WC_Order $order Order.
	 * @param mixed     $fee   Price the courier charges, with VAT.
	 * @return bool
	 */
	public static function keep_total( $order, $fee ) {
		$payer = self::payer( $order );

		if ( 'customer' === $payer ) {
			// Never a 0.00 fee "paid to the courier".
			foreach ( (float) $fee > 0 ? $order->get_items( 'shipping' ) : array() as $item ) {
				$item->update_meta_data( self::FEE_META, wc_format_decimal( $fee, wc_get_price_decimals() ) );
				$item->update_meta_data( self::CURRENCY_META, get_option( 'woocommerce_currency' ) );
				$item->save();
				break;
			}

			return true;
		}

		return 'shop' === $payer && self::from_checkout( $order );
	}

	/**
	 * Whether the checkout decided who pays the courier (its amounts and waybill were set then).
	 *
	 * @param \WC_Order $order Order.
	 * @return bool
	 */
	public static function from_checkout( $order ) {
		// The checkout stamps without a basis; the order screen always records one.
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( '' !== (string) $item->get_meta( self::PAYER_META ) ) {
				return '' === (string) $item->get_meta( self::BASIS_META );
			}
		}

		return false;
	}

	/**
	 * The decision stored at checkout: "customer", "shop", or "" for an order without one (made
	 * before 0.21.8, or a quick or admin order). A refund follows its order.
	 *
	 * @param \WC_Order|\WC_Order_Refund|false $order Order or refund.
	 * @return string
	 */
	public static function payer( $order ) {
		if ( is_a( $order, 'WC_Order_Refund' ) ) {
			$order = wc_get_order( $order->get_parent_id() );
		}

		foreach ( $order ? $order->get_items( 'shipping' ) : array() as $item ) {
			$payer = (string) $item->get_meta( self::PAYER_META );

			if ( '' !== $payer ) {
				return $payer;
			}
		}

		return '';
	}

	/**
	 * The one rule for documents and the NRA file: did the shop collect the delivery on this order?
	 * A refund (credit note) follows its order.
	 *
	 * @param \WC_Order|\WC_Order_Refund|false $order Order or refund.
	 * @return bool
	 */
	public static function shop_collects( $order ) {
		$payer = self::payer( $order );

		if ( '' !== $payer ) {
			return 'customer' !== $payer;
		}

		// No decision on the order: the "Is ... courier?" rule (the update turned the retired "Remove delivery from documents" into it), so documents and NRA files of these orders stay as they were.
		if ( is_a( $order, 'WC_Order_Refund' ) ) {
			$order = wc_get_order( $order->get_parent_id() );
		}

		if ( ! $order || 'cod' !== $order->get_payment_method() ) {
			return true;
		}

		$methods = $order->get_items( 'shipping' );
		$method  = reset( $methods );

		return ! ( $method && 'yes' === nashenski_get_option( 'shippings', $method->get_method_id() . '_is_courier' ) );
	}

	/**
	 * "6,50 €, paid to the courier on delivery, not included in the total".
	 *
	 * @param mixed  $fee      Fee with VAT.
	 * @param string $currency Currency; the store's when empty.
	 * @return string HTML.
	 */
	public static function text( $fee, $currency = '' ) {
		/* translators: %s: delivery price, for example 6,50 € */
		return sprintf( __( '%s, paid to the courier on delivery, not included in the total', 'nashenski-commerce' ), wc_price( $fee, array( 'currency' => $currency ) ) );
	}

	/**
	 * The fee of an order the customer pays the courier for, or ''.
	 *
	 * @param \WC_Order $order Order.
	 * @return string
	 */
	private static function order_fee( $order ) {
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( 'customer' === $item->get_meta( self::PAYER_META ) && (float) $item->get_meta( self::FEE_META ) > 0 ) {
				return (string) $item->get_meta( self::FEE_META );
			}
		}

		return '';
	}

	/**
	 * The currency of a line's fee: the one stored with it, else the order's.
	 *
	 * @param \WC_Order_Item_Shipping $item  Shipping line.
	 * @param \WC_Order|null          $order Order.
	 * @return string
	 */
	private static function fee_currency( $item, $order ) {
		$currency = (string) $item->get_meta( self::CURRENCY_META );

		return '' !== $currency ? $currency : ( $order ? $order->get_currency() : '' );
	}

	/**
	 * Emails, the thank-you page and My Account: "Econt: 6,50 €, paid to the courier on delivery, not included in the total".
	 *
	 * @param string    $shipping Shipping text.
	 * @param \WC_Order $order    Order.
	 * @return string
	 */
	public static function order_shipping( $shipping, $order ) {
		$fee = $order instanceof \WC_Order ? self::order_fee( $order ) : '';

		if ( '' === $fee ) {
			return $shipping;
		}

		$items = $order->get_items( 'shipping' );

		return $shipping . ': ' . self::text( $fee, self::fee_currency( reset( $items ), $order ) );
	}

	/**
	 * Order screen: the fee line of a shipping line reads "Delivery".
	 *
	 * @param string $key  Shown key.
	 * @param object $meta Meta.
	 * @return string
	 */
	public static function meta_key( $key, $meta ) {
		return self::FEE_META === ( $meta->key ?? '' ) ? __( 'Delivery', 'nashenski-commerce' ) : $key;
	}

	/**
	 * Order screen: the fee with its info text.
	 *
	 * @param string $value Shown value.
	 * @param object $meta  Meta.
	 * @param mixed  $item  Order item.
	 * @return string
	 */
	public static function meta_value( $value, $meta, $item = null ) {
		if ( self::FEE_META !== ( $meta->key ?? '' ) ) {
			return $value;
		}

		// Only a line whose customer pays the courier says "paid to the courier".
		if ( ! is_object( $item ) || ! method_exists( $item, 'get_meta' ) || 'customer' !== $item->get_meta( self::PAYER_META ) ) {
			return $value;
		}

		$order = method_exists( $item, 'get_order' ) ? $item->get_order() : null;

		return self::text( $meta->value, self::fee_currency( $item, $order ? $order : null ) );
	}
}
