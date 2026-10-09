<?php
namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A hex colour (WooCommerce colour picker).
 */
class Color_Field extends Base_Field {
	public function __construct( $name, $title, $help_text = '', $desc = '' ) {
		$this->set_type( 'color' );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
	}
}
