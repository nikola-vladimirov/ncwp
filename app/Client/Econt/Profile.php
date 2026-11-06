<?php
namespace Nashenski\Client\Econt;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

class Profile {
	const PROFILE_ENDPOINT = 'Profile/ProfileService.getClientProfiles.json';

	private $is_valid_profile, $profile_data, $container;

	public function __construct( $container ) {
		$this->container = $container;
		$this->set_is_valid_profile();
		$this->get_profile_data( true );
	}

	public function get_profile_data( $forced = false ) {
		if ( $forced ) {
			$this->set_profile_data();
		}

		return $this->return_single_profile();
	}

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

	public function check_credentials() {
		$profile_data = $this->fetch_profile_data();

		return $this->container[ Client::ECONT ]::validate_access( $profile_data );
	}

	public function is_valid_profile( $forced = false ) {
		if ( $forced ) {
			$this->set_is_valid_profile();
		}

		return $this->is_valid_profile;
	}

	private function set_is_valid_profile() {
		$this->is_valid_profile = filter_var( nashenski_get_option( 'econt', 'is_valid_profile' ), FILTER_VALIDATE_BOOLEAN );
	}

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

	public static function get_sender_payment_method() {
		$method        = 'cash';
		$cd_pay_option = nashenski_get_option( 'econt', 'pay_options' );
		$sender_method = nashenski_get_option( 'econt', 'sender_payment_type' );

		if ( $cd_pay_option && $cd_pay_option !== 'no' && $sender_method ) {
			$method = $sender_method;
		}

		return $method;
	}

	protected function return_single_profile() {
		$profile_id = 0;

		if ( ! empty( $this->profile_data['profiles'] ) ) {
			if ( $selected_id = nashenski_get_option( 'econt', 'profile_key' ) ) {
				$profile_id = $selected_id;
			}

			return $this->profile_data['profiles'][ $profile_id ] ?? reset( $this->profile_data['profiles'] );
		}

		return;
	}

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
