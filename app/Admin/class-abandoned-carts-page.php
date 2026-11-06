<?php
/**
 * Abandoned Carts Page.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

use Nashenski\Abandoned_Carts\Tracker;

defined( 'ABSPATH' ) || exit;

// The cart table is read on every page view, so caching would show stale rows. Queries name the table with %i.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- see above.
/**
 * WooCommerce > Abandoned carts: the totals and the list of carts, with per-cart and bulk actions
 * (send a reminder, edit the contact, delete). Every action is an admin-post request with a nonce.
 */
class Abandoned_Carts_Page {
	const SLUG = 'nashenski-abandoned-carts';

	/** Carts listed on a page. */
	const PER_PAGE = 20;

	const ACTION_BULK   = 'nashenski_carts_bulk';
	const ACTION_REMIND = 'nashenski_cart_remind';
	const ACTION_DELETE = 'nashenski_cart_delete';
	const ACTION_UPDATE = 'nashenski_cart_update';

	/** Status keys the screen can filter by, with the label. */
	const VISIBLE = array( 'open', 'recovered', 'emptied', 'unsubscribed' );

	/**
	 * Construct.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION_BULK, array( __CLASS__, 'handle_bulk' ) );
		add_action( 'admin_post_' . self::ACTION_REMIND, array( __CLASS__, 'handle_remind' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_' . self::ACTION_UPDATE, array( __CLASS__, 'handle_update' ) );
	}

	/**
	 * Add menu.
	 */
	public static function add_menu() {
		if ( ! Tracker::enabled() ) {
			return;
		}

		add_submenu_page(
			'woocommerce',
			__( 'Abandoned carts', 'nashenski-commerce' ),
			__( 'Abandoned carts', 'nashenski-commerce' ),
			'manage_woocommerce', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- manage_woocommerce is WooCommerce's own capability.
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Statuses.
	 */
	private static function statuses() {
		return array(
			'open'         => __( 'Open', 'nashenski-commerce' ),
			'recovered'    => __( 'Ordered', 'nashenski-commerce' ),
			'emptied'      => __( 'Emptied', 'nashenski-commerce' ),
			'unsubscribed' => __( 'Unsubscribed', 'nashenski-commerce' ),
		);
	}

	/**
	 * Bulk action. The handlers check the capability and the nonce, do their work and go back.
	 */
	public static function handle_bulk() {
		self::guard( self::ACTION_BULK );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$bulk = isset( $_POST['bulk'] ) ? sanitize_key( wp_unslash( $_POST['bulk'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$ids   = isset( $_POST['carts'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['carts'] ) ) : array();
		$count = 0;

		if ( $ids && 'delete' === $bulk ) {
			$count = Tracker::delete_rows( $ids );
			self::back( 'deleted_many', $count );
		}

		if ( $ids && 'remind' === $bulk ) {
			foreach ( $ids as $id ) {
				if ( Tracker::send_now( $id ) ) {
					++$count;
				}
			}
			self::back( 'reminded_many', $count );
		}

		self::back( 'no_selection', 0 );
	}

	/**
	 * Handle remind.
	 */
	public static function handle_remind() {
		self::guard( self::ACTION_REMIND );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in guard().
		$id = isset( $_GET['cart'] ) ? absint( $_GET['cart'] ) : 0;

		self::back( Tracker::send_now( $id ) ? 'reminded' : 'send_failed', 0 );
	}

	/**
	 * Handle delete.
	 */
	public static function handle_delete() {
		self::guard( self::ACTION_DELETE );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked in guard().
		$id = isset( $_GET['cart'] ) ? absint( $_GET['cart'] ) : 0;

		self::back( Tracker::delete_row( $id ) ? 'deleted' : 'no_selection', 0 );
	}

	/**
	 * Handle update.
	 */
	public static function handle_update() {
		self::guard( self::ACTION_UPDATE );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$id = isset( $_POST['cart'] ) ? absint( $_POST['cart'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in guard().
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		if ( ! $id || ! is_email( $email ) ) {
			self::back( 'update_invalid', 0 );
		}

		self::back( Tracker::update_contact( $id, $email, $name, $phone ) ? 'updated' : 'update_invalid', 0 );
	}

	/**
	 * Guard.
	 *
	 * @param mixed $action Action.
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- manage_woocommerce is WooCommerce's own capability.
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Goes back to the list with a notice code.
	 *
	 * @param mixed $notice Notice.
	 * @param mixed $count Count.
	 */
	private static function back( $notice, $count ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- the nonce was checked above; these only carry the filter back.
		$status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended -- see above.
		$paged = isset( $_REQUEST['paged'] ) ? max( 1, absint( wp_unslash( $_REQUEST['paged'] ) ) ) : 1;

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                   => self::SLUG,
					'status'                 => $status,
					'paged'                  => $paged,
					'nashenski_carts_notice' => $notice,
					'nashenski_carts_count'  => (int) $count,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Action url.
	 *
	 * @param mixed $action Action.
	 * @param mixed $id Id.
	 */
	private static function action_url( $action, $id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => $action,
					'cart'   => (int) $id,
					'status' => self::current_filter(),
					'paged'  => self::current_page(),
				),
				admin_url( 'admin-post.php' )
			),
			$action
		);
	}

	/**
	 * The filter and page number to carry back into forms and links.
	 */
	private static function current_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the filter only.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';

		return in_array( $status, self::VISIBLE, true ) ? $status : '';
	}

	/**
	 * Current page.
	 */
	private static function current_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the page number only.
		return isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
	}

	/**
	 * Render.
	 */
	public static function render() {
		$status = self::current_filter();
		$paged  = self::current_page();
		$offset = ( $paged - 1 ) * self::PER_PAGE;
		$steps  = count( Tracker::steps() );
		$counts = Tracker::counts_by_status();
		$total  = Tracker::count_rows( $status );
		$carts  = Tracker::get_rows( $status, self::PER_PAGE, $offset );
		$labels = self::statuses();
		$pages  = max( 1, (int) ceil( $total / self::PER_PAGE ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- showing an edit form only.
		$edit_id  = isset( $_GET['edit'] ) ? absint( wp_unslash( $_GET['edit'] ) ) : 0;
		$edit_row = $edit_id ? Tracker::get_row( $edit_id ) : null;

		$open_value = Tracker::open_value();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Abandoned carts', 'nashenski-commerce' ); ?></h1>

			<?php self::notice( isset( $_GET['nashenski_carts_notice'] ) ? sanitize_key( wp_unslash( $_GET['nashenski_carts_notice'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only. ?>

			<p>
				<?php
				printf(
					/* translators: 1: number of open carts, 2: value of open carts, 3: number of carts ordered */
					esc_html__( 'Open carts: %1$d (%2$s). Ordered: %3$d.', 'nashenski-commerce' ),
					(int) ( isset( $counts['open'] ) ? $counts['open'] : 0 ),
					esc_html( wp_strip_all_tags( wc_price( $open_value ) ) ),
					(int) ( isset( $counts['recovered'] ) ? $counts['recovered'] : 0 )
				);
				?>
			</p>

			<?php self::render_edit( $edit_row ); ?>

			<ul class="subsubsub">
				<?php
				$views = array_merge( array( '' => __( 'All', 'nashenski-commerce' ) ), $labels );
				$links = array();

				foreach ( $views as $key => $label ) {
					$url = add_query_arg(
						array(
							'page'   => self::SLUG,
							'status' => $key,
						),
						admin_url( 'admin.php' )
					);

					$links[] = sprintf(
						'<li><a href="%s" class="%s">%s <span class="count">(%d)</span></a></li>',
						esc_url( $url ),
						$status === $key ? 'current' : '',
						esc_html( $label ),
						(int) ( '' === $key ? array_sum( $counts ) : ( isset( $counts[ $key ] ) ? $counts[ $key ] : 0 ) )
					);
				}

				echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with escaped parts above.
				?>
			</ul>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_BULK ); ?>">
				<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
				<input type="hidden" name="paged" value="<?php echo esc_attr( $paged ); ?>">
				<?php wp_nonce_field( self::ACTION_BULK ); ?>

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<label for="nashenski-bulk" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'nashenski-commerce' ); ?></label>
						<select name="bulk" id="nashenski-bulk">
							<option value=""><?php esc_html_e( 'Bulk actions', 'nashenski-commerce' ); ?></option>
							<option value="remind"><?php esc_html_e( 'Send reminder', 'nashenski-commerce' ); ?></option>
							<option value="delete"><?php esc_html_e( 'Delete', 'nashenski-commerce' ); ?></option>
						</select>
						<button type="submit" class="button"><?php esc_html_e( 'Apply', 'nashenski-commerce' ); ?></button>
					</div>
				</div>

				<table class="widefat striped">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><input type="checkbox" id="nashenski-select-all"></td>
							<th><?php esc_html_e( 'Last change', 'nashenski-commerce' ); ?></th>
							<th><?php esc_html_e( 'Shopper', 'nashenski-commerce' ); ?></th>
							<th><?php esc_html_e( 'Items', 'nashenski-commerce' ); ?></th>
							<th><?php esc_html_e( 'Total', 'nashenski-commerce' ); ?></th>
							<th><?php esc_html_e( 'Coupon', 'nashenski-commerce' ); ?></th>
							<th><?php esc_html_e( 'Last step', 'nashenski-commerce' ); ?></th>
							<th><?php esc_html_e( 'Reminders', 'nashenski-commerce' ); ?></th>
							<th><?php esc_html_e( 'Status', 'nashenski-commerce' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'nashenski-commerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $carts ) : ?>
							<tr><td colspan="10"><?php esc_html_e( 'No carts found.', 'nashenski-commerce' ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( (array) $carts as $cart ) : ?>
							<?php
							$items = json_decode( (string) $cart->cart, true );
							$names = array();

							foreach ( (array) $items as $item ) {
								$names[] = sprintf( '%s x %d', isset( $item['name'] ) ? $item['name'] : '', isset( $item['quantity'] ) ? $item['quantity'] : 1 );
							}
							?>
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" name="carts[]" value="<?php echo esc_attr( $cart->id ); ?>"></th>
								<td><?php echo esc_html( get_date_from_gmt( $cart->last_activity, 'Y-m-d H:i' ) ); ?></td>
								<td>
									<?php echo esc_html( trim( $cart->first_name . ' ' ) ); ?>
									<?php
									if ( $cart->email ) :
										?>
										<br><a href="mailto:<?php echo esc_attr( $cart->email ); ?>"><?php echo esc_html( $cart->email ); ?></a><?php endif; ?>
									<?php
									if ( $cart->phone ) :
										?>
										<br><?php echo esc_html( $cart->phone ); ?><?php endif; ?>
								</td>
								<td><?php echo esc_html( implode( ', ', $names ) ); ?></td>
								<td><?php echo wp_kses_post( wc_price( (float) $cart->cart_total, array( 'currency' => $cart->currency ) ) ); ?></td>
								<td><?php echo esc_html( $cart->coupon_code ); ?></td>
								<td><?php echo esc_html( 'checkout' === $cart->step ? __( 'Checkout', 'nashenski-commerce' ) : __( 'Cart', 'nashenski-commerce' ) ); ?></td>
								<td><?php echo esc_html( (int) $cart->emails_sent . ' / ' . $steps ); ?></td>
								<td><?php echo esc_html( isset( $labels[ $cart->status ] ) ? $labels[ $cart->status ] : $cart->status ); ?></td>
								<td>
									<a href="
									<?php
									echo esc_url(
										add_query_arg(
											array(
												'page'   => self::SLUG,
												'status' => $status,
												'paged'  => $paged,
												'edit'   => (int) $cart->id,
											),
											admin_url( 'admin.php' )
										)
									);
									?>
												"><?php esc_html_e( 'Edit', 'nashenski-commerce' ); ?></a>
									<?php if ( is_email( $cart->email ) && 'open' === $cart->status ) : ?>
										| <a href="<?php echo esc_url( self::action_url( self::ACTION_REMIND, $cart->id ) ); ?>"><?php esc_html_e( 'Send reminder', 'nashenski-commerce' ); ?></a>
									<?php endif; ?>
									| <a href="<?php echo esc_url( self::action_url( self::ACTION_DELETE, $cart->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this cart?', 'nashenski-commerce' ) ); ?>');"><?php esc_html_e( 'Delete', 'nashenski-commerce' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php
								/* translators: %d: number of carts */
								printf( esc_html__( '%d carts', 'nashenski-commerce' ), (int) $total );
								?>
							</span>
							<?php
							$pagination = paginate_links(
								array(
									'base'      => add_query_arg(
										array(
											'page'   => self::SLUG,
											'status' => $status,
											'paged'  => '%#%',
										),
										admin_url( 'admin.php' )
									),
									'format'    => '',
									'current'   => $paged,
									'total'     => $pages,
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								)
							);

							echo wp_kses_post( $pagination );
							?>
						</div>
					</div>
				<?php endif; ?>
			</form>
		</div>
		<script>
		( function () {
			var all = document.getElementById( 'nashenski-select-all' );
			if ( all ) {
				all.addEventListener( 'change', function () {
					document.querySelectorAll( 'input[name="carts[]"]' ).forEach( function ( box ) { box.checked = all.checked; } );
				} );
			}
		}() );
		</script>
		<?php
	}

	/**
	 * The inline edit form for one cart, shown above the list when Edit is pressed.
	 *
	 * @param mixed $row Row.
	 */
	private static function render_edit( $row ) {
		if ( ! $row ) {
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="nashenski-cart-edit" style="max-width:520px;margin:12px 0;padding:12px;border:1px solid #c3c4c7;background:#fff;">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_UPDATE ); ?>">
			<input type="hidden" name="cart" value="<?php echo esc_attr( $row->id ); ?>">
			<input type="hidden" name="status" value="<?php echo esc_attr( self::current_filter() ); ?>">
			<input type="hidden" name="paged" value="<?php echo esc_attr( self::current_page() ); ?>">
			<?php wp_nonce_field( self::ACTION_UPDATE ); ?>
			<h2>
				<?php
				/* translators: %d: cart id */
				printf( esc_html__( 'Edit cart #%d', 'nashenski-commerce' ), (int) $row->id );
				?>
			</h2>
			<p>
				<label for="nashenski-edit-email"><?php esc_html_e( 'Email', 'nashenski-commerce' ); ?></label><br>
				<input type="email" class="regular-text" id="nashenski-edit-email" name="email" required value="<?php echo esc_attr( $row->email ); ?>">
			</p>
			<p>
				<label for="nashenski-edit-name"><?php esc_html_e( 'First name', 'nashenski-commerce' ); ?></label><br>
				<input type="text" class="regular-text" id="nashenski-edit-name" name="first_name" value="<?php echo esc_attr( $row->first_name ); ?>">
			</p>
			<p>
				<label for="nashenski-edit-phone"><?php esc_html_e( 'Phone', 'nashenski-commerce' ); ?></label><br>
				<input type="text" class="regular-text" id="nashenski-edit-phone" name="phone" value="<?php echo esc_attr( $row->phone ); ?>">
			</p>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'nashenski-commerce' ); ?></button>
				<a class="button" href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'page'   => self::SLUG,
							'status' => self::current_filter(),
						),
						admin_url( 'admin.php' )
					)
				);
				?>
										"><?php esc_html_e( 'Cancel', 'nashenski-commerce' ); ?></a>
			</p>
		</form>
		<?php
	}

	/**
	 * Notice.
	 *
	 * @param mixed $code Code.
	 */
	private static function notice( $code ) {
		if ( '' === $code ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$count = isset( $_GET['nashenski_carts_count'] ) ? absint( wp_unslash( $_GET['nashenski_carts_count'] ) ) : 0;

		$messages = array(
			'reminded'       => array( 'success', __( 'Reminder sent.', 'nashenski-commerce' ) ),
			'reminded_many'  => array( 'success', sprintf( /* translators: %d: number of reminders */ _n( '%d reminder sent.', '%d reminders sent.', $count, 'nashenski-commerce' ), $count ) ),
			'deleted'        => array( 'success', __( 'Cart deleted.', 'nashenski-commerce' ) ),
			'deleted_many'   => array( 'success', sprintf( /* translators: %d: number of carts */ _n( '%d cart deleted.', '%d carts deleted.', $count, 'nashenski-commerce' ), $count ) ),
			'updated'        => array( 'success', __( 'Cart updated.', 'nashenski-commerce' ) ),
			'update_invalid' => array( 'error', __( 'Enter a valid email address.', 'nashenski-commerce' ) ),
			'send_failed'    => array( 'error', __( 'The reminder could not be sent. Check the WooCommerce email settings.', 'nashenski-commerce' ) ),
			'no_selection'   => array( 'warning', __( 'Select at least one cart first.', 'nashenski-commerce' ) ),
		);

		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $messages[ $code ][0] ),
			esc_html( $messages[ $code ][1] )
		);
	}
}
