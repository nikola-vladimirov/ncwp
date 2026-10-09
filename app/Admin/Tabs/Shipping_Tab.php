<?php
namespace Nashenski\Admin\Tabs;
use Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;
class Shipping_Tab extends Base_Tab {
	public function __construct() {
		$this->set_name( __( 'Shipping', 'nashenski-commerce' ) );
		$this->set_tab_slug( "shipping" );
	}

	public function load_fields() {
		$fields = apply_filters( 'nashenski/admin/shipping/fields', array(
			'checkout' => array(
				new Fields\Select_Field( array(
					'bg' => array( 'id' => 'bg', 'label' => __( 'Bulgaria only (country field hidden)', 'nashenski-commerce' ) ),
					'bg_gr_ro' => array( 'id' => 'bg_gr_ro', 'label' => __( 'Bulgaria, Greece and Romania', 'nashenski-commerce' ) ),
					'woocommerce' => array( 'id' => 'woocommerce', 'label' => __( 'As set in WooCommerce > Settings > General', 'nashenski-commerce' ) ),
				), 'countries', __( 'Customer countries', 'nashenski-commerce' ), null, null, __( 'With Bulgaria only, every order is for Bulgaria and customers do not see the country field.', 'nashenski-commerce' ) ),
				( new Fields\TrueFalse_Field( 'courier_logos', __( 'Courier logos', 'nashenski-commerce' ), null, null, __( 'Show the courier logo next to each shipping option at checkout and in the cart.', 'nashenski-commerce' ) ) )->set_default( 'yes' ),
				new Fields\Select_Field( array(
					'off' => array( 'id' => 'off', 'label' => __( 'Off', 'nashenski-commerce' ) ),
					'cyrillic' => array( 'id' => 'cyrillic', 'label' => __( 'Bulgarian addresses in Cyrillic', 'nashenski-commerce' ) ),
					'auto' => array( 'id' => 'auto', 'label' => __( 'Cyrillic in Bulgaria, Latin abroad', 'nashenski-commerce' ) ),
				), 'transliteration', __( 'Transliterate addresses', 'nashenski-commerce' ), null, null, __( 'Converts the address the customer typed when the order is placed, for example "Ruse, ul. Tsar Osvoboditel" to "Русе, ул. Цар Освободител". Labels use the converted address. Orders also get a "Transliterate addresses" action.', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'transliterate_names', __( 'Transliterate names too', 'nashenski-commerce' ), null, null, __( 'Also convert first and last names.', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'alternative_shipping_table', __( 'Alternative shipping options layout ( checkout )', 'nashenski-commerce' ), null, null, __( 'Make shipping options on 2 rows and full width in the checkout table.', 'nashenski-commerce' ) ),
			)
		) );

		$this->set_fields( $fields );
	}

	public function get_groups_titles() {
		$titles = apply_filters( 'nashenski/admin/shipping/groups_titles', array(
			'checkout' => array(
				'title' => __( 'Countries and delivery options', 'nashenski-commerce' ),
				'desc' => __( 'Applies to every checkout: classic, block and page builder checkouts.', 'nashenski-commerce' ),
			),
		) );

		return $titles;
	}
}
