<?php
/** Offline: check_shipments() query (no meta_query on legacy, NOT EXISTS filter instead; kept on HPOS), limit and window, the offset rotation, and saves only for changed orders. Run: php tests/shipments-check-query.php */
namespace Automattic\WooCommerce\Utilities {
	class OrderUtil {
		public static function custom_orders_table_usage_is_enabled() { return $GLOBALS['hpos']; }
	}
}

namespace Nashenski\Container {
	class Client {
		const ECONT = 'econt';
		const SPEEDY = 'speedy';
		const BOXNOW = 'boxnow';
	}
}

namespace Nashenski\Front_End\Checkout {
	class Pickers {
		public static function connected( $courier ) { return true; }
	}
}

namespace {
define( 'ABSPATH', __DIR__ . '/fixtures/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['hpos'] = false;
$GLOBALS['queries'] = array();
$GLOBALS['wp_args'] = array();
$GLOBALS['filters'] = array();
$GLOBALS['checked'] = array();
$GLOBALS['limit'] = 100;
$GLOBALS['throw'] = false;
$GLOBALS['result'] = array();
$GLOBALS['mid_batch'] = null;
$GLOBALS['options'] = array();
$GLOBALS['labels'] = array(); // Order id => label array; an order without one has no waybill.
$GLOBALS['delivered'] = ''; // The status the owner chose for a delivered parcel.

function get_option( $k, $d = false ) { return $GLOBALS['options'][ $k ] ?? $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function apply_filters( $tag, $v ) { return $GLOBALS['limit']; }
function add_filter( $tag, $cb ) { $GLOBALS['filters'][ $tag ][] = $cb; }
function remove_filter( $tag, $cb ) { $GLOBALS['filters'][ $tag ] = array_values( array_filter( $GLOBALS['filters'][ $tag ] ?? array(), function ( $f ) use ( $cb ) { return $f !== $cb; } ) ); }
function __( $t ) { return $t; }
function wp_list_pluck( $a, $k ) { return array_column( (array) $a, $k ); }
function nashenski_get_option( $group, $name ) { return 'delivered' === $name ? $GLOBALS['delivered'] : ''; }
function nashenski_get_order_label( $id ) { // check_order() reads it first.
	$GLOBALS['checked'][] = $id;
	if ( $GLOBALS['mid_batch'] ) { call_user_func( $GLOBALS['mid_batch'], $id ); }
	return $GLOBALS['labels'][ $id ] ?? array();
}
class FakeClient {
	public function api_call() { return array( 'data' => array( array( 'state' => 'delivered' ) ) ); }
}
function nashenski() { return new class { public function container() { return array( 'boxnow' => new FakeClient() ); } }; }
function wc_get_order( $id ) { // A fresh read of the stored order.
	foreach ( $GLOBALS['result'] as $o ) { if ( $o->id === $id ) { return $o->snapshot(); } }
	return false;
}
function wc_get_orders( $a ) {
	$GLOBALS['queries'][] = $a;
	$wp = array();
	foreach ( $GLOBALS['filters']['woocommerce_order_data_store_cpt_get_orders_query'] ?? array() as $cb ) { $wp = $cb( $wp, $a, null ); }
	$GLOBALS['wp_args'][] = $wp;
	if ( $GLOBALS['throw'] ) { throw new RuntimeException( 'query failed' ); }
	// Like the datastore: orders without the state meta (NOT EXISTS), ID ASC, then offset and limit.
	$r = array_values( array_filter( $GLOBALS['result'], function ( $o ) { return ! isset( $o->meta['_nashenski_shipment_state'] ); } ) );
	usort( $r, function ( $x, $y ) { return $x->id <=> $y->id; } );
	$r = array_slice( $r, $a['offset'] ?? 0, $a['limit'] > 0 ? $a['limit'] : null );
	return array_map( function ( $o ) { return wc_get_order( $o->id ); }, $r );
}

class FakeOrder {
	public $id;
	public $meta = array();
	public $saves = 0;
	public $status = 'processing';
	public $stored; // The stored order this copy was loaded from.
	private $dirty = false;
	public function __construct( $id ) { $this->id = $id; }
	public function snapshot() { $c = clone $this; $c->stored = $this; return $c; }
	public function get_id() { return $this->id; }
	public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
	public function get_status() { return $this->status; }
	public function update_meta_data( $k, $v ) { $this->meta[ $k ] = $v; }
	public function set_date_modified( $t ) { $this->dirty = true; }
	public function add_order_note( $n ) {}
	public function update_status( $s, $n = '' ) { $this->status = $s; $this->dirty = true; $this->save(); }
	public function save() { // Like the legacy store: the post row (status) is rewritten from this copy only when the status or a date changed; meta is always saved.
		$s = $this->stored;
		$s->saves++;
		$s->meta = $this->meta;
		if ( $this->dirty ) { $s->status = $this->status; $this->dirty = false; }
	}
}

require __DIR__ . '/../app/Shipping/Automation.php';

function check( $ok, $what ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: $what\n" ); exit( 1 ); } echo "ok - $what\n"; }
function not_exists( $q ) { return '_nashenski_shipment_state' === $q['key'] && 'NOT EXISTS' === $q['compare']; }
function run() { $GLOBALS['checked'] = array(); Nashenski\Shipping\Automation::check_shipments(); return $GLOBALS['checked']; }
function offset() { return $GLOBALS['options']['nashenski_shipments_offset'] ?? null; }
function orders( ...$ids ) { $GLOBALS['result'] = array_map( function ( $i ) { return new FakeOrder( $i ); }, $ids ); }
$hook = 'woocommerce_order_data_store_cpt_get_orders_query';

// Legacy storage.
orders( 7, 8 );
$before = time();
check( array( 7, 8 ) === run(), 'every returned order is checked' );
$q = $GLOBALS['queries'][0];

check( ! array_key_exists( 'meta_query', $q ), 'legacy: no meta_query argument' );
check( not_exists( $GLOBALS['wp_args'][0]['meta_query'][0] ), 'legacy: the datastore filter adds NOT EXISTS' );
check( empty( $GLOBALS['filters'][ $hook ] ), 'legacy: the filter is removed after the query' );
check( array( 'processing', 'on-hold' ) === $q['status'] && 'ASC' === $q['order'] && 100 === $q['limit'], 'status, order direction and limit unchanged' );
check( preg_match( '/^>(\d+)$/', $q['date_created'], $m ) && abs( (int) $m[1] - ( $before - 60 * 86400 ) ) <= 2 && ! isset( $q['date_modified'] ), 'the window is date_created > now - 60 days, not date_modified' );
check( 1 === count( $GLOBALS['queries'] ), 'one query, no paging' );
check( 'ID' === $q['orderby'] && 0 === $q['offset'] && 0 === offset(), 'ordered by ID; the first run starts at offset 0 and fewer orders than the limit leaves it at 0' );

// The limit passes through as main did: -1 stays -1, with no offset and none saved.
$GLOBALS['limit'] = -1;
run();
$q = end( $GLOBALS['queries'] );
check( -1 === $q['limit'] && 0 === $q['offset'] && 0 === offset(), 'limit -1 is passed through unchanged, no paging' );

// A throwing query still removes the filter.
$GLOBALS['throw'] = true;
try { run(); $thrown = false; } catch ( RuntimeException $e ) { $thrown = true; }
check( $thrown && empty( $GLOBALS['filters'][ $hook ] ), 'legacy: the filter is removed when the query throws' );

// HPOS: the condition stays in the query, no filter.
$GLOBALS['throw'] = false;
$GLOBALS['hpos'] = true;
$GLOBALS['limit'] = 100;
run();
check( not_exists( end( $GLOBALS['queries'] )['meta_query'][0] ), 'HPOS: NOT EXISTS stays in the query' );

// Unchanged orders (no waybill here) are not saved, and the offset steps through the list and wraps.
$GLOBALS['hpos'] = false;
$GLOBALS['limit'] = 2;
$GLOBALS['options'] = array();
orders( 1, 2, 3 );
check( array( 1, 2 ) === run() && 2 === offset(), 'rotation: the first run takes the first two, the offset moves to 2' );
check( array( 3 ) === run() && 0 === offset(), 'rotation: the second run takes the last order and the offset wraps to 0' );
check( array( 1, 2 ) === run() && 2 === offset(), 'rotation: after the wrap the first two again' );
foreach ( $GLOBALS['result'] as $o ) { check( 0 === $o->saves && ! isset( $o->meta['_nashenski_shipment_checked'] ), "unchanged order {$o->id}: never saved, no checked meta" ); }

// A changed order is saved once; its unchanged neighbour is not, and the offset allows for the order that left the list.
$GLOBALS['options'] = array();
$GLOBALS['delivered'] = 'wc-completed';
orders( 41, 42 );
$GLOBALS['labels'] = array( 41 => array( 'method' => 'nashenski_boxnow', 'number' => 'P1' ) );
check( array( 41, 42 ) === run(), 'changed: both orders are checked' );
check( 1 === $GLOBALS['result'][0]->saves && 'completed' === $GLOBALS['result'][0]->status && 'delivered' === $GLOBALS['result'][0]->meta['_nashenski_shipment_state'], 'changed: the delivered order is saved once with its new status and state' );
check( 0 === $GLOBALS['result'][1]->saves && 1 === offset(), 'changed: the unchanged order is not saved, and the next page starts at 1 (41 left the list)' );
check( array() === run() && 0 === offset(), 'changed: the next run reads past the end of the list and wraps' );

// A status the owner sets while the batch runs survives, on an unchanged order (not saved) and on a changed one (meta-only save).
$GLOBALS['options'] = array();
$GLOBALS['delivered'] = '';
orders( 21, 22 );
$GLOBALS['labels'] = array( 22 => array( 'method' => 'nashenski_boxnow', 'number' => 'P2' ) );
$GLOBALS['mid_batch'] = function ( $id ) { $GLOBALS['result'][ $id - 21 ]->status = 'on-hold'; };
run();
$GLOBALS['mid_batch'] = null;
check( 'on-hold' === $GLOBALS['result'][0]->status && 0 === $GLOBALS['result'][0]->saves, 'unchanged order: on-hold set mid-batch survives, nothing saved' );
check( 'on-hold' === $GLOBALS['result'][1]->status && 1 === $GLOBALS['result'][1]->saves && isset( $GLOBALS['result'][1]->meta['_nashenski_shipment_state'] ), 'changed order with no status chosen: on-hold set mid-batch survives, the state is saved' );
echo "done\n";
}
