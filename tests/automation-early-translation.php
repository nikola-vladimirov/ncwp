<?php
/** Offline: Automation::__construct must not call __() (or actions()) before init. Run: php tests/automation-early-translation.php */
$src = file_get_contents( __DIR__ . '/../app/Shipping/Automation.php' );
preg_match( '/public function __construct\(\) \{(.*?)\n\t\}\n/s', $src, $m );
$body = $m[1] ?? '';
$fail = array();
if ( '' === $body ) { $fail[] = 'constructor not found'; }
if ( preg_match( '/\b__\(|_e\(|esc_html__\(|esc_attr__\(|self::actions\(/', $body ) ) { $fail[] = 'constructor translates or calls actions()'; }
if ( ! preg_match( '/const ACTION_IDS = array\( (.*?) \);/s', $src, $ids ) ) { $fail[] = 'ACTION_IDS missing'; }
else {
	preg_match( '/function actions\(\) \{(.*?)\n\t\}/s', $src, $a );
	foreach ( explode( ',', $ids[1] ) as $id ) {
		if ( false === strpos( $a[1] ?? '', trim( $id ) ) ) { $fail[] = 'actions() lacks ' . trim( $id ); }
	}
}
echo $fail ? "FAIL: " . implode( '; ', $fail ) . "\n" : "ok: constructor has no early translation, ids match actions()\n";
exit( $fail ? 1 : 0 );
