<?php
/**
 * Fields tab.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Front_End\Checkout\Field_Editor;

defined( 'ABSPATH' ) || exit;

/**
 * Order notes (Checkout; the field table is Field_Editor::settings(), shown before them).
 */
class Fields_Tab extends Base_Tab {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_name( __( 'Checkout fields', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'fields' );
	}

	/**
	 * Load fields.
	 */
	public function load_fields() {
		$fields = \Nashenski\Hooks::filter(
			'nashenski_admin_settings_fields_fields',
			'nashenski/admin/settings/fields/fields',
			array(
				Field_Editor::GROUP_CLASSIC => array(
					new Fields\Select_Field(
						array(
							'default' => array(
								'id'    => 'default',
								'label' => __( 'Default (as WooCommerce shows it)', 'nashenski-commerce' ),
							),
							'hidden'  => array(
								'id'    => 'hidden',
								'label' => __( 'Hidden', 'nashenski-commerce' ),
							),
						),
						'order_comments',
						__( 'Order notes', 'nashenski-commerce' ),
						null,
						null,
						__( 'Classic checkout only. In the block checkout, turn order notes on or off in the Checkout block settings.', 'nashenski-commerce' )
					),
				),
			)
		);

		$this->set_fields( $fields );
	}

	/**
	 * Get groups titles.
	 *
	 * @return array Result.
	 */
	public function get_groups_titles() {
		return array(
			Field_Editor::GROUP_CLASSIC => array(
				'title' => __( 'Order notes', 'nashenski-commerce' ),
				'desc'  => __( 'The box where customers can leave a note with the order.', 'nashenski-commerce' ),
			),
		);
	}
}
