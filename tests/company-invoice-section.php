<?php
/** Offline check: the company invoice group is shown in Documents, not Checkout. Run: php tests/company-invoice-section.php */
$src = file_get_contents( __DIR__ . '/../app/Admin/Settings_Page.php' );
preg_match( "/'checkout' => array\((.*?)\n\t\t\t\),/s", $src, $checkout );
preg_match( "/'documents' => array\((.*?)\n\t\t\t\),/s", $src, $documents );
$fail = 0;
foreach ( array(
	'documents has company group' => false !== strpos( $documents[1] ?? '', 'Company_Fields::GROUP' ),
	'checkout lacks company group' => false === strpos( $checkout[1] ?? 'x', 'Company_Fields::GROUP' ),
	'checkout keeps order notes group' => false !== strpos( $checkout[1] ?? '', 'Field_Editor::GROUP_CLASSIC' ),
) as $name => $ok ) {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	$fail += $ok ? 0 : 1;
}
exit( $fail ? 1 : 0 );
