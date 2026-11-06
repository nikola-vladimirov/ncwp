<?php
/**
 * Product for the carton packer.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Packer;

defined( 'ABSPATH' ) || exit;

/**
 * A product to pack: its name and size.
 */
class Product {
	/**
	 * Product name.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * Product size.
	 *
	 * @var Size
	 */
	public Size $size;

	/**
	 * Sets up the product.
	 *
	 * @param string $name Product name.
	 * @param Size   $size Product size.
	 */
	public function __construct( string $name, Size $size ) {
		$this->name = $name;
		$this->size = $size;
	}
}
