<?php
/**
 * Econt API client.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the file name to match the class name.

namespace Nashenski\Client;

use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * Calls the Econt API with the store's login.
 */
class Econt {
	use Courier_Client;

	const LIVE_URL                 = 'https://ee.econt.com/services/';
	const DEMO_URL                 = 'https://demo.econt.com/ee/services/';
	const LABELS_ENDPOINT          = 'Shipments/LabelService.createLabel.json';
	const UPDATE_LABELS_ENDPOINT   = 'Shipments/LabelService.updateLabel.json';
	const DELETE_LABELS_ENDPOINT   = 'Shipments/LabelService.deleteLabels.json';
	const SHIPMENT_STATUS_ENDPOINT = 'Shipments/ShipmentService.getShipmentStatuses.json';
	const CACHE_FOLDER             = File::CACHE_FOLDER . 'econt' . DIRECTORY_SEPARATOR;

	/**
	 * Environment: live or demo.
	 *
	 * @var string
	 */
	private $env = '';

	/**
	 * API user.
	 *
	 * @var string
	 */
	private $user = '';

	/**
	 * API password.
	 *
	 * @var string
	 */
	private $password = '';

	/**
	 * Base URL of the API.
	 *
	 * @var string
	 */
	private $base_endpoint;

	/**
	 * Cache files kept when the cache is cleared.
	 *
	 * @var string[]
	 */
	public static $skip_cached_files = array(
		'cities-BG.json',
	);

	/**
	 * Load the login and the endpoint from the settings.
	 */
	public function __construct() {
		$this->set_env( nashenski_get_option( 'econt', 'env' ) );
		$this->load_user();
		$this->load_password();
		$this->load_base_endpoint();
	}

	/**
	 * Call an endpoint. Always an array.
	 *
	 * @param string $endpoint Endpoint path.
	 * @param array  $args     Request body.
	 * @return array
	 */
	public function api_call( $endpoint, $args ) {
		// Countries, cities, offices, streets and quarters are public at Econt. Sending a
		// login there only makes them fail when the login is wrong, so send it only elsewhere.
		$is_public = 0 === strpos( $endpoint, 'Nomenclatures/' );

		if ( ! $is_public && ( empty( $this->get_user() ) || empty( $this->get_password() ) ) ) {
			return array( 'success' => false );
		}

		$headers = array( 'content-type' => 'application/json' );

		if ( ! $is_public ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( $this->get_user() . ':' . $this->get_password() ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic login header.
		}

		$request = wp_remote_post(
			$this->get_base_endpoint() . $endpoint,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $args ),
				'timeout' => 60,
			)
		);

		// Always an array, so callers never get null. Econt's own error body (type, message) is kept for validate_access().
		if ( is_wp_error( $request ) ) {
			return array(
				'success' => false,
				'error'   => $request->get_error_message(),
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $request ), true );

