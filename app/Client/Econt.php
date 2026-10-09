<?php
namespace Nashenski\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

class Econt {
	use Courier_Client;

	const LIVE_URL = 'https://ee.econt.com/services/';
    const DEMO_URL = 'https://demo.econt.com/ee/services/';
    const LABELS_ENDPOINT = 'Shipments/LabelService.createLabel.json';
    const UPDATE_LABELS_ENDPOINT = 'Shipments/LabelService.updateLabel.json';
    const DELETE_LABELS_ENDPOINT = 'Shipments/LabelService.deleteLabels.json';
    const SHIPMENT_STATUS_ENDPOINT = 'Shipments/ShipmentService.getShipmentStatuses.json';
    const CACHE_FOLDER = File::CACHE_FOLDER . 'econt' . DIRECTORY_SEPARATOR;

    private $env = '';
    private $user = '';
    private $password = '';
    private $base_endpoint;
    public static $skip_cached_files = array(
		'cities-BG.json',
	);

	public function __construct() {	
		$this->set_env( nashenski_get_option( 'econt', 'env' ) );
		$this->load_user();
		$this->load_password();
		$this->load_base_endpoint();
	}

	public function api_call( $endpoint, $args ) {
		// Countries, cities, offices, streets and quarters are public at Econt. Sending a
		// login there only makes them fail when the login is wrong, so send it only elsewhere.
		$is_public = 0 === strpos( $endpoint, 'Nomenclatures/' );

		if ( ! $is_public && ( empty( $this->get_user() ) || empty( $this->get_password() ) ) ) {
	        return;
	    }

		$headers = array( 'content-type' => 'application/json' );

		if ( ! $is_public ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( $this->get_user() . ':' . $this->get_password() );
		}

		$request = wp_remote_post( $this->get_base_endpoint() . $endpoint, array(
			'headers' => $headers,
			'body' => wp_json_encode( $args ),
			'timeout' => 60,
		) );

		return json_decode( wp_remote_retrieve_body( $request ), 1 );
	}

	public function label_request( $type, $request_body, $destination = array() ) {
		if ( empty( $this->get_user() ) || empty( $this->get_password() ) ) {
			return;
		}

		$endpoints = array(
			'calculate' => self::LABELS_ENDPOINT,
			'create' => self::LABELS_ENDPOINT,
			'update' => self::UPDATE_LABELS_ENDPOINT,
			'delete' => self::DELETE_LABELS_ENDPOINT,
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

		foreach ( array( 'name' => 'name', 'post_code' => 'postCode' ) as $from => $to ) {
			if ( ! empty( $destination[ $from ] ) && empty( $city[ $to ] ) ) {
				$city[ $to ] = $destination[ $from ];
			}
		}

		$request_body['label']['receiverAddress']['city'] = $city;

		return $request_body;
	}

	private function get_destination_data( $request_body, $destination ) {
		$city = $request_body['label']['receiverAddress']['city'] ?? array();

		if ( ! is_array( $city ) ) {
			$city = array();
		}

		return array_filter( array(
			'id' => $city['id'] ?? ( $destination['id'] ?? '' ),
			'country_code' => $city['countryCode'] ?? ( $city['country']['code2'] ?? ( $destination['country_code'] ?? '' ) ),
			'name' => $city['name'] ?? ( $city['nameEn'] ?? ( $destination['name'] ?? '' ) ),
			'region' => $city['regionName'] ?? ( $city['regionNameEn'] ?? ( $destination['region'] ?? '' ) ),
			'post_code' => $city['postCode'] ?? ( $destination['post_code'] ?? '' ),
		), function( $value ) {
			return $value !== '' && $value !== null;
		} );
	}

	public static function validate_access( $api_call ) {
		if ( is_array( $api_call ) ) {
			return !( isset( $api_call['type'] ) && $api_call['type'] == 'ExInvalidParam' );
		}
	}

	//Loaders
	public function load_user() {
		$this->set_user( nashenski_get_option( 'econt', 'user' ) );
	}

	public function load_password() {
		$this->set_password( nashenski_get_option( 'econt', 'password' ) );
	}

	//Getters

	public function get_user() {
		return $this->user;
	}

	public function get_password() {
		return $this->password;
	}

	public function get_env() {
		return $this->env;
	}

	public function get_base_endpoint() {
		return $this->base_endpoint;
	}

	//Setters

	private function set_user( $user ) {
		$this->user = $user;
	}

	private function set_password( $password ) {
		$this->password = $password;
	}

	public function set_env( $env ) {
		$this->env = $env;
	}

	private function set_base_endpoint( $endpoint ) {
		$this->base_endpoint = $endpoint;
	}

	public static function clear_profile_data() {
		nashenski_set_option( 'econt', 'profile_data', '' );
	}
}
