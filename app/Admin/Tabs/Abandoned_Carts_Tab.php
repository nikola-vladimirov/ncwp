<?php
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Abandoned_Carts\Tracker;

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
				( new Fields\TrueFalse_Field(
					'auto_coupon',
					__( 'Automatic coupon', 'nashenski-commerce' ),
					null,
					null,
					__( 'Give each cart its own single-use coupon when the first reminder goes out. The reminder names the code and the resume link applies it.', 'nashenski-commerce' )
				) )->set_default( 'no' ),
				new Fields\Select_Field(
					Tracker::coupon_types(),
					'coupon_type',
					__( 'Coupon discount', 'nashenski-commerce' ),
					null,
					null,
					__( 'How the automatic coupon takes money off. Used only with the automatic coupon.', 'nashenski-commerce' )
				),
				( new Fields\Text_Field(
					'coupon_amount',
					__( 'Coupon value', 'nashenski-commerce' ),
					__( 'A number. Percent for a percentage discount, an amount for a fixed discount. The automatic coupon needs a value above zero.', 'nashenski-commerce' ),
					null,
					'',
					'number'
				) )->set_default( '5' ),
				( new Fields\Text_Field(
					'coupon_days',
					__( 'Coupon expires after (days)', 'nashenski-commerce' ),
					__( 'The automatic coupon stops working after this many days. Leave 0 for no expiry.', 'nashenski-commerce' ),
					null,
					'',
					'number'
				) )->set_default( '7' ),
				new Fields\Text_Field(
					'coupon',
					__( 'Manual coupon code', 'nashenski-commerce' ),
					__( 'Optional. Used only when the automatic coupon is off. The reminder names this code; create the coupon in WooCommerce yourself.', 'nashenski-commerce' ),
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
				'desc'  => __( 'Keeps the carts that were not ordered and sends reminders to shoppers who typed an email, even a logged-out shopper at checkout. Reminders go out between 08:00 and 22:00 site time. The subject and the body are edited under WooCommerce > Settings > Emails > Abandoned cart reminder. The list is under WooCommerce > Abandoned carts.', 'nashenski-commerce' ),
			),
		);
	}
}
