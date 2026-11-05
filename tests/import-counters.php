<?php
/** Offline test: Migration::copy_options() keeps a higher Nashenski number counter. Run: php tests/import-counters.php */
namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	class CounterDatabase {
		public $options = 'options';
		public function prepare( $query, ...$args ) { return $query; }
		public function esc_like( $value ) { return $value; }
		public function get_col() { return array_keys( $GLOBALS['options'] ); }
	}
	function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
	function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; }

	require __DIR__ . '/../app/Admin/Migration.php';

	$GLOBALS['wpdb'] = new CounterDatabase();
	$GLOBALS['options'] = array(
		'bulgarisation_settings_invoice_next_invoice_number' => '0000000005',
		'bulgarisation_settings_invoice_next_invoice_separate_number' => '0000000003',
		'bulgarisation_settings_invoice_company_name' => 'Old Ltd',
		'nashenski_settings_invoice_next_invoice_number' => '0006548667',
	);

	$copy = new ReflectionMethod( \Nashenski\Admin\Migration::class, 'copy_options' );

	$copy->invoke( null, 'bulgarisation_settings_', 'bulgarisation' );
	$o = $GLOBALS['options'];

	$fail = array();
	if ( '0006548667' !== $o['nashenski_settings_invoice_next_invoice_number'] ) { $fail[] = 'higher counter was lowered'; }
	if ( '0000000003' !== ( $o['nashenski_settings_invoice_next_invoice_separate_number'] ?? null ) ) { $fail[] = 'unset counter was not copied'; }
	if ( 'Old Ltd' !== ( $o['nashenski_settings_invoice_company_name'] ?? null ) ) { $fail[] = 'ordinary setting not copied'; }

	$GLOBALS['options']['nashenski_settings_invoice_next_invoice_number'] = '0000000002';
	$copy->invoke( null, 'bulgarisation_settings_', 'bulgarisation' );
	if ( '0000000005' !== $GLOBALS['options']['nashenski_settings_invoice_next_invoice_number'] ) { $fail[] = 'lower counter was not raised to the old value'; }

	echo $fail ? 'FAIL: ' . implode( '; ', $fail ) . "\n" : "OK import-counters\n";
	exit( $fail ? 1 : 0 );
}
