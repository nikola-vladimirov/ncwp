<?php
/**
 * Econt client profile.
 *
 * @package Nashenski
 */

namespace Nashenski\Client\Econt;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * The store's Econt client profiles and sender addresses.
 */
class Profile {
	const PROFILE_ENDPOINT = 'Profile/ProfileService.getClientProfiles.json';

	/**
	 * Whether the saved profile is valid.
	 *
	 * @var bool
	 */
	private $is_valid_profile;

	/**
	 * Saved profile data.
	 *
	 * @var mixed
	 */
	private $profile_data;

	/**
	 * Client container.
	 *
	 * @var \Pimple\Container|array
	 */
	private $container;

	/**
	 * Load the saved profile.
	 *
	 * @param \Pimple\Container|array $container Client container.
	 */
	public function __construct( $container ) {
		$this->container = $container;
		$this->set_is_valid_profile();
		$this->get_profile_data( true );
	}

	/**
	 * The chosen profile.
	 *
	 * @param bool $forced Reload the saved data first.
	 * @return array|null
	 */
	public function get_profile_data( $forced = false ) {
		if ( $forced ) {
			$this->set_profile_data();
		}

		return $this->return_single_profile();
	}

	/**
	 * Ask Econt for the profiles and save them when the login works.
	 *
	 * @return array
	 */
	public function fetch_profile_data() {
		$this->container[ Client::ECONT ]->set_env( nashenski_get_option( 'econt', 'env' ) );
		$this->container[ Client::ECONT ]->load_user();
		$this->container[ Client::ECONT ]->load_password();
		$this->container[ Client::ECONT ]->load_base_endpoint();

		$profile_data = $this->container[ Client::ECONT ]->api_call(
			self::PROFILE_ENDPOINT,
			array(
				'GetClientProfilesRequest' => '',
			)
		);

		if ( $this->container[ Client::ECONT ]->validate_access( $profile_data ) ) {
			nashenski_set_option( 'econt', 'profile_data', $profile_data );
			delete_transient( 'nashenski_econt_profile_failed' );
		}

		return $profile_data;
	}

	/**
	 * Whether the saved login works.
	 *
	 * @return bool|null
	 */
	public function check_credentials() {
		$profile_data = $this->fetch_profile_data();

		return $this->container[ Client::ECONT ]::validate_access( $profile_data );
	}

	/**
	 * Whether the saved profile is valid.
	 *
	 * @param bool $forced Reload the setting first.
	 * @return bool
	 */
	public function is_valid_profile( $forced = false ) {
		if ( $forced ) {
			$this->set_is_valid_profile();
		}

		return $this->is_valid_profile;
	}

	/**
	 * Load the "valid profile" setting.
	 */
	private function set_is_valid_profile() {
		$this->is_valid_profile = filter_var( nashenski_get_option( 'econt', 'is_valid_profile' ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Load the saved profiles, fetching them when none are saved.
	 */
	private function set_profile_data() {
		$this->profile_data = nashenski_get_option( 'econt', 'profile_data' );

		if ( ! $this->profile_data && ! get_transient( 'nashenski_econt_profile_failed' ) ) {
			$this->profile_data = $this->fetch_profile_data();

			// Not saved means the login failed; do not call the courier again on every request.
			if ( ! nashenski_get_option( 'econt', 'profile_data' ) ) {
				set_transient( 'nashenski_econt_profile_failed', 1, 5 * MINUTE_IN_SECONDS );
			}
		}
	}

	/**
	 * The profile's addresses for a select.
	 *
	 * @return array
	 */
	public function get_formatted_addresses() {
		$formatted = array();

		if ( ! empty( $this->get_profile_data() ) ) {
			foreach ( $this->get_profile_data()['addresses'] as $key => $address ) {
				$formatted[ $key ] = array(
					'id'    => $key,
					'label' => implode( ' ', array( $address['city']['name'], $address['quarter'], $address['street'], $address['num'], $address['other'] ) ),
				);
			}
		}

		return $formatted;
	}

	/**
	 * How the sender pays: cash, or the chosen method when paying by agreement.
	 *
	 * @return string
	 */
	public static function get_sender_payment_method() {
		$method        = 'cash';
		$cd_pay_option = nashenski_get_option( 'econt', 'pay_options' );
		$sender_method = nashenski_get_option( 'econt', 'sender_payment_type' );

		if ( $cd_pay_option && 'no' !== $cd_pay_option && $sender_method ) {
			$method = $sender_method;
		}

		return $method;
	}

	/**
	 * The chosen profile, else the first one; null when there are none.
	 *
	 * @return array|null
	 */
	protected function return_single_profile() {
		$profile_id = 0;

		if ( ! empty( $this->profile_data['profiles'] ) ) {
			$selected_id = nashenski_get_option( 'econt', 'profile_key' );

			if ( $selected_id ) {
				$profile_id = $selected_id;
			}

			return $this->profile_data['profiles'][ $profile_id ] ?? reset( $this->profile_data['profiles'] );
		}

		return null;
	}

	/**
	 * The profiles for the settings select.
	 *
	 * @return array
	 */
	public function get_profiles_for_settings() {
		$all_profiles = nashenski_get_option( 'econt', 'profile_data' );
		$options      = array();

		if ( ! empty( $all_profiles['profiles'] ) ) {
			foreach ( $all_profiles['profiles'] as $key => $profile ) {
				$options[ $key ] = array(
					'id'    => $key,
					'label' => $profile['client']['name'],
				);

			}
		}

		return $options;
	}
}
