<?php
/**
 * Offline regressions: php -d memory_limit=64M tests/pigeon-resources.php
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Front_End\Checkout {
	/**
	 * Pickers.
	 */
	class Pickers {
		/**
		 * Connected.
		 *
		 * @param mixed $name Name.
		 * @return mixed Result.
		 */
		public static function connected( $name ) {
			return $GLOBALS['connected']; }
	}
}

namespace Nashenski\Pricing {
	/**
	 * Price lists.
	 */
	class Price_Lists {
		/**
		 * Get.
		 *
		 * @param mixed $courier Courier.
		 * @return array Result.
		 */
		public static function get( $courier ) {
			++$GLOBALS['public_quotes'];
			return array(
				'bands'        => array(
					array(
						'to'      => 1000000,
						'office'  => 9,
						'locker'  => 9,
						'address' => 9,
					),
				),
				'surcharge'    => 0,
				'vat_included' => true,
				'cod'          => array( 'percent' => 0 ),
			);
		}
	}
}

namespace {
	define( 'ABSPATH', __DIR__ . '/fixtures/' );
	/**
	 * Wc shipping method.
	 */
	class WC_Shipping_Method {
		/**
		 * Title.
		 *
		 * @var string
		 */
		public $title = 'Pigeon';
		/**
		 * Id.
		 *
		 * @var string
		 */
		public $id = 'nashenski_pigeon';
		/**
		 * Instance id.
		 *
		 * @var int
		 */
		public $instance_id = 1;
		/**
		 * Rates.
		 *
		 * @var array
		 */
		public $rates = array();
		/**
		 * Add rate.
		 *
		 * @param mixed $rate Rate.
		 */
		public function add_rate( $rate ) {
			$this->rates[] = $rate; }
	}
	require __DIR__ . '/../vendor/autoload.php';
	set_error_handler(
		function ( $severity, $message, $file, $line ) {
			throw new \ErrorException( $message, 0, $severity, $file, $line );
		}
	);

	use Nashenski\Shipping\Packer\Packing_Limit_Exceeded;
	use Nashenski\Shipping\Packer\Packing_Budget;
	use Nashenski\Shipping\Packer\APSPackage;
	use Nashenski\Shipping\Pigeon\PackageBoxes;
	use Nashenski\Shipping\Pigeon\APSBoxes;

