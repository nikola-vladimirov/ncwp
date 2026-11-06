<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloader maps the class name to the file name.
/**
 * Connectix API client.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the Connectix shopper check API.
 */
class Connectix {
	const LIVE_URL             = 'https://api.connectix.bg/';
	const SANDBOX_URL          = 'https://api-sandbox.connectix.bg/';
	const INTEGRATION_ENDPOINT = 'integration/register';
	const CHECK_ENDPOINT       = 'shopper/check';
	const TRACKING_ENDPOINT    = 'trackings';

	/**
	 * Environment, "live" or sandbox.
	 *
	 * @var string
	 */
	private $env = '';

	/**
	 * API token.
	 *
	 * @var string
	 */
	private $token = '';

	/**
	 * Integration token from the register call.
	 *
	 * @var string
	 */
	private $integration_token = '';

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $base_endpoint;

	/**
	 * Loads the settings.
	 */
	public function __construct() {
		$this->set_env( nashenski_get_option( 'connectix', 'env' ) );
		$this->load_token();
		$this->load_base_endpoint();
	}

	/**
	 * Posts a JSON request to the API.
	 *
	 * @param string $endpoint Endpoint path.
	 * @param array  $args     Request body.
	 * @return array|null Decoded response, or null without a token.
	 */
	public function api_call( $endpoint, $args ) {
		if ( empty( $this->get_token() ) ) {
			return;
		}

		$request = wp_remote_post(
			$this->get_base_endpoint() . $endpoint,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => $this->get_token(),
				),
				'body'    => wp_json_encode( $args ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $request ) ) {
			return array(
				'success' => false,
				'error'   => $request->get_error_message(),
			);
		}

		return json_decode( wp_remote_retrieve_body( $request ), 1 );
	}

	/**
	 * Registers the integration and keeps its token.
	 *
	 * @return bool True when the API gave a token.
	 */
	public function validate_access() {
		$has_access = false;

		$response = $this->api_call(
			self::INTEGRATION_ENDPOINT,
			array(
				'type'     => 'ecommerce',
				'platform' => 'Nashenski Commerce',
				'secret'   => $this->get_secret(),
			)
		);

		if ( ! empty( $response ) && ! empty( $response['token'] ) ) {
			$this->set_integration_token( $response['token'] );
			$has_access = true;
		}

		return $has_access;
	}

	/**
	 * Loads the API token from the settings.
	 */
	public function load_token() {
		$this->set_token( nashenski_get_option( 'connectix', 'token' ) );
	}

	/**
	 * Picks the live or sandbox URL.
	 */
	public function load_base_endpoint() {
		$base_endpoint = self::SANDBOX_URL;

		if ( 'live' === $this->get_env() ) {
			$base_endpoint = self::LIVE_URL;
		}

		$this->set_base_endpoint( $base_endpoint );
	}

	/**
	 * API token.
	 *
	 * @return string
	 */
	public function get_token() {
		return $this->token;
	}

	/**
	 * Integration token, registering first when there is none yet.
	 *
	 * @return string
	 */
	public function get_integration_token() {
		if ( ! $this->integration_token ) {
			$this->validate_access();
		}

		return $this->integration_token;
	}

	/**
	 * Environment.
	 *
	 * @return string
	 */
	public function get_env() {
		return $this->env;
	}

	/**
	 * API base URL.
	 *
	 * @return string
	 */
	public function get_base_endpoint() {
		return $this->base_endpoint;
	}

	/**
	 * Site secret for the register call, made once.
	 *
	 * @return string
	 */
	public function get_secret() {
		$secret = nashenski_get_option( 'connectix', 'secret' );

		if ( ! $secret ) {
			$secret = wp_generate_password( 32, false );
			nashenski_set_option( 'connectix', 'secret', $secret );
		}

		return $secret;
	}

	/**
	 * Risk levels the API returns, with their labels.
	 *
	 * @return array
	 */
	public static function get_levels_of_probability() {
		return array(
			array(
				'key'   => 'no_info',
				'label' => __( 'No Info', 'nashenski-commerce' ),
			),
			array(
				'key'   => 'low',
				'label' => __( 'Low', 'nashenski-commerce' ),
			),
			array(
				'key'   => 'medium',
				'label' => __( 'Medium', 'nashenski-commerce' ),
			),
			array(
				'key'   => 'high',
				'label' => __( 'High', 'nashenski-commerce' ),
			),
			array(
				'key'   => 'ultra_high',
				'label' => __( 'Ultra high', 'nashenski-commerce' ),
			),
		);
	}

	/**
	 * Sets the API token.
	 *
	 * @param string $token Token.
	 */
	private function set_token( $token ) {
		$this->token = $token;
	}

	/**
	 * Sets the integration token.
	 *
	 * @param string $token Token.
	 */
	private function set_integration_token( $token ) {
		$this->integration_token = $token;
	}

	/**
	 * Sets the environment.
	 *
	 * @param string $env Environment.
	 */
	public function set_env( $env ) {
		$this->env = $env;
	}

	/**
	 * Sets the API base URL.
	 *
	 * @param string $endpoint URL.
	 */
	private function set_base_endpoint( $endpoint ) {
		$this->base_endpoint = $endpoint;
	}
}