		return is_array( $data ) ? $data : array(
			'success' => false,
			'error'   => 'HTTP ' . wp_remote_retrieve_response_code( $request ),
		);
	}

	/**
	 * Calculate, create, update or delete a label. Null without a login or for an unknown type.
	 *
	 * @param string $type         calculate, create, update or delete.
	 * @param array  $request_body Request body.
	 * @param array  $destination  Destination city details.
	 * @return array|null
	 */
	public function label_request( $type, $request_body, $destination = array() ) {
		if ( empty( $this->get_user() ) || empty( $this->get_password() ) ) {
			return;
		}

		$endpoints = array(
			'calculate' => self::LABELS_ENDPOINT,
			'create'    => self::LABELS_ENDPOINT,
			'update'    => self::UPDATE_LABELS_ENDPOINT,
			'delete'    => self::DELETE_LABELS_ENDPOINT,
		);

		if ( ! isset( $endpoints[ $type ] ) ) {
			return;
		}

		if ( in_array( $type, array( 'calculate', 'create' ), true ) ) {
			$request_body = $this->fill_receiver_city( $request_body, $this->get_destination_data( $request_body, $destination ) );
		}

		return $this->api_call( $endpoints[ $type ], $request_body );
	}

	/**
	 * When the receiver address has no Econt city ID, give Econt the country, name and
	 * postcode so it can find the city itself (needed outside Bulgaria).
	 *
	 * @param array $request_body Request body.
	 * @param array $destination  Destination city details.
	 * @return array
	 */
	private function fill_receiver_city( $request_body, $destination ) {
		if ( empty( $request_body['label']['receiverAddress'] ) || ! empty( $destination['id'] ) ) {
			return $request_body;
		}

		$city = $request_body['label']['receiverAddress']['city'] ?? array();
		$city = is_array( $city ) ? $city : array();

		if ( ! empty( $destination['country_code'] ) && empty( $city['country']['code2'] ) ) {
			$city['country'] = array( 'code2' => $destination['country_code'] );
		}

		foreach ( array(
			'name'      => 'name',
			'post_code' => 'postCode',
		) as $from => $to ) {
			if ( ! empty( $destination[ $from ] ) && empty( $city[ $to ] ) ) {
				$city[ $to ] = $destination[ $from ];
			}
		}

		$request_body['label']['receiverAddress']['city'] = $city;

		return $request_body;
	}

	/**
	 * The receiver city details, from the request or else from the destination.
	 *
	 * @param array $request_body Request body.
	 * @param array $destination  Destination city details.
	 * @return array
	 */
	private function get_destination_data( $request_body, $destination ) {
		$city = $request_body['label']['receiverAddress']['city'] ?? array();

		if ( ! is_array( $city ) ) {
			$city = array();
		}

		return array_filter(
			array(
				'id'           => $city['id'] ?? ( $destination['id'] ?? '' ),
				'country_code' => $city['countryCode'] ?? ( $city['country']['code2'] ?? ( $destination['country_code'] ?? '' ) ),
				'name'         => $city['name'] ?? ( $city['nameEn'] ?? ( $destination['name'] ?? '' ) ),
				'region'       => $city['regionName'] ?? ( $city['regionNameEn'] ?? ( $destination['region'] ?? '' ) ),
				'post_code'    => $city['postCode'] ?? ( $destination['post_code'] ?? '' ),
			),
			function ( $value ) {
				return '' !== $value && null !== $value;
			}
		);
	}

	/**
	 * Whether an API answer means the login works. Null when the answer is not an array.
	 *
	 * @param mixed $api_call API answer.
	 * @return bool|null
	 */
	public static function validate_access( $api_call ) {
		if ( is_array( $api_call ) ) {
			return ! ( isset( $api_call['type'] ) && 'ExInvalidParam' === $api_call['type'] );
		}
	}

	/**
	 * Load the API user from the settings.
	 */
	public function load_user() {
		$this->set_user( nashenski_get_option( 'econt', 'user' ) );
	}

	/**
	 * Load the API password from the settings.
	 */
	public function load_password() {
		$this->set_password( nashenski_get_option( 'econt', 'password' ) );
	}

	/**
	 * The API user.
	 *
	 * @return string
	 */
	public function get_user() {
		return $this->user;
	}

	/**
	 * The API password.
	 *
	 * @return string
	 */
	public function get_password() {
		return $this->password;
	}

	/**
	 * The environment: live or demo.
	 *
	 * @return string
	 */
	public function get_env() {
		return $this->env;
	}

	/**
	 * Base URL of the API.
	 *
	 * @return string
	 */
	public function get_base_endpoint() {
		return $this->base_endpoint;
	}

	/**
	 * Set the API user.
	 *
	 * @param string $user User.
	 */
	private function set_user( $user ) {
		$this->user = $user;
	}

	/**
	 * Set the API password.
	 *
	 * @param string $password Password.
	 */
	private function set_password( $password ) {
		$this->password = $password;
	}

	/**
	 * Set the environment.
	 *
	 * @param string $env live or demo.
	 */
	public function set_env( $env ) {
		$this->env = $env;
	}

	/**
	 * Set the base URL of the API.
	 *
	 * @param string $endpoint Base URL.
	 */
	private function set_base_endpoint( $endpoint ) {
		$this->base_endpoint = $endpoint;
	}

	/**
	 * Forget the saved Econt profile.
	 */
	public static function clear_profile_data() {
		nashenski_set_option( 'econt', 'profile_data', '' );
	}
}
