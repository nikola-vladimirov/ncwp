<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.
/**
 * Tracker.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Abandoned_Carts;

defined( 'ABSPATH' ) || exit;

// Custom table: every query names the table with %i and prepares its values.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the cart table is read and written on every change, so caching would show stale rows.
/**
 * Abandoned carts: keeps the carts that were not ordered in a table of the site's own, and sends up to
 * three reminders to a shopper who typed an email. The email is recorded at checkout even for a
 * logged-out shopper, so a cart is never tracked without a way to reach the owner of it.
 *
 * Reminders go out through the WooCommerce email "Abandoned cart reminder", so the owner edits the
 * subject and body in WooCommerce > Settings > Emails. Each reminder carries a link that refills the
 * cart and a single-use coupon made for that cart. Nothing leaves the site.
 *
 * Cart row states: open (counts and can get reminders), recovered (an order was placed from it),
 * emptied (the shopper emptied the cart), unsubscribed (the shopper clicked the link in a reminder).
 * A row keeps its session key only while it is open, so each finished cart frees the key for the next one.
 */
class Tracker {
	const TABLE           = 'nashenski_carts';
	const DB_OPTION       = 'nashenski_carts_db_version';
	const DB_VERSION      = '2';
	const SEND_HOOK       = 'nashenski/abandoned_carts/send';
	const CLEAN_HOOK      = 'nashenski/abandoned_carts/clean';
	const GROUP           = 'nashenski';
	const UNSUBSCRIBE_VAR = 'nashenski_unsubscribe';
	const RESUME_VAR      = 'nashenski_resume';
	const EMAIL_ID        = 'nashenski_abandoned_cart';

	/** Reminders are not sent between these hours (site time). */
	const QUIET_FROM = 22;
	const QUIET_TO   = 8;

