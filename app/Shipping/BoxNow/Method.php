<?php
/**
 * BOX NOW shipping method.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\BoxNow;

use Nashenski\Container\Client;
use Nashenski\Pricing\Price_Lists;

defined( 'ABSPATH' ) || exit;

/**
 * BOX NOW shipping method: the rate, the price rules and the parcel locker size checks.
 */
class Method extends \WC_Shipping_Method {
	const METHOD_ID = 'nashenski_boxnow';

	/**
	 * Container.
	 *
	 * @var mixed
	 */
	public $container;

	/**
	 * Cookie data.
	 *
	 * @var mixed
	 */
	public $cookie_data;

	/**
	 * Package.
	 *
	 * @var array
	 */
	public $package;

	/**
	 * Cost between 1.
	 *
	 * @var mixed
	 */
	public $cost_between_1;

	/**
	 * Cost between 2.
	 *
	 * @var mixed
	 */
	public $cost_between_2;

	/**
	 * Cost between 3.
	 *
	 * @var mixed
	 */
	public $cost_between_3;

	/**
	 * Cost between 4.
	 *
	 * @var mixed
	 */
	public $cost_between_4;

	/**
	 * Free shipping over.
	 *
	 * @var mixed
	 */
	public $free_shipping_over;

	/**
	 * Tax status.
	 *
	 * @var string
	 */
	public $tax_status;

