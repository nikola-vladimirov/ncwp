<?php
/**
 * Packing limit error.
 *
 * @package Nashenski
 */

namespace Nashenski\Shipping\Packer;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown when packing would take more work than its budget allows.
 */
class Packing_Limit_Exceeded extends \RuntimeException {}
