<?php
/** Offline: a bank transfer invoice waits for the payment; other methods unchanged. php tests/documents-bacs-invoice.php */
namespace Nashenski\Invoice\Document {
	class Log { public static $made = array(); }
	class NRA { public function __construct( $o ) {} public function generate_file() { Log::$made[] = 'nra'; } }
	class Proforma { public function __construct( $o ) {} public function generate_file() { Log::$made[] = 'proforma'; } }
	class Invoice { public function __construct( $o ) {} public function generate_file() { Log::$made[] = 'invoice'; } }
	class NRARefunded { public function __construct( $o ) {} public function generate_file() { Log::$made[] = 'nra_refund'; } }
	class CreditNotice { public function __construct( $o ) {} public function generate_file() { Log::$made[] = 'credit'; } }
}
namespace Automattic\WooCommerce\Utilities {
	class OrderUtil { public static function custom_orders_table_usage_is_enabled() { return false; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	require __DIR__ . '/../vendor/autoload.php';
	class FakeOrder {
		public $method, $paid, $has_invoice = false, $has_number = true;
		public function __construct( $method, $paid ) { $this->method = $method; $this->paid = $paid; }
		public function get_payment_method() { return $this->method; }
		public function is_paid() { return $this->paid; }
		public function get_refunds() { return array(); }
		public function get_meta( $key ) { return "nashenski_invoice_document" === $key ? $this->has_invoice : ( "nashenski_order_number" === $key ? $this->has_number : "" ); }
	}
	$order = null;
	function wc_get_order( $id ) { return $GLOBALS['order']; }
	$trigger = '';
	$hooks = array();
	function nashenski_get_option( $group, $key ) { return array( 'apis/enable_documents' => 'yes', 'invoice/invoices' => 'always', 'invoice/trigger' => $GLOBALS['trigger'] )[ $group . '/' . $key ] ?? ''; }
	function add_action( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
	function add_filter( $hook, $cb, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
	function apply_filters( $hook, $value ) { return $value; }
	$checks = 0;
	function expect( $c, $m ) { $GLOBALS['checks']++; if ( ! $c ) { throw new RuntimeException( $m ); } }
	function made() { $m = \Nashenski\Invoice\Document\Log::$made; \Nashenski\Invoice\Document\Log::$made = array(); return $m; }

	$order = new FakeOrder( 'bacs', false );
	\Nashenski\Admin\Order\Documents::generate_documents( 1 );
	expect( array( 'nra', 'proforma' ) === made(), 'Unpaid bacs: order document and proforma, no invoice' );
	\Nashenski\Admin\Order\Documents::generate_invoice_when_paid( 1 );
	expect( array() === made(), 'Unpaid bacs: still no invoice' );
	$order->paid = true;
	\Nashenski\Admin\Order\Documents::generate_invoice_when_paid( 1 );
	expect( array( 'invoice' ) === made(), 'Paid bacs: invoice made' );
	$order->has_invoice = true;
	\Nashenski\Admin\Order\Documents::generate_invoice_when_paid( 1 );
	expect( array() === made(), 'An existing invoice is not made twice' );

	$order = new FakeOrder( 'cod', false );
	\Nashenski\Admin\Order\Documents::generate_documents( 2 );
	expect( array( 'nra', 'invoice' ) === made(), 'Non-bacs: invoice at checkout as before' );

	// The paid hook makes nothing for card or cod orders, or a bacs order without a checkout order document (renewal).
	foreach ( array( 'stripe', 'cod' ) as $method ) {
		$order = new FakeOrder( $method, true );
		\Nashenski\Admin\Order\Documents::generate_invoice_when_paid( 3 );
		expect( array() === made(), "Paid $method order: the paid hook makes no invoice" );
	}
	$order = new FakeOrder( 'bacs', true );
	$order->has_number = false;
	\Nashenski\Admin\Order\Documents::generate_invoice_when_paid( 3 );
	expect( array() === made(), 'Paid bacs order without an order number (renewal): no invoice' );

	// The order document was deleted (retention) but the number stays: the paid bacs order is still invoiced. The fake order has no order document meta.
	$order = new FakeOrder( 'bacs', true );
	\Nashenski\Admin\Order\Documents::generate_invoice_when_paid( 3 );
	expect( array( 'invoice' ) === made(), 'Paid bacs order with its order document deleted but the number kept: invoice made' );
	// A non-bacs refund still gets its credit notice without the invoice meta check.
	$order = new FakeOrder( 'stripe', true );
	\Nashenski\Admin\Order\Documents::generate_refunded_documents( 5, 1 );
	expect( array( 'nra_refund', 'credit' ) === made(), 'Non-bacs refund: credit notice unchanged' );

	// The paid hooks exist for the default trigger only.
	new \Nashenski\Admin\Order\Actions();
	expect( ! empty( $hooks['woocommerce_payment_complete'] ), 'Default trigger registers the paid hook' );
	$hooks = array();
	$trigger = 'order_completed';
	new \Nashenski\Admin\Order\Actions();
	expect( empty( $hooks['woocommerce_payment_complete'] ) && empty( $hooks['woocommerce_order_status_processing'] ), 'Completed trigger does not register the paid hooks' );

	// A refund of an unpaid bacs order makes no credit notice; a paid and invoiced one does.
	$order = new FakeOrder( 'bacs', false );
	\Nashenski\Admin\Order\Documents::generate_refunded_documents( 5, 1 );
	expect( array( 'nra_refund' ) === made(), 'Unpaid bacs refund: no credit notice' );
	$order->has_invoice = true;
	\Nashenski\Admin\Order\Documents::generate_refunded_documents( 5, 1 );
	expect( array( 'nra_refund', 'credit' ) === made(), 'Invoiced order refund: credit notice made' );

	echo 'PASS: ' . $checks . " bacs invoice assertions\n";
}
