<?php
/**
 * Packing limit error.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the file name to match the class name.

namespace Nashenski\Shipping\Packer;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when packing would take more work than its budget allows.
 */
class Packing_Limit_Exceeded extends \RuntimeException {}
