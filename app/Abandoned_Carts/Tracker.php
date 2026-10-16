<?php
namespace Nashenski\Abandoned_Carts;

defined( 'ABSPATH' ) || exit;

// Custom table: every query names the table with %i and prepares its values.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the cart table is read and written on every change, so caching would show stale rows.
/**
 * Abandoned carts: keeps the carts that were not ordered in a table of the site's own, and sends up to
 * three reminders to a shopper who typed an email. Nothing leaves the site. Reminders are plain text
 * through wp_mail(), not the WooCommerce mailer.
 *
 * Cart row states: open (counts and can get reminders), recovered (an order was placed from it),
 * emptied (the shopper emptied the cart), unsubscribed (the shopper clicked the link in a reminder).
 * A row keeps its session key only while it is open, so each finished cart frees the key for the next one.
 */
class Tracker {
	const TABLE           = 'nashenski_carts';
	const DB_OPTION       = 'nashenski_carts_db_version';
	const DB_VERSION      = '1';
	const SEND_HOOK       = 'nashenski/abandoned_carts/send';
	const CLEAN_HOOK      = 'nashenski/abandoned_carts/clean';
	const GROUP           = 'nashenski';
	const UNSUBSCRIBE_VAR = 'nashenski_unsubscribe';

	/** Reminders are not sent between these hours (site time). */
	const QUIET_FROM = 22;
	const QUIET_TO   = 8;

	/** Rows handled per send run. */
	const BATCH = 100;

