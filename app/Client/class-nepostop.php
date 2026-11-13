<?php
/**
 * NepoStop API client.
 *
 * @package Nashenski
 */

namespace Nashenski\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Client of the nepostop.com API.
 */
class NepoStop {
	const LIVE_URL              = 'https://nepostop.com/api/';
	const ADD_SHIPMENT_ENDPOINT = 'v1/shipments';
	const CHECK_ENDPOINT        = 'v1/phone-check';

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key = '';

	/**
	 * Loads the API key.
	 */
	public function __construct() {
		$this->load_api_key();
	}

	/**
	 * Posts to an endpoint of the API.
	 *
	 * @param string $endpoint Endpoint after the base URL.
	 * @param array  $args     Request body.
	 * @return array|null The decoded answer, or null when there is no API key.
	 */
	public function api_call( $endpoint, $args ) {
		if ( empty( $this->get_api_key() ) ) {
			return;
		}

		$request = wp_remote_post(
			self::LIVE_URL . $endpoint,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'X-API-Key'    => $this->get_api_key(),
				),
				'body'    => wp_json_encode( $args ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $request ) ) {
			return array(
				'success' => false,
				'found'   => false,
				'error'   => $request->get_error_message(),
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $request );
		$data   = json_decode( wp_remote_retrieve_body( $request ), 1 );

		// NepoStop answers in JSON, errors too. Anything else (a gateway page) is an error with its HTTP status.
		return is_array( $data ) ? $data : array(
			'success' => false,
			'found'   => false,
			'error'   => 'HTTP ' . $status,
		);
	}

	/**
	 * Tells whether the saved API key is accepted.
	 *
	 * @return bool
	 */
	public function validate_access() {
		$has_access = false;

		$request = wp_remote_post(
			self::LIVE_URL . self::CHECK_ENDPOINT,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
					'X-API-Key'    => $this->get_api_key(),
				),
				'body'    => wp_json_encode( array( 'phone_number' => '0888000001' ) ),
				'timeout' => 15,
			)
		);

		if ( ! is_wp_error( $request ) && 200 === (int) wp_remote_retrieve_response_code( $request ) ) {
			$has_access = true;
		}

		return $has_access;
	}

	/**
	 * Loads the API key from the settings.
	 */
	public function load_api_key() {
		$this->set_api_key( nashenski_get_option( 'nepostop', 'api_key' ) );
	}

	/**
	 * Gets the API key.
	 *
	 * @return string
	 */
	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * Sets the API key.
	 *
	 * @param string $api_key API key.
	 */
	private function set_api_key( $api_key ) {
		$this->api_key = $api_key;
	}
}
