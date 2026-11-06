<?php // phpcs:disable WordPress.Files.FileName -- class files are named after the class and autoloaded by namespace.
namespace Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Base_Field Class.
 */
abstract class Base_Field {
	/**
	 * Field type.
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Field name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Field title.
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * Field value.
	 *
	 * @var mixed
	 */
	protected $value;

	/**
	 * Help text.
	 *
	 * @var string
	 */
	protected $help_text;

	/**
	 * Validation rules.
	 *
	 * @var mixed
	 */
	protected $validation_rules;

	/**
	 * Description.
	 *
	 * @var string
	 */
	protected $desc;

	/**
	 * The field type.
	 *
	 * @return string
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * The field name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * The field title.
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * The help text.
	 *
	 * @return string
	 */
	public function get_help_text() {
		return $this->help_text;
	}

	/**
	 * The description.
	 *
	 * @return string
	 */
	public function get_desc() {
		return $this->desc;
	}

	/**
	 * The validation rules.
	 *
	 * @return mixed
	 */
	public function get_validation_rules() {
		return $this->validation_rules;
	}

	/**
	 * Sets the field type.
	 *
	 * @param string $type Field type.
	 * @return string The type as saved.
	 */
	public function set_type( $type ) {
		$this->type = sanitize_title_with_dashes( $type );

		return $this->type;
	}

	/**
	 * Sets the field name.
	 *
	 * @param string $name Field name.
	 * @return string The name as saved.
	 */
	public function set_name( $name ) {
		$this->name = sanitize_title_with_dashes( $name );

		return $this->name;
	}

	/**
	 * Sets the field title.
	 *
	 * @param string $title Field title.
	 * @return string The title as saved.
	 */
	public function set_title( $title ) {
		$this->title = esc_html( $title );

		return $this->title;
	}

	/**
	 * Sets the help text.
	 *
	 * @param string $help_text Help text.
	 * @return string The help text as saved.
	 */
	public function set_help_text( $help_text ) {
		$this->help_text = ( $help_text ) ? wp_kses_post( $help_text ) : '';

		return $this->help_text;
	}

	/**
	 * Sets the description.
	 *
	 * @param string $desc Description.
	 * @return string The description as saved.
	 */
	public function set_desc( $desc ) {
		$this->desc = ( $desc ) ? wp_kses_post( $desc ) : '';

		return $this->desc;
	}

	/**
	 * Sets the validation rules.
	 *
	 * @param mixed $validation_rules Validation rules.
	 * @return mixed The rules as saved.
	 */
	public function set_validation_rules( $validation_rules ) {
		$this->validation_rules = $validation_rules;

		return $this->validation_rules;
	}

	/**
	 * Value WooCommerce shows and saves when the option was never saved.
	 *
	 * @var mixed
	 */
	protected $default = null;

	/**
	 * The default value.
	 *
	 * @return mixed
	 */
	public function get_default() {
		return $this->default;
	}

	/**
	 * Sets the default value.
	 *
	 * @param mixed $default_value Default value.
	 * @return $this
	 */
	public function set_default( $default_value ) {
		$this->default = $default_value;

		return $this;
	}

	/**
	 * Extra keys for the WooCommerce setting, for example checkboxgroup.
	 *
	 * @var array
	 */
	protected $wc = array();

	/**
	 * The extra WooCommerce keys.
	 *
	 * @return array
	 */
	public function get_wc() {
		return $this->wc;
	}

	/**
	 * Sets the extra WooCommerce keys.
	 *
	 * @param array $wc Extra keys.
	 * @return $this
	 */
	public function set_wc( $wc ) {
		$this->wc = $wc;

		return $this;
	}
}
