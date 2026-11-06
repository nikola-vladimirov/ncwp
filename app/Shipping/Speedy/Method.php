<?php
/**
 * Method.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Speedy;

use Nashenski\Container\Client;

use Nashenski\Shipping\Packer\Carton_Packer;
use Nashenski\Shipping\Packer\APSPackage;
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
	const METHOD_ID                 = 'nashenski_speedy';
	const VOLUMETRIC_WEIGHT_DIVISOR = 5000;
	const MAX_PARCELS               = 10;

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
	 * Content data.
	 *
	 * @var mixed
	 */
	private $content_data = null;
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
	 * Aps box sizes.
	 *
	 * @var array
	 */
	private static $aps_box_sizes = array(
		array(
			'name'       => 'APS',
			'length'     => 62,
			'width'      => 36,
			'height'     => 38,
			'max_weight' => 20,
		),
	);

	/**
	 * Constructor.
	 *
	 * @param int $instance_id Instance id.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->container          = nashenski()->container();
		$this->id                 = self::METHOD_ID;
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'Nashenski Commerce - Speedy', 'nashenski-commerce' );  // Title shown in admin.
		$this->method_description = __( 'Enables Speedy delivery and automatically calculate shipping price.', 'nashenski-commerce' ); // Description shown in admin.
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
		);
		$this->enabled            = 'yes';

		// The REST API and the form both run this filter when they save the zone settings.
		add_filter( 'woocommerce_shipping_' . $this->id . '_instance_settings_values', array( Price_Lists::class, 'clean_amounts' ) );

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
		$this->content_data      = null;
		$this->packing_budget    = new Packing_Budget();
		$this->resource_rejected = false;

		try {
			$this->calculate_shipping_with_limits( $package );
		} catch ( Packing_Limit_Exceeded $e ) {
			$this->resource_rejected = true;
			$this->content_data      = null;
			// A rejected quote must not leave a previously prepared label in the session.
			WC()->session->set( 'nashenski-speedy-label', null );
		}
	}

	/**
	 * Calculate shipping with limits.
	 *
	 * @param mixed $package Package.
	 */
	private function calculate_shipping_with_limits( $package ) {
		$this->packing_budget()->spend( count( $package['contents'] ?? array() ) );

		$disable_aps = ( 'automat' === $this->delivery_type && ! APSPackage::package_fits_largest_box( $this->package, self::$aps_box_sizes, self::VOLUMETRIC_WEIGHT_DIVISOR, $this->packing_budget() ) );

		if ( apply_filters( 'nashenski/speedy/rate/disable_aps', $disable_aps, $this ) ) {
			return;
		}

		if ( ! $this->package_fits_max_parcels() ) {
			return;
		}

		do_action( 'nashenski/speedy/rate/before_calculate', $this );

		$fixed_price = $this->applicable_fixed_price();

		if ( is_array( $this->cookie_data ) ) {
			$this->cookie_data['fixed_price'] = $fixed_price;
		}

		$rate = array(
			'label'     => $this->title,
			'meta_data' => array(),
		);

		$rate['meta_data']['delivery_type'] = $this->delivery_type;
		$rate['meta_data']['validated']     = false;
		$payment_by_data                    = $this->generate_payment_by_data();
		$chosen_shippings                   = WC()->session->get( 'chosen_shipping_methods' );
		$country                            = apply_filters( 'nashenski/speedy/country_for_validation', $package['destination']['country'], $this );

		if ( isset( $this->cookie_data['type'] ) &&
			$this->cookie_data['type'] === $this->delivery_type &&
			( ! empty( $chosen_shippings ) && ( $chosen_shippings[0] === $this->id . ':' . $this->instance_id ) ) &&
			(
				( ! empty( $this->cookie_data['selectedAddress'] ) && $this->has_address_detail() ) ||
				( isset( $this->cookie_data['selectedOffice'] ) && $this->cookie_data['selectedOffice'] ) ||
				( ! empty( $this->cookie_data['mysticQuarter'] ) && empty( $this->cookie_data['selectedAddress'] ) )
			)
		) {
			$request_data = $this->calculate_shipping_price_from_api();
			// The public list has Bulgarian prices only; foreign orders wait for the courier's own price.
			if ( 'BG' === $this->courier_country() ) {
				$request_data = \Nashenski\Pricing\Public_Price::fallback( 'speedy', $request_data, $this );
			}

			if ( ! empty( $request_data['public_price'] ) ) {
				$rate['meta_data']['price_source'] = 'public';
			}
			$rate['meta_data']['validated'] = true;
			$rate['request_data']           = $request_data;

			if ( isset( $request_data['errors'] ) ) {
				$rate['meta_data']['validated'] = false;
				$rate['meta_data']['errors']    = $request_data['errors'];
			} elseif ( isset( $request_data['price'] ) ) {
				$rate['cost'] = $request_data['price'];

				if ( wc_tax_enabled() ) {
					$rate['taxes'] = nashenski_get_shipping_rate_taxes( $request_data['price'], $country );
				}
			}
		} elseif ( 'BG' !== $this->courier_country() ) {
			// No widget address: a foreign order gets no price, not even "Free", and the checkout asks for the address.
			$rate['meta_data']['address_missing'] = true;
			$this->free_shipping                  = false;
		}

		if ( $this->free_shipping ) {
			$rate['meta_data']['free_shipping'] = true;
			$rate['cost']                       = 0;
			unset( $rate['taxes'] );
		}

		// The fixed price only once the customer has chosen a delivery point (request_data is set then).
		if ( ! $this->free_shipping && ! empty( $fixed_price ) && isset( $rate['request_data'] ) ) {
			$tax_rate     = apply_filters( 'nashenski/shipping/fixed_price_tax_rate', 20, $country );
			$rate['cost'] = nashenski_tax_based_price( $fixed_price, $tax_rate );

			if ( wc_tax_enabled() ) {
				$rate['taxes'] = nashenski_get_shipping_rate_taxes( $rate['cost'], $country );
			}
		}

		$rate['meta_data']['cookie_data'] = $this->cookie_data;
		$rate                             = apply_filters( 'nashenski/speedy/rate', $rate, $this );

		// Register the rate.
		$this->packing_budget()->spend( 0 );
		$this->add_rate( $rate );
	}

	/**
	 * Has address detail.
	 *
	 * @return bool Result.
	 */
	private function has_address_detail() {
		foreach ( array( 'streetNumber', 'blockNumber', 'entranceNumber', 'floorNumber', 'apartmentNumber', 'other' ) as $field ) {
			if ( ! empty( $this->cookie_data[ $field ] ) ) {
				return true;
			}
		}

		return false;
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
				'default'     => __( 'Speedy', 'nashenski-commerce' ),
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
			'international'      => array(
				'title'       => __( 'Ship abroad', 'nashenski-commerce' ),
				'type'        => 'select',
				'css'         => 'width: 400px;',
				'default'     => 'no',
				'options'     => array(
					'no'  => __( 'No', 'nashenski-commerce' ),
					'yes' => __( 'Yes, to the Speedy price list countries', 'nashenski-commerce' ),
				),
				'description' => __( 'Prices for these countries come only from Speedy, not from the public price list.', 'nashenski-commerce' ),
				'desc_tip'    => true,
			),
			'test'               => array(
				'title'   => __( 'Review and test', 'nashenski-commerce' ),
				'type'    => 'select',
				'css'     => 'width: 400px;',
				'default' => 'no',
				'options' => nashenski_get_shipping_tests_options(),
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
				'title'       => __( 'Fixed price', 'nashenski-commerce' ),
				'type'        => 'number',
				'placeholder' => '0',
				'description' => __( 'Enter a fixed price that will be payed by the client, and will be included in the COD.', 'nashenski-commerce' ),
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
		// Speedy's published CEE price list (services.speedy.bg/pricelist, 06.11.2026). shortcut: other EU countries need their own price rows.
		$countries = 'yes' === $this->get_option( 'international', 'no' ) ? array( 'BG', 'RO', 'GR', 'CY', 'HU', 'CZ', 'HR', 'PL', 'SK', 'SI' ) : array( 'BG' );
		return in_array( $package['destination']['country'] ?? '', apply_filters( 'nashenski/speedy/available_countries', $countries ), true );
	}

	/**
	 * Country the courier prices for: 'BG' (the Bulgarian rules) only when the widget and the package agree,
	 * otherwise the foreign one of the two (the package when the widget gives none).
	 *
	 * @return string ISO code, or '' when none is known.
	 */
	public function courier_country() {
		$widget  = (string) ( $this->cookie_data['country'] ?? '' );
		$package = (string) ( $this->package['destination']['country'] ?? '' );
		return '' !== $widget && 'BG' !== $widget ? $widget : $package;
	}

	/**
	 * Fixed price for this order: like Econt, it applies to Bulgaria only.
	 *
	 * @return mixed The fixed price, or '' when none applies.
	 */
	public function applicable_fixed_price() {
		return 'BG' === $this->courier_country() ? $this->fixed_price : '';
	}

	/**
	 * Get cookie data.
	 *
	 * @return mixed Result.
	 */
	public static function get_cookie_data() {
		return nashenski_courier_cookie( 'nashenski--speedy-address' );
	}

	/**
	 * Calculate shipping price from api.
	 *
	 * @return array|mixed Result.
	 */
	public function calculate_shipping_price_from_api() {
		// No login: the public price list prices the order (Public_Price::fallback).
		if ( ! \Nashenski\Front_End\Checkout\Pickers::connected( 'speedy' ) ) {
			return array( 'errors' => __( 'This delivery cannot be priced now. Choose another delivery method.', 'nashenski-commerce' ) );
		}

		$data = array(
			'price' => '0',
		);

		$request_body = apply_filters( 'nashenski/speedy/calculate_label', $this->generate_label(), $this );
		$this->packing_budget()->spend( 0 );
		Packing_Budget::check_parcels( count( $request_body['content']['parcels'] ?? array() ), $this->get_max_parcels() );

		WC()->session->set( 'nashenski-speedy-label', $request_body );

		$request = $this->container[ Client::SPEEDY ]->label_request( 'calculate', $request_body );

		if ( ! isset( $request ) ) {
			$data['errors'] = __( 'Calculation failed. Please try again.', 'nashenski-commerce' );
		} elseif ( isset( $request['error'] ) || isset( $request['calculations'][0]['error'] ) ) {
			if ( isset( $request['calculations'][0]['error'] ) ) {
				$data['errors'] = $request['calculations'][0]['error']['message'];
			} elseif ( isset( $request['success'] ) && ! $request['success'] && isset( $request['error'] ) ) {
				$data['errors'] = $request['error'];
			} else {
				$data['errors'] = $request['error']['message'];
			}
		} elseif ( isset( $request['calculations'] ) ) {
			$calc_data                 = $request['calculations'][0];
			$data['price_with_vat']    = number_format( $calc_data['price']['total'], 2 );
			$data['price_without_vat'] = number_format( $calc_data['price']['amount'], 2 );
			$data['vat']               = number_format( $calc_data['price']['vat'], 2 );

			$data['price'] = ( wc_tax_enabled() ) ? $data['price_without_vat'] : $data['price_with_vat'];
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
			'sender' => $this->generate_sender_data(),
		);

		if ( $this->cookie_data ) {
			$label['recipient'] = $this->generate_recipient_data();
		}

		$services_data   = '';
		$payment_by_data = '';
		$country         = $this->courier_country();

		if ( 'BG' !== $country ) {
			$services_data   = $this->generate_services_data( '202', $country );
			$payment_by_data = $this->generate_payment_by_data( 'SENDER' );
		} else {
			$services_data   = $this->generate_services_data();
			$payment_by_data = $this->generate_payment_by_data();
		}

		$content_data = $this->get_content_data();

		return array_merge( $label, $content_data, $services_data, $payment_by_data );
	}

	/**
	 * Get content data.
	 *
	 * @return mixed Result.
	 * @throws Packing_Limit_Exceeded When the packing budget is exceeded.
	 */
	private function get_content_data() {
		if ( $this->resource_rejected ) {
			throw new Packing_Limit_Exceeded( 'Shipping calculation was already rejected.' );
		}
		if ( null === $this->content_data ) {
			$this->content_data = $this->generate_content_data();
		}

		return $this->content_data;
	}

	/**
	 * Package fits max parcels.
	 *
	 * @return bool Result.
	 */
	private function package_fits_max_parcels(): bool {
		$content_data = $this->get_content_data();
		$parcels      = $content_data['content']['parcels'] ?? array();
		$max_parcels  = $this->get_max_parcels();

		return $max_parcels <= 0 || count( $parcels ) <= $max_parcels;
	}

	/**
	 * Get max parcels.
	 *
	 * @return int Result.
	 */
	public function get_max_parcels(): int {
		return (int) apply_filters( 'nashenski/speedy/max_parcels', self::MAX_PARCELS, $this->package, $this );
	}

	/**
	 * Packing budget.
	 *
	 * @return Packing_Budget Result.
	 */
	public function packing_budget(): Packing_Budget {
		if ( null === $this->packing_budget ) {
			$this->packing_budget = new Packing_Budget();
		}

		return $this->packing_budget;
	}

	/**
	 * Generate sender data.
	 *
	 * @return mixed Result.
	 */
	private function generate_sender_data() {
		$send_from = nashenski_get_option( 'speedy', 'send_from' );
		$sender    = array(
			'clientId'    => $this->container[ Client::SPEEDY_PROFILE ]->get_profile_data()['clientId'],
			'contactName' => nashenski_get_option( 'speedy', 'name' ),
			'phone1'      => array(
				'number' => nashenski_get_option( 'speedy', 'phone' ),
			),
		);

		if ( 'office' === $send_from ) {
			$sender['dropoffOfficeId'] = $this->generate_sender_office_code();
		}

		return $sender;
	}

	/**
	 * Generate sender office code.
	 *
	 * @return mixed Result.
	 */
	private function generate_sender_office_code() {
		return str_replace( 'officeID-', '', nashenski_get_option( 'speedy_send_from', 'office' ) );
	}

	/**
	 * Generate recipient data.
	 *
	 * @return mixed Result.
	 */
	private function generate_recipient_data() {
		$session_customer = WC()->session->get( 'customer' );

		$recipient = array(
			'privatePerson' => true,
			'clientName'    => $this->cookie_data['receiver'],
			'phone1'        => array(
				'number' => nashenski_format_phone( $this->cookie_data['phone'], $this->cookie_data['country'] ),
			),
		);

		if ( ! empty( $session_customer['email'] ) ) {
			$recipient['email'] = $session_customer['email'];
		}

		if ( isset( $this->cookie_data['billing_to_company'] ) && $this->cookie_data['billing_to_company'] ) {
			$recipient['privatePerson'] = false;
			$recipient['contactName']   = $recipient['clientName'];
			$recipient['clientName']    = $this->cookie_data['billing_company'];
		}

		if ( 'address' === $this->cookie_data['type'] ) {
			$recipient['addressLocation'] = $this->generate_recipient_address();
			$recipient['address']         = $this->generate_recipient_address();
		} elseif ( in_array( $this->cookie_data['type'], array( 'office', 'automat' ), true ) ) {
			$recipient['pickupOfficeId'] = $this->generate_recipient_office_code();
			$recipient['country']        = $this->cookie_data['country'];
		}

		return $recipient;
	}

	/**
	 * Generate recipient address.
	 *
	 * @return mixed Result.
	 */
	private function generate_recipient_address() {
		$raw_city    = sanitize_text_field( $this->cookie_data['city'] );
		$raw_state   = sanitize_text_field( $this->cookie_data['state'] );
		$country_id  = $this->container[ Client::SPEEDY_COUNTRIES ]->get_country_id( sanitize_text_field( $this->cookie_data['country'] ) );
		$cities_data = $this->container[ Client::SPEEDY_CITIES ]->get_filtered_cities( $raw_city, $raw_state, $country_id );

		if ( ! in_array( $cities_data['city'], $cities_data['cities_only_names'], true ) || ! isset( $cities_data['cities'][ $cities_data['city_key'] ] ) ) {
			return( array() );
		}

		$address = array(
			'countryId' => $country_id,
			'siteId'    => $cities_data['cities'][ $cities_data['city_key'] ]['id'],
		);

		if ( ! empty( $this->cookie_data['selectedAddress']['type'] ) && 'streets' === $this->cookie_data['selectedAddress']['type'] ) {
			$address['streetId'] = str_replace( 'street-', '', $this->cookie_data['selectedAddress']['orig_key'] );
			$this->add_address_details( $address );
		} elseif ( ( ! empty( $this->cookie_data['selectedAddress']['type'] ) && 'quarters' === $this->cookie_data['selectedAddress']['type'] ) ||
			$this->cookie_data['mysticQuarter']
		) {
			if ( ! empty( $this->cookie_data['mysticQuarter'] ) ) {
				$address['addressNote'] = $this->cookie_data['mysticQuarter'];
			} else {
				$address['complexId'] = str_replace( 'qtr-', '', $this->cookie_data['selectedAddress']['orig_key'] );
				$this->add_address_details( $address );
			}
		}

		return $address;
	}

	/**
	 * Add address details.
	 *
	 * @param & $address Address.
	 */
	private function add_address_details( &$address ) {
		$mapping = array(
			'streetNumber'    => 'streetNo',
			'blockNumber'     => 'blockNo',
			'entranceNumber'  => 'entranceNo',
			'floorNumber'     => 'floorNo',
			'apartmentNumber' => 'apartmentNo',
		);

		foreach ( $mapping as $cookie_key => $address_key ) {
			if ( ! empty( $this->cookie_data[ $cookie_key ] ) ) {
				$address[ $address_key ] = sanitize_text_field( $this->cookie_data[ $cookie_key ] );
			}
		}

		// Keep labels created with the previous combined field usable.
		if ( empty( $this->cookie_data['blockNumber'] ) && ! empty( $this->cookie_data['other'] ) ) {
			$parts = preg_split( '/\s+/', trim( $this->cookie_data['other'] ) );
			foreach ( array( 'blockNo', 'entranceNo', 'floorNo', 'apartmentNo' ) as $index => $key ) {
				if ( ! empty( $parts[ $index ] ) ) {
					$address[ $key ] = $parts[ $index ];
				}
			}
		}
	}

	/**
	 * Generate recipient office code.
	 *
	 * @return mixed Result.
	 */
	private function generate_recipient_office_code() {
		return ( isset( $this->cookie_data['selectedOffice'] ) ) ? $this->cookie_data['selectedOffice'] : '';
	}

	/**
	 * Generate content data.
	 *
	 * @return array Result.
	 */
	private function generate_content_data() {
		$names   = array();
		$content = array(
			'package' => 'BOX',
		);

		$weigth          = 0;
		$sizes           = array();
		$product_weights = array();
		$auto_sizes      = wc_string_to_bool( nashenski_get_option( 'speedy', 'auto_size' ) );
		$allocated_units = 0;

		foreach ( $this->package['contents'] as $key => $item ) {
			$item_weight = 0;

			if ( $item['data']->get_weight() ) {
				$item_weight = wc_get_weight( $item['data']->get_weight(), 'kg' );
				$weigth     += $item_weight * $item['quantity'];
			}

			$force = nashenski_get_option( 'speedy', 'force_variations_in_desc' );
			$name  = $item['data']->get_name();

			if ( 'yes' === $force && is_a( $item['data'], 'WC_Product_Variation' ) ) {
				$name .= ' - ' . $item['data']->get_attribute_summary();
			}

			$name    = nashenski_normalize_text_for_label( $name );
			$names[] = $name;

			if ( $auto_sizes && $item['data']->get_length() && $item['data']->get_width() && $item['data']->get_height() ) {
				$this->packing_budget()->check_units( ! empty( $item['quantity'] ) ? $item['quantity'] : 1 );
				$quantity         = ! empty( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1;
				$allocated_units += $quantity;
				$this->packing_budget()->check_units( $allocated_units );
				$this->packing_budget()->spend( $quantity );

				foreach ( range( 1, $quantity ) as $i ) {
					$sizes[]           = new Product(
						$name,
						new Size(
							wc_get_dimension( $item['data']->get_length(), 'mm', get_option( 'woocommerce_dimension_unit' ) ),
							wc_get_dimension( $item['data']->get_width(), 'mm', get_option( 'woocommerce_dimension_unit' ) ),
							wc_get_dimension( $item['data']->get_height(), 'mm', get_option( 'woocommerce_dimension_unit' ) ),
						)
					);
					$product_weights[] = $item_weight;
				}
			}
		}

		if ( ! $weigth ) {
			$weigth = apply_filters( 'nashenski/speedy/label/weight', 1, $this->package, $this );
		}

		$content['contents'] = nashenski_normalize_text_for_label( implode( ',', $names ), 99 );

		$pack_sizes = $auto_sizes ? apply_filters(
			'nashenski/speedy/label/sizes',
			array(
				'width'  => 17,
				'depth'  => 17,
				'height' => 17,
			),
			$this->package,
			$this
		) : array();
		$max_weight = self::get_max_parcel_weight( $this->delivery_type, nashenski_get_option( 'speedy', 'send_from' ) );

		if ( $auto_sizes && ! empty( $sizes ) ) {
			$carton_sizes       = 'automat' === $this->delivery_type ? self::get_aps_box_sizes_for_packer() : array();
			$content['parcels'] = self::generate_parcels_from_products( $sizes, $product_weights, $carton_sizes, $max_weight, $this->get_max_parcels(), $this->packing_budget() );
		} else {
			$content['parcels'] = self::generate_parcels( $weigth, $pack_sizes, $max_weight, $this->get_max_parcels() );
		}

		return array(
			'content' => $content,
		);
	}

	/**
	 * Generate parcels from products.
	 *
	 * @param array               $products Products.
	 * @param array               $product_weights Product weights.
	 * @param array               $carton_sizes Carton sizes.
	 * @param float               $max_weight Max weight.
	 * @param int                 $max_parcels Max parcels.
	 * @param Packing_Budget|null $budget Budget.
	 * @return array Result.
	 */
	private static function generate_parcels_from_products( array $products, array $product_weights, array $carton_sizes, float $max_weight, int $max_parcels = 0, ?Packing_Budget $budget = null ): array {
		$budget = $budget ?? new Packing_Budget();
		$budget->check_units( count( $products ) );
		$packer        = new Carton_Packer( $budget );
		$groups        = array();
		$group_items   = array();
		$group_weights = array();

		foreach ( $products as $index => $product ) {
			$budget->spend();
			$product_weight    = isset( $product_weights[ $index ] ) ? (float) $product_weights[ $index ] : 0;
			$candidate_items   = array_merge( $group_items, array( $product ) );
			$candidate_weights = array_merge( $group_weights, array( $product_weight ) );

			if ( empty( $group_items ) || self::products_fit_parcel_weight( $packer, $candidate_items, $candidate_weights, $carton_sizes, $max_weight ) ) {
				$group_items   = $candidate_items;
				$group_weights = $candidate_weights;
				continue;
			}

			Packing_Budget::check_parcels( count( $groups ) + 1, $max_parcels );
			$groups[] = array(
				'products' => $group_items,
				'weights'  => $group_weights,
			);

			$group_items   = array( $product );
			$group_weights = array( $product_weight );
		}

		if ( ! empty( $group_items ) ) {
			Packing_Budget::check_parcels( count( $groups ) + 1, $max_parcels );
			$groups[] = array(
				'products' => $group_items,
				'weights'  => $group_weights,
			);
		}

		$parcels = array();

		foreach ( $groups as $group ) {
			$result     = $packer->find_best_carton( $group['products'], $carton_sizes );
			$pack_sizes = self::get_pack_sizes_from_result( $result );
			$weight     = array_sum( $group['weights'] );

			$group_parcels = self::generate_parcels( $weight, $pack_sizes, $max_weight, $max_parcels );
			Packing_Budget::check_parcels( count( $parcels ) + count( $group_parcels ), $max_parcels );
			foreach ( $group_parcels as $parcel ) {
				$parcels[] = $parcel;
			}
		}

		foreach ( $parcels as $key => &$parcel ) {
			$parcel['seqNo'] = $key + 1;
		}

		unset( $parcel );

		return $parcels;
	}

	/**
	 * Products fit parcel weight.
	 *
	 * @param Carton_Packer $packer Packer.
	 * @param array         $products Products.
	 * @param array         $weights Weights.
	 * @param array         $carton_sizes Carton sizes.
	 * @param float         $max_weight Max weight.
	 * @return bool Result.
	 */
	private static function products_fit_parcel_weight( Carton_Packer $packer, array $products, array $weights, array $carton_sizes, float $max_weight ): bool {
		if ( $max_weight <= 0 ) {
			return true;
		}

		$weight = array_sum( $weights );

		if ( self::use_volumetric_weight_for_parcels() ) {
			$result     = $packer->find_best_carton( $products, $carton_sizes );
			$pack_sizes = self::get_pack_sizes_from_result( $result );
			$weight     = self::get_parcel_count_weight( $weight, $pack_sizes );
		}

		return $weight <= $max_weight;
	}

	/**
	 * Get pack sizes from result.
	 *
	 * @param mixed $result Result.
	 * @return array Result.
	 */
	private static function get_pack_sizes_from_result( $result ): array {
		return array(
			'width'  => wc_get_dimension( $result->W, 'cm', 'mm' ),
			'depth'  => wc_get_dimension( $result->L, 'cm', 'mm' ),
			'height' => wc_get_dimension( $result->H, 'cm', 'mm' ),
		);
	}

	/**
	 * Generate parcels.
	 *
	 * @param mixed $weight Weight.
	 * @param array $pack_sizes Pack sizes.
	 * @param float $max_weight Max weight.
	 * @param int   $max_parcels Max parcels.
	 * @return array Result.
	 * @throws Packing_Limit_Exceeded When the packing budget is exceeded.
	 */
	private static function generate_parcels( $weight, array $pack_sizes, float $max_weight, int $max_parcels = 0 ): array {
		$min_weight = (float) apply_filters( 'nashenski/speedy/min_parcel_weight', 0.100 );
		$weight     = is_numeric( $weight ) ? (float) $weight : $min_weight;
		if ( ! is_finite( $min_weight ) || $min_weight <= 0 || ! is_finite( $weight ) || ! is_finite( $max_weight ) ) {
			throw new Packing_Limit_Exceeded( 'Shipping parcel weights must be finite.' );
		}
		$weight       = max( $min_weight, $weight );
		$count_weight = max( $min_weight, self::get_parcel_count_weight( $weight, $pack_sizes ) );
		if ( ! is_finite( $count_weight ) ) {
			throw new Packing_Limit_Exceeded( 'Shipping volumetric weight must be finite.' );
		}
		$parcels = array();

		if ( $max_weight <= 0 ) {
			return array( self::generate_parcel( 1, $weight, $pack_sizes ) );
		}

		Packing_Budget::check_parcels( ceil( $count_weight / $max_weight ), $max_parcels );

		if ( abs( $count_weight - $weight ) > 0.0001 ) {
			$parcel_count  = (int) ceil( $count_weight / $max_weight );
			$parcel_count  = max( 1, $parcel_count );
			$parcel_weight = $weight / $parcel_count;

			for ( $i = 1; $i <= $parcel_count; $i++ ) {
				$parcels[] = self::generate_parcel( $i, max( $min_weight, $parcel_weight ), $pack_sizes );
			}

			return $parcels;
		}

		while ( $count_weight > $max_weight ) {
			$parcels[]     = self::generate_parcel( count( $parcels ) + 1, $max_weight, $pack_sizes );
			$count_weight -= $max_weight;
			$weight       -= $max_weight;
		}

		$parcels[] = self::generate_parcel( count( $parcels ) + 1, max( $min_weight, $weight ), $pack_sizes );

		return $parcels;
	}

	/**
	 * Generate parcel.
	 *
	 * @param int   $seq_no Seq no.
	 * @param float $weight Weight.
	 * @param array $pack_sizes Pack sizes.
	 * @return array Result.
	 */
	private static function generate_parcel( int $seq_no, float $weight, array $pack_sizes ): array {
		$parcel = array(
			'seqNo'  => $seq_no,
			'weight' => round( $weight, 3 ),
		);

		if ( ! empty( $pack_sizes ) ) {
			$parcel['size'] = $pack_sizes;
		}

		return $parcel;
	}

	/**
	 * Get max parcel weight.
	 *
	 * @param mixed $delivery_type Delivery type.
	 * @param mixed $send_from_type Send from type.
	 * @return float Result.
	 */
	public static function get_max_parcel_weight( $delivery_type = null, $send_from_type = null ): float {
		$max_weight = self::use_volumetric_weight_for_parcels() || 'address' !== $delivery_type || 'office' === $send_from_type ? 32 : 50;

		return (float) apply_filters( 'nashenski/speedy/max_parcel_weight', $max_weight, $delivery_type, $send_from_type );
	}

	/**
	 * Use volumetric weight for parcels.
	 *
	 * @return bool Result.
	 */
	public static function use_volumetric_weight_for_parcels(): bool {
		return wc_string_to_bool( nashenski_get_option( 'speedy', 'declared_value' ) );
	}

	/**
	 * Get parcel volumetric weight.
	 *
	 * @param array $pack_sizes Pack sizes.
	 * @return float Result.
	 */
	public static function get_parcel_volumetric_weight( array $pack_sizes ): float {
		if (
			empty( $pack_sizes['width'] ) ||
			empty( $pack_sizes['depth'] ) ||
			empty( $pack_sizes['height'] )
		) {
			return 0;
		}

		return ( (float) $pack_sizes['width'] * (float) $pack_sizes['depth'] * (float) $pack_sizes['height'] ) / self::VOLUMETRIC_WEIGHT_DIVISOR;
	}

	/**
	 * Get parcel count weight.
	 *
	 * @param float $real_weight Real weight.
	 * @param array $pack_sizes Pack sizes.
	 * @return float Result.
	 */
	private static function get_parcel_count_weight( float $real_weight, array $pack_sizes ): float {
		if ( ! self::use_volumetric_weight_for_parcels() ) {
			return $real_weight;
		}

		$volumetric_weight = self::get_parcel_volumetric_weight( $pack_sizes );

		return $volumetric_weight > 0 ? $volumetric_weight : $real_weight;
	}

	/**
	 * Get aps box sizes for packer.
	 *
	 * @return mixed Result.
	 */
	private static function get_aps_box_sizes_for_packer() {
		$box_sizes = array();

		foreach ( self::$aps_box_sizes as $box_size ) {
			$box_sizes[] = array(
				'name'   => $box_size['name'],
				'length' => wc_get_dimension( $box_size['length'], 'mm', 'cm' ),
				'width'  => wc_get_dimension( $box_size['width'], 'mm', 'cm' ),
				'height' => wc_get_dimension( $box_size['height'], 'mm', 'cm' ),
			);
		}

		return $box_sizes;
	}

	/**
	 * Generate services data.
	 *
	 * @param string $service_id Service id.
	 * @param string $country Country.
	 * @return array Result.
	 */
	private function generate_services_data( $service_id = '505', $country = 'BG' ) {
		$services   = array(
			'autoAdjustPickupDate' => true,
			'serviceId'            => $service_id,
			'serviceIds'           => array( $service_id ),
			'additionalServices'   => array(),
		);
		$is_fragile = wc_string_to_bool( nashenski_get_option( 'speedy', 'declared_value' ) );

		if ( $is_fragile ) {
			$services['additionalServices']['declaredValue'] = array(
				'amount'                => nashenski_get_package_total(),
				'fragile'               => $is_fragile,
				'ignoreIfNotApplicable' => true,
			);
		}

		if ( 'cod' === $this->cookie_data['payment'] ) {
			$cod_data = array();

			if ( '505' === $service_id ) {
				$cod_data = array(
					'amount'         => nashenski_get_package_total(),
					'processingType' => ( wc_string_to_bool( nashenski_get_option( 'speedy', 'ppp' ) ) ) ? 'POSTAL_MONEY_TRANSFER' : 'CASH',
				);
			} else {
				$cod_data = array(
					'amount' => nashenski_get_package_total(),
				);
			}

			$services['additionalServices']['cod'] = $cod_data;

			if ( wc_string_to_bool( nashenski_get_option( 'speedy', 'kb' ) ) && wc_tax_enabled() ) {
				$services['additionalServices']['cod']['fiscalReceiptItems'] = array();

				foreach ( WC()->cart->get_cart() as $cart_item ) {
					if ( ! $cart_item['line_total'] || $cart_item['line_total'] <= 0 ) {
						continue;
					}

					$rate = round( ( $cart_item['line_tax'] / $cart_item['line_total'] ) * 100 );
					$services['additionalServices']['cod']['fiscalReceiptItems'][] = array(
						'description'   => nashenski_normalize_text_for_label( $cart_item['data']->get_name(), 49 ),
						'vatGroup'      => nashenski_get_vat_group_from_rate( $rate ),
						'amount'        => number_format( $cart_item['line_total'], 2, '.', '' ),
						'amountWithVat' => number_format( $cart_item['line_total'] + $cart_item['line_tax'], 2, '.', '' ),
					);
				}

				if ( apply_filters( 'nashenski/shipping/package_total_includes_fees', true ) && WC()->cart->get_fee_total() > 0 ) {
					$total = floatval( WC()->cart->get_fee_total() );
					$tax   = floatval( WC()->cart->get_fee_tax() );
					$rate  = round( ( $tax / $total ) * 100 );

					$services['additionalServices']['cod']['fiscalReceiptItems'][] = array(
						'description'   => __( 'Fees', 'nashenski-commerce' ),
						'vatGroup'      => nashenski_get_vat_group_from_rate( $rate ),
						'amount'        => number_format( $total, 2, '.', '' ),
						'amountWithVat' => number_format( $total + $tax, 2, '.', '' ),
					);
				}
			}

			if ( 'GR' !== $country &&
				'no' !== $this->test &&
				! ( isset( $this->cookie_data['selectedOfficeType'] ) && 'APT' === $this->cookie_data['selectedOfficeType'] )
			) {
				if ( 'review' === $this->test ) {
					$test = 'OPEN';
				} elseif ( 'test' === $this->test ) {
					$test = 'TEST';
				}

				$services['additionalServices']['obpd'] = array(
					'option'                  => $test,
					'returnShipmentServiceId' => $service_id,
					'returnShipmentPayer'     => 'SENDER',
				);
			}
		}

		if ( empty( $services['additionalServices'] ) ) {
			unset( $services['additionalServices'] );
		}

		return array(
			'service' => $services,
		);
	}

	/**
	 * Generate payment by data.
	 *
	 * @param string $shipping_costs_payer Shipping costs payer.
	 * @return array Result.
	 */
	private function generate_payment_by_data( $shipping_costs_payer = 'RECIPIENT' ) {
		$payment = array(
			'courierServicePayer' => $shipping_costs_payer,
			'declaredValuePayer'  => $shipping_costs_payer,
			'packagePayer'        => $shipping_costs_payer,
		);

		if ( isset( $this->cookie_data['payment'] ) && 'cod' !== $this->cookie_data['payment'] ) {
			$payment['courierServicePayer'] = 'SENDER';
			$payment['declaredValuePayer']  = 'SENDER';
			$payment['packagePayer']        = 'SENDER';
		}

		if ( ! empty( $this->applicable_fixed_price() ) && 'RECIPIENT' === $payment['courierServicePayer'] ) {
			$payment['courierServicePayer'] = 'SENDER';
		}

		$free_over = Price_Lists::free_over( $this->courier_country(), $this->free_shipping_over, $this->free_shipping_by_country );

		if ( nashenski_has_free_shipping_coupon_in_cart() ||
			( (float) $free_over > 0 && nashenski_get_package_total() >= $free_over )
		) {
			$this->free_shipping = true;

			if ( isset( $this->cookie_data['fixed_price'] ) ) {
				unset( $this->cookie_data['fixed_price'] );
			}

			if ( 'RECIPIENT' === $payment['courierServicePayer'] ) {
				$payment['courierServicePayer'] = 'SENDER';
				$payment['declaredValuePayer']  = 'SENDER';
				$payment['packagePayer']        = 'SENDER';
			}
		}

		if ( nashenski_get_option( 'speedy', 'administrative_fee' ) === 'yes' ) {
			$payment['administrativeFee'] = true;
		}

		return array(
			'payment' => $payment,
		);
	}

	/**
	 * Validate speedy method.
	 *
	 * @param mixed $fields Fields.
	 * @param mixed $errors Errors.
	 */
	public static function validate_speedy_method( $fields, $errors ) {
		if ( ! WC()->cart->needs_shipping() ) {
			return;
		}

		$chosen_shippings = WC()->session->get( 'chosen_shipping_methods' );

		foreach ( $chosen_shippings as $key => $shipping ) {
			if ( strpos( $shipping, 'nashenski_speedy' ) !== false ) {
				$data = nashenski_chosen_rate( $key, $shipping );

				if ( ! $data ) {
					continue;
				}

				if ( 'nashenski_speedy' === $data->method_id && ! $data->meta_data['validated'] ) {
					$meta_data = $data->get_meta_data();

					if ( ! empty( $meta_data['errors'] ) ) {
						/* translators: %s: error message from the courier */
						$errors->add( 'validation', sprintf( __( 'Speedy - %s', 'nashenski-commerce' ), $meta_data['errors'] ) );
					} elseif ( ! empty( $meta_data['address_missing'] ) ) {
						$errors->add( 'validation', __( 'Please fill in your delivery address.', 'nashenski-commerce' ) );
					} else {
						$errors->add( 'validation', __( 'Please choose delivery option!', 'nashenski-commerce' ) );
					}
				} elseif ( 'nashenski_speedy' === $data->method_id ) {
					$cookie_data = self::get_cookie_data();

					if ( ! empty( $cookie_data ) &&
						( ! empty( $cookie_data['type'] ) && 'office' === $cookie_data['type'] ) &&
						empty( $cookie_data['selectedOffice'] )
					) {
						$errors->add( 'validation', __( 'Please choose a office.', 'nashenski-commerce' ) );
					}

					if ( ! empty( $cookie_data ) &&
						( ! empty( $cookie_data['type'] ) && 'automat' === $cookie_data['type'] ) &&
						empty( $cookie_data['selectedOffice'] )
					) {
						$errors->add( 'validation', __( 'Please choose a automat.', 'nashenski-commerce' ) );
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
		nashenski_save_checkout_label( $order_id, 'speedy' );
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

		if ( nashenski_get_option( 'speedy', 'label_after_checkout' ) === 'yes' ) {
			$email_ids_to_send[] = 'customer_processing_order';
			$email_ids_to_send[] = 'customer_on_hold_order';
		}

		$email_ids_to_send = apply_filters( 'nashenski/speedy/emails_to_send_label_number', $email_ids_to_send );

		if ( ! in_array( $email->id, $email_ids_to_send, true ) ) {
			return;
		}

		$label = $order->get_meta( 'nashenski_speedy_label' );

		if ( ! isset( $label['id'] ) ) {
			return;
		}

		$number = $label['id'];
		$url    = \Nashenski\Shipping\Automation::tracking_url( 'speedy', $number );

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

		$track_number_text = apply_filters( 'nashenski/speedy/track_number_text_in_email', $track_number_text, $url, $order );

		echo wp_kses_post( wpautop( $track_number_text ) );
	}
}
