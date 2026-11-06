<?php
/**
 * Class autoloader: Nashenski\Foo\Bar_Baz lives in app/Foo/class-bar-baz.php.
 *
 * @package Nashenski
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'Nashenski\\' ) ) {
			return;
		}
		$parts = explode( '\\', substr( $class_name, strlen( 'Nashenski\\' ) ) );
		$name  = array_pop( $parts );
		$file  = __DIR__ . '/' . ( $parts ? implode( '/', $parts ) . '/' : '' ) . 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);
