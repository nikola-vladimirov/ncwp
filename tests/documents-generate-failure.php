<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName, Generic.Classes.DuplicateClassName, Generic.Files.OneObjectStructurePerFile.MultipleFound, Universal.Files.SeparateFunctionsFromOO.Mixed, Universal.Namespaces, Generic.CodeAnalysis.UnusedFunctionParameter -- standalone offline test: stubs of plugin and WooCommerce classes in their own namespaces live in this file and keep the signatures of what they stand in for.
/**
 * Offline: a numbering failure while documents are generated is logged, not thrown, and a retry runs. php tests/documents-generate-failure.php
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Invoice\Document {
	/**
	 * Order document stub that fails until told not to.
	 */
	class NRA {
		/**
		 * Whether generate_file() throws.
		 *
		 * @var bool
		 */
		public static $fail = true;

		/**
		 * Number of generate_file() calls.
		 *
		 * @var int
		 */
		public static $calls = 0;

		/**
		 * Exception class to throw.
		 *
		 * @var string
		 */
		public static $exception = 'RuntimeException';

		/**
		 * Stub constructor.
		 *
		 * @param mixed $order Order.
		 */
		public function __construct( $order ) {}

		/**
		 * Counts the call and throws while $fail is on.
		 *
		 * @throws \RuntimeException The configured exception.
		 */
		public function generate_file() {
			++self::$calls;
			if ( self::$fail ) {
				$class = self::$exception;
				throw new $class( 'Could not lock document numbering. Please retry.' ); } }
	}

	/**
	 * Proforma stub that always fails.
	 */
	class Proforma {
		/**
		 * Exception class to throw.
		 *
		 * @var string
		 */
		public static $exception = 'RuntimeException';

		/**
		 * Stub constructor.
		 *
		 * @param mixed $order Order.
		 */
		public function __construct( $order ) {}

		/**
		 * Throws the configured exception.
		 *
		 * @throws \RuntimeException The configured exception.
		 */
		public function generate_file() {
			$class = self::$exception;
			throw new $class( 'Could not lock document numbering. Please retry.' ); }
	}

	/**
	 * Refund document stub that always fails.
	 */
	class NRARefunded {
		/**
		 * Exception class to throw.
		 *
		 * @var string
		 */
		public static $exception = 'RuntimeException';

		/**
		 * Stub constructor.
		 *
		 * @param int $refund_id Refund ID.
		 */
		public function __construct( $refund_id ) {}

		/**
		 * Throws the configured exception.
		 *
		 * @throws \RuntimeException The configured exception.
		 */
		public function generate_file() {
			$class = self::$exception;
			throw new $class( 'Could not lock document numbering. Please retry.' ); }
	}
}

namespace Nashenski\Invoice {
	/**
	 * Numbering stub that cannot lock.
	 */
	class Number_Allocator {
		/**
		 * Always fails.
		 *
		 * @throws \RuntimeException Always.
		 */
		public static function allocate() {
			throw new \RuntimeException( 'Could not lock document numbering. Please retry.' ); }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	require_once __DIR__ . '/fixtures/hooks.php';
	require __DIR__ . '/../vendor/autoload.php';
	require_once __DIR__ . '/../app/autoload.php';

	/**
	 * Order stub.
	 */
	class FakeOrder {
		/**
		 * Order notes added to any order.
		 *
		 * @var array
		 */
		public static $notes = array();

		/**
		 * Deleted meta keys.
		 *
		 * @var array
		 */
		public $meta_deleted = array();

		/**
		 * Number of saves.
		 *
		 * @var int
		 */
		public $saved = 0;

		/**
		 * Whether this is the parent order rather than a renewal.
		 *
		 * @var bool
		 */
		public $parent = false;

		/**
		 * Whether save() throws.
		 *
		 * @var bool
		 */
		public $throw_on_save = false;

		/**
		 * Records the deleted key.
		 *
		 * @param string $key Meta key.
		 */
		public function delete_meta_data( $key ) {
			$this->meta_deleted[] = $key; }

