<?php
namespace Nashenski\Admin\Tabs;

defined( 'ABSPATH' ) || exit;

/**
 * A group of settings shown in WooCommerce > Settings > Nashenski Commerce (see Settings_Page).
 */
abstract class Base_Tab {
	protected $name;
	protected $fields = array();
	public $tab_slug;

	public function get_name() {
		return $this->name;
	}

	public function get_tab_slug() {
		return $this->tab_slug;
	}

	public function get_fields() {
		return $this->fields;
	}

	public function set_name( $name ) {
		return $this->name = esc_html( $name );
	}

	public function set_tab_slug( $slug ) {
		return $this->tab_slug = urlencode( $slug );
	}

	public function set_fields( $fields ) {
		$this->fields = $fields;
	}
}
