<?php
/**
 * Recommended Settings.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * "Recommended settings" on the Help page: a list of settings that suit a Bulgarian store,
 * each with its current and recommended value. Only the rows the user ticks are applied.
 */
class Recommended_Settings {
	const ACTION = 'nashenski_apply_recommended';
	const NONCE  = 'nashenski_recommended_nonce';

	/**
	 * Construct.
	 */
	public function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * Details.
	 *
	 * @return array[] key => array( option, value, label, why, labels ) where labels maps values to readable text.
	 */
	public static function items() {
		$yes_no = array(
			'yes' => __( 'On', 'nashenski-commerce' ),
			'no'  => __( 'Off', 'nashenski-commerce' ),
		);
		$field  = array(
			'required' => __( 'Required', 'nashenski-commerce' ),
			'optional' => __( 'Optional', 'nashenski-commerce' ),
			'hidden'   => __( 'Hidden', 'nashenski-commerce' ),
		);

		$items = array(
			'countries'       => array(
				'option'  => 'nashenski_settings_checkout_countries',
				'default' => 'bg',
				'value'   => 'bg',
				'label'   => __( 'Customer countries', 'nashenski-commerce' ),
				'why'     => __( 'Every order is for Bulgaria and customers do not see the country field.', 'nashenski-commerce' ),
				'labels'  => array(
					'bg'          => __( 'Bulgaria only', 'nashenski-commerce' ),
					'bg_gr_ro'    => __( 'Bulgaria, Greece and Romania', 'nashenski-commerce' ),
					'woocommerce' => __( 'WooCommerce settings', 'nashenski-commerce' ),
				),
			),
			'logos'           => array(
				'option'  => 'nashenski_settings_checkout_courier_logos',
				'default' => 'yes',
				'value'   => 'yes',
				'label'   => __( 'Courier logos at checkout', 'nashenski-commerce' ),
				'why'     => __( 'Customers recognise the courier at a glance.', 'nashenski-commerce' ),
				'labels'  => $yes_no,
			),
			'transliteration' => array(
				'option'  => 'nashenski_settings_checkout_transliteration',
				'default' => 'off',
				'value'   => 'auto',
				'label'   => __( 'Transliterate addresses', 'nashenski-commerce' ),
				'why'     => __( 'Couriers find Bulgarian addresses typed in Latin letters.', 'nashenski-commerce' ),
				'labels'  => array(
					'off'      => __( 'Off', 'nashenski-commerce' ),
					'cyrillic' => __( 'Bulgarian addresses in Cyrillic', 'nashenski-commerce' ),
					'auto'     => __( 'Cyrillic in Bulgaria, Latin abroad', 'nashenski-commerce' ),
				),
			),
			'phone'           => array(
				'option' => 'woocommerce_checkout_phone_field',
				'value'  => 'required',
				'label'  => __( 'Phone field', 'nashenski-commerce' ),
				'why'    => __( 'Couriers call or text the customer before delivery.', 'nashenski-commerce' ),
				'labels' => $field,
			),
			'postcode'        => array(
				'option'  => 'nashenski_settings_fields_postcode',
				'default' => 'default',
				'value'   => 'optional',
				'label'   => __( 'Postcode field', 'nashenski-commerce' ),
				'why'     => __( 'Bulgarian couriers deliver by city and office, so the postcode should not stop an order.', 'nashenski-commerce' ),
				'labels'  => array_merge( array( 'default' => __( 'Default', 'nashenski-commerce' ) ), $field ),
			),
			'currency'        => array(
				'option' => 'woocommerce_currency',
				'value'  => 'EUR',
				'label'  => __( 'Store currency', 'nashenski-commerce' ),
				'why'    => __( 'Bulgaria uses the euro since 1 January 2026.', 'nashenski-commerce' ),
				'labels' => array(),
			),
			'currency_pos'    => array(
				'option' => 'woocommerce_currency_pos',
				'value'  => 'right_space',
				'label'  => __( 'Currency position', 'nashenski-commerce' ),
				'why'    => __( 'Prices read the Bulgarian way: 12,50 €.', 'nashenski-commerce' ),
				'labels' => array(
					'left'        => __( 'Left', 'nashenski-commerce' ),
					'right'       => __( 'Right', 'nashenski-commerce' ),
					'left_space'  => __( 'Left with space', 'nashenski-commerce' ),
					'right_space' => __( 'Right with space', 'nashenski-commerce' ),
				),
			),
			'decimal'         => array(
				'option' => 'woocommerce_price_decimal_sep',
				'value'  => ',',
				'label'  => __( 'Decimal separator', 'nashenski-commerce' ),
				'why'    => __( 'Bulgarian number format.', 'nashenski-commerce' ),
				'labels' => array(),
			),
			'thousand'        => array(
				'option' => 'woocommerce_price_thousand_sep',
				'value'  => ' ',
				'label'  => __( 'Thousand separator', 'nashenski-commerce' ),
				'why'    => __( 'Bulgarian number format.', 'nashenski-commerce' ),
				'labels' => array(
					' ' => __( 'Space', 'nashenski-commerce' ),
					',' => ',',
					'.' => '.',
					''  => __( 'None', 'nashenski-commerce' ),
				),
			),
		);

		// With taxes on, Bulgarian shops show prices with VAT included.
		if ( 'yes' === get_option( 'woocommerce_calc_taxes' ) ) {
			$items['prices_include_tax'] = array(
				'option' => 'woocommerce_prices_include_tax',
				'value'  => 'yes',
				'label'  => __( 'Prices entered with tax', 'nashenski-commerce' ),
				'why'    => __( 'Prices in Bulgaria are shown and entered with VAT included.', 'nashenski-commerce' ),
				'labels' => array(
					'yes' => __( 'Yes', 'nashenski-commerce' ),
					'no'  => __( 'No', 'nashenski-commerce' ),
				),
			);
		}

		return apply_filters( 'nashenski/recommended_settings', $items );
	}

	/**
	 * Current.
	 *
	 * @param mixed $item Item.
	 */
	public static function current( $item ) {
		$value = get_option( $item['option'], null );

		// Nashenski options that were never saved behave like their default.
		if ( null === $value || false === $value ) {
			return isset( $item['default'] ) ? (string) $item['default'] : '';
		}

		return (string) $value;
	}

	/**
	 * Readable.
	 *
	 * @param mixed $item Item.
	 * @param mixed $value Value.
	 */
	public static function readable( $item, $value ) {
		if ( isset( $item['labels'][ $value ] ) ) {
			return $item['labels'][ $value ];
		}

		return '' === $value ? __( '(not set)', 'nashenski-commerce' ) : $value;
	}

	/**
	 * Handle.
	 */
	public static function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ) );
		}

		check_admin_referer( self::ACTION, self::NONCE );

		$chosen  = isset( $_POST['nashenski_recommended'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['nashenski_recommended'] ) ) : array();
		$applied = 0;

		foreach ( self::items() as $key => $item ) {
			if ( in_array( $key, $chosen, true ) && self::current( $item ) !== (string) $item['value'] ) {
				update_option( $item['option'], $item['value'] );
				++$applied;
			}
		}

		wp_safe_redirect( add_query_arg( 'nashenski_recommended_applied', $applied, Settings::url( 'help' ) ) );
		exit;
	}
}
