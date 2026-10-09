<?php
namespace Nashenski\Front_End\Checkout;

defined( 'ABSPATH' ) || exit;

/**
 * Company invoice fields at checkout: "I want an invoice for a company", then company name, EIK,
 * VAT number, MOL, town and address. Turned on or off in Checkout > Company invoice.
 *
 * - Classic and page builder checkouts: the fields come from Company (billing fields). When a
 *   builder checkout is made of single field elements and the template has none of them, the
 *   script places them after the billing fields.
 * - Block checkout: the same fields through WooCommerce's additional checkout fields, shown only
 *   when the box is ticked, and saved into the same order data, so documents read them the same way.
 */
class Company_Fields {
	const GROUP = 'company';

	/**
	 * Block checkout field ID => order meta (or "company" for the billing company name).
	 */
	const BLOCK_FIELDS = array(
		'nashenski/to-company' => '_billing_to_company',
		'nashenski/company-name' => 'company',
		'nashenski/company-eik' => '_billing_company_eik',
		'nashenski/company-vat' => '_billing_vat_number',
		'nashenski/company-mol' => '_billing_company_mol',
		'nashenski/company-settlement' => '_billing_company_settlement',
		'nashenski/company-address' => '_billing_company_address',
	);

	/**
	 * On when the store turned it on. Stores that never chose keep the old behaviour: on while
	 * documents are on and invoices are not switched off.
	 */
	public static function enabled() {
		$saved = nashenski_get_option( self::GROUP, 'fields' );

		if ( in_array( $saved, array( 'yes', 'no' ), true ) ) {
			return 'yes' === $saved;
		}

		return 'yes' === nashenski_get_option( 'apis', 'enable_documents' ) && 'disable' !== nashenski_get_option( 'invoice', 'invoices' );
	}

	public function __construct() {
		new Company();

		add_action( 'woocommerce_init', array( __CLASS__, 'register_block_fields' ) );
		add_action( 'woocommerce_set_additional_field_value', array( __CLASS__, 'save_block_field' ), 10, 4 );
		add_action( 'wp_footer', array( __CLASS__, 'print_template' ) );
	}

	/**
	 * JSON Schema rule: the "company invoice" box is ticked.
	 */
	private static function when_ticked() {
		return array(
			'checkout' => array(
				'properties' => array(
					'additional_fields' => array(
						'required' => array( 'nashenski/to-company' ),
						'properties' => array(
							'nashenski/to-company' => array( 'const' => true ),
						),
					),
				),
			),
		);
	}

	private static function when_not_ticked() {
		return array(
			'checkout' => array(
				'properties' => array(
					'additional_fields' => array(
						'properties' => array(
							'nashenski/to-company' => array( 'not' => array( 'const' => true ) ),
						),
					),
				),
			),
		);
	}

	public static function register_block_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field( array(
			'id' => 'nashenski/to-company',
			'label' => __( 'I want an invoice for a company', 'nashenski-commerce' ),
			// A checkbox is never required, so it would read "(optional)".
			'optionalLabel' => __( 'I want an invoice for a company', 'nashenski-commerce' ),
			'location' => 'order',
			'type' => 'checkbox',
		) );

		$vat_required = 'no' !== nashenski_get_option( 'nap', 'dds_number_required' );
		$fields = array(
			'nashenski/company-name' => array( __( 'Company name', 'nashenski-commerce' ), true ),
			'nashenski/company-eik' => array( __( 'EIK', 'nashenski-commerce' ), true ),
			'nashenski/company-vat' => array( __( 'VAT number', 'nashenski-commerce' ), $vat_required ),
			'nashenski/company-mol' => array( __( 'MOL (responsible person)', 'nashenski-commerce' ), true ),
			'nashenski/company-settlement' => array( __( 'Registered town', 'nashenski-commerce' ), true ),
			'nashenski/company-address' => array( __( 'Registered address', 'nashenski-commerce' ), true ),
		);

		foreach ( $fields as $id => $field ) {
			$args = array(
				'id' => $id,
				'label' => $field[0],
				'optionalLabel' => $field[0] . ' ' . __( '(optional)', 'nashenski-commerce' ),
				'location' => 'order',
				'type' => 'text',
				'required' => $field[1] ? self::when_ticked() : false,
				'hidden' => self::when_not_ticked(),
				'sanitize_callback' => function ( $value ) {
					return sanitize_text_field( (string) $value );
				},
			);

			if ( 'nashenski/company-eik' === $id ) {
				$args['validate_callback'] = array( __CLASS__, 'validate_eik' );
			}

			woocommerce_register_additional_checkout_field( $args );
		}
	}

	/**
	 * Bulgarian EIK: 9 or 13 digits, optionally with the BG prefix.
	 */
	public static function validate_eik( $value ) {
		$digits = preg_replace( '/^BG/i', '', preg_replace( '/\s+/', '', (string) $value ) );

		if ( '' !== $digits && ! preg_match( '/^(\d{9}|\d{13})$/', $digits ) ) {
			return new \WP_Error( 'nashenski_invalid_eik', __( 'The EIK has 9 or 13 digits.', 'nashenski-commerce' ) );
		}

		return true;
	}

	/**
	 * Block checkout: copy each company field into the order data the documents read.
	 */
	public static function save_block_field( $key, $value, $group, $wc_object ) {
		if ( ! isset( self::BLOCK_FIELDS[ $key ] ) || ! $wc_object instanceof \WC_Order ) {
			return;
		}

		$target = self::BLOCK_FIELDS[ $key ];

		if ( 'nashenski/to-company' === $key ) {
			$value = $value ? '1' : '';
		}

		if ( 'company' === $target ) {
			$wc_object->set_billing_company( (string) $value );
		} else {
			$wc_object->update_meta_data( $target, (string) $value );
		}
	}

	/**
	 * Builder checkouts made of single field elements: the company fields, ready for the script to
	 * place after the billing fields when the template does not have them.
	 */
	public static function print_template() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || Pickers::is_block_checkout() || ! WC()->checkout() ) {
			return;
		}

		$fields = WC()->checkout()->get_checkout_fields( 'billing' );
		$keys = array( 'billing_to_company', 'billing_company', 'billing_company_eik', 'billing_vat_number', 'billing_company_mol', 'billing_company_settlement', 'billing_company_address' );

		echo '<template id="nashenski-company-fields" class="nashenski-extra-fields">';

		foreach ( $keys as $key ) {
			if ( isset( $fields[ $key ] ) ) {
				woocommerce_form_field( $key, $fields[ $key ], WC()->checkout()->get_value( $key ) );
			}
		}

		echo '</template>';
	}
}
