<?php
/**
 * BOX NOW order meta box and labels.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

use Nashenski\Container\Client;
use Nashenski\Shipping\BoxNow\Method;
use Nashenski\Shipping\Packer\Packing_Limit_Exceeded;

defined( 'ABSPATH' ) || exit;

/**
 * BOX NOW meta box on the order screen: creates, prints and deletes labels.
 */
class BoxNow {
	/** Most units of one order that are packed into boxes automatically. */
	const MAX_AUTO_PACK_UNITS = 200;

	/**
	 * Plugin container.
	 *
	 * @var mixed
	 */
	private static $container = null;

	/**
	 * Registers the hooks.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );

		add_action( 'wp_ajax_nashenski_boxnow_generate_label', array( __CLASS__, 'generate_label_ajax' ) );
		add_action( 'wp_ajax_nashenski_boxnow_print_label', array( __CLASS__, 'print_label_endpoint' ) );
		add_action( 'wp_ajax_nashenski_boxnow_delete_label', array( __CLASS__, 'delete_label' ) );
	}

	/**
	 * Adds the BOX NOW meta box.
	 */
	public static function add_meta_boxes() {
		nashenski_add_courier_meta_box( 'boxnow', __( 'BOX NOW Delivery', 'nashenski-commerce' ), array( __CLASS__, 'meta_box' ) );
	}

	/**
	 * Prints the meta box and passes the label data to the script.
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
				if ( 'nashenski_boxnow' === $shipping['method_id'] ) {
					echo '<div id="nashenski--boxnow-admin"></div>';

					$label = self::generate_label_data( $theorder->get_id() );

					if ( empty( $label['items'] ) && ! $theorder->get_meta( 'nashenski_boxnow_shipment_status' ) ) {
						echo '<div class="notice notice-warning inline"><p>' . esc_html( self::too_many_units_message() ) . '</p></div>';
					}

					$cookie_data     = $theorder->get_meta( 'nashenski_boxnow_cookie_data' );
					$sender_data     = self::generate_sender_data();
					$shipment_status = $theorder->get_meta( 'nashenski_boxnow_shipment_status' );
					$box_size        = ( nashenski_get_option( 'boxnow_price', 'box_size' ) ) ? nashenski_get_option( 'boxnow_price', 'box_size' ) : 'auto';
					$sizes           = array(
						'small'  => 1,
						'medium' => 2,
						'large'  => 3,
					);

					if ( ! $cookie_data ) {
						foreach ( $shipping->get_meta_data() as $meta_data ) {
							$data = $meta_data->get_data();

							if ( 'cookie_data' === $data['key'] ) {
								$cookie_data = $data['value'];
							}
						}
					}

					wp_localize_script(
						'nashenski-js-admin',
						'nashenski_boxnow',
						array(
							'label'           => $label,
							'othersLabels'    => $theorder->get_meta( 'nashenski_boxnow_other_labels' ),
							'shipmentStatus'  => $shipment_status,
							'cookie_data'     => $cookie_data,
							'paymentType'     => $theorder->get_payment_method(),
							'destinations'    => self::get_destinations(),
							'origin'          => $sender_data['locationId'],
							'origins'         => self::get_origins(),
							'orderId'         => $theorder->get_id(),
							'allowReturn'     => wc_string_to_bool( nashenski_get_option( 'boxnow_send_from', 'allow_return' ) ),
							'compartmentSize' => ( isset( $sizes[ $box_size ] ) ) ? $sizes[ $box_size ] : 2,
							'i18n'            => self::get_i18n(),
							'nonce'           => wp_create_nonce( 'nashenski_admin_label' ),
						)
					);
					break;
				}
			}
		}
	}

	/**
	 * Texts for the meta box script.
	 *
	 * @return array
	 */
	protected static function get_i18n() {
		return array(
			'sendTo'               => __( 'Send to', 'nashenski-commerce' ),
			'sendFrom'             => __( 'Send From', 'nashenski-commerce' ),
			'warehouseApm'         => __( 'Warehouse/Automat', 'nashenski-commerce' ),
			'apm'                  => __( 'Automat', 'nashenski-commerce' ),
			'total'                => __( 'Total price', 'nashenski-commerce' ),
			'allowReturn'          => __( 'Allow returns', 'nashenski-commerce' ),
			'updateShipmentStatus' => __( 'Update shipment status', 'nashenski-commerce' ),
			'generateLabel'        => __( 'Generate label', 'nashenski-commerce' ),
			'deleteLabel'          => __( 'Delete label', 'nashenski-commerce' ),
			'label'                => __( 'Label', 'nashenski-commerce' ),
			'selected'             => __( 'Selected', 'nashenski-commerce' ),
			'choose'               => __( 'Choose', 'nashenski-commerce' ),
			'labelData'            => __( 'Label data', 'nashenski-commerce' ),
			'shipmentStatus'       => __( 'Shipment status', 'nashenski-commerce' ),
			'boxSize'              => __( 'Box size', 'nashenski-commerce' ),
			'auto'                 => __( 'Automatically pack products to boxes', 'nashenski-commerce' ),
			'smallBox'             => __( 'Small Box', 'nashenski-commerce' ),
			'mediumBox'            => __( 'Medium Box', 'nashenski-commerce' ),
			'largeBox'             => __( 'Large Box', 'nashenski-commerce' ),
			'pack'                 => __( 'Pack', 'nashenski-commerce' ),
			'addPack'              => __( 'Add Pack', 'nashenski-commerce' ),
			'removePack'           => __( 'Remove Pack', 'nashenski-commerce' ),
			'weight'               => __( 'Weight', 'nashenski-commerce' ),
			'price'                => __( 'Price', 'nashenski-commerce' ),
			'name'                 => __( 'Name', 'nashenski-commerce' ),
		);
	}

