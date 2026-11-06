<?php
/**
 * Speedy admin: the order label, its payment, services and recipient data.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

use Nashenski\Container\Client;
use Nashenski\Pricing\Price_Lists;
use Nashenski\Shipping\Speedy\Address;
use Nashenski\Shipping\Packer\Packing_Budget;
use Nashenski\Shipping\Packer\Packing_Limit_Exceeded;

defined( 'ABSPATH' ) || exit;

/**
 * Speedy label handling on the order screen.
 */
class Speedy {
	/**
	 * Container.
	 *
	 * @var mixed
	 */
	private static $container = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );

		add_action( 'wp_ajax_nashenski_speedy_generate_label', array( __CLASS__, 'generate_label' ) );
		add_action( 'wp_ajax_nashenski_speedy_delete_label', array( __CLASS__, 'delete_label' ) );
		add_action( 'wp_ajax_nashenski_speedy_update_shipment_status', array( __CLASS__, 'update_shipment_status' ) );
		add_action( 'wp_ajax_nashenski_speedy_print_labels', array( __CLASS__, 'print_labels_endpoint' ) );

		add_filter( 'nashenski/speedy/calculate_label', array( __CLASS__, 'set_min_parcel_weight' ), 30, 2 );
		// The minimum parcel weight is applied only when calculating a label, not on the create and update label filters.
	}

	/**
	 * Add meta boxes.
	 */
	public static function add_meta_boxes() {
		nashenski_add_courier_meta_box( 'speedy', __( 'Speedy Delivery', 'nashenski-commerce' ), array( __CLASS__, 'meta_box' ) );
	}

	/**
	 * Meta box.
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
				if ( 'nashenski_speedy' === $shipping['method_id'] ) {
					echo '<div id="nashenski--speedy-admin"></div>';

					$label_data      = array();
					$cookie_data     = $theorder->get_meta( 'nashenski_speedy_cookie_data' );
					$shipment_status = $theorder->get_meta( 'nashenski_speedy_shipment_status' );
					$operations      = $theorder->get_meta( 'nashenski_speedy_operations' );

					$label = $theorder->get_meta( 'nashenski_speedy_label' );

					if ( $label ) {
						$label_data = $label;

						if ( $label_data['content']['parcels'] ) {
							foreach ( $label_data['content']['parcels'] as $key => &$parcel ) {
								if ( isset( $parcel['sizes'] ) ) {
									$parcel['size'] = $parcel['sizes'];
									unset( $parcel['sizes'] );
								} elseif ( empty( $parcel['size'] ) ) {
									$parcel['size'] = array(
										'depth'  => 17,
										'width'  => 17,
										'height' => 17,
									);
								}
							}
						}
					}

					if ( ! $cookie_data ) {
						foreach ( $shipping->get_meta_data() as $meta_data ) {
							$data = $meta_data->get_data();

							if ( 'cookie_data' === $data['key'] ) {
								$cookie_data = $data['value'];
							}
						}
					}

					// City and region come from the order's address (the one the street lists use), not from the
					// checkout data, which can be stale after the address was edited.
					if ( is_array( $cookie_data ) ) {
						$prefix               = nashenski_is_different_shipping_address( $theorder ) ? 'shipping' : 'billing';
						$cookie_data['city']  = $theorder->{"get_{$prefix}_city"}();
						$cookie_data['state'] = $theorder->{"get_{$prefix}_state"}();
					}

					if ( $theorder->get_payment_method() === 'cod' && ! isset( $label_data['service']['additionalServices'] ) ) {
						$label_data = self::fix_cod_data( $label_data, $theorder );
					}

					wp_localize_script(
						'nashenski-js-admin',
						'nashenski_speedy',
						array(
							'label'          => $label_data,
							'shipmentStatus' => $shipment_status,
							'operations'     => $operations,
							'cookie_data'    => $cookie_data,
							'paymentType'    => $theorder->get_payment_method(),
							'sendFrom'       => self::get_send_from_data(),
							'offices'        => self::get_offices( $theorder ),
							'streets'        => self::get_streets( $cookie_data, $theorder ),
							'orderId'        => $theorder->get_id(),
							'testsOptions'   => nashenski_return_array_for_select( nashenski_get_shipping_tests_options() ),
							'testOption'     => self::get_test_option( $label_data ),
							'maxParcels'     => \Nashenski\Shipping\Speedy\Method::MAX_PARCELS,
							'i18n'           => self::get_i18n(),
							'nonce'          => wp_create_nonce( 'nashenski_admin_label' ),
						)
					);
					break;
				}
			}
		}
	}

	/**
	 * Fix cod data.
	 *
	 * @param mixed $label_data Label data.
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	public static function fix_cod_data( $label_data, $order ) {
		$service_id = $label_data['service']['serviceId'] ?? null;

		if ( '505' === $service_id ) {
			$cod_data = array(
				'amount'         => $order->get_total(),
				'processingType' => ( wc_string_to_bool( nashenski_get_option( 'speedy', 'ppp' ) ) ) ? 'POSTAL_MONEY_TRANSFER' : 'CASH',
			);
		} else {
			$cod_data = array(
				'amount' => $order->get_total(),
			);
		}

		$label_data['service']['additionalServices']['cod'] = $cod_data;
		$is_fragile = wc_string_to_bool( nashenski_get_option( 'speedy', 'declared_value' ) );

		if ( $is_fragile ) {
			$label_data['service']['additionalServices']['declaredValue'] = array(
				'amount'                => $order->get_total(),
				'fragile'               => $is_fragile,
				'ignoreIfNotApplicable' => true,
			);
		}

		return $label_data;
	}

	/**
	 * Get i18n.
	 *
	 * @return mixed Result.
	 */
	protected static function get_i18n() {
		return array(
			'updateShipmentStatus'  => __( 'Update shipment status', 'nashenski-commerce' ),
			'updateLabel'           => __( 'Update label', 'nashenski-commerce' ),
			'generateLabel'         => __( 'Generate label', 'nashenski-commerce' ),
			'deleteLabel'           => __( 'Delete label', 'nashenski-commerce' ),
			'label'                 => __( 'Label', 'nashenski-commerce' ),
			'default'               => __( 'Default', 'nashenski-commerce' ),
			'selected'              => __( 'Selected', 'nashenski-commerce' ),
			'choose'                => __( 'Choose', 'nashenski-commerce' ),
			'deliveryPayedBy'       => __( 'Delivery is payed by', 'nashenski-commerce' ),
			'cd'                    => __( 'Cash on delivery', 'nashenski-commerce' ),
			'packCount'             => __( 'Pack count', 'nashenski-commerce' ),
			'shipmentType'          => __( 'Shipment type', 'nashenski-commerce' ),
			'other'                 => __( 'Other', 'nashenski-commerce' ),
			'blVhEt'                => __( 'bl. vh. et.', 'nashenski-commerce' ),
			'streetNumber'          => __( 'Street number', 'nashenski-commerce' ),
			'blockNumber'           => __( 'Block', 'nashenski-commerce' ),
			'entranceNumber'        => __( 'Entrance', 'nashenski-commerce' ),
			'floorNumber'           => __( 'Floor', 'nashenski-commerce' ),
			'apartmentNumber'       => __( 'Apartment', 'nashenski-commerce' ),
			'addressDetailRequired' => __( 'Fill in at least one of street number, block, entrance, floor or apartment.', 'nashenski-commerce' ),
			'streetQuarter'         => __( 'Street/Quarter', 'nashenski-commerce' ),
			'office'                => __( 'Office', 'nashenski-commerce' ),
			'automat'               => __( 'Automat', 'nashenski-commerce' ),
			'address'               => __( 'Address', 'nashenski-commerce' ),
			'deliveryType'          => __( 'Delivery type', 'nashenski-commerce' ),
			'labelData'             => __( 'Label data', 'nashenski-commerce' ),
			'buyer'                 => __( 'Buyer', 'nashenski-commerce' ),
			'sender'                => __( 'Sender', 'nashenski-commerce' ),
			'weight'                => __( 'Physical Weight', 'nashenski-commerce' ),
			'volumetricWeight'      => __( 'Volumetric weight', 'nashenski-commerce' ),
			'chargeableWeight'      => __( 'Chargeable weight', 'nashenski-commerce' ),
			'fixedPrice'            => __( 'Fixed price', 'nashenski-commerce' ),
			'copyLabelData'         => __( 'Copy Label Data', 'nashenski-commerce' ),
			'copyLabelDataMessage'  => __( 'You just copied the label data used for the Speedy API.', 'nashenski-commerce' ),
			'shipmentStatus'        => __( 'Shipment status', 'nashenski-commerce' ),
			'time'                  => __( 'Time:', 'nashenski-commerce' ),
			'event'                 => __( 'Event:', 'nashenski-commerce' ),
			'details'               => __( 'Details:', 'nashenski-commerce' ),
			'reviewAndTest'         => __( 'Review and test', 'nashenski-commerce' ),
			'declaredValue'         => __( 'Declared value', 'nashenski-commerce' ),
			'mysticQuarter'         => __( 'Street or quarter', 'nashenski-commerce' ),
			'description'           => __( 'Description', 'nashenski-commerce' ),
			'a4WithCopy'            => __( 'A4 with copy on same page', 'nashenski-commerce' ),
			'a4OnSingle'            => __( 'A4 with copy on single page', 'nashenski-commerce' ),
			'sendFrom'              => __( 'Send From', 'nashenski-commerce' ),
			'length'                => __( 'Length', 'nashenski-commerce' ),
			'width'                 => __( 'Width', 'nashenski-commerce' ),
			'height'                => __( 'Height', 'nashenski-commerce' ),
			'pack'                  => __( 'Pack', 'nashenski-commerce' ),
			'addPack'               => __( 'Add Pack', 'nashenski-commerce' ),
			'removePack'            => __( 'Remove Pack', 'nashenski-commerce' ),
		);
	}

	/**
	 * Get send from data.
	 *
	 * @return mixed Result.
	 */
	protected static function get_send_from_data() {
		$container = nashenski()->container();
		$type      = nashenski_get_option( 'speedy', 'send_from' );
		$data      = array(
			'type' => $type,
		);

		$data['currentOffice'] = nashenski_get_option( 'speedy_send_from', 'office' );
		$data['offices']       = ( nashenski_get_option( 'speedy_send_from', 'city' ) ) ? $container[ Client::SPEEDY_OFFICES ]->get_formatted_offices( nashenski_get_option( 'speedy_send_from', 'city' ) ) : array();

		if ( ! empty( $data['offices'] ) ) {
			$data['offices'] = array_merge( $data['offices']['shops'], $data['offices']['aps'] );
		}

		$data['currentAddress'] = nashenski_get_option( 'speedy', 'profile_key' );
		$data['addresses']      = $container[ Client::SPEEDY_PROFILE ]->get_profiles_for_settings();

		return $data;
	}

	/**
	 * Get offices.
	 *
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	protected static function get_offices( $order ) {
		$offices = array();
		$city_id = false;
		$country = $order->get_billing_country();
		$state   = $order->get_billing_state();
		$city    = $order->get_billing_city();

		if ( nashenski_is_different_shipping_address( $order ) ) {
			$country = $order->get_shipping_country();
			$state   = $order->get_shipping_state();
			$city    = $order->get_shipping_city();
		}

		$country_id = self::$container[ Client::SPEEDY_COUNTRIES ]->get_country_id( $country );

		if ( '300' === $country_id ) {
			$city_id = 0;
		} else {
			$cities_data = self::$container[ Client::SPEEDY_CITIES ]->get_filtered_cities( $city, $state, $country_id );

			if ( false !== $cities_data['city_key'] ) {
				$city_id = $cities_data['cities'][ $cities_data['city_key'] ]['id'];
			}
		}

		if ( ! ( false === $city_id || null === $city_id ) ) {
			$offices = self::$container[ Client::SPEEDY_OFFICES ]->get_offices( $city_id );
			$offices = ( isset( $offices['offices'] ) ) ? $offices['offices'] : array();
		}

		return $offices;
	}

	/**
	 * Get streets.
	 *
	 * @param mixed $cookie_data Cookie data.
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	protected static function get_streets( $cookie_data, $order ) {
		$streets  = array();
		$quarters = array();
		$query    = ' ';
		$country  = $order->get_billing_country();
		$state    = $order->get_billing_state();
		$city     = $order->get_billing_city();

		if ( nashenski_is_different_shipping_address( $order ) ) {
			$country = $order->get_shipping_country();
			$state   = $order->get_shipping_state();
			$city    = $order->get_shipping_city();
		}

		$country_id = self::$container[ Client::SPEEDY_COUNTRIES ]->get_country_id( $country );

		if ( ! empty( $cookie_data['selectedAddress'] ) ) {
			$query = explode( ' ', $cookie_data['selectedAddress']['label'] );
			unset( $query[0] );
			$query = implode( ' ', $query );
		}

		$cities_data = self::$container[ Client::SPEEDY_CITIES ]->get_filtered_cities( $city, $state, $country_id );

		if ( false !== $cities_data['city_key'] ) {
			$city_id = $cities_data['cities'][ $cities_data['city_key'] ]['id'];

			$streets  = nashenski_return_array_for_select( Address::get_streets_for_query( $city_id, $query ), 1, array( 'type' => 'streets' ) );
			$quarters = nashenski_return_array_for_select( Address::get_quarters_for_query( $city_id, $query ), 1, array( 'type' => 'quarters' ) );
		}

		return array_merge( $streets, $quarters );
	}

	/**
	 * Get test option.
	 *
	 * @param mixed $label Label.
	 * @return mixed Result.
	 */
	protected static function get_test_option( $label ) {
		$option = 'no';

		if ( ! empty( $label['service']['additionalServices']['obpd']['option'] ) ) {
			if ( 'OPEN' === $label['service']['additionalServices']['obpd']['option'] ) {
				$option = 'review';
			} elseif ( 'TEST' === $label['service']['additionalServices']['obpd']['option'] ) {
				$option = 'test';
			}
		}

		return $option;
	}

	/**
	 * Delete label.
	 */
	public static function delete_label() {
		nashenski_check_admin_label_actions();

		$container       = nashenski()->container();
		$order_id        = nashenski_label_request( 'orderId' );
		$shipment_status = nashenski_label_request( 'shipmentStatus', array() );
		$order           = wc_get_order( $order_id );

		$response = $container[ Client::SPEEDY ]->label_request(
			'delete',
			array(
				'shipmentId' => $shipment_status['id'],
				'comment'    => 'Нулиране',
			)
		);

		$order->update_meta_data( 'nashenski_speedy_shipment_status', '' );
		$order->update_meta_data( 'nashenski_speedy_operations', '' );
		$order->save();
		nashenski_add_label_order_note( $order, 'Speedy', 'deleted', $shipment_status['id'] ?? '' );

		wp_send_json_success( $response );
		wp_die();
	}

	/**
	 * Update shipment status.
	 */
	public static function update_shipment_status() {
		nashenski_check_admin_label_actions();

		$container       = nashenski()->container();
		$order_id        = nashenski_label_request( 'orderId' );
		$order           = wc_get_order( $order_id );
		$shipment_status = nashenski_label_request( 'shipmentStatus', array() );
		$data            = array();

		if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', (string) ( $shipment_status['id'] ?? '' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid parcel number.', 'nashenski-commerce' ) ), 400 );
		}

		$response = $container[ Client::SPEEDY ]->api_call(
			$container[ Client::SPEEDY ]::TRACK_ENDPOINT,
			array(
				'parcels' => array( array( 'id' => $shipment_status['id'] ) ),
			)
		);

		$order_shipment_status = $response['parcels'][0]['operations'];

		$order->update_meta_data( 'nashenski_speedy_operations', $order_shipment_status );
		$order->save();

		wp_send_json_success( array( 'operations' => $order_shipment_status ) );
		wp_die();
	}

	/**
	 * Generate label.
	 */
	public static function generate_label() {
		nashenski_check_admin_label_actions();

		$order = wc_get_order( nashenski_label_request( 'orderId' ) );
		$label = nashenski_label_request( 'label_data', array() );

		$label = self::update_sender( $label );
		$label = self::update_customer_note( $label, $order );
		$label = self::update_recipient_data( $label );
		$label = self::update_payment_by( $label, $order );
		$label = self::update_services( $label, $order );
		$label = self::update_fiscal_items( $label, $order );

		$data = self::send_label_to_speedy( $label, $order );

		wp_send_json_success( $data );
		wp_die();
	}

	/**
	 * Generate label after order generated.
	 *
	 * @param mixed $order_id Order id.
	 */
	public static function generate_label_after_order_generated( $order_id ) {
		$order = wc_get_order( $order_id );
		$label = $order->get_meta( 'nashenski_speedy_label' );
		if ( ! $label ) {
			return;
		}

		$label = self::update_sender( $label );
		$label = self::update_customer_note( $label, $order );
		$label = self::update_cod( $label, $order );
		$label = self::update_fiscal_items( $label, $order );

		self::send_label_to_speedy( $label, $order );
	}

	/**
	 * Update sender.
	 *
	 * @param mixed $label Label.
	 * @return mixed Result.
	 */
	public static function update_sender( $label ) {
		$container = nashenski()->container();
		$send_from = nashenski_get_option( 'speedy', 'send_from' );
		$sender    = array(
			'clientId'    => $container[ Client::SPEEDY_PROFILE ]->get_profile_data()['clientId'],
			'contactName' => nashenski_get_option( 'speedy', 'name' ),
			'phone1'      => array(
				'number' => nashenski_get_option( 'speedy', 'phone' ),
			),
		);

		if ( 'office' === $send_from ) {
			$sender['dropoffOfficeId'] = str_replace( 'officeID-', '', nashenski_get_option( 'speedy_send_from', 'office' ) );
		}

		$new_send_from      = ( ( null !== nashenski_label_request( 'send_from', null ) ) ) ? nashenski_label_request( 'send_from', array() ) : '';
		$new_send_from_type = ( ( null !== nashenski_label_request( 'send_from_type', null ) ) ) ? nashenski_label_request( 'send_from_type' ) : '';

		if ( ! empty( $new_send_from ) && ! empty( $new_send_from_type ) ) {
			switch ( $new_send_from_type ) {
				case 'address':
					$profiles           = nashenski_get_option( 'speedy', 'profile_data' );
					$sender['clientId'] = $profiles['clients'][ $new_send_from ]['clientId'];

					unset( $sender['dropoffOfficeId'] );
					break;
				case 'office':
					$sender['dropoffOfficeId'] = str_replace( 'officeID-', '', $new_send_from );
					break;
			}
		}

		$label['sender'] = $sender;

		return $label;
	}

	/**
	 * Update fiscal items.
	 *
	 * @param mixed $label Label.
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	public static function update_fiscal_items( $label, $order ) {
		$country = ( $order->get_shipping_country() ) ? $order->get_shipping_country() : $order->get_billing_country();

		if ( 'BG' === $country &&
			wc_string_to_bool( nashenski_get_option( 'speedy', 'kb' ) ) &&
			$order->get_payment_method() === 'cod'
		) {
			$cookie_data = $order->get_meta( 'nashenski_speedy_cookie_data' );
			$label['service']['additionalServices']['cod']['fiscalReceiptItems'] = array();

			foreach ( $order->get_items() as $item ) {
				if ( $item->get_total() <= 0 ) {
					continue;
				}

				$rate = nashenski_get_order_item_vat_rate( $item, $order, false, 0 );

				$label['service']['additionalServices']['cod']['fiscalReceiptItems'][] = array(
					'description'   => nashenski_normalize_text_for_label( $item->get_name(), 49 ),
					'vatGroup'      => nashenski_get_vat_group_from_rate( $rate ),
					'amount'        => number_format( $item->get_total(), 2, '.', '' ),
					'amountWithVat' => number_format( $item->get_total() + $item->get_total_tax(), 2, '.', '' ),
				);
			}

			if ( ! empty( $order->get_items( 'fee' ) ) && apply_filters( 'nashenski/shipping/package_total_includes_fees', true ) ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks extensions.
				foreach ( $order->get_items( 'fee' ) as $item ) {
					if ( $item->get_total() <= 0 ) {
						continue;
					}

					$rate = nashenski_get_order_item_vat_rate( $item, $order, false, 0 );

					$label['service']['additionalServices']['cod']['fiscalReceiptItems'][] = array(
						'description'   => nashenski_normalize_text_for_label( $item->get_name(), 49 ),
						'vatGroup'      => nashenski_get_vat_group_from_rate( $rate ),
						'amount'        => number_format( $item->get_total(), 2, '.', '' ),
						'amountWithVat' => number_format( $item->get_total() + $item->get_total_tax(), 2, '.', '' ),
					);
				}
			}

			if ( '' !== Price_Lists::positive( $cookie_data['fixed_price'] ?? '' ) ) {
				$rate = ( wc_tax_enabled() ) ? 20 : 0;

				$label['service']['additionalServices']['cod']['fiscalReceiptItems'][] = array(
					'description'   => nashenski_normalize_text_for_label( 'Доставка', 49 ),
					'vatGroup'      => nashenski_get_vat_group_from_rate( $rate ),
					'amount'        => nashenski_tax_based_price( $cookie_data['fixed_price'] ),
					'amountWithVat' => number_format( $cookie_data['fixed_price'], 2, '.', '' ),
				);
			}
		} elseif ( isset( $label['service']['additionalServices']['cod']['fiscalReceiptItems'] ) ) {
			unset( $label['service']['additionalServices']['cod']['fiscalReceiptItems'] );
		}

		return $label;
	}

	/**
	 * Update cod.
	 *
	 * @param mixed $label Label.
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	public static function update_cod( $label, $order ) {
		$cookie_data = $order->get_meta( 'nashenski_speedy_cookie_data' );

		if ( isset( $label['service']['additionalServices']['cod']['amount'] ) ) {
			if ( isset( $label['service']['additionalServices']['cod']['amount'] ) &&
				'' !== Price_Lists::positive( $cookie_data['fixed_price'] ?? '' )
			) {
				$label['service']['additionalServices']['cod']['amount'] += number_format( $cookie_data['fixed_price'], 2, '.', '' );
				$label['service']['additionalServices']['cod']['amount']  = number_format( $label['service']['additionalServices']['cod']['amount'], 2, '.', '' );
			}
		}

		return $label;
	}

	/**
	 * Set min parcel weight.
	 *
	 * @param mixed $label Label.
	 * @param mixed $context Context.
	 * @return mixed Result.
	 * @throws Packing_Limit_Exceeded When a parcel weight is not finite.
	 */
	public static function set_min_parcel_weight( $label, $context = null ) {
		if ( empty( $label['content']['parcels'] ) || ! is_array( $label['content']['parcels'] ) ) {
			return $label;
		}

		$min_weight = (float) apply_filters( 'nashenski/speedy/min_parcel_weight', 0.100 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks extensions.
		$max_weight = self::get_max_parcel_weight( $label, $context );
		if ( ! is_finite( $min_weight ) || $min_weight <= 0 || ! is_finite( $max_weight ) ) {
			throw new Packing_Limit_Exceeded( 'Shipping parcel weights must be finite.' );
		}
		$max_parcels = $context instanceof \Nashenski\Shipping\Speedy\Method ? $context->get_max_parcels() : 0;
		Packing_Budget::check_parcels( count( $label['content']['parcels'] ), $max_parcels );
		$parcels = array();

		foreach ( $label['content']['parcels'] as $parcel ) {
			$weight = isset( $parcel['weight'] ) && is_numeric( $parcel['weight'] ) ? (float) $parcel['weight'] : $min_weight;
			if ( ! is_finite( $weight ) ) {
				throw new Packing_Limit_Exceeded( 'Shipping parcel weights must be finite.' );
			}
			$weight      = max( $min_weight, $weight );
			$split_count = $max_weight > 0 ? max( 1, ceil( $weight / $max_weight ) ) : 1;
			Packing_Budget::check_parcels( count( $parcels ) + $split_count, $max_parcels );

			if ( $max_weight <= 0 ) {
				$parcel['weight'] = round( $weight, 3 );
				$parcels[]        = $parcel;
				continue;
			}

			while ( $weight > $max_weight ) {
				Packing_Budget::check_parcels( count( $parcels ) + 1, $max_parcels );
				$parcel['weight'] = round( $max_weight, 3 );
				$parcels[]        = $parcel;
				$weight          -= $max_weight;
			}

			$parcel['weight'] = round( max( $min_weight, $weight ), 3 );
			Packing_Budget::check_parcels( count( $parcels ) + 1, $max_parcels );
			$parcels[] = $parcel;
		}

		foreach ( $parcels as $key => &$parcel ) {
			$parcel['seqNo'] = $key + 1;
		}

		unset( $parcel );

		$label['content']['parcels'] = $parcels;

		return $label;
	}

	/**
	 * Get max parcel weight.
	 *
	 * @param mixed $label Label.
	 * @param mixed $context Context.
	 * @return mixed Result.
	 */
	private static function get_max_parcel_weight( $label, $context = null ): float {
		if ( ! empty( $label['service']['additionalServices']['declaredValue'] ) ) {
			return (float) apply_filters( 'nashenski/speedy/max_parcel_weight', 32, self::get_delivery_type( $label, $context ), self::get_send_from_type( $context ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks extensions.
		}

		return \Nashenski\Shipping\Speedy\Method::get_max_parcel_weight(
			self::get_delivery_type( $label, $context ),
			self::get_send_from_type( $context )
		);
	}

	/**
	 * Get delivery type.
	 *
	 * @param mixed $label Label.
	 * @param mixed $context Context.
	 * @return mixed Result.
	 */
	private static function get_delivery_type( $label, $context = null ): string {
		if ( is_object( $context ) && ! empty( $context->delivery_type ) ) {
			return $context->delivery_type;
		}

		if ( ! empty( nashenski_label_request( 'type', array() )['id'] ?? '' ) ) {
			return ( nashenski_label_request( 'type', array() )['id'] ?? '' );
		}

		if ( ! empty( $label['recipient']['address'] ) || ! empty( $label['recipient']['addressLocation'] ) ) {
			return 'address';
		}

		return 'office';
	}

	/**
	 * Get send from type.
	 *
	 * @param mixed $context Context.
	 * @return mixed Result.
	 */
	private static function get_send_from_type( $context = null ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- the argument stays for the existing callers.
		if ( ! empty( nashenski_label_request( 'send_from_type' ) ) ) {
			return nashenski_label_request( 'send_from_type' );
		}

		return nashenski_get_option( 'speedy', 'send_from' );
	}

	/**
	 * Send label to speedy.
	 *
	 * @param mixed $label Label.
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	public static function send_label_to_speedy( $label, $order ) {
		$data           = array();
		$order_id       = $order->get_id();
		$action         = $order->get_meta( 'nashenski_speedy_shipment_status' ) ? 'updated' : 'created';
		$generated_data = self::generate_response( $label, $order );
		$response       = $generated_data['response'];
		$request_body   = $generated_data['request_body'];

		if ( isset( $response['error'] ) ) {
			if ( isset( $response['success'] ) && ! $response['success'] ) {
				$data['message'] = $response['error'];
			} else {
				$data['message'] = $response['error']['message'];
			}
		} else {
			$request_body['id']     = $response['id'];
			$data['shipmentStatus'] = $response;
			$data['label']          = $request_body;

			$order->update_meta_data( 'nashenski_speedy_label', $request_body );
			$order->update_meta_data( 'nashenski_speedy_shipment_status', $response );
			$order->save();
			nashenski_add_label_order_note( $order, 'Speedy', $action, $response['id'] ?? '' );

			self::update_order_shipping_price( $response, $order_id );
		}

		do_action( 'nashenski/speedy/after_send_label', $data, $order ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks extensions.

		return $data;
	}

	/**
	 * Update recipient data.
	 *
	 * @param mixed $label Label.
	 * @return mixed Result.
	 */
	protected static function update_recipient_data( $label ) {
		$order_id            = nashenski_label_request( 'orderId' );
		$order               = wc_get_order( $order_id );
		$type                = nashenski_label_request( 'type', array() );
		$cookie_data         = nashenski_label_request( 'cookie_data', array() );
		$cookie_data['type'] = $type['id'];
		foreach ( array( 'streetNumber', 'blockNumber', 'entranceNumber', 'floorNumber', 'apartmentNumber' ) as $field ) {
			$cookie_data[ $field ] = nashenski_label_request( $field );
		}

		$label = self::update_phone_and_names( $label );

		unset( $label['recipient']['addressLocation'] );
		unset( $label['recipient']['address'] );
		unset( $label['recipient']['pickupOfficeId'] );

		if ( 'address' === $cookie_data['type'] ) {
			$label['recipient']['addressLocation'] = self::generate_recipient_address( $order );
			$label['recipient']['address']         = self::generate_recipient_address( $order );
		} elseif ( in_array( $cookie_data['type'], array( 'office', 'automat' ), true ) ) {
			$office                               = nashenski_label_request( 'office', array() );
			$label['recipient']['pickupOfficeId'] = $office['id'];
		}

		$order->update_meta_data( 'nashenski_speedy_cookie_data', $cookie_data );
		$order->save();

		return $label;
	}

	/**
	 * Generate recipient address.
	 *
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	protected static function generate_recipient_address( $order ) {
		$container            = nashenski()->container();
		$cookie_data          = nashenski_label_request( 'cookie_data', array() );
		$country_id           = $container[ Client::SPEEDY_COUNTRIES ]->get_country_id( $order->get_billing_country() );
		$cities_data          = $container[ Client::SPEEDY_CITIES ]->get_filtered_cities( $cookie_data['city'], $cookie_data['state'], $country_id );
		$address['countryId'] = $country_id;
		// An unknown town stays empty (Speedy then says so) instead of taking the first town of the region.
		$address['siteId'] = false !== $cities_data['city_key'] ? $cities_data['cities'][ $cities_data['city_key'] ]['id'] : null;
		$street            = nashenski_label_request( 'street', array() );
		$other             = nashenski_label_request( 'other' );
		$details           = array(
			'streetNo'    => nashenski_label_request( 'streetNumber' ),
			'blockNo'     => nashenski_label_request( 'blockNumber' ),
			'entranceNo'  => nashenski_label_request( 'entranceNumber' ),
			'floorNo'     => nashenski_label_request( 'floorNumber' ),
			'apartmentNo' => nashenski_label_request( 'apartmentNumber' ),
		);

		if ( ! empty( $street['type'] ) && 'streets' === $street['type'] ) {
			$address['streetId'] = str_replace( 'street-', '', $street['orig_key'] );
			$address             = array_merge( $address, array_filter( $details ) );
		} elseif ( ( ! empty( $street['type'] ) && 'quarters' === $street['type'] ) ||
			! empty( $cookie_data['mysticQuarter'] )
		) {
			if ( ! empty( $cookie_data['mysticQuarter'] ) ) {
				if ( '300' === (string) $country_id ) {
					$street_number           = nashenski_label_request( 'streetNumber' );
					$address['addressLine1'] = $cookie_data['mysticQuarter'] . ' ' . $other . ' ' . $street_number;
					$address['postCode']     = $order->get_billing_postcode();
				}

				$address['addressNote'] = $cookie_data['mysticQuarter'];
			} else {
				$address['complexId'] = str_replace( 'qtr-', '', $street['orig_key'] );
				$address              = array_merge( $address, array_filter( $details ) );

				if ( empty( $details['blockNo'] ) && ! empty( $other ) ) {
					$parts = explode( ' ', $other );

					$address['blockNo'] = $parts[0];

					if ( isset( $parts[1] ) ) {
						$address['entranceNo'] = $parts[1];
					}

					if ( isset( $parts[2] ) ) {
						$address['floorNo'] = $parts[2];
					}

					if ( isset( $parts[3] ) ) {
						$address['apartmentNo'] = $parts[3];
					}

					if ( isset( $parts[4] ) ) {
						unset( $parts[0], $parts[1], $parts[2], $parts[3] );

						$address['addressNote'] = implode( ' ', $parts );
					}
				}
			}
		}

		return $address;
	}

	/**
	 * Update payment by.
	 *
	 * @param mixed $label Label.
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	protected static function update_payment_by( $label, $order ) {
		$payment_by = nashenski_label_request( 'paymentBy', array() );
		unset( $label['payment'] );
		$order_id = $order->get_id();

		$recipient_country = '';
		$bulgarian_id      = 'BG';

		if ( isset( $label['recipient']['addressLocation']['countryId'] ) ) {
			$recipient_country = $label['recipient']['addressLocation']['countryId'];
			$bulgarian_id      = '100';
		} else {
			$recipient_country = $label['recipient']['country'];
		}

		if ( isset( $label['service']['additionalServices']['cod']['amount'] ) && $label['service']['additionalServices']['cod']['amount'] && (string) $recipient_country === (string) $bulgarian_id ) {
			$payment['declaredValuePayer'] = 'RECIPIENT';
			$payment['packagePayer']       = 'RECIPIENT';
		} else {
			$payment['declaredValuePayer'] = 'SENDER';
			$payment['packagePayer']       = 'SENDER';
		}

		if ( 'fixed' === $payment_by['id'] ) {
			$payment['courierServicePayer'] = 'SENDER';

			self::update_order_shipping_price( $label, $order_id, $label );
		} else {
			$payment['courierServicePayer'] = $payment_by['id'];
		}

		if ( 'SENDER' === $payment['courierServicePayer'] ) {
			$payment['declaredValuePayer'] = 'SENDER';
			$payment['packagePayer']       = 'SENDER';
		}

		if ( nashenski_get_option( 'speedy', 'administrative_fee' ) === 'yes' ) {
			$payment['administrativeFee'] = true;
		}

		$label['payment'] = $payment;

		return $label;
	}

	/**
	 * Update services.
	 *
	 * @param mixed $label Label.
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	protected static function update_services( $label, $order ) {
		$payment_by     = nashenski_label_request( 'paymentBy', array() );
		$cookie_data    = nashenski_label_request( 'cookie_data', array() );
		$country        = ( $order->get_shipping_country() ) ? $order->get_shipping_country() : $order->get_billing_country();
		$declared_value = nashenski_label_request( 'declaredValue' );
		$test_option    = ( nashenski_label_request( 'testOption', array() )['id'] ?? '' );
		$service_id     = '505';

		if ( 'BG' !== $country ) {
			$service_id = '202';
		}

		if ( isset( $label['service']['additionalServices']['declaredValue'] ) ) {
			unset( $label['service']['additionalServices']['declaredValue'] );
		}

		if ( isset( $label['service']['additionalServices']['obpd'] ) ) {
			unset( $label['service']['additionalServices']['obpd'] );
		}

		if ( isset( $label['service']['additionalServices']['cod']['amount'] ) ) {
			if ( isset( $label['service']['additionalServices']['cod']['amount'] ) &&
				'' !== Price_Lists::positive( $cookie_data['fixed_price'] ?? '' ) &&
				'fixed' === $payment_by['id']
			) {
				$label['service']['additionalServices']['cod']['amount'] += number_format( $cookie_data['fixed_price'], 2, '.', '' );
				$label['service']['additionalServices']['cod']['amount']  = number_format( $label['service']['additionalServices']['cod']['amount'], 2, '.', '' );
			}
		}

		if ( $declared_value ) {
			$label['service']['additionalServices']['declaredValue'] = array(
				'amount'                => $declared_value,
				'fragile'               => true,
				'ignoreIfNotApplicable' => true,
			);
		}

		$test = '';

		if ( 'review' === $test_option ) {
			$test = 'OPEN';
		} elseif ( 'test' === $test_option ) {
			$test = 'TEST';
		}

		if ( $test ) {
			$label['service']['additionalServices']['obpd'] = array(
				'option'                  => $test,
				'returnShipmentServiceId' => $service_id,
				'returnShipmentPayer'     => 'SENDER',
			);
		}

		if ( empty( $label['service']['additionalServices'] ) ) {
			unset( $label['service']['additionalServices'] );
		}

		return $label;
	}

	/**
	 * Update phone and names.
	 *
	 * @param mixed $label Label.
	 * @return mixed Result.
	 */
	protected static function update_phone_and_names( $label ) {
		$order = wc_get_order( nashenski_label_request( 'orderId' ) );
		$phone = array();
		$name  = '';

		if ( $order->get_shipping_first_name() && $order->get_shipping_last_name() ) {
			$name = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
		} else {
			$name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		}

		if ( $order->get_shipping_phone() ) {
			$phone = array( 'number' => nashenski_format_phone( $order->get_shipping_phone(), $order->get_shipping_country() ) );
		} else {
			$phone = array( 'number' => nashenski_format_phone( $order->get_billing_phone(), $order->get_billing_country() ) );
		}

		unset( $label['recipient']['clientName'] );
		unset( $label['recipient']['contactName'] );

		$label['recipient']['privatePerson'] = true;
		$label['recipient']['clientName']    = $name;
		$label['recipient']['phone1']        = $phone;
		$label['recipient']['email']         = $order->get_billing_email();

		if ( $order->get_meta( '_billing_to_company' ) ) {
			$label['recipient']['privatePerson'] = false;
			$label['recipient']['contactName']   = $label['recipient']['clientName'];
			$label['recipient']['clientName']    = $order->get_billing_company();
		}

		return $label;
	}

	/**
	 * Update order shipping price.
	 *
	 * @param mixed $response Response.
	 * @param mixed $order_id Order id.
	 * @param mixed $request_body Request body.
	 */
	protected static function update_order_shipping_price( $response, $order_id, $request_body = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the argument stays for the existing callers.
		if ( ( null === nashenski_label_request( 'paymentBy', null ) ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		$price = 0;

		if ( $order->get_payment_method() !== 'cod' ) {
			return;
		}

		$payment_by = nashenski_label_request( 'paymentBy', array() );

		if ( ! empty( $payment_by ) ) {
			$cookie_data = nashenski_label_request( 'cookie_data', array() );

			if ( 'RECIPIENT' === $payment_by['id'] ) {
				$price = ( wc_tax_enabled() ) ? $response['price']['amount'] : $response['price']['total'];
			} elseif ( 'fixed' === $payment_by['id'] && '' !== Price_Lists::positive( $cookie_data['fixed_price'] ?? '' ) ) {
				$tax_rate = apply_filters( 'nashenski/speedy/fixed_price_tax_rate', 20, $order->get_shipping_country() ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks extensions.
				$price    = nashenski_tax_based_price( $cookie_data['fixed_price'], $tax_rate );
			}
		}

		foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
			$item->set_total( $price );
			$item->calculate_taxes();
			$item->save();
		}

		$order->calculate_totals();
		$order->save();
	}

	/**
	 * Update customer note.
	 *
	 * @param mixed $label Label.
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	protected static function update_customer_note( $label, $order ) {
		if ( $order->get_customer_note() ) {
			$label['shipmentNote'] = mb_substr( $order->get_customer_note(), 0, 200 );
		} else {
			unset( $label['shipmentNote'] );
		}

		return $label;
	}

	/**
	 * Generate response.
	 *
	 * @param mixed $label Label.
	 * @param mixed $order Order.
	 * @return mixed Result.
	 */
	protected static function generate_response( $label, $order ) {
		$container       = nashenski()->container();
		$shipment_status = $order->get_meta( 'nashenski_speedy_shipment_status' );

		if ( $shipment_status ) {
			if ( ! $label['id'] ) {
				$label['id'] = $shipment_status['id'];
			}

			$request_body = apply_filters( 'nashenski/speedy/update_label', $label, $order ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks extensions.

			$response = $container[ Client::SPEEDY ]->label_request( 'update', $request_body );
		} else {
			unset( $label['id'] );

			$request_body = apply_filters( 'nashenski/speedy/create_label', $label, $order ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- public hook name, renaming it breaks extensions.
			$response     = $container[ Client::SPEEDY ]->label_request( 'create', $request_body );
		}

		return array(
			'response'     => $response,
			'request_body' => $request_body,
		);
	}

	/**
	 * Print labels endpoint.
	 */
	public static function print_labels_endpoint() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ), '', array( 'response' => 403 ) );
		}

		// The print links carry the label nonce of the order screen.
		if ( null === nashenski_label_request( 'nonce', null ) ) {
			wp_die( esc_html__( 'This link has expired. Reload the order page and try again.', 'nashenski-commerce' ), '', array( 'response' => 403 ) );
		}

		$container = nashenski()->container();
		$labels    = explode( '|', nashenski_label_request( 'parcels' ) );
		$size      = nashenski_label_request( 'size' );
		$parcels   = array();
		$awbsc     = ( null !== nashenski_label_request( 'awbsc', null ) ) ? nashenski_label_request( 'awbsc' ) : '';

		foreach ( $labels as $label ) {
			if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $label ) ) {
				continue;
			}

			$parcels[] = array(
				'parcel' => array(
					'id' => $label,
				),
			);
		}

		if ( ! $parcels ) {
			wp_die( esc_html__( 'Invalid parcel number.', 'nashenski-commerce' ), '', array( 'response' => 400 ) );
		}

		$request_body = array(
			'paperSize' => $size,
			'parcels'   => $parcels,
		);

		if ( ! empty( $awbsc ) ) {
			$request_body['additionalWaybillSenderCopy'] = $awbsc;
		}

		$pdf_escaped = $container[ Client::SPEEDY ]->api_call( $container[ Client::SPEEDY ]::PRINT_LABELS_ENDPOINT, $request_body, 1 );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Length: ' . strlen( $pdf_escaped ) );
		header( 'Content-disposition: inline; filename="' . $parcels[0]['parcel']['id'] . '.pdf"' );

		echo $pdf_escaped; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PDF file from the courier, sent as application/pdf.

		wp_die();
	}
}
