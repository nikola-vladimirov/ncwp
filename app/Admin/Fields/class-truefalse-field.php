<?php
/**
 * True/false settings field.
 *
 * @package Nashenski
 */

namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A yes/no setting shown as a checkbox.
 */
class TrueFalse_Field extends Base_Field {
	/**
	 * Builds the field.
	 *
	 * @param string $name             Field name.
	 * @param string $title            Field title.
	 * @param string $help_text        Help text.
	 * @param string $validation_rules Validation rules.
	 * @param string $desc             Description.
	 */
	public function __construct( $name, $title, $help_text = '', $validation_rules = '', $desc = '' ) {
		$this->set_type( 'true_false' );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
		$this->set_validation_rules( $validation_rules );
	}
}
