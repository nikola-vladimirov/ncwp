<?php
/**
 * Profile.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client\Speedy;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Profile.
 */
class Profile {
	const PROFILE_ENDPOINT = 'client/contract';
	const CLIENT_ENDPOINT  = 'client/';

	/**
	 * Whether the profile is valid.
	 *
	 * @var mixed
	 */
	private $is_valid_profile;

	/**
	 * Profile data.
	 *
	 * @var mixed
	 */
	private $profile_data;

	/**
	 * Container.
	 *
	 * @var mixed
	 */
	private $container;

	/**
	 * Clients.
	 *
	 * @var mixed
	 */
	private $clients;

	/**
	 * Constructor.
	 *
	 * @param mixed $container Container.
	 */
	public function __construct( $container ) {
		$this->container = $container;
		$this->set_is_valid_profile();
		$this->get_profile_data( true );
	}

	/**
	 * Get profile data.
	 *
	 * @param bool $forced Forced.
	 * @return mixed
	 */
	public function get_profile_data( $forced = false ) {
		if ( $forced ) {
			$this->set_profile_data();
			$this->load_clients();
		}

		return $this->return_single_profile();
	}

	/**
	 * Get client.
	 *
	 * @param mixed $id Id.
	 * @return mixed
	 */
	public function get_client( $id ) {
		return $this->container[ Client::SPEEDY ]->api_call( self::CLIENT_ENDPOINT . $id, array() );
	}

	/**
	 * Get clients.
	 *
	 * @return mixed
	 */
	public function get_clients() {
		return is_array( $this->clients ) ? $this->clients : array();
	}

	/**
	 * Fetch profile data.
	 *
	 * @return mixed
	 */
	public function fetch_profile_data() {
		$this->container[ Client::SPEEDY ]->load_user();
		$this->container[ Client::SPEEDY ]->load_password();

		$profile_data = $this->container[ Client::SPEEDY ]->api_call( self::PROFILE_ENDPOINT, array() );

		if ( $this->container[ Client::SPEEDY ]->validate_access( $profile_data ) ) {
			$clients = array();

			if ( ! empty( $profile_data['clients'] ) ) {
				foreach ( $profile_data['clients'] as $profile ) {
					$client = $this->get_client( $profile['clientId'] );

					if ( isset( $client['client'] ) ) {
						$clients[ $profile['clientId'] ] = $client['client'];
					}
				}
			} else {
				$profile_client = $this->container[ Client::SPEEDY ]->api_call( self::CLIENT_ENDPOINT, array() );

				if ( ! empty( $profile_client['clientId'] ) ) {
					$client = $this->get_client( $profile_client['clientId'] );

					if ( isset( $client['client'] ) ) {
						$clients[ $profile_client['clientId'] ] = $client['client'];

						$profile_data = array(
							'clients' => array( $client['client'] ),
						);
					}
				}
			}

			// A login with no client (empty contract) is not a usable profile: save nothing.
			if ( ! empty( $clients ) ) {
				nashenski_set_option( 'speedy', 'profile_data', $profile_data );
				nashenski_set_option( 'speedy', 'clients', $clients );
				delete_transient( 'nashenski_speedy_profile_failed' );
			}
		}

		return $profile_data;
	}

	/**
	 * Check credentials.
	 *
	 * @return mixed
	 */
	public function check_credentials() {
		$profile_data = $this->fetch_profile_data();

		return $this->container[ Client::SPEEDY ]::validate_access( $profile_data ) && ! empty( $profile_data['clients'] );
	}

	/**
	 * Is valid profile.
	 *
	 * @param bool $forced Forced.
	 * @return mixed
	 */
	public function is_valid_profile( $forced = false ) {
		if ( $forced ) {
			$this->set_is_valid_profile();
		}

		return $this->is_valid_profile;
	}

	/**
	 * Load clients.
	 */
	protected function load_clients() {
		$this->clients = nashenski_get_option( 'speedy', 'clients' );
	}

	/**
	 * Set is valid profile.
	 */
	private function set_is_valid_profile() {
		$this->is_valid_profile = filter_var( nashenski_get_option( 'speedy', 'is_valid_profile' ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Set profile data.
	 */
	private function set_profile_data() {
		$this->profile_data = nashenski_get_option( 'speedy', 'profile_data' );

		if ( ! $this->profile_data && ! get_transient( 'nashenski_speedy_profile_failed' ) ) {
			$this->profile_data = $this->fetch_profile_data();

			// Not saved means the login failed; do not call the courier again on every request.
			if ( ! nashenski_get_option( 'speedy', 'profile_data' ) ) {
				set_transient( 'nashenski_speedy_profile_failed', 1, 5 * MINUTE_IN_SECONDS );
			}
		}
	}

	/**
	 * Return single profile.
	 *
	 * @return mixed
	 */
	protected function return_single_profile() {
		$profile_id = 0;

		if ( ! empty( $this->profile_data['clients'] ) ) {
			$selected_id = nashenski_get_option( 'speedy', 'profile_key' );
			if ( $selected_id ) {
				$profile_id = $selected_id;
			}

			return $this->profile_data['clients'][ $profile_id ] ?? reset( $this->profile_data['clients'] );
		}
	}

	/**
	 * Get profiles for settings.
	 *
	 * @return mixed
	 */
	public function get_profiles_for_settings() {
		$all_profiles = nashenski_get_option( 'speedy', 'profile_data' );
		$options      = array();

		$clients = $this->get_clients();

		if ( ! empty( $all_profiles['clients'] ) ) {
			foreach ( $all_profiles['clients'] as $key => $profile ) {
				if ( ! isset( $profile['clientId'], $clients[ $profile['clientId'] ] ) ) {
					continue;
				}

				$client = $clients[ $profile['clientId'] ];
				$label  = ( isset( $client['objectName'] ) && $client['objectName'] ) ? $client['objectName'] : $client['clientName'];

				$options[ $key ] = array(
					'id'    => $key,
					'label' => $label . ' ( ID:' . $profile['clientId'] . ' )',
				);

			}
		}

		return $options;
	}
}
