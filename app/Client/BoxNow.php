<?php
namespace Nashenski\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

class BoxNow {
	use Courier_Client;

	const LIVE_URL = 'https://api-production.boxnow.bg';
    const DEMO_URL = 'https://api-stage.boxnow.bg';
    const AUTH_ENDPOINT = '/api/v1/auth-sessions';
    const CREATE_LABELS_ENDPOINT = '/api/v1/delivery-requests';

	const CACHE_FOLDER = File::CACHE_FOLDER . 'boxnow' . DIRECTORY_SEPARATOR;

	private $env = '';
	private $client_id = '';
	private $client_secret = '';
	private $base_endpoint;
	private $access_token;

	public function __construct() {
		$this->set_env( nashenski_get_option( 'boxnow', 'env' ) );
		$this->load_client_id();
		$this->load_client_secret();
		$this->load_base_endpoint();

		$this->load_access_token( true );
	}

	public function api_call( $endpoint, $args, $method = 'POST', $return_plain = false ) {
		if ( ! $this->get_access_token() ) {
			return;
		}

		$url = $this->get_base_endpoint() . $endpoint;
		
		$request_args = array(
			'timeout' => 15,
			'headers' => array(
				'content-type' => 'application/json',
				'Authorization' => 'Bearer ' . $this->get_access_token(),
			),
			'body' => wp_json_encode( $args ),
		);

		if ( $method === 'GET' ) {
			unset( $request_args['body'] );

			$request = wp_remote_get( add_query_arg( $args, $url ), $request_args );
		} else {
			$request = wp_remote_post( $url, $request_args );
		}

		if ( $return_plain ) {
			return wp_remote_retrieve_body( $request );
		}

		$response = json_decode( wp_remote_retrieve_body( $request ), 1 );

		// An error answer carries BOX NOW's code and sometimes its own (English) text.
		if ( isset( $response['code'] ) ) {
			$text = self::error_text( $response['code'] );
			if ( '' === $text && is_scalar( $response['message'] ?? null ) ) {
				$text = (string) $response['message'];
			}
			$response['message'] = rtrim( 'BOX NOW ' . $response['code'] . ': ' . $text, ' :' );

			if ( ! empty( $response['jsonSchemaErrors'] ) && is_array( $response['jsonSchemaErrors'] ) ) {
				$response['message'] .= ' ' . implode( '. ', array_map( function ( $error ) {
					return is_scalar( $error ) ? $error : wp_json_encode( $error );
				}, $response['jsonSchemaErrors'] ) );
			}
		}

		return $response;
	}

	/**
	 * Error codes from the BOX NOW API integration manual (v1.69, boxnow.bg) that making or cancelling a label can return.
	 */
	public static function error_text( $code ) {
		$texts = array(
			'P400' => __( 'Some label data is not valid.', 'nashenski-commerce' ),
			'P401' => __( 'The sending warehouse or locker is not valid. Choose one from the list.', 'nashenski-commerce' ),
			'P402' => __( 'The destination locker is not valid. Choose one from the list.', 'nashenski-commerce' ),
			'P403' => __( 'Your BOX NOW account cannot send from any locker (AnyAPM). Contact BOX NOW.', 'nashenski-commerce' ),
			'P405' => __( 'The phone number is not valid. Use the full international format, for example +359 88 123 4567.', 'nashenski-commerce' ),
			'P406' => __( 'Choose a box size: small, medium or large.', 'nashenski-commerce' ),
			'P407' => __( 'The country code is not valid.', 'nashenski-commerce' ),
			'P408' => __( 'The cash on delivery amount must be more than 0 and less than 5000.', 'nashenski-commerce' ),
			'P410' => __( 'BOX NOW already has a label for this order number.', 'nashenski-commerce' ),
			'P411' => __( 'Your BOX NOW account cannot use cash on delivery. Contact BOX NOW.', 'nashenski-commerce' ),
			'P413' => __( 'The return warehouse is not valid.', 'nashenski-commerce' ),
			'P415' => __( 'Your BOX NOW account cannot deliver to an address. Contact BOX NOW.', 'nashenski-commerce' ),
			'P416' => __( 'Your BOX NOW account cannot use cash on delivery to an address. Contact BOX NOW.', 'nashenski-commerce' ),
			'P420' => __( 'This parcel can no longer be cancelled.', 'nashenski-commerce' ),
			'P421' => __( 'The parcel weight is not valid.', 'nashenski-commerce' ),
			'P466' => __( 'BOX NOW does not accept new labels because your account has an overdue payment.', 'nashenski-commerce' ),
			'P600' => __( 'BOX NOW could not reach the locker. Try again later.', 'nashenski-commerce' ),
		);

		return $texts[ $code ] ?? '';
	}

	public function load_access_token( $forced = false ) {
		if ( $forced ) {
			$this->load_client_id();
			$this->load_client_secret();
		}

		$request = wp_remote_post( $this->get_base_endpoint() . self::AUTH_ENDPOINT, array(
			'headers' => array( 
				'Content-Type' => 'application/json',
			),
			'body' => wp_json_encode( array(
				'grant_type' => 'client_credentials',
				'client_id' => $this->get_client_id(),
				'client_secret' => $this->get_client_secret(),
			) ),
		) );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) !== 200 ) {
			$this->set_access_token( null );
			return;
		}

		$response = json_decode( wp_remote_retrieve_body( $request ), true );
		$this->set_access_token( $response[ 'access_token' ] );
	}

	//Loaders
	public function load_client_id() {
		$this->set_client_id( nashenski_get_option( 'boxnow', 'client_id' ) );
	}

	public function load_client_secret() {
		$this->set_client_secret( nashenski_get_option( 'boxnow', 'client_secret' ) );
	}

	//Getters

	public function get_client_id() {
		return $this->client_id;
	}

	public function get_client_secret() {
		return $this->client_secret;
	}

	public function get_env() {
		return $this->env;
	}

	public function get_base_endpoint() {
		return $this->base_endpoint;
	}

	public function get_access_token() {
		return $this->access_token;
	}

	//Setters

	private function set_client_id( $user ) {
		$this->client_id = $user;
	}

	private function set_client_secret( $password ) {
		$this->client_secret = $password;
	}

	public function set_env( $env ) {
		$this->env = $env;
	}

	private function set_base_endpoint( $endpoint ) {
		$this->base_endpoint = $endpoint;
	}

	private function set_access_token( $access_token ) {
		$this->access_token = $access_token;
	}
}
