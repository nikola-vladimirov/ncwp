<?php
/**
 * Offline test (0.21.10): "Enable invoices" and "How to issue invoices" replace "When to generate
 * invoices?", "Invoice numbering" and "Company invoice fields". Covers the helper every reader asks
 * (Invoice\Mode), the upgrade from the old options, and what the Bulgarisation and mreja.net N-18
 * imports make of those plugins' invoice settings.
 * Run: php tests/invoice-mode.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions, Generic.Files.OneObjectStructurePerFile, Generic.CodeAnalysis.UnusedFunctionParameter -- offline test script: it stubs WordPress in one file, keeps WordPress's own names and signatures, and prints to a terminal.

define( 'ABSPATH', __DIR__ . '/fixtures/' );

/**
 * Option stub.
 *
 * @param string $name     Option.
 * @param mixed  $fallback Default.
 * @return mixed
 */
function get_option( $name, $fallback = false ) {
	return $GLOBALS['options'][ $name ] ?? $fallback;
}

/**
 * Option write stub.
 *
 * @param string $name  Option.
 * @param mixed  $value Value.
 * @return bool
 */
function update_option( $name, $value ) {
	$GLOBALS['options'][ $name ] = $value;
	return true;
}

require __DIR__ . '/../app/functions.php';
require __DIR__ . '/../app/Invoice/class-mode.php';
require __DIR__ . '/../app/Admin/class-migration.php';
require __DIR__ . '/../app/Admin/class-import-sources.php';

use Nashenski\Invoice\Mode;

/**
 * Order stub: meta only.
 */
class Invoice_Mode_Order {
	/**
	 * Meta.
	 *
	 * @var array
	 */
	public $meta = array();

	/**
	 * Get meta.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public function get_meta( $key ) {
		return $this->meta[ $key ] ?? '';
	}

	/**
	 * Update meta.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 */
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}
}

$checks = 0;

/**
 * Expect.
 *
 * @param mixed  $condition Condition.
 * @param string $message   Message.
 */
function expect( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: $message\n" );
		exit( 1 );
	}
}

/**
 * The saved pair, as "enabled/mode"; "unset" for an option that does not exist.
 *
 * @return string
 */
function pair() {
	return get_option( 'nashenski_settings_invoice_enabled', 'unset' ) . '/' . get_option( 'nashenski_settings_invoice_mode', 'unset' );
}

// The helper: invoices are on only with the checkbox and the "Invoices and N-18" switch.
$GLOBALS['options'] = array();
expect( ! Mode::enabled() && ! Mode::in_nra(), 'nothing saved: invoices off, not in the NRA audit file' );
$GLOBALS['options'] = array( 'nashenski_settings_invoice_enabled' => 'yes' );
expect( ! Mode::enabled(), 'the checkbox alone is not enough while "Invoices and N-18" is off' );
$GLOBALS['options']['nashenski_settings_apis_enable_documents'] = 'yes';
expect( Mode::enabled(), 'checkbox and "Invoices and N-18" on: invoices on' );
$GLOBALS['options']['nashenski_settings_invoice_enabled'] = 'no';
expect( ! Mode::enabled(), 'checkbox off: invoices off' );
$GLOBALS['options']['nashenski_settings_invoice_mode'] = 'request';
expect( ! Mode::in_nra(), 'request: not in the NRA audit file' );
$GLOBALS['options']['nashenski_settings_invoice_mode'] = 'request_nra';
expect( Mode::in_nra(), 'request_nra: in the NRA audit file' );

// The first invoice of an order decides, once, whether the order stays out of the NRA audit file.
$first_order = new Invoice_Mode_Order();
Mode::mark( $first_order );
expect( ! Mode::out_of_nra( $first_order ), 'request_nra: the order stays in the file' );
$GLOBALS['options']['nashenski_settings_invoice_mode'] = 'request';
$invoiced       = new Invoice_Mode_Order();
$invoiced->meta = array( 'nashenski_invoice_document' => 12 );
Mode::mark( $invoiced );
expect( ! Mode::out_of_nra( $invoiced ), 'an order that already has an invoice is not moved out of the file' );
Mode::mark( $first_order );
expect( Mode::out_of_nra( $first_order ) && 'yes' === $first_order->meta['nashenski_invoice_own_program'], 'request: the first invoice marks the order out of the file, in the meta the own-program orders have' );
$GLOBALS['options']['nashenski_settings_invoice_mode'] = 'request_nra';
$first_order->meta['nashenski_invoice_document']       = 13;
Mode::mark( $first_order );
expect( Mode::out_of_nra( $first_order ), 'an order kept out of the file stays out after the setting changes' );

