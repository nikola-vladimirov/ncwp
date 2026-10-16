<?php
namespace Nashenski\Admin;
use Nashenski\Container\Client;
use Nashenski\Shipping\Pigeon\Method;
use Nashenski\Shipping\Pigeon\Address;
use Nashenski\Transliteration;

use function PHPSTORM_META\map;

defined( 'ABSPATH' ) || exit;

class Pigeon {
	private static $container = null;

	public function __construct() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );

		add_action( 'wp_ajax_nashenski_pigeon_generate_label', array( __CLASS__, 'generate_label' ) );
		add_action( 'wp_ajax_nashenski_pigeon_delete_label', array( __CLASS__, 'delete_label' ) );
		add_action( 'wp_ajax_nashenski_pigeon_update_shipment_status', array( __CLASS__, 'update_shipment_status' ) );
		add_action( 'wp_ajax_nashenski_pigeon_print_labels', array( __CLASS__, 'print_labels_endpoint' ) );

		add_filter( 'nashenski/pigeon/calculate_label', array( __CLASS__, 'set_min_package_weight' ), 30 );
	}

	public static function add_meta_boxes() {
		nashenski_add_courier_meta_box( 'pigeon', __( 'Pigeon Express', 'nashenski-commerce' ), array( __CLASS__, 'meta_box' ) );
	}

	public static function meta_box() {
		global $post;
		$theorder = isset( $GLOBALS['theorder'] ) ? $GLOBALS['theorder'] : null;

		self::$container = nashenski()->container();

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $post->ID );
		}

		if ( !empty( $theorder->get_items( 'shipping' ) ) ) {
			foreach ( $theorder->get_items( 'shipping' ) as $shipping ) {
				if ( $shipping['method_id'] === 'nashenski_pigeon' ) {
					echo '<div id="nashenski--pigeon-admin"></div>';

					$label_data = array();
					$cookie_data = $theorder->get_meta( 'nashenski_pigeon_cookie_data' );
					$shipment_status = $theorder->get_meta( 'nashenski_pigeon_shipment_status' );
					$operations = $theorder->get_meta( 'nashenski_pigeon_operations' );

					if ( $label = $theorder->get_meta( 'nashenski_pigeon_label' ) ) {
						$label_data = $label;
					}

					if ( !$cookie_data ) {
						foreach ( $shipping->get_meta_data() as $meta_data ) {
							$data = $meta_data->get_data();

							if ( $data['key'] == 'nashenski_pigeon_cookie_data' ) {
								$cookie_data = $data['value'];
							}
						}	
					}
					
					wp_localize_script( 'nashenski-js-admin', 'nashenski_pigeon', array(
						'label' => $label_data,
						'shipmentStatus' => $shipment_status,
						'operations' => $operations,
						'cookie_data' => $cookie_data,
						'paymentType' => $theorder->get_payment_method(),
						'sendFrom' => self::get_send_from_data(),
						'offices' => self::get_offices( $theorder ),
						'lockers' => self::get_lockers( $theorder ),
						'streets' => self::get_streets( $cookie_data, $theorder ),
						'orderId' => $theorder->get_id(),
						'testOption' => self::get_test_option( $label_data ),
						'i18n' => self::get_i18n(),
						'nonce' => wp_create_nonce( 'nashenski_admin_label' ),
					) );
					break;
				}
			}
		}
	}

	protected static function get_send_from_data() {
		$container = nashenski()->container();
		$type = nashenski_get_option( 'pigeon', 'send_from' );
		$data = [
			'type' => $type,
		];

		$data['currentOffice'] = nashenski_get_option( 'pigeon_send_from', 'office' );
		$data['offices'] = ( nashenski_get_option( 'pigeon_send_from', 'city' ) ) ? $container[ Client::PIGEON_OFFICES ]->get_formatted_offices( nashenski_get_option( 'pigeon_send_from', 'city' ) ) : [];
		
		$data['currentAddress'] = nashenski_get_option( 'pigeon_send_from', 'address' );

		return $data;
	}

	private static function get_city_id( $order ) {
		if ( !self::$container ) {
			self::$container = nashenski()->container();
		}

		$city_id = false;
		$state = $order->get_billing_state();
		$city = $order->get_billing_city();
		
		if ( nashenski_is_different_shipping_address( $order ) ) {
			$state = $order->get_shipping_state();
			$city = $order->get_shipping_city();
		}

		$city = Transliteration::latin2cyrillic( $city );
		$cities_data = self::$container[ Client::PIGEON_CITIES ]->get_filtered_cities( $city, $state );

		if ( $cities_data['city_key'] !== false ) {
			$city_id = $cities_data['cities_search_names'][ $cities_data['city_key'] ]['id'];
		}

		return $city_id;
	}

	protected static function get_offices( $order ) {
		$offices = [];
		$city_id = self::get_city_id( $order );

		if ( !( $city_id === false || $city_id === null ) ) {
			$offices = self::$container[ Client::PIGEON_OFFICES ]->get_formatted_offices( $city_id );
		}

		return $offices;
	}

	protected static function get_lockers( $order ) {
		$lockers = [];
		$city_id = self::get_city_id( $order );

		if ( !( $city_id === false || $city_id === null ) ) {
			$lockers = self::$container[ Client::PIGEON_LOCKERS ]->get_formatted_lockers( $city_id );
		}

		return $lockers;
	}

	protected static function get_streets( $cookie_data, $order ) {
		$streets = [];
		$state = $order->get_billing_state();
		$city = $order->get_billing_city();
		
		if ( nashenski_is_different_shipping_address( $order ) ) {
			$state = $order->get_shipping_state();
			$city = $order->get_shipping_city();
		}

		$city = Transliteration::latin2cyrillic( $city );
		$cities_data = self::$container[ Client::PIGEON_CITIES ]->get_filtered_cities( $city, $state );

		if ( $cities_data['city_key'] !== false ) {
			$city_id = self::get_city_id( $order );

			if ( !( $city_id === false || $city_id === null ) ) {
				$streets = nashenski_return_array_for_select( Address::get_streets_for_query( $city_id, '' ), 1, array( 'type' => 'streets' ) );
			}

		}

		return $streets;
	}

	protected static function get_i18n() {
		return array(
			'updateShipmentStatus' => __( 'Update shipment status', 'nashenski-commerce' ),
			'updateLabel' => __( 'Update label', 'nashenski-commerce' ),
			'generateLabel' => __( 'Generate label', 'nashenski-commerce' ),
			'deleteLabel' => __( 'Delete label', 'nashenski-commerce' ),
			'label' => __( 'Label', 'nashenski-commerce' ), 
			'default' => __( 'Default', 'nashenski-commerce' ),
			'selected' => __( 'Selected', 'nashenski-commerce' ),
			'choose' => __( 'Choose', 'nashenski-commerce' ),
			'deliveryPayedBy' => __( 'Delivery is payed by', 'nashenski-commerce' ),
			'cd' => __( 'Cash on delivery', 'nashenski-commerce' ), 
			'packCount' => __( 'Pack count', 'nashenski-commerce' ),
			'shipmentType' => __( 'Shipment type', 'nashenski-commerce' ),
			'other' => __( 'Other', 'nashenski-commerce' ),
			'blVhEt' => __( 'bl. vh. et.', 'nashenski-commerce' ),
			'streetNumber' => __( 'Street number', 'nashenski-commerce' ),
			'streetQuarter' => __( 'Street/Quarter', 'nashenski-commerce' ),
			'office' => __( 'Office', 'nashenski-commerce' ),
			'address' => __( 'Address', 'nashenski-commerce' ),
			'locker' => __( 'Locker', 'nashenski-commerce' ),
			'deliveryType' => __( 'Delivery type', 'nashenski-commerce' ),
			'labelData' => __( 'Label data', 'nashenski-commerce' ),
			'buyer' => __( 'Buyer', 'nashenski-commerce' ),
			'sender' => __( 'Sender', 'nashenski-commerce' ),
			'weight' => __( 'Weight', 'nashenski-commerce' ),
			'fixedPrice' => __( 'Fixed price', 'nashenski-commerce' ),
			'copyLabelData' => __( 'Copy Label Data', 'nashenski-commerce' ),
			'copyLabelDataMessage' => __( 'You just copied the label data used for the Pigeon Express API.', 'nashenski-commerce' ),
			'shipmentStatus' => __( 'Shipment status', 'nashenski-commerce' ),
			'referenceNumber' => __( 'Reference number', 'nashenski-commerce' ),
			'trackingCodes' => __( 'Tracking codes', 'nashenski-commerce' ),
			'time' => __( 'Time:', 'nashenski-commerce' ),
			'event' => __( 'Event:', 'nashenski-commerce' ),
			'details' => __( 'Details:', 'nashenski-commerce' ),
			'reviewAndTest' => __( 'Review and test', 'nashenski-commerce' ),
			'declaredValue' => __( 'Declared value', 'nashenski-commerce' ),
			'mysticQuarter' => __( 'Street or quarter', 'nashenski-commerce' ),
			'description' => __( 'Description', 'nashenski-commerce' ),
			'sendFrom' => __('Send From', 'nashenski-commerce'),
			'length' => __('Length', 'nashenski-commerce'),
			'width' => __('Width', 'nashenski-commerce'),
			'height' => __('Height', 'nashenski-commerce'),
			'pack' => __('Pack', 'nashenski-commerce'),
			'addPack' => __('Add Pack', 'nashenski-commerce'),
			'removePack' => __('Remove Pack', 'nashenski-commerce'),
			'returnAtMyExpense' => __('Return at my expense', 'nashenski-commerce'),
			'note' => __('Note for courier', 'nashenski-commerce'),
		);
	}

	protected static function get_test_option( $label ) {
		return ( !empty( $label['service_codes']['shipment_test_before_payment'] ) ) ? 'yes' : 'no';
	}

	public static function message_dismiss_callback() {
		// The built admin JS (dist/) sends no nonce, so only the capability is checked.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1, 403 );
		}

		update_option( 'nashenski_pigeon_express_message_dismiss', true );
	}

	public static function generate_label_after_order_generated( $order_id ) {
		$order = wc_get_order( $order_id );
		$label = $order->get_meta( 'nashenski_pigeon_label' );
		if ( !$label ) {
			return;
		}

		$label = self::update_sender( $label );
		$label = self::update_services( $label );
		$label = self::update_customer_note( $label, $order );

		self::send_label_to_pigeon( $label, $order );
	}

	public static function generate_label() {
		nashenski_check_admin_label_actions();

		$order = wc_get_order( nashenski_label_request( 'orderId' ) );
		$label = nashenski_label_request( 'label_data', array() );

		$label = self::update_sender( $label );
		$label = self::update_receiver_data( $label );
		$label = self::update_services( $label );
		$label = self::update_items( $label, $order );
		$label = self::update_payment_by( $label, $order );
		$label = self::update_customer_note( $label, $order );

		$data = self::send_label_to_pigeon( $label, $order );

		wp_send_json_success( $data );
		wp_die();
	}

	public static function update_sender( $label ) {
		$send_from = nashenski_get_option( 'pigeon', 'send_from' );
		$label['pickup_type'] = $send_from;

		if ( $send_from == 'office' ) {
			$label['pickup_office_id'] = str_replace( 'officeID-', '', nashenski_get_option( 'pigeon_send_from', 'office' ) );
		} else {
			$label['pickup_address'] = [
				'city_id' => str_replace( 'cityID-', '', nashenski_get_option( 'pigeon_send_from', 'city' ) ),
				'additional_info' => nashenski_get_option( 'pigeon_send_from', 'address' ),
			];
		}

		if ( ( null !== nashenski_label_request( 'send_from', null ) ) && ( null !== nashenski_label_request( 'send_from_type', null ) ) ) {
			$new_send_from = nashenski_label_request( 'send_from', array() );
			$new_send_from_address = nashenski_label_request( 'send_from_address' );
			$new_send_from_type = nashenski_label_request( 'send_from_type' );

			if ( !empty( $new_send_from ) && !empty( $new_send_from_type ) ) {
				switch ( $new_send_from_type ) {
					case 'address':
						$label['pickup_type'] = 'address';
						$label['pickup_address'] = [
							'city_id' => str_replace( 'cityID-', '', nashenski_get_option( 'pigeon_send_from', 'city' ) ),
							'additional_info' => $new_send_from_address,
						];

						unset( $label['pickup_office_id'] );
						break;
					case 'office':
						$label['pickup_type'] = 'office';
						$label['pickup_office_id'] = str_replace( 'officeID-', '', $new_send_from );

						unset( $label['pickup_address'] );
						break;
				}
			}	
		}

		return $label;
	}

	public static function update_receiver_data( $label ) {
		$order_id = nashenski_label_request( 'orderId' );
		$order = wc_get_order( $order_id );
		$type = nashenski_label_request( 'type', array() );

		$phone = '';
		$name = '';

		if ( $order->get_shipping_first_name() && $order->get_shipping_last_name() ) {
			$name = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
		} else {
			$name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
		}

		if ( $order->get_shipping_phone() ) {
			$phone = nashenski_format_phone( $order->get_shipping_phone(), $order->get_shipping_country() );
		} else {
			$phone = nashenski_format_phone( $order->get_billing_phone(), $order->get_billing_country() );
		}

		$label['delivery_type'] = $type['id'];
		$label['receiver_name'] = $name;
		$label['receiver_phone'] = trim( $phone );
		$label['receiver_email'] = $order->get_billing_email();

		unset( $label['delivery_address'] );
		unset( $label['delivery_office_id'] );

		if ( $label['delivery_type'] === 'address' ) {
			$label['delivery_address'] = self::generate_receiver_address( $order );
		} else if ( $label['delivery_type'] === 'office' ) {
			$label['delivery_office_id'] = absint( self::generate_receiver_office_code() );
		} else if ( $label['delivery_type'] === 'locker' ) {
			$label['delivery_office_id'] = absint( self::generate_receiver_locker_code() );
		}

		return $label;
	}

	private static function generate_receiver_address( $order) {
		$street = nashenski_label_request( 'street', array() );

		$receiver_address = [
			'city_id' => self::get_city_id( $order ),
			'street_id' => str_replace( 'street-', '', $street['orig_key'] ),
			'additional_info' => nashenski_label_request( 'other' ),
		];

		return $receiver_address;
	}

	private static function generate_receiver_office_code() {
		$office = nashenski_label_request( 'office', array() );

		return str_replace( 'officeID-', '', $office['id'] );
	}

	private static function generate_receiver_locker_code() {
		$locker = nashenski_label_request( 'locker', array() );

		return str_replace( 'lockerID-', '', $locker['id'] );
	}

	private static function update_payment_by( $label, $order ) {
		$payment_by = nashenski_label_request( 'paymentBy', array() );
		$cookie_data = nashenski_label_request( 'cookie_data', array() );
		
		if ( $payment_by['id'] === 'fixed' ) {
			$label['who_pays'] = 'sender';

			if ( $order->get_payment_method() === 'cod' ) {
				$label['service_codes']['cod_amount'] += number_format( $cookie_data['fixed_price'], 2, '.', '');
			}

			self::update_order_shipping_price( $cookie_data['fixed_price'], $order );
		} else {
			$label['who_pays'] = $payment_by['id'];
		}

		return $label;
	}

	private static function update_order_shipping_price( $price, $order ) {
		foreach( $order->get_items( 'shipping' ) as $item_id => $item ) {
			$item->set_total( $price );
			$item->calculate_taxes();
			$item->save();
		}

		$order->calculate_shipping();
		$order->calculate_totals();
		$order->save();
	}

	private static function update_services( $label ) {
		if ( ( null !== nashenski_label_request( 'declaredValue', null ) ) ) {
			$declared_value = nashenski_label_request( 'declaredValue' );
		}

		if ( ( null !== nashenski_label_request( 'testOption', null ) ) ) {
			$test = nashenski_label_request( 'testOption' );
		}

		$label['sms_notification'] = wc_string_to_bool( $label['sms_notification'] );

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
		} else if ( isset( $label['service_codes']['paper_return_receipt'] ) ) {
			unset( $label['service_codes']['paper_return_receipt'] );
		}

		if ( nashenski_get_option( 'pigeon_services', 'return_receipt' ) === 'yes' ) {
			$label['service_codes']['return_receipt'] = true;
		} else if ( isset( $label['service_codes']['return_receipt'] ) ) {
			unset( $label['service_codes']['return_receipt'] );
		}
		
		if ( nashenski_get_option( 'pigeon_services', 'service_return_documents' ) === 'yes' ) {
			$label['service_codes']['service_return_documents'] = true;
		} else if ( isset( $label['service_codes']['service_return_documents'] ) ) {
			unset( $label['service_codes']['service_return_documents'] );
		}

		if ( nashenski_get_option( 'pigeon_services', 'pay_with_pos' ) === 'yes' ) {
			$label['pay_with_pos'] = true;
		} else if ( isset( $label['pay_with_pos'] ) ) {
			unset( $label['pay_with_pos'] );
		}
		
		if ( nashenski_get_option( 'pigeon_services', 'id_verification_and_document_signature' ) === 'yes' ) {
			$label['service_codes']['ID_verification_and_document_signature'] = true;
		} else if ( isset( $label['service_codes']['ID_verification_and_document_signature'] ) ) {
			unset( $label['service_codes']['ID_verification_and_document_signature'] );
		}
		
		if ( nashenski_get_option( 'pigeon_services', 'cod_card_fee' ) === 'yes' ) {
			$label['service_codes']['cod_card_fee'] = true;
		} else if ( isset( $label['service_codes']['cod_card_fee'] ) ) {
			unset( $label['service_codes']['cod_card_fee'] );
		}
		
		if ( ( null !== nashenski_label_request( 'type', null ) ) ) {
			$type = nashenski_label_request( 'type', array() );
			$type = $type['id'];

			if ( $type !== 'locker' ) {
				if ( $test === 'yes' ) {
					$label['service_codes']['shipment_test_before_payment'] = true;
				} else if ( isset( $label['service_codes']['shipment_test_before_payment'] ) ) {
					unset( $label['service_codes']['shipment_test_before_payment'] );
				}
			} else if ( isset( $label['service_codes']['shipment_test_before_payment'] ) ) {
				unset( $label['service_codes']['shipment_test_before_payment'] );
			}
		}

		return $label;
	}

	private static function update_items( $label, $order ) {
		$label['inventory_items'] = array();

		foreach ( $order->get_items() as $item ) {
			$label['inventory_items'][] = array(
				'description' => nashenski_normalize_text_for_label( $item->get_name() ),
				'quantity' => $item['quantity'],
			);
		}

		return $label;
	}

	public static function set_min_package_weight( $label ) {
		if ( empty( $label['packages'] ) || ! is_array( $label['packages'] ) ) {
			return $label;
		}

		$min_weight = (float) apply_filters( 'nashenski/pigeon/min_package_weight', 0.100 );

		foreach ( $label['packages'] as &$package ) {
			if ( ! isset( $package['weight'] ) || ! is_numeric( $package['weight'] ) || (float) $package['weight'] < $min_weight ) {
				$package['weight'] = $min_weight;
			}
		}

		unset( $package );

		return $label;
	}

	protected static function update_customer_note( $label, $order ) {
		if ( $order->get_customer_note() ) {
			$label['note'] = nashenski_normalize_text_for_label( $order->get_customer_note(), 1000 );
		} else {
			unset( $label['note'] );
		}

		return $label;
	}

	public static function send_label_to_pigeon( $label, $order ) {
		$data = [];
		$container = nashenski()->container();
		$request_body = apply_filters( 'nashenski/pigeon/create_label', $label, $order );
		$response = $container[ Client::PIGEON ]->api_call( $container[ Client::PIGEON ]::CREATE_LABEL_ENDPOINT, $request_body, 'POST' );
		
		if ( isset( $response['success'] ) && !$response['success'] ) {
			if ( isset( $response['message'] ) ) {
				$errors = [ $response['message'] ];
			}

			if ( isset( $response['errors'] ) ) {
				foreach ( $response['errors'] as $key => $error ) {
					$errors[] = $key . ": " . implode( " ", $error );
				}
			}

			$data['message'] = implode( ", ", $errors );
		} else {
			unset( $response['data']['label_pdf'] );
			//$data['price'] = nashenski_tax_based_price( $response['data']['total_price'] );
			$data['label'] = $request_body;
			$data['shipmentStatus'] = $response;

			$order->update_meta_data( 'nashenski_pigeon_label', $request_body );
			$order->update_meta_data( 'nashenski_pigeon_shipment_status', $response );
			$order->save();
			nashenski_add_label_order_note( $order, 'Pigeon Express', 'created', $response['data']['reference_number'] ?? '' );
		}

		do_action( 'nashenski/pigeon/after_send_label', $data, $order );

		return $data;
	}

	public static function print_labels_endpoint() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ), '', array( 'response' => 403 ) );
		}

		// The print links carry the label nonce of the order screen.
		if ( null === nashenski_label_request( 'nonce', null ) ) {
			wp_die( esc_html__( 'This link has expired. Reload the order page and try again.', 'nashenski-commerce' ), '', array( 'response' => 403 ) );
		}

		$container = nashenski()->container();
		$order_id = nashenski_label_request( 'order-id' );
		$order = wc_get_order( $order_id );
		$shipment_status = $order->get_meta( 'nashenski_pigeon_shipment_status' );
		$size = ( null !== nashenski_label_request( 'size', null ) ) ? sanitize_key( nashenski_label_request( 'size' ) ) : '';
		$request_args = array();

		if ( 'a4' === $size ) {
			$request_args['format'] = 'a4';
		}

		$pdf_escaped = $container[ Client::PIGEON ]->api_call( $container[ Client::PIGEON ]::CREATE_LABEL_ENDPOINT . "/" . $shipment_status['data']['reference_number'] . "/label", $request_args, 'GET', true );
		
		header('Content-Type: application/pdf');
		header('Content-Length: '.strlen( $pdf_escaped ));
		header('Content-disposition: inline; filename="' . $shipment_status['data']['reference_number'] . '.pdf"');

		echo $pdf_escaped; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PDF file from the courier, sent as application/pdf.

		wp_die();
	}

	public static function delete_label() {
		nashenski_check_admin_label_actions();

		$container = nashenski()->container();
		$order_id = nashenski_label_request( 'orderId' );
		$shipment_status = nashenski_label_request( 'shipmentStatus', array() );
		$order = wc_get_order( $order_id );
		
		$response = $container[ Client::PIGEON ]->api_call( $container[ Client::PIGEON ]::CREATE_LABEL_ENDPOINT . "/" . $shipment_status['data']['reference_number'] . "/cancel", [], 'POST' );

		$order->update_meta_data( 'nashenski_pigeon_shipment_status', '' );
		$order->update_meta_data( 'nashenski_pigeon_operations', '' );
		$order->save();
		nashenski_add_label_order_note( $order, 'Pigeon Express', 'deleted', $shipment_status['data']['reference_number'] ?? '' );
		
		wp_send_json_success( $response );
		wp_die();
	}

	public static function update_shipment_status() {
		nashenski_check_admin_label_actions();

		$container = nashenski()->container();
		$order_id = nashenski_label_request( 'orderId' );
		$order = wc_get_order( $order_id );
		$shipment_status = nashenski_label_request( 'shipmentStatus', array() );

		$response = $container[ Client::PIGEON ]->api_call( $container[ Client::PIGEON ]::CREATE_LABEL_ENDPOINT . "/" . $shipment_status['data']['reference_number'] . "/track", [] );

		$order_shipment_status = $response['data']['tracking'];
		
		$order->update_meta_data( 'nashenski_pigeon_operations', $order_shipment_status );
		$order->save();

		wp_send_json_success( array( 'operations' => $order_shipment_status ) );
		wp_die();
	}
}