	/** Rows handled per send run. */
	const BATCH = 100;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'unsubscribe' ) );
		add_action( 'template_redirect', array( __CLASS__, 'resume' ) );
		add_action( self::SEND_HOOK, array( __CLASS__, 'send_due' ) );
		add_action( self::CLEAN_HOOK, array( __CLASS__, 'clean_old' ) );
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_email' ) );

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
		// The block checkout does not fire checkout_order_created.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'mark_recovered' ) );
	}

	/**
	 * Registers the reminder as a WooCommerce email, so it is edited and sent the WooCommerce way.
	 *
	 * @param mixed $emails Emails.
	 */
	public static function register_email( $emails ) {
		$emails[ self::EMAIL_ID ] = new Email();

		return $emails;
	}

	/**
	 * Enabled.
	 *
	 * @return mixed
	 */
	public static function enabled() {
		return 'yes' === nashenski_get_option( 'apis', 'enable_abandoned_carts' );
	}

	/**
	 * Table.
	 *
	 * @return mixed
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Hours after the last cart change for each reminder, from the setting (default 1, 24, 72).
	 * Up to three, sorted.
	 */
	public static function steps() {
		$hours = array_filter(
			array_map( 'floatval', explode( ',', (string) nashenski_get_option( 'abandoned_carts', 'hours' ) ) ),
			function ( $hour ) {
				return $hour > 0;
			}
		);

		if ( ! $hours ) {
			$hours = array( 1, 24, 72 );
		}

		sort( $hours );

		return array_slice( array_values( $hours ), 0, 3 );
	}

	/**
	 * Retention days.
	 *
	 * @return mixed
	 */
	public static function retention_days() {
		$days = (int) nashenski_get_option( 'abandoned_carts', 'retention_days' );

		return $days > 0 ? $days : 90;
	}

	/**
	 * The discount types the auto coupon can use, as WooCommerce names them.
	 */
	public static function coupon_types() {
		return array(
			'percent'       => __( 'Percentage off', 'nashenski-commerce' ),
			'fixed_cart'    => __( 'Fixed amount off the cart', 'nashenski-commerce' ),
			'fixed_product' => __( 'Fixed amount off each product', 'nashenski-commerce' ),
		);
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

		dbDelta(
			"CREATE TABLE {$table} (
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
			coupon_code varchar(120) NOT NULL DEFAULT '',
			coupon_id bigint(20) unsigned NOT NULL DEFAULT 0,
			emails_sent tinyint(3) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			last_activity datetime NOT NULL,
			last_email datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_key (session_key),
			KEY status_activity (status,last_activity),
			KEY token (token),
			KEY email (email)
		) {$charset};"
		);

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

	/**
	 * Session key.
	 *
	 * @return mixed
	 */
	private static function session_key() {
		return WC()->session ? (string) WC()->session->get_customer_id() : '';
	}

	/**
	 * The cart contents and totals as a row, ready to insert or merge into an update. Each item keeps
	 * its product and variation ids, so a reminder link can put the very same items back in the cart.
	 */
	private static function cart_data() {
		$items = array();

		if ( WC()->cart && ! WC()->cart->is_empty() ) {
			foreach ( WC()->cart->get_cart() as $item ) {
				$product = $item['data'];
				$items[] = array(
					'name'         => $product->get_name(),
					'quantity'     => (int) $item['quantity'],
					'total'        => (float) $item['line_total'],
					'url'          => $product->get_permalink(),
					'product_id'   => (int) $item['product_id'],
					'variation_id' => (int) $item['variation_id'],
					'variation'    => isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array(),
				);
			}
		}

		return array(
			'cart'          => wp_json_encode( $items ),
			'cart_total'    => ( WC()->cart && ! WC()->cart->is_empty() ) ? (float) WC()->cart->get_total( 'edit' ) : 0,
			'currency'      => get_woocommerce_currency(),
			'last_activity' => current_time( 'mysql', true ),
		);
	}

	/**
	 * Saves the cart on every change. An emptied cart stops being tracked.
	 */
	public static function record_cart() {
		$key = self::session_key();

		if ( '' === $key || ! WC()->cart ) {
			return;
		}

		if ( WC()->cart->is_empty() ) {
			self::mark_emptied();

			return;
		}

		global $wpdb;
		$table = self::table();
		$id    = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE session_key = %s', $table, $key ) );

		if ( $id ) {
			$wpdb->update( $table, self::cart_data(), array( 'id' => (int) $id ) );

			return;
		}

		$wpdb->insert(
			$table,
			self::cart_data() + array(
				'session_key' => $key,
				'status'      => 'open',
				'step'        => 'cart',
				'token'       => wp_generate_password( 32, false ),
				'created_at'  => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * The shopper emptied the cart: it is no longer tracked, so its reminders stop.
	 * An order placed from the cart is marked recovered first, so this does nothing after it.
	 * Logging out also empties the cart, but keeps the saved one ($clear_persistent_cart false): that cart is still open.
	 *
	 * @param bool $clear_persistent_cart Clear persistent cart.
	 */
	public static function mark_emptied( $clear_persistent_cart = true ) {
		$key = self::session_key();

		if ( '' === $key || ! $clear_persistent_cart ) {
			return;
		}

		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET status = %s, session_key = NULL WHERE session_key = %s AND status = %s', self::table(), 'emptied', $key, 'open' ) );
	}

	/**
	 * Classic checkout sends the whole form on every change (the field values arrive as one string).
	 *
	 * @param mixed $post_data Post data.
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
	 *
	 * @param mixed $customer Customer.
	 * @param mixed $request  Request.
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
	 * Keeps the email the shopper typed at checkout. A logged-out shopper has no account, so if no row
	 * was recorded yet the cart is stored right here; the email is never dropped just because the cart
	 * did not change before checkout.
	 *
	 * @param mixed $email Email.
	 * @param mixed $name  Name.
	 * @param mixed $phone Phone.
	 * @param mixed $step  Step.
	 */
	private static function save_contact( $email, $name, $phone, $step ) {
		$key   = self::session_key();
		$email = sanitize_email( (string) $email );

		if ( '' === $key || ! is_email( $email ) ) {
			return;
		}

		global $wpdb;
		$table = self::table();

		$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE session_key = %s AND status = %s', $table, $key, 'open' ) );

		$contact = array(
			'email'      => $email,
			'first_name' => sanitize_text_field( (string) $name ),
			'phone'      => sanitize_text_field( (string) $phone ),
			'step'       => sanitize_key( $step ),
		);

		if ( $id ) {
			$wpdb->update( $table, $contact, array( 'id' => (int) $id ) );

			return;
		}

		// No open row for this session: make one from the current cart so the email is kept.
		$wpdb->insert(
			$table,
			self::cart_data() + $contact + array(
				'session_key' => $key,
				'status'      => 'open',
				'token'       => wp_generate_password( 32, false ),
				'created_at'  => current_time( 'mysql', true ),
			)
		);
	}

	/**
	 * An order was placed from this session's cart (checkout, or a quick order from the cart).
	 */
	public static function mark_recovered() {
		$key = self::session_key();

		// Quick order calls this too, and the table only exists once the feature was turned on.
		if ( '' === $key || ! get_option( self::DB_OPTION ) ) {
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

		$rows = $wpdb->get_results(
			$wpdb->prepare(
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
			)
		);

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

	/**
	 * Sends one reminder now, whatever the schedule says. Used by the "Send reminder" button.
	 *
	 * @param mixed $id Id.
	 */
	public static function send_now( $id ) {
		$row = self::get_row( $id );

		// Only open carts: a shopper who ordered, emptied the cart or unsubscribed is not reminded, not even by hand.
		if ( ! $row || 'open' !== $row->status || ! is_email( $row->email ) ) {
			return false;
		}

		if ( ! self::send( $row ) ) {
			return false;
		}

		global $wpdb;

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET emails_sent = emails_sent + 1, last_email = %s WHERE id = %d', self::table(), current_time( 'mysql', true ), (int) $row->id ) );

		return true;
	}

	/**
	 * Sends one reminder: through the WooCommerce email when it is registered, through wp_mail() when
	 * it is not (for example a very old WooCommerce). Returns true when the mailer took it.
	 *
	 * @param mixed $row Row.
	 */
	private static function send( $row ) {
		$coupon = self::coupon_for( $row );
		$data   = self::email_data( $row, $coupon );

		$mailer = function_exists( 'WC' ) && WC()->mailer() ? WC()->mailer() : null;

		if ( $mailer && isset( $mailer->emails[ self::EMAIL_ID ] ) && $mailer->emails[ self::EMAIL_ID ] instanceof Email ) {
			return (bool) $mailer->emails[ self::EMAIL_ID ]->trigger( $row, $data );
		}

		return self::send_plain( $row, $data );
	}

	/**
	 * The values the placeholders take in a reminder.
	 *
	 * @param mixed $row    Row.
	 * @param mixed $coupon Coupon.
	 */
	public static function email_data( $row, $coupon = null ) {
		$coupon = null === $coupon ? self::coupon_for( $row ) : $coupon;
		$items  = json_decode( (string) $row->cart, true );
		$lines  = array();

		foreach ( (array) $items as $item ) {
			$lines[] = sprintf( '- %s x %d', isset( $item['name'] ) ? $item['name'] : '', isset( $item['quantity'] ) ? (int) $item['quantity'] : 1 );
		}

		$total = html_entity_decode( wp_strip_all_tags( wc_price( $row->cart_total, array( 'currency' => $row->currency ) ) ), ENT_QUOTES, 'UTF-8' );

		return array(
			'name'             => '' !== $row->first_name ? $row->first_name : __( 'there', 'nashenski-commerce' ),
			'items'            => implode( "\n", $lines ),
			'cart_total'       => $total,
			'coupon'           => $coupon,
			'coupon_line'      => '' !== $coupon
				/* translators: %s: coupon code */
				? sprintf( __( 'Use the code %s when you order. It works once.', 'nashenski-commerce' ), $coupon )
				: '',
			'resume_link'      => add_query_arg( self::RESUME_VAR, $row->token, home_url( '/' ) ),
			'unsubscribe_link' => add_query_arg( self::UNSUBSCRIBE_VAR, $row->token, home_url( '/' ) ),
			'cart_url'         => wc_get_cart_url(),
			'site_title'       => get_bloginfo( 'name' ),
			'email'            => $row->email,
		);
	}

	/**
	 * The fallback plain text reminder, used only when the WooCommerce email is not available.
	 *
	 * @param mixed $row  Row.
	 * @param mixed $data Data.
	 */
	private static function send_plain( $row, $data ) {
		$body = array(
			/* translators: %s: shopper's first name */
			sprintf( __( 'Hello %s,', 'nashenski-commerce' ), $data['name'] ),
			'',
			__( 'You left these items in your cart:', 'nashenski-commerce' ),
			$data['items'],
			'',
			/* translators: %s: cart total, with the currency */
			sprintf( __( 'Total: %s', 'nashenski-commerce' ), $data['cart_total'] ),
		);

		if ( '' !== $data['coupon_line'] ) {
			$body[] = '';
			$body[] = $data['coupon_line'];
		}

		$body[] = '';
		$body[] = __( 'Go back to your cart:', 'nashenski-commerce' );
		$body[] = $data['resume_link'];
		$body[] = '';
		$body[] = __( 'Do not want these emails? Unsubscribe here:', 'nashenski-commerce' );
		$body[] = $data['unsubscribe_link'];

		return (bool) wp_mail(
			$row->email,
			/* translators: %s: site name */
			sprintf( __( 'You left items in your cart at %s', 'nashenski-commerce' ), get_bloginfo( 'name' ) ),
			implode( "\n", $body )
		);
	}

	/**
	 * The coupon code to name in a reminder. With auto coupons on, the cart gets its own single-use
	 * coupon, made once and kept on the row. Otherwise the manually saved code is used.
	 *
	 * @param mixed $row Row.
	 */
	public static function coupon_for( $row ) {
		$amount = (float) str_replace( ',', '.', (string) nashenski_get_option( 'abandoned_carts', 'coupon_amount' ) );

		if ( 'yes' !== nashenski_get_option( 'abandoned_carts', 'auto_coupon' ) || $amount <= 0 ) {
			return self::manual_coupon();
		}

		$code = isset( $row->coupon_code ) ? trim( (string) $row->coupon_code ) : '';

		// A code kept on the row is only trusted while the coupon behind it exists. A row saved by an
		// older build can carry a code that was never created, and naming such a code would leave the
		// shopper with something WooCommerce does not know, so it is replaced by a real coupon.
		if ( '' !== $code && self::coupon_exists( $code ) ) {
			return $code;
		}

		return self::create_coupon( $row, $amount );
	}

	/**
	 * Whether WooCommerce has a coupon with this code.
	 *
	 * @param mixed $code Code.
	 */
	private static function coupon_exists( $code ) {
		if ( '' === $code ) {
			return false;
		}

		// Without the lookup the stored code is the only evidence there is, so it is kept as it was.
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return true;
		}

		return (int) wc_get_coupon_id_by_code( $code ) > 0;
	}

	/**
	 * Manual coupon.
	 *
	 * @return mixed
	 */
	public static function manual_coupon() {
		return sanitize_text_field( (string) nashenski_get_option( 'abandoned_carts', 'coupon' ) );
	}

	/**
	 * Create coupon.
	 *
	 * @param mixed $row    Row.
	 * @param mixed $amount Amount.
	 * @return mixed
	 */
	private static function create_coupon( $row, $amount ) {
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return self::manual_coupon();
		}

		try {
			$code   = 'NASH-' . strtoupper( wp_generate_password( 8, false, false ) );
			$coupon = new \WC_Coupon();

			$coupon->set_code( $code );
			$coupon->set_discount_type( nashenski_get_choice( 'abandoned_carts', 'coupon_type', self::coupon_types() ) );
			$coupon->set_amount( $amount );
			$coupon->set_usage_limit( 1 );
			$coupon->set_usage_limit_per_user( 1 );
			$coupon->set_individual_use( true );

			if ( is_email( $row->email ) ) {
				$coupon->set_email_restrictions( array( $row->email ) );
			}

			$days = (int) nashenski_get_option( 'abandoned_carts', 'coupon_days' );

			if ( $days > 0 ) {
				$coupon->set_date_expires( time() + $days * DAY_IN_SECONDS );
			}

			/* translators: %s: shopper's email */
			$coupon->set_description( sprintf( __( 'Abandoned cart coupon for %s', 'nashenski-commerce' ), $row->email ) );
			$saved = $coupon->save();

			// Nothing is remembered for the row unless the coupon really exists in WooCommerce, so a
			// reminder can never name a code that was not created.
			if ( ! $saved || $coupon->get_id() <= 0 ) {
				return '';
			}

			global $wpdb;

			$wpdb->update(
				self::table(),
				array(
					'coupon_code' => $code,
					'coupon_id'   => (int) $coupon->get_id(),
				),
				array( 'id' => (int) $row->id )
			);

			$row->coupon_code = $code;

			return $code;
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * The link in a reminder that refills the cart from the row and applies its coupon.
	 */
	public static function resume() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the check; a link in an email cannot carry a nonce.
		if ( empty( $_GET[ self::RESUME_VAR ] ) || ! get_option( self::DB_OPTION ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- see above.
		$token = sanitize_text_field( wp_unslash( $_GET[ self::RESUME_VAR ] ) );

		if ( '' === $token || ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
			return;
		}

		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE token = %s', self::table(), $token ) );

		if ( ! $row ) {
			return;
		}

		if ( ! WC()->cart->is_empty() ) {
			WC()->cart->empty_cart();
		}

		$added = 0;

		foreach ( (array) json_decode( (string) $row->cart, true ) as $item ) {
			$product_id   = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$variation_id = isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0;
			$variation    = isset( $item['variation'] ) && is_array( $item['variation'] ) ? $item['variation'] : array();
			$quantity     = isset( $item['quantity'] ) ? max( 1, (int) $item['quantity'] ) : 1;

			if ( $product_id && WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation ) ) {
				++$added;
			}
		}

		$coupon = sanitize_text_field( (string) $row->coupon_code );

		if ( $added && '' !== $coupon ) {
			WC()->cart->apply_coupon( $coupon );
		}

		WC()->session->set_customer_session_cookie( true );

		if ( $added ) {
			wc_add_notice( __( 'We put the items back in your cart.', 'nashenski-commerce' ), 'success' );
		} else {
			wc_add_notice( __( 'The items from the reminder are no longer available.', 'nashenski-commerce' ), 'error' );
		}

		wp_safe_redirect( wc_get_cart_url() );
		exit;
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

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE last_activity < %s',
				self::table(),
				gmdate( 'Y-m-d H:i:s', time() - self::retention_days() * DAY_IN_SECONDS )
			)
		);
	}

	// The read and write helpers the WooCommerce > Abandoned carts screen uses.

	/**
	 * Gets one cart row.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function get_row( $id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), (int) $id ) );
	}

	/**
	 * A page of carts, newest first, optionally only one status.
	 *
	 * @param string $status Status.
	 * @param int    $limit  Limit.
	 * @param int    $offset Offset.
	 */
	public static function get_rows( $status = '', $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table = self::table();

		if ( '' !== $status ) {
			return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY last_activity DESC LIMIT %d OFFSET %d', $table, $status, $limit, $offset ) );
		}

		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY last_activity DESC LIMIT %d OFFSET %d', $table, $limit, $offset ) );
	}

	/**
	 * Count rows.
	 *
	 * @param string $status Status.
	 * @return mixed
	 */
	public static function count_rows( $status = '' ) {
		global $wpdb;
		$table = self::table();

		if ( '' !== $status ) {
			return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, $status ) );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
	}

	/**
	 * The value of the open carts, for the summary line.
	 */
	public static function open_value() {
		global $wpdb;

		return (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(cart_total),0) FROM %i WHERE status = %s', self::table(), 'open' ) );
	}

	/**
	 * Counts per status, for the summary row: array( 'open' => 3, ... ).
	 */
	public static function counts_by_status() {
		global $wpdb;

		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT status, COUNT(*) AS found FROM %i GROUP BY status', self::table() ) );
		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ $row->status ] = (int) $row->found;
		}

		return $counts;
	}

	/**
	 * Update contact.
	 *
	 * @param mixed $id    Id.
	 * @param mixed $email Email.
	 * @param mixed $name  Name.
	 * @param mixed $phone Phone.
	 * @return mixed
	 */
	public static function update_contact( $id, $email, $name, $phone ) {
		global $wpdb;

		return (bool) $wpdb->update(
			self::table(),
			array(
				'email'      => sanitize_email( (string) $email ),
				'first_name' => sanitize_text_field( (string) $name ),
				'phone'      => sanitize_text_field( (string) $phone ),
			),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Delete row.
	 *
	 * @param mixed $id Id.
	 * @return mixed
	 */
	public static function delete_row( $id ) {
		global $wpdb;

		return (bool) $wpdb->delete( self::table(), array( 'id' => (int) $id ) );
	}

	/**
	 * Deletes cart rows.
	 *
	 * @param int[] $ids Row ids.
	 * @return int Rows removed.
	 */
	public static function delete_rows( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) );

		if ( ! $ids ) {
			return 0;
		}

		global $wpdb;
		$table = self::table();
		$in    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in holds only %d placeholders, filled by prepare().
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE id IN ($in)", array_merge( array( $table ), $ids ) ) );
	}
}
