<?php
namespace Nashenski\Client;

defined( 'ABSPATH' ) || exit;

/**
 * nekorekten.com API (https://nekorekten.com/bg/api/doc).
 * Auth: "Api-Key" header. Limit: 10 requests per minute, so answers are cached.
 */
class Nekorekten {
	const BASE_URL         = 'https://api.nekorekten.com/api/v1/';
	const REPORTS_ENDPOINT = 'reports';
	const USAGE_ENDPOINT   = 'api-keys/current/usage';
	const CACHE_SECONDS    = 600;

	private $api_key = '';

	public function __construct() {
		$this->api_key = (string) nashenski_get_option( 'nekorekten', 'api_key' );
	}

	public function get_api_key() {
		return $this->api_key;
	}

	/**
	 * @return array|null Decoded JSON, or null when there is no key or no usable answer.
	 */
	private function get( $endpoint, $query = array() ) {
		if ( '' === $this->api_key ) {
			return null;
		}

		$response = wp_remote_get(
			add_query_arg( $query, self::BASE_URL . $endpoint ),
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'  => 'application/json',
					'Api-Key' => $this->api_key,
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Reports about a phone number: array( 'count' => int, 'items' => array ), or null on error.
	 */
	public function find_by_phone( $phone, $force = false ) {
		$phone = preg_replace( '/[^0-9+]/', '', (string) $phone );

		if ( '' === $phone ) {
			return null;
		}

		$cache_key = 'nashenski_nk_' . md5( $phone );
		$cached    = $force ? false : get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->get( self::REPORTS_ENDPOINT, array( 'phone' => $phone ) );

		if ( null === $data ) {
			return null;
		}

		$result = array(
			'count' => isset( $data['count'] ) ? (int) $data['count'] : count( (array) ( $data['items'] ?? array() ) ),
			'items' => array(),
		);

		// Keep only what the order screen shows.
		foreach ( (array) ( $data['items'] ?? array() ) as $item ) {
			$result['items'][] = array(
				'text' => isset( $item['text'] ) ? wp_strip_all_tags( (string) $item['text'] ) : '',
				'date' => isset( $item['createDate'] ) ? (string) $item['createDate'] : '',
			);
		}

		set_transient( $cache_key, $result, self::CACHE_SECONDS );

		return $result;
	}

	/**
	 * POST, PATCH or DELETE on reports.
	 *
	 * @return array|string The decoded answer on a 2xx, else the reason to show.
	 */
	private function write( $method, $endpoint, $fields = null ) {
		if ( '' === $this->api_key ) {
			return __( 'Add the nekorekten.com API key in the settings first.', 'nashenski-commerce' );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'Api-Key'      => $this->api_key,
			),
		);

		if ( null !== $fields ) {
			$args['body'] = wp_json_encode( $fields );
		}

		$response = wp_remote_request( self::BASE_URL . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			return is_array( $data ) ? $data : array();
		}

		/* translators: %d: HTTP status code */
		return is_array( $data ) && ! empty( $data['message'] ) ? wp_strip_all_tags( (string) $data['message'] ) : sprintf( __( 'nekorekten.com answered with error %d.', 'nashenski-commerce' ), $code );
	}

	/**
	 * Sends a report: text plus a phone or email, names optional.
	 *
	 * @return array|string array( 'id' => report id, '' when the answer had none ), else the reason.
	 */
	public function send_report( $fields ) {
		$data = $this->write( 'POST', self::REPORTS_ENDPOINT, $fields );

		return is_array( $data ) ? array( 'id' => isset( $data['details']['id'] ) ? (string) $data['details']['id'] : '' ) : $data;
	}

	/**
	 * @return true|string
	 */
	public function edit_report( $id, $fields ) {
		$data = $this->write( 'PATCH', self::REPORTS_ENDPOINT . '/' . rawurlencode( $id ), $fields );

		return is_array( $data ) ? true : $data;
	}

	/**
	 * @return true|string
	 */
	public function delete_report( $id ) {
		$data = $this->write( 'DELETE', self::REPORTS_ENDPOINT . '/' . rawurlencode( $id ) );

		return is_array( $data ) ? true : $data;
	}

	public function validate_access() {
		return null !== $this->get( self::USAGE_ENDPOINT );
	}
}
