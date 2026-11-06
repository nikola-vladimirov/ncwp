<?php
/**
 * Checkout office, locker and address pickers.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Front_End\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Office, locker and address pickers for every checkout, and how the shipping options read.
 *
 * - One picker (assets/js/pickers.js) for the classic checkout, page builder checkouts
 *   (pickers-classic.js) and the block checkout (pickers-block.js). It writes the courier
 *   cookie the shipping methods read, then asks WooCommerce to price the cart again.
 * - A shipping option that still needs an office, locker or address says "Complete the
 *   fields to calculate" instead of a price or "Free".
 * - Office and locker options of a courier that has no login and no public office list are
 *   hidden, because the customer could not choose anything.
 * - The chosen office or address is written into the order address when that is empty.
 */
class Pickers {
	const PENDING_META = 'nashenski_pending';

	// Login settings of each courier (group = courier slug).
	const LOGIN = array(
		'econt'  => array( 'user', 'password' ),
		'speedy' => array( 'user', 'password' ),
		'boxnow' => array( 'client_id', 'client_secret' ),
		'pigeon' => array( 'api_key', 'api_secret' ),
	);

	/**
	 * Hooks the pickers into the checkout.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'woocommerce_after_shipping_rate', array( __CLASS__, 'render_container' ), 5, 2 );
		add_filter( 'woocommerce_cart_shipping_packages', array( __CLASS__, 'add_choice' ) );
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'mark_rates' ), 100, 2 );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( __CLASS__, 'full_label' ), 20, 2 );
		add_filter( 'bricks/dynamic_data/format_value', array( __CLASS__, 'builder_shipping_total' ), 10, 4 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'fill_order_address' ), 5, 3 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_ajax_nashenski_search_towns', array( __CLASS__, 'search_towns' ) );
		add_action( 'wp_ajax_nopriv_nashenski_search_towns', array( __CLASS__, 'search_towns' ) );
	}

	/**
	 * The pickers ask for the town themselves when the checkout hides the town or the region
	 * field: every courier needs both. The towns come from Econt's public list, so only with Econt on.
	 */
	public static function town_picker() {
		return 'yes' === nashenski_get_option( 'apis', 'enable_econt' )
			&& ( 'hidden' === Field_Editor::rule( 'city' ) || 'hidden' === Field_Editor::rule( 'state' ) );
	}

	/**
	 * Towns and villages in Bulgaria for the picker's town search: exact names first, then names
	 * that start with the text, then the rest. Latin text is read as Cyrillic too ("Varna").
	 */
	public static function search_towns() {
		nashenski_check_picker_request();

		$text     = mb_strtolower( trim( nashenski_remove_city_prefix( nashenski_picker_request( 'query' ) ) ) );
		$cyrillic = mb_strtolower( \Nashenski\Transliteration::latin2cyrillic( $text ) );
		$found    = array();

		if ( mb_strlen( $text ) > 1 ) {
			$cities  = nashenski()->container()[ \Nashenski\Container\Client::ECONT_CITIES ];
			$regions = array_flip( $cities->get_regions( 'BG' ) );

			foreach ( $cities->get_cities( 'BG' )['cities'] ?? array() as $city ) {
				$state = $regions[ $city['regionName'] ?? '' ] ?? '';
				$names = array( mb_strtolower( (string) $city['name'] ), mb_strtolower( (string) ( $city['nameEn'] ?? '' ) ) );
				$rank  = 3;

				foreach ( $names as $name ) {
					foreach ( array( $cyrillic, $text ) as $query ) {
						$at = mb_strpos( $name, $query );

						if ( false !== $at ) {
							$rank = min( $rank, $name === $query ? 0 : ( 0 === $at ? 1 : 2 ) );
						}
					}
				}

				if ( $state && $rank < 3 ) {
					$found[] = array(
						'rank'     => $rank,
						'name'     => $city['name'],
						'state'    => $state,
						'region'   => $city['regionName'],
						'postcode' => (string) ( $city['postCode'] ?? '' ),
					);
				}
			}

			usort(
				$found,
				function ( $a, $b ) {
					return array( $a['rank'], $a['name'] ) <=> array( $b['rank'], $b['name'] );
				}
			);
		}

		wp_send_json_success( array( 'towns' => array_slice( $found, 0, 30 ) ) );
	}

	/**
	 * Courier slug of a rate or method ID such as "nashenski_econt:3", or ''.
	 *
	 * @param string $id Rate or method ID.
	 * @return string
	 */
	public static function courier_of( $id ) {
		return preg_match( '/^nashenski_([a-z]+)/', (string) $id, $match ) && in_array( $match[1], \Nashenski\Plugin::COURIERS, true ) ? $match[1] : '';
	}

