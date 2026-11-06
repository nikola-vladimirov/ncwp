<?php // phpcs:ignore WordPress.Files.FileName -- PSR-4 autoloading needs the file named after its class.
/**
 * Field editor.
 *
 * @package Nashenski_Commerce
 */

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
	const GROUP         = 'fields';
	const GROUP_CLASSIC = 'fields_classic';

	/** Order, width and extra fields: list of rows ( key, width [, rule, label, type, options] ). */
	const LAYOUT_OPTION = 'nashenski_settings_fields_layout';

	// Address field keys without the billing_/shipping_ prefix (labels in address_fields(), which needs translations loaded).
	const KEYS = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone' );

	/** Types of extra fields => block checkout type (it has text, select and checkbox). */
	const TYPES = array(
		'text'     => 'text',
		'textarea' => 'text',
		'select'   => 'select',
		'checkbox' => 'checkbox',
		'email'    => 'text',
		'tel'      => 'text',
		'number'   => 'text',
		'date'     => 'text',
	);

	/**
	 * Constructor.
	 */
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
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_conditions' ) );
	}

	/**
	 * Classic checkout: the script shows and hides the rows with a field-value rule as the customer
	 * fills in the form. The rules also apply when the form is submitted (checkout_fields()).
	 */
	public static function enqueue_conditions() {
		if ( ! is_checkout() || Pickers::is_block_checkout() ) {
			return;
		}

		$targets = array();
		$names   = array();

		foreach ( self::layout() as $row ) {
			$names[ $row['key'] ] = self::field_name( 'billing', $row['key'] );

			if ( empty( $row['when'] ) ) {
				continue;
			}

			$types = in_array( $row['key'], self::KEYS, true ) ? array( 'billing', 'shipping' ) : array( 'billing' );

			foreach ( $types as $type ) {
				$targets[] = array(
					'name' => self::field_name( $type, $row['key'] ),
					'when' => $row['when'],
				);
			}
		}

		if ( ! $targets ) {
			return;
		}

		$url  = nashenski()->plugin_dir_url() . 'assets/';
		$file = nashenski()->plugin_dir_path() . 'assets/js/checkout-conditions.js';

		wp_enqueue_script( 'nashenski-checkout-conditions', $url . 'js/checkout-conditions.js', array( 'jquery' ), \Nashenski\Plugin::VERSION . '-' . filemtime( $file ), true );
		wp_add_inline_script(
			'nashenski-checkout-conditions',
			'window.nashenskiConditions = ' . wp_json_encode(
				array(
					'names'   => $names,
					'targets' => $targets,
				),
				JSON_HEX_TAG | JSON_HEX_AMP
			) . ';',
			'before'
		);
	}

	/**
	 * Hide rendered field.
	 *
	 * @param mixed $html Html.
	 * @param mixed $key  Key.
	 * @return mixed
	 */
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
			'last_name'  => __( 'Last name', 'nashenski-commerce' ),
			'company'    => __( 'Company name', 'nashenski-commerce' ),
			'address_1'  => __( 'Street address', 'nashenski-commerce' ),
			'address_2'  => __( 'Apartment, suite, unit (address line 2)', 'nashenski-commerce' ),
			'city'       => __( 'Town / City', 'nashenski-commerce' ),
			'state'      => __( 'Region / State', 'nashenski-commerce' ),
			'postcode'   => __( 'Postcode', 'nashenski-commerce' ),
			'phone'      => __( 'Phone', 'nashenski-commerce' ),
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
				$css  .= '.wc-block-checkout .wc-block-components-address-form ' . $field . $field . '{flex:0 0 ' . ( 'half' === $row['width'] ? 'calc(50% - 6px)' : '100%' ) . '}';
			}
		}

		return $css;
	}

	/**
	 * Fields WooCommerce controls with an option of its own ("hidden", "optional", "required").
	 */
	private static function option_fields() {
		return array(
			'company'   => 'woocommerce_checkout_company_field',
			'address_2' => 'woocommerce_checkout_address_2_field',
			'phone'     => 'woocommerce_checkout_phone_field',
		);
	}

	/**
	 * Rule.
	 *
	 * @param mixed $field Field.
	 * @return mixed
	 */
	public static function rule( $field ) {
		$group = in_array( $field, array( 'email', 'order_comments' ), true ) ? self::GROUP_CLASSIC : self::GROUP;
		$rule  = nashenski_get_option( $group, $field );

		return in_array( $rule, array( 'required', 'optional', 'hidden' ), true ) ? $rule : 'default';
	}

	/**
	 * Has rules.
	 *
	 * @return mixed
	 */
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

	/**
	 * Custom rows.
	 *
	 * @return mixed
	 */
	private static function custom_rows() {
		return array_filter(
			self::layout(),
			function ( $row ) {
				return 0 === strpos( $row['key'], 'custom_' ) && 'hidden' !== ( $row['rule'] ?? '' );
			}
		);
	}

	/**
	 * Checkout field name of a row: billing_email, shipping_city, billing_nashenski_<slug>.
	 *
	 * @param mixed $type Type.
	 * @param mixed $key  Key.
	 */
	public static function field_name( $type, $key ) {
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
	 *
	 * @param mixed $definition Definition.
	 * @param mixed $rule       Rule.
	 */
	private static function apply_rule( $definition, $rule ) {
		switch ( $rule ) {
			case 'hidden':
				return null;
			case 'required':
				$definition['required'] = true;
				$definition['hidden']   = false;
				break;
			case 'optional':
				$definition['required'] = false;
				break;
		}

		return $definition;
	}

	/**
	 * Default address fields.
	 *
	 * @param mixed $fields Fields.
	 * @return mixed
	 */
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
	 *
	 * @param mixed $locale Locale.
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
				$entry['hidden']   = true;
				$entry['required'] = false;
			} elseif ( 'default' !== $rule ) {
				$entry['hidden']   = false;
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

	/**
	 * Country locale.
	 *
	 * @param mixed $locales Locales.
	 * @return mixed
	 */
	public static function country_locale( $locales ) {
		foreach ( $locales as $country => $locale ) {
			$locales[ $country ] = self::locale_entry( is_array( $locale ) ? $locale : array() );
		}

		return $locales;
	}

	/**
	 * Billing fields.
	 *
	 * @param mixed $fields Fields.
	 * @return mixed
	 */
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
	 *
	 * @param mixed $fields Fields.
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

			$fields[ $type ] = self::remove_unshown( $fields[ $type ], $type );
		}

		$fields['billing'] = self::billing_fields( $fields['billing'] ?? array() );

		if ( 'hidden' === self::rule( 'order_comments' ) ) {
			unset( $fields['order']['order_comments'] );
		}

		return $fields;
	}

	/**
	 * On submit, a field whose rule is not met is removed: it is not checked and not saved. The page
	 * itself keeps the field and the script hides it, so nothing is removed when the form is shown.
	 *
	 * @param array  $section The fields of the section.
	 * @param string $type    'billing' or 'shipping'.
	 * @return array The fields without the ones whose rule is not met.
	 */
	private static function remove_unshown( $section, $type ) {
		// Only the checkout submit removes fields. Another POST (a failed login, a cart update) keeps the form as it is.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only tells which request this is; WooCommerce checks the nonce.
		$ajax = isset( $_GET['wc-ajax'] ) ? sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce is verified on the next line.
		$nonce = sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ?? '' ) );

		if ( ! wp_verify_nonce( $nonce, 'woocommerce-process_checkout' ) || ( '' !== $ajax && 'checkout' !== $ajax ) ) {
			return $section;
		}

		$values = array();

		foreach ( self::layout() as $row ) {
			$name = self::field_name( 'billing', $row['key'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checks the checkout nonce before it reads the fields.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read only to compare with the rule; never output or saved, and not stripped, so it matches the script.
			$posted                = wp_unslash( $_POST[ $name ] ?? '' );
			$values[ $row['key'] ] = Conditions::clean_text( is_scalar( $posted ) ? $posted : '' );
		}

		// A hidden field counts as empty for the rules that read it, as in the script; a few passes settle the chains.
		$rules  = array_filter(
			self::layout(),
			function ( $row ) {
				return ! empty( $row['when'] );
			}
		);
		$hidden = array();
		$passes = count( $rules );

		for ( $pass = 0; $pass <= $passes; $pass++ ) {
			$changed = false;

			foreach ( $rules as $row ) {
				$now                   = ! Conditions::shown( $row['when'], $values );
				$changed               = $changed || ( $hidden[ $row['key'] ] ?? null ) !== $now;
				$hidden[ $row['key'] ] = $now;

				if ( $now && '' !== $values[ $row['key'] ] ) {
					$values[ $row['key'] ] = '';
					$changed               = true;
				}
			}

			if ( ! $changed ) {
				break;
			}
		}

		foreach ( $rules as $row ) {
			$name = self::field_name( $type, $row['key'] );

			if ( $hidden[ $row['key'] ] && isset( $section[ $name ] ) ) {
				unset( $section[ $name ] );
			}
		}

		return $section;
	}

	/**
	 * Order and width from the layout, and the extra fields in the billing section. Two half-width
	 * fields in a row share a line.
	 *
	 * @param mixed $section Section.
	 * @param mixed $type    Type.
	 */
	private static function arrange( $section, $type ) {
		$priorities = self::priorities();
		$widths     = array();

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
				$widths[ $name ]              = $row['width'];
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

		uasort(
			$section,
			function ( $a, $b ) {
				return ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 );
			}
		);

		$left = true;

		foreach ( $section as $name => $field ) {
			if ( ! isset( $widths[ $name ] ) ) {
				$left = true;
				continue;
			}

			$class                     = 'half' === $widths[ $name ] ? ( $left ? 'form-row-first' : 'form-row-last' ) : 'form-row-wide';
			$left                      = 'half' === $widths[ $name ] ? ! $left : true;
			$section[ $name ]['class'] = array_merge( array_diff( (array) ( $field['class'] ?? array() ), array( 'form-row-first', 'form-row-last', 'form-row-wide' ) ), array( $class ) );
		}

		return $section;
	}

	/**
	 * An extra field for woocommerce_form_field().
	 *
	 * @param mixed $row Row.
	 */
	private static function custom_definition( $row ) {
		$definition = array(
			'type'     => $row['type'],
			'label'    => $row['label'],
			'required' => 'required' === $row['rule'],
			'class'    => array(),
		);

		if ( 'select' === $row['type'] ) {
			$choices               = self::choices( $row );
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

	/**
	 * Choices.
	 *
	 * @param mixed $row Row.
	 * @return mixed
	 */
	private static function choices( $row ) {
		return array_values( array_filter( array_map( array( Conditions::class, 'clean_text' ), explode( "\n", (string) ( $row['options'] ?? '' ) ) ), 'strlen' ) );
	}

	/**
	 * Block checkout: the extra fields under the email (text, list or checkbox there).
	 */
	public static function register_block_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		$types = array();

		$derived = array();

		foreach ( self::custom_rows() as $row ) {
			$types[ $row['key'] ] = $row['type'];

			// A field its own rule can hide keeps its value in the block checkout, so a rule that reads it stays classic only.
			if ( ! empty( $row['when'] ) ) {
				$derived[] = $row['key'];
			}
		}

		foreach ( self::custom_rows() as $row ) {
			$args = array(
				'id'            => 'nashenski/' . substr( $row['key'], 7 ),
				'label'         => $row['label'],
				'optionalLabel' => $row['label'] . ' ' . __( '(optional)', 'nashenski-commerce' ),
				'location'      => 'contact',
				'type'          => self::TYPES[ $row['type'] ] ?? 'text',
				'required'      => 'required' === $row['rule'],
			);

			// A field-value rule the block checkout can express: "required" and "hidden" as JSON Schema (as Company_Fields).
			$rule = Conditions::block_rule( $row['when'] ?? null, $types, 'required' === $row['rule'], $derived );

			if ( $rule ) {
				$args['required'] = $rule['required'];
				$args['hidden']   = $rule['hidden'];
			} elseif ( ! empty( $row['when'] ) ) {
				// A rule the block checkout cannot write: the field stays visible, but is not required by its own setting.
				$args['required'] = false;
			}

			if ( 'select' === $args['type'] ) {
				$args['options'] = array_map(
					function ( $choice ) {
						return array(
							'value' => $choice,
							'label' => $choice,
						);
					},
					self::choices( $row )
				);
			}

			woocommerce_register_additional_checkout_field( $args );
		}
	}

	/**
	 * Block checkout: keep each extra field also where the classic checkout keeps it, so the order
	 * screen, emails and exports read one place.
	 *
	 * @param mixed $key       Key.
	 * @param mixed $value     Value.
	 * @param mixed $group     Group.
	 * @param mixed $wc_object Wc object.
	 */
	public static function save_block_field( $key, $value, $group, $wc_object ) {
		if ( 0 === strpos( (string) $key, 'nashenski/' ) && $wc_object instanceof \WC_Order && ! in_array( $key, array_keys( Company_Fields::BLOCK_FIELDS ), true ) ) {
			$wc_object->update_meta_data( '_billing_nashenski_' . substr( $key, 10 ), is_bool( $value ) ? ( $value ? '1' : '0' ) : (string) $value );
		}
	}

	/**
	 * The extra fields an order has: label => value.
	 *
	 * @param mixed $order Order.
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

	/**
	 * Admin values.
	 *
	 * @param mixed $order Order.
	 */
	public static function admin_values( $order ) {
		foreach ( self::values( $order ) as $label => $value ) {
			printf( '<p><strong>%1$s:</strong><br>%2$s</p>', esc_html( $label ), nl2br( esc_html( $value ) ) );
		}
	}

	/**
	 * Email values.
	 *
	 * @param mixed $fields        Fields.
	 * @param mixed $sent_to_admin Sent to admin.
	 * @param mixed $order         Order.
	 * @return mixed
	 */
	public static function email_values( $fields, $sent_to_admin, $order ) {
		foreach ( self::values( $order ) as $label => $value ) {
			$fields[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		return $fields;
	}

	/* ---------- settings: Checkout > Checkout fields ---------- */

	/**
	 * Rows for the settings table: the saved layout with any missing standard field added, or
	 * WooCommerce's own order and widths.
	 */
	private static function table_rows() {
		$rows  = self::layout();
		$keys  = wp_list_pluck( $rows, 'key' );
		$order = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone', 'email' );

		foreach ( $order as $key ) {
			if ( ! in_array( $key, $keys, true ) ) {
				$rows[] = array(
					'key'   => $key,
					'width' => in_array( $key, array( 'first_name', 'last_name' ), true ) ? 'half' : 'full',
				);
			}
		}

		return $rows;
	}

	/**
	 * Settings.
	 *
	 * @return mixed
	 */
	public static function settings() {
		return array(
			array(
				'type'  => 'title',
				'id'    => 'nashenski_field_layout',
				'title' => __( 'Checkout fields', 'nashenski-commerce' ),
				'desc'  => __( 'Show, require or hide each field, set its width and order, and add your own fields.', 'nashenski-commerce' ),
			),
			array(
				'type'  => 'nashenski_field_layout',
				'id'    => self::LAYOUT_OPTION,
				'title' => __( 'Fields', 'nashenski-commerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'nashenski_field_layout',
			),
		);
	}

	/**
	 * Select.
	 *
	 * @param mixed $name    Name.
	 * @param mixed $options Options.
	 * @param mixed $current Current.
	 * @return mixed
	 */
	private static function select( $name, $options, $current ) {
		$html = '<select name="' . esc_attr( $name ) . '">';

		foreach ( $options as $value => $label ) {
			$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}

		return $html . '</select>';
	}

	/**
	 * Row html.
	 *
	 * @param mixed $name Name.
	 * @param mixed $uid  Uid.
	 * @param mixed $row  Row.
	 * @return mixed
	 */
	private static function row_html( $name, $uid, $row ) {
		$custom = 0 === strpos( $row['key'], 'custom_' ) || '' === $row['key'];
		$labels = array( 'email' => __( 'Email address', 'nashenski-commerce' ) ) + self::address_fields();
		$base   = $name . '[' . $uid . ']';
		$rules  = array(
			'default'  => __( 'Default (as WooCommerce shows it)', 'nashenski-commerce' ),
			'required' => __( 'Required', 'nashenski-commerce' ),
			'optional' => __( 'Optional', 'nashenski-commerce' ),
			'hidden'   => __( 'Hidden', 'nashenski-commerce' ),
		);
		$types  = array(
			'text'     => __( 'Text', 'nashenski-commerce' ),
			'textarea' => __( 'Text area', 'nashenski-commerce' ),
			'select'   => __( 'List', 'nashenski-commerce' ),
			'checkbox' => __( 'Checkbox', 'nashenski-commerce' ),
			'email'    => __( 'Email', 'nashenski-commerce' ),
			'tel'      => __( 'Phone', 'nashenski-commerce' ),
			'number'   => __( 'Number', 'nashenski-commerce' ),
			'date'     => __( 'Date', 'nashenski-commerce' ),
		);

		if ( $custom ) {
			unset( $rules['default'] );
			$field = '<input type="text" name="' . esc_attr( $base . '[label]' ) . '" value="' . esc_attr( $row['label'] ?? '' ) . '" placeholder="' . esc_attr__( 'Label', 'nashenski-commerce' ) . '" required> '
				. self::select( $base . '[type]', $types, $row['type'] ?? 'text' )
				. '<textarea name="' . esc_attr( $base . '[options]' ) . '" rows="3" class="nashenski-layout__options" placeholder="' . esc_attr__( 'One choice per line', 'nashenski-commerce' ) . '">' . esc_textarea( $row['options'] ?? '' ) . '</textarea>';
			$rule  = $row['rule'] ?? 'optional';
		} else {
			$field = '<strong>' . esc_html( $labels[ $row['key'] ] ?? $row['key'] ) . '</strong>';
			$rule  = self::rule( $row['key'] );
		}

		$mode = empty( $row['when'] ) ? '' : ( ! empty( $row['when']['hide'] ) ? 'hide' : 'show' );

		return sprintf(
			'<tr class="nashenski-layout__row%1$s"><td class="nashenski-layout__move"><button type="button" class="button-link nashenski-layout__up" aria-label="%2$s">&uarr;</button><button type="button" class="button-link nashenski-layout__down" aria-label="%3$s">&darr;</button></td><td>%4$s<input type="hidden" name="%5$s" value="%6$s"></td><td>%7$s</td><td>%8$s</td><td class="nashenski-when"><select class="nashenski-when__rule" aria-label="%10$s"><option value=""%13$s>%14$s</option><option value="show"%15$s>%16$s</option><option value="hide"%17$s>%18$s</option></select> <button type="button" class="button-link nashenski-when__open" aria-expanded="false"%19$s>%20$s</button><span class="nashenski-when__summary"></span><input type="hidden" class="nashenski-when__json" name="%11$s" value="%12$s"><div class="nashenski-when__panel" hidden></div></td><td>%9$s</td></tr>',
			$custom ? ' nashenski-layout__row--custom' . ( 'select' === ( $row['type'] ?? '' ) ? ' is-select' : '' ) : '',
			esc_attr__( 'Move up', 'nashenski-commerce' ),
			esc_attr__( 'Move down', 'nashenski-commerce' ),
			$field,
			esc_attr( $base . '[key]' ),
			esc_attr( $row['key'] ),
			self::select( $base . '[rule]', $rules, $rule ),
			self::select(
				$base . '[width]',
				array(
					'full' => __( 'Full width', 'nashenski-commerce' ),
					'half' => __( 'Half width', 'nashenski-commerce' ),
				),
				$row['width'] ?? 'full'
			),
			$custom ? '<button type="button" class="button-link button-link-delete nashenski-layout__remove">' . esc_html__( 'Remove', 'nashenski-commerce' ) . '</button>' : '',
			esc_attr__( 'Condition', 'nashenski-commerce' ),
			esc_attr( $base . '[when]' ),
			esc_attr( empty( $row['when'] ) ? '' : wp_json_encode( $row['when'], JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_APOS ) ),
			$mode ? '' : ' selected',
			esc_html__( 'Always shown', 'nashenski-commerce' ),
			'show' === $mode ? ' selected' : '',
			esc_html__( 'Show when...', 'nashenski-commerce' ),
			'hide' === $mode ? ' selected' : '',
			esc_html__( 'Hide when...', 'nashenski-commerce' ),
			$mode ? '' : ' hidden',
			esc_html__( 'Edit condition', 'nashenski-commerce' )
		);
	}

	/**
	 * The table: one row per field, in checkout order.
	 *
	 * @param mixed $value Value.
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
							<th><?php esc_html_e( 'Condition', 'nashenski-commerce' ); ?></th>
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
				<template class="nashenski-layout__template">
				<?php
				echo self::row_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in row_html().
					$name,
					'__uid__',
					array(
						'key'   => '',
						'type'  => 'text',
						'rule'  => 'optional',
						'width' => 'full',
					)
				);
				?>
				</template>
				<template class="nashenski-when__template"
						data-shown="
						<?php
						/* translators: %s: the field, the operator and the value */
						echo esc_attr__( 'Shown when: %s', 'nashenski-commerce' );
						?>
						"
						data-hidden="
						<?php
						/* translators: %s: the field, the operator and the value */
						echo esc_attr__( 'Hidden when: %s', 'nashenski-commerce' );
						?>
						"
						data-and="<?php echo esc_attr__( 'and', 'nashenski-commerce' ); ?>"
							data-or="<?php echo esc_attr__( 'or', 'nashenski-commerce' ); ?>"
						data-is-hidden="
						<?php
						/* translators: %s: field label */
						echo esc_attr__( '%s is hidden, so its value always counts as empty.', 'nashenski-commerce' );
						?>
						"
						data-missing="
						<?php
						/* translators: %s: field key */
						echo esc_attr__( '%s is not in the table any more. The rule is dropped on save.', 'nashenski-commerce' );
						?>
						"
						data-yes="<?php echo esc_attr__( 'ticked', 'nashenski-commerce' ); ?>"
						data-no="<?php echo esc_attr__( 'not ticked', 'nashenski-commerce' ); ?>"
						data-pick="<?php echo esc_attr__( 'Choose a field', 'nashenski-commerce' ); ?>"
					>
						<div class="nashenski-when__body">
							<p><select class="nashenski-when__mode">
								<option value="show"><?php esc_html_e( 'Show this field when:', 'nashenski-commerce' ); ?></option>
									<option value="hide"><?php esc_html_e( 'Hide this field when:', 'nashenski-commerce' ); ?></option>
								</select></p>
								<div class="nashenski-when__groups"></div>
								<p><button type="button" class="button nashenski-when__addgroup"><?php esc_html_e( 'Add alternative (or)', 'nashenski-commerce' ); ?></button> <button type="button" class="button-link nashenski-when__close"><?php esc_html_e( 'Close', 'nashenski-commerce' ); ?></button></p>
							</div>
							<div class="nashenski-when__group">
								<p class="nashenski-when__or"><strong><?php esc_html_e( 'or', 'nashenski-commerce' ); ?></strong></p>
								<p class="description"><?php esc_html_e( 'All of these are true:', 'nashenski-commerce' ); ?></p>
								<div class="nashenski-when__list"></div>
								<p><button type="button" class="button nashenski-when__add"><?php esc_html_e( 'Add condition', 'nashenski-commerce' ); ?></button></p>
							</div>
						<div class="nashenski-when__cond">
							<select class="nashenski-when__field"></select>
							<select class="nashenski-when__op">
								<option value="value_eq"><?php esc_html_e( 'is', 'nashenski-commerce' ); ?></option>
								<option value="value_ne"><?php esc_html_e( 'is not', 'nashenski-commerce' ); ?></option>
								<option value="value_gt"><?php esc_html_e( 'is greater than', 'nashenski-commerce' ); ?></option>
								<option value="value_le"><?php esc_html_e( 'is less than', 'nashenski-commerce' ); ?></option>
								<option value="empty"><?php esc_html_e( 'is empty', 'nashenski-commerce' ); ?></option>
								<option value="not_empty"><?php esc_html_e( 'is not empty', 'nashenski-commerce' ); ?></option>
							</select>
							<span class="nashenski-when__value"></span>
							<button type="button" class="button-link button-link-delete nashenski-when__del"><?php esc_html_e( 'Remove', 'nashenski-commerce' ); ?></button>
							<span class="description nashenski-when__note"></span>
						</div>
					</template>
					<p><button type="button" class="button nashenski-layout__add"><?php esc_html_e( 'Add your own field', 'nashenski-commerce' ); ?></button></p>
				<p class="description"><?php esc_html_e( 'Use the arrows to change the order. Two half-width fields next to each other share a line. With company invoice fields on, the company name belongs to them.', 'nashenski-commerce' ); ?></p>
				<?php echo \Nashenski\Admin\Checkout_Fields_Import::html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html(). ?>
				<details class="nashenski-more">
					<summary><?php esc_html_e( 'Which checkouts follow what', 'nashenski-commerce' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Show, required and hidden apply to the billing and shipping address in every checkout. Order and width also apply to the address in the block checkout, where the email always comes first and your own fields come after it. The place of the email applies to the classic checkout and to builder checkouts that show WooCommerce\'s billing fields. Builder checkouts made of single field elements keep the order you placed them in. When the town or region is hidden, the courier fields ask for the town (from Econt\'s list of towns when Econt is on).', 'nashenski-commerce' ); ?></p>
				<p class="description"><?php esc_html_e( 'Conditions: the classic checkout follows all of them. The block checkout follows conditions on your own fields that read your own fields, and not "greater" or "less"; other conditions are ignored there.', 'nashenski-commerce' ); ?></p>
					</details>
			</td>
		</tr>
		<?php
	}

	/**
	 * Keep the rows in the order they were sent; standard fields also save their visibility in
	 * the options the rest of the editor reads.
	 *
	 * @param mixed $value     Value.
	 * @param mixed $option    Option.
	 * @param mixed $raw_value Raw value.
	 */
	public static function sanitize_layout_field( $value, $option, $raw_value ) {
		if ( 'nashenski_field_layout' !== ( $option['type'] ?? '' ) ) {
			return $value;
		}

		$rows     = array();
		$standard = array_merge( array( 'email' ), self::KEYS );
		$saved    = array();

		foreach ( self::layout() as $row ) {
			$saved[ $row['key'] ] = $row['when'] ?? null;
		}

		foreach ( (array) $raw_value as $uid => $raw ) {
			$key   = sanitize_key( $raw['key'] ?? '' );
			$rule  = in_array( $raw['rule'] ?? '', array( 'default', 'required', 'optional', 'hidden' ), true ) ? $raw['rule'] : 'default';
			$width = 'half' === ( $raw['width'] ?? '' ) ? 'half' : 'full';
			// The table does not post the rules of a row, so a saved table keeps the ones the row had.
			$when = is_array( $raw ) && array_key_exists( 'when', $raw ) ? $raw['when'] : ( $saved[ $key ] ?? null );
			$when = self::clean_when( is_string( $when ) ? json_decode( $when, true ) : $when );

			if ( in_array( $key, $standard, true ) ) {
				nashenski_set_option( 'email' === $key ? self::GROUP_CLASSIC : self::GROUP, $key, $rule );
				$rows[] = array(
					'key'   => $key,
					'width' => $width,
					'when'  => $when,
				);
				continue;
			}

			$label = sanitize_text_field( $raw['label'] ?? '' );

			if ( '' === $label ) {
				continue;
			}

			$rows[] = array(
				'key'     => '' !== $key ? $key : 'custom_' . sanitize_key( $uid ),
				'label'   => $label,
				'type'    => isset( self::TYPES[ $raw['type'] ?? '' ] ) ? $raw['type'] : 'text',
				'options' => implode( "\n", array_filter( array_map( array( Conditions::class, 'clean_value' ), explode( "\n", (string) ( $raw['options'] ?? '' ) ) ), 'strlen' ) ),
				'rule'    => 'default' === $rule ? 'optional' : $rule,
				'width'   => $width,
				'when'    => $when,
			);
		}

		return self::check_rules( $rows );
	}

	/**
	 * The rules against the rows that are saved: a rule with a condition that reads a row no longer in the
	 * table is dropped whole (a cut rule would show the field in cases it did not allow, as the import does),
	 * a rule that reads its own row is dropped, and so are rules that read each
	 * other in a loop (A reads B and B reads A), directly or through other rows.
	 *
	 * @param array $rows The rows as saved.
	 * @return array The rows with the rules cleaned.
	 */
	private static function check_rules( $rows ) {
		$keys = wp_list_pluck( $rows, 'key' );
		$deps = array();

		foreach ( $rows as $i => $row ) {
			if ( ! $row['when'] ) {
				continue;
			}

			$rows[ $i ]['when'] = array_diff( self::when_fields( $row['when'] ), $keys ) ? null : $row['when'];

			if ( $rows[ $i ]['when'] ) {
				$deps[ $row['key'] ] = array_unique( self::when_fields( $rows[ $i ]['when'] ) );
			}
		}

		$loops = array();

		foreach ( $deps as $key => $reads ) {
			$seen = array();

			while ( $reads ) {
				$field = array_pop( $reads );

				if ( $field === $key ) {
					$loops[] = $key;
					break;
				}

				if ( ! isset( $seen[ $field ] ) ) {
					$seen[ $field ] = true;
					$reads          = array_merge( $reads, $deps[ $field ] ?? array() );
				}
			}
		}

		foreach ( $rows as $i => $row ) {
			if ( in_array( $row['key'], $loops, true ) ) {
				$rows[ $i ]['when'] = null;
			}
		}

		return $rows;
	}

	/**
	 * A field-value rule as saved: only the known operators, keys and texts. Null when it has none.
	 *
	 * @param mixed $when The rule as sent.
	 * @return array|null The cleaned rule.
	 */
	private static function clean_when( $when ) {
		if ( ! is_array( $when ) || empty( $when['sets'] ) ) {
			return null;
		}

		$sets = array();

		// A rule is read on every checkout page view: at most 20 alternatives of 20 conditions. A longer one is dropped whole, as a cut one would show the field in more cases than it allows.
		if ( count( (array) $when['sets'] ) > 20 ) {
			return null;
		}

		foreach ( (array) $when['sets'] as $set ) {
			$conditions = array();

			if ( count( (array) $set ) > 20 ) {
				return null;
			}

			foreach ( (array) $set as $condition ) {
				$operator = is_array( $condition ) ? ( $condition['operator'] ?? '' ) : '';
				$field    = is_array( $condition ) ? sanitize_key( $condition['field'] ?? '' ) : '';

				if ( ! in_array( $operator, Conditions::OPERATORS, true ) || '' === $field ) {
					return null;
				}

				$conditions[] = array(
					'field'    => $field,
					'operator' => $operator,
					'value'    => Conditions::clean_value( is_scalar( $condition['value'] ?? '' ) ? $condition['value'] : '' ),
				);
			}

			if ( $conditions ) {
				$sets[] = $conditions;
			}
		}

		return $sets ? array(
			'hide' => ! empty( $when['hide'] ),
			'sets' => $sets,
		) : null;
	}

	/**
	 * The row keys a rule reads.
	 *
	 * @param array $when A cleaned rule.
	 * @return array The keys.
	 */
	private static function when_fields( $when ) {
		$fields = array();

		foreach ( $when['sets'] as $set ) {
			foreach ( $set as $condition ) {
				$fields[] = $condition['field'];
			}
		}

		return $fields;
	}
}
