<?php
namespace Nashenski;

use Automattic\WooCommerce\Utilities\OrderUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Order documents (PDF) and accounting exports (NRA XML, Delta TXT) hold customer data, so they are
 * kept in a private uploads folder and handed out only through download(), which checks who asks.
 *
 * The folder name has a random part: Apache and LiteSpeed obey the .htaccess in it, and on nginx
 * the path is not guessable and is never sent to the browser.
 */
class Document_Storage {
	const DIR_OPTION = 'nashenski_documents_dir';
	const ACTION = 'nashenski_document';
	const MIGRATE_HOOK = 'nashenski/documents/migrate';
	const MIGRATE_BATCH = 50;
	const MIGRATE_OPTION = 'nashenski_documents_migration';
	const CLEANUP_HOOK = 'nashenski_delete_receipts';
	const CLEANUP_OPTION = 'nashenski_settings_invoice_delete_receipts_days';
	const CLEANUP_BATCH = 100;
	const RECEIPT_DELETED_META = '_nashenski_order_document_deleted';
	const EXPORT_UNTIL_META = '_nashenski_export_until';
	const EXPORT_CLEANUP_HOOK = 'nashenski_delete_export_files';
	const EXPORT_DAYS = 7;

	/**
	 * The folder documents were public in before 0.11.1.
	 */
	const LEGACY_SUBDIR = 'nashenski';

	/**
	 * Public folders documents were in: ours before 0.11.1, and Bulgarisation's (4.x and 5.x), whose
	 * documents the import takes over.
	 */
	const LEGACY_SUBDIRS = array( 'nashenski', 'woo-bg', 'bulgarisation' );
	const MIGRATE_VERSION_OPTION = 'nashenski_documents_migration_version';
	const MIGRATE_VERSION = 2;

	public function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'download' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'download' ) );
		add_action( self::MIGRATE_HOOK, array( __CLASS__, 'migrate_batch' ) );
		add_action( 'init', array( __CLASS__, 'schedule_migration' ), 20 );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'delete_receipts' ) );
		add_action( self::EXPORT_CLEANUP_HOOK, array( __CLASS__, 'delete_export_files' ) );
		add_action( 'init', array( __CLASS__, 'schedule_export_cleanup' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'hide_from_media_library' ) );
		add_filter( 'ajax_query_attachments_args', array( __CLASS__, 'hide_from_media_grid' ) );
		add_filter( 'posts_where', array( __CLASS__, 'hide_documents_where' ), 10, 2 );
		add_action( 'admin_post_' . self::CLEANUP_HOOK, array( __CLASS__, 'delete_receipts_now' ) );
		add_action( 'admin_notices', array( __CLASS__, 'receipts_deleted_notice' ) );
		add_action( 'add_option_' . self::CLEANUP_OPTION, array( __CLASS__, 'schedule_receipt_cleanup' ) );
		add_action( 'update_option_' . self::CLEANUP_OPTION, array( __CLASS__, 'schedule_receipt_cleanup' ) );
	}

	/**
	 * Export files (XML, text) are downloaded once; a daily job deletes them after EXPORT_DAYS.
	 */
	public static function schedule_export_cleanup() {
		if ( ! wp_next_scheduled( self::EXPORT_CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::EXPORT_CLEANUP_HOOK );
		}
	}

	public static function delete_export_files() {
		$ids = get_posts( array(
			'post_type' => 'attachment',
			'post_status' => 'any',
			'fields' => 'ids',
			'posts_per_page' => self::CLEANUP_BATCH,
			'meta_query' => array( array( 'key' => self::EXPORT_UNTIL_META, 'value' => time(), 'compare' => '<', 'type' => 'NUMERIC' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- daily background job.
		) );

		foreach ( $ids as $id ) {
			wp_delete_attachment( $id, true );
		}
	}

	/**
	 * Keep the private documents out of the Media Library (list and grid): they are for the order screens.
	 */
	public static function hide_from_media_library( $query ) {
		global $pagenow;

		if ( is_admin() && 'upload.php' === $pagenow && $query->is_main_query() ) {
			$query->set( 'nashenski_hide_documents', 1 );
		}
	}

	public static function hide_from_media_grid( $args ) {
		$args['nashenski_hide_documents'] = 1;

		return $args;
	}

	public static function hide_documents_where( $where, $query ) {
		global $wpdb;

		if ( $query->get( 'nashenski_hide_documents' ) ) {
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID NOT IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s )", 'nashenski-documents-%' );
		}

		return $where;
	}

	/**
	 * Daily cleanup while "Delete order documents after" is above 0, set when the setting is saved.
	 */
	public static function schedule_receipt_cleanup() {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		as_unschedule_all_actions( self::CLEANUP_HOOK, array(), 'nashenski' );

		if ( (int) get_option( self::CLEANUP_OPTION ) > 0 ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::CLEANUP_HOOK, array(), 'nashenski' );
		}
	}

	/**
	 * Delete the order document PDFs (Bulgarian and English; invoices, credit notes and refund
	 * documents stay) of orders older than the setting, or of all orders with $all. Works in
	 * batches and queues the next one while any are left. The number stays on the order, so the
	 * document can be made again and still shows in exports.
	 */
	public static function delete_receipts( $all = false ) {
		$days = (int) get_option( self::CLEANUP_OPTION );

		if ( ! $all && $days < 1 ) {
			return 0;
		}

		$args = array(
			'limit' => self::CLEANUP_BATCH,
			'return' => 'ids',
			'type' => 'shop_order',
		);

		if ( ! $all ) {
			$args['date_created'] = '<' . ( time() - $days * DAY_IN_SECONDS );
		}

		$has_document = array( 'relation' => 'OR', array( 'key' => 'nashenski_order_document', 'compare' => 'EXISTS' ), array( 'key' => 'nashenski_order_document_en', 'compare' => 'EXISTS' ) );
		$legacy = ! ( class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled() );

		if ( $legacy ) {
			// The legacy post datastore rejects the meta_query argument, so add it to the WP_Query it builds.
			$add = function ( $wp_query_args ) use ( $has_document ) {
				$wp_query_args['meta_query'] = array_merge( isset( $wp_query_args['meta_query'] ) ? (array) $wp_query_args['meta_query'] : array(), array( $has_document ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- batched background job.

				return $wp_query_args;
			};
			add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $add );
		} else {
			$args['meta_query'] = array( $has_document ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- batched background job.
		}

		try {
			$ids = wc_get_orders( $args );
		} finally {
			if ( $legacy ) {
				remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $add );
			}
		}

		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );

			if ( ! $order ) {
				continue;
			}

			foreach ( array( 'nashenski_order_document', 'nashenski_order_document_en' ) as $meta ) {
				$attach_id = (int) $order->get_meta( $meta );

				if ( $attach_id && 'attachment' === get_post_type( $attach_id ) ) {
					wp_delete_attachment( $attach_id, true );
				}

				$order->delete_meta_data( $meta );
			}

			$order->update_meta_data( self::RECEIPT_DELETED_META, time() );
			$order->save();
		}

		if ( count( $ids ) === self::CLEANUP_BATCH && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::CLEANUP_HOOK, array( (bool) $all ), 'nashenski' );
		}

		return count( $ids );
	}

	/**
	 * The "Delete all now" button of the documents settings.
	 */
	public static function delete_receipts_now() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You cannot do this.', 'nashenski-commerce' ), 403 );
		}

		check_admin_referer( self::CLEANUP_HOOK );

		// Batches run in the background, so a large store does not time out the click.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::CLEANUP_HOOK, array( true ), 'nashenski' );
		} else {
			self::delete_receipts( true );
		}

		wp_safe_redirect( add_query_arg( 'nashenski-receipts-deleted', 1, wp_get_referer() ? wp_get_referer() : admin_url() ) );
		exit;
	}

	public static function receipts_deleted_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only shows a notice.
		if ( empty( $_GET['nashenski-receipts-deleted'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Order documents are being deleted in the background.', 'nashenski-commerce' ) . '</p></div>';
	}

	/**
	 * Folder name under uploads, e.g. "nashenski-documents-3f9a…", made once per site.
	 */
	public static function subdir() {
		$subdir = get_option( self::DIR_OPTION );

		if ( ! $subdir || ! preg_match( '/^nashenski-documents-[a-f0-9]{32}$/', $subdir ) ) {
			$subdir = 'nashenski-documents-' . bin2hex( random_bytes( 16 ) );
			update_option( self::DIR_OPTION, $subdir, false );
		}

		return $subdir;
	}

	/**
	 * upload_dir filter: point uploads at the private folder.
	 */
	public static function change_upload_dir( $args ) {
		$subdir = '/' . self::subdir();

		$args['path'] = $args['basedir'] . $subdir;
		$args['url'] = $args['baseurl'] . $subdir;
		$args['subdir'] = $subdir;

		return $args;
	}

	/**
	 * Absolute path of the private folder, created with its deny rules when missing.
	 */
	public static function path() {
		$uploads = wp_upload_dir( null, false );
		$path = trailingslashit( $uploads['basedir'] ) . self::subdir();

		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
		}

		if ( ! file_exists( $path . '/.htaccess' ) ) {
			File::put_to_file( $path . '/.htaccess', "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n" );
		}

		if ( ! file_exists( $path . '/index.php' ) ) {
			File::put_to_file( $path . '/index.php', "<?php\n// Silence is golden.\n" );
		}

		return $path;
	}

	/**
	 * Write $contents as $name.$extension into the private folder as a private attachment.
	 *
	 * @return int Attachment ID, 0 on failure.
	 */
	public static function save( $name, $extension, $contents ) {
		self::path();

		add_filter( 'upload_dir', array( __CLASS__, 'change_upload_dir' ) );
		$file = wp_upload_bits( $name . '.' . $extension, null, $contents );
		remove_filter( 'upload_dir', array( __CLASS__, 'change_upload_dir' ) );

		if ( ! empty( $file['error'] ) ) {
			return 0;
		}

		$attach_id = wp_insert_attachment( array(
			'guid' => $file['file'],
			'post_mime_type' => $file['type'],
			'post_title' => $name,
			'post_content' => '',
			'post_status' => 'private',
		), $file['file'] );

		if ( is_wp_error( $attach_id ) ) {
			return 0;
		}

		if ( 'pdf' !== $extension ) {
			update_post_meta( $attach_id, self::EXPORT_UNTIL_META, time() + self::EXPORT_DAYS * DAY_IN_SECONDS );
		}

		return (int) $attach_id;
	}

	/**
	 * Link to a document of an order or refund, stored in its $meta (e.g. nashenski_invoice_document_en).
	 *
	 * The link carries a signature that expires, used only for guest orders: the page showing it
	 * (WooCommerce's order-received page) has already checked the order key and, after its grace
	 * period, the email, so a copied link cannot be reused for long.
	 */
	public static function order_document_url( $order, $meta ) {
		$expires = time() + (int) apply_filters( 'nashenski/documents/guest_link_lifetime', DAY_IN_SECONDS );

		return add_query_arg( array(
			'action' => self::ACTION,
			'order' => $order->get_id(),
			'doc' => $meta,
			'expires' => $expires,
			'token' => self::sign( $order->get_id(), $meta, $expires ),
		), admin_url( 'admin-post.php' ) );
	}

	private static function sign( $order_id, $meta, $expires ) {
		return hash_hmac( 'sha256', $order_id . '|' . $meta . '|' . $expires, wp_salt( 'auth' ) );
	}

	/**
	 * Link to an accounting export (not tied to one order); staff only.
	 */
	public static function export_url( $attach_id ) {
		return add_query_arg( array(
			'action' => self::ACTION,
			'file' => absint( $attach_id ),
		), admin_url( 'admin-post.php' ) );
	}

	/**
	 * admin-post handler for both kinds of links.
	 */
	public static function download() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a read-only download; access is checked below.
		$order_id = absint( $_GET['order'] ?? 0 );
		$meta = sanitize_key( wp_unslash( $_GET['doc'] ?? '' ) );
		$expires = absint( $_GET['expires'] ?? 0 );
		$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
		$file_id = absint( $_GET['file'] ?? 0 );
		// phpcs:enable

		$attach_id = 0;

		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			$main_order = $order instanceof \WC_Order_Refund ? wc_get_order( $order->get_parent_id() ) : $order;
			$signed = $expires >= time() && hash_equals( self::sign( $order_id, $meta, $expires ), $token );

			if ( $main_order && self::is_document_meta( $meta ) && self::can_download( $main_order, $signed ) ) {
				$attach_id = absint( $order->get_meta( $meta ) );
			}
		} else if ( $file_id && current_user_can( 'manage_woocommerce' ) ) {
			$attach_id = $file_id;
		}

		$path = $attach_id ? get_attached_file( $attach_id ) : '';

		if ( ! $path || ! self::is_stored_here( $path ) || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'The document was not found or you do not have access to it.', 'nashenski-commerce' ), '', array( 'response' => 404 ) );
		}

		$type = wp_check_filetype( $path );
		$disposition = 'application/pdf' === $type['type'] ? 'inline' : 'attachment';

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . sanitize_file_name( basename( $path ) ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streams a stored document.
		exit;
	}

	/**
	 * Who may open a document of $order, the same rules WooCommerce uses for the order pages:
	 * shop staff; for an order of a registered customer only that customer, logged in (a link alone
	 * is not enough, like WooCommerce's "known shoppers" check); for a guest order a valid,
	 * unexpired signed link.
	 *
	 * @param \WC_Order $order  The order (for a refund: its parent order).
	 * @param bool      $signed The link's signature is valid and not expired.
	 */
	private static function can_download( $order, $signed ) {
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_shop_orders' ) ) {
			$allowed = true;
		} else if ( $order->get_customer_id() ) {
			$allowed = current_user_can( 'view_order', $order->get_id() );
		} else {
			$allowed = $signed;
		}

		return (bool) apply_filters( 'nashenski/documents/can_download', $allowed, $order );
	}

	/**
	 * Only the order meta keys that hold document attachments may be requested.
	 */
	private static function is_document_meta( $meta ) {
		$documents = array(
			'nashenski_order_document',
			'nashenski_invoice_document',
			'nashenski_invoice_copy_document',
			'nashenski_refunded_order_document',
			'nashenski_refunded_invoice_document',
			'nashenski_refunded_invoice_copy_document',
			'nashenski_proform_document',
		);

		return in_array( preg_replace( '/_en$/', '', $meta ), $documents, true );
	}

	/**
	 * The file is in the private folder, or in the old public one while it is being migrated.
	 */
	private static function is_stored_here( $path ) {
		$uploads = wp_upload_dir( null, false );
		$base = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
		$path = wp_normalize_path( $path );

		foreach ( array_merge( array( self::subdir() ), self::LEGACY_SUBDIRS ) as $subdir ) {
			if ( 0 === strpos( $path, $base . $subdir . '/' ) && false === strpos( $path, '..' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Start moving documents out of the old public folders, once per site and MIGRATE_VERSION
	 * (version 2 added Bulgarisation's folders), and again after an import ($again).
	 */
	public static function schedule_migration( $again = false ) {
		if ( ( ! $again && (int) get_option( self::MIGRATE_VERSION_OPTION ) >= self::MIGRATE_VERSION ) || ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		if ( ! as_has_scheduled_action( self::MIGRATE_HOOK, array(), 'nashenski' ) ) {
			as_enqueue_async_action( self::MIGRATE_HOOK, array(), 'nashenski' );
		}

		if ( ! get_option( self::MIGRATE_OPTION ) ) {
			update_option( self::MIGRATE_OPTION, 'scheduled', false );
		}

		update_option( self::MIGRATE_VERSION_OPTION, self::MIGRATE_VERSION, false );
	}

	/**
	 * Move up to MIGRATE_BATCH old public documents into the private folder and make their
	 * attachments private; queues itself again while any are left.
	 */
	public static function migrate_batch( $after = 0 ) {
		global $wpdb;

		$private = self::path();
		$uploads = wp_upload_dir( null, false );

		// In ID order after $after, so a file that cannot be moved is skipped instead of blocking the rest.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time migration, batched.
		$ids = array_map( 'absint', $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wp_attached_file'
			WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.ID > %d
			AND p.post_mime_type IN ( 'application/pdf', 'application/xml', 'text/xml', 'text/plain' )
			AND ( m.meta_value LIKE %s OR m.meta_value LIKE %s OR m.meta_value LIKE %s )
			ORDER BY p.ID ASC
			LIMIT %d",
			absint( $after ),
			$wpdb->esc_like( self::LEGACY_SUBDIRS[0] . '/' ) . '%',
			$wpdb->esc_like( self::LEGACY_SUBDIRS[1] . '/' ) . '%',
			$wpdb->esc_like( self::LEGACY_SUBDIRS[2] . '/' ) . '%',
			self::MIGRATE_BATCH
		) ) );

		$failed = array();

		foreach ( $ids as $id ) {
			$old = get_attached_file( $id );
			$new = $private . '/' . basename( $old );

			if ( $old && file_exists( $old ) && ( file_exists( $new ) || ! @rename( $old, $new ) ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.rename_rename -- a failed move leaves the file where it was.
				$failed[] = $id;
				continue;
			}

			if ( file_exists( $new ) ) {
				update_attached_file( $id, $new );
			}

			wp_update_post( array( 'ID' => $id, 'post_status' => 'private' ) );
		}

		// Files left public, so a site owner (or support) can find them: the option holds their IDs.
		if ( $failed ) {
			$state = get_option( self::MIGRATE_OPTION );
			$state = is_array( $state ) ? $state : array();
			$state['failed'] = array_values( array_unique( array_merge( $state['failed'] ?? array(), $failed ) ) );
			update_option( self::MIGRATE_OPTION, $state, false );
		}

		if ( count( $ids ) === self::MIGRATE_BATCH ) {
			as_enqueue_async_action( self::MIGRATE_HOOK, array( end( $ids ) ), 'nashenski' );
			return;
		}

		// Leftovers of a failed invoice merge before 0.11.1.
		foreach ( array( 'original.pdf', 'copy.pdf' ) as $leftover ) {
			wp_delete_file( trailingslashit( $uploads['basedir'] ) . self::LEGACY_SUBDIR . '/' . $leftover );
		}
	}
}
