<?php
/**
 * Carton packing result.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Packer;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- L, W and H are read by the couriers and Carton_Packer; renaming them breaks those callers.
/**
 * The carton chosen for a set of items, with where each item sits.
 */
class Pack_Result {
	/**
	 * Carton length.
	 *
	 * @var int
	 */
	public int $L;

	/**
	 * Carton width.
	 *
	 * @var int
	 */
	public int $W;

	/**
	 * Carton height.
	 *
	 * @var int
	 */
	public int $H;

	/**
	 * Where each item sits.
	 *
	 * @var Placement[]
	 */
	public array $placements;

	/**
	 * Share of the carton volume the items fill.
	 *
	 * @var float
	 */
	public float $fill_rate;

	/**
	 * Sets up the result.
	 *
	 * @param int   $length     Carton length.
	 * @param int   $width      Carton width.
	 * @param int   $height     Carton height.
	 * @param array $placements Where each item sits.
	 * @param float $fill_rate  Share of the carton volume the items fill.
	 */
	public function __construct( int $length, int $width, int $height, array $placements, float $fill_rate ) {
		$this->L          = $length;
		$this->W          = $width;
		$this->H          = $height;
		$this->placements = $placements;
		$this->fill_rate  = $fill_rate;
	}
}
