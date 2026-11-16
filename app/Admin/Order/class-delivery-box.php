<?php
/**
 * Delivery box for a quick order: the owner picks the shipping option on the order screen.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Order;

use Nashenski\Front_End\Checkout\Pickers;
use Nashenski\Front_End\Quick_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Delivery for a quick order: the shopper chose no courier, so the owner picks the shipping option
 * and the office, locker or address on the order screen, with the same pickers as the checkout.
 * Saving adds the courier's shipping line with the same data a checkout order has, so the courier
 * box (label, documents) appears. The price comes when the label is made.
 */
class Delivery_Box {
	const NONCE = 'nashenski_delivery';

	/**
	 * Hooks the meta box, the save and the delivery price email.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ), 20 );
		// Before the invoice made on save (60) and who pays the courier (70).
		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save' ), 55 );

		foreach ( \Nashenski\Plugin::COURIERS as $courier ) {
			add_action( "nashenski_{$courier}_after_send_label", array( __CLASS__, 'email_delivery_price' ), 10, 2 );
		}
	}

	/**
	 * The quick order shopper saw no delivery price: once a label sets it, send the order details
	 * email (WooCommerce "Order details"), with the delivery price and the new total. Again only
	 * when the price changes.
	 *
	 * @param array          $data  Courier label response.
	 * @param \WC_Order|null $order Order.
	 * @return void
	 */
	public static function email_delivery_price( $data, $order ) {
		$order = $order ? wc_get_order( $order->get_id() ) : false;

		if ( ! $order || ! $order->get_meta( Quick_Order::META_CONSENT ) || ! $order->get_billing_email() ) {
			return;
		}

		$price = wc_format_decimal( $order->get_shipping_total() + $order->get_shipping_tax(), wc_get_price_decimals() );

		if ( $price <= 0 || $order->get_meta( '_nashenski_delivery_price_emailed' ) === $price ) {
			return;
		}

		$emails = WC()->mailer()->get_emails();

		if ( empty( $emails['WC_Email_Customer_Invoice'] ) ) {
			return;
		}

		// Marked first, so a second label request at the same time does not email again.
		$order->update_meta_data( '_nashenski_delivery_price_emailed', $price );
		$order->save();
		$emails['WC_Email_Customer_Invoice']->trigger( $order->get_id(), $order );
		$order->add_order_note(
			sprintf(
			/* translators: 1: delivery price, 2: order total */
				__( 'Delivery price %1$s and the new total %2$s emailed to the customer.', 'nashenski-commerce' ),
				html_entity_decode( wp_strip_all_tags( wc_price( $price, array( 'currency' => $order->get_currency() ) ) ) ),
				html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) )
			)
		);
		$order->save();
	}

	/**
	 * The order on the edit screen.
	 *
	 * @return \WC_Order|false
	 */
	public static function order() {
		global $post;

		if ( isset( $GLOBALS['theorder'] ) && is_object( $GLOBALS['theorder'] ) ) {
			return $GLOBALS['theorder'];
		}

		return $post ? wc_get_order( $post->ID ) : false;
	}

	/**
	 * Only quick orders that have no courier shipping line yet.
	 *
	 * @param mixed $order Order on the screen.
	 * @return bool
	 */
	private static function applies( $order ) {
		if ( ! $order instanceof \WC_Order || ! $order->get_meta( Quick_Order::META_CONSENT ) ) {
			return false;
		}

		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( Pickers::courier_of( $item->get_method_id() ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The courier shipping options of the store's zones: rate id => array( courier, type, title, label ).
	 * Office options of a courier without a login and without a public list are left out, as at checkout.
	 */
	public static function options() {
		$options = array();

		$names = nashenski_couriers();

		foreach ( Pickers::rate_types() as $id => $rate ) {
			$courier = $rate['courier'];

			if ( 'yes' !== nashenski_get_option( 'apis', 'enable_' . $courier ) ) {
				continue;
			}

			if ( 'address' !== $rate['type'] && ! Pickers::connected( $courier ) && ! Pickers::public_offices( $courier ) ) {
				continue;
			}

			$method        = \WC_Shipping_Zones::get_shipping_method( (int) substr( $id, strpos( $id, ':' ) + 1 ) );
			$rate['title'] = $method ? $method->get_title() : $id;
			// Method titles such as "To an office" repeat between couriers, so the list names the courier.
			$rate['label']  = false === stripos( $rate['title'], $names[ $courier ] ) ? $names[ $courier ] . ': ' . $rate['title'] : $rate['title'];
			$options[ $id ] = $rate;
		}

		return $options;
	}

	/**
	 * Adds the delivery box to the order screen when it applies.
	 *
	 * @return void
	 */
	public static function add_meta_box() {
		$order = self::order();

		if ( ! self::applies( $order ) ) {
			return;
		}

		$screen = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		add_meta_box( 'nashenski_delivery', __( 'Delivery', 'nashenski-commerce' ), array( __CLASS__, 'render' ), $screen, 'normal', 'high' );
	}

	/**
	 * Prints the delivery box.
	 *
	 * @return void
	 */
	public static function render() {
		$order   = self::order();
		$options = self::options();

		if ( ! $options ) {
			echo '<p>' . esc_html__( 'No courier shipping option is set up. Add one under WooCommerce > Settings > Shipping.', 'nashenski-commerce' ) . '</p>';

			return;
		}

		$url = nashenski()->plugin_dir_url() . 'assets/';
		$dir = nashenski()->plugin_dir_path() . 'assets/';

		wp_enqueue_style( 'nashenski-checkout', $url . 'css/checkout.css', array(), \Nashenski\Plugin::VERSION . '-' . filemtime( $dir . 'css/checkout.css' ) );
		// The picker lists open below the box: keep them above the boxes that follow.
		wp_add_inline_style( 'nashenski-checkout', '#nashenski_delivery, #nashenski_delivery .inside { overflow: visible; } #nashenski_delivery { position: relative; z-index: 20; }' );
		wp_enqueue_script( 'nashenski-pickers', $url . 'js/pickers.js', array( 'wp-element' ), \Nashenski\Plugin::VERSION . '-' . filemtime( $dir . 'js/pickers.js' ), true );
		// On the order screen the town is always asked: the quick order form may not have it. The lists
		// stay as the courier sends them (no locale), whatever the language of the user's profile.
		wp_localize_script(
			'nashenski-pickers',
			'nashenskiPickers',
			array_merge(
				Pickers::config(),
				array(
					'townPicker' => true,
					'locale'     => '',
					'latin'      => false,
				)
			)
		);
		wp_enqueue_script( 'nashenski-pickers-admin', $url . 'js/pickers-admin.js', array( 'nashenski-pickers' ), \Nashenski\Plugin::VERSION . '-' . filemtime( $dir . 'js/pickers-admin.js' ), true );

		$data = array(
			'address' => array(
				'first_name' => $order->get_billing_first_name(),
				'last_name'  => $order->get_billing_last_name(),
				'phone'      => $order->get_billing_phone(),
				'city'       => $order->get_billing_city(),
				'state'      => $order->get_billing_state(),
				'postcode'   => $order->get_billing_postcode(),
				'country'    => $order->get_billing_country() ? $order->get_billing_country() : 'BG',
			),
			// A quick order has no payment method yet; most are paid on delivery.
			'payment' => $order->get_payment_method() ? $order->get_payment_method() : 'cod',
		);
		?>
		<div id="nashenski-delivery" data-order="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">
			<p><?php esc_html_e( 'Choose how the order is delivered, then the town and the office, locker or address. Save the order to add the shipping line; the courier box for the label appears after saving.', 'nashenski-commerce' ); ?></p>
			<p>
				<label for="nashenski-delivery-rate"><?php esc_html_e( 'Shipping option', 'nashenski-commerce' ); ?></label><br>
				<select id="nashenski-delivery-rate" name="nashenski_delivery_rate">
					<option value=""><?php esc_html_e( 'Choose', 'nashenski-commerce' ); ?></option>
					<?php foreach ( $options as $id => $option ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" data-courier="<?php echo esc_attr( $option['courier'] ); ?>" data-type="<?php echo esc_attr( $option['type'] ); ?>"><?php echo esc_html( $option['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<div class="nashenski-delivery-picker nashenski-additional-fields"></div>
			<input type="hidden" name="nashenski_delivery_choice" value="">
			<?php wp_nonce_field( self::NONCE, 'nashenski_delivery_nonce' ); ?>
			<p><button type="submit" class="button button-primary" name="save" value="save"><?php esc_html_e( 'Save delivery', 'nashenski-commerce' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Adds the courier shipping line from the choice, in place of any other shipping line.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function save( $order_id ) {
		if ( ! isset( $_POST['nashenski_delivery_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nashenski_delivery_nonce'] ) ), self::NONCE ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		if ( empty( $_POST['nashenski_delivery_rate'] ) ) {
			return;
		}

		$order   = wc_get_order( $order_id );
		$rate_id = sanitize_text_field( wp_unslash( $_POST['nashenski_delivery_rate'] ) );
		$options = self::options();
		// Sanitized as text before decoding (the picker writes < > % as JSON escapes, so nothing is cut out of it) and value by value after.
		$choice = isset( $_POST['nashenski_delivery_choice'] ) ? map_deep( json_decode( sanitize_text_field( wp_unslash( $_POST['nashenski_delivery_choice'] ) ), true ), 'sanitize_text_field' ) : null;

		if ( ! self::applies( $order ) || ! isset( $options[ $rate_id ] ) ) {
			return;
		}

		$option = $options[ $rate_id ];
		$choice = is_array( $choice ) ? $choice : array();

		if ( ! self::complete( $choice, $option ) ) {
			\WC_Admin_Meta_Boxes::add_error( __( 'Delivery was not saved: choose the town and the office, locker or address first.', 'nashenski-commerce' ) );

			return;
		}

		foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
			$order->remove_item( $item_id );
		}

		$courier = $option['courier'];
		$item    = new \WC_Order_Item_Shipping();
		$item->set_method_title( $option['title'] );
		$item->set_method_id( 'nashenski_' . $courier );
		$item->set_instance_id( (int) substr( $rate_id, strpos( $rate_id, ':' ) + 1 ) );
		$item->set_total( 0 );
		$item->add_meta_data( 'delivery_type', $option['type'], true );
		$item->add_meta_data( 'cookie_data', $choice, true );
		$order->add_item( $item );
		$order->update_meta_data( "nashenski_{$courier}_cookie_data", $choice );

		// The town chosen in the picker goes into the shipping address, as checkout does.
		foreach ( array( 'city', 'state', 'postcode', 'country' ) as $field ) {
			if ( ! empty( $choice[ $field ] ) ) {
				$order->{"set_shipping_$field"}( $choice[ $field ] );

				if ( ! $order->{"get_billing_$field"}() ) {
					$order->{"set_billing_$field"}( $choice[ $field ] );
				}
			}
		}

		$order->set_shipping_first_name( $order->get_billing_first_name() );
		$order->set_shipping_last_name( $order->get_billing_last_name() );
		$order->set_shipping_phone( $order->get_billing_phone() );

		// The label boxes offer cash on delivery and fill the shipping price only on a cod order, as from checkout.
		if ( ! $order->get_payment_method() && 'cod' === ( $choice['payment'] ?? '' ) ) {
			$gateways = WC()->payment_gateways()->payment_gateways();
			$order->set_payment_method( isset( $gateways['cod'] ) ? $gateways['cod'] : 'cod' );
		}

		$order->calculate_totals();
		$order->add_order_note(
			sprintf(
			/* translators: 1: shipping option, 2: office, locker or address */
				__( 'Delivery set on the order screen: %1$s, %2$s. The price comes with the label.', 'nashenski-commerce' ),
				$option['title'],
				Pickers::describe_choice( $choice, $option['type'] )
			)
		);
		$order->save();
	}

	/**
	 * Whether the picker finished: the type matches and an office, locker or street was chosen.
	 *
	 * @param array $choice Picker choice.
	 * @param array $option Delivery option.
	 * @return bool
	 */
	private static function complete( $choice, $option ) {
		if ( 'boxnow' === $option['courier'] ) {
			return ! empty( $choice['selectedApm'] );
		}

		if ( ( $choice['type'] ?? '' ) !== $option['type'] ) {
			return false;
		}

		if ( 'address' === $option['type'] ) {
			return ! empty( $choice['city'] ) && ( ! empty( $choice['selectedAddress'] ) || ! empty( $choice['streetNumber'] ) );
		}

		return ! empty( $choice['selectedOffice'] ) || ! empty( $choice['selectedLocker'] );
	}
}
