<?php
namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

class Multi_Field extends Base_Field {
	protected $fields_types;

	public function __construct( $fields_types, $name, $title, $help_text = '', $validation_rules = '', $desc = '' ) {
		$this->set_type( 'multi' );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
		$this->set_validation_rules( $validation_rules );
		$this->set_fields_types( $fields_types );
	}

	public function get_fields_types() {
		return $this->fields_types;
	}

	public function set_fields_types( $fields_types ) {
		$this->fields_types = $fields_types;
	}
}
