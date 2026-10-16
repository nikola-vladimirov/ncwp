<?php
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Front_End\Quick_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Quick order section: where the button shows and which extra fields the form asks for.
 */
class Quick_Order_Tab extends Base_Tab {
	public function __construct() {
		$this->set_name( __( 'Quick order', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'quick_order' );
	}

	public function load_fields() {
		$rows = array(
			new Fields\Select_Field(
				Quick_Order::placements(),
				'placement',
				__( 'Where the button shows', 'nashenski-commerce' ),
				null,
				null,
				__( 'The button opens the form next to the add-to-cart button or the cart totals.', 'nashenski-commerce' )
			),
			( new Fields\TrueFalse_Field( 'confirm_to_shopper', __( 'Confirmation email', 'nashenski-commerce' ), null, null, __( 'Send the shopper an email that the quick order was received', 'nashenski-commerce' ) ) )->set_default( 'no' ),
		);

		// Name, phone and email are always in the form. The rest is the owner's choice.
		foreach ( Quick_Order::optional_fields() as $key => $title ) {
			$rows[] = ( new Fields\TrueFalse_Field( 'field_' . $key, $title, null, null, __( 'Show in the form', 'nashenski-commerce' ) ) )->set_default( 'no' );
		}

		$fields = apply_filters( 'nashenski/admin/settings/quick_order/fields', array(
			'quick_order' => $rows,
		) );

		$this->set_fields( $fields );
	}

	public function get_groups_titles() {
		return array(
			'quick_order' => array(
				'title' => __( 'Quick order', 'nashenski-commerce' ),
				'desc'  => __( 'A short form for shoppers who do not want to check out. The shopper sends name, phone and email, and the order waits for you to confirm it. No stock is reserved until you do. Change the order status to Pending payment to send the payment link.', 'nashenski-commerce' ),
			),
		);
	}
}
