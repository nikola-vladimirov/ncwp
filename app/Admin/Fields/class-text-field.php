<?php
/**
 * Text settings field.
 *
 * @package Nashenski
 */

namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A text input (or another input type) on the settings screen.
 */
class Text_Field extends Base_Field {
	/**
	 * Input type: text, number, email and so on.
	 *
	 * @var string
	 */
	protected $subtype;

	/**
	 * Set up the field.
	 *
	 * @param string $name             Setting name.
	 * @param string $title            Label.
	 * @param string $help_text        Help text.
	 * @param string $validation_rules Validation rules.
	 * @param string $desc             Description.
	 * @param string $subtype          Input type.
	 */
	public function __construct( $name, $title, $help_text = '', $validation_rules = '', $desc = '', $subtype = 'text' ) {
		$this->set_type( 'text' );
		$this->set_subtype( $subtype );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
		$this->set_validation_rules( $validation_rules );
	}

	/**
	 * The input type.
	 *
	 * @return string
	 */
	public function get_subtype() {
		return $this->subtype;
	}

	/**
	 * Set the input type.
	 *
	 * @param string $subtype Input type.
	 * @return string
	 */
	public function set_subtype( $subtype ) {
		$this->subtype = sanitize_title_with_dashes( $subtype );

		return $this->subtype;
	}
}
