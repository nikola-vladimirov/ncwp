<?php
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

class CVC_Tab extends Base_Tab {
	protected $container, $tab_name;
	
	public function __construct() {
		$this->container = nashenski()->container();
		$this->tab_name = get_called_class();
		$this->set_name( __( 'CVC', 'nashenski-commerce' ) );
		$this->set_tab_slug( "cvc" );

		add_action( 'nashenski/admin/settings/afte_save_fields/'. $this->tab_name, array( $this, 'after_save_fields' ) );
		add_filter( 'nashenski/admin/settings/cvc/fields', array( $this, 'add_send_from_fields' ), 20 );
		add_filter( 'nashenski/admin/settings/cvc/groups_titles', array( $this, 'add_send_from_group_title' ) );
		
	}

	public function load_fields() {
		$fields = array(
			'cvc' => array(
				new Fields\Text_Field( 'token', __( 'API Token', 'nashenski-commerce' ), __( 'Enter your token.', 'nashenski-commerce' ), 'required' ),
			),
		);

		$fields = apply_filters( 'nashenski/admin/settings/cvc/fields', $fields );

		$this->set_fields( $fields );
	}

	public function add_send_from_fields( $fields ) {
		if ( $this->container[ Client::CVC_PROFILE ]->is_valid_profile( true ) ) {
			$send_from = ( nashenski_get_option( 'cvc_sender', 'send_from' ) ) ? nashenski_get_option( 'cvc_sender', 'send_from' ) : 'office';
			
			$addresses = $this->container[ Client::CVC_PROFILE ]->get_formatted_addresses();
			$cities = $this->container[ Client::CVC_CITIES ]->get_formatted_cities();

			$fields[ 'cvc_sender' ] = [
				new Fields\Text_Field( 'name', __( 'Name', 'nashenski-commerce' ), __( 'Enter sender name.', 'nashenski-commerce' ), 'required' ),
				new Fields\Text_Field( 'phone', __( 'Phone', 'nashenski-commerce' ), __( 'Enter sender phone.', 'nashenski-commerce' ), 'required' ),
				new Fields\Text_Field( 'email', __( 'E-mail', 'nashenski-commerce' ), __( 'Enter sender email.', 'nashenski-commerce' ), 'required|email' ),
				new Fields\TrueFalse_Field( 'ppp', __( 'Cash on delivery as PPP.', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'contract_pay', __( 'Pay by contract', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'force_variations_in_desc', __( 'Force variations in label', 'nashenski-commerce' ), null, null, __( 'Add additional variations information. Please use this option only if you want the variation data to be available in the label print and it\'s missing.', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'label_after_checkout', __( 'Generate label after checkout', 'nashenski-commerce' ), null, null, __( 'This option will try to generate your label immediately after user checkout. Also, will add the tracking number in the order email.', 'nashenski-commerce' ) ),
				new Fields\Select_Field( $cities, 'city', __( 'City', 'nashenski-commerce' ) ),
				new Fields\Select_Field( 
					array(
						'office' => array(
							'id' => 'office',
							'label' => __( 'Office', 'nashenski-commerce' ),
						),
						'address' => array(
							'id' => 'address',
							'label' => __( 'Address', 'nashenski-commerce' ),
						),
					), 'send_from', __( 'Send From', 'nashenski-commerce' ), null, null, __( 'Select from where you will send the packages and save to show more options.', 'nashenski-commerce' ) 
				),
			];

			switch ( $send_from ) {
				case 'address':
					if ( !empty( $addresses ) ) {
						$fields[ 'cvc_sender' ][] = new Fields\Select_Field( $addresses, 'address', __( 'Select Address', 'nashenski-commerce' ) );
					}

					break;
				case 'office':
					if ( $city = nashenski_get_option( 'cvc_sender', 'city' ) ) {
						$offices = $this->container[ Client::CVC_HUBS ]->get_formatted_hubs( $city );

						if ( !empty( $offices ) ) {
							$fields[ 'cvc_sender' ][] = new Fields\Select_Field( $offices, 'office', __( 'Office', 'nashenski-commerce' ) );
						}
					}
					
					break;
			}
		}

		return $fields;
	}

	public function get_groups_titles() {
		$titles = apply_filters( 'nashenski/admin/settings/cvc/groups_titles', array(
			'cvc' => array(
				'title' => __( 'CVC account', 'nashenski-commerce' ),
				'desc' => __( 'The API token CVC gave you. After saving, your sender options appear.', 'nashenski-commerce' ),
			),
		) );

		return $titles;
	}

	public function add_send_from_group_title( $titles ) {
		if ( $this->container[ Client::CVC_PROFILE ]->is_valid_profile( true ) ) {
			$titles['cvc_sender'] = array(
				'title' => __( 'Where you send from', 'nashenski-commerce' ),
			);
		}

		return $titles;
	}

	public function after_save_fields() {
		$is_valid_profile = $this->container[ Client::CVC_PROFILE ]->check_credentials( true );

		nashenski_set_option( 'cvc', 'is_valid_profile', $is_valid_profile );

		if ( $is_valid_profile ) {
			$this->container[ Client::CVC_PROFILE ]->get_profile_data();
		}
	}

	public function auth_test() {
		$error = '';

		if ( !$this->container[ Client::CVC_PROFILE ]->is_valid_profile( true ) ) {
			$error = __( 'API Token is incorrect.', 'nashenski-commerce' );
		}

		return wpautop( $error );
	}
}
