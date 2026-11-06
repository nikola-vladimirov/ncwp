<?php
/**
 * Image settings field.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * An image from the media library. Stores the attachment ID.
 */
class Image_Field extends Base_Field {
	/**
	 * Sets up the field.
	 *
	 * @param string $name      Option name.
	 * @param string $title     Field title.
	 * @param string $help_text Help text.
	 * @param string $desc      Description.
	 */
	public function __construct( $name, $title, $help_text = '', $desc = '' ) {
		$this->set_type( 'image' );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
	}
}
