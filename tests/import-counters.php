<?php
/**
 * Offline test: Migration::copy_options() keeps a higher Nashenski number counter. Run: php tests/import-counters.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput, WordPress.WP.GlobalVariablesOverride, Generic.CodeAnalysis.UnusedFunctionParameter -- offline test script: it stubs WordPress in one file, keeps WordPress's own names and signatures, and prints to a terminal.

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	/**
	 * Counterdatabase.
	 */
	class CounterDatabase {
		/**
		 * Options.
		 *
		 * @var string
		 */
		public $options = 'options';
		/**
		 * Prepare.
		 *
		 * @param mixed   $query Query.
		 * @param mixed[] ...$args Args.
		 * @return mixed Result.
		 */
		public function prepare( $query, ...$args ) {
			return $query; }
		/**
		 * Esc like.
		 *
		 * @param mixed $value Value.
		 * @return mixed Result.
		 */
		public function esc_like( $value ) {
			return $value; }
		/**
		 * Get col.
		 *
		 * @return mixed Result.
		 */
		public function get_col() {
			return array_keys( $GLOBALS['options'] ); }
	}
	/**
	 * Get option.
	 *
	 * @param mixed $key Key.
	 * @param bool  $fallback Default.
	 * @return mixed Result.
	 */
	function get_option( $key, $fallback = false ) {
		return $GLOBALS['options'][ $key ] ?? $fallback; }
	/**
	 * Update option.
	 *
	 * @param mixed $key Key.
	 * @param mixed $value Value.
	 * @param mixed $autoload Autoload.
	 */
	function update_option( $key, $value, $autoload = null ) {
		$GLOBALS['options'][ $key ] = $value; }

	require __DIR__ . '/../app/Admin/class-migration.php';

	$GLOBALS['wpdb']    = new CounterDatabase();
	$GLOBALS['options'] = array(
		'bulgarisation_settings_invoice_next_invoice_number' => '0000000005',
		'bulgarisation_settings_invoice_next_invoice_separate_number' => '0000000003',
		'bulgarisation_settings_invoice_company_name'    => 'Old Ltd',
		'nashenski_settings_invoice_next_invoice_number' => '0006548667',
	);

	$copy = new ReflectionMethod( \Nashenski\Admin\Migration::class, 'copy_options' );
	if ( PHP_VERSION_ID < 80100 ) {
		$copy->setAccessible( true );
	}

	$copy->invoke( null, 'bulgarisation_settings_', 'bulgarisation' );
	$o = $GLOBALS['options'];

	$fail = array();
	if ( '0006548667' !== $o['nashenski_settings_invoice_next_invoice_number'] ) {
		$fail[] = 'higher counter was lowered'; }
	if ( '0000000003' !== ( $o['nashenski_settings_invoice_next_invoice_separate_number'] ?? null ) ) {
		$fail[] = 'unset counter was not copied'; }
	if ( 'Old Ltd' !== ( $o['nashenski_settings_invoice_company_name'] ?? null ) ) {
		$fail[] = 'ordinary setting not copied'; }

	$GLOBALS['options']['nashenski_settings_invoice_next_invoice_number'] = '0000000002';
	$copy->invoke( null, 'bulgarisation_settings_', 'bulgarisation' );
	if ( '0000000005' !== $GLOBALS['options']['nashenski_settings_invoice_next_invoice_number'] ) {
		$fail[] = 'lower counter was not raised to the old value'; }

	$GLOBALS['options']['bulgarisation_settings_invoice_next_invoice_separate_number'] = '';
	$GLOBALS['options']['nashenski_settings_invoice_next_invoice_separate_number']     = '0000000042';
	$copy->invoke( null, 'bulgarisation_settings_', 'bulgarisation' );
	if ( '0000000042' !== $GLOBALS['options']['nashenski_settings_invoice_next_invoice_separate_number'] ) {
		$fail[] = 'empty old separate counter wiped the live one'; }
	echo $fail ? 'FAIL: ' . implode( '; ', $fail ) . "\n" : "OK import-counters\n";
	exit( $fail ? 1 : 0 );
}
