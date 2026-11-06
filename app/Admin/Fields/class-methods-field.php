<?php
/**
 * Shipping methods field: a checkbox for each shipping method in the shipping zones.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A checkbox for each shipping method in the shipping zones. Saved as a JSON list of method keys
 * ("method_id:instance_id"), the same key the checkout rates have.
 */
class Methods_Field extends Base_Field {
	/**
	 * Method choices.
	 *
	 * @var array
	 */
	protected $choices;

	/**
	 * Sets up the field.
	 *
	 * @param array  $choices          Method choices.
	 * @param string $name             Field name.
	 * @param string $title            Field title.
	 * @param string $help_text        Help text.
	 * @param string $validation_rules Validation rules.
	 * @param string $desc             Description.
	 */
	public function __construct( array $choices, $name, $title, $help_text = '', $validation_rules = '', $desc = '' ) {
		$this->set_type( 'methods' );
		$this->set_choices( $choices );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
		$this->set_validation_rules( $validation_rules );
	}

	/**
	 * The method choices.
	 *
	 * @return array
	 */
	public function get_choices() {
		return $this->choices;
	}

	/**
	 * Sets the method choices.
	 *
	 * @param array $choices Method choices.
	 *
	 * @return void
	 */
	public function set_choices( $choices ) {
		$this->choices = $choices;
	}
}
