<?php
namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A checkbox for each shipping method in the shipping zones. Saved as a JSON list of method keys
 * ("method_id:instance_id"), the same key the checkout rates have.
 */
class Methods_Field extends Base_Field {
	protected $choices;

	public function __construct( array $choices, $name, $title, $help_text = '', $validation_rules = '', $desc = '' ) {
		$this->set_type( 'methods' );
		$this->set_choices( $choices );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
		$this->set_validation_rules( $validation_rules );
	}

	public function get_choices() {
		return $this->choices;
	}

	public function set_choices( $choices ) {
		$this->choices = $choices;
	}
}
