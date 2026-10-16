<?php
namespace Nashenski\Reports\Providers;

use Nashenski\Client\Nekorekten as Client;

defined( 'ABSPATH' ) || exit;

/**
 * nekorekten.com reports about the customer's phone number.
 */
class Nekorekten extends Provider_Base {
	const META_PREFIX = 'nashenski_reports-nekorekten_phone_';

	public function __construct() {
		$this->set_name( __( 'nekorekten.com', 'nashenski-commerce' ) );
	}

	private static function phone( $order ) {
		return $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone();
	}

	private static function threshold() {
		$threshold = (int) nashenski_get_option( 'nekorekten', 'level_of_warning' );

		return max( 1, $threshold );
	}

	private static function to_reports( $result ) {
		if ( ! is_array( $result ) || $result['count'] < self::threshold() ) {
			return array();
		}

		/* translators: %d: number of reports */
		return array( sprintf( _n( 'nekorekten.com has %d report about this phone number.', 'nekorekten.com has %d reports about this phone number.', $result['count'], 'nashenski-commerce' ), $result['count'] ) );
	}

	public static function get_all_reports( $order, $force = false ) {
		$phone = self::phone( $order );
		$meta_key = self::META_PREFIX . md5( $phone );
		$result = $order->get_meta( $meta_key );

		if ( ! $result || $force ) {
			$fresh = ( new Client() )->find_by_phone( $phone, (bool) $force );

			if ( null !== $fresh ) {
				$result = $fresh;
				$order->update_meta_data( $meta_key, $result );
				$order->save();
			}
		}

		return array(
			'result' => $result,
			'reports' => self::to_reports( $result ),
		);
	}

	public static function get_all_reports_from_meta( $order ) {
		return array( 'reports' => self::to_reports( $order->get_meta( self::META_PREFIX . md5( self::phone( $order ) ) ) ) );
	}

	public function print_reports_html( $order ) {
		$data = self::get_all_reports( $order );
		$result = $data['result'];
		?>
		<div class="order_data_column_container">
			<?php if ( ! is_array( $result ) ) : ?>
				<h4><?php esc_html_e( 'nekorekten.com did not answer. Check the API key in the settings.', 'nashenski-commerce' ); ?></h4>
			<?php elseif ( 0 === (int) $result['count'] ) : ?>
				<h4><?php esc_html_e( 'No reports were found for this phone number.', 'nashenski-commerce' ); ?></h4>
			<?php else : ?>
				<h4>
					<?php
					/* translators: %d: number of reports */
					echo esc_html( sprintf( _n( '%d report was found for this phone number.', '%d reports were found for this phone number.', $result['count'], 'nashenski-commerce' ), $result['count'] ) );
					?>
				</h4>
				<ul>
					<?php foreach ( array_slice( $result['items'], 0, 5 ) as $item ) : ?>
						<li>
							<?php if ( $item['date'] ) : ?>
								<strong><?php echo esc_html( mysql2date( get_option( 'date_format' ), $item['date'] ) ); ?>:</strong>
							<?php endif; ?>
							<?php echo esc_html( wp_trim_words( $item['text'], 40 ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<p>
				<label for="nashenski-nk-text"><?php esc_html_e( 'Send a report about this customer to nekorekten.com', 'nashenski-commerce' ); ?></label><br>
				<textarea id="nashenski-nk-text" name="nashenski_nk_text" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'What happened, for example: refused the parcel paid on delivery.', 'nashenski-commerce' ); ?>"></textarea>
			</p>
			<p class="description"><?php esc_html_e( 'The report is public on nekorekten.com with the phone number and the name of the order.', 'nashenski-commerce' ); ?></p>
			<?php wp_nonce_field( 'nashenski_nk_report', 'nashenski_nk_nonce' ); ?>
			<p><button type="submit" class="button" name="nashenski_nk_send" value="1"><?php esc_html_e( 'Send report', 'nashenski-commerce' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * The Send report button of the order screen: sends the text with the order's phone and name.
	 */
	public static function send_report( $order_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked right below.
		if ( empty( $_POST['nashenski_nk_send'] ) || empty( $_POST['nashenski_nk_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nashenski_nk_nonce'] ) ), 'nashenski_nk_report' ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$text = isset( $_POST['nashenski_nk_text'] ) ? mb_substr( trim( sanitize_textarea_field( wp_unslash( $_POST['nashenski_nk_text'] ) ) ), 0, 1000 ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( '' === $text ) {
			\WC_Admin_Meta_Boxes::add_error( __( 'Report not sent: write what happened first.', 'nashenski-commerce' ) );

			return;
		}

		// A double click or a resubmitted form must not publish the same report twice.
		if ( md5( $text ) === $order->get_meta( '_nashenski_nk_reported' ) ) {
			return;
		}

		$result = ( new Client() )->send_report( array_filter( array(
			'text' => $text,
			'phone' => self::phone( $order ),
			'firstName' => $order->get_billing_first_name(),
			'lastName' => $order->get_billing_last_name(),
		) ) );

		if ( true !== $result ) {
			/* translators: %s: reason from nekorekten.com */
			\WC_Admin_Meta_Boxes::add_error( sprintf( __( 'nekorekten.com did not take the report: %s', 'nashenski-commerce' ), $result ) );

			return;
		}

		$order->update_meta_data( '_nashenski_nk_reported', md5( $text ) );
		$order->save();
		/* translators: %s: report text */
		$order->add_order_note( sprintf( __( 'Report sent to nekorekten.com: %s', 'nashenski-commerce' ), $text ) );
		self::get_all_reports( $order, true );
	}

	public static function check_for_reports_on_checkout( $phone ) {
		return self::to_reports( ( new Client() )->find_by_phone( $phone ) );
	}
}