	/**
	 * Whether the store entered a login for the courier (not whether it works).
	 *
	 * @param string $courier Courier slug.
	 * @return bool
	 */
	public static function connected( $courier ) {
		foreach ( self::LOGIN[ $courier ] ?? array() as $key ) {
			if ( '' === trim( (string) nashenski_get_option( $courier, $key ) ) ) {
				return false;
			}
		}

		return isset( self::LOGIN[ $courier ] );
	}

	/**
	 * Couriers whose offices customers can choose without a login: Econt publishes its office
	 * list, Speedy has a public office map, Pigeon Express publishes its offices and lockers.
	 * (BOX NOW lockers are public too, but BOX NOW is only offered with a login.)
	 *
	 * @param string $courier Courier slug.
	 * @return bool
	 */
	public static function public_offices( $courier ) {
		return in_array( $courier, array( 'econt', 'speedy', 'pigeon' ), true );
	}

	/**
	 * Delivery type of a rate: office, automat, locker, apm or address.
	 *
	 * @param \WC_Shipping_Rate $rate Shipping rate.
	 * @return string
	 */
	public static function type_of( $rate ) {
		$courier = self::courier_of( $rate->get_id() );

		if ( 'boxnow' === $courier ) {
			return 'apm';
		}

		$meta = $rate->get_meta_data();

		return isset( $meta['delivery_type'] ) ? (string) $meta['delivery_type'] : 'office';
	}

	/**
	 * Whether a rate still waits for the customer's office, locker or address.
	 *
	 * @param mixed $rate Shipping rate.
	 * @return bool
	 */
	public static function is_pending( $rate ) {
		if ( ! is_object( $rate ) || ! self::courier_of( $rate->get_id() ) ) {
			return false;
		}

		$meta = $rate->get_meta_data();

		return isset( $meta[ self::PENDING_META ] ) && 'yes' === $meta[ self::PENDING_META ];
	}

	/**
	 * WooCommerce keeps calculated rates until the package changes, and the office or address
	 * choice lives in the courier cookies. With the choice in the package, every request prices
	 * the current choice, also an order placed before the checkout had updated the price.
	 *
	 * @param array $packages Shipping packages.
	 * @return array
	 */
	public static function add_choice( $packages ) {
		$choice = md5(
			wp_json_encode(
				array_map(
					function ( $courier ) {
						return nashenski_courier_cookie( 'nashenski--' . $courier . ( 'boxnow' === $courier ? '-apm' : '-address' ) );
					},
					\Nashenski\Plugin::COURIERS
				)
			)
		);

		foreach ( $packages as $key => $package ) {
			$packages[ $key ]['nashenski_choice'] = $choice;
		}

		return $packages;
	}

	/**
	 * Mark rates that still wait for the customer's choice, and drop office options nobody can choose.
	 *
	 * @param array $rates   Package rates.
	 * @param array $package Shipping package.
	 * @return array
	 */
	public static function mark_rates( $rates, $package ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress hook callback signature.
		foreach ( $rates as $id => $rate ) {
			$courier = self::courier_of( $id );

			if ( ! $courier ) {
				continue;
			}

			$type = self::type_of( $rate );

			if ( 'address' !== $type && ! self::connected( $courier ) && ! self::public_offices( $courier ) ) {
				unset( $rates[ $id ] );
				continue;
			}

			$meta   = $rate->get_meta_data();
			$free   = ! empty( $meta['free_shipping'] );
			$cookie = isset( $meta['cookie_data'] ) && is_array( $meta['cookie_data'] ) ? $meta['cookie_data'] : array();
			$chosen = 'boxnow' === $courier ? ! empty( $cookie['selectedApm'] ) : ! empty( $meta['validated'] );

			if ( (float) $rate->get_cost() <= 0 && ! $free && ! $chosen ) {
				$rate->add_meta_data( self::PENDING_META, 'yes' );
			}
		}

		return $rates;
	}

	/**
	 * Text shown instead of a price while the choice is missing.
	 *
	 * @return string
	 */
	public static function pending_text() {
		return __( 'Complete the fields to calculate', 'nashenski-commerce' );
	}

