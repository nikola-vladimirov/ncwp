<?php
namespace Nashenski\Admin;
use Nashenski\Container\Client;
use Nashenski\Shipping\CVC\Address;

defined( 'ABSPATH' ) || exit;

class CVC {
	private static $container = null;

	public function __construct() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );

		add_action( 'wp_ajax_nashenski_cvc_generate_label', array( __CLASS__, 'generate_label' ) );
		add_action( 'wp_ajax_nashenski_cvc_delete_label', array( __CLASS__, 'delete_label' ) );
		add_action( 'wp_ajax_nashenski_cvc_update_actions', array( __CLASS__, 'update_actions' ) );
	}

	public static function add_meta_boxes() {
		nashenski_add_courier_meta_box( 'cvc', __( 'CVC Delivery', 'nashenski-commerce' ), array( __CLASS__, 'meta_box' ) );
	}

	public static function meta_box() {
		global $post;
		$theorder = isset( $GLOBALS['theorder'] ) ? $GLOBALS['theorder'] : null;

		self::$container = nashenski()->container();

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $post->ID );
		} else {
			$order = $theorder;
		}

		if ( !empty( $theorder->get_items( 'shipping' ) ) ) {
			foreach ( $theorder->get_items( 'shipping' ) as $shipping ) {
				if ( $shipping['method_id'] === 'nashenski_cvc' ) {
					echo '<div id="nashenski--cvc-admin"></div>';

					$label_data = array();
					$cookie_data = $theorder->get_meta( 'nashenski_cvc_cookie_data' );
					$shipment_status = $theorder->get_meta( 'nashenski_cvc_shipment_status' );
					$actions = $theorder->get_meta( 'nashenski_cvc_actions' );

					if ( $label = $theorder->get_meta( 'nashenski_cvc_label' ) ) {
						$label_data = $label;
					}

					if ( !$cookie_data ) {
						foreach ( $shipping->get_meta_data() as $meta_data ) {
							$data = $meta_data->get_data();

							if ( $data['key'] == 'cookie_data' ) {
								$cookie_data = $data['value'];
							}
						}	
					}
					
					wp_localize_script( 'nashenski-js-admin', 'nashenski_cvc', array(
						'label' => $label_data,
						'shipmentStatus' => $shipment_status,
						'actions' => $actions,
						'paymentType' => $theorder->get_payment_method(),
						'cookie_data' => $cookie_data,
						'offices' => self::get_offices( $cookie_data ),
						'streets' => self::get_streets( $cookie_data ),
						'orderId' => $theorder->get_id(),
						'testsOptions' => nashenski_return_array_for_select( nashenski_get_shipping_tests_options() ),
						'testOption' => self::get_test_option( $label_data ),
						'i18n' => self::get_i18n(),
						'nonce' => wp_create_nonce( 'nashenski_admin_label' ),
					) );
					break;
				}
			}
		}
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
			'deliveryType' => __( 'Delivery type', 'nashenski-commerce' ),
			'labelData' => __( 'Label data', 'nashenski-commerce' ),
			'buyer' => __( 'Buyer', 'nashenski-commerce' ),
			'sender' => __( 'Sender', 'nashenski-commerce' ),
			'contract' => __( 'Contract', 'nashenski-commerce' ),
			'weight' => __( 'Weight', 'nashenski-commerce' ),
			'fixedPrice' => __( 'Fixed price', 'nashenski-commerce' ),
			'copyLabelData' => __( 'Copy Label Data', 'nashenski-commerce' ),
			'copyLabelDataMessage' => __( 'You just copied the label data used for the cvc API.', 'nashenski-commerce' ),
			'shipmentStatus' => __( 'Shipment status', 'nashenski-commerce' ),
			'time' => __( 'Time:', 'nashenski-commerce' ),
			'event' => __( 'Event:', 'nashenski-commerce' ),
			'details' => __( 'Details:', 'nashenski-commerce' ),
			'reviewAndTest' => __( 'Review and test', 'nashenski-commerce' ),
			'declaredValue' => __( 'Declared value', 'nashenski-commerce' ),
			'description' => __( 'Description', 'nashenski-commerce' ),
		);
	}

	protected static function get_offices( $cookie_data ) {
		$country = ( !empty( $cookie_data['country'] ) ) ? $cookie_data['country'] : 100;
		$country_id = self::$container[ Client::CVC_COUNTRIES ]->get_country_id( $country );

		return self::$container[ Client::CVC_OFFICES ]->get_all_offices( $country_id ); 
	}

	protected static function get_streets( $cookie_data ) {
		// Orders made without the checkout picker have no CVC data.
		if ( ! is_array( $cookie_data ) || empty( $cookie_data['city'] ) ) {
			return [];
		}

		$country = ( !empty( $cookie_data['country'] ) ) ? $cookie_data['country'] : 100;
		$country_id = self::$container[ Client::CVC_COUNTRIES ]->get_country_id( $country );
		$raw_city = sanitize_text_field( $cookie_data['city'] );
		
		if ( empty( $raw_city ) ) {
			return [];
		}

		$state_id = self::$container[ Client::CVC_CITIES ]->get_state_id( sanitize_text_field( $cookie_data['state'] ), $country_id );
		$cities_data = self::$container[ Client::CVC_CITIES ]->get_filtered_cities( $raw_city, $state_id, $country_id );

		if ( false === $cities_data['city_key'] ) {
			return [];
		}

		$city_id = $cities_data['cities'][ $cities_data['city_key'] ][ 'id' ];

		$streets = nashenski_return_array_for_select( Address::get_streets_for_query( $city_id, $cookie_data['selectedAddress']['label'] ), 1, array( 'type' => 'streets' ) );

		$quarters = nashenski_return_array_for_select( Address::get_quarters_for_query( $city_id, $cookie_data['selectedAddress']['label'] ), 1, array( 'type' => 'quarters' ) );

		return array_merge( $streets, $quarters );
	}

	protected static function get_test_option( $label ) {
		$option = 'no';

		if ( !empty( $label['is_observe'] ) && !empty( $label['is_test'] ) ) {
			$option = 'test';
		} else if ( !empty( $label['is_observe'] ) ) {
			$option = 'review';
		}

		return $option;
	}

	public static function delete_label() {
		nashenski_check_admin_label_actions();

		$container = nashenski()->container();
		$order_id = nashenski_label_request( 'orderId' );
		$shipment_status = nashenski_label_request( 'shipmentStatus', array() );
		$order = wc_get_order( $order_id );
		
		$response = $container[ Client::CVC ]->api_call( $container[ Client::CVC ]::CANCEL_LABELS_ENDPOINT, array(
			'wb' => $shipment_status['wb'],
		) );

		$order->update_meta_data( 'nashenski_cvc_shipment_status', '' );
		$order->update_meta_data( 'nashenski_cvc_actions', '' );
		$order->save();
		nashenski_add_label_order_note( $order, 'CVC', 'deleted', $shipment_status['wb'] ?? '' );
		
		wp_send_json_success( $response );
		wp_die();
	}

	public static function update_actions() {
		nashenski_check_admin_label_actions();

		$container = nashenski()->container();
		$order_id = nashenski_label_request( 'orderId' );
		$shipment_status = nashenski_label_request( 'shipmentStatus', array() );
		$order = wc_get_order( $order_id );

		$response = $container[ Client::CVC ]->api_call( $container[ Client::CVC ]::ACTIONS_ENDPOINT, array(
			'wb' => $shipment_status['wb'],
		), 'GET' );

		$order->update_meta_data( 'nashenski_cvc_actions', $response['history'] );
		$order->save();

		wp_send_json_success( array(
			'actions' => $response['history'],
		) );
		wp_die();
	}

	public static function generate_label() {
		nashenski_check_admin_label_actions();

		$order_id = nashenski_label_request( 'orderId' );
		$label = nashenski_label_request( 'label_data', array() );

		$label = self::update_sender( $label );
		$label = self::update_receiver_address( $label );
		$label = self::update_payment_by( $label, $order_id );
		$label = self::update_test_options( $label );
		$label = self::update_phone_and_names( $label );
		$label = self::update_os_value_and_cod( $label );

		$data = self::send_label_to_cvc( $label, $order_id );

		wp_send_json_success( $data );
		wp_die();
	}

	public static function generate_label_after_order_generated( $order_id ) {
		$order = wc_get_order( $order_id );
		$label = $order->get_meta( 'nashenski_cvc_label' );

		if ( !$label ) {
			return;
		}

		$label = self::update_sender( $label );
		$label = self::update_shipment_description( $label, $order_id );

		self::send_label_to_cvc( $label, $order_id );
	}

	public static function send_label_to_cvc( $label, $order_id ) {
		$data = [];
		$order = wc_get_order( $order_id );

		$generated_data = self::generate_response( $label, $order_id );
		$response = $generated_data['response'];
		$request_body = $generated_data['request_body'];

		if ( ! $response['success'] ) {
			$data['message'] = $response['error'];
		} else {
			$request_body['wb'] = $response['wb'];
			$data['shipmentStatus'] = $response;
			$data['label'] = $request_body;

			$order->update_meta_data( 'nashenski_cvc_label', $request_body );
			$order->update_meta_data( 'nashenski_cvc_shipment_status', $response );
			$order->save();
			nashenski_add_label_order_note( $order, 'CVC', 'created', $response['wb'] ?? '' );

			self::update_order_shipping_price( $response, $order_id );
		}

		do_action( 'nashenski/cvc/after_send_label', $data, $order );

		return $data;
	}

	public static function update_sender( $label ) {
		$container = nashenski()->container();
		$city = str_replace( 'cityID-', '', nashenski_get_option( 'cvc_sender', 'city' ) );

		$sender_data = array( 
			"name" => nashenski_get_option( 'cvc_sender', 'name' ), 
			"phone" => nashenski_get_option( 'cvc_sender', 'phone' ), 
			"email" => nashenski_get_option( 'cvc_sender', 'email' ), 
			"country_id" => 100, 
			"city_id" => $city,
			"zip" => $container[ Client::CVC_CITIES ]->get_city_zip_by_id( $city ),
		);

		$send_from = nashenski_get_option( 'cvc_sender', 'send_from' );

		if ( $send_from === 'address' ) {
			$sender_data[ "custom_location_id" ] = nashenski_get_option( 'cvc_sender', 'address' );
		} else if ( $send_from === 'office' ) {
			$sender_data[ "hub_id" ] = str_replace( 'hubID-', '', nashenski_get_option( 'cvc_sender', 'office' ) );
		}

		$label['pickup_date'] = date_i18n( 'Y-m-d' );
		$label['sender'] = $sender_data;

		return $label;
	}

	protected static function update_receiver_address( $label ) {
		$container = nashenski()->container();
		$order_id = nashenski_label_request( 'orderId' );
		$order = wc_get_order( $order_id );
		$type = nashenski_label_request( 'type' );
		$cookie_data = nashenski_label_request( 'cookie_data', array() );
		$cookie_data['type'] = $type['id'];

		$label = self::unset_rec_address_fields( $label );

		if ( $type['id'] === 'address' ) {
			$street = nashenski_label_request( 'street', array() );
			if ( $street['type'] === 'streets' ) {
				$label['rec']["street_id"] = str_replace('street-', '', $street['orig_key'] ); 
				$label['rec']["num"] = nashenski_label_request( 'streetNumber' );
			} else if ( $street['type'] === 'quarters' ) {
				$label['rec']["qt_id"] = str_replace('qtr-', '', $street['orig_key'] );
			}

			$label = self::update_rec_other_fields( $label );
		} else if ( $type['id'] === 'office' ) {
			$office = nashenski_label_request( 'office', array() );
			$label['rec']["office_id"] = $office['id'];
		}

		$order->update_meta_data( 'nashenski_cvc_cookie_data', $cookie_data );
		$order->save();

		return $label;
	}

	protected static function unset_rec_address_fields( $label ) {
		if ( isset( $label['rec'][ 'office_id' ] ) ) {
			unset( $label['rec'][ 'office_id' ] );
		}

		if ( isset( $label['rec'][ 'street' ] ) ) {
			unset( $label['rec'][ 'street' ] );
		}

		if ( isset( $label['rec'][ 'street_id' ] ) ) {
			unset( $label['rec'][ 'street_id' ] );
		}

		if ( isset( $label['rec'][ 'num' ] ) ) {
			unset( $label['rec'][ 'num' ] );
		}
		
		if ( isset( $label['rec'][ 'qt_id' ] ) ) {
			unset( $label['rec'][ 'qt_id' ] );
		}

		if ( isset( $label['rec']["block"] ) ) {
			unset( $label['rec']["block"] );
		}

		if ( isset( $label['rec']["entr"] ) ) {
			unset( $label['rec']["entr"] );
		}

		if ( isset( $label['rec']["floor"] ) ) {
			unset( $label['rec']["floor"] );
		}

		if ( isset( $label['rec']["ap"] ) ) {
			unset( $label['rec']["ap"] );
		}

		if ( isset( $label['rec']["notes"] ) ) {
			unset( $label['rec']["notes"] );
		}

		return $label;
	}

	protected static function update_rec_other_fields( $label ) {
		if ( !empty( nashenski_label_request( 'other' ) ) ) {
			$other = nashenski_label_request( 'other' );

			$label['rec']["block"] = $parts[0];

			if ( isset( $parts[1] ) ) {
				$label['rec']["entr"] = $parts[1];
			}

			if ( isset( $parts[2] ) ) {
				$label['rec']["floor"] = $parts[2];
			}

			if ( isset( $parts[3] ) ) {
				$label['rec']["ap"] = $parts[3];
			}

			if ( isset( $parts[4] ) ) {
				unset( $parts[0], $parts[1], $parts[2], $parts[3] );

				$label['rec']["notes"] = implode( ' ', $parts ) ;
			}
		}

		return $label;
	}

	protected static function update_payment_by( $label, $order_id ) {
		$payment_by = nashenski_label_request( 'paymentBy' );

		if ( $payment_by['id'] === 'fixed'  ) {
			$label['payer'] = 'sender';

			self::update_order_shipping_price( '', $order_id, $label );
		} else {
			$label['payer'] = $payment_by['id'];
		}

		if ( nashenski_get_option( 'cvc_sender', 'contract_pay' ) === 'yes' ) {
			$label['payer'] = 'contract';
		}
		
		return $label;
	}

	protected static function update_test_options( $label ) {
		$test_option = ( nashenski_label_request( 'testOption', array() )['id'] ?? '' );

		if ( isset( $label['is_observe'] ) ) {
			unset( $label['is_observe'] );
		}

		if ( isset( $label['is_test'] ) ) {
			unset( $label['is_test'] );
		}

		if ( $test_option == 'review' ) {
			$label['is_observe'] = 1;
		} else if ( $test_option == 'test' ) {
			$label['is_observe'] = 1;
			$label['is_test'] = 1;
		}
		
		return $label;
	}

	protected static function update_shipment_description( $label, $order_id ) {
		$force = nashenski_get_option( 'cvc_sender', 'force_variations_in_desc' );
		$order = wc_get_order( $order_id );
		$names = [];

		foreach ( $order->get_items() as $item ) {
			$name = $item->get_name();

			if ( $force === 'yes' ) {
				if ( $attributes = $item->get_meta_data() ) {
					$name .= ' - ' .implode( ',', wp_list_pluck( $attributes, 'value' ) );
				}
			}

			$names[] = $name;
		}

		$label[ 'description' ] = implode( ', ', $names );

		return $label;
	}

	protected static function update_phone_and_names( $label ) {
		$order = wc_get_order( nashenski_label_request( 'orderId' ) );
		$phone = [];
		$name = '';

		if ( $order->get_shipping_first_name() && $order->get_shipping_last_name() ) {
			$name = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
		} else {
			$name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
		}

		if ( $order->get_shipping_phone() ) {
			$phone = $order->get_shipping_phone();
		} else {
			$phone = $order->get_billing_phone();
		}

		$label['rec']['name'] = $name;
		$label['rec']['phone'] = $phone;

		return $label;
	}

	protected static function update_os_value_and_cod( $label ) {
		$cookie_data = nashenski_label_request( 'cookie_data', array() );
		$payment_by = nashenski_label_request( 'paymentBy', array() );

		if ( isset( $label['cod_amount'] ) ) {
			if ( !$label['cod_amount'] ) {
				unset( $label['cod_amount'] );
			} else if ( 
				isset( $label['cod_amount'] ) && 
				$cookie_data['fixed_price'] && 
				$payment_by['id'] == 'fixed'
			) {
				$label['cod_amount'] += number_format( $cookie_data['fixed_price'], 2 );
				$label['cod_amount'] = number_format( $label['cod_amount'], 2 );
			}
		}

		if ( isset( $label[ 'os_value' ] ) ) {
			unset( $label[ 'os_value' ] );
		}

		if ( nashenski_label_request( 'declaredValue' ) ) {
			$label[ 'os_value' ] = nashenski_label_request( 'declaredValue' );
		}

		return $label;
	}

	protected static function update_order_shipping_price( $response, $order_id, $request_body = [] ) {
		if ( ( null === nashenski_label_request( 'paymentBy', null ) ) ) {
			return;
		}
		
		$order = wc_get_order( $order_id );
		$price = 0;

		if ( $order->get_payment_method() !== 'cod' ) { 
			return;
		}

		if ( $payment_by = nashenski_label_request( 'paymentBy' ) ) {
			$cookie_data = nashenski_label_request( 'cookie_data', array() );

			if ( $payment_by['id'] == 'rec' ) {
				$price = ( wc_tax_enabled() ) ? $response['price'] : $response['price_with_vat'];
			} else if ( $payment_by['id'] == 'fixed' && $cookie_data['fixed_price'] ) {
				$request_body['cod_amount'] += $cookie_data['fixed_price'];
				$price = nashenski_tax_based_price( $cookie_data['fixed_price'] );
			}
		}

		foreach( $order->get_items( 'shipping' ) as $item_id => $item ) {
			$item->set_total( $price );
			$item->calculate_taxes();
			$item->save();
		}

		$order->calculate_shipping();
		$order->calculate_totals();
		$order->save();

		if ( !empty( $request_body ) ) {
			return $request_body;
		}
	}

	protected static function generate_response( $label, $order_id ) {
		$container = nashenski()->container();
		$order = wc_get_order( $order_id );

		$request_body = apply_filters( 'nashenski/cvc/create_label', $label, $order );

		$response = $container[ Client::CVC ]->api_call( $container[ Client::CVC ]::CREATE_LABELS_ENDPOINT, $request_body );

		return [
			'response' => $response,
			'request_body' => $request_body,
		];
	}
}
