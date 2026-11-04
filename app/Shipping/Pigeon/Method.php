<?php
namespace Nashenski\Shipping\Pigeon;
use Nashenski\Container\Client;

use Nashenski\Shipping\Packer\Carton_Packer;
use Nashenski\Shipping\Packer\Product;
use Nashenski\Shipping\Packer\Size;
use Nashenski\Shipping\Packer\Packing_Budget;
use Nashenski\Shipping\Packer\Packing_Limit_Exceeded;
use Nashenski\Transliteration;

defined( 'ABSPATH' ) || exit;

class Method extends \WC_Shipping_Method {
	const METHOD_ID = "nashenski_pigeon";
	public $container, $cookie_data, $package, $delivery_type, $free_shipping_over, $fixed_price, $test, $tax_status, $free_shipping = false;
	private $packing_budget = null;
	private $resource_rejected = false;

	public function __construct( $instance_id = 0 ) {
		$this->container          = nashenski()->container();
		$this->id                 = 'nashenski_pigeon'; 
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Nashenski Commerce - Pigeon Express', 'nashenski-commerce' );  // Title shown in admin
		$this->method_description = __( 'Enables Pigeon Express delivery and automatically calculate shipping price.', 'nashenski-commerce' ); // Description shown in admin
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
		$this->packing_budget = new Packing_Budget();
		$this->resource_rejected = false;

		try {
			$this->calculate_shipping_with_limits( $package );
		} catch ( Packing_Limit_Exceeded $e ) {
			$this->resource_rejected = true;
			WC()->session->set( 'nashenski-pigeon-label', null );
		}
	}

	private function calculate_shipping_with_limits( $package ) {
		$this->packing_budget()->spend( count( $package['contents'] ?? array() ) );
		if ( in_array( $this->delivery_type, array( 'office', 'address' ), true ) ) {
			PackageBoxes::validate_package( $package, $this->packing_budget() );
		}

		$disable_aps = (
			$this->delivery_type === 'locker' &&
			(
				! APSBoxes::package_fits_largest_locker( $this->package, $this->packing_budget() ) ||
				! APSBoxes::package_weight_fits_locker( $this->package )
			)
		);

		$disable_packages = (
			in_array( $this->delivery_type, array( 'office', 'address' ), true ) &&
			! PackageBoxes::package_weight_fits_shipment( $this->package, $this )
		);
		
		if(
			apply_filters( 'nashenski/pigeon/rate/disable_aps', $disable_aps, $this ) ||
			apply_filters( 'nashenski/pigeon/rate/disable_packages', $disable_packages, $this )
		) {
			return;
		}

		do_action( 'nashenski/pigeon/rate/before_calculate', $this );

		$rate = array(
			'label' => $this->title,
			'cost' => 0,
			'meta_data' => [],
		);

		$rate['meta_data']['delivery_type'] = $this->delivery_type;
		$rate['meta_data']['validated'] = false;
		$payment_by_data = $this->generate_payment_by_data();
		$chosen_shippings = WC()->session->get('chosen_shipping_methods');
		$country = apply_filters('nashenski/pigeon/country_for_validation', $package['destination']['country'], $this );

		if ( 
			isset( $this->cookie_data['type'] ) && 
			$this->cookie_data['type'] === $this->delivery_type && 
			( !empty( $chosen_shippings ) && ( $chosen_shippings[0] === $this->id . ':' . $this->instance_id ) ) &&
			( 
				( isset( $this->cookie_data['other'] ) && $this->cookie_data['other'] && $this->cookie_data['selectedAddress'] ) || 
				( isset( $this->cookie_data['streetNumber']) && $this->cookie_data['streetNumber'] && $this->cookie_data['selectedAddress'] ) || 
				( isset( $this->cookie_data['selectedOffice'] ) && $this->cookie_data['selectedOffice'] ) ||
				( isset( $this->cookie_data['selectedLocker'] ) && $this->cookie_data['selectedLocker'] ) 
			) 
		) {
			$request_data = $this->calculate_shipping_price_from_api();
			$request_data = \Nashenski\Pricing\Public_Price::fallback( 'pigeon', $request_data, $this );

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
		}

		if ( $this->free_shipping ) {
			$rate['meta_data']['free_shipping'] = true;
			$rate[ 'cost' ] = 0;
			unset( $rate[ 'taxes' ] );
		}

		if ( !$this->free_shipping && !empty( $this->fixed_price ) ) {
			$tax_rate = apply_filters('nashenski/shipping/fixed_price_tax_rate', 20, $country );
			$rate[ 'cost' ] = nashenski_tax_based_price( $this->fixed_price, $tax_rate );
			
			if ( wc_tax_enabled() ) {
				$rate[ 'taxes' ] = nashenski_get_shipping_rate_taxes( $rate[ 'cost' ], $country );
			}
		}

		$rate = apply_filters( 'nashenski/pigeon/rate', $rate, $this );

		// Register the rate
		$this->packing_budget()->spend( 0 );
		$this->add_rate( $rate );
	}