	/**
	 * Classic checkout and cart: "Econt: Complete the fields to calculate" instead of a bare name.
	 *
	 * @param string            $label Rate label.
	 * @param \WC_Shipping_Rate $rate  Shipping rate.
	 * @return string
	 */
	public static function full_label( $label, $rate ) {
		if ( self::is_pending( $rate ) ) {
			return $label . ': <span class="nashenski-rate-pending">' . esc_html( self::pending_text() ) . '</span>';
		}

		$meta = is_object( $rate ) && self::courier_of( $rate->get_id() ) ? $rate->get_meta_data() : array();

		if ( ! empty( $meta['free_shipping'] ) && (float) $rate->get_cost() <= 0 && false === strpos( $label, 'woocommerce-Price-amount' ) ) {
			return $label . ': <span class="nashenski-rate-free">' . esc_html__( 'Free shipping', 'nashenski-commerce' ) . '</span>';
		}

		return $label;
	}

	/**
	 * The builder's shipping total tag says "Free" for any rate without a price.
	 *
	 * @param mixed  $value   Tag value.
	 * @param string $tag     Dynamic data tag.
	 * @param int    $post_id Post ID.
	 * @param array  $filters Tag filters.
	 * @return mixed
	 */
	public static function builder_shipping_total( $value, $tag, $post_id, $filters ) {
		if ( ! is_string( $tag ) || false === strpos( $tag, 'cart_shipping_total_amount' ) || isset( $filters['value'] ) ) {
			return $value;
		}

		$rate = self::chosen_rate();

		return $rate && self::is_pending( $rate ) ? '<span class="nashenski-rate-pending">' . esc_html( self::pending_text() ) . '</span>' : $value;
	}

	/**
	 * The chosen rate of the first package.
	 *
	 * @return \WC_Shipping_Rate|null
	 */
	private static function chosen_rate() {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ! WC()->shipping() ) {
			return null;
		}

		$chosen   = WC()->session->get( 'chosen_shipping_methods', array() );
		$packages = WC()->shipping()->get_packages();
		$id       = $chosen[0] ?? '';

