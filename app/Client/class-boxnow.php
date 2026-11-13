<?php
/**
 * Boxnow.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Box now.
 */
class BoxNow {
	use Courier_Client {
		clear_cache_folder as protected clear_courier_cache_folder;
	}

	const LIVE_URL               = 'https://api-production.boxnow.bg';
	const DEMO_URL               = 'https://api-stage.boxnow.bg';
	const AUTH_ENDPOINT          = '/api/v1/auth-sessions';
	const CREATE_LABELS_ENDPOINT = '/api/v1/delivery-requests';

	const CACHE_NAME = 'boxnow';

	/**
	 * Env.
	 *
	 * @var string
	 */
	private $env = '';
	/**
	 * Client id.
	 *
	 * @var string
	 */
	private $client_id = '';
	/**
	 * Client secret.
	 *
	 * @var string
	 */
	private $client_secret = '';
	/**
	 * Base endpoint.
	 *
	 * @var mixed
	 */
	private $base_endpoint;
	/**
	 * Access token.
	 *
	 * @var mixed
	 */
	private $access_token;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->set_env( nashenski_get_option( 'boxnow', 'env' ) );
		$this->load_client_id();
		$this->load_client_secret();
		$this->load_base_endpoint();

		$this->load_access_token( true );
	}

	/**
	 * The shared cache clearing, plus the sender addresses kept in the private documents folder.
	 *
	 * @param bool $skip_cached_files Skip cached files.
	 */
	public static function clear_cache_folder( $skip_cached_files = false ) {
		static::clear_courier_cache_folder( $skip_cached_files );
		BoxNow\Origins::clear();
	}

	/**
	 * Api call.
	 *
	 * @param mixed  $endpoint     Endpoint.
	 * @param mixed  $args         Args.
	 * @param string $method       Method.
	 * @param bool   $return_plain Return plain.
	 * @return mixed
	 */
	public function api_call( $endpoint, $args, $method = 'POST', $return_plain = false ) {
		if ( ! $this->get_access_token() ) {
			return;
		}

		$url = $this->get_base_endpoint() . $endpoint;

		$request_args = array(
			'timeout' => 15,
			'headers' => array(
				'content-type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->get_access_token(),
			),
			'body'    => wp_json_encode( $args ),
		);

		if ( 'GET' === $method ) {
			unset( $request_args['body'] );

			$request = wp_remote_get( add_query_arg( $args, $url ), $request_args );
		} else {
			$request = wp_remote_post( $url, $request_args );
		}

		// No answer at all (no connection, a timeout): nothing to read.
		if ( is_wp_error( $request ) ) {
			return $return_plain ? '' : null;
		}

		$status = (int) wp_remote_retrieve_response_code( $request );

		// A file (a label PDF) only when BOX NOW says it is one: an error answer is not a file.
		if ( $return_plain ) {
			return 200 === $status ? wp_remote_retrieve_body( $request ) : '';
		}

		$response = json_decode( wp_remote_retrieve_body( $request ), 1 );

		// An error with no JSON in it (a gateway page) still gets a code, for the message below.
		if ( ! is_array( $response ) && $status >= 400 ) {
			$response = array( 'code' => 'HTTP ' . $status );
		}

		// An error answer carries BOX NOW's code and sometimes its own (English) text.
		if ( isset( $response['code'] ) ) {
			$text = self::error_text( $response['code'] );
			if ( '' === $text && is_scalar( $response['message'] ?? null ) ) {
				$text = (string) $response['message'];
			}
			$response['message'] = rtrim( 'BOX NOW ' . $response['code'] . ': ' . $text, ' :' );

			if ( ! empty( $response['jsonSchemaErrors'] ) && is_array( $response['jsonSchemaErrors'] ) ) {
				$response['message'] .= ' ' . implode(
					'. ',
					array_map(
						function ( $error ) {
							return is_scalar( $error ) ? $error : wp_json_encode( $error );
						},
						$response['jsonSchemaErrors']
					)
				);
			}
		}

		return $response;
	}

	/**
	 * Error codes from the BOX NOW API integration manual (v1.69, boxnow.bg) that making or cancelling a label can return.
	 *
	 * @param mixed $code Code.
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

	/**
	 * Load access token.
	 *
	 * @param bool $forced Forced.
	 */
	public function load_access_token( $forced = false ) {
		if ( $forced ) {
			$this->load_client_id();
			$this->load_client_secret();
		}

		$token_key = 'nashenski_boxnow_token_' . md5( $this->get_base_endpoint() . $this->get_client_id() . $this->get_client_secret() );
		$cached    = get_transient( $token_key );

		if ( $cached ) {
			$this->set_access_token( $cached );
			return;
		}

		$request = wp_remote_post(
			$this->get_base_endpoint() . self::AUTH_ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'grant_type'    => 'client_credentials',
						'client_id'     => $this->get_client_id(),
						'client_secret' => $this->get_client_secret(),
					)
				),
			)
		);

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) !== 200 ) {
			$this->set_access_token( null );
			return;
		}

		$response = json_decode( wp_remote_retrieve_body( $request ), true );
		$token    = $response['access_token'] ?? null;
		$this->set_access_token( $token );

		if ( $token ) {
			set_transient( $token_key, $token, max( 60, (int) ( $response['expires_in'] ?? 0 ) - 60 ) );
		}
	}

	// Loaders.

	/**
	 * Load client id.
	 */
	public function load_client_id() {
		$this->set_client_id( nashenski_get_option( 'boxnow', 'client_id' ) );
	}

	/**
	 * Load client secret.
	 */
	public function load_client_secret() {
		$this->set_client_secret( nashenski_get_option( 'boxnow', 'client_secret' ) );
	}

	// Getters.

	/**
	 * Get client id.
	 *
	 * @return mixed
	 */
	public function get_client_id() {
		return $this->client_id;
	}

	/**
	 * Get client secret.
	 *
	 * @return mixed
	 */
	public function get_client_secret() {
		return $this->client_secret;
	}

	/**
	 * Get env.
	 *
	 * @return mixed
	 */
	public function get_env() {
		return $this->env;
	}

	/**
	 * Get base endpoint.
	 *
	 * @return mixed
	 */
	public function get_base_endpoint() {
		return $this->base_endpoint;
	}

	/**
	 * Get access token.
	 *
	 * @return mixed
	 */
	public function get_access_token() {
		return $this->access_token;
	}

	// Setters.

	/**
	 * Set client id.
	 *
	 * @param mixed $user User.
	 */
	private function set_client_id( $user ) {
		$this->client_id = $user;
	}

	/**
	 * Set client secret.
	 *
	 * @param mixed $password Password.
	 */
	private function set_client_secret( $password ) {
		$this->client_secret = $password;
	}

	/**
	 * Set env.
	 *
	 * @param mixed $env Env.
	 */
	public function set_env( $env ) {
		$this->env = $env;
	}

	/**
	 * Set base endpoint.
	 *
	 * @param mixed $endpoint Endpoint.
	 */
	private function set_base_endpoint( $endpoint ) {
		$this->base_endpoint = $endpoint;
	}

	/**
	 * Set access token.
	 *
	 * @param mixed $access_token Access token.
	 */
	private function set_access_token( $access_token ) {
		$this->access_token = $access_token;
	}
}
