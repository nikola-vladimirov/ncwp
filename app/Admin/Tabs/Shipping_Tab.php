<?php // phpcs:disable WordPress.Files.FileName -- class files are named after the class and autoloaded by namespace.
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;
/**
 * Shipping section of the Nashenski settings.
 */
class Shipping_Tab extends Base_Tab {
	/**
	 * Names the section.
	 */
	public function __construct() {
		$this->set_name( __( 'Shipping', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'shipping' );
	}

	/**
	 * Builds the fields of the section.
	 *
	 * @return void
	 */
	public function load_fields() {
		$fields = apply_filters(
			'nashenski/admin/shipping/fields', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-separated hook names are the plugin's public API.
			array(
				'checkout' => array(
					new Fields\Select_Field(
						array(
							'bg'          => array(
								'id'    => 'bg',
								'label' => __( 'Bulgaria only (country field hidden)', 'nashenski-commerce' ),
							),
							'bg_gr_ro'    => array(
								'id'    => 'bg_gr_ro',
								'label' => __( 'Bulgaria, Greece and Romania', 'nashenski-commerce' ),
							),
							'woocommerce' => array(
								'id'    => 'woocommerce',
								'label' => __( 'As set in WooCommerce > Settings > General', 'nashenski-commerce' ),
							),
						),
						'countries',
						__( 'Customer countries', 'nashenski-commerce' ),
						null,
						null,
						__( 'With Bulgaria only, every order is for Bulgaria and customers do not see the country field.', 'nashenski-commerce' )
					),
					new Fields\Select_Field(
						array(
							'off'      => array(
								'id'    => 'off',
								'label' => __( 'Off', 'nashenski-commerce' ),
							),
							'cyrillic' => array(
								'id'    => 'cyrillic',
								'label' => __( 'Bulgarian addresses in Cyrillic', 'nashenski-commerce' ),
							),
							'auto'     => array(
								'id'    => 'auto',
								'label' => __( 'Cyrillic in Bulgaria, Latin abroad', 'nashenski-commerce' ),
							),
						),
						'transliteration',
						__( 'Transliterate addresses', 'nashenski-commerce' ),
						null,
						null,
						__( 'Converts the address when the order is placed, for example "Ruse, ul. Tsar Osvoboditel" to "Русе, ул. Цар Освободител". Waybills use the converted address.', 'nashenski-commerce' )
					),
					new Fields\TrueFalse_Field( 'transliterate_names', __( 'Transliterate names too', 'nashenski-commerce' ), null, null, __( 'Also convert first and last names.', 'nashenski-commerce' ) ),
					( new Fields\TrueFalse_Field( 'courier_logos', __( 'Courier logos', 'nashenski-commerce' ), null, null, __( 'Show the courier logo next to each shipping option at checkout and in the cart.', 'nashenski-commerce' ) ) )->set_default( 'yes' ),
				),
			)
		);

		$this->set_fields( $fields );
	}

	/**
	 * The titles of the field groups.
	 *
	 * @return array
	 */
	public function get_groups_titles() {
		$titles = apply_filters(
			'nashenski/admin/shipping/groups_titles', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-separated hook names are the plugin's public API.
			array(
				'checkout' => array(
					'title' => __( 'Countries and delivery', 'nashenski-commerce' ),
					'desc'  => __( 'Where you deliver and how addresses reach the courier. Applies to every checkout.', 'nashenski-commerce' ),
				),
			)
		);

		return $titles;
	}
}
