<?php
/**
 * Boxnow tab.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Boxnow tab.
 */
class BoxNow_Tab extends Base_Tab {
	/**
	 * Container.
	 *
	 * @var mixed
	 */
	protected $container;

	/**
	 * Tab name.
	 *
	 * @var mixed
	 */
	protected $tab_name;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->container = nashenski()->container();
		$this->tab_name  = get_called_class();
		$this->set_name( __( 'BOX NOW', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'boxnow' );

		add_filter( 'nashenski/admin/settings/boxnow/fields', array( $this, 'add_send_from_fields' ), 20 );
	}

	/**
	 * Load fields.
	 */
	public function load_fields() {
		$fields = array(
			'boxnow' => array(
				new Fields\Select_Field(
					array(
						'demo' => array(
							'id'    => 'demo',
							'label' => __( 'Demo', 'nashenski-commerce' ),
						),
						'live' => array(
							'id'    => 'live',
							'label' => __( 'Live', 'nashenski-commerce' ),
						),
					),
					'env',
					__( 'Environment', 'nashenski-commerce' ),
					null,
					null,
					__( 'Select used environment.', 'nashenski-commerce' )
				),
				new Fields\Text_Field( 'client_id', __( 'Client ID', 'nashenski-commerce' ), __( 'Enter your Client ID.', 'nashenski-commerce' ), 'required' ),
				new Fields\Text_Field( 'client_secret', __( 'Client Secret', 'nashenski-commerce' ), __( 'Enter your Client Secret.', 'nashenski-commerce' ), 'required', null, 'password' ),
			),
		);

		$fields = apply_filters( 'nashenski/admin/settings/boxnow/fields', $fields );

		$this->set_fields( $fields );
	}

	/**
	 * BOX NOW accepted the client ID and secret. Without it the other options show greyed out (Settings_Page).
	 */
	public function connected() {
		return (bool) $this->container[ Client::BOXNOW ]->get_access_token();
	}

