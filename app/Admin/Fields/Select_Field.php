<?php
namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

class Select_Field extends Base_Field {
	protected $options;

	public function __construct( array $options, $name, $title, $help_text = '', $validation_rules = '', $desc = '' ) {
		$this->set_type( 'select' );
		$this->set_options( $options );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
		$this->set_validation_rules( $validation_rules );
	}

	public function get_options() {
		return $this->options;
	}

	public function set_options( $options ) {
		$this->options = $options;
	}
}
