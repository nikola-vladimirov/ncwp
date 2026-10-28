<?php
namespace Nashenski\Client\Speedy;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

class Profile {
    const PROFILE_ENDPOINT = 'client/contract';
    const CLIENT_ENDPOINT = 'client/';

    private $is_valid_profile, $profile_data, $container, $clients;

	public function __construct( $container ) {
		$this->container = $container;
		$this->set_is_valid_profile();
		$this->get_profile_data( true );
	}

	public function get_profile_data( $forced = false ) {
		if ( $forced ) {
			$this->set_profile_data();
			$this->load_clients();
		}

		return $this->return_single_profile();
	}

	public function get_client( $id ) {
		return $this->container[ Client::SPEEDY ]->api_call( self::CLIENT_ENDPOINT . $id, array() );
	}

	public function get_clients() {
		return $this->clients;
	}

	public function fetch_profile_data() {
		$this->container[ Client::SPEEDY ]->load_user();
		$this->container[ Client::SPEEDY ]->load_password();

		$profile_data = $this->container[ Client::SPEEDY ]->api_call( self::PROFILE_ENDPOINT, array() );

		if ( $this->container[ Client::SPEEDY ]->validate_access( $profile_data ) ) {
			$clients = [];

			if ( !empty( $profile_data['clients'] ) ) {
				foreach ( $profile_data['clients'] as $profile ) {
					$client = $this->get_client( $profile['clientId'] );

					if ( isset( $client[ 'client' ] ) ) {
						$clients[ $profile['clientId'] ] = $client[ 'client' ];
					}
				}
			} else {
				$profile_client = $this->container[ Client::SPEEDY ]->api_call( self::CLIENT_ENDPOINT, array() );

				if ( ! empty( $profile_client['clientId'] ) ) {
					$client = $this->get_client( $profile_client['clientId'] );

					if ( isset( $client['client'] ) ) {
						$clients[ $profile_client['clientId'] ] = $client['client'];

						$profile_data = [
							'clients' => [ $client['client'] ],
						];
					}
				}
			} 

			nashenski_set_option( 'speedy', 'profile_data', $profile_data );
			delete_transient( 'nashenski_speedy_profile_failed' );
				
			if ( !empty( $clients ) ) {
				nashenski_set_option( 'speedy', 'clients', $clients );
			}
		}

		return $profile_data;
	}

	public function check_credentials() {
		$profile_data = $this->fetch_profile_data();

		return $this->container[ Client::SPEEDY ]::validate_access( $profile_data );
	}

	public function is_valid_profile( $forced = false ) {
		if ( $forced ) {
			$this->set_is_valid_profile();
		}

		return $this->is_valid_profile;
	}

	protected function load_clients() {
		$this->clients = nashenski_get_option( 'speedy', 'clients' );
	}

	private function set_is_valid_profile() {
		$this->is_valid_profile = filter_var( nashenski_get_option( 'speedy', 'is_valid_profile' ), FILTER_VALIDATE_BOOLEAN );
	}

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

	protected function return_single_profile() {
		$profile_id = 0;

		if ( !empty( $this->profile_data[ 'clients' ] ) ) {
			if ( $selected_id = nashenski_get_option( 'speedy', 'profile_key' ) ) {
				$profile_id = $selected_id;
			}

			return $this->profile_data['clients'][ $profile_id ] ?? reset( $this->profile_data['clients'] );
		}

		return;
	}

	public function get_profiles_for_settings() {
		$all_profiles = nashenski_get_option( 'speedy', 'profile_data' );
		$options = array();

		if ( !empty( $all_profiles['clients'] ) ) {
			foreach ( $all_profiles['clients'] as $key => $profile ) {
				$client = $this->clients[ $profile['clientId'] ];
				$label = ( isset( $client['objectName'] ) && $client['objectName'] ) ? $client['objectName'] : $client['clientName'];

				$options[ $key ] = array(
					'id' => $key,
					'label' => $label . " ( ID:" . $profile['clientId'] . " )",
				);

			}
		}

		return $options;
	}
}
