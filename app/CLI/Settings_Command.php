<?php
namespace Nashenski\CLI;

use Nashenski\Admin\Settings_Page;
use Nashenski\Pricing\Price_Lists;

defined( 'ABSPATH' ) || exit;

/**
 * wp nashenski setting list|get|set|reset: every setting the admin shows, by its option name.
 * The short name drops the prefix, so `apis_enable_econt` is `nashenski_settings_apis_enable_econt`.
 */
class Settings_Command {

	const PREFIX = 'nashenski_settings_';

	/** Tab classes whose fields the settings page can show, enabled or not. */
	const TABS = array(
		'Settings_Tab',
		'Nra_Tab',
		'Shipping_Tab',
		'Fields_Tab',
		'Econt_Tab',
		'Speedy_Tab',
		'BoxNow_Tab',
		'Pigeon_Tab',
		'CVC_Tab',
		'Connectix_Tab',
		'NepoStop_Tab',
		'Nekorekten_Tab',
		'Quick_Order_Tab',
		'Abandoned_Carts_Tab',
	);

	/** Setting types that are headings, not values. */
	const NOT_VALUES = array( 'title', 'sectionend', 'nashenski_box', 'info' );

	/** Types whose value is a JSON document, sanitized by the same filter the admin uses. */
	const JSON_TYPES = array( 'nashenski_price_list', 'nashenski_rows', 'nashenski_methods', 'nashenski_field_layout' );

	/**
	 * List the settings, with their current value.
	 *
	 * ## OPTIONS
	 *
	 * [--tab=<tab>]
	 * : Only one settings tab, for example econt, speedy, boxnow, pigeon, cvc, checkout, documents, checks.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp nashenski setting list --tab=econt
	 *     wp nashenski setting list --format=json
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc ) {
		$rows = array();

		foreach ( self::all() as $setting ) {
			if ( ! empty( $assoc['tab'] ) && $assoc['tab'] !== $setting['tab'] ) {
				continue;
			}

			$value = get_option( $setting['id'] );

			$rows[] = array(
				'name'    => substr( $setting['id'], strlen( self::PREFIX ) ),
				'tab'     => $setting['tab'],
				'type'    => $setting['type'],
				'value'   => self::is_secret( $setting ) ? ( '' === (string) $value ? '(empty)' : '(set)' ) : self::show( $value ),
				'default' => self::show( $setting['default'] ?? '' ),
			);
		}

		if ( ! $rows ) {
			\WP_CLI::error( 'No setting matches --tab=' . ( $assoc['tab'] ?? '' ) . '.' );
		}

		\WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, array( 'name', 'tab', 'type', 'value', 'default' ) );
	}

	/**
	 * Print one setting's value.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Setting name, short (apis_enable_econt) or full (nashenski_settings_apis_enable_econt).
	 *
	 * ## EXAMPLES
	 *
	 *     wp nashenski setting get apis_enable_econt
	 */
	public function get( $args ) {
		$setting = self::find( $args[0] );
		$value = get_option( $setting['id'] );

		\WP_CLI::line( is_scalar( $value ) || null === $value ? (string) $value : wp_json_encode( $value ) );
	}

	/**
	 * Save one setting, the way the settings page does.
	 *
	 * Plain values are checked against the type the admin uses (yes or no for a checkbox, one of the
	 * listed choices for a select, a valid email, a number). Table settings (price lists, rows, methods,
	 * field layout) take JSON. Pass `-` as the value to read it from standard input, so a key or password
	 * does not end up in the shell history.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Setting name, short or full (see `setting list`).
	 *
	 * <value>
	 * : The new value. Use `-` to read it from standard input.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nashenski setting set apis_enable_econt yes
	 *     wp nashenski setting set econt_user iasp-dev
	 *     echo "$ECONT_PASSWORD" | wp nashenski setting set econt_password -
	 *     wp nashenski setting set prices_econt '[{"from":"0","to":"5","price":"4.50"}]'
	 */
	public function set( $args ) {
		$setting = self::find( $args[0] );
		$raw = $args[1] ?? '';

		if ( '-' === $raw ) {
			$raw = rtrim( (string) stream_get_contents( STDIN ), "\r\n" );
		}

		$value = self::parse( $setting, $raw );

		if ( 0 === strpos( $setting['type'], 'nashenski_' ) ) {
			// The same sanitizers the settings page runs on save (price lists, rows, methods, layout, style, image).
			$value = \Nashenski\Admin\Settings_Page::sanitize_value( $value, $setting );
		}

		update_option( $setting['id'], $value );
		self::after_save( $setting );

		\WP_CLI::success( sprintf( '%s saved (%s).', substr( $setting['id'], strlen( self::PREFIX ) ), self::is_secret( $setting ) ? 'hidden' : self::show( $value ) ) );
	}

	/**
	 * Put one setting back to its default.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Setting name, short or full.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nashenski setting reset apis_enable_econt
	 */
	public function reset( $args ) {
		$setting = self::find( $args[0] );
		$default = $setting['default'] ?? null;

		if ( null === $default ) {
			delete_option( $setting['id'] );
		} else {
			update_option( $setting['id'], $default );
		}

		self::after_save( $setting );

		\WP_CLI::success( sprintf( '%s reset to its default.', substr( $setting['id'], strlen( self::PREFIX ) ) ) );
	}

