<?php
/**
 * The Speedy cities of a country, cached in a file per region.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Client\Speedy;

use Nashenski\Container\Client;
use Nashenski\Transliteration;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * The Speedy cities of a country, cached in a file per region.
 */
class Cities {
	const CITIES_ENDPOINT = 'location/site/csv/';

	/**
	 * Cities by region, loaded on first use.
	 *
	 * @var array
	 */
	private $cities = array();

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
	 * Reads the cities of a region from the cache file, or from Speedy when there is none.
	 *
	 * @param string     $region     Region name.
	 * @param string|int $country_id Speedy country ID.
	 * @return array
	 */
	protected function load_cities( $region, $country_id = '100' ) {
		if ( ! is_dir( $this->container[ Client::SPEEDY ]::cache_folder() ) ) {
			wp_mkdir_p( $this->container[ Client::SPEEDY ]::cache_folder() );
		}

		// The "region-" part is new: earlier files were cached as empty lists by the old case-sensitive filter.
		$cities_by_region_file = $this->container[ Client::SPEEDY ]::cache_folder() . 'cities-' . $country_id . '-region-' . $region . '.json';

		if ( file_exists( $cities_by_region_file ) ) {
			$cities = json_decode( File::get_file( $cities_by_region_file ), 1 );
		} else {
			$cities_file = $this->container[ Client::SPEEDY ]::cache_folder() . 'cities-' . $country_id . '.csv';

			$failed_key = 'nashenski_speedy_cities_failed_' . $country_id;

			if ( ! is_file( $cities_file ) || ! filesize( $cities_file ) ) {
				// A recent failed fetch is not retried on every request.
				if ( get_transient( $failed_key ) ) {
					return array();
				}

				$body = $this->container[ Client::SPEEDY ]->api_call( self::CITIES_ENDPOINT . $country_id, array(), 1 );

				if ( is_string( $body ) && '' !== trim( $body ) ) {
					File::put_to_file( $cities_file, $body );
				}

				clearstatcache();
			}

			if ( ! is_file( $cities_file ) || ! filesize( $cities_file ) ) {
				if ( is_file( $cities_file ) ) {
					wp_delete_file( $cities_file );
				}

				set_transient( $failed_key, 1, 5 * MINUTE_IN_SECONDS );

				return array();
			}

			// Speedy writes regionEn in mixed case ("Sofia (stolitsa)"); the regions list is upper case.
			$cities = array_values(
				array_filter(
					nashenski_read_csv( $cities_file ),
					function ( $city ) use ( $region ) {
						return mb_strtoupper( $city['regionEn'] ) === $region;
					}
				)
			);

			File::put_to_file( $cities_by_region_file, wp_json_encode( $cities ) );
		}

		$this->set_cities( $region, $cities );

		return $cities;
	}

	/**
	 * The cities of a region.
	 *
	 * @param string     $region     Region name.
	 * @param string|int $country_id Speedy country ID.
	 * @return array
	 */
	public function get_cities( $region, $country_id = '100' ) {
		if ( empty( $this->cities[ $region ] ) ) {
			$this->load_cities( $region, $country_id );
		}

		return $this->cities[ $region ] ?? array();
	}

	/**
	 * Drops the loaded cities of a region from memory.
	 *
	 * @param string $region Region name.
	 * @return void
	 */
	public function release_cities( $region ) {
		unset( $this->cities[ $region ] );
	}

	/**
	 * The cities of every region as picker choices, sorted by label.
	 *
	 * @param string|int $country_id Speedy country ID.
	 * @return array
	 */
	public function get_formatted_cities( $country_id = '100' ) {
		$formatted = array();
		$regions   = $this->get_regions( $country_id );

		foreach ( $regions as $region_key => $region ) {
			$cities = $this->get_cities( $region, $country_id );

			if ( ! empty( $cities ) ) {
				foreach ( $cities as $city ) {
					$formatted[ 'cityID-' . $city['id'] ] = array(
						'id'    => 'cityID-' . $city['id'],
						'label' => sprintf( '%s - %s', $region, $city['name'] ),
					);
				}
			}

			// The formatted list is all we need here. Keeping the raw data for all
			// 28 regions roughly doubles the peak memory used by the settings tab.
			unset( $this->cities[ $region ], $cities );
		}

		uasort(
			$formatted,
			function ( $a, $b ) {
				return strcmp( $a['label'], $b['label'] );
			}
		);

		return $formatted;
	}

	/**
	 * The cities of a region given by its code.
	 *
	 * @param string     $region_code Region code.
	 * @param string|int $country_id  Speedy country ID.
	 * @return array
	 */
	public function get_cities_by_region( $region_code, $country_id = 100 ) {
		// No region, or no Speedy country (Speedy did not answer).
		$region = $region_code ? ( $this->get_regions( $country_id )[ $region_code ] ?? '' ) : '';

		if ( ! $region ) {
			return array();
		}

		return array_values( $this->get_cities( $region, $country_id ) );
	}

