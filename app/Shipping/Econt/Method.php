<?php
/**
 * Method.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Econt;

use Nashenski\Container\Client;
use Nashenski\Admin\Econt as Econt_Admin;

use Nashenski\Shipping\Packer\Carton_Packer;
use Nashenski\Shipping\Packer\Product;
use Nashenski\Shipping\Packer\Size;
use Nashenski\Shipping\Packer\Packing_Budget;
use Nashenski\Shipping\Packer\Packing_Limit_Exceeded;
use Nashenski\Pricing\Price_Lists;

defined( 'ABSPATH' ) || exit;

/**
 * Method.
 */
class Method extends \WC_Shipping_Method {
	const METHOD_ID = 'nashenski_econt';
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
	 * @var mixed
	 */
	public $package;

	/**
	 * Delivery type.
	 *
	 * @var mixed
	 */
	public $delivery_type;

	/**
	 * Free shipping over.
	 *
	 * @var mixed
	 */
	public $free_shipping_over;

	/**
	 * Free shipping over, abroad: the owner's "RO=150, GR=200" text.
	 *
	 * @var mixed
	 */
	public $free_shipping_by_country;

	/**
	 * Fixed price.
	 *
	 * @var mixed
	 */
	public $fixed_price;

	/**
	 * Test.
	 *
	 * @var mixed
	 */
	public $test;

	/**
	 * Sms.
	 *
	 * @var mixed
	 */
	public $sms;

	/**
	 * Tax status.
	 *
	 * @var mixed
	 */
	public $tax_status;

