<?php
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Front_End\Checkout\Field_Editor;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout section: company invoice fields and order notes (the field table is Field_Editor::settings(), shown before them).
 */
class Fields_Tab extends Base_Tab {
	public function __construct() {
		$this->set_name( __( 'Checkout fields', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'fields' );
	}

	public function load_fields() {
		$fields = apply_filters( 'nashenski/admin/settings/fields/fields', array(
			\Nashenski\Front_End\Checkout\Company_Fields::GROUP => array(
				( new Fields\TrueFalse_Field( 'fields', __( 'Company invoice fields', 'nashenski-commerce' ), null, null, __( 'Add "I want an invoice for a company" to the checkout. When ticked, the customer fills in company name, EIK, VAT number, MOL, town and address.', 'nashenski-commerce' ) ) )->set_default( \Nashenski\Front_End\Checkout\Company_Fields::enabled() ? 'yes' : 'no' ),
				new Fields\Select_Field(
					array(
						'nra' => array( 'id' => 'nra', 'label' => __( 'Website invoices: in the NRA audit file', 'nashenski-commerce' ) ),
						'own' => array( 'id' => 'own', 'label' => __( 'Own invoicing program: custom number, not in the NRA audit file', 'nashenski-commerce' ) ),
					),
					'invoice_numbering',
					__( 'Invoice numbering', 'nashenski-commerce' ),
					null,
					null,
					__( 'Own invoicing program: no invoice is made automatically. Type the number from your program on the order screen and press "Make invoice". Those orders stay out of the NRA audit file and the monthly audit email.', 'nashenski-commerce' )
				),
			),
			Field_Editor::GROUP_CLASSIC => array(
				new Fields\Select_Field(
					array(
						'default' => array( 'id' => 'default', 'label' => __( 'Default (as WooCommerce shows it)', 'nashenski-commerce' ) ),
						'hidden' => array( 'id' => 'hidden', 'label' => __( 'Hidden', 'nashenski-commerce' ) ),
					),
					'order_comments',
					__( 'Order notes', 'nashenski-commerce' ),
					null,
					null,
					__( 'Classic checkout only. In the block checkout, turn order notes on or off in the Checkout block settings.', 'nashenski-commerce' )
				),
			),
		) );

		$this->set_fields( $fields );
	}

	public function get_groups_titles() {
		return array(
			\Nashenski\Front_End\Checkout\Company_Fields::GROUP => array(
				'title' => __( 'Company invoice', 'nashenski-commerce' ),
				'desc' => __( 'Lets customers ask for an invoice made out to their company. Works in every checkout.', 'nashenski-commerce' ),
			),
			Field_Editor::GROUP_CLASSIC => array(
				'title' => __( 'Order notes', 'nashenski-commerce' ),
				'desc' => __( 'The box where customers can leave a note with the order.', 'nashenski-commerce' ),
			),
		);
	}
}
