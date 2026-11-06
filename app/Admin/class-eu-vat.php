<?php
/**
 * Eu vat.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

use Nashenski\Admin\Fields;
use Nashenski\Front_End\Checkout\EU_Vat as Front_End_EU_Vat;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

defined( 'ABSPATH' ) || exit;

/**
 * Eu vat.
 */
class EU_Vat {
	/**
	 * Tax classes.
	 *
	 * @var mixed
	 */
	protected $tax_classes;

	/**
	 * EU countries.
	 *
	 * @var mixed
	 */
	protected $eu_countries;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'nashenski_admin_nra_fields', array( __CLASS__, 'add_fields_in_settings' ) );
		add_action( 'woocommerce_admin_billing_fields', array( __CLASS__, 'admin_billing_fields' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );

		if ( ! nashenski_get_option( 'invoice', 'digital_tax_classes' ) ) {
			nashenski_set_option( 'invoice', 'digital_tax_classes', 'standard' );
		}
	}

	/**
	 * Add fields in settings.
	 *
	 * @param mixed $fields Fields.
	 * @return mixed
	 */
	public static function add_fields_in_settings( $fields ) {
		if ( extension_loaded( 'soap' ) ) {
			$fields['invoice'][] = new Fields\TrueFalse_Field( 'enable_vies', __( 'Enable VIES validation', 'nashenski-commerce' ), null, null, __( 'Validate the VAT number with the European VIES system or only with regex.', 'nashenski-commerce' ) );
		}

		$fields['invoice'][] = new Fields\Select_Field( nashenski_get_tax_classes(), 'digital_tax_classes', __( 'Tax Classes for Digital Goods', 'nashenski-commerce' ), null, null, __( 'This option tells the plugin which of your tax classes are for digital goods. This affects the taxable location of the user as of 1st Jan 2015.', 'nashenski-commerce' ) );

		return $fields;
	}

	/**
	 * Admin billing fields.
	 *
	 * @param mixed $fields Fields.
	 * @return mixed
	 */
	public static function admin_billing_fields( $fields ) {
		$theorder = isset( $GLOBALS['theorder'] ) ? $GLOBALS['theorder'] : null;

		$vat_number = is_object( $theorder ) ? nashenski_get_vat_from_order( $theorder ) : '';

		$fields['vat_number'] = array(
			'label' => __( 'VAT number', 'nashenski-commerce' ),
			'show'  => false,
			'id'    => '_billing_vat_number',
			'value' => $vat_number,
		);

		return $fields;
	}

	/**
	 * Add meta boxes.
	 */
	public static function add_meta_boxes() {
		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? array( wc_get_page_screen_id( 'shop-order' ), wc_get_page_screen_id( 'shop_subscription' ) )
		: array( 'shop_order', 'shop_subscription' );

		add_meta_box( 'nashenski_eu_vat', __( 'EU VAT', 'nashenski-commerce' ), array( __CLASS__, 'output_meta_box' ), array( 'shop_order', 'shop_subscription' ), 'side' );
	}

	/**
	 * Is eu order.
	 *
	 * @param mixed $order Order.
	 * @return mixed
	 */
	protected static function is_eu_order( $order ) {
		return in_array( $order->get_billing_country(), Front_End_EU_Vat::get_eu_countries(), true );
	}

	/**
	 * Output meta box.
	 */
	public static function output_meta_box() {
		global $post;
		$theorder = isset( $GLOBALS['theorder'] ) ? $GLOBALS['theorder'] : null;

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $post->ID );
		}

		// We only need this box for EU orders.
		if ( ! self::is_eu_order( $theorder ) ) {
			echo wp_kses_post( wpautop( __( 'This order is out of scope for EU VAT.', 'nashenski-commerce' ) ) );
			return;
		}

		$data      = self::get_order_vat_data( $theorder );
		$countries = WC()->countries->get_countries();
		?>
		<table class="wc-eu-vat-table" cellspacing="0">
			<tbody>
				<?php if ( $data->vat_number ) : ?>
					<tr>
						<th><?php echo wp_kses_post( __( 'VAT number', 'nashenski-commerce' ) ); ?></th>
						<td><?php echo esc_html( $data->vat_number ); ?></td>
						<td>
						<?php
						if ( ! $data->validated ) {
							echo wp_kses_post( '<span class="tips" data-tip="' . wc_sanitize_tooltip( __( 'Validation was not possible', 'nashenski-commerce' ) ) . '">?<span>' );
						} else {
							echo $data->valid ? '&#10004;' : '&#10008;';
						}
						?>
						</td>
					</tr>
				<?php else : ?>
					<tr>
						<th><?php esc_html_e( 'IP Address', 'nashenski-commerce' ); ?></th>
						<td><?php echo $data->ip_address ? esc_html( $data->ip_address ) : esc_html__( 'Unknown', 'nashenski-commerce' ); ?></td>
						<td></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'IP Country', 'nashenski-commerce' ); ?></th>
						<td>
						<?php
						if ( $data->ip_country ) {
							echo esc_html( $countries[ $data->billing_country ] ?? $data->billing_country ) . ' ';

							if ( $data->billing_country === $data->ip_country ) {
								echo '<span style="color:green">&#10004;</span>';
							} elseif ( $data->self_declared ) {
								esc_html_e( '(self-declared)', 'nashenski-commerce' );
							} else {
								echo '<span style="color:red">&#10008;</span>';
							}
						} else {
							esc_html_e( 'Unknown', 'nashenski-commerce' );
						}
						?>
						<td></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Billing Country', 'nashenski-commerce' ); ?></th>
						<td><?php echo $data->billing_country ? esc_html( $countries[ $data->billing_country ] ) : esc_html__( 'Unknown', 'nashenski-commerce' ); ?></td>
						<td></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get order vat data.
	 *
	 * @param mixed $order Order.
	 * @return mixed
	 */
	protected static function get_order_vat_data( $order ) {
		return (object) array(
			'vat_number'      => nashenski_get_vat_from_order( $order ),
			'valid'           => wc_string_to_bool( $order->get_meta( '_vat_number_is_valid', true ) ),
			'validated'       => wc_string_to_bool( $order->get_meta( '_vat_number_is_validated', true ) ),
			'billing_country' => $order->get_billing_country(),
			'ip_address'      => $order->get_customer_ip_address(),
			'ip_country'      => $order->get_meta( '_customer_ip_country', true ),
			'self_declared'   => wc_string_to_bool( $order->get_meta( '_customer_self_declared_country', true ) ),
		);
	}
}
