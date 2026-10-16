<?php
namespace Nashenski\Admin\Tabs;
use Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;
class Nra_Tab extends Base_Tab {
	public function __construct() {
		$this->set_name( __( 'Documents', 'nashenski-commerce' ) );
		$this->set_tab_slug( "documents" );
	}

	public function load_fields() {
		$fields = apply_filters( 'nashenski/admin/nra/fields', array(
			'nap' => array(
				new Fields\Text_Field( 'company_name', __( 'Company Name', 'nashenski-commerce' ), __( 'Your company name.', 'nashenski-commerce' ), 'required' ),
				new Fields\Text_Field( 'mol', __( 'MOL', 'nashenski-commerce' ), __( 'Your company MOL.', 'nashenski-commerce' ), 'required' ),
				new Fields\Text_Field( 'eik', __( 'EIK', 'nashenski-commerce' ), __( 'Your company EIK.', 'nashenski-commerce' ), 'required' ),
				new Fields\TrueFalse_Field( 'vat_113_9', __( 'Company registered under Article 113(9) of the Bulgarian VAT Act', 'nashenski-commerce' ) ),
				new Fields\Text_Field( 'dds_number', __( 'VAT Number', 'nashenski-commerce' ), __( 'Your company VAT Number. To be filled in if you have a VAT registration.', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'dds_number_required', __( 'VAT number required on checkout?', 'nashenski-commerce' ) ),
				new Fields\Text_Field( 'phone_number', __( 'Company phone Number', 'nashenski-commerce' ), __( 'Contact phone number of your company.', 'nashenski-commerce' ), 'required' ),
				new Fields\Text_Field( 'city', __( 'Settlement', 'nashenski-commerce' ), __( 'The place of registration of your company.', 'nashenski-commerce' ) ),
				new Fields\Text_Field( 'address', __( 'Company Address', 'nashenski-commerce' ), __( 'The registration address of your company.', 'nashenski-commerce' ), 'required' ),
				new Fields\Text_Field( 'email', __( 'Contact email', 'nashenski-commerce' ), '', 'required|email' ),
				new Fields\Text_Field( 'nap_number', __( 'NRA unique number', 'nashenski-commerce' ), __( 'The number received by the NRA when submitting information under Art. 52p according to Annex № 33.', 'nashenski-commerce' ) ),
				new Fields\Text_Field( 'domain', __( 'Domain Name', 'nashenski-commerce' ), __( 'Web address of the e-shop. It is introduced in the same way as when submitting information under Art. 52p according to Annex № 33 in the portal for electronic services of the NRA.', 'nashenski-commerce' ) ),
			),
			'invoice' => array(
				new Fields\TrueFalse_Field( 'nra_n18', __( 'Enable N-18', 'nashenski-commerce' ), null, null, __( 'Turn on the NRA audit file (XML) in Exports. The order document is made for every order either way.', 'nashenski-commerce' ) ),
				new Fields\Select_Field( array(
					'disable' => array(
						'id' => 'disable',
						'label' => __( 'Disable invoice generation', 'nashenski-commerce' ),
					),
					'only_fields' => array(
						'id' => 'only_fields',
						'label' => __( 'Add only fields on checkout', 'nashenski-commerce' ),
					),
					'only_for_company' => array(
						'id' => 'only_for_company',
						'label' => __( 'Only for companies', 'nashenski-commerce' ),
					),
					'always' => array(
						'id' => 'always',
						'label' => __( 'Always', 'nashenski-commerce' ),
					),
				), 'invoices', __( 'When to generate invoices?', 'nashenski-commerce' ) ),
				new Fields\Select_Field( array(
					'order_created' => array(
						'id' => 'order_created',
						'label' => __( 'Order created', 'nashenski-commerce' ),
					),
					'order_completed' => array(
						'id' => 'order_completed',
						'label' => __( 'Order completed', 'nashenski-commerce' ),
					)
				), 'trigger', __( 'When to generate documents?', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'remove_shipping', __( 'Remove delivery from documents', 'nashenski-commerce' ), null, null, __( 'This option will remove the delivery from invoices and XML file.', 'nashenski-commerce' ) ),
				new Fields\Text_Field( 'next_invoice_number', __( 'Next NRA Document Number', 'nashenski-commerce' ), __( 'The document number of the next order.', 'nashenski-commerce' ), 'required' ),
				new Fields\Text_Field( 'next_invoice_separate_number', __( 'Next Invoice/Credit notice Number', 'nashenski-commerce' ), __( 'If it\' filled the invoices will have different number than the NRA documents.', 'nashenski-commerce' ) ),
				new Fields\Text_Field( 'due_days', __( 'Due days', 'nashenski-commerce' ), __( 'Days added to documents due date.', 'nashenski-commerce' ) ),
				new Fields\Text_Field( 'prepared_by', __( 'Prepared by', 'nashenski-commerce' ), __( 'Enter the name of the compiler of the automatically generated documents, which will be displayed in them.', 'nashenski-commerce' ), null ),
				new Fields\Text_Field( 'identification_code', __( 'Identification code', 'nashenski-commerce' ), null, null, __( "Enter an ID in the invoices and credit memos that replace the compiler's signature.", 'nashenski-commerce' ) ),
				new Fields\Text_Field( 'footer_text', __( 'Footer text', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'total_text', __( 'Total in words', 'nashenski-commerce' ), null, null, __( 'Print the total in Bulgarian words under the table, in euro. Older orders in leva stay in leva.', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'separate_copy_documents', __( 'Generate separate copy documents', 'nashenski-commerce' ), null, null, __( 'When enabled, invoice and credit notice copies will be generated as separate documents instead of being merged with the original. The copies can be sent to an accountant email.', 'nashenski-commerce' ) ),
				new Fields\Text_Field( 'accountant_email', __( 'Accountant email', 'nashenski-commerce' ), __( 'Separate several addresses with commas. Gets the invoice and credit notice copies when separate copy documents are on, and the monthly audit file when that is on.', 'nashenski-commerce' ), null ),
				new Fields\TrueFalse_Field( 'monthly_nra_email', __( 'Email the audit file every month', 'nashenski-commerce' ), null, null, __( 'On the 1st of each month, the NRA audit file (XML, Ordinance N-18) of the month before goes to the accountant email.', 'nashenski-commerce' ) ),
				( new Fields\Text_Field(
					'delete_receipts_days',
					__( 'Delete order documents after (days)', 'nashenski-commerce' ),
					__( 'Saves disk space: the order document PDFs (Bulgarian and English) of older orders are deleted once a day. Invoices, credit notes and refund documents are kept. The document number stays on the order, so the document can be made again. Leave 0 to keep them all.', 'nashenski-commerce' ),
					null,
					// Two clicks, no script: setting descriptions go through wp_kses_post.
					sprintf(
						'<details><summary>%1$s</summary>%2$s <a class="button button-link-delete" href="%3$s">%4$s</a></details>',
						esc_html__( 'Delete all order documents now', 'nashenski-commerce' ),
						esc_html__( 'The order document PDFs of all orders are deleted. Invoices stay.', 'nashenski-commerce' ),
						esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . \Nashenski\Document_Storage::CLEANUP_HOOK ), \Nashenski\Document_Storage::CLEANUP_HOOK ) ),
						esc_html__( 'Yes, delete them', 'nashenski-commerce' )
					),
					'number'
				) )->set_default( '0' ),
			),
			'shop' => array(
				new Fields\Select_Field( array(
					'a' => array(
						'id' => 'a',
						'label' => __( 'Group - A', 'nashenski-commerce' ),
					),
					'b' => array(
						'id' => 'b',
						'label' => __( 'Group - B', 'nashenski-commerce' ),
					),
					'g' => array(
						'id' => 'g',
						'label' => __( 'Group - G', 'nashenski-commerce' ),
					),
				), 'vat_group', __( 'Vat Group', 'nashenski-commerce' ), null, null, __( 'group "A" - for goods and services, the sales of which are exempt from taxation, for goods and services, the sales of which are subject to 0% VAT, as well as for sales, for which VAT is not charged; group "B" - for goods and services, the sales of which are subject to 20% value added tax; group "D" - for goods and services, the sales of which are subject to 9% value added tax; combined - the group is determined for each product in the store separately.', 'nashenski-commerce' ) ),
				new Fields\Select_Field( nashenski_get_return_methods(), 'return_method', __( 'Return method', 'nashenski-commerce' ), __( 'How to return the amount when the client cancel the order.', 'nashenski-commerce' ) ),
			)
		) );

		$fields = $this->add_payment_methods_fields( $fields );
		$fields = $this->add_shipping_methods_fields( $fields );
		$fields['pdf'] = self::pdf_fields();

		$this->set_fields( $fields );
	}

	/**
	 * Documents > PDF appearance.
	 */
	public static function pdf_fields() {
		$defaults = \Nashenski\Invoice\PDF\Style::field_defaults();
		$fields = array(
			new Fields\Image_Field( 'logo', __( 'Logo', 'nashenski-commerce' ), null, __( 'Shown at the top of every document. Without one, the site logo from the theme or the site icon is used.', 'nashenski-commerce' ) ),
			new Fields\Select_Field( array(
				'small' => array( 'id' => 'small', 'label' => __( 'Small', 'nashenski-commerce' ) ),
				'medium' => array( 'id' => 'medium', 'label' => __( 'Medium', 'nashenski-commerce' ) ),
				'large' => array( 'id' => 'large', 'label' => __( 'Large', 'nashenski-commerce' ) ),
			), 'logo_size', __( 'Logo size', 'nashenski-commerce' ) ),
			( new Fields\TrueFalse_Field( 'show_site_details', __( 'Website details', 'nashenski-commerce' ), null, null, __( 'Show the website name, address, email and phone under the logo', 'nashenski-commerce' ) ) )->set_default( 'yes' ),
		);

		if ( \Nashenski\Invoice\PDF\Style::tokenforge_active() ) {
			$fields[] = ( new Fields\TrueFalse_Field( 'follow_tokenforge', __( 'Follow TokenForge colours', 'nashenski-commerce' ), null, null, __( 'Use the TokenForge palette for the colours below, now and after you change it', 'nashenski-commerce' ) ) )->set_default( 'yes' );
		}

		$fields[] = ( new Fields\Color_Field( 'title_color', __( 'Title colour', 'nashenski-commerce' ), null, __( 'Document title and website name.', 'nashenski-commerce' ) ) )->set_default( $defaults['title_color'] );
		$fields[] = ( new Fields\Color_Field( 'accent_color', __( 'Accent colour', 'nashenski-commerce' ), null, __( 'Lines under the header and above the footer.', 'nashenski-commerce' ) ) )->set_default( $defaults['accent_color'] );
		$fields[] = ( new Fields\Color_Field( 'text_color', __( 'Text colour', 'nashenski-commerce' ) ) )->set_default( $defaults['text_color'] );
		$fields[] = ( new Fields\Color_Field( 'table_color', __( 'Table header background', 'nashenski-commerce' ) ) )->set_default( $defaults['table_color'] );
		$fields[] = new Fields\Select_Field( array(
			'dejavu sans' => array( 'id' => 'dejavu sans', 'label' => __( 'Sans serif (DejaVu Sans)', 'nashenski-commerce' ) ),
			'dejavu serif' => array( 'id' => 'dejavu serif', 'label' => __( 'Serif (DejaVu Serif)', 'nashenski-commerce' ) ),
		), 'font', __( 'Font', 'nashenski-commerce' ), null, null, __( 'Both fonts print Cyrillic and Latin.', 'nashenski-commerce' ) );
		$fields[] = ( new Fields\Select_Field( array(
			'small' => array( 'id' => 'small', 'label' => __( 'Small', 'nashenski-commerce' ) ),
			'normal' => array( 'id' => 'normal', 'label' => __( 'Normal', 'nashenski-commerce' ) ),
			'large' => array( 'id' => 'large', 'label' => __( 'Large', 'nashenski-commerce' ) ),
		), 'font_size', __( 'Text size', 'nashenski-commerce' ) ) )->set_default( 'normal' );
		$fields[] = new Fields\Select_Field( array(
			'A4' => array( 'id' => 'A4', 'label' => __( 'A4', 'nashenski-commerce' ) ),
			'letter' => array( 'id' => 'letter', 'label' => __( 'Letter', 'nashenski-commerce' ) ),
		), 'paper', __( 'Paper size', 'nashenski-commerce' ) );
		$fields[] = ( new Fields\Select_Field( array(
			'compact' => array( 'id' => 'compact', 'label' => __( 'Compact', 'nashenski-commerce' ) ),
			'normal' => array( 'id' => 'normal', 'label' => __( 'Normal', 'nashenski-commerce' ) ),
			'spacious' => array( 'id' => 'spacious', 'label' => __( 'Spacious', 'nashenski-commerce' ) ),
		), 'density', __( 'Spacing', 'nashenski-commerce' ), null, null, __( 'Line spacing of the supplier and customer boxes and padding of the tables.', 'nashenski-commerce' ) ) )->set_default( 'compact' );
		$fields[] = ( new Fields\Select_Field( array_map( function ( $example ) {
			return array( 'label' => $example );
		}, \Nashenski\Invoice\PDF\Style::date_formats() ), 'date_format', __( 'Date format', 'nashenski-commerce' ) ) )->set_default( 'd.m.Y' );
		$fields[] = ( new Fields\TrueFalse_Field( 'show_contacts', __( 'Phone and email', 'nashenski-commerce' ), null, null, __( 'Show the phone and email of the supplier and the customer', 'nashenski-commerce' ) ) )->set_default( 'yes' );
		$fields[] = ( new Fields\TrueFalse_Field( 'show_received', __( 'Received by', 'nashenski-commerce' ), null, null, __( 'Show the "Received" box with the customer name', 'nashenski-commerce' ) ) )->set_default( 'yes' );

		return $fields;
	}

	public function gateways_titles() {
		$gateways = WC()->payment_gateways->payment_gateways();
		
		if ( empty( $gateways ) ) {
			return;
		}

		foreach ( $gateways as $slug => $gateway ) {
			if ( wc_string_to_bool( $gateway->enabled ) ) {
				add_filter( 'nashenski/admin/nra/groups_titles', function( $groups_titles ) use ( $gateway, $slug ) {
					$groups_titles[ $slug ] = array(
						/* translators: %s: payment method name */
						'title' => sprintf( __( 'Payment method: %s', 'nashenski-commerce' ), $gateway->title ),
					);

					return $groups_titles;
				} );
			}
		}
	}

	public function get_groups_titles() {
		$this->gateways_titles();

		$titles = apply_filters( 'nashenski/admin/nra/groups_titles', array(
			'nap' => array(
				'title' => __( 'Company details', 'nashenski-commerce' ),
				'desc' => __( 'Printed on invoices and used in the NRA audit file.', 'nashenski-commerce' ),
			),
			'invoice' => array(
				'title' => __( 'Invoices and documents', 'nashenski-commerce' ),
			),
			'shop' => array(
				'title' => __( 'VAT and refunds', 'nashenski-commerce' ),
			),
			'shippings' => array(
				'title' => __( 'Shipping on documents', 'nashenski-commerce' ),
			),
			'pdf' => array(
				'title' => __( 'PDF appearance', 'nashenski-commerce' ),
				'desc' => \Nashenski\Invoice\PDF\Style::tokenforge_active()
					? __( 'TokenForge is active: its colours fill in the fields below, and with "Follow TokenForge colours" on, documents keep matching the website.', 'nashenski-commerce' )
					: __( 'Logo, colours, font and paper of invoices, credit notes and order documents.', 'nashenski-commerce' ),
			),
		) );

		return $titles;
	}

	public function add_shipping_methods_fields( $fields ) {
		$gateways = WC()->payment_gateways->payment_gateways();

		if ( !empty( $gateways ) ) {
			$fields[ 'gateways' ] = array();
			foreach ( $gateways as $slug => $gateway ) {
				if ( wc_string_to_bool( $gateway->enabled ) ) {
					$fields[ $slug ] = array(
						new Fields\Select_Field( nashenski_get_payment_types(), 'payment_type', __( 'Payment Type', 'nashenski-commerce' ), __( 'Manner of payment of the order according to the NRA system.', 'nashenski-commerce' ) ),
						new Fields\Text_Field( 'virtual_pos_number', __( 'Virtual POS №', 'nashenski-commerce' ) ),
						new Fields\Text_Field( 'identifier', __( 'Identifier', 'nashenski-commerce' ) ),
					);
				}
			}
		}

		return $fields;
	}

	public function add_payment_methods_fields( $fields ) {
		$shippings = WC()->shipping->get_shipping_methods();

		foreach ( $shippings as $shipping ) {
			$fields['shippings'][] = new Fields\TrueFalse_Field(
				$shipping->id . '_is_courier', 
				/* translators: %s: shipping method name */
				sprintf( __( 'Is %s courier?', 'nashenski-commerce' ), $shipping->method_title ),
				__( 'If set to yes, all deliveries with PPP will be removed from documents and XML export', 'nashenski-commerce' ) 
			);
		}

		return $fields;
	}
}
