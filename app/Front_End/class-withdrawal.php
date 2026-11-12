<?php
/**
 * EU withdrawal function.
 *
 * @package Nashenski
 */

namespace Nashenski\Front_End;

defined( 'ABSPATH' ) || exit;

/**
 * EU withdrawal function (Directive 2011/83/EU, Article 11a, added by Directive (EU) 2023/2673,
 * applied from 19 June 2026).
 *
 * WooCommerce 11.1+ has the form itself ("Order withdrawal" feature): name, order number and email,
 * a "confirm withdrawal" step and the acknowledgement email with the date and time. This adds what
 * the directive asks around it: a "withdraw from contract here" link that is easy to find on every
 * page and on each order (My Account, order received page, order emails) during the withdrawal
 * period, a shortcode for any builder, prefilled fields, the site's look and Bulgarian texts.
 */
class Withdrawal {
	const GROUP = 'withdrawal';

	/** WooCommerce's own on/off switch for the feature. */
	const FEATURE_OPTION = 'woocommerce_feature_order_withdrawal_enabled';

	/** Customer emails that carry the link. */
	const EMAILS = array( 'customer_processing_order', 'customer_on_hold_order', 'customer_completed_order', 'customer_invoice' );

	/** What the customer submitted, kept on the order. */
	const META = '_nashenski_withdrawal';

	/** Query var of the links to the form: the order number to fill in. */
	const ORDER_VAR = 'nashenski_order';

	/**
	 * Registers the hooks.
	 */
	public function __construct() {
		add_filter( 'gettext_woocommerce', array( __CLASS__, 'translate' ), 10, 2 );

		if ( ! self::available() ) {
			return;
		}

		// Submitted withdrawals stay visible on their orders even if the function is turned off later.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'order_pill' ) );

		if ( ! self::enabled() ) {
			return;
		}

		add_action( 'woocommerce_order_note_added', array( __CLASS__, 'record' ), 10, 2 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_column' ), 12 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_column' ), 12 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );

		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_shortcode( 'nashenski_withdrawal_link', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_footer', array( __CLASS__, 'footer_link' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( __CLASS__, 'order_action' ), 10, 2 );
		add_action( 'woocommerce_login_form_end', array( __CLASS__, 'login_link' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'order_link' ) );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_link' ), 20, 4 );
	}

	/**
	 * WooCommerce has the withdrawal form (11.1 and newer).
	 */
	public static function available() {
		return class_exists( '\Automattic\WooCommerce\Internal\OrderWithdrawal\OrderWithdrawalController' );
	}

	/**
	 * Tells whether WooCommerce's withdrawal feature is on.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return self::available() && 'yes' === get_option( self::FEATURE_OPTION );
	}

	/**
	 * The withdrawal form, with the order number filled in when an order is given.
	 *
	 * @param \WC_Order|null $order Order.
	 */
	public static function url( $order = null ) {
		$url = wc_get_endpoint_url( 'order-withdrawal', '', wc_get_page_permalink( 'myaccount' ) );

		return $order ? add_query_arg( self::ORDER_VAR, rawurlencode( $order->get_order_number() ), $url ) : $url;
	}

	/**
	 * Lets WordPress read the order number from the link.
	 *
	 * @param string[] $vars Public query vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::ORDER_VAR;

		return $vars;
	}

	/**
	 * The text of the withdrawal link.
	 *
	 * @return string
	 */
	public static function label() {
		return __( 'Withdraw from contract here', 'nashenski-commerce' );
	}

	/**
	 * An order whose withdrawal period runs: paid or on its way, and at most the set number of
	 * days after it was completed (delivered). Not when a withdrawal was already sent.
	 *
	 * @param mixed $order Order.
	 * @return bool
	 */
	public static function in_period( $order ) {
		if ( ! $order instanceof \WC_Order || ! $order->has_status( array( 'processing', 'on-hold', 'completed' ) ) || 'yes' === $order->get_meta( '_order_withdrawal_requested' ) ) {
			return false;
		}

		$completed = $order->get_date_completed();
		$days      = absint( nashenski_get_option( self::GROUP, 'days' ) );
		$days      = $days ? $days : 14;

		return ! $completed || time() <= $completed->getTimestamp() + $days * DAY_IN_SECONDS;
	}