// The upgrade table: old invoices, old numbering => the pair.
$table = array(
	array( 'disable', 'nra', 'no/request' ),
	array( 'disable', 'own', 'no/request' ),
	array( 'only_fields', 'own', 'yes/request' ),
	array( 'only_for_company', 'own', 'yes/request' ),
	array( 'always', 'own', 'yes/request' ),
	array( 'only_fields', 'nra', 'yes/request_nra' ),
	array( 'only_for_company', 'nra', 'yes/request_nra' ),
	array( 'always', 'nra', 'yes/request_nra' ),
	array( 'only_for_company', false, 'yes/request_nra' ), // Numbering never saved: its first choice was the NRA audit file.
	array( '', 'nra', 'no/request' ),
);

foreach ( $table as $row ) {
	$GLOBALS['options'] = array_filter(
		array(
			'nashenski_settings_invoice_invoices'          => $row[0],
			'nashenski_settings_company_invoice_numbering' => $row[1],
			'nashenski_settings_company_fields'            => 'no', // Company fields off with invoices on: the checkout box is part of invoices now.
		),
		function ( $value ) {
			return false !== $value;
		}
	);
	$before             = $GLOBALS['options'];
	Mode::upgrade();
	expect( pair() === $row[2], "upgrade of {$row[0]} + " . json_encode( $row[1] ) . ' gives ' . $row[2] . ', got ' . pair() );
	expect( array_intersect_key( $GLOBALS['options'], $before ) === $before, 'the old options stay for one version' );
}

// Once: a later run does not undo what the store chose after the upgrade.
$GLOBALS['options'] = array(
	'nashenski_settings_invoice_invoices' => 'always',
	'nashenski_settings_invoice_enabled'  => 'no',
	'nashenski_settings_invoice_mode'     => 'request',
);
Mode::upgrade();
expect( 'no/request' === pair(), 'the upgrade runs once' );

// A new store has nothing to map: the pair stays unset for the setup wizard and the settings.
$GLOBALS['options'] = array();
Mode::upgrade();
expect( 'unset/unset' === pair(), 'a store that never saved the old choice is left alone' );

// A store on 0.21.9 or earlier that never saved the old choice keeps what it had: the company box while documents were on, unless "Company invoice fields" was saved off; in the NRA file unless numbering was "own".
foreach ( array(
	array( array( 'nashenski_settings_apis_enable_documents' => 'yes' ), 'yes/request_nra' ),
	array(
		array(
			'nashenski_settings_apis_enable_documents'     => 'yes',
			'nashenski_settings_company_invoice_numbering' => 'own',
		),
		'yes/request',
	),
	array(
		array(
			'nashenski_settings_apis_enable_documents' => 'yes',
			'nashenski_settings_company_fields'        => 'no',
		),
		'no/request',
	),
	array( array( 'nashenski_settings_apis_enable_documents' => 'no' ), 'no/request' ),
	array(
		array(
			'nashenski_settings_apis_enable_documents' => 'no',
			'nashenski_settings_company_fields'        => 'yes',
		),
		'yes/request_nra',
	),
) as $row ) {
	$GLOBALS['options'] = $row[0];
	Mode::upgrade( '0.21.9' );
	expect( pair() === $row[1], 'unsaved choice on 0.21.9 with ' . json_encode( $row[0] ) . ' gives ' . $row[1] . ', got ' . pair() );
}
$GLOBALS['options'] = array( 'nashenski_settings_apis_enable_documents' => 'yes' );
Mode::upgrade( '0.21.10' );
expect( 'unset/unset' === pair(), 'a site already on 0.21.10 with nothing saved is left alone' );

// Bulgarisation for WooCommerce, 4.x (woo_bg) and 5.x (bulgarisation): its invoices were in the NRA audit file while its N-18 was on.
foreach ( array( 'woo_bg', 'bulgarisation' ) as $old ) {
	foreach ( array(
		array( 'disable', 'yes', 'no/request' ),
		array( 'only_fields', 'yes', 'yes/request_nra' ),
		array( 'only_for_company', 'yes', 'yes/request_nra' ),
		array( 'always', 'yes', 'yes/request_nra' ),
		array( 'only_for_company', 'no', 'yes/request' ),
		array( 'always', false, 'yes/request' ),
	) as $row ) {
		// Imported values win over what Nashenski had.
		$GLOBALS['options'] = array(
			$old . '_settings_invoice_invoices'  => $row[0],
			$old . '_settings_invoice_nra_n18'   => $row[1],
			'nashenski_settings_invoice_enabled' => 'no',
			'nashenski_settings_invoice_mode'    => 'request',
		);
		\Nashenski\Admin\Migration::invoice_settings( $old );
		expect( pair() === $row[2], "$old import of {$row[0]} with N-18 " . json_encode( $row[1] ) . ' gives ' . $row[2] . ', got ' . pair() );
	}

	$GLOBALS['options'] = array( 'nashenski_settings_invoice_enabled' => 'yes' );
	\Nashenski\Admin\Migration::invoice_settings( $old );
	expect( 'yes/unset' === pair(), "$old without a saved invoice choice changes nothing" );
}

