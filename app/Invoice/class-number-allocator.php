<?php
/**
 * Document number allocation.
 *
 * @package Nashenski
 */

namespace Nashenski\Invoice;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/** Serialize all issuers, including shared order/refund and manually typed numbers. */
class Number_Allocator {
	/**
	 * Whether this request holds the numbering lock.
	 *
	 * @var bool
	 */
	private static $issuing = false;

	/** Cache group for reads inside one locked section; see forget(). */
	const CACHE_GROUP = 'nashenski_numbers';

	/**
	 * Cache key salt of the current locked section.
	 *
	 * @var string
	 */
	private static $token = '';

	/**
	 * Reads are cached only for the current locked section: every lock, write and save changes
	 * the token, so no read is ever served from before them, and the token never repeats across
	 * requests even if a persistent object cache ignores the non-persistent group.
	 */
	private static function forget() {
		self::$token = wp_generate_uuid4();
	}

	/**
	 * Cache key of a read in the current locked section.
	 *
	 * @param string $query Read name.
	 * @param array  $args  Read arguments.
	 * @return string
	 */
	private static function key( $query, $args ) {
		return md5( self::$token . $query . wp_json_encode( $args ) );
	}

	/**
	 * Run the callback while holding the numbering lock.
	 *
	 * @param callable $callback Code to run.
	 * @return mixed
	 * @throws \RuntimeException When the lock cannot be taken.
	 */
	private static function locked( $callback ) {
		global $wpdb;
		$lock = 'nashenski_numbers_' . md5( DB_NAME . ':' . $wpdb->options );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WordPress has no API for a named lock, and its result must never be cached.
		if ( self::$issuing || '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			throw new \RuntimeException( 'Could not lock document numbering. Please retry.' );
		}
		self::$issuing = true;
		wp_cache_add_non_persistent_groups( self::CACHE_GROUP );
		self::forget();
		try {
			return $callback();
		} finally {
			self::$issuing = false;
			self::forget();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WordPress has no API for a named lock, and releasing it cannot be cached.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * A numbering setting, read from the database.
	 *
	 * @param string $name Setting name.
	 * @return string|null
	 * @throws \RuntimeException When the read fails.
	 */
	private static function option( $name ) {
		global $wpdb;
		$args = array( $wpdb->options, 'nashenski_settings_invoice_' . $name );
		$key  = self::key( 'option', $args );
		$hit  = wp_cache_get( $key, self::CACHE_GROUP );
		if ( is_array( $hit ) ) {
			return $hit[0];
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- get_option() can still hold a value cached before the lock was taken; the counter must be read from the database.
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $args[0], $args[1] ) );
		if ( $wpdb->last_error ) {
			throw new \RuntimeException( 'Could not read document numbering.' );
		}
		wp_cache_set( $key, array( $value ), self::CACHE_GROUP, MINUTE_IN_SECONDS );
		return $value;
	}

	/**
	 * Save the next number of a sequence.
	 *
	 * @param string $name Setting name.
	 * @param int    $next Next number.
	 * @throws \RuntimeException When the write fails.
	 */
	private static function advance( $name, $next ) {
		global $wpdb;
		$key   = 'nashenski_settings_invoice_' . $name;
		$value = str_pad( $next, 10, '0', STR_PAD_LEFT );
		self::forget();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- update_option() cannot do an atomic upsert of the counter.
		if ( false === $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $wpdb->options (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = %s",
				$key,
				$value,
				$value
			)
		) ) {
			throw new \RuntimeException( 'Could not reserve document number.' );
		}
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/**
	 * Order meta table, its order id column and its row id column.
	 *
	 * @return array
	 */
	public static function storage() {
		global $wpdb;
		return OrderUtil::custom_orders_table_usage_is_enabled()
			? array( $wpdb->prefix . 'wc_orders_meta', 'order_id', 'id' )
			: array( $wpdb->postmeta, 'post_id', 'meta_id' );
	}

	/**
	 * The saved number row of an order, or an empty row.
	 *
	 * @param \WC_Order $order Order.
	 * @param string    $meta  Meta key.
	 * @return object
	 * @throws \RuntimeException When the read fails.
	 */
	private static function saved( $order, $meta ) {
		global $wpdb;
		list( $table, $column, $id ) = self::storage();
		$args                        = array( $id, $table, $column, $order->get_id(), $meta, $id );
		$key                         = self::key( 'saved', $args );
		$rows                        = wp_cache_get( $key, self::CACHE_GROUP );
		if ( ! is_array( $rows ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- reads the stored meta rows with their meta_id, past the HPOS metadata cache; no WordPress API returns them.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT %i AS meta_id, meta_value FROM %i WHERE %i = %d AND meta_key = %s ORDER BY %i', $id, $table, $column, $order->get_id(), $meta, $id ) );
			if ( $wpdb->last_error ) {
				throw new \RuntimeException( 'Could not read saved document number.' );
			}
			wp_cache_set( $key, $rows, self::CACHE_GROUP, MINUTE_IN_SECONDS );
		}
		if ( $rows ) {
			return $rows[0];
		}
		$empty             = new \stdClass();
		$empty->meta_id    = 0;
		$empty->meta_value = '';
		return $empty;
	}

	/**
	 * Put the number on the order object, tied to its database row.
	 *
	 * @param \WC_Order $order  Order.
	 * @param string    $meta   Meta key.
	 * @param string    $number Number.
	 * @param object    $row    Saved row.
	 * @param bool      $clean  Mark the meta as saved.
	 */
	private static function sync( $order, $meta, $number, $row, $clean = false ) {
		$order->update_meta_data( $meta, $number );
		// A stale order may not know the row inserted by another request. Keep its ID
		// so a later WooCommerce save updates that row rather than inserting a duplicate.
		foreach ( $order->get_meta_data() as $item ) {
			if ( $item->key === $meta ) {
				$item->id = (int) $row->meta_id;
				if ( $clean ) {
					$item->apply_changes();
				}
			}
		}
	}

	/**
	 * Meta keys that share a sequence.
	 *
	 * @param string $option Sequence setting.
	 * @return array
	 */
	private static function keys( $option ) {
		if ( 'next_invoice_separate_number' === $option ) {
			return array( 'nashenski_order_invoice_number' );
		}
		$keys = array( 'nashenski_order_number', 'nashenski_refunded_order_number' );
		if ( ! self::option( 'next_invoice_separate_number' ) ) {
			$keys[] = 'nashenski_order_invoice_number';
		}
		return $keys;
	}

	/**
	 * Id of an order that already uses the number in the sequence, or null.
	 *
	 * @param int    $number Number.
	 * @param string $option Sequence setting.
	 * @param int    $except Order id to leave out.
	 * @return string|null
	 * @throws \RuntimeException When the read fails.
	 */
	private static function used( $number, $option, $except = 0 ) {
		global $wpdb;
		list( $table, $column ) = self::storage();
		$keys                   = self::keys( $option );
		$args                   = array_merge( array( $column, $table ), $keys, array( $number, $column, $except ) );
		$key                    = self::key( 'used', $args );
		$hit                    = wp_cache_get( $key, self::CACHE_GROUP );
		if ( is_array( $hit ) ) {
			return $hit[0];
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- one indexed lookup of a number across all orders under the lock; wc_get_orders() cannot compare meta as a number.
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT %i FROM %i WHERE meta_key IN (' . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ') AND CAST(meta_value AS UNSIGNED) = %d AND %i <> %d LIMIT 1', $args ) );
		if ( $wpdb->last_error ) {
			throw new \RuntimeException( 'Could not read document numbering.' );
		}
		wp_cache_set( $key, array( $value ), self::CACHE_GROUP, MINUTE_IN_SECONDS );
		return $value;
	}

	/**
	 * Highest number saved under the meta keys.
	 *
	 * @param array $keys Meta keys.
	 * @return string|null
	 * @throws \RuntimeException When the read fails.
	 */
	private static function highest( $keys ) {
		global $wpdb;
		list( $table ) = self::storage();
		$args          = array_merge( array( $table ), $keys );
		$key           = self::key( 'highest', $args );
		$hit           = wp_cache_get( $key, self::CACHE_GROUP );
		if ( is_array( $hit ) ) {
			return $hit[0];
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- the highest number across all orders under the lock; no WordPress API returns an aggregate of meta values.
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM %i WHERE meta_key IN (' . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ')', $args ) );
		if ( $wpdb->last_error ) {
			throw new \RuntimeException( 'Could not read document numbering.' );
		}
		wp_cache_set( $key, array( $value ), self::CACHE_GROUP, MINUTE_IN_SECONDS );
		return $value;
	}

	/**
	 * Save the number on the order and check it was written.
	 *
	 * @param \WC_Order $order  Order.
	 * @param string    $meta   Meta key.
	 * @param int       $number Number.
	 * @param object    $row    Saved row.
	 * @throws \RuntimeException When the number was not saved.
	 * @throws \Throwable When the order save fails.
	 */
	private static function persist( $order, $meta, $number, $row ) {
		$padded = str_pad( $number, 10, '0', STR_PAD_LEFT );
		self::sync( $order, $meta, $padded, $row );
		try {
			$stored = $order->save();
			self::forget(); // The save wrote to the meta table; read it back from there.
			if ( ! $stored || (string) self::saved( $order, $meta )->meta_value !== $padded ) {
				throw new \RuntimeException( 'Could not save document number.' );
			}
		} catch ( \Throwable $error ) {
			// Do not leave a failed assignment queued for a later, unlocked order save.
			foreach ( $order->get_meta_data() as $item ) {
				if ( $item->key === $meta ) {
					if ( ! $item->id ) {
						$item->value = null;
					}
					$item->apply_changes();
				}
			}
			throw $error;
		}
	}

	/**
	 * No numbers while another plugin gives out the same ones (Import_Sources::n18_paused()).
	 *
	 * @throws \RuntimeException While numbering is paused.
	 */
	private static function not_paused() {
		if ( class_exists( \Nashenski\Admin\Import_Sources::class ) && \Nashenski\Admin\Import_Sources::n18_paused() ) {
			throw new \RuntimeException( esc_html( \Nashenski\Admin\Import_Sources::paused_reason() ) );
		}
	}

	/**
	 * Continue the sequences after numbers another plugin gave out: under the numbering lock, each
	 * counter lower than the highest number moves to the number after it.
	 *
	 * @param callable $highest Returns sequence setting => highest number used elsewhere; called under the lock.
	 * @return int Counters moved.
	 */
	public static function continue_after( $highest ) {
		return self::locked(
			function () use ( $highest ) {
				$moved = 0;
				foreach ( $highest() as $option => $number ) {
					if ( (int) $number && (int) $number + 1 > (int) self::option( $option ) ) {
						self::advance( $option, (int) $number + 1 );
						++$moved;
					}
				}
				return $moved;
			}
		);
	}

	/**
	 * The order's document number: the saved one, or the next one of the sequence.
	 *
	 * @param \WC_Order $order   Order.
	 * @param string    $meta    Meta key.
	 * @param string    $option  Sequence setting.
	 * @param bool      $replace Replace a number another order also has.
	 * @return mixed
	 */
	public static function allocate( $order, $meta, $option, $replace = false ) {
		self::not_paused();
		// Who pays the courier is settled before the first document number (quick, admin and REST orders).
		if ( $order instanceof \WC_Order && class_exists( '\Nashenski\Shipping\Courier_Fee' ) ) {
			\Nashenski\Shipping\Courier_Fee::decide( $order );
		}
		return self::locked(
			function () use ( $order, $meta, $option, $replace ) {
				if ( ! $order->get_id() || ! in_array( $option, array( 'next_invoice_number', 'next_invoice_separate_number' ), true ) || ! in_array( $meta, self::keys( $option ), true ) ) {
						throw new \InvalidArgumentException( 'Invalid document numbering sequence.' );
				}
				$row = self::saved( $order, $meta );
				if ( $row->meta_value && ! $replace ) {
					self::sync( $order, $meta, $row->meta_value, $row, true );
					return $row->meta_value;
				}
				// Renewals replace an existing reference; unnumbered orders remain untouched.
				if ( $replace && ! $row->meta_value ) {
					return '';
				}
				// A number this order already owns (e.g. issued at checkout) stays; only another order's number is replaced.
				if ( $replace && ! self::used( (int) $row->meta_value, $option, $order->get_id() ) ) {
					self::sync( $order, $meta, $row->meta_value, $row, true );
					return $row->meta_value;
				}
				$number = max( 1, (int) self::option( $option ) );
				if ( self::used( $number, $option ) ) {
					$number = max( $number, (int) self::highest( self::keys( $option ) ) + 1 );
				}
				if ( ! is_int( $number ) || $number >= PHP_INT_MAX ) {
					throw new \RuntimeException( 'Document numbering sequence exhausted.' );
				}
				self::advance( $option, $number + 1 ); // Keep this reservation even if saving fails.
				self::persist( $order, $meta, $number, $row );
				return $number;
			}
		);
	}

	/**
	 * $own_program: the number comes from the shop's own invoicing program, so the website sequence stays as it is.
	 *
	 * @param \WC_Order  $order       Order.
	 * @param int|string $number      Invoice number.
	 * @param bool       $own_program Whether the shop's own program issued it.
	 * @return string Error message, or ''.
	 */
	public static function manual( $order, $number, $own_program = false ) {
		self::not_paused();
		return self::locked(
			function () use ( $order, $number, $own_program ) {
				$option = self::option( 'next_invoice_separate_number' ) ? 'next_invoice_separate_number' : 'next_invoice_number';
				if ( self::used( (int) $number, $option, $order->get_id() ) ) {
						/* translators: %s: invoice number */
						return sprintf( __( 'Number %s is already used by another order. Choose another invoice number.', 'nashenski-commerce' ), str_pad( (int) $number, 10, '0', STR_PAD_LEFT ) );
				}
				if ( $own_program ) {
					$order->update_meta_data( 'nashenski_invoice_own_program', 'yes' );
				} elseif ( (int) $number >= (int) self::option( $option ) ) {
					self::advance( $option, (int) $number + 1 );
				}
				self::persist( $order, 'nashenski_order_invoice_number', (int) $number, self::saved( $order, 'nashenski_order_invoice_number' ) );
				return '';
			}
		);
	}

	/**
	 * Keep historical references unchanged, but never import one over another order's number.
	 *
	 * @param \WC_Order  $order  Order.
	 * @param string     $meta   Meta key.
	 * @param int|string $number Imported number.
	 * @param string     $option Sequence setting.
	 * @return bool Whether the number was imported.
	 */
	public static function import_number( $order, $meta, $number, $option ) {
		return self::locked(
			function () use ( $order, $meta, $number, $option ) {
				$row = self::saved( $order, $meta );
				if ( $row->meta_value ) {
						self::sync( $order, $meta, $row->meta_value, $row, true );
						return false;
				}
				// Shared numbering (empty separate counter) draws from the shared pool, so check every key it uses.
				$check = ( 'next_invoice_separate_number' === $option && ! self::option( $option ) ) ? 'next_invoice_number' : $option;
				if ( ! preg_match( '/^\d{1,10}$/', (string) $number ) || ! (int) $number || self::used( (int) $number, $check, $order->get_id() ) ) {
					throw new \RuntimeException( 'Cannot import a conflicting or invalid document number. The historical reference was not changed.' );
				}
				// $check is the shared counter when the separate one is empty; advancing the empty one would switch the shop to separate numbering.
				if ( (int) $number >= (int) self::option( $check ) ) {
					self::advance( $check, (int) $number + 1 );
				}
				self::persist( $order, $meta, (int) $number, $row );
				return true;
			}
		);
	}
}
