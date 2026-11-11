<?php
/**
 * Offline: the abandoned carts personal data exporter and eraser work by email, page by page, and
 * touch no other email's rows; the privacy policy text names the real retention. Run: php tests/abandoned-carts-privacy.php
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
	 * DELETE ... WHERE email = %s LIMIT %d.
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

// Erase, 100 rows a call, until done.
$calls = 0;
do {
	$result = Tracker::erase_personal_data( 'ana@example.com' );
	++$calls;
} while ( ! $result['done'] && $calls < 10 );
expect( 3 === $calls && true === $result['items_removed'] && false === $result['items_retained'], 'the eraser removes 250 rows in 3 pages' );
expect( 3 === count( $wpdb->rows ), 'the other email keeps its rows' );
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
