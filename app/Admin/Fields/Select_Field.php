<?php
/**
 * Select settings field.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.

namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A setting shown as a drop-down list.
 */
class Select_Field extends Base_Field {
	/**
	 * The choices.
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * Builds the field.
	 *
	 * @param array  $options          Choices, value => label.
	 * @param string $name             Field name.
	 * @param string $title            Field title.
	 * @param string $help_text        Help text.
	 * @param string $validation_rules Validation rules.
	 * @param string $desc             Description.
	 */
	public function __construct( array $options, $name, $title, $help_text = '', $validation_rules = '', $desc = '' ) {
		$this->set_type( 'select' );
		$this->set_options( $options );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
		$this->set_validation_rules( $validation_rules );
	}

	/**
	 * Gets the choices.
	 *
	 * @return array
	 */
	public function get_options() {
		return $this->options;
	}

	/**
	 * Sets the choices.
	 *
	 * @param array $options Choices, value => label.
	 */
	public function set_options( $options ) {
		$this->options = $options;
	}
}
