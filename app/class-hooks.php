<?php
/**
 * Runs the plugin's public hooks under their underscore names.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski;

defined( 'ABSPATH' ) || exit;

/**
 * Before 0.21.0 the hooks were named with slashes (nashenski/econt/rate); now
 * nashenski_econt_rate. Callbacks on the old name still run, first, through the
 * WordPress deprecated-hook functions (WordPress 4.6 and later).
 */
class Hooks {

	/**
	 * Applies a filter under its new name, after the callbacks on its old name.
	 *
	 * @param string $hook  New hook name.
	 * @param string $old   Old slash hook name.
	 * @param mixed  $value Value to filter.
	 * @param mixed  ...$args Extra arguments, as the old filter passed them.
	 * @return mixed
	 */
	public static function filter( $hook, $old, $value, ...$args ) {
		// Old name kept for add-ons; deprecated since 0.21.0.
		$value = apply_filters_deprecated( $old, array_merge( array( $value ), $args ), '0.21.0', $hook );
		return apply_filters( $hook, $value, ...$args );
	}

	/**
	 * Fires an action under its new name, after the callbacks on its old name.
	 *
	 * @param string $hook New hook name.
	 * @param string $old  Old slash hook name.
	 * @param mixed  ...$args Arguments, as the old action passed them.
	 */
	public static function action( $hook, $old, ...$args ) {
		// Old name kept for add-ons; deprecated since 0.21.0. do_action() hands '' to callbacks when there are no arguments.
		do_action_deprecated( $old, $args ? $args : array( '' ), '0.21.0', $hook );
		do_action( $hook, ...$args );
	}
}
