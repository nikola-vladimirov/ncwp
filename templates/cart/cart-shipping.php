<?php
/**
 * Shipping methods in the cart and checkout totals, with the options on their own full-width row
 * (Checkout > "Alternative shipping options layout"). Based on WooCommerce's cart/cart-shipping.php 3.6.0.
 *
 * Variables from WooCommerce: $package, $available_methods, $show_package_details,
 * $show_shipping_calculator, $package_details, $package_name, $index, $chosen_method,
 * $formatted_destination, $has_calculated_shipping.
 *
 * The do_action() and apply_filters() calls below are WooCommerce's own hooks; themes and plugins
 * (and Nashenski Commerce's office pickers) depend on them, so they keep their names.
 *
 * @package Nashenski\Templates
 */

defined( 'ABSPATH' ) || exit;

$nashenski_destination = isset( $formatted_destination ) ? $formatted_destination : WC()->countries->get_formatted_address( $package['destination'], ', ' );
$nashenski_calculated = ! empty( $has_calculated_shipping );
$nashenski_show_calculator = ! empty( $show_shipping_calculator );
$nashenski_calculator_text = '';
?>
<tr class="woocommerce-shipping-totals shipping nashenski--shipping-title">
	<th colspan="2"><?php echo wp_kses_post( $package_name ); ?></th>
</tr>

<tr class="nashenski--shipping-methods">
	<td colspan="2" data-title="<?php echo esc_attr( $package_name ); ?>">
		<?php if ( $available_methods ) : ?>
			<ul id="shipping_method" class="woocommerce-shipping-methods">
				<?php foreach ( $available_methods as $nashenski_method ) : ?>
					<li>
						<?php
						if ( 1 < count( $available_methods ) ) {
							printf( '<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />', esc_attr( $index ), esc_attr( sanitize_title( $nashenski_method->id ) ), esc_attr( $nashenski_method->id ), checked( $nashenski_method->id, $chosen_method, false ) );
						} else {
							printf( '<input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" />', esc_attr( $index ), esc_attr( sanitize_title( $nashenski_method->id ) ), esc_attr( $nashenski_method->id ) );
						}

						printf( '<label for="shipping_method_%1$s_%2$s">%3$s</label>', esc_attr( $index ), esc_attr( sanitize_title( $nashenski_method->id ) ), wp_kses_post( wc_cart_totals_shipping_method_label( $nashenski_method ) ) );
						do_action( 'woocommerce_after_shipping_rate', $nashenski_method, $index ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
						?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( is_cart() ) : ?>
				<p class="woocommerce-shipping-destination">
					<?php
					if ( $nashenski_destination ) {
						/* translators: %s: shipping destination */
						echo wp_kses_post( sprintf( esc_html__( 'Shipping to %s.', 'nashenski-commerce' ) . ' ', '<strong>' . esc_html( $nashenski_destination ) . '</strong>' ) );
						$nashenski_calculator_text = esc_html__( 'Change address', 'nashenski-commerce' );
					} else {
						echo wp_kses_post( apply_filters( 'woocommerce_shipping_estimate_html', __( 'Shipping options will be updated during checkout.', 'nashenski-commerce' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
					}
					?>
				</p>
			<?php endif; ?>
			<?php
		elseif ( ! $nashenski_calculated || ! $nashenski_destination ) :
			if ( is_cart() && 'no' === get_option( 'woocommerce_enable_shipping_calc' ) ) {
				echo wp_kses_post( apply_filters( 'woocommerce_shipping_not_enabled_on_cart_html', __( 'Shipping costs are calculated during checkout.', 'nashenski-commerce' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
			} else {
				echo wp_kses_post( apply_filters( 'woocommerce_shipping_may_be_available_html', __( 'Enter your address to view shipping options.', 'nashenski-commerce' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
			}
		elseif ( ! is_cart() ) :
			echo wp_kses_post( apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'nashenski-commerce' ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
		else :
			/* translators: %s: shipping destination */
			$nashenski_no_options = sprintf( esc_html__( 'No shipping options were found for %s.', 'nashenski-commerce' ) . ' ', '<strong>' . esc_html( $nashenski_destination ) . '</strong>' );
			echo wp_kses_post( apply_filters( 'woocommerce_cart_no_shipping_available_html', $nashenski_no_options ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce hook.
			$nashenski_calculator_text = esc_html__( 'Enter a different address', 'nashenski-commerce' );
		endif;
		?>

		<?php if ( $show_package_details ) : ?>
			<?php echo '<p class="woocommerce-shipping-contents"><small>' . esc_html( $package_details ) . '</small></p>'; ?>
		<?php endif; ?>

		<?php if ( $nashenski_show_calculator ) : ?>
			<?php woocommerce_shipping_calculator( $nashenski_calculator_text ); ?>
		<?php endif; ?>
	</td>
</tr>
