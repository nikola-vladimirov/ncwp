<?php
/**
 * Speedy cron jobs.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.
// phpcs:disable WordPress.NamingConventions.ValidHookName -- the plugin's public hooks use "nashenski/area/name".

namespace Nashenski\Cron;

use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

/**
 * Weekly clean-up of the Speedy cache folder.
 */
class Speedy {
	/**
	 * Registers the hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'cron_schedule' ) );
		add_action( 'nashenski/speedy/clear_cache_folder', array( __CLASS__, 'clear_cache_folder' ) );
	}

	/**
	 * Schedules the weekly clean-up when it is not scheduled yet.
	 */
	public static function cron_schedule() {
		if ( ! wp_next_scheduled( 'nashenski/speedy/clear_cache_folder' ) ) {
			wp_schedule_event( time(), 'weekly', 'nashenski/speedy/clear_cache_folder' );
		}
	}

	/**
	 * Clears the Speedy cache folder.
	 */
	public static function clear_cache_folder() {
		nashenski()->container()[ Client::SPEEDY ]::clear_cache_folder( true );
	}
}
