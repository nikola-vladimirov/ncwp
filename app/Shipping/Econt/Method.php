<?php
namespace Nashenski\Shipping\Econt;
use Nashenski\Container\Client;
use Nashenski\Admin\Econt as Econt_Admin;

use Nashenski\Shipping\Packer\Carton_Packer;
use Nashenski\Shipping\Packer\Product;
use Nashenski\Shipping\Packer\Size;

defined( 'ABSPATH' ) || exit;

class Method extends \WC_Shipping_Method {
	const METHOD_ID = "nashenski_econt";
	public $container, $cookie_data, $package, $delivery_type, $free_shipping_over, $fixed_price, $test, $sms, $tax_status, $free_shipping = false;

	public function __construct( $instance_id = 0 ) {
		$this->container          = nashenski()->container();
		$this->id                 = 'nashenski_econt'; 
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Nashenski Commerce - Econt', 'nashenski-commerce' );  // Title shown in admin
		$this->method_description = __( 'Enables econt delivery and automatically calculate shipping price.', 'nashenski-commerce' ); // Description shown in admin
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);
		$this->enabled = 'yes';

		$this->init();
	}

	/**
	 * Init your settings
	 *
	 * @access public
	 * @return void
	 */
	function init() {
		// Load the settings API
		$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
		$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

		$this->title                = $this->get_option( 'title' );
		$this->delivery_type        = $this->get_option( 'delivery_type' );
		$this->free_shipping_over   = $this->get_option( 'free_shipping_over' );
		$this->fixed_price          = $this->get_option( 'fixed_price' );
		$this->test                 = $this->get_option( 'test' );
		$this->sms                  = $this->get_option( 'sms' );
		$this->tax_status           = ( wc_tax_enabled() ) ? 'taxable' : 'none' ;

		// Save settings in admin if you have any defined
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * calculate_shipping function.
	 *
	 * @access public
	 * @param mixed $package
	 * @return void
	 */
	public function calculate_shipping( $package = Array() ) {
		$this->cookie_data = self::get_cookie_data();
		$this->package = $package;

		$disable_aps = ( $this->delivery_type === 'automat' && ! APSBoxes::package_fits_largest_automat( $this->package ) );

		if( apply_filters( 'nashenski/econt/rate/disable_aps', $disable_aps, $this ) ) {
			return;
		}

		do_action( 'nashenski/econt/rate/before_calculate', $this );

		$rate = array(
			'label' => $this->title,
			'cost' => 0,
			'meta_data' => [],
		);

		$rate['meta_data']['delivery_type'] = $this->delivery_type;
		$rate['meta_data']['validated'] = false;
		$chosen_shippings = WC()->session->get('chosen_shipping_methods');
		$country = apply_filters('nashenski/econt/country_for_validation', $package['destination']['country'], $this );

		if ( 
			isset( $this->cookie_data['type'] ) && 
			$this->cookie_data['type'] === $this->delivery_type && 
			( !empty( $chosen_shippings ) && ( $chosen_shippings[0] === $this->id . ':' . $this->instance_id ) ) &&
			( 
				( !empty( $this->cookie_data['selectedAddress'] ) && $this->has_address_detail() ) ||
				( isset( $this->cookie_data['selectedOffice'] ) && $this->cookie_data['selectedOffice'] ) 
			) 
		) {
			$request_data = $this->calculate_shipping_price_from_api();
			$request_data = \Nashenski\Pricing\Public_Price::fallback( 'econt', $request_data, $this );

			if ( ! empty( $request_data['public_price'] ) ) {
				$rate['meta_data']['price_source'] = 'public';
			}

			$rate['request_data'] = $request_data;
			$rate['meta_data']['validated'] = true;
			$rate['meta_data']['cookie_data'] = $this->cookie_data;

			if ( isset( $request_data['errors'] ) ) {
				$rate['meta_data']['validated'] = false;
				$rate['meta_data']['errors'] = $request_data['errors'];
			} elseif ( $request_data['price']  ) {
				$rate['cost'] = $request_data['price'];

				if ( wc_tax_enabled() ) {
					$rate[ 'taxes' ] = nashenski_get_shipping_rate_taxes( $request_data['price'], $country );
				}
			}

			if ( !$rate['cost'] && $rate['meta_data']['validated'] ) {
				$this->free_shipping = true;
			}
		}

		if ( $this->free_shipping ) {
			$rate['meta_data']['free_shipping'] = true;
			$rate[ 'cost' ] = 0;
			unset( $rate[ 'taxes' ] );
		} 

		$rate = apply_filters( 'nashenski/econt/rate', $rate, $this );

		// Register the rate
		$this->add_rate( $rate );
	}

	private function has_address_detail() {
		return self::has_address_detail_data( $this->cookie_data );
	}

	private static function has_address_detail_data( $cookie_data ) {
		$structured_fields = array( 'blockNumber', 'entranceNumber', 'floorNumber', 'apartmentNumber' );
		$type = $cookie_data['selectedAddress']['type'] ?? '';

		if ( $type === 'streets' ) {
			return isset( $cookie_data['streetNumber'] ) && trim( (string) $cookie_data['streetNumber'] ) !== '';
		}

		if ( $type !== 'quarters' ) {
			return false;
		}

		foreach ( $structured_fields as $field ) {
			if ( isset( $cookie_data[ $field ] ) && trim( (string) $cookie_data[ $field ] ) !== '' ) {
				return true;
			}
		}

		// Cookies created by the old checkout only contain the combined field.
		$has_structured_keys = (bool) array_intersect( $structured_fields, array_keys( $cookie_data ) );

		$legacy_other = isset( $cookie_data['other'] ) ? trim( (string) $cookie_data['other'] ) : '';

		return !$has_structured_keys && $legacy_other !== '';
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title'            => array(
				'title'       => __( 'Title', 'nashenski-commerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'nashenski-commerce' ),
				'default'     => __( 'Econt', 'nashenski-commerce' ),
				'desc_tip'    => true,
			),
			'delivery_type'    => array(
				'title'             => __( 'Delivery Type', 'nashenski-commerce' ),
				'type'              => 'select',
				'css'               => 'width: 400px;',
				'default'           => '',
				'options'           => array(
					'office' => __( 'Office', 'nashenski-commerce' ),
					'automat' => __( 'Automat', 'nashenski-commerce' ),
					'address' => __( 'Address', 'nashenski-commerce' ),
				),
			),
			'test'    => array(
				'title'             => __( 'Review and test', 'nashenski-commerce' ),
				'type'              => 'select',
				'css'               => 'width: 400px;',
				'default'           => 'no',
				'options'           => nashenski_get_shipping_tests_options(),
			),
			'sms'    => array(
				'title'             => __( 'SMS notification', 'nashenski-commerce' ),
				'type'              => 'select',
				'css'               => 'width: 400px;',
				'default'           => 'no',
				'options'           => array(
					'no' => __( 'No', 'nashenski-commerce' ),
					'yes' => __( 'Yes', 'nashenski-commerce' ),
				),
				'description' => __( 'Send customer notification for delivery.', 'nashenski-commerce' ),
			),
			'free_shipping_over' => array(
				'title'       => __( 'Free shipping over', 'nashenski-commerce' ),
				'type'        => 'number',
				'placeholder' => '0',
				'description' => __( 'Free shipping over total cart price.', 'nashenski-commerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'fixed_price' => array(
				'title'       => __( 'Maximum price payed by the client', 'nashenski-commerce' ),
				'type'        => 'number',
				'placeholder' => '0',
				'description' => __( 'Enter a fixed price that the users will pay. The remaining will be payed by you.', 'nashenski-commerce' ) . __( 'Can be used only in Bulgaria', 'nashenski-commerce' ),
				'default'     => '',
				'desc_tip'    => true,
			)
		);
	}

	public function is_available( $package ) {
		return in_array( $package['destination']['country'], apply_filters( 'nashenski/econt/available_countries', array( 'BG' ) ), true );
	}

	public function get_instance_form_fields() {
		return parent::get_instance_form_fields();
	}

	public static function get_cookie_data() {
		return nashenski_courier_cookie( 'nashenski--econt-address' );
	}

	public function calculate_shipping_price_from_api() {
		$data = array(
			'price' => '0',
		);
		
		$request_body = apply_filters( 'nashenski/econt/calculate_label', array(
			'label' => $this->generate_label(),
			'mode' => 'calculate',
		), $this );

		if ( isset( $request_body['label']['senderOfficeCode'] ) ) {
			unset( $request_body['label']['senderAddress'] );
		}

		WC()->session->set( 'nashenski-econt-label' , $request_body );

		$request = $this->container[ Client::ECONT ]->label_request( 'calculate', $request_body, array(
			'country_code' => $this->cookie_data['country'] ?? ( $this->package['destination']['country'] ?? '' ),
			'name' => $this->cookie_data['city'] ?? ( $this->package['destination']['city'] ?? '' ),
			'region' => $this->cookie_data['state'] ?? ( $this->package['destination']['state'] ?? '' ),
			'post_code' => $this->package['destination']['postcode'] ?? '',
		) );

		if ( !isset( $request ) ) {
			$data['errors'] = array(
				'message' => __( 'Calculation failed. Please try again.', 'nashenski-commerce' ),
			);
		} else if ( isset( $request['success'] ) && !$request['success'] && isset( $request['error'] ) ) {
			$data['errors'] = array(
				'message' => $request['error'],
			);
		} else if (
			isset( $request['type'] ) && 
			( 
				$request['type'] === 'ExInvalidParam' ||
				$request['type'] === 'ExException' ||
				$request['type'] === 'ExInvalidData' 
			)
		) {
			$data['errors'] = $request;
		} else if ( $this->cookie_data[ 'country' ] !== 'BG' ) {
			$data['price'] = nashenski_tax_based_price( $request['label']['senderDueAmount'] );
		} else if ( isset( $request['label']['receiverDueAmount'] ) ) {
			$data['price'] = nashenski_tax_based_price( $request['label']['receiverDueAmount'] );
		}

		return $data;
	}

	private function generate_label() {
		$label = array(
			'senderClient' => $this->generate_sender_data(),
			'senderAgent' => $this->generate_sender_auth(),
			'senderAddress' => $this->generate_sender_address(),
		);

		$send_from = nashenski_get_option( 'econt', 'send_from' );

		if ( $send_from == 'office' ) {
			$label['senderOfficeCode'] = $this->generate_sender_office_code();
		}

		if ( $this->cookie_data ) {
			if ( isset( $this->cookie_data['billing_to_company'] ) && $this->cookie_data['billing_to_company'] ) {
				$label['receiverAgent'] = $this->generate_receiver_data();
				$label['receiverClient'] = $this->generate_receiver_data();
				$label['receiverClient']['juridicalEntity'] = true;
				$label['receiverClient']['molName'] = $this->cookie_data['billing_company_mol'];
				
				if ( !empty( $this->cookie_data['billing_company'] ) ) {
					$label['receiverClient']['name'] = $this->cookie_data['billing_company'];
				}

				if ( $this->cookie_data['billing_vat_number'] ) {
					$vat_number = $this->cookie_data['billing_vat_number'];
					$label['receiverClient']['ein'] = substr( $vat_number, 2 );
					$label['receiverClient']['ddsEin'] = $label['receiverClient']['ein'];
					$label['receiverClient']['ddsEinPrefix'] = str_replace( $label['receiverClient']['ein'], '', $vat_number );
				}
			} else {
				$label['receiverClient'] = $this->generate_receiver_data();
				$label['receiverAgent'] = $this->generate_receiver_data();
			}

			if ( $this->cookie_data['type'] === 'address' ) {
				$label['receiverAddress'] = $this->generate_receiver_address();
			} else if ( in_array( $this->cookie_data['type'], [ 'office', 'automat' ], true ) ) {
				$label['receiverOfficeCode'] = $this->generate_receiver_office_code();
			}
		}

		$cart_data = $this->generate_cart_data();
		$other_data = $this->generate_other_data();
		$payment_by_data = $this->generate_payment_by_data();

		if ( isset( $this->cookie_data[ 'country' ] ) && $this->cookie_data[ 'country' ] !== 'BG' ) {
			unset( $payment_by_data[ 'paymentReceiverMethod' ] );
			unset( $payment_by_data[ 'paymentReceiverAmount' ] );

			$payment_by_data['paymentSenderMethod'] = $this->container[ Client::ECONT_PROFILE ]->get_sender_payment_method();
		}

		return array_merge( $label, $cart_data, $other_data, $payment_by_data );
	}

	private function generate_sender_auth() {
		return array(
			'name' => nashenski_get_option( 'econt', 'name' ),
			'phones' => [ nashenski_get_option( 'econt', 'phone' ) ],
		);
	}

	private function generate_sender_data() {
		$profile = $this->container[ Client::ECONT_PROFILE ]->get_profile_data();

		// No profile while the Econt login is missing or wrong.
		return is_array( $profile ) && isset( $profile['client'] ) ? $profile['client'] : null;
	}

	private function generate_sender_address() {
		$address = '';
		$id = nashenski_get_option( 'econt_send_from', 'address' );
		$send_from = nashenski_get_option( 'econt', 'send_from' );

		if ( $id !== '' && $send_from === 'address' ) {
			$profile_addresses = $this->container[ Client::ECONT_PROFILE ]->get_profile_data()['addresses'];

			$address = $profile_addresses[ $id ];
		}

		return $address;
	}

	private function generate_sender_office_code() {
		$office = nashenski_get_option( 'econt_send_from', 'office' );
		
		return str_replace( 'officeID-', '', $office );
	}

	private function generate_receiver_data() {
		$session_customer = WC()->session->get( 'customer' );

		$data = array(
			'name' => $this->cookie_data[ 'receiver' ],
			'phones' => array( $this->cookie_data[ 'phone' ] ),
		);

		if ( !empty( $session_customer[ 'email' ] ) ) {
			$data['email'] = $session_customer[ 'email' ];
		}

		return $data;
	}

	private function generate_receiver_address() {
		if ( empty( $this->cookie_data['state'] ) ) {
			return [];
		}
		
		$country = $this->cookie_data['country'];
		$states = $this->container[ Client::ECONT_CITIES ]->get_regions( $country );
		$state = $states[ $this->cookie_data['state'] ];
		$cities = $this->container[ Client::ECONT_CITIES ]->get_filtered_cities( $this->cookie_data['city'], $state, $country );
		$city_key = $cities['city_key'];
		$type = ( !empty( $this->cookie_data['selectedAddress']['type'] ) ) ? $this->cookie_data['selectedAddress']['type'] :'';

		if ( empty( $cities['cities'][ $city_key ] ) ) {
			return [];
		}

		$receiver_address = array(
			'city' => $cities['cities'][ $city_key ],
		);

		unset( $receiver_address['city']['servingOffices'] );

		if ( $type === 'streets' ) {
			$street_number = trim( (string) ( $this->cookie_data['streetNumber'] ?? '' ) );
			$num_parts = $street_number === '' ? array() : preg_split( '/\s+/', $street_number );
			$receiver_address['street'] = $this->cookie_data['selectedAddress']['label'];
			$num = !empty( $num_parts ) ? array_shift( $num_parts ) : '';
			$this->cookie_data['streetNumber'] = $num;
			$additional_parts = array();

			if ( $num !== '' ) {
				$receiver_address['num'] = $num;
			}

			if ( !empty( $num_parts ) ) {
				$additional_parts[] = implode( ' ', $num_parts );
			}

			$legacy_other = trim( (string) ( $this->cookie_data['other'] ?? '' ) );
			if (
				!empty( $this->cookie_data['otherField'] ) &&
				( Address::has_structured_details( $this->cookie_data ) || $legacy_other === '' )
			) {
				$additional_parts[] = $this->cookie_data['otherField'];
			}

			$other = Address::format_other( $this->cookie_data, $additional_parts );
			$this->cookie_data['other'] = $other;

			if ( $other !== '' ) {
				$receiver_address['other'] = $other;
			}
		} else if ( $type === 'quarters' ) {
			$receiver_address['quarter'] = $this->cookie_data['selectedAddress']['label'];
			$other = Address::format_other( $this->cookie_data, array( $this->cookie_data['otherField'] ?? '' ) );
			$this->cookie_data['other'] = $other;

			if ( $other !== '' ) {
				$receiver_address['other'] = $other;
			}
		}

		return $receiver_address;
	}

	private function generate_receiver_office_code() {
		return ( isset( $this->cookie_data['selectedOffice'] ) ) ? $this->cookie_data['selectedOffice'] : '';
	}

	private function generate_cart_data() {
		$names = array();
		$cart = array(
			'packCount' => 1,
			'shipmentType' => 'PACK',
			'weight' => 0,
		);

		$sizes = [];
		$auto_sizes = wc_string_to_bool( nashenski_get_option( 'econt', 'auto_size' ) );
		$is_fragile = wc_string_to_bool( nashenski_get_option( 'econt', 'declared_value' ) );
		
		foreach ( $this->package[ 'contents' ] as $key => $item ) {
			if ( $item['data']->get_weight() ) {
				$cart['weight'] += wc_get_weight( $item['data']->get_weight(), 'kg' ) * $item['quantity'];
			}

			if ( $cart['weight'] > 0 && $cart['weight'] < 0.100 ) {
				$cart['weight'] = 0.100;
			}

			$force = nashenski_get_option( 'econt', 'force_variations_in_desc' );

			$name = $item['data']->get_name();

			if ( $force === 'yes' && is_a( $item['data'], 'WC_Product_Variation' ) ) {
				if ( $item['data']->get_attribute_summary() ) {
					$name .= ' - ' . $item['data']->get_attribute_summary();
				} else if( !empty( $item['data']->get_attributes() ) ) {
					$name .= ' - ' . implode(', ', $item['data']->get_attributes() );
				}
			}

			$name = nashenski_normalize_text_for_label( $name );
			$names[] = $name;

			if ( $auto_sizes && $item['data']->get_length() && $item['data']->get_width() && $item['data']->get_height() ) {
				$quantity = ! empty( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1;

				foreach ( range( 1, $quantity ) as $i ) {
					$sizes[] = new Product( 
						$name, 
						new Size( 
							wc_get_dimension( $item['data']->get_length(), 'mm', get_option( 'woocommerce_dimension_unit' ) ), 
							wc_get_dimension( $item['data']->get_width(), 'mm', get_option( 'woocommerce_dimension_unit' ) ), 
							wc_get_dimension( $item['data']->get_height(), 'mm', get_option( 'woocommerce_dimension_unit' ) ),
						) 
					);
				}
			}
		}

		if ( !$cart['weight'] ) {
			$cart['weight'] = apply_filters( 'nashenski/econt/label/weight', 1, $this->package, $this );
		}

		$cart['shipmentDescription'] = nashenski_normalize_text_for_label( implode( ', ', $names ), 500 );
		
		if ( $auto_sizes && $this->delivery_type !== 'automat' ) {
			$pack = array(
				'width'  => 17,
				'height' => 17,
				'length' => 17,
				'weight' => $cart['weight'],
			);

			if ( ! empty( $sizes ) ) {
				$packer = new Carton_Packer();
				$result = $packer->find_best_carton( $sizes );

				$pack['width']  = wc_get_dimension( $result->W, 'cm', 'mm' );
				$pack['height'] = wc_get_dimension( $result->H, 'cm', 'mm' );
				$pack['length'] = wc_get_dimension( $result->L, 'cm', 'mm' );
			}

			$cart['packCount'] = 1;
			$cart['packs']     = array( $pack );
		} else {
			$cart['packCount'] = 1;
			$cart['packs']     = [
				[
					'width'  => 17,
					'height' => 17,
					'length' => 17,
					'weight' => $cart['weight']
				]
			];
		}

		if ( $this->cookie_data['payment'] === 'cod' ) {
			$cart['services']['cdType'] = 'get';
			$cart['services']['cdAmount'] = nashenski_get_package_total();
			$cart['services']['cdCurrency'] = get_woocommerce_currency();

			$cd_pay_option = nashenski_get_option( 'econt', 'pay_options' );
			
			if ( $cd_pay_option && $cd_pay_option !== 'no' ) {
				$cart['services']['cdPayOptionsTemplate'] = $cd_pay_option;
			}
		}

		if ( $is_fragile && empty( $this->cookie_data['selectedOfficeIsAPS'] ) ) {
			$cart[ 'services' ]['declaredValueAmount'] = nashenski_get_package_total();
			$cart[ 'services' ]['declaredValueCurrency'] = get_woocommerce_currency();
		}

		$send_from = nashenski_get_option( 'econt', 'send_from' );

		if ( $send_from === 'office' ) {
			$offices = $this->container[ Client::ECONT_OFFICES ]->get_formatted_offices( nashenski_get_option( 'econt_send_from', 'office_city' ) );
			$office = nashenski_get_option( 'econt_send_from', 'office' );

			if ( !empty( $offices['aps'] ) && array_key_exists( $office, $offices['aps'] ) ) {
				unset( $cart[ 'services' ]['declaredValueAmount'] );
				unset( $cart[ 'services' ]['declaredValueCurrency'] );
			}
		}

		if ( $this->sms === 'yes' ) {
			$cart['services']['smsNotification'] = true;
		}

		return $cart;
	}

	private function generate_other_data() {
		$other_data = [];
		
		if ( empty( $this->cookie_data['selectedOfficeIsAPS'] ) ) {
			if ( $this->test == 'review' ) {
				$other_data['payAfterAccept'] = true;
			} else if ( $this->test == 'test' ) {
				$other_data['payAfterAccept'] = true;
				$other_data['payAfterTest'] = true;
			}
		}

		return $other_data;
	}
	
	private function generate_payment_by_data() {
		$payment_by_data = array(
			'paymentReceiverMethod' => 'cash',
		);

		if ( !empty( $this->fixed_price ) && isset( $this->cookie_data['country'] ) && $this->cookie_data['country'] === 'BG' ) {
			$payment_by_data['paymentSenderMethod'] = $this->container[ Client::ECONT_PROFILE ]->get_sender_payment_method();
			$payment_by_data['paymentReceiverMethod'] = 'cash';
			$payment_by_data['paymentReceiverAmount'] = $this->fixed_price;
		}

		if ( 
			nashenski_has_free_shipping_coupon_in_cart() || 
			( !empty( $this->free_shipping_over ) && nashenski_get_package_total() > $this->free_shipping_over )
		) {
			$this->free_shipping = true;

			unset( $payment_by_data[ 'paymentReceiverMethod' ] );
			unset( $payment_by_data[ 'paymentReceiverAmount' ] );

			$payment_by_data['paymentSenderMethod'] = $this->container[ Client::ECONT_PROFILE ]->get_sender_payment_method();
		}

		return $payment_by_data;
	}

	public static function validate_econt_method( $fields, $errors ){
		if ( ! WC()->cart->needs_shipping() ) {
			return;
		}

		$chosen_shippings = WC()->session->get('chosen_shipping_methods');

		foreach ( $chosen_shippings as $key => $shipping ) {
			if ( strpos( $shipping, 'nashenski_econt' ) !== false ) {
				$data = nashenski_chosen_rate( $key, $shipping );

				if ( ! $data ) {
					continue;
				}

				if ( $data->method_id === 'nashenski_econt' && ! $data->meta_data['validated'] ) {
					$meta_data = $data->get_meta_data();

					if ( !empty( $meta_data['errors'] ) && !empty( array_filter( $meta_data['errors'] ) ) ) {
						$message = array_merge( array( __( 'Econt - ', 'nashenski-commerce' ) ) , nashenski()->container()[ Client::ECONT ]::add_error_message( $meta_data['errors'] ) );
						$errors->add( 'validation', implode( ' ', $message ) );
					} else {
						$cookie_data = self::get_cookie_data();
						$address_type = $cookie_data['selectedAddress']['type'] ?? '';

						if (
							!empty( $cookie_data['type'] ) &&
							$cookie_data['type'] === 'address' &&
							!empty( $cookie_data['selectedAddress'] ) &&
							!self::has_address_detail_data( $cookie_data )
						) {
							if ( $address_type === 'streets' ) {
								$errors->add( 'validation', __( 'Street number is required.', 'nashenski-commerce' ) );
							} else if ( $address_type === 'quarters' ) {
								$errors->add( 'validation', __( 'Fill in at least one of block, entrance, floor or apartment.', 'nashenski-commerce' ) );
							}
						} else {
							$errors->add( 'validation', __( 'Please choose delivery option!', 'nashenski-commerce' ) );
						}
					}
				} 

				if ( $data->method_id === 'nashenski_econt' ) { 
					$cookie_data = self::get_cookie_data();

					if ( 
						! empty( $cookie_data ) && 
						( !empty( $cookie_data['type'] ) && $cookie_data['type'] === 'office' ) &&  
						empty( $cookie_data['selectedOffice'] ) 
					) {
						$errors->add( 'validation', __( 'Please choose a office.', 'nashenski-commerce' ) );
					}

					if (
						! empty( $cookie_data ) &&
						( !empty( $cookie_data['type'] ) && $cookie_data['type'] === 'automat' ) &&
						empty( $cookie_data['selectedOffice'] )
					) {
						$errors->add( 'validation', __( 'Please choose a automat.', 'nashenski-commerce' ) );
					}

					if(
						! empty( $cookie_data ) && 
						( !empty( $cookie_data['type'] ) && $cookie_data['type'] === 'address' ) &&  
						empty( $cookie_data['selectedAddress'] ) 
					) {
						$errors->add( 'validation', __( 'Please choose address.', 'nashenski-commerce' ) ); 
					}
				}
			}
		}
	}

	public static function save_label_data_to_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( !empty( $order->get_items( 'shipping' ) ) ) {
			foreach ( $order->get_items( 'shipping' ) as $shipping ) {
				if ( $shipping['method_id'] === 'nashenski_econt' ) {
					$cookie_data = '';

					foreach ( $shipping->get_meta_data() as $meta_data ) {
						$data = $meta_data->get_data();

						if ( $data['key'] == 'cookie_data' ) {
							$cookie_data = $data['value'];
						}
					}

					if ( $cookie_data ) {
						$order->update_meta_data( 'nashenski_econt_cookie_data', $cookie_data );
					}
					
					if ( WC()->session->get( 'nashenski-econt-label' ) ) {
						$label = WC()->session->get( 'nashenski-econt-label' );

						if ( isset( $cookie_data['payment'] ) ) {
							$container = nashenski()->container();

							if ( $cookie_data['payment'] !== 'cod' ) {
								unset( 
									$label['label']['paymentReceiverMethod'],
									$label['label']['paymentReceiverAmount'],
									$label['label']['paymentSenderMethod'],
								);
								
								$label['label']['paymentSenderMethod'] = $container[ Client::ECONT_PROFILE ]->get_sender_payment_method();
							} else if ( 
								$cookie_data['country'] !== 'BG' && 
								!$shipping->free_shipping &&
								empty( $cookie_data['fixed_price'] )
							) {
								$label['label']['paymentReceiverMethod'] = 'cash';
							}	
						}

						$order->update_meta_data( 'nashenski_econt_label', apply_filters( 'nashenski/econt/label_before_save', $label, $order ) );
						WC()->session->__unset( 'nashenski-econt-label' );
					}

					$order->save();
					break;
				}
			}
		}
	}

	public static function add_label_number_to_email( $order, $sent_to_admin, $plain_text, $email ) {
		$email_ids_to_send = [ 'customer_completed_order' ];

		if ( nashenski_get_option( 'econt', 'label_after_checkout' ) === 'yes' ) {
			$email_ids_to_send[] = 'customer_processing_order';
			$email_ids_to_send[] = 'customer_on_hold_order';
		}
		
		$email_ids_to_send = apply_filters( 'nashenski/econt/emails_to_send_label_number', $email_ids_to_send );

		if ( !in_array( $email->id, $email_ids_to_send ) ) {
			return;
		}

		$label = $order->get_meta( 'nashenski_econt_label' );

		if ( !isset( $label['label']['shipmentNumber'] ) ) {
			return;
		}

		$number = $label['label']['shipmentNumber'];
		$url = \Nashenski\Shipping\Automation::tracking_url( 'econt', $number );

		$track_number_text = sprintf( 
			/* translators: 1: waybill number, 2: tracking link */
			__( 'Label number: %1$s. %2$s', 'nashenski-commerce' ), 
			$number, 
			sprintf( '<a href="%s" target="_blank">%s</a>',
				$url,
				__( 'Track your order.' , 'nashenski-commerce' )
			)
		);

		$track_number_text = apply_filters( 'nashenski/econt/track_number_text_in_email', $track_number_text, $url, $order );

		echo wp_kses_post( wpautop( $track_number_text ) );
	}
}