		return $id && isset( $packages[0]['rates'][ $id ] ) ? $packages[0]['rates'][ $id ] : null;
	}

	/**
	 * Holder for the picker under each Nashenski Commerce shipping option. The script fills the
	 * one of the chosen option.
	 *
	 * @param \WC_Shipping_Rate $rate  Shipping rate.
	 * @param int               $index Package index.
	 */
	public static function render_container( $rate, $index ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- WordPress hook callback signature.
		$courier = self::courier_of( $rate->get_id() );

		if ( ! $courier ) {
			return;
		}

		printf(
			'<div class="nashenski-additional-fields" data-rate="%1$s" data-courier="%2$s" data-type="%3$s" data-pending="%4$s"></div>',
			esc_attr( $rate->get_id() ),
			esc_attr( $courier ),
			esc_attr( self::type_of( $rate ) ),
			self::is_pending( $rate ) ? '1' : '0'
		);
	}

	/**
	 * Delivery type of every Nashenski Commerce shipping method in the shipping zones, by rate ID.
	 * The block checkout does not print our holders, so the script needs this to label the options.
	 */
	public static function rate_types() {
		$types   = array();
		$zones   = \WC_Shipping_Zones::get_zones();
		$zones[] = array( 'shipping_methods' => ( new \WC_Shipping_Zone( 0 ) )->get_shipping_methods() );

		foreach ( $zones as $zone ) {
			foreach ( $zone['shipping_methods'] ?? array() as $method ) {
				$courier = self::courier_of( $method->id );

				if ( $courier ) {
					$types[ $method->id . ':' . $method->instance_id ] = array(
						'courier' => $courier,
						'type'    => 'boxnow' === $courier ? 'apm' : (string) $method->get_option( 'delivery_type', 'office' ),
					);
				}
			}
		}

		return $types;
	}

	/**
	 * Whether the page uses the block cart or checkout.
	 *
	 * @return bool
	 */
	public static function is_block_checkout() {
		return is_singular() && ( has_block( 'woocommerce/checkout' ) || has_block( 'woocommerce/cart' ) );
	}

	/**
	 * Adds the layout class on the cart and checkout.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( Courier_Logos::is_cart_or_checkout() ) {
			$classes[] = 'nashenski-shipping--' . Appearance::layout();
		}

		return $classes;
	}

	/**
	 * Loads the picker styles and scripts on the cart and checkout.
	 */
	public static function enqueue() {
		if ( ! Courier_Logos::is_cart_or_checkout() ) {
			return;
		}

		$url = nashenski()->plugin_dir_url() . 'assets/';
		$dir = nashenski()->plugin_dir_path() . 'assets/';
		$ver = function ( $file ) use ( $dir ) {
			return \Nashenski\Plugin::VERSION . '-' . filemtime( $dir . $file );
		};

		wp_enqueue_style( 'nashenski-checkout', $url . 'css/checkout.css', array(), $ver( 'css/checkout.css' ) );
		wp_add_inline_style( 'nashenski-checkout', Appearance::css() );

		// The block checkout ignores the "hidden" rule for the country, so hide it here when only Bulgaria is allowed.
		if ( array( 'BG' ) === Countries::countries() ) {
			wp_add_inline_style( 'nashenski-checkout', '.wc-block-components-address-form__country{display:none!important}' );
		}

		wp_enqueue_script( 'nashenski-pickers', $url . 'js/pickers.js', array( 'wp-element' ), $ver( 'js/pickers.js' ), true );
		wp_localize_script( 'nashenski-pickers', 'nashenskiPickers', self::config() );

		if ( self::is_block_checkout() ) {
			wp_add_inline_style( 'nashenski-checkout', Field_Editor::block_css() );
			wp_enqueue_script( 'nashenski-pickers-block', $url . 'js/pickers-block.js', array( 'nashenski-pickers', 'wp-data', 'wp-plugins', 'wc-blocks-checkout', 'wc-blocks-data-store' ), $ver( 'js/pickers-block.js' ), true );
		} else {
			wp_enqueue_script( 'nashenski-pickers-classic', $url . 'js/pickers-classic.js', array( 'nashenski-pickers', 'jquery' ), $ver( 'js/pickers-classic.js' ), true );
		}
	}

	/**
	 * Settings and texts for the picker script.
	 *
	 * @return array
	 */
	public static function config() {
		$connected = array();

		foreach ( \Nashenski\Plugin::COURIERS as $courier ) {
			$connected[ $courier ] = self::connected( $courier );
		}

		return array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'nashenski_pickers' ),
			'rates'      => self::rate_types(),
			'connected'  => $connected,
			'townPicker' => self::town_picker(),
			'logos'      => 'no' === nashenski_get_option( 'checkout', 'courier_logos' ) ? array() : Courier_Logos::get_logos(),
			'showType'   => 'no' !== nashenski_get_option( Appearance::GROUP, 'show_type' ),
			'pending'    => self::pending_text(),
			'types'      => array(
				'office'  => __( 'To an office', 'nashenski-commerce' ),
				'automat' => __( 'To a parcel locker', 'nashenski-commerce' ),
				'locker'  => __( 'To a parcel locker', 'nashenski-commerce' ),
				'apm'     => __( 'To a parcel locker', 'nashenski-commerce' ),
				'address' => __( 'To your address', 'nashenski-commerce' ),
			),
			'i18n'       => array(
				'office'       => __( 'Office', 'nashenski-commerce' ),
				'automat'      => __( 'Parcel locker', 'nashenski-commerce' ),
				'locker'       => __( 'Parcel locker', 'nashenski-commerce' ),
				'apm'          => __( 'Parcel locker', 'nashenski-commerce' ),
				'searchOffice' => __( 'Search by town, name or address', 'nashenski-commerce' ),
				'change'       => __( 'Change', 'nashenski-commerce' ),
				'map'          => __( 'Choose on the map', 'nashenski-commerce' ),
				'mapTitle'     => __( 'Choose an office on the map', 'nashenski-commerce' ),
				'close'        => __( 'Close', 'nashenski-commerce' ),
				'loading'      => __( 'Loading...', 'nashenski-commerce' ),
				'none'         => __( 'Nothing found. Check the town in the address above.', 'nashenski-commerce' ),
				'noMatch'      => __( 'No match. Try another word.', 'nashenski-commerce' ),
				'enterCity'    => __( 'Enter your town in the address above to see the list.', 'nashenski-commerce' ),
				'mapOnly'      => __( 'Choose it on the map. The price comes from the courier\'s public price list.', 'nashenski-commerce' ),
				'notLocker'    => __( 'This is an office. Choose a parcel locker, or pick the office delivery option.', 'nashenski-commerce' ),
				'notOffice'    => __( 'This is a parcel locker. Choose an office, or pick the parcel locker delivery option.', 'nashenski-commerce' ),
				'street'       => __( 'Street or quarter', 'nashenski-commerce' ),
				'streetSearch' => __( 'Start typing the street or quarter', 'nashenski-commerce' ),
				'streetFree'   => __( 'Street and number', 'nashenski-commerce' ),
				'streetNumber' => __( 'Number', 'nashenski-commerce' ),
				'block'        => __( 'Block', 'nashenski-commerce' ),
				'entrance'     => __( 'Entrance', 'nashenski-commerce' ),
				'floor'        => __( 'Floor', 'nashenski-commerce' ),
				'apartment'    => __( 'Apartment', 'nashenski-commerce' ),
				'other'        => __( 'Note for the courier (optional)', 'nashenski-commerce' ),
				'error'        => __( 'The list could not be loaded. Please try again.', 'nashenski-commerce' ),
				'retry'        => __( 'Try again', 'nashenski-commerce' ),
				'choose'       => __( 'Choose', 'nashenski-commerce' ),
				'town'         => __( 'Town or village', 'nashenski-commerce' ),
				'townSearch'   => __( 'Start typing your town or village', 'nashenski-commerce' ),
				/* translators: %s: region name */
				'region'       => __( '%s region', 'nashenski-commerce' ),
			),
			'maps'       => array(
				'econt'  => 'https://officelocator.econt.com/',
				'speedy' => 'https://services.speedy.bg/office_locator_widget_v3/office_locator.php',
			),
		);
	}

	/**
	 * Office, locker or address text of a courier choice, as the customer saw it.
	 *
	 * @param mixed  $cookie Courier choice.
	 * @param string $type   Delivery type.
	 * @return string
	 */
	public static function describe_choice( $cookie, $type ) {
		if ( ! is_array( $cookie ) ) {
			return '';
		}

		if ( 'address' === $type ) {
			$street = $cookie['selectedAddress']['label'] ?? '';
			$parts  = array();

			if ( ! empty( $cookie['streetNumber'] ) ) {
				$street .= ' ' . $cookie['streetNumber'];
			}

			$details = array(
				'blockNumber'     => __( 'bl.', 'nashenski-commerce' ),
				'entranceNumber'  => __( 'entr.', 'nashenski-commerce' ),
				'floorNumber'     => __( 'fl.', 'nashenski-commerce' ),
				'apartmentNumber' => __( 'ap.', 'nashenski-commerce' ),
			);

			foreach ( $details as $key => $short ) {
				if ( ! empty( $cookie[ $key ] ) ) {
					$parts[] = $short . ' ' . $cookie[ $key ];
				}
			}

			return trim( implode( ', ', array_filter( array_merge( array( trim( $street ) ), $parts ) ) ) );
		}

		return (string) ( $cookie['selectedLabel'] ?? '' );
	}

	/**
	 * Put the chosen office or address into the order address when the customer did not type one
	 * (stores often hide the address fields for office delivery). With the street field hidden the
	 * choice always wins, so an address saved from an earlier order cannot end up on this one.
	 *
	 * @param int       $order_id Order ID.
	 * @param array     $posted   Posted checkout data.
	 * @param \WC_Order $order    The order.
	 */
	public static function fill_order_address( $order_id, $posted = array(), $order = null ) {
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		foreach ( $order->get_items( 'shipping' ) as $item ) {
			$courier = self::courier_of( $item->get_method_id() );

			if ( ! $courier ) {
				continue;
			}

			$type   = 'boxnow' === $courier ? 'apm' : (string) $item->get_meta( 'delivery_type' );
			$cookie = $item->get_meta( 'cookie_data' );
			$text   = self::describe_choice( $cookie, $type );

			if ( '' === $text ) {
				return;
			}

			$changed = false;
			$hidden  = 'hidden' === Field_Editor::rule( 'address_1' );

			foreach ( array( 'billing', 'shipping' ) as $kind ) {
				if ( $hidden ? $text !== $order->{"get_{$kind}_address_1"}() : '' === (string) $order->{"get_{$kind}_address_1"}() ) {
					$order->{"set_{$kind}_address_1"}( $text );
					$changed = true;
				}
			}

			// The town the picker asked for when the checkout hides the town or region field.
			foreach ( array( 'city', 'state', 'postcode' ) as $field ) {
				$value = is_array( $cookie ) ? (string) ( $cookie[ $field ] ?? '' ) : '';

				foreach ( array( 'billing', 'shipping' ) as $kind ) {
					if ( '' !== $value && $value !== $order->{"get_{$kind}_{$field}"}() && ( 'hidden' === Field_Editor::rule( $field ) || '' === $order->{"get_{$kind}_{$field}"}() ) ) {
						$order->{"set_{$kind}_{$field}"}( $value );
						$changed = true;
					}
				}
			}

			foreach ( array( 'city', 'state', 'country', 'first_name', 'last_name' ) as $field ) {
				if ( '' === (string) $order->{"get_shipping_{$field}"}() && '' !== (string) $order->{"get_billing_{$field}"}() ) {
					$order->{"set_shipping_{$field}"}( $order->{"get_billing_{$field}"}() );
					$changed = true;
				}
			}

			if ( $changed ) {
				$order->save();
			}

			return;
		}
	}
}
