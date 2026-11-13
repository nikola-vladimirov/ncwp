<?php
/**
 * Offline: the abandoned carts personal data exporter and eraser work by email, page by page, and
 * touch no other email's rows; the eraser deletes the unused reminder coupons and keeps (and reports) used
 * ones; the privacy policy text names the real retention. Run: php tests/abandoned-carts-privacy.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Files.SeparateFunctionsFromOO.Mixed, Generic.Files.OneObjectStructurePerFile.MultipleFound, WordPress.Security.EscapeOutput -- an offline test script: it stubs WordPress functions and $wpdb in one file.

define( 'ABSPATH', __DIR__ . '/fixtures/' );

$GLOBALS['table_exists'] = true;
$GLOBALS['policy']       = '';

/**
 * Stub: the table version option.
 *
 * @param string $name Option name.
 * @return mixed
 */
function get_option( $name ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- the stub keeps WordPress's signature.
	return $GLOBALS['table_exists'] ? '2' : false; }

/**
 * Stub: no plugin settings, so the defaults apply.
 *
 * @return string
 */
function nashenski_get_option() {
	return ''; }

/**
 * Stub.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text; }

/**
 * Stub.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( $text, ENT_QUOTES ); }

/**
 * Stub: keep the suggested policy text.
 *
 * @param string $name Plugin name.
 * @param string $text Policy text.
 */
function wp_add_privacy_policy_content( $name, $text ) {
	$GLOBALS['policy'] = $name . ': ' . $text; }

/**
 * Stub.
 *
 * @param mixed $value Value.
 * @return int
 */
function absint( $value ) {
	return abs( (int) $value ); }

define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['cache']      = array();
$GLOBALS['cache_hits'] = 0;

/**
 * Stub.
 *
 * @param mixed $value Value.
 * @return string
 */
