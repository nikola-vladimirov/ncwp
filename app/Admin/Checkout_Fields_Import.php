<?php
namespace Nashenski\Admin;

use Nashenski\Front_End\Checkout\Conditions;
use Nashenski\Front_End\Checkout\Field_Editor;

defined( 'ABSPATH' ) || exit;

/**
 * Copies the checkout fields of WooCommerce Checkout Field Editor (free or Pro) into the Checkout
 * fields table: show, required and hidden for the address fields, widths, order, the extra fields
 * and their field-value conditions (equals, not equals, greater, less, empty, not empty). Cart,
 * product, repeat and other rules are left out and counted. Company invoice fields stay with
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
				$text .= ' ' . sprintf( __( 'Fields with conditions taken over: %d.', 'nashenski-commerce' ), $done['conditions'] );
			}

			if ( $done['left_out'] ) {
				/* translators: %d: number of fields and sections */
				$text .= ' ' . sprintf( __( 'Left out, rules Nashenski does not have (cart, product, repeat and others): %d.', 'nashenski-commerce' ), $done['left_out'] );
			}

			$html .= '<div class="notice notice-success inline"><p>' . esc_html( $text ) . '</p></div>';
		}

		if ( nashenski_query_arg( 'nashenski_import_failed' ) ) {
			$html .= '<div class="notice notice-error inline"><p>' . esc_html__( 'The fields of Checkout Field Editor could not be read. Activate that plugin and import again.', 'nashenski-commerce' ) . '</p></div>';
		}

		if ( is_array( get_option( self::SOURCE ) ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION );
			$html .= '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Import fields from Checkout Field Editor', 'nashenski-commerce' ) . '</a></p>';
			$html .= '<p class="description">' . esc_html__( 'Copies the show, required and hidden rules, widths, order, extra fields and their conditions on other fields of WooCommerce Checkout Field Editor (free or Pro). It replaces the table above.', 'nashenski-commerce' ) . '</p>';
		}

		return $html;
	}

	public static function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'nashenski-commerce' ) );
		}

		check_admin_referer( self::ACTION );

		$sections = get_option( self::SOURCE );

		$back = wp_get_referer() ? wp_get_referer() : Settings::url();

		if ( is_array( $sections ) && ! self::readable( $sections ) ) {
			wp_safe_redirect( add_query_arg( 'nashenski_import_failed', 1, $back ) );
			exit;
		}

		if ( is_array( $sections ) ) {
			update_option( self::DONE_OPTION, self::run( $sections ), false );
		}

		wp_safe_redirect( add_query_arg( 'nashenski_imported_fields', 1, $back ) );
		exit;
	}

	/**
	 * Are the saved sections real objects? With the other plugin switched off, PHP gives back
	 * __PHP_Incomplete_Class objects, and an import would reset the standard fields.
	 */
	private static function readable( $sections ) {
		foreach ( $sections as $section ) {
			if ( ! is_object( $section ) || $section instanceof \__PHP_Incomplete_Class ) {
				return false;
			}

			foreach ( (array) ( $section->fields ?? array() ) as $field ) {
				if ( $field instanceof \__PHP_Incomplete_Class ) {
					return false;
				}
			}
		}

		return (bool) $sections;
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

		// The save path drops rules that read a missing row, their own field or loop back; those are not taken over (counted once).
		// A rule that lost one of its conditions would show the field in more cases than the source allowed, so it is dropped whole.
		$report = $converted['report'];
		$source = array();

		foreach ( $converted['rows'] as $row ) {
			$source[ $row['key'] ] = $row;
		}

		$report['conditions'] = 0;

		foreach ( $rows as $i => $row ) {
			if ( empty( $source[ $row['key'] ]['when'] ) ) {
				continue;
			}

			if ( ! empty( $row['when'] ) && self::count_conditions( $row['when'] ) < self::count_conditions( $source[ $row['key'] ]['when'] ) ) {
				$rows[ $i ]['when'] = null;
				$row['when'] = null;
			}

			if ( ! empty( $row['when'] ) ) {
				$report['conditions']++;
			} elseif ( empty( $source[ $row['key'] ]['counted'] ) ) {
				$report['left_out']++;
			}
		}

		// Every option write comes last, once the conversion has worked.
		foreach ( array_diff( $standard, array_keys( $converted['rules'] ) ) as $field ) {
			// Standard fields the other plugin does not have go back to the default.
			nashenski_set_option( 'email' === $field ? Field_Editor::GROUP_CLASSIC : Field_Editor::GROUP, $field, 'default' );
		}

		if ( null !== $converted['order_comments'] ) {
			nashenski_set_option( Field_Editor::GROUP_CLASSIC, 'order_comments', $converted['order_comments'] );
		}

		update_option( Field_Editor::LAYOUT_OPTION, $rows, false );

		return $report;
	}

	private static function count_conditions( $when ) {
		$count = 0;

		foreach ( $when['sets'] as $set ) {
			$count += count( $set );
		}

		return $count;
	}

	/**
	 * The other plugin's fields as table rows (in checkout order) and the rule of each standard field.
	 * Billing comes before shipping, so the billing rule wins for the shared address fields.
	 * A field-value rule that points at a field the table does not have is left out, like a rule the
	 * table cannot run.
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
		$pending = array();
		$choices = array();
		$checkboxes = array();
		$order_comments = null;
		$report = array( 'address' => 0, 'extra' => 0, 'skipped' => array(), 'conditions' => 0, 'left_out' => 0 );
		$standard = array_merge( array( 'email' ), Field_Editor::KEYS );

		foreach ( $sections as $section ) {
			if ( self::has_rules( $section->conditional_rules_json ?? '' ) || self::has_rules( $section->conditional_rules_ajax_json ?? '' ) ) {
				$report['left_out']++;
			}

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

				$rule = self::field_rule( $field );

				if ( 'order_comments' === $key ) {
					$report['left_out'] += ( $rule['left_out'] || $rule['sets'] ) && $enabled ? 1 : 0;
					$order_comments = $enabled ? 'default' : 'hidden';
					continue;
				}

				// Rules are taken over on the extra fields only: an address field is set for billing and shipping alike, and a shipping field has no rule of its own here.
				if ( in_array( $key, $standard, true ) || 0 === strpos( $name, 'shipping_' ) ) {
					if ( $rule['sets'] ) {
						$rule['sets'] = null;
						$rule['left_out'] = true;
					}
				}

				// Counted before any skip below, so a dropped rule is reported. A company field or a switched-off field is not a dropped rule: it is not shown at all.
				if ( $rule['left_out'] && $enabled && ! preg_match( self::COMPANY, $name ) ) {
					$report['left_out']++;
				}

				if ( in_array( $key, $standard, true ) ) {
					if ( ! isset( $rules[ $key ] ) ) {
						$rules[ $key ] = ! $enabled ? 'hidden' : ( $required ? 'required' : 'optional' );
						$rows[] = array( 'key' => $key, 'rule' => $rules[ $key ], 'width' => $width, 'when' => null );
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

				// A choice without text shows its key, in the list and in the conditions alike.
					$texts = array();

					foreach ( (array) $field->options as $option ) {
						$option = (array) $option;
						$texts[ (string) ( $option['key'] ?? '' ) ] = '' !== (string) ( $option['text'] ?? '' ) ? (string) $option['text'] : (string) ( $option['key'] ?? '' );
					}

					$choices[ $row_key ] = $texts;
				$checkboxes[ $row_key ] = 'checkbox' === $field->type ? ( Conditions::clean_value( $field->value ?? '' ) ?: '1' ) : null;

				$rows[] = array(
					'key' => $row_key,
					'label' => '' !== (string) $field->title ? $field->title : ucfirst( str_replace( '_', ' ', $key ) ),
					'type' => isset( Field_Editor::TYPES[ $field->type ] ) ? $field->type : 'text',
					'options' => implode( "\n", $texts ),
					'rule' => $required ? 'required' : 'optional',
					'width' => $width,
					'when' => null,
				);
				$report['extra']++;

				if ( $rule['sets'] ) {
					$pending[ count( $rows ) - 1 ] = array( 'sets' => $rule['sets'], 'left_out' => $rule['left_out'] );
				}
			}
		}

		// The rules point at rows by key; the rows are checked against each other in Field_Editor::sanitize_layout_field().
		foreach ( $pending as $index => $item ) {
			$when = array( 'hide' => $item['sets']['hide'], 'sets' => $item['sets']['sets'] );
			foreach ( $when['sets'] as $s => $set ) {
				foreach ( $set as $c => $condition ) {
					// A list keeps the choice text, while the other plugin compares the option key.
					if ( isset( $choices[ $condition['field'] ][ $condition['value'] ] ) ) {
						$when['sets'][ $s ][ $c ]['value'] = Conditions::clean_value( $choices[ $condition['field'] ][ $condition['value'] ] );
					}
					if ( isset( $checkboxes[ $condition['field'] ] ) && $checkboxes[ $condition['field'] ] === $condition['value'] ) {
						$when['sets'][ $s ][ $c ]['value'] = '1';
					} elseif ( isset( $checkboxes[ $condition['field'] ] ) && '0' === $condition['value'] ) {
						// An unchecked box without an on_value posts 0 in the other plugin; here it posts nothing.
						$when['sets'][ $s ][ $c ]['value'] = '';
					}
				}
			}

			$rows[ $index ]['when'] = $when;
			// A condition on a row the table lacks, a loop or a self rule are dropped when the rows are cleaned (Field_Editor::sanitize_layout_field()), as on save.
			$rows[ $index ]['counted'] = $item['left_out'];
			$report['conditions']++;
		}

		return array( 'rows' => $rows, 'rules' => $rules, 'report' => $report, 'order_comments' => $order_comments );
	}
	/**
	 * The field-value rule of a field, from WooCommerce Checkout Field Editor: its condition sets
	 * (sets: array of sets of conditions, with hide), and whether the field has a rule that cannot be
	 * taken over (left_out). Stored as urlencoded JSON. The other plugin's classic check reads the
	 * levels as AND (rule sets), AND (rules), OR (condition sets), AND (conditions). Only one rule set
	 * with one rule is taken over, where the condition sets are "or" and each set is "and"; any other
	 * shape is left out, so the two checkouts cannot read it differently.
	 */
	private static function field_rule( $field ) {
		$rule = array( 'sets' => null, 'left_out' => self::has_rules( $field->conditional_rules_json ?? '' ) || self::has_rules( $field->repeat_rules ?? '' ) );
		$data = json_decode( urldecode( (string) ( $field->conditional_rules_ajax_json ?? '' ) ), true );
		$rule_sets = array_values( is_array( $data ) ? $data : array() );

		if ( ! $rule_sets ) {
			return $rule;
		}

		$rules = array_values( (array) $rule_sets[0] );

		if ( 1 !== count( $rule_sets ) || 1 !== count( $rules ) ) {
			$rule['left_out'] = true;

			return $rule;
		}

		$sets = array();

		foreach ( (array) $rules[0] as $condition_set ) {
			$conditions = array();

			foreach ( (array) $condition_set as $condition ) {
				$converted = self::field_condition( $condition );

				if ( null === $converted ) {
					$rule['left_out'] = true;

					return $rule;
				}

				$conditions[] = $converted;
			}

			if ( $conditions ) {
				$sets[] = $conditions;
			}
		}

		if ( $sets ) {
			$rule['sets'] = array( 'hide' => 'hide' === (string) ( $field->rules_action_ajax ?? '' ), 'sets' => $sets );
		}

		return $rule;
	}

	/**
	 * One condition as Nashenski keeps it (the row key it reads), or null when it cannot be taken over.
	 */
	private static function field_condition( $condition ) {
		if ( ! is_array( $condition ) || 'field' !== ( $condition['operand_type'] ?? '' ) || ! in_array( $condition['operator'] ?? '', Conditions::OPERATORS, true ) ) {
			return null;
		}

		$operand = (array) ( $condition['operand'] ?? array() );

		if ( 1 !== count( $operand ) ) {
			return null;
		}

		$raw = reset( $operand );

		if ( ! is_scalar( $raw ) || ! is_scalar( $condition['value'] ?? '' ) ) {
			return null;
		}

		$raw = (string) $raw;

		// A trigger on a shipping field would read the billing value; it is left out. A field of a custom section has no prefix.
		if ( '' === $raw || 0 === strpos( $raw, 'shipping_' ) ) {
			return null;
		}

		$name = preg_replace( '/^(billing|additional)_/', '', $raw );
		$field = in_array( $name, array_merge( array( 'email' ), Field_Editor::KEYS ), true ) ? $name : 'custom_' . sanitize_key( $name );

		return array( 'field' => $field, 'operator' => $condition['operator'], 'value' => Conditions::clean_value( $condition['value'] ?? '' ) );
	}

	/**
	 * A rule is set when its text is neither empty nor an empty list.
	 */
	private static function has_rules( $json ) {
		$text = trim( urldecode( (string) $json ) );

		return '' !== $text && '[]' !== $text;
	}
}
