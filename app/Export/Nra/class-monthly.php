<?php
/**
 * Monthly email of the NRA audit file.
 *
 * @package Nashenski
 */

namespace Nashenski\Export\Nra;

defined( 'ABSPATH' ) || exit;

/**
 * On the 1st of each month, the NRA audit file (Ordinance N-18) of the month before goes to the
 * accountant email, when "Email the audit file every month" is on.
 */
class Monthly {
	const HOOK = 'nashenski/nra/monthly';

	/**
	 * Registers the hooks.
	 */
	public function __construct() {
		add_action( 'admin_init', array( __CLASS__, 'schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'send' ) );
	}

	/**
	 * Tells whether the monthly email is on and has a recipient.
	 *
	 * @return bool
	 */
	private static function on() {
		return 'yes' === nashenski_get_option( 'invoice', 'monthly_nra_email' ) && '' !== trim( (string) nashenski_get_option( 'invoice', 'accountant_email' ) );
	}

	/**
	 * Schedules or unschedules the monthly job to match the setting.
	 */
	public static function schedule() {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		$scheduled = as_has_scheduled_action( self::HOOK, array(), 'nashenski' );

		if ( self::on() && ! $scheduled ) {
			// 07:00 UTC on the 1st (Action Scheduler reads cron times in UTC): 09:00 or 10:00 in Bulgaria.
			as_schedule_cron_action( time(), '0 7 1 * *', self::HOOK, array(), 'nashenski' );
		} elseif ( ! self::on() && $scheduled ) {
			as_unschedule_all_actions( self::HOOK, array(), 'nashenski' );
		}
	}

	/**
	 * Send the file of $month ('Y-m', the month before by default). Returns true when sent.
	 *
	 * @param string $month Month as Y-m, or empty for the month before.
	 * @return bool
	 */
	public static function send( $month = '' ) {
		if ( ! self::on() ) {
			return false;
		}

		$month  = preg_match( '/^\d{4}-\d{2}$/', (string) $month ) ? $month : wp_date( 'Y-m', strtotime( 'first day of last month' ) );
		$result = ( new Export( $month, '' ) )->get_xml_file();
		$id     = empty( $result['attachment_id'] ) ? 0 : $result['attachment_id'];
		$path   = $id ? get_attached_file( $id ) : '';

		if ( ! $path ) {
			return false;
		}

		$shop = get_bloginfo( 'name' );

		return wp_mail(
			nashenski_get_option( 'invoice', 'accountant_email' ),
			/* translators: 1: month (YYYY-MM), 2: shop name */
			sprintf( __( 'NRA audit file for %1$s - %2$s', 'nashenski-commerce' ), $month, $shop ),
			/* translators: 1: month (YYYY-MM), 2: shop name */
			sprintf( __( 'The audit file (Ordinance N-18) of %2$s for %1$s is attached. It was made by Nashenski Commerce.', 'nashenski-commerce' ), $month, $shop )
				. ( empty( $result['errors'] ) ? '' : "\n\n" . wp_strip_all_tags( implode( "\n", (array) $result['errors'] ) ) ),
			'',
			array( $path )
		);
	}
}
