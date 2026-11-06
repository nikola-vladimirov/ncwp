<?php
/**
 * Export Builder.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Admin;

use Nashenski\Export\Builder\Builder;
use Nashenski\Export\Builder\Fields;
use Nashenski\Export\Builder\Writer;

defined( 'ABSPATH' ) || exit;

/**
 * Exports > Custom export: choose the records, the columns and the file, preview it, save it for
 * next time and download it. Files are made on request and never stored on the server.
 */
class Export_Builder {
	const NONCE = 'nashenski_export_builder';

	/**
	 * Construct.
	 */
	public function __construct() {
		add_action( 'wp_ajax_nashenski_export_preview', array( __CLASS__, 'preview' ) );
		add_action( 'wp_ajax_nashenski_export_save', array( __CLASS__, 'save' ) );
		add_action( 'wp_ajax_nashenski_export_delete', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_nashenski_export_download', array( __CLASS__, 'download' ) );
	}

	/**
	 * Check.
	 */
	private static function check() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'nashenski-commerce' ) ), 403 );
		}
	}

	/**
	 * Posted config.
	 */
	private static function posted_config() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked in check(); normalized below.
		$raw = isset( $_POST['config'] ) ? json_decode( wp_unslash( $_POST['config'] ), true ) : array();

		return Builder::normalize( $raw );
	}

	/**
	 * Preview.
	 */
	public static function preview() {
		self::check();

		$config = self::posted_config();

		if ( ! $config['columns'] ) {
			wp_send_json_error( array( 'message' => __( 'Add at least one column.', 'nashenski-commerce' ) ) );
		}

		$result = Builder::rows( $config, 20 );
		$kinds  = Fields::kinds( $config );

		$rows = array_map(
			function ( $row ) use ( $kinds, $config ) {
				return array_map(
					function ( $value, $i ) use ( $kinds, $config ) {
						return Builder::format( $value, $kinds[ $i ], $config );
					},
					$row,
					array_keys( $row )
				);
			},
			$result['rows']
		);

		wp_send_json_success(
			array(
				'labels' => wp_list_pluck( $config['columns'], 'label' ),
				'rows'   => $rows,
				'total'  => $result['total'],
				'file'   => Builder::filename( $config ),
			)
		);
	}

	/**
	 * Save.
	 */
	public static function save() {
		self::check();

		$config = self::posted_config();

		if ( '' === $config['name'] ) {
			wp_send_json_error( array( 'message' => __( 'Give the export a name.', 'nashenski-commerce' ) ) );
		}

		$templates         = Builder::templates();
		$key               = sanitize_title( $config['name'] );
		$key               = '' !== $key ? $key : 'export-' . wp_rand();
		$templates[ $key ] = $config;
		update_option( Builder::TEMPLATES_OPTION, $templates, false );

		wp_send_json_success(
			array(
				'key'       => $key,
				'templates' => $templates,
			)
		);
	}

	/**
	 * Delete.
	 */
	public static function delete() {
		self::check();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in check().
		$key       = isset( $_POST['key'] ) ? sanitize_title( wp_unslash( $_POST['key'] ) ) : '';
		$templates = Builder::templates();
		unset( $templates[ $key ] );
		update_option( Builder::TEMPLATES_OPTION, $templates, false );

		wp_send_json_success( array( 'templates' => $templates ) );
	}

	/**
	 * Download.
	 */
	public static function download() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( self::NONCE, 'nonce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'nashenski-commerce' ), 403 );
		}

		$config = self::posted_config();

		if ( ! $config['columns'] ) {
			wp_die( esc_html__( 'Add at least one column.', 'nashenski-commerce' ) );
		}

		if ( 'xlsx' === $config['format'] && ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'Excel files need the PHP Zip extension, which this server does not have. Choose CSV instead.', 'nashenski-commerce' ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- long exports.
		}

		$result = Builder::rows( $config );
		$path   = Writer::write( $config, $result['rows'] );
		$name   = Builder::filename( $config );

		nashenski_send_download( $path, $name, Writer::mime( $config['format'] ) . ( in_array( $config['format'], array( 'csv', 'json', 'xml' ), true ) && 'cp1251' !== $config['encoding'] ? '; charset=utf-8' : '' ) );
	}

	/**
	 * Data the builder script needs.
	 */
	public static function script_data() {
		$fields = array();

		foreach ( Fields::all() as $id => $field ) {
			$fields[] = array(
				'id'      => $id,
				'label'   => $field['label'],
				'group'   => $field['group'],
				'sources' => $field['sources'],
			);
		}

		return array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'postUrl'   => admin_url( 'admin-post.php' ),
			'nonce'     => wp_create_nonce( self::NONCE ),
			'fields'    => $fields,
			'presets'   => Builder::presets(),
			'templates' => Builder::templates(),
			'defaults'  => Builder::defaults(),
			'i18n'      => array(
				'newExport'     => __( 'New export', 'nashenski-commerce' ),
				'readyMade'     => __( 'Ready-made', 'nashenski-commerce' ),
				'savedGroup'    => __( 'Saved exports', 'nashenski-commerce' ),
				'add'           => __( 'Add', 'nashenski-commerce' ),
				'remove'        => __( 'Remove', 'nashenski-commerce' ),
				'up'            => __( 'Move up', 'nashenski-commerce' ),
				'down'          => __( 'Move down', 'nashenski-commerce' ),
				'heading'       => __( 'Column heading', 'nashenski-commerce' ),
				'noColumns'     => __( 'No columns yet. Add fields from the list.', 'nashenski-commerce' ),
				'saved'         => __( 'Saved.', 'nashenski-commerce' ),
				'deleted'       => __( 'Deleted.', 'nashenski-commerce' ),
				'confirmDelete' => __( 'Delete this saved export?', 'nashenski-commerce' ),
				/* translators: 1: rows shown, 2: rows in the file, 3: file name */
				'previewOf'     => __( 'First %1$d of %2$d rows. File: %3$s', 'nashenski-commerce' ),
				'noRows'        => __( 'Nothing matches these filters.', 'nashenski-commerce' ),
				'failed'        => __( 'Something went wrong. Please try again.', 'nashenski-commerce' ),
				'metaPrompt'    => __( 'Order meta key, for example _billing_company_eik', 'nashenski-commerce' ),
				'metaGroup'     => __( 'Order meta', 'nashenski-commerce' ),
			),
		);
	}

	/**
	 * Checkboxes.
	 *
	 * @param mixed $name Name.
	 * @param mixed $options Options.
	 * @param mixed $css_class Class.
	 */
	private static function checkboxes( $name, $options, $css_class = '' ) {
		echo '<div class="nashenski-builder__checks ' . esc_attr( $css_class ) . '">';

		foreach ( $options as $value => $label ) {
			printf(
				'<label><input type="checkbox" data-list="%1$s" value="%2$s"> %3$s</label>',
				esc_attr( $name ),
				esc_attr( $value ),
				esc_html( $label )
			);
		}

		echo '</div>';
	}

	/**
	 * Output.
	 */
	public static function output() {
		$gateways = array();

		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
			$gateways[ $id ] = $gateway->get_title() ? $gateway->get_title() : $id;
		}

		$couriers = nashenski_couriers() + array( 'other' => __( 'Other shipping', 'nashenski-commerce' ) );
		?>
		<div class="nashenski-builder" id="nashenski-builder">
			<h2><?php esc_html_e( 'Custom export', 'nashenski-commerce' ); ?></h2>
			<p class="nashenski-builder__intro"><?php esc_html_e( 'Build your own file: choose the records, the columns and their order, the format and the file name. Preview it, save it for next month and download it.', 'nashenski-commerce' ); ?></p>

			<div class="nashenski-builder__bar">
				<label for="nashenski-builder-template"><?php esc_html_e( 'Start from', 'nashenski-commerce' ); ?></label>
				<select id="nashenski-builder-template" class="nashenski-builder__template"></select>
				<button type="button" class="button-link button-link-delete nashenski-builder__delete" hidden><?php esc_html_e( 'Delete this saved export', 'nashenski-commerce' ); ?></button>
			</div>

			<div class="nashenski-builder__steps">
				<fieldset class="nashenski-builder__step">
					<legend><span>1</span> <?php esc_html_e( 'What to export', 'nashenski-commerce' ); ?></legend>
					<div class="nashenski-builder__sources">
						<label><input type="radio" name="nashenski_builder_source" value="orders"> <strong><?php esc_html_e( 'Orders', 'nashenski-commerce' ); ?></strong><span><?php esc_html_e( 'One row per order', 'nashenski-commerce' ); ?></span></label>
						<label><input type="radio" name="nashenski_builder_source" value="lines"> <strong><?php esc_html_e( 'Products sold', 'nashenski-commerce' ); ?></strong><span><?php esc_html_e( 'One row per product line', 'nashenski-commerce' ); ?></span></label>
						<label><input type="radio" name="nashenski_builder_source" value="documents"> <strong><?php esc_html_e( 'Documents', 'nashenski-commerce' ); ?></strong><span><?php esc_html_e( 'One row per invoice, credit note or order document', 'nashenski-commerce' ); ?></span></label>
					</div>
				</fieldset>

				<fieldset class="nashenski-builder__step">
					<legend><span>2</span> <?php esc_html_e( 'Which records', 'nashenski-commerce' ); ?></legend>
					<div class="nashenski-builder__row">
						<label><?php esc_html_e( 'From', 'nashenski-commerce' ); ?> <input type="date" data-key="from"></label>
						<label><?php esc_html_e( 'To', 'nashenski-commerce' ); ?> <input type="date" data-key="to"></label>
						<label><?php esc_html_e( 'By', 'nashenski-commerce' ); ?>
							<select data-key="date_field">
								<option value="created"><?php esc_html_e( 'order date', 'nashenski-commerce' ); ?></option>
								<option value="paid"><?php esc_html_e( 'payment date', 'nashenski-commerce' ); ?></option>
								<option value="completed"><?php esc_html_e( 'completion date', 'nashenski-commerce' ); ?></option>
							</select>
						</label>
					</div>
					<p class="nashenski-builder__quick">
						<button type="button" class="button button-small" data-range="this-month"><?php esc_html_e( 'This month', 'nashenski-commerce' ); ?></button>
						<button type="button" class="button button-small" data-range="last-month"><?php esc_html_e( 'Last month', 'nashenski-commerce' ); ?></button>
						<button type="button" class="button button-small" data-range="this-year"><?php esc_html_e( 'This year', 'nashenski-commerce' ); ?></button>
						<button type="button" class="button button-small" data-range="last-year"><?php esc_html_e( 'Last year', 'nashenski-commerce' ); ?></button>
					</p>
					<details class="nashenski-builder__filter">
						<summary><?php esc_html_e( 'Order status', 'nashenski-commerce' ); ?> <em data-summary="statuses"></em></summary>
						<?php self::checkboxes( 'statuses', wc_get_order_statuses() ); ?>
					</details>
					<details class="nashenski-builder__filter">
						<summary><?php esc_html_e( 'Payment method', 'nashenski-commerce' ); ?> <em data-summary="payments"></em></summary>
						<?php self::checkboxes( 'payments', $gateways ); ?>
					</details>
					<details class="nashenski-builder__filter">
						<summary><?php esc_html_e( 'Courier', 'nashenski-commerce' ); ?> <em data-summary="couriers"></em></summary>
						<?php self::checkboxes( 'couriers', $couriers ); ?>
					</details>
					<details class="nashenski-builder__filter" data-only="documents">
						<summary><?php esc_html_e( 'Document type', 'nashenski-commerce' ); ?> <em data-summary="doc_types"></em></summary>
						<?php self::checkboxes( 'doc_types', Fields::document_types() ); ?>
					</details>
					<label class="nashenski-builder__line"><input type="checkbox" data-key="company_only"> <?php esc_html_e( 'Only orders with a company invoice', 'nashenski-commerce' ); ?></label>
					<p class="description"><?php esc_html_e( 'Leave a list empty to include everything.', 'nashenski-commerce' ); ?></p>
				</fieldset>

				<fieldset class="nashenski-builder__step nashenski-builder__step--wide">
					<legend><span>3</span> <?php esc_html_e( 'Columns', 'nashenski-commerce' ); ?></legend>
					<div class="nashenski-builder__columns">
						<div class="nashenski-builder__available">
							<input type="search" class="nashenski-builder__search" placeholder="<?php esc_attr_e( 'Find a field', 'nashenski-commerce' ); ?>" aria-label="<?php esc_attr_e( 'Find a field', 'nashenski-commerce' ); ?>">
							<div class="nashenski-builder__fields" role="list"></div>
							<p><button type="button" class="button-link nashenski-builder__meta"><?php esc_html_e( 'Add an order meta field', 'nashenski-commerce' ); ?></button></p>
						</div>
						<div class="nashenski-builder__chosen">
							<p class="nashenski-builder__hint"><?php esc_html_e( 'Drag to reorder. Click a heading to rename it.', 'nashenski-commerce' ); ?></p>
							<ol class="nashenski-builder__list"></ol>
						</div>
					</div>
				</fieldset>

				<fieldset class="nashenski-builder__step nashenski-builder__step--wide">
					<legend><span>4</span> <?php esc_html_e( 'File', 'nashenski-commerce' ); ?></legend>
					<div class="nashenski-builder__formats">
						<label><input type="radio" name="nashenski_builder_format" value="csv"> CSV</label>
						<label><input type="radio" name="nashenski_builder_format" value="xlsx"> Excel (.xlsx)</label>
						<label><input type="radio" name="nashenski_builder_format" value="json"> JSON</label>
						<label><input type="radio" name="nashenski_builder_format" value="xml"> XML</label>
					</div>
					<div class="nashenski-builder__row">
						<label data-only-format="csv"><?php esc_html_e( 'Separator', 'nashenski-commerce' ); ?>
							<select data-key="separator">
								<option value=";"><?php esc_html_e( 'Semicolon ;', 'nashenski-commerce' ); ?></option>
								<option value=","><?php esc_html_e( 'Comma ,', 'nashenski-commerce' ); ?></option>
								<option value="tab"><?php esc_html_e( 'Tab', 'nashenski-commerce' ); ?></option>
								<option value="|"><?php esc_html_e( 'Vertical bar |', 'nashenski-commerce' ); ?></option>
							</select>
						</label>
						<label data-only-format="csv"><?php esc_html_e( 'Encoding', 'nashenski-commerce' ); ?>
							<select data-key="encoding">
								<option value="utf8bom"><?php esc_html_e( 'UTF-8 for Excel', 'nashenski-commerce' ); ?></option>
								<option value="utf8">UTF-8</option>
								<option value="cp1251"><?php esc_html_e( 'Windows-1251 (older accounting software)', 'nashenski-commerce' ); ?></option>
							</select>
						</label>
						<label><?php esc_html_e( 'Decimal mark', 'nashenski-commerce' ); ?>
							<select data-key="decimal">
								<option value=",">12,50</option>
								<option value=".">12.50</option>
							</select>
						</label>
						<label><?php esc_html_e( 'Dates', 'nashenski-commerce' ); ?>
							<select data-key="date_format">
								<option value="d.m.Y">31.12.2026</option>
								<option value="Y-m-d">2026-12-31</option>
								<option value="d.m.Y H:i">31.12.2026 14:30</option>
								<option value="Y-m-d H:i:s">2026-12-31 14:30:00</option>
								<option value="d/m/Y">31/12/2026</option>
							</select>
						</label>
					</div>
					<div class="nashenski-builder__row">
						<label class="nashenski-builder__grow"><?php esc_html_e( 'File name', 'nashenski-commerce' ); ?> <input type="text" data-key="filename" spellcheck="false"></label>
						<label data-only-format="xml"><?php esc_html_e( 'Root element', 'nashenski-commerce' ); ?> <input type="text" data-key="xml_root" size="10" spellcheck="false"></label>
						<label data-only-format="xml"><?php esc_html_e( 'Row element', 'nashenski-commerce' ); ?> <input type="text" data-key="xml_row" size="10" spellcheck="false"></label>
					</div>
					<p class="description"><?php esc_html_e( 'In the file name, {from}, {to}, {today} and {source} are replaced with the dates and the record type.', 'nashenski-commerce' ); ?></p>
					<label class="nashenski-builder__line"><input type="checkbox" data-key="header"> <?php esc_html_e( 'Headings in the first row', 'nashenski-commerce' ); ?></label>
				</fieldset>
			</div>

			<div class="nashenski-builder__actions">
				<label><?php esc_html_e( 'Save as', 'nashenski-commerce' ); ?> <input type="text" data-key="name" placeholder="<?php esc_attr_e( 'For example: Monthly sales for the accountant', 'nashenski-commerce' ); ?>"></label>
				<button type="button" class="button nashenski-builder__save"><?php esc_html_e( 'Save export', 'nashenski-commerce' ); ?></button>
				<span class="nashenski-builder__spacer"></span>
				<button type="button" class="button nashenski-builder__preview-button"><?php esc_html_e( 'Preview', 'nashenski-commerce' ); ?></button>
				<button type="button" class="button button-primary nashenski-builder__download"><?php esc_html_e( 'Download file', 'nashenski-commerce' ); ?></button>
				<span class="spinner"></span>
			</div>
			<div class="nashenski-builder__message" aria-live="polite"></div>
			<div class="nashenski-builder__preview"></div>
		</div>
		<?php
	}
}
