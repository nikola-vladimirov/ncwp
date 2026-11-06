<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.
/**
 * Boxnow.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Cron;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Box now.
 */
class BoxNow {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'cron_schedule' ) );
		add_action( 'nashenski/boxnow/clear_cache_folder', array( __CLASS__, 'clear_cache_folder' ) );
	}

	/**
	 * Cron schedule.
	 */
	public static function cron_schedule() {
		if ( ! wp_next_scheduled( 'nashenski/boxnow/clear_cache_folder' ) ) {
			wp_schedule_event( time(), 'weekly', 'nashenski/boxnow/clear_cache_folder' );
		}
	}

	/**
	 * Clear cache folder.
	 */
	public static function clear_cache_folder() {
		nashenski()->container()[ Client::BOXNOW ]::clear_cache_folder();
	}
}
