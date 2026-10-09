<?php
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;
class Connectix_Tab extends Base_Tab {
	protected $tab_name, $container;
	
	public function __construct() {
        $this->container = nashenski()->container();
        $this->tab_name = get_called_class();
		$this->set_name( __( 'Connectix', 'nashenski-commerce' ) );
		$this->set_tab_slug( "connectix" );
	}

	public function load_fields() {
		$fields = apply_filters( 'nashenski/admin/settings/connectix/fields', array(
			'connectix' => array(
                new Fields\Select_Field( array(
                    'live' => array(
                        'id' => 'live',
                        'label' => __( 'Live', 'nashenski-commerce' ),
                    ),
                    'sandbox' => array(
                        'id' => 'sandbox',
                        'label' => __( 'Sandbox', 'nashenski-commerce' ),
                    ),
                ), 'env', __( 'Environment', 'nashenski-commerce' ), null, null, __( 'Select used environment.', 'nashenski-commerce' ) ),
				new Fields\Text_Field( 'token', __( 'Token', 'nashenski-commerce' ), null, 'required', __( 'Enter the provided token from connectix.bg.', 'nashenski-commerce' ) ),
                new Fields\Select_Field( array(
                    'low' => array(
                        'id' => 'low',
                        'label' => __( 'Low', 'nashenski-commerce' ),
                    ),
                    'medium' => array(
                        'id' => 'medium',
                        'label' => __( 'Medium', 'nashenski-commerce' ),
                    ),
                    'high' => array(
                        'id' => 'high',
                        'label' => __( 'High', 'nashenski-commerce' ),
                    ),
                    'ultra_high' => array(
                        'id' => 'ultra_high',
                        'label' => __( 'Ultra high', 'nashenski-commerce' ),
                    ),
                ), 'level_of_warning', __( 'Level of Warning', 'nashenski-commerce' ), null, null, __( 'Choose level of warning that will trigger notification or disable COD.', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'enable_tracking', __( 'Send tracking information', 'nashenski-commerce' ), null, null, __('By enabling this option, the plugin will send Speedy and Econt tracking numbers to Connectix.bg. This way, you will help the service work better.', 'nashenski-commerce' ) ),
			)
		) );

		$this->set_fields( $fields );
	}

	public function get_groups_titles() {
		$titles = apply_filters( 'nashenski/admin/settings/connectix/groups_titles', array(
			'connectix' => array(
				'title' => __( 'Connectix', 'nashenski-commerce' ),
			),
		) );

		return $titles;
	}

    public function auth_test() {
		$error = '';

		if ( !$this->container[ Client::CONNECTIX ]->validate_access() ) {
			$error = __( 'Token is incorrect.', 'nashenski-commerce' ); 
		}

		return wpautop( $error );
	}
}