		/**
		 * Counts the save, or throws.
		 *
		 * @throws \RuntimeException When $throw_on_save is on.
		 */
		public function save() {
			if ( $this->throw_on_save ) {
				throw new \RuntimeException( 'database is gone' );
			} ++$this->saved; }

		/**
		 * Records the note.
		 *
		 * @param string $note Note.
		 */
		public function add_order_note( $note ) {
			self::$notes[] = $note; }

		/**
		 * Refunds: none.
		 *
		 * @return array
		 */
		public function get_refunds() {
			return array(); }

		/**
		 * Order ID.
		 *
		 * @return int
		 */
		public function get_id() {
			return 9; }

		/**
		 * Meta: empty, but the customer asked for a company invoice.
		 *
		 * @param string $key Meta key.
		 * @return string
		 */
		public function get_meta( $key ) {
			return '_billing_to_company' === $key ? '1' : ''; }

		/**
		 * Payment method.
		 *
		 * @return string
		 */
		public function get_payment_method() {
			return 'bacs'; }
	}

	/**
	 * Logger stub that keeps the lines.
	 */
	class FakeLogger {
		/**
		 * Logged lines: message and context.
		 *
		 * @var array
		 */
		public $lines = array();

		/**
		 * Records an error line.
		 *
		 * @param string $message Message.
		 * @param array  $context Context.
		 */
		public function error( $message, $context = array() ) {
			$this->lines[] = array( $message, $context ); }
	}
	$logger = new FakeLogger();

	/**
	 * Translation stub.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = '' ) {
		return $text; }

	/**
	 * Order stub.
	 *
	 * @param int $id Order ID.
	 * @return FakeOrder
	 */
	function wc_get_order( $id ) {
		return new FakeOrder(); }

	/**
	 * Subscriptions stub: a FakeOrder that is not a parent is a renewal.
	 *
	 * @param mixed $order Order.
	 * @return bool
	 */
	function wcs_order_contains_renewal( $order ) {
		return $order instanceof FakeOrder && empty( $order->parent ); }

	/**
	 * Logger stub.
	 *
	 * @return FakeLogger
	 */
	function wc_get_logger() {
		return $GLOBALS['logger']; }

	/**
	 * Option stub: nothing was imported, so documents are not paused.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	function get_option( $name, $fallback = false ) {
		return 'nashenski_n18_state' === $name ? 'none' : $fallback; // No N-18 documents on this site.
	}

	/**
	 * Options stub: documents on while $docs_on is set.
	 *
	 * @param string $group Option group.
	 * @param string $key   Option name.
	 * @return string
	 */
	function nashenski_get_option( $group, $key ) {
		if ( empty( $GLOBALS['docs_on'] ) ) {
			return ''; }
		return array(
			'apis/enable_documents' => 'yes',
			'invoice/enabled'       => 'yes',
		)[ $group . '/' . $key ] ?? '';
	}

	/**
	 * Keeps the filter callback.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 */
	function add_filter( $hook, $callback ) {
		$GLOBALS['hooks'][ $hook ][] = $callback; }

	/**
	 * Keeps the action callback.
	 *
	 * @param string   $hook     Hook name.
	 * @param callable $callback Callback.
	 */
	function add_action( $hook, $callback ) {
		$GLOBALS['hooks'][ $hook ][] = $callback; }

	/**
	 * Filter stub: returns the value.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		return $value; }
	$checks = 0;

	/**
	 * Counts a check and throws when it fails.
	 *
	 * @param bool   $condition Result.
	 * @param string $message   What was checked.
	 * @throws RuntimeException When the check fails.
	 */
	function expect( $condition, $message ) {
		++$GLOBALS['checks'];
		if ( ! $condition ) {
			throw new RuntimeException( $message ); } } // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI test: the message goes to the terminal, not to a page.

