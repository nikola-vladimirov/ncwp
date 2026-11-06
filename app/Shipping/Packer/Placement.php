<?php // phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the class name as the file name.
/**
 * Item placement in a carton.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Packer;

defined( 'ABSPATH' ) || exit;

/**
 * Where one item sits in a carton.
 */
class Placement {
	/**
	 * Item name.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Item size along x, y and z.
	 *
	 * @var array{0:int,1:int,2:int}
	 */
	public array $dims;

	/**
	 * Item corner along x, y and z.
	 *
	 * @var array{0:int,1:int,2:int}
	 */
	public array $origin;

	/**
	 * Shelf number.
	 *
	 * @var int
	 */
	public int $shelf_index;

	/**
	 * Row number on the shelf.
	 *
	 * @var int
	 */
	public int $row_index;

	/**
	 * Sets up the placement.
	 *
	 * @param string $name        Item name.
	 * @param array  $dims        Item size along x, y and z.
	 * @param array  $origin      Item corner along x, y and z.
	 * @param int    $shelf_index Shelf number.
	 * @param int    $row_index   Row number on the shelf.
	 */
	public function __construct( string $name, array $dims, array $origin, int $shelf_index, int $row_index ) {
		$this->name        = $name;
		$this->dims        = $dims;
		$this->origin      = $origin;
		$this->shelf_index = $shelf_index;
		$this->row_index   = $row_index;
	}
}
