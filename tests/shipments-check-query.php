<?php
/**
 * Offline: check_shipments() query (no meta_query on legacy, NOT EXISTS filter instead; kept on HPOS), limit and window, the date cursor rotation, and saves only for changed orders. Run: php tests/shipments-check-query.php
 *
 * @package Nashenski_Commerce
 */

namespace Automattic\WooCommerce\Utilities {
	/**
	 * Orderutil.
	 */
	class OrderUtil {
		/**
		 * Custom orders table usage is enabled.
		 *
		 * @return mixed Result.
		 */
		public static function custom_orders_table_usage_is_enabled() {
			return $GLOBALS['hpos']; }
	}
}

namespace Nashenski\Container {
	/**
	 * Client.
	 */
	class Client {
		const ECONT  = 'econt';
		const SPEEDY = 'speedy';
		const BOXNOW = 'boxnow';
	}
}

namespace Nashenski\Front_End\Checkout {
	/**
	 * Pickers.
	 */
	class Pickers {
		/**
		 * Connected.
		 *
		 * @param mixed $courier Courier.
		 * @return bool Result.
		 */
		public static function connected( $courier ) {
			return true; }
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	define( 'DAY_IN_SECONDS', 86400 );

	$GLOBALS['hpos']      = false;
	$GLOBALS['queries']   = array();
	$GLOBALS['wp_args']   = array();
	$GLOBALS['orderbys']  = array();
	$GLOBALS['filters']   = array();
	$GLOBALS['checked']   = array();
	$GLOBALS['limit']     = 100;
	$GLOBALS['seconds']   = 20; // The time budget of one run.
	$GLOBALS['throw']     = false;
	$GLOBALS['result']    = array();
	$GLOBALS['mid_batch'] = null;
	$GLOBALS['options']   = array();
	$GLOBALS['labels']    = array(); // Order id => label array; an order without one has no waybill.
	$GLOBALS['delivered'] = ''; // The status the owner chose for a delivered parcel.

