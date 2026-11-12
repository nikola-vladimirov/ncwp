<?php // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride, Universal.Namespaces, WordPress.WP.AlternativeFunctions, WordPress.DB.SlowDBQuery -- offline CLI test script: no WordPress, so stubs, unused stub parameters and plain-text output are on purpose.
/**
 * Offline check (0.21.7): the mreja.net N-18 plugin is imported only while it is off. Active again after
 * its import, Nashenski Commerce makes no documents and takes no numbers, says so per capability and
 * notes the orders it skipped; once it is off, its import runs again and takes over the numbers it gave
 * out meanwhile, continuing both sequences after all five of its document types (shared and separate).
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

namespace Nashenski\Admin {
	/**
	 * Settings page URL stub.
	 */
	class Settings {
		/**
		 * URL of a settings section.
		 *
		 * @param string $section Section.
		 * @return string
		 */
		public static function url( $section = '' ) {
			return '/settings/' . $section;
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/' );
	define( 'DB_NAME', 'test' );
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'ARRAY_A', 'ARRAY_A' );
	$active = 'active' === ( $argv[1] ?? '' );
	if ( $active ) {
		define( 'NOTASUPTO_VERSION_NUM', '0.3.9' );
	}

	/**
	 * A fresh site with the N-18 plugin's documents table and our counters.
	 *
	 * @param array $options Options.
	 * @param array $rows    N-18 documents: id, type, num, order_id.
	 */
	function fresh( $options, $rows ) {
		$GLOBALS['options']    = $options + array( 'nashenski_settings_invoice_next_invoice_number' => '0000000101' );
		$GLOBALS['rows']       = $rows;
		$GLOBALS['meta']       = array();
		$GLOBALS['orders']     = array();
		$GLOBALS['locks']      = 0;
		$GLOBALS['caps']       = array( 'manage_woocommerce', 'activate_plugins' );
		$GLOBALS['files_gone'] = false;
	}

	/**
	 * Option stub.
	 *
	 * @param string $name     Option.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	function get_option( $name, $fallback = false ) {
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
	 * Plugin setting write stub.
	 *
	 * @param string $group Group.
	 * @param string $name  Name.
	 * @param mixed  $value Value.
	 */
	function nashenski_set_option( $group, $name, $value ) {
		update_option( 'nashenski_settings_' . $group . '_' . $name, $value );
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
	 * Cache flush stub.
	 */
	function wp_cache_flush() {}
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
	 * Plural translation stub.
	 *
	 * @param string $single Singular.
	 * @param string $plural Plural.
	 * @param int    $count  Count.
	 * @return string
	 */
	function _n( $single, $plural, $count ) {
		return 1 === $count ? $single : $plural;
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
	 * @param string $text Text.
	 */
	function esc_html_e( $text ) {
		echo $text;
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
	 * Kses stub.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	function wp_kses_post( $html ) {
		return $html;
	}
	/**
	 * Kses stub.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	function wp_kses( $html ) {
		return $html;
	}
	/**
	 * Key stub.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return $key;
	}
	/**
	 * Admin URL stub.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function admin_url( $path = '' ) {
		return '/admin/' . $path;
	}
	/**
	 * Nonce URL stub.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function wp_nonce_url( $url ) {
		return $url . '&_wpnonce=1';
	}
	/**
	 * Nonce check stub.
	 *
	 * @return bool
	 */
	function check_admin_referer() {
		return true;
	}
	/**
	 * Request value stub: set $GLOBALS['query'].
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function nashenski_query_arg( $key ) {
		return $GLOBALS['query'][ $key ] ?? '';
	}
	/**
	 * Unslash stub.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return $value;
	}
	/**
	 * Date stub.
	 *
	 * @return string
	 */
	function wp_date() {
		return '2026-11-11';
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
		return $GLOBALS['files_gone'] ? array() : array( 'woocommerce-notasupto/index.php' => array( 'Name' => 'WooCommerce Наредба Н-18 облекчен режим плъгин' ) );
	}
	/**
	 * Active plugins: the N-18 plugin while its constant is defined.
	 *
	 * @return bool
	 */
	function is_plugin_active() {
		return defined( 'NOTASUPTO_VERSION_NUM' );
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
	 * Stops the request.
	 *
	 * @param string $message Message.
	 * @throws RuntimeException Always.
	 */
	function wp_die( $message ) {
		throw new RuntimeException( 'died: ' . $message );
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
		 * Order number.
		 *
		 * @return int
		 */
		public function get_order_number() {
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
		 */
		public function save() {
			$GLOBALS['meta'][ $this->id ] = $this->meta;
			return $this->id;
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
		 * Like escape stub.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		public function esc_like( $text ) {
			return $text;
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
				return '1';
			}
			if ( 0 === strpos( $q, 'SELECT RELEASE_LOCK' ) ) {
				return '1';
			}
			if ( 0 === strpos( $q, 'SHOW TABLES' ) ) {
				return $a[0];
			}
			if ( 0 === strpos( $q, 'SELECT 1 FROM' ) ) {
				return $GLOBALS['rows'] ? '1' : null;
			}
			if ( 0 === strpos( $q, 'SELECT MAX(id)' ) ) {
				return $GLOBALS['rows'] ? (string) max( array_column( $GLOBALS['rows'], 'id' ) ) : null;
			}
			if ( 0 === strpos( $q, 'SELECT option_value' ) ) {
				return $GLOBALS['options'][ $a[1] ] ?? null;
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
				$keys   = array_slice( $a, 2, count( $a ) - 5 );
				$number = (int) $a[ count( $a ) - 3 ];
				$except = (int) $a[ count( $a ) - 1 ];
				foreach ( $GLOBALS['meta'] as $id => $meta ) {
					foreach ( $keys as $key ) {
						if ( $id !== $except && isset( $meta[ $key ] ) && (int) $meta[ $key ] === $number ) {
							return $id;
						}
					}
				}
			}
			return null;
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

	require __DIR__ . '/../app/Admin/class-migration.php';
	require __DIR__ . '/../app/Admin/class-import-sources.php';
	require __DIR__ . '/../app/Admin/class-help-page.php';
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
	/**
	 * The Help page's import table.
	 *
	 * @return string
	 */
	function help_import() {
		ob_start();
		( new \ReflectionMethod( Admin\Help_Page::class, 'output_import' ) )->invoke( null );
		return ob_get_clean();
	}
	/**
	 * The import entry of the N-18 plugin.
	 *
	 * @return array
	 */
	function n18_done() {
		return $GLOBALS['options'][ Import_Sources::DONE_OPTION ]['mreja_n18'] ?? array();
	}

	// All five types: order documents with cancellations, invoices with credit and debit notes.
	$five = array( row( 1, 'purchase_order', 150, 1 ), row( 2, 'credit_note', 155, 1 ), row( 3, 'purchase_order_cancel', 160, 1 ), row( 4, 'debit_note', 158, 2 ), row( 5, 'invoice', 120, 2 ) );

	if ( ! $GLOBALS['active'] ) {
		// A store without any N-18 data is untouched; its state is stored the first time a document asks.
		fresh( array(), array() );
		check( false === Import_Sources::n18_paused() && 'none' === $GLOBALS['options'][ Import_Sources::N18_STATE ], 'a store without N-18 data is untouched, and its state is stored' );

		// Its settings alone hold no numbers: no pause, but the import is offered.
		fresh( array( 'notasuptooptions' => array( 'seller_company_name' => 'Shop' ) ), array() );
		Import_Sources::n18_refresh();
		check( 'none' === Import_Sources::n18_state() && false === Import_Sources::n18_paused() && isset( Import_Sources::open_names()['mreja_n18'] ), 'its settings alone: no pause, the import is offered' );

		// Its documents, but our counters are already past its numbers: nothing can clash, no pause.
		fresh(
			array(
				'nashenski_settings_invoice_next_invoice_number' => '0000000300',
				'nashenski_settings_invoice_next_invoice_separate_number' => '0000000300',
			),
			$five
		);
		Import_Sources::n18_refresh();
		check( 'none' === Import_Sources::n18_state() && isset( Import_Sources::open_names()['mreja_n18'] ), 'counters past its numbers: no pause, the import is offered' );

		// It was active meanwhile (n18_paused() set "first" then) and reused 200, which order 7 already had from us:
		// still paused after it is off, and its first import notes and lists the clash.
		$GLOBALS['options'][ Import_Sources::N18_STATE ] = 'first';
		$GLOBALS['meta'][7]                              = array( 'nashenski_order_number' => '0000000200' );
		$GLOBALS['rows'][]                               = row( 6, 'purchase_order', 200, 6 );
		Import_Sources::n18_refresh();
		check( 'first' === Import_Sources::n18_state() && Import_Sources::n18_paused(), 'after it was active, documents stay paused once it is off, until the import' );
		$report = Import_Sources::import( 'mreja_n18' );
		check( 1 === count( wc_get_order( 6 )->notes ) && in_array( 'Orders whose N-18 number clashes; correct their documents: #6', $report, true ) && ! wc_get_order( 1 )->notes, 'its first import notes and lists the reused number: ' . implode( ' / ', $report ) );
		check( 'done' === Import_Sources::n18_state() && false === Import_Sources::n18_paused(), 'after that import documents go on' );

		// Its documents alone (its files and settings deleted): the Help page offers the import, and it works.
		fresh( array(), $five );
		$GLOBALS['files_gone'] = true;
		check( Import_Sources::n18_paused() && 'first' === Import_Sources::n18_state(), 'its documents alone pause new numbers on the first request' );
		check( isset( Import_Sources::sources()['mreja_n18'] ) && false !== strpos( help_import(), 'Import</a>' ), 'its documents alone are offered for import' );
		Import_Sources::import( 'mreja_n18' );
		check( '0000000150' === wc_get_order( 1 )->get_meta( 'nashenski_order_number' ) && 'done' === Import_Sources::n18_state() && false === Import_Sources::n18_paused(), 'the import works from its documents alone' );

		// N-18 documents that are not imported yet: no documents and no numbers until the import, which the notice links to.
		fresh( array( 'nashenski_settings_invoice_next_invoice_separate_number' => '0000000050' ), $five );
		Import_Sources::n18_refresh();
		check( 'first' === Import_Sources::n18_state() && Import_Sources::n18_paused() && false !== strpos( Import_Sources::paused_reason(), 'Run its import' ), 'its data, not imported yet: paused until the import' );
		ob_start();
		Import_Sources::n18_notice();
		check( false !== strpos( ob_get_clean(), '/settings/help#import' ), 'the notice links to the import while the plugin is off' );
		try {
			Number_Allocator::allocate( wc_get_order( 9 ), 'nashenski_order_number', 'next_invoice_number' );
			check( false, 'no number before the import' );
		} catch ( \RuntimeException $e ) {
			check( 0 === $GLOBALS['locks'], 'no number before the import' );
		}
		check( false !== strpos( help_import(), 'Import</a>' ), 'the Help page offers the import while the plugin is off' );

		// The import: numbers taken over, each sequence after its own types, documents go on.
		Import_Sources::import( 'mreja_n18' );
		check( '0000000150' === wc_get_order( 1 )->get_meta( 'nashenski_order_number' ) && '0000000120' === wc_get_order( 2 )->get_meta( 'nashenski_order_invoice_number' ), 'the import takes over the numbers' );
		check( '0000000161' === counter( 'next_invoice_number' ) && '0000000159' === counter( 'next_invoice_separate_number' ), 'separate counters continue after their own types: ' . counter( 'next_invoice_number' ) . ', ' . counter( 'next_invoice_separate_number' ) );
		check( 'done' === Import_Sources::n18_state() && false === Import_Sources::n18_paused() && 5 === n18_done()['last_id'], 'imported: documents go on, and the last row is kept' );

		// Nashenski gives out 161 to order 5. The plugin runs again (on and off without an admin page) and its
		// own counter reuses 161 for order 4; it also documents order 3.
		$GLOBALS['meta'][5]                                   = array( 'nashenski_order_number' => '0000000161' );
		$GLOBALS['rows']                                      = array_merge( $five, array( row( 6, 'purchase_order', 170, 3 ), row( 7, 'invoice', 165, 3 ), row( 8, 'purchase_order_cancel', 175, 1 ), row( 9, 'purchase_order', 161, 4 ) ) );
		$GLOBALS['options'][ Import_Sources::DISMISS_OPTION ] = array( 'mreja_n18' );
		Import_Sources::n18_refresh();
		check( 'again' === Import_Sources::n18_state() && Import_Sources::n18_paused() && false !== strpos( Import_Sources::paused_reason(), 'run again' ), 'its table grew after the import: paused, also with the plugin off' );
		check( ! in_array( 'mreja_n18', $GLOBALS['options'][ Import_Sources::DISMISS_OPTION ], true ) && isset( Import_Sources::open_names()['mreja_n18'] ), 'the dismissed import notice comes back' );
		check( false !== strpos( help_import(), 'Import</a>' ), 'the Help page offers the import again' );

		// The rerun takes over its new numbers and lists the order whose number clashes, with a note on it.
		$report = Import_Sources::import( 'mreja_n18' );
		check( '0000000170' === wc_get_order( 3 )->get_meta( 'nashenski_order_number' ) && '0000000165' === wc_get_order( 3 )->get_meta( 'nashenski_order_invoice_number' ) && '0000000150' === wc_get_order( 1 )->get_meta( 'nashenski_order_number' ), 'the rerun takes over the new numbers, numbered orders keep theirs' );
		check( ! wc_get_order( 4 )->get_meta( 'nashenski_order_number' ) && 1 === count( wc_get_order( 4 )->notes ) && false !== strpos( wc_get_order( 4 )->notes[0], '0000000161' ) && in_array( 'Orders whose N-18 number clashes; correct their documents: #4', $report, true ), 'a reused number is noted on its order and listed: ' . implode( ' / ', $report ) );
		check( ! wc_get_order( 1 )->notes && ! wc_get_order( 3 )->notes, 'orders without a clash get no note' );
		check( '0000000176' === counter( 'next_invoice_number' ) && '0000000166' === counter( 'next_invoice_separate_number' ), 'the counters continue after its new highest numbers: ' . counter( 'next_invoice_number' ) . ', ' . counter( 'next_invoice_separate_number' ) );
		check( 'done' === Import_Sources::n18_state() && false === Import_Sources::n18_paused() && false !== strpos( help_import(), 'Imported on' ), 'after the rerun documents go on' );
		$report = Import_Sources::import( 'mreja_n18' );
		check( 1 === count( wc_get_order( 4 )->notes ) && ! preg_grep( '/clashes/', $report ), 'another run notes no clash twice' );

		// Its data is gone while a rerun is due: a rerun is impossible, so no pause for ever.
		Import_Sources::n18_refresh();
		$GLOBALS['options'][ Import_Sources::N18_STATE ] = 'again';
		$GLOBALS['rows']                                 = array();
		Import_Sources::n18_refresh();
		check( 'done' === Import_Sources::n18_state() && false === Import_Sources::n18_paused(), 'with its data gone the pause ends' );

		// Shared numbering (empty separate counter): all five types continue the one counter.
		fresh( array(), $five );
		Import_Sources::import( 'mreja_n18' );
		check( '0000000161' === counter( 'next_invoice_number' ) && '' === counter( 'next_invoice_separate_number' ), 'shared numbering continues after all five types and stays shared: ' . counter( 'next_invoice_number' ) );

		// The cases with the N-18 plugin active run in a second process.
		exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' active 2>&1', $output, $code ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- the plugin-active cases.
		check( 0 === $code, 'plugin active: ' . implode( ' / ', $output ) );
	} else {
		// Active and not imported yet: paused, and the import is refused in the Help page, the notice and the handler.
		fresh( array(), $five );
		check( true === Import_Sources::n18_paused() && 'first' === Import_Sources::n18_state() && false !== strpos( Import_Sources::paused_reason(), 'Deactivate it and run its import' ), 'active, not imported: paused until it is off and imported' );
		$past = array(
			'nashenski_settings_invoice_next_invoice_number' => '0000000300',
			Import_Sources::N18_STATE => 'none',
		);
		fresh( $past, $five );
		check( true === Import_Sources::n18_paused() && 'first' === Import_Sources::n18_state(), 'active, not imported, counters past its numbers: paused all the same' );
		fresh( array(), $five );
		$html = help_import();
		check( false !== strpos( $html, 'Deactivate the N-18 plugin first, then import.' ) && false !== strpos( $html, '/admin/plugins.php' ) && false === strpos( $html, 'Import</a>' ), 'the Help page asks to deactivate it first, with the Plugins link' );
		$GLOBALS['caps'] = array( 'manage_woocommerce' );
		check( false !== strpos( help_import(), 'Ask an administrator' ), 'a shop manager is asked to get an administrator' );
		$GLOBALS['caps'] = array( 'manage_woocommerce', 'activate_plugins' );
		ob_start();
		Import_Sources::render();
		check( false !== strpos( ob_get_clean(), 'Deactivate the N-18 plugin first, then import.' ), 'the import notice says the same' );
		$_GET['source'] = 'mreja_n18';
		try {
			Import_Sources::handle();
			check( false, 'the import handler refuses' );
		} catch ( \RuntimeException $e ) {
			check( false !== strpos( $e->getMessage(), 'died: Deactivate the N-18 plugin first' ), 'the import handler refuses' );
		}
		check( array( 'Deactivate the N-18 plugin first, then import.' ) === Import_Sources::import( 'mreja_n18' ) && ! n18_done() && ! wc_get_order( 1 )->get_meta( 'nashenski_order_number' ), 'the import itself refuses and changes nothing' );

		// Active again after its import: no documents and no numbers until it is off and imported again.
		fresh(
			array(
				Import_Sources::DONE_OPTION => array(
					'mreja_n18' => array(
						'time'    => 1,
						'report'  => array(),
						'last_id' => 5,
					),
				),
				Import_Sources::N18_STATE   => 'done',
			),
			$five
		);
		check( true === Import_Sources::n18_paused() && 'again' === Import_Sources::n18_state() && false !== strpos( Import_Sources::paused_reason(), 'Deactivate it at once' ), 'active again after its import: paused, with the reason' );
		try {
			Number_Allocator::allocate( wc_get_order( 7 ), 'nashenski_order_number', 'next_invoice_number' );
			check( false, 'an automatic number is refused while paused' );
		} catch ( \RuntimeException $e ) {
			check( Import_Sources::paused_reason() === $e->getMessage() && 0 === $GLOBALS['locks'], 'an automatic number is refused before the lock, with the reason' );
		}
		try {
			Number_Allocator::manual( wc_get_order( 7 ), 500 );
			check( false, 'a typed number is refused while paused' );
		} catch ( \RuntimeException $e ) {
			check( 0 === $GLOBALS['locks'], 'a typed number is refused' );
		}
		Documents::generate_documents( 7 ); // Would need the document classes if it went on.
		Documents::generate_refunded_documents( 8, 7 );
		$notes = wc_get_order( 7 )->notes;
		check( 1 === count( $notes ) && 0 === strpos( $notes[0], 'No documents were made. The old N-18 plugin is active again' ), 'skipped documents leave one note that says what to do' );

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
	echo 'PASS: ' . $checks . ' N-18 checks' . ( $GLOBALS['active'] ? ' (plugin active)' : '' ) . "\n";
}
