<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloader maps the class name to the file name.
/**
 * Color picker settings field.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A hex colour (WooCommerce colour picker).
 */
class Color_Field extends Base_Field {
	/**
	 * Sets up the field.
	 *
	 * @param string $name      Option name.
	 * @param string $title     Field title.
	 * @param string $help_text Tooltip text.
	 * @param string $desc      Description under the field.
	 */
	public function __construct( $name, $title, $help_text = '', $desc = '' ) {
		$this->set_type( 'color' );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
	}
}
