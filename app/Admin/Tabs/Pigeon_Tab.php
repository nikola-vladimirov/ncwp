<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.
/**
 * Pigeon Tab.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Pigeon Tab.
 */
class Pigeon_Tab extends Base_Tab {
	/**
	 * Container.
	 *
	 * @var mixed
	 */
	protected $container;

	/**
	 * Tab name.
	 *
	 * @var string
	 */
	protected $tab_name;

	/**
	 * Construct.
	 */
	public function __construct() {
		$this->container = nashenski()->container();
		$this->tab_name  = get_called_class();
		$this->set_name( __( 'Pigeon Express', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'pigeon' );

		add_action( 'nashenski/admin/settings/after_save_fields/' . $this->tab_name, array( $this, 'after_save_fields' ) );
		add_filter( 'nashenski/admin/settings/pigeon/fields', array( $this, 'add_send_from_fields' ), 10 );
		add_filter( 'nashenski/admin/settings/pigeon/fields', array( $this, 'add_additional_fields' ), 20 );
	}

	/**
	 * Load fields.
	 */
	public function load_fields() {
		$fields = array(
			'pigeon' => array(
				( new Fields\Select_Field(
					array(
						'live'    => array(
							'id'    => 'live',
							'label' => __( 'Live', 'nashenski-commerce' ),
						),
						'sandbox' => array(
							'id'    => 'sandbox',
							'label' => __( 'Sandbox', 'nashenski-commerce' ),
						),
					),
					'env',
					__( 'Environment', 'nashenski-commerce' ),
					null,
					null,
					__( 'Select used environment.', 'nashenski-commerce' )
				) )->set_default( 'sandbox' ), // Matches the client, which uses the sandbox until Live is saved.
				new Fields\Text_Field( 'api_key', __( 'API Key', 'nashenski-commerce' ), __( 'Enter your API key.', 'nashenski-commerce' ), 'required' ),
				new Fields\Text_Field( 'api_secret', __( 'API Secret', 'nashenski-commerce' ), __( 'Enter your API secret.', 'nashenski-commerce' ), 'required', null, 'password' ),
			),
		);

		$fields = apply_filters( 'nashenski/admin/settings/pigeon/fields', $fields ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- the plugin's public hooks are namespaced with slashes (nashenski/...), renaming would break add-ons.

		$this->set_fields( $fields );
	}

	/**
	 * Pigeon Express accepted the key and secret. Without it the other options show greyed out (Settings_Page).
	 */
	public function connected() {
		return $this->container[ Client::PIGEON ]->is_valid_access( true );
	}

	/**
	 * Add additional fields.
	 *
	 * @param mixed $fields Fields.
	 */
	public function add_additional_fields( $fields ) {
		$fields['pigeon'][] = new Fields\TrueFalse_Field( 'label_after_checkout', __( 'Generate label after checkout', 'nashenski-commerce' ), null, null, __( 'This option will try to generate your label immediately after user checkout. Also, will add the tracking number in the order email.', 'nashenski-commerce' ) );
		$fields['pigeon'][] = new Fields\TrueFalse_Field( 'auto_size', __( 'Automatically calculate box size', 'nashenski-commerce' ), null, null, __( 'Automatically calculate LxWxH of the box for the parcel.', 'nashenski-commerce' ) );
		$fields['pigeon'][] = new Fields\Select_Field(
			array(
				'office'  => array(
					'id'    => 'office',
					'label' => __( 'Office', 'nashenski-commerce' ),
				),
				'address' => array(
					'id'    => 'address',
					'label' => __( 'Address', 'nashenski-commerce' ),
				),
			),
			'send_from',
			__( 'Send From', 'nashenski-commerce' ),
			null,
			null,
			__( 'Select from where you will send the packages and save to show more options.', 'nashenski-commerce' )
		);

		$fields['pigeon_services'][] = new Fields\TrueFalse_Field( 'pay_with_pos', __( 'Pay with POS', 'nashenski-commerce' ), null, null, __( 'POS payment available.', 'nashenski-commerce' ) );
		$fields['pigeon_services'][] = new Fields\TrueFalse_Field( 'declared_value', __( 'Declared value', 'nashenski-commerce' ), null, null, __( 'Adds declared value to the label if payment is COD.', 'nashenski-commerce' ) );
		$fields['pigeon_services'][] = new Fields\TrueFalse_Field( 'paper_return_receipt', __( 'Paper Return Receipt', 'nashenski-commerce' ), null, null, __( 'Request for a paper receipt', 'nashenski-commerce' ) );
		$fields['pigeon_services'][] = new Fields\TrueFalse_Field( 'return_receipt', __( 'Return Receipt', 'nashenski-commerce' ), null, null, __( 'Request for a return receipt in electronic format', 'nashenski-commerce' ) );
		$fields['pigeon_services'][] = new Fields\TrueFalse_Field( 'service_return_documents', __( 'Service Return Documents', 'nashenski-commerce' ), null, null, __( 'Return of original documents from the recipient to the sender after signing.', 'nashenski-commerce' ) );
		$fields['pigeon_services'][] = new Fields\TrueFalse_Field( 'id_verification_and_document_signature', __( 'ID Verification and Document Signature', 'nashenski-commerce' ), null, null, __( 'Verify ID and sign documents electronically.', 'nashenski-commerce' ) );
		$fields['pigeon_services'][] = new Fields\TrueFalse_Field( 'cod_card_fee', __( 'COD Card Fee', 'nashenski-commerce' ), null, null, __( 'Charge a fee for COD card payments.', 'nashenski-commerce' ) );

		return $fields;
	}

	/**
	 * Add send from fields.
	 *
	 * @param mixed $fields Fields.
	 */
	public function add_send_from_fields( $fields ) {
		// Without a working login the lists stay empty and every field shows greyed out.
		$connected                  = $this->connected();
		$cities                     = $connected ? $this->container[ Client::PIGEON_CITIES ]->get_formatted_cities() : array();
		$offices                    = $connected ? $this->container[ Client::PIGEON_OFFICES ]->get_formatted_offices( nashenski_get_option( 'pigeon_send_from', 'city' ) ) : array();
		$fields['pigeon_send_from'] = array();

		if ( $cities || ! $connected ) {
			$fields['pigeon_send_from'][] = new Fields\Select_Field( $cities, 'city', __( 'City', 'nashenski-commerce' ) );
			$fields['pigeon_send_from'][] = new Fields\Text_Field( 'address', __( 'Address', 'nashenski-commerce' ), __( 'Enter your address.', 'nashenski-commerce' ) );
		}

		if ( $offices || ! $connected ) {
			$fields['pigeon_send_from'][] = new Fields\Select_Field(
				$offices,
				'office',
				__( 'Office', 'nashenski-commerce' ),
				null,
				null,
				__( 'Choose a city and save in order to show offices.', 'nashenski-commerce' )
			);
		}

		return $fields;
	}

	/**
	 * Get groups titles.
	 */
	public function get_groups_titles() {
		$titles = apply_filters(
			'nashenski/admin/settings/pigeon/groups_titles', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- the plugin's public hooks are namespaced with slashes (nashenski/...), renaming would break add-ons.
			array(
				'pigeon'           => array(
					'title' => __( 'Pigeon Express account', 'nashenski-commerce' ),
					'desc'  => __( 'The API key and secret Pigeon Express gave you. The other options unlock once Pigeon Express accepts them.', 'nashenski-commerce' ),
				),
				'pigeon_send_from' => array(
					'title' => __( 'Where you send from', 'nashenski-commerce' ),
					'desc'  => __( 'The sender details on your labels and the place you send parcels from.', 'nashenski-commerce' ),
				),
				'pigeon_services'  => array(
					'title' => __( 'Extra services on labels', 'nashenski-commerce' ),
					/* translators: %s: Pigeon Express price page address */
					'desc'  => sprintf( __( 'You can see the current prices on the <a href="%s" target="_blank">Pigeon Express website</a> or according to your agreed upon terms.', 'nashenski-commerce' ), 'https://pigeonexpress.com/prices-and-taxes/' ),
				),
			)
		);

		return $titles;
	}

	/**
	 * After save fields.
	 */
	public function after_save_fields() {
		$is_valid_access = $this->container[ Client::PIGEON ]->check_credentials();

		nashenski_set_option( 'pigeon', 'is_valid_access', $is_valid_access );
	}

	/**
	 * Auth test.
	 */
	public function auth_test() {
		$error = '';

		if ( ! $this->container[ Client::PIGEON ]->is_valid_access( true ) ) {
			$error = __( 'API Key and API Secret are incorrect.', 'nashenski-commerce' );
		}

		return wpautop( $error );
	}
}
