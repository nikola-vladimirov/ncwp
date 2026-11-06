<?php // phpcs:disable WordPress.Files.FileName -- class files are named after the class and autoloaded by namespace.
namespace Nashenski\Client\Pigeon;

use Nashenski\Container\Client;
use Nashenski\Transliteration;
use Nashenski\File;

defined( 'ABSPATH' ) || exit;

/**
 * The Pigeon cities, cached in a file per country, and the city search.
 */
class Cities {
	const CITIES_ENDPOINT = '/cities';

	/**
	 * Cities by country code, loaded on first use.
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
	 * Reads the cities from the cache file, or from Pigeon when there is none.
	 *
	 * @param string $country_code Country code.
	 * @return array
	 */
	protected function load_cities( $country_code = 'BG' ) {
		if ( ! is_dir( $this->container[ Client::PIGEON ]::CACHE_FOLDER ) ) {
			wp_mkdir_p( $this->container[ Client::PIGEON ]::CACHE_FOLDER );
		}

		$cities_file = $this->container[ Client::PIGEON ]::CACHE_FOLDER . 'cities-' . $country_code . '.json';
		$cities      = File::get_file( $cities_file );

		if ( ! $cities ) {
			$all_cities = array();
			$complete   = false;

			// Cap the pages; cache only when every page answered.
			for ( $page = 1; $page <= 200; $page++ ) {
				$data = $this->get_page( $page );

				if ( empty( $data['success'] ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
					break;
				}

				$all_cities = array_merge( $all_cities, $data['data'] );

				if ( $page >= (int) ( $data['meta']['last_page'] ?? 1 ) ) {
					$complete = true;
					break;
				}
			}

			if ( ! empty( $all_cities ) ) {
				$cities = wp_json_encode( array( 'cities' => $all_cities ) );

				if ( $complete ) {
					File::put_to_file( $cities_file, $cities );
				}
				unset( $all_cities, $data );
			}
		}

		$cities = json_decode( $cities, 1 );

		$this->set_cities( $cities, $country_code );

		return $cities;
	}

	/**
	 * One page of cities from Pigeon.
	 *
	 * @param int $page Page number.
	 * @return mixed
	 */
	public function get_page( $page = 1 ) {
		return $this->container[ Client::PIGEON ]->api_call(
			self::CITIES_ENDPOINT,
			array(
				'page' => $page,
			)
		);
	}

	/**
	 * Cities whose name matches the query, cached for the same query.
	 *
	 * @param string $query City name.
	 * @return array|null
	 */
	public function find_city( $query ) {
		$city_search = null;

		$query       = mb_substr( (string) $query, 0, 64 );
		$hash        = md5( $query );
		$city_file   = $this->container[ Client::PIGEON ]::CACHE_FOLDER . 'city-search-' . $hash . '.json';
		$city_search = File::get_file( $city_file );

		$miss_key = 'nashenski_miss_' . md5( $city_file );

		// An empty answer is remembered for 5 minutes, so the same lookup does not call the courier again.
		if ( ! $city_search && ! get_transient( $miss_key ) ) {
			$request = $this->container[ Client::PIGEON ]->api_call(
				self::CITIES_ENDPOINT,
				array(
					'page' => 1,
					'name' => $query,
				)
			);

			if ( ! empty( $request['success'] ) && ! empty( $request['data'] ) && is_array( $request['data'] ) ) {
				$city_search = wp_json_encode( $request['data'] );

				File::put_to_file( $city_file, $city_search );
			} else {
				set_transient( $miss_key, 1, 5 * MINUTE_IN_SECONDS );
			}
		}

		$city_search = json_decode( $city_search, 1 );

		return $city_search;
	}

	/**
	 * The cities as picker choices, sorted by label.
	 *
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_formatted_cities( $country_code = 'BG' ) {
		$cities    = $this->get_cities( $country_code );
		$formatted = array();

		if ( is_array( $cities ) && isset( $cities['cities'] ) && is_array( $cities['cities'] ) ) {
			foreach ( $cities['cities'] as $city ) {
				$label    = $city['name'] ?? '';
				$district = $city['district'] ?? '';

				if ( $district && $district !== $label ) {
					$label = $district . ' - ' . $label;
				}

				if ( ! empty( $city['name'] ) && $district ) {
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
		}

		unset( $this->cities[ $country_code ], $cities );

		return $formatted;
	}

	/**
	 * The cities of a country.
	 *
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_cities( $country_code = 'BG' ) {
		if ( empty( $this->cities[ $country_code ] ) ) {
			$this->load_cities( $country_code );
		}

		return $this->cities[ $country_code ];
	}

	/**
	 * Drops the loaded cities of a country from memory.
	 *
	 * @param string $country_code Country code.
	 * @return void
	 */
	public function release_cities( $country_code = 'BG' ) {
		unset( $this->cities[ $country_code ] );
	}

	/**
	 * Name of a region.
	 *
	 * @param string $state_code   Region code.
	 * @param string $country_code Country code.
	 * @return string
	 */
	public function get_state_name( $state_code, $country_code = 'BG' ) {
		$states = $this->get_regions( $country_code );

		return $states[ $state_code ] ?? '';
	}

	/**
	 * The cities that match the query in a region, with the key of the exact match.
	 *
	 * @param string $query City name typed by the shopper.
	 * @param string $state Region code or name.
	 * @return array
	 */
	public function get_filtered_cities( $query, $state ) {
		if ( empty( $state ) ) {
			return array(
				'city'                        => '',
				'cities'                      => array(),
				'cities_only_names'           => array(),
				'cities_search_names'         => array(),
				'cities_only_names_dropdowns' => array(),
				'city_key'                    => false,
			);
		}

		$city = $this->normalize_city_name( $query );
		// The state can be a code (BG-22) or a region name ("София (столица)"); the list uses names as districts.
		$state_name = $this->get_state_name( $this->region_code( $state ) );
		$cities     = $this->find_city( $city );

		$cities_only_names           = array();
		$cities_search_names         = array();
		$cities_only_names_dropdowns = array();

		if ( ! empty( $cities ) ) {
			foreach ( $cities as $temp_city ) {
				// A state we cannot place takes every district; the exact match is then only used when it is unique.
				if ( $state_name && $this->normalize_text( $temp_city['district'] ) !== $this->normalize_text( $state_name ) ) {
					continue;
				}

				$cities_only_names_dropdowns[] = $temp_city['name'];
				$temp_city['name']             = $this->normalize_city_name( $temp_city['name'] );
				$cities_only_names[]           = $temp_city['name'];
				$cities_search_names[]         = $temp_city;
			}
		}

		$city_key = array_search( $city, array_column( $cities_search_names, 'name' ), true );

		if ( ! $state_name && count( array_keys( array_column( $cities_search_names, 'name' ), $city, true ) ) > 1 ) {
			$city_key = false;
		}

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
	 * The region code for a code or a region name, or '' when it is neither.
	 *
	 * @param string $state Region code or name.
	 * @return string
	 */
	private function region_code( $state ) {
		$regions = $this->get_regions();

		if ( isset( $regions[ $state ] ) ) {
			return $state;
		}

		foreach ( $regions as $code => $name ) {
			if ( $this->normalize_text( $name ) === $this->normalize_text( $state ) ) {
				return $code;
			}
		}

		return '';
	}

	/**
	 * City name without prefix such as "гр.", in Cyrillic and plain text.
	 *
	 * @param string $city City name.
	 * @return string
	 */
	private function normalize_city_name( $city ) {
		$city = nashenski_remove_city_prefix( $city );
		$city = Transliteration::latin2cyrillic( $city );

		return $this->normalize_text( $city );
	}

	/**
	 * Text in lower case with one space between words and no edge punctuation.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function normalize_text( $text ) {
		$text = mb_strtolower( trim( (string) $text ) );
		$text = preg_replace( '/[\s\p{Zs}]+/u', ' ', $text );

		return trim( $text, " \t\n\r\0\x0B.,-" );
	}

	/**
	 * Regions of a country: code => name.
	 *
	 * @param string $country_code Country code.
	 * @return array
	 */
	public function get_regions( $country_code = 'BG' ) {
		$states = array(
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
				'BG-22' => 'София (столица)',
				'BG-23' => 'София',
				'BG-24' => 'Стара Загора',
				'BG-25' => 'Търговище',
				'BG-26' => 'Хасково',
				'BG-27' => 'Шумен',
				'BG-28' => 'Ямбол',
			),
		);

		return $states[ $country_code ];
	}

	/**
	 * Keeps the cities of a country.
	 *
	 * @param array  $cities       Cities.
	 * @param string $country_code Country code.
	 * @return void
	 */
	private function set_cities( $cities, $country_code = 'BG' ) {
		$this->cities[ $country_code ] = $cities;
	}
}
