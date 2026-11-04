<?php
/** Offline: a numbering failure while documents are generated is logged, not thrown, and a retry runs. php tests/documents-generate-failure.php */
namespace Nashenski\Invoice\Document {
	class NRA {
		public static $fail = true, $calls = 0, $exception = 'RuntimeException';
		public function __construct( $order ) {}
		public function generate_file() { self::$calls++; if ( self::$fail ) { $class = self::$exception; throw new $class( 'Could not lock document numbering. Please retry.' ); } }
	}
	class Proforma {
		public static $exception = 'RuntimeException';
		public function __construct( $order ) {}
		public function generate_file() { $class = self::$exception; throw new $class( 'Could not lock document numbering. Please retry.' ); }
	}
	class NRARefunded {
		public static $exception = 'RuntimeException';
		public function __construct( $refund_id ) {}
		public function generate_file() { $class = self::$exception; throw new $class( 'Could not lock document numbering. Please retry.' ); }
	}
}
namespace Nashenski\Invoice {
	class Number_Allocator {
		public static function allocate() { throw new \RuntimeException( 'Could not lock document numbering. Please retry.' ); }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	require __DIR__ . '/../vendor/autoload.php';
	class FakeOrder {
		public static $notes = array();
		public $meta_deleted = array(), $saved = 0, $parent = false, $throw_on_save = false;
		public function delete_meta_data( $key ) { $this->meta_deleted[] = $key; }
		public function save() { if ( $this->throw_on_save ) { throw new \RuntimeException( 'database is gone' ); } $this->saved++; }
		public function add_order_note( $note ) { self::$notes[] = $note; }
		public function get_refunds() { return array(); }
		public function get_id() { return 9; }
		public function get_meta( $key ) { return ''; }
		public function get_payment_method() { return 'bacs'; }
	}
	class FakeLogger { public $lines = array(); public function error( $message, $context = array() ) { $this->lines[] = array( $message, $context ); } }
	$logger = new FakeLogger();
	function __( $text, $domain = '' ) { return $text; }
	function wc_get_order( $id ) { return new FakeOrder(); }
	function wcs_order_contains_renewal( $order ) { return $order instanceof FakeOrder && empty( $order->parent ); }
	function wc_get_logger() { return $GLOBALS['logger']; }
	function nashenski_get_option( $group, $key ) {
		if ( empty( $GLOBALS['docs_on'] ) ) { return ''; }
		return array( 'apis/enable_documents' => 'yes', 'invoice/invoices' => 'always' )[ $group . '/' . $key ] ?? '';
	}
	function add_filter( $hook, $callback ) { $GLOBALS['hooks'][ $hook ][] = $callback; }
	function add_action( $hook, $callback ) { $GLOBALS['hooks'][ $hook ][] = $callback; }
	function apply_filters( $hook, $value ) { return $value; }
	$checks = 0;
	function expect( $condition, $message ) { $GLOBALS['checks']++; if ( ! $condition ) { throw new RuntimeException( $message ); } }