	/**
	 * Get option.
	 *
	 * @param mixed  $name Name.
	 * @param string $default Default.
	 * @return string Result.
	 */
	function get_option( $name, $default = '' ) {
		return 'woocommerce_dimension_unit' === $name ? 'cm' : $default; }
	/**
	 * Absint.
	 *
	 * @param mixed $value Value.
	 * @return mixed Result.
	 */
	function absint( $value ) {
		return abs( (int) $value ); }
	/**
	 * Nashenski normalize text for label.
	 *
	 * @param mixed $value Value.
	 * @param int   $length Length.
	 * @return mixed Result.
	 */
	function nashenski_normalize_text_for_label( $value, $length = 0 ) {
		return $value; }
	/**
	 * Wc get dimension.
	 *
	 * @param mixed  $value Value.
	 * @param mixed  $to To.
	 * @param string $from From.
	 * @return mixed Result.
	 */
	function wc_get_dimension( $value, $to, $from = 'cm' ) {
		return (float) $value * ( $to === $from ? 1 : ( 'mm' === $to ? 10 : 0.1 ) ); }
	/**
	 * Wc get weight.
	 *
	 * @param mixed $value Value.
	 * @param mixed $to To.
	 * @return mixed Result.
	 */
	function wc_get_weight( $value, $to ) {
		return (float) $value; }
	/**
	 * Nashenski get option.
	 *
	 * @param mixed $group Group.
	 * @param mixed $key Key.
	 * @return mixed Result.
	 */
	function nashenski_get_option( $group, $key ) {
		return $GLOBALS['options'][ $group . '/' . $key ] ?? ''; }
	/**
	 * Wc string to bool.
	 *
	 * @param mixed $value Value.
	 * @return mixed Result.
	 */
	function wc_string_to_bool( $value ) {
		return in_array( $value, array( true, 1, '1', 'yes' ), true ); }
	/**
	 * .
	 *
	 * @param mixed  $value Value.
	 * @param string $domain Domain.
	 * @return mixed Result.
	 */
	function __( $value, $domain = '' ) {
		return $value; }
	/**
	 * Esc html.
	 *
	 * @param mixed $value Value.
	 * @return mixed Result.
	 */
	function esc_html( $value ) {
		return $value; }
	/**
	 * Nashenski courier cookie.
	 *
	 * @param mixed $key Key.
	 * @return mixed Result.
	 */
	function nashenski_courier_cookie( $key ) {
		return $GLOBALS['cookie']; }
	/**
	 * Nashenski has free shipping coupon in cart.
	 *
	 * @return mixed Result.
	 */
	function nashenski_has_free_shipping_coupon_in_cart() {
		return $GLOBALS['free_coupon']; }
	/**
	 * Nashenski get package total.
	 *
	 * @return mixed Result.
	 */
	function nashenski_get_package_total() {
		return $GLOBALS['package_total'] ?? 100; }
	/**
	 * Nashenski tax based price.
	 *
	 * @param mixed $value Value.
	 * @param int   $tax Tax.
	 * @return mixed Result.
	 */
	function nashenski_tax_based_price( $value, $tax = 20 ) {
		return $value; }
	/**
	 * Wc tax enabled.
	 *
	 * @return bool Result.
	 */
	function wc_tax_enabled() {
		return false; }
	/**
	 * Do action.
	 *
	 * @param mixed   $name Name.
	 * @param mixed[] ...$args Args.
	 */
	function do_action( $name, ...$args ) {}
	/**
	 * Wc.
	 *
	 * @return mixed Result.
	 */
	function WC() {
		return $GLOBALS['wc']; }
	/**
	 * Apply filters.
	 *
	 * @param mixed   $name Name.
	 * @param mixed   $value Value.
	 * @param mixed[] ...$args Args.
	 * @return mixed Result.
	 */
	function apply_filters( $name, $value, ...$args ) {
		foreach ( $GLOBALS['filters'][ $name ] ?? array() as $callback ) {
			$value = $callback( $value, ...$args ); }
		return $value;
	}
	/**
	 * Wc product.
	 */
	class WC_Product {
		/**
		 * Reads.
		 *
		 * @var int
		 */
		public $reads = 0;
		/**
		 * Read limit.
		 *
		 * @var mixed
		 */
		public $read_limit = PHP_INT_MAX;
		/**
		 * Weight.
		 *
		 * @var mixed
		 */
		private $weight;

