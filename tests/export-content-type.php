<?php
/**
 * Offline test: an export download names its character set, Windows-1251 included.
 * Run: php tests/export-content-type.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions -- offline test script: it stubs WordPress in one file, keeps WordPress's own names and signatures, and prints to a terminal.

namespace Nashenski\Export\Builder {
	/**
	 * Writer fixture.
	 */
	class Writer {
		/**
		 * Mime.
		 *
		 * @param string $format Format.
		 */
		public static function mime( $format ) {
			return array(
				'csv'  => 'text/csv',
				'json' => 'application/json',
				'xml'  => 'application/xml',
				'xlsx' => 'application/vnd.ms-excel',
			)[ $format ];
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	/**
	 * Add action.
	 */
	function add_action() {}

	require __DIR__ . '/../app/Admin/class-export-builder.php';

	$checks = 0;
	foreach ( array(
		array( 'csv', 'utf8', 'text/csv; charset=utf-8' ),
		array( 'csv', 'utf8bom', 'text/csv; charset=utf-8' ),
		array( 'csv', 'cp1251', 'text/csv; charset=windows-1251' ),
		array( 'json', 'utf8', 'application/json; charset=utf-8' ),
		array( 'xml', 'cp1251', 'application/xml; charset=windows-1251' ),
		array( 'xlsx', 'utf8', 'application/vnd.ms-excel' ),
	) as $case ) {
		++$checks;
		$content = Nashenski\Admin\Export_Builder::content_type(
			array(
				'format'   => $case[0],
				'encoding' => $case[1],
			)
		);
		if ( $case[2] !== $content ) {
			fwrite( STDERR, "FAIL: {$case[0]} {$case[1]} gave '$content', wanted '{$case[2]}'\n" );
			exit( 1 );
		}
	}

	echo 'PASS: ' . $checks . " export content type assertions\n";
}
