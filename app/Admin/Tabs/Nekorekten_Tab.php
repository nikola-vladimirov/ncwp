<?php
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Client\Nekorekten;

defined( 'ABSPATH' ) || exit;

/**
 * nekorekten.com settings section.
 */
class Nekorekten_Tab extends Base_Tab {
	public function __construct() {
		$this->set_name( __( 'nekorekten.com', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'nekorekten' );
	}

	public function load_fields() {
		$fields = apply_filters( 'nashenski/admin/settings/nekorekten/fields', array(
			'nekorekten' => array(
				new Fields\Text_Field(
					'api_key',
					__( 'API key', 'nashenski-commerce' ),
					null,
					'required',
					sprintf(
						/* translators: %s: link to nekorekten.com */
						__( 'Create a key in your %s profile under "API keys" and allow your server\'s IP address.', 'nashenski-commerce' ),
						'<a href="https://nekorekten.com/" target="_blank" rel="noopener noreferrer">nekorekten.com</a>'
					),
					'password'
				),
				( new Fields\Text_Field(
					'level_of_warning',
					__( 'Reports for a warning', 'nashenski-commerce' ),
					__( 'Number of reports', 'nashenski-commerce' ),
					'required',
					__( 'From how many reports about a phone number the order shows a warning and cash on delivery is turned off at checkout.', 'nashenski-commerce' ),
					'number'
				) )->set_default( '1' ),
				new Fields\Methods_Field( \Nashenski\Reports\Reports::shipping_method_choices(), 'hide_methods', __( 'Hide these methods', 'nashenski-commerce' ), __( 'The checked methods are not offered to a customer that this service flags at the level of warning above.', 'nashenski-commerce' ) ),
			),
		) );

		$this->set_fields( $fields );
	}

	public function get_groups_titles() {
		return array(
			'nekorekten' => array(
				'title' => __( 'nekorekten.com', 'nashenski-commerce' ),
			),
		);
	}

	public function auth_test() {
		$client = new Nekorekten();

		if ( '' === $client->get_api_key() ) {
			return wpautop( __( 'Enter your nekorekten.com API key.', 'nashenski-commerce' ) );
		}

		return $client->validate_access() ? '' : wpautop( __( 'nekorekten.com did not accept the API key. Check the key and the allowed IP addresses in your nekorekten.com profile.', 'nashenski-commerce' ) );
	}
}
