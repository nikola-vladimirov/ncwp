<?php
/** Isolated WordPress/WooCommerce integration test; never point this at a live site. */
$numbering_wp = getenv( 'NASHENSKI_NUMBERING_WP' );
if ( ! $numbering_wp || ! is_file( $numbering_wp . '/wp-load.php' ) ) {
	fwrite( STDERR, "Set NASHENSKI_NUMBERING_WP to a disposable WordPress + WooCommerce installation.\n" );
	exit( 1 );
}
require $numbering_wp . '/wp-load.php';
require dirname( __DIR__ ) . '/vendor/autoload.php';
require dirname( __DIR__ ) . '/app/functions.php';
if ( get_option( 'blogname' ) !== 'Numbering tests' ) {
	throw new RuntimeException( 'Refusing to run outside the disposable Numbering tests site.' );
}
wp_set_current_user( get_user_by( 'login', 'numbering-test' )->ID );

use Automattic\WooCommerce\Utilities\OrderUtil;
use Nashenski\Invoice\Document\BaseDocument;
use Nashenski\Admin\Order\Actions;
use Nashenski\Admin\Order\Subscriptions;

function document( $order, $meta = 'nashenski_order_number', $option = 'next_invoice_number' ) {
	$doc                         = ( new ReflectionClass( BaseDocument::class ) )->newInstanceWithoutConstructor();
	$doc->woo_order              = $order;
	$doc->document_number_meta   = $meta;
	$doc->document_number_option = $option;
	return $doc;
}

if ( ( $argv[1] ?? '' ) === '--worker' ) {
	list( , , $id, $mode, $barrier, $token ) = $argv;
	$order                                   = wc_get_order( (int) $id );
	$order->get_meta( 'nashenski_order_number' ); // Deliberately retain a stale object.
	get_option( 'nashenski_settings_invoice_next_invoice_number' );
	get_option( 'nashenski_settings_invoice_next_invoice_separate_number' );
	file_put_contents( $barrier . '/' . $token, 'ready' );
	$end = microtime( true ) + 20;
	while ( ! file_exists( $barrier . '/go' ) && microtime( true ) < $end ) {
		usleep( 10000 ); }
	// Widens the old unlocked get_option -> save race; fixed allocator bypasses this cache.
	add_filter(
		'option_nashenski_settings_invoice_next_invoice_number',
		function ( $n ) {
			usleep( 300000 );
			return $n;
		}
	);
	add_filter(
		'option_nashenski_settings_invoice_next_invoice_separate_number',
		function ( $n ) {
			usleep( 300000 );
			return $n;
		}
	);
	if ( $mode === 'renewal' ) {
		Subscriptions::increase_order_doc_number( $order );
		$n = $order->get_meta( 'nashenski_order_number' );
	} elseif ( $mode === 'manual' ) {
		$error = Actions::use_invoice_number( $order, '500' );
		$n     = $error ? 'conflict' : $order->get_meta( 'nashenski_order_invoice_number' );
	} elseif ( $mode === 'n18' ) {
		$report = \Nashenski\Admin\Import_Sources::import( 'mreja_n18' );
		// A conflicting row is skipped and counted, not thrown; report the exact skip count so a silent no-op cannot pass.
		$skips = 0;
		foreach ( $report as $line ) {
			if ( preg_match( '/^(\d+) document numbers? skipped/', $line, $found ) ) {
				$skips = (int) $found[1]; }
		}
		$n = $skips ? 'skipped:' . $skips : ( fresh_number( $order->get_id(), 'nashenski_order_number' ) ?: 'none' );
	} else {
		$meta = $mode === 'refund' ? 'nashenski_refunded_order_number' : ( $mode === 'invoice' ? 'nashenski_order_invoice_number' : 'nashenski_order_number' );
		$n    = document( $order, $meta, $mode === 'invoice' ? 'next_invoice_separate_number' : 'next_invoice_number' )->get_document_number();
	}
	echo 'NUMBER_RESULT:' . $n . "\n";
	exit;
}

