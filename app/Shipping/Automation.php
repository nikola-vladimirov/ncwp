<?php
/**
 * Automation.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping;

use Nashenski\Container\Client;
use Nashenski\Front_End\Checkout\Pickers;
use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Waybill work for every courier at once:
 * - the order number on the waybill;
 * - a tracking link on the customer's order page;
 * - order statuses that follow the shipment (delivered, or going back to you), checked every hour;
 * - "Create waybill", "Request a courier" and "Check the shipment now" for one order or many.
 */
class Automation {
	const GROUP    = 'automation';
	const CHECK    = 'nashenski/shipments/check';
	const AS_GROUP = 'nashenski';

	/** Set once the shipment was delivered or went back, so the hourly check leaves the order alone. */
	const STATE_META = '_nashenski_shipment_state';

	/** Creation time (timestamp) of the last order of the previous hourly batch; the next batch starts there. */
	const CURSOR_OPTION = 'nashenski_shipments_cursor';

	/** Public tracking pages; the waybill number goes at the end. */
	const TRACKING = array(
		'econt'  => 'https://www.econt.com/services/track-shipment/',
		'speedy' => 'https://www.speedy.bg/bg/track-shipment?shipmentNumber=',
		'boxnow' => 'https://boxnow.bg/en?track=',
		'pigeon' => 'https://track.pigeonexpress.com/?tracking_number=',
	);

	/** Couriers whose shipment status can be read. Pigeon Express does not publish its status values. */
	const FOLLOWED = array( 'econt', 'speedy', 'boxnow' );

	/** Couriers whose API takes a courier request. The others collect with the waybill itself. */
	const PICKUP = array( 'econt', 'speedy' );