// The mreja.net N-18 plugin (notasuptooptions): "Фактури?" Да is document_type = invoice; all its orders were in its NRA file.
foreach ( array(
	array( array( 'document_type' => 'invoice' ), 'yes', 'request_nra' ),
	array(
		array(
			'document_type'  => 'invoice',
			'manual_invoice' => '1',
		),
		'yes',
		'request_nra',
	),
	array( array( 'document_type' => 'purchase_order' ), 'no', 'request' ),
	array( array(), 'no', 'request' ),
) as $row ) {
	$got = \Nashenski\Admin\Import_Sources::n18_invoices( $row[0] );
	expect( $row[1] === $got['enabled'] && $row[2] === $got['mode'], 'N-18 ' . json_encode( $row[0] ) . " gives {$row[1]}/{$row[2]}" );
}

// The settings screen: the pair sits in Invoices and documents, the old controls and the company group are gone.
$nra    = file_get_contents( __DIR__ . '/../app/Admin/Tabs/class-nra-tab.php' );
$fields = file_get_contents( __DIR__ . '/../app/Admin/Tabs/class-fields-tab.php' );
$app    = '';
$direct = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( __DIR__ . '/../app', FilesystemIterator::SKIP_DOTS ) ) as $file ) {
	if ( 'php' !== $file->getExtension() || 'class-mode.php' === $file->getFilename() ) {
		continue;
	}
	$source = file_get_contents( $file->getPathname() );
	$app   .= $source;
	if ( preg_match( "/nashenski_get_option\( 'invoice', '(enabled|mode)'/", $source ) ) {
		$direct[] = $file->getFilename();
	}
}
sort( $direct );
expect( preg_match( "/'invoice'\s*=> array\(.*?'nra_n18'.*?TrueFalse_Field\( 'enabled'.*?'request'\s*=> array.*?'request_nra'\s*=> array.*?'mode',.*?'trigger'/s", $nra ), 'Enable invoices and How to issue invoices follow Enable N-18, with request as the first (default) choice' );
expect( false === strpos( $nra, "'invoices'" ) && false === strpos( $fields, 'invoice_numbering' ) && false === strpos( $fields, 'Company_Fields' ), 'the old controls and the company group are gone' );
expect( ! preg_match( "/nashenski_get_option\( '(invoice', 'invoices|company', '(fields|invoice_numbering))'/", $app ), 'no reader of the old options is left outside Invoice\Mode' );
expect( array( 'class-import-sources.php', 'class-setup-wizard.php' ) === $direct, 'the pair is read through Invoice\Mode; only the N-18 import and the setup wizard read the saved checkbox itself, to keep or show it: ' . implode( ', ', $direct ) );

// Nothing hides while invoices are off: the numbering and due days also feed "Make invoice" on the order screen.
expect( false === strpos( file_get_contents( __DIR__ . '/../assets/js/settings.js' ), 'nashenski-invoice-only' ), 'settings.js hides no invoice rows' );
expect( false !== strpos( file_get_contents( __DIR__ . '/../app/Admin/class-setting-help.php' ), "'Also used when you make an invoice by hand.'" ), 'the next number note is in the help text' );

// The company fields keep their checkout keys and order meta, so imports from other plugins still map onto them.
$company = file_get_contents( __DIR__ . '/../app/Front_End/Checkout/class-company-fields.php' ) . file_get_contents( __DIR__ . '/../app/Front_End/Checkout/class-company.php' );
foreach ( array( 'billing_to_company', 'billing_company', 'billing_company_eik', 'billing_vat_number', 'billing_company_mol', 'billing_company_settlement', 'billing_company_address', '_billing_to_company', '_billing_company_eik', '_billing_vat_number', '_billing_company_mol', '_billing_company_settlement', '_billing_company_address' ) as $key ) {
	expect( false !== strpos( $company, "'$key'" ), "company field key $key is unchanged" );
}

echo 'PASS: ' . $checks . " invoice mode assertions\n";