	/**
	 * Add send from fields.
	 *
	 * @param mixed $fields Fields.
	 * @return mixed Result.
	 */
	public function add_send_from_fields( $fields ) {
		$fields['boxnow_send_from'][] = new Fields\Text_Field( 'name', __( 'Name', 'nashenski-commerce' ) );
		$fields['boxnow_send_from'][] = new Fields\Text_Field( 'phone', __( 'Phone', 'nashenski-commerce' ) );
		$fields['boxnow_send_from'][] = new Fields\Text_Field( 'email', __( 'E-mail', 'nashenski-commerce' ) );
		$fields['boxnow_send_from'][] = new Fields\Select_Field(
			$this->connected() ? self::get_from_locations() : array(),
			'location',
			__( 'Location', 'nashenski-commerce' ),
		);
		$fields['boxnow_send_from'][] = new Fields\TrueFalse_Field( 'allow_return', __( 'Allow returns', 'nashenski-commerce' ) );
		$fields['boxnow_send_from'][] = new Fields\TrueFalse_Field( 'label_after_checkout', __( 'Generate label after checkout', 'nashenski-commerce' ), null, null, __( 'This option will try to generate your label immediately after user checkout. Also, will add the tracking number in the order email.', 'nashenski-commerce' ) );

		$fields['boxnow_price'][] = new Fields\Select_Field(
			array(
				'from_to_kg'                 => array(
					'id'    => 'from_to_kg',
					'label' => __( 'From/To in kg', 'nashenski-commerce' ),
				),
				'from_to_order_total'        => array(
					'id'    => 'from_to_order_total',
					'label' => __( 'From/To Order Total', 'nashenski-commerce' ),
				),
				'from_to_kg_and_order_total' => array(
					'id'    => 'from_to_kg_and_order_total',
					'label' => __( 'From/To kg and Order Total', 'nashenski-commerce' ),
				),
			),
			'price_type',
			__( 'Price type', 'nashenski-commerce' ),
			null,
			null,
			__( 'After you select the option, you must save the settings in order the correct price settings to be shown.', 'nashenski-commerce' )
		);

		$price_type = nashenski_get_option( 'boxnow_price', 'price_type' );
		if ( $price_type ) {
			switch ( $price_type ) {
				case 'from_to_kg':
					$fields['boxnow_price'][] = new Fields\Multi_Field(
						array(
							'type'           => __( 'kg', 'nashenski-commerce' ),
							'separator_text' => __( ' to ', 'nashenski-commerce' ),
							'price_text'     => __( 'Price', 'nashenski-commerce' ),
							'add_row_text'   => __( '+ Add new row', 'nashenski-commerce' ),
							'currency'       => get_woocommerce_currency_symbol(),
							'max'            => 20,
							'step'           => 0.001,
						),
						'from_to_kg',
						__( 'From/To in kg', 'nashenski-commerce' ),
						null,
						'required'
					);
					break;
				case 'from_to_order_total':
					$fields['boxnow_price'][] = new Fields\Multi_Field(
						array(
							'type'           => get_woocommerce_currency_symbol(),
							'separator_text' => __( ' to ', 'nashenski-commerce' ),
							'add_row_text'   => __( '+ Add new row', 'nashenski-commerce' ),
							'price_text'     => __( 'Price', 'nashenski-commerce' ),
							'currency'       => get_woocommerce_currency_symbol(),
						),
						'from_to_order_total',
						__( 'From/To Order Total', 'nashenski-commerce' ),
						null,
						'required'
					);
					break;
				case 'from_to_kg_and_order_total':
					$fields['boxnow_price'][] = new Fields\Multi_Two_Field(
						array(
							'type'           => __( 'kg', 'nashenski-commerce' ),
							'type2'          => get_woocommerce_currency_symbol(),
							'and_text'       => __( 'and', 'nashenski-commerce' ),
							'separator_text' => __( ' to ', 'nashenski-commerce' ),
							'price_text'     => __( 'Price', 'nashenski-commerce' ),
							'add_row_text'   => __( '+ Add new row', 'nashenski-commerce' ),
							'currency'       => get_woocommerce_currency_symbol(),
							'max_type1'      => 20,
							'step_type1'     => 0.001,
							'step_type2'     => 0.001,
						),
						'from_to_kg_and_order_total',
						__( 'From/To kg and Order Total', 'nashenski-commerce' ),
						null,
						'required'
					);
					break;
			}
		}

		$fields['boxnow_price'][] = new Fields\Text_Field( 'hide_over', __( 'Hide shipping over cart total price', 'nashenski-commerce' ), null, null, __( 'If filled will hide the shipping method if the cart total price is over the sum.', 'nashenski-commerce' ), 'number' );
		$fields['boxnow_price'][] = new Fields\Text_Field( 'free_shipping_over', __( 'Free shipping over', 'nashenski-commerce' ), __( 'Free shipping at or above total cart price.', 'nashenski-commerce' ), null, null, 'number' );
		$fields['boxnow_price'][] = new Fields\Select_Field(
			array(
				'auto'   => array(
					'id'    => 'auto',
					'label' => __( 'Automatically pack products to boxes', 'nashenski-commerce' ),
				),
				'small'  => array(
					'id'    => 'small',
					'label' => __( 'Small Box', 'nashenski-commerce' ),
				),
				'medium' => array(
					'id'    => 'medium',
					'label' => __( 'Medium Box', 'nashenski-commerce' ),
				),
				'large'  => array(
					'id'    => 'large',
					'label' => __( 'Large Box', 'nashenski-commerce' ),
				),
			),
			'box_size',
			__( 'Box size', 'nashenski-commerce' ),
			null,
			null,
			__( 'Default box size', 'nashenski-commerce' )
		);

		return $fields;
	}

	/**
	 * Get groups titles.
	 *
	 * @return mixed Result.
	 */
	public function get_groups_titles() {
		$titles = apply_filters(
			'nashenski/admin/settings/boxnow/groups_titles',
			array(
				'boxnow'           => array(
					'title' => __( 'BOX NOW account', 'nashenski-commerce' ),
					'desc'  => __( 'Client ID and secret from your BOX NOW e-shop account. The other options unlock once BOX NOW accepts them.', 'nashenski-commerce' ),
				),
				'boxnow_send_from' => array(
					'title' => __( 'Where you hand over parcels', 'nashenski-commerce' ),
					'desc'  => __( 'The sender details on your labels and the place you send parcels from.', 'nashenski-commerce' ),
				),
				'boxnow_price'     => array(
					'title' => __( 'What customers pay', 'nashenski-commerce' ),
					'desc'  => __( 'How Nashenski Commerce calculates the BOX NOW price at checkout.', 'nashenski-commerce' ),
				),
			)
		);

		return $titles;
	}

	/**
	 * Auth test.
	 *
	 * @return mixed Result.
	 */
	public function auth_test() {
		$error = '';

		$this->container[ Client::BOXNOW ]->load_access_token( true );

		if ( ! $this->container[ Client::BOXNOW ]->get_access_token() ) {
			$error = __( 'Incorrect Client ID or Client Secret.', 'nashenski-commerce' );
		}

		$this->container[ Client::BOXNOW ]::clear_cache_folder();

		return wpautop( $error );
	}

	/**
	 * Get from locations.
	 *
	 * @return mixed Result.
	 */
	public static function get_from_locations() {
		$container = nashenski()->container();

		return $container[ Client::BOXNOW_ORIGINS ]->get_formatted_origins();
	}
}
