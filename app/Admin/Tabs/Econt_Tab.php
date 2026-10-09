<?php
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

class Econt_Tab extends Base_Tab {
	protected $container, $tab_name;
	
	public function __construct() {
		$this->container = nashenski()->container();
		$this->tab_name = get_called_class();
		$this->set_name( __( 'Econt', 'nashenski-commerce' ) );
		$this->set_tab_slug( "econt" );

		add_action( 'nashenski/admin/settings/afte_save_fields/'. $this->tab_name, array( $this, 'after_save_fields' ) );
		add_filter( 'nashenski/admin/settings/econt/fields', array( $this, 'add_profile_data_fields' ), 10 );
		add_filter( 'nashenski/admin/settings/econt/fields', array( $this, 'add_send_from_fields' ), 20 );
		add_filter( 'nashenski/admin/settings/econt/groups_titles', array( $this, 'add_send_from_group_title' ) );
	}

	public function load_fields() {
		$fields = array(
			'econt' => array(
				new Fields\Select_Field( array(
						'demo' => array(
							'id' => 'demo',
							'label' => __( 'Demo', 'nashenski-commerce' ),
						),
						'live' => array(
							'id' => 'live',
							'label' => __( 'Live', 'nashenski-commerce' ),
						),
					), 'env', __( 'Environment', 'nashenski-commerce' ), null, null, __( 'Select used environment. For demo you can use Username: "iasp-dev" and Password: "1Asp-dev". For live environment use your credentials or create a registration at <a target="_blank" href="https://login.econt.com/register/">Econt Delivery</a>', 'nashenski-commerce' ) ),
				new Fields\Text_Field( 'user', __( 'Username', 'nashenski-commerce' ), __( 'Enter your username.', 'nashenski-commerce' ), 'required' ),
				new Fields\Text_Field( 'password', __( 'Password', 'nashenski-commerce' ), __( 'Enter your password.', 'nashenski-commerce' ), 'required', null, 'password' ),
			)
		);

		$fields = apply_filters( 'nashenski/admin/settings/econt/fields', $fields );

		$this->set_fields( $fields );
	}

	public function add_profile_data_fields( $fields ) {
		if ( $this->container[ Client::ECONT_PROFILE ]->is_valid_profile( true ) ) {
			$all_profiles = $this->container[ Client::ECONT_PROFILE ]->get_profiles_for_settings();

			if ( !empty( $all_profiles ) ) {
				$cd_pay_option = nashenski_get_option( 'econt', 'pay_options' );

				$fields[ 'econt' ][] = new Fields\Select_Field( $all_profiles, 'profile_key', __( 'Select profile', 'nashenski-commerce' ), null, null, __( 'Select the profile you want to use and save to show or update the other options.', 'nashenski-commerce' )
				);
				$fields[ 'econt' ][] = new Fields\Text_Field( 'name', __( 'Name', 'nashenski-commerce' ) );
				$fields[ 'econt' ][] = new Fields\Text_Field( 'phone', __( 'Phone', 'nashenski-commerce' ) );
				$fields[ 'econt' ][] = new Fields\TrueFalse_Field( 'force_variations_in_desc', __( 'Force variations in label', 'nashenski-commerce' ), null, null, __( 'Add additional variations information. Please use this option only if you want the variation data to be available in the label print and it\'s missing.', 'nashenski-commerce' ) );
				$fields[ 'econt' ][] = new Fields\TrueFalse_Field( 'label_after_checkout', __( 'Generate label after checkout', 'nashenski-commerce' ), null, null, __( 'This option will try to generate your label immediately after user checkout. Also, will add the tracking number in the order email.', 'nashenski-commerce' ) );
				$fields[ 'econt' ][] = new Fields\TrueFalse_Field( 'declared_value', __( 'Declared value', 'nashenski-commerce' ), null, null, __( 'Adds declared value to the label if payment is COD.', 'nashenski-commerce' ) );
				$fields[ 'econt' ][] = new Fields\TrueFalse_Field( 'auto_size', __( 'Automatically calculate box size', 'nashenski-commerce' ), null, null, __( 'Automatically calculate LxWxH of the box for the parcel.', 'nashenski-commerce' ) );
				$fields[ 'econt' ][] = new Fields\Select_Field( $this->generate_pay_options(), 'pay_options', __( 'Cash on delivery agreement', 'nashenski-commerce' ), null, null, __( 'Choose cash on delivery agreement', 'nashenski-commerce' ) );

				if ( $cd_pay_option && $cd_pay_option !== 'no' ) {
					$fields[ 'econt' ][] = new Fields\Select_Field( 
						array(
							'' => array(
								'id' => 'credit',
								'label' => __( 'Credit', 'nashenski-commerce' ),
							),
							'cash' => array(
								'id' => 'cash',
								'label' => __( 'Cash', 'nashenski-commerce' ),
							),
						), 'sender_payment_type', __( 'Sender payment type', 'nashenski-commerce' ), null, null, __( 'Select how you will pay for the shipments.', 'nashenski-commerce' ) 
					);

					$fields[ 'econt' ][] = new Fields\Select_Field( 
						array(
							'invoice' => array(
								'id' => 'invoice',
								'label' => __( 'Invoice number', 'nashenski-commerce' ),
							),
							'packing_list' => array(
								'id' => 'packing_list',
								'label' => __( 'Packing list', 'nashenski-commerce' ),
							),
						), 'invoice_or_packing_list', __( 'Packing list or invoice number', 'nashenski-commerce' ), null, null, __( 'Select if you want to have packing list or invoice number in the label.', 'nashenski-commerce' ) 
					);
				}

				$fields[ 'econt' ][] = new Fields\Select_Field( 
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
				);
			}
		}

		return $fields;
	}