		/**
		 * Dimensions.
		 *
		 * @var mixed
		 */
		private $dimensions;
		/**
		 * Constructor.
		 *
		 * @param int   $weight Weight.
		 * @param array $dimensions Dimensions.
		 */
		public function __construct( $weight = 0, array $dimensions = array( 0, 0, 0 ) ) {
			$this->weight     = $weight;
			$this->dimensions = $dimensions; }
		/**
		 * Get name.
		 *
		 * @return string Result.
		 */
		public function get_name() {
			return 'Product'; }
		/**
		 * Get length.
		 *
		 * @return mixed Result.
		 */
		public function get_length() {
			return $this->dimensions[0]; }
		/**
		 * Get width.
		 *
		 * @return mixed Result.
		 */
		public function get_width() {
			return $this->dimensions[1]; }
		/**
		 * Get height.
		 *
		 * @return mixed Result.
		 */
		public function get_height() {
			return $this->dimensions[2]; }
		/**
		 * Get weight.
		 *
		 * @return mixed Result.
		 * @throws RuntimeException When the product is read more often than the test allows.
		 */
		public function get_weight() {
			if ( ++$this->reads > $this->read_limit ) {
				throw new RuntimeException( 'Test stopped unsafe per-unit expansion before resource exhaustion.' ); }
			return $this->weight;
		}
	}
	/**
	 * Rejected.
	 *
	 * @param mixed $callback Callback.
	 * @return bool Result.
	 */
	function rejected( $callback ) {
		try {
			$callback();
		} catch ( Packing_Limit_Exceeded $e ) {
			return true; }
		return false;
	}
	/**
	 * Invoke.
	 *
	 * @param mixed   $class Class.
	 * @param mixed   $name Name.
	 * @param mixed[] ...$args Args.
	 * @return mixed Result.
	 */
	function invoke( $class, $name, ...$args ) {
		$r = new ReflectionMethod( $class, $name );
		if ( PHP_VERSION_ID < 80100 ) {
			$r->setAccessible( true ); }
		return $r->invoke( is_object( $class ) ? $class : null, ...$args );
	}
	$checks = 0;
	/**
	 * Expect.
	 *
	 * @param mixed $condition Condition.
	 * @param mixed $message Message.
	 * @throws RuntimeException When the check fails.
	 */
	function expect( $condition, $message ) {
		++$GLOBALS['checks'];
		if ( ! $condition ) {
			throw new RuntimeException( $message ); } }
	$product             = new WC_Product();
	$product->read_limit = 5;
	$package             = array(
		'contents' => array(
			array(
				'quantity' => 2000,
				'data'     => $product,
			),
		),
	);
	if ( function_exists( 'memory_reset_peak_usage' ) ) {
		memory_reset_peak_usage(); }
	$before = memory_get_usage();
	expect(
		rejected(
			function () use ( $package ) {
				invoke( PackageBoxes::class, 'get_product_items', $package );
			}
		),
		'Excessive quantities must be rejected before expansion'
	);
	expect( memory_get_peak_usage() - $before < 512 * 1024, 'Unit preflight must not allocate proportional to quantity' );

	/**
	 * Pigeonsession.
	 */
	class PigeonSession {
		/**
		 * Data.
		 *
		 * @var array
		 */
		public $data = array(
			'chosen_shipping_methods' => array( 'nashenski_pigeon:1' ),
			'customer'                => array(),
		);
		/**
		 * Get.
		 *
		 * @param mixed $key Key.
		 * @return mixed Result.
		 */
		public function get( $key ) {
			return $this->data[ $key ] ?? null; }
		/**
		 * Set.
		 *
		 * @param mixed $key Key.
		 * @param mixed $value Value.
		 */
		public function set( $key, $value ) {
			$this->data[ $key ] = $value; }
	}
	/**
	 * Pigeoncourier.
	 */
	class PigeonCourier {
		const CALCULATE_ENDPOINT = '/calculate';
		/**
		 * Api call.
		 *
		 * @param mixed $endpoint Endpoint.
		 * @param mixed $body Body.
		 * @param mixed $method Method.
		 * @return mixed Result.
		 */
		public function api_call( $endpoint, $body, $method ) {
			++$GLOBALS['api_calls'];
			$GLOBALS['last_request'] = $body;
			return $GLOBALS['api_error'] ? array(
				'success' => false,
				'errors'  => array( array( 'Offline fixture' ) ),
			) : array( 'data' => array( 'total_price' => 12 ) );
		}
	}
	/**
	 * Pigeonmethod.
	 */
	class PigeonMethod extends \Nashenski\Shipping\Pigeon\Method {
		/**
		 * Constructor.
		 *
		 * @param string $type Type.
		 */
		public function __construct( $type = 'office' ) {
			$this->delivery_type = $type;
			$this->test          = 'no';
			$this->container     = array( 'client.pigeon' => new PigeonCourier() ); }
	}
	/**
	 * Fixture.
	 *
	 * @param bool $auto Auto.
	 */
	function fixture( $auto = false ) {
		$GLOBALS['filters']                     = array();
		$GLOBALS['options']                     = $GLOBALS['filters'];
		$GLOBALS['options']['pigeon/auto_size'] = $auto ? 'yes' : 'no';
		$GLOBALS['connected']                   = true;
		$GLOBALS['free_coupon']                 = false;
		$GLOBALS['api_error']                   = $GLOBALS['free_coupon'];
		$GLOBALS['public_quotes']               = 0;
		$GLOBALS['api_calls']                   = $GLOBALS['public_quotes'];
		$GLOBALS['last_request']                = null;
		$GLOBALS['cookie']                      = array(
			'type'           => 'office',
			'selectedOffice' => 1,
			'selectedLocker' => 1,
			'payment'        => 'card',
			'country'        => 'BG',
			'receiver'       => 'Test',
			'phone'          => '0888000000',
			'state'          => '',
		);
		$GLOBALS['wc']                          = (object) array( 'session' => new PigeonSession() );
		if ( $auto ) {
			$GLOBALS['filters']['nashenski/pigeon/calculate_label'][] = array( APSBoxes::class, 'calculate_label' ); }
		$GLOBALS['filters']['nashenski/pigeon/calculate_label'][] = array( PackageBoxes::class, 'calculate_label' );
		$GLOBALS['filters']['nashenski/pigeon/calculate_label'][] = array( 'Nashenski\\Admin\\Pigeon', 'set_min_package_weight' );
	}
	/**
	 * Package.
	 *
	 * @param mixed $quantity Quantity.
	 * @param int   $weight Weight.
	 * @param array $dimensions Dimensions.
	 * @return array Result.
	 */
	function package( $quantity, $weight = 1, array $dimensions = array( 1, 1, 1 ) ) {
		return array(
			'contents'    => array(
				array(
					'quantity' => $quantity,
					'data'     => new WC_Product( $weight, $dimensions ),
				),
			),
			'destination' => array( 'country' => 'BG' ),
		);
	}

