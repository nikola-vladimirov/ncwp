<?php
namespace Nashenski\Admin\Tabs;
use Nashenski\Admin\Fields;

defined( 'ABSPATH' ) || exit;
class Settings_Tab extends Base_Tab {
	public function __construct() {
		$this->set_name( __( 'Settings', 'nashenski-commerce' ) );
		$this->set_tab_slug( "settings" );
	}

	public function load_fields() {
		$fields = apply_filters( 'nashenski/admin/settings/fields', array(
			'apis' => array(
				// One row for all couriers and one for all services (WooCommerce checkbox groups).
				( new Fields\TrueFalse_Field( 'enable_econt', __( 'Couriers', 'nashenski-commerce' ), null, null, __( 'Delivery to Econt offices, lockers and addresses', 'nashenski-commerce' ) ) )->set_wc( array( 'checkboxgroup' => 'start' ) ),
				( new Fields\TrueFalse_Field( 'enable_speedy', __( 'Speedy', 'nashenski-commerce' ), null, null, __( 'Delivery to Speedy offices, lockers and addresses', 'nashenski-commerce' ) ) )->set_wc( array( 'checkboxgroup' => '' ) ),
				( new Fields\TrueFalse_Field( 'enable_boxnow', __( 'BOX NOW', 'nashenski-commerce' ), null, null, __( 'Delivery to BOX NOW lockers', 'nashenski-commerce' ) ) )->set_wc( array( 'checkboxgroup' => '' ) ),
				( new Fields\TrueFalse_Field( 'enable_pigeon', __( 'Pigeon Express', 'nashenski-commerce' ), null, null, __( 'Delivery to Pigeon Express offices, lockers and addresses', 'nashenski-commerce' ) ) )->set_wc( array( 'checkboxgroup' => '' ) ),
				( new Fields\TrueFalse_Field( 'enable_cvc', __( 'CVC', 'nashenski-commerce' ), null, null, __( 'Delivery to CVC offices and addresses', 'nashenski-commerce' ) ) )->set_wc( array( 'checkboxgroup' => 'end' ) ),
				new Fields\TrueFalse_Field( 'enable_documents', __( 'Invoices and N-18', 'nashenski-commerce' ), null, null, __( 'Order documents, invoices, credit notes and the NRA audit file', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'enable_quick_order', __( 'Quick order', 'nashenski-commerce' ), null, null, __( 'A "Quick order" button on the product page and cart. The shopper sends a short form, and the order waits for your confirmation', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'enable_abandoned_carts', __( 'Abandoned carts', 'nashenski-commerce' ), null, null, __( 'Keep the carts that were not ordered and send reminders to shoppers who typed an email', 'nashenski-commerce' ) ),
			),
			'reports' => array(
				( new Fields\TrueFalse_Field( 'enable_nekorekten', __( 'Services', 'nashenski-commerce' ), null, null, __( 'Reports about the customer\'s phone number from nekorekten.com', 'nashenski-commerce' ) ) )->set_wc( array( 'checkboxgroup' => 'start' ) ),
				( new Fields\TrueFalse_Field( 'enable_connectix', __( 'Connectix', 'nashenski-commerce' ), null, null, __( 'Delivery history of the customer from connectix.bg', 'nashenski-commerce' ) ) )->set_wc( array( 'checkboxgroup' => '' ) ),
				( new Fields\TrueFalse_Field( 'enable_nepostop', __( 'NepoStop', 'nashenski-commerce' ), null, null, __( 'Share of refused parcels for the customer\'s phone number from nepostop.com', 'nashenski-commerce' ) ) )->set_wc( array( 'checkboxgroup' => 'end' ) ),
				new Fields\TrueFalse_Field( 'column', __( 'Orders list', 'nashenski-commerce' ), null, null, __( 'Add a "Reports" column to the orders list', 'nashenski-commerce' ) ),
				new Fields\TrueFalse_Field( 'check_on_checkout', __( 'Cash on delivery', 'nashenski-commerce' ), null, null, __( 'Check the phone number at checkout and hide cash on delivery when reports are found', 'nashenski-commerce' ) . '<br><small>' . __( 'nekorekten.com answers at most 10 checks a minute; Nashenski Commerce remembers answers for 10 minutes.', 'nashenski-commerce' ) . '</small>' ),
			)
		) );

		$this->set_fields( $fields );
	}

	public function get_groups_titles() {
		$titles = apply_filters( 'nashenski/admin/settings/groups_titles', array(
			'apis' => array(
				'title' => __( 'What Nashenski Commerce does in this store', 'nashenski-commerce' ),
				'desc' => __( 'Turn on the couriers you work with and the documents you need. Each one adds its own settings: couriers in the Couriers section, documents in the Documents section. Quick order and abandoned carts are in the Checkout section.', 'nashenski-commerce' ),
			),
			'reports' => array(
				'title' => __( 'Customer checks', 'nashenski-commerce' ),
				'desc' => __( 'Find out before shipping whether a customer tends to refuse parcels. Turn on a service, save, and enter its API key below.', 'nashenski-commerce' ),
			),
		) );

		return $titles;
	}

}
