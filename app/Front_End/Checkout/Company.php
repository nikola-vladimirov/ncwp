<?php
namespace Nashenski\Front_End\Checkout;

defined( 'ABSPATH' ) || exit;

class Company {
	public function __construct() {
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'vat_number_field' ), 20 );
		add_action( 'woocommerce_admin_billing_fields', array( __CLASS__, 'admin_billing_fields' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_fields' ), 5, 2 );
		add_filter( 'woocommerce_states', array( __CLASS__, 'reorder_bg_states' ) );
		add_filter( 'woocommerce_get_country_locale', array( __CLASS__, 'bg_locale_fields' ) );
		add_filter( 'woocommerce_form_field_checkbox', array( __CLASS__, 'checkbox_without_optional' ), 10, 2 );
	}

	public static function vat_number_field( $fields ) {
		if ( class_exists( 'WC_EU_VAT_Number_Init' ) && isset( $fields['billing']['billing_vat_number'] ) ) {
			$fields['billing']['billing_vat_number']['required'] = ( nashenski_get_option('nap', 'dds_number_required' ) !== 'no' );
			$fields['billing']['billing_vat_number']['class'][] = 'nashenski-company-info form-row-first';
		}

		if ( isset( $fields['billing']['billing_company'] ) ) {
			$fields['billing']['billing_company']['priority'] = 121;
			$fields['billing']['billing_company']['required'] = true;
			$fields['billing']['billing_company']['class'] = array( 'nashenski-company-info form-row-last' );
		} else {
			$fields['billing']['billing_company'] = array(
				'label' => __( 'Company name', 'nashenski-commerce' ),
				'autocomplete' => 'organization',
				'priority' => 121,
				'required' => true,
				'class' => 'nashenski-company-info form-row-last',
			);
		}
		
		$fields['billing']['billing_to_company'] = array(
			'type'      => 'checkbox',
			'label'    => __( 'I want an invoice for a company', 'nashenski-commerce' ),
			'class'    => array(
				'form-row-wide',
			),
			'id' => 'woo-billing-to-company',
			'priority' => 119,
		);

		$fields['billing']['billing_company_mol'] = array(
			'label'    => __( 'MOL (responsible person)', 'nashenski-commerce' ),
			'required' => true,
			'class'    => array(
				'form-row-first',
				'nashenski-company-info',
			),
			'id' => 'nashenski-billing-company-mol',
			'priority' => 121,
		);

		$fields['billing']['billing_company_eik'] = array(
			'label'    => __( 'EIK', 'nashenski-commerce' ),
			'required' => true,
			'class'    => array(
				'form-row-last',
				'nashenski-company-info',
			),
			'id' => 'nashenski-billing-company-eik',
			'priority' => 121,
		);

		$fields['billing']['billing_company_settlement'] = array(
			'label'    => __( 'Registered town', 'nashenski-commerce' ),
			'required' => true,
			'class'    => array(
				'form-row-first',
				'nashenski-company-info',
			),
			'id' => 'nashenski-billing-company-settlement',
			'priority' => 121,
		);

		$fields['billing']['billing_company_address'] = array(
			'label'    => __( 'Registered address', 'nashenski-commerce' ),
			'required' => true,
			'class'    => array(
				'form-row-last',
				'nashenski-company-info',
			),
			'id' => 'nashenski-billing-company-address',
			'priority' => 121,
			'show'  => false,
		);

		return $fields;
	}

	/**
	 * A checkbox is never "required", so WooCommerce would call it optional.
	 */
	public static function checkbox_without_optional( $field, $key ) {
		return 'billing_to_company' === $key ? preg_replace( '#\s*<span class="optional">.*?</span>#', '', $field ) : $field;
	}

	public static function admin_billing_fields( $fields ) {
		unset( $fields[ 'company' ] );

		$fields['to_company'] = array(
			'label' => __( 'Do you want a company invoice?', 'nashenski-commerce' ),
			'show'  => false,
		);

		$fields['company'] = array(
			'label' => __( 'Company', 'nashenski-commerce' ),
			'show'  => true,
			'wrapper_class' => '_billing_address_2_field'
		);

		$fields['company_mol'] = array(
			'label' => __( 'MOL', 'nashenski-commerce' ),
			'show'  => true,
			'wrapper_class' => '_billing_address_1_field'
		);

		$fields['company_eik'] = array(
			'label' => __( 'EIK', 'nashenski-commerce' ),
			'show'  => true,
			'wrapper_class' => '_billing_address_2_field'
		);

		$fields['company_settlement'] = array(
			'label' => __( 'Settlement', 'nashenski-commerce' ),
			'show'  => true,
			'wrapper_class' => '_billing_address_1_field'
		);

		$fields['company_address'] = array(
			'label' => __( 'Company Address', 'nashenski-commerce' ),
			'show'  => true,
			'wrapper_class' => '_billing_address_2_field'
		);

		return $fields;
	}

	public static function validate_fields( $data, $errors ) {
		$fields = array(
			'billing_company',
			'billing_company_eik',
			'billing_company_mol',
			'billing_company_settlement',
			'billing_company_address',
			'billing_vat_number',
		);
		
		if ( empty( $data['billing_to_company'] ) ) {
			foreach ( $fields as $field ) {
				unset( $errors->errors[ $field . '_required'] );
				unset( $errors->error_data[ $field . '_required'] );
			}
		}
	}

	public static function reorder_bg_states( $states ) {
		$oblasti = $states['BG'];
		asort( $oblasti );
		$states['BG'] = $oblasti;

		return $states;
	}

	public static function bg_locale_fields( $fields ) {
		if ( isset( $fields['BG']['state']['required'] ) ) {
			$fields['BG']['state']['required'] = true;
		}

		return $fields;
	}
}

add_action( 'woocommerce_after_checkout_validation', function( $data, $errors ) {
	unset( $errors->errors[ 'billing_vat_number'] );
	unset( $errors->error_data[ 'billing_vat_number'] );
}, 5, 2 );