<?php
/**
 * The Pigeon Express box on the order screen: label, tracking and the AJAX actions behind it.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

use Nashenski\Container\Client;
use Nashenski\Shipping\Pigeon\Method;
use Nashenski\Shipping\Pigeon\Address;
use Nashenski\Shipping\Pigeon\Payment;
use Nashenski\Transliteration;

use function PHPSTORM_META\map;

defined( 'ABSPATH' ) || exit;

/**
 * The Pigeon Express box on the order screen: label, tracking and the AJAX actions behind it.
 */
class Pigeon {
	/**
	 * Dependency container.
	 *
	 * @var object|null
	 */
	private static $container = null;

	/**
	 * Hooks the order box, the AJAX actions and the minimum parcel weight.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );

		add_action( 'wp_ajax_nashenski_pigeon_generate_label', array( __CLASS__, 'generate_label' ) );
		add_action( 'wp_ajax_nashenski_pigeon_delete_label', array( __CLASS__, 'delete_label' ) );
		add_action( 'wp_ajax_nashenski_pigeon_update_shipment_status', array( __CLASS__, 'update_shipment_status' ) );
		add_action( 'wp_ajax_nashenski_pigeon_print_labels', array( __CLASS__, 'print_labels_endpoint' ) );

		add_filter( 'nashenski_pigeon_calculate_label', array( __CLASS__, 'set_min_package_weight' ), 30 );
	}

	/**
	 * Adds the Pigeon Express box to the order screen.
	 *
	 * @return void
	 */
	public static function add_meta_boxes() {
		nashenski_add_courier_meta_box( 'pigeon', __( 'Pigeon Express', 'nashenski-commerce' ), array( __CLASS__, 'meta_box' ) );
	}

