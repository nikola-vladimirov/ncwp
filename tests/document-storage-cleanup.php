<?php
/** Offline: delete_receipts() sends no meta_query and keeps the date filter. Run: php tests/document-storage-cleanup.php */
define( 'ABSPATH', __DIR__ . '/fixtures/' );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['queries'] = array();
$GLOBALS['pages'] = array(); // paged => ids
$GLOBALS['orders'] = array(); // id => FakeOrder

function get_option( $k ) { return 30; }
function wc_get_orders( $a ) { $GLOBALS['queries'][] = $a; return $GLOBALS['pages'][ $a['paged'] ] ?? array(); }
function wc_get_order( $id ) { return $GLOBALS['orders'][ $id ]; }
function get_post_type( $id ) { return 'attachment'; }
function wp_delete_attachment( $id ) { $GLOBALS['deleted'][] = $id; }

class FakeOrder {
	public $meta;
	public $saved = false;
	public function __construct( $meta ) { $this->meta = $meta; }
	public function meta_exists( $k ) { return isset( $this->meta[ $k ] ); }
	public function get_meta( $k ) { return $this->meta[ $k ] ?? ''; }
	public function delete_meta_data( $k ) { unset( $this->meta[ $k ] ); }
	public function update_meta_data( $k, $v ) { $this->meta[ $k ] = $v; }
	public function save() { $this->saved = true; }
}

require __DIR__ . '/../app/Document_Storage.php';

function check( $ok, $what ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: $what\n" ); exit( 1 ); } echo "ok - $what\n"; }

$GLOBALS['orders'] = array(
	1 => new FakeOrder( array( 'nashenski_order_document' => 11 ) ),
	2 => new FakeOrder( array() ),
	3 => new FakeOrder( array( 'nashenski_order_document_en' => 33 ) ),
);
$GLOBALS['pages'] = array( 1 => array( 1, 2, 3 ) );

$before = time();
$n = Nashenski\Document_Storage::delete_receipts();
$q = $GLOBALS['queries'][0];

check( ! array_key_exists( 'meta_query', $q ), 'no meta_query argument' );
check( 'shop_order' === $q['type'] && 'ids' === $q['return'], 'type and return unchanged' );
check( preg_match( '/^<(\d+)$/', $q['date_created'], $m ) && abs( (int) $m[1] - ( $before - 30 * 86400 ) ) <= 2, 'date_created is < now - retention days' );
check( 2 === $n && $GLOBALS['orders'][1]->saved && $GLOBALS['orders'][3]->saved && ! $GLOBALS['orders'][2]->saved, 'only orders with documents are cleared' );
check( array( 11, 33 ) === $GLOBALS['deleted'], 'attachments deleted' );
check( ! isset( $GLOBALS['orders'][1]->meta['nashenski_order_document'] ) && isset( $GLOBALS['orders'][1]->meta['_nashenski_order_document_deleted'] ), 'meta cleared, deleted marker set' );

$GLOBALS['queries'] = array();
Nashenski\Document_Storage::delete_receipts( true );
check( ! isset( $GLOBALS['queries'][0]['date_created'] ) && ! isset( $GLOBALS['queries'][0]['meta_query'] ), 'delete all: no date filter, no meta_query' );
echo "done\n";
