<?php
/**
 * Multi settings field.
 *
 * @package Nashenski
 */

// phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the file name to match the class name.

namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * A group of fields saved as one setting.
 */
class Multi_Field extends Base_Field {
	/**
	 * The fields of the group.
	 *
	 * @var array
	 */
	protected $fields_types;

	/**
	 * Set up the field.
	 *
	 * @param array  $fields_types     The fields of the group.
	 * @param string $name             Setting name.
	 * @param string $title            Label.
	 * @param string $help_text        Help text.
	 * @param string $validation_rules Validation rules.
	 * @param string $desc             Description.
	 */
	public function __construct( $fields_types, $name, $title, $help_text = '', $validation_rules = '', $desc = '' ) {
		$this->set_type( 'multi' );
		$this->set_name( $name );
		$this->set_title( $title );
		$this->set_help_text( $help_text );
		$this->set_desc( $desc );
		$this->set_validation_rules( $validation_rules );
		$this->set_fields_types( $fields_types );
	}

	/**
	 * The fields of the group.
	 *
	 * @return array
	 */
	public function get_fields_types() {
		return $this->fields_types;
	}

	/**
	 * Set the fields of the group.
	 *
	 * @param array $fields_types The fields of the group.
	 */
	public function set_fields_types( $fields_types ) {
		$this->fields_types = $fields_types;
	}
}
