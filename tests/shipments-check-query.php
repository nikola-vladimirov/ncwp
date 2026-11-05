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
$GLOBALS['result'] = array();

function apply_filters( $tag, $v ) { return $GLOBALS['limit']; }
function add_filter( $tag, $cb ) { $GLOBALS['filters'][ $tag ][] = $cb; }
function remove_filter( $tag, $cb ) { $GLOBALS['filters'][ $tag ] = array_values( array_filter( $GLOBALS['filters'][ $tag ] ?? array(), function ( $f ) use ( $cb ) { return $f !== $cb; } ) ); }
function __( $t ) { return $t; }
function wp_list_pluck( $a ) { return array(); }
function nashenski_get_order_label( $id ) { $GLOBALS['checked'][] = $id; return array(); } // check_order() reads it first.
function wc_get_orders( $a ) {
	$GLOBALS['queries'][] = $a;
	$wp = array();
	foreach ( $GLOBALS['filters']['woocommerce_order_data_store_cpt_get_orders_query'] ?? array() as $cb ) { $wp = $cb( $wp, $a, null ); }
	$GLOBALS['wp_args'][] = $wp;
	if ( $GLOBALS['throw'] ) { throw new RuntimeException( 'query failed' ); }
	return $GLOBALS['result'];
}

class FakeOrder {
	public $id;
	public function __construct( $id ) { $this->id = $id; }
	public function get_id() { return $this->id; }
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
check( array( 'processing', 'on-hold' ) === $q['status'] && 'date_modified' === $q['orderby'] && 'ASC' === $q['order'] && 100 === $q['limit'], 'status, order and limit unchanged' );
check( preg_match( '/^>(\d+)$/', $q['date_modified'], $m ) && abs( (int) $m[1] - ( $before - 60 * 86400 ) ) <= 2, 'date_modified is > now - 60 days' );
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
echo "done\n";
}
