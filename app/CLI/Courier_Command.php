<?php
namespace Nashenski\CLI;

defined( 'ABSPATH' ) || exit;

/**
 * wp nashenski courier reload|check: the two buttons on each courier's settings tab, from the command line.
 */
class Courier_Command {

	/**
	 * Download the courier's offices, cities and profile again.
	 *
	 * Same as "Reload offices and cities" on the courier tab. The data also refreshes itself once a week.
	 *
	 * ## OPTIONS
	 *
	 * <courier>
	 * : econt, speedy, boxnow or pigeon.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nashenski courier reload econt
	 */
	public function reload( $args ) {
		$courier = self::courier( $args[0] );
		$class = nashenski()->container()[ 'client.' . $courier ];

		$class::clear_cache_folder();

		if ( method_exists( $class, 'clear_profile_data' ) ) {
			$class::clear_profile_data();
		}

		\WP_CLI::success( sprintf( '%s offices, cities and profile will be downloaded again the next time they are needed.', ucfirst( $courier ) ) );
	}

	/**
	 * Check the courier login now, the way saving its tab does, and print whether it works.
	 *
	 * ## OPTIONS
	 *
	 * <courier>
	 * : econt, speedy, boxnow or pigeon.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nashenski courier check boxnow
	 */
	public function check( $args ) {
		$courier = self::courier( $args[0] );
		$class = 'Nashenski\\Admin\\Tabs\\' . self::tab_class( $courier );
		$tab = new $class();

		do_action( 'nashenski/admin/settings/afte_save_fields/' . $class );

		$connected = method_exists( $tab, 'connected' ) && $tab->connected();

		\WP_CLI::line( sprintf( '%s login: %s', ucfirst( $courier ), $connected ? 'works' : 'does not work (see the courier tab for the reason)' ) );
	}

	private static function courier( $name ) {
		$name = strtolower( (string) $name );

		if ( ! in_array( $name, \Nashenski\Plugin::COURIERS, true ) ) {
			\WP_CLI::error( 'Use one of: ' . implode( ', ', \Nashenski\Plugin::COURIERS ) . '.' );
		}

		return $name;
	}

	private static function tab_class( $courier ) {
		$map = array(
			'econt'  => 'Econt_Tab',
			'speedy' => 'Speedy_Tab',
			'boxnow' => 'BoxNow_Tab',
			'pigeon' => 'Pigeon_Tab',
		);

		return $map[ $courier ];
	}
}
