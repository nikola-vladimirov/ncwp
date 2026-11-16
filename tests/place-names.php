<?php
/**
 * Offline: office and locker names follow the page language. A Bulgarian page, or a list asked
 * for without a language (the order screen), keeps the couriers' text; any other language gets
 * Econt's and Speedy's English names, BOX NOW's Latin titles and Latin letters for the rest,
 * while the IDs the waybill uses stay as they are. Run: php tests/place-names.php
 *
 * @package Nashenski_Commerce
 */

// phpcs:disable WordPress.Files.FileName, Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.Security.EscapeOutput -- an offline test script: it stubs WordPress functions.

define( 'ABSPATH', __DIR__ . '/fixtures/' );

$GLOBALS['test_request'] = '';
$GLOBALS['test_locale']  = 'bg_BG';

/**
 * Stub.
 *
 * @param string $hook  Hook.
 * @param mixed  $value Value.
 * @return mixed
 */
function apply_filters( $hook, $value ) {
	return $value;
}

/**
 * Stub: the site language.
 *
 * @return string
 */
function determine_locale() {
	return $GLOBALS['test_locale'];
}

/**
 * Stub: the "locale" the checkout script sends.
 *
 * @param string $key      Key.
 * @param mixed  $fallback Fallback.
 * @return mixed
 */
function nashenski_picker_request( $key, $fallback = '' ) {
	return 'locale' === $key ? $GLOBALS['test_request'] : $fallback;
}

require dirname( __DIR__ ) . '/app/class-transliteration.php';
require dirname( __DIR__ ) . '/app/Front_End/Checkout/class-pickers.php';

use Nashenski\Front_End\Checkout\Pickers;

$failures = 0;

/**
 * Records a check.
 *
 * @param bool   $ok   Passed.
 * @param string $what What was checked.
 */
function check( $ok, $what ) {
	global $failures;
	echo ( $ok ? 'ok   ' : 'FAIL ' ), $what, "\n";
	$failures += $ok ? 0 : 1;
}

$econt  = array(
	array(
		'code'    => '1127',
		'name'    => 'София Дружба',
		'nameEn'  => 'Sofia Druzhba',
		'address' => array(
			'fullAddress'   => 'София, ж.к. Дружба, бул. Цариградско шосе №1',
			'fullAddressEn' => 'zhk Druzhba, bul. Tsarigradsko shose 1',
			'city'          => array( 'nameEn' => 'Sofia' ),
		),
	),
	array(
		'code'    => '2',
		'name'    => 'Русе Център',
		'nameEn'  => '',
		'address' => array(
			'fullAddress'   => 'Русе, ул. Александровска 1',
			'fullAddressEn' => 'Ruse ul. Aleksandrovska 1',
			'city'          => array( 'nameEn' => 'Ruse' ),
		),
	),
);
$speedy = array(
	array(
		'id'      => 63,
		'name'    => 'РУСЕ - БЪЛГАРИЯ (БУЛ.)',
		'nameEn'  => 'RUSE - BULGARIA (BUL.)',
		'address' => array( 'fullAddressString' => 'гр. Русе бул. България No 310' ),
	),
);
$boxnow = array(
	array(
		'id'           => '1840',
		'name'         => 'BOX NOW - Пазар Север, ул. Ханс Кристиан Андерсен 5, София - 24/7',
		'title'        => 'Pazar Sever, ul. H. K. A, Sofia',
		'addressLine1' => 'ул. Ханс Кристиан Андерсен 5',
		'addressLine2' => 'София',
	),
	array(
		'id'    => '7',
		'name'  => 'BOX NOW - Лидл Перник',
		'title' => 'Лидл Перник',
	),
);
$pigeon = array(
	array(
		'id'      => 26,
		'name'    => 'Айтос Васил Априлов АПС (1529)',
		'address' => 'обл. Бургас, гр. Айтос, улица ВАСИЛ АПРИЛОВ, № 16',
	),
);

// Bulgarian page and no language: the couriers' text, untouched.
$GLOBALS['test_request'] = 'bg_BG';
check( Pickers::localize_places( $econt, 'econt' ) === $econt, 'Bulgarian page keeps Econt as sent' );
check( Pickers::localize_places( $pigeon, 'pigeon' ) === $pigeon, 'Bulgarian page keeps Pigeon as sent' );
$GLOBALS['test_request'] = '';
$GLOBALS['test_locale']  = 'en_US';
check( Pickers::localize_places( $boxnow, 'boxnow' ) === $boxnow, 'no language sent (order screen) keeps BOX NOW as sent, whatever the user language' );

// English page.
$GLOBALS['test_request'] = 'en_US';
$e                       = Pickers::localize_places( $econt, 'econt' );
check( 'Sofia Druzhba' === $e[0]['name'], 'Econt English name' );
check( 'Sofia, zhk Druzhba, bul. Tsarigradsko shose 1' === $e[0]['address']['fullAddress'], 'Econt English address gets the town in front' );
check( 'Ruse ul. Aleksandrovska 1' === $e[1]['address']['fullAddress'], 'Econt English address that names the town is not doubled' );
check( 'Ruse Tsentar' === $e[1]['name'], 'Econt office without an English name is transliterated' );
check( '1127' === $e[0]['code'], 'Econt office code (waybill) unchanged' );

$sp = Pickers::localize_places( $speedy, 'speedy' );
check( 'RUSE - BULGARIA (BUL.)' === $sp[0]['name'], 'Speedy English name' );
check( 'gr. Ruse bul. Bulgaria No 310' === $sp[0]['address']['fullAddressString'], 'Speedy address in Latin' );
check( 63 === $sp[0]['id'], 'Speedy office id unchanged' );

$b = Pickers::localize_places( $boxnow, 'boxnow' );
check( 'Pazar Sever, ul. H. K. A, Sofia' === $b[0]['name'], 'BOX NOW Latin title' );
check( 'ul. Hans Kristian Andersen 5' === $b[0]['addressLine1'] && 'Sofia' === $b[0]['addressLine2'], 'BOX NOW address lines in Latin' );
check( 'BOX NOW - Lidl Pernik' === $b[1]['name'], 'BOX NOW Cyrillic title falls back to the name in Latin' );

$p = Pickers::localize_places( $pigeon, 'pigeon' );
check( 'Aytos Vasil Aprilov APS (1529)' === $p[0]['name'], 'Pigeon name in Latin' );
check( false === \Nashenski\Transliteration::has_cyrillic( $p[0]['address'] ), 'Pigeon address in Latin' );
check( 26 === $p[0]['id'], 'Pigeon id unchanged' );

// Other languages count as non-Bulgarian; any bg locale as Bulgarian.
check( Pickers::latin_names( 'de_DE' ) && ! Pickers::latin_names( 'bg_BG' ) && ! Pickers::latin_names( 'bg' ), 'latin_names by locale' );
check( 'nope' === Pickers::localize_places( 'nope', 'econt' ), 'a list that is not an array passes through' );

echo $failures ? "\n$failures FAILED\n" : "\nall passed\n";
exit( $failures ? 1 : 0 );