	/**
	 * Builds the label data of an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public static function generate_label_data( $order_id ) {
		$order       = wc_get_order( $order_id );
		$cookie_data = $order->get_meta( 'nashenski_boxnow_cookie_data' );

		if ( ! $cookie_data ) {
			foreach ( $order->get_items( 'shipping' ) as $shipping ) {
				if ( 'nashenski_boxnow' === $shipping['method_id'] ) {
					foreach ( $shipping->get_meta_data() as $meta_data ) {
						$data = $meta_data->get_data();

						if ( 'cookie_data' === $data['key'] ) {
							$cookie_data = $data['value'];
						}
					}
				}
			}
		}

		$label_data = array(
			'orderNumber'         => strval( wp_rand() . '-' . $order->get_id() ),
			'origin'              => self::generate_sender_data(),
			'destination'         => self::generate_receiver_data( $order, $cookie_data ),
			'allowReturn'         => wc_string_to_bool( nashenski_get_option( 'boxnow_send_from', 'allow_return' ) ),
			'invoiceValue'        => '0.00',
			'paymentMode'         => 'prepaid',
			'amountToBeCollected' => '0.00',
		);

		if ( $order->get_payment_method() === 'cod' ) {
			$label_data['paymentMode']         = $order->get_payment_method();
			$label_data['invoiceValue']        = $order->get_total();
			$label_data['amountToBeCollected'] = $order->get_total();
		}

		$cached_items = $order->get_meta( 'nashenski--box-now-items' );

		if ( $cached_items ) {
			$label_data['items'] = $cached_items;
		} else {
			$label_data['items'] = self::generate_items( $order );
		}

		return $label_data;
	}

	/**
	 * Sender data from the settings.
	 *
	 * @return array
	 */
	public static function generate_sender_data() {
		return array(
			'contactName'   => nashenski_get_option( 'boxnow_send_from', 'name' ),
			'contactNumber' => self::format_phone( nashenski_format_phone( nashenski_get_option( 'boxnow_send_from', 'phone' ) ) ),
			'contactEmail'  => nashenski_get_option( 'boxnow_send_from', 'email' ),
			'locationId'    => str_replace( array( 'originID-', 'destionationID-' ), '', nashenski_get_option( 'boxnow_send_from', 'location' ) ),
		);
	}