	/**
	 * A page builder's editor or canvas (Bricks, Oxygen, Breakdance, Elementor, Etch, Beaver Builder, Divi,
	 * WPBakery, Brizy, Thrive) or the Customizer preview. The links Nashenski adds on its own stay out of
	 * them; a shortcode placed by hand still shows. Other builders: the filter.
	 */
	public static function in_builder() {
		$in = ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() )
			|| ( function_exists( 'bricks_is_builder_call' ) && bricks_is_builder_call() )
			|| ( defined( 'SHOW_CT_BUILDER' ) && SHOW_CT_BUILDER )
			|| is_customize_preview()
			|| ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor, \Elementor\Plugin::$instance->preview ) && ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() ) );

		// Each builder opens its editor and canvas with one of these in the address of the page (add_query_arg() gives that address).
		$args = array();
		wp_parse_str( (string) wp_parse_url( add_query_arg( array() ), PHP_URL_QUERY ), $args );
		$in = $in || array_intersect_key( $args, array_flip( array( 'bricks', 'brickspreview', 'ct_builder', 'oxygen_iframe', 'breakdance', 'breakdance_iframe', 'elementor-preview', 'fl_builder', 'et_fb', 'vc_editable', 'brizy-edit', 'brizy-edit-iframe', 'tve', 'etch' ) ) );

		return (bool) \Nashenski\Hooks::filter( 'nashenski_withdrawal_in_builder', 'nashenski/withdrawal/in_builder', $in );
	}

	/**
	 * The withdrawal link as escaped HTML.
	 *
	 * @param string $url    Form address.
	 * @param bool   $button Style as a button.
	 * @return string
	 */
	public static function link( $url, $button = false ) {
		self::style();

		return sprintf(
			'<a class="nashenski-withdrawal-link%1$s" href="%2$s">%3$s</a>',
			$button ? ' nashenski-withdrawal-link--button' : '',
			esc_url( $url ),
			esc_html( self::label() )
		);
	}

	/**
	 * [nashenski_withdrawal_link] or [nashenski_withdrawal_link button="yes"], for any page, footer
	 * or builder template.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'button' => 'no' ), $atts, 'nashenski_withdrawal_link' );

		return self::link( self::url(), wc_string_to_bool( $atts['button'] ) );
	}

	/**
	 * The link at the bottom of every page, so it is on view whatever the theme or builder.
	 */
	public static function footer_link() {
		if ( 'none' !== nashenski_get_option( self::GROUP, 'placement' ) && ! self::in_builder() ) {
			echo wp_kses_post( '<p class="nashenski-withdrawal-bar">' . self::link( self::url() ) . '</p>' );
		}
	}

	/**
	 * My Account > Orders: a button next to "View".
	 *
	 * @param array     $actions Order actions.
	 * @param \WC_Order $order   Order.
	 * @return array
	 */
	public static function order_action( $actions, $order ) {
		if ( self::in_period( $order ) ) {
			$actions['nashenski-withdraw'] = array(
				'url'  => self::url( $order ),
				'name' => self::label(),
			);
		}

		return $actions;
	}

	/**
	 * My Account for visitors who are not signed in (customers who ordered without an account):
	 * under the login form, WooCommerce's own or a builder's login form element.
	 */
	public static function login_link() {
		if ( is_account_page() && ! is_user_logged_in() && ! self::in_builder() ) {
			echo wp_kses_post( '<p class="nashenski-withdrawal-account">' . esc_html__( 'Ordered without an account?', 'nashenski-commerce' ) . ' ' . self::link( self::url(), true ) . '</p>' );
		}
	}

	/**
	 * Order view and order received pages.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function order_link( $order ) {
		if ( self::in_period( $order ) && ! self::in_builder() ) {
			echo wp_kses_post( '<p class="nashenski-withdrawal-order">' . self::link( self::url( $order ), true ) . '</p>' );
		}
	}

	/**
	 * Customer order emails.
	 *
	 * @param \WC_Order      $order         Order.
	 * @param bool           $sent_to_admin Whether the email goes to the admin.
	 * @param bool           $plain_text    Whether the email is plain text.
	 * @param \WC_Email|null $email         Email.
	 */
	public static function email_link( $order, $sent_to_admin, $plain_text, $email = null ) {
		if ( $sent_to_admin || ! $email || ! in_array( $email->id, self::EMAILS, true ) || ! self::in_period( $order ) ) {
			return;
		}

		if ( $plain_text ) {
			// Plain text has no HTML entities: this context keeps "&" as it is, so the link still works.
			echo "\n" . esc_html( self::label() ) . ': ' . esc_url( self::url( $order ), null, 'plain_text' ) . "\n";
		} else {
			echo wp_kses_post( '<p style="margin:16px 0">' . self::link( self::url( $order ) ) . '</p>' );
		}
	}

	/**
	 * The stylesheet, added once and only when a link or the form is output (link() calls it).
	 */
	private static function style() {
		if ( wp_style_is( 'nashenski-withdrawal', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( 'nashenski-withdrawal', nashenski()->plugin_dir_url() . 'assets/css/withdrawal.css', array(), \Nashenski\Plugin::VERSION );
		wp_add_inline_style( 'nashenski-withdrawal', wp_strip_all_tags( Checkout\Appearance::css() ) );
	}

	/**
	 * The form page: the site's look (other pages get it when a link is output); the form page also gets
	 * the order number from the link and, for signed-in customers, their name and email.
	 */
	public static function enqueue() {
		if ( ! is_wc_endpoint_url( 'order-withdrawal' ) ) {
			return;
		}

		self::style();

		$customer = is_user_logged_in() && WC()->customer ? WC()->customer : null;
		$fill     = array_filter(
			array(
				// A link in an email cannot carry a nonce (guests, days later); the number only prefills the form, which WooCommerce checks on submit.
				'order_number'       => sanitize_text_field( (string) get_query_var( self::ORDER_VAR ) ),
				'first_name'         => $customer ? $customer->get_billing_first_name() : '',
				'last_name'          => $customer ? $customer->get_billing_last_name() : '',
				'email'              => $customer ? $customer->get_billing_email() : '',
				'email_confirmation' => $customer ? $customer->get_billing_email() : '',
			)
		);

		// Printed in the footer, after the form, so it runs at once.
		wp_enqueue_script( 'nashenski-withdrawal', nashenski()->plugin_dir_url() . 'assets/js/withdrawal.js', array(), \Nashenski\Plugin::VERSION, true );
		wp_localize_script( 'nashenski-withdrawal', 'nashenskiWithdrawal', $fill );
	}

	/**
	 * Keep what the customer submitted on the order. WooCommerce adds its order note right after it
	 * accepted a withdrawal (emails sent, order marked), while the form is still in this request.
	 *
	 * @param int       $note_id Order note ID.
	 * @param \WC_Order $order   Order.
	 */
	public static function record( $note_id, $order ) {
		if ( ! isset( $_POST['woocommerce-order-withdrawal-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce-order-withdrawal-nonce'] ) ), 'woocommerce_order_withdrawal' ) ) {
			return;
		}

		if ( ! $order instanceof \WC_Order || 'yes' !== $order->get_meta( '_order_withdrawal_requested' ) || $order->get_meta( self::META ) ) {
			return;
		}

		$request = array( 'submitted' => self::submitted_at() );

		foreach ( array( 'first_name', 'last_name', 'email', 'order_number', 'withdrawal_type' ) as $key ) {
			$request[ $key ] = isset( $_POST[ 'order_withdrawal_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'order_withdrawal_' . $key ] ) ) : '';
		}

		$request['additional_details'] = isset( $_POST['order_withdrawal_additional_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['order_withdrawal_additional_details'] ) ) : '';
		$order->update_meta_data( self::META, $request );
		$order->save_meta_data();
	}

	/**
	 * The submission time WooCommerce put in its emails, so the order shows the same second as the
	 * customer's acknowledgement.
	 */
	private static function submitted_at() {
		foreach ( WC()->mailer()->get_emails() as $email ) {
			if ( in_array( $email->id, array( 'customer_order_withdrawal_requested', 'order_withdrawal_requested' ), true ) && ! empty( $email->submitted_at ) ) {
				return (int) $email->submitted_at;
			}
		}

		return time();
	}

	/**
	 * The withdrawal submitted for an order: submitted (timestamp), first_name, last_name, email,
	 * order_number, withdrawal_type, additional_details. Withdrawals sent before Nashenski Commerce
	 * kept them only have WooCommerce's order note: its date and type (from_note). Null when the
	 * order has none.
	 *
	 * @param \WC_Order $order Order.
	 * @return array|null
	 */
	public static function request( $order ) {
		if ( ! $order instanceof \WC_Order || 'yes' !== $order->get_meta( '_order_withdrawal_requested' ) ) {
			return null;
		}

		$request = $order->get_meta( self::META );

		if ( is_array( $request ) ) {
			return $request;
		}

		// WooCommerce's note "Order withdrawal requested. Withdrawal type: ..." in English or Bulgarian.
		$bg     = include nashenski()->plugin_dir_path() . 'app/Front_End/withdrawal-texts-bg.php';
		$format = 'Order withdrawal requested. Withdrawal type: %s.';

		foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) as $note ) {
			foreach ( array( $format, $bg[ $format ] ) as $text ) {
				$prefix = strstr( $text, '%s', true );

				if ( 0 === strpos( $note->content, $prefix ) ) {
					return array(
						'submitted' => $note->date_created->getTimestamp(),
						'type_text' => rtrim( substr( $note->content, strlen( $prefix ) ), '.' ),
						'from_note' => true,
					);
				}
			}
		}

		return array( 'from_note' => true );
	}

	/**
	 * The type of withdrawal as text.
	 *
	 * @param array $request Withdrawal.
	 * @return string
	 */
	private static function type_label( $request ) {
		$types = array(
			'full_order'          => __( 'The full order', 'nashenski-commerce' ),
			'specific_items_only' => __( 'Specific items only', 'nashenski-commerce' ),
		);

		return $request['type_text'] ?? ( $types[ $request['withdrawal_type'] ?? '' ] ?? '' );
	}

	/**
	 * The date and time of a withdrawal as text.
	 *
	 * @param array $request Withdrawal.
	 * @return string
	 */
	private static function date( $request ) {
		return empty( $request['submitted'] ) ? '' : wp_date( wc_date_format() . ' ' . wc_time_format(), $request['submitted'] );
	}

	/**
	 * The "Withdrawal requested" pill as escaped HTML.
	 *
	 * @return string
	 */
	private static function pill() {
		return '<mark class="nashenski-report-pill nashenski-withdrawal-pill"><span>' . esc_html__( 'Withdrawal requested', 'nashenski-commerce' ) . '</span></mark>';
	}

	/**
	 * Orders list: a "Withdrawal" column after the status.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$out = array();

		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;

			if ( 'order_status' === $key ) {
				$out['nashenski_withdrawal'] = __( 'Withdrawal', 'nashenski-commerce' );
			}
		}

		return $out;
	}

	/**
	 * Prints the cell of the "Withdrawal" column.
	 *
	 * @param string        $column Column key.
	 * @param \WC_Order|int $order  Order or its ID.
	 */
	public static function column( $column, $order ) {
		if ( 'nashenski_withdrawal' !== $column ) {
			return;
		}

		$request = self::request( $order instanceof \WC_Order ? $order : wc_get_order( $order ) );

		if ( ! $request ) {
			echo '<span class="nashenski-report-pill nashenski-report-pill--none">&ndash;</span>';
			return;
		}

		$details = (string) ( $request['additional_details'] ?? '' );

		echo wp_kses_post( self::pill() );
		printf(
			'<small class="nashenski-withdrawal-summary">%1$s<br>%2$s%3$s</small>',
			esc_html( self::date( $request ) ),
			esc_html( self::type_label( $request ) ),
			'' !== $details ? '<br><span title="' . esc_attr( $details ) . '">' . esc_html( wp_html_excerpt( $details, 60, '...' ) ) . '</span>' : ''
		);
	}

	/**
	 * Order screen: a pill under the customer, like the reports pill, pointing to the box below.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function order_pill( $order ) {
		$request = self::request( $order );

		if ( $request ) {
			printf(
				'<p class="form-field form-field-wide nashenski-reports-status">%1$s <a href="#nashenski_withdrawal">%2$s</a></p>',
				wp_kses_post( self::pill() ),
				esc_html(
					sprintf(
					/* translators: %s: date and time */
						__( 'The customer withdrew from the contract on %s. See the "Withdrawal from contract" box.', 'nashenski-commerce' ),
						self::date( $request )
					)
				)
			);
		}
	}

	/**
	 * Adds the box with the submitted withdrawal on the order screen.
	 */
	public static function add_meta_box() {
		$order = isset( $GLOBALS['theorder'] ) && $GLOBALS['theorder'] instanceof \WC_Order ? $GLOBALS['theorder'] : wc_get_order( get_the_ID() );

		if ( ! self::request( $order ) ) {
			return;
		}

		$screen = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';

		add_meta_box( 'nashenski_withdrawal', __( 'Withdrawal from contract', 'nashenski-commerce' ), array( __CLASS__, 'meta_box' ), $screen, 'normal', 'high' );
	}

	/**
	 * Everything the customer submitted.
	 *
	 * @param \WC_Order|\WP_Post $post_or_order Order or its post.
	 */
	public static function meta_box( $post_or_order ) {
		$order   = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		$request = self::request( $order );
		$details = $request['additional_details'] ?? '';
		$details = $details ? $details : __( 'None provided', 'nashenski-commerce' );
		$rows    = array(
			__( 'Submitted', 'nashenski-commerce' )     => self::date( $request ),
			__( 'Name', 'nashenski-commerce' )          => trim( ( $request['first_name'] ?? '' ) . ' ' . ( $request['last_name'] ?? '' ) ),
			__( 'Email address', 'nashenski-commerce' ) => $request['email'] ?? '',
			__( 'Order number', 'nashenski-commerce' )  => $request['order_number'] ?? '',
			__( 'Withdrawing', 'nashenski-commerce' )   => self::type_label( $request ),
			__( 'Additional details', 'nashenski-commerce' ) => empty( $request['from_note'] ) ? $details : '',
		);

		echo '<table class="widefat striped nashenski-withdrawal-details"><tbody>';

		foreach ( array_filter( $rows, 'strlen' ) as $label => $value ) {
			printf( '<tr><th scope="row">%1$s</th><td>%2$s</td></tr>', esc_html( $label ), nl2br( esc_html( $value ) ) );
		}

		echo '</tbody></table><p class="description">';

		echo esc_html(
			empty( $request['from_note'] )
			? __( 'The customer received an acknowledgement by email with these details and the date and time of submission.', 'nashenski-commerce' )
			: __( 'This withdrawal was sent before Nashenski Commerce kept the submitted details. The date and the choice come from the order note; the full details are in the emails WooCommerce sent to you and to the customer.', 'nashenski-commerce' )
		);

		echo '</p>';
	}

	/**
	 * Bulgarian for WooCommerce's withdrawal texts while its own language pack has none.
	 *
	 * @param string $translation Translation so far.
	 * @param string $text        Original text.
	 * @return string
	 */
	public static function translate( $translation, $text ) {
		static $bg = null;

		if ( $translation !== $text || 'bg_BG' !== determine_locale() ) {
			return $translation;
		}

		if ( null === $bg ) {
			$bg = include nashenski()->plugin_dir_path() . 'app/Front_End/withdrawal-texts-bg.php';
		}

		return $bg[ $text ] ?? $translation;
	}

	/**
	 * Checkout > EU withdrawal function.
	 */
	public static function settings() {
		$desc = __( 'Lets customers withdraw from an order online, as EU law asks from 19 June 2026 (Directive 2011/83/EU, Article 11a). The customer gets a dated email and so do you. Mention it in your terms.', 'nashenski-commerce' );

		if ( ! self::available() ) {
			return array(
				array(
					'type'  => 'title',
					'id'    => 'nashenski_withdrawal',
					'title' => __( 'EU withdrawal function', 'nashenski-commerce' ),
					'desc'  => $desc . ' <strong>' . esc_html__( 'Needs WooCommerce 11.1 or newer.', 'nashenski-commerce' ) . '</strong>',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'nashenski_withdrawal',
				),
			);
		}

		return array(
			array(
				'type'  => 'title',
				'id'    => 'nashenski_withdrawal',
				'title' => __( 'EU withdrawal function', 'nashenski-commerce' ),
				'desc'  => $desc,
			),
			array(
				'type'    => 'checkbox',
				'id'      => self::FEATURE_OPTION,
				'title'   => __( 'Withdrawal function', 'nashenski-commerce' ),
				'desc'    => __( 'Turn on the withdrawal form and the "Withdraw from contract here" links', 'nashenski-commerce' ),
				'default' => 'no',
			),
			array(
				'type'    => 'select',
				'id'      => 'nashenski_settings_' . self::GROUP . '_placement',
				'title'   => __( 'Link on every page', 'nashenski-commerce' ),
				'class'   => 'wc-enhanced-select',
				'css'     => 'width: 100%;',
				'default' => 'footer',
				'options' => array(
					'footer' => __( 'At the bottom of every page', 'nashenski-commerce' ),
					'none'   => __( 'I place it myself (menu, footer template or shortcode)', 'nashenski-commerce' ),
				),
				'desc'    => sprintf(
					/* translators: 1: shortcode, 2: address of the withdrawal form */
					__( 'Shortcode for any page or builder: %1$s (add button="yes" for a button). Address for menus: %2$s', 'nashenski-commerce' ),
					'<code>[nashenski_withdrawal_link]</code>',
					'<code>' . esc_html( self::url() ) . '</code>'
				),
			),
			array(
				'type'              => 'number',
				'id'                => 'nashenski_settings_' . self::GROUP . '_days',
				'title'             => __( 'Withdrawal period (days)', 'nashenski-commerce' ),
				'desc'              => __( 'The link shows on an order (My Account, order page, order emails) until this many days after the order is completed. The form itself is always open.', 'nashenski-commerce' ),
				'default'           => '14',
				'css'               => 'width: 6em;',
				'custom_attributes' => array(
					'min'  => 14,
					'step' => 1,
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'nashenski_withdrawal',
			),
		);
	}
}