	/**
	 * Get option.
	 *
	 * @param mixed $k K.
	 * @param bool  $d D.
	 * @return mixed Result.
	 */
	function get_option( $k, $d = false ) {
		return $GLOBALS['options'][ $k ] ?? $d; }
	/**
	 * Update option.
	 *
	 * @param mixed $k K.
	 * @param mixed $v V.
	 * @param mixed $autoload Autoload.
	 * @return bool Result.
	 */
	function update_option( $k, $v, $autoload = null ) {
		$GLOBALS['options'][ $k ] = $v;
		return true; }
	/**
	 * Apply filters.
	 *
	 * @param mixed $tag Tag.
	 * @param mixed $v V.
	 * @return string Result.
	 */
	function apply_filters( $tag, $v ) {
		return 'nashenski/shipments/check_seconds' === $tag ? $GLOBALS['seconds'] : $GLOBALS['limit']; }
	/**
	 * Add filter.
	 *
	 * @param mixed $tag Tag.
	 * @param mixed $cb Cb.
	 */
	function add_filter( $tag, $cb ) {
		$GLOBALS['filters'][ $tag ][] = $cb; }
	/**
	 * Remove filter.
	 *
	 * @param mixed $tag Tag.
	 * @param mixed $cb Cb.
	 */
	function remove_filter( $tag, $cb ) {
		$GLOBALS['filters'][ $tag ] = array_values(
			array_filter(
				$GLOBALS['filters'][ $tag ] ?? array(),
				function ( $f ) use ( $cb ) {
					return $f !== $cb;
				}
			)
		); }
	/**
	 * .
	 *
	 * @param mixed $t T.
	 * @return mixed Result.
	 */
	function __( $t ) {
		return $t; }
	/**
	 * Wp list pluck.
	 *
	 * @param mixed $a A.
	 * @param mixed $k K.
	 * @return mixed Result.
	 */
	function wp_list_pluck( $a, $k ) {
		return array_column( (array) $a, $k ); }
	/**
	 * Nashenski get option.
	 *
	 * @param mixed $group Group.
	 * @param mixed $name Name.
	 * @return string Result.
	 */
	function nashenski_get_option( $group, $name ) {
		return 'delivered' === $name ? $GLOBALS['delivered'] : ''; }
	/**
	 * Nashenski get order label.
	 *
	 * @param mixed $id Id.
	 * @return mixed Result.
	 */
	function nashenski_get_order_label( $id ) {
		// check_order() reads it first.
		$GLOBALS['checked'][] = $id;
		if ( $GLOBALS['mid_batch'] ) {
			call_user_func( $GLOBALS['mid_batch'], $id ); }
		return $GLOBALS['labels'][ $id ] ?? array();
	}
	/**
	 * Fakeclient.
	 */
	class FakeClient {
		/**
		 * Api call.
		 *
		 * @return array Result.
		 */
		public function api_call() {
			return array( 'data' => array( array( 'state' => 'delivered' ) ) ); }
	}
	/**
	 * Nashenski.
	 *
	 * @return mixed Result.
	 */
	function nashenski() {
		return new class() {
			/**
			 * Container.
			 *
			 * @return array Clients.
			 */
			public function container() {
				return array( 'boxnow' => new FakeClient() );
			}
		};
	}
	/**
	 * Wc get order.
	 *
	 * @param mixed $id Id.
	 * @return mixed|bool Result.
	 */
	function wc_get_order( $id ) {
		// A fresh read of the stored order.
		foreach ( $GLOBALS['result'] as $o ) {
			if ( $o->id === $id ) {
				return $o->snapshot(); }
		}
		return false;
	}
	/**
	 * Wc get orders.
	 *
	 * @param mixed $a A.
	 * @return mixed Result.
	 * @throws RuntimeException . (false !== strpos( $type, "Packing" ) ? " When the packing budget is exceeded." : " On failure.") .
	 */
	function wc_get_orders( $a ) {
		$GLOBALS['queries'][] = $a;
		$wp                   = array();
		foreach ( $GLOBALS['filters']['woocommerce_order_data_store_cpt_get_orders_query'] ?? array() as $cb ) {
			$wp = $cb( $wp, $a, null ); }
		$GLOBALS['wp_args'][] = $wp;
		$orderby              = ! empty( $GLOBALS['filters']['posts_orderby'] ) ? $GLOBALS['filters']['posts_orderby'][0]( '' ) : ''; // What the posts_orderby filter returns while the query runs.
		$GLOBALS['orderbys'][] = $orderby;
		if ( $GLOBALS['throw'] ) {
			throw new RuntimeException( 'query failed' ); }
		// Like the datastore: orders without the state meta (NOT EXISTS), the date_created window (HPOS) or the legacy date_query, the order, then offset and limit.
		$r = array_values(
			array_filter(
				$GLOBALS['result'],
				function ( $o ) {
					return ! isset( $o->meta['_nashenski_shipment_state'] );
				}
			)
		);
		if ( preg_match( '/^(>=?)(\d+)$/', $a['date_created'] ?? '', $m ) ) {
			$r = array_values(
				array_filter(
					$r,
					function ( $o ) use ( $m ) {
						return '>=' === $m[1] ? $o->date >= (int) $m[2] : $o->date > (int) $m[2];
					}
				)
			);
		}
		$dq = $wp['date_query'][0] ?? null; // Legacy: WP_Query reads the six-key array form on post_date_gmt, with no timezone step.
		if ( $dq ) {
			$t = gmmktime( $dq['after']['hour'], $dq['after']['minute'], $dq['after']['second'], $dq['after']['month'], $dq['after']['day'], $dq['after']['year'] );
			$r = array_values(
				array_filter(
					$r,
					function ( $o ) use ( $t ) {
						return $o->date >= $t;
					}
				)
			);
		}
		if ( ! empty( $a['exclude'] ) ) {
			$r = array_values(
				array_filter(
					$r,
					function ( $o ) use ( $a ) {
						return ! in_array( $o->id, $a['exclude'], true );
					}
				)
			);
		}
		$by_date = 'date' === ( $a['orderby'] ?? 'ID' );
		// Legacy sorts by the local post_date unless posts_orderby asks for post_date_gmt; HPOS sorts by the creation time.
		$gmt = $GLOBALS['hpos'] || 'post_date_gmt ASC' === $orderby;
		$key = function ( $o ) use ( $gmt ) {
			return $gmt ? $o->date : ( $o->local ?? $o->date );
		};
		usort(
			$r,
			function ( $x, $y ) use ( $by_date, $key ) {
				return $by_date ? array( $key( $x ), $x->id ) <=> array( $key( $y ), $y->id ) : $x->id <=> $y->id;
			}
		);
		$r = array_slice( $r, $a['offset'] ?? 0, $a['limit'] > 0 ? $a['limit'] : null );
		return array_map(
			function ( $o ) {
				return wc_get_order( $o->id );
			},
			$r
		);
	}

