<?php
/** Offline: these constructors must not call __() or the label helpers before init. Run: php tests/automation-early-translation.php */
$checks = array(
	'app/Shipping/Automation.php' => array( 'ACTION_IDS', 'actions' ),
	'app/Front_End/Quick_Order.php' => array( 'PLACEMENT_IDS', 'placements' ),
);
$fail = array();
foreach ( $checks as $file => list( $const, $labels ) ) {
	$src = file_get_contents( __DIR__ . '/../' . $file );
	preg_match( '/public function __construct\(\) \{(.*?)\n\t\}\n/s', $src, $m );
	$body = $m[1] ?? '';
	if ( '' === $body ) { $fail[] = "$file: constructor not found"; }
	if ( preg_match( '/\b__\(|\b_e\(|esc_html__\(|esc_attr__\(|self::' . $labels . '\(/', $body ) ) { $fail[] = "$file: constructor translates or calls $labels()"; }
	if ( ! preg_match( '/const ' . $const . ' = array\( (.*?) \);/s', $src, $ids ) ) { $fail[] = "$file: $const missing"; continue; }
	preg_match( '/function ' . $labels . '\(\) \{(.*?)\n\t\}/s', $src, $a );
	foreach ( explode( ',', $ids[1] ) as $id ) {
		if ( false === strpos( $a[1] ?? '', trim( $id ) ) ) { $fail[] = "$file: $labels() lacks " . trim( $id ); }
	}
}
echo $fail ? "FAIL: " . implode( '; ', $fail ) . "\n" : "ok: 2 constructors have no early translation, ids match their label lists\n";
exit( $fail ? 1 : 0 );