$assertions = 0;
function check( $condition, $message ) {
	global $assertions;
	++$assertions;
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
}
function fails( $callback, $message ) {
	$failed = false;
	try {
		$callback();
	} catch ( RuntimeException $e ) {
		$failed = true; }
	check( $failed, $message );
}
function next_number() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'nashenski_settings_invoice_next_invoice_number'" );
}
function fresh_number( $id, $key ) {
	global $wpdb;
	$table   = OrderUtil::custom_orders_table_usage_is_enabled() ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;
	$column  = OrderUtil::custom_orders_table_usage_is_enabled() ? 'order_id' : 'post_id';
	$meta_id = OrderUtil::custom_orders_table_usage_is_enabled() ? 'id' : 'meta_id';
	return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM $table WHERE $column = %d AND meta_key = %s ORDER BY $meta_id LIMIT 1", $id, $key ) );
}
function pair( $jobs ) {
	$dir = sys_get_temp_dir() . '/numbering-' . bin2hex( random_bytes( 6 ) );
	mkdir( $dir );
	$workers = array();
	foreach ( $jobs as $i => $job ) {
		$pipes     = array();
		$p         = proc_open(
			array( PHP_BINARY, '-d', 'memory_limit=512M', '-d', 'error_reporting=22527', __FILE__, '--worker', (string) $job[0], $job[1], $dir, (string) $i ),
			array(
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes
		);
		$workers[] = array( $p, $pipes );
	}
	$end = microtime( true ) + 20;
	while ( count( glob( $dir . '/*' ) ) < count( $jobs ) && microtime( true ) < $end ) {
		usleep( 10000 ); }
	file_put_contents( $dir . '/go', 'go' );
	$numbers = array();
	foreach ( $workers as $worker ) {
		$out = stream_get_contents( $worker[1][1] );
		$err = stream_get_contents( $worker[1][2] );
		fclose( $worker[1][1] );
		fclose( $worker[1][2] );
		check( proc_close( $worker[0] ) === 0, 'Worker failed: ' . $out . $err );
		check( preg_match( '/NUMBER_RESULT:([^\r\n]+)/', $out, $m ), 'Missing worker result: ' . $out );
		$numbers[] = $m[1];
	}
	foreach ( glob( $dir . '/*' ) as $path ) {
		unlink( $path ); }
	rmdir( $dir );
	return $numbers;
}

foreach ( array( false, true ) as $hpos ) {
	$synchronizer = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class );
	for ( $batch = 0; $batch < 100 && $synchronizer->get_total_pending_count(); $batch++ ) {
		$synchronizer->process_batch( $synchronizer->get_next_batch_to_process( 100 ) );
	}
	update_option( 'woocommerce_custom_orders_table_enabled', $hpos ? 'yes' : 'no' );
	check( OrderUtil::custom_orders_table_usage_is_enabled() === $hpos, 'Storage mode switched' );
	$orders = array();
	for ( $i = 0; $i < 22; $i++ ) {
		$orders[] = wc_create_order(); }
	$start = random_int( 100000, 1000000000 );
	nashenski_set_option( 'invoice', 'next_invoice_number', (string) $start );
	nashenski_set_option( 'invoice', 'next_invoice_separate_number', (string) $start );
	$n = pair( array( array( $orders[0]->get_id(), 'order' ), array( $orders[1]->get_id(), 'order' ) ) );
	check( (int) $n[0] !== (int) $n[1], 'Concurrent orders must receive distinct numbers' );
	check( min( $n ) == $start && max( $n ) == $start + 1, 'Concurrent allocations consume consecutive numbers' );
	$n = pair( array( array( $orders[2]->get_id(), 'order' ), array( $orders[2]->get_id(), 'order' ) ) );
	check( $n[0] == $n[1], 'Stale copies of one order reuse its persisted number' );
	check( (int) $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'nashenski_settings_invoice_next_invoice_number'" ) === $start + 3, 'Same-order allocation advances once' );
	$refund = wc_create_refund(
		array(
			'order_id' => $orders[0]->get_id(),
			'amount'   => 0,
		)
	);
	$n      = pair( array( array( $orders[3]->get_id(), 'order' ), array( $refund->get_id(), 'refund' ) ) );
	check( $n[0] != $n[1], 'Orders and refunds share one protected sequence' );
	$n = pair( array( array( $orders[4]->get_id(), 'invoice' ), array( $orders[5]->get_id(), 'invoice' ) ) );
	check( $n[0] != $n[1] && min( $n ) == $start, 'Separate invoice sequence allocates independently' );
	// The renewal carries the parent's number (L1: a number another order holds is replaced).
	$renewal_parent = wc_create_order();
	$orders[]       = $renewal_parent;
	$renewal_parent->update_meta_data( 'nashenski_order_number', '0000000009' );
	$renewal_parent->save();
	$orders[6]->update_meta_data( 'nashenski_order_number', '0000000009' );
	$orders[6]->save();
	$n = pair( array( array( $orders[6]->get_id(), 'renewal' ), array( $orders[7]->get_id(), 'order' ) ) );
	check( $n[0] != $n[1] && (int) $n[0] !== 9, 'Renewal replacement and checkout cannot collide' );
	check( fresh_number( $renewal_parent->get_id(), 'nashenski_order_number' ) === '0000000009', 'The parent keeps its number when the renewal is replaced' );
	// L1: a number the renewal already owns (issued at checkout) is kept, not swapped, and the counter does not move.
	$own        = wc_create_order();
	$orders[]   = $own;
	$own_number = str_pad( (string) ( $start + 9000 ), 10, '0', STR_PAD_LEFT );
	$own->update_meta_data( 'nashenski_order_number', $own_number );
	$own->save();
	$before = next_number();
	Subscriptions::increase_order_doc_number( $own );
	check( fresh_number( $own->get_id(), 'nashenski_order_number' ) === $own_number && $own->get_meta( 'nashenski_order_number' ) === $own_number, 'A renewal keeps the number it already owns' );
	check( next_number() === $before, 'Keeping the renewal own number does not consume a new number' );
	// L1: a renewal that copied another order's number gets a fresh one in-process too; the other order is untouched.
	$copied   = wc_create_order();
	$orders[] = $copied;
	$copied->update_meta_data( 'nashenski_order_number', $own_number );
	$copied->save();
	Subscriptions::increase_order_doc_number( $copied );
	check( fresh_number( $copied->get_id(), 'nashenski_order_number' ) !== $own_number && (int) fresh_number( $copied->get_id(), 'nashenski_order_number' ) >= $before, 'A renewal carrying another order\'s number is renumbered' );
	check( fresh_number( $own->get_id(), 'nashenski_order_number' ) === $own_number, 'The number\'s real owner is left alone' );
	nashenski_set_option( 'invoice', 'next_invoice_separate_number', '' );
	nashenski_set_option( 'invoice', 'next_invoice_number', '500' );
	$n = pair( array( array( $orders[8]->get_id(), 'manual' ), array( $orders[9]->get_id(), 'order' ) ) );
	check( $n[0] === 'conflict' || $n[0] != $n[1], 'Manual assignment cannot race automatic allocation' );
	check( fresh_number( $orders[0]->get_id(), 'nashenski_order_number' ) == $start || fresh_number( $orders[0]->get_id(), 'nashenski_order_number' ) == $start + 1, 'Persisted original reference remains stable' );
	// A stale counter must skip numbers already held by refunds and manual invoices too.
	nashenski_set_option( 'invoice', 'next_invoice_number', (string) fresh_number( $refund->get_id(), 'nashenski_refunded_order_number' ) );
	$n = document( $orders[10] )->get_document_number();
	check( $n != fresh_number( $refund->get_id(), 'nashenski_refunded_order_number' ), 'Reset counter cannot reuse a refund reference' );
	check( document( $orders[10] )->get_document_number() == $n, 'Repeated rendering is stable' );
	check( Actions::use_invoice_number( $orders[11], 'not-a-number' ) !== '', 'Invalid manual number rejected' );
	check( Actions::use_invoice_number( $orders[11], '0' ) !== '', 'Zero manual number rejected' );
	// Both sequences may legitimately issue the same numerical reference.
	$independent = random_int( 1000000001, 1500000000 );
	nashenski_set_option( 'invoice', 'next_invoice_number', (string) $independent );
	nashenski_set_option( 'invoice', 'next_invoice_separate_number', (string) $independent );
	$invoice = document( $orders[12], 'nashenski_order_invoice_number', 'next_invoice_separate_number' )->get_document_number();
	$main    = document( $orders[13] )->get_document_number();
	check( $invoice == $independent && $main == $independent, 'Independent sequences permit identical numbers' );
	$orders[0]->update_meta_data( 'numbering_test_pending', 'keep me' );
	$stable = document( $orders[0] )->get_document_number();
	$orders[0]->save();
	check( fresh_number( $orders[0]->get_id(), 'numbering_test_pending' ) === 'keep me', 'Refreshing a stale number preserves pending metadata' );
	check( fresh_number( $orders[0]->get_id(), 'nashenski_order_number' ) == $stable, 'Later stale-object save preserves its reference' );
	$table  = $hpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;
	$column = $hpos ? 'order_id' : 'post_id';
	check( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $column = %d AND meta_key = 'nashenski_order_number'", $orders[0]->get_id() ) ) === 1, 'Stale object does not insert duplicate metadata rows' );
	$before    = next_number();
	$fail_save = function ( $order ) use ( $orders ) {
		if ( $order->get_id() === $orders[14]->get_id() ) {
			throw new RuntimeException( 'Injected save failure' ); }
	};
	add_action( 'woocommerce_before_order_object_save', $fail_save );
	try {
		fails(
			function () use ( $orders ) {
				document( $orders[14] )->get_document_number();
			},
			'Failed save cannot return a number'
		); } finally {
		remove_action( 'woocommerce_before_order_object_save', $fail_save ); }
		check( ! fresh_number( $orders[14]->get_id(), 'nashenski_order_number' ), 'Failed save leaves order unnumbered' );
		$orders[14]->save();
		check( ! fresh_number( $orders[14]->get_id(), 'nashenski_order_number' ), 'Later unrelated save cannot flush the failed numbering assignment' );
		check( next_number() === $before + 1, 'Failed save consumes its reservation' );
		check( document( $orders[14] )->get_document_number() == $before + 1, 'Retry ignores its unsaved cached number and uses a new reservation' );
		$lock = 'nashenski_numbers_' . md5( DB_NAME . ':' . $wpdb->options );
		check( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_FREE_LOCK(%s)', $lock ) ) === 1, 'Save exception releases lock' );
		$other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		check( (int) $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) === 1, 'Second connection holds actual database lock' );
		$no_wait = function ( $sql ) {
			return str_replace( ', 5)', ', 0)', $sql );
		};
	add_filter( 'query', $no_wait );
	$before = next_number();
	try {
		fails(
			function () use ( $orders ) {
				document( $orders[15] )->get_document_number();
			},
			'Lock contention fails closed'
		); } finally {
		remove_filter( 'query', $no_wait );
		$other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		$other->close(); }
		check( next_number() === $before && ! fresh_number( $orders[15]->get_id(), 'nashenski_order_number' ), 'Lock timeout issues no number' );
		$fail_reserve = function ( $sql ) {
			return strpos( $sql, 'INSERT INTO' ) === 0 && strpos( $sql, 'nashenski_settings_invoice_next_invoice_number' ) !== false ? 'INSERT INTO deliberately_missing_numbering_table VALUES (1)' : $sql;
		};
	add_filter( 'query', $fail_reserve );
	$wpdb->suppress_errors( true );
	try {
		fails(
			function () use ( $orders ) {
				document( $orders[16] )->get_document_number();
			},
			'Reservation write error fails closed'
		); } finally {
		remove_filter( 'query', $fail_reserve );
		$wpdb->suppress_errors( false ); }
		check( ! fresh_number( $orders[16]->get_id(), 'nashenski_order_number' ) && next_number() === $before, 'Failed reservation cannot assign or advance a number' );
		check( document( $orders[16] )->get_document_number() == $before, 'Retry works after reservation error' );
		$stale = wc_get_order( $orders[15]->get_id() );
		$stale->get_meta( 'nashenski_order_invoice_number' );
		$first = random_int( 1600000000, 1700000000 );
		check( Actions::use_invoice_number( $orders[15], (string) $first ) === '', 'First manual reference assigned' );
		check( document( $stale, 'nashenski_order_invoice_number', 'next_invoice_separate_number' )->get_document_number() == $first, 'Stale object reads freshly assigned manual reference' );
		check( Actions::use_invoice_number( $orders[15], (string) ( $first + 1 ) ) === '', 'Manual reference replaced after stale read' );
		check( Actions::use_invoice_number( $orders[16], (string) $first ) === '', 'Released manual reference assigned elsewhere' );
		$stale->save();
		check( fresh_number( $orders[15]->get_id(), 'nashenski_order_invoice_number' ) == $first + 1, 'Later stale save cannot overwrite newer numbering and create a duplicate' );
		// Main's own-program numbering: the typed number is kept but the website sequence does not move.
		nashenski_set_option( 'company', 'invoice_numbering', 'own' );
		$sequence = (int) nashenski_get_option( 'invoice', 'next_invoice_separate_number' );
		check( Actions::use_invoice_number( $orders[16], (string) ( $first + 5000 ) ) === '', 'Own-program manual reference assigned' );
		nashenski_set_option( 'company', 'invoice_numbering', '' );
		check( fresh_number( $orders[16]->get_id(), 'nashenski_invoice_own_program' ) === 'yes' && (int) nashenski_get_option( 'invoice', 'next_invoice_separate_number' ) === $sequence, 'Own-program number is flagged and does not advance the sequence' );
		$legacy       = random_int( 1800000000, 1900000000 );
		$legacy_table = $wpdb->prefix . 'notasupto_documents';
		$wpdb->query( "CREATE TABLE IF NOT EXISTS $legacy_table (order_id BIGINT, type VARCHAR(32), num VARCHAR(20))" );
		$wpdb->query( "TRUNCATE TABLE $legacy_table" );
		$wpdb->insert(
			$legacy_table,
			array(
				'order_id' => $orders[17]->get_id(),
				'type'     => 'purchase_order',
				'num'      => $legacy,
			)
		);
	nashenski_set_option( 'invoice', 'next_invoice_number', (string) $legacy );
	$n = pair( array( array( $orders[17]->get_id(), 'n18' ), array( $orders[18]->get_id(), 'order' ) ) );
	// Whoever takes the lock first wins: checkout first means the import is skipped exactly once and leaves the order unnumbered; import first means both get distinct numbers.
	if ( 0 === strpos( $n[0], 'skipped:' ) ) {
		check( $n[0] === 'skipped:1' && ! fresh_number( $orders[17]->get_id(), 'nashenski_order_number' ) && $n[1] == $legacy, 'A skipped N-18 import is counted exactly once and its number is absent' );
	} else {
		check( $n[0] == $legacy && $n[1] == $legacy + 1, 'Actual N-18 import and checkout cannot issue duplicate numbers' );
	}
	$history = random_int( 2000000000, 2100000000 );
	$wpdb->query( "TRUNCATE TABLE $legacy_table" );
	$wpdb->insert(
		$legacy_table,
		array(
			'order_id' => $orders[19]->get_id(),
			'type'     => 'invoice',
			'num'      => (string) $history,
		)
	);
	\Nashenski\Admin\Import_Sources::import( 'mreja_n18' );
	check( fresh_number( $orders[19]->get_id(), 'nashenski_order_invoice_number' ) === (string) $history, 'N-18 preserves an available historical invoice reference' );
	$wpdb->query( "DROP TABLE $legacy_table" );
	$orders[20]->update_meta_data( 'woo_bg_order_number', (string) ( $history + 1 ) );
	$orders[20]->update_meta_data( 'woo_bg_test_preserved', 'legacy value' );
	$orders[20]->save();
	nashenski_set_option( 'invoice', 'next_invoice_number', (string) ( $history + 1 ) );
	check( document( $orders[21] )->get_document_number() == $history + 1, 'Checkout owns reference before competing legacy import' );
	$copy = new ReflectionMethod( \Nashenski\Admin\Migration::class, 'copy_meta' );
	if ( PHP_VERSION_ID < 80100 ) {
		$copy->setAccessible( true ); }
	$skipped = 0;
	$copy->invokeArgs( null, array( $table, $column, 'woo_bg', &$skipped ) );
	check( $skipped === 1, 'A conflicting legacy reference is skipped and counted, not thrown' );
	check( ! fresh_number( $orders[20]->get_id(), 'nashenski_order_number' ), 'Conflicting legacy reference not assigned' );
	check( fresh_number( $orders[20]->get_id(), 'woo_bg_order_number' ) === (string) ( $history + 1 ), 'Conflicting historical source remains unchanged' );
	$orders[20]->update_meta_data( 'woo_bg_order_number', (string) ( $history + 2 ) );
	$orders[20]->save();
	$skipped     = 0;
	$loaded      = array();
	$count_loads = function ( $class, $type, $id ) use ( &$loaded ) {
		$loaded[] = (int) $id;
		return $class;
	};
	add_filter( 'woocommerce_order_class', $count_loads, 10, 3 );
	$copy->invokeArgs( null, array( $table, $column, 'woo_bg', &$skipped ) );
	check( fresh_number( $orders[20]->get_id(), 'nashenski_order_number' ) === (string) ( $history + 2 ) && ! $skipped, 'Bulk-prefix migration preserves available reference' );
	$loaded = array();
	$copy->invokeArgs( null, array( $table, $column, 'woo_bg', &$skipped ) );
	remove_filter( 'woocommerce_order_class', $count_loads, 10 );
	check( ! in_array( $orders[20]->get_id(), $loaded, true ), 'Orders that already hold the number are not loaded again' );
	// Only the authoritative table is imported; the other one may be stale.
	$other_table  = $hpos ? $wpdb->postmeta : $wpdb->prefix . 'wc_orders_meta';
	$other_column = $hpos ? 'post_id' : 'order_id';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $other_table ) ) === $other_table ) {
		$stale    = wc_create_order();
		$orders[] = $stale;
		$wpdb->insert(
			$other_table,
			array(
				$other_column => $stale->get_id(),
				'meta_key'    => 'woo_bg_order_number',
				'meta_value'  => (string) ( $history + 77 ),
			)
		);
		$copy->invokeArgs( null, array( $other_table, $other_column, 'woo_bg', &$skipped ) );
		$wpdb->delete(
			$other_table,
			array(
				$other_column => $stale->get_id(),
				'meta_key'    => 'woo_bg_order_number',
			)
		);
		check( ! fresh_number( $stale->get_id(), 'nashenski_order_number' ), 'A number in the non-authoritative table is not imported' );
	}
	// An imported invoice number must not switch shared numbering to a separate sequence.
	$shared   = wc_create_order();
	$orders[] = $shared;
	nashenski_set_option( 'invoice', 'next_invoice_separate_number', '' );
	check( \Nashenski\Invoice\Number_Allocator::import_number( $shared, 'nashenski_order_invoice_number', $history + 300, 'next_invoice_separate_number' ), 'Invoice number imported under shared numbering' );
	check( ! $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'nashenski_settings_invoice_next_invoice_separate_number'" ), 'Importing an invoice number leaves shared numbering shared' );
	check( next_number() > $history + 300, 'A shared-mode import advances the shared counter above the imported number' );
	$separate = wc_create_order();
	$orders[] = $separate;
	nashenski_set_option( 'invoice', 'next_invoice_separate_number', '5' );
	\Nashenski\Invoice\Number_Allocator::import_number( $separate, 'nashenski_order_invoice_number', $history + 301, 'next_invoice_separate_number' );
	check( (int) $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'nashenski_settings_invoice_next_invoice_separate_number'" ) === $history + 302, 'Separate numbering still advances after an import' );
	// Shared numbering: an imported separate invoice number must not collide with a number in the shared pool.
	nashenski_set_option( 'invoice', 'next_invoice_separate_number', '' );
	$pooled   = wc_create_order();
	$orders[] = $pooled;
	$pooled->update_meta_data( 'nashenski_order_number', (string) ( $history + 400 ) );
	$pooled->save();
	$clash    = wc_create_order();
	$orders[] = $clash;
	fails(
		function () use ( $clash, $history ) {
			\Nashenski\Invoice\Number_Allocator::import_number( $clash, 'nashenski_order_invoice_number', $history + 400, 'next_invoice_separate_number' );
		},
		'Imported separate number cannot reuse a shared-pool number'
	);
	check( ! fresh_number( $clash->get_id(), 'nashenski_order_invoice_number' ), 'The conflicting import left the order unnumbered' );
	// Separate numbering stays independent: the same value is allowed in the other sequence.
	nashenski_set_option( 'invoice', 'next_invoice_separate_number', '5' );
	check( \Nashenski\Invoice\Number_Allocator::import_number( $clash, 'nashenski_order_invoice_number', $history + 400, 'next_invoice_separate_number' ), 'Separate numbering still allows a number used in the order sequence' );
	// N-18 import on a shared-numbering shop must not set the separate counter.
	nashenski_set_option( 'invoice', 'next_invoice_separate_number', '' );
	$legacy_table = $wpdb->prefix . 'notasupto_documents';
	$wpdb->query( "CREATE TABLE IF NOT EXISTS $legacy_table (order_id BIGINT, type VARCHAR(32), num VARCHAR(20))" );
	$wpdb->query( "TRUNCATE TABLE $legacy_table" );
	$imported = wc_create_order();
	$orders[] = $imported;
	$wpdb->insert(
		$legacy_table,
		array(
			'order_id' => $imported->get_id(),
			'type'     => 'invoice',
			'num'      => (string) ( $history + 500 ),
		)
	);
	\Nashenski\Admin\Import_Sources::import( 'mreja_n18' );
	$wpdb->query( "DROP TABLE $legacy_table" );
	check( fresh_number( $imported->get_id(), 'nashenski_order_invoice_number' ) === (string) ( $history + 500 ), 'N-18 invoice imported on a shared-numbering shop' );
	check( ! $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'nashenski_settings_invoice_next_invoice_separate_number'" ), 'N-18 import keeps a shared-numbering shop shared' );
	$next_new = wc_create_order();
	$orders[] = $next_new;
	check( (int) document( $next_new, 'nashenski_order_invoice_number', 'next_invoice_number' )->get_document_number() > $history + 500, 'After an N-18 import on a shared-numbering shop, the next new invoice number is above the highest imported one' );
	nashenski_set_option( 'invoice', 'next_invoice_separate_number', '' );
	foreach ( $orders as $order ) {
		$order->delete( true ); }
	$refund->delete( true );
}
echo "PASS: $assertions document numbering assertions (legacy + HPOS)\n";
