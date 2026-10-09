<?php
namespace Nashenski\Shipping\CVC;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

class Method extends \WC_Shipping_Method {
	const METHOD_ID = "nashenski_cvc";
	public $container, $cookie_data, $package, $delivery_type, $free_shipping_over, $fixed_price, $test, $is_sat, $sms, $tax_status, $free_shipping = false;

	public function __construct( $instance_id = 0 ) {
		$this->container          = nashenski()->container();
		$this->id                 = 'nashenski_cvc'; 
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Nashenski Commerce - CVC', 'nashenski-commerce' );  // Title shown in admin
		$this->method_description = __( 'Enables CVC delivery and automatically calculate shipping price.', 'nashenski-commerce' ); // Description shown in admin
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
		$this->is_sat               = $this->get_option( 'is_sat' );
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
		$this->cookie_data = $this->get_cookie_data();
		$this->package = $package;

		do_action( 'nashenski/cvc/rate/before_calculate', $this );

		if ( is_array( $this->cookie_data ) ) {
			$this->cookie_data['fixed_price'] = $this->fixed_price;
		}

		$rate = array(
			'label' => $this->title,
			'meta_data' => [],
		);

		$rate['meta_data']['delivery_type'] = $this->delivery_type;
		$rate['meta_data']['validated'] = false;
		$payment_by_data = $this->generate_payment_by_data();
		$chosen_shippings = WC()->session->get('chosen_shipping_methods');

		if ( 
			isset( $this->cookie_data['type'] ) && 
			$this->cookie_data['type'] === $this->delivery_type && 
			( !empty( $chosen_shippings ) && ( $chosen_shippings[0] === $this->id . ':' . $this->instance_id ) ) &&
			( 
				( isset( $this->cookie_data['other'] ) && $this->cookie_data['other'] && $this->cookie_data['selectedAddress'] ) || 
				( isset( $this->cookie_data['streetNumber']) && $this->cookie_data['streetNumber'] && $this->cookie_data['selectedAddress'] ) || 
				( isset( $this->cookie_data['selectedOffice'] ) && $this->cookie_data['selectedOffice'] ) 
			) 
		) {
			if ( empty( $this->fixed_price ) && $payment_by_data[ 'payer' ] !== 'rec') {
				$this->free_shipping = true;
			}

			$request_data = $this->calculate_shipping_price_from_api();
			$request_data = \Nashenski\Pricing\Public_Price::fallback( 'cvc', $request_data, $this );

			if ( ! empty( $request_data['public_price'] ) ) {
				$rate['meta_data']['price_source'] = 'public';
			}
			$rate['meta_data']['validated'] = true;
			$rate['request_data'] = $request_data;
			
			if ( isset( $request_data['errors'] ) ) {
				$rate['meta_data']['validated'] = false;
				$rate['meta_data']['errors'] = $request_data['errors'];
			} elseif ( isset( $request_data['price'] )  ) {
				$rate['cost'] = $request_data['price'];
			}
		}

		if ( $this->free_shipping ) {
			$rate['meta_data']['free_shipping'] = true;
			$rate[ 'cost' ] = 0;
		} 

		if ( !$this->free_shipping && !empty( $this->fixed_price ) ) {
			$rate[ 'cost' ] = nashenski_tax_based_price( $this->fixed_price );
		}
		
		$rate['meta_data']['cookie_data'] = $this->cookie_data;
		$rate = apply_filters( 'nashenski/cvc/rate', $rate, $this );

		// Register the rate
		$this->add_rate( $rate );
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
				'default'     => __( 'CVC', 'nashenski-commerce' ),
				'desc_tip'    => true,
			),
			'delivery_type'    => array(
				'title'             => __( 'Delivery Type', 'nashenski-commerce' ),
				'type'              => 'select',
				'css'               => 'width: 400px;',
				'default'           => '',
				'options'           => array(
					'office' => __( 'Office', 'nashenski-commerce' ),
					'address' => __( 'Address', 'nashenski-commerce' ),
				),
			),
			'test'    => array(
				'title'             => __( 'Review and test', 'nashenski-commerce' ),
				'type'              => 'select',
				'css'               => 'width: 400px;',
				'default'           => 'no',
				'options'           => array(
					'no' => __( 'No', 'nashenski-commerce' ),
					'review' => __( 'Review only', 'nashenski-commerce' ),
					'test' => __( 'Review and test', 'nashenski-commerce' ),
				),
			),
			'is_sat'    => array(
				'title'             => __( 'Saturday Delivery', 'nashenski-commerce' ),
				'type'              => 'select',
				'css'               => 'width: 400px;',
				'default'           => 'no',
				'options'           => array(
					'no' => __( 'No', 'nashenski-commerce' ),
					'yes' => __( 'Yes', 'nashenski-commerce' ),
				),
				'description' => __( 'Saturday delivery/pickup.', 'nashenski-commerce' ),
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
				'title'       => __( 'Fixed price', 'nashenski-commerce' ),
				'type'        => 'number',
				'placeholder' => '0',
				'description' => __( 'Enter a fixed price that will be payed by you, and will be included in the order.', 'nashenski-commerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	public function is_available( $package ) {
		return 'BG' === $package['destination']['country'];
	}

	public function get_instance_form_fields() {
		return parent::get_instance_form_fields();
	}

	public static function get_cookie_data() {
		return nashenski_courier_cookie( 'nashenski--cvc-address' );
	}

	public function calculate_shipping_price_from_api() {
		// No login: the public price list prices the order (Public_Price::fallback).
		if ( ! \Nashenski\Front_End\Checkout\Pickers::connected( 'cvc' ) ) {
			return array( 'errors' => array( 'not_connected' ) );
		}

		$data = array(
			'price' => '0',
		);
		
		$request_body = apply_filters( 'nashenski/cvc/calculate_label', $this->generate_label(), $this );

		WC()->session->set( 'nashenski-cvc-label' , $request_body );

		$request = $this->container[ Client::CVC ]->api_call( $this->container[ Client::CVC ]::CALC_LABELS_ENDPOINT, $request_body );
		
		if ( !isset( $request ) ) {
			$data['errors'] = __( 'Calculation failed. Please try again.', 'nashenski-commerce' );
		} else if ( !$request['success'] ) {
			$data['errors'] = $request['error'];
		} else if ( isset( $request['price'] ) ) {
			$data['price_with_vat'] = number_format( $request['price_with_vat'], 2 );
			$data['price_without_vat'] = number_format( $request['price'], 2 );
			$data['vat'] = number_format( $request['price_with_vat'] - $request['price'], 2 );
			$data['price'] = ( wc_tax_enabled() ) ? $data['price_without_vat'] : $data['price_with_vat'];
		}

		return $data;
	}

	private function generate_label() {
		$label = array(
			"pickup_date" => date_i18n( 'Y-m-d' ), // "2020-10-27
			"total_parcels" => '1', // "2020-10-27
			"sender" => $this->generate_sender_data(),
		);

		if ( $this->cookie_data ) {
			$label['rec'] = $this->generate_receiver_data();
		}

		$cart_data = $this->generate_cart_data();
		$other_data = $this->generate_other_data();
		$payment_by_data = $this->generate_payment_by_data();

		return array_merge( $label, $cart_data, $other_data, $payment_by_data );
	}

	private function generate_sender_data() {
		$city = str_replace( 'cityID-', '', nashenski_get_option( 'cvc_sender', 'city' ) );

		$sender_data = array( 
			"name" => nashenski_get_option( 'cvc_sender', 'name' ), 
			"phone" => nashenski_get_option( 'cvc_sender', 'phone' ), 
			"email" => nashenski_get_option( 'cvc_sender', 'email' ), 
			"country_id" => 100, 
			"city_id" => $city,
			"zip" => $this->container[ Client::CVC_CITIES ]->get_city_zip_by_id( $city ),
		);

		$send_from = nashenski_get_option( 'cvc_sender', 'send_from' );

		if ( $send_from === 'address' ) {
			$sender_data[ "custom_location_id" ] = nashenski_get_option( 'cvc_sender', 'address' );
		} else if ( $send_from === 'office' ) {
			$sender_data[ "hub_id" ] = str_replace( 'hubID-', '', nashenski_get_option( 'cvc_sender', 'office' ) );
		}

		return $sender_data;
	}

	private function generate_receiver_data() {
		$rec = [
			"name" => $this->cookie_data['receiver'], 
			"phone" => $this->cookie_data['phone'],
		];

		if ( $this->cookie_data['type'] === 'address' ) {
			$country = ( !empty( $this->cookie_data['country'] ) ) ? sanitize_text_field( $this->cookie_data['country'] ) : 100;
			$country_id = $this->container[ Client::CVC_COUNTRIES ]->get_country_id( $country );
			$raw_city = ( !empty( $this->cookie_data['city'] ) ) ? sanitize_text_field( $this->cookie_data['city'] ) : '';
			$state_id = ( !empty( $this->cookie_data['state'] ) ) ? $this->container[ Client::CVC_CITIES ]->get_state_id( sanitize_text_field( $this->cookie_data['state'] ), $country_id ) : '';
			$city = $this->container[ Client::CVC_CITIES ]->search_for_city( $raw_city, $state_id, $country_id );

			if ( empty( $city ) ) {
				return( [] );
			}
			
			$rec['country_id'] = $country_id;
			$rec['city_id'] = $city[0]['id'];
			$rec['zip'] = $city[0]['zip'];

			if ( $this->cookie_data['selectedAddress']['type'] === 'streets' ) {
				$rec["street_id"] = str_replace('street-', '', $this->cookie_data['selectedAddress']['orig_key'] ); 
				$rec["num"] = $this->cookie_data['streetNumber'];
			} else if ( $this->cookie_data['selectedAddress']['type'] === 'quarters' ) {
				$rec["qt_id"] = str_replace('qtr-', '', $this->cookie_data['selectedAddress']['orig_key'] );

				if ( !empty( $this->cookie_data[ 'other' ] ) ) {
					$parts = explode( ' ', $this->cookie_data[ 'other' ] );

					$rec["block"] = $parts[0];

					if ( isset( $parts[1] ) ) {
						$rec["entr"] = $parts[1];
					}

					if ( isset( $parts[2] ) ) {
						$rec["floor"] = $parts[2];
					}

					if ( isset( $parts[3] ) ) {
						$rec["ap"] = $parts[3];
					}

					if ( isset( $parts[4] ) ) {
						unset( $parts[0], $parts[1], $parts[2], $parts[3] );

						$rec["notes"] = implode( ' ', $parts ) ;
					}
				}
			}
		} else if ( $this->cookie_data['type'] === 'office' ) {
			$rec["office_id"] = $this->cookie_data['selectedOffice'];
		}

		return $rec;
	}

	private function generate_cart_data() {
		$names = array();
		$cart = array(
			'parcel_type' => 'parcel',
			'total_kgs' => 0,
			'reject_payer' => 'rec',
		);

		foreach ( $this->package[ 'contents' ] as $key => $item ) {
			if ( $item['data']->get_weight() ) {
				$cart['total_kgs'] += wc_get_weight( $item['data']->get_weight(), 'kg' ) * $item['quantity'];
			}

			$names[] = $item['data']->get_name();
		}

		if ( !$cart['total_kgs'] ) {
			$cart['total_kgs'] = apply_filters( 'nashenski/cvc/label/weight', 1, $this->package, $this );
		}

		$cart['description'] = implode( ', ', $names );

		if ( $this->cookie_data['payment'] === 'cod' ) {
			$cart['cod_amount'] = nashenski_get_package_total();

			if ( nashenski_get_option( 'cvc', 'ppp' ) === 'yes' ) {
				$cart['is_cod_ppp'] = 1;
			}
		}

		foreach ( $this->package[ 'contents' ] as $key => $item ) {
			$_product = wc_get_product( $item[ 'product_id' ] );

			if ( $_product->get_meta( '_nashenski_fragile' ) === 'on' ) {
				$cart['is_fragile'] = 1;
			}
		}

		if ( $cart['is_fragile'] ) {
			$cart[ 'os_value' ] = number_format( nashenski_get_package_total(), 2 );
		}

		return $cart;
	}

	private function generate_other_data() {
		$other_data = array();

		if ( $this->sms === 'yes' ) {
			$other_data['is_sms'] = 1;
		}

		if ( $this->is_sat === 'yes' ) {
			$other_data['is_sat'] = 1;
		}

		if ( $this->test == 'review' ) {
			$other_data['is_observe'] = 1;
		} else if ( $this->test == 'test' ) {
			$other_data['is_observe'] = 1;
			$other_data['is_test'] = 1;
		}

		return $other_data;
	}
	
	private function generate_payment_by_data() {
		$payment_by_data = array(
			'payer' => 'rec',
			'reject_payer' => 'rec', 
		);

		if ( !empty( $this->fixed_price ) && $payment_by_data[ 'payer' ] === 'rec' ) {
			$payment_by_data[ 'payer' ] = 'sender';
		}

		if ( 
			nashenski_has_free_shipping_coupon_in_cart() ||
			( !empty( $this->free_shipping_over ) && nashenski_get_package_total() > $this->free_shipping_over )
		) {
			$this->free_shipping = true;
			
			if ( isset( $this->cookie_data['fixed_price'] ) ) {
				unset( $this->cookie_data['fixed_price'] );
			}

			if ( $payment_by_data[ 'payer' ] === 'rec' ) {
				$payment_by_data[ 'payer' ] = 'sender';
			}
		}

		if ( $payment_by_data[ 'payer' ] === 'sender' && nashenski_get_option( 'cvc_sender', 'contract_pay' ) === 'yes' ) {
			$payment_by_data[ 'payer' ] = 'contract';
		}

		return $payment_by_data;
	}

	public static function validate_cvc_method( $fields, $errors ) {
		if ( ! WC()->cart->needs_shipping() ) {
			return;
		}
		
		$chosen_shippings = WC()->session->get('chosen_shipping_methods');

		foreach ( $chosen_shippings as $key => $shipping ) {
			if ( strpos( $shipping, 'nashenski_cvc' ) !== false ) {
				$data = WC()->session->get( 'shipping_for_package_' . $key )['rates'][ $shipping ];

				if ( $data->method_id === 'nashenski_cvc' && ! $data->meta_data['validated'] ) {
					$meta_data = $data->get_meta_data();

					if ( !empty( $meta_data['errors'] ) ) {
						/* translators: %s: error message from the courier */
						$errors->add( 'validation', sprintf( __( 'CVC - %s', 'nashenski-commerce' ), $meta_data['errors'] ) );
					} else {
						$errors->add( 'validation', __( 'Please choose delivery option!', 'nashenski-commerce' ) );
					}
				}
			}
		}
	}

	public static function save_label_data_to_order( $order_id ) {
		nashenski_save_checkout_label( $order_id, 'cvc' );
	}

	public static function add_label_number_to_email( $order, $sent_to_admin, $plain_text, $email ) {
		$email_ids_to_send = [ 'customer_completed_order' ];

		if ( nashenski_get_option( 'cvc_sender', 'label_after_checkout' ) === 'yes' ) {
			$email_ids_to_send[] = 'customer_processing_order';
			$email_ids_to_send[] = 'customer_on_hold_order';
		}
		
		$email_ids_to_send = apply_filters( 'nashenski/cvc/emails_to_send_label_number', $email_ids_to_send );

		if ( !in_array( $email->id, $email_ids_to_send ) ) {
			return;
		}
		
		$label = $order->get_meta( 'nashenski_cvc_label' );

		if ( !isset( $label['wb'] ) ) {
			return;
		}

		$number = $label['wb'];
		$url = 'https://my.e-cvc.bg/track?wb=' . $number;

		$track_number_text = sprintf( 
			/* translators: 1: waybill number, 2: tracking link */
			__( 'Label number: %1$s. %2$s', 'nashenski-commerce' ), 
			$number, 
			sprintf( '<a href="%s" target="_blank">%s</a>',
				$url,
				__( 'Track your order.' , 'nashenski-commerce' )
			)
		);

		$track_number_text = apply_filters( 'nashenski/cvc/track_number_text_in_email', $track_number_text, $url, $order );

		echo wp_kses_post( wpautop( $track_number_text ) );
	}
}