	/**
	 * The cities of a region, with the key of the one that matches the typed name.
	 *
	 * @param string     $city       City name typed by the shopper.
	 * @param string     $state      Region code or name.
	 * @param string|int $country_id Speedy country ID.
	 * @return array
	 */
	public function get_filtered_cities( $city, $state, $country_id = 100 ) {
		$city = $this->normalize_city_name( $city, $country_id );

		$cities = $this->get_cities_by_region( $state, $country_id );

		// Orders can keep the region by name ("София") instead of the code ("BG-22"). Then search every region.
		if ( empty( $cities ) && ! array_key_exists( $state, $this->get_regions( $country_id ) ) ) {
			foreach ( array_keys( $this->get_regions( $country_id ) ) as $region_code ) {
				$cities = $this->get_cities_by_region( $region_code, $country_id );

				if ( false !== $this->find_city_key( $city, $cities, $country_id ) ) {
					break;
				}
			}
		}

		$cities_only_names           = array();
		$cities_search_names         = array();
		$cities_only_names_dropdowns = array();

		if ( ! empty( $cities ) ) {
			foreach ( $cities as $temp_city ) {
				$cities_only_names_dropdowns[] = $temp_city['name'];
				$temp_city['name']             = $this->normalize_city_name( $temp_city['name'], $country_id );
				$cities_only_names[]           = $temp_city['name'];
				$cities_search_names[]         = $temp_city;
			}
		}

		$city_key = $this->find_city_key( $city, $cities, $country_id );

		return array(
			'city'                        => $city,
			'cities'                      => $cities,
			'cities_only_names'           => $cities_only_names,
			'cities_search_names'         => $cities_search_names,
			'cities_only_names_dropdowns' => $cities_only_names_dropdowns,
			// false when not found, like the other couriers (callers check !== false).
			'city_key'                    => $city_key ?? false,
		);
	}

	/**
	 * Key of the city with this normalized name, or false.
	 *
	 * @param string     $city       Normalized city name.
	 * @param array      $cities     Cities.
	 * @param string|int $country_id Speedy country ID.
	 * @return int|string|false
	 */
	private function find_city_key( $city, $cities, $country_id ) {
		foreach ( $cities as $key => $temp_city ) {
			if ( $city === $this->normalize_city_name( $temp_city['name'], $country_id ) ) {
				return $key;
			}
		}

		return false;
	}

	/**
	 * City name without prefix such as "гр.", transliterated for Bulgaria, in lower case.
	 *
	 * @param string     $city       City name.
	 * @param string|int $country_id Speedy country ID.
	 * @return string
	 */
	private function normalize_city_name( $city, $country_id ) {
		$city = nashenski_remove_city_prefix( $city );

		if ( '100' === (string) $country_id ) {
			$city = Transliteration::latin2cyrillic( $city );
		}

		$city = mb_strtolower( $city );
		$city = preg_replace( '/[\s\p{Zs}]+/u', ' ', $city );

		return trim( $city, " \t\n\r\0\x0B.,-" );
	}

	/**
	 * Regions of a country: code => name.
	 *
	 * @param string|int|null $country_id Speedy country ID.
	 * @return array
	 */
	public function get_regions( $country_id = 100 ) {
		$regions = \Nashenski\Hooks::filter(
			'nashenski_speedy_cities_regions',
			'nashenski/speedy/cities/regions',
			array(
				100 => array(
					'BG-01' => 'BLAGOEVGRAD',
					'BG-02' => 'BURGAS',
					'BG-03' => 'VARNA',
					'BG-04' => 'VELIKO TARNOVO',
					'BG-05' => 'VIDIN',
					'BG-06' => 'VRATSA',
					'BG-07' => 'GABROVO',
					'BG-08' => 'DOBRICH',
					'BG-09' => 'KARDZHALI',
					'BG-10' => 'KYUSTENDIL',
					'BG-11' => 'LOVECH',
					'BG-12' => 'MONTANA',
					'BG-13' => 'PAZARDZHIK',
					'BG-14' => 'PERNIK',
					'BG-15' => 'PLEVEN',
					'BG-16' => 'PLOVDIV',
					'BG-17' => 'RAZGRAD',
					'BG-18' => 'RUSE',
					'BG-19' => 'SILISTRA',
					'BG-20' => 'SLIVEN',
					'BG-21' => 'SMOLYAN',
					'BG-22' => 'SOFIA (STOLITSA)',
					'BG-23' => 'SOFIA',
					'BG-24' => 'STARA ZAGORA',
					'BG-25' => 'TARGOVISHTE',
					'BG-26' => 'HASKOVO',
					'BG-27' => 'SHUMEN',
					'BG-28' => 'YAMBOL',
				),
			)
		);

		// No country id when Speedy did not answer (no login yet).
		return null === $country_id ? array() : ( $regions[ $country_id ] ?? array() );
	}

	/**
	 * Keeps the cities of a region.
	 *
	 * @param string $region Region name.
	 * @param array  $cities Cities.
	 * @return void
	 */
	private function set_cities( $region, $cities ) {
		$this->cities[ $region ] = $cities;
	}
}
