<?php
namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * An image from the media library. Stores the attachment ID.
 */
class Image_Field extends Base_Field {
	public function __construct( $name, $title, $help_text = '', $desc = '' ) {
		$this->set_type( 'image' );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
	}
}
