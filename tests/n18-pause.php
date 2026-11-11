<?php // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride, Universal.Namespaces -- offline CLI test script: no WordPress, so stubs, unused stub parameters and plain-text output are on purpose.
/**
 * Offline check (0.21.7): after the import of the mreja.net N-18 plugin, while it is active, Nashenski
 * Commerce makes no documents and takes no numbers, says so, and notes the orders it skipped. Once
 * the plugin is off, the counters continue after its highest number once, also when two requests race.
 *
 * Run: php tests/n18-pause.php
 *
 * @package Nashenski_Commerce
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DB_NAME', 'test' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'ARRAY_A', 'ARRAY_A' );

	$GLOBALS['options'] = array(
		'nashenski_imported_sources'                     => array( 'mreja_n18' => array( 'time' => 1 ) ),
		'nashenski_settings_invoice_next_invoice_number' => '0000000101',
	);
	$GLOBALS['rows']    = array(
		array(
			'type' => 'purchase_order',
			'num'  => '150',
		),
		array(
			'type' => 'credit_note',
			'num'  => '155',
		),
	);
	$GLOBALS['locks']   = 0;
	$GLOBALS['stale']   = false;

	/**
	 * Option stub. With $GLOBALS['stale'], the flag reads as missing, like a request that read it before another set it.
	 *
	 * @param string $name     Option.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	function get_option( $name, $fallback = false ) {
		if ( $GLOBALS['stale'] && 'nashenski_n18_numbers_continued' === $name ) {
			return $fallback;
		}
		return $GLOBALS['options'][ $name ] ?? $fallback;
	}
	/**
	 * Option write stub.
	 *
	 * @param string $name  Option.
	 * @param mixed  $value Value.
	 */
	function update_option( $name, $value ) {
		$GLOBALS['options'][ $name ] = $value;
	}
	/**
	 * Plugin setting stub.
	 *
	 * @param string $group Group.
	 * @param string $name  Name.
	 * @return mixed
	 */
	function nashenski_get_option( $group, $name ) {
		return get_option( 'nashenski_settings_' . $group . '_' . $name );
	}
	/**
	 * Cache stubs: nothing is cached.
	 */
	function wp_cache_add_non_persistent_groups() {}
	/**
	 * Cache read stub.
	 *
	 * @return false
	 */
	function wp_cache_get() {
		return false;
	}
	/**
	 * Cache write stub.
	 */
	function wp_cache_set() {}
	/**
	 * Cache delete stub.
	 */
	function wp_cache_delete() {}
	/**
	 * UUID stub.
	 *
	 * @return string
	 */
	function wp_generate_uuid4() {
		return uniqid();
	}
	/**
	 * JSON stub.
	 *
	 * @param mixed $data Data.
	 * @return string
	 */
	function wp_json_encode( $data ) {
		return json_encode( $data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the stub of it.
	}
	/**
	 * Escaping and translation stubs.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
	/**
	 * Escaping stub.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return $text;
	}
	/**
	 * Escaping stub.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html__( $text ) {
		return $text;
	}
	/**
	 * Escaping stub.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return $url;
	}
	/**
	 * Admin URL stub.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( $path ) {
		return '/admin/' . $path;
	}
	/**
	 * Single site.
	 *
	 * @return bool
	 */
	function is_multisite() {
		return false;
	}
	/**
	 * Capability stub: set $GLOBALS['can'].
	 *
	 * @return bool
	 */
	function current_user_can() {
		return ! empty( $GLOBALS['can'] );
	}
	/**
	 * Installed plugins: the N-18 plugin.
	 *
	 * @return array
	 */
	function get_plugins() {
		return array( 'woocommerce-notasupto/index.php' => array( 'Name' => 'WooCommerce Наредба Н-18 облекчен режим плъгин' ) );
	}
	/**
	 * Order lookup stub.
	 *
	 * @return object
	 */
	function wc_get_order() {
		return $GLOBALS['order'];
	}

	/**
	 * Database fixture: the numbering lock, the options table and the N-18 plugin's table.
	 */
	class FakeDb {
		/**
		 * Prefix.
		 *
		 * @var string
		 */
		public $prefix = 'wp_';
		/**
		 * Options table.
		 *
		 * @var string
		 */
		public $options = 'wp_options';
		/**
		 * Last error.
		 *
		 * @var string
		 */
		public $last_error = '';
		/**
		 * Prepare: the query and its values.
		 *
		 * @param string $query Query.
		 * @param mixed  ...$args Values.
		 * @return array
		 */
		public function prepare( $query, ...$args ) {
			return array( $query, $args );
		}
		/**
		 * One value.
		 *
		 * @param array $p Prepared query.
		 * @return mixed
		 */
		public function get_var( $p ) {
			if ( 0 === strpos( $p[0], 'SELECT GET_LOCK' ) ) {
				++$GLOBALS['locks'];
				return '1';
			}
			if ( 0 === strpos( $p[0], 'SHOW TABLES' ) ) {
				return $p[1][0];
			}
			if ( 0 === strpos( $p[0], 'SELECT option_value' ) ) {
				return $GLOBALS['options'][ $p[1][1] ] ?? null;
			}
			return '1';
		}
		/**
		 * Rows of the N-18 plugin's table.
		 *
		 * @return array
		 */
		public function get_results() {
			return $GLOBALS['rows'];
		}
		/**
		 * Write: the counter upsert.
		 *
		 * @param array $p Prepared query.
		 * @return int
		 */
		public function query( $p ) {
			$GLOBALS['options'][ $p[1][0] ] = $p[1][1];
			return 1;
		}
	}
	$GLOBALS['wpdb'] = new FakeDb();

	/**
	 * Order fixture: notes and meta.
	 */
	class FakeOrder {
		/**
		 * Notes.
		 *
		 * @var array
		 */
		public $notes = array();
		/**
		 * Meta.
		 *
		 * @var array
		 */
		public $meta = array();
		/**
		 * ID.
		 *
		 * @return int
		 */
		public function get_id() {
			return 7;
		}
		/**
		 * Meta read.
		 *
		 * @param string $key Key.
		 * @return mixed
		 */
		public function get_meta( $key ) {
			return $this->meta[ $key ] ?? '';
		}
		/**
		 * Meta write.
		 *
		 * @param string $key   Key.
		 * @param mixed  $value Value.
		 */
		public function update_meta_data( $key, $value ) {
			$this->meta[ $key ] = $value;
		}
		/**
		 * Save stub.
		 */
		public function save() {}
		/**
		 * Note.
		 *
		 * @param string $note Note.
		 */
		public function add_order_note( $note ) {
			$this->notes[] = $note;
		}
	}

	require __DIR__ . '/../app/Admin/class-import-sources.php';
	require __DIR__ . '/../app/Invoice/class-number-allocator.php';
	require __DIR__ . '/../app/Admin/Order/class-documents.php';
}