	public function packing_budget(): Packing_Budget {
		if ( $this->resource_rejected ) {
			throw new Packing_Limit_Exceeded( 'Shipping calculation was already rejected.' );
		}
		if ( null === $this->packing_budget ) {
			$this->packing_budget = new Packing_Budget();
		}
		return $this->packing_budget;
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
				'default'     => __( 'Pigeon Express', 'nashenski-commerce' ),
				'desc_tip'    => true,
			),
			'delivery_type'    => array(
				'title'             => __( 'Delivery Type', 'nashenski-commerce' ),
				'type'              => 'select',
				'css'               => 'width: 400px;',
				'default'           => '',
				'options'           => array(
					'office' => __( 'Office', 'nashenski-commerce' ),
					'locker' => __( 'APS', 'nashenski-commerce' ),
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
					'yes' => __( 'Yes', 'nashenski-commerce' ),
				),
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
				'description' => __( 'Enter a fixed price that will be payed by the client, and will be included in the COD.', 'nashenski-commerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	public function is_available( $package ) {
		return in_array( $package['destination']['country'], apply_filters( 'nashenski/pigeon/available_countries', array( 'BG' ) ), true );
	}

	public function get_instance_form_fields() {
		return parent::get_instance_form_fields();
	}

	public static function get_cookie_data() {
		return Payment::cookie( nashenski_courier_cookie( 'nashenski--pigeon-address' ) );
	}

	public function calculate_shipping_price_from_api() {
		$this->packing_budget()->spend( 0 );
		// No login: the public price list prices the order (Public_Price::fallback).
		if ( ! \Nashenski\Front_End\Checkout\Pickers::connected( 'pigeon' ) ) {
			return array( 'errors' => array( 'not_connected' ) );
		}

		$data = array(
			'price' => '0',
		);
		
		$request_body = apply_filters( 'nashenski/pigeon/calculate_label', $this->generate_label(), $this );
		$this->packing_budget()->spend( 0 );

		WC()->session->set( 'nashenski-pigeon-label' , $request_body );

		$request = $this->container[ Client::PIGEON ]->api_call( $this->container[ Client::PIGEON ]::CALCULATE_ENDPOINT, $request_body, 'POST' );
		

		if ( isset( $request['success'] ) && !$request['success'] ) {
			if ( !empty( $request['errors'] ) ) {
				$data['errors'] = $request['errors'];
			} else if ( !empty( $request['message'] ) ) {
				$data['errors'] = [ [ $request['message'] ] ];
			} else {
				$data['errors'] = [ [ __( 'An error occurred while calculating the shipping price. Please try again later.', 'nashenski-commerce' ) ] ];
			}
		} else if ( isset( $request['data']['total_price'] ) ) {
			$data['price'] = nashenski_tax_based_price( $request['data']['total_price'] );
		}

		return $data;
	}

	private function generate_label() {
		$sender_data = $this->generate_sender_data();
		$receiver_data = $this->generate_receiver_data();
		$cart_data = $this->generate_cart_data();
		$other_data = $this->generate_other_data();
		$payment_by_data = $this->generate_payment_by_data();

		return array_merge_recursive( $sender_data, $receiver_data, $cart_data, $other_data, $payment_by_data );
	}

	private function generate_sender_data() {
		$send_from = nashenski_get_option( 'pigeon', 'send_from' );

		$label = [
			'pickup_type' => $send_from,
		];

		if ( $send_from == 'office' ) {
			$label['pickup_office_id'] = $this->generate_sender_office_code();
		} else {
			$label['pickup_address'] = [
				'city_id' => str_replace( 'cityID-', '', nashenski_get_option( 'pigeon_send_from', 'city' ) ),
				'additional_info' => nashenski_get_option( 'pigeon_send_from', 'address' ),
			];
		}

		return $label;
	}

	private function generate_sender_office_code() {
		$office = nashenski_get_option( 'pigeon_send_from', 'office' );
		
		return str_replace( 'officeID-', '', $office );
	}

	private function generate_receiver_data() {
		if ( empty($this->cookie_data ) ) {
			return [];
		}
		
		$session_customer = WC()->session->get( 'customer' );

		$data = array(
			'delivery_type' => $this->cookie_data[ 'type' ],
			'receiver_name' => $this->cookie_data[ 'receiver' ],
			'receiver_phone' => trim( $this->cookie_data[ 'phone' ] ),
		);

		if ( !empty( $session_customer[ 'email' ] ) ) {
			$data['receiver_email'] = $session_customer[ 'email' ];
		}

		if ( $this->cookie_data[ 'type' ] === 'address' ) {
			$data['delivery_address'] = $this->generate_receiver_address();
		} else if ( $this->cookie_data[ 'type' ] === 'office' ) {
			$data['delivery_office_id'] = absint( $this->generate_receiver_office_code() );
		} else if ( $this->cookie_data[ 'type' ] === 'locker' ) {
			$data['delivery_office_id'] = absint( $this->generate_receiver_locker_code() );
		}

		return $data;
	}

	private function generate_receiver_address() {
		if ( empty( $this->cookie_data['state'] ) ) {
			return [];
		}
		
		$raw_city = Transliteration::latin2cyrillic( $this->cookie_data[ 'city' ] );
		$cities_data = $this->container[ Client::PIGEON_CITIES ]->get_filtered_cities( $raw_city, $this->cookie_data[ 'state' ] ) ;
		$city_id = $cities_data['cities_search_names'][ $cities_data['city_key'] ][ 'id' ];

		$receiver_address = [
			'city_id' => $city_id,
			'street_id' => str_replace( 'street-', '', $this->cookie_data[ 'selectedAddress' ]['orig_key'] ),
			'additional_info' => $this->cookie_data[ 'streetNumber' ],
		];

		return $receiver_address;
	}

	private function generate_receiver_office_code() {
		return ( isset( $this->cookie_data['selectedOffice'] ) ) ? $this->cookie_data['selectedOffice'] : '';
	}

	private function generate_receiver_locker_code() {
		return ( isset( $this->cookie_data['selectedLocker'] ) ) ? $this->cookie_data['selectedLocker'] : '';
	}

	private function generate_cart_data() {
		$this->packing_budget()->spend( count( $this->package['contents'] ?? array() ) );
		$cart_data = [
			'packages' => [
				[
					'weight' => 1,
				]
			],
			'inventory_items' => [],
		];
		$weight = 0;
		$sizes = [];
		$auto_sizes = wc_string_to_bool( nashenski_get_option( 'pigeon', 'auto_size' ) );
		$allocated_units = 0;

		foreach ( $this->package[ 'contents' ] as $key => $item ) {
			$item_weight = 0;
			
			if ( $item['data']->get_weight() ) {
				$item_weight = wc_get_weight( $item['data']->get_weight(), 'kg' ) * $item['quantity'];
			}
	
			if ( $item_weight > 0 && $item_weight < 0.100 ) {
				$item_weight = 0.100;
			
			}

			$weight += $item_weight;			

			$force = nashenski_get_option( 'pigeon', 'force_variations_in_desc' );

			$name = $item['data']->get_name();

			if ( $force === 'yes' && is_a( $item['data'], 'WC_Product_Variation' ) ) {
				if ( $item['data']->get_attribute_summary() ) {
					$name .= ' - ' . $item['data']->get_attribute_summary();
				} else if( !empty( $item['data']->get_attributes() ) ) {
					$name .= ' - ' . implode(', ', $item['data']->get_attributes() );
				}
			}

			$name = nashenski_normalize_text_for_label( $name );

			$cart_data['inventory_items'][] = array(
				'description' => $name,
				'quantity' => $item['quantity'],
			);

			if ( $auto_sizes && $item['data']->get_length() && $item['data']->get_width() && $item['data']->get_height() ) {
				$quantity = $item['quantity'] ?? 1;
				$this->packing_budget()->check_units( is_numeric( $quantity ) ? abs( (float) $quantity ) : $quantity );
				// Numeric range() includes both ends; account for descending and fractional ranges too.
				$units = (int) floor( abs( (float) $quantity - 1 ) ) + 1;
				$allocated_units += $units;
				$this->packing_budget()->check_units( $allocated_units );
				$this->packing_budget()->spend( $units );
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

		if ( !$weight ) {
			$weight = apply_filters( 'nashenski/pigeon/label/weight', 1, $this->package, $this );
		}
		if ( ! is_numeric( $weight ) || ! is_finite( (float) $weight ) ) {
			throw new Packing_Limit_Exceeded( 'Shipping weight must be finite.' );
		}

		$cart_data['packages'][0]['weight'] = $weight;

		if ( empty( $sizes ) ) {
			$cart_data['packages'][0]['width'] = 17;
			$cart_data['packages'][0]['length'] = 17;
			$cart_data['packages'][0]['height'] = 17;
		} else if ( ( $auto_sizes || $this->cookie_data['type'] === 'locker' ) && !empty( $sizes ) ) {
			// Office and address labels are sized again per parcel (PackageBoxes), so a big cart that
			// cannot be searched in its own budget keeps the default size instead of hiding the method.
			$locker = 'locker' === $this->delivery_type;
			try {
				$result = ( new Carton_Packer( $locker ? $this->packing_budget() : new Packing_Budget() ) )->find_best_carton( $sizes );
			} catch ( Packing_Limit_Exceeded $e ) {
				if ( $locker ) {
					throw $e;
				}
				$result = null;
			}

			$cart_data['packages'][0]['width'] = $result ? wc_get_dimension( $result->W, 'cm', 'mm' ) : 17;
			$cart_data['packages'][0]['length'] = $result ? wc_get_dimension( $result->L, 'cm', 'mm' ) : 17;
			$cart_data['packages'][0]['height'] = $result ? wc_get_dimension( $result->H, 'cm', 'mm' ) : 17;
		} 

		return $cart_data;
	}

	private function generate_other_data() {
		$other_data = [
			//'service_type' => nashenski_get_option( 'pigeon', 'service_type' ),
			'service_type' => 'standard',
			'service_codes' => [],
			'sms_notification' => true,
		];

		$is_fragile = wc_string_to_bool( nashenski_get_option( 'pigeon_services', 'declared_value' ) );
	
		if ( $is_fragile  ) {
			$other_data['service_codes']['declared_value'] = nashenski_get_package_total();
		}

		if ( nashenski_get_option( 'pigeon_services', 'pay_with_pos' ) === 'yes' ) {
			$other_data['pay_with_pos'] = true;
		}
		
		if ( nashenski_get_option( 'pigeon_services', 'paper_return_receipt' ) === 'yes' ) {
			$other_data['service_codes']['paper_return_receipt'] = true;
		}

		if ( nashenski_get_option( 'pigeon_services', 'return_receipt' ) === 'yes' ) {
			$other_data['service_codes']['return_receipt'] = true;
		}
		
		if ( nashenski_get_option( 'pigeon_services', 'service_return_documents' ) === 'yes' ) {
			$other_data['service_codes']['service_return_documents'] = true;
		}
		
		if ( nashenski_get_option( 'pigeon_services', 'id_verification_and_document_signature' ) === 'yes' ) {
			$other_data['service_codes']['ID_verification_and_document_signature'] = true;
		}
		
		if ( nashenski_get_option( 'pigeon_services', 'cod_card_fee' ) === 'yes' ) {
			$other_data['service_codes']['cod_card_fee'] = true;
		}
		
		if ( $this->cookie_data[ 'type' ] !== 'locker' ) {
			if ( $this->test === 'yes' ) {
				$other_data['service_codes']['shipment_test_before_payment'] = true;
			}
		}

		$other_data['note'] = '';

		return $other_data;
	}
	
	private function generate_payment_by_data() {
		$payment = [
			'who_pays' => 'receiver',
		];

		if ( isset( $this->cookie_data['payment'] ) && $this->cookie_data['payment'] === 'cod' ) {
			$payment['service_codes']['cod_amount'] = nashenski_get_package_total();
		}

		if ( isset( $this->cookie_data['payment'] ) && $this->cookie_data['payment'] !== 'cod' ) {
			$payment[ 'who_pays' ] = 'sender';
		}

		if ( 
			nashenski_has_free_shipping_coupon_in_cart() ||
			( !empty( $this->free_shipping_over ) && nashenski_get_package_total() > $this->free_shipping_over )
		) {
			$this->free_shipping = true;
			
			if ( isset( $this->cookie_data['fixed_price'] ) ) {
				unset( $this->cookie_data['fixed_price'] );
			}

			if ( $payment[ 'who_pays' ] === 'receiver' ) {
				$payment[ 'who_pays' ] = 'sender';
			}
		}

		if ( !empty( $this->fixed_price ) && $payment[ 'who_pays' ] === 'receiver' ) {
			$payment[ 'who_pays' ] = 'sender';
			$payment['service_codes']['cod_amount'] += number_format($this->fixed_price, 2, '.', '');
		}

		return $payment;
	}

	public static function validate_pigeon_method( $fields, $errors ){
		if ( ! WC()->cart->needs_shipping() ) {
			return;
		}

		$chosen_shippings = WC()->session->get('chosen_shipping_methods');

		foreach ( $chosen_shippings as $key => $shipping ) {
			if ( strpos( $shipping, 'nashenski_pigeon' ) !== false ) {
				$data = nashenski_chosen_rate( $key, $shipping );

				if ( ! $data ) {
					continue;
				}

				if ( $data->method_id === 'nashenski_pigeon' && ! $data->meta_data['validated'] ) {
					$meta_data = $data->get_meta_data();

					if ( !empty( $meta_data['errors'] ) && !empty( array_filter( $meta_data['errors'] ) ) ) {
						$message = array_merge( array( __( 'Pigeon Express - ', 'nashenski-commerce' ) ), wp_list_pluck( $meta_data['errors'], 0 ) );
						$errors->add( 'validation', implode( ' ', $message ) );
					} else {
						$errors->add( 'validation', __( 'Please choose delivery option!', 'nashenski-commerce' ) );
					}
				}

				if ( $data->method_id === 'nashenski_pigeon' ) { 
					$cookie_data = self::get_cookie_data();

					if ( 
						! empty( $cookie_data ) && 
						( !empty( $cookie_data['type'] ) && $cookie_data['type'] === 'office' ) &&  
						empty( $cookie_data['selectedOffice'] ) 
					) {
						$errors->add( 'validation', __( 'Please choose an office.', 'nashenski-commerce' ) );
					}

					if ( 
						! empty( $cookie_data ) && 
						( !empty( $cookie_data['type'] ) && $cookie_data['type'] === 'locker' ) &&  
						empty( $cookie_data['selectedLocker'] ) 
					) {
						$errors->add( 'validation', __( 'Please choose a locker.', 'nashenski-commerce' ) );
					}

					if(
						! empty( $cookie_data ) && 
						( !empty( $cookie_data['type'] ) && $cookie_data['type'] === 'address' ) &&  
						empty( $cookie_data['selectedAddress'] ) 
					) {
						$errors->add( 'validation', __( 'Please choose an address.', 'nashenski-commerce' ) ); 
					}
				}
			}
		}
	}

	public static function save_label_data_to_order( $order_id ) {
		nashenski_save_checkout_label( $order_id, 'pigeon' );
	}

	public static function add_label_number_to_email( $order, $sent_to_admin, $plain_text, $email ) {
		$email_ids_to_send = [ 'customer_completed_order' ];

		if ( nashenski_get_option( 'pigeon', 'label_after_checkout' ) === 'yes' ) {
			$email_ids_to_send[] = 'customer_processing_order';
			$email_ids_to_send[] = 'customer_on_hold_order';
		}
		
		$email_ids_to_send = apply_filters( 'nashenski/pigeon/emails_to_send_label_number', $email_ids_to_send );

		if ( !in_array( $email->id, $email_ids_to_send ) ) {
			return;
		}

		$label = $order->get_meta( 'nashenski_pigeon_label' );

		if ( !isset( $label['data']['reference_number'] ) ) {
			return;
		}

		$number = $label['data']['reference_number'];
		$url = \Nashenski\Shipping\Automation::tracking_url( 'pigeon', $number );

		$track_number_text = sprintf( 
			/* translators: 1: waybill number, 2: tracking link */
			__( 'Label number: %1$s. %2$s', 'nashenski-commerce' ), 
			$number, 
			sprintf( '<a href="%s" target="_blank">%s</a>',
				$url,
				__( 'Track your order.' , 'nashenski-commerce' )
			)
		);

		$track_number_text = apply_filters( 'nashenski/pigeon/track_number_text_in_email', $track_number_text, $url, $order );

		echo wp_kses_post( wpautop( $track_number_text ) );
	}
}