	\Nashenski\Admin\Order\Documents::generate_documents( 7 );
	expect( 1 === \Nashenski\Invoice\Document\NRA::$calls, 'The failing document was attempted' );
	expect( 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#7' ), 'The failure is logged with source nashenski and the order number' );
	\Nashenski\Invoice\Document\NRA::$fail = false;
	\Nashenski\Admin\Order\Documents::generate_documents( 7 );
	expect( 2 === \Nashenski\Invoice\Document\NRA::$calls && 1 === count( $logger->lines ), 'Regenerating retries the document without another error' );
	// The other hooks (checkout proforma, refund, subscription number) log a numbering failure and do not throw.
	$docs_on = true;
	$logger->lines = array();
	\Nashenski\Admin\Order\Documents::generate_proforma( 7 );
	expect( 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#7' ), 'A proforma numbering failure is logged, not thrown' );
	$logger->lines = array();
	\Nashenski\Admin\Order\Actions::create_refund_action( 7, 70 );
	expect( 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#7' ), 'A refund document numbering failure is logged, not thrown' );
	// WooCommerce deletes the refund on ANY exception thrown by this hook, so a non-runtime one is logged too.
	\Nashenski\Invoice\Document\NRARefunded::$exception = 'LogicException';
	$logger->lines = array();
	\Nashenski\Admin\Order\Actions::create_refund_action( 7, 70 );
	expect( 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#7' ), 'A non-runtime exception during refund creation is logged, not rethrown' );
	$docs_on = false; // Documents off, so generate_documents only makes the order document (stubbed).
	$logger->lines = array();
	FakeOrder::$notes = array();
	$renewal = new FakeOrder();
	\Nashenski\Admin\Order\Subscriptions::subscriptions_created( null, $renewal );
	expect( 2 === \Nashenski\Invoice\Document\NRA::$calls && 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#9' ), 'A subscription number failure is logged, not thrown, and no document is generated' );
	expect( array( 'nashenski_order_number' ) === $renewal->meta_deleted && 1 === $renewal->saved, 'The number carried over from the earlier order is removed and saved' );
	expect( 1 === count( FakeOrder::$notes ) && 'Some documents were not generated: Could not lock document numbering. Please retry.' === FakeOrder::$notes[0], 'A renewal numbering failure adds the order note with the message' );
	// The checkout hook passes the numbered parent order: it is left alone.
	$parent = new FakeOrder();
	$parent->parent = true;
	$calls = \Nashenski\Invoice\Document\NRA::$calls;
	\Nashenski\Admin\Order\Subscriptions::subscriptions_created( null, $parent );
	expect( $calls === \Nashenski\Invoice\Document\NRA::$calls && ! $parent->meta_deleted && ! $parent->saved, 'A non-renewal order is not renumbered or regenerated' );
	expect( array( 'a' => 1 ) === \Nashenski\Admin\Order\Subscriptions::drop_document_data( array( 'a' => 1, 'nashenski_order_number' => '1', 'nashenski_order_document' => 5 ) ), 'A renewal does not copy the number or the order document' );
	// L2: a non-array value passes through unchanged and does not fatal.
	foreach ( array( null, 'text', 5, false ) as $value ) {
		expect( $value === \Nashenski\Admin\Order\Subscriptions::drop_document_data( $value ), 'A non-array value is returned unchanged' );
	}
	// L3: the resubscribe filter is registered with the same callback and strips the keys.
	new \Nashenski\Admin\Order\Subscriptions();
	foreach ( array( 'wc_subscriptions_renewal_order_data', 'wc_subscriptions_subscription_data', 'wc_subscriptions_resubscribe_order_data' ) as $hook ) {
		expect( isset( $hooks[ $hook ] ) && array( 'Nashenski\Admin\Order\Subscriptions', 'drop_document_data' ) === array( $hooks[ $hook ][0][0], $hooks[ $hook ][0][1] ), "The $hook filter drops the document data" );
	}
	expect( array( 'a' => 1 ) === call_user_func( $hooks['wc_subscriptions_resubscribe_order_data'][0], array( 'a' => 1, 'nashenski_order_number' => '1', 'nashenski_order_document' => 5 ) ), 'A resubscribe order does not copy the number or the order document' );
	// L4: a save that throws inside the renewal catch is logged with source nashenski, not rethrown.
	$logger->lines = array();
	$broken = new FakeOrder();
	$broken->throw_on_save = true;
	\Nashenski\Admin\Order\Subscriptions::subscriptions_created( null, $broken );
	expect( 2 === count( $logger->lines ) && 'nashenski' === $logger->lines[1][1]['source'] && false !== strpos( $logger->lines[1][0], '#9' ) && false !== strpos( $logger->lines[1][0], 'database is gone' ), 'A throwing save after a numbering failure is logged, not rethrown' );
	expect( array( 'nashenski_order_number' ) === $broken->meta_deleted, 'The carried-over number is still removed when the save throws' );
	// A failed refund document leaves an order note too.
	FakeOrder::$notes = array();
	\Nashenski\Invoice\Document\NRARefunded::$exception = 'LogicException';
	\Nashenski\Admin\Order\Actions::create_refund_action( 7, 70 );
	expect( 1 === count( FakeOrder::$notes ), 'A failed refund document adds an order note' );
	// Regenerate PDF's failing leaves an order note, not only a log line.
	\Nashenski\Invoice\Document\NRA::$fail = true;
	FakeOrder::$notes = array();
	\Nashenski\Admin\Order\Documents::generate_documents( 7 );
	expect( 1 === count( FakeOrder::$notes ) && false !== strpos( FakeOrder::$notes[0], 'Could not lock' ), 'A failed document generation adds an order note naming the failure' );
	// The checkout proforma failure leaves the same order note.
	$docs_on = true;
	FakeOrder::$notes = array();
	\Nashenski\Admin\Order\Documents::generate_proforma( 7 );
	expect( array( 'Some documents were not generated: Could not lock document numbering. Please retry.' ) === FakeOrder::$notes, 'A failed checkout proforma adds the order note with the message' );
	// A plain Exception (a dompdf error, say) is caught and logged too, so checkout and Regenerate PDF's do not break.
	\Nashenski\Invoice\Document\Proforma::$exception = 'Exception';
	\Nashenski\Invoice\Document\NRA::$exception = 'Exception';
	\Nashenski\Invoice\Document\NRA::$fail = true;
	$logger->lines = array();
	FakeOrder::$notes = array();
	\Nashenski\Admin\Order\Documents::generate_proforma( 7 );
	expect( 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#7' ) && 1 === count( FakeOrder::$notes ), 'A plain Exception from the proforma is logged and noted, not thrown' );
	$logger->lines = array();
	FakeOrder::$notes = array();
	\Nashenski\Admin\Order\Documents::generate_documents( 7 );
	expect( 1 === count( $logger->lines ) && false !== strpos( $logger->lines[0][0], '#7' ) && 1 === count( FakeOrder::$notes ), 'A plain Exception while generating documents is logged and noted, not thrown' );
	echo 'PASS: ' . $checks . " document generation failure assertions\n";
}
