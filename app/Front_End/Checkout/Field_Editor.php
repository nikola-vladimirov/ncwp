<?php
namespace Nashenski\Front_End\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Checkout field editor: order, width, show, require, make optional or hide checkout fields,
 * and the store's own extra fields.
 *
 * One setting per address field applies to the billing and the shipping address, in the
 * classic checkout (and every builder that uses it) and in the block checkout:
 * - address fields go through WooCommerce's default address fields and country locale,
 *   which both checkouts read (the block checkout also takes their order from there);
 * - company, address line 2 and phone go through WooCommerce's own options for them,
 *   which the block checkout reads too;
 * - email and order notes can only be hidden in the classic checkout;
 * - the width and the place of email and the extra fields apply to the classic checkout; in the
 *   block checkout the extra fields sit under the email. Address field widths apply to both.
 */
class Field_Editor {
	const GROUP = 'fields';
	const GROUP_CLASSIC = 'fields_classic';

	/** Order, width and extra fields: list of rows ( key, width [, rule, label, type, options] ). */
	const LAYOUT_OPTION = 'nashenski_settings_fields_layout';

	// Address field keys without the billing_/shipping_ prefix (labels in address_fields(), which needs translations loaded).
	const KEYS = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone' );

	/** Types of extra fields => block checkout type (it has text, select and checkbox). */
	const TYPES = array( 'text' => 'text', 'textarea' => 'text', 'select' => 'select', 'checkbox' => 'checkbox', 'email' => 'text', 'tel' => 'text', 'number' => 'text', 'date' => 'text' );

