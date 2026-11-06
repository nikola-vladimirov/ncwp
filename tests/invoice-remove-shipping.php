<?php // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, Universal.Namespaces, WordPress.Security.EscapeOutput -- offline CLI test script: no WordPress, so stubs, unused stub parameters, filesystem calls and plain-text output are on purpose.
/** Offline: "Remove delivery from documents" drops delivery on cash on delivery orders only (D03). Run: php tests/invoice-remove-shipping.php */
namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	$options = array();
	/**
	 * Option stub.
	 *
	 * @param string $name Option name.
	 *
	 * @return mixed
	 */
	function get_option( $name ) {
		return $GLOBALS['options'][ $name ] ?? ''; }
	/**
	 * Refund stub: only the parent ID is read.
	 */
	class WC_Order_Refund {
		/**
		 * Parent order.
		 *
		 * @var object
		 */
		public $parent;
		/**
		 * Parent order ID.
		 *
		 * @return int
		 */
		public function get_parent_id() {
			return 1; }
	}
	/**
	 * Order stub with a payment method and shipping methods.
	 */
	class FakeOrder {
		/**
		 * Payment method.
		 *
		 * @var string
		 */
		public $payment;
		/**
		 * Shipping methods.
		 *
		 * @var array
		 */
		public $methods;
		/**
		 * Constructor.
		 *
		 * @param string $payment Payment method.
		 * @param array  $methods Shipping methods.
		 */
		public function __construct( $payment, $methods ) {
			$this->payment = $payment;
			$this->methods = $methods; }
		/**
		 * Payment method.
		 *
		 * @return string
		 */
		public function get_payment_method() {
			return $this->payment; }
		/**
		 * Shipping methods.
		 *
		 * @return array
		 */
		public function get_shipping_methods() {
			return $this->methods; }
	}
	/**
	 * Order lookup stub: always the parent order.
	 *
	 * @param int $id Order ID.
	 *
	 * @return object
	 */
	function wc_get_order( $id ) {
		return $GLOBALS['parent']; }
	require __DIR__ . '/../app/functions.php';

	$checks = 0;
	/**
	 * Assertion: throws when the condition fails.
	 *
	 * @param bool   $condition Condition.
	 * @param string $message   Failure message.
	 *
	 * @throws RuntimeException When the condition is false.
	 */
	function expect( $condition, $message ) {
		++$GLOBALS['checks'];
		if ( ! $condition ) {
			throw new RuntimeException( $message ); }
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
