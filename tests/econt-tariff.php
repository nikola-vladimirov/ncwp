<?php
/**
 * Offline test: the Econt tariff text parser reads the parcels table and fails safe on a changed layout.
 * Run: php tests/econt-tariff.php
 *
 * @package Nashenski_Commerce
 */

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	/**
	 * Apply filters.
	 *
	 * @param mixed $hook  Hook.
	 * @param mixed $value Value.
	 */
	function apply_filters( $hook, $value ) {
		return $value; }

	require __DIR__ . '/../app/Pricing/Price_Lists.php';
	require __DIR__ . '/../app/Pricing/Econt_Tariff.php';

	use Nashenski\Pricing\Econt_Tariff;

	$checks = 0;
	/**
	 * Expect.
	 *
	 * @param mixed $condition Condition.
	 * @param mixed $message Message.
	 */
	function expect( $condition, $message ) {
		++$GLOBALS['checks'];
		if ( ! $condition ) {
			fwrite( STDERR, 'FAIL: ' . $message . "\n" );
			exit( 1 ); }
	}

	$sample = file_get_contents( __DIR__ . '/fixtures/econt-tariff.txt' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- offline test.
	$fails  = function ( $text ) {
		return is_string( Econt_Tariff::parse( $text ) );
	};

	// 1. Good sample: the shipped Econt defaults, band for band.
	$list     = Econt_Tariff::parse( $sample );
	$defaults = \Nashenski\Pricing\Price_Lists::defaults( 'econt' );
	unset( $defaults['source'] );
	expect( is_array( $list ), 'the sample parses: ' . ( is_string( $list ) ? $list : '' ) );
	expect( array( 1, 2, 5, 10, 15, 20 ) === array_column( $list['bands'], 'to' ), 'six bands up to 1, 2, 5, 10, 15 and 20 kg' );
	expect( 7.84 === $list['bands'][3]['office'] && 7.06 === $list['bands'][3]['locker'] && 12.62 === $list['bands'][3]['address'], 'up to 10 kg: code 2 office, 10% off for the locker, code 3 address' );
	expect( '2026-10-01' === $list['valid_from'] && true === $list['vat_included'], 'valid from date and VAT included' );
	expect( $defaults == $list, 'the sample gives the shipped Econt defaults' ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- 0 and 0.0 in surcharge and cod min.

	// 2. A missing row fails safe.
	expect( $fails( str_replace( "над 5 до 10 кг 12.62€ 7.84€ 12.62€ 14.15€\n", '', $sample ) ), 'a missing band row is an error' );
	expect( $fails( str_replace( 'над 5 до 10 кг 12.62€ 7.84€ 12.62€ 14.15€', 'над 5 до 10 кг 12.62€ 7.84€ 12.62€', $sample ) ), 'a row with 3 prices is an error' );

	// 3. A changed band fails safe.
	expect( $fails( str_replace( 'над 5 до 10 кг', 'над 5 до 12 кг', $sample ) ), 'a changed band limit is an error' );
	expect( $fails( str_replace( 'над 15 до 20 кг 19.82€ 10.79€ 19.82€ 21.63€', "над 15 до 20 кг 19.82€ 10.79€ 19.82€ 21.63€\nнад 20 до 25 кг 20.82€ 11.79€ 20.82€ 22.63€", $sample ) ), 'an extra band is an error' );
	expect( $fails( str_replace( '12.62€ 7.84€ 12.62€ 14.15€', '12.62€ 12.62€ 7.84€ 14.15€', $sample ) ), 'swapped columns (a price lower than the band before) are an error' );

	// 4. Cash on delivery as % rows.
	expect( 1.65 === $list['cod']['percent'] && 0 === $list['cod']['min'], 'cash on delivery to a bank account: 1.65%' );
	expect( 1.9 === Econt_Tariff::parse( str_replace( 'в офис на Еконт в брой 3.30 % 3.30 % 1.65 %', 'в офис на Еконт в брой 3.30 % 3.30 % 1.90 %', $sample ) )['cod']['percent'], 'a new cash on delivery % is read from the last column' );
	expect( $fails( str_replace( 'в офис на Еконт в брой 3.30 % 3.30 % 1.65 %', 'в офис на Еконт в брой 3.30 % 1.65 %', $sample ) ), 'a cash on delivery row without 3 percentages is an error' );

	// 5. Over 20 kg, split over several lines.
	expect( 10.79 === $list['over']['base']['office'] && 0.43 === $list['over']['per_kg']['office'] && 0.39 === $list['over']['per_kg']['locker'], 'over 20 kg, code 2: 10.79 + 0.43 per kg, locker 0.39' );
	expect( 19.82 === $list['over']['base']['address'] && 0.52 === $list['over']['per_kg']['address'], 'over 20 kg, code 3: 19.82 + 0.52 per kg' );
	expect( $fails( preg_replace( '/10\.79€ \+ 0\.43€ за\nвсеки кг над 20 кг до\n50 кг\n/u', '', $sample ) ), 'an over 20 kg row with 3 cells is an error' );
	expect( $fails( str_replace( "19.82€ + 0.52€ за\nвсеки кг над 20 кг до\n50 кг\n10.79€ + 0.43€ за", "10.79€ + 0.43€ за\nвсеки кг над 20 кг до\n50 кг\n19.82€ + 0.52€ за", $sample ) ), 'over 20 kg cells read out of order are an error' );
	expect( is_array( Econt_Tariff::parse( str_replace( "\n", "\r\n", $sample ) ) ), 'Windows line ends are read' );

	// 6. Cyrillic: only UTF-8 text with the Bulgarian labels is read.
	expect( $fails( mb_convert_encoding( $sample, 'Windows-1251', 'UTF-8' ) ), 'Windows-1251 text is an error' );
	expect( $fails( mb_convert_encoding( $sample, 'UTF-8', 'Windows-1252' ) ), 'double-encoded (mojibake) text is an error' );
	expect( $fails( str_replace( 'Колетни пратки', 'Kолетни пратки', $sample ) ), 'a Latin K in the table title is an error' );

	echo 'PASS: ' . $checks . " Econt tariff assertions\n";
}
