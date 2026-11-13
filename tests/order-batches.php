<?php
/**
 * Offline test: the export builder and the one-time import read orders in bounded steps.
 * Run: php tests/order-batches.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions, Generic.Files.OneObjectStructurePerFile, Generic.CodeAnalysis.UnusedFunctionParameter, Squiz.Commenting -- offline test script: it stubs WordPress in one file, keeps WordPress's own names and signatures, and prints to a terminal.

namespace Automattic\WooCommerce\Utilities {
	class OrderUtil {
		public static $hpos = true;
		public static function custom_orders_table_usage_is_enabled() {
			return self::$hpos;
		}
	}
}

namespace Nashenski\Export\Builder {
	class Fields {
		public static function courier( $order ) {
			return '';
		}
		public static function documents( $order ) {
			return array();
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );

	$GLOBALS['queries'] = array();
	$GLOBALS['loaded']  = 0;
	$GLOBALS['flushes'] = 0;
	$GLOBALS['ids']     = range( 1, 1150 );
	$GLOBALS['failed']  = 0;

	class WC_Order {
		public $id;
		public function __construct( $id ) {
			$this->id = $id;
		}
		public function get_meta( $key ) {
			return '';
		}
		public function get_status() {
			return 'completed';
		}
		public function get_payment_method() {
			return 'cod';
		}
	}
	class WC_Order_Refund extends WC_Order {}

	function wp_timezone_string() {
		return 'UTC';
	}
	function wp_cache_flush_runtime() {
		++$GLOBALS['flushes'];
	}
	function wc_get_order( $id ) {
		++$GLOBALS['loaded'];
		return 7 === $id ? false : new WC_Order( $id );
	}
	function wc_get_orders( $args ) {
		$GLOBALS['queries'][] = $args;
		return -1 === $args['limit'] ? $GLOBALS['ids'] : array_slice( $GLOBALS['ids'], ( $args['paged'] - 1 ) * $args['limit'], $args['limit'] );
	}
	function check( $ok, $label ) {
		echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . "\n";
		$GLOBALS['failed'] += $ok ? 0 : 1;
	}

	require __DIR__ . '/../app/Export/Builder/class-builder.php';
	require __DIR__ . '/../app/Admin/class-import-sources.php';

	$config = array(
		'source'       => 'orders',
		'from'         => '2026-10-01',
		'to'           => '2026-10-31',
		'date_field'   => 'created',
		'statuses'     => array(),
		'payments'     => array(),
		'couriers'     => array(),
		'company_only' => false,
		'doc_types'    => array(),
		'columns'      => array(),
	);

	// The preview of plain orders stops loading at the limit and still knows the total.
	$result = \Nashenski\Export\Builder\Builder::rows( $config, 20 );
	check( 20 === count( $result['rows'] ) && 1150 === $result['total'] && 21 === $GLOBALS['loaded'], 'the preview loads only the orders it shows and counts the rest by ID' );

	// A per-order filter needs every order: the count comes from the loop, as before.
	$GLOBALS['loaded'] = 0;
	$result            = \Nashenski\Export\Builder\Builder::rows( array( 'company_only' => true ) + $config, 20 );
	check( 0 === $result['total'] && 1150 === $GLOBALS['loaded'], 'a per-order filter still checks every order' );

	// The download (no limit) loads every order.
	$GLOBALS['loaded'] = 0;
	$result            = \Nashenski\Export\Builder\Builder::rows( $config );
	check( 1149 === count( $result['rows'] ) && 1149 === $result['total'], 'the download has every order' );

	// Documents: the query has a lower bound on the last change, next to the upper bound on the order date.
	\Nashenski\Export\Builder\Builder::rows( array( 'source' => 'documents' ) + $config );
	$query = end( $GLOBALS['queries'] );
	$from  = strtotime( '2026-10-01 00:00:00 UTC' );
	$to    = strtotime( '2026-10-31 23:59:59 UTC' );
	check( '>=' . $from === $query['date_modified'] && '<=' . $to === $query['date_created'], 'documents: orders last changed before the period are not loaded' );

	// In the posts table a document saved later does not move post_modified: no lower bound there.
	\Automattic\WooCommerce\Utilities\OrderUtil::$hpos = false;
	\Nashenski\Export\Builder\Builder::rows( array( 'source' => 'documents' ) + $config );
	$query = end( $GLOBALS['queries'] );
	check( ! isset( $query['date_modified'] ) && '<=' . $to === $query['date_created'], 'documents without HPOS: every order up to the end of the period is read' );
	\Automattic\WooCommerce\Utilities\OrderUtil::$hpos = true;

	// The import walks the orders page by page and never holds them all.
	$GLOBALS['queries'] = array();
	$GLOBALS['flushes'] = 0;
	$method             = new ReflectionMethod( \Nashenski\Admin\Import_Sources::class, 'orders_with' );
	$orders             = $method->invoke( null, '_old_key' );
	$seen               = 0;
	check( $orders instanceof Generator && ! $GLOBALS['queries'], 'the import does not query before the first order is asked for' );
	foreach ( $orders as $found ) {
		$seen += $found instanceof WC_Order ? 1 : 0;
	}
	check( 1149 === $seen && 3 === count( $GLOBALS['queries'] ) && 'ids' === $GLOBALS['queries'][0]['return'] && '_old_key' === $GLOBALS['queries'][0]['meta_key'], 'the import sees every order, in three pages of IDs' );
	check( 11 === $GLOBALS['flushes'], 'the runtime cache is flushed every 100 orders' );

	echo $GLOBALS['failed'] ? "FAILED: {$GLOBALS['failed']}\n" : "PASS: order batch assertions\n";
	exit( $GLOBALS['failed'] ? 1 : 0 );
}