namespace Nashenski {
	use Nashenski\Admin\Import_Sources;
	use Nashenski\Invoice\Number_Allocator;

	$fail = array();
	/**
	 * Records a failed check.
	 *
	 * @param bool   $ok   Result.
	 * @param string $what What was checked.
	 */
	function check( $ok, $what ) {
		if ( ! $ok ) {
			$GLOBALS['fail'][] = $what;
		}
	}

	// The N-18 plugin is off (its constant is not defined): numbering continues after its highest number, once.
	check( false === Import_Sources::n18_paused(), 'not paused once the N-18 plugin is off' );
	check( '0000000156' === $GLOBALS['options']['nashenski_settings_invoice_next_invoice_number'], 'shared numbering continues after the highest N-18 number (155): ' . $GLOBALS['options']['nashenski_settings_invoice_next_invoice_number'] );
	check( isset( $GLOBALS['options'][ Import_Sources::N18_CONTINUED ] ) && 1 === $GLOBALS['locks'], 'the re-read is recorded under the numbering lock' );
	Import_Sources::n18_paused();
	check( 1 === $GLOBALS['locks'], 'the second request does not read again' );

	// A request that read the flag before the first one set it waits for the lock, then finds the flag.
	$GLOBALS['rows'][] = array(
		'type' => 'purchase_order',
		'num'  => '300',
	);
	$GLOBALS['stale']  = true;
	Import_Sources::n18_paused();
	$GLOBALS['stale'] = false;
	check( 2 === $GLOBALS['locks'] && '0000000156' === $GLOBALS['options']['nashenski_settings_invoice_next_invoice_number'], 'a racing request changes nothing' );

	// Only a counter below the other plugin's number moves.
	check( 0 === Number_Allocator::continue_after( array( 'next_invoice_number' => 100 ) ) && '0000000156' === $GLOBALS['options']['nashenski_settings_invoice_next_invoice_number'], 'a higher counter stays' );

	// The N-18 plugin is active again: documents and numbers are paused.
	define( 'NOTASUPTO_VERSION_NUM', '0.3.9' );
	$locks = $GLOBALS['locks'];
	check( true === Import_Sources::n18_paused(), 'paused while the N-18 plugin is active' );
	try {
		Number_Allocator::manual( new \FakeOrder(), 500 );
		check( false, 'a typed invoice number is refused while paused' );
	} catch ( \RuntimeException $e ) {
		check( Import_Sources::paused_reason() === $e->getMessage() && $locks === $GLOBALS['locks'], 'no number is taken, and the reason is given' );
	}

	$GLOBALS['order'] = new \FakeOrder();
	Admin\Order\Documents::generate_documents( 7 ); // Would need the document classes if it went on.
	Admin\Order\Documents::generate_refunded_documents( 8, 7 );
	check( 1 === count( $GLOBALS['order']->notes ) && 'yes' === $GLOBALS['order']->get_meta( Admin\Order\Documents::PAUSED_META ), 'skipped automatic documents leave one note on the order' );

	// The error notice, for users who manage the shop.
	ob_start();
	Import_Sources::n18_notice();
	check( '' === ob_get_clean(), 'no notice for users who cannot manage the shop' );
	$GLOBALS['can'] = true;
	ob_start();
	Import_Sources::n18_notice();
	$html = ob_get_clean();
	check( false !== strpos( $html, 'notice-error' ) && false !== strpos( $html, Import_Sources::paused_reason() ) && false !== strpos( $html, '/admin/plugins.php' ), 'the error notice gives the reason and links to Plugins' );

	if ( $fail ) {
		fwrite( STDERR, 'FAIL: ' . implode( "\nFAIL: ", $fail ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI output.
		exit( 1 );
	}
	echo "PASS: 11 N-18 pause checks\n";
}
