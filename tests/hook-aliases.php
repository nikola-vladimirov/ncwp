<?php
/**
 * Offline check: every public hook fires under its underscore name and keeps its old slash name
 * as a deprecated alias (0.21.0). Reads the call sites; needs no WordPress.
 *
 * Run: php tests/hook-aliases.php
 *
 * @package Nashenski_Commerce
 */

$root  = dirname( __DIR__ );
$files = array( $root . '/nashenski-commerce.php' );
foreach ( array( 'app', 'templates' ) as $dir ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$root/$dir", FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( 'php' === $file->getExtension() ) {
			$files[] = $file->getPathname();
		}
	}
}

$fail  = array();
$sites = 0;
$old   = array();
foreach ( $files as $file_path ) {
	$text = file_get_contents( $file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- offline test.
	$rel  = substr( $file_path, strlen( $root ) + 1 );
	// No hook may still fire under a slash name alone.
	if ( preg_match( '/\b(apply_filters|do_action)(_ref_array)?\(\s*[\'"]nashenski\//', $text ) ) {
		$fail[] = "$rel fires a slash hook name without the alias";
	}
	// Each alias call: new name first, then the old name it replaces.
	preg_match_all( '/Hooks::(filter|action)\(\s*([^,]+?),\s*([^,]+?)\s*[,)]/', $text, $calls, PREG_SET_ORDER );
	foreach ( $calls as $call ) {
		++$sites;
		$old[ $call[3] ] = true;
		if ( str_replace( '/', '_', $call[3] ) !== $call[2] || false === strpos( $call[3], 'nashenski/' ) ) {
			$fail[] = "$rel: {$call[2]} does not alias {$call[3]}";
		}
	}
}

// The helper is where the old names run, through the WordPress deprecated-hook functions.
$helper = file_get_contents( "$root/app/class-hooks.php" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- offline test.
foreach ( array( 'apply_filters_deprecated( $old', 'do_action_deprecated( $old' ) as $needle ) {
	if ( false === strpos( $helper, $needle ) ) {
		$fail[] = "app/class-hooks.php lacks $needle";
	}
}

// 0.21.0 renamed 165 hook names at 189 call sites; fewer means an old name lost its alias.
if ( count( $old ) < 165 || $sites < 189 ) {
	$fail[] = 'only ' . count( $old ) . " old names at $sites call sites keep their alias (expected 165 at 189)";
}

echo $fail ? 'FAIL: ' . implode( "\nFAIL: ", $fail ) . "\n" : 'PASS: ' . count( $old ) . " old hook names at $sites call sites run as deprecated aliases\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
exit( $fail ? 1 : 0 );
