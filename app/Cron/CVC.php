<?php
namespace Nashenski\Cron;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;

class CVC {
	function __construct() {
		add_action( 'wp', array( __CLASS__, 'cron_schedule' )  );
		add_action( 'nashenski/cvc/clear_cache_folder', array( __CLASS__, 'clear_cache_folder' ) );
	}

	public static function cron_schedule() {
		if ( ! wp_next_scheduled( 'nashenski/cvc/clear_cache_folder' ) ) {
			wp_schedule_event( current_time( 'timestamp' ), 'weekly', 'nashenski/cvc/clear_cache_folder' );
		}
	}

	public static function clear_cache_folder() {
		nashenski()->container()[ Client::CVC ]::clear_cache_folder();
	}
}