	foreach ( array( 'office', 'address' ) as $type ) {
		fixture();
		$cookie['type'] = $type;
		$m              = new PigeonMethod( $type );
		$m->calculate_shipping( package( 3, 0.5 ) );
		expect( 1 === count( $m->rates ) && 12 === $m->rates[0]['cost'], 'Normal office/address quote must retain its price' );
		expect( 1 === count( $last_request['packages'] ) && 1.5 === $last_request['packages'][0]['weight'], 'Normal package weight and count must remain unchanged' );
		expect(
			array(
				array(
					'description' => 'Product',
					'quantity'    => 3,
				),
			) === $last_request['inventory_items'],
			'Inventory quantities must not be truncated'
		);
	}

	foreach ( array( false, true ) as $disconnected ) {
		fixture();
		$connected = ! $disconnected;
		$api_error = ! $disconnected;
		$m         = new PigeonMethod();
		$m->calculate_shipping( package( 1 ) );
		expect( 1 === count( $m->rates ) && 9.0 === $m->rates[0]['cost'] && 1 === $public_quotes, 'Ordinary login/API errors must retain public-price fallback' );
	}

	foreach ( array( false, true ) as $auto ) {
		foreach ( array( false, true ) as $connected_fixture ) {
			foreach ( array( 'office', 'address' ) as $type ) {
				fixture( $auto );
				$connected      = $connected_fixture;
				$cookie['type'] = $type;
				$filters['nashenski/pigeon/rate/disable_packages'][] = function () {
					return false;
				};
				$m                = new PigeonMethod( $type );
				$m->fixed_price   = 5;
				$m->free_shipping = true;
				$free_coupon      = true;
				$wc->session->set( 'nashenski-pigeon-label', array( 'stale' => true ) );
				if ( function_exists( 'memory_reset_peak_usage' ) ) {
					memory_reset_peak_usage(); }
				$memory = memory_get_usage();
				$start  = microtime( true );
				$m->calculate_shipping( package( '1e300', 0 ) );
				expect( ! $m->rates && ! $api_calls && ! $public_quotes, 'Huge unweighted quantities must not reach rate, client or any price fallback' );
				expect( null === $wc->session->get( 'nashenski-pigeon-label' ), 'Rejection must clear stale session labels' );
				expect( memory_get_peak_usage() - $memory < 2 * 1024 * 1024 && microtime( true ) - $start < 2, 'Rejection must not amplify quantity into memory or CPU work' );
				expect(
					rejected(
						function () use ( $m ) {
							$m->calculate_shipping_price_from_api();
						}
					),
					'Direct API helper must retain rejected state, including while disconnected'
				);
				$m->fixed_price   = 0;
				$m->free_shipping = false;
				$free_coupon      = false;
				$m->calculate_shipping( package( 1 ) );
				expect( 1 === count( $m->rates ), 'A normal subsequent calculation must reset rejected state' );
			}
		}
	}