	/**
	 * Fakeorder.
	 */
	class FakeOrder {
		/**
		 * Id.
		 *
		 * @var mixed
		 */
		public $id;
		/**
		 * Date: the GMT creation time, as getTimestamp() returns it.
		 *
		 * @var int
		 */
		public $date = 0;
		/**
		 * Local: the local post_date as a sort key; null means the same as the GMT date (no clock change).
		 *
		 * @var int|null
		 */
		public $local = null;
		/**
		 * Meta.
		 *
		 * @var array
		 */
		public $meta = array();
		/**
		 * Saves.
		 *
		 * @var int
		 */
		public $saves = 0;
		/**
		 * Status.
		 *
		 * @var string
		 */
		public $status = 'processing';
		/**
		 * The stored order this copy was loaded from.
		 *
		 * @var mixed
		 */
		public $stored;

		/**
		 * Whether set_date_modified was called.
		 *
		 * @var bool
		 */
		private $dirty = false;
		/**
		 * Constructor.
		 *
		 * @param mixed $id Id.
		 */
		public function __construct( $id ) {
			$this->id = $id; }
		/**
		 * Snapshot.
		 *
		 * @return mixed Result.
		 */
		public function snapshot() {
			$c         = clone $this;
			$c->stored = $this;
			return $c; }
		/**
		 * Get id.
		 *
		 * @return mixed Result.
		 */
		public function get_id() {
			return $this->id; }
		/**
		 * Get date created.
		 *
		 * @return mixed Result.
		 */
		public function get_date_created() {
			$t = $this->date;
			return new class( $t ) {
				/**
				 * Timestamp.
				 *
				 * @var mixed
				 */
				private $t;

				/**
				 * Constructor.
				 *
				 * @param mixed $t T.
				 */
				public function __construct( $t ) {
					$this->t = $t;
				}

				/**
				 * Get the timestamp.
				 *
				 * @return mixed Timestamp.
				 */
				public function getTimestamp() {
					return $this->t;
				}
			};
		}
		/**
		 * Get meta.
		 *
		 * @param mixed $k K.
		 * @return mixed Result.
		 */
		public function get_meta( $k ) {
			return $this->meta[ $k ] ?? ''; }
		/**
		 * Get status.
		 *
		 * @return mixed Result.
		 */
		public function get_status() {
			return $this->status; }
		/**
		 * Update meta data.
		 *
		 * @param mixed $k K.
		 * @param mixed $v V.
		 */
		public function update_meta_data( $k, $v ) {
			$this->meta[ $k ] = $v; }
		/**
		 * Set date modified.
		 *
		 * @param mixed $t T.
		 */
		public function set_date_modified( $t ) {
			$this->dirty = true; }
		/**
		 * Add order note.
		 *
		 * @param mixed $n N.
		 */
		public function add_order_note( $n ) {}
		/**
		 * Update status.
		 *
		 * @param mixed  $s S.
		 * @param string $n N.
		 */
		public function update_status( $s, $n = '' ) {
			$this->status = $s;
			$this->dirty  = true;
			$this->save(); }
		/**
		 * Save.
		 */
		public function save() {
			// Like the legacy store: the post row (status) is rewritten from this copy only when the status or a date changed; meta is always saved.
			$s = $this->stored;
			++$s->saves;
			$s->meta = $this->meta;
			if ( $this->dirty ) {
				$s->status   = $this->status;
				$this->dirty = false; }
		}
	}

	require __DIR__ . '/../app/Shipping/Automation.php';