	/**
	 * Receiver data from the order.
	 *
	 * @param \WC_Order $order       Order.
	 * @param array     $cookie_data Checkout choice of the customer.
	 * @return array
	 */
	public static function generate_receiver_data( $order, $cookie_data ) {
		if ( $order->get_shipping_phone() ) {
			$phone = $order->get_shipping_phone();
		} else {
			$phone = $order->get_billing_phone();
		}

		if ( $order->get_shipping_first_name() && $order->get_shipping_last_name() ) {
			$name = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
		} else {
			$name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
		}

		return array(
			'contactNumber' => self::format_phone( nashenski_format_phone( $phone ) ),
			'contactEmail'  => $order->get_billing_email(),
			'contactName'   => $name,
			'locationId'    => ( ! empty( $cookie_data['selectedApm'] ) ) ? $cookie_data['selectedApm'] : '',
		);
	}

	/**
	 * Turns a local phone number into the +359 form.
	 *
	 * @param string $phone Phone number.
	 * @return string
	 */
	public static function format_phone( $phone ) {
		$phone = substr( $phone, 1 );
		$phone = '+359' . $phone;

		return $phone;
	}

	/**
	 * Box size from the settings.
	 *
	 * @return string
	 */
	public static function get_box_size() {
		$box_size = ( nashenski_get_option( 'boxnow_price', 'box_size' ) ) ? nashenski_get_option( 'boxnow_price', 'box_size' ) : 'auto';

		return $box_size;
	}

	/**
	 * Notice for orders too big to pack automatically.
	 *
	 * @return string
	 */
	public static function too_many_units_message() {
		/* translators: %d: unit limit */
		return sprintf( __( 'This order has more than %d units, so it is not packed into BOX NOW boxes automatically. Add the boxes by hand.', 'nashenski-commerce' ), self::MAX_AUTO_PACK_UNITS );
	}

	/**
	 * Packs the order items into boxes.
	 *
	 * @param \WC_Order $order Order.
	 * @return array
	 */
	public static function generate_items( $order ) {
		$box_size = self::get_box_size();

		if ( 'auto' === $box_size ) {
			try {
				$items = self::auto_generate_items_to_boxes( $order );
			} catch ( Packing_Limit_Exceeded $e ) {
				$items = array();
			}
		} else {
			$items = self::generate_all_items_to_one_box( $order, $box_size );
		}

		return $items;
	}

	/**
	 * Packs the order items into as many boxes as their size needs.
	 *
	 * @param \WC_Order $order Order.
	 * @return array
	 * @throws Packing_Limit_Exceeded When the order has more than MAX_AUTO_PACK_UNITS units.
	 */
	public static function auto_generate_items_to_boxes( $order ) {
		$default_box_values = array(
			'name'            => '',
			'weight'          => 0,
			'value'           => '0.00',
			'compartmentSize' => 0,
		);

		$current_volume = 0;
		$order_items    = array();

		$items     = array( $default_box_values );
		$box_count = 0;

		$units = 0;

		foreach ( $order->get_items() as $order_item ) {
			$units += max( 0, ceil( (float) $order_item['quantity'] ) );
		}

		if ( $units > self::MAX_AUTO_PACK_UNITS ) {
			throw new Packing_Limit_Exceeded( 'Order has too many units to pack automatically.' );
		}

		foreach ( $order->get_items() as $order_item ) {
			for ( $i = 0; $i < $order_item['quantity']; $i++ ) {
				$order_items[] = $order_item;
			}
		}

		foreach ( $order_items as $key => $order_item ) {
			$_product = $order_item->get_product();

			$unit   = get_option( 'woocommerce_dimension_unit' );
			$height = $_product->get_height() ? wc_get_dimension( (float) $_product->get_height(), 'cm', $unit ) : 7;
			$width  = $_product->get_width() ? wc_get_dimension( (float) $_product->get_width(), 'cm', $unit ) : 44;
			$length = $_product->get_length() ? wc_get_dimension( (float) $_product->get_length(), 'cm', $unit ) : 58;

			$item_sizes      = Method::determine_item_size( $height, $width, $length );
			$current_volume += $item_sizes['volume'];

			if ( $item_sizes['oversize'] || $item_sizes['volume'] > 89320 || $current_volume > 89320 ) {
				$current_volume = $item_sizes['volume'];
				++$box_count;
				$items[ $box_count ] = array(
					'name'            => '',
					'weight'          => 0,
					'value'           => '0.00',
					'compartmentSize' => 0,
				);
			}

			$weight                         = ( $_product->get_weight() ) ? wc_get_weight( $_product->get_weight(), 'kg' ) : 1;
			$items[ $box_count ]['weight'] += (float) $weight;
			$items[ $box_count ]['name']   .= nashenski_normalize_text_for_label( $order_item->get_name() . ';' );

			if ( $item_sizes['size'] ) {
				$items[ $box_count ]['compartmentSize'] = Method::determine_item_size_by_volume( $current_volume );
			} else {
				$items[ $box_count ]['compartmentSize'] = '1';
			}
		}

		return $items;
	}

