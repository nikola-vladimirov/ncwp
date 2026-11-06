<?php
/**
 * Weekly clean-up of the Pigeon cache folder.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Cron;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Weekly clean-up of the Pigeon cache folder.
 */
class Pigeon {
	/**
	 * Hooks the schedule and the clean-up.
	 */
	public function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'cron_schedule' ) );
		add_action( 'nashenski/pigeon/clear_cache_folder', array( __CLASS__, 'clear_cache_folder' ) );
	}

	/**
	 * Schedules the weekly clean-up when it is not scheduled yet.
	 *
	 * @return void
	 */
	public static function cron_schedule() {
		if ( ! wp_next_scheduled( 'nashenski/pigeon/clear_cache_folder' ) ) {
			wp_schedule_event( time(), 'weekly', 'nashenski/pigeon/clear_cache_folder' );
		}
	}

	/**
	 * Clears the Pigeon cache folder.
	 *
	 * @return void
	 */
	public static function clear_cache_folder() {
		nashenski()->container()[ Client::PIGEON ]::clear_cache_folder( true );
	}
}
