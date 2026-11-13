<?php // phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, WordPress.Security.EscapeOutput -- offline CLI test script: no WordPress, so stubs, unused stub parameters and plain-text output are on purpose.
/**
 * Offline: the shipping option card text (cards and side by side layouts). The courier name stays
 * for screen readers, one short price line, and the long text once under the options
 * (data-nashenski-note). The plain layout keeps the old one-line label. Run: php tests/rate-card-label.php
 *
 * @package Nashenski_Commerce
 */

define( 'ABSPATH', __DIR__ . '/fixtures/' );
$layout = 'buttons';
/**
 * Translation stub.
 *
 * @param string $text Text.
 * @return string
 */
function __( $text ) {
	return $text; }
/**
 * Translation stub.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html__( $text ) {
	return $text; }
/**
 * Translation stub.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html_x( $text ) {
	return $text; }
/**
 * Translation stub.
 *
 * @param string $text Text.
 * @return string
 */
function esc_attr__( $text ) {
	return $text; }
/**
 * Escaping stub.
 *
 * @param string $text Text.
 * @return string
 */
function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES ); }
/**
 * Escaping stub.
 *
 * @param string $text Text.
 * @return string
 */
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES ); }
/**
 * Kses stub.
 *
 * @param string $html HTML.
 * @return string
 */
function wp_kses_post( $html ) {
	return $html; }
/**
 * Tag stripping stub.
 *
 * @param string $html HTML.
 * @return string
 */
function wp_strip_all_tags( $html ) {
	return strip_tags( $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- stub of the WordPress function.
}
/**
 * Price stub.
 *
 * @param mixed $amount Amount.
 * @param array $args   Arguments: the currency.
 * @return string
 */
function wc_price( $amount, $args = array() ) {
	return '<bdi>' . number_format( (float) $amount, 2, ',', '' ) . ' ' . ( empty( $args['currency'] ) ? '€' : $args['currency'] ) . '</bdi>'; }
/**
 * Hook stubs.
 */
function add_action() {}
/**
 * Hook stubs.
 */
function add_filter() {}
/**
 * Option stub: the layout.
 *
 * @param string $group Group.
 * @param string $key   Key.
 * @return string
 */
function nashenski_get_option( $group, $key ) {
	return 'layout' === $key ? $GLOBALS['layout'] : ''; }
require __DIR__ . '/../app/class-plugin.php';
require __DIR__ . '/../app/Shipping/class-courier-fee.php';
require __DIR__ . '/../app/Front_End/Checkout/class-appearance.php';
require __DIR__ . '/../app/Front_End/Checkout/class-pickers.php';

use Nashenski\Front_End\Checkout\Pickers;

/**
 * Shipping rate stub.
 */
class FakeRate {
	/**
	 * Constructor.
	 *
	 * @param float $cost Cost.
	 * @param array $meta Meta.
	 */
	public function __construct( $cost, $meta = array() ) {
		$this->cost = $cost;
		$this->meta = $meta + array( 'validated' => true ); }
	/**
	 * Cost.
	 *
	 * @var float
	 */
	public $cost;
	/**
	 * Meta.
	 *
	 * @var array
	 */
	public $meta;
	/**
	 * Rate ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return 'nashenski_econt:3'; }
	/**
	 * Label.
	 *
	 * @return string
	 */
	public function get_label() {
		return 'Econt'; }
	/**
	 * Cost.
	 *
	 * @return float
	 */
	public function get_cost() {
		return $this->cost; }
	/**
	 * Taxes.
	 *
	 * @return array
	 */
	public function get_taxes() {
		return array(); }
	/**
	 * Meta.
	 *
	 * @return array
	 */
	public function get_meta_data() {
		return $this->meta; }
}

$checks = 0;
/**
 * Assertion: throws when the condition fails.
 *
 * @param bool   $condition Condition.
 * @param string $message   Failure message.
 * @throws RuntimeException When the condition is false.
 */
function expect( $condition, $message ) {
	++$GLOBALS['checks'];
	if ( ! $condition ) {
		throw new RuntimeException( $message ); }
}

$label = Pickers::full_label( 'Econt', new FakeRate( 0, array( 'nashenski_pending' => 'yes' ) ) );
expect( false !== strpos( $label, '<span class="nashenski-rate-courier">Econt</span>' ) && false === strpos( $label, 'Econt:' ), 'The courier name stays for screen readers, no dangling colon' );
expect( false !== strpos( $label, '>Price after address</span>' ) && false !== strpos( $label, 'data-nashenski-note="Choose your office, locker or address to see the delivery price."' ), 'Not priced yet: a short card text, the hint goes under the options' );
expect( false === strpos( $label, 'Complete the fields to calculate' ), 'Not priced yet: no long text in the card' );

$label = Pickers::full_label( 'Econt', new FakeRate( 0, array( 'nashenski_courier_fee' => '3.52' ) ) );
expect( false !== strpos( $label, '<bdi>3,52 €</bdi> <span class="nashenski-rate-note">to the courier</span>' ), 'Customer pays: the price and a short "to the courier" in the card' );
expect( false !== strpos( $label, 'data-nashenski-note="3,52 €, paid to the courier on delivery, not included in the total"' ), 'Customer pays: the full sentence once under the options' );
$label = Pickers::full_label(
	'Econt',
	new FakeRate(
		0,
		array(
			'nashenski_courier_fee'   => '3.52',
			'_nashenski_fee_currency' => 'BGN',
		)
	)
);
expect( false !== strpos( $label, '<bdi>3,52 BGN</bdi>' ) && false !== strpos( $label, 'data-nashenski-note="3,52 BGN, paid' ), 'R7: the fee prints in the currency it was calculated in (the store\'s), not the active one' );

$label = Pickers::full_label( 'Econt', new FakeRate( 0, array( 'free_shipping' => true ) ) );
expect( false !== strpos( $label, 'nashenski-rate-free">Free</span>' ) && false === strpos( $label, 'data-nashenski-note' ), 'Free shipping: "Free", no note' );

$label = Pickers::full_label( 'Econt: <bdi>3,44 €</bdi> <small class="tax_label">(incl. VAT)</small>', new FakeRate( 3.44 ) );
expect( '<span class="nashenski-rate-courier">Econt</span><span class="nashenski-rate-price"><bdi>3,44 €</bdi> <small class="tax_label">(incl. VAT)</small></span>' === $label, 'Shop collects: the price WooCommerce formatted, tax label kept' );
$label = Pickers::full_label( 'Econt', new FakeRate( 3.44 ) );
expect( false !== strpos( $label, 'nashenski-rate-price"><bdi>3,44 €</bdi>' ), 'A label without the price (page builder): the price is added' );

expect(
	'Flat rate: 5 €' === Pickers::full_label(
		'Flat rate: 5 €',
		new class() {
			/**
			 * Rate ID.
			 *
			 * @return string
			 */
			public function get_id() {
				return 'flat_rate:2'; }
			/**
			 * Meta.
			 *
			 * @return array
			 */
			public function get_meta_data() {
				return array(); }
		}
	),
	'Other shipping methods keep their label'
);

$layout = 'plain';
$label  = Pickers::full_label( 'Econt', new FakeRate( 0, array( 'nashenski_pending' => 'yes' ) ) );
expect( 'Econt: <span class="nashenski-rate-pending">Complete the fields to calculate</span>' === $label, 'Plain layout: the one-line label as before' );

echo 'PASS: ' . $checks . " shipping option card assertions\n";
