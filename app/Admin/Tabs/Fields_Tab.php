<?php
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Front_End\Checkout\Field_Editor;

defined( 'ABSPATH' ) || exit;

/**
 * "Checkout fields" section: company invoice fields and order notes (the field table is Field_Editor::settings()).
 */
class Fields_Tab extends Base_Tab {
	public function __construct() {
		$this->set_name( __( 'Checkout fields', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'fields' );
	}

	public function load_fields() {
		$fields = apply_filters( 'nashenski/admin/settings/fields/fields', array(
			\Nashenski\Front_End\Checkout\Company_Fields::GROUP => array(
				( new Fields\TrueFalse_Field( 'fields', __( 'Company invoice fields', 'nashenski-commerce' ), null, null, __( 'Add "I want an invoice for a company" to the checkout. When ticked, the customer fills in company name, EIK, VAT number, MOL, town and address, and the invoice is made out to the company.', 'nashenski-commerce' ) ) )->set_default( \Nashenski\Front_End\Checkout\Company_Fields::enabled() ? 'yes' : 'no' ),
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
				'desc' => __( 'Works in the classic checkout, the block checkout and page builder checkouts. In a builder checkout made of single field elements, the fields appear after the billing fields unless you placed them yourself.', 'nashenski-commerce' ),
			),
			Field_Editor::GROUP_CLASSIC => array(
				'title' => __( 'Order notes', 'nashenski-commerce' ),
			),
		);
	}
}
