<?php
namespace Nashenski\Admin;

use Nashenski\Front_End\Checkout\Field_Editor;

defined( 'ABSPATH' ) || exit;

/**
 * Copies the checkout fields of WooCommerce Checkout Field Editor (free or Pro) into the Checkout
 * fields table: show, required and hidden for the address fields, widths, order and the extra
 * fields. Conditions of the Pro version are not taken over. Company invoice fields stay with
 * Nashenski's own company fields. A new import replaces the table.
 */
class Checkout_Fields_Import {
	const ACTION = 'nashenski_import_checkout_fields';
	const SOURCE = 'thwcfe_sections';
	const DONE_OPTION = 'nashenski_imported_checkout_fields';

	/** Extra fields of the company invoice: Nashenski's own company fields take them over. */
	const COMPANY = '/company|invoice|vat|mol|eik/i';

	public function __construct() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * The import link under the table, and the result of the last import.
	 */
	public static function html() {
		$html = '';

		$done = get_option( self::DONE_OPTION );

		if ( $done && nashenski_query_arg( 'nashenski_imported_fields' ) ) {
			$text = sprintf(
				/* translators: 1: number of address fields, 2: number of extra fields */
				__( 'Import finished: %1$d address fields and %2$d extra fields.', 'nashenski-commerce' ),
				$done['address'],
				$done['extra']
			);

			if ( $done['skipped'] ) {
				/* translators: %s: field names */
				$text .= ' ' . sprintf( __( 'Not taken over, company invoice fields (Nashenski has its own): %s.', 'nashenski-commerce' ), implode( ', ', $done['skipped'] ) );
			}

			if ( $done['conditions'] ) {
				/* translators: %d: number of fields */
				$text .= ' ' . sprintf( __( 'Fields with conditions, not taken over: %d.', 'nashenski-commerce' ), $done['conditions'] );
			}

			$html .= '<div class="notice notice-success inline"><p>' . esc_html( $text ) . '</p></div>';
		}

		if ( is_array( get_option( self::SOURCE ) ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
			$html .= '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Import fields from Checkout Field Editor', 'nashenski-commerce' ) . '</a></p>';
			$html .= '<p class="description">' . esc_html__( 'Copies the show, required and hidden rules, widths, order and extra fields of WooCommerce Checkout Field Editor (free or Pro). It replaces the table above.', 'nashenski-commerce' ) . '</p>';
		}

		return $html;
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ) );
		}

		check_admin_referer( self::ACTION );

		$sections = get_option( self::SOURCE );

		if ( is_array( $sections ) ) {
			update_option( self::DONE_OPTION, self::run( $sections ), false );
		}

		$back = wp_get_referer() ? wp_get_referer() : Settings::url();
		wp_safe_redirect( add_query_arg( 'nashenski_imported_fields', 1, $back ) );
		exit;
	}

	/**
	 * Writes the table and the standard field rules from the other plugin's sections.
	 *
	 * @param array $sections Section objects of WooCommerce Checkout Field Editor.
	 * @return array Counts and the names left out.
	 */
	public static function run( $sections ) {
		$converted = self::convert( $sections );
		$standard = array_merge( array( 'email' ), Field_Editor::KEYS );

		$rows = Field_Editor::sanitize_layout_field( $converted['rows'], array( 'type' => 'nashenski_field_layout' ), $converted['rows'] );

		// Standard fields the other plugin does not have go back to the default.
		foreach ( array_diff( $standard, array_keys( $converted['rules'] ) ) as $field ) {
			nashenski_set_option( 'email' === $field ? Field_Editor::GROUP_CLASSIC : Field_Editor::GROUP, $field, 'default' );
		}

		update_option( Field_Editor::LAYOUT_OPTION, $rows, false );

		return $converted['report'];
	}

	/**
	 * The other plugin's fields as table rows (in checkout order) and the rule of each standard field.
	 * Billing comes before shipping, so the billing rule wins for the shared address fields.
	 *
	 * @param array $sections Section objects of WooCommerce Checkout Field Editor.
	 * @return array rows, rules (field => rule) and report.
	 */
	public static function convert( $sections ) {
		$sections = array_filter( (array) $sections, 'is_object' );
		usort( $sections, function ( $a, $b ) {
			return (float) $a->order <=> (float) $b->order;
		} );

		$rows = array();
		$rules = array();
		$seen = array();
		$report = array( 'address' => 0, 'extra' => 0, 'skipped' => array(), 'conditions' => 0 );
		$standard = array_merge( array( 'email' ), Field_Editor::KEYS );

		foreach ( $sections as $section ) {
			$fields = array_filter( (array) $section->fields, 'is_object' );
			usort( $fields, function ( $a, $b ) {
				return (float) $a->order <=> (float) $b->order;
			} );

			foreach ( $fields as $field ) {
				$name = (string) $field->name;
				$key = preg_replace( '/^(billing|shipping|additional)_/', '', $name );
				$enabled = '1' === (string) $field->enabled;
				$required = in_array( (string) $field->required, array( 'yes', '1' ), true );
				$width = preg_match( '/form-row-(first|last)/', (string) $field->cssclass ) ? 'half' : 'full';

				if ( 'order_comments' === $key ) {
					nashenski_set_option( Field_Editor::GROUP_CLASSIC, 'order_comments', $enabled ? 'default' : 'hidden' );
					continue;
				}

				if ( in_array( $key, $standard, true ) ) {
					if ( ! isset( $rules[ $key ] ) ) {
						$rules[ $key ] = ! $enabled ? 'hidden' : ( $required ? 'required' : 'optional' );
						$rows[] = array( 'key' => $key, 'rule' => $rules[ $key ], 'width' => $width );
						$report['address']++;
					}
					continue;
				}

				// WooCommerce's own country field, and the other plugin's fields that are not its own extra fields.
				if ( '1' !== (string) $field->custom_field || ! $enabled ) {
					continue;
				}

				if ( preg_match( self::COMPANY, $name ) ) {
					$report['skipped'][] = $name;
					continue;
				}

				$row_key = 'custom_' . sanitize_key( $key );

				if ( isset( $seen[ $row_key ] ) ) {
					continue;
				}

				$seen[ $row_key ] = true;

				if ( ! empty( $field->conditional_rules_json ) && '[]' !== $field->conditional_rules_json ) {
					$report['conditions']++;
				}

				$rows[] = array(
					'key' => $row_key,
					'label' => '' !== (string) $field->title ? $field->title : ucfirst( str_replace( '_', ' ', $key ) ),
					'type' => isset( Field_Editor::TYPES[ $field->type ] ) ? $field->type : 'text',
					'options' => implode( "\n", array_column( (array) $field->options, 'text' ) ),
					'rule' => $required ? 'required' : 'optional',
					'width' => $width,
				);
				$report['extra']++;
			}
		}

		return array( 'rows' => $rows, 'rules' => $rules, 'report' => $report );
	}
}
