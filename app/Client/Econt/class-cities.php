<?php
/**
 * Econt cities lookup.
 *
 * @package Nashenski
 */

namespace Nashenski\Client\Econt;

use Nashenski\Container\Client;
use Nashenski\Transliteration;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * Econt cities, kept in a cache file per country.
 */
class Cities {
	const CITIES_ENDPOINT = 'Nomenclatures/NomenclaturesService.getCities.json';

	/**
	 * Loaded cities by country.
	 *
	 * @var array
	 */
	private $cities = array();

	/**
	 * Service container.
	 *
	 * @var \Pimple\Container
	 */
	private $container;

	/**
	 * Keeps the container.
	 *
	 * @param \Pimple\Container $container Service container.
	 */
	public function __construct( $container ) {
		$this->container = $container;
	}

	/**
	 * Loads the cities of a country from the cache file or from Econt.
	 *
	 * @param string $country_code Country code.
	 * @return array|null
	 */
	protected function load_cities( $country_code = 'BG' ) {
		// Guests send the country: only a country code goes into a file name or to Econt.
		if ( ! is_string( $country_code ) || ! preg_match( '/^[A-Z]{2}$/', $country_code ) ) {
			return null;
		}

		if ( ! is_dir( $this->container[ Client::ECONT ]::cache_folder() ) ) {
			wp_mkdir_p( $this->container[ Client::ECONT ]::cache_folder() );
		}

		$cities_file = $this->container[ Client::ECONT ]::cache_folder() . 'cities-' . $country_code . '.json';
		$cities      = File::get_file( $cities_file );

		if ( ! $cities ) {
			$failed_key = 'nashenski_econt_cities_failed_' . $country_code;

			// A recent failed fetch is not retried on every request.
			if ( get_transient( $failed_key ) ) {
				return null;
			}

			$api_call = $this->container[ Client::ECONT ]->api_call( self::CITIES_ENDPOINT, array( 'countryCode' => $country_code ) );

			if ( is_array( $api_call ) ) {
				if ( $this->container[ Client::ECONT ]::validate_access( $api_call ) ) {
					if ( ! empty( $api_call['cities'] ) ) {
						foreach ( $api_call['cities'] as &$city ) {
							unset( $city['servingOffices'] );
						}
						unset( $city );

						$cities = wp_json_encode( $api_call );

						File::put_to_file( $cities_file, $cities );
						unset( $api_call );
					}
				}
			}

			if ( ! $cities ) {
				set_transient( $failed_key, 1, 5 * MINUTE_IN_SECONDS );

				return null;
			}
		}

		$cities = json_decode( $cities, 1 );

		$this->set_cities( $cities, $country_code );

		return $cities;
	}

	/**
	 * Gets the cities of a country as id and label, sorted by label.
	 *
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_formatted_cities( $country_code = 'BG' ) {
		$cities    = $this->container[ Client::ECONT_CITIES ]->get_cities( $country_code );
		$formatted = array();

		foreach ( $cities['cities'] ?? array() as $city ) {
			$label       = $city['name'] ?? '';
			$region_name = $city['regionName'] ?? '';

			if ( $region_name && $region_name !== $label ) {
				$label = $region_name . ' - ' . $label;
			}

			if ( ! empty( $city['name'] ) && $region_name ) {
				$formatted[ 'cityID-' . $city['id'] ] = array(
					'id'    => 'cityID-' . $city['id'],
					'label' => $label,
				);
			}
		}

		uasort(
			$formatted,
			function ( $a, $b ) {
				return strcmp( $a['label'], $b['label'] );
			}
		);

		// The settings select uses only the compact formatted representation.
		// Do not retain the complete Econt city records alongside it.
		unset( $this->cities[ $country_code ], $cities );

		return $formatted;
	}

	/**
	 * Gets the cities of a country.
	 *
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_cities( $country_code = 'BG' ) {
		if ( empty( $this->cities[ $country_code ] ) ) {
			$this->load_cities( $country_code );
		}

		return $this->cities[ $country_code ] ?? null;
	}

	/**
	 * Forgets the loaded cities of a country.
	 *
	 * @param string $country_code Country code.
	 */
	public function release_cities( $country_code = 'BG' ) {
		unset( $this->cities[ $country_code ] );
	}