	\Nashenski\Admin\Order\Documents::generate_documents( 7 );
	expect( 1 === \Nashenski\Invoice\Document\NRA::$calls, 'The failing document was attempted' );
	expect( 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#7' ), 'The failure is logged with source nashenski and the order number' );
	\Nashenski\Invoice\Document\NRA::$fail = false;
	\Nashenski\Admin\Order\Documents::generate_documents( 7 );
	expect( 2 === \Nashenski\Invoice\Document\NRA::$calls && 1 === count( $logger->lines ), 'Regenerating retries the document without another error' );
	// The other hooks (checkout proforma, refund, subscription number) log a numbering failure and do not throw.
	$docs_on       = true;
	$logger->lines = array();
	\Nashenski\Admin\Order\Documents::generate_proforma( 7 );
	expect( 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#7' ), 'A proforma numbering failure is logged, not thrown' );
	$logger->lines = array();
	\Nashenski\Admin\Order\Actions::create_refund_action( 7, 70 );
	expect( 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#7' ), 'A refund document numbering failure is logged, not thrown' );
	// WooCommerce deletes the refund on ANY exception thrown by this hook, so a non-runtime one is logged too.
	\Nashenski\Invoice\Document\NRARefunded::$exception = 'LogicException';
	$logger->lines                                      = array();
	\Nashenski\Admin\Order\Actions::create_refund_action( 7, 70 );
	expect( 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#7' ), 'A non-runtime exception during refund creation is logged, not rethrown' );
	$docs_on          = false; // Documents off, so generate_documents only makes the order document (stubbed).
	$logger->lines    = array();
	FakeOrder::$notes = array();
	$renewal          = new FakeOrder();
	\Nashenski\Admin\Order\Subscriptions::subscriptions_created( null, $renewal );
	expect( 2 === \Nashenski\Invoice\Document\NRA::$calls && 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#9' ), 'A subscription number failure is logged, not thrown, and no document is generated' );
	expect( array( 'nashenski_order_number' ) === $renewal->meta_deleted && 1 === $renewal->saved, 'The number carried over from the earlier order is removed and saved' );
	expect( 1 === count( FakeOrder::$notes ) && 'Some documents were not generated: Could not lock document numbering. Please retry.' === FakeOrder::$notes[0], 'A renewal numbering failure adds the order note with the message' );
	// The checkout hook passes the numbered parent order: it is left alone.
	$parent         = new FakeOrder();
	$parent->parent = true;
	$calls          = \Nashenski\Invoice\Document\NRA::$calls;
	\Nashenski\Admin\Order\Subscriptions::subscriptions_created( null, $parent );
	expect( \Nashenski\Invoice\Document\NRA::$calls === $calls && ! $parent->meta_deleted && ! $parent->saved, 'A non-renewal order is not renumbered or regenerated' );
	expect(
		array( 'a' => 1 ) === \Nashenski\Admin\Order\Subscriptions::drop_document_data(
			array(
				'a'                        => 1,
				'nashenski_order_number'   => '1',
				'nashenski_order_document' => 5,
			)
		),
		'A renewal does not copy the number or the order document'
	);
	// L2: a non-array value passes through unchanged and does not fatal.
	foreach ( array( null, 'text', 5, false ) as $value ) {
		expect( \Nashenski\Admin\Order\Subscriptions::drop_document_data( $value ) === $value, 'A non-array value is returned unchanged' );
	}
	// L3: the resubscribe filter is registered with the same callback and strips the keys.
	new \Nashenski\Admin\Order\Subscriptions();
	foreach ( array( 'wc_subscriptions_renewal_order_data', 'wc_subscriptions_subscription_data', 'wc_subscriptions_resubscribe_order_data' ) as $hook ) {
		expect( isset( $hooks[ $hook ] ) && array( 'Nashenski\Admin\Order\Subscriptions', 'drop_document_data' ) === array( $hooks[ $hook ][0][0], $hooks[ $hook ][0][1] ), "The $hook filter drops the document data" );
	}
	expect(
		array( 'a' => 1 ) === call_user_func(
			$hooks['wc_subscriptions_resubscribe_order_data'][0],
			array(
				'a'                        => 1,
				'nashenski_order_number'   => '1',
				'nashenski_order_document' => 5,
			)
		),
		'A resubscribe order does not copy the number or the order document'
	);
	// L4: a save that throws inside the renewal catch is logged with source nashenski, not rethrown.
	$logger->lines         = array();
	$broken                = new FakeOrder();
	$broken->throw_on_save = true;
	\Nashenski\Admin\Order\Subscriptions::subscriptions_created( null, $broken );
	expect( 2 === count( $logger->lines ) && 'nashenski' === $logger->lines[1][1]['source'] && false !== strpos( $logger->lines[1][0], '#9' ) && false !== strpos( $logger->lines[1][0], 'database is gone' ), 'A throwing save after a numbering failure is logged, not rethrown' );
	expect( array( 'nashenski_order_number' ) === $broken->meta_deleted, 'The carried-over number is still removed when the save throws' );
	// A failed refund document leaves an order note too.
	FakeOrder::$notes                                   = array();
	\Nashenski\Invoice\Document\NRARefunded::$exception = 'LogicException';
	\Nashenski\Admin\Order\Actions::create_refund_action( 7, 70 );
	expect( 1 === count( FakeOrder::$notes ), 'A failed refund document adds an order note' );
	// A PHP Error (not an Exception) from the document step must not be rethrown either, or WooCommerce deletes the refund.
	foreach ( array( 'ArgumentCountError', 'TypeError' ) as $error_class ) {
		\Nashenski\Invoice\Document\NRARefunded::$exception = $error_class;
		$logger->lines                                      = array();
		FakeOrder::$notes                                   = array();
		try {
			\Nashenski\Admin\Order\Actions::create_refund_action( 7, 70 );
			$rethrown = false;
		} catch ( \Throwable $e ) {
			$rethrown = true;
		}
		expect( ! $rethrown, "A $error_class from the refund document is not rethrown" );
		expect( 1 === count( $logger->lines ) && 1 === count( FakeOrder::$notes ) && false !== strpos( FakeOrder::$notes[0], 'Some documents were not generated' ), "A $error_class from the refund document is logged and adds the order note" );
	}
	// Regenerate PDF's failing leaves an order note, not only a log line.
	\Nashenski\Invoice\Document\NRA::$fail = true;
	FakeOrder::$notes                      = array();
	\Nashenski\Admin\Order\Documents::generate_documents( 7 );
	expect( 1 === count( FakeOrder::$notes ) && false !== strpos( FakeOrder::$notes[0], 'Could not lock' ), 'A failed document generation adds an order note naming the failure' );
	// The checkout proforma failure leaves the same order note.
	$docs_on          = true;
	FakeOrder::$notes = array();
	\Nashenski\Admin\Order\Documents::generate_proforma( 7 );
	expect( array( 'Some documents were not generated: Could not lock document numbering. Please retry.' ) === FakeOrder::$notes, 'A failed checkout proforma adds the order note with the message' );
	// A plain Exception (a dompdf error, say) is caught and logged too, so checkout and Regenerate PDF's do not break.
	\Nashenski\Invoice\Document\Proforma::$exception = 'Exception';
	\Nashenski\Invoice\Document\NRA::$exception      = 'Exception';
	\Nashenski\Invoice\Document\NRA::$fail           = true;
	$logger->lines                                   = array();
	FakeOrder::$notes                                = array();
	\Nashenski\Admin\Order\Documents::generate_proforma( 7 );
	expect( 1 === count( $logger->lines ) && 'nashenski' === $logger->lines[0][1]['source'] && false !== strpos( $logger->lines[0][0], '#7' ) && 1 === count( FakeOrder::$notes ), 'A plain Exception from the proforma is logged and noted, not thrown' );
	$logger->lines    = array();
	FakeOrder::$notes = array();
	\Nashenski\Admin\Order\Documents::generate_documents( 7 );
	expect( 1 === count( $logger->lines ) && false !== strpos( $logger->lines[0][0], '#7' ) && 1 === count( FakeOrder::$notes ), 'A plain Exception while generating documents is logged and noted, not thrown' );
	echo 'PASS: ' . (int) $checks . " document generation failure assertions\n";
}
