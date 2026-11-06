<?php
/** Offline order courier box regressions. Run: php tests/order-courier-display.php */
namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	function __( $text ) {
		return $text; }
	function esc_html__( $text ) {
		return $text; }
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES ); }
	class WC_Order {
		public $items = array();
		public function get_items( $type ) {
			return $this->items; }
	}
	class WC_Order_Refund {
		public function get_items( $type ) {
			return array(); }
	}
	class Line {
		public $id, $meta;
		public function __construct( $id, $meta = array() ) {
			$this->id   = $id;
			$this->meta = $meta; }
		public function get_method_id() {
			return $this->id; }
		public function get_meta( $key ) {
			return $this->meta[ $key ] ?? ''; }
	}

	require __DIR__ . '/../vendor/autoload.php';
	require __DIR__ . '/../app/functions.php';

	$checks = 0;
	function expect( $condition, $message ) {
		++$GLOBALS['checks'];
		if ( ! $condition ) {
			throw new RuntimeException( $message ); }
	}
	function order( array $lines ) {
		$o        = new WC_Order();
		$o->items = $lines;
		return $o; }
	function summary( array $lines ) {
		return \Nashenski\Admin\Order\Courier_Info::summary( order( $lines ) ); }
	function html( $order ) {
		$GLOBALS['theorder'] = $order;
		ob_start();
		\Nashenski\Admin\Order\Courier_Info::render();
		return ob_get_clean();
	}

	foreach ( array(
		'econt'  => 'Econt',
		'speedy' => 'Speedy',
		'boxnow' => 'BOX NOW',
		'pigeon' => 'Pigeon Express',
	) as $slug => $name ) {
		expect( summary( array( new Line( "nashenski_$slug", array( 'delivery_type' => 'office' ) ) ) )['courier'] === $name, "Courier name of $slug" );
	}

	$types = array(
		'office'  => 'To an office',
		'automat' => 'To a parcel locker',
		'locker'  => 'To a parcel locker',
		'address' => 'To an address',
	);
	foreach ( array( 'econt', 'speedy', 'pigeon' ) as $slug ) {
		foreach ( $types as $type => $label ) {
			expect( summary( array( new Line( "nashenski_$slug", array( 'delivery_type' => $type ) ) ) )['type'] === $label, "Type $type of $slug" );
		}
	}
	expect( '' === summary( array( new Line( 'nashenski_econt' ) ) )['type'], 'A line without a delivery type shows no type' );
	foreach ( array( 'office', 'address', '' ) as $stored ) {
		expect( 'To a parcel locker' === summary( array( new Line( 'nashenski_boxnow', array( 'delivery_type' => $stored ) ) ) )['type'], "BOX NOW is lockers only, stored '$stored'" );
	}

	$info = summary(
		array(
			new Line(
				'nashenski_speedy',
				array(
					'delivery_type' => 'office',
					'cookie_data'   => array( 'selectedLabel' => 'Sofia, Mladost 1' ),
				)
			),
		)
	);
	expect( 'Sofia, Mladost 1' === $info['place'], 'Chosen office is shown' );
	expect( '' === summary( array( new Line( 'nashenski_speedy', array( 'delivery_type' => 'office' ) ) ) )['place'], 'No chosen office stored shows no place' );

	expect( array() === summary( array() ), 'No shipping line gives no courier' );
	expect( array() === summary( array( new Line( 'flat_rate' ), new Line( 'free_shipping' ) ) ), 'Other shipping methods give no courier' );
	expect( 'Speedy' === summary( array( new Line( 'flat_rate' ), new Line( 'nashenski_speedy', array( 'delivery_type' => 'address' ) ) ) )['courier'], 'The courier line is found after another line' );

	expect( '<p>No courier</p>' === html( order( array() ) ), 'Order without courier says No courier' );
	$out = html(
		order(
			array(
				new Line(
					'nashenski_econt',
					array(
						'delivery_type' => 'automat',
						'cookie_data'   => array( 'selectedLabel' => '<b>Locker "1"</b>' ),
					)
				),
			)
		)
	);
	expect( '<p><strong>Econt</strong><br>To a parcel locker<br>&lt;b&gt;Locker &quot;1&quot;&lt;/b&gt;</p>' === $out, 'Box text is escaped: ' . $out );

	echo 'PASS: ' . $checks . " order courier display assertions\n";
}
