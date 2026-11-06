<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.
/**
 * Speedy Tab.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Speedy Tab.
 */
class Speedy_Tab extends Base_Tab {
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
		$this->set_name( __( 'Speedy', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'speedy' );

		add_action( 'nashenski/admin/settings/after_save_fields/' . $this->tab_name, array( $this, 'after_save_fields' ) );
		add_filter( 'nashenski/admin/settings/speedy/fields', array( $this, 'add_profile_data_fields' ), 10 );
		add_filter( 'nashenski/admin/settings/speedy/fields', array( $this, 'add_send_from_fields' ), 20 );
	}

	/**
	 * Load fields.
	 */
	public function load_fields() {
		$fields = array(
			'speedy' => array(
				new Fields\Text_Field( 'user', __( 'Username', 'nashenski-commerce' ), __( 'Enter your username.', 'nashenski-commerce' ), 'required' ),
				new Fields\Text_Field( 'password', __( 'Password', 'nashenski-commerce' ), __( 'Enter your password.', 'nashenski-commerce' ), 'required', null, 'password' ),
			),
		);

		$fields = apply_filters( 'nashenski/admin/settings/speedy/fields', $fields ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- the plugin's public hooks are namespaced with slashes (nashenski/...), renaming would break add-ons.

		$this->set_fields( $fields );
	}

	/**
	 * The login works and has profiles. Without it the other options show greyed out (Settings_Page).
	 */
	public function connected() {
		return $this->container[ Client::SPEEDY_PROFILE ]->is_valid_profile( true ) && $this->container[ Client::SPEEDY_PROFILE ]->get_profiles_for_settings();
	}

	/**
	 * Add profile data fields.
	 *
	 * @param mixed $fields Fields.
	 */
	public function add_profile_data_fields( $fields ) {
		$all_profiles = $this->connected() ? $this->container[ Client::SPEEDY_PROFILE ]->get_profiles_for_settings() : array();

		$fields['speedy'][] = new Fields\Select_Field(
			$all_profiles,
			'profile_key',
			__( 'Select profile', 'nashenski-commerce' ),
			null,
			null,
			__( 'Select the profile you want to use and save to show or update the other options.', 'nashenski-commerce' )
		);
		$fields['speedy'][] = new Fields\Text_Field( 'name', __( 'Name', 'nashenski-commerce' ) );
		$fields['speedy'][] = new Fields\Text_Field( 'phone', __( 'Phone', 'nashenski-commerce' ) );
		$fields['speedy'][] = new Fields\TrueFalse_Field( 'ppp', __( 'Cash on delivery as PPP.', 'nashenski-commerce' ) );
		$fields['speedy'][] = new Fields\TrueFalse_Field( 'kb', __( 'Cash on delivery with cash receipt.', 'nashenski-commerce' ) );
		$fields['speedy'][] = new Fields\TrueFalse_Field( 'administrative_fee', __( 'Administrative fee on price calculations', 'nashenski-commerce' ), null, null, __( 'Usage of administrative fee is enabled and configured in client contract. Ignored if not applicable for user.', 'nashenski-commerce' ) );
		$fields['speedy'][] = new Fields\TrueFalse_Field( 'force_variations_in_desc', __( 'Force variations in label', 'nashenski-commerce' ), null, null, __( 'Add additional variations information. Please use this option only if you want the variation data to be available in the label print and it\'s missing.', 'nashenski-commerce' ) );
		$fields['speedy'][] = new Fields\TrueFalse_Field( 'label_after_checkout', __( 'Generate label after checkout', 'nashenski-commerce' ), null, null, __( 'This option will try to generate your label immediately after user checkout. Also, will add the tracking number in the order email.', 'nashenski-commerce' ) );
		$fields['speedy'][] = new Fields\TrueFalse_Field( 'declared_value', __( 'Declared value', 'nashenski-commerce' ), null, null, __( 'Adds declared value to the label if payment is COD.', 'nashenski-commerce' ) );
		$fields['speedy'][] = new Fields\TrueFalse_Field( 'auto_size', __( 'Automatically calculate box size', 'nashenski-commerce' ), null, null, __( 'Automatically calculate LxWxH of the box for the parcel.', 'nashenski-commerce' ) );
		$fields['speedy'][] = new Fields\Select_Field(
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
			__( 'Select from where you will send the packages and save to show more options.', 'nashenski-commerce' ) . ' ' . __( 'If you choose "Address" the profile address will be used.', 'nashenski-commerce' ),
		);

		return $fields;
	}

	/**
	 * Add send from fields.
	 *
	 * @param mixed $fields Fields.
	 */
	public function add_send_from_fields( $fields ) {
		// Without a working login the lists stay empty and every field shows greyed out.
		$connected                    = $this->connected();
		$cities                       = $connected ? $this->container[ Client::SPEEDY_CITIES ]->get_formatted_cities() : array();
		$fields['speedy_send_from']   = array();
		$fields['speedy_send_from'][] = new Fields\Select_Field( $cities, 'city', __( 'City', 'nashenski-commerce' ) );

		if ( ! $connected || nashenski_get_option( 'speedy_send_from', 'city' ) ) {
			$offices = $connected ? $this->container[ Client::SPEEDY_OFFICES ]->get_formatted_offices( nashenski_get_option( 'speedy_send_from', 'city' ) ) : array();
			if ( ! empty( $offices['shops'] ) || ! $connected ) {
				$fields['speedy_send_from'][] = new Fields\Select_Field(
					$offices['shops'] ?? array(),
					'office',
					__( 'Office', 'nashenski-commerce' ),
					null,
					null,
					__( 'Choose a city and save in order to show offices.', 'nashenski-commerce' )
				);
			}
		}

		return $fields;
	}

	/**
	 * Get groups titles.
	 */
	public function get_groups_titles() {
		$titles = apply_filters(
			'nashenski/admin/settings/speedy/groups_titles', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- the plugin's public hooks are namespaced with slashes (nashenski/...), renaming would break add-ons.
			array(
				'speedy'           => array(
					'title' => __( 'Speedy account', 'nashenski-commerce' ),
					'desc'  => __( 'The user and password of your Speedy API account. The other options unlock once Speedy accepts the login.', 'nashenski-commerce' ),
				),
				'speedy_send_from' => array(
					'title' => __( 'Where you send from', 'nashenski-commerce' ),
					'desc'  => __( 'The sender details on your labels and the place you send parcels from.', 'nashenski-commerce' ),
				),
			)
		);

		return $titles;
	}

	/**
	 * After save fields.
	 */
	public function after_save_fields() {
		$is_valid_profile = $this->container[ Client::SPEEDY_PROFILE ]->check_credentials( true );

		nashenski_set_option( 'speedy', 'is_valid_profile', $is_valid_profile );

		if ( $is_valid_profile ) {
			$this->update_profile_fields();
		}
	}

	/**
	 * Update profile fields.
	 */
	public function update_profile_fields() {
		$profile = $this->container[ Client::SPEEDY_PROFILE ]->get_profile_data( 1 );
		$clients = $this->container[ Client::SPEEDY_PROFILE ]->get_clients();
		$client  = $clients[ $profile['clientId'] ];

		$name  = nashenski_get_option( 'speedy', 'name' );
		$phone = nashenski_get_option( 'speedy', 'phone' );

		if ( ! $name ) {
			nashenski_set_option( 'speedy', 'name', $client['contactName'] );
		}

		if ( ! $phone ) {
			nashenski_set_option( 'speedy', 'phone', $client['phones'][0]['number'] );
		}
	}

	/**
	 * Auth test.
	 */
	public function auth_test() {
		$error = '';

		if ( ! $this->container[ Client::SPEEDY_PROFILE ]->is_valid_profile( true ) ) {
			$error = __( 'Username and password are incorrect.', 'nashenski-commerce' );
		} else {
			$all_profiles = $this->container[ Client::SPEEDY_PROFILE ]->get_profiles_for_settings();

			if ( empty( $all_profiles ) ) {
				$error = __( 'No profiles was found. Please contact with Speedy.', 'nashenski-commerce' );
			}
		}

		return wpautop( $error );
	}
}