	fixture();
	$cookie                                       = array();
	$wc->session->data['chosen_shipping_methods'] = array();
	$m = new PigeonMethod();
	$m->calculate_shipping( package( PHP_INT_MAX, 0 ) );
	expect( ! $m->rates, 'Preflight must reject even before destination selection' );

	fixture( true );
	$cookie['type'] = 'locker';
	$m              = new PigeonMethod( 'locker' );
	$m->calculate_shipping( package( 3, 0.5 ) );
	expect( 1 === count( $m->rates ) && 1.5 === $last_request['packages'][0]['weight'], 'Normal locker quote must retain weight' );
	expect( array( 44.0, 61.0, 8.0 ) === array( $last_request['packages'][0]['width'], $last_request['packages'][0]['length'], $last_request['packages'][0]['height'] ), 'Normal locker dimensions must retain its chosen box' );
	$p = package( 2, 0.1, array( 60, 40, 30 ) );
	expect( APSBoxes::package_fits_largest_locker( $p, new Packing_Budget() ), 'Early locker eligibility must remain a per-product fit test' );
	fixture( true );
	$cookie['type'] = 'locker';
	$m              = new PigeonMethod( 'locker' );
	$m->calculate_shipping( $p );
	expect( 1 === count( $m->rates ) && 2 === count( $last_request['packages'] ), 'Locker sizing must retain legitimate multi-box output' );

	fixture();
	$cookie['type'] = 'locker';
	$m              = new PigeonMethod( 'locker' );
	$m->calculate_shipping( package( 1000000, 0 ) );
	expect( 1 === count( $m->rates ) && 1000000 === $last_request['inventory_items'][0]['quantity'], 'No blanket quantity cap may be added to non-expanding locker calculation' );

	fixture( true );
	$cookie['type'] = 'locker';
	$m              = new PigeonMethod( 'locker' );
	$m->calculate_shipping( package( PHP_INT_MAX, 0 ) );
	expect( ! $m->rates && ! $api_calls && ! $public_quotes, 'Automatic locker sizing must reject before expansion' );

	foreach ( array( 'office', 'locker' ) as $type ) {
		fixture( true );
		$cookie['type']  = $type;
		$p               = package( 600, 0.001 );
		$p['contents'][] = $p['contents'][0];
		$m               = new PigeonMethod( $type );
		$m->calculate_shipping( $p );
		expect( ! $m->rates && ! $api_calls && ! $public_quotes, 'Expanded-unit limits must be cumulative across lines and locker paths' );
	}

	fixture();
	$normal = invoke( PackageBoxes::class, 'split_weight_into_packages', 65 );
	expect( array( 30.0, 30.0, 5.0 ) === array_column( $normal, 'weight' ), 'Weight fallback must retain 30/30/5 splitting' );
	expect( array( 30.0, 30.0 ) === array_column( invoke( PackageBoxes::class, 'split_weight_into_packages', 60 ), 'weight' ), 'Exact multiples must not add an empty trailing parcel' );
	expect( array( 0.1 ) === array_column( invoke( PackageBoxes::class, 'split_weight_into_packages', 0 ), 'weight' ), 'Weight fallback must retain its minimum weight' );
	foreach ( array( INF, NAN, 1e300, '1e999', 'invalid' ) as $weight ) {
		expect(
			rejected(
				function () use ( $weight ) {
					invoke( PackageBoxes::class, 'split_weight_into_packages', $weight );
				}
			),
			'Nonfinite/enormous fallback weights must be rejected before splitting'
		);
	}

