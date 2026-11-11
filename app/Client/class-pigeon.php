<?php
/**
 * The Pigeon Express API client: credentials, environment and requests.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client;

defined( 'ABSPATH' ) || exit;

/**
 * The Pigeon Express API client: credentials, environment and requests.
 */
class Pigeon {
	use Courier_Client;

	const LIVE_URL              = 'https://api.pigeonexpress.com/v1';
	const DEMO_URL              = 'https://api-demo.pigeonexpress.com/v1';
	const CREATE_LABEL_ENDPOINT = '/shipments';
	const CALCULATE_ENDPOINT    = '/shipments/calculate';

	const CACHE_NAME = 'pigeon';

	/**
	 * Environment: live or demo.
	 *
	 * @var string
	 */
	private $env = '';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key = '';

	/**
	 * API secret.
	 *
	 * @var string
	 */
	private $api_secret = '';

	/**
	 * API URL for the environment.
	 *
	 * @var string
	 */
	private $base_endpoint;

	/**
	 * Whether the saved credentials were accepted.
	 *
	 * @var bool
	 */
	private $is_valid_access;

	/**
	 * Cache files that clearing the cache folder keeps.
	 *
	 * @var array
	 */
	public static $skip_cached_files = array(
		'cities-BG.json',
	);

	/**
	 * Loads the environment and the credentials from the settings.
	 */
	public function __construct() {
		$this->set_env( nashenski_get_option( 'pigeon', 'env' ) );
		$this->load_api_key();
		$this->load_api_secret();
		$this->load_base_endpoint();
	}

	/**
	 * Calls the API.
	 *
	 * @param string $endpoint Endpoint path.
	 * @param array  $args     Request arguments.
	 * @param string $method   HTTP method.
	 * @param bool   $plain    Return the body as text instead of decoded JSON.
	 * @return mixed Null without credentials.
	 */
	public function api_call( $endpoint, $args, $method = 'GET', $plain = false ) {
		if ( empty( $this->get_api_key() ) || empty( $this->get_api_secret() ) ) {
			return;
		}

		$url          = $this->get_base_endpoint() . $endpoint;
		$request_args = array(
			'timeout' => 15,
			'headers' => array(
				'content-type' => 'application/json',
				'X-API-Key'    => $this->get_api_key(),
				'X-API-Secret' => $this->get_api_secret(),
				'X-Platform'   => 'WordPress Nashenski',
			),
			'body'    => wp_json_encode( $args ),
		);

		if ( 'GET' === $method ) {
			unset( $request_args['body'] );

			$request = wp_remote_get( add_query_arg( $args, $url ), $request_args );
		} else {
			$request = wp_remote_post( $url, $request_args );
		}

		if ( $plain ) {
			return wp_remote_retrieve_body( $request );
		} else {
			return json_decode( wp_remote_retrieve_body( $request ), 1 );
		}
	}

	/**
	 * Whether the saved credentials were accepted.
	 *
	 * @param bool $forced Read the setting again.
	 * @return bool
	 */
	public function is_valid_access( $forced = false ) {
		if ( $forced ) {
			$this->set_is_valid_access();
		}

		return $this->is_valid_access;
	}

	/**
	 * Reads the saved access check result.
	 *
	 * @return void
	 */
	private function set_is_valid_access() {
		$this->is_valid_access = filter_var( nashenski_get_option( 'pigeon', 'is_valid_access' ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Asks Pigeon whether the credentials work.
	 *
	 * @return bool
	 */
	public function check_credentials() {
		if ( empty( $this->get_api_key() ) || empty( $this->get_api_secret() ) ) {
			return false;
		}

		// Without valid keys Pigeon redirects to its website, which also answers 200,
		// so do not follow redirects and expect a JSON answer.
		$request = wp_remote_get(
			$this->get_base_endpoint() . Pigeon\Cities::CITIES_ENDPOINT . '/759',
			array(
				'redirection' => 0,
				'timeout'     => 15,
				'headers'     => array(
					'content-type' => 'application/json',
					'X-API-Key'    => $this->get_api_key(),
					'X-API-Secret' => $this->get_api_secret(),
				),
			)
		);

		return 200 === wp_remote_retrieve_response_code( $request )
			&& is_array( json_decode( wp_remote_retrieve_body( $request ), true ) );
	}

	/**
	 * Loads the API key from the settings.
	 *
	 * @return void
	 */
	public function load_api_key() {
		$this->set_api_key( nashenski_get_option( 'pigeon', 'api_key' ) );
	}

	/**
	 * Loads the API secret from the settings.
	 *
	 * @return void
	 */
	public function load_api_secret() {
		$this->set_api_secret( nashenski_get_option( 'pigeon', 'api_secret' ) );
	}

	/**
	 * The API key.
	 *
	 * @return string
	 */
	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * The API secret.
	 *
	 * @return string
	 */
	public function get_api_secret() {
		return $this->api_secret;
	}

	/**
	 * The environment.
	 *
	 * @return string
	 */
	public function get_env() {
		return $this->env;
	}

	/**
	 * The API URL.
	 *
	 * @return string
	 */
	public function get_base_endpoint() {
		return $this->base_endpoint;
	}

	/**
	 * Keeps the API key.
	 *
	 * @param string $api_key API key.
	 * @return void
	 */
	private function set_api_key( $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Keeps the API secret.
	 *
	 * @param string $api_secret API secret.
	 * @return void
	 */
	private function set_api_secret( $api_secret ) {
		$this->api_secret = $api_secret;
	}

	/**
	 * Sets the environment.
	 *
	 * @param string $env Environment.
	 * @return void
	 */
	public function set_env( $env ) {
		$this->env = $env;
	}

	/**
	 * Keeps the API URL.
	 *
	 * @param string $endpoint API URL.
	 * @return void
	 */
	private function set_base_endpoint( $endpoint ) {
		$this->base_endpoint = $endpoint;
	}
}
