<?php
namespace Nashenski\Front_End\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Field-value conditions of the checkout fields: a field or a row shows or hides by the value of
 * another checkout field. A rule is array( 'hide' => bool, 'sets' => array of condition sets ):
 * the sets are "or", the conditions of a set are "and". Imported from WooCommerce Checkout Field
 * Editor; the same rule is checked on the server (classic checkout at submit), in the classic script
 * (live) and as JSON Schema for the block checkout.
 */
class Conditions {
	const OPERATORS = array( 'value_eq', 'value_ne', 'value_gt', 'value_le', 'empty', 'not_empty' );

	/**
	 * Plain text rule shared with the classic script: ASCII spaces, tabs and line breaks become one space,
	 * and the ends are cut (no other whitespace, so NBSP stays).
	 */
	public static function clean_text( $text ) {
		return trim( preg_replace( '/[ \t\r\n]+/', ' ', (string) $text ), ' ' );
	}

	/**
	 * A typed or imported condition value or list choice: invalid UTF-8 out, then the plain rule above.
	 * Tags are kept as text (the value is only compared and escaped on output), so "&lt;18" stays as typed.
	 */
	public static function clean_value( $text ) {
		return self::clean_text( wp_check_invalid_utf8( (string) $text ) );
	}

	/**
	 * A number as the classic script reads one: a plain decimal, no hex, no Infinity.
	 */
	public static function is_number( $text ) {
		return 1 === preg_match( '/\A[+-]?(\d+\.?\d*|\.\d+)([eE][+-]?\d+)?\z/', (string) $text );
	}

	/**
	 * Is the row shown? $values: row key => value as a string ('1' for a ticked checkbox).
	 */
	public static function shown( $when, $values ) {
		if ( empty( $when['sets'] ) ) {
			return true;
		}

		$met = false;

		foreach ( $when['sets'] as $set ) {
			if ( self::set_met( $set, $values ) ) {
				$met = true;
				break;
			}
		}

		return empty( $when['hide'] ) ? $met : ! $met;
	}

	private static function set_met( $set, $values ) {
		foreach ( $set as $condition ) {
			if ( ! self::test( $condition, $values[ $condition['field'] ] ?? '' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * One condition against a value.
	 */
	public static function test( $condition, $value ) {
		$value = (string) $value;
		$operand = (string) $condition['value'];

		switch ( $condition['operator'] ) {
			case 'value_eq':
				return $value === $operand;
			case 'value_ne':
				return $value !== $operand;
			case 'value_gt':
				return self::is_number( $value ) && self::is_number( $operand ) && (float) $value > (float) $operand;
			case 'value_le':
				return self::is_number( $value ) && self::is_number( $operand ) && (float) $value < (float) $operand;
			case 'empty':
				return '' === $value;
			case 'not_empty':
				return '' !== $value;
		}

		return false;
	}

	/**
	 * JSON Schema for the block checkout: the "required" and "hidden" rules of one extra field.
	 * $types: row key => type of the trigger fields ('checkbox' or other). Returns null when a
	 * condition cannot be written as a block rule (greater, less, or a trigger that is not an extra
	 * field, or one a rule of its own can hide: its value stays in the block checkout); the block checkout then ignores the rule and the classic checkout keeps it.
	 */
	public static function block_rule( $when, $types, $required, $derived = array() ) {
		if ( empty( $when['sets'] ) ) {
			return null;
		}

		$any = array();

		foreach ( $when['sets'] as $set ) {
			$all = array();

			foreach ( $set as $condition ) {
				$schema = self::block_condition( $condition, $types, $derived );

				if ( null === $schema ) {
					return null;
				}

				$all[] = $schema;
			}

			$any[] = 1 === count( $all ) ? $all[0] : array( 'allOf' => $all );
		}

		// One set needs no combinator: the block checkout's rule check reads plain schemas best.
		$met = 1 === count( $any ) ? $any[0] : array( 'anyOf' => $any );
		// The extra fields sit in the contact location, so WooCommerce keeps their values under customer, not checkout.
		$wrap = function ( $schema ) {
			return array( 'customer' => array( 'properties' => array( 'additional_fields' => $schema ) ) );
		};

		if ( empty( $when['hide'] ) ) {
			return array(
				'hidden' => $wrap( array( 'not' => $met ) ),
				'required' => $required ? $wrap( $met ) : false,
			);
		}

		return array(
			'hidden' => $wrap( $met ),
			'required' => $required ? $wrap( array( 'not' => $met ) ) : false,
		);
	}

	private static function block_condition( $condition, $types, $derived = array() ) {
		$field = $condition['field'];

		if ( 0 !== strpos( $field, 'custom_' ) || ! isset( $types[ $field ] ) || in_array( $field, $derived, true ) || ! in_array( $condition['operator'], array( 'value_eq', 'value_ne', 'empty', 'not_empty' ), true ) ) {
			return null;
		}

		$id = 'nashenski/' . substr( $field, 7 );
		$checkbox = 'checkbox' === $types[ $field ];
		// A blank-only text is not filled, as the classic script cleans it to nothing.
		$filled = array( 'required' => array( $id ), 'properties' => array( $id => $checkbox ? array( 'const' => true ) : array( 'pattern' => '[^ \\t\\r\\n]' ) ) );

		if ( 'empty' === $condition['operator'] || 'not_empty' === $condition['operator'] ) {
			return 'not_empty' === $condition['operator'] ? $filled : array( 'not' => $filled );
		}

		// Equal to nothing means empty, as the classic check reads a missing field.
		$text = self::clean_text( $condition['value'] );

		// A box is ticked or not: any other value cannot be written as a block rule.
		if ( $checkbox && '' !== $text && '1' !== $text ) {
			return null;
		}

		// The classic check cleans the typed value too, so " Sofia" and "Sofia" are the same: spaces around and between the words are allowed.
		$words = array_map( function ( $word ) {
			return addcslashes( $word, '\\^$.*+?()[]{}|/' );
		}, explode( ' ', $text ) );
		$match = $checkbox ? array( 'const' => true ) : array( 'pattern' => '^[ \\t\\r\\n]*' . implode( '[ \\t\\r\\n]+', $words ) . '[ \\t\\r\\n]*$' );
		$equal = '' === $text ? array( 'not' => $filled ) : array( 'required' => array( $id ), 'properties' => array( $id => $match ) );

		return 'value_eq' === $condition['operator'] ? $equal : array( 'not' => $equal );
	}
}
