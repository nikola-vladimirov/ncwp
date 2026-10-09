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
		</div>
		<?php
	}

	public static function check_for_reports_on_checkout( $phone ) {
		return self::to_reports( ( new Client() )->find_by_phone( $phone ) );
	}
}
