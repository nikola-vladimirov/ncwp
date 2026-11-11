<?php
/**
 * Customer reports.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Reports;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Customer checks: asks the turned-on services about refused parcels and shows or acts on the reports.
 */
class Reports {
	const CHECKOUT_SESSION_NAME = 'nashenski_reports_found';
	const LOOKUPS_PER_HOUR      = 20;

	/**
	 * Services that are on and have a login.
	 *
	 * @var array
	 */
	protected $providers = array();

	/**
	 * Registers the init hook.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Registers the hooks when a service is on.
	 */
	public function init() {
		$this->providers = self::get_providers();

		if ( empty( $this->providers ) && empty( self::missing_logins() ) ) {
			return;
		}

		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'hide_payment_methods' ), 20 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( Providers\Nekorekten::class, 'handle_form' ), 70 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'customer_status_info' ) );

		if ( nashenski_get_option( 'reports', 'column' ) === 'yes' ) {
			add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'set_customer_status_info' ) );

			add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_order_list_column' ), 11 );
			add_filter( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'add_order_list_column_content' ), 11, 2 );

			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'add_order_list_column' ), 11 );
			add_filter( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'add_order_list_column_content' ), 11, 2 );
		}

		if ( nashenski_get_option( 'reports', 'check_on_checkout' ) === 'yes' ) {
			add_action( 'woocommerce_available_payment_gateways', array( __CLASS__, 'filter_payment_methods' ) );
			add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_cod' ), 10, 2 );
			add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'unset_session' ), 30 );
		}
	}

	/**
	 * Customer check services: slug => array( option that holds the login, provider class ).
	 */
	const SERVICES = array(
		'connectix'  => array( 'token', Providers\Connectix::class ),
		'nepostop'   => array( 'api_key', Providers\NepoStop::class ),
		'nekorekten' => array( 'api_key', Providers\Nekorekten::class ),
	);

	/**
	 * Services that are turned on, split by whether their login is filled in. Without a login they
	 * are not asked: an empty answer would read like "no reports".
	 *
	 * @return Providers\Provider_Base[][] array( 'ready' => ..., 'missing' => ... )
	 */
	private static function services() {
		$services = array(
			'ready'   => array(),
			'missing' => array(),
		);

		foreach ( self::SERVICES as $slug => list( $login, $class ) ) {
			if ( 'yes' === nashenski_get_option( 'reports', 'enable_' . $slug ) ) {
				$group                = '' === trim( (string) nashenski_get_option( $slug, $login ) ) ? 'missing' : 'ready';
				$services[ $group ][] = new $class();
			}
		}

		return $services;
	}

	/**
	 * Names of the services that are turned on without a login.
	 */
	public static function missing_logins() {
		return array_map(
			function ( $provider ) {
				return $provider->get_name();
			},
			self::services()['missing']
		);
	}

	/**
	 * Services that are on and have a login, after the providers filter.
	 *
	 * @return Providers\Provider_Base[]
	 */
	public static function get_providers() {
		$providers = self::services()['ready'];

		$providers = array_filter(
			\Nashenski\Hooks::filter( 'nashenski_reports_providers', 'nashenski/reports/providers', $providers ),
			function ( $provider ) {
				return ( is_a( $provider, 'Nashenski\Reports\Providers\Provider_Base' ) );
			}
		);

		return $providers;
	}

	/**
	 * Adds the Reports meta box to the order screen.
	 */
	public static function add_meta_boxes() {
		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? array( wc_get_page_screen_id( 'shop-order' ), wc_get_page_screen_id( 'shop_subscription' ) )
		: array( 'shop_order', 'shop_subscription' );

		$screen = array_filter( $screen );

		add_meta_box( 'nashenski_customer_reports', esc_html__( 'Reports', 'nashenski-commerce' ), array( __CLASS__, 'meta_box' ), $screen, 'normal', 'default' );
	}

	/**
	 * Prints the Reports meta box.
	 */
	public static function meta_box() {
		$theorder = isset( $GLOBALS['theorder'] ) ? $GLOBALS['theorder'] : null;

		if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$post = get_post( absint( nashenski_query_arg( 'id' ) ) );
		} else {
			global $post;
		}

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $post->ID );
		}
		?>
		<div class="panel-wrap woocommerce">
			<input name="post_title" type="hidden" value="<?php echo empty( $post->post_title ) ? esc_html__( 'Order', 'nashenski-commerce' ) : esc_attr( $post->post_title ); ?>" />
			<input name="post_status" type="hidden" value="<?php echo esc_attr( $post->post_status ); ?>" />

			<div id="order_data" class="panel woocommerce-order-data">
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'nashenski--reports-refresh', 1 ), 'nashenski_reports_refresh', 'nashenski_reports_nonce' ) ); ?>"><?php esc_html_e( 'Refresh data', 'nashenski-commerce' ); ?></a>

				<?php if ( self::missing_logins() ) : ?>
					<div class="nashenski--report-provider">
						<h3><?php echo esc_html( implode( ', ', self::missing_logins() ) ); ?></h3>
						<p>
						<?php
						echo wp_kses_post(
							sprintf(
							/* translators: %s: link to the Customer checks settings */
								__( 'Not checked: turned on without an API key or token. Add it in %s, or turn the service off there.', 'nashenski-commerce' ),
								'<a href="' . esc_url( \Nashenski\Admin\Settings::url( 'checks' ) ) . '">' . esc_html__( 'Customer checks', 'nashenski-commerce' ) . '</a>'
							)
						);
						?>
						</p>
					</div>
				<?php endif; ?>

				<?php foreach ( self::get_providers() as $provider ) : ?>
					<div class="nashenski--report-provider">
						<h3><?php echo esc_html( $provider->get_name() ); ?></h3>

						<?php $provider->print_reports_html( $theorder ); ?>
					</div><!-- /.order_data_column_container -->
				<?php endforeach; ?>
			</div>
		</div>

		<div class="clear"></div>
		<?php
	}

	/**
	 * Prints the report status under the order details.
	 *
	 * @param \WC_Order $order Order.
	 */
	public static function customer_status_info( $order ) {
		$refresh = false;

		// "Refresh data" asks the services again (it carries its own nonce).
		if ( isset( $_GET['nashenski--reports-refresh'], $_GET['nashenski_reports_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nashenski_reports_nonce'] ) ), 'nashenski_reports_refresh' ) && current_user_can( 'manage_woocommerce' ) ) {
			$refresh = true;
		}
		$reports = self::get_all_reports( $order, $refresh );
		?>
		<p class="form-field form-field-wide nashenski-reports-status">
			<?php echo wp_kses_post( self::pill( count( $reports ), true ) ); ?>
			<a href="#nashenski_customer_reports">
				<?php
				echo esc_html(
					count( $reports )
					? __( 'Customer has reports for refused parcels. See the Reports box.', 'nashenski-commerce' )
					: __( 'No reports for this customer.', 'nashenski-commerce' )
				);
				?>
			</a>
		</p>
		<?php
	}

	/**
	 * Status pill in the WooCommerce order status style: grey when nothing was found, red with the
	 * number of reports otherwise, a dash when the order was never checked.
	 *
	 * @param int  $count   Number of reports.
	 * @param bool $checked Whether the order was checked.
	 * @return string HTML.
	 */
	public static function pill( $count, $checked ) {
		if ( ! $checked ) {
			return '<span class="nashenski-report-pill nashenski-report-pill--none" aria-label="' . esc_attr__( 'Not checked', 'nashenski-commerce' ) . '">&ndash;</span>';
		}

		if ( ! $count ) {
			return '<mark class="nashenski-report-pill nashenski-report-pill--clean"><span>' . esc_html__( 'No reports', 'nashenski-commerce' ) . '</span></mark>';
		}

		/* translators: %d: number of reports */
		$text = sprintf( _n( '%d report', '%d reports', $count, 'nashenski-commerce' ), $count );

		return '<mark class="nashenski-report-pill nashenski-report-pill--found"><span>' . esc_html( $text ) . '</span></mark>';
	}

	/**
	 * Checks the customer of a new order.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function set_customer_status_info( $order_id ) {
		$order = wc_get_order( $order_id );

		self::get_all_reports( $order, 1 );
	}

	/**
	 * Reports of all services for an order.
	 *
	 * @param \WC_Order $order Order.
	 * @param bool      $force Whether to ask the services again.
	 * @return array
	 */
	public static function get_all_reports( $order, $force = false ) {
		$providers   = self::get_providers();
		$all_reports = array();

		foreach ( $providers as $provider ) {
			$provider_reports = $provider::get_all_reports( $order, $force );
			$all_reports      = array_merge( $all_reports, $provider_reports['reports'] );
		}

		return $all_reports;
	}

	/**
	 * Saved reports of all services for an order.
	 *
	 * @param \WC_Order $order Order.
	 * @return array
	 */
	public static function get_all_reports_from_meta( $order ) {
		$providers   = self::get_providers();
		$all_reports = array();

		foreach ( $providers as $provider ) {
			$provider_reports = $provider::get_all_reports_from_meta( $order );
			if ( isset( $provider_reports['reports'] ) ) {
				$all_reports = array_merge( $all_reports, $provider_reports['reports'] );
			}
		}

		return $all_reports;
	}

	/**
	 * Adds the Reports column after the shipping address.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function add_order_list_column( $columns ) {
		$reordered_columns = array();

		foreach ( $columns as $key => $column ) {
			$reordered_columns[ $key ] = $column;

			if ( 'shipping_address' === $key ) {
				$reordered_columns['order_reports'] = esc_html__( 'Reports', 'nashenski-commerce' );
			}
		}

		return $reordered_columns;
	}

	/**
	 * Prints the Reports column.
	 *
	 * @param string              $column  Column key.
	 * @param int|\WC_Order|mixed $post_id Order ID, or the order on the HPOS screen.
	 */
	public static function add_order_list_column_content( $column, $post_id ) {
		if ( 'order_reports' !== $column ) {
			return;
		}

		$order = wc_get_order( $post_id );

		if ( ! $order ) {
			return;
		}

		$checked = false;
		$count   = 0;

		foreach ( self::get_providers() as $provider ) {
			$found = $provider::get_all_reports_from_meta( $order );

			if ( isset( $found['reports'] ) && is_array( $found['reports'] ) ) {
				$checked = true;
				$count  += count( $found['reports'] );
			}
		}

		echo wp_kses_post( self::pill( $count, $checked ) );
	}

	/**
	 * Hides cash on delivery in the order review when the checkout phone has reports.
	 *
	 * @param array $available_gateways Payment gateways.
	 * @return array
	 */
	public static function filter_payment_methods( $available_gateways ) {
		if ( is_admin() || empty( $available_gateways['cod'] ) ) {
			return $available_gateways;
		}

		$phone = self::checkout_phone();

		if ( '' === $phone ) {
			return $available_gateways;
		}

		$reports_by_phone = self::check_in_session( $phone );

		if ( is_array( $reports_by_phone ) && count( $reports_by_phone ) > 0 ) {
			unset( $available_gateways['cod'] );
		}

		return $available_gateways;
	}

	/**
	 * Refuses cash on delivery for a checkout phone with reports.
	 *
	 * The filter_payment_methods() method only hides cash on delivery in the order review; the order itself
	 * (classic checkout, and the block checkout through Block_Checkout::validate()) is checked here.
	 *
	 * @param array     $data   Posted checkout data.
	 * @param \WP_Error $errors Checkout errors.
	 */
	public static function validate_cod( $data, $errors ) {
		$phone = (string) ( $data['billing_phone'] ?? '' );

		if ( 'cod' !== ( $data['payment_method'] ?? '' ) || '' === $phone || ! WC()->session ) {
			return;
		}

		$reports_by_phone = self::check_in_session( $phone );

		if ( is_array( $reports_by_phone ) && count( $reports_by_phone ) > 0 ) {
			WC()->session->set( 'refresh_totals', true );
			$errors->add( 'validation', __( 'Cash on delivery is not available for this order. Please choose another payment method.', 'nashenski-commerce' ) );
		}
	}

	/**
	 * Reports the customer's phone has, from every service that is on.
	 *
	 * @param string $phone Phone number.
	 * @return array
	 */
	public static function check_in_session( $phone ) {
		$found = array();

		foreach ( self::get_providers() as $provider ) {
			$found = array_merge( $found, self::reports_for( $provider, $phone ) );
		}

		return $found;
	}

	/**
	 * Reports one service has about the phone. Asked once per session and phone, so the checkout
	 * does not call the service again on every price update.
	 *
	 * @param Providers\Provider_Base $provider Service.
	 * @param string                  $phone    Phone number.
	 * @return array
	 */
	private static function reports_for( $provider, $phone ) {
		$cache     = WC()->session->get( self::CHECKOUT_SESSION_NAME );
		$cache     = is_array( $cache ) ? $cache : array();
		$phone_key = md5( $phone );
		$class     = get_class( $provider );

		if ( ! isset( $cache[ $phone_key ][ $class ] ) ) {
			if ( ! self::allow_lookup() ) {
				return array();
			}

			$cache[ $phone_key ][ $class ] = (array) $provider::check_for_reports_on_checkout( $phone );
			WC()->session->set( self::CHECKOUT_SESSION_NAME, $cache );
		}

		return $cache[ $phone_key ][ $class ];
	}

	/**
	 * Paid lookups per visitor address: at most LOOKUPS_PER_HOUR an hour, so the checkout cannot be used to
	 * test other people's phone numbers. Over the limit the lookup is skipped and the checkout goes on.
	 * shortcut: a transient per IP, not atomic under parallel requests, upgrade if abused.
	 */
	private static function allow_lookup() {
		$key  = 'nashenski_lookups_' . md5( \WC_Geolocation::get_ip_address() );
		$used = (int) get_transient( $key );

		if ( $used >= self::LOOKUPS_PER_HOUR ) {
			return false;
		}

		set_transient( $key, $used + 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * The phone of the checkout: the billing phone, or the cart customer's for the block checkout.
	 */
	public static function checkout_phone() {
		$data  = nashenski_order_review_data( array( 'billing_phone' ) );
		$phone = $data['billing_phone'] ?? '';

		// Block checkout: the Store API keeps the phone on the cart's customer.
		if ( '' === $phone && WC()->customer && method_exists( WC(), 'is_store_api_request' ) && WC()->is_store_api_request() ) {
			$phone = (string) WC()->customer->get_billing_phone();
		}

		return (string) $phone;
	}

	/**
	 * Hides the payment methods a service lists, when that service flags the checkout phone (its own
	 * level of warning). Each service's list is used only while that service is on.
	 *
	 * @param array $gateways Payment gateways.
	 * @return array
	 */
	public static function hide_payment_methods( $gateways ) {
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! WC()->session ) {
			return $gateways;
		}

		$phone = self::checkout_phone();

		if ( '' === $phone ) {
			return $gateways;
		}

		$hidden = array();

		foreach ( self::SERVICES as $slug => list( $login, $class ) ) {
			$payments = self::hidden_payments( $slug );

			if ( ! $payments || 'yes' !== nashenski_get_option( 'reports', 'enable_' . $slug ) || '' === trim( (string) nashenski_get_option( $slug, $login ) ) ) {
				continue;
			}

			if ( self::reports_for( new $class(), $phone ) ) {
				$hidden = array_merge( $hidden, $payments );
			}
		}

		return $hidden ? array_diff_key( $gateways, array_flip( $hidden ) ) : $gateways;
	}

	/**
	 * The payment method IDs a service hides.
	 *
	 * @param string $slug Service slug.
	 * @return array
	 */
	public static function hidden_payments( $slug ) {
		$payments = json_decode( (string) nashenski_get_option( $slug, 'hide_payments' ), true );

		return is_array( $payments ) ? array_values( $payments ) : array();
	}

	/**
	 * Payment methods turned on in WooCommerce: gateway ID => title.
	 */
	public static function payment_method_choices() {
		$choices = array();

		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
			if ( 'yes' === $gateway->enabled ) {
				$choices[ $id ] = $gateway->get_title();
			}
		}

		return $choices;
	}

	/**
	 * Forgets the checkout lookups once the order is placed.
	 */
	public static function unset_session() {
		WC()->session->__unset( self::CHECKOUT_SESSION_NAME );
	}
}