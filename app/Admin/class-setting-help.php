<?php
/**
 * Setting help.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Descriptions and tooltips of every setting, in one place. Each entry is
 * array( description under the field, tooltip ); a description is only used when the field has
 * none of its own. Keys are setting IDs, or patterns starting with "/".
 */
class Setting_Help {
	/**
	 * Constructor.
	 */
	public function __construct() {
		foreach ( array( 'econt', 'speedy', 'boxnow', 'pigeon' ) as $courier ) {
			add_filter( 'woocommerce_shipping_instance_form_fields_nashenski_' . $courier, array( __CLASS__, 'apply_to_method' ) );
		}
	}

	/**
	 * Texts.
	 *
	 * @return array Result.
	 */
	private static function texts() {
		$login_tip = __( 'Demo works with the courier\'s test system and nothing is really shipped. Switch to Live with your real login before you start sending.', 'nashenski-commerce' );

		return array(
			// Overview.
			'nashenski_settings_apis_enable_documents'     => array( '', __( 'Turns on invoices, credit notes, the PDF look and the NRA audit file. The order document is made for every order either way.', 'nashenski-commerce' ) ),
			'nashenski_settings_automation_order_number'   => array( '', __( 'The courier prints it on the waybill, so whoever packs or delivers the parcel knows which order it is.', 'nashenski-commerce' ) ),
			'nashenski_settings_automation_delivered'      => array( __( 'The status an order gets when the courier reports the parcel delivered.', 'nashenski-commerce' ), __( 'Usually Completed. "Do not change" only adds a note to the order.', 'nashenski-commerce' ) ),
			'nashenski_settings_automation_returned'       => array( '', __( 'For example Cancelled or On hold. The note on the order says what the courier reported.', 'nashenski-commerce' ) ),

			// Courier logins and price lists.
			'/^nashenski_settings_(econt|boxnow|pigeon)_env$/' => array( '', $login_tip ),
			'nashenski_settings_econt_user'                => array( __( 'The username of your Econt account.', 'nashenski-commerce' ), __( 'For a test, choose Demo and use the demo login shown under Environment.', 'nashenski-commerce' ) ),
			'nashenski_settings_econt_password'            => array( __( 'The password of your Econt account.', 'nashenski-commerce' ), __( 'Stored on your site and sent only to Econt.', 'nashenski-commerce' ) ),
			'nashenski_settings_speedy_user'               => array( __( 'The Speedy username for online shops. It is not your MySpeedy login.', 'nashenski-commerce' ), __( 'Speedy gives it with your contract. Without a contract, write to telesales@speedy.bg.', 'nashenski-commerce' ) ),
			'nashenski_settings_speedy_password'           => array( __( 'The password that goes with the Speedy username.', 'nashenski-commerce' ), __( 'Stored on your site and sent only to Speedy.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_boxnow_client_(id|secret)$/' => array( __( 'From the email BOX NOW sends with your contract.', 'nashenski-commerce' ), __( 'Demo and live have different keys. Choose the environment that matches them.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_pigeon_api_(key|secret)$/' => array( __( 'Given by Pigeon Express for your account.', 'nashenski-commerce' ), __( 'Without them customers still choose Pigeon offices and lockers from Pigeon\'s public list, but waybills need them.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_prices_use_public_/'     => array( '', __( 'When the courier cannot price an order (no login, or no answer), the price comes from the list below. Off: the option shows no price then.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_prices_(econt|speedy|pigeon)$/' => array( __( 'The courier\'s published prices by weight for each delivery type.', 'nashenski-commerce' ), __( 'Edit them, or press "Check the courier website" to compare with the latest published prices.', 'nashenski-commerce' ) ),

			// Courier settings that appear once the login works.
			'/_profile_key$/'                              => array( '', __( 'An account can have several sender profiles. Waybills are made with the one you choose.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_(econt|speedy|boxnow_send_from)_name$/' => array( __( 'Sender contact on the waybill.', 'nashenski-commerce' ), __( 'The courier contacts this person about pickups and problems with a parcel.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_(econt|speedy|boxnow_send_from)_phone$/' => array( __( 'Sender phone on the waybill.', 'nashenski-commerce' ), __( 'The courier calls this number about pickups and problems with a parcel.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_boxnow_send_from_email$/' => array( __( 'Sender email on the waybill.', 'nashenski-commerce' ), __( 'The courier writes to this address about your parcels.', 'nashenski-commerce' ) ),
			'/_force_variations_in_desc$/'                 => array( '', __( 'Use it only when the waybill contents miss sizes or colours of the products.', 'nashenski-commerce' ) ),
			'/_label_after_checkout$/'                     => array( '', __( 'The waybill is made as soon as the order is placed and its number goes into the order emails. Leave it off if you check orders before shipping.', 'nashenski-commerce' ) ),
			'/_declared_value$/'                           => array( '', __( 'Insures the parcel for the order value. The courier charges a little extra for it.', 'nashenski-commerce' ) ),
			'/_auto_size$/'                                => array( '', __( 'Uses the product dimensions to fit the order into the courier\'s box sizes. Products without dimensions count as the default size.', 'nashenski-commerce' ) ),
			'nashenski_settings_econt_pay_options'         => array( '', __( 'Your cash on delivery agreement with Econt: how and when Econt pays the collected money to you.', 'nashenski-commerce' ) ),
			'nashenski_settings_econt_sender_payment_type' => array( '', __( 'How you pay Econt for the shipments you send.', 'nashenski-commerce' ) ),
			'nashenski_settings_econt_invoice_or_packing_list' => array( '', __( 'Econt shows the customer either your invoice number or a packing list of the products.', 'nashenski-commerce' ) ),
			'/_send_from$/'                                => array( '', __( 'Address: the courier comes to you. Office: you take the parcels to a courier office, which is often cheaper. Save to see the next fields.', 'nashenski-commerce' ) ),
			'/_send_from_(address|office|city|office_city)$/' => array( '', __( 'Where your parcels start. Prices are calculated from here.', 'nashenski-commerce' ) ),
			'nashenski_settings_speedy_ppp'                => array( '', __( 'Speedy pays the collected money back by postal money transfer. Needs that agreement with Speedy and cannot be combined with ordinary cash on delivery.', 'nashenski-commerce' ) ),
			'nashenski_settings_speedy_kb'                 => array( '', __( 'Speedy issues the cash receipt for the collected amount on your behalf. Needs that agreement with Speedy.', 'nashenski-commerce' ) ),
			'nashenski_settings_speedy_administrative_fee' => array( '', __( 'Adds the administrative fee from your Speedy contract to the price, when the customer pays for delivery.', 'nashenski-commerce' ) ),
			'nashenski_settings_boxnow_send_from_location' => array( '', __( 'The warehouse or locker BOX NOW collects your parcels from.', 'nashenski-commerce' ) ),
			'nashenski_settings_boxnow_send_from_allow_return' => array( '', __( 'The customer can return the parcel at a BOX NOW locker.', 'nashenski-commerce' ) ),
			'nashenski_settings_boxnow_price_price_type'   => array( '', __( 'BOX NOW has no price calculation in its API, so you set the prices here. Save after choosing to see the right table.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_boxnow_price_from_to_/'  => array( '', __( 'Each row is a range and the price the customer pays in it.', 'nashenski-commerce' ) ),
			'nashenski_settings_boxnow_price_hide_over'    => array( '', __( 'For example 200: BOX NOW is not offered for bigger orders.', 'nashenski-commerce' ) ),
			'nashenski_settings_boxnow_price_free_shipping_over' => array( '', __( 'Orders at or above this total ship for free with BOX NOW.', 'nashenski-commerce' ) ),
			'nashenski_settings_boxnow_price_box_size'     => array( '', __( 'The locker compartment size for orders whose products have no dimensions.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_pigeon_services_/'       => array( '', __( 'An extra Pigeon Express service. It may cost extra and must be in your contract.', 'nashenski-commerce' ) ),

			// Checkout.
			'nashenski_settings_checkout_countries'        => array( '', __( '"As set in WooCommerce" keeps the countries of WooCommerce > Settings > General.', 'nashenski-commerce' ) ),
			'nashenski_settings_checkout_courier_logos'    => array( '', __( 'Customers recognise the courier at a glance.', 'nashenski-commerce' ) ),
			'nashenski_settings_checkout_transliteration'  => array( '', __( 'Couriers search addresses in Cyrillic, so Latin addresses are converted. Each order also has a "Transliterate addresses" action.', 'nashenski-commerce' ) ),
			'nashenski_settings_checkout_transliterate_names' => array( '', __( 'Names on the waybill then match the customer\'s ID card.', 'nashenski-commerce' ) ),
			'nashenski_settings_company_fields'            => array( '', __( 'The details go on the invoice and into the NRA audit file. In a builder checkout of single field elements, the fields come after the billing fields unless you place them.', 'nashenski-commerce' ) ),
			'nashenski_settings_company_invoice_numbering' => array( '', __( 'Changing it later does not move invoices already made in or out of the NRA audit file. Ask your accountant which one fits your shop.', 'nashenski-commerce' ) ),
			'nashenski_settings_fields_classic_order_comments' => array( '', __( 'Hidden: customers cannot leave a note with the order.', 'nashenski-commerce' ) ),
			'nashenski_settings_appearance_layout'         => array( __( 'Cards with a border, buttons side by side, or the plain list of your theme.', 'nashenski-commerce' ), __( 'Cards make the chosen courier stand out. Side by side saves space when there are few options.', 'nashenski-commerce' ) ),
			'nashenski_settings_appearance_show_type'      => array( '', __( 'Shows "To an office", "To a parcel locker" or "To your address" under each option.', 'nashenski-commerce' ) ),
			'nashenski_settings_appearance_free_over_label' => array( '', __( 'Uses the "Free shipping over" total of each shipping method. Customers see what they need to spend to ship for free.', 'nashenski-commerce' ) ),
			'nashenski_settings_appearance_free_progress'  => array( '', __( 'On the cart and checkout. Uses the lowest "Free shipping over" total among the options offered.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_appearance_(accent|selected_bg|border|muted|field_border|field_bg)$/' => array( __( 'Empty: the colour of your design tokens or theme.', 'nashenski-commerce' ), __( 'Any CSS colour, for example #1a7f37 or var(--primary).', 'nashenski-commerce' ) ),
			'/^nashenski_settings_appearance_(radius|field_radius|gap|logo_height)$/' => array( __( 'Empty: the size of your design tokens or theme.', 'nashenski-commerce' ), __( 'Any CSS size, for example 8px, 0.5rem or var(--space-s).', 'nashenski-commerce' ) ),
			'woocommerce_feature_order_withdrawal_enabled' => array( '', __( 'Required by EU law from 19 June 2026 for shops with online checkout.', 'nashenski-commerce' ) ),
			'nashenski_settings_withdrawal_placement'      => array( '', __( 'Or place the link yourself with the shortcode [nashenski_withdrawal_link].', 'nashenski-commerce' ) ),
			'nashenski_settings_withdrawal_days'           => array( '', __( '14 days is the legal minimum; give more if your terms promise more.', 'nashenski-commerce' ) ),

			// Documents.
			'nashenski_settings_nap_company_name'          => array( __( 'As in the Commercial Register.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_nap_mol'                   => array( __( 'The person who represents the company.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_nap_eik'                   => array( __( '9 or 13 digits. The NRA audit file cannot be made without it.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_nap_vat_113_9'             => array( '', __( 'For a company that is not registered for VAT: invoices carry the note about Article 113(9), and invoices, proformas and credit notes show no VAT. Works while WooCommerce taxes are off.', 'nashenski-commerce' ) ),
			'nashenski_settings_nap_dds_number'            => array( __( 'Starts with BG. Leave empty if you are not registered for VAT.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_nap_dds_number_required'   => array( '', __( 'Customers who ask for a company invoice must then enter the company VAT number.', 'nashenski-commerce' ) ),
			'nashenski_settings_nap_phone_number'          => array( __( 'Shown on your documents.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_nap_city'                  => array( __( 'Town of the registered address.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_nap_address'               => array( __( 'Registered address, shown on invoices.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_nap_email'                 => array( __( 'Shown on your documents.', 'nashenski-commerce' ), __( 'Customers and the NRA may write to this address about documents.', 'nashenski-commerce' ) ),
			'nashenski_settings_nap_nap_number'            => array( __( 'The NRA gives it when you register the online shop.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_nap_domain'                => array( __( 'The shop address as registered with the NRA.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_invoice_nra_n18'           => array( '', __( 'Needed by shops that take card or bank payments without a fiscal receipt (Ordinance N-18, Article 52o).', 'nashenski-commerce' ) ),
			'nashenski_settings_invoice_invoices'          => array( __( 'Which orders get an invoice automatically.', 'nashenski-commerce' ), __( '"Only for companies" makes invoices for customers who ticked "I want an invoice for a company". Any order can also get one by hand.', 'nashenski-commerce' ) ),
			'nashenski_settings_invoice_trigger'           => array( __( 'When the order document and invoice are made.', 'nashenski-commerce' ), __( '"Order completed" waits until you complete the order, so cancelled orders get no documents.', 'nashenski-commerce' ) ),
			'nashenski_settings_invoice_next_invoice_number' => array( __( 'The number of the next order document, 10 digits.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_invoice_next_invoice_separate_number' => array( __( 'Leave empty to number invoices together with order documents.', 'nashenski-commerce' ) . ' ' . __( 'Also used when you make an invoice by hand.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_invoice_due_days'          => array( __( 'Days from the invoice date to the payment due date.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_invoice_prepared_by'       => array( __( 'Printed as the person who prepared the document.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_invoice_identification_code' => array( '', __( 'Bulgarian invoices may carry an identification code instead of a signature.', 'nashenski-commerce' ) ),
			'nashenski_settings_invoice_footer_text'       => array( __( 'Printed at the bottom of every document.', 'nashenski-commerce' ), __( 'For example your bank account or a thank-you line.', 'nashenski-commerce' ) ),
			'nashenski_settings_invoice_total_text'        => array( '', __( 'Bulgarian invoices often show the total in words.', 'nashenski-commerce' ) ),
			'nashenski_settings_invoice_separate_copy_documents' => array( '', __( 'Turn on to send the copies to your accountant.', 'nashenski-commerce' ) ),
			'nashenski_settings_invoice_accountant_email'  => array( __( 'Your accountant\'s email address.', 'nashenski-commerce' ), '' ),
			'nashenski_settings_invoice_monthly_nra_email' => array( '', __( 'The file is due by the 15th of the next month. Your accountant gets it on the 1st.', 'nashenski-commerce' ) ),
			'nashenski_settings_invoice_digital_tax_classes' => array( '', __( 'Products in these tax classes count as digital goods for EU VAT: the customer\'s country rate applies.', 'nashenski-commerce' ) ),
			'nashenski_settings_shop_vat_group'            => array( '', __( 'The VAT group of your sales in the NRA audit file. Most shops sell in group B (20%).', 'nashenski-commerce' ) ),
			'nashenski_settings_shop_return_method'        => array( __( 'How you pay customers back for cancelled orders.', 'nashenski-commerce' ), __( 'Goes into the NRA audit file for refunded orders.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_shippings_.+_is_courier$/' => array( '', __( 'Yes: for cash on delivery orders with this method, the delivery charge is left out of the order document and the NRA file, because the courier collects it and gives its own receipt.', 'nashenski-commerce' ) ),
			'/_payment_type$/'                             => array( __( 'The NRA payment type of this payment method.', 'nashenski-commerce' ), __( 'How the NRA audit file reports payments made with this method.', 'nashenski-commerce' ) ),
			'/_virtual_pos_number$/'                       => array( __( 'The number of the virtual POS terminal from your bank.', 'nashenski-commerce' ), __( 'Needed in the NRA audit file for card payments.', 'nashenski-commerce' ) ),
			'/_identifier$/'                               => array( __( 'The identifier of the payment provider, if it gave you one.', 'nashenski-commerce' ), __( 'Goes into the NRA audit file with each payment.', 'nashenski-commerce' ) ),
			'nashenski_settings_pdf_logo'                  => array( '', __( 'A PNG or JPG about 600 pixels wide prints sharp.', 'nashenski-commerce' ) ),
			'nashenski_settings_pdf_logo_size'             => array( __( 'How big the logo is at the top of the document.', 'nashenski-commerce' ), __( 'Larger logos push the document down; Small keeps long orders on one page.', 'nashenski-commerce' ) ),
			'nashenski_settings_pdf_show_site_details'     => array( '', __( 'Turn off when your logo already has these details.', 'nashenski-commerce' ) ),
			'nashenski_settings_pdf_follow_tokenforge'     => array( '', __( 'Documents then keep the colours of your site.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_pdf_(title_color|accent_color|text_color|table_color)$/' => array( __( 'Pick a colour or type a hex code.', 'nashenski-commerce' ), __( 'Dark text on a light table header prints best.', 'nashenski-commerce' ) ),
			'nashenski_settings_pdf_font'                  => array( '', __( 'Both have Cyrillic letters.', 'nashenski-commerce' ) ),
			'nashenski_settings_pdf_font_size'             => array( __( 'Size of the document text.', 'nashenski-commerce' ), __( 'Smaller text fits more products on a page.', 'nashenski-commerce' ) ),
			'nashenski_settings_pdf_paper'                 => array( __( 'The page size of the PDF documents.', 'nashenski-commerce' ), __( 'A4 suits most office printers.', 'nashenski-commerce' ) ),
			'nashenski_settings_pdf_density'               => array( '', __( 'Compact fits long orders on one page.', 'nashenski-commerce' ) ),
			'nashenski_settings_pdf_date_format'           => array( __( 'How dates look on the documents.', 'nashenski-commerce' ), __( 'Bulgarian documents usually use day.month.year.', 'nashenski-commerce' ) ),
			'nashenski_settings_pdf_show_contacts'         => array( '', __( 'Turn off to keep phone numbers and emails off printed documents.', 'nashenski-commerce' ) ),
			'nashenski_settings_pdf_show_received'         => array( '', __( 'The space where the customer signs on receipt.', 'nashenski-commerce' ) ),

			// Customer checks.
			'nashenski_settings_reports_column'            => array( '', __( 'A warning sign in the orders list for customers with reports.', 'nashenski-commerce' ) ),
			'nashenski_settings_reports_check_on_checkout' => array( '', __( 'Customers with refused parcels then pay online or by bank transfer.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_(nekorekten|nepostop|connectix)_api_key$/' => array( '', __( 'From your account on the service\'s website.', 'nashenski-commerce' ) ),
			'/^nashenski_settings_(nekorekten|nepostop|connectix)_level_of_warning$/' => array( '', __( 'Lower means stricter: more customers get a warning.', 'nashenski-commerce' ) ),
			'nashenski_settings_nepostop_upload_labels'    => array( '', __( 'Shares your waybills with NepoStop, so its data about refused parcels grows. Tell your customers in your privacy policy.', 'nashenski-commerce' ) ),

			// Shipping methods in WooCommerce > Settings > Shipping zones.
			'method:title'                                 => array( __( 'The name customers see at checkout.', 'nashenski-commerce' ), __( 'Short is best, for example "To an office". The courier logo is shown next to it.', 'nashenski-commerce' ) ),
			'method:delivery_type'                         => array( __( 'Add one method per delivery type to the zone.', 'nashenski-commerce' ), __( 'Office and parcel locker show a list and a map; address shows the street search.', 'nashenski-commerce' ) ),
			'method:test'                                  => array( __( 'Whether the customer may open or test the parcel before paying.', 'nashenski-commerce' ), __( 'Not possible at parcel lockers. If the customer refuses after a test, the return is paid as your contract says.', 'nashenski-commerce' ) ),
			'method:sms'                                   => array( '', __( 'The courier texts the customer about the delivery. It may cost extra.', 'nashenski-commerce' ) ),
			'method:free_shipping_over'                    => array( __( 'Orders at or above this total ship for free with this method.', 'nashenski-commerce' ), __( 'You still pay the courier; the customer sees "Free".', 'nashenski-commerce' ) ),
			'method:international'                         => array( __( 'Also deliver to customers in other countries. Prices come only from the courier.', 'nashenski-commerce' ), '' ),
			'method:free_shipping_by_country'              => array( __( 'Free shipping for customers abroad, per country: RO=150, GR=200.', 'nashenski-commerce' ), '' ),
			'method:fixed_price'                           => array( __( 'A set delivery price instead of the courier\'s calculation.', 'nashenski-commerce' ), __( 'The difference to the real courier price is yours.', 'nashenski-commerce' ) ),
		);
	}

	/**
	 * Find.
	 *
	 * @param mixed $id Id.
	 * @return mixed|null Result.
	 */
	private static function find( $id ) {
		static $texts = null;
		$texts        = $texts ?? self::texts();

		if ( isset( $texts[ $id ] ) ) {
			return $texts[ $id ];
		}

		foreach ( $texts as $key => $text ) {
			if ( '/' === $key[0] && preg_match( $key, $id ) ) {
				return $text;
			}
		}

		return null;
	}

	/**
	 * Settings of a section (WooCommerce settings arrays).
	 *
	 * @param mixed $settings Settings.
	 */
	public static function apply( $settings ) {
		foreach ( $settings as &$setting ) {
			$text = empty( $setting['id'] ) || in_array( $setting['type'] ?? '', array( 'title', 'sectionend' ), true ) ? null : self::find( $setting['id'] );

			if ( ! $text ) {
				continue;
			}

			if ( '' !== $text[0] && '' === trim( wp_strip_all_tags( (string) ( $setting['desc'] ?? '' ) ) ) ) {
				$setting['desc'] = $text[0];
			}

			if ( '' !== $text[1] && ! is_string( $setting['desc_tip'] ?? null ) ) {
				// A string tooltip keeps the description visible under the field.
				$setting['desc_tip'] = $text[1];
			}
		}

		return $settings;
	}

	/**
	 * Shipping method settings in a shipping zone.
	 *
	 * @param mixed $fields Fields.
	 */
	public static function apply_to_method( $fields ) {
		foreach ( $fields as $key => &$field ) {
			$text = self::find( 'method:' . $key );

			if ( ! $text ) {
				continue;
			}

			$description = trim( (string) ( $field['description'] ?? '' ) );

			// "desc_tip => true" showed the description as the tooltip: keep it there, the new text goes under the field.
			$tip                  = true === ( $field['desc_tip'] ?? false ) && '' !== $description ? $description : $text[1];
			$field['description'] = true === ( $field['desc_tip'] ?? false ) || '' === $description ? $text[0] : $description;

			if ( '' !== $tip ) {
				$field['desc_tip'] = $tip;
			}
		}

		return $fields;
	}
}
