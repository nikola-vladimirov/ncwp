<?php
namespace Nashenski\Reports;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

class Reports {
	const CHECKOUT_SESSION_NAME = 'nashenski_reports_found';
    protected $providers = [];

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
    }

	public function init() {
		$this->providers = self::get_providers();

		if ( empty( $this->providers ) && empty( self::missing_logins() ) ) {
			return;
		}

        add_filter( 'woocommerce_package_rates', array( __CLASS__, 'hide_methods' ), 95, 2 );
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
		'connectix' => array( 'token', Providers\Connectix::class ),
		'nepostop' => array( 'api_key', Providers\NepoStop::class ),
		'nekorekten' => array( 'api_key', Providers\Nekorekten::class ),
	);

	/**
	 * Services that are turned on, split by whether their login is filled in. Without a login they
	 * are not asked: an empty answer would read like "no reports".
	 *
	 * @return Providers\Provider_Base[][] array( 'ready' => ..., 'missing' => ... )
	 */
	private static function services() {
		$services = array( 'ready' => array(), 'missing' => array() );

		foreach ( self::SERVICES as $slug => list( $login, $class ) ) {
			if ( 'yes' === nashenski_get_option( 'reports', 'enable_' . $slug ) ) {
				$services[ '' === trim( (string) nashenski_get_option( $slug, $login ) ) ? 'missing' : 'ready' ][] = new $class();
			}
		}

		return $services;
	}

	/**
	 * Names of the services that are turned on without a login.
	 */
	public static function missing_logins() {
		return array_map( function ( $provider ) {
			return $provider->get_name();
		}, self::services()['missing'] );
	}

	public static function get_providers() {
        $providers = self::services()['ready'];
        
        $providers = array_filter( apply_filters( 'nashenski/reports/providers', $providers ), function ( $provider ) {
            return ( is_a( $provider, 'Nashenski\Reports\Providers\Provider_Base' ) );
        } );

        return $providers;        
    }

    public static function add_meta_boxes() {
		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? array( wc_get_page_screen_id( 'shop-order' ), wc_get_page_screen_id( 'shop_subscription' ) )
		: array( 'shop_order', 'shop_subscription' );

		$screen = array_filter( $screen );

		add_meta_box( 'nashenski_customer_reports', esc_html__( 'Reports', 'nashenski-commerce' ), array( __CLASS__, 'meta_box' ), $screen, 'normal', 'default' );
	}

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
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'nashenski--reports-refresh', 1 ), 'nashenski_reports_refresh', 'nashenski_reports_nonce' ) ); ?>"><?php esc_html_e( 'Refresh data', 'nashenski-commerce' ) ?></a>

				<?php if ( self::missing_logins() ) : ?>
					<div class="nashenski--report-provider">
						<h3><?php echo esc_html( implode( ', ', self::missing_logins() ) ); ?></h3>
						<p><?php
						echo wp_kses_post( sprintf(
							/* translators: %s: link to the Customer checks settings */
							__( 'Not checked: turned on without an API key or token. Add it in %s, or turn the service off there.', 'nashenski-commerce' ),
							'<a href="' . esc_url( \Nashenski\Admin\Settings::url( 'checks' ) ) . '">' . esc_html__( 'Customer checks', 'nashenski-commerce' ) . '</a>'
						) );
						?></p>
					</div>
				<?php endif; ?>

                <?php foreach ( self::get_providers() as $provider ): ?>
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

    public static function customer_status_info( $order ) {
		$refresh = false;

		// "Refresh data" asks the services again (it carries its own nonce).
		if ( isset( $_GET['nashenski--reports-refresh'], $_GET['nashenski_reports_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nashenski_reports_nonce'] ) ), 'nashenski_reports_refresh' ) && current_user_can( 'manage_woocommerce' ) ) {
			$refresh = true;
		}
		$reports = self::get_all_reports( $order, $refresh );
		?>
		<p class="form-field form-field-wide nashenski-reports-status">
			<?php echo self::pill( count( $reports ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill(). ?>
			<a href="#nashenski_customer_reports">
				<?php
				echo esc_html( count( $reports )
					? __( 'Customer has reports for refused parcels. See the Reports box.', 'nashenski-commerce' )
					: __( 'No reports for this customer.', 'nashenski-commerce' ) );
				?>
			</a>
		</p>
		<?php
	}

	/**
	 * Status pill in the WooCommerce order status style: grey when nothing was found, red with the
	 * number of reports otherwise, a dash when the order was never checked.
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
    public static function set_customer_status_info( $order_id ) {
		$order = wc_get_order( $order_id );

		self::get_all_reports( $order, 1 );
	}

    public static function get_all_reports( $order, $force = false ) {
        $providers = self::get_providers();
        $all_reports = [];

        foreach ( $providers as $provider ) {
            $provider_reports = $provider::get_all_reports( $order, $force );
            $all_reports = array_merge( $all_reports, $provider_reports['reports'] );
        }

        return $all_reports;
    }

    public static function get_all_reports_from_meta( $order ) {
        $providers = self::get_providers();
        $all_reports = [];

        foreach ( $providers as $provider ) {
            $provider_reports = $provider::get_all_reports_from_meta( $order );
			if ( isset( $provider_reports['reports'] ) ) {
				$all_reports = array_merge( $all_reports, $provider_reports['reports'] );
			}
        }

        return $all_reports;
    }

    public static function add_order_list_column( $columns ) {
		$reordered_columns = array();

	    foreach( $columns as $key => $column){
	        $reordered_columns[ $key ] = $column;

	        if( $key ==  'shipping_address' ){
	            $reordered_columns[ 'order_reports' ] = esc_html__( 'Reports', 'nashenski-commerce' );
	        }
	    }

	    return $reordered_columns;
	}

	public static function add_order_list_column_content( $column, $post_id ) {
		if ( 'order_reports' !== $column ) {
			return;
		}

		$order = wc_get_order( $post_id );

		if ( ! $order ) {
			return;
		}

		$checked = false;
		$count = 0;

		foreach ( self::get_providers() as $provider ) {
			$found = $provider::get_all_reports_from_meta( $order );

			if ( isset( $found['reports'] ) && is_array( $found['reports'] ) ) {
				$checked = true;
				$count += count( $found['reports'] );
			}
		}

		echo self::pill( $count, $checked ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in pill().
	}
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
	    	unset(  $available_gateways['cod'] );
		}
	     
	    return $available_gateways;
	}

	/**
	 * filter_payment_methods() only hides cash on delivery in the order review; the order itself
	 * (classic checkout, and the block checkout through Block_Checkout::validate()) is checked here.
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
	 */
	private static function reports_for( $provider, $phone ) {
		$cache = WC()->session->get( self::CHECKOUT_SESSION_NAME );
		$cache = is_array( $cache ) ? $cache : array();
		$phone_key = md5( $phone );
		$class = get_class( $provider );

		if ( ! isset( $cache[ $phone_key ][ $class ] ) ) {
			$cache[ $phone_key ][ $class ] = (array) $provider::check_for_reports_on_checkout( $phone );
			WC()->session->set( self::CHECKOUT_SESSION_NAME, $cache );
		}

		return $cache[ $phone_key ][ $class ];
	}

	/**
	 * The phone of the checkout: the billing phone, or the cart customer's for the block checkout.
	 */
	public static function checkout_phone() {
		$data = nashenski_order_review_data();
		$phone = $data['billing_phone'] ?? '';

		// Block checkout: the Store API keeps the phone on the cart's customer.
		if ( '' === $phone && WC()->customer && method_exists( WC(), 'is_store_api_request' ) && WC()->is_store_api_request() ) {
			$phone = (string) WC()->customer->get_billing_phone();
		}

		return (string) $phone;
	}

	/**
	 * Hides the rates of the methods a service lists, when that service flags the checkout phone
	 * (its own level of warning). Each service's list is used only while that service is on.
	 */
	public static function hide_methods( $rates, $package ) {
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! WC()->session ) {
			return $rates;
		}

		$phone = self::checkout_phone();

		if ( '' === $phone ) {
			return $rates;
		}

		$hidden = array();

		foreach ( self::SERVICES as $slug => list( $login, $class ) ) {
			$methods = self::hidden_methods( $slug );

			if ( ! $methods || 'yes' !== nashenski_get_option( 'reports', 'enable_' . $slug ) || '' === trim( (string) nashenski_get_option( $slug, $login ) ) ) {
				continue;
			}

			if ( self::reports_for( new $class(), $phone ) ) {
				$hidden = array_merge( $hidden, $methods );
			}
		}

		return $hidden ? array_diff_key( $rates, array_flip( $hidden ) ) : $rates;
	}

	/**
	 * The method keys ("method_id:instance_id") a service hides.
	 */
	public static function hidden_methods( $slug ) {
		$methods = json_decode( (string) nashenski_get_option( $slug, 'hide_methods' ), true );

		return is_array( $methods ) ? array_values( $methods ) : array();
	}

	/**
	 * Shipping methods turned on in the shipping zones, and in the rest of the world:
	 * "method_id:instance_id" => "Zone - Method title".
	 */
	public static function shipping_method_choices() {
		$choices = array();
		$zones = \WC_Shipping_Zones::get_zones();
		$zones[] = array( 'zone_name' => __( 'Rest of the world', 'woocommerce' ), 'shipping_methods' => ( new \WC_Shipping_Zone( 0 ) )->get_shipping_methods( true ) );

		foreach ( $zones as $zone ) {
			foreach ( $zone['shipping_methods'] ?? array() as $method ) {
				if ( 'yes' === $method->enabled ) {
					$choices[ $method->id . ':' . $method->instance_id ] = $zone['zone_name'] . ' - ' . $method->get_title();
				}
			}
		}

		return $choices;
	}

	public static function unset_session() {
		WC()->session->__unset( self::CHECKOUT_SESSION_NAME );
	}

}