<?php
/**
 * Offline test: in Documents, "Invoice numbering" sits under "When to generate invoices?", keeps its
 * company option name, and the invoice-only rows hide while invoices are off.
 * Run: php tests/invoice-only-settings.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * WooCommerce settings page fixture.
 */
class WC_Settings_Page {}

require __DIR__ . '/../app/Admin/Settings_Page.php';

$checks = 0;
/**
 * Expect.
 *
 * @param mixed $condition Condition.
 * @param mixed $message Message.
 */
function expect( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

// The Documents section as built: the invoice box first, the company box after it.
$section = array(
	array(
		'type' => 'title',
		'id'   => 'nashenski_invoice',
	),
	array( 'id' => 'nashenski_settings_invoice_nra_n18' ),
	array( 'id' => 'nashenski_settings_invoice_invoices' ),
	array( 'id' => 'nashenski_settings_invoice_trigger' ),
	array( 'id' => 'nashenski_settings_invoice_next_invoice_number' ),
	array( 'id' => 'nashenski_settings_invoice_next_invoice_separate_number' ),
	array( 'id' => 'nashenski_settings_invoice_due_days' ),
	array( 'id' => 'nashenski_settings_invoice_separate_copy_documents' ),
	array(
		'type' => 'sectionend',
		'id'   => 'nashenski_invoice',
	),
	array(
		'type' => 'title',
		'id'   => 'nashenski_company',
	),
	array( 'id' => 'nashenski_settings_company_fields' ),
	array( 'id' => 'nashenski_settings_company_invoice_numbering' ),
	array(
		'type' => 'sectionend',
		'id'   => 'nashenski_company',
	),
);

foreach ( array( 'always', 'only_for_company', 'only_fields', '', 'disable' ) as $invoices ) {
	$rows = \Nashenski\Admin\Settings_Page::invoice_rows( $section, $invoices );
	$ids  = array_column( $rows, 'id' );
	$by   = array_column( $rows, null, 'id' );

	expect( count( $rows ) === count( $section ), "$invoices: no setting is lost or added" );
	expect( array_search( 'nashenski_settings_company_invoice_numbering', $ids, true ) === array_search( 'nashenski_settings_invoice_invoices', $ids, true ) + 1, "$invoices: numbering sits right under When to generate invoices?" );
	expect( array_search( 'nashenski_settings_company_invoice_numbering', $ids, true ) < array_search( 'nashenski_company', $ids, true ), "$invoices: numbering is in the invoices box, not the company box" );

	foreach ( \Nashenski\Admin\Settings_Page::INVOICE_ONLY as $key ) {
		$hidden = false !== strpos( $by[ $key ]['row_class'] ?? '', 'hidden' );
		expect( 0 === strpos( $by[ $key ]['row_class'] ?? '', 'nashenski-invoice-only' ), "$invoices: $key is marked invoice-only" );
		expect( ( 'disable' === $invoices ) === $hidden, "$invoices: $key is " . ( 'disable' === $invoices ? 'hidden' : 'shown' ) );
		expect( ! isset( $by[ $key ]['is_option'] ) && ! isset( $by[ $key ]['custom_attributes'] ), "$invoices: $key is still saved (not locked)" );
	}

	foreach ( array( 'nashenski_settings_invoice_invoices', 'nashenski_settings_invoice_next_invoice_number', 'nashenski_settings_company_fields' ) as $key ) {
		expect( ! isset( $by[ $key ]['row_class'] ), "$invoices: $key stays visible" );
	}
}

// Each invoice-only id is a real field with that option name.
$source = file_get_contents( __DIR__ . '/../app/Admin/Tabs/Nra_Tab.php' ) . file_get_contents( __DIR__ . '/../app/Admin/Tabs/Fields_Tab.php' );
foreach ( \Nashenski\Admin\Settings_Page::INVOICE_ONLY as $key ) {
	$name = preg_replace( '/^nashenski_settings_(invoice|company)_/', '', $key );
	expect( false !== strpos( $source, "'$name'" ), "$name is a field of the Documents or Fields tab" );
}

// The live toggle watches the same select and row class.
$js = file_get_contents( __DIR__ . '/../assets/js/settings.js' );
expect( false !== strpos( $js, "'#nashenski_settings_invoice_invoices'" ) && false !== strpos( $js, "'.wc-settings-row-nashenski-invoice-only'" ), 'settings.js toggles the invoice-only rows on change' );

echo 'PASS: ' . $checks . " invoice-only settings assertions\n";
