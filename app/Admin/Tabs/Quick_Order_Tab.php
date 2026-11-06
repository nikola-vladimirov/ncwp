<?php // phpcs:disable WordPress.Files.FileName -- class files are named after the class and autoloaded by namespace.
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Front_End\Quick_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Quick order section: where the button shows and which extra fields the form asks for.
 */
class Quick_Order_Tab extends Base_Tab {
	/**
	 * Names the section.
	 */
	public function __construct() {
		$this->set_name( __( 'Quick order', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'quick_order' );
	}

	/**
	 * Builds the fields of the section.
	 *
	 * @return void
	 */
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
			new Fields\Select_Field(
				Quick_Order::templates(),
				'template',
				__( 'Form template', 'nashenski-commerce' ),
				null,
				null,
				__( 'A ready-made set of fields. Minimal asks for name, phone and email only. Choose Custom to pick the fields one by one below.', 'nashenski-commerce' )
			),
			new Fields\Select_Field(
				Quick_Order::appearances(),
				'appearance',
				__( 'How the form appears', 'nashenski-commerce' ),
				null,
				null,
				__( 'Where the form opens when the shopper presses Quick order.', 'nashenski-commerce' )
			),
			new Fields\Select_Field(
				self::phone_country_options(),
				'phone_country',
				__( 'Phone country', 'nashenski-commerce' ),
				null,
				null,
				__( 'The country the phone numbers are from. It sets the country code the numbers are checked and saved with.', 'nashenski-commerce' )
			),
			new Fields\Select_Field(
				Quick_Order::phone_formats(),
				'phone_format',
				__( 'Phone format', 'nashenski-commerce' ),
				null,
				null,
				__( 'How the shopper types the number. National keeps the leading zero, international adds the country code. Both are saved the same way.', 'nashenski-commerce' )
			),
			( new Fields\TrueFalse_Field( 'confirm_to_shopper', __( 'Confirmation email', 'nashenski-commerce' ), null, null, __( 'Send the shopper an email that the quick order was received', 'nashenski-commerce' ) ) )->set_default( 'no' ),
		);

		// Name, phone and email are always in the form. These are used with the Custom template.
		foreach ( Quick_Order::optional_fields() as $key => $title ) {
			$rows[] = ( new Fields\TrueFalse_Field( 'field_' . $key, $title, null, null, __( 'Show in the form (Custom template)', 'nashenski-commerce' ) ) )->set_default( 'no' );
		}

		$fields = apply_filters(
			'nashenski/admin/settings/quick_order/fields', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-separated hook names are the plugin's public API.
			array(
				'quick_order' => $rows,
			)
		);

		$this->set_fields( $fields );
	}

	/**
	 * The country choices for the setting: every dial code the form knows, labelled with the country.
	 */
	private function phone_country_options() {
		$codes   = Quick_Order::dial_codes();
		$names   = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_countries() : array();
		$options = array();

		foreach ( $codes as $iso => $dial ) {
			$name            = isset( $names[ $iso ] ) ? $names[ $iso ] : $iso;
			$options[ $iso ] = sprintf( '%s (+%s)', $name, $dial );
		}

		asort( $options );

		return $options;
	}

	/**
	 * The title and description of the section.
	 *
	 * @return array
	 */
	public function get_groups_titles() {
		return array(
			'quick_order' => array(
				'title' => __( 'Quick order', 'nashenski-commerce' ),
				'desc'  => __( 'A short form for shoppers who do not want to check out. The shopper sends name, phone and email, and the order waits for you to confirm it. No stock is reserved until you do. Change the order status to Pending payment to send the payment link.', 'nashenski-commerce' ),
			),
		);
	}
}
