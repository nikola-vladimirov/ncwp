<?php // phpcs:disable WordPress.Files.FileName -- class files are named after the class and autoloaded by namespace.
namespace Nashenski\Admin\Tabs;

use Nashenski\Admin\Fields;
use Nashenski\Container\Client;

defined( 'ABSPATH' ) || exit;
/**
 * NepoStop section: the API key, the level of warning and the payment methods to hide.
 */
class NepoStop_Tab extends Base_Tab {
	/**
	 * Name of this tab class.
	 *
	 * @var string
	 */
	protected $tab_name;
	/**
	 * Dependency container.
	 *
	 * @var object
	 */
	protected $container;

	/**
	 * Names the section.
	 */
	public function __construct() {
		$this->container = nashenski()->container();
		$this->tab_name  = get_called_class();
		$this->set_name( __( 'NepoStop', 'nashenski-commerce' ) );
		$this->set_tab_slug( 'nepostop' );
	}

	/**
	 * Builds the fields of the section.
	 *
	 * @return void
	 */
	public function load_fields() {
		$fields = apply_filters(
			'nashenski/admin/settings/nepostop/fields', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-separated hook names are the plugin's public API.
			array(
				'nepostop' => array(
					new Fields\Text_Field( 'api_key', __( 'API Key', 'nashenski-commerce' ), null, 'required', __( 'Enter the provided API key from nepostop.com.', 'nashenski-commerce' ) ),
					new Fields\Text_Field( 'level_of_warning', __( 'Level of Warning', 'nashenski-commerce' ), __( 'In percentages', 'nashenski-commerce' ), 'required', __( 'Enter how many percentages will trigger a warning or disable COD.', 'nashenski-commerce' ), 'number' ),
					new Fields\Methods_Field( \Nashenski\Reports\Reports::payment_method_choices(), 'hide_payments', __( 'Hide these payment methods', 'nashenski-commerce' ), __( 'The checked payment methods are not offered to a customer that this service flags at the level of warning above.', 'nashenski-commerce' ) ),
					new Fields\TrueFalse_Field( 'upload_labels', __( 'Automatically upload labels to nepostop.com', 'nashenski-commerce' ), null, null, __( 'By enabling this option, the plugin will send Speedy and Econt tracking numbers to nepostop.com and you can track them from their platform. This way, you will help the service work better.', 'nashenski-commerce' ) ),
				),
			)
		);

		$this->set_fields( $fields );
	}

	/**
	 * The title of the section.
	 *
	 * @return array
	 */
	public function get_groups_titles() {
		$titles = apply_filters(
			'nashenski/admin/settings/nepostop/groups_titles', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- slash-separated hook names are the plugin's public API.
			array(
				'nepostop' => array(
					'title' => __( 'NepoStop', 'nashenski-commerce' ),
				),
			)
		);

		return $titles;
	}

	/**
	 * Checks the API key against NepoStop.
	 *
	 * @return string Error message as HTML, empty when the key works.
	 */
	public function auth_test() {
		$error = '';

		if ( ! $this->container[ Client::NEPOSTOP ]->validate_access() ) {
			$error = __( 'API Key is incorrect.', 'nashenski-commerce' );
		}

		return wpautop( $error );
	}
}