function wp_json_encode( $value ) {
	return json_encode( $value ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the stub of wp_json_encode() itself.

/**
 * Stub: an object cache in memory, as WordPress has without a persistent one.
 *
 * @param string $key   Key.
 * @param string $group Group.
 * @return mixed
 */
function wp_cache_get( $key, $group ) {
	if ( ! isset( $GLOBALS['cache'][ $group ] ) || ! array_key_exists( $key, $GLOBALS['cache'][ $group ] ) ) {
		return false;
	}
	++$GLOBALS['cache_hits'];
	return $GLOBALS['cache'][ $group ][ $key ]; }

/**
 * Stub.
 *
 * @param string $key   Key.
 * @param mixed  $value Value.
 * @param string $group Group.
 */
function wp_cache_set( $key, $value, $group ) {
	$GLOBALS['cache'][ $group ][ $key ] = $value; }

/**
 * Stub.
 *
 * @param string $key   Key.
 * @param string $group Group.
 */
function wp_cache_delete( $key, $group ) {
	unset( $GLOBALS['cache'][ $group ][ $key ] ); }

/**
 * Stub: as in WordPress, a new value once the old one was deleted.
 *
 * @param string $group Group.
 * @return string
 */
function wp_cache_get_last_changed( $group ) {
	static $n = 0;
	if ( ! isset( $GLOBALS['cache'][ $group ]['last_changed'] ) ) {
		$GLOBALS['cache'][ $group ]['last_changed'] = 'changed-' . ( ++$n );
	}
	return $GLOBALS['cache'][ $group ]['last_changed']; }

/**
 * Stub: coupons 7 (unused) and 8 (used) exist; 9 was deleted.
 *
 * @param int $id Post ID.
 * @return string|false
 */
function get_post_type( $id ) {
	return isset( $GLOBALS['coupons'][ $id ] ) ? 'shop_coupon' : false; }

/**
 * Coupon stub: its use count, and delete() removes it.
 */
class WC_Coupon {
	/**
	 * ID.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Constructor.
	 *
	 * @param int $id ID.
	 */
	public function __construct( $id ) {
		$this->id = $id; }

	/**
	 * Use count.
	 *
	 * @return int
	 */
	public function get_usage_count() {
		return $GLOBALS['coupons'][ $this->id ]; }

	/**
	 * Delete.
	 *
	 * @param bool $force Skip the trash.
	 * @return bool
	 */
	public function delete( $force ) {
		unset( $GLOBALS['coupons'][ $this->id ] );
		return $force; }
}

$GLOBALS['coupons'] = array(
	7 => 0,
	8 => 1,
);

/**
 * A $wpdb with the cart rows in memory. prepare() hands the query and its values on as they are.
 */
class Fake_WPDB {
	/**
	 * Table prefix.
	 *
	 * @var string
	 */
	public $prefix = 'wp_';

	/**
	 * Cart rows.
	 *
	 * @var object[]
	 */
	public $rows = array();

	/**
	 * Prepare.
	 *
	 * @param string $query Query.
	 * @param mixed  ...$args Values.
	 * @return array
	 */
	public function prepare( $query, ...$args ) {
		return array( $query, $args );
	}

	/**
	 * SELECT ... WHERE email = %s ORDER BY id LIMIT %d OFFSET %d.
	 *
	 * @param array $prepared Query and values.
	 * @return object[]
	 */
	public function get_results( $prepared ) {
		list( , $args ) = $prepared;
		$rows           = array_values(
			array_filter(
				$this->rows,
				function ( $row ) use ( $args ) {
					return $row->email === $args[1];
				}
			)
		);

		return array_slice( $rows, $args[3], $args[2] );
	}

	/**
	 * SELECT coupon_id ... WHERE email = %s ORDER BY id LIMIT %d.
	 *
	 * @param array $prepared Query and values.
	 * @return array
	 */
	public function get_col( $prepared ) {
		return array_column( $this->get_results( array( '', array( '', $prepared[1][1], $prepared[1][2], 0 ) ) ), 'coupon_id' );
	}

	/**
	 * DELETE ... WHERE email = %s ORDER BY id LIMIT %d.
	 *
	 * @param array $prepared Query and values.
	 * @return int Rows removed.
	 */
	public function query( $prepared ) {
		list( , $args ) = $prepared;
		$removed        = 0;

		foreach ( $this->rows as $index => $row ) {
			if ( $row->email === $args[1] && $removed < $args[2] ) {
				unset( $this->rows[ $index ] );
				++$removed;
			}
		}

		return $removed;
	}
}

require __DIR__ . '/../app/Abandoned_Carts/class-tracker.php';

use Nashenski\Abandoned_Carts\Tracker;

$fail = array();
$pass = 0;

/**
 * Record a check.
 *
 * @param bool   $ok   Result.
 * @param string $what What was checked.
 */
function expect( $ok, $what ) {
	global $fail, $pass;
	if ( $ok ) {
		++$pass;
	} else {
		$fail[] = $what;
	}
}

$wpdb = new Fake_WPDB(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- the stub $wpdb the tracker reads.
for ( $n = 1; $n <= 253; $n++ ) {
	$wpdb->rows[] = (object) array(
		'id'            => $n,
		'email'         => $n <= 250 ? 'ana@example.com' : 'bob@example.com',
		'first_name'    => 'Ana',
		'phone'         => '0888123456',
		'cart'          => '[{"name":"Mug","quantity":2}]',
		'cart_total'    => '24.0000',
		'currency'      => 'EUR',
		'status'        => 'open',
		'created_at'    => '2026-11-01 10:00:00',
		'last_activity' => '2026-11-02 10:00:00',
		'coupon_id'     => array(
			1 => 7,
			2 => 8,
			3 => 9,
		)[ $n ] ?? 0,
	);
}

// Registration.
$exporters = Tracker::register_exporter( array() );
$erasers   = Tracker::register_eraser( array() );
expect( is_callable( $exporters['nashenski-abandoned-carts']['callback'] ?? null ), 'the exporter is registered' );
expect( is_callable( $erasers['nashenski-abandoned-carts']['callback'] ?? null ), 'the eraser is registered' );

// Export, 100 rows a page.
$first = Tracker::export_personal_data( 'ana@example.com', 1 );
$last  = Tracker::export_personal_data( 'ana@example.com', 3 );
expect( 100 === count( $first['data'] ) && false === $first['done'], 'page 1 has 100 rows and more follow' );
expect( 50 === count( $last['data'] ) && true === $last['done'], 'page 3 has the last 50 rows' );
$item   = $first['data'][0];
$values = array_column( $item['data'], 'value', 'name' );
expect( 'nashenski-cart-1' === $item['item_id'] && 'nashenski-abandoned-carts' === $item['group_id'], 'an item has its own id in the cart group' );
expect( '0888123456' === $values['Phone'] && 'Ana' === $values['First name'] && '[{"name":"Mug","quantity":2}]' === $values['Cart'] && '2026-11-02 10:00:00' === $values['Last activity'], 'an item lists the cart columns' );
$bob = Tracker::export_personal_data( 'bob@example.com', 1 );
expect( 3 === count( $bob['data'] ) && $bob['done'], 'another email gets only its own rows' );

// The same read again is answered by the object cache; a write to the table clears it.
$hits  = $GLOBALS['cache_hits'];
$again = Tracker::export_personal_data( 'bob@example.com', 1 );
expect( $GLOBALS['cache_hits'] === $hits + 1 && $again == $bob, 'a repeated read comes from the object cache' ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- two arrays of equal rows, not the same objects.
$stamp = wp_cache_get_last_changed( 'nashenski_carts' );

// Erase, 100 rows a call, until done. The first page has the coupons.
$calls = 0;
do {
	$result = Tracker::erase_personal_data( 'ana@example.com' );
	if ( 0 === $calls ) {
		expect( true === $result['items_retained'] && false !== strpos( $result['messages'][0] ?? '', 'kept for the store' ), 'a used coupon is reported as retained' );
	}
	++$calls;
} while ( ! $result['done'] && $calls < 10 );
expect( false === $result['items_retained'] && array() === $result['messages'], 'a page without coupons retains nothing' );
expect( 3 === $calls && true === $result['items_removed'], 'the eraser removes 250 rows in 3 pages' );
expect( array( 8 => 1 ) === $GLOBALS['coupons'], 'the unused reminder coupon is deleted, the used one is kept, a deleted one is skipped' );
expect( 3 === count( $wpdb->rows ), 'the other email keeps its rows' );
expect( wp_cache_get_last_changed( 'nashenski_carts' ) !== $stamp && array() === Tracker::export_personal_data( 'ana@example.com', 1 )['data'], 'after the erase no read is answered from before it' );
$again = Tracker::erase_personal_data( 'ana@example.com' );
expect( false === $again['items_removed'] && $again['done'], 'nothing left to remove' );

// No table: the feature was never on.
$GLOBALS['table_exists'] = false;
$none                    = Tracker::export_personal_data( 'bob@example.com', 1 );
$none_erase              = Tracker::erase_personal_data( 'bob@example.com' );
expect( array() === $none['data'] && $none['done'] && $none_erase['done'] && 3 === count( $wpdb->rows ), 'without the table nothing is read or deleted' );

// Privacy policy text.
Tracker::privacy_policy_text();
expect( false !== strpos( $GLOBALS['policy'], 'deleted 90 days after' ) && false !== strpos( $GLOBALS['policy'], 'NepoStop' ), 'the policy text names the retention and the customer checks' );

if ( $fail ) {
	fwrite( STDERR, 'FAIL: ' . implode( "\nFAIL: ", $fail ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- offline test, WordPress is not loaded.
	exit( 1 );
}
echo 'PASS: ' . $pass . " abandoned carts privacy checks\n";
