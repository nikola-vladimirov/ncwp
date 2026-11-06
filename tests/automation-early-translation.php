<?php
/**
 * Offline: these constructors must not call __() or the label helpers before init. Run: php tests/automation-early-translation.php
 *
 * @package Nashenski_Commerce
 */

$checks = array(
	'app/Shipping/class-automation.php'   => array( 'ACTION_IDS', 'actions' ),
	'app/Front_End/class-quick-order.php' => array( 'PLACEMENT_IDS', 'placements' ),
);
$fail   = array();
foreach ( $checks as $file => list( $const, $labels ) ) {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a local source file, no WordPress here.
	$src = file_get_contents( __DIR__ . '/../' . $file );
	preg_match( '/public function __construct\(\) \{(.*?)\n\t\}\n/s', $src, $m );
	$body = $m[1] ?? '';
	if ( '' === $body ) {
		$fail[] = "$file: constructor not found"; }
	if ( preg_match( '/\b__\(|\b_e\(|esc_html__\(|esc_attr__\(|self::' . $labels . '\(/', $body ) ) {
		$fail[] = "$file: constructor translates or calls $labels()"; }
	if ( ! preg_match( '/const ' . $const . ' = array\( (.*?) \);/s', $src, $ids ) ) {
		$fail[] = "$file: $const missing";
		continue; }
	preg_match( '/function ' . $labels . '\(\) \{(.*?)\n\t\}/s', $src, $a );
	foreach ( explode( ',', $ids[1] ) as $const_id ) {
		if ( false === strpos( $a[1] ?? '', trim( $const_id ) ) ) {
			$fail[] = "$file: $labels() lacks " . trim( $const_id ); }
	}
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output, WordPress is not loaded.
echo $fail ? 'FAIL: ' . implode( '; ', $fail ) . "\n" : "ok: 2 constructors have no early translation, ids match their label lists\n";
exit( $fail ? 1 : 0 );