	public function __construct() {
		if ( self::custom_rows() ) {
			add_action( 'woocommerce_init', array( __CLASS__, 'register_block_fields' ) );
			add_action( 'woocommerce_set_additional_field_value', array( __CLASS__, 'save_block_field' ), 10, 4 );
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_values' ) );
			add_filter( 'woocommerce_email_order_meta_fields', array( __CLASS__, 'email_values' ), 10, 3 );
			// Before the company invoice fields' template (10), so these come first.
			add_action( 'wp_footer', array( __CLASS__, 'print_template' ), 9 );
		}

		if ( ! self::has_rules() && ! self::layout() ) {
			return;
		}

		add_filter( 'woocommerce_default_address_fields', array( __CLASS__, 'default_address_fields' ), 1000 );
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'country_locale' ), 1000 );
		add_filter( 'woocommerce_get_country_locale_default', array( __CLASS__, 'locale_entry' ), 1000 );
		add_filter( 'woocommerce_get_country_locale_base', array( __CLASS__, 'locale_entry' ), 1000 );
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'billing_fields' ), 1000 );
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'checkout_fields' ), 1000 );

		foreach ( self::option_fields() as $field => $option ) {
			$override = function ( $value ) use ( $field ) {
				$rule = self::rule( $field );

				return 'default' === $rule ? $value : $rule;
			};

			// "option_" runs when the option was saved, "default_option_" when it never was.
			add_filter( 'option_' . $option, $override );
			add_filter( 'default_option_' . $option, $override );
		}

		if ( 'hidden' === self::rule( 'order_comments' ) ) {
			add_filter( 'woocommerce_enable_order_notes_field', '__return_false', 1000 );
		}

		// Page builders can place single fields by key and render them even when the field
		// was removed (Bricks does), so hidden fields also render as nothing.
		add_filter( 'woocommerce_form_field', array( __CLASS__, 'hide_rendered_field' ), PHP_INT_MAX, 2 );
	}

	public static function hide_rendered_field( $html, $key ) {
		// With company invoice fields on, the company name belongs to them.
		if ( 'billing_company' === $key && Company_Fields::enabled() ) {
			return $html;
		}

		if ( preg_match( '/^(?:billing|shipping)_(.+)$/', (string) $key, $m ) && in_array( $m[1], self::KEYS, true ) ) {
			$field = $m[1];
		} elseif ( 'billing_email' === $key ) {
			$field = 'email';
		} elseif ( 'order_comments' === $key ) {
			$field = 'order_comments';
		} else {
			return $html;
		}

		return 'hidden' === self::rule( $field ) ? '' : $html;
	}

	/**
	 * Address fields (without the billing_/shipping_ prefix) and their labels.
	 */
	public static function address_fields() {
		return array(
			'first_name' => __( 'First name', 'nashenski-commerce' ),
			'last_name' => __( 'Last name', 'nashenski-commerce' ),
			'company' => __( 'Company name', 'nashenski-commerce' ),
			'address_1' => __( 'Street address', 'nashenski-commerce' ),
			'address_2' => __( 'Apartment, suite, unit (address line 2)', 'nashenski-commerce' ),
			'city' => __( 'Town / City', 'nashenski-commerce' ),
			'state' => __( 'Region / State', 'nashenski-commerce' ),
			'postcode' => __( 'Postcode', 'nashenski-commerce' ),
			'phone' => __( 'Phone', 'nashenski-commerce' ),
		);
	}

	/**
	 * Block checkout: the widths from the layout. WooCommerce lets every field grow to the full
	 * line when it is alone, so the widths are fixed here (two halves and the 12px gap fill a line).
	 */
	public static function block_css() {
		$css = '';

		foreach ( self::layout() as $row ) {
			if ( in_array( $row['key'], self::KEYS, true ) ) {
				$field = '.wc-block-components-address-form__' . $row['key'];
				$css .= '.wc-block-checkout .wc-block-components-address-form ' . $field . $field . '{flex:0 0 ' . ( 'half' === $row['width'] ? 'calc(50% - 6px)' : '100%' ) . '}';
			}
		}

		return $css;
	}

	/**
	 * Fields WooCommerce controls with an option of its own ("hidden", "optional", "required").
	 */
	private static function option_fields() {
		return array(
			'company' => 'woocommerce_checkout_company_field',
			'address_2' => 'woocommerce_checkout_address_2_field',
			'phone' => 'woocommerce_checkout_phone_field',
		);
	}

	public static function rule( $field ) {
		$group = in_array( $field, array( 'email', 'order_comments' ), true ) ? self::GROUP_CLASSIC : self::GROUP;
		$rule = nashenski_get_option( $group, $field );

		return in_array( $rule, array( 'required', 'optional', 'hidden' ), true ) ? $rule : 'default';
	}

	private static function has_rules() {
		foreach ( array_merge( self::KEYS, array( 'email', 'order_comments' ) ) as $field ) {
			if ( 'default' !== self::rule( $field ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The saved order, widths and extra fields, or array() when never saved (WooCommerce's layout).
	 */
	public static function layout() {
		$layout = get_option( self::LAYOUT_OPTION );

		return is_array( $layout ) ? $layout : array();
	}

	private static function custom_rows() {
		return array_filter( self::layout(), function ( $row ) {
			return 0 === strpos( $row['key'], 'custom_' ) && 'hidden' !== ( $row['rule'] ?? '' );
		} );
	}

	/**
	 * Checkout field name of a row: billing_email, shipping_city, billing_nashenski_<slug>.
	 */
	private static function field_name( $type, $key ) {
		return 0 === strpos( $key, 'custom_' ) ? 'billing_nashenski_' . substr( $key, 7 ) : $type . '_' . $key;
	}

	/**
	 * Place of each field in the layout: key => priority (10, 20, ...).
	 */
	private static function priorities() {
		$priorities = array();

		foreach ( array_values( self::layout() ) as $i => $row ) {
			$priorities[ $row['key'] ] = ( $i + 1 ) * 10;
		}

		return $priorities;
	}

	/**
	 * Apply a rule to one field definition. Returns null when the field must be removed.
	 */
	private static function apply_rule( $definition, $rule ) {
		switch ( $rule ) {
			case 'hidden':
				return null;
			case 'required':
				$definition['required'] = true;
				$definition['hidden'] = false;
				break;
			case 'optional':
				$definition['required'] = false;
				break;
		}

		return $definition;
	}

	public static function default_address_fields( $fields ) {
		$priorities = self::priorities();

		foreach ( self::KEYS as $key ) {
			if ( ! isset( $fields[ $key ] ) ) {
				continue;
			}

			$definition = self::apply_rule( $fields[ $key ], self::rule( $key ) );

			if ( null === $definition ) {
				unset( $fields[ $key ] );
				continue;
			}

			if ( isset( $priorities[ $key ] ) ) {
				$definition['priority'] = $priorities[ $key ];
			}

			$fields[ $key ] = $definition;
		}

		// The country goes just before the street.
		if ( isset( $fields['country'], $priorities['address_1'] ) ) {
			$fields['country']['priority'] = $priorities['address_1'] - 5;
		}

		return $fields;
	}

	/**
	 * Country locale entries override the default fields per country, so the rules go there too.
	 * The block checkout reads "hidden", "required" and the order ("priority") from here.
	 */
	public static function locale_entry( $locale ) {
		$priorities = self::priorities();

		foreach ( self::KEYS as $key ) {
			$rule = self::rule( $key );

			if ( 'default' === $rule && ! isset( $priorities[ $key ] ) ) {
				continue;
			}

			$entry = isset( $locale[ $key ] ) && is_array( $locale[ $key ] ) ? $locale[ $key ] : array();

			if ( 'hidden' === $rule ) {
				$entry['hidden'] = true;
				$entry['required'] = false;
			} elseif ( 'default' !== $rule ) {
				$entry['hidden'] = false;
				$entry['required'] = 'required' === $rule;
			}

			if ( isset( $priorities[ $key ] ) ) {
				$entry['priority'] = $priorities[ $key ];
			}

			$locale[ $key ] = $entry;
		}

		if ( isset( $priorities['address_1'] ) ) {
			$locale['country'] = array_merge( isset( $locale['country'] ) && is_array( $locale['country'] ) ? $locale['country'] : array(), array( 'priority' => $priorities['address_1'] - 5 ) );
		}

		return $locale;
	}

	public static function country_locale( $locales ) {
		foreach ( $locales as $country => $locale ) {
			$locales[ $country ] = self::locale_entry( is_array( $locale ) ? $locale : array() );
		}

		return $locales;
	}

	public static function billing_fields( $fields ) {
		$rule = self::rule( 'email' );

		if ( 'hidden' === $rule ) {
			unset( $fields['billing_email'] );
		} elseif ( 'default' !== $rule && isset( $fields['billing_email'] ) ) {
			$fields['billing_email']['required'] = 'required' === $rule;
		}

		return $fields;
	}

	/**
	 * Classic checkout: the final list, after themes and other plugins added theirs.
	 */
	public static function checkout_fields( $fields ) {
		foreach ( array( 'billing', 'shipping' ) as $type ) {
			if ( empty( $fields[ $type ] ) ) {
				continue;
			}

			foreach ( self::KEYS as $key ) {
				$name = $type . '_' . $key;

				if ( ! isset( $fields[ $type ][ $name ] ) || ( 'billing_company' === $name && Company_Fields::enabled() ) ) {
					continue;
				}

				$definition = self::apply_rule( $fields[ $type ][ $name ], self::rule( $key ) );

				if ( null === $definition ) {
					unset( $fields[ $type ][ $name ] );
				} else {
					$fields[ $type ][ $name ] = $definition;
				}
			}

			if ( self::layout() ) {
				$fields[ $type ] = self::arrange( $fields[ $type ], $type );
			}
		}

		$fields['billing'] = self::billing_fields( $fields['billing'] ?? array() );

		if ( 'hidden' === self::rule( 'order_comments' ) ) {
			unset( $fields['order']['order_comments'] );
		}

		return $fields;
	}

	/**
	 * Order and width from the layout, and the extra fields in the billing section. Two half-width
	 * fields in a row share a line.
	 */
	private static function arrange( $section, $type ) {
		$priorities = self::priorities();
		$widths = array();

		foreach ( self::layout() as $row ) {
			$name = self::field_name( $type, $row['key'] );

			if ( 0 === strpos( $row['key'], 'custom_' ) ) {
				if ( 'billing' !== $type || 'hidden' === ( $row['rule'] ?? '' ) ) {
					continue;
				}

				$section[ $name ] = self::custom_definition( $row );
			}

			// With company invoice fields on, the company name belongs to them.
			if ( isset( $section[ $name ] ) && ! ( 'billing_company' === $name && Company_Fields::enabled() ) ) {
				$section[ $name ]['priority'] = $priorities[ $row['key'] ];
				$widths[ $name ] = $row['width'];
			}
		}

		// ponytail: fields of other plugins placed after WooCommerce's last one (email, 110) stay after
		// the whole layout; ones placed in between keep their number.
		foreach ( $section as $name => $field ) {
			if ( ! isset( $widths[ $name ] ) && ( $field['priority'] ?? 0 ) >= 110 ) {
				$section[ $name ]['priority'] += count( $priorities ) * 10;
			}
		}

		if ( isset( $section[ $type . '_country' ], $priorities['address_1'] ) ) {
			$section[ $type . '_country' ]['priority'] = $priorities['address_1'] - 5;
		}

		uasort( $section, function ( $a, $b ) {
			return ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 );
		} );

		$left = true;

		foreach ( $section as $name => $field ) {
			if ( ! isset( $widths[ $name ] ) ) {
				$left = true;
				continue;
			}

			$class = 'half' === $widths[ $name ] ? ( $left ? 'form-row-first' : 'form-row-last' ) : 'form-row-wide';
			$left = 'half' === $widths[ $name ] ? ! $left : true;
			$section[ $name ]['class'] = array_merge( array_diff( (array) ( $field['class'] ?? array() ), array( 'form-row-first', 'form-row-last', 'form-row-wide' ) ), array( $class ) );
		}

		return $section;
	}

	/**
	 * An extra field for woocommerce_form_field().
	 */
	private static function custom_definition( $row ) {
		$definition = array(
			'type' => $row['type'],
			'label' => $row['label'],
			'required' => 'required' === $row['rule'],
			'class' => array(),
		);

		if ( 'select' === $row['type'] ) {
			$choices = self::choices( $row );
			$definition['options'] = array( '' => __( 'Choose an option', 'nashenski-commerce' ) ) + array_combine( $choices, $choices );
		} elseif ( 'email' === $row['type'] ) {
			$definition['validate'] = array( 'email' );
		} elseif ( 'tel' === $row['type'] ) {
			$definition['validate'] = array( 'phone' );
		}

		return $definition;
	}

	/**
	 * Builder checkouts made of single field elements: the extra fields, ready for the script to
	 * place after the billing fields when the template does not have them.
	 */
	public static function print_template() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || Pickers::is_block_checkout() || ! WC()->checkout() ) {
			return;
		}

		$fields = WC()->checkout()->get_checkout_fields( 'billing' );

		echo '<template class="nashenski-extra-fields">';

		foreach ( self::custom_rows() as $row ) {
			$key = self::field_name( 'billing', $row['key'] );

			if ( isset( $fields[ $key ] ) ) {
				woocommerce_form_field( $key, $fields[ $key ], WC()->checkout()->get_value( $key ) );
			}
		}

		echo '</template>';
	}

	private static function choices( $row ) {
		return array_values( array_filter( array_map( 'trim', explode( "\n", (string) ( $row['options'] ?? '' ) ) ), 'strlen' ) );
	}

	/**
	 * Block checkout: the extra fields under the email (text, list or checkbox there).
	 */
	public static function register_block_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		foreach ( self::custom_rows() as $row ) {
			$args = array(
				'id' => 'nashenski/' . substr( $row['key'], 7 ),
				'label' => $row['label'],
				'optionalLabel' => $row['label'] . ' ' . __( '(optional)', 'nashenski-commerce' ),
				'location' => 'contact',
				'type' => self::TYPES[ $row['type'] ] ?? 'text',
				'required' => 'required' === $row['rule'],
			);

			if ( 'select' === $args['type'] ) {
				$args['options'] = array_map( function ( $choice ) {
					return array( 'value' => $choice, 'label' => $choice );
				}, self::choices( $row ) );
			}

			woocommerce_register_additional_checkout_field( $args );
		}
	}

	/**
	 * Block checkout: keep each extra field also where the classic checkout keeps it, so the order
	 * screen, emails and exports read one place.
	 */
	public static function save_block_field( $key, $value, $group, $wc_object ) {
		if ( 0 === strpos( (string) $key, 'nashenski/' ) && $wc_object instanceof \WC_Order && ! in_array( $key, array_keys( Company_Fields::BLOCK_FIELDS ), true ) ) {
			$wc_object->update_meta_data( '_billing_nashenski_' . substr( $key, 10 ), is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value );
		}
	}

	/**
	 * The extra fields an order has: label => value.
	 */
	private static function values( $order ) {
		$values = array();

		foreach ( self::layout() as $row ) {
			if ( 0 !== strpos( $row['key'], 'custom_' ) ) {
				continue;
			}

			$value = (string) $order->get_meta( '_billing_nashenski_' . substr( $row['key'], 7 ) );

			if ( '' !== $value ) {
				$values[ $row['label'] ] = 'checkbox' === $row['type'] ? ( '1' === $value ? __( 'Yes', 'nashenski-commerce' ) : __( 'No', 'nashenski-commerce' ) ) : $value;
			}
		}

		return $values;
	}

	public static function admin_values( $order ) {
		foreach ( self::values( $order ) as $label => $value ) {
			printf( '<p><strong>%1$s:</strong><br>%2$s</p>', esc_html( $label ), nl2br( esc_html( $value ) ) );
		}
	}

	public static function email_values( $fields, $sent_to_admin, $order ) {
		foreach ( self::values( $order ) as $label => $value ) {
			$fields[] = array( 'label' => $label, 'value' => $value );
		}

		return $fields;
	}

	/* ---------- settings: Checkout > Checkout fields ---------- */

	/**
	 * Rows for the settings table: the saved layout with any missing standard field added, or
	 * WooCommerce's own order and widths.
	 */
	private static function table_rows() {
		$rows = self::layout();
		$keys = wp_list_pluck( $rows, 'key' );
		$order = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone', 'email' );

		foreach ( $order as $key ) {
			if ( ! in_array( $key, $keys, true ) ) {
				$rows[] = array(
					'key' => $key,
					'width' => in_array( $key, array( 'first_name', 'last_name' ), true ) ? 'half' : 'full',
				);
			}
		}

		return $rows;
	}

	public static function settings() {
		return array(
			array(
				'type' => 'title',
				'id' => 'nashenski_field_layout',
				'title' => __( 'Checkout fields', 'nashenski-commerce' ),
				'desc' => __( 'Show, require or hide each field, set its width and order, and add your own fields.', 'nashenski-commerce' ),
			),
			array(
				'type' => 'nashenski_field_layout',
				'id' => self::LAYOUT_OPTION,
				'title' => __( 'Fields', 'nashenski-commerce' ),
			),
			array(
				'type' => 'sectionend',
				'id' => 'nashenski_field_layout',
			),
		);
	}

	private static function select( $name, $options, $current ) {
		$html = '<select name="' . esc_attr( $name ) . '">';

		foreach ( $options as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		return $html . '</select>';
	}

	private static function row_html( $name, $uid, $row ) {
		$custom = 0 === strpos( $row['key'], 'custom_' ) || '' === $row['key'];
		$labels = array( 'email' => __( 'Email address', 'nashenski-commerce' ) ) + self::address_fields();
		$base = $name . '[' . $uid . ']';
		$rules = array(
			'default' => __( 'Default (as WooCommerce shows it)', 'nashenski-commerce' ),
			'required' => __( 'Required', 'nashenski-commerce' ),
			'optional' => __( 'Optional', 'nashenski-commerce' ),
			'hidden' => __( 'Hidden', 'nashenski-commerce' ),
		);
		$types = array(
			'text' => __( 'Text', 'nashenski-commerce' ),
			'textarea' => __( 'Text area', 'nashenski-commerce' ),
			'select' => __( 'List', 'nashenski-commerce' ),
			'checkbox' => __( 'Checkbox', 'nashenski-commerce' ),
			'email' => __( 'Email', 'nashenski-commerce' ),
			'tel' => __( 'Phone', 'nashenski-commerce' ),
			'number' => __( 'Number', 'nashenski-commerce' ),
			'date' => __( 'Date', 'nashenski-commerce' ),
		);

		if ( $custom ) {
			unset( $rules['default'] );
			$field = '<input type="text" name="' . esc_attr( $base . '[label]' ) . '" value="' . esc_attr( $row['label'] ?? '' ) . '" placeholder="' . esc_attr__( 'Label', 'nashenski-commerce' ) . '" required> '
				. self::select( $base . '[type]', $types, $row['type'] ?? 'text' )
				. '<textarea name="' . esc_attr( $base . '[options]' ) . '" rows="3" class="nashenski-layout__options" placeholder="' . esc_attr__( 'One choice per line', 'nashenski-commerce' ) . '">' . esc_textarea( $row['options'] ?? '' ) . '</textarea>';
			$rule = $row['rule'] ?? 'optional';
		} else {
			$field = '<strong>' . esc_html( $labels[ $row['key'] ] ?? $row['key'] ) . '</strong>';
			$rule = self::rule( $row['key'] );
		}

		return sprintf(
			'<tr class="nashenski-layout__row%1$s"><td class="nashenski-layout__move"><button type="button" class="button-link nashenski-layout__up" aria-label="%2$s">&uarr;</button><button type="button" class="button-link nashenski-layout__down" aria-label="%3$s">&darr;</button></td><td>%4$s<input type="hidden" name="%5$s" value="%6$s"></td><td>%7$s</td><td>%8$s</td><td>%9$s</td></tr>',
			$custom ? ' nashenski-layout__row--custom' . ( 'select' === ( $row['type'] ?? '' ) ? ' is-select' : '' ) : '',
			esc_attr__( 'Move up', 'nashenski-commerce' ),
			esc_attr__( 'Move down', 'nashenski-commerce' ),
			$field,
			esc_attr( $base . '[key]' ),
			esc_attr( $row['key'] ),
			self::select( $base . '[rule]', $rules, $rule ),
			self::select( $base . '[width]', array( 'full' => __( 'Full width', 'nashenski-commerce' ), 'half' => __( 'Half width', 'nashenski-commerce' ) ), $row['width'] ?? 'full' ),
			$custom ? '<button type="button" class="button-link button-link-delete nashenski-layout__remove">' . esc_html__( 'Remove', 'nashenski-commerce' ) . '</button>' : ''
		);
	}

	/**
	 * The table: one row per field, in checkout order.
	 */
	public static function output_layout_field( $value ) {
		$name = $value['id'];
		?>
		<tr valign="top">
			<th scope="row" class="titledesc"><?php echo esc_html( $value['title'] ); ?></th>
			<td class="forminp">
				<table class="widefat striped nashenski-layout">
					<thead>
						<tr>
							<th class="nashenski-layout__move"><span class="screen-reader-text"><?php esc_html_e( 'Position', 'nashenski-commerce' ); ?></span></th>
							<th><?php esc_html_e( 'Field', 'nashenski-commerce' ); ?></th>
							<th><?php esc_html_e( 'Show', 'nashenski-commerce' ); ?></th>
							<th><?php esc_html_e( 'Width', 'nashenski-commerce' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'nashenski-commerce' ); ?></span></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( self::table_rows() as $i => $row ) {
							echo self::row_html( $name, 'r' . $i, $row ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in row_html().
						}
						?>
					</tbody>
				</table>
				<template class="nashenski-layout__template"><?php echo self::row_html( $name, '__uid__', array( 'key' => '', 'type' => 'text', 'rule' => 'optional', 'width' => 'full' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in row_html(). ?></template>
				<p><button type="button" class="button nashenski-layout__add"><?php esc_html_e( 'Add your own field', 'nashenski-commerce' ); ?></button></p>
				<p class="description"><?php esc_html_e( 'Use the arrows to change the order. Two half-width fields next to each other share a line. With company invoice fields on, the company name belongs to them.', 'nashenski-commerce' ); ?></p>
				<?php echo \Nashenski\Admin\Checkout_Fields_Import::html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html(). ?>
				<details class="nashenski-more">
					<summary><?php esc_html_e( 'Which checkouts follow what', 'nashenski-commerce' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Show, required and hidden apply to the billing and shipping address in every checkout. Order and width also apply to the address in the block checkout, where the email always comes first and your own fields come after it. The place of the email applies to the classic checkout and to builder checkouts that show WooCommerce\'s billing fields. Builder checkouts made of single field elements keep the order you placed them in. When the town or region is hidden, the courier fields ask for the town (from Econt\'s list of towns when Econt is on).', 'nashenski-commerce' ); ?></p>
				</details>
			</td>
		</tr>
		<?php
	}

	/**
	 * Keep the rows in the order they were sent; standard fields also save their visibility in
	 * the options the rest of the editor reads.
	 */
	public static function sanitize_layout_field( $value, $option, $raw_value ) {
		if ( 'nashenski_field_layout' !== ( $option['type'] ?? '' ) ) {
			return $value;
		}

		$rows = array();
		$standard = array_merge( array( 'email' ), self::KEYS );

		foreach ( (array) $raw_value as $uid => $raw ) {
			$key = sanitize_key( $raw['key'] ?? '' );
			$rule = in_array( $raw['rule'] ?? '', array( 'default', 'required', 'optional', 'hidden' ), true ) ? $raw['rule'] : 'default';
			$width = 'half' === ( $raw['width'] ?? '' ) ? 'half' : 'full';

			if ( in_array( $key, $standard, true ) ) {
				nashenski_set_option( 'email' === $key ? self::GROUP_CLASSIC : self::GROUP, $key, $rule );
				$rows[] = array( 'key' => $key, 'width' => $width );
				continue;
			}

			$label = sanitize_text_field( $raw['label'] ?? '' );

			if ( '' === $label ) {
				continue;
			}

			$rows[] = array(
				'key' => '' !== $key ? $key : 'custom_' . sanitize_key( $uid ),
				'label' => $label,
				'type' => isset( self::TYPES[ $raw['type'] ?? '' ] ) ? $raw['type'] : 'text',
				'options' => sanitize_textarea_field( $raw['options'] ?? '' ),
				'rule' => 'default' === $rule ? 'optional' : $rule,
				'width' => $width,
			);
		}

		return $rows;
	}
}