	/**
	 * Puts all order items into one box.
	 *
	 * @param \WC_Order $order    Order.
	 * @param string    $box_size small, medium or large.
	 * @return array
	 */
	public static function generate_all_items_to_one_box( $order, $box_size ) {
		$sizes = array(
			'small'  => 1,
			'medium' => 2,
			'large'  => 3,
		);

		$items = array(
			array(
				'name'            => '',
				'weight'          => 0,
				'value'           => '0.00',
				'compartmentSize' => $sizes[ $box_size ],
			),
		);

		foreach ( $order->get_items() as $order_item ) {
			$_product            = $order_item->get_product();
			$weight              = ( $_product->get_weight() ) ? wc_get_weight( $_product->get_weight(), 'kg' ) : 1;
			$items[0]['weight'] += (float) $weight * $order_item['quantity'];
			$items[0]['name']   .= nashenski_normalize_text_for_label( $order_item->get_name() . ';' );
		}

		return $items;
	}

	/**
	 * BOX NOW destinations.
	 *
	 * @return array
	 */
	public static function get_destinations() {
		$container = nashenski()->container();

		return $container[ Client::BOXNOW_DESTINATIONS ]->get_destinations();
	}

	/**
	 * BOX NOW origins.
	 *
	 * @return array
	 */
	public static function get_origins() {
		$container = nashenski()->container();

		// None while the BOX NOW login does not work.
		return array_values( (array) $container[ Client::BOXNOW_ORIGINS ]->get_origins() );
	}

	/**
	 * Creates the label of an order.
	 *
	 * @param int|string $order_id Order ID.
	 * @return array|null
	 */
	public static function generate_label( $order_id = '' ) {
		return self::generate_label_for_order( $order_id );
	}

	/**
	 * Creates the label once the order is placed.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public static function generate_label_after_order_generated( $order_id ) {
		return self::generate_label( $order_id );
	}

	/**
	 * Creates the label of an order, optionally with the meta box form values.
	 *
	 * @param int|string $order_id         Order ID.
	 * @param bool       $use_request_data Whether to read the form values.
	 * @return array|null
	 */
	private static function generate_label_for_order( $order_id = '', $use_request_data = false ) {
		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order ) {
			return;
		}

		$label_data = self::generate_label_data( $order->get_id() );

		if (
			$use_request_data &&
			( null !== nashenski_label_request( 'origin', null ) ) &&
			is_array( nashenski_label_request( 'origin', null ) ) &&
			( null !== ( nashenski_label_request( 'origin', array() )['id'] ?? null ) )
		) {
			$label_data['origin']['locationId'] = ( nashenski_label_request( 'origin', array() )['id'] ?? '' );
		}

		if (
			$use_request_data &&
			( null !== nashenski_label_request( 'destination', null ) ) &&
			is_array( nashenski_label_request( 'destination', null ) ) &&
			( null !== ( nashenski_label_request( 'destination', array() )['id'] ?? null ) )
		) {
			$label_data['destination']['locationId'] = ( nashenski_label_request( 'destination', array() )['id'] ?? '' );
		}

		if ( $use_request_data && ( null !== nashenski_label_request( 'items', null ) ) ) {
			// Form fields arrive as text; BOX NOW checks that these two are numbers.
			$label_data['items'] = array_values(
				array_map(
					function ( $item ) {
						$item                    = (array) $item;
						$item['weight']          = (float) ( $item['weight'] ?? 0 );
						$item['compartmentSize'] = (int) ( $item['compartmentSize'] ?? 0 );

						return $item;
					},
					nashenski_label_request( 'items', array() )
				)
			);
			$order->update_meta_data( 'nashenski--box-now-items', $label_data['items'] );
		}