	public function __construct() {
		add_action( 'init', array( __CLASS__, 'unsubscribe' ) );
		add_action( self::SEND_HOOK, array( __CLASS__, 'send_due' ) );
		add_action( self::CLEAN_HOOK, array( __CLASS__, 'clean_old' ) );

		if ( ! self::enabled() ) {
			return;
		}

		add_action( 'init', array( __CLASS__, 'install' ) );
		add_action( 'woocommerce_cart_updated', array( __CLASS__, 'record_cart' ) );
		// The Store API empties the cart without firing cart_updated, so the emptied hook is needed too.
		add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'mark_emptied' ) );
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'capture_from_checkout' ) );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( __CLASS__, 'capture_from_store_api' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'mark_recovered' ) );
	}

	public static function enabled() {
		return 'yes' === nashenski_get_option( 'apis', 'enable_abandoned_carts' );
	}

	public static function table() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Hours after the last cart change for each reminder, from the setting (default 1, 24, 72).
	 * Up to three, sorted.
	 */
	public static function steps() {
		$hours = array_filter( array_map( 'floatval', explode( ',', (string) nashenski_get_option( 'abandoned_carts', 'hours' ) ) ), function ( $hour ) {
			return $hour > 0;
		} );

		if ( ! $hours ) {
			$hours = array( 1, 24, 72 );
		}

		sort( $hours );

		return array_slice( array_values( $hours ), 0, 3 );
	}

	public static function retention_days() {
		$days = (int) nashenski_get_option( 'abandoned_carts', 'retention_days' );

		return $days > 0 ? $days : 90;
	}

	/**
	 * Creates the table, once per DB version, and schedules the send and clean-up jobs.
	 */
	public static function install() {
		if ( get_option( self::DB_OPTION ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_key varchar(64) DEFAULT NULL,
			email varchar(190) NOT NULL DEFAULT '',
			first_name varchar(100) NOT NULL DEFAULT '',
			phone varchar(40) NOT NULL DEFAULT '',
			step varchar(20) NOT NULL DEFAULT 'cart',
			cart longtext NOT NULL,
			cart_total decimal(19,4) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'open',
			token varchar(64) NOT NULL DEFAULT '',
			emails_sent tinyint(3) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			last_activity datetime NOT NULL,
			last_email datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_key (session_key),
			KEY status_activity (status,last_activity),
			KEY email (email)
		) {$charset};" );

		if ( function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( self::SEND_HOOK, array(), self::GROUP ) ) {
				as_schedule_recurring_action( time() + 5 * MINUTE_IN_SECONDS, 15 * MINUTE_IN_SECONDS, self::SEND_HOOK, array(), self::GROUP );
			}

			if ( ! as_has_scheduled_action( self::CLEAN_HOOK, array(), self::GROUP ) ) {
				as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::CLEAN_HOOK, array(), self::GROUP );
			}
		}

		update_option( self::DB_OPTION, self::DB_VERSION );
	}

	private static function session_key() {
		return WC()->session ? (string) WC()->session->get_customer_id() : '';
	}

	/**
	 * Saves the cart on every change. An emptied cart stops being tracked.
	 */
	public static function record_cart() {
		$key = self::session_key();

		if ( '' === $key || ! WC()->cart ) {
			return;
		}

		global $wpdb;
		$table = self::table();

		if ( WC()->cart->is_empty() ) {
			self::mark_emptied();

			return;
		}

		$items = array();

		foreach ( WC()->cart->get_cart() as $item ) {
			$product = $item['data'];
			$items[] = array(
				'name'     => $product->get_name(),
				'quantity' => (int) $item['quantity'],
				'total'    => (float) $item['line_total'],
				'url'      => $product->get_permalink(),
			);
		}

		$now  = current_time( 'mysql', true );
		$data = array(
			'cart'          => wp_json_encode( $items ),
			'cart_total'    => (float) WC()->cart->get_total( 'edit' ),
			'currency'      => get_woocommerce_currency(),
			'last_activity' => $now,
		);

		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE session_key = %s', $table, $key ) );

		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $id ) );

			return;
		}

		$wpdb->insert( $table, $data + array(
			'session_key' => $key,
			'status'      => 'open',
			'step'        => 'cart',
			'token'       => wp_generate_password( 32, false ),
			'created_at'  => $now,
		) );
	}

	/**
	 * The shopper emptied the cart: it is no longer tracked, so its reminders stop.
	 * An order placed from the cart is marked recovered first, so this does nothing after it.
	 */
	public static function mark_emptied() {
		$key = self::session_key();

		if ( '' === $key ) {
			return;
		}

		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET status = %s, session_key = NULL WHERE session_key = %s AND status = %s', self::table(), 'emptied', $key, 'open' ) );
	}

	/**
	 * Classic checkout sends the whole form on every change (the field values arrive as one string).
	 */
	public static function capture_from_checkout( $post_data ) {
		parse_str( (string) $post_data, $data );

		self::save_contact(
			isset( $data['billing_email'] ) ? $data['billing_email'] : '',
			isset( $data['billing_first_name'] ) ? $data['billing_first_name'] : '',
			isset( $data['billing_phone'] ) ? $data['billing_phone'] : '',
			'checkout'
		);
	}

	/**
	 * The block checkout sends the billing address with each update.
	 */
	public static function capture_from_store_api( $customer, $request ) {
		$billing = $request->get_param( 'billing_address' );

		if ( ! is_array( $billing ) ) {
			return;
		}

		self::save_contact(
			isset( $billing['email'] ) ? $billing['email'] : '',
			isset( $billing['first_name'] ) ? $billing['first_name'] : '',
			isset( $billing['phone'] ) ? $billing['phone'] : '',
			'checkout'
		);
	}

	/**
	 * The email is kept only once the shopper has typed one. Only open carts get it.
	 */
	private static function save_contact( $email, $name, $phone, $step ) {
		$key   = self::session_key();
		$email = sanitize_email( (string) $email );

		if ( '' === $key || ! is_email( $email ) ) {
			return;
		}

		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			'UPDATE %i SET email = %s, first_name = %s, phone = %s, step = %s WHERE session_key = %s AND status = %s',
			self::table(),
			$email,
			sanitize_text_field( (string) $name ),
			sanitize_text_field( (string) $phone ),
			sanitize_key( $step ),
			$key,
			'open'
		) );
	}

	/**
	 * An order was placed from this session's cart (checkout, or a quick order from the cart).
	 */
	public static function mark_recovered() {
		$key = self::session_key();

		if ( '' === $key ) {
			return;
		}

		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET status = %s, session_key = NULL WHERE session_key = %s AND status = %s', self::table(), 'recovered', $key, 'open' ) );
	}

	/**
	 * Sends the reminders that are due. Runs every 15 minutes through Action Scheduler.
	 */
	public static function send_due() {
		if ( ! self::enabled() ) {
			return;
		}

		$hour = (int) current_time( 'G' );

		if ( $hour >= self::QUIET_FROM || $hour < self::QUIET_TO ) {
			return;
		}

		global $wpdb;
		$table = self::table();
		$steps = self::steps();

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM %i WHERE status = %s AND email <> %s AND emails_sent < %d AND last_activity <= %s
			AND email NOT IN (SELECT email FROM %i WHERE status = %s) ORDER BY last_activity LIMIT %d',
			$table,
			'open',
			'',
			count( $steps ),
			gmdate( 'Y-m-d H:i:s', time() - min( $steps ) * HOUR_IN_SECONDS ),
			$table,
			'unsubscribed',
			self::BATCH
		) );

		foreach ( (array) $rows as $row ) {
			// Each reminder waits its own number of hours after the last cart change.
			// ponytail: the wait runs from the last cart change, not the first abandonment; add a column when that matters.
			$due = strtotime( $row->last_activity . ' UTC' ) + $steps[ (int) $row->emails_sent ] * HOUR_IN_SECONDS;

			if ( time() < $due || ! self::send( $row ) ) {
				continue;
			}

			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET emails_sent = emails_sent + 1, last_email = %s WHERE id = %d', $table, current_time( 'mysql', true ), (int) $row->id ) );
		}
	}

	private static function send( $row ) {
		$items = json_decode( (string) $row->cart, true );
		$lines = array();

		foreach ( (array) $items as $item ) {
			$lines[] = sprintf( '- %s x %d', $item['name'], $item['quantity'] );
		}

		$coupon = sanitize_text_field( (string) nashenski_get_option( 'abandoned_carts', 'coupon' ) );
		$link   = add_query_arg( self::UNSUBSCRIBE_VAR, $row->token, home_url( '/' ) );
		$total  = html_entity_decode( wp_strip_all_tags( wc_price( $row->cart_total, array( 'currency' => $row->currency ) ) ), ENT_QUOTES, 'UTF-8' );

		$body = array_merge(
			array(
				'' !== $row->first_name
					/* translators: %s: shopper's first name */
					? sprintf( __( 'Hello %s,', 'nashenski-commerce' ), $row->first_name )
					: __( 'Hello,', 'nashenski-commerce' ),
				'',
				__( 'You left these items in your cart:', 'nashenski-commerce' ),
			),
			$lines,
			array(
				'',
				/* translators: %s: cart total, with the currency */
				sprintf( __( 'Total: %s', 'nashenski-commerce' ), $total ),
			),
			'' !== $coupon
				? array(
					'',
					/* translators: %s: coupon code */
					sprintf( __( 'Use the code %s when you order.', 'nashenski-commerce' ), $coupon ),
				)
				: array(),
			array(
				'',
				__( 'Go back to your cart:', 'nashenski-commerce' ),
				wc_get_cart_url(),
				'',
				__( 'Do not want these emails? Unsubscribe here:', 'nashenski-commerce' ),
				$link,
			)
		);

		return (bool) wp_mail(
			$row->email,
			/* translators: %s: site name */
			sprintf( __( 'You left items in your cart at %s', 'nashenski-commerce' ), get_bloginfo( 'name' ) ),
			implode( "\n", $body )
		);
	}

	/**
	 * The one-click link in a reminder. Stops all reminders to that email.
	 */
	public static function unsubscribe() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the check; a link in an email cannot carry a nonce.
		if ( empty( $_GET[ self::UNSUBSCRIBE_VAR ] ) || ! get_option( self::DB_OPTION ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		$token = sanitize_text_field( wp_unslash( $_GET[ self::UNSUBSCRIBE_VAR ] ) );

		global $wpdb;
		$table = self::table();
		$email = $wpdb->get_var( $wpdb->prepare( 'SELECT email FROM %i WHERE token = %s', $table, $token ) );

		if ( $email ) {
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET status = %s, session_key = NULL WHERE (email = %s AND status = %s) OR token = %s', $table, 'unsubscribed', $email, 'open', $token ) );
		}

		// The same answer whether or not the token exists, so the link reveals nothing.
		wp_die(
			esc_html__( 'You will not get more reminders from this shop.', 'nashenski-commerce' ),
			esc_html__( 'Unsubscribed', 'nashenski-commerce' ),
			array( 'response' => 200 )
		);
	}

	/**
	 * Deletes carts whose last change is older than the retention setting. Runs even with the feature off.
	 */
	public static function clean_old() {
		if ( ! get_option( self::DB_OPTION ) ) {
			return;
		}

		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			'DELETE FROM %i WHERE last_activity < %s',
			self::table(),
			gmdate( 'Y-m-d H:i:s', time() - self::retention_days() * DAY_IN_SECONDS )
		) );
	}
}