	/**
	 * Gets the cities of a region.
	 *
	 * @param string $region       Region name.
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_cities_by_region( $region, $country_code = 'BG' ) {
		$cities = $this->get_cities( $country_code );

		if ( empty( $cities ) ) {
			return array();
		}

		$cities = array_filter(
			$cities['cities'],
			function ( $city ) use ( $region ) {
				if ( mb_strtolower( $city['regionName'] ?? '' ) === mb_strtolower( $region ) ||
				mb_strtolower( $city['regionNameEn'] ) === mb_strtolower( $region )
				) {
					return true;
				}
			}
		);

		return array_values( $cities );
	}

	/**
	 * Gets the name of a state.
	 *
	 * @param string $state_code   State code.
	 * @param string $country_code Country code.
	 * @return string
	 */
	public function get_state_name( $state_code, $country_code = 'BG' ) {
		$countries = new \WC_Countries();
		$states    = $countries->get_states( $country_code );

		return $states[ $state_code ];
	}

	/**
	 * Remembers the cities of a country.
	 *
	 * @param array  $cities       Cities.
	 * @param string $country_code Country code.
	 */
	private function set_cities( $cities, $country_code = 'BG' ) {
		$this->cities[ $country_code ] = $cities;
	}

	/**
	 * Finds a city among the cities of a state.
	 *
	 * @param string $city         City name.
	 * @param string $state        State, or comma-separated regions for Greece.
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_filtered_cities( $city, $state, $country_code = 'BG' ) {
		$city = $this->normalize_city_name( $city, $country_code );

		if ( 'GR' === $country_code ) {
			$regions = explode( ',', $state );
			$cities  = array();

			foreach ( $regions as $region ) {
				$cities = array_merge( $cities, self::get_cities_by_region( $region, $country_code ) );
			}
		} else {
			$cities = self::get_cities_by_region( $state, $country_code );
		}

		$cities_only_names           = array();
		$cities_search_names         = array();
		$cities_only_names_dropdowns = array();
		$key                         = 'name';

		if ( 'BG' !== $country_code ) {
			$key = 'nameEn';
		}

		if ( ! empty( $cities ) ) {
			foreach ( $cities as $temp_city ) {
				$cities_only_names_dropdowns[] = $temp_city[ $key ];
				$temp_city[ $key ]             = $this->normalize_city_name( $temp_city[ $key ], $country_code );
				$cities_only_names[]           = $temp_city[ $key ];
				$cities_search_names[]         = $temp_city;
			}
		}

		$city_key = array_search( $city, array_column( $cities_search_names, $key ), true );

		return array(
			'city'                        => $city,
			'cities'                      => $cities,
			'cities_only_names'           => $cities_only_names,
			'cities_search_names'         => $cities_search_names,
			'cities_only_names_dropdowns' => $cities_only_names_dropdowns,
			'city_key'                    => $city_key,
		);
	}

	/**
	 * Lower-cases a city name and removes prefixes and stray punctuation.
	 *
	 * @param string $city         City name.
	 * @param string $country_code Country code.
	 * @return string
	 */
	private function normalize_city_name( $city, $country_code ) {
		$city = nashenski_remove_city_prefix( $city );

		if ( 'BG' === $country_code ) {
			$city = Transliteration::latin2cyrillic( $city );
		}

		$city = mb_strtolower( $city );
		$city = preg_replace( '/[\s\p{Zs}]+/u', ' ', $city );

		return trim( $city, " \t\n\r\0\x0B.,-" );
	}

	/**
	 * Gets the regions of a country.
	 *
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_regions( $country_code = 'BG' ) {
		$regions = \Nashenski\Hooks::filter(
			'nashenski_econt_cities_regions',
			'nashenski/econt/cities/regions',
			array(
				'BG' => array(
					'BG-01' => 'Благоевград',
					'BG-02' => 'Бургас',
					'BG-03' => 'Варна',
					'BG-04' => 'Велико Търново',
					'BG-05' => 'Видин',
					'BG-06' => 'Враца',
					'BG-07' => 'Габрово',
					'BG-08' => 'Добрич',
					'BG-09' => 'Кърджали',
					'BG-10' => 'Кюстендил',
					'BG-11' => 'Ловеч',
					'BG-12' => 'Монтана',
					'BG-13' => 'Пазарджик',
					'BG-14' => 'Перник',
					'BG-15' => 'Плевен',
					'BG-16' => 'Пловдив',
					'BG-17' => 'Разград',
					'BG-18' => 'Русе',
					'BG-19' => 'Силистра',
					'BG-20' => 'Сливен',
					'BG-21' => 'Смолян',
					'BG-22' => 'София',
					'BG-23' => 'София Област',
					'BG-24' => 'Стара Загора',
					'BG-25' => 'Търговище',
					'BG-26' => 'Хасково',
					'BG-27' => 'Шумен',
					'BG-28' => 'Ямбол',
				),
			)
		);

		return $regions[ $country_code ] ?? array();
	}
}
