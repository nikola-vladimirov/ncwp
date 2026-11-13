<?php // phpcs:disable WordPress.Files.FileName, Universal.Namespaces, Generic.Files.OneObjectStructurePerFile, Universal.Files.SeparateFunctionsFromOO, Generic.Classes.DuplicateClassName -- offline test: WordPress stubs stand in for the real classes and share one file with the test.
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
		 * @return mixed Result.
		 */
		public static function connected() {
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
		 * @return array Result.
		 */
		public static function get() {
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
	require_once __DIR__ . '/fixtures/hooks.php';
	require __DIR__ . '/../vendor/autoload.php';
	require_once __DIR__ . '/../app/autoload.php';
	set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- offline test turns warnings into failures.
		function ( $severity, $message, $file, $line ) {
			throw new \ErrorException( $message, 0, $severity, $file, $line ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI test failure message, not HTML.
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
	 * @param mixed  $name          Name.
	 * @param string $default_value Default.
	 * @return string Result.
	 */
	function get_option( $name, $default_value = '' ) {
		return 'woocommerce_dimension_unit' === $name ? 'cm' : $default_value; }
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
	 * @return mixed Result.
	 */
	function nashenski_normalize_text_for_label( $value ) {
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
	 * @return mixed Result.
	 */
	function wc_get_weight( $value ) {
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
	 * @param mixed $value Value.
	 * @return mixed Result.
	 */
	function __( $value ) {
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
	 * @return mixed Result.
	 */
	function nashenski_courier_cookie() {
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
	 * @return mixed Result.
	 */
	function nashenski_tax_based_price( $value ) {
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
	function WC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- stub of WooCommerce's WC().
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
	 * @param mixed   $target Class name or object.
	 * @param mixed   $name   Method name.
	 * @param mixed[] ...$args Args.
	 * @return mixed Result.
	 */
	function invoke( $target, $name, ...$args ) {
		$r = new ReflectionMethod( $target, $name );
		if ( PHP_VERSION_ID < 80100 ) {
			$r->setAccessible( true ); }
		return $r->invoke( is_object( $target ) ? $target : null, ...$args );
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
			throw new RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI test failure message, not HTML.
		}
	}
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
		 * @return mixed Result.
		 */
		public function api_call( $endpoint, $body ) {
			++$GLOBALS['api_calls'];
			$GLOBALS['last_request'] = $body;
			if ( 'no_price' === $GLOBALS['api_error'] ) {
				return array( 'data' => array() ); }
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

	foreach ( array( 'office', 'address' ) as $delivery_type ) {
		fixture();
		$cookie['type'] = $delivery_type;
		$pigeon_method  = new PigeonMethod( $delivery_type );
		$pigeon_method->calculate_shipping( package( 3, 0.5 ) );
		expect( 1 === count( $pigeon_method->rates ) && 12 === $pigeon_method->rates[0]['cost'], 'Normal office/address quote must retain its price' );
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
		$connected     = ! $disconnected;
		$api_error     = ! $disconnected;
		$pigeon_method = new PigeonMethod();
		$pigeon_method->calculate_shipping( package( 1 ) );
		expect( 1 === count( $pigeon_method->rates ) && 9.0 === $pigeon_method->rates[0]['cost'] && 1 === $public_quotes, 'Ordinary login/API errors must retain public-price fallback' );
	}

	// A reply without a price is an error: the public price when the list is on, no valid rate when it is off. Never a free delivery.
	foreach ( array( '', 'no' ) as $use_public ) {
		fixture();
		$options['prices/use_public_pigeon'] = $use_public;
		$api_error                           = 'no_price';
		$pigeon_method                       = new PigeonMethod();
		$pigeon_method->calculate_shipping( package( 1 ) );
		$pigeon_rate = $pigeon_method->rates[0] ?? array();
		expect( 1 === $api_calls && ! $pigeon_method->free_shipping && empty( $pigeon_rate['meta_data']['free_shipping'] ), 'A Pigeon reply without a price must not become free shipping' );
		if ( 'no' === $use_public ) {
			expect( false === $pigeon_rate['meta_data']['validated'] && ! empty( $pigeon_rate['meta_data']['errors'] ), 'A Pigeon reply without a price must be an error when the public list is off' );
		} else {
			expect( 9.0 === $pigeon_rate['cost'] && true === $pigeon_rate['meta_data']['validated'], 'A Pigeon reply without a price must use the public price' );
		}
	}

	foreach ( array( false, true ) as $auto ) {
		foreach ( array( false, true ) as $connected_fixture ) {
			foreach ( array( 'office', 'address' ) as $delivery_type ) {
				fixture( $auto );
				$connected      = $connected_fixture;
				$cookie['type'] = $delivery_type;
				$filters['nashenski/pigeon/rate/disable_packages'][] = function () {
					return false;
				};
				$pigeon_method                                       = new PigeonMethod( $delivery_type );
				$pigeon_method->fixed_price                          = 5;
				$pigeon_method->free_shipping                        = true;
				$free_coupon = true;
				$wc->session->set( 'nashenski-pigeon-label', array( 'stale' => true ) );
				if ( function_exists( 'memory_reset_peak_usage' ) ) {
					memory_reset_peak_usage(); }
				$memory = memory_get_usage();
				$start  = microtime( true );
				$pigeon_method->calculate_shipping( package( '1e300', 0 ) );
				expect( ! $pigeon_method->rates && ! $api_calls && ! $public_quotes, 'Huge unweighted quantities must not reach rate, client or any price fallback' );
				expect( null === $wc->session->get( 'nashenski-pigeon-label' ), 'Rejection must clear stale session labels' );
				expect( memory_get_peak_usage() - $memory < 2 * 1024 * 1024 && microtime( true ) - $start < 2, 'Rejection must not amplify quantity into memory or CPU work' );
				expect(
					rejected(
						function () use ( $pigeon_method ) {
							$pigeon_method->calculate_shipping_price_from_api();
						}
					),
					'Direct API helper must retain rejected state, including while disconnected'
				);
				$pigeon_method->fixed_price   = 0;
				$pigeon_method->free_shipping = false;
				$free_coupon                  = false;
				$pigeon_method->calculate_shipping( package( 1 ) );
				expect( 1 === count( $pigeon_method->rates ), 'A normal subsequent calculation must reset rejected state' );
			}
		}
	}

	fixture();
	$cookie                                       = array();
	$wc->session->data['chosen_shipping_methods'] = array();
	$pigeon_method                                = new PigeonMethod();
	$pigeon_method->calculate_shipping( package( PHP_INT_MAX, 0 ) );
	expect( ! $pigeon_method->rates, 'Preflight must reject even before destination selection' );

	fixture( true );
	$cookie['type'] = 'locker';
	$pigeon_method  = new PigeonMethod( 'locker' );
	$pigeon_method->calculate_shipping( package( 3, 0.5 ) );
	expect( 1 === count( $pigeon_method->rates ) && 1.5 === $last_request['packages'][0]['weight'], 'Normal locker quote must retain weight' );
	expect( array( 44.0, 61.0, 8.0 ) === array( $last_request['packages'][0]['width'], $last_request['packages'][0]['length'], $last_request['packages'][0]['height'] ), 'Normal locker dimensions must retain its chosen box' );
	$p = package( 2, 0.1, array( 60, 40, 30 ) );
	expect( APSBoxes::package_fits_largest_locker( $p, new Packing_Budget() ), 'Early locker eligibility must remain a per-product fit test' );
	fixture( true );
	$cookie['type'] = 'locker';
	$pigeon_method  = new PigeonMethod( 'locker' );
	$pigeon_method->calculate_shipping( $p );
	expect( 1 === count( $pigeon_method->rates ) && 2 === count( $last_request['packages'] ), 'Locker sizing must retain legitimate multi-box output' );

	fixture();
	$cookie['type'] = 'locker';
	$pigeon_method  = new PigeonMethod( 'locker' );
	$pigeon_method->calculate_shipping( package( 1000000, 0 ) );
	expect( 1 === count( $pigeon_method->rates ) && 1000000 === $last_request['inventory_items'][0]['quantity'], 'No blanket quantity cap may be added to non-expanding locker calculation' );

	fixture( true );
	$cookie['type'] = 'locker';
	$pigeon_method  = new PigeonMethod( 'locker' );
	$pigeon_method->calculate_shipping( package( PHP_INT_MAX, 0 ) );
	expect( ! $pigeon_method->rates && ! $api_calls && ! $public_quotes, 'Automatic locker sizing must reject before expansion' );

	foreach ( array( 'office', 'locker' ) as $delivery_type ) {
		fixture( true );
		$cookie['type']  = $delivery_type;
		$p               = package( 600, 0.001 );
		$p['contents'][] = $p['contents'][0];
		$pigeon_method   = new PigeonMethod( $delivery_type );
		$pigeon_method->calculate_shipping( $p );
		expect( ! $pigeon_method->rates && ! $api_calls && ! $public_quotes, 'Expanded-unit limits must be cumulative across lines and locker paths' );
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
	$pigeon_method                              = new PigeonMethod();
	$pigeon_method->calculate_shipping( package( 1, 0 ) );
	expect( ! $pigeon_method->rates && ! $api_calls && ! $public_quotes, 'Nonfinite fallback weights must fail before quote fallback' );
	fixture();
	$filters['nashenski/pigeon/rate/disable_packages'][] = function () {
		return false;
	};
	$pigeon_method                                       = new PigeonMethod();
	$pigeon_method->calculate_shipping( package( 1, 40000, array( 300, 300, 300 ) ) );
	expect( ! $pigeon_method->rates && ! $api_calls, 'Bypassing the commercial weight check must not remove the absolute split ceiling' );
	fixture();
	$pigeon_method = new PigeonMethod();
	$pigeon_method->calculate_shipping( package( 1, 65, array( 300, 300, 300 ) ) );
	expect( 1 === count( $pigeon_method->rates ) && array( 30.0, 30.0, 5.0 ) === array_column( $last_request['packages'], 'weight' ), 'Ordinary cannot-fit failures must retain weight fallback' );

	fixture();
	$pigeon_method          = new PigeonMethod();
	$pigeon_method->package = package( PHP_INT_MAX, 0 );
	expect(
		rejected(
			function () use ( $pigeon_method ) {
				PackageBoxes::calculate_label( array(), $pigeon_method );
			}
		),
		'Direct package filter must enforce preflight and propagate resource rejection'
	);
	$pigeon_method          = new PigeonMethod( 'locker' );
	$pigeon_method->package = package( PHP_INT_MAX, 0 );
	expect(
		rejected(
			function () use ( $pigeon_method ) {
				APSBoxes::calculate_label( array(), $pigeon_method );
			}
		),
		'Direct locker filter must propagate resource rejection'
	);
	$pigeon_method = (object) array(
		'delivery_type' => 'office',
		'package'       => package( PHP_INT_MAX, 0 ),
	);
	expect(
		rejected(
			function () use ( $pigeon_method ) {
				PackageBoxes::calculate_label( array(), $pigeon_method );
			}
		),
		'Standalone package filter must obtain a bounded budget'
	);

	fixture();
	$p                      = package( 3, 0.5 );
	$pigeon_method          = new PigeonMethod();
	$pigeon_method->package = $p;
	$property               = new ReflectionProperty( 'Nashenski\\Shipping\\Pigeon\\Method', 'packing_budget' );
	if ( PHP_VERSION_ID < 80100 ) {
		$property->setAccessible( true ); }
	$property->setValue( $pigeon_method, new Packing_Budget( 2 ) );
	expect(
		rejected(
			function () use ( $pigeon_method ) {
				PackageBoxes::calculate_label( array(), $pigeon_method );
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
	$pigeon_method = new PigeonMethod();
	$start         = microtime( true );
	$pigeon_method->calculate_shipping( $p );
	expect( 1 === count( $pigeon_method->rates ) && 1 === $api_calls && microtime( true ) - $start < 5, 'Mixed product searches that exhaust the carton budget must fall back to the weight split and keep the quote' );

	fixture();
	$pigeon_method = new PigeonMethod();
	$pigeon_method->calculate_shipping( $p );
	expect( 1 === count( $pigeon_method->rates ) && 1 === count( $last_request['packages'] ), 'Large mixed cart without automatic sizing must also keep its quote' );
	fixture( true );
	$p2             = $p;
	$p2['contents'] = array_merge( $p['contents'], $p['contents'], $p['contents'], $p['contents'], $p['contents'], $p['contents'] );
	$pigeon_method  = new PigeonMethod();
	$pigeon_method->calculate_shipping( $p2 );
	expect( 1200 === count( $p2['contents'] ) && ! $pigeon_method->rates && ! $api_calls, 'The unit cap must still reject carts over 1000 units after the weight fallback was added' );

	fixture();
	$pigeon_method = new PigeonMethod();
	$pigeon_method->calculate_shipping( package( 50, 0.01 ) );
	expect( 1 === count( $pigeon_method->rates ), 'A normal fifty-unit cart must remain available' );

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
	$pigeon_method              = new PigeonMethod();
	$pigeon_method->package     = package( 2000, 0 );
	$pigeon_method->cookie_data = $cookie;
	expect(
		rejected(
			function () use ( $pigeon_method ) {
				$pigeon_method->calculate_shipping_price_from_api();
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
	$pigeon_method                                 = new PigeonMethod();
	$pigeon_method->fixed_price                    = 5;
	$pigeon_method->free_shipping                  = true;
	$pigeon_method->calculate_shipping( package( 1 ) );
	expect( ! $pigeon_method->rates && ! $api_calls && ! $public_quotes, 'Swallowed label-filter rejection must not restore a quote' );
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
	$pigeon_method                      = new PigeonMethod();
	$pigeon_method->calculate_shipping( package( 1 ) );
	expect( ! $pigeon_method->rates && null === $wc->session->get( 'nashenski-pigeon-label' ), 'Final rate registration must honor sticky rejection' );

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
	$pigeon_method            = new PigeonMethod( 'locker' );
	$pigeon_method->package   = array( 'contents' => array_fill( 0, 2000, $item ) );
	expect(
		rejected(
			function () use ( $pigeon_method ) {
				APSBoxes::calculate_label( array(), $pigeon_method );
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
		$package_total                     = (int) $total / 100;
		$pigeon_method                     = new PigeonMethod();
		$pigeon_method->free_shipping_over = 100;
		$pigeon_method->calculate_shipping( package( 1 ) );
		expect( $pigeon_method->free_shipping === $free, 'Pigeon free shipping at total ' . $package_total . ' must be ' . var_export( $free, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- builds the failure message.
	}
	unset( $package_total );
	// A threshold that is zero in any spelling is no threshold: a cart of 99.99 does not ship free on it.
	foreach ( array(
		'0.00' => false,
		'0'    => false,
		''     => false,
		'1'    => true,
		'100'  => false,
	) as $threshold => $free ) {
		fixture();
		$package_total                     = 99.99;
		$pigeon_method                     = new PigeonMethod();
		$pigeon_method->free_shipping_over = $threshold;
		$pigeon_method->calculate_shipping( package( 1 ) );
		expect( $pigeon_method->free_shipping === $free, 'Pigeon free shipping at threshold "' . $threshold . '" must be ' . var_export( $free, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- builds the failure message.
	}
	unset( $package_total );
	echo 'PASS: ' . (int) $checks . " Pigeon assertions\n";
}
