<?php
/**
 * Offline check: the company invoice group is shown in Documents, not Checkout. Run: php tests/company-invoice-section.php
 *
 * @package Nashenski_Commerce
 */

$src = file_get_contents( __DIR__ . '/../app/Admin/Settings_Page.php' );
preg_match( "/'checkout'\s*=> array\((.*?)\n\t\t\t\),/s", $src, $checkout );
preg_match( "/'documents'\s*=> array\((.*?)\n\t\t\t\),/s", $src, $documents );
$fail = 0;
foreach ( array(
	'documents has company group'                          => false !== strpos( $documents[1] ?? '', 'Company_Fields::GROUP' ),
	'checkout fields part takes all groups except company' => false !== strpos( $checkout[1] ?? '', "array( 'fields', null, null, \$this->tab( 'documents' ) ? array( \\Nashenski\\Front_End\\Checkout\\Company_Fields::GROUP ) : array() )" ),
	'part filter skips excluded groups'                    => false !== strpos( $src, 'in_array( $group, $part[3] ?? array(), true )' ),
) as $name => $ok ) {
	echo ( $ok ? 'ok   ' : 'FAIL ' ) . $name . "\n";
	$fail += $ok ? 0 : 1;
}
exit( $fail ? 1 : 0 );
