<?php
namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce > Settings > Nashenski > Help: recommended settings, moving from another plugin,
 * getting started and how every part of the plugin is used.
 */
class Help_Page {
	public static function output() {
		self::output_applied_notice();
		?>
		<div class="nashenski-help">
			<?php
			self::output_recommended();
			self::output_import();
			?>

			<h2><?php esc_html_e( 'Getting started', 'nashenski-commerce' ); ?></h2>
			<ol class="nashenski-help__steps">
				<?php foreach ( self::steps() as $step ) : ?>
					<li><?php echo wp_kses_post( $step ); ?></li>
				<?php endforeach; ?>
			</ol>

			<h2><?php esc_html_e( 'How to use it', 'nashenski-commerce' ); ?></h2>
			<?php foreach ( self::topics() as $title => $lines ) : ?>
				<details class="nashenski-help__topic">
					<summary><?php echo esc_html( $title ); ?></summary>
					<ul>
						<?php foreach ( $lines as $line ) : ?>
							<li><?php echo wp_kses_post( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endforeach; ?>

			<h2><?php esc_html_e( 'Support', 'nashenski-commerce' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: link to the support forum */
					esc_html__( 'Ask questions and report problems in the %s.', 'nashenski-commerce' ),
					'<a href="https://wordpress.org/support/plugin/nashenski-commerce/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'support forum', 'nashenski-commerce' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	private static function link( $section, $label ) {
		return '<a href="' . esc_url( Settings::url( $section ) ) . '">' . esc_html( $label ) . '</a>';
	}

	private static function steps() {
		$zones = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ) . '">' . esc_html__( 'WooCommerce > Settings > Shipping', 'nashenski-commerce' ) . '</a>';

		return array(
			/* translators: %s: link to the Overview section */
			sprintf( esc_html__( 'In %s, turn on the couriers you work with, invoices and the customer checks you need, and save.', 'nashenski-commerce' ), self::link( '', __( 'Overview', 'nashenski-commerce' ) ) ),
			/* translators: %s: link to the Couriers section */
			sprintf( esc_html__( 'In %s, enter the login or API keys the courier gave you for online shops.', 'nashenski-commerce' ), self::link( 'couriers', __( 'Couriers', 'nashenski-commerce' ) ) ),
			/* translators: %s: link to WooCommerce shipping zones */
			sprintf( esc_html__( 'In %s, add the courier shipping methods to your zone: one method per delivery type (to an office, to a locker, to an address).', 'nashenski-commerce' ), $zones ),
			/* translators: %s: link to the Checkout section */
			sprintf( esc_html__( 'In %s, choose the countries, the checkout fields and the look of the shipping options.', 'nashenski-commerce' ), self::link( 'checkout', __( 'Checkout', 'nashenski-commerce' ) ) ),
			/* translators: %s: link to the Documents section */
			sprintf( esc_html__( 'In %s, fill in your company details and choose when invoices are made. Add your logo for the PDF documents.', 'nashenski-commerce' ), self::link( 'documents', __( 'Documents', 'nashenski-commerce' ) ) ),
			/* translators: %s: link to the Exports section */
			sprintf( esc_html__( 'Every month, create the NRA audit file in %s and submit it by the 15th, or have it emailed to your accountant.', 'nashenski-commerce' ), self::link( 'exports', __( 'Exports', 'nashenski-commerce' ) ) ),
		);
	}

	/**
	 * Title => lines (HTML allowed, already escaped).
	 */
	private static function topics() {
		$overview = self::link( '', __( 'Overview', 'nashenski-commerce' ) );
		$couriers = self::link( 'couriers', __( 'Couriers', 'nashenski-commerce' ) );
		$checkout = self::link( 'checkout', __( 'Checkout', 'nashenski-commerce' ) );
		$documents = self::link( 'documents', __( 'Documents', 'nashenski-commerce' ) );
		$exports = self::link( 'exports', __( 'Exports', 'nashenski-commerce' ) );

		return array(
				__( 'Couriers and shipping methods', 'nashenski-commerce' ) => array(
					/* translators: %s: link to the Overview section */
					sprintf( esc_html__( 'Turn a courier on in %s. Its page appears in Couriers and its methods can be added to your shipping zones.', 'nashenski-commerce' ), $overview ),
					esc_html__( 'Add one method per delivery type to a zone: to an office, to a parcel locker and to an address. BOX NOW has one method, for its lockers.', 'nashenski-commerce' ),
					/* translators: %s: link to the Couriers section */
					sprintf( esc_html__( 'Enter the login in %s. Demo logins only reach the courier\'s test system, where nothing is really shipped. If the courier refuses a login, the courier page says so.', 'nashenski-commerce' ), $couriers ),
					esc_html__( 'On the courier page, fill in "Where you send from" and the sender details. They stay greyed out until the courier accepts the login.', 'nashenski-commerce' ),
					esc_html__( 'Without a login, customers can still choose: Econt offices and lockers from Econt\'s list, Speedy offices on Speedy\'s map, Pigeon Express offices and lockers and BOX NOW lockers from the lists they publish. CVC offices need a login. Waybills always need one.', 'nashenski-commerce' ),
					esc_html__( 'Cities, offices and lockers are kept on your site and refreshed once a week. "Reload offices and cities" on a courier page refreshes them at once.', 'nashenski-commerce' ),
				),
				__( 'Shipping prices', 'nashenski-commerce' ) => array(
					esc_html__( 'With a login, the courier calculates each price with your contract. Without one, or when the courier does not answer, the courier\'s public price list is used. You can edit it on the courier page and compare it with the courier website.', 'nashenski-commerce' ),
					esc_html__( 'To compare, open the courier page and press "Check the courier website" in "Public prices". You see what changed, and new prices are filled in only when you choose. It also checks once a week by itself.', 'nashenski-commerce' ),
					esc_html__( 'Each shipping method in a zone can have a free shipping amount and a fixed price.', 'nashenski-commerce' ),
					esc_html__( 'When a courier publishes new prices, a notice says so. Save that courier\'s prices or press Dismiss.', 'nashenski-commerce' ),
				),
				__( 'Checkout', 'nashenski-commerce' ) => array(
					esc_html__( 'Works with the classic checkout, the block checkout and checkout pages made with a page builder, including step-by-step checkouts.', 'nashenski-commerce' ),
					esc_html__( 'When the customer picks a courier, they choose an office, a locker or an address right under it, from a list or on the courier\'s map. Until then the option says "Complete the fields to calculate".', 'nashenski-commerce' ),
					/* translators: %s: link to the Checkout section */
					sprintf( esc_html__( 'In %s > Checkout fields you set the order, Required, Optional or Hidden and the width of every field, and add your own fields.', 'nashenski-commerce' ), $checkout ),
					esc_html__( '"Company invoice fields" adds "I want an invoice for a company" with company name, EIK, VAT number, MOL, town and address.', 'nashenski-commerce' ),
					esc_html__( '"Shipping options look" sets cards or the plain look of your theme, colours, borders, corners and spacing. Empty settings follow your design tokens or theme.', 'nashenski-commerce' ),
				),
				__( 'Waybills, couriers and order statuses', 'nashenski-commerce' ) => array(
					esc_html__( 'Open an order to create, update, print or cancel its waybill in the courier box.', 'nashenski-commerce' ),
					esc_html__( 'In the "Order actions" box of an order, and in the bulk actions of the orders list: "Create waybill" makes the waybill from the customer\'s choices, "Request a courier" books a pickup with Econt or Speedy for all the chosen waybills, "Check the shipment now" reads the status from the courier.', 'nashenski-commerce' ),
					esc_html__( 'Print the label from the order or from the orders list. Where a courier offers several print sizes, such as A4 or A6 for Speedy, choose one first.', 'nashenski-commerce' ),
					esc_html__( 'Econt courier requests skip weekends and holidays by themselves. Speedy picks the next possible day.', 'nashenski-commerce' ),
					esc_html__( '"Generate label after checkout" on a courier page makes the waybill as soon as an order is placed.', 'nashenski-commerce' ),
					/* translators: %s: link to the Overview section */
					sprintf( esc_html__( 'In %s > Waybills and order statuses: put the order number on the waybill, and choose the status orders get when the parcel is delivered or goes back to you. Open orders are checked every hour.', 'nashenski-commerce' ), $overview ),
					esc_html__( 'Customers see "Track your parcel" with the waybill number on their order page, and get it in the order emails.', 'nashenski-commerce' ),
				),
				__( 'Quick order', 'nashenski-commerce' ) => array(
					esc_html__( 'Turn it on in WooCommerce > Settings > Nashenski Commerce > Quick order. A "Quick order" button then shows on the product page and/or the cart, for shoppers who do not want to go through checkout.', 'nashenski-commerce' ),
					esc_html__( 'Choose how the form opens (dialog, inline or side panel) and its fields: a ready-made template or a Custom one. Name, phone and email are always asked, and only Bulgarian phone numbers are accepted.', 'nashenski-commerce' ),
					esc_html__( 'Each submission becomes an order with the status "Quick order". No stock is reserved and no payment link is sent until you change the status. You get the WooCommerce "New order" email.', 'nashenski-commerce' ),
					esc_html__( 'To ship it, open the order. In the Delivery box choose the shipping option, the town and the office, locker or address, then press "Save delivery". The courier box for the label appears, and the price comes when the label is made. The customer then gets an email with the delivery price and the new total.', 'nashenski-commerce' ),
				),
				__( 'Abandoned carts', 'nashenski-commerce' ) => array(
					esc_html__( 'Turn it on in WooCommerce > Settings > Nashenski Commerce > Abandoned carts. It is off by default.', 'nashenski-commerce' ),
					esc_html__( 'It keeps the carts that were not ordered and records the email as soon as the shopper types it, even at checkout without an account. Mention this in your privacy policy.', 'nashenski-commerce' ),
					esc_html__( 'Set when the reminders go out (by default 1, 24 and 72 hours after the last change, at most three, only between 08:00 and 22:00) and whether each cart gets a single-use coupon. Edit the "Abandoned cart reminder" email in WooCommerce > Settings > Emails.', 'nashenski-commerce' ),
					esc_html__( 'The link in the email refills the cart. Every email has an unsubscribe link, and a shopper who unsubscribes gets no more reminders.', 'nashenski-commerce' ),
					esc_html__( 'See the carts in WooCommerce > Abandoned carts. Filter them by status, send a reminder by hand or delete carts. Old carts are deleted once the retention period ends.', 'nashenski-commerce' ),
				),
				__( 'Invoices and documents', 'nashenski-commerce' ) => array(
					esc_html__( 'The order document is made for every order. Invoices are made as set in "When to generate invoices?", and credit notes for refunds.', 'nashenski-commerce' ),
					esc_html__( 'To make an invoice by hand, open the order and use "Make invoice" in the PDF Files box. You can change the number; the next automatic number continues after it.', 'nashenski-commerce' ),
					/* translators: %s: link to the Documents section */
					sprintf( esc_html__( 'The look of the PDF files (logo, colours, font, spacing) is in %s > PDF appearance. Every document is made in Bulgarian and in English.', 'nashenski-commerce' ), $documents ),
					/* translators: %s: link to the Documents section */
					sprintf( esc_html__( 'Your company details, the numbering and when documents are made are in %s > Company details and Invoices and documents.', 'nashenski-commerce' ), $documents ),
					esc_html__( 'To save disk space, set "Delete order documents after (days)" in Documents > Invoices and documents. Only the order document PDFs of older orders are deleted; invoices, credit notes and refund documents stay. "Delete all order documents now" does it at once, after a confirmation.', 'nashenski-commerce' ),
					esc_html__( 'Copies can go to your accountant by email, and the monthly NRA audit file too.', 'nashenski-commerce' ),
				),
				__( 'NRA audit file and exports', 'nashenski-commerce' ) => array(
					/* translators: %s: link to the Exports section */
					sprintf( esc_html__( '%s has the NRA audit file (Ordinance N-18), the invoice archive and the Microinvest Delta file for a chosen month. The audit file is checked against the NRA schema and has no order limit.', 'nashenski-commerce' ), $exports ),
					esc_html__( 'Choose the month and press "Create XML file" for the NRA, "Download archive" for the invoices or "Create file" for Delta.', 'nashenski-commerce' ),
					esc_html__( 'The custom export takes orders, products sold or documents, with your filters, columns and order, as CSV, Excel, JSON or XML. Save an export to run it again next month.', 'nashenski-commerce' ),
				),
				__( 'EU VAT number check', 'nashenski-commerce' ) => array(
					esc_html__( 'Turn on "Enable VIES validation" in Documents > Invoices and documents. A company customer\'s VAT number is then checked with the European VIES system when the order is placed.', 'nashenski-commerce' ),
					esc_html__( 'An error shows only when the number is not valid or when the check itself fails. The option needs the PHP SOAP extension on your server.', 'nashenski-commerce' ),
				),
				__( 'EU withdrawal function', 'nashenski-commerce' ) => array(
					/* translators: %s: link to the Checkout section */
					sprintf( esc_html__( 'Turn it on in %s > EU withdrawal function. A "Withdraw from contract here" link appears at the bottom of every page, on each order in My Account and in the order emails.', 'nashenski-commerce' ), $checkout ),
					esc_html__( 'Place the link yourself with the shortcode [nashenski_withdrawal_link] (button="yes" makes it a button).', 'nashenski-commerce' ),
					esc_html__( 'Requests show in the orders list ("Withdrawal" column) and on the order, with everything the customer submitted.', 'nashenski-commerce' ),
				),
				__( 'Customer checks', 'nashenski-commerce' ) => array(
					esc_html__( 'nekorekten.com, Connectix and NepoStop tell you whether a phone number has a history of refused parcels. Nashenski Commerce can show a warning on the order and hide cash on delivery for such customers.', 'nashenski-commerce' ),
					esc_html__( 'Turn a service on in Overview, save, then enter its API key or token in Customer checks. A service without a key is not asked.', 'nashenski-commerce' ),
					esc_html__( 'On the order, the Reports box shows the answers. Press "Refresh data" to ask again.', 'nashenski-commerce' ),
					esc_html__( 'For nekorekten.com you can report a customer yourself: write what happened and press "Send report". The report carries the name, phone and email of the order. Change the text and press "Save report", or press "Delete report" to remove it from nekorekten.com.', 'nashenski-commerce' ),
					esc_html__( 'Connectix and NepoStop can also get tracking information or labels from your orders. Both are optional.', 'nashenski-commerce' ),
				),
				__( 'Mathematically impossible prices', 'nashenski-commerce' ) => array(
					esc_html__( 'Open Products > Mathematically impossible prices and press "Scan products". It lists the prices that cannot be split into a price without VAT and the VAT without rounding.', 'nashenski-commerce' ),
					esc_html__( 'Press "Fix all" to correct them. The check matters most when your prices are entered including tax.', 'nashenski-commerce' ),
				),
				__( 'Moving from another plugin', 'nashenski-commerce' ) => array(
					esc_html__( 'Nashenski Commerce imports from Bulgarisation for WooCommerce, the mreja.net plugins (Econt Express, BOX NOW Delivery, Ordinance N-18), Drusoft Shipping for Econt and for Speedy, Econt Delivery and Speedy Shipping.', 'nashenski-commerce' ),
					esc_html__( 'It takes over the courier logins, sender details, the waybill numbers of your orders, the shipping zone methods and, from the N-18 plugin, the company details and document numbers. Then the old plugin is switched off. Its own data stays in the database.', 'nashenski-commerce' ),
					esc_html__( 'Econt Delivery and Drusoft Shipping for Econt use a different Econt key: enter your Econt username and password after the import.', 'nashenski-commerce' ),
				),
				__( 'Updates', 'nashenski-commerce' ) => array(
					esc_html__( 'New versions show up in Dashboard > Updates and on the Plugins screen, like any other plugin update. Automatic updates can be turned on there too.', 'nashenski-commerce' ),
				),
				__( 'When something does not work', 'nashenski-commerce' ) => array(
					esc_html__( '"Nothing found" under a courier: check the login on the courier page. In BOX NOW demo mode there are only two test lockers, both in Sofia.', 'nashenski-commerce' ),
					esc_html__( 'No courier options at checkout: add the methods to the shipping zone of the customer\'s address and give your products a weight.', 'nashenski-commerce' ),
					esc_html__( '"Complete the fields to calculate" does not go away: the customer has not chosen an office, a locker or an address yet.', 'nashenski-commerce' ),
					esc_html__( 'No documents: turn on "Invoices and N-18" in Overview and check "When to generate documents?" in Documents.', 'nashenski-commerce' ),
				),
		);
	}

	/**
	 * Plugins whose data can be imported, with their state and the import button.
	 */
	private static function output_import() {
		$rows = array();

		if ( Migration::has_old_data() ) {
			$rows[] = array( __( 'Bulgarisation for WooCommerce', 'nashenski-commerce' ), get_option( Migration::DONE_OPTION ), wp_nonce_url( admin_url( 'admin-post.php?action=' . Migration::ACTION ), Migration::ACTION ) );
		}

		foreach ( Import_Sources::sources() as $slug => $source ) {
			$rows[] = array( $source['name'], $source['done'], Import_Sources::url( $slug ) );
		}

		if ( ! $rows ) {
			return;
		}
		?>
		<h2 id="import"><?php esc_html_e( 'Moving from another plugin', 'nashenski-commerce' ); ?></h2>
		<p><?php esc_html_e( 'These plugins are installed or left data on your site. Import takes over what Nashenski Commerce can use and switches the plugin off.', 'nashenski-commerce' ); ?></p>
		<table class="widefat striped nashenski-import">
			<tbody>
				<?php foreach ( $rows as list( $name, $done, $url ) ) : ?>
					<tr>
						<td><?php echo esc_html( $name ); ?></td>
						<td>
							<?php if ( $done ) : ?>
								<?php
								/* translators: %s: date */
								echo esc_html( sprintf( __( 'Imported on %s', 'nashenski-commerce' ), wp_date( get_option( 'date_format' ), (int) ( $done['time'] ?? 0 ) ) ) );
								?>
							<?php else : ?>
								<a class="button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Import and switch off', 'nashenski-commerce' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private static function output_applied_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		if ( ! isset( $_GET['nashenski_recommended_applied'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count = absint( $_GET['nashenski_recommended_applied'] );

		echo '<div class="notice notice-success inline"><p>' . esc_html( sprintf(
			/* translators: %d: number of settings */
			_n( '%d setting changed.', '%d settings changed.', $count, 'nashenski-commerce' ),
			$count
		) ) . '</p></div>';
	}

	/**
	 * Table of recommended settings with a checkbox per row. The button submits WooCommerce's
	 * settings form to admin-post (formaction), because forms cannot be nested.
	 */
	private static function output_recommended() {
		$items = Recommended_Settings::items();
		$open = 0;
		?>
		<h2 id="recommended"><?php esc_html_e( 'Recommended settings', 'nashenski-commerce' ); ?></h2>
		<p><?php esc_html_e( 'Settings that suit most Bulgarian stores. Tick the ones you want and apply them; nothing else changes.', 'nashenski-commerce' ); ?></p>
		<table class="widefat striped nashenski-recommended">
			<thead>
				<tr>
					<td class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Apply', 'nashenski-commerce' ); ?></span></td>
					<th><?php esc_html_e( 'Setting', 'nashenski-commerce' ); ?></th>
					<th><?php esc_html_e( 'Now', 'nashenski-commerce' ); ?></th>
					<th><?php esc_html_e( 'Recommended', 'nashenski-commerce' ); ?></th>
					<th><?php esc_html_e( 'Why', 'nashenski-commerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $items as $key => $item ) : ?>
					<?php
					$current = Recommended_Settings::current( $item );
					$same = $current === (string) $item['value'];
					$open += $same ? 0 : 1;
					?>
					<tr>
						<th scope="row" class="check-column">
							<?php if ( $same ) : ?>
								<span class="dashicons dashicons-yes" aria-label="<?php esc_attr_e( 'Already set', 'nashenski-commerce' ); ?>"></span>
							<?php else : ?>
								<input type="checkbox" name="nashenski_recommended[]" value="<?php echo esc_attr( $key ); ?>" id="nashenski-recommended-<?php echo esc_attr( $key ); ?>" checked>
							<?php endif; ?>
						</th>
						<td><label for="nashenski-recommended-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $item['label'] ); ?></label></td>
						<td><?php echo esc_html( Recommended_Settings::readable( $item, $current ) ); ?></td>
						<td><?php echo esc_html( Recommended_Settings::readable( $item, (string) $item['value'] ) ); ?></td>
						<td><?php echo esc_html( $item['why'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<?php if ( $open ) : ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( Recommended_Settings::ACTION ); ?>">
				<?php wp_nonce_field( Recommended_Settings::ACTION, Recommended_Settings::NONCE ); ?>
				<button type="submit" class="button button-primary" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" formmethod="post"><?php esc_html_e( 'Apply recommended settings', 'nashenski-commerce' ); ?></button>
			<?php else : ?>
				<em><?php esc_html_e( 'All recommended settings are in place.', 'nashenski-commerce' ); ?></em>
			<?php endif; ?>
		</p>
		<?php
	}
}
