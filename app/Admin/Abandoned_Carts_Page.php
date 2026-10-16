<?php
namespace Nashenski\Admin;

use Nashenski\Abandoned_Carts\Tracker;

defined( 'ABSPATH' ) || exit;

// The cart table is read on every page view, so caching would show stale rows. Queries name the table with %i.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- see above.
/**
 * WooCommerce > Abandoned carts: totals and the newest carts. Read-only.
 */
class Abandoned_Carts_Page {
	const SLUG = 'nashenski-abandoned-carts';

	/** Carts listed on the page. ponytail: no paging, filters or CSV yet; add them when the list gets long. */
	const LIMIT = 100;

	public function __construct() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
	}

	public static function add_menu() {
		if ( ! Tracker::enabled() ) {
			return;
		}

		add_submenu_page(
			'woocommerce',
			__( 'Abandoned carts', 'nashenski-commerce' ),
			__( 'Abandoned carts', 'nashenski-commerce' ),
			'manage_woocommerce',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		global $wpdb;
		$table = Tracker::table();

		$totals = $wpdb->get_row( $wpdb->prepare(
			'SELECT COUNT(*) AS total,
			SUM(status = %s) AS open_count,
			COALESCE(SUM(CASE WHEN status = %s THEN cart_total ELSE 0 END), 0) AS open_value,
			SUM(status = %s) AS recovered_count,
			COALESCE(SUM(CASE WHEN status = %s THEN cart_total ELSE 0 END), 0) AS recovered_value
			FROM %i',
			'open',
			'open',
			'recovered',
			'recovered',
			$table
		) );

		$carts = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY last_activity DESC LIMIT %d', $table, self::LIMIT ) );
		$steps = count( Tracker::steps() );

		$statuses = array(
			'open'         => __( 'Open', 'nashenski-commerce' ),
			'recovered'    => __( 'Ordered', 'nashenski-commerce' ),
			'emptied'      => __( 'Emptied', 'nashenski-commerce' ),
			'unsubscribed' => __( 'Unsubscribed', 'nashenski-commerce' ),
		);

		$total = (int) ( $totals ? $totals->total : 0 );
		$share = $total ? round( 100 * (int) $totals->recovered_count / $total ) : 0;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Abandoned carts', 'nashenski-commerce' ); ?></h1>

			<p>
				<?php
				printf(
					/* translators: 1: number of open carts, 2: value of open carts, 3: number of carts ordered, 4: value of carts ordered, 5: share of carts ordered in percent */
					esc_html__( 'Open carts: %1$d (%2$s). Ordered: %3$d (%4$s). Share ordered: %5$d%%.', 'nashenski-commerce' ),
					(int) ( $totals ? $totals->open_count : 0 ),
					esc_html( wp_strip_all_tags( wc_price( $totals ? $totals->open_value : 0 ) ) ),
					(int) ( $totals ? $totals->recovered_count : 0 ),
					esc_html( wp_strip_all_tags( wc_price( $totals ? $totals->recovered_value : 0 ) ) ),
					(int) $share
				);
				?>
			</p>

			<p class="description">
				<?php
				printf(
					/* translators: %d: number of rows */
					esc_html__( 'Newest %d carts. Carts are kept for the number of days set in the Abandoned carts settings.', 'nashenski-commerce' ),
					(int) self::LIMIT
				);
				?>
			</p>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Last change', 'nashenski-commerce' ); ?></th>
						<th><?php esc_html_e( 'Shopper', 'nashenski-commerce' ); ?></th>
						<th><?php esc_html_e( 'Items', 'nashenski-commerce' ); ?></th>
						<th><?php esc_html_e( 'Total', 'nashenski-commerce' ); ?></th>
						<th><?php esc_html_e( 'Last step', 'nashenski-commerce' ); ?></th>
						<th><?php esc_html_e( 'Reminders', 'nashenski-commerce' ); ?></th>
						<th><?php esc_html_e( 'Status', 'nashenski-commerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $carts ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No carts yet.', 'nashenski-commerce' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( (array) $carts as $cart ) : ?>
						<?php
						$items = json_decode( (string) $cart->cart, true );
						$names = array();

						foreach ( (array) $items as $item ) {
							$names[] = sprintf( '%s x %d', $item['name'], $item['quantity'] );
						}
						?>
						<tr>
							<td><?php echo esc_html( get_date_from_gmt( $cart->last_activity, 'Y-m-d H:i' ) ); ?></td>
							<td>
								<?php echo esc_html( trim( $cart->first_name . ' ' ) ); ?>
								<?php if ( $cart->email ) : ?><br><a href="mailto:<?php echo esc_attr( $cart->email ); ?>"><?php echo esc_html( $cart->email ); ?></a><?php endif; ?>
								<?php if ( $cart->phone ) : ?><br><?php echo esc_html( $cart->phone ); ?><?php endif; ?>
							</td>
							<td><?php echo esc_html( implode( ', ', $names ) ); ?></td>
							<td><?php echo wp_kses_post( wc_price( (float) $cart->cart_total, array( 'currency' => $cart->currency ) ) ); ?></td>
							<td><?php echo esc_html( 'checkout' === $cart->step ? __( 'Checkout', 'nashenski-commerce' ) : __( 'Cart', 'nashenski-commerce' ) ); ?></td>
							<td><?php echo esc_html( (int) $cart->emails_sent . ' / ' . $steps ); ?></td>
							<td><?php echo esc_html( isset( $statuses[ $cart->status ] ) ? $statuses[ $cart->status ] : $cart->status ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