	public function add_send_from_fields( $fields ) {
		if ( $this->container[ Client::ECONT_PROFILE ]->is_valid_profile( true ) ) {
			$all_profiles = $this->container[ Client::ECONT_PROFILE ]->get_profiles_for_settings();

			if ( !empty( $all_profiles ) ) {
				$send_from = ( nashenski_get_option( 'econt', 'send_from' ) ) ? nashenski_get_option( 'econt', 'send_from' ) : 'office';
				$addresses = $this->container[ Client::ECONT_PROFILE ]->get_formatted_addresses();
				$cities = $this->container[ Client::ECONT_CITIES ]->get_formatted_cities();
				$offices = $this->container[ Client::ECONT_OFFICES ]->get_formatted_offices( nashenski_get_option( 'econt_send_from', 'office_city' ) );
				$fields[ 'econt_send_from' ] = [];

				$offices = array_merge( $offices['shops'], $offices['aps'] );

				if ( !empty( $addresses ) ) {
					$fields[ 'econt_send_from' ][] = new Fields\Select_Field( $addresses, 'address', __( 'Select Address', 'nashenski-commerce' ) );
				}

				if ( !empty( $cities ) ) {
					$fields[ 'econt_send_from' ][] = new Fields\Select_Field( $cities, 'office_city', __( 'City', 'nashenski-commerce' ) );
				}

				if ( !empty( $offices ) ) {
					$fields[ 'econt_send_from' ][] = new Fields\Select_Field( 
						$offices, 
						'office', 
						__( 'Office', 'nashenski-commerce' ), 
						null, 
						null, 
						__('Choose a city and save in order to show offices.', 'nashenski-commerce' ) 
					);
				}
			}
		}

		return $fields;
	}

	public function generate_pay_options() {
		$pay_options = [
			'no' => array(
				'id' => 'no',
				'label' => __( 'No agreement', 'nashenski-commerce' )
			),
		];

		if ( !empty( $this->container[ Client::ECONT_PROFILE ]->get_profile_data()['cdPayOptions'] ) ) {
			foreach ( $this->container[ Client::ECONT_PROFILE ]->get_profile_data()['cdPayOptions'] as $option ) {
				$pay_options[ $option[ 'num' ] ] = array(
					'id' => $option[ 'num' ],
					'label' => $option[ 'num' ],
				);
			}
		}
		
		return $pay_options;
	}

	public function get_groups_titles() {
		$titles = apply_filters( 'nashenski/admin/settings/econt/groups_titles', array(
			'econt' => array(
				'title' => __( 'Econt account', 'nashenski-commerce' ),
				'desc' => __( 'Use the user and password of an Econt e-shop integration. After saving, your profiles and label options appear.', 'nashenski-commerce' ),
			),
		) );

		return $titles;
	}

	public function add_send_from_group_title( $titles ) {
		if ( $this->container[ Client::ECONT_PROFILE ]->is_valid_profile( true ) ) {
			$titles['econt_send_from'] = array(
				'title' => __( 'Where you send from', 'nashenski-commerce' ),
			);
		}
		return $titles;
	}

	public function after_save_fields() {
		$is_valid_profile = $this->container[ Client::ECONT_PROFILE ]->check_credentials( true );

		nashenski_set_option( 'econt', 'is_valid_profile', $is_valid_profile );

		if ( $is_valid_profile ) {
			$this->update_profile_fields();
		}
	}

	public function update_profile_fields() {
		$profile_data = $this->container[ Client::ECONT_PROFILE ]->get_profile_data( 1 );

		$name = nashenski_get_option( 'econt', 'name' );
		$phone = nashenski_get_option( 'econt', 'phone' );

		if ( !$name ) {
			nashenski_set_option( 'econt', 'name', $profile_data['client']['molName'] );
		}
		
		if ( !$phone ) {
			nashenski_set_option( 'econt', 'phone', $profile_data['client']['phones'][0] );
		}
	}

	public function auth_test() {
		$error = '';

		if ( !$this->container[ Client::ECONT_PROFILE ]->is_valid_profile( true ) ) {
			$error = __( 'Username and password are incorrect. Create API keys in your Econt account under "Integration for online shops".', 'nashenski-commerce' ); 
		} else {
			$api_response = $this->container[ Client::ECONT_PROFILE ]->fetch_profile_data();
			$all_profiles = $this->container[ Client::ECONT_PROFILE ]->get_profiles_for_settings();

			if ( isset( $api_response['message'] ) && ! is_array( $api_response['message'] ) ) {
				$error = "API: " . $api_response['message'];
			} elseif ( empty( $all_profiles ) ) {
				$error = __( 'No profiles was found. Please contact with Econt.', 'nashenski-commerce' );
			}
		}

		return wpautop( $error );
	}
}