	/**
	 * Check.
	 *
	 * @param mixed $ok Ok.
	 * @param mixed $what What.
	 */
	function check( $ok, $what ) {
		if ( ! $ok ) {
			fwrite( STDERR, "FAIL: $what\n" );
			exit( 1 );
		} echo "ok - $what\n"; }
	/**
	 * Not exists.
	 *
	 * @param mixed $q Q.
	 * @return string Result.
	 */
	function not_exists( $q ) {
		return '_nashenski_shipment_state' === $q['key'] && 'NOT EXISTS' === $q['compare']; }
	/**
	 * Run.
	 *
	 * @return mixed Result.
	 */
	function run() {
		$GLOBALS['checked'] = array();
		Nashenski\Shipping\Automation::check_shipments();
		return $GLOBALS['checked']; }
	/**
	 * Cursor.
	 *
	 * @return mixed Result.
	 */
	function cursor() {
		$c = $GLOBALS['options']['nashenski_shipments_cursor'] ?? null;
		return is_array( $c ) ? $c['from'] : $c; } // The second the cursor stands on.
	/**
	 * Cursor ids.
	 *
	 * @return mixed Result.
	 */
	function cursor_ids() {
		$c = $GLOBALS['options']['nashenski_shipments_cursor'] ?? null;
		return is_array( $c ) ? $c['ids'] : null; } // The IDs already read in that second.
	/**
	 * Stored.
	 *
	 * @param mixed $from From.
	 * @param array $ids Ids.
	 */
	function stored( $from, $ids = array() ) {
		$GLOBALS['options'] = array(
			'nashenski_shipments_cursor' => array(
				'from' => $from,
				'ids'  => $ids,
			),
		); }
	/**
	 * The start second a query asked for: the date_created string (HPOS) or the legacy GMT date_query.
	 *
	 * @param int|null $i Query index; null is the last query.
	 * @return int Result.
	 */
	function qfrom( $i = null ) {
		$i = null === $i ? count( $GLOBALS['queries'] ) - 1 : $i;
		$q = $GLOBALS['queries'][ $i ];
		if ( isset( $q['date_created'] ) ) {
			return (int) substr( $q['date_created'], 2 ); }
		$a = $GLOBALS['wp_args'][ $i ]['date_query'][0]['after'];
		return gmmktime( $a['hour'], $a['minute'], $a['second'], $a['month'], $a['day'], $a['year'] );
	}
	/**
	 * Day.
	 *
	 * @param mixed $id Id.
	 * @return mixed Result.
	 */
	function day( $id ) {
		return time() - 10 * 86400 + $id * 60; } // Order dates grow with the id, all inside the 60-day window.
	/**
	 * Orders.
	 *
	 * @param mixed[] ...$ids Ids.
	 */
	function orders( ...$ids ) {
		$GLOBALS['result'] = array_map(
			function ( $i ) {
				$o       = new FakeOrder( $i );
				$o->date = day( $i );
				return $o;
			},
			$ids
		); }
	/**
	 * Leave.
	 *
	 * @param mixed $id Id.
	 */
	function leave( $id ) {
		$GLOBALS['result'] = array_values(
			array_filter(
				$GLOBALS['result'],
				function ( $o ) use ( $id ) {
					return $o->id !== $id;
				}
			)
		); } // Completed, cancelled or too old: gone from the list.
	$hook = 'woocommerce_order_data_store_cpt_get_orders_query';

	// Legacy storage.
	orders( 7, 8 );
	$before = time();
	check( array( 7, 8 ) === run(), 'every returned order is checked' );
	$q = $GLOBALS['queries'][0];

	check( ! array_key_exists( 'meta_query', $q ), 'legacy: no meta_query argument' );
	check( not_exists( $GLOBALS['wp_args'][0]['meta_query'][0] ), 'legacy: the datastore filter adds NOT EXISTS' );
	check( empty( $GLOBALS['filters'][ $hook ] ), 'legacy: the filter is removed after the query' );
	check( array( 'processing', 'on-hold' ) === $q['status'] && 'ASC' === $q['order'] && 100 === $q['limit'], 'status, order direction and limit unchanged' );
	check( abs( qfrom( 0 ) - ( $before - 60 * 86400 ) ) <= 2 && ! isset( $q['date_modified'] ), 'the window starts now - 60 days (date_created), not date_modified' );
	// Note 23: the legacy store filters on post_date_gmt with the six-key array form (no timezone step) and sorts by post_date_gmt, so a clock change cannot skip an order.
	$dq = $GLOBALS['wp_args'][0]['date_query'] ?? null;
	$f  = gmdate( 'Y n j G i s', qfrom( 0 ) );
	list( $y, $mo, $d, $h, $mi, $se ) = array_map( 'intval', explode( ' ', $f ) );
	check( ! isset( $q['date_created'] ), 'legacy: no date_created argument (its filter is on the local date)' );
	check(
		array(
			array(
				'column'    => 'post_date_gmt',
				'inclusive' => true,
				'after'     => array(
					'year'   => $y,
					'month'  => $mo,
					'day'    => $d,
					'hour'   => $h,
					'minute' => $mi,
					'second' => $se,
				),
			),
		) === $dq,
		'legacy: date_query is post_date_gmt, inclusive, after the six-key array'
	);
	check( 'post_date_gmt ASC' === $GLOBALS['orderbys'][0] && empty( $GLOBALS['filters']['posts_orderby'] ), 'legacy: posts_orderby returns post_date_gmt ASC during the query and is removed after it' );
	check( 1 === count( $GLOBALS['queries'] ), 'one query, no paging' );
	check( 'date' === $q['orderby'] && ! isset( $q['offset'] ), 'oldest first by creation date, no offset' );
	check( abs( cursor() - ( $before - 60 * 86400 ) ) <= 2, 'a short page resets the cursor to the window start' );

