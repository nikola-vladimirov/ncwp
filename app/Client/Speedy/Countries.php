<?php // phpcs:disable WordPress.Files.FileName -- class files are named after the class and autoloaded by namespace.
namespace Nashenski\Client\Speedy;

use Nashenski\Container\Client;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * The Speedy countries, cached in a file.
 */
class Countries {
	const COUNTRIES_ENDPOINT = 'location/country/csv/';

	/**
	 * Countries, loaded on first use.
	 *
	 * @var array
	 */
	private $countries = array();

	/**
	 * Dependency container.
	 *
	 * @var object
	 */
	private $container;

	/**
	 * Keeps the container.
	 *
	 * @param object $container Dependency container.
	 */
	public function __construct( $container ) {
		$this->container = $container;
	}

	/**
	 * Reads the country with this ISO code from the cache file, or from Speedy when there is none.
	 *
	 * @param string $name_en Country ISO alpha-2 code.
	 * @return array
	 */
	protected function load_countries( $name_en ) {
		if ( ! is_dir( $this->container[ Client::SPEEDY ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::SPEEDY ]::CACHE_FOLDER );
		}

		$countries_file = $this->container[ Client::SPEEDY ]::CACHE_FOLDER . 'countries.csv';

		$failed_key = 'nashenski_speedy_countries_failed';

		if ( ! is_file( $countries_file ) || ! filesize( $countries_file ) ) {
			// A recent failed fetch is not retried on every request.
			if ( get_transient( $failed_key ) ) {
				return array();
			}

			$body = $this->container[ Client::SPEEDY ]->api_call( self::COUNTRIES_ENDPOINT, array(), 1 );

			if ( is_string( $body ) && '' !== trim( $body ) ) {
				File::put_to_file( $countries_file, $body );
			}

			clearstatcache();
		}

		if ( ! is_file( $countries_file ) || ! filesize( $countries_file ) ) {
			if ( is_file( $countries_file ) ) {
				wp_delete_file( $countries_file );
			}

			set_transient( $failed_key, 1, 5 * MINUTE_IN_SECONDS );

			return array();
		}

		$countries = array_filter(
			nashenski_read_csv( $countries_file ),
			function ( $country ) use ( $name_en ) {
				return $country['isoAlpha2'] === $name_en;
			}
		);

		$this->set_countries( $countries );

		return $countries;
	}

	/**
	 * The country with this ISO code.
	 *
	 * @param string $country Country ISO alpha-2 code.
	 * @return array
	 */
	public function get_countries_by_name_en( $country ) {
		if ( empty( $this->countries ) ) {
			$this->load_countries( $country );
		}

		return $this->countries;
	}

	/**
	 * Speedy ID of a country.
	 *
	 * @param string $key Country ISO alpha-2 code.
	 * @return mixed Null when Speedy did not answer.
	 */
	public function get_country_id( $key ) {
		$this->get_countries_by_name_en( $key );

		// Null when Speedy did not answer (no login yet).
		return array_values( (array) $this->countries )[0]['id'] ?? null;
	}

	/**
	 * Keeps the countries.
	 *
	 * @param array $countries_by_name_en Countries.
	 * @return void
	 */
	private function set_countries( $countries_by_name_en ) {
		$this->countries = $countries_by_name_en;
	}
}
