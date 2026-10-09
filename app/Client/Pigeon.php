<?php
namespace Nashenski\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

class Pigeon {
	use Courier_Client;

	const LIVE_URL = 'https://api.pigeonexpress.com/v1';
    const DEMO_URL = 'https://api-demo.pigeonexpress.com/v1';
    const CREATE_LABEL_ENDPOINT = '/shipments';
    const CALCULATE_ENDPOINT = '/shipments/calculate';
    
    const CACHE_FOLDER = File::CACHE_FOLDER . 'pigeon' . DIRECTORY_SEPARATOR;

    private $env = '';
    private $api_key = '';
    private $api_secret = '';
    private $base_endpoint;
	private $is_valid_access;

    public static $skip_cached_files = array(
		'cities-BG.json',
	);

	public function __construct() {	
		$this->set_env( nashenski_get_option( 'pigeon', 'env' ) );
		$this->load_api_key();
		$this->load_api_secret();
		$this->load_base_endpoint();
	}

	public function api_call( $endpoint, $args, $method = 'GET', $plain = false ) {
		if ( empty( $this->get_api_key() ) || empty( $this->get_api_secret() ) ) {
	        return;
	    }

		$url = $this->get_base_endpoint() . $endpoint;
		$request_args = array(
			'timeout' => 15,
			'headers' => array(
				'content-type' => 'application/json',
				'X-API-Key' => $this->get_api_key(),
				'X-API-Secret' => $this->get_api_secret(),
				'X-Platform' => 'WordPress Nashenski',
			),
			'body' => wp_json_encode( $args ),
		);

		if ( $method === 'GET' ) {
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

	public function is_valid_access( $forced = false ) {
		if ( $forced ) {
			$this->set_is_valid_access();
		}

		return $this->is_valid_access;
	}

	private function set_is_valid_access() {
		$this->is_valid_access = filter_var( nashenski_get_option( 'pigeon', 'is_valid_access' ), FILTER_VALIDATE_BOOLEAN );
	}

	public function check_credentials() {
		if ( empty( $this->get_api_key() ) || empty( $this->get_api_secret() ) ) {
			return false;
		}

		// Without valid keys Pigeon redirects to its website, which also answers 200,
		// so do not follow redirects and expect a JSON answer.
		$request = wp_remote_get( $this->get_base_endpoint() . Pigeon\Cities::CITIES_ENDPOINT . '/759', array(
			'redirection' => 0,
			'timeout' => 15,
			'headers' => array(
				'content-type' => 'application/json',
				'X-API-Key' => $this->get_api_key(),
				'X-API-Secret' => $this->get_api_secret(),
			)
		) );

		return 200 === wp_remote_retrieve_response_code( $request )
			&& is_array( json_decode( wp_remote_retrieve_body( $request ), true ) );
	}

	//Loaders
	public function load_api_key() {
		$this->set_api_key( nashenski_get_option( 'pigeon', 'api_key' ) );
	}

	public function load_api_secret() {
		$this->set_api_secret( nashenski_get_option( 'pigeon', 'api_secret' ) );
	}

	//Getters

	public function get_api_key() {
		return $this->api_key;
	}

	public function get_api_secret() {
		return $this->api_secret;
	}

	public function get_env() {
		return $this->env;
	}

	public function get_base_endpoint() {
		return $this->base_endpoint;
	}

	//Setters

	private function set_api_key( $api_key ) {
		$this->api_key = $api_key;
	}

	private function set_api_secret( $api_secret ) {
		$this->api_secret = $api_secret;
	}

	public function set_env( $env ) {
		$this->env = $env;
	}

	private function set_base_endpoint( $endpoint ) {
		$this->base_endpoint = $endpoint;
	}
}
