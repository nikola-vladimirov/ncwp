<?php
/**
 * Nekorekten.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Reports\Providers;

use Nashenski\Client\Nekorekten as Client;

defined( 'ABSPATH' ) || exit;

/**
 * Reports from nekorekten.com about the customer's phone number.
 */
class Nekorekten extends Provider_Base {
	const META_PREFIX = 'nashenski_reports-nekorekten_phone_';
	const SENT_META   = '_nashenski_nk_sent';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_name( __( 'nekorekten.com', 'nashenski-commerce' ) );
	}

	/**
	 * Phone.
	 *
	 * @param mixed $order Order.
	 * @return mixed
	 */
	private static function phone( $order ) {
		return $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone();
	}

	/**
	 * The customer fields a report carries, as nekorekten.com names them.
	 *
	 * @param mixed $order Order.
	 */
	private static function person( $order ) {
		return array(
			'firstName' => trim( $order->get_billing_first_name() ),
			'lastName'  => trim( $order->get_billing_last_name() ),
			'phone'     => trim( self::phone( $order ) ),
			'email'     => trim( $order->get_billing_email() ),
		);
	}

	/**
	 * Threshold.
	 *
	 * @return mixed
	 */
	private static function threshold() {
		$threshold = (int) nashenski_get_option( 'nekorekten', 'level_of_warning' );

		return max( 1, $threshold );
	}

	/**
	 * To reports.
	 *
	 * @param mixed $result Result.
	 * @return mixed
	 */
	private static function to_reports( $result ) {
		if ( ! is_array( $result ) || ( $result['count'] ?? 0 ) < self::threshold() ) {
			return array();
		}

		/* translators: %d: number of reports */
		return array( sprintf( _n( 'nekorekten.com has %d report about this phone number.', 'nekorekten.com has %d reports about this phone number.', $result['count'], 'nashenski-commerce' ), $result['count'] ) );
	}

	/**
	 * Get all reports.
	 *
	 * @param mixed $order Order.
	 * @param bool  $force Force.
	 * @return mixed
	 */
	public static function get_all_reports( $order, $force = false ) {
		$phone    = self::phone( $order );
		$meta_key = self::META_PREFIX . md5( $phone );
		$result   = $order->get_meta( $meta_key );

		if ( ! $result || $force ) {
			$fresh = ( new Client() )->find_by_phone( $phone, (bool) $force );

			if ( null !== $fresh ) {
				$result = $fresh;
				$order->update_meta_data( $meta_key, $result );
				$order->save();
			}
		}

		return array(
			'result'  => $result,
			'reports' => self::to_reports( $result ),
		);
	}

	/**
	 * Get all reports from meta.
	 *
	 * @param mixed $order Order.
	 * @return mixed
	 */
	public static function get_all_reports_from_meta( $order ) {
		return array( 'reports' => self::to_reports( $order->get_meta( self::META_PREFIX . md5( self::phone( $order ) ) ) ) );
	}

	/**
	 * Print reports html.
	 *
	 * @param mixed $order Order.
	 * @return mixed
	 */
	public function print_reports_html( $order ) {
		$data   = self::get_all_reports( $order );
		$result = $data['result'];
		?>
		<div class="order_data_column_container">
			<?php if ( ! is_array( $result ) ) : ?>
				<h4><?php esc_html_e( 'nekorekten.com did not answer. Check the API key in the settings.', 'nashenski-commerce' ); ?></h4>
			<?php elseif ( 0 === (int) ( $result['count'] ?? 0 ) ) : ?>
				<h4><?php esc_html_e( 'No reports were found for this phone number.', 'nashenski-commerce' ); ?></h4>
			<?php else : ?>
				<h4>
					<?php
					/* translators: %d: number of reports */
					echo esc_html( sprintf( _n( '%d report was found for this phone number.', '%d reports were found for this phone number.', $result['count'], 'nashenski-commerce' ), $result['count'] ) );
					?>
				</h4>
				<ul>
					<?php foreach ( array_slice( $result['items'] ?? array(), 0, 5 ) as $item ) : ?>
						<li>
							<?php if ( $item['date'] ) : ?>
								<strong><?php echo esc_html( mysql2date( get_option( 'date_format' ), $item['date'] ) ); ?>:</strong>
							<?php endif; ?>
							<?php echo esc_html( wp_trim_words( $item['text'], 40 ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php foreach ( self::sent( $order ) as $id => $sent_text ) : ?>
				<div class="nashenski-nk-sent">
					<p>
						<label for="nashenski-nk-edit-<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Report sent from this order', 'nashenski-commerce' ); ?></label><br>
						<textarea id="nashenski-nk-edit-<?php echo esc_attr( $id ); ?>" name="nashenski_nk_edit_text[<?php echo esc_attr( $id ); ?>]" rows="3" class="large-text"><?php echo esc_textarea( $sent_text ); ?></textarea>
					</p>
					<p>
						<button type="submit" class="button" name="nashenski_nk_edit" value="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Save report', 'nashenski-commerce' ); ?></button>
						<button type="submit" class="button button-link-delete" name="nashenski_nk_delete" value="<?php echo esc_attr( $id ); ?>" data-nashenski-confirm="<?php esc_attr_e( 'Delete this report from nekorekten.com?', 'nashenski-commerce' ); ?>"><?php esc_html_e( 'Delete report', 'nashenski-commerce' ); ?></button>
					</p>
				</div>
			<?php endforeach; ?>
			<p>
				<label for="nashenski-nk-text"><?php esc_html_e( 'Send a report about this customer to nekorekten.com', 'nashenski-commerce' ); ?></label><br>
				<textarea id="nashenski-nk-text" name="nashenski_nk_text" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'What happened, for example: refused the parcel paid on delivery.', 'nashenski-commerce' ); ?>"></textarea>
			</p>
			<p class="description">
				<?php
				/* translators: %s: name, phone and email of the order */
				echo esc_html( sprintf( __( 'Sent with the report, which is public on nekorekten.com: %s', 'nashenski-commerce' ), implode( ', ', array_filter( self::person( $order ) ) ) ) );
				?>
			</p>
			<?php wp_nonce_field( 'nashenski_nk_report', 'nashenski_nk_nonce' ); ?>
			<p><button type="submit" class="button" name="nashenski_nk_send" value="1"><?php esc_html_e( 'Send report', 'nashenski-commerce' ); ?></button></p>
		</div>
		<?php
	}

	/**
	 * Reports sent from this order: nekorekten.com id => text.
	 *
	 * @param mixed $order Order.
	 */
	private static function sent( $order ) {
		$sent = $order->get_meta( self::SENT_META );

		return is_array( $sent ) ? $sent : array();
	}

	/**
	 * Clean text.
	 *
	 * @param mixed $text Text.
	 * @return mixed
	 */
	private static function clean_text( $text ) {
		return mb_substr( trim( sanitize_textarea_field( (string) $text ) ), 0, 1000 );
	}

	/**
	 * The Send, Save and Delete report buttons of the order screen.
	 *
	 * @param mixed $order_id Order id.
	 */
	public static function handle_form( $order_id ) {
		if ( ! isset( $_POST['nashenski_nk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nashenski_nk_nonce'] ) ), 'nashenski_nk_report' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		if ( empty( $_POST['nashenski_nk_send'] ) && empty( $_POST['nashenski_nk_edit'] ) && empty( $_POST['nashenski_nk_delete'] ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$sent = self::sent( $order );

		if ( ! empty( $_POST['nashenski_nk_delete'] ) ) {
			$id = sanitize_text_field( wp_unslash( $_POST['nashenski_nk_delete'] ) );

			if ( isset( $sent[ $id ] ) ) {
				self::delete( $order, $id, $sent );
			}

			return;
		}

		if ( ! empty( $_POST['nashenski_nk_edit'] ) ) {
			$id   = sanitize_text_field( wp_unslash( $_POST['nashenski_nk_edit'] ) );
			$text = isset( $_POST['nashenski_nk_edit_text'][ $id ] ) ? self::clean_text( sanitize_textarea_field( wp_unslash( $_POST['nashenski_nk_edit_text'][ $id ] ) ) ) : '';

			if ( isset( $sent[ $id ] ) ) {
				self::edit( $order, $id, $text, $sent );
			}

			return;
		}

		$text = isset( $_POST['nashenski_nk_text'] ) ? self::clean_text( sanitize_textarea_field( wp_unslash( $_POST['nashenski_nk_text'] ) ) ) : '';
		self::send( $order, $text, $sent );
	}

	/**
	 * Send.
	 *
	 * @param mixed $order Order.
	 * @param mixed $text  Text.
	 * @param mixed $sent  Sent.
	 */
	private static function send( $order, $text, $sent ) {
		if ( '' === $text ) {
			\WC_Admin_Meta_Boxes::add_error( __( 'Report not sent: write what happened first.', 'nashenski-commerce' ) );

			return;
		}

		// A double click or a resubmitted form must not publish the same report twice.
		if ( in_array( $text, $sent, true ) || md5( $text ) === $order->get_meta( '_nashenski_nk_reported' ) ) {
			return;
		}

		$person = self::person( $order );

		// nekorekten.com needs a phone or an email next to the text.
		if ( '' === $person['phone'] && '' === $person['email'] ) {
			\WC_Admin_Meta_Boxes::add_error( __( 'Report not sent: the order has no phone or email.', 'nashenski-commerce' ) );

			return;
		}

		$result = ( new Client() )->send_report( array_filter( array( 'text' => $text ) + $person ) );

		if ( ! is_array( $result ) ) {
			/* translators: %s: reason from nekorekten.com */
			\WC_Admin_Meta_Boxes::add_error( sprintf( __( 'nekorekten.com did not take the report: %s', 'nashenski-commerce' ), $result ) );

			return;
		}

		// Without an id in the answer the report cannot be edited or deleted from here.
		if ( '' !== $result['id'] ) {
			$sent[ $result['id'] ] = $text;
			$order->update_meta_data( self::SENT_META, $sent );
		}

		$order->update_meta_data( '_nashenski_nk_reported', md5( $text ) );
		$order->save();
		/* translators: %s: report text */
		$order->add_order_note( sprintf( __( 'Report sent to nekorekten.com: %s', 'nashenski-commerce' ), $text ) );
		self::get_all_reports( $order, true );
	}

	/**
	 * Edit.
	 *
	 * @param mixed $order Order.
	 * @param mixed $id    Id.
	 * @param mixed $text  Text.
	 * @param mixed $sent  Sent.
	 */
	private static function edit( $order, $id, $text, $sent ) {
		if ( '' === $text ) {
			\WC_Admin_Meta_Boxes::add_error( __( 'Report not saved: the text is empty. Use Delete report to remove it.', 'nashenski-commerce' ) );

			return;
		}

		if ( $text === $sent[ $id ] ) {
			return;
		}

		$result = ( new Client() )->edit_report( $id, array( 'text' => $text ) );

		if ( true !== $result ) {
			/* translators: %s: reason from nekorekten.com */
			\WC_Admin_Meta_Boxes::add_error( sprintf( __( 'nekorekten.com did not save the report: %s', 'nashenski-commerce' ), $result ) );

			return;
		}

		$sent[ $id ] = $text;
		$order->update_meta_data( self::SENT_META, $sent );
		$order->update_meta_data( '_nashenski_nk_reported', md5( $text ) );
		$order->save();
		/* translators: %s: new report text */
		$order->add_order_note( sprintf( __( 'Report on nekorekten.com changed to: %s', 'nashenski-commerce' ), $text ) );
	}

	/**
	 * Delete.
	 *
	 * @param mixed $order Order.
	 * @param mixed $id    Id.
	 * @param mixed $sent  Sent.
	 */
	private static function delete( $order, $id, $sent ) {
		$result = ( new Client() )->delete_report( $id );

		if ( true !== $result ) {
			/* translators: %s: reason from nekorekten.com */
			\WC_Admin_Meta_Boxes::add_error( sprintf( __( 'nekorekten.com did not delete the report: %s', 'nashenski-commerce' ), $result ) );

			return;
		}

		$text = $sent[ $id ];
		unset( $sent[ $id ] );
		$order->update_meta_data( self::SENT_META, $sent );
		// The same text may be sent again after a delete.
		$order->delete_meta_data( '_nashenski_nk_reported' );
		$order->save();
		/* translators: %s: deleted report text */
		$order->add_order_note( sprintf( __( 'Report deleted from nekorekten.com: %s', 'nashenski-commerce' ), $text ) );
		self::get_all_reports( $order, true );
	}

	/**
	 * Check for reports on checkout.
	 *
	 * @param mixed $phone Phone.
	 * @return mixed
	 */
	public static function check_for_reports_on_checkout( $phone ) {
		return self::to_reports( ( new Client() )->find_by_phone( $phone ) );
	}
}