	// The limit passes through as main did: -1 stays -1, with no offset and none saved.
	$GLOBALS['limit'] = -1;
	run();
	$q = end( $GLOBALS['queries'] );
	check( -1 === $q['limit'] && ! isset( $q['offset'] ) && abs( cursor() - ( $before - 60 * 86400 ) ) <= 2, 'limit -1 is passed through unchanged, no paging, the cursor is not touched' );

	// Note 24: a limit of 0 or below -1 would cap the query at 10 and read the same first orders every run, so it becomes -1.
	foreach ( array( 0, -2, -50 ) as $bad ) {
		$GLOBALS['limit'] = $bad;
		run();
		check( -1 === end( $GLOBALS['queries'] )['limit'], "limit $bad is read as -1 (no cap at 10)" );
	}
	$GLOBALS['limit'] = 100;

	// A throwing query still removes the filter.
	$GLOBALS['throw'] = true;
	try {
		run();
		$thrown = false;
	} catch ( RuntimeException $e ) {
		$thrown = true; }
	check( $thrown && empty( $GLOBALS['filters'][ $hook ] ) && empty( $GLOBALS['filters']['posts_orderby'] ), 'legacy: both filters are removed when the query throws' );

	// HPOS: the condition stays in the query, no filter.
	$GLOBALS['throw'] = false;
	$GLOBALS['hpos']  = true;
	$GLOBALS['limit'] = 100;
	run();
	check( not_exists( end( $GLOBALS['queries'] )['meta_query'][0] ), 'HPOS: NOT EXISTS stays in the query' );
	check( isset( end( $GLOBALS['queries'] )['date_created'] ) && array() === end( $GLOBALS['wp_args'] ) && '' === end( $GLOBALS['orderbys'] ), 'HPOS: date_created stays in the query, no date_query and no posts_orderby' );

	// The cursor advances across batches; unchanged orders (no waybill here) are never saved. The IDs read in the cursor second are skipped next time, so no order is read twice.
	$GLOBALS['hpos']    = false;
	$GLOBALS['limit']   = 2;
	$GLOBALS['options'] = array();
	orders( 1, 2, 3, 4, 5 );
	check( array( 1, 2 ) === run() && day( 2 ) === cursor() && array( 2 ) === cursor_ids(), 'cursor: the first batch is 1, 2 and the cursor is the date of order 2 with its ID' );
	check( array( 3, 4 ) === run() && day( 4 ) === cursor() && array( 4 ) === cursor_ids(), 'cursor: the next batch starts at the cursor and skips order 2 (3, 4)' );
	$q = $GLOBALS['queries'][ count( $GLOBALS['queries'] ) - 1 ];
	check( day( 2 ) === qfrom() && array( 2 ) === $q['exclude'], 'cursor: the query asks for orders created at or after the cursor, excluding the stored IDs' );
	check( array( 5 ) === run() && abs( cursor() - ( time() - 60 * 86400 ) ) <= 2 && array() === cursor_ids(), 'reset: a short page ends the cycle and the cursor goes back to the window start with no IDs' );
	check( array( 1, 2 ) === run(), 'reset: the next cycle starts at the oldest order again' );
	foreach ( $GLOBALS['result'] as $o ) {
		check( 0 === $o->saves && ! isset( $o->meta['_nashenski_shipment_checked'] ), "unchanged order {$o->id}: never saved, no checked meta" ); }

	// A cursor older than the window is held at the window start.
	stored( 5 );
	run();
	check( abs( qfrom() - ( time() - 60 * 86400 ) ) <= 2 && ! isset( end( $GLOBALS['queries'] )['exclude'] ), 'cursor: one older than the window is replaced by the window start, no IDs' );

