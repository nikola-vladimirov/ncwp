<?php
namespace Nashenski\Invoice;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/** Serialize all issuers, including shared order/refund and manually typed numbers. */
class Number_Allocator {
	private static $issuing = false;

	private static function locked( $callback ) {
		global $wpdb;
		$lock = 'nashenski_numbers_' . md5( DB_NAME . ':' . $wpdb->options );
		// A named MySQL lock cannot be cached: it must reflect this connection right now.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( self::$issuing || '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) {
			throw new \RuntimeException( 'Could not lock document numbering. Please retry.' );
		}
		self::$issuing = true;
		try {
			return $callback();
		} finally {
			self::$issuing = false;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- releases the uncached named lock taken above
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/** $sql holds only %s, %d and %i placeholders (and IN lists of them); $args fills them. */
	private static function value( $sql, $args ) {
		global $wpdb;
		// Numbers must come from the database itself, never from a cache.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is a literal from this class, placeholders only
		$value = $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
		if ( $wpdb->last_error ) {
			throw new \RuntimeException( 'Could not read document numbering.' );
		}
		return $value;
	}

	private static function option( $name ) {
		global $wpdb;
		// get_option() can still contain a pre-lock request or persistent-cache value.
		return self::value( 'SELECT option_value FROM %i WHERE option_name = %s', array( $wpdb->options, 'nashenski_settings_invoice_' . $name ) );
	}

	private static function advance( $name, $next ) {
		global $wpdb;
		$key = 'nashenski_settings_invoice_' . $name;
		$value = str_pad( $next, 10, '0', STR_PAD_LEFT );
		// The reservation must hit the database under the lock; there is nothing to cache.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( false === $wpdb->query( $wpdb->prepare(
			"INSERT INTO $wpdb->options (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = %s",
			$key, $value, $value
		) ) ) {
			throw new \RuntimeException( 'Could not reserve document number.' );
		}
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	public static function storage() {
		global $wpdb;
		return OrderUtil::custom_orders_table_usage_is_enabled()
			? array( $wpdb->prefix . 'wc_orders_meta', 'order_id', 'id' )
			: array( $wpdb->postmeta, 'post_id', 'meta_id' );
	}

	private static function saved( $order, $meta ) {
		global $wpdb;
		list( $table, $column, $id ) = self::storage();
		// Read the authoritative store, including when HPOS metadata caching is enabled.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- must bypass caches
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT %i AS meta_id, meta_value FROM %i WHERE %i = %d AND meta_key = %s ORDER BY %i', $id, $table, $column, $order->get_id(), $meta, $id ) );
		if ( $wpdb->last_error ) {
			throw new \RuntimeException( 'Could not read saved document number.' );
		}
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- an empty row object, not a query
		return $rows ? $rows[0] : (object) array( 'meta_id' => 0, 'meta_value' => '' );
	}

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

	private static function keys( $option ) {
		if ( $option === 'next_invoice_separate_number' ) {
			return array( 'nashenski_order_invoice_number' );
		}
		$keys = array( 'nashenski_order_number', 'nashenski_refunded_order_number' );
		if ( ! self::option( 'next_invoice_separate_number' ) ) {
			$keys[] = 'nashenski_order_invoice_number';
		}
		return $keys;
	}

	private static function used( $number, $option, $except = 0 ) {
		global $wpdb;
		list( $table, $column ) = self::storage();
		$keys = self::keys( $option );
		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a list of %s, one per key
		return self::value( "SELECT %i FROM %i WHERE meta_key IN ($placeholders) AND CAST(meta_value AS UNSIGNED) = %d AND %i <> %d LIMIT 1", array_merge( array( $column, $table ), $keys, array( $number, $column, $except ) ) );
	}

	private static function persist( $order, $meta, $number, $row ) {
		$padded = str_pad( $number, 10, '0', STR_PAD_LEFT );
		self::sync( $order, $meta, $padded, $row );
		try {
			if ( ! $order->save() || (string) self::saved( $order, $meta )->meta_value !== $padded ) {
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

	public static function allocate( $order, $meta, $option, $replace = false ) {
		return self::locked( function () use ( $order, $meta, $option, $replace ) {
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
				list( $table ) = self::storage();
				$keys = self::keys( $option );
				$marks = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $marks is a list of %s, one per key
				$number = max( $number, (int) self::value( "SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM %i WHERE meta_key IN ($marks)", array_merge( array( $table ), $keys ) ) + 1 );
			}
			if ( ! is_int( $number ) || $number >= PHP_INT_MAX ) {
				throw new \RuntimeException( 'Document numbering sequence exhausted.' );
			}
			self::advance( $option, $number + 1 ); // Keep this reservation even if saving fails.
			self::persist( $order, $meta, $number, $row );
			return $number;
		} );
	}

	/** $own_program: the number comes from the shop's own invoicing program, so the website sequence stays as it is. */
	public static function manual( $order, $number, $own_program = false ) {
		return self::locked( function () use ( $order, $number, $own_program ) {
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
		} );
	}

	/** Keep historical references unchanged, but never import one over another order's number. */
	public static function import_number( $order, $meta, $number, $option ) {
		return self::locked( function () use ( $order, $meta, $number, $option ) {
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
		} );
	}
}
