<?php
/**
 * Offline stand-ins for the WordPress deprecated-hook functions that Nashenski\Hooks calls.
 * The old name runs through the test's own apply_filters() and do_action() stubs.
 *
 * @package Nashenski_Commerce
 */

require_once dirname( __DIR__, 2 ) . '/app/class-hooks.php';

if ( ! function_exists( 'apply_filters_deprecated' ) ) {
	/**
	 * Runs the old filter name, as WordPress does.
	 *
	 * @param string $hook Old hook name.
	 * @param array  $args Value and extra arguments.
	 * @return mixed
	 */
	function apply_filters_deprecated( $hook, $args ) {
		return apply_filters( $hook, ...$args );
	}
}

if ( ! function_exists( 'do_action_deprecated' ) ) {
	/**
	 * Fires the old action name, as WordPress does.
	 *
	 * @param string $hook Old hook name.
	 * @param array  $args Arguments.
	 */
	function do_action_deprecated( $hook, $args ) {
		if ( function_exists( 'do_action' ) ) {
			do_action( $hook, ...$args );
		}
	}
}