	/**
	 * Whether this rate is free.
	 *
	 * @var bool
	 */
	public $free_shipping = false;

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Shipping zone instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->container          = nashenski()->container();
		$this->id                 = self::METHOD_ID;
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Nashenski Commerce - BOX NOW', 'nashenski-commerce' ); // Title shown in admin.
		$this->method_description = __( 'Enables BOX NOW delivery.', 'nashenski-commerce' ); // Description shown in admin.
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);
		$this->enabled            = 'yes';

		$this->init();
	}

	/**
	 * Init your settings
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		// Load the settings API.
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings.
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		$this->title              = $this->get_option( 'title' );
		$this->free_shipping_over = Price_Lists::non_negative( nashenski_get_option( 'boxnow_price', 'free_shipping_over' ) );
		$this->tax_status         = ( wc_tax_enabled() ) ? 'taxable' : 'none';

		// Save settings in admin if you have any defined.
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Calculate the shipping rate.
	 *
	 * @access public
	 * @param mixed $package Package.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ) {
		$this->cookie_data = self::get_cookie_data();
		$this->package     = $package;
		$weight            = $this->get_total_weight();
		$apm_size          = self::get_allowed_apm_size();
		$hide_over         = nashenski_get_option( 'boxnow_price', 'hide_over' );

		if (
			$weight > 20 ||
			$apm_size['has_oversize_item'] ||
			( $hide_over && nashenski_get_package_total() >= $hide_over )
		) {
			return;
		}

		if ( ! empty( $this->cookie_data['payment'] ) && 'cod' === $this->cookie_data['payment'] && $this->has_more_than_one_box() ) {
			return;
		}

		do_action( 'nashenski/boxnow/rate/before_calculate', $this );

		$country = apply_filters( 'nashenski/boxnow/country_for_validation', $package['destination']['country'], $this );

		$rate = array(
			'label'     => $this->title,
			'cost'      => 0,
			'meta_data' => array(),
		);

		$price_type = nashenski_get_option( 'boxnow_price', 'price_type' );

		if ( (float) $this->free_shipping_over > 0 && nashenski_get_package_total() >= $this->free_shipping_over ) {
			$rate['meta_data']['free_shipping'] = true;
			$this->free_shipping                = true;
		} elseif ( $price_type ) {
			$price = 0;

			switch ( $price_type ) {
				case 'from_to_kg':
					$price = $this->get_price_from_to_kg_option();
					break;
				case 'from_to_order_total':
					$price = $this->get_price_from_to_order_total_option();
					break;
				case 'from_to_kg_and_order_total':
					$price = $this->get_price_from_to_combined();
					break;
			}

			if ( (float) $price > 0 ) {
				$tax_rate     = apply_filters( 'nashenski/shipping/fixed_price_tax_rate', 20, $country );
				$rate['cost'] = nashenski_tax_based_price( $price, $tax_rate );

				if ( wc_tax_enabled() ) {
					$rate['taxes'] = nashenski_get_shipping_rate_taxes( $rate['cost'], $country );
				}
			}
		}

		$rate['meta_data']['cookie_data'] = $this->cookie_data;
		$rate                             = apply_filters( 'nashenski/boxnow/rate', $rate, $this );

		if ( '0.00' === $rate['cost'] && ! $this->free_shipping ) {
			return;
		}

		// Register the rate.
		$this->add_rate( $rate );
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title' => array(
				'title'       => __( 'Title', 'nashenski-commerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'nashenski-commerce' ),
				'default'     => __( 'BOX NOW', 'nashenski-commerce' ),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Whether the method is offered for the package destination.
	 *
	 * @param array $package Package.
	 * @return bool Result.
	 */
	public function is_available( $package ) {
		return in_array( $package['destination']['country'], apply_filters( 'nashenski/boxnow/available_countries', array( 'BG' ) ), true );
	}

	/**
	 * The customer's BOX NOW choice from the checkout cookie.
	 *
	 * @return array|string Cookie data, or an empty string.
	 */
	public static function get_cookie_data() {
		return nashenski_courier_cookie( 'nashenski--boxnow-apm' );
	}

	/**
	 * Whether the package needs more than one box.
	 *
	 * @return bool Result.
	 */
	public function has_more_than_one_box() {
		$current_volume = 0;

		foreach ( $this->package['contents'] as $order_item ) {
			$quantity = $order_item['quantity'] ?? 0;

			if ( ! is_numeric( $quantity ) || ! is_finite( (float) $quantity ) ) {
				return true;
			}

			if ( $quantity <= 0 ) {
				continue;
			}

			// Match the former per-unit loop for positive fractional quantities,
			// without allocating one array entry per unit.
			$quantity = ceil( (float) $quantity );
			$_product = $order_item['data'];

			$unit   = get_option( 'woocommerce_dimension_unit' );
			$height = $_product->get_height() ? wc_get_dimension( (float) $_product->get_height(), 'cm', $unit ) : 7;
			$width  = $_product->get_width() ? wc_get_dimension( (float) $_product->get_width(), 'cm', $unit ) : 44;
			$length = $_product->get_length() ? wc_get_dimension( (float) $_product->get_length(), 'cm', $unit ) : 58;

			$item_sizes      = self::determine_item_size( $height, $width, $length );
			$current_volume += $item_sizes['volume'] * $quantity;

			if ( ! is_finite( $current_volume ) || $item_sizes['oversize'] || $item_sizes['volume'] > 89320 || $current_volume > 89320 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Total weight of the package in kg.
	 *
	 * @return float|int Weight.
	 */
	public function get_total_weight() {
		$weight = 0;

		foreach ( $this->package['contents'] as $key => $item ) {
			if ( $item['data']->get_weight() ) {
				$weight += wc_get_weight( $item['data']->get_weight(), 'kg' ) * $item['quantity'];
			}
		}

		if ( ! $weight ) {
			$weight = apply_filters( 'nashenski/boxnow/method/weight', 1, $this->package, $this );
		}

		return $weight;
	}

	/**
	 * Price from the weight rows of the settings.
	 *
	 * @return mixed Price, or nothing when there are no rows.
	 */
	public function get_price_from_to_kg_option() {
		$price  = 0;
		$weight = $this->get_total_weight();

		$from_to_kg = nashenski_get_option( 'boxnow_price', 'from_to_kg' );

		if ( $from_to_kg ) {
			$from_to_kg = json_decode( $from_to_kg, 1 );

			if ( ! $from_to_kg || empty( array_filter( $from_to_kg ) ) ) {
				return;
			}

			foreach ( $from_to_kg as $row ) {
				if ( ! $row['from'] ) {
					$row['from'] = 0;
				}

				if ( ! $row['to'] ) {
					$row['to'] = 20;
				}

				if ( $row['from'] <= $weight && $row['to'] > $weight ) {
					$price = $row['price'];

					break;
				}
			}
		}

		return $price;
	}

	/**
	 * Price from the order total rows of the settings.
	 *
	 * @return mixed Price, or nothing when there are no rows.
	 */
	public function get_price_from_to_order_total_option() {
		$price = 0;
		$total = nashenski_get_package_total();

		$from_to_total = nashenski_get_option( 'boxnow_price', 'from_to_order_total' );

		if ( $from_to_total ) {
			$from_to_total = json_decode( $from_to_total, 1 );

			if ( ! $from_to_total || empty( array_filter( $from_to_total ) ) ) {
				return;
			}

			foreach ( $from_to_total as $row ) {
				if ( ! $row['to'] ) {
					$row['to'] = 999999;
				}

				if ( ! $row['from'] ) {
					$row['from'] = 0;
				}

				if ( $row['from'] <= $total && $row['to'] > $total ) {
					$price = $row['price'];

					break;
				}
			}
		}

		return $price;
	}

	/**
	 * Price from the weight and order total rows of the settings.
	 *
	 * @return mixed Price, or nothing when there are no rows.
	 */
	public function get_price_from_to_combined() {
		$price  = 0;
		$weight = $this->get_total_weight();
		$total  = nashenski_get_package_total();

		$from_to = nashenski_get_option( 'boxnow_price', 'from_to_kg_and_order_total' );

		if ( $from_to ) {
			$from_to = json_decode( $from_to, 1 );

			if ( ! $from_to || empty( array_filter( $from_to ) ) ) {
				return;
			}

			foreach ( $from_to as $row ) {
				if ( ! $row['to_price'] ) {
					$row['to_price'] = 999999;
				}

				if ( ! $row['from_price'] ) {
					$row['from_price'] = 0;
				}

				if ( ! $row['to'] ) {
					$row['to'] = 20;
				}

				if ( ! $row['from'] ) {
					$row['from'] = 0;
				}

				if (
					( $row['from'] <= $weight && $row['to'] > $weight ) &&
					( $row['from_price'] <= $total && $row['to_price'] > $total )
				) {
					$price = $row['price'];

					break;
				}
			}
		}

		return $price;
	}

	/**
	 * Checkout validation: BOX NOW needs a chosen locker.
	 *
	 * @param array     $fields Posted checkout fields.
	 * @param \WP_Error $errors Validation errors.
	 */
	public static function validate_boxnow_method( $fields, $errors ) {
		if ( ! WC()->cart->needs_shipping() ) {
			return;
		}

		$chosen_shippings = WC()->session->get( 'chosen_shipping_methods' );

		foreach ( $chosen_shippings as $key => $shipping ) {
			if ( strpos( $shipping, 'nashenski_boxnow' ) !== false ) {
				$data = nashenski_chosen_rate( $key, $shipping );

				if ( ! $data ) {
					continue;
				}

				if ( 'nashenski_boxnow' === $data->method_id ) {
					$cookie_data = self::get_cookie_data();

					if ( empty( $cookie_data['selectedApm'] ) ) {
						$errors->add( 'validation', __( 'Please choose a automat.', 'nashenski-commerce' ) );
					}
				}
			}
		}
	}

	/**
	 * Keep the customer's BOX NOW choice on the order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function save_label_data_to_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! empty( $order->get_items( 'shipping' ) ) ) {
			foreach ( $order->get_items( 'shipping' ) as $shipping ) {
				if ( 'nashenski_boxnow' === $shipping['method_id'] ) {
					$cookie_data = '';

					foreach ( $shipping->get_meta_data() as $meta_data ) {
						$data = $meta_data->get_data();

						if ( 'cookie_data' === $data['key'] ) {
							$cookie_data = $data['value'];
						}
					}

					if ( $cookie_data ) {
						$order->update_meta_data( 'nashenski_boxnow_cookie_data', $cookie_data );
					}

					$order->save();
					break;
				}
			}
		}
	}

	/**
	 * The largest locker size the cart needs, and whether an item is oversize.
	 *
	 * @return array Max size and oversize flag.
	 */
	public static function get_allowed_apm_size() {
		$max_size          = '0';
		$has_oversize_item = false;

		foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
			$_product  = $cart_item['data'];
			$item_data = self::determine_item_size(
				wc_get_dimension( (float) $_product->get_height(), 'cm', get_option( 'woocommerce_dimension_unit' ) ),
				wc_get_dimension( (float) $_product->get_width(), 'cm', get_option( 'woocommerce_dimension_unit' ) ),
				wc_get_dimension( (float) $_product->get_length(), 'cm', get_option( 'woocommerce_dimension_unit' ) )
			);

			if ( $item_data['oversize'] ) {
				$has_oversize_item = true;
			}

			$max_size = ( $item_data['size'] > $max_size ) ? $item_data['size'] : $max_size;
		}

		return array(
			'max_size'          => $max_size,
			'has_oversize_item' => $has_oversize_item,
		);
	}

	/**
	 * The box size and volume of one item.
	 *
	 * @param mixed $height Height in cm.
	 * @param mixed $width  Width in cm.
	 * @param mixed $length Length in cm.
	 * @return array Size, volume and oversize flag.
	 */
	public static function determine_item_size( $height, $width, $length ) {
		$max_diagonal = 80.78;
		$dimensions   = array(
			array(
				'box_size' => 3,
				'height'   => 35,
				'width'    => 44,
				'length'   => 58,
			),
			array(
				'box_size' => 2,
				'height'   => 16,
				'width'    => 44,
				'length'   => 58,
			),
			array(
				'box_size' => 1,
				'height'   => 7,
				'width'    => 44,
				'length'   => 58,
			),
		);

		$item = array(
			'oversize' => false,
			'volume'   => 0,
			'size'     => 0,
		);

		foreach ( $dimensions as $size ) {
			if ( ( ! is_numeric( $height ) || 0.0 === (float) $height ) ||
				( ! is_numeric( $width ) || 0.0 === (float) $width ) ||
				( ! is_numeric( $length ) || 0.0 === (float) $length )
			) {
				$item['size']   = 2;
				$item['volume'] = 40832;
			} elseif (
				$height <= $size['height'] &&
				$width <= $size['width'] &&
				$length <= $size['length']
			) {
				$item['size']   = $size['box_size'];
				$item['volume'] = $length * $width * $height;
			} elseif ( 3 === $size['box_size'] ) {
				$item['volume']   = $length * $width * $height;
				$item['max_side'] = max( $length, $width, $height );
				$item['size']     = 3;

				if ( $item['max_side'] > $max_diagonal ) {
					$item['oversize'] = true;
				}
			}
		}

		return $item;
	}

	/**
	 * The box size for a volume.
	 *
	 * @param mixed $volume Volume in cm3.
	 * @return int Box size.
	 */
	public static function determine_item_size_by_volume( $volume ) {
		$size = 2;

		if ( $volume <= '17864' ) {
			$size = 1;
		} elseif ( $volume <= 40832 ) {
			$size = 2;
		} elseif ( $volume <= 89320 ) {
			$size = 3;
		}

		return $size;
	}

	/**
	 * Adds the label numbers and tracking links to the customer emails.
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Whether the email goes to the admin.
	 * @param bool      $plain_text    Whether the email is plain text.
	 * @param \WC_Email $email         Email.
	 */
	public static function add_label_number_to_email( $order, $sent_to_admin, $plain_text, $email ) {
		$email_ids_to_send = array( 'customer_completed_order' );

		if ( nashenski_get_option( 'boxnow_send_from', 'label_after_checkout' ) === 'yes' ) {
			$email_ids_to_send[] = 'customer_processing_order';
			$email_ids_to_send[] = 'customer_on_hold_order';
		}

		$email_ids_to_send = apply_filters( 'nashenski/boxnow/emails_to_send_label_number', $email_ids_to_send );

		if ( ! in_array( $email->id, $email_ids_to_send, true ) ) {
			return;
		}

		$shipment_status = $order->get_meta( 'nashenski_boxnow_shipment_status' );

		if ( ! isset( $shipment_status ) || empty( $shipment_status['parcels'] ) ) {
			return;
		}

		$track_numbers = array();

		foreach ( $shipment_status['parcels'] as $parcel ) {
			$number = $parcel['id'];
			$url    = \Nashenski\Shipping\Automation::tracking_url( 'boxnow', $number );

			$track_numbers[] = sprintf(
				/* translators: 1: waybill number, 2: tracking link */
				__( 'Label number: %1$s. %2$s', 'nashenski-commerce' ),
				$number,
				sprintf(
					'<a href="%s" target="_blank">%s</a>',
					$url,
					__( 'Track your order.', 'nashenski-commerce' )
				)
			);
		}

		$track_number_text = implode( '<br>', $track_numbers );
		$track_number_text = apply_filters( 'nashenski/boxnow/track_number_text_in_email', $track_number_text, $order );

		echo wp_kses_post( wpautop( $track_number_text ) );
	}
}
