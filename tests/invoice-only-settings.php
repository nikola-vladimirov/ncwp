<?php
/**
 * Offline test: in Documents, "Invoice numbering" sits under "When to generate invoices?", keeps its
 * company option name, and no invoice row hides while invoices are off ("Make invoice" uses them).
 * Run: php tests/invoice-only-settings.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions -- offline test script: it stubs WordPress in one file, keeps WordPress's own names and signatures, and prints to a terminal.

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * WooCommerce settings page fixture.
 */
class WC_Settings_Page {}

require __DIR__ . '/../app/Admin/class-settings-page.php';

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

// Nothing hides while invoices are off: these all feed "Make invoice" on the order screen.
$manual = array( 'nashenski_settings_company_invoice_numbering', 'nashenski_settings_invoice_next_invoice_separate_number', 'nashenski_settings_invoice_due_days', 'nashenski_settings_invoice_separate_copy_documents' );
$rows   = \Nashenski\Admin\Settings_Page::invoice_rows( $section );
$ids    = array_column( $rows, 'id' );
$by     = array_column( $rows, null, 'id' );

expect( count( $rows ) === count( $section ), 'no setting is lost or added' );
expect( array_search( 'nashenski_settings_company_invoice_numbering', $ids, true ) === array_search( 'nashenski_settings_invoice_invoices', $ids, true ) + 1, 'numbering sits right under When to generate invoices?' );
expect( array_search( 'nashenski_settings_company_invoice_numbering', $ids, true ) < array_search( 'nashenski_company', $ids, true ), 'numbering is in the invoices box, not the company box' );

foreach ( $manual as $key ) {
	expect( ! isset( $by[ $key ]['row_class'] ), "$key stays visible" );
	expect( ! isset( $by[ $key ]['is_option'] ) && ! isset( $by[ $key ]['custom_attributes'] ), "$key is still saved (not locked)" );
}

// Each manual-use id is a real field with that option name, and the document code reads it.
$source = file_get_contents( __DIR__ . '/../app/Admin/Tabs/class-nra-tab.php' ) . file_get_contents( __DIR__ . '/../app/Admin/Tabs/class-fields-tab.php' );
$docs   = file_get_contents( __DIR__ . '/../app/Admin/Order/class-documents.php' ) . file_get_contents( __DIR__ . '/../app/Invoice/Document/class-invoice.php' ) . file_get_contents( __DIR__ . '/../app/Invoice/Document/class-basedocument.php' );
foreach ( $manual as $key ) {
	$name = preg_replace( '/^nashenski_settings_(invoice|company)_/', '', $key );
	expect( false !== strpos( $source, "'$name'" ), "$name is a field of the Documents or Fields tab" );
	expect( false !== strpos( $docs, "'$name'" ), "$name is read when an invoice is made" );
}

// No live hide toggle is left, and the next number says it also serves hand-made invoices.
$js = file_get_contents( __DIR__ . '/../assets/js/settings.js' );
expect( false === strpos( $js, 'nashenski-invoice-only' ), 'settings.js no longer hides invoice rows' );
expect( false !== strpos( file_get_contents( __DIR__ . '/../app/Admin/class-setting-help.php' ), "'Also used when you make an invoice by hand.'" ), 'next number note is in the help text' );

echo 'PASS: ' . $checks . " invoice-only settings assertions\n";
