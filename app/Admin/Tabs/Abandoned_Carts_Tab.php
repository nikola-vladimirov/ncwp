<?php
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Abandoned carts section: reminder times, the coupon the reminder names, and how long carts are kept.
 */
class Abandoned_Carts_Tab extends Base_Tab {
	public function __construct() {
		$this->set_name( __( 'Abandoned carts', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'abandoned_carts' );
	}

	public function load_fields() {
		$fields = apply_filters( 'nashenski/admin/settings/abandoned_carts/fields', array(
			'abandoned_carts' => array(
				( new Fields\Text_Field(
					'hours',
					__( 'Reminder times', 'nashenski-commerce' ),
					__( 'Hours after the last change to the cart. Up to three, separated by commas.', 'nashenski-commerce' ),
					null,
					__( 'Example: 1, 24, 72. The first reminder goes one hour after the last change, the next a day later, the last three days later.', 'nashenski-commerce' )
				) )->set_default( '1, 24, 72' ),
				new Fields\Text_Field(
					'coupon',
					__( 'Coupon code', 'nashenski-commerce' ),
					__( 'Optional. The reminder names this code. Create the coupon in WooCommerce; the plugin only sends the code.', 'nashenski-commerce' ),
					null,
					''
				),
				( new Fields\Text_Field(
					'retention_days',
					__( 'Keep carts for (days)', 'nashenski-commerce' ),
					__( 'Carts older than this are deleted. Shoppers can ask the shop to delete their cart.', 'nashenski-commerce' ),
					null,
					'',
					'number'
				) )->set_default( '90' ),
			),
		) );

		$this->set_fields( $fields );
	}

	public function get_groups_titles() {
		return array(
			'abandoned_carts' => array(
				'title' => __( 'Abandoned carts', 'nashenski-commerce' ),
				'desc'  => __( 'Keeps the carts that were not ordered and sends reminders to shoppers who typed an email. Reminders go out between 08:00 and 22:00 site time. Nothing is sent outside the site. The list is under WooCommerce > Abandoned carts.', 'nashenski-commerce' ),
			),
		);
	}
}