	fixture();
	$filters['nashenski/pigeon/label/weight'][] = function () {
		return INF;
	};
	$m = new PigeonMethod();
	$m->calculate_shipping( package( 1, 0 ) );
	expect( ! $m->rates && ! $api_calls && ! $public_quotes, 'Nonfinite fallback weights must fail before quote fallback' );
	fixture();
	$filters['nashenski/pigeon/rate/disable_packages'][] = function () {
		return false;
	};
	$m = new PigeonMethod();
	$m->calculate_shipping( package( 1, 40000, array( 300, 300, 300 ) ) );
	expect( ! $m->rates && ! $api_calls, 'Bypassing the commercial weight check must not remove the absolute split ceiling' );
	fixture();
	$m = new PigeonMethod();
	$m->calculate_shipping( package( 1, 65, array( 300, 300, 300 ) ) );
	expect( 1 === count( $m->rates ) && array( 30.0, 30.0, 5.0 ) === array_column( $last_request['packages'], 'weight' ), 'Ordinary cannot-fit failures must retain weight fallback' );

	fixture();
	$m          = new PigeonMethod();
	$m->package = package( PHP_INT_MAX, 0 );
	expect(
		rejected(
			function () use ( $m ) {
				PackageBoxes::calculate_label( array(), $m );
			}
		),
		'Direct package filter must enforce preflight and propagate resource rejection'
	);
	$m          = new PigeonMethod( 'locker' );
	$m->package = package( PHP_INT_MAX, 0 );
	expect(
		rejected(
			function () use ( $m ) {
				APSBoxes::calculate_label( array(), $m );
			}
		),
		'Direct locker filter must propagate resource rejection'
	);
	$m = (object) array(
		'delivery_type' => 'office',
		'package'       => package( PHP_INT_MAX, 0 ),
	);
	expect(
		rejected(
			function () use ( $m ) {
				PackageBoxes::calculate_label( array(), $m );
			}
		),
		'Standalone package filter must obtain a bounded budget'
	);

	fixture();
	$p          = package( 3, 0.5 );
	$m          = new PigeonMethod();
	$m->package = $p;
	$property   = new ReflectionProperty( 'Nashenski\\Shipping\\Pigeon\\Method', 'packing_budget' );
	if ( PHP_VERSION_ID < 80100 ) {
		$property->setAccessible( true ); }
	$property->setValue( $m, new Packing_Budget( 2 ) );
	expect(
		rejected(
			function () use ( $m ) {
				PackageBoxes::calculate_label( array(), $m );
			}
		),
		'Shared budget exhaustion in the unit preflight must escape the weight fallback'
	);
	expect(
		rejected(
			function () use ( $p ) {
				APSBoxes::package_fits_largest_locker( $p, new Packing_Budget( 1 ) );
			}
		),
		'Per-product locker wrapper must propagate budget exhaustion'
	);

	fixture( true );
	$p             = package( 1 );
	$p['contents'] = array();
	for ( $i = 0; $i < 200; $i++ ) {
		$p['contents'][] = array(
			'quantity' => 1,
			'data'     => new WC_Product( 0, array( 1 + $i % 7, 2 + $i % 11, 3 + $i % 13 ) ),
		); }
	$m     = new PigeonMethod();
	$start = microtime( true );
	$m->calculate_shipping( $p );
	expect( 1 === count( $m->rates ) && 1 === $api_calls && microtime( true ) - $start < 5, 'Mixed product searches that exhaust the carton budget must fall back to the weight split and keep the quote' );

	fixture();
	$m = new PigeonMethod();
	$m->calculate_shipping( $p );
	expect( 1 === count( $m->rates ) && 1 === count( $last_request['packages'] ), 'Large mixed cart without automatic sizing must also keep its quote' );
	fixture( true );
	$p2             = $p;
	$p2['contents'] = array_merge( $p['contents'], $p['contents'], $p['contents'], $p['contents'], $p['contents'], $p['contents'] );
	$m              = new PigeonMethod();
	$m->calculate_shipping( $p2 );
	expect( 1200 === count( $p2['contents'] ) && ! $m->rates && ! $api_calls, 'The unit cap must still reject carts over 1000 units after the weight fallback was added' );