	// A future cursor is a reset, not an empty loop: the orders are read at once and the stored cursor is valid again.
	$GLOBALS['limit'] = 2;
	orders( 1, 2, 3, 4, 5 );
	stored( time() + 100000, array( 1, 2 ) );
	check( array( 1, 2 ) === run() && cursor() <= time() && array( 2 ) === cursor_ids(), 'future cursor: reset to the window start, orders are read, a valid cursor is stored' );

	// A corrupt cursor value is the window start with no IDs.
	foreach ( array(
		'abc',
		12345,
		array(),
		array(
			'from' => 'x',
			'ids'  => array( 1 ),
		),
		array(
			'from' => day( 3 ),
			'ids'  => 'nope',
		),
		true,
	) as $bad ) {
		$GLOBALS['options'] = array( 'nashenski_shipments_cursor' => $bad );
		check( array( 1, 2 ) === run() && array( 2 ) === cursor_ids() && ! isset( end( $GLOBALS['queries'] )['exclude'] ), 'corrupt cursor ' . json_encode( $bad ) . ': window start, no IDs' );
	}

	// More orders in one second than the limit: every order is read across the cycle and none is skipped.
	$GLOBALS['options'] = array();
	orders( 1, 2, 3, 4, 5 );
	foreach ( array( 1, 2, 3, 4 ) as $i ) {
		$GLOBALS['result'][ $i - 1 ]->date = day( 1 ); }
	$seen = array_merge( run(), run(), run() );
	check( array( 1, 2, 3, 4, 5 ) === $seen, 'same second: four orders share one second with limit 2; all five are read in three runs, none skipped' );
	check( abs( cursor() - ( time() - 60 * 86400 ) ) <= 2 && array() === cursor_ids(), 'same second: the cycle ends with a reset' );
	foreach ( $GLOBALS['result'] as $o ) {
		check( 0 === $o->saves, "same second: unchanged order {$o->id} not saved" ); }
	// The merged IDs after the second page of the shared second.
	$GLOBALS['options'] = array();
	run();
	run();
	check( day( 1 ) === cursor() && array( 1, 2, 3, 4 ) === cursor_ids(), 'same second: the stored IDs of the shared second are merged across pages' );

	// An order that leaves the list between runs (completed by the owner) causes no skip.
	$GLOBALS['options'] = array();
	orders( 1, 2, 3, 4, 5 );
	$seen = run();
	leave( 1 );
	$seen = array_merge( $seen, run() );
	check( array( 1, 2, 3, 4 ) === $seen, 'leaving: order 1 left after the first batch, the second batch still reaches 3 and 4 (nothing skipped)' );
	leave( 2 );
	check( array( 5 ) === run(), 'leaving: order 2 left too; 5 is reached in the next batch' );

	// A page whose orders share one timestamp does not skip: its IDs are excluded, the cursor stays on that second.
	$GLOBALS['options'] = array();
	orders( 1, 2, 3 );
	$GLOBALS['result'][1]->date = day( 1 );
	$GLOBALS['result'][0]->date = $GLOBALS['result'][1]->date;
	check( array( 1, 2 ) === run() && day( 1 ) === cursor() && array( 1, 2 ) === cursor_ids(), 'same timestamp: the cursor stays on the shared second with both IDs' );
	check( array( 3 ) === run(), 'same timestamp: the next batch moves on to order 3' );
	// Note 25: an order created in the cursor second after the run is read next (the cursor keeps the second, it does not step to second + 1).
	$GLOBALS['options'] = array();
	orders( 1, 2, 3 );
	$GLOBALS['result'][0]->date = day( 1 );
	$GLOBALS['result'][1]->date = day( 1 );
	run();
	$late               = new FakeOrder( 9 );
	$late->date         = day( 1 );
	$GLOBALS['result'][] = $late;
	check( array( 9, 3 ) === run(), 'later order: an order created in the cursor second after the run is read as the next order (9, then 3)' );
	check( 0 === $late->saves, 'later order: the new order is not saved' );