	/**
	 * Prints the Pigeon Express box and gives the script its data.
	 *
	 * @return void
	 */
	public static function meta_box() {
		global $post;
		$theorder = isset( $GLOBALS['theorder'] ) ? $GLOBALS['theorder'] : null;

		self::$container = nashenski()->container();

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $post->ID );
		}

		if ( ! empty( $theorder->get_items( 'shipping' ) ) ) {
			foreach ( $theorder->get_items( 'shipping' ) as $shipping ) {
				if ( 'nashenski_pigeon' === $shipping['method_id'] ) {
					$label_data      = array();
					$cookie_data     = $theorder->get_meta( 'nashenski_pigeon_cookie_data' );
					$shipment_status = $theorder->get_meta( 'nashenski_pigeon_shipment_status' );
					$operations      = $theorder->get_meta( 'nashenski_pigeon_operations' );

					$label = $theorder->get_meta( 'nashenski_pigeon_label' );

					if ( $label ) {
						$label_data = $label;
					}

					if ( ! $cookie_data ) {
						foreach ( $shipping->get_meta_data() as $meta_data ) {
							$data = $meta_data->get_data();

							if ( 'nashenski_pigeon_cookie_data' === $data['key'] ) {
								$cookie_data = $data['value'];
							}
						}
					}

					$cookie_data = Payment::cookie( $cookie_data );
					// City and region come from the order's address (the one the street lists use), not from the
					// checkout data, which can be stale after the address was edited.
					if ( is_array( $cookie_data ) ) {
						$place                = self::receiver_place( $theorder );
						$cookie_data['city']  = $place['city'];
						$cookie_data['state'] = $place['state'];
					}

					try {
						$defaults = Payment::defaults( $theorder, $label_data ); } catch ( \InvalidArgumentException $error ) {
						echo '<p class="notice notice-error">' . esc_html( $error->getMessage() ) . '</p>';
						return; }
						echo '<div id="nashenski--pigeon-admin"></div>';
						$label_data = $defaults['label'];
						wp_localize_script(
							'nashenski-js-admin',
							'nashenski_pigeon',
							array(
								'label'             => $label_data,
								'shipmentStatus'    => $shipment_status,
								'operations'        => $operations,
								'cookie_data'       => $cookie_data,
								'fixedPrice'        => $defaults['fixedPrice'],
								'fixedPriceEnabled' => $defaults['fixedPriceEnabled'],
								'paymentType'       => $theorder->get_payment_method(),
								'sendFrom'          => self::get_send_from_data(),
								'offices'           => self::get_offices( $theorder ),
								'lockers'           => self::get_lockers( $theorder ),
								'streets'           => self::get_streets( $cookie_data, $theorder ),
								'orderId'           => $theorder->get_id(),
								'testOption'        => self::get_test_option( $label_data ),
								'i18n'              => self::get_i18n(),
								'nonce'             => wp_create_nonce( 'nashenski_admin_label' ),
							)
						);
					break;
				}
			}
		}
	}

	/**
	 * Where the parcel is sent from: the saved type, office and address.
	 *
	 * @return array
	 */
	protected static function get_send_from_data() {
		$container = nashenski()->container();
		$type      = nashenski_get_option( 'pigeon', 'send_from' );
		$data      = array(
			'type' => $type,
		);

		$data['currentOffice'] = nashenski_get_option( 'pigeon_send_from', 'office' );
		$data['offices']       = ( nashenski_get_option( 'pigeon_send_from', 'city' ) ) ? $container[ Client::PIGEON_OFFICES ]->get_formatted_offices( nashenski_get_option( 'pigeon_send_from', 'city' ) ) : array();

		$data['currentAddress'] = nashenski_get_option( 'pigeon_send_from', 'address' );

		return $data;
	}

	/**
	 * City and region the receiver is in: the shipping address when it differs from the billing one. When that
	 * address has no region, the other address is used, so an order with only one region filled in still finds its city.
	 *
	 * @param WC_Order $order Order.
	 * @return array City and state.
	 */
	public static function receiver_place( $order ) {
		$prefix = nashenski_is_different_shipping_address( $order ) ? 'shipping' : 'billing';
		$other  = 'shipping' === $prefix ? 'billing' : 'shipping';
		$place  = array(
			'city'  => $order->{"get_{$prefix}_city"}(),
			'state' => $order->{"get_{$prefix}_state"}(),
		);

		if ( '' === $place['state'] ) {
			$place = array(
				'city'  => $order->{"get_{$other}_city"}(),
				'state' => $order->{"get_{$other}_state"}(),
			);
		}

		return $place;
	}

	/**
	 * Pigeon city ID of the receiver, or false when the city is not found.
	 *
	 * @param WC_Order $order Order.
	 * @return mixed
	 */
	private static function get_city_id( $order ) {
		if ( ! self::$container ) {
			self::$container = nashenski()->container();
		}

		$city_id     = false;
		$place       = self::receiver_place( $order );
		$state       = $place['state'];
		$city        = Transliteration::latin2cyrillic( $place['city'] );
		$cities_data = self::$container[ Client::PIGEON_CITIES ]->get_filtered_cities( $city, $state );

		if ( false !== $cities_data['city_key'] ) {
			$city_id = $cities_data['cities_search_names'][ $cities_data['city_key'] ]['id'];
		}

		return $city_id;
	}

	/**
	 * Offices in the city of the receiver.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	protected static function get_offices( $order ) {
		$offices = array();
		$city_id = self::get_city_id( $order );

		if ( ! ( false === $city_id || null === $city_id ) ) {
			$offices = self::$container[ Client::PIGEON_OFFICES ]->get_formatted_offices( $city_id );
		}

		return $offices;
	}

	/**
	 * Lockers in the city of the receiver.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	protected static function get_lockers( $order ) {
		$lockers = array();
		$city_id = self::get_city_id( $order );

		if ( ! ( false === $city_id || null === $city_id ) ) {
			$lockers = self::$container[ Client::PIGEON_LOCKERS ]->get_formatted_lockers( $city_id );
		}

		return $lockers;
	}

	/**
	 * Streets in the city of the receiver.
	 *
	 * @param array    $cookie_data Checkout data of the order.
	 * @param WC_Order $order       Order.
	 * @return array
	 */
	protected static function get_streets( $cookie_data, $order ) {
		$streets     = array();
		$place       = self::receiver_place( $order );
		$state       = $place['state'];
		$city        = Transliteration::latin2cyrillic( $place['city'] );
		$cities_data = self::$container[ Client::PIGEON_CITIES ]->get_filtered_cities( $city, $state );

		if ( false !== $cities_data['city_key'] ) {
			$city_id = self::get_city_id( $order );

			if ( ! ( false === $city_id || null === $city_id ) ) {
				$streets = nashenski_return_array_for_select( Address::get_streets_for_query( $city_id, '' ), 1, array( 'type' => 'streets' ) );
			}
		}

		return $streets;
	}

	/**
	 * Texts for the box script.
	 *
	 * @return array
	 */
	protected static function get_i18n() {
		return array(
			'updateShipmentStatus' => __( 'Update shipment status', 'nashenski-commerce' ),
			'updateLabel'          => __( 'Update label', 'nashenski-commerce' ),
			'generateLabel'        => __( 'Generate label', 'nashenski-commerce' ),
			'deleteLabel'          => __( 'Delete label', 'nashenski-commerce' ),
			'label'                => __( 'Label', 'nashenski-commerce' ),
			'default'              => __( 'Default', 'nashenski-commerce' ),
			'selected'             => __( 'Selected', 'nashenski-commerce' ),
			'choose'               => __( 'Choose', 'nashenski-commerce' ),
			'deliveryPayedBy'      => __( 'Delivery is payed by', 'nashenski-commerce' ),
			'cd'                   => __( 'Cash on delivery', 'nashenski-commerce' ),
			'packCount'            => __( 'Pack count', 'nashenski-commerce' ),
			'shipmentType'         => __( 'Shipment type', 'nashenski-commerce' ),
			'other'                => __( 'Other', 'nashenski-commerce' ),
			'blVhEt'               => __( 'bl. vh. et.', 'nashenski-commerce' ),
			'streetNumber'         => __( 'Street number', 'nashenski-commerce' ),
			'streetQuarter'        => __( 'Street/Quarter', 'nashenski-commerce' ),
			'office'               => __( 'Office', 'nashenski-commerce' ),
			'address'              => __( 'Address', 'nashenski-commerce' ),
			'locker'               => __( 'Locker', 'nashenski-commerce' ),
			'deliveryType'         => __( 'Delivery type', 'nashenski-commerce' ),
			'labelData'            => __( 'Label data', 'nashenski-commerce' ),
			'buyer'                => __( 'Buyer', 'nashenski-commerce' ),
			'sender'               => __( 'Sender', 'nashenski-commerce' ),
			'weight'               => __( 'Weight', 'nashenski-commerce' ),
			'fixedPrice'           => __( 'Fixed price', 'nashenski-commerce' ),
			'copyLabelData'        => __( 'Copy Label Data', 'nashenski-commerce' ),
			'copyLabelDataMessage' => __( 'You just copied the label data used for the Pigeon Express API.', 'nashenski-commerce' ),
			'shipmentStatus'       => __( 'Shipment status', 'nashenski-commerce' ),
			'referenceNumber'      => __( 'Reference number', 'nashenski-commerce' ),
			'trackingCodes'        => __( 'Tracking codes', 'nashenski-commerce' ),
			'time'                 => __( 'Time:', 'nashenski-commerce' ),
			'event'                => __( 'Event:', 'nashenski-commerce' ),
			'details'              => __( 'Details:', 'nashenski-commerce' ),
			'reviewAndTest'        => __( 'Review and test', 'nashenski-commerce' ),
			'declaredValue'        => __( 'Declared value', 'nashenski-commerce' ),
			'mysticQuarter'        => __( 'Street or quarter', 'nashenski-commerce' ),
			'description'          => __( 'Description', 'nashenski-commerce' ),
			'sendFrom'             => __( 'Send From', 'nashenski-commerce' ),
			'length'               => __( 'Length', 'nashenski-commerce' ),
			'width'                => __( 'Width', 'nashenski-commerce' ),
			'height'               => __( 'Height', 'nashenski-commerce' ),
			'pack'                 => __( 'Pack', 'nashenski-commerce' ),
			'addPack'              => __( 'Add Pack', 'nashenski-commerce' ),
			'removePack'           => __( 'Remove Pack', 'nashenski-commerce' ),
			'returnAtMyExpense'    => __( 'Return at my expense', 'nashenski-commerce' ),
			'note'                 => __( 'Note for courier', 'nashenski-commerce' ),
		);
	}

	/**
	 * Whether the label asks to test the parcel before payment.
	 *
	 * @param array $label Label data.
	 * @return string "yes" or "no".
	 */
	protected static function get_test_option( $label ) {
		return ( ! empty( $label['service_codes']['shipment_test_before_payment'] ) ) ? 'yes' : 'no';
	}

	/**
	 * Remembers that the Pigeon Express message was dismissed.
	 *
	 * @return void
	 */
	public static function message_dismiss_callback() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1, 403 );
		}

		check_ajax_referer( 'nashenski_dismiss_notice', 'nonce' );

		update_option( 'nashenski_pigeon_express_message_dismiss', true );
	}

	/**
	 * Sends the label saved on a new order to Pigeon.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public static function generate_label_after_order_generated( $order_id ) {
		$order = wc_get_order( $order_id );
		$label = $order->get_meta( 'nashenski_pigeon_label' );
		if ( ! $label ) {
			return;
		}

		$label = self::update_sender( $label );
		$label = self::update_services( $label );
		$label = self::update_customer_note( $label, $order );
		$label = Payment::label( $label, $order );

		self::send_label_to_pigeon( $label, $order );
	}

	/**
	 * AJAX: creates the label at Pigeon from the box data.
	 *
	 * @return void
	 */
	public static function generate_label() {
		nashenski_check_admin_label_actions();

		$order = wc_get_order( nashenski_label_request( 'orderId' ) );
		try {
			$payment = Payment::request( $order ); } catch ( \InvalidArgumentException $error ) {
			wp_send_json_error( array( 'message' => $error->getMessage() ) );
			return; }
			$label = nashenski_label_request( 'label_data', array() );

			$label = self::update_sender( $label );
			$label = self::update_receiver_data( $label );
			$label = self::update_services( $label );
			$label = self::update_items( $label, $order );
			$label = self::update_payment_by( $label, $order, $payment );
			$label = self::update_customer_note( $label, $order );

			$data = self::send_label_to_pigeon( $label, $order );
			if ( isset( $data['shipmentStatus'] ) && null !== $payment['override'] ) {
				$order->update_meta_data( Payment::COD_OVERRIDE_META, $payment['override'] );
				$order->save();
			}

			wp_send_json_success( $data );
			wp_die();
	}

	/**
	 * Sets where the parcel is picked up: the saved setting, or the choice made in the box.
	 *
	 * @param array $label Label data.
	 * @return array
	 */
	public static function update_sender( $label ) {
		$send_from            = nashenski_get_option( 'pigeon', 'send_from' );
		$label['pickup_type'] = $send_from;

		if ( 'office' === $send_from ) {
			$label['pickup_office_id'] = str_replace( 'officeID-', '', nashenski_get_option( 'pigeon_send_from', 'office' ) );
		} else {
			$label['pickup_address'] = array(
				'city_id'         => str_replace( 'cityID-', '', nashenski_get_option( 'pigeon_send_from', 'city' ) ),
				'additional_info' => nashenski_get_option( 'pigeon_send_from', 'address' ),
			);
		}

		if ( ( null !== nashenski_label_request( 'send_from', null ) ) && ( null !== nashenski_label_request( 'send_from_type', null ) ) ) {
			$new_send_from         = nashenski_label_request( 'send_from', array() );
			$new_send_from_address = nashenski_label_request( 'send_from_address' );
			$new_send_from_type    = nashenski_label_request( 'send_from_type' );

			if ( ! empty( $new_send_from ) && ! empty( $new_send_from_type ) ) {
				switch ( $new_send_from_type ) {
					case 'address':
						$label['pickup_type']    = 'address';
						$label['pickup_address'] = array(
							'city_id'         => str_replace( 'cityID-', '', nashenski_get_option( 'pigeon_send_from', 'city' ) ),
							'additional_info' => $new_send_from_address,
						);

						unset( $label['pickup_office_id'] );
						break;
					case 'office':
						$label['pickup_type']      = 'office';
						$label['pickup_office_id'] = str_replace( 'officeID-', '', $new_send_from );

						unset( $label['pickup_address'] );
						break;
				}
			}
		}

		return $label;
	}

	/**
	 * Sets the receiver (name, phone, email and delivery place) from the order and the box.
	 *
	 * @param array $label Label data.
	 * @return array
	 */
	public static function update_receiver_data( $label ) {
		$order_id = nashenski_label_request( 'orderId' );
		$order    = wc_get_order( $order_id );
		$type     = nashenski_label_request( 'type', array() );

		$phone = '';
		$name  = '';

		if ( $order->get_shipping_first_name() && $order->get_shipping_last_name() ) {
			$name = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
		} else {
			$name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		}

		if ( $order->get_shipping_phone() ) {
			$phone = nashenski_format_phone( $order->get_shipping_phone(), $order->get_shipping_country() );
		} else {
			$phone = nashenski_format_phone( $order->get_billing_phone(), $order->get_billing_country() );
		}

		$label['delivery_type']  = $type['id'];
		$label['receiver_name']  = $name;
		$label['receiver_phone'] = trim( $phone );
		$label['receiver_email'] = $order->get_billing_email();

		unset( $label['delivery_address'] );
		unset( $label['delivery_office_id'] );

		if ( 'address' === $label['delivery_type'] ) {
			$label['delivery_address'] = self::generate_receiver_address( $order );
		} elseif ( 'office' === $label['delivery_type'] ) {
			$label['delivery_office_id'] = absint( self::generate_receiver_office_code() );
		} elseif ( 'locker' === $label['delivery_type'] ) {
			$label['delivery_office_id'] = absint( self::generate_receiver_locker_code() );
		}

		return $label;
	}

	/**
	 * Delivery address of the label: city, street and extra text.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	private static function generate_receiver_address( $order ) {
		$street = nashenski_label_request( 'street', array() );

		$receiver_address = array(
			'city_id'         => self::get_city_id( $order ),
			'street_id'       => str_replace( 'street-', '', $street['orig_key'] ),
			'additional_info' => nashenski_label_request( 'other' ),
		);

		return $receiver_address;
	}

	/**
	 * Office code chosen in the box.
	 *
	 * @return string
	 */
	private static function generate_receiver_office_code() {
		$office = nashenski_label_request( 'office', array() );

		return str_replace( 'officeID-', '', $office['id'] );
	}

	/**
	 * Locker code chosen in the box.
	 *
	 * @return string
	 */
	private static function generate_receiver_locker_code() {
		$locker = nashenski_label_request( 'locker', array() );

		return str_replace( 'lockerID-', '', $locker['id'] );
	}

	/**
	 * Sets who pays for the delivery and the cash on delivery amount.
	 *
	 * @param array      $label   Label data.
	 * @param WC_Order   $order   Order.
	 * @param array|null $payment Payment request, read from the box when empty.
	 * @return array
	 */
	private static function update_payment_by( $label, $order, $payment = null ) {
		$payment = $payment ?? Payment::request( $order );
		if ( 'fixed' === $payment['mode'] ) {
			self::update_order_shipping_price( $payment['price'], $order ); }
		$label['who_pays'] = 'fixed' === $payment['mode'] ? 'sender' : $payment['mode'];
		return Payment::label( $label, $order, $payment['override'], true );
	}

	/**
	 * Saves a fixed delivery price on the shipping line of the order.
	 *
	 * @param mixed    $price Price.
	 * @param WC_Order $order Order.
	 * @return void
	 */
	private static function update_order_shipping_price( $price, $order ) {
		$item = Payment::shipping_item( $order );
		$item->set_total( Payment::amount( $price ) );
		$item->save();

		$order->calculate_shipping();
		$order->calculate_totals();
		$order->save();
	}

	/**
	 * Sets the additional services from the settings and the box.
	 *
	 * @param array $label Label data.
	 * @return array
	 */
	private static function update_services( $label ) {
		if ( ( null !== nashenski_label_request( 'declaredValue', null ) ) ) {
			$declared_value = nashenski_label_request( 'declaredValue' );
		}

		$test = '';

		if ( ( null !== nashenski_label_request( 'testOption', null ) ) ) {
			$test = nashenski_label_request( 'testOption' );
		}

		$label['sms_notification'] = wc_string_to_bool( $label['sms_notification'] ?? '' );

		$is_fragile = wc_string_to_bool( nashenski_get_option( 'pigeon_services', 'declared_value' ) );

		if ( ( null !== nashenski_label_request( 'declaredValue', null ) ) ) {
			if ( $declared_value && $is_fragile ) {
				$label['service_codes']['declared_value'] = $declared_value;
			} else {
				unset( $label['service_codes']['declared_value'] );
			}
		}

		if ( nashenski_get_option( 'pigeon_services', 'paper_return_receipt' ) === 'yes' ) {
			$label['service_codes']['paper_return_receipt'] = true;
		} elseif ( isset( $label['service_codes']['paper_return_receipt'] ) ) {
			unset( $label['service_codes']['paper_return_receipt'] );
		}

		if ( nashenski_get_option( 'pigeon_services', 'return_receipt' ) === 'yes' ) {
			$label['service_codes']['return_receipt'] = true;
		} elseif ( isset( $label['service_codes']['return_receipt'] ) ) {
			unset( $label['service_codes']['return_receipt'] );
		}

		if ( nashenski_get_option( 'pigeon_services', 'service_return_documents' ) === 'yes' ) {
			$label['service_codes']['service_return_documents'] = true;
		} elseif ( isset( $label['service_codes']['service_return_documents'] ) ) {
			unset( $label['service_codes']['service_return_documents'] );
		}

		if ( nashenski_get_option( 'pigeon_services', 'pay_with_pos' ) === 'yes' ) {
			$label['pay_with_pos'] = true;
		} elseif ( isset( $label['pay_with_pos'] ) ) {
			unset( $label['pay_with_pos'] );
		}

		if ( nashenski_get_option( 'pigeon_services', 'id_verification_and_document_signature' ) === 'yes' ) {
			$label['service_codes']['ID_verification_and_document_signature'] = true;
		} elseif ( isset( $label['service_codes']['ID_verification_and_document_signature'] ) ) {
			unset( $label['service_codes']['ID_verification_and_document_signature'] );
		}

		if ( nashenski_get_option( 'pigeon_services', 'cod_card_fee' ) === 'yes' ) {
			$label['service_codes']['cod_card_fee'] = true;
		} elseif ( isset( $label['service_codes']['cod_card_fee'] ) ) {
			unset( $label['service_codes']['cod_card_fee'] );
		}

		if ( ( null !== nashenski_label_request( 'type', null ) ) ) {
			$type = nashenski_label_request( 'type', array() );
			$type = $type['id'];

			if ( 'locker' !== $type ) {
				if ( 'yes' === $test ) {
					$label['service_codes']['shipment_test_before_payment'] = true;
				} elseif ( isset( $label['service_codes']['shipment_test_before_payment'] ) ) {
					unset( $label['service_codes']['shipment_test_before_payment'] );
				}
			} elseif ( isset( $label['service_codes']['shipment_test_before_payment'] ) ) {
				unset( $label['service_codes']['shipment_test_before_payment'] );
			}
		}

		return $label;
	}

	/**
	 * Lists the order items on the label.
	 *
	 * @param array    $label Label data.
	 * @param WC_Order $order Order.
	 * @return array
	 */
	private static function update_items( $label, $order ) {
		$label['inventory_items'] = array();

		foreach ( $order->get_items() as $item ) {
			$label['inventory_items'][] = array(
				'description' => nashenski_normalize_text_for_label( $item->get_name() ),
				'quantity'    => $item['quantity'],
			);
		}

		return $label;
	}

	/**
	 * Raises the weight of each parcel to the minimum.
	 *
	 * @param array $label Label data.
	 * @return array
	 */
	public static function set_min_package_weight( $label ) {
		if ( empty( $label['packages'] ) || ! is_array( $label['packages'] ) ) {
			return $label;
		}

		$min_weight = (float) \Nashenski\Hooks::filter( 'nashenski_pigeon_min_package_weight', 'nashenski/pigeon/min_package_weight', 0.100 );

		foreach ( $label['packages'] as &$package ) {
			if ( ! isset( $package['weight'] ) || ! is_numeric( $package['weight'] ) || (float) $package['weight'] < $min_weight ) {
				$package['weight'] = $min_weight;
			}
		}

		unset( $package );

		return $label;
	}

	/**
	 * Puts the customer note on the label, or removes the note when there is none.
	 *
	 * @param array    $label Label data.
	 * @param WC_Order $order Order.
	 * @return array
	 */
	protected static function update_customer_note( $label, $order ) {
		if ( $order->get_customer_note() ) {
			$label['note'] = nashenski_normalize_text_for_label( $order->get_customer_note(), 1000 );
		} else {
			unset( $label['note'] );
		}

		return $label;
	}

	/**
	 * Sends the label to Pigeon and saves the answer on the order.
	 *
	 * @param array    $label Label data.
	 * @param WC_Order $order Order.
	 * @return array The label and shipment status, or a message.
	 */
	public static function send_label_to_pigeon( $label, $order ) {
		$data         = array();
		$container    = nashenski()->container();
		$request_body = \Nashenski\Hooks::filter( 'nashenski_pigeon_create_label', 'nashenski/pigeon/create_label', $label, $order );
		if ( isset( $request_body['service_codes']['cod_amount'] ) ) {
			$request_body['service_codes']['cod_amount'] = Payment::amount( $request_body['service_codes']['cod_amount'] );
		}
		$response = $container[ Client::PIGEON ]->api_call( $container[ Client::PIGEON ]::CREATE_LABEL_ENDPOINT, $request_body, 'POST' );

		if ( isset( $response['success'] ) && ! $response['success'] ) {
			if ( isset( $response['message'] ) ) {
				$errors = array( $response['message'] );
			}

			if ( isset( $response['errors'] ) ) {
				foreach ( $response['errors'] as $key => $error ) {
					$errors[] = $key . ': ' . implode( ' ', $error );
				}
			}

			$data['message'] = implode( ', ', $errors );
		} else {
			unset( $response['data']['label_pdf'] );
			$data['label']          = $request_body;
			$data['shipmentStatus'] = $response;

			$order->update_meta_data( 'nashenski_pigeon_label', $request_body );
			$order->update_meta_data( 'nashenski_pigeon_shipment_status', $response );
			$order->save();
			nashenski_add_label_order_note( $order, 'Pigeon Express', 'created', $response['data']['reference_number'] ?? '' );
		}

		\Nashenski\Hooks::action( 'nashenski_pigeon_after_send_label', 'nashenski/pigeon/after_send_label', $data, $order );

		return $data;
	}

	/**
	 * Prints the label PDF of the order.
	 *
	 * @return void
	 */
	public static function print_labels_endpoint() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ), '', array( 'response' => 403 ) );
		}

		// The print links carry the label nonce of the order screen.
		if ( null === nashenski_label_request( 'nonce', null ) ) {
			wp_die( esc_html__( 'This link has expired. Reload the order page and try again.', 'nashenski-commerce' ), '', array( 'response' => 403 ) );
		}

		$container       = nashenski()->container();
		$order_id        = nashenski_label_request( 'order-id' );
		$order           = wc_get_order( $order_id );
		$shipment_status = $order->get_meta( 'nashenski_pigeon_shipment_status' );
		$size            = ( null !== nashenski_label_request( 'size', null ) ) ? sanitize_key( nashenski_label_request( 'size' ) ) : '';
		$request_args    = array();

		if ( 'a4' === $size ) {
			$request_args['format'] = 'a4';
		}

		$pdf_escaped = $container[ Client::PIGEON ]->api_call( $container[ Client::PIGEON ]::CREATE_LABEL_ENDPOINT . '/' . $shipment_status['data']['reference_number'] . '/label', $request_args, 'GET', true );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Length: ' . strlen( $pdf_escaped ) );
		header( 'Content-disposition: inline; filename="' . $shipment_status['data']['reference_number'] . '.pdf"' );

		echo $pdf_escaped; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PDF file from the courier, sent as application/pdf.

		wp_die();
	}

	/**
	 * AJAX: cancels the label at Pigeon and clears it from the order.
	 *
	 * @return void
	 */
	public static function delete_label() {
		nashenski_check_admin_label_actions();

		$container       = nashenski()->container();
		$order_id        = nashenski_label_request( 'orderId' );
		$shipment_status = nashenski_label_request( 'shipmentStatus', array() );
		$order           = wc_get_order( $order_id );

		$response = $container[ Client::PIGEON ]->api_call( $container[ Client::PIGEON ]::CREATE_LABEL_ENDPOINT . '/' . $shipment_status['data']['reference_number'] . '/cancel', array(), 'POST' );

		$order->update_meta_data( 'nashenski_pigeon_shipment_status', '' );
		$order->update_meta_data( 'nashenski_pigeon_operations', '' );
		$order->save();
		nashenski_add_label_order_note( $order, 'Pigeon Express', 'deleted', $shipment_status['data']['reference_number'] ?? '' );

		wp_send_json_success( $response );
		wp_die();
	}

	/**
	 * AJAX: reads the tracking events from Pigeon and saves them on the order.
	 *
	 * @return void
	 */
	public static function update_shipment_status() {
		nashenski_check_admin_label_actions();

		$container       = nashenski()->container();
		$order_id        = nashenski_label_request( 'orderId' );
		$order           = wc_get_order( $order_id );
		$shipment_status = nashenski_label_request( 'shipmentStatus', array() );

		$response = $container[ Client::PIGEON ]->api_call( $container[ Client::PIGEON ]::CREATE_LABEL_ENDPOINT . '/' . $shipment_status['data']['reference_number'] . '/track', array() );

		$order_shipment_status = $response['data']['tracking'];

		$order->update_meta_data( 'nashenski_pigeon_operations', $order_shipment_status );
		$order->save();

		wp_send_json_success( array( 'operations' => $order_shipment_status ) );
		wp_die();
	}
}
