<?php
namespace Nashenski\Client;

use Nashenski\File;

defined( 'ABSPATH' ) || exit;

class Speedy {
	use Courier_Client;

	const BASE_ENDPOINT          = 'https://api.speedy.bg/v1/';
	const CALC_LABELS_ENDPOINT   = 'calculate';
	const CREATE_LABELS_ENDPOINT = 'shipment';
	const UPDATE_LABELS_ENDPOINT = 'shipment/update';
	const DELETE_LABELS_ENDPOINT = 'shipment/cancel';
	const PRINT_LABELS_ENDPOINT  = 'print';
	const TRACK_ENDPOINT         = 'track';
	const CACHE_FOLDER           = File::CACHE_FOLDER . 'speedy' . DIRECTORY_SEPARATOR;

	private $user                    = '';
	private $password                = '';
	public static $skip_cached_files = array(
		'cities-100.csv',
		'cities-642.csv',
		'cities-300.csv',
	);

	public function __construct() {
		$this->load_user();
		$this->load_password();
	}

	public function api_call( $endpoint, $args, $return_plain = false ) {
		// Without a login Speedy answers with an error page; never call it, so nothing wrong gets cached.
		if ( '' === trim( (string) $this->get_user() ) || '' === trim( (string) $this->get_password() ) ) {
			return $return_plain ? '' : array(
				'error' => array(
					'code'    => 1,
					'message' => __( 'Speedy is not connected.', 'nashenski-commerce' ),
				),
			);
		}

		$args = array_merge(
			array(
				'userName' => $this->get_user(),
				'password' => $this->get_password(),
			),
			$args
		);

		$request = wp_remote_post(
			self::BASE_ENDPOINT . $endpoint,
			array(
				'headers' => array(
					'content-type' => 'application/json',
				),
				'body'    => wp_json_encode( $args ),
				'timeout' => 30,
			)
		);

		if ( $return_plain ) {
			$body = wp_remote_retrieve_body( $request );

			// Lists come as CSV; an error page or an error message must not end up in the cache.
			if ( 200 !== (int) wp_remote_retrieve_response_code( $request ) || preg_match( '/^\s*(<|\{\s*"error)/i', $body ) ) {
				return '';
			}

			return $body;
		}

		return json_decode( wp_remote_retrieve_body( $request ), 1 );
	}

	public function label_request( $type, $request_body ) {
		$endpoints = array(
			'calculate' => self::CALC_LABELS_ENDPOINT,
			'create'    => self::CREATE_LABELS_ENDPOINT,
			'update'    => self::UPDATE_LABELS_ENDPOINT,
			'delete'    => self::DELETE_LABELS_ENDPOINT,
		);

		if ( ! isset( $endpoints[ $type ] ) || empty( $this->get_user() ) || empty( $this->get_password() ) ) {
			return;
		}

		return $this->api_call( $endpoints[ $type ], $request_body );
	}

	public static function validate_access( $api_call ) {
		return is_array( $api_call ) && ! ( isset( $api_call['error'] ) && $api_call['error']['code'] === 1 );
	}

	// Loaders
	public function load_user() {
		$this->set_user( nashenski_get_option( 'speedy', 'user' ) );
	}

	public function load_password() {
		$this->set_password( nashenski_get_option( 'speedy', 'password' ) );
	}

	// Getters

	public function get_user() {
		return $this->user;
	}

	public function get_password() {
		return $this->password;
	}

	// Setters

	private function set_user( $user ) {
		$this->user = $user;
	}

	private function set_password( $password ) {
		$this->password = $password;
	}

	public static function clear_profile_data() {
		nashenski_set_option( 'speedy', 'profile_data', '' );
		nashenski_set_option( 'speedy', 'clients', '' );
	}
}