	/**
	 * Every setting, keyed by option name, as the WooCommerce setting arrays the settings page builds.
	 */
	private static function all() {
		static $all = null;

		if ( null !== $all ) {
			return $all;
		}

		if ( ! class_exists( 'WC_Settings_Page', false ) ) {
			require_once WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php';
		}

		$all = array();

		foreach ( self::TABS as $tab_class ) {
			$class = 'Nashenski\\Admin\\Tabs\\' . $tab_class;
			$tab = new $class();
			$tab->load_fields();

			foreach ( (array) $tab->get_fields() as $group => $fields ) {
				foreach ( $fields as $field ) {
					self::add( $all, Settings_Page::to_wc_setting( $group, $field ), $tab->get_tab_slug(), $group, $class );
				}
			}
		}

		// Settings the checkout, automation and withdrawal pages add themselves.
		foreach ( \Nashenski\Shipping\Automation::settings() as $setting ) {
			self::add( $all, $setting, 'automation', '' );
		}
		foreach ( \Nashenski\Front_End\Checkout\Field_Editor::settings() as $setting ) {
			self::add( $all, $setting, 'checkout', 'fields' );
		}
		foreach ( \Nashenski\Front_End\Checkout\Appearance::settings() as $setting ) {
			self::add( $all, $setting, 'checkout', 'appearance' );
		}
		foreach ( \Nashenski\Front_End\Withdrawal::settings() as $setting ) {
			self::add( $all, $setting, 'checkout', 'withdrawal' );
		}

		// Public price lists: one switch and one table per courier that has a list.
		foreach ( Price_Lists::COURIERS as $courier ) {
			self::add( $all, array( 'id' => 'nashenski_settings_prices_use_public_' . $courier, 'type' => 'checkbox', 'default' => 'yes', 'title' => 'Use public prices, ' . $courier ), 'prices', $courier );
			self::add( $all, array( 'id' => Price_Lists::option_name( $courier ), 'type' => 'nashenski_price_list', 'courier' => $courier, 'default' => null, 'title' => 'Public prices, ' . $courier ), 'prices', $courier );
		}

		ksort( $all );

		return $all;
	}

	private static function add( array &$all, $setting, $tab, $group, $tab_class = null ) {
		if ( empty( $setting['id'] ) || in_array( $setting['type'] ?? '', self::NOT_VALUES, true ) || isset( $all[ $setting['id'] ] ) ) {
			return;
		}

		$setting['tab'] = $tab;
		$setting['group'] = $group;
		$setting['tab_class'] = $tab_class;
		$all[ $setting['id'] ] = $setting;
	}

	private static function find( $name ) {
		$all = self::all();

		foreach ( array( $name, self::PREFIX . $name ) as $id ) {
			if ( isset( $all[ $id ] ) ) {
				return $all[ $id ];
			}
		}

		\WP_CLI::error( sprintf( 'Unknown setting "%s". See: wp nashenski setting list', $name ) );
	}

	/**
	 * The stored value for a setting, from the text the command line gave.
	 */
	private static function parse( $setting, $raw ) {
		$raw = (string) $raw;

		if ( in_array( $setting['type'], self::JSON_TYPES, true ) ) {
			$data = json_decode( $raw, true );

			if ( null === $data && 'null' !== trim( $raw ) ) {
				\WP_CLI::error( 'This setting takes JSON. Check the quotes and brackets.' );
			}

			return (array) $data;
		}

		switch ( $setting['type'] ) {
			case 'checkbox':
				$word = strtolower( trim( $raw ) );

				if ( in_array( $word, array( 'yes', 'true', '1', 'on' ), true ) ) {
					return 'yes';
				}
				if ( in_array( $word, array( 'no', 'false', '0', 'off' ), true ) ) {
					return 'no';
				}

				\WP_CLI::error( 'Use yes or no.' );

			case 'select':
				$keys = array_map( 'strval', array_keys( (array) ( $setting['options'] ?? array() ) ) );

				if ( $keys && ! in_array( $raw, $keys, true ) ) {
					\WP_CLI::error( 'Use one of: ' . implode( ', ', $keys ) . '.' );
				}

				return $raw;

			case 'number':
				if ( ! is_numeric( $raw ) ) {
					\WP_CLI::error( 'Use a number.' );
				}

				return $raw;

			case 'email':
				if ( '' !== $raw && ! is_email( $raw ) ) {
					\WP_CLI::error( 'Use a valid email address.' );
				}

				return sanitize_email( $raw );

			case 'color':
				$color = sanitize_hex_color( $raw );

				if ( '' !== $raw && null === $color ) {
					\WP_CLI::error( 'Use a hex colour, for example #1d4ed8.' );
				}

				return (string) $color;

			case 'nashenski_image':
				return absint( $raw );

			case 'nashenski_style':
				return $raw;

			default:
				return sanitize_text_field( $raw );
		}
	}

	private static function is_secret( $setting ) {
		return 'password' === $setting['type'] || ( 'text' === $setting['type'] && (bool) preg_match( '/key|token|secret|password/i', $setting['id'] ) );
	}

	private static function show( $value ) {
		if ( is_array( $value ) ) {
			return wp_json_encode( $value );
		}

		return null === $value || false === $value ? '' : (string) $value;
	}

	/**
	 * What saving the settings page does after the values change: rebuild the courier clients and let the
	 * tab check its login or fill its profile data.
	 */
	private static function after_save( $setting ) {
		$container = nashenski()->container();

		foreach ( array_keys( \Nashenski\Container\Client::SERVICES ) as $service ) {
			unset( $container[ $service ] );
		}

		$container->register( new \Nashenski\Container\Client() );

		if ( ! empty( $setting['tab_class'] ) ) {
			do_action( 'nashenski/admin/settings/afte_save_fields/' . $setting['tab_class'] );
		}
	}
}
