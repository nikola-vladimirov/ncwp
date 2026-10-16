<?php
namespace Nashenski\Cron;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

class Econt {
	function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'cron_schedule' ) );
		add_action( 'nashenski/econt/clear_cache_folder', array( __CLASS__, 'clear_cache_folder' ) );
	}

	public static function cron_schedule() {
		if ( ! wp_next_scheduled( 'nashenski/econt/clear_cache_folder' ) ) {
			wp_schedule_event( current_time( 'timestamp' ), 'weekly', 'nashenski/econt/clear_cache_folder' );
		}
	}

	public static function clear_cache_folder() {
		nashenski()->container()[ Client::ECONT ]::clear_cache_folder( true );
	}
}
