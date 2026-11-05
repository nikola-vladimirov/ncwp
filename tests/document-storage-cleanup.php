<?php
/** Offline: delete_receipts() never sends meta_query on legacy storage (the filter adds it), keeps it on HPOS, keeps the date filter. Run: php tests/document-storage-cleanup.php */
namespace Automattic\WooCommerce\Utilities {
	class OrderUtil {
		public static function custom_orders_table_usage_is_enabled() { return $GLOBALS['hpos']; }
	}
}

namespace {
define( 'ABSPATH', __DIR__ . '/fixtures/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['hpos'] = false;
$GLOBALS['queries'] = array();
$GLOBALS['wp_args'] = array(); // what the datastore filter produced per query
$GLOBALS['filters'] = array();
$GLOBALS['queue'] = array(); // ids returned by successive wc_get_orders calls
$GLOBALS['orders'] = array();
$GLOBALS['enqueued'] = 0;
$GLOBALS['deleted'] = array();

function get_option( $k ) { return 30; }
function add_filter( $tag, $cb ) { $GLOBALS['filters'][ $tag ][] = $cb; }
function remove_filter( $tag, $cb ) { $GLOBALS['filters'][ $tag ] = array_values( array_filter( $GLOBALS['filters'][ $tag ] ?? array(), function ( $f ) use ( $cb ) { return $f !== $cb; } ) ); }
function wc_get_orders( $a ) {
	$GLOBALS['queries'][] = $a;
	if ( $GLOBALS['throw'] ?? false ) { throw new RuntimeException( 'query failed' ); }
	$wp = array();
	foreach ( $GLOBALS['filters']['woocommerce_order_data_store_cpt_get_orders_query'] ?? array() as $cb ) { $wp = $cb( $wp, $a, null ); }
	$GLOBALS['wp_args'][] = $wp;
	return array_shift( $GLOBALS['queue'] ) ?? array();
}
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ] ?? new FakeOrder( array( 'nashenski_order_document' => 0 ) ); }
function get_post_type( $id ) { return 'attachment'; }
function wp_delete_attachment( $id ) { $GLOBALS['deleted'][] = $id; }
function as_enqueue_async_action() { $GLOBALS['enqueued']++; }

class FakeOrder {
	public $meta;
	public $saved = false;
	public function __construct( $meta ) { $this->meta = $meta; }
	public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
	public function delete_meta_data( $k ) { unset( $this->meta[ $k ] ); }
	public function update_meta_data( $k, $v ) { $this->meta[ $k ] = $v; }
	public function save() { $this->saved = true; }
}

require __DIR__ . '/../app/Document_Storage.php';

function check( $ok, $what ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: $what\n" ); exit( 1 ); } echo "ok - $what\n"; }
function has_doc_query( $q ) { return isset( $q['relation'] ) && 'OR' === $q['relation'] && 'nashenski_order_document' === $q[0]['key'] && 'nashenski_order_document_en' === $q[1]['key']; }

// Legacy storage.
$GLOBALS['orders'] = array(
	1 => new FakeOrder( array( 'nashenski_order_document' => 11 ) ),
	3 => new FakeOrder( array( 'nashenski_order_document_en' => 33 ) ),
);
$GLOBALS['queue'] = array( array( 1, 3 ) );
$before = time();
$n = Nashenski\Document_Storage::delete_receipts();
$q = $GLOBALS['queries'][0];

check( ! array_key_exists( 'meta_query', $q ), 'legacy: no meta_query argument to wc_get_orders' );
check( has_doc_query( $GLOBALS['wp_args'][0]['meta_query'][0] ), 'legacy: the datastore filter adds the document condition' );
check( empty( $GLOBALS['filters']['woocommerce_order_data_store_cpt_get_orders_query'] ), 'legacy: the filter is removed after the query' );
check( 'shop_order' === $q['type'] && 'ids' === $q['return'] && 100 === $q['limit'], 'type, return and limit unchanged' );
check( preg_match( '/^<(\d+)$/', $q['date_created'], $m ) && abs( (int) $m[1] - ( $before - 30 * 86400 ) ) <= 2, 'date_created is < now - retention days' );
check( 2 === $n && $GLOBALS['orders'][1]->saved && $GLOBALS['orders'][3]->saved, 'matching orders are cleared' );
check( array( 11, 33 ) === $GLOBALS['deleted'], 'attachments deleted' );
check( ! isset( $GLOBALS['orders'][1]->meta['nashenski_order_document'] ) && isset( $GLOBALS['orders'][1]->meta['_nashenski_order_document_deleted'] ), 'meta cleared, deleted marker set' );
check( 0 === $GLOBALS['enqueued'], 'a short batch queues nothing' );

// Two full batches of 100 re-queue once per full batch; the short last one does not.
$GLOBALS['queue'] = array( range( 1001, 1100 ), range( 2001, 2100 ), range( 3001, 3030 ) );
foreach ( array( 1, 2, 3 ) as $run ) { Nashenski\Document_Storage::delete_receipts(); }
check( 2 === $GLOBALS['enqueued'], 'each full batch of 100 re-queues once, the short one does not' );

// A throwing query still removes the filter.
$GLOBALS['throw'] = true;
try { Nashenski\Document_Storage::delete_receipts(); $thrown = false; } catch ( RuntimeException $e ) { $thrown = true; }
$GLOBALS['throw'] = false;
check( $thrown && empty( $GLOBALS['filters']['woocommerce_order_data_store_cpt_get_orders_query'] ), 'legacy: the filter is removed when the query throws' );

// HPOS: meta_query stays in the query, no filter.
$GLOBALS['hpos'] = true;
$GLOBALS['queries'] = array();
$GLOBALS['queue'] = array( array() );
Nashenski\Document_Storage::delete_receipts( true );
check( has_doc_query( $GLOBALS['queries'][0]['meta_query'][0] ), 'HPOS: document condition stays in the query' );
check( ! isset( $GLOBALS['queries'][0]['date_created'] ), 'delete all: no date filter' );
echo "done\n";
}
