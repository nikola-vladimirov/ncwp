<?php // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride, Universal.Namespaces, WordPress.WP.AlternativeFunctions, WordPress.DB.SlowDBQuery -- offline CLI test script: no WordPress, so stubs, unused stub parameters and plain-text output are on purpose.
/**
 * Offline check (0.21.7): after the import of the mreja.net N-18 plugin, while it is active, Nashenski
 * Commerce makes no documents and takes no numbers, says so, and notes the orders it skipped. Once the
 * plugin is off, the orders it documented meanwhile get its numbers and the counters continue after its
 * highest number in both sequences (all five of its document types), once, also when requests race.
 *
 * Run: php tests/n18-pause.php (it runs itself again with "active" for the plugin-active cases, because
 * the plugin's constant cannot be undefined).
 *
 * @package Nashenski_Commerce
 */

namespace Automattic\WooCommerce\Utilities {
	/**
	 * HPOS stub.
	 */
	class OrderUtil {
		/**
		 * HPOS is on.
		 *
		 * @return bool
		 */
		public static function custom_orders_table_usage_is_enabled() {
			return true;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DB_NAME', 'test' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'DAY_IN_SECONDS', 86400 );
	define( 'ARRAY_A', 'ARRAY_A' );
	$active = 'active' === ( $argv[1] ?? '' );
	if ( $active ) {
		define( 'NOTASUPTO_VERSION_NUM', '0.3.9' );
	}

	/**
	 * A fresh site: N-18 imported (rows up to id 4), its documents table, our counters.
	 *
	 * @param array $options Options added.
	 * @param array $rows    N-18 documents: id, type, num, order_id.
	 */
	function fresh( $options, $rows ) {
		$GLOBALS['options']    = $options + array(
			'nashenski_imported_sources' => array(
				'mreja_n18' => array(
					'time'        => 1,
					'n18_last_id' => 4,
				),
			),
			'nashenski_settings_invoice_next_invoice_number' => '0000000101',
		);
		$GLOBALS['rows']       = $rows;
		$GLOBALS['meta']       = array();
		$GLOBALS['orders']     = array();
		$GLOBALS['locks']      = 0;
		$GLOBALS['lock_busy']  = false;
		$GLOBALS['stale']      = false;
		$GLOBALS['admin']      = true;
		$GLOBALS['caps']       = array( 'manage_woocommerce', 'activate_plugins' );
		$GLOBALS['n18_reads']  = 0;
		$GLOBALS['scheduled']  = array();
		$GLOBALS['logged']     = array();
		$GLOBALS['nra']        = 0;
		$GLOBALS['save_fails'] = false;
	}

	/**
	 * Logger stub: keeps the errors.
	 *
	 * @return object
	 */
	function wc_get_logger() {
		return new class() {
			/**
			 * Error.
			 *
			 * @param string $message Message.
			 */
			public function error( $message ) {
				$GLOBALS['logged'][] = $message;
			}
		};
	}
	/**
	 * Cron stub: the next run of an event.
	 *
	 * @param string $hook Event.
	 * @return int|false
	 */
	function wp_next_scheduled( $hook ) {
		return in_array( $hook, $GLOBALS['scheduled'], true ) ? time() : false;
	}
	/**
	 * Cron stub: schedules one run.
	 *
	 * @param int    $time Time.
	 * @param string $hook Event.
	 */
	function wp_schedule_single_event( $time, $hook ) {
		$GLOBALS['scheduled'][] = $hook;
	}
	/**
	 * Order query stub: the orders that carry a meta key.
	 *
	 * @param array $args Query.
	 * @return array
	 */
	function wc_get_orders( $args ) {
		$ids = array();
		foreach ( $GLOBALS['orders'] as $id => $order ) {
			if ( $order->get_meta( $args['meta_key'] ) ) {
				$ids[] = $id;
			}
		}
		return array_slice( $ids, 0, $args['limit'] );
	}

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
	 * Option delete stub.
	 *
	 * @param string $name Option.
	 */
	function delete_option( $name ) {
		unset( $GLOBALS['options'][ $name ] );
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
	 * Cache stub.
	 */
	function wp_cache_add_non_persistent_groups() {}
	/**
	 * Cache read stub: nothing is cached.
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
	 * Translation stub.
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
	 * Admin request stub: set $GLOBALS['admin'].
	 *
	 * @return bool
	 */
	function is_admin() {
		return $GLOBALS['admin'];
	}
	/**
	 * Not an AJAX request.
	 *
	 * @return bool
	 */
	function wp_doing_ajax() {
		return false;
	}
	/**
	 * Not a cron request.
	 *
	 * @return bool
	 */
	function wp_doing_cron() {
		return false;
	}
	/**
	 * Capability stub: set $GLOBALS['caps'].
	 *
	 * @param string $cap Capability.
	 * @return bool
	 */
	function current_user_can( $cap ) {
		return in_array( $cap, $GLOBALS['caps'], true );
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
	 * Uploads stub: no saved PDF files.
	 *
	 * @return array
	 */
	function wp_upload_dir() {
		return array(
			'basedir' => __DIR__ . '/no-uploads',
			'baseurl' => '/uploads',
		);
	}
	/**
	 * Order lookup: one order object per id.
	 *
	 * @param int $id Order id.
	 * @return FakeOrder
	 */
	function wc_get_order( $id ) {
		$GLOBALS['orders'][ $id ] = $GLOBALS['orders'][ $id ] ?? new FakeOrder( (int) $id );
		return $GLOBALS['orders'][ $id ];
	}

	/**
	 * WooCommerce order class stub.
	 */
	class WC_Order {}

	/**
	 * An order meta row, as WooCommerce hands it out.
	 */
	class FakeMeta {
		/**
		 * Key.
		 *
		 * @var string
		 */
		public $key;
		/**
		 * Row id.
		 *
		 * @var int
		 */
		public $id = 0;
		/**
		 * Value.
		 *
		 * @var mixed
		 */
		public $value;
		/**
		 * Constructor.
		 *
		 * @param string $key   Key.
		 * @param mixed  $value Value.
		 */
		public function __construct( $key, $value ) {
			$this->key   = $key;
			$this->value = $value;
		}
		/**
		 * Marks it saved.
		 */
		public function apply_changes() {}
	}

	/**
	 * Order fixture: meta saved to the fake meta table, and notes.
	 */
	class FakeOrder extends WC_Order {
		/**
		 * Id.
		 *
		 * @var int
		 */
		public $id;
		/**
		 * Meta.
		 *
		 * @var array
		 */
		public $meta = array();
		/**
		 * Notes.
		 *
		 * @var array
		 */
		public $notes = array();
		/**
		 * Constructor.
		 *
		 * @param int $id Id.
		 */
		public function __construct( $id ) {
			$this->id = $id;
		}
		/**
		 * Id.
		 *
		 * @return int
		 */
		public function get_id() {
			return $this->id;
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
		 * Meta rows.
		 *
		 * @return array
		 */
		public function get_meta_data() {
			$rows = array();
			foreach ( $this->meta as $key => $value ) {
				$rows[] = new FakeMeta( $key, $value );
			}
			return $rows;
		}
		/**
		 * Save: into the fake meta table.
		 *
		 * @return int
		 * @throws \Exception With $GLOBALS['save_fails'], as a failed HPOS write.
		 */
		public function save() {
			if ( $GLOBALS['save_fails'] ) {
				throw new \Exception( 'Database write failed.' ); // As an HPOS save does.
			}
			$GLOBALS['meta'][ $this->id ] = $this->meta;
			return $this->id;
		}
		/**
		 * Meta delete.
		 *
		 * @param string $key Key.
		 */
		public function delete_meta_data( $key ) {
			unset( $this->meta[ $key ] );
		}
		/**
		 * Refunds: none.
		 *
		 * @return array
		 */
		public function get_refunds() {
			return array();
		}
		/**
		 * Note.
		 *
		 * @param string $note Note.
		 */
		public function add_order_note( $note ) {
			$this->notes[] = $note;
		}
	}

	/**
	 * Database fixture: the numbering lock, the options table, the order meta table and the N-18 plugin's table.
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
		 * @param string $query   Query.
		 * @param mixed  ...$args Values, or one array of them.
		 * @return array
		 */
		public function prepare( $query, ...$args ) {
			return array( $query, ( 1 === count( $args ) && is_array( $args[0] ) ) ? $args[0] : $args );
		}
		/**
		 * Orders whose meta $keys hold $number, leaving out $except.
		 *
		 * @param array $keys   Meta keys.
		 * @param int   $number Number.
		 * @param int   $except Order id.
		 * @return int|null
		 */
		private function meta_with( $keys, $number, $except ) {
			foreach ( $GLOBALS['meta'] as $id => $meta ) {
				foreach ( $keys as $key ) {
					if ( $id !== $except && isset( $meta[ $key ] ) && (int) $meta[ $key ] === $number ) {
						return $id;
					}
				}
			}
			return null;
		}
		/**
		 * One value.
		 *
		 * @param array $p Prepared query.
		 * @return mixed
		 */
		public function get_var( $p ) {
			list( $q, $a ) = $p;
			if ( 0 === strpos( $q, 'SELECT GET_LOCK' ) ) {
				++$GLOBALS['locks'];
				return $GLOBALS['lock_busy'] ? '0' : '1';
			}
			if ( 0 === strpos( $q, 'SHOW TABLES' ) ) {
				return $a[0];
			}
			if ( 0 === strpos( $q, 'SELECT option_value' ) ) {
				return $GLOBALS['options'][ $a[1] ] ?? null;
			}
			if ( 0 === strpos( $q, 'SELECT MAX(id)' ) ) {
				return max( array_column( $GLOBALS['rows'], 'id' ) );
			}
			if ( 0 === strpos( $q, 'SELECT order_id FROM' ) ) {
				++$GLOBALS['n18_reads'];
				$except = array_pop( $a );
				$number = array_pop( $a );
				$types  = array_slice( $a, 1 );
				foreach ( $GLOBALS['rows'] as $row ) {
					if ( in_array( $row['type'], $types, true ) && (int) $row['num'] === (int) $number && (int) $row['order_id'] !== (int) $except ) {
						return (string) $row['order_id'];
					}
				}
				return null;
			}
			if ( false !== strpos( $q, 'MAX(CAST(meta_value' ) ) {
				$high = 0;
				foreach ( $GLOBALS['meta'] as $meta ) {
					foreach ( array_slice( $a, 1 ) as $key ) {
						$high = max( $high, (int) ( $meta[ $key ] ?? 0 ) );
					}
				}
				return (string) $high;
			}
			if ( false !== strpos( $q, 'WHERE meta_key IN' ) ) {
				$keys = array_slice( $a, 2, count( $a ) - 5 );
				return $this->meta_with( $keys, (int) $a[ count( $a ) - 3 ], (int) $a[ count( $a ) - 1 ] );
			}
			return '1';
		}
		/**
		 * Rows.
		 *
		 * @param array $p Prepared query.
		 * @return array
		 */
		public function get_results( $p ) {
			list( $q, $a ) = $p;
			if ( false !== strpos( $q, 'AS meta_id' ) ) {
				$value = $GLOBALS['meta'][ $a[3] ][ $a[4] ] ?? null;
				return null === $value ? array() : array(
					(object) array(
						'meta_id'    => 1,
						'meta_value' => $value,
					),
				);
			}
			if ( false !== strpos( $a[0], 'products' ) ) {
				return array();
			}
			if ( false !== strpos( $q, 'GROUP BY type' ) ) {
				$max = array();
				foreach ( $GLOBALS['rows'] as $row ) {
					$max[ $row['type'] ] = max( $max[ $row['type'] ] ?? 0, (int) $row['num'] );
				}
				$out = array();
				foreach ( $max as $type => $num ) {
					$out[] = array(
						'type' => $type,
						'num'  => (string) $num,
					);
				}
				return $out;
			}
			$after = $a[2] ?? 0;
			return array_values(
				array_filter(
					$GLOBALS['rows'],
					function ( $row ) use ( $after ) {
						return $row['id'] > $after;
					}
				)
			);
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
	 * The order document class: counts the documents made.
	 */
	class Fake_NRA {
		/**
		 * Makes the document.
		 */
		public function generate_file() {
			++$GLOBALS['nra'];
		}
	}
	class_alias( 'Fake_NRA', 'Nashenski\Invoice\Document\NRA' );

	require __DIR__ . '/../app/Admin/class-import-sources.php';
	require __DIR__ . '/../app/Invoice/class-number-allocator.php';
	require __DIR__ . '/../app/Admin/Order/class-documents.php';
}

namespace Nashenski {
	use Nashenski\Admin\Import_Sources;
	use Nashenski\Admin\Order\Documents;
	use Nashenski\Invoice\Number_Allocator;

	$fail   = array();
	$checks = 0;
	/**
	 * Records a check.
	 *
	 * @param bool   $ok   Result.
	 * @param string $what What was checked.
	 */
	function check( $ok, $what ) {
		++$GLOBALS['checks'];
		if ( ! $ok ) {
			$GLOBALS['fail'][] = $what;
		}
	}
	/**
	 * A row of the N-18 documents table.
	 *
	 * @param int    $id    Row id.
	 * @param string $type  Document type.
	 * @param int    $num   Number.
	 * @param int    $order Order id.
	 * @return array
	 */
	function row( $id, $type, $num, $order ) {
		return array(
			'id'       => $id,
			'type'     => $type,
			'num'      => str_pad( $num, 10, '0', STR_PAD_LEFT ),
			'order_id' => $order,
		);
	}
	/**
	 * A counter.
	 *
	 * @param string $name Sequence setting.
	 * @return string
	 */
	function counter( $name ) {
		return $GLOBALS['options'][ 'nashenski_settings_invoice_' . $name ] ?? '';
	}

	$before = array( row( 1, 'purchase_order', 150, 1 ), row( 2, 'credit_note', 155, 1 ), row( 3, 'purchase_order_cancel', 160, 1 ), row( 4, 'debit_note', 158, 2 ) );

	if ( ! $GLOBALS['active'] ) {
		// An import before 0.21.7 (no row id), on a storefront request: not busy, numbering continues inline.
		fresh( array(), $before );
		unset( $GLOBALS['options']['nashenski_imported_sources']['mreja_n18']['n18_last_id'] );
		$GLOBALS['admin'] = false;
		check( false === Import_Sources::n18_paused() && ! Import_Sources::$busy, 'an old import on a storefront request is not busy' );
		check( '0000000161' === counter( 'next_invoice_number' ) && 4 === $GLOBALS['options']['nashenski_imported_sources']['mreja_n18']['n18_last_id'], 'its numbering continues after all five types and starts the catch-up from here' );

		// Shared numbering, storefront request. During the pause the N-18 plugin documented order 900 (purchase
		// order 170) and order 901 (invoice 170): both hold 170 in its two sequences, ours has one.
		fresh( array(), array_merge( $before, array( row( 5, 'purchase_order', 170, 900 ), row( 6, 'invoice', 170, 901 ) ) ) );
		$GLOBALS['admin'] = false;
		check( false === Import_Sources::n18_paused(), 'not paused once the plugin is off, on any request' );
		check( '0000000170' === wc_get_order( 900 )->get_meta( 'nashenski_order_number' ), 'an order the N-18 plugin documented during the pause keeps its number' );
		check( array( 5 ) === array_column( wc_get_order( 900 )->get_meta( Import_Sources::LEGACY_META )['mreja_n18']['notasupto_documents'] ?? array(), 'id' ), 'and keeps its N-18 document rows' );
		check( ! wc_get_order( 901 )->get_meta( 'nashenski_order_invoice_number' ) && 1 === count( wc_get_order( 901 )->notes ) && false !== strpos( wc_get_order( 901 )->notes[0], 'could not take it over' ), 'the other order with 170 is not numbered twice and gets a note' );
		check( ! wc_get_order( 900 )->notes, 'an order taken over gets no note' );
		check( '0000000171' === counter( 'next_invoice_number' ), 'shared numbering continues after the highest N-18 number: ' . counter( 'next_invoice_number' ) );
		check( 6 === $GLOBALS['options']['nashenski_imported_sources']['mreja_n18']['n18_last_id'], 'the catch-up remembers its last row' );
		$locks = $GLOBALS['locks'];
		Import_Sources::n18_paused();
		check( $locks === $GLOBALS['locks'], 'a second request does not read again' );

		// A request that read the flag before the first one set it waits for the lock, then finds the flag.
		$GLOBALS['stale'] = true;
		Import_Sources::n18_paused();
		$GLOBALS['stale'] = false;
		check( '0000000171' === counter( 'next_invoice_number' ), 'a racing request changes nothing' );

		// Within a day of the continue, a request that still had the N-18 plugin loaded gave out 171: skipped.
		$GLOBALS['rows'][] = row( 7, 'purchase_order', 171, 902 );
		check( 172 === Number_Allocator::allocate( wc_get_order( 903 ), 'nashenski_order_number', 'next_invoice_number' ), 'a number the N-18 plugin gave out is not given again' );

		// A day later automatic numbers no longer read its table; a typed number still does.
		$GLOBALS['options'][ Import_Sources::N18_CONTINUED ] = time() - 2 * DAY_IN_SECONDS;
		$GLOBALS['rows'][]                                   = row( 8, 'purchase_order', 173, 904 );
		$GLOBALS['n18_reads']                                = 0;
		check( 173 === Number_Allocator::allocate( wc_get_order( 905 ), 'nashenski_order_number', 'next_invoice_number' ) && 0 === $GLOBALS['n18_reads'], 'after a day, automatic numbers skip the N-18 table' );
		$GLOBALS['rows'][] = row( 9, 'purchase_order', 180, 907 );
		check( '' !== Number_Allocator::manual( wc_get_order( 906 ), 180 ) && 1 === $GLOBALS['n18_reads'], 'a typed number is still checked against it' );

		// Only a counter below the other plugin's number moves.
		check(
			0 === Number_Allocator::continue_after(
				function () {
					return array( 'next_invoice_number' => 100 );
				}
			),
			'a higher counter stays'
		);

		// Separate counters: order documents and cancellations, invoices with credit and debit notes.
		fresh( array( 'nashenski_settings_invoice_next_invoice_separate_number' => '0000000050' ), array_merge( $before, array( row( 5, 'invoice', 130, 3 ) ) ) );
		Import_Sources::n18_paused();
		check( '0000000161' === counter( 'next_invoice_number' ) && '0000000159' === counter( 'next_invoice_separate_number' ), 'separate counters continue after their own types: ' . counter( 'next_invoice_number' ) . ', ' . counter( 'next_invoice_separate_number' ) );

		// The lock times out: the order waits with one note, and a scheduled retry makes its documents.
		fresh( array(), $before );
		$GLOBALS['lock_busy'] = true;
		check( Documents::paused( wc_get_order( 7 ) ) && Import_Sources::$busy && false !== strpos( Import_Sources::paused_reason(), 'busy' ), 'a lock timeout skips the documents with its own reason' );
		Documents::paused( wc_get_order( 7 ) ); // The proforma and the other documents of the same checkout.
		check( 1 === count( wc_get_order( 7 )->notes ) && 'yes' === wc_get_order( 7 )->get_meta( Documents::RETRY_META ) && in_array( Documents::RETRY_HOOK, $GLOBALS['scheduled'], true ), 'the order waits, with one note, and a retry is scheduled' );
		$GLOBALS['scheduled'] = array();
		Documents::retry();
		check( array( Documents::RETRY_HOOK ) === $GLOBALS['scheduled'] && 0 === $GLOBALS['nra'] && wc_get_order( 7 )->get_meta( Documents::RETRY_META ), 'still busy: the retry tries again later' );
		$GLOBALS['lock_busy'] = false;
		Documents::retry();
		check( 1 === $GLOBALS['nra'] && ! wc_get_order( 7 )->get_meta( Documents::RETRY_META ) && 1 === count( wc_get_order( 7 )->notes ), 'free again: the retry makes the documents' );

		// An order that cannot be saved during the catch-up: busy, logged, no fatal error on admin screens.
		fresh( array(), array_merge( $before, array( row( 5, 'purchase_order', 170, 900 ) ) ) );
		$GLOBALS['save_fails'] = true;
		ob_start();
		Import_Sources::n18_notice();
		$html = ob_get_clean();
		check( Import_Sources::$busy && '' === $html && $GLOBALS['logged'] && ! isset( $GLOBALS['options'][ Import_Sources::N18_CONTINUED ] ), 'a failed save during the catch-up is busy and logged, not fatal' );

		// The cases with the N-18 plugin active run in a second process.
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' active 2>&1', $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- the plugin-active cases.
		check( 0 === $code, 'plugin active: ' . implode( ' / ', $output ) );
	} else {
		// Reactivated after the counters continued: paused again, and they continue again next time.
		fresh( array( Import_Sources::N18_CONTINUED => 1 ), $before );
		check( true === Import_Sources::n18_paused() && ! isset( $GLOBALS['options'][ Import_Sources::N18_CONTINUED ] ), 'a reactivated plugin pauses again and clears the flag' );

		// An import before 0.21.7 (no row id): the pause starts the catch-up from its last row.
		fresh( array(), $before );
		unset( $GLOBALS['options']['nashenski_imported_sources']['mreja_n18']['n18_last_id'] );
		Import_Sources::n18_paused();
		check( 4 === ( $GLOBALS['options']['nashenski_imported_sources']['mreja_n18']['n18_last_id'] ?? null ), 'an old import gets its catch-up start while paused' );

		// No numbers, automatic or typed, and the reason is given; no lock is taken.
		fresh( array(), $before );
		try {
			Number_Allocator::allocate( wc_get_order( 7 ), 'nashenski_order_number', 'next_invoice_number' );
			check( false, 'an automatic number is refused while paused' );
		} catch ( \RuntimeException $e ) {
			check( Import_Sources::paused_reason() === $e->getMessage() && 0 === $GLOBALS['locks'], 'an automatic number is refused, with the reason' );
		}
		try {
			Number_Allocator::manual( wc_get_order( 7 ), 500 );
			check( false, 'a typed number is refused while paused' );
		} catch ( \RuntimeException $e ) {
			check( 0 === $GLOBALS['locks'], 'a typed number is refused' );
		}

		// Skipped automatic documents leave one note that says what to do.
		Documents::generate_documents( 7 );
		Documents::generate_refunded_documents( 8, 7 );
		$notes = wc_get_order( 7 )->notes;
		check( 1 === count( $notes ) && false !== strpos( $notes[0], 'keeps its numbers' ) && 0 === $GLOBALS['nra'], 'skipped documents leave one note' );

		// The error notice: for shop managers, with the link only for those who can deactivate plugins.
		$GLOBALS['caps'] = array();
		ob_start();
		Import_Sources::n18_notice();
		check( '' === ob_get_clean(), 'no notice for users who cannot manage the shop' );
		$GLOBALS['caps'] = array( 'manage_woocommerce' );
		ob_start();
		Import_Sources::n18_notice();
		$html = ob_get_clean();
		check( false !== strpos( $html, 'Ask an administrator' ) && false === strpos( $html, 'plugins.php' ), 'a shop manager is asked to get an administrator' );
		$GLOBALS['caps'] = array( 'manage_woocommerce', 'activate_plugins' );
		ob_start();
		Import_Sources::n18_notice();
		$html = ob_get_clean();
		check( false !== strpos( $html, 'notice-error' ) && false !== strpos( $html, Import_Sources::paused_reason() ) && false !== strpos( $html, '/admin/plugins.php' ), 'an administrator gets the reason and the Plugins link' );
	}

	if ( $fail ) {
		fwrite( STDERR, 'FAIL: ' . implode( "\nFAIL: ", $fail ) . "\n" );
		exit( 1 );
	}
	echo 'PASS: ' . $checks . ' N-18 pause checks' . ( $GLOBALS['active'] ? ' (plugin active)' : '' ) . "\n";
}