		if ( $use_request_data && ( null !== nashenski_label_request( 'declaredValue', null ) ) ) {
			if ( nashenski_label_request( 'declaredValue' ) > 0 ) {
				$total = number_format( nashenski_label_request( 'declaredValue' ), 2, '.', '' );

				$label_data['paymentMode']         = 'cod';
				$label_data['invoiceValue']        = $total;
				$label_data['amountToBeCollected'] = $total;
			} else {
				$label_data['paymentMode']         = 'prepaid';
				$label_data['invoiceValue']        = '0.00';
				$label_data['amountToBeCollected'] = '0.00';
			}
		}

		if ( $use_request_data && ( null !== nashenski_label_request( 'allowReturn', null ) ) ) {
			$label_data['allowReturn'] = wc_string_to_bool( nashenski_label_request( 'allowReturn' ) );
		}

		if ( empty( $label_data['items'] ) ) {
			return array( 'message' => self::too_many_units_message() );
		}

		return self::send_label_to_boxnow( $label_data, $order );
	}

	/**
	 * AJAX: creates the label from the meta box.
	 *
	 * @param int|string $order_id Order ID when no request value is sent.
	 */
	public static function generate_label_ajax( $order_id = '' ) {
		nashenski_check_admin_label_actions();

		$order_id = ( ( null !== nashenski_label_request( 'orderId', null ) ) ) ? absint( nashenski_label_request( 'orderId' ) ) : $order_id;

		if ( ! $order_id ) {
			return;
		}

		wp_send_json_success( self::generate_label_for_order( $order_id, true ) );
		wp_die();
	}

	/**
	 * Sends the label to BOX NOW, one parcel per box.
	 *
	 * @param array     $label Label data.
	 * @param \WC_Order $order Order.
	 * @return array
	 */
	public static function send_label_to_boxnow( $label, $order ) {
		$data         = array();
		$request_body = \Nashenski\Hooks::filter( 'nashenski_boxnow_create_label', 'nashenski/boxnow/create_label', $label, $order );
		$orig_items   = $request_body['items'];
		$other_labels = array();

		if ( count( $request_body['items'] ) > 1 ) {
			$first_item            = array_shift( $request_body['items'] );
			$other_labels_items    = $request_body['items'];
			$request_body['items'] = array( $first_item );
			$other_labels          = array();

			foreach ( $other_labels_items as $key => $item ) {
				$new_label                 = $request_body;
				$new_label['orderNumber'] .= '-' . ( $key + 2 );
				$new_label['items']        = array( $item );
				$other_labels[]            = $new_label;
			}
		}

		if ( 'cod' === $order->get_payment_method() && ! empty( $other_labels ) ) {
			return array(
				'message' => __( 'Cash on delivery orders cannot be split into multiple packets.', 'nashenski-commerce' ),
			);
		}

		$request  = self::send_label_request( $request_body );
		$response = json_decode( wp_remote_retrieve_body( $request ), 1 );

		if ( isset( $response['message'] ) ) {
			$data['message'] = $response['message'];
		} else {

			if ( ! empty( $other_labels ) ) {
				$data['otherLabels'] = self::send_other_labels( $other_labels, $order );
			}

			$request_body['items'] = $orig_items;

			$data['label']          = $request_body;
			$data['shipmentStatus'] = $response;

			$order->update_meta_data( 'nashenski_boxnow_label', $request_body );
			$order->update_meta_data( 'nashenski_boxnow_shipment_status', $response );
			$order->save();
			nashenski_add_label_order_note( $order, 'BOX NOW', 'created', wp_list_pluck( $response['parcels'] ?? array(), 'id' ) );
		}

		\Nashenski\Hooks::action( 'nashenski_boxnow_after_send_label', 'nashenski/boxnow/after_send_label', $data, $order );

		return $data;
	}

	/**
	 * Sends the labels of the second and later boxes.
	 *
	 * @param array     $other_labels Label data, one per box.
	 * @param \WC_Order $order        Order.
	 * @return array
	 */
	public static function send_other_labels( $other_labels, $order ) {
		$order->update_meta_data( 'nashenski_boxnow_other_labels', $other_labels );
		$other_labels_data = array();

		foreach ( $other_labels as $label ) {
			$request  = self::send_label_request( $label );
			$response = json_decode( wp_remote_retrieve_body( $request ), 1 );

			if ( isset( $response['message'] ) ) {
				continue;
			}

			$other_labels_data[] = array(
				'label'          => $label,
				'shipmentStatus' => $response,
			);
		}

		$order->update_meta_data( 'nashenski_boxnow_other_labels', $other_labels_data );
		$order->save();

		return $other_labels_data;
	}

	/**
	 * Sends one label request to BOX NOW.
	 *
	 * @param array $label Label data.
	 * @return array Response shaped like an HTTP response, with a body.
	 */
	public static function send_label_request( $label ) {
		// Direct call to BOX NOW. Callers read the result like an HTTP response body.
		$client   = nashenski()->container()[ Client::BOXNOW ];
		$response = $client->api_call( $client::CREATE_LABELS_ENDPOINT, $label );

		if ( ! is_array( $response ) ) {
			$response = array(
				'message' => __( 'BOX NOW did not answer. Check the connection settings and try again.', 'nashenski-commerce' ),
			);
		}

		return array( 'body' => wp_json_encode( $response ) );
	}

	/**
	 * AJAX: cancels the parcels of an order.
	 */
	public static function delete_label() {
		nashenski_check_admin_label_actions();

		$container       = nashenski()->container();
		$order_id        = nashenski_label_request( 'orderId' );
		$shipment_status = nashenski_label_request( 'shipmentStatus', array() );
		$order           = wc_get_order( $order_id );
		$parcel_numbers  = wp_list_pluck( $shipment_status['parcels'] ?? array(), 'id' );

		foreach ( $shipment_status['parcels'] as $parcel ) {
			if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', (string) $parcel['id'] ) ) {
				continue;
			}

			$response = $container[ Client::BOXNOW ]->api_call( '/api/v1/parcels/' . $parcel['id'] . ':cancel', array() );
		}

		$order->update_meta_data( 'nashenski_boxnow_shipment_status', '' );
		$order->update_meta_data( 'nashenski_boxnow_operations', '' );

		$other_labels = $order->get_meta( 'nashenski_boxnow_other_labels' );

		if ( $other_labels ) {
			foreach ( $other_labels as $other_label ) {
				$parcel_numbers = array_merge( $parcel_numbers, wp_list_pluck( $other_label['shipmentStatus']['parcels'] ?? array(), 'id' ) );
				foreach ( $other_label['shipmentStatus']['parcels'] as $parcel ) {
					if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', (string) $parcel['id'] ) ) {
						continue;
					}

					$response = $container[ Client::BOXNOW ]->api_call( '/api/v1/parcels/' . $parcel['id'] . ':cancel', array() );
				}
			}

			$order->update_meta_data( 'nashenski_boxnow_other_labels', '' );
		}

		$order->save();
		nashenski_add_label_order_note( $order, 'BOX NOW', 'deleted', $parcel_numbers );

		wp_send_json_success( $response );
		wp_die();
	}

	/**
	 * AJAX: streams the label PDF of a parcel.
	 */
	public static function print_label_endpoint() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ), '', array( 'response' => 403 ) );
		}

		// The print links carry the label nonce of the order screen.
		if ( null === nashenski_label_request( 'nonce', null ) ) {
			wp_die( esc_html__( 'This link has expired. Reload the order page and try again.', 'nashenski-commerce' ), '', array( 'response' => 403 ) );
		}

		$container = nashenski()->container();
		$parcel    = nashenski_label_request( 'parcel' );

		if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', (string) $parcel ) ) {
			wp_die( esc_html__( 'Invalid parcel number.', 'nashenski-commerce' ), '', array( 'response' => 400 ) );
		}

		$url = '/api/v1/parcels/' . $parcel . '/label.pdf';

		$pdf_escaped = $container[ Client::BOXNOW ]->api_call( $url, array(), 'GET', true );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Length: ' . strlen( $pdf_escaped ) );
		header( 'Content-disposition: inline; filename="' . $parcel . '.pdf"' );

		echo $pdf_escaped; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- PDF file from the courier, sent as application/pdf.

		wp_die();
	}

	/**
	 * AJAX: hides the BOX NOW notice.
	 */
	public static function message_dismiss_callback() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- WooCommerce capability.
			wp_die( -1, 403 );
		}

		check_ajax_referer( 'nashenski_dismiss_notice', 'nonce' );

		update_option( 'nashenski_boxnow_message_dismiss', true );
	}
}
