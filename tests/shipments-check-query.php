<?php
/** Offline: check_shipments() sends no meta_query, pages the query and skips finished orders in PHP. Run: php tests/shipments-check-query.php */
define( 'ABSPATH', __DIR__ . '/fixtures/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['queries'] = array();
$GLOBALS['checked'] = array();
$GLOBALS['limit'] = 2;

function apply_filters( $tag, $v ) { return $GLOBALS['limit']; }
function __( $t ) { return $t; }
function wp_list_pluck( $a ) { return array(); }
function nashenski_get_order_label( $id ) { $GLOBALS['checked'][] = $id; return array(); } // check_order() reads it first.
function wc_get_orders( $a ) { $GLOBALS['queries'][] = $a; return $GLOBALS['pages'][ $a['paged'] ] ?? array(); }

class FakeOrder {
	public $id;
	public $done;
	public function __construct( $id, $done ) { $this->id = $id; $this->done = $done; }
	public function get_id() { return $this->id; }
	public function meta_exists( $k ) { return $this->done && '_nashenski_shipment_state' === $k; }
}

require __DIR__ . '/../app/Shipping/Automation.php';

function check( $ok, $what ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: $what\n" ); exit( 1 ); } echo "ok - $what\n"; }

// Page 1 is full (limit 2) with one finished order; page 2 has the rest.
$GLOBALS['pages'] = array(
	1 => array( new FakeOrder( 1, true ), new FakeOrder( 2, false ) ),
	2 => array( new FakeOrder( 3, false ), new FakeOrder( 4, false ) ),
	3 => array( new FakeOrder( 5, false ) ),
);

$before = time();
Nashenski\Shipping\Automation::check_shipments();
$q = $GLOBALS['queries'][0];

check( ! array_key_exists( 'meta_query', $q ) && ! array_key_exists( 'meta_query', $GLOBALS['queries'][1] ), 'no meta_query argument' );
check( array( 'processing', 'on-hold' ) === $q['status'] && 'date_modified' === $q['orderby'] && 'ASC' === $q['order'] && 2 === $q['limit'], 'status, order and limit unchanged' );
check( preg_match( '/^>(\d+)$/', $q['date_modified'], $m ) && abs( (int) $m[1] - ( $before - 60 * 86400 ) ) <= 2, 'date_modified is > now - 60 days' );
check( array( 2, 3 ) === $GLOBALS['checked'], 'finished order skipped, at most limit orders checked' );
check( 2 === count( $GLOBALS['queries'] ), 'stops paging once the limit is filled' );
echo "done\n";