	// Note 25: a clock change. Autumn: order 1 (first hour, GMT+3) is created before order 2 (second hour, GMT+2) but its local time is later, so the local order is 2, 1.
	// Sorted by the local date the cursor would stand on order 2 and skip order 1; sorted by the GMT date both are read.
	$GLOBALS['limit']   = 1;
	$GLOBALS['options'] = array();
	orders( 1, 2 );
	$GLOBALS['result'][0]->date  = day( 1 );
	$GLOBALS['result'][1]->date  = day( 1 ) + 1800;
	$GLOBALS['result'][0]->local = day( 1 ) + 3 * 3600;
	$GLOBALS['result'][1]->local = day( 1 ) + 1800 + 2 * 3600;
	check( array( 1, 2 ) === array_merge( run(), run() ), 'clock change: both orders are read across two runs, none skipped' );
	$GLOBALS['limit'] = 2;

	// Out of time: a run stops after the budget, the cursor stands on the last order read, and the cycle still reaches every order.
	$GLOBALS['options']   = array();
	$GLOBALS['limit']     = 100;
	$GLOBALS['seconds']   = 0;
	$GLOBALS['mid_batch'] = function () {
		usleep( 2000 );
	};
	orders( 1, 2, 3, 4, 5 );
	check( array( 1 ) === run() && day( 1 ) === cursor() && array( 1 ) === cursor_ids(), 'time budget: a slow run reads one order and the cursor stands on it, not back at the start' );
	check( array( 2 ) === run() && day( 2 ) === cursor(), 'time budget: the next run goes on with order 2' );
	$seen = array_merge( run(), run() );
	check( array( 3, 4 ) === $seen, 'time budget: orders 3 and 4 follow' );
	check( array( 5 ) === run() && abs( cursor() - ( time() - 60 * 86400 ) ) <= 2 && array() === cursor_ids(), 'time budget: the last order ends the cycle and the cursor goes back to the window start' );
	foreach ( $GLOBALS['result'] as $o ) {
		check( 0 === $o->saves, "time budget: unchanged order {$o->id} not saved" ); }
	// With limit -1 (no cursor) the budget does not cut the run: every order is still read.
	$GLOBALS['limit'] = -1;
	check( array( 1, 2, 3, 4, 5 ) === run(), 'time budget: limit -1 reads every order, the budget is not used' );
	$GLOBALS['seconds']   = 20;
	$GLOBALS['limit']     = 2;
	$GLOBALS['mid_batch'] = null;
	// A changed order is saved once; its unchanged neighbour is not.
	$GLOBALS['options']   = array();
	$GLOBALS['delivered'] = 'wc-completed';
	orders( 41, 42 );
	$GLOBALS['labels'] = array(
		41 => array(
			'method' => 'nashenski_boxnow',
			'number' => 'P1',
		),
	);
	check( array( 41, 42 ) === run() && day( 42 ) === cursor(), 'changed: both orders are checked, the cursor is the date of order 42' );
	check( 1 === $GLOBALS['result'][0]->saves && 'completed' === $GLOBALS['result'][0]->status && 'delivered' === $GLOBALS['result'][0]->meta['_nashenski_shipment_state'], 'changed: the delivered order is saved once with its new status and state' );
	check( 0 === $GLOBALS['result'][1]->saves, 'changed: the unchanged order is not saved' );
	check( array() === run() && 1 === $GLOBALS['result'][0]->saves && 0 === $GLOBALS['result'][1]->saves, 'changed: the next run reads neither (41 has its state, 42 was read), the changed order is not saved again' );
	// A status the owner sets while the batch runs survives, on an unchanged order (not saved) and on a changed one (meta-only save).
	$GLOBALS['options']   = array();
	$GLOBALS['delivered'] = '';
	orders( 21, 22 );
	$GLOBALS['labels']    = array(
		22 => array(
			'method' => 'nashenski_boxnow',
			'number' => 'P2',
		),
	);
	$GLOBALS['mid_batch'] = function ( $id ) {
		$GLOBALS['result'][ $id - 21 ]->status = 'on-hold';
	};
	run();
	$GLOBALS['mid_batch'] = null;
	check( 'on-hold' === $GLOBALS['result'][0]->status && 0 === $GLOBALS['result'][0]->saves, 'unchanged order: on-hold set mid-batch survives, nothing saved' );
	check( 'on-hold' === $GLOBALS['result'][1]->status && 1 === $GLOBALS['result'][1]->saves && isset( $GLOBALS['result'][1]->meta['_nashenski_shipment_state'] ), 'changed order with no status chosen: on-hold set mid-batch survives, the state is saved' );
	echo "done\n";
}
