<?php // phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions -- offline CLI test script: temp files and plain-text output are on purpose.
/**
 * Offline check: PDF::merge() joins the original and the copy of a document with FPDI (0.21.7, in place
 * of the dev-master PDF merger library): all pages, in order, each with its own size and orientation.
 *
 * Run: php tests/pdf-merge.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/Invoice/PDF/class-pdf.php';

$dir = sys_get_temp_dir() . '/nashenski-pdf-merge-' . getmypid();
mkdir( $dir );

// The original: two A4 portrait pages. The copy: one 100 x 50 mm landscape page.
$original = new FPDF();
$original->AddPage();
$original->AddPage();
$original->Output( 'F', "$dir/original.pdf" );
$copy = new FPDF();
$copy->AddPage( 'L', array( 50, 100 ) );
$copy->Output( 'F', "$dir/copy.pdf" );

file_put_contents( "$dir/merged.pdf", Nashenski\Invoice\PDF\PDF::merge( array( "$dir/original.pdf", "$dir/copy.pdf" ) ) );

$reader = new setasign\Fpdi\Fpdi();
$count  = $reader->setSourceFile( "$dir/merged.pdf" );
$found  = array();
for ( $number = 1; $number <= $count; $number++ ) {
	$size    = $reader->getTemplateSize( $reader->importPage( $number ) );
	$found[] = $size['orientation'] . ' ' . round( $size['width'] ) . 'x' . round( $size['height'] );
}

// FPDI keeps its source files open until it is freed; Windows cannot remove the folder before that.
unset( $reader );
gc_collect_cycles();
foreach ( array( 'original.pdf', 'copy.pdf', 'merged.pdf' ) as $file ) {
	unlink( "$dir/$file" );
}
rmdir( $dir );

$expected = array( 'P 210x297', 'P 210x297', 'L 100x50' );
if ( $found !== $expected ) {
	fwrite( STDERR, 'FAIL: pages ' . implode( ', ', $found ) . ', expected ' . implode( ', ', $expected ) . "\n" );
	exit( 1 );
}
echo 'PASS: ' . $count . " pages merged in order, each with its own size and orientation\n";