	/**
	 * Free shipping.
	 *
	 * @var bool
	 */
	public $free_shipping = false;
	/**
	 * Packing budget.
	 *
	 * @var mixed
	 */
	private $packing_budget = null;
	/**
	 * Resource rejected.
	 *
	 * @var bool
	 */
	private $resource_rejected = false;

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance id.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->container          = nashenski()->container();
		$this->id                 = 'nashenski_econt';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Nashenski Commerce - Econt', 'nashenski-commerce' );  // Title shown in admin.
		$this->method_description = __( 'Enables econt delivery and automatically calculate shipping price.', 'nashenski-commerce' ); // Description shown in admin.
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);
		$this->enabled            = 'yes';

		// The REST API and the form both run this filter when they save the zone settings.
		add_filter( 'woocommerce_shipping_' . $this->id . '_instance_settings_values', array( Price_Lists::class, 'clean_amounts' ), 10, 2 );

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
		$this->delivery_type      = $this->get_option( 'delivery_type' );
		$this->free_shipping_over = Price_Lists::non_negative( $this->get_option( 'free_shipping_over' ) );
		$this->free_shipping_by_country = $this->get_option( 'free_shipping_by_country' );
		$this->fixed_price        = Price_Lists::positive( $this->get_option( 'fixed_price' ) );
		$this->test               = $this->get_option( 'test' );
		$this->sms                = $this->get_option( 'sms' );
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
		$this->cookie_data       = self::get_cookie_data();
		$this->package           = $package;
		$this->packing_budget    = new Packing_Budget();
		$this->resource_rejected = false;

		try {
			$this->calculate_shipping_with_limits( $package );
		} catch ( Packing_Limit_Exceeded $e ) {
			$this->resource_rejected = true;
			WC()->session->set( 'nashenski-econt-label', null );
		}
	}

	/**
	 * Calculate shipping with limits.
	 *
	 * @param mixed $package Package.
	 */
	private function calculate_shipping_with_limits( $package ) {
		$this->packing_budget()->spend( count( $package['contents'] ?? array() ) );
		$disable_aps = ( 'automat' === $this->delivery_type && ! APSBoxes::package_fits_largest_automat( $this->package, $this->packing_budget() ) );

		if ( apply_filters( 'nashenski/econt/rate/disable_aps', $disable_aps, $this ) ) {
			return;
		}

		do_action( 'nashenski/econt/rate/before_calculate', $this );

		$rate = array(
			'label'     => $this->title,
			'cost'      => 0,
			'meta_data' => array(),
		);

		$rate['meta_data']['delivery_type'] = $this->delivery_type;
		$rate['meta_data']['validated']     = false;
		$chosen_shippings                   = WC()->session->get( 'chosen_shipping_methods' );
		$country                            = apply_filters( 'nashenski/econt/country_for_validation', $package['destination']['country'], $this );

		if ( isset( $this->cookie_data['type'] ) &&
			$this->cookie_data['type'] === $this->delivery_type &&
			( ! empty( $chosen_shippings ) && ( $chosen_shippings[0] === $this->id . ':' . $this->instance_id ) ) &&
			(
				( ! empty( $this->cookie_data['selectedAddress'] ) && $this->has_address_detail() ) ||
				( isset( $this->cookie_data['selectedOffice'] ) && $this->cookie_data['selectedOffice'] )
			)
		) {
			$request_data = $this->calculate_shipping_price_from_api();
			// The public list has Bulgarian prices only; foreign orders wait for the courier's own price.
			if ( 'BG' === $this->courier_country() ) {
				$request_data = \Nashenski\Pricing\Public_Price::fallback( 'econt', $request_data, $this );
			}

			if ( ! empty( $request_data['public_price'] ) ) {
				$rate['meta_data']['price_source'] = 'public';
			}

			$rate['request_data']             = $request_data;
			$rate['meta_data']['validated']   = true;
			$rate['meta_data']['cookie_data'] = $this->cookie_data;

			if ( isset( $request_data['errors'] ) ) {
				$rate['meta_data']['validated'] = false;
				$rate['meta_data']['errors']    = $request_data['errors'];
			} elseif ( $request_data['price'] ) {
				$rate['cost'] = $request_data['price'];

				if ( wc_tax_enabled() ) {
					$rate['taxes'] = nashenski_get_shipping_rate_taxes( $request_data['price'], $country );
				}
			}

			if ( ! $rate['cost'] && $rate['meta_data']['validated'] ) {
				$this->free_shipping = true;
			}
		} elseif ( 'BG' !== $this->courier_country() ) {
			// No widget address: a foreign order gets no price, and the checkout asks for the address.
			$rate['meta_data']['address_missing'] = true;
		}

		if ( $this->free_shipping ) {
			$rate['meta_data']['free_shipping'] = true;
			$rate['cost']                       = 0;
			unset( $rate['taxes'] );
		}

		$rate = apply_filters( 'nashenski/econt/rate', $rate, $this );

		// Register the rate.
		$this->packing_budget()->spend( 0 );
		$this->add_rate( $rate );
	}

	/**
	 * Packing budget.
	 *
	 * @return Packing_Budget Result.
	 * @throws Packing_Limit_Exceeded When the packing budget is exceeded.
	 */
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
	 * Has address detail.
	 *
	 * @return mixed Result.
	 */
	private function has_address_detail() {
		return self::has_address_detail_data( $this->cookie_data );
	}

	/**
	 * Has address detail data.
	 *
	 * @param mixed $cookie_data Cookie data.
	 * @return mixed|bool Result.
	 */
	private static function has_address_detail_data( $cookie_data ) {
		$structured_fields = array( 'blockNumber', 'entranceNumber', 'floorNumber', 'apartmentNumber' );
		$type              = $cookie_data['selectedAddress']['type'] ?? '';

		if ( 'streets' === $type ) {
			return isset( $cookie_data['streetNumber'] ) && trim( (string) $cookie_data['streetNumber'] ) !== '';
		}

		if ( 'quarters' !== $type ) {
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

		return ! $has_structured_keys && '' !== $legacy_other;
	}

	/**
	 * Both "number" settings here are amounts: a negative or non-numeric value is saved empty.
	 *
	 * @param string $key   Setting key.
	 * @param string $value Posted value.
	 * @return mixed
	 */
	public function validate_number_field( $key, $value ) {
		return Price_Lists::non_negative( $this->validate_text_field( $key, $value ) );
	}

	/**
	 * Init form fields.
	 */
	public function init_form_fields() {
		$this->instance_form_fields = array(
			'title'              => array(
				'title'       => __( 'Title', 'nashenski-commerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'nashenski-commerce' ),
				'default'     => __( 'Econt', 'nashenski-commerce' ),
				'desc_tip'    => true,
			),
			'delivery_type'      => array(
				'title'   => __( 'Delivery Type', 'nashenski-commerce' ),
				'type'    => 'select',
				'css'     => 'width: 400px;',
				'default' => '',
				'options' => array(
					'office'  => __( 'Office', 'nashenski-commerce' ),
					'automat' => __( 'Automat', 'nashenski-commerce' ),
					'address' => __( 'Address', 'nashenski-commerce' ),
				),
			),
			'test'               => array(
				'title'   => __( 'Review and test', 'nashenski-commerce' ),
				'type'    => 'select',
				'css'     => 'width: 400px;',
				'default' => 'no',
				'options' => nashenski_get_shipping_tests_options(),
			),
			'international'      => array(
				'title'       => __( 'Ship abroad', 'nashenski-commerce' ),
				'type'        => 'select',
				'css'         => 'width: 400px;',
				'default'     => 'no',
				'options'     => array(
					'no'  => __( 'No', 'nashenski-commerce' ),
					'yes' => __( 'Yes, to Romania and Greece', 'nashenski-commerce' ),
				),
				'description' => __( 'Prices for these countries come only from Econt, not from the public price list.', 'nashenski-commerce' ),
				'desc_tip'    => true,
			),
			'sms'                => array(
				'title'       => __( 'SMS notification', 'nashenski-commerce' ),
				'type'        => 'select',
				'css'         => 'width: 400px;',
				'default'     => 'no',
				'options'     => array(
					'no'  => __( 'No', 'nashenski-commerce' ),
					'yes' => __( 'Yes', 'nashenski-commerce' ),
				),
				'description' => __( 'Send customer notification for delivery.', 'nashenski-commerce' ),
			),
			'free_shipping_over' => array(
				'title'       => __( 'Free shipping over', 'nashenski-commerce' ),
				'type'        => 'number',
				'placeholder' => '0',
				'description' => __( 'Free shipping at or above total cart price.', 'nashenski-commerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'free_shipping_by_country' => array(
				'title'       => __( 'Free shipping over, abroad', 'nashenski-commerce' ),
				'type'        => 'text',
				'placeholder' => 'RO=150, GR=200',
				'description' => __( 'Country code and order total, for example RO=150, GR=200. Countries not listed get no free shipping.', 'nashenski-commerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'fixed_price'        => array(
				'title'       => __( 'Maximum price payed by the client', 'nashenski-commerce' ),
				'type'        => 'number',
				'placeholder' => '0',
				'description' => __( 'Enter a fixed price that the users will pay. The remaining will be payed by you.', 'nashenski-commerce' ) . __( 'Can be used only in Bulgaria', 'nashenski-commerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Is available.
	 *
	 * @param mixed $package Package.
	 * @return mixed Result.
	 */
	public function is_available( $package ) {
		$countries = 'yes' === $this->get_option( 'international', 'no' ) ? array( 'BG', 'RO', 'GR' ) : array( 'BG' );
		return in_array( $package['destination']['country'] ?? '', apply_filters( 'nashenski/econt/available_countries', $countries ), true );
	}

	/**
	 * Country the courier prices for: 'BG' (the Bulgarian rules) only when the widget and the package agree,
	 * otherwise the foreign one of the two. A blank side defers to the other.
	 *
	 * @return string ISO code, or '' when none is known.
	 */
	public function courier_country() {
		$widget  = (string) ( $this->cookie_data['country'] ?? '' );
		$package = (string) ( $this->package['destination']['country'] ?? '' );
		return '' !== $widget && ( 'BG' !== $widget || '' === $package ) ? $widget : $package;
	}

	/**
	 * Country of the label's receiver, which picks who pays: the widget's office or address, else the package.
	 * Same rule as the Speedy label, so a Bulgarian office with a foreign package country keeps receiver-pays.
	 *
	 * @return string ISO code.
	 */
	public function label_country() {
		$widget = $this->cookie_data['country'] ?? '';
		return '' !== $widget ? $widget : $this->courier_country();
	}

	/**
	 * Get cookie data.
	 *
	 * @return mixed Result.
	 */
	public static function get_cookie_data() {
		return nashenski_courier_cookie( 'nashenski--econt-address' );
	}

	/**
	 * Calculate shipping price from api.
	 *
	 * @return mixed Result.
	 */
	public function calculate_shipping_price_from_api() {
		$this->packing_budget()->spend( 0 );
		$data = array(
			'price' => '0',
		);

		$label        = $this->generate_label();
		$request_body = apply_filters(
			'nashenski/econt/calculate_label',
			array(
				'label' => $label,
				'mode'  => 'calculate',
			),
			$this
		);
		$this->packing_budget()->spend( 0 );

		if ( isset( $request_body['label']['senderOfficeCode'] ) ) {
			unset( $request_body['label']['senderAddress'] );
		}

		$prepared                              = $request_body;
		$prepared[ Service_Policy::OVERRIDES ] = Service_Policy::capture_overrides( $label, $request_body['label'] );
		WC()->session->set( 'nashenski-econt-label', $prepared );

		$request = $this->container[ Client::ECONT ]->label_request(
			'calculate',
			$request_body,
			array(
				'country_code' => $this->cookie_data['country'] ?? ( $this->package['destination']['country'] ?? '' ),
				'name'         => $this->cookie_data['city'] ?? ( $this->package['destination']['city'] ?? '' ),
				'region'       => $this->cookie_data['state'] ?? ( $this->package['destination']['state'] ?? '' ),
				'post_code'    => $this->package['destination']['postcode'] ?? '',
			)
		);

		if ( ! isset( $request ) ) {
			$data['errors'] = array(
				'message' => __( 'Calculation failed. Please try again.', 'nashenski-commerce' ),
			);
		} elseif ( isset( $request['success'] ) && ! $request['success'] && isset( $request['error'] ) ) {
			$data['errors'] = array(
				'message' => $request['error'],
			);
		} elseif (
			isset( $request['type'] ) &&
			(
				'ExInvalidParam' === $request['type'] ||
				'ExException' === $request['type'] ||
				'ExInvalidData' === $request['type']
			)
		) {
			$data['errors'] = $request;
		} elseif ( 'BG' !== $this->label_country() ) {
			$data['price'] = nashenski_tax_based_price( $request['label']['senderDueAmount'] );
		} elseif ( isset( $request['label']['receiverDueAmount'] ) ) {
			$data['price'] = nashenski_tax_based_price( $request['label']['receiverDueAmount'] );
		}

		return $data;
	}

	/**
	 * Generate label.
	 *
	 * @return mixed Result.
	 */
	private function generate_label() {
		$label = array(
			'senderClient'  => $this->generate_sender_data(),
			'senderAgent'   => $this->generate_sender_auth(),
			'senderAddress' => $this->generate_sender_address(),
		);

		$send_from = nashenski_get_option( 'econt', 'send_from' );

		if ( 'office' === $send_from ) {
			$label['senderOfficeCode'] = $this->generate_sender_office_code();
		}

		if ( $this->cookie_data ) {
			if ( isset( $this->cookie_data['billing_to_company'] ) && $this->cookie_data['billing_to_company'] ) {
				$label['receiverAgent']                     = $this->generate_receiver_data();
				$label['receiverClient']                    = $this->generate_receiver_data();
				$label['receiverClient']['juridicalEntity'] = true;
				$label['receiverClient']['molName']         = $this->cookie_data['billing_company_mol'];

				if ( ! empty( $this->cookie_data['billing_company'] ) ) {
					$label['receiverClient']['name'] = $this->cookie_data['billing_company'];
				}

				if ( $this->cookie_data['billing_vat_number'] ) {
					$vat_number                              = $this->cookie_data['billing_vat_number'];
					$label['receiverClient']['ein']          = substr( $vat_number, 2 );
					$label['receiverClient']['ddsEin']       = $label['receiverClient']['ein'];
					$label['receiverClient']['ddsEinPrefix'] = str_replace( $label['receiverClient']['ein'], '', $vat_number );
				}
			} else {
				$label['receiverClient'] = $this->generate_receiver_data();
				$label['receiverAgent']  = $this->generate_receiver_data();
			}

			if ( 'address' === $this->cookie_data['type'] ) {
				$label['receiverAddress'] = $this->generate_receiver_address();
			} elseif ( in_array( $this->cookie_data['type'], array( 'office', 'automat' ), true ) ) {
				$label['receiverOfficeCode'] = $this->generate_receiver_office_code();
			}
		}

		$cart_data       = $this->generate_cart_data();
		$other_data      = $this->generate_other_data();
		$payment_by_data = $this->generate_payment_by_data();

		if ( 'BG' !== $this->label_country() ) {
			unset( $payment_by_data['paymentReceiverMethod'] );
			unset( $payment_by_data['paymentReceiverAmount'] );

			$payment_by_data['paymentSenderMethod'] = $this->container[ Client::ECONT_PROFILE ]->get_sender_payment_method();
		}

		return array_merge( $label, $cart_data, $other_data, $payment_by_data );
	}

	/**
	 * Generate sender auth.
	 *
	 * @return array Result.
	 */
	private function generate_sender_auth() {
		return array(
			'name'   => nashenski_get_option( 'econt', 'name' ),
			'phones' => array( nashenski_get_option( 'econt', 'phone' ) ),
		);
	}

	/**
	 * Generate sender data.
	 *
	 * @return mixed Result.
	 */
	private function generate_sender_data() {
		$profile = $this->container[ Client::ECONT_PROFILE ]->get_profile_data();

		// No profile while the Econt login is missing or wrong.
		return is_array( $profile ) && isset( $profile['client'] ) ? $profile['client'] : null;
	}

	/**
	 * Generate sender address.
	 *
	 * @return mixed Result.
	 */
	private function generate_sender_address() {
		$address   = '';
		$id        = nashenski_get_option( 'econt_send_from', 'address' );
		$send_from = nashenski_get_option( 'econt', 'send_from' );

		if ( '' !== $id && 'address' === $send_from ) {
			$profile_addresses = $this->container[ Client::ECONT_PROFILE ]->get_profile_data()['addresses'];

			$address = $profile_addresses[ $id ];
		}

		return $address;
	}

	/**
	 * Generate sender office code.
	 *
	 * @return mixed Result.
	 */
	private function generate_sender_office_code() {
		$office = nashenski_get_option( 'econt_send_from', 'office' );

		return str_replace( 'officeID-', '', $office );
	}

	/**
	 * Generate receiver data.
	 *
	 * @return mixed Result.
	 */
	private function generate_receiver_data() {
		$session_customer = WC()->session->get( 'customer' );

		$data = array(
			'name'   => $this->cookie_data['receiver'],
			'phones' => array( $this->cookie_data['phone'] ),
		);

		if ( ! empty( $session_customer['email'] ) ) {
			$data['email'] = $session_customer['email'];
		}

		return $data;
	}

	/**
	 * Generate receiver address.
	 *
	 * @return array|mixed Result.
	 */
	private function generate_receiver_address() {
		if ( empty( $this->cookie_data['state'] ) ) {
			return array();
		}

		$country  = $this->cookie_data['country'];
		$states   = $this->container[ Client::ECONT_CITIES ]->get_regions( $country );
		$state    = $states[ $this->cookie_data['state'] ];
		$cities   = $this->container[ Client::ECONT_CITIES ]->get_filtered_cities( $this->cookie_data['city'], $state, $country );
		$city_key = $cities['city_key'];
		$type     = ( ! empty( $this->cookie_data['selectedAddress']['type'] ) ) ? $this->cookie_data['selectedAddress']['type'] : '';

		if ( empty( $cities['cities'][ $city_key ] ) ) {
			return array();
		}

		$receiver_address = array(
			'city' => $cities['cities'][ $city_key ],
		);

		unset( $receiver_address['city']['servingOffices'] );

		if ( 'streets' === $type ) {
			$street_number                     = trim( (string) ( $this->cookie_data['streetNumber'] ?? '' ) );
			$num_parts                         = '' === $street_number ? array() : preg_split( '/\s+/', $street_number );
			$receiver_address['street']        = $this->cookie_data['selectedAddress']['label'];
			$num                               = ! empty( $num_parts ) ? array_shift( $num_parts ) : '';
			$this->cookie_data['streetNumber'] = $num;
			$additional_parts                  = array();

			if ( '' !== $num ) {
				$receiver_address['num'] = $num;
			}

			if ( ! empty( $num_parts ) ) {
				$additional_parts[] = implode( ' ', $num_parts );
			}

			$legacy_other = trim( (string) ( $this->cookie_data['other'] ?? '' ) );
			if (
				! empty( $this->cookie_data['otherField'] ) &&
				( Address::has_structured_details( $this->cookie_data ) || '' === $legacy_other )
			) {
				$additional_parts[] = $this->cookie_data['otherField'];
			}

			$other                      = Address::format_other( $this->cookie_data, $additional_parts );
			$this->cookie_data['other'] = $other;

			if ( '' !== $other ) {
				$receiver_address['other'] = $other;
			}
		} elseif ( 'quarters' === $type ) {
			$receiver_address['quarter'] = $this->cookie_data['selectedAddress']['label'];
			$other                       = Address::format_other( $this->cookie_data, array( $this->cookie_data['otherField'] ?? '' ) );
			$this->cookie_data['other']  = $other;

			if ( '' !== $other ) {
				$receiver_address['other'] = $other;
			}
		}

		return $receiver_address;
	}

	/**
	 * Generate receiver office code.
	 *
	 * @return mixed Result.
	 */
	private function generate_receiver_office_code() {
		return ( isset( $this->cookie_data['selectedOffice'] ) ) ? $this->cookie_data['selectedOffice'] : '';
	}

	/**
	 * Generate cart data.
	 *
	 * @return mixed Result.
	 * @throws Packing_Limit_Exceeded When the packing budget is exceeded.
	 */
	private function generate_cart_data() {
		$this->packing_budget()->spend( count( $this->package['contents'] ?? array() ) );
		$names = array();
		$cart  = array(
			'packCount'    => 1,
			'shipmentType' => 'PACK',
			'weight'       => 0,
		);

		$sizes           = array();
		$auto_sizes      = wc_string_to_bool( nashenski_get_option( 'econt', 'auto_size' ) );
		$is_fragile      = wc_string_to_bool( nashenski_get_option( 'econt', 'declared_value' ) );
		$allocated_units = 0;

		foreach ( $this->package['contents'] as $key => $item ) {
			if ( $item['data']->get_weight() ) {
				$cart['weight'] += wc_get_weight( $item['data']->get_weight(), 'kg' ) * $item['quantity'];
			}

			if ( $cart['weight'] > 0 && $cart['weight'] < 0.100 ) {
				$cart['weight'] = 0.100;
			}

			$force = nashenski_get_option( 'econt', 'force_variations_in_desc' );

			$name = $item['data']->get_name();

			if ( 'yes' === $force && is_a( $item['data'], 'WC_Product_Variation' ) ) {
				if ( $item['data']->get_attribute_summary() ) {
					$name .= ' - ' . $item['data']->get_attribute_summary();
				} elseif ( ! empty( $item['data']->get_attributes() ) ) {
					$name .= ' - ' . implode( ', ', $item['data']->get_attributes() );
				}
			}

			$name    = nashenski_normalize_text_for_label( $name );
			$names[] = $name;

			if ( $auto_sizes && 'automat' !== $this->delivery_type && $item['data']->get_length() && $item['data']->get_width() && $item['data']->get_height() ) {
				$quantity = $item['quantity'] ?? 1;
				$this->packing_budget()->check_units( '' === $quantity ? 1 : $quantity );
				$quantity         = max( 1, (int) $quantity );
				$allocated_units += $quantity;
				$this->packing_budget()->check_units( $allocated_units );
				$this->packing_budget()->spend( $quantity );

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

		if ( ! $cart['weight'] ) {
			$cart['weight'] = apply_filters( 'nashenski/econt/label/weight', 1, $this->package, $this );
		}

		if ( ! is_numeric( $cart['weight'] ) || ! is_finite( (float) $cart['weight'] ) ) {
			throw new Packing_Limit_Exceeded( 'Shipping weight must be finite.' );
		}

		$cart['shipmentDescription'] = nashenski_normalize_text_for_label( implode( ', ', $names ), 500 );

		if ( $auto_sizes && 'automat' !== $this->delivery_type ) {
			$pack = array(
				'width'  => 17,
				'height' => 17,
				'length' => 17,
				'weight' => $cart['weight'],
			);

			if ( ! empty( $sizes ) ) {
				$packer = new Carton_Packer( $this->packing_budget() );
				$result = $packer->find_best_carton( $sizes );

				$pack['width']  = wc_get_dimension( $result->W, 'cm', 'mm' );
				$pack['height'] = wc_get_dimension( $result->H, 'cm', 'mm' );
				$pack['length'] = wc_get_dimension( $result->L, 'cm', 'mm' );
			}

			$cart['packCount'] = 1;
			$cart['packs']     = array( $pack );
		} else {
			$cart['packCount'] = 1;
			$cart['packs']     = array(
				array(
					'width'  => 17,
					'height' => 17,
					'length' => 17,
					'weight' => $cart['weight'],
				),
			);
		}

		if ( 'cod' === $this->cookie_data['payment'] ) {
			$cart['services']['cdType']     = 'get';
			$cart['services']['cdAmount']   = nashenski_get_package_total();
			$cart['services']['cdCurrency'] = get_woocommerce_currency();

			$cd_pay_option = nashenski_get_option( 'econt', 'pay_options' );

			if ( $cd_pay_option && 'no' !== $cd_pay_option ) {
				$cart['services']['cdPayOptionsTemplate'] = $cd_pay_option;
			}
		}

		if ( $is_fragile && ! $this->recipient_is_aps() ) {
			$cart['services']['declaredValueAmount']   = nashenski_get_package_total();
			$cart['services']['declaredValueCurrency'] = get_woocommerce_currency();
		}

		$send_from = nashenski_get_option( 'econt', 'send_from' );

		if ( 'office' === $send_from ) {
			$offices = $this->container[ Client::ECONT_OFFICES ]->get_formatted_offices( nashenski_get_option( 'econt_send_from', 'office_city' ) );
			$office  = nashenski_get_option( 'econt_send_from', 'office' );

			if ( ! empty( $offices['aps'] ) && array_key_exists( $office, $offices['aps'] ) ) {
				unset( $cart['services']['declaredValueAmount'] );
				unset( $cart['services']['declaredValueCurrency'] );
			}
		}

		if ( 'yes' === $this->sms ) {
			$cart['services']['smsNotification'] = true;
		}

		return $cart;
	}

	/**
	 * Generate other data.
	 *
	 * @return mixed Result.
	 */
	private function generate_other_data() {
		$other_data = array();

		if ( in_array( $this->test, array( 'review', 'test' ), true ) && ! $this->recipient_is_aps() ) {
			if ( 'review' === $this->test ) {
				$other_data['payAfterAccept'] = true;
			} elseif ( 'test' === $this->test ) {
				$other_data['payAfterAccept'] = true;
				$other_data['payAfterTest']   = true;
			}
		}

		return $other_data;
	}

	/**
	 * Recipient is aps.
	 *
	 * @return bool|mixed Result.
	 */
	private function recipient_is_aps() {
		if ( ( $this->cookie_data['type'] ?? '' ) === 'address' ) {
			return false;
		}
		return Service_Policy::recipient_is_aps( $this->container, $this->cookie_data['selectedOffice'] ?? '', (array) $this->cookie_data );
	}

	/**
	 * Generate payment by data.
	 *
	 * @return mixed Result.
	 */
	private function generate_payment_by_data() {
		$payment_by_data = array(
			'paymentReceiverMethod' => 'cash',
		);

		if ( ! empty( $this->fixed_price ) && isset( $this->cookie_data['country'] ) && 'BG' === $this->cookie_data['country'] ) {
			$payment_by_data['paymentSenderMethod']   = $this->container[ Client::ECONT_PROFILE ]->get_sender_payment_method();
			$payment_by_data['paymentReceiverMethod'] = 'cash';
			$payment_by_data['paymentReceiverAmount'] = $this->fixed_price;
		}

		$free_over = Price_Lists::free_over( $this->label_country(), $this->free_shipping_over, $this->free_shipping_by_country );

		if ( nashenski_has_free_shipping_coupon_in_cart() ||
			( (float) $free_over > 0 && nashenski_get_package_total() >= $free_over )
		) {
			$this->free_shipping = true;

			unset( $payment_by_data['paymentReceiverMethod'] );
			unset( $payment_by_data['paymentReceiverAmount'] );

			$payment_by_data['paymentSenderMethod'] = $this->container[ Client::ECONT_PROFILE ]->get_sender_payment_method();
		}

		return $payment_by_data;
	}

	/**
	 * Validate econt method.
	 *
	 * @param mixed $fields Fields.
	 * @param mixed $errors Errors.
	 */
	public static function validate_econt_method( $fields, $errors ) {
		if ( ! WC()->cart->needs_shipping() ) {
			return;
		}

		$chosen_shippings = WC()->session->get( 'chosen_shipping_methods' );

		foreach ( $chosen_shippings as $key => $shipping ) {
			if ( strpos( $shipping, 'nashenski_econt' ) !== false ) {
				$data = nashenski_chosen_rate( $key, $shipping );

				if ( ! $data ) {
					continue;
				}

				if ( 'nashenski_econt' === $data->method_id && ! $data->meta_data['validated'] ) {
					$meta_data = $data->get_meta_data();

					if ( ! empty( $meta_data['errors'] ) && ! empty( array_filter( $meta_data['errors'] ) ) ) {
						$message = array_merge( array( __( 'Econt - ', 'nashenski-commerce' ) ), nashenski()->container()[ Client::ECONT ]::add_error_message( $meta_data['errors'] ) );
						$errors->add( 'validation', implode( ' ', $message ) );
					} elseif ( ! empty( $meta_data['address_missing'] ) ) {
						$errors->add( 'validation', __( 'Please fill in your delivery address.', 'nashenski-commerce' ) );
					} else {
						$cookie_data  = self::get_cookie_data();
						$address_type = $cookie_data['selectedAddress']['type'] ?? '';

						if (
							! empty( $cookie_data['type'] ) &&
							'address' === $cookie_data['type'] &&
							! empty( $cookie_data['selectedAddress'] ) &&
							! self::has_address_detail_data( $cookie_data )
						) {
							if ( 'streets' === $address_type ) {
								$errors->add( 'validation', __( 'Street number is required.', 'nashenski-commerce' ) );
							} elseif ( 'quarters' === $address_type ) {
								$errors->add( 'validation', __( 'Fill in at least one of block, entrance, floor or apartment.', 'nashenski-commerce' ) );
							}
						} else {
							$errors->add( 'validation', __( 'Please choose delivery option!', 'nashenski-commerce' ) );
						}
					}
				}

				if ( 'nashenski_econt' === $data->method_id ) {
					$cookie_data = self::get_cookie_data();

					if ( ! empty( $cookie_data ) &&
						( ! empty( $cookie_data['type'] ) && 'office' === $cookie_data['type'] ) &&
						empty( $cookie_data['selectedOffice'] )
					) {
						$errors->add( 'validation', __( 'Please choose a office.', 'nashenski-commerce' ) );
					}

					if (
						! empty( $cookie_data ) &&
						( ! empty( $cookie_data['type'] ) && 'automat' === $cookie_data['type'] ) &&
						empty( $cookie_data['selectedOffice'] )
					) {
						$errors->add( 'validation', __( 'Please choose a automat.', 'nashenski-commerce' ) );
					}

					if (
						! empty( $cookie_data ) &&
						( ! empty( $cookie_data['type'] ) && 'address' === $cookie_data['type'] ) &&
						empty( $cookie_data['selectedAddress'] )
					) {
						$errors->add( 'validation', __( 'Please choose address.', 'nashenski-commerce' ) );
					}
				}
			}
		}
	}

	/**
	 * Save label data to order.
	 *
	 * @param mixed $order_id Order id.
	 */
	public static function save_label_data_to_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! empty( $order->get_items( 'shipping' ) ) ) {
			foreach ( $order->get_items( 'shipping' ) as $shipping ) {
				if ( 'nashenski_econt' === $shipping['method_id'] ) {
					$cookie_data = '';

					foreach ( $shipping->get_meta_data() as $meta_data ) {
						$data = $meta_data->get_data();

						if ( 'cookie_data' === $data['key'] ) {
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

							if ( 'cod' !== $cookie_data['payment'] ) {
								unset(
									$label['label']['paymentReceiverMethod'],
									$label['label']['paymentReceiverAmount'],
									$label['label']['paymentSenderMethod'],
								);

								$label['label']['paymentSenderMethod'] = $container[ Client::ECONT_PROFILE ]->get_sender_payment_method();
							} elseif ( 'BG' !== $cookie_data['country'] &&
								! $shipping->free_shipping &&
								empty( $cookie_data['fixed_price'] )
							) {
								$label['label']['paymentReceiverMethod'] = 'cash';
							}
						}

						$overrides                          = $label[ Service_Policy::OVERRIDES ] ?? array();
						$label['label']                     = Service_Policy::for_order( $label['label'], $order, nashenski()->container(), $shipping, $overrides );
						$before_filter                      = $label['label'];
						$label                              = apply_filters( 'nashenski/econt/label_before_save', $label, $order );
						$label[ Service_Policy::OVERRIDES ] = Service_Policy::capture_overrides( $before_filter, $label['label'], $overrides );
						$order->update_meta_data( 'nashenski_econt_label', $label );
						WC()->session->__unset( 'nashenski-econt-label' );
					}

					$order->save();
					break;
				}
			}
		}
	}

	/**
	 * Add label number to email.
	 *
	 * @param mixed $order Order.
	 * @param mixed $sent_to_admin Sent to admin.
	 * @param mixed $plain_text Plain text.
	 * @param mixed $email Email.
	 */
	public static function add_label_number_to_email( $order, $sent_to_admin, $plain_text, $email ) {
		$email_ids_to_send = array( 'customer_completed_order' );

		if ( nashenski_get_option( 'econt', 'label_after_checkout' ) === 'yes' ) {
			$email_ids_to_send[] = 'customer_processing_order';
			$email_ids_to_send[] = 'customer_on_hold_order';
		}

		$email_ids_to_send = apply_filters( 'nashenski/econt/emails_to_send_label_number', $email_ids_to_send );

		if ( ! in_array( $email->id, $email_ids_to_send, true ) ) {
			return;
		}

		$label = $order->get_meta( 'nashenski_econt_label' );

		if ( ! isset( $label['label']['shipmentNumber'] ) ) {
			return;
		}

		$number = $label['label']['shipmentNumber'];
		$url    = \Nashenski\Shipping\Automation::tracking_url( 'econt', $number );

		$track_number_text = sprintf(
			/* translators: 1: waybill number, 2: tracking link */
			__( 'Label number: %1$s. %2$s', 'nashenski-commerce' ),
			$number,
			sprintf(
				'<a href="%s" target="_blank">%s</a>',
				$url,
				__( 'Track your order.', 'nashenski-commerce' )
			)
		);

		$track_number_text = apply_filters( 'nashenski/econt/track_number_text_in_email', $track_number_text, $url, $order );

		echo wp_kses_post( wpautop( $track_number_text ) );
	}
}
