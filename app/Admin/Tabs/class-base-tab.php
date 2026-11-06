<?php
/**
 * Base tab.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin\Tabs;

defined( 'ABSPATH' ) || exit;

/**
 * A group of settings shown in WooCommerce > Settings > Nashenski Commerce (see Settings_Page).
 */
abstract class Base_Tab {
	/**
	 * Name.
	 *
	 * @var mixed
	 */
	protected $name;
	/**
	 * Fields.
	 *
	 * @var array
	 */
	protected $fields = array();
	/**
	 * Tab slug.
	 *
	 * @var mixed
	 */
	public $tab_slug;

	/**
	 * Get name.
	 *
	 * @return mixed
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get tab slug.
	 *
	 * @return mixed
	 */
	public function get_tab_slug() {
		return $this->tab_slug;
	}

	/**
	 * Get fields.
	 *
	 * @return mixed
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Set name.
	 *
	 * @param mixed $name Name.
	 * @return mixed
	 */
	public function set_name( $name ) {
		$this->name = esc_html( $name );

		return $this->name;
	}

	/**
	 * Set tab slug.
	 *
	 * @param mixed $slug Slug.
	 * @return mixed
	 */
	public function set_tab_slug( $slug ) {
		$this->tab_slug = rawurlencode( $slug );

		return $this->tab_slug;
	}

	/**
	 * Set fields.
	 *
	 * @param mixed $fields Fields.
	 */
	public function set_fields( $fields ) {
		$this->fields = $fields;
	}
}
