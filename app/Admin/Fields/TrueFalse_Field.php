<?php
namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

class TrueFalse_Field extends Base_Field {
	public function __construct( $name, $title, $help_text = '', $validation_rules = '', $desc = '' ) {
		$this->set_type( 'true_false' );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
		$this->set_validation_rules( $validation_rules );
	}
}
