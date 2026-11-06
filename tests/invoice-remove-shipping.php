<?php
/** Offline: "Remove delivery from documents" drops delivery on cash on delivery orders only (D03). Run: php tests/invoice-remove-shipping.php */
namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	$options = array();
	function get_option( $name ) { return $GLOBALS['options'][ $name ] ?? ''; }
	class WC_Order_Refund { public $parent; public function get_parent_id() { return 1; } }
	class FakeOrder {
		public $payment, $methods;
		public function __construct( $payment, $methods ) { $this->payment = $payment; $this->methods = $methods; }
		public function get_payment_method() { return $this->payment; }
		public function get_shipping_methods() { return $this->methods; }
	}
	function wc_get_order( $id ) { return $GLOBALS['parent']; }
	require __DIR__ . '/../app/functions.php';

	$checks = 0;
	function expect( $condition, $message ) {
		$GLOBALS['checks']++;
		if ( ! $condition ) { throw new RuntimeException( $message ); }
	}
	$econt = array( array( 'method_id' => 'nashenski_econt' ) );
	$local = array( array( 'method_id' => 'local_pickup' ) );
	$options['nashenski_settings_shippings_nashenski_econt_is_courier'] = 'yes';

	$options['nashenski_settings_invoice_remove_shipping'] = 'yes';
	expect( 'yes' === nashenski_maybe_remove_shipping( new FakeOrder( 'cod', $econt ) ), 'Setting on: a COD courier order drops delivery' );
	expect( 'yes' === nashenski_maybe_remove_shipping( new FakeOrder( 'cod', $local ) ), 'Setting on: a COD order of another method drops delivery' );
	expect( 'no' === nashenski_maybe_remove_shipping( new FakeOrder( 'bacs', $econt ) ), 'Setting on: a bank transfer order keeps delivery' );
	expect( 'no' === nashenski_maybe_remove_shipping( new FakeOrder( 'stripe', $econt ) ), 'Setting on: a card order keeps delivery' );

	$options['nashenski_settings_invoice_remove_shipping'] = 'no';
	expect( 'yes' === nashenski_maybe_remove_shipping( new FakeOrder( 'cod', $econt ) ), 'Setting off: a COD courier order still drops delivery' );
	expect( 'no' === nashenski_maybe_remove_shipping( new FakeOrder( 'cod', $local ) ), 'Setting off: a COD order of another method keeps delivery' );
	expect( 'no' === nashenski_maybe_remove_shipping( new FakeOrder( 'stripe', $econt ) ), 'Setting off: a card order keeps delivery' );

	// A credit note follows its parent order.
	$options['nashenski_settings_invoice_remove_shipping'] = 'yes';
	$parent = new FakeOrder( 'stripe', $econt );
	expect( 'no' === nashenski_maybe_remove_shipping( new WC_Order_Refund() ), 'A refund of a card order keeps delivery' );
	$parent = new FakeOrder( 'cod', $econt );
	expect( 'yes' === nashenski_maybe_remove_shipping( new WC_Order_Refund() ), 'A refund of a COD order drops delivery' );

	echo 'PASS: ' . $checks . " invoice delivery line assertions\n";
}
