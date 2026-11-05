<?php
/** Offline: a bank transfer invoice waits for the payment; other methods unchanged. php tests/documents-bacs-invoice.php */
namespace Nashenski\Invoice\Document {
	class Log { public static $made = array(); }
	class NRA { public function __construct( $o ) {} public function generate_file() { Log::$made[] = 'nra'; } }
	class Proforma { public function __construct( $o ) {} public function generate_file() { Log::$made[] = 'proforma'; } }
	class Invoice { public function __construct( $o ) {} public function generate_file() { Log::$made[] = 'invoice'; } }
}
namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	require __DIR__ . '/../vendor/autoload.php';
	class FakeOrder {
		public $method, $paid, $has_invoice = false;
		public function __construct( $method, $paid ) { $this->method = $method; $this->paid = $paid; }
		public function get_payment_method() { return $this->method; }
		public function is_paid() { return $this->paid; }
		public function get_refunds() { return array(); }
		public function get_meta( $key ) { return 'nashenski_invoice_document' === $key ? $this->has_invoice : ''; }
	}
	$order = null;
	function wc_get_order( $id ) { return $GLOBALS['order']; }
	function nashenski_get_option( $group, $key ) { return array( 'apis/enable_documents' => 'yes', 'invoice/invoices' => 'always' )[ $group . '/' . $key ] ?? ''; }
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
	echo 'PASS: ' . $checks . " bacs invoice assertions\n";
}
