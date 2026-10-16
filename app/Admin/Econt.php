<?php
namespace Nashenski\Admin;
use Nashenski\Container\Client;
use Nashenski\Shipping\Econt\Address;

defined( 'ABSPATH' ) || exit;

class Econt {
	private static $container = null;

	public function __construct() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );

		add_action( 'wp_ajax_nashenski_econt_generate_label', array( __CLASS__, 'generate_label' ) );
		add_action( 'wp_ajax_nashenski_econt_delete_label', array( __CLASS__, 'delete_label' ) );
		add_action( 'wp_ajax_nashenski_econt_update_shipment_status', array( __CLASS__, 'update_shipment_status' ) );

		add_filter( 'nashenski/econt/calculate_label', array( __CLASS__, 'set_min_pack_weight' ), 30 );
		add_filter( 'nashenski/econt/create_label', array( __CLASS__, 'set_min_pack_weight' ), 30 );
		add_filter( 'nashenski/econt/update_label', array( __CLASS__, 'set_min_pack_weight' ), 30 );
	}

	public static function add_meta_boxes() {
		nashenski_add_courier_meta_box( 'econt', __( 'Econt Delivery', 'nashenski-commerce' ), array( __CLASS__, 'meta_box' ) );
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
				if ( $shipping['method_id'] === 'nashenski_econt' ) {
					echo '<div id="nashenski--econt-admin"></div>';

					$label_data = array();
					$cookie_data = $theorder->get_meta( 'nashenski_econt_cookie_data' );
					$shipment_status = $theorder->get_meta( 'nashenski_econt_shipment_status' );

					if ( $label = $theorder->get_meta( 'nashenski_econt_label' ) ) {
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
					
					// An order made outside checkout (quick order, admin order) has no label draft from the cart.
					if ( empty( $label_data['label'] ) ) {
						$label_data = array( 'label' => self::draft_label( $theorder, is_array( $cookie_data ) ? $cookie_data : array() ) );
					}

					wp_localize_script( 'nashenski-js-admin', 'nashenski_econt', array(
						'useInvoiceNumber' => self::use_invoice_number(),
						'invoiceNumber' => self::get_invoice_number( $theorder->get_id() ),
						'label' => $label_data['label'] ?? null,
						'sendFrom' => self::get_send_from_data(),
						'shipmentStatus' => $shipment_status,
						'cookie_data' => $cookie_data,
						'paymentType' => $theorder->get_payment_method(),
						'shipmentTypes' => self::get_shipment_types(),
						'offices' => self::get_offices( $cookie_data, $theorder ),
						'streets' => self::get_streets( $cookie_data, $theorder ),
						'orderId' => $theorder->get_id(),
						'testsOptions' => nashenski_return_array_for_select( nashenski_get_shipping_tests_options() ),
						'testOption' => self::get_test_option( $label_data['label'] ?? array() ),
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
			'blockNumber' => __( 'Block', 'nashenski-commerce' ),
			'entranceNumber' => __( 'Entrance', 'nashenski-commerce' ),
			'floorNumber' => __( 'Floor', 'nashenski-commerce' ),
			'apartmentNumber' => __( 'Apartment', 'nashenski-commerce' ),
			'streetNumberRequired' => __( 'Street number is required.', 'nashenski-commerce' ),
			'quarterDetailRequired' => __( 'Fill in at least one of block, entrance, floor or apartment.', 'nashenski-commerce' ),
			'streetQuarter' => __( 'Street/Quarter', 'nashenski-commerce' ),
			'office' => __( 'Office', 'nashenski-commerce' ),
			'automat' => __( 'Automat', 'nashenski-commerce' ),
			'address' => __( 'Address', 'nashenski-commerce' ),
			'deliveryType' => __( 'Delivery type', 'nashenski-commerce' ),
			'labelData' => __( 'Label data', 'nashenski-commerce' ),
			'buyer' => __( 'Buyer', 'nashenski-commerce' ),
			'sender' => __( 'Sender', 'nashenski-commerce' ),
			'weight' => __( 'Weight', 'nashenski-commerce' ),
			'fixedPrice' => __( 'Fixed price', 'nashenski-commerce' ),
			'copyLabelData' => __( 'Copy Label Data', 'nashenski-commerce' ),
			'copyLabelDataMessage' => __( 'You just copied the label data used for the Econt API.', 'nashenski-commerce' ),
			'shipmentStatus' => __( 'Shipment status', 'nashenski-commerce' ),
			'time' => __( 'Time:', 'nashenski-commerce' ),
			'event' => __( 'Event:', 'nashenski-commerce' ),
			'details' => __( 'Details:', 'nashenski-commerce' ),
			'reviewAndTest' => __( 'Review and test', 'nashenski-commerce' ),
			'declaredValue' => __( 'Declared value', 'nashenski-commerce' ),
			'description' => __( 'Description', 'nashenski-commerce' ),
			'invoiceNum' => __( 'Invoice number', 'nashenski-commerce' ),
			'partialDelivery' => __( 'Partial delivery', 'nashenski-commerce' ),
			'sendFrom' => __('Send From', 'nashenski-commerce'),
			'length' => __('Length', 'nashenski-commerce'),
			'width' => __('Width', 'nashenski-commerce'),
			'height' => __('Height', 'nashenski-commerce'),
			'pack' => __('Pack', 'nashenski-commerce'),
			'addPack' => __('Add Pack', 'nashenski-commerce'),
			'removePack' => __('Remove Pack', 'nashenski-commerce'),
		);
	}

	/**
	 * A label draft from the order, as checkout builds one from the cart. Generate label fills
	 * the sender, address, payment and test options from the box.
	 */
	protected static function draft_label( $order, $cookie_data ) {
		$names  = array();
		$weight = 0;

		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			if ( $product && $product->get_weight() ) {
				$weight += wc_get_weight( $product->get_weight(), 'kg' ) * $item->get_quantity();
			}

			$names[] = $item->get_name();
		}

		$weight   = $weight ? max( 0.1, $weight ) : 1;
		$receiver = array(
			'name'   => $cookie_data['receiver'] ?? $order->get_formatted_shipping_full_name(),
			'phones' => array( $cookie_data['phone'] ?? $order->get_billing_phone() ),
		);
		$label    = array(
			'receiverClient'        => $receiver,
			'receiverAgent'         => $receiver,
			'packCount'             => 1,
			'shipmentType'          => 'PACK',
			'weight'                => $weight,
			'shipmentDescription'   => nashenski_normalize_text_for_label( implode( ', ', $names ), 500 ),
			'packs'                 => array( array( 'width' => 17, 'height' => 17, 'length' => 17, 'weight' => $weight ) ),
			'services'              => array(),
			'paymentReceiverMethod' => 'cash',
		);

		if ( ! empty( $cookie_data['selectedOffice'] ) ) {
			$label['receiverOfficeCode'] = $cookie_data['selectedOffice'];
		}

		if ( 'cod' === $order->get_payment_method() ) {
			$label['services'] = array(
				'cdType'     => 'get',
				'cdAmount'   => wc_format_decimal( $order->get_total() - $order->get_shipping_total() - $order->get_shipping_tax(), wc_get_price_decimals() ),
				'cdCurrency' => $order->get_currency(),
			);
		}

		return $label;
	}

	protected static function get_shipment_types() {
		return nashenski_return_array_for_select( array(
			'PACK' => __('Pack', 'nashenski-commerce'),
			'DOCUMENT' => __('Document', 'nashenski-commerce'),
			'PALLET' => __('Pallet', 'nashenski-commerce'),
			'CARGO' => __('Cargo', 'nashenski-commerce'),
			'DOCUMENTPALLET' => __('Document-Pallet', 'nashenski-commerce'),
		) );
	}

	protected static function get_send_from_data() {
		$container = nashenski()->container();
		$type = nashenski_get_option( 'econt', 'send_from' );
		$data = [
			'type' => $type,
		];

		$data['currentOffice'] = nashenski_get_option( 'econt_send_from', 'office' );
		$data['offices'] = ( nashenski_get_option( 'econt_send_from', 'office_city' ) ) ? self::$container[ Client::ECONT_OFFICES ]->get_formatted_offices( nashenski_get_option( 'econt_send_from', 'office_city' ) ) : [];

		if ( !empty( $data['offices'] ) ) {
			$data['offices'] = array_merge( $data['offices']['shops'], $data['offices']['aps'] );
		}
		
		$data['currentAddress'] = nashenski_get_option( 'econt_send_from', 'address' );
		$data['addresses'] = $container[ Client::ECONT_PROFILE ]->get_formatted_addresses();


		return $data;
	}

	protected static function get_offices( $cookie_data, $order ) {
		$offices = [];
		$country = $order->get_billing_country();
		$state = $order->get_billing_state();
		$city = $order->get_billing_city();

		if ( nashenski_is_different_shipping_address( $order ) ) {
			$country = $order->get_shipping_country();
			$state = $order->get_shipping_state();
			$city = $order->get_shipping_city();
		}

		$states = self::$container[ Client::ECONT_CITIES ]->get_regions( $country );
		$state = $states[ $state ];
		$cities_data = self::$container[ Client::ECONT_CITIES ]->get_filtered_cities( $city, $state, $country );

		if ( $cities_data['city_key'] !== false ) {
			$offices = self::$container[ Client::ECONT_OFFICES ]->get_offices( $cities_data['cities'][ $cities_data['city_key'] ]['id'], $country );
			$offices = ( isset( $offices['offices'] ) ) ? $offices['offices'] : [];
		}

		return $offices;
	}

	protected static function get_streets( $cookie_data, $order ) {
		$streets = [];
		$quarters = [];
		$country = $order->get_billing_country();
		$state = $order->get_billing_state();
		$city = $order->get_billing_city();
		
		if ( nashenski_is_different_shipping_address( $order ) ) {
			$country = $order->get_shipping_country();
			$state = $order->get_shipping_state();
			$city = $order->get_shipping_city();
		}

		$states = self::$container[ Client::ECONT_CITIES ]->get_regions( $country );
		$state = $states[ $state ];

		$cities_data = self::$container[ Client::ECONT_CITIES ]->get_filtered_cities( $city, $state, $country );

		if ( $cities_data['city_key'] !== false ) {
			$streets = nashenski_return_array_for_select( 
				Address::get_streets_for_query( '', $cities_data['city_key'], $cities_data['cities'] ), 
				1, 
				array( 'type' => 'streets' ) 
			);

			$quarters = nashenski_return_array_for_select( 
				Address::get_quarters_for_query( '', $cities_data['city_key'], $cities_data['cities'] ), 
				1, 
				array( 'type' => 'quarters' ) 
			);
		}

		return array_merge( $streets, $quarters );
	}

	protected static function get_test_option( $label ) {
		$option = 'no';

		if ( !empty( $label['payAfterAccept'] ) && !empty( $label['payAfterTest'] ) ) {
			$option = 'test';
		} else if ( !empty( $label['payAfterAccept'] ) ) {
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

		if ( isset( $shipment_status['label']['shipmentNumber'] ) ) {
			$shipment_number = $shipment_status['label']['shipmentNumber'];
			$response = $container[ Client::ECONT ]->label_request( 'delete', array(
				'shipmentNumbers' => [ $shipment_number ]
			) );

			$order->update_meta_data( 'nashenski_econt_shipment_status', '' );
			$order->save();
			nashenski_add_label_order_note( $order, 'Econt', 'deleted', $shipment_number );
		} else {
			$response = [ 'message' => 'Няма намерена товарителница' ];
		}
		
		wp_send_json_success( $response );
		wp_die();
	}

	public static function update_shipment_status() {
		nashenski_check_admin_label_actions();
		
		$container = nashenski()->container();
		$order_id = nashenski_label_request( 'orderId' );
		$order = wc_get_order( $order_id );
		$shipment_status = nashenski_label_request( 'shipmentStatus', array() );
		$data = array();
		$order_shipment_status = $order->get_meta( 'nashenski_econt_shipment_status' );

		$response = $container[ Client::ECONT ]->api_call( $container[ Client::ECONT ]::SHIPMENT_STATUS_ENDPOINT, array(
			'shipmentNumbers' => [ $shipment_status['label']['shipmentNumber'] ]
		) );

		$status = array_shift( $response );
		$status = array_shift( $status );
		$data['shipmentStatus'] = $status['status'];
		$order_shipment_status['label'] = $data['shipmentStatus'];
		
		$order->update_meta_data( 'nashenski_econt_shipment_status', $order_shipment_status );
		$order->save();

		wp_send_json_success( $data );
		wp_die();
	}

	public static function generate_label() {
		nashenski_check_admin_label_actions();

		$order_id = $order_id = nashenski_label_request( 'orderId' );
		$label = nashenski_label_request( 'label_data', array() );

		$label = self::update_sender( $label );
		$label = self::update_receiver_address( $label );
		$label = self::update_label_pay_options( $label, $order_id );
		$label = self::update_payment_by( $label, $order_id );
		$label = self::update_test_options( $label );
		$label = self::update_shipment_type( $label );
		$label = self::update_phone_and_names( $label );
		$label = self::update_os_value( $label );
		$label = self::update_weight( $label );

		$data = self::send_label_to_econt( $label, $order_id );

		wp_send_json_success( $data );
		wp_die();
	}

	public static function generate_label_after_order_generated( $order_id ) {
		$order = wc_get_order( $order_id );
		$label = $order->get_meta( 'nashenski_econt_label' );

		if ( !$label ) {
			return;
		}

		$label = $label['label'];
		$label = self::update_sender( $label );
		$label = self::update_weight( $label );
		$label = self::update_label_pay_options( $label, $order_id );
		$label = self::update_phone_and_names( $label, $order_id );

		self::send_label_to_econt( $label, $order_id );
	}

	public static function send_label_to_econt( $label, $order_id ) {
		$data = [];
		$order = wc_get_order( $order_id );
		$action = $order->get_meta( 'nashenski_econt_shipment_status' ) ? 'updated' : 'created';

		$generated_data = self::generate_response( $label, $order_id );
		$response = $generated_data['response'];
		$request_body = $generated_data['request_body'];

		if ( 
			isset( $response['type'] ) && 
			( strpos( $response['type'], 'ExInvalid' ) !== false || strpos( $response['type'], 'ExException' ) !== false )
		) {
			$errors = nashenski()->container()[ Client::ECONT ]::add_error_message( $response );

			$data['message'] = implode('', $errors );
		} else if ( isset( $response['label'] ) ) {
			$request_body['label']['shipmentNumber'] = $response['label']['shipmentNumber'];
			$data['shipmentStatus'] = $response;
			$data['label'] = $request_body['label'];

			$order->update_meta_data( 'nashenski_econt_label', $request_body );
			$order->update_meta_data( 'nashenski_econt_shipment_status', $response );
			$order->save();
			nashenski_add_label_order_note( $order, 'Econt', $action, $response['label']['shipmentNumber'] );

			self::update_order_shipping_price( $response, $order_id, $label );
		}

		do_action( 'nashenski/econt/after_send_label', $data, $order );

		return $data;
	}

	public static function update_sender( $label ) {
		$container = nashenski()->container();

		$label['senderClient'] = $container[ Client::ECONT_PROFILE ]->get_profile_data()['client'];
		$label['senderAgent'] = array(
			'name' => nashenski_get_option( 'econt', 'name' ),
			'phones' => [ nashenski_get_option( 'econt', 'phone' ) ],
		);
		$label['senderAddress'] = self::generate_sender_address();
		$send_from = nashenski_get_option( 'econt', 'send_from' );

		if ( $send_from == 'office' ) {
			$office = nashenski_get_option( 'econt_send_from', 'office' );
			$label['senderOfficeCode'] = str_replace( 'officeID-', '', $office );
		}

		if ( ( null !== nashenski_label_request( 'send_from', null ) ) && ( null !== nashenski_label_request( 'send_from_type', null ) ) ) {
			$new_send_from = nashenski_label_request( 'send_from', array() );
			$new_send_from_type = nashenski_label_request( 'send_from_type' );

			if ( !empty( $new_send_from ) && !empty( $new_send_from_type ) ) {
				switch ( $new_send_from_type ) {
					case 'address':
						$profile_addresses = $container[ Client::ECONT_PROFILE ]->get_profile_data()['addresses'];

						$label['senderAddress'] = $profile_addresses[ $new_send_from ];
						unset( $label['senderOfficeCode'] );
						break;
					case 'office':
						$label['senderOfficeCode'] = str_replace( 'officeID-', '', $new_send_from );
						break;
				}
			}
		}

		return $label;
	}

	public static function update_weight( $label ) {
		$weight = 0;
		
		if ( !empty( $label['packs'] ) ) {
			foreach ( $label['packs'] as $pack ) {
				$weight += isset( $pack['weight'] ) ? floatval( $pack['weight'] ) : 0;
			}
	
			$label['weight'] = $weight;
		} else {
			$label['weight'] = apply_filters( 'nashenski/econt/default_weight', 0.100 );
		}


		return $label;
	}

	public static function set_min_pack_weight( $request_body ) {
		if ( empty( $request_body['label'] ) || ! is_array( $request_body['label'] ) ) {
			return $request_body;
		}

		$min_weight = (float) apply_filters( 'nashenski/econt/min_pack_weight', 0.100 );
		$weight     = 0;

		if ( ! empty( $request_body['label']['packs'] ) && is_array( $request_body['label']['packs'] ) ) {
			foreach ( $request_body['label']['packs'] as &$pack ) {
				if ( ! isset( $pack['weight'] ) || ! is_numeric( $pack['weight'] ) || (float) $pack['weight'] < $min_weight ) {
					$pack['weight'] = $min_weight;
				}

				$weight += (float) $pack['weight'];
			}

			unset( $pack );

			$request_body['label']['weight'] = round( $weight, 3 );
		} elseif ( isset( $request_body['label']['weight'] ) && ( ! is_numeric( $request_body['label']['weight'] ) || (float) $request_body['label']['weight'] < $min_weight ) ) {
			$request_body['label']['weight'] = $min_weight;
		}

		return $request_body;
	}

	public static function generate_sender_address() {
		$container = nashenski()->container();
		$address = '';
		$id = nashenski_get_option( 'econt_send_from', 'address' );
		$send_from = nashenski_get_option( 'econt', 'send_from' );

		if ( $id !== '' && $send_from === 'address' ) {
			$profile_addresses = $container[ Client::ECONT_PROFILE ]->get_profile_data()['addresses'];

			$address = $profile_addresses[ $id ];
		}

		return $address;
	}

	protected static function update_receiver_address( $label ) {
		$container = nashenski()->container();
		$order_id = nashenski_label_request( 'orderId' );
		$order = wc_get_order( $order_id );
		$type = nashenski_label_request( 'type', array() );
		$cookie_data = nashenski_label_request( 'cookie_data', array() );
		$cookie_data['type'] = $type['id'];
		$country = ( $order->get_shipping_country() ) ? $order->get_shipping_country() : $order->get_billing_country();

		unset( $label[ 'receiverOfficeCode' ] );
		unset( $label[ 'receiverAddress' ] );

		if ( in_array( $type['id'], [ 'office', 'automat' ], true ) ) {
			$label[ 'receiverDeliveryType' ] = 'office';
			$office = nashenski_label_request( 'office', array() );
			$label['receiverOfficeCode'] = $office['code'];
			$cookie_data['selectedOffice'] = $office['code'];
			$cookie_data['selectedOfficeIsAPS'] = $office['isAPS'];
		} else {
			$label[ 'receiverDeliveryType' ] = 'door';
			$cookie_data['selectedAddress'] = nashenski_label_request( 'street', array() );
			$cookie_data['streetNumber'] = nashenski_label_request( 'streetNumber' );
			foreach ( array( 'blockNumber', 'entranceNumber', 'floorNumber', 'apartmentNumber' ) as $field ) {
				$cookie_data[ $field ] = nashenski_label_request( $field );
			}
			$cookie_data['other'] = nashenski_label_request( 'other' );

			$states = $container[ Client::ECONT_CITIES ]->get_regions( $country );
			$state = $states[ $cookie_data['state'] ];

			$cities_data = $container[ Client::ECONT_CITIES ]->get_filtered_cities( $cookie_data['city'], $state, $country );
			$cities = $cities_data['cities'];
			$city_key = $cities_data['city_key'];

			$type = ( !empty( $cookie_data['selectedAddress']['type'] ) ) ? $cookie_data['selectedAddress']['type'] :'';

			$receiver_address = array(
				'city' => $cities[ $city_key ],
			);
			
			unset( $receiver_address['city']['servingOffices'] );

			if ( $type === 'streets' ) {
				$receiver_address['street'] = $cookie_data['selectedAddress']['label'];
				if ( trim( (string) $cookie_data['streetNumber'] ) !== '' ) {
					$receiver_address['num'] = $cookie_data['streetNumber'];
				}
			} else if ( $type === 'quarters' ) {
				$receiver_address['quarter'] = $cookie_data['selectedAddress']['label'];
			}

			$additional_parts = array();
			$legacy_other = trim( (string) ( $cookie_data['other'] ?? '' ) );
			if (
				$type === 'quarters' ||
				Address::has_structured_details( $cookie_data ) ||
				$legacy_other === ''
			) {
				$additional_parts[] = $cookie_data['otherField'] ?? '';
			}

			$other = Address::format_other( $cookie_data, $additional_parts );
			$cookie_data['other'] = $other;

			if ( $other !== '' ) {
				$receiver_address['other'] = $other;
			}

			$label['receiverAddress'] = $receiver_address;
		}

		$order->update_meta_data( 'nashenski_econt_cookie_data', $cookie_data );
		$order->save();

		return $label;
	}

	protected static function update_label_pay_options( $label, $order_id ) {
		$order = wc_get_order( $order_id );
		$cookie_data = $order->get_meta( 'nashenski_econt_cookie_data' );

		unset( $label['services']['cdPayOptionsTemplate'] );
		unset( $label['packingListType'] );
		unset( $label['packingList'] );
		unset( $label['instructions'] );

		// Orders made without the checkout picker have no saved choices: use the order's payment method.
		if ( 'cod' === ( is_array( $cookie_data ) && isset( $cookie_data['payment'] ) ? $cookie_data['payment'] : $order->get_payment_method() ) ) {
			$cd_pay_option = nashenski_get_option( 'econt', 'pay_options' );

			if ( $cd_pay_option && $cd_pay_option !== 'no' ) {
				$packing_list_or_invoice = nashenski_get_option( 'econt', 'invoice_or_packing_list' );
				$label['services']['cdPayOptionsTemplate'] = $cd_pay_option;

				if ( $packing_list_or_invoice === 'packing_list' ) {
					unset( $label['services']['invoiceNum'] );
					$label["packingListType"] = 'digital';
					$label["packingList"] = [];
					
					foreach ( $order->get_items() as $key => $item ) {
						if ( $item->get_total() <= 0 ) {
							continue;
						}
						/*
						/ Count is added to the name for a reason.
						/ When you have discount with more than 1 quantity, the label cannot be generated because of differences in the cents.
						*/
						
						$item_weight = ( $item->get_product()->get_weight() ) ? wc_get_weight( $item->get_product()->get_weight(), 'kg' ) * $item['quantity'] : 0.100;

						$label["packingList"][] = [
							'inventoryNum' => $key,
							'description' => nashenski_normalize_text_for_label( $item->get_name() . " x " . $item->get_quantity() ),
							'weight' => number_format( $item_weight, 3 ),
							'count' => 1,
							'price' => number_format( $item->get_total(), 2, '.', '' ) + number_format( $item->get_total_tax(), 2, '.', '' ),
						];
					}

					if ( !empty( $order->get_items( 'fee' ) ) && apply_filters('nashenski/shipping/package_total_includes_fees', true ) ) {
						foreach ( $order->get_items( 'fee' ) as $key => $item ) {
							if ( $item->get_total() <= 0 ) {
								continue;
							}

							$key++;
							
							$rate = nashenski_get_order_item_vat_rate( $item, $order );

							$label["packingList"][] = [
								'inventoryNum' => $key,
								'description' => nashenski_normalize_text_for_label( $item->get_name() . " x " . $item->get_quantity() ),
								'weight' => 0.05,
								'count' => 1,
								'price' => number_format( $item->get_total(), 2, '.', '' ) + number_format( $item->get_total_tax(), 2, '.', '' ),
							];
						}
					}
				} else if ( ( empty( $packing_list_or_invoice ) || $packing_list_or_invoice === 'invoice' ) && empty( $label['services']['invoiceNum'] ) ) {
					$label['services']['invoiceNum'] = self::get_invoice_number( $order_id );
				}
			}
		}
		
		return $label;
	}

	public static function get_invoice_number( $order_id ) {
		$order = wc_get_order( $order_id );
		$cookie_data = $order->get_meta( 'nashenski_econt_cookie_data' );
		$invoice_num = '';

		if ( !empty( $cookie_data['payment'] ) && $cookie_data['payment'] === 'cod' ) {
			$cd_pay_option = nashenski_get_option( 'econt', 'pay_options' );

			if ( $cd_pay_option && $cd_pay_option !== 'no' ) {
				if ( self::use_invoice_number() ) {
					$cd_pay_options = nashenski()->container()[ Client::ECONT_PROFILE ]->get_profile_data()['cdPayOptions'];

					foreach ( $cd_pay_options as $option ) {
						if ( $option['num'] === $cd_pay_option && $option['method'] != 'office' ) {
							$order = wc_get_order( $order_id );
							$document_number = $order->get_meta( 'nashenski_order_number' );

							if ( !$document_number ) {
								$document_number = str_pad( $order->get_id(), 10, '0', STR_PAD_LEFT );
							}

							$invoice_num = $document_number . '/' . gmdate( 'd.m.y', strtotime( $order->get_date_created() ) );
						}
					}
				}
			}
		}

		return $invoice_num;
	}

	public static function use_invoice_number() {
		$cd_pay_option = nashenski_get_option( 'econt', 'pay_options' );
		$use = false;

		if ( $cd_pay_option && $cd_pay_option !== 'no' ) {
			$packing_list_or_invoice = nashenski_get_option( 'econt', 'invoice_or_packing_list' );

			$use = ( !$packing_list_or_invoice || $packing_list_or_invoice === 'invoice' );
		}

		return $use;
	}

	protected static function update_payment_by( $label, $order_id ) {
		$payment_by = nashenski_label_request( 'paymentBy', array() );
		$fixed_price = ( isset( $label['paymentReceiverAmount'] ) ) ? $label['paymentReceiverAmount'] : 0;
		$sender_method = nashenski()->container()[ Client::ECONT_PROFILE ]->get_sender_payment_method();

		unset( 
			$label['paymentReceiverMethod'],
			$label['paymentSenderMethod'],
			$label['paymentReceiverAmount'],
		);

		if ( $payment_by['id'] == 'buyer' ) {
			$label['paymentReceiverMethod'] = 'cash';
		} elseif ( $payment_by['id'] == 'sender' ) {
			$label['paymentSenderMethod'] = $sender_method;
		} elseif ( $payment_by['id'] == 'fixed' ) {
			$label['paymentSenderMethod'] = $sender_method;
			$label['paymentReceiverMethod'] = 'cash';
			$label['paymentReceiverAmount'] = $fixed_price;

			self::update_order_shipping_price( $label, $order_id, $label );
		}
		
		return $label;
	}

	protected static function update_test_options( $label ) {
		$payment_by = nashenski_label_request( 'testOption', array() );
		$label['payAfterAccept'] = false;
		$label['payAfterTest'] = false;

		if ( isset( $label['partialDelivery'] ) ) {
			unset( $label['partialDelivery'] );
		}

		if ( $payment_by['id'] == 'review' ) {
			$label['payAfterAccept'] = true;
		} else if ( $payment_by['id'] == 'test' ) {
			$label['payAfterAccept'] = true;
			$label['payAfterTest'] = true;

			if ( wc_string_to_bool( nashenski_label_request( 'partialDelivery' ) ) ) {
				$label['partialDelivery'] = true;
			}

		}
		
		return $label;
	}

	protected static function update_shipment_type( $label ) {
		$shipment_type = nashenski_label_request( 'shipmentType', array() );
		$label['shipmentType'] = strtolower( $shipment_type['id'] );
		
		return $label;
	}

	protected static function update_phone_and_names( $label, $order_id = null ) {
		if ( !$order_id ) {
			$order_id = nashenski_label_request( 'orderId' );
		}

		if ( !$order_id ) {
			return $label;
		}

		$order = wc_get_order( $order_id );
		$phone = [];
		$name = '';

		unset( $label['receiverClient'] );
		unset( $label['receiverAgent'] );

		if ( $order->get_shipping_first_name() && $order->get_shipping_last_name() ) {
			$name = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
		} else {
			$name = $order->get_billing_first_name() . " " . $order->get_billing_last_name();
		}

		if ( $order->get_shipping_phone() ) {
			$phone = [ $order->get_shipping_phone() ];
		} else {
			$phone = [ $order->get_billing_phone() ];
		}
		
		$label['receiverClient'] = array();
		$label['receiverClient']['name'] = $name;
		$label['receiverClient']['email'] = $order->get_billing_email();
		$label['receiverClient']['phones'] = $phone;
		$label['receiverAgent'] = $label['receiverClient'];
		
		if ( $order->get_meta( '_billing_to_company' ) ) {
			$label['receiverClient']['juridicalEntity'] = true;
			$label['receiverClient']['molName'] = $order->get_meta( '_billing_company_mol' );
			$label['receiverClient']['name'] = $order->get_billing_company();
			
			if ( $order->get_meta( '_billing_vat_number' ) ) {
				$label['receiverClient']['ein'] = substr( $order->get_meta( '_billing_vat_number' ), 2 );
				$label['receiverClient']['ddsEin'] = $label['receiverClient']['ein'];
				$label['receiverClient']['ddsEinPrefix'] = str_replace( $label['receiverClient']['ein'], '', $order->get_meta( '_billing_vat_number' ) );
			}
		}

		return $label;
	}

	protected static function update_os_value( $label ) {
		if ( isset( $label[ 'services' ]['declaredValueAmount'] ) ) {
			unset( $label[ 'services' ]['declaredValueAmount'] );
			unset( $label[ 'services' ]['declaredValueCurrency'] );
		}

		if ( nashenski_label_request( 'declaredValue' ) ) {
			$label[ 'services' ]['declaredValueAmount'] = nashenski_label_request( 'declaredValue' );
			$label[ 'services' ]['declaredValueCurrency'] = get_woocommerce_currency();
		}

		return $label;
	}

	protected static function update_order_shipping_price( $response, $order_id, $request_body = [] ) {
		if ( ( null === nashenski_label_request( 'paymentBy', null ) ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		$price = 0;
		$cookie_data = $order->get_meta( 'nashenski_econt_cookie_data' );

		if ( $order->get_payment_method() !== 'cod' ) { 
			return;
		}

		$payment_by = nashenski_label_request( 'paymentBy', array() );

		if ( !empty( $payment_by ) ) {
			$tax_rate = apply_filters('nashenski/econt/fixed_price_tax_rate', 20, $order->get_shipping_country() );

			if ( $payment_by['id'] == 'buyer' ) {
				$price = nashenski_tax_based_price( $response['label']['receiverDueAmount'], $tax_rate );
			} else if ( $payment_by['id'] == 'fixed' && !empty( $request_body['paymentReceiverAmount'] ) ) {
				$price = nashenski_tax_based_price( $request_body['paymentReceiverAmount'], $tax_rate );
			}
		}

		$price = apply_filters( 'nashenski/admin/econt/price_for_update', $price, $order, nashenski_label_request(), $response, $request_body );

		foreach( $order->get_items( 'shipping' ) as $item_id => $item ) {
			$item->set_total( $price );
			$item->calculate_taxes();
			$item->save();
		}

		$order->calculate_shipping();
		$order->calculate_totals();
		$order->save();
	}

	protected static function generate_response( $label, $order_id ) {
		$container = nashenski()->container();
		$order = wc_get_order( $order_id );
		$shipment_status = $order->get_meta( 'nashenski_econt_shipment_status' );
		$cookie_data = $order->get_meta( 'nashenski_econt_cookie_data' );

		if ( ! is_array( $cookie_data ) ) {
			$cookie_data = array();
		}

		$country = $order->get_shipping_country() ?: $order->get_billing_country();
		$city = $order->get_shipping_city() ?: $order->get_billing_city();
		$state = $order->get_shipping_state() ?: $order->get_billing_state();
		$post_code = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
		$destination = array(
			'country_code' => $cookie_data['country'] ?? $country,
			'name' => $cookie_data['city'] ?? $city,
			'region' => $cookie_data['state'] ?? $state,
			'post_code' => $post_code,
		);

		$label['holidayDeliveryDay'] = 'workday';

		if ( $shipment_status ) {
			if ( !$label['shipmentNumber'] ) {
				$label['shipmentNumber'] = $shipment_status['label']['shipmentNumber'];
			}

			$request_body = apply_filters( 'nashenski/econt/update_label', array(
				'label' => $label,
			), $order );

			$response = $container[ Client::ECONT ]->label_request( 'update', $request_body );
		} else {
			unset( $label['shipmentNumber'] );
			
			$request_body = apply_filters( 'nashenski/econt/create_label', array(
				'label' => $label,
				'mode' => 'create',
			), $order );

			$response = $container[ Client::ECONT ]->label_request( 'create', $request_body, $destination );
		}

		return [
			'response' => $response,
			'request_body' => $request_body,
		];
	}
}