	fixture();
	$m = new PigeonMethod();
	$m->calculate_shipping( package( 50, 0.01 ) );
	expect( 1 === count( $m->rates ), 'A normal fifty-unit cart must remain available' );

	foreach ( array(
		0  => 1,
		-2 => 2,
		2  => 2,
	) as $quantity => $expected ) {
		$items = invoke( PackageBoxes::class, 'get_product_items', package( $quantity, 0 ) );
		expect( count( $items ) === $expected, 'Package quantity defaults/absolute-value behavior must be retained' );
	}
	expect( 2 === count( invoke( PackageBoxes::class, 'get_product_items', package( 0.5, 0 ) ) ), 'Existing fractional helper range behavior must remain unchanged' );

	fixture( true );
	$m              = new PigeonMethod();
	$m->package     = package( 2000, 0 );
	$m->cookie_data = $cookie;
	expect(
		rejected(
			function () use ( $m ) {
				$m->calculate_shipping_price_from_api();
			}
		) && ! $api_calls,
		'Direct API preparation must lazily enforce packing limits'
	);

	fixture();
	$filters['nashenski/pigeon/calculate_label'][] = function ( $label, $method ) {
		try {
			$method->packing_budget()->spend( Packing_Budget::MAX_WORK + 1 );
		} catch ( Packing_Limit_Exceeded $e ) {
			// Expected: the budget limit is reached.
			unset( $e );
		}
			return $label;
	};
	$m                = new PigeonMethod();
	$m->fixed_price   = 5;
	$m->free_shipping = true;
	$m->calculate_shipping( package( 1 ) );
	expect( ! $m->rates && ! $api_calls && ! $public_quotes, 'Swallowed label-filter rejection must not restore a quote' );
	fixture();
	$filters['nashenski/pigeon/rate'][] = function ( $rate, $method ) {
		try {
			$method->packing_budget()->spend( Packing_Budget::MAX_WORK + 1 );
		} catch ( Packing_Limit_Exceeded $e ) {
			// Expected: the budget limit is reached.
			unset( $e );
		}
			return $rate;
	};
	$m                                  = new PigeonMethod();
	$m->calculate_shipping( package( 1 ) );
	expect( ! $m->rates && null === $wc->session->get( 'nashenski-pigeon-label' ), 'Final rate registration must honor sticky rejection' );

	fixture();
	$p     = package( 3, 0.1 );
	$boxes = array(
		array(
			'length'     => 61,
			'width'      => 44,
			'height'     => 37,
			'max_weight' => 30,
		),
	);
	expect( APSPackage::package_products_fit_largest_box( $p, $boxes ) === APSPackage::package_products_fit_largest_box( $p, $boxes, new Packing_Budget() ), 'Optional shared helper budget must preserve normal per-product eligibility' );

	fixture( true );
	$item                     = package( 1, 0 )['contents'][0];
	$item['data']->read_limit = 5;
	$m                        = new PigeonMethod( 'locker' );
	$m->package               = array( 'contents' => array_fill( 0, 2000, $item ) );
	expect(
		rejected(
			function () use ( $m ) {
				APSBoxes::calculate_label( array(), $m );
			}
		),
		'Direct locker filtering must reject excessive rows before conversion or ordinary-error fallback'
	);
	expect( 0 === $item['data']->reads, 'Excessive-row rejection must precede product extraction' );
	foreach ( array(
		'10000' => true,
		'9999'  => false,
	) as $total => $free ) {
		fixture();
		$package_total         = (int) $total / 100;
		$m                     = new PigeonMethod();
		$m->free_shipping_over = 100;
		$m->calculate_shipping( package( 1 ) );
		expect( $m->free_shipping === $free, 'Pigeon free shipping at total ' . $package_total . ' must be ' . var_export( $free, true ) );
	}
	unset( $package_total );
	echo 'PASS: ' . $checks . " Pigeon assertions\n";
}
