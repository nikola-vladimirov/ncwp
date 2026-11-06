<?php
namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

class Text_Field extends Base_Field {
	protected $subtype;

	public function __construct( $name, $title, $help_text = '', $validation_rules = '', $desc = '', $subtype = 'text' ) {
		$this->set_type( 'text' );
		$this->set_subtype( $subtype );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
		$this->set_validation_rules( $validation_rules );
	}

	// Getters
	public function get_subtype() {
		return $this->subtype;
	}

	// Setters
	public function set_subtype( $subtype ) {
		return $this->subtype = sanitize_title_with_dashes( $subtype );
	}
}
