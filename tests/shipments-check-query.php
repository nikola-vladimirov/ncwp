<?php
/** Offline: check_shipments() sends no meta_query on legacy storage (a filter adds NOT EXISTS), keeps it on HPOS, keeps limit 1:1. Run: php tests/shipments-check-query.php */
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
$GLOBALS['wp_args'] = array();
$GLOBALS['filters'] = array();
$GLOBALS['checked'] = array();
$GLOBALS['limit'] = 100;
$GLOBALS['throw'] = false;
$GLOBALS['rotate'] = false;
$GLOBALS['result'] = array();
$GLOBALS['clock'] = 0;
$GLOBALS['mid_batch'] = null;

function apply_filters( $tag, $v ) { return $GLOBALS['limit']; }
function add_filter( $tag, $cb ) { $GLOBALS['filters'][ $tag ][] = $cb; }
function remove_filter( $tag, $cb ) { $GLOBALS['filters'][ $tag ] = array_values( array_filter( $GLOBALS['filters'][ $tag ] ?? array(), function ( $f ) use ( $cb ) { return $f !== $cb; } ) ); }
function __( $t ) { return $t; }
function wp_list_pluck( $a ) { return array(); }
function nashenski_get_order_label( $id ) { // check_order() reads it first.
	$GLOBALS['checked'][] = $id;
	if ( $GLOBALS['mid_batch'] ) { call_user_func( $GLOBALS['mid_batch'], $id ); }
	return array();
}
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
	if ( $GLOBALS['rotate'] ) { // Like the datastore: date_modified ASC, then the limit.
		$r = $GLOBALS['result'];
		usort( $r, function ( $x, $y ) { return $x->modified <=> $y->modified; } );
		return array_map( function ( $o ) { return $o->snapshot(); }, array_slice( $r, 0, $a['limit'] ) );
	}
	return array_map( function ( $o ) { return $o->snapshot(); }, $GLOBALS['result'] );
}

class FakeOrder {
	public $id;
	public $modified = 0;
	public $meta = array();
	public $saves = 0;
	public $status = 'processing';
	public $stored; // The stored order this copy was loaded from.
	private $touched = false;
	public function __construct( $id ) { $this->id = $id; }
	public function snapshot() { $c = clone $this; $c->stored = $this; return $c; }
	public function get_id() { return $this->id; }
	public function update_meta_data( $k, $v ) { $this->meta[ $k ] = $v; }
	public function set_date_modified( $t ) { $this->touched = true; }
	public function save() { // Like the legacy store: the whole row is rewritten from this copy; the modified date moves only if it was set.
		$s = $this->stored;
		$s->saves++;
		$s->status = $this->status;
		$s->meta = $this->meta;
		if ( $this->touched ) { $s->modified = ++$GLOBALS['clock']; $this->touched = false; }
	}
}

require __DIR__ . '/../app/Shipping/Automation.php';

function check( $ok, $what ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: $what\n" ); exit( 1 ); } echo "ok - $what\n"; }
function not_exists( $q ) { return '_nashenski_shipment_state' === $q['key'] && 'NOT EXISTS' === $q['compare']; }
$hook = 'woocommerce_order_data_store_cpt_get_orders_query';

// Legacy storage.
$GLOBALS['result'] = array( new FakeOrder( 7 ), new FakeOrder( 8 ) );
$before = time();
Nashenski\Shipping\Automation::check_shipments();
$q = $GLOBALS['queries'][0];

check( ! array_key_exists( 'meta_query', $q ), 'legacy: no meta_query argument' );
check( not_exists( $GLOBALS['wp_args'][0]['meta_query'][0] ), 'legacy: the datastore filter adds NOT EXISTS' );
check( empty( $GLOBALS['filters'][ $hook ] ), 'legacy: the filter is removed after the query' );
check( array( 'processing', 'on-hold' ) === $q['status'] && 'modified' === $q['orderby'] && 'ASC' === $q['order'] && 100 === $q['limit'], 'status, order and limit unchanged' );
check( preg_match( '/^>(\d+)$/', $q['date_created'], $m ) && abs( (int) $m[1] - ( $before - 60 * 86400 ) ) <= 2 && ! isset( $q['date_modified'] ), 'the window is date_created > now - 60 days, not date_modified' );
check( array( 7, 8 ) === $GLOBALS['checked'], 'every returned order is checked' );
check( 1 === count( $GLOBALS['queries'] ), 'one query, no paging' );

// The limit passes through as main did: -1 stays -1.
$GLOBALS['limit'] = -1;
Nashenski\Shipping\Automation::check_shipments();
check( -1 === end( $GLOBALS['queries'] )['limit'], 'limit -1 is passed through unchanged' );

// A throwing query still removes the filter.
$GLOBALS['throw'] = true;
try { Nashenski\Shipping\Automation::check_shipments(); $thrown = false; } catch ( RuntimeException $e ) { $thrown = true; }
check( $thrown && empty( $GLOBALS['filters'][ $hook ] ), 'legacy: the filter is removed when the query throws' );

// HPOS: the condition stays in the query, no filter.
$GLOBALS['throw'] = false;
$GLOBALS['hpos'] = true;
$GLOBALS['limit'] = 100;
Nashenski\Shipping\Automation::check_shipments();
check( not_exists( end( $GLOBALS['queries'] )['meta_query'][0] ), 'HPOS: NOT EXISTS stays in the query' );
// An unchanged open order (no waybill here) is saved with the checked meta, so the next run reaches the others.
$GLOBALS['hpos'] = false;
$GLOBALS['rotate'] = true;
$GLOBALS['limit'] = 2;
$GLOBALS['result'] = array( new FakeOrder( 1 ), new FakeOrder( 2 ), new FakeOrder( 3 ), new FakeOrder( 4 ) );
foreach ( $GLOBALS['result'] as $i => $o ) { $o->modified = -10 + $i; }
$GLOBALS['checked'] = array();
Nashenski\Shipping\Automation::check_shipments();
check( array( 1, 2 ) === $GLOBALS['checked'], 'rotation: the first run takes the two oldest' );
check( isset( $GLOBALS['result'][0]->meta['_nashenski_shipment_checked'] ) && 1 === $GLOBALS['result'][0]->saves, 'unchanged order: saved once with the checked meta' );
$GLOBALS['checked'] = array();
Nashenski\Shipping\Automation::check_shipments();
check( array( 3, 4 ) === $GLOBALS['checked'], 'rotation: the second run reaches the newer orders (needs the modified-date bump)' );

// The owner puts an order on hold while the batch runs: the save must not undo it.
$GLOBALS['result'] = array( new FakeOrder( 21 ), new FakeOrder( 22 ) );
$GLOBALS['limit'] = 2;
$GLOBALS['mid_batch'] = function ( $id ) { if ( 21 === $id ) { $GLOBALS['result'][0]->status = 'on-hold'; } };
Nashenski\Shipping\Automation::check_shipments();
$GLOBALS['mid_batch'] = null;
check( 'on-hold' === $GLOBALS['result'][0]->status && isset( $GLOBALS['result'][0]->meta['_nashenski_shipment_checked'] ), 'a status changed during the batch survives the checked save, and the meta is saved' );
check( 'processing' === $GLOBALS['result'][1]->status && 1 === $GLOBALS['result'][1]->saves, 'the other order is saved once, status unchanged' );
echo "done\n";
}