	/** Order action ids; the translated labels come from actions(), which must not run before init. */
	const ACTION_IDS = array( 'nashenski_create_waybill', 'nashenski_request_courier', 'nashenski_check_shipment' );

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( 'yes' === nashenski_get_option( self::GROUP, 'order_number' ) ) {
			foreach ( array_keys( Register::COURIERS ) as $courier ) {
				foreach ( array( 'create_label', 'update_label' ) as $hook ) {
					add_filter(
						"nashenski/{$courier}/{$hook}",
						function ( $label, $order ) use ( $courier ) {
							return self::add_order_number( $courier, $label, $order );
						},
						20,
						2
					);
				}
			}
		}

		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'tracking_on_order_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
		add_action( self::CHECK, array( __CLASS__, 'check_shipments' ) );

		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'order_actions' ), 10, 2 );

		foreach ( self::ACTION_IDS as $action ) {
			add_action(
				'woocommerce_order_action_' . $action,
				function ( $order ) use ( $action ) {
					self::notice( self::run( $action, array( $order ) ) );
				}
			);
		}

		$screen = OrderUtil::custom_orders_table_usage_is_enabled() ? 'woocommerce_page_wc-orders' : 'edit-shop_order';
		add_filter( 'bulk_actions-' . $screen, array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-' . $screen, array( __CLASS__, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( __CLASS__, 'show_notice' ) );
	}

	/**
	 * Actions.
	 *
	 * @return array Result.
	 */
	private static function actions() {
		return array(
			'nashenski_create_waybill'  => __( 'Create waybill', 'nashenski-commerce' ),
			'nashenski_request_courier' => __( 'Request a courier (Econt, Speedy)', 'nashenski-commerce' ),
			'nashenski_check_shipment'  => __( 'Check the shipment now', 'nashenski-commerce' ),
		);
	}

	/**
	 * Settings: Overview > Waybills and order statuses.
	 */
	public static function settings() {
		$statuses = array( '' => __( 'Do not change', 'nashenski-commerce' ) ) + wc_get_order_statuses();

		return array(
			array(
				'type'  => 'title',
				'id'    => 'nashenski_automation',
				'title' => __( 'Waybills and order statuses', 'nashenski-commerce' ),
				'desc'  => __( 'For every courier you use. On each order, and for many orders at once in the orders list, you also get "Create waybill", "Request a courier" and "Check the shipment now".', 'nashenski-commerce' ),
			),
			array(
				'id'      => 'nashenski_settings_' . self::GROUP . '_order_number',
				'type'    => 'checkbox',
				'title'   => __( 'Order number on the waybill', 'nashenski-commerce' ),
				'desc'    => __( 'Econt and Speedy get it in their reference field, BOX NOW on the label and Pigeon Express in the note for the courier.', 'nashenski-commerce' ),
				'default' => 'no',
			),
			array(
				'id'      => 'nashenski_settings_' . self::GROUP . '_delivered',
				'type'    => 'select',
				'title'   => __( 'When the parcel is delivered', 'nashenski-commerce' ),
				'options' => $statuses,
				'default' => '',
			),
			array(
				'id'      => 'nashenski_settings_' . self::GROUP . '_returned',
				'type'    => 'select',
				'title'   => __( 'When the parcel goes back to you', 'nashenski-commerce' ),
				'desc'    => __( 'Refused by the customer or returned. Orders that are processing or on hold and have an Econt, Speedy or BOX NOW waybill are checked every hour, and each change is noted on the order. Pigeon Express does not publish its statuses yet.', 'nashenski-commerce' ),
				'options' => $statuses,
				'default' => '',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'nashenski_automation',
			),
		);
	}

	/**
	 * Order number on the waybill.
	 *
	 * @param mixed $courier Courier.
	 * @param mixed $label Label.
	 * @param mixed $order Order.
	 */
	public static function add_order_number( $courier, $label, $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return $label;
		}

		/* translators: %s: order number */
		$text = sprintf( __( 'Order %s', 'nashenski-commerce' ), $order->get_order_number() );

		switch ( $courier ) {
			case 'econt':
				$label['label']['orderNumber'] = (string) $order->get_order_number();
				break;
			case 'speedy':
				// Speedy's reference field takes 30 characters.
				$label['ref1'] = mb_substr( $text, 0, 30 );
				break;
			case 'boxnow':
				$label['overwriteSenderShippingLabelInfo'] = array( 'row4' => $text );
				break;
			case 'pigeon':
				$label['note'] = empty( $label['note'] ) ? $text : $text . '. ' . $label['note'];
				break;
		}

		return $label;
	}

	/**
	 * Tracking.
	 *
	 * @param mixed $courier Courier.
	 * @param mixed $number Number.
	 */
	public static function tracking_url( $courier, $number ) {
		return isset( self::TRACKING[ $courier ] ) && '' !== (string) $number ? self::TRACKING[ $courier ] . rawurlencode( (string) $number ) : '';
	}

	/**
	 * The order's courier and waybill numbers: array( courier, array( number, ... ) ).
	 *
	 * @param mixed $order Order.
	 */
	public static function waybill( $order ) {
		$label   = nashenski_get_order_label( $order->get_id() );
		$courier = preg_replace( '/^nashenski_/', '', (string) ( $label['method'] ?? '' ) );
		$numbers = isset( $label['number'] ) ? array( $label['number'] ) : wp_list_pluck( $label['items'] ?? array(), 'number' );

		return array( $courier, array_values( array_filter( array_map( 'strval', $numbers ), 'strlen' ) ) );
	}

	/**
	 * Tracking on order page.
	 *
	 * @param mixed $order Order.
	 */
	public static function tracking_on_order_page( $order ) {
		list( $courier, $numbers ) = self::waybill( $order );
		$links                     = array();

		foreach ( $numbers as $number ) {
			$url = self::tracking_url( $courier, $number );

			if ( $url ) {
				$links[] = '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $number ) . '</a>';
			}
		}

		if ( $links ) {
			echo '<p class="nashenski-tracking">' . esc_html__( 'Track your parcel:', 'nashenski-commerce' ) . ' ' . wp_kses_post( implode( ', ', $links ) ) . '</p>';
		}
	}

	/**
	 * Order statuses that follow the shipment.
	 */
	private static function follows_shipments() {
		return '' !== (string) nashenski_get_option( self::GROUP, 'delivered' ) || '' !== (string) nashenski_get_option( self::GROUP, 'returned' );
	}

	/**
	 * Hourly check on while a status is chosen, off otherwise (Action Scheduler, part of WooCommerce).
	 */
	public static function schedule() {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		$scheduled = as_has_scheduled_action( self::CHECK, array(), self::AS_GROUP );

		if ( self::follows_shipments() && ! $scheduled ) {
			as_schedule_recurring_action( time() + 5 * MINUTE_IN_SECONDS, HOUR_IN_SECONDS, self::CHECK, array(), self::AS_GROUP );
		} elseif ( ! self::follows_shipments() && $scheduled ) {
			as_unschedule_all_actions( self::CHECK, array(), self::AS_GROUP );
		}
	}

	/**
	 * Check the shipments of the next page of open orders and move the cursor on.
	 */
	public static function check_shipments() {
		// ponytail: 100 open orders per hour, created in the last 60 days; a busier store can raise it.
		$limit = (int) apply_filters( 'nashenski/shipments/check_limit', 100 );

		// A value below 1 other than -1 (0, negative, null, text) becomes the default 100: -1 would drop the time budget and load every open order at once.
		if ( $limit < 1 && -1 !== $limit ) {
			$limit = 100;
		}

		// Each run reads the open orders from the creation second the last run reached, oldest first, skipping the IDs already read in that second:
		// an unchanged order is never saved just to move it back, an order that leaves the list cannot make another one be skipped,
		// and more orders in one second than the limit are still all reached.
		$start  = time() - 60 * DAY_IN_SECONDS;
		$from   = $start;
		$seen   = array();
		$cursor = $limit > 0 ? get_option( self::CURSOR_OPTION ) : false;

		// A missing, corrupt, older-than-the-window or future cursor means the window start with no IDs.
		if ( is_array( $cursor ) && isset( $cursor['from'], $cursor['ids'] ) && is_int( $cursor['from'] ) && is_array( $cursor['ids'] ) && $cursor['from'] >= $start && $cursor['from'] <= time() ) {
			$from = $cursor['from'];
			$seen = array_map( 'intval', $cursor['ids'] );
		}

		$args = array(
			'status'       => array( 'processing', 'on-hold' ),
			'limit'        => $limit,
			'date_created' => '>=' . $from,
			'orderby'      => 'date',
			'order'        => 'ASC',
		);

		if ( $seen ) {
			$args['exclude'] = $seen; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- the IDs already read in one second, at most the limit (100).
		}
		$open   = array(
			'key'     => self::STATE_META,
			'compare' => 'NOT EXISTS',
		);
		$legacy = ! ( class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled() );

		if ( $legacy ) {
			// The legacy post datastore rejects the meta_query argument, so add it to the WP_Query it builds.
			// It also filters date_created on the GMT date but sorts by the local post_date, and at a clock change the two orders differ:
			// the cursor would skip orders. So the window goes in as a GMT date_query (the array form has no timezone step) and the sort is on the GMT date too.
			unset( $args['date_created'] );
			$parts = array_combine(
				array( 'year', 'month', 'day', 'hour', 'minute', 'second' ),
				array_map( 'intval', explode( ' ', gmdate( 'Y n j G i s', $from ) ) )
			);
			// The window goes on the first query built only: a nested query in the same run (refunds, inside compile_orders) must not get it.
			$add = function ( $wp_query_args ) use ( $open, $parts, &$add ) {
				remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $add );
				$wp_query_args['meta_query'] = array_merge( isset( $wp_query_args['meta_query'] ) ? (array) $wp_query_args['meta_query'] : array(), array( $open ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				$wp_query_args['date_query'] = array(
					array(
						'column'    => 'post_date_gmt',
						'inclusive' => true,
						'after'     => $parts,
					),
				);
				// Marks this query, so the GMT sort below applies to it only.
				$wp_query_args['nashenski_shipments_check'] = true;

				return $wp_query_args;
			};
			// Some callers pass the order only, with no query: then the order stays as it is.
			$order = function ( $orderby, $query = null ) {
				return $query && $query->get( 'nashenski_shipments_check' ) ? 'post_date_gmt ASC' : $orderby;
			};
			add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $add );
			add_filter( 'posts_orderby', $order, 10, 2 );
		} else {
			$args['meta_query'] = array( $open ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		try {
			$orders = wc_get_orders( $args );
		} finally {
			if ( $legacy ) {
				remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $add );
				remove_filter( 'posts_orderby', $order );
			}
		}

		$dates = array();
		// Each order is one courier call (up to 60 s when the courier is slow), so a run stops after this many seconds and the next one goes on from there.
		$budget = (float) apply_filters( 'nashenski/shipments/check_seconds', 20 );
		$began  = microtime( true );

		foreach ( $orders as $order ) {
			// check_order() saves an order only when its shipment changed (delivered or returned); the others are left alone.
			// A change-only save writes the order's meta and its new status, never a stale copy of the rest of the row.
			self::check_order( $order );
			$dates[ $order->get_id() ] = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : $from;

			if ( $limit > 0 && microtime( true ) - $began > $budget ) {
				break;
			}
		}

		if ( $limit > 0 ) {
			// Orders left unread (out of time) make the page a full one: the cursor moves to the last order read, not back to the start.
			if ( count( $orders ) < $limit && count( $dates ) === count( $orders ) ) {
				// A short page is the end of the list, so start again at the window start.
				$next = array(
					'from' => $start,
					'ids'  => array(),
				);
			} else {
				// Go on from the page's last second; the IDs read in it are skipped next time, with the stored ones when it is still the same second.
				$last = max( $dates );
				$ids  = array_keys(
					array_filter(
						$dates,
						function ( $date ) use ( $last ) {
							return $date === $last;
						}
					)
				);
				$next = array(
					'from' => $last,
					'ids'  => array_values( array_unique( array_merge( $last === $from ? $seen : array(), $ids ) ) ),
				);
			}

			update_option( self::CURSOR_OPTION, $next, false );
		}
	}

	/**
	 * Read the shipment, note a delivery or a return once and set the chosen status.
	 * Returns 'delivered', 'returned', '' (still on its way) or an error message.
	 *
	 * @param mixed $order Order.
	 */
	public static function check_order( $order ) {
		list( $courier, $numbers ) = self::waybill( $order );

		if ( ! $numbers ) {
			return __( 'This order has no waybill yet.', 'nashenski-commerce' );
		}

		if ( ! in_array( $courier, self::FOLLOWED, true ) || ! Pickers::connected( $courier ) ) {
			return __( 'The shipment status can only be read for Econt, Speedy and BOX NOW, with their login.', 'nashenski-commerce' );
		}

		$state = self::state( $courier, $numbers[0] );

		if ( '' === $state || $order->get_meta( self::STATE_META ) === $state ) {
			return $state;
		}

		$order->update_meta_data( self::STATE_META, $state );
		$note = 'delivered' === $state
			/* translators: %s: waybill number */
			? sprintf( __( 'The courier delivered parcel %s.', 'nashenski-commerce' ), $numbers[0] )
			/* translators: %s: waybill number */
			: sprintf( __( 'Parcel %s is going back to you (refused or returned).', 'nashenski-commerce' ), $numbers[0] );
		$status = (string) nashenski_get_option( self::GROUP, $state );

		if ( '' !== $status && 'wc-' . $order->get_status() !== $status ) {
			$order->update_status( preg_replace( '/^wc-/', '', $status ), $note );
		} else {
			$order->add_order_note( $note );
			$order->save();
		}

		return $state;
	}

	/**
	 * 'delivered', 'returned' or '' from the courier's own tracking.
	 *
	 * @param mixed $courier Courier.
	 * @param mixed $number Number.
	 */
	public static function state( $courier, $number ) {
		$c = nashenski()->container();

		switch ( $courier ) {
			case 'econt':
				$response = $c[ Client::ECONT ]->api_call( $c[ Client::ECONT ]::SHIPMENT_STATUS_ENDPOINT, array( 'shipmentNumbers' => array( $number ) ) );
				$events   = wp_list_pluck( $response['shipmentStatuses'][0]['status']['trackingEvents'] ?? array(), 'destinationType' );

				return self::first_match( $events, array( 'client' ), array( 'return', 'is_returning_to_sender', 'returned_to_sender' ) );
			case 'speedy':
				$response = $c[ Client::SPEEDY ]->api_call( $c[ Client::SPEEDY ]::TRACK_ENDPOINT, array( 'parcels' => array( array( 'id' => $number ) ) ) );
				$codes    = array_map( 'intval', wp_list_pluck( $response['parcels'][0]['operations'] ?? array(), 'operationCode' ) );

				// Speedy's final codes: -14 delivered, 124 delivered back to the sender (125 to 129 are
				// destroyed, theft, cancelled, closed: not "back to you").
				return self::first_match( $codes, array( -14 ), array( 124 ) );
			case 'boxnow':
				$response = $c[ Client::BOXNOW ]->api_call( '/api/v1/parcels', array( 'parcelId' => $number ), 'GET' );

				return self::first_match( array( $response['data'][0]['state'] ?? '' ), array( 'delivered' ), array( 'returned', 'expired-return' ) );
		}

		return '';
	}

	/**
	 * First match.
	 *
	 * @param mixed $seen Seen.
	 * @param mixed $delivered Delivered.
	 * @param mixed $returned Returned.
	 * @return string|mixed Result.
	 */
	private static function first_match( $seen, $delivered, $returned ) {
		if ( array_intersect( $delivered, $seen ) ) {
			return 'delivered';
		}

		return array_intersect( $returned, $seen ) ? 'returned' : '';
	}

	/**
	 * One order or many: create waybill, request a courier, check now.
	 *
	 * @param mixed $actions Actions.
	 * @param mixed $order Order.
	 */
	public static function order_actions( $actions, $order = null ) {
		return $order instanceof \WC_Order && self::waybill( $order )[0] ? $actions + self::actions() : $actions;
	}

	/**
	 * Bulk actions.
	 *
	 * @param mixed $actions Actions.
	 * @return mixed Result.
	 */
	public static function bulk_actions( $actions ) {
		return $actions + self::actions();
	}

	/**
	 * Handle bulk action.
	 *
	 * @param mixed $redirect Redirect.
	 * @param mixed $action Action.
	 * @param mixed $ids Ids.
	 * @return mixed Result.
	 */
	public static function handle_bulk_action( $redirect, $action, $ids ) {
		if ( isset( self::actions()[ $action ] ) && current_user_can( 'edit_shop_orders' ) ) {
			self::notice( self::run( $action, array_filter( array_map( 'wc_get_order', (array) $ids ) ) ) );
		}

		return $redirect;
	}

	/**
	 * Run an action on orders. Returns the lines of the notice.
	 *
	 * @param mixed $action Action.
	 * @param mixed $orders Orders.
	 */
	public static function run( $action, $orders ) {
		if ( 'nashenski_request_courier' === $action ) {
			return self::request_courier( $orders );
		}

		$lines = array();

		foreach ( $orders as $order ) {
			if ( 'nashenski_create_waybill' === $action ) {
				$result = self::create_waybill( $order );
			} else {
				$state  = self::check_order( $order );
				$result = 'delivered' === $state ? __( 'delivered', 'nashenski-commerce' ) : ( 'returned' === $state ? __( 'going back to you', 'nashenski-commerce' ) : ( '' === $state ? __( 'still on its way', 'nashenski-commerce' ) : $state ) );
			}

			/* translators: 1: order number, 2: result */
			$lines[] = sprintf( __( 'Order %1$s: %2$s', 'nashenski-commerce' ), $order->get_order_number(), $result );
		}

		return $lines;
	}

	/**
	 * The same waybill the "Generate label after checkout" option makes, from the customer's choices.
	 *
	 * @param mixed $order Order.
	 */
	public static function create_waybill( $order ) {
		list( $courier, $numbers ) = self::waybill( $order );

		if ( $numbers ) {
			/* translators: %s: waybill number */
			return sprintf( __( 'already has waybill %s', 'nashenski-commerce' ), implode( ', ', $numbers ) );
		}

		if ( ! isset( Register::COURIERS[ $courier ] ) || ! Pickers::connected( $courier ) ) {
			return __( 'not sent with a courier that has a login', 'nashenski-commerce' );
		}

		try {
			call_user_func( array( 'Nashenski\\Admin\\' . Register::COURIERS[ $courier ][0], 'generate_label_after_order_generated' ), $order->get_id() );
		} catch ( \Throwable $e ) {
			// One order's data must not stop the others in a bulk action.
			/* translators: %s: error message */
			return sprintf( __( 'could not be sent: %s', 'nashenski-commerce' ), $e->getMessage() );
		}

		$numbers = self::waybill( wc_get_order( $order->get_id() ) )[1];

		return $numbers
			/* translators: %s: waybill number */
			? sprintf( __( 'waybill %s created', 'nashenski-commerce' ), implode( ', ', $numbers ) )
			: __( 'the courier did not create the waybill; open the order to see why', 'nashenski-commerce' );
	}

	/**
	 * One courier request per courier for all the chosen orders (Econt, Speedy).
	 *
	 * @param mixed $orders Orders.
	 */
	public static function request_courier( $orders ) {
		$by_courier = array();
		$lines      = array();

		foreach ( $orders as $order ) {
			list( $courier, $numbers ) = self::waybill( $order );

			if ( in_array( $courier, self::PICKUP, true ) && $numbers && Pickers::connected( $courier ) ) {
				$by_courier[ $courier ][ $order->get_id() ] = array( $order, $numbers[0] );
			} else {
				/* translators: %s: order number */
				$lines[] = sprintf( __( 'Order %s: only Econt and Speedy waybills can get a courier request here.', 'nashenski-commerce' ), $order->get_order_number() );
			}
		}

		foreach ( $by_courier as $courier => $items ) {
			$numbers = wp_list_pluck( $items, 1 );
			$error   = 'econt' === $courier ? self::econt_courier( $numbers, reset( $items )[0] ) : self::speedy_courier( $numbers );

			foreach ( $items as list( $order, $number ) ) {
				$order->add_order_note(
					$error
					/* translators: 1: waybill number, 2: the courier's answer */
					? sprintf( __( 'Courier request for waybill %1$s failed: %2$s', 'nashenski-commerce' ), $number, $error )
					/* translators: %s: waybill number */
					: sprintf( __( 'Courier requested for waybill %s.', 'nashenski-commerce' ), $number )
				);
			}

			/* translators: 1: courier, 2: waybill numbers, 3: result */
			$lines[] = sprintf( __( '%1$s, waybills %2$s: %3$s', 'nashenski-commerce' ), nashenski_couriers()[ $courier ] ?? $courier, implode( ', ', $numbers ), $error ? $error : __( 'courier requested', 'nashenski-commerce' ) );
		}

		return $lines;
	}

	/**
	 * Econt: today until 14:00 (Econt's usual cut-off), else the next working day. Econt answers
	 * "... е почивен ден" for a holiday, so that day is skipped and the request sent again.
	 * Returns '' or the error.
	 *
	 * @param mixed $numbers Numbers.
	 * @param mixed $order Order.
	 */
	private static function econt_courier( $numbers, $order ) {
		$c     = nashenski()->container();
		$label = (array) ( $order->get_meta( 'nashenski_econt_label' )['label'] ?? array() );

		if ( ! empty( $label['senderOfficeCode'] ) ) {
			return __( 'you send from an Econt office, so no courier comes to you', 'nashenski-commerce' );
		}

		$day = new \DateTime( 'now', wp_timezone() );

		if ( (int) $day->format( 'Hi' ) >= 1400 ) {
			$day->modify( '+1 day' )->setTime( 9, 0 );
		}

		for ( $attempt = 0; $attempt < 7; $attempt++ ) {
			while ( (int) $day->format( 'N' ) > 5 ) {
				$day->modify( '+1 day' )->setTime( 9, 0 );
			}

			$response = $c[ Client::ECONT ]->api_call(
				'Shipments/ShipmentService.requestCourier.json',
				array(
					'requestTimeFrom'   => $day->format( 'Y-m-d H:i:s' ),
					'requestTimeTo'     => $day->format( 'Y-m-d' ) . ' 14:00:00',
					'shipmentType'      => 'pack',
					'shipmentPackCount' => count( $numbers ),
					'attachShipments'   => array_values( $numbers ),
					'senderClient'      => $label['senderClient'] ?? null,
					'senderAddress'     => $label['senderAddress'] ?? null,
				)
			);

			if ( empty( $response['type'] ) || false !== stripos( (string) $response['type'], 'AlreadyOrdered' ) ) {
				return '';
			}

			$error = trim( (string) ( $response['message'] ?? '' ) );
			if ( ! $error ) {
				$error = (string) ( $response['innerErrors'][0]['message'] ?? __( 'Econt did not accept the request.', 'nashenski-commerce' ) );
			}

			if ( false === mb_stripos( $error, 'почивен ден' ) ) {
				return $error;
			}

			$day->modify( '+1 day' )->setTime( 9, 0 );
		}

		return $error;
	}

	/**
	 * Speedy picks the next possible day itself. Returns '' or the error.
	 *
	 * @param mixed $numbers Numbers.
	 */
	private static function speedy_courier( $numbers ) {
		$c        = nashenski()->container();
		$response = $c[ Client::SPEEDY ]->api_call(
			'pickup',
			array(
				'explicitShipmentIdList' => array_values( $numbers ),
				'visitEndTime'           => (string) apply_filters( 'nashenski/speedy/pickup_until', '17:30' ),
				'autoAdjustPickupDate'   => true,
			)
		);

		// "Already ordered" (for example when the waybill ordered the courier) is fine.
		if ( empty( $response['error'] ) || 'consignments.consignment_is_ordered' === ( $response['error']['context'] ?? '' ) ) {
			return '';
		}

		return (string) ( $response['error']['message'] ?? __( 'Speedy did not accept the request.', 'nashenski-commerce' ) );
	}

	/**
	 * Notice after an action.
	 *
	 * @param mixed $lines Lines.
	 */
	private static function notice( $lines ) {
		set_transient( 'nashenski_actions_' . get_current_user_id(), $lines, MINUTE_IN_SECONDS );
	}

	/**
	 * Show notice.
	 */
	public static function show_notice() {
		$key   = 'nashenski_actions_' . get_current_user_id();
		$lines = get_transient( $key );

		if ( $lines ) {
			delete_transient( $key );
			echo '<div class="notice notice-info is-dismissible"><p>' . wp_kses_post( implode( '<br>', array_map( 'esc_html', (array) $lines ) ) ) . '</p></div>';
		}
	}
}
