<?php
/**
 * Base class of the report providers.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.

namespace Nashenski\Reports\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Shared contract of the courier report providers.
 */
abstract class Provider_Base {
	/**
	 * Provider name, already escaped.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Gets all reports of an order, optionally refreshing them.
	 *
	 * @param \WC_Order $order Order.
	 * @param bool      $force Refresh from the courier.
	 * @return mixed
	 */
	abstract public static function get_all_reports( $order, $force = false );

	/**
	 * Gets the reports already saved in the order meta.
	 *
	 * @param \WC_Order $order Order.
	 * @return mixed
	 */
	abstract public static function get_all_reports_from_meta( $order );

	/**
	 * Prints the reports of an order.
	 *
	 * @param \WC_Order $order Order.
	 */
	abstract public function print_reports_html( $order );

	/**
	 * Gets the provider name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Sets the provider name.
	 *
	 * @param string $name Name.
	 * @return string The escaped name.
	 */
	public function set_name( $name ) {
		$this->name = esc_html( $name );
		return $this->name;
	}
}
