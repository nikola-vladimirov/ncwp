<?php
/**
 * Offline regressions: php -d memory_limit=64M tests/speedy-resources.php
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
		 * @param mixed $courier Courier.
		 * @return mixed Result.
		 */
		public static function connected( $courier ) {
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
		public $title = 'Speedy';
		/**
		 * Id.
		 *
		 * @var string
		 */
		public $id = 'nashenski_speedy';
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

	$options = array();
	$filters = array();
	$checks  = 0;
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
	 * Get option.
	 *
	 * @param mixed  $key Key.
	 * @param string $default Default.
	 * @return string Result.
	 */
	function get_option( $key, $default = '' ) {
		return 'woocommerce_dimension_unit' === $key ? 'cm' : $default; }
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
	 * Wc get dimension.
	 *
	 * @param mixed  $value Value.
	 * @param mixed  $to To.
	 * @param string $from From.
	 * @return mixed Result.
	 */
	function wc_get_dimension( $value, $to, $from = 'cm' ) {
		return $from === $to ? (float) $value : (float) $value * ( 'mm' === $to ? 10 : 0.1 ); }
	/**
	 * Nashenski normalize text for label.
	 *
	 * @param mixed $value Value.
	 * @param int   $limit Limit.
	 * @return mixed Result.
	 */
	function nashenski_normalize_text_for_label( $value, $limit = 0 ) {
		return $limit ? substr( $value, 0, $limit ) : $value; }
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
	 * Nashenski format phone.
	 *
	 * @param mixed $phone Phone.
	 * @param mixed $country Country.
	 * @return mixed Result.
	 */
	function nashenski_format_phone( $phone, $country ) {
		return $phone; }
	/**
	 * Nashenski label request.
	 *
	 * @param mixed  $key Key.
	 * @param string $default Default.
	 * @return mixed Result.
	 */
	function nashenski_label_request( $key, $default = '' ) {
		return $default; }
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
	 * @param mixed   $hook Hook.
	 * @param mixed[] ...$args Args.
	 */
	function do_action( $hook, ...$args ) {}
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
	 * @param mixed   $hook Hook.
	 * @param mixed   $value Value.
	 * @param mixed[] ...$args Args.
	 * @return mixed Result.
	 */
	function apply_filters( $hook, $value, ...$args ) {
		foreach ( $GLOBALS['filters'][ $hook ] ?? array() as $callback ) {
			$value = $callback( $value, ...$args ); }
		return $value;
	}
	/**
	 * Expect.
	 *
	 * @param mixed $value Value.
	 * @param mixed $message Message.
	 * @throws RuntimeException . (false !== strpos( $type, "Packing" ) ? " When the packing budget is exceeded." : " On failure.") .
	 */
	function expect( $value, $message ) {
		++$GLOBALS['checks'];
		if ( ! $value ) {
			throw new RuntimeException( $message ); }
	}
	/**
	 * Invoke.
	 *
	 * @param mixed   $class Class.
	 * @param mixed   $method Method.
	 * @param mixed[] ...$args Args.
	 * @return mixed Result.
	 */
	function invoke( $class, $method, ...$args ) {
		$r = new ReflectionMethod( $class, $method );
		if ( PHP_VERSION_ID < 80100 ) {
			$r->setAccessible( true ); }
		return $r->invoke( is_object( $class ) ? $class : null, ...$args );
	}
	/**
	 * Resource rejected.
	 *
	 * @param mixed $callback Callback.
	 * @return bool Result.
	 */
	function resource_rejected( $callback ) {
		try {
			$callback();
		} catch ( \Nashenski\Shipping\Packer\Packing_Limit_Exceeded $e ) {
			return true; }
		return false;
	}
	$method_class = 'Nashenski\\Shipping\\Speedy\\Method';
	$normal       = invoke( $method_class, 'generate_parcels', 65, array(), 32 );
	expect( array( 32.0, 32.0, 1.0 ) === array_column( $normal, 'weight' ), 'Normal real-weight parcel split changed' );
	expect(
		resource_rejected(
			function () use ( $method_class ) {
				// 10,000 parcels safely reproduce the allocation bug without exhausting this test process.
				invoke( $method_class, 'generate_parcels', 320000, array(), 32 );
			}
		),
		'Excessive parcel count must be rejected before allocation'
	);

	use Nashenski\Shipping\Packer\Packing_Budget;
	use Nashenski\Shipping\Packer\Carton_Packer;
	use Nashenski\Shipping\Packer\APSPackage;
	use Nashenski\Shipping\Packer\APSPacker;
	use Nashenski\Shipping\Packer\Product;
	use Nashenski\Shipping\Packer\Size;

	/**
	 * Wc product.
	 */
	class WC_Product {
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
		 * Reads.
		 *
		 * @var int
		 */
		public $reads = 0;
		/**
		 * Constructor.
		 *
		 * @param int   $weight Weight.
		 * @param array $dimensions Dimensions.
		 */
		public function __construct( $weight = 1, array $dimensions = array( 1, 1, 1 ) ) {
			$this->weight     = $weight;
			$this->dimensions = $dimensions; }
		/**
		 * Get weight.
		 *
		 * @return mixed Result.
		 */
		public function get_weight() {
			++$this->reads;
			return $this->weight; }
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
		 * Get name.
		 *
		 * @return string Result.
		 */
		public function get_name() {
			return 'Product'; }
	}
	/**
	 * Speedysession.
	 */
	class SpeedySession {
		/**
		 * Data.
		 *
		 * @var array
		 */
		public $data = array(
			'chosen_shipping_methods' => array( 'nashenski_speedy:1' ),
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
	 * Speedycourier.
	 */
	class SpeedyCourier {
		/**
		 * Label request.
		 *
		 * @param mixed $mode Mode.
		 * @param mixed $label Label.
		 * @return mixed Result.
		 */
		public function label_request( $mode, $label ) {
			++$GLOBALS['api_calls'];
			return $GLOBALS['api_error'] ? array( 'error' => array( 'message' => 'Offline fixture' ) ) : array(
				'calculations' => array(
					array(
						'price' => array(
							'total'  => 12,
							'amount' => 10,
							'vat'    => 2,
						),
					),
				),
			);
		}
	}
	/**
	 * Speedyprofile.
	 */
	class SpeedyProfile {
		/**
		 * Get profile data.
		 *
		 * @return array Result.
		 */
		public function get_profile_data() {
			return array( 'clientId' => 1 ); }
	}
	/**
	 * Speedymethod.
	 */
	class SpeedyMethod extends \Nashenski\Shipping\Speedy\Method {
		/**
		 * Constructor.
		 *
		 * @param string $type Type.
		 */
		public function __construct( $type = 'office' ) {
			$this->delivery_type = $type;
			$this->test          = 'no';
			$this->container     = array(
				'client.speedy'         => new SpeedyCourier(),
				'client.speedy.profile' => new SpeedyProfile(),
			);
		}
	}
	/**
	 * Fixture.
	 */
	function fixture() {
		$GLOBALS['filters']       = array();
		$GLOBALS['options']       = $GLOBALS['filters'];
		$GLOBALS['connected']     = true;
		$GLOBALS['free_coupon']   = false;
		$GLOBALS['api_error']     = false;
		$GLOBALS['public_quotes'] = 0;
		$GLOBALS['api_calls']     = $GLOBALS['public_quotes'];
		$GLOBALS['cookie']        = array(
			'type'           => 'office',
			'selectedOffice' => 'officeID-1',
			'payment'        => 'card',
			'country'        => 'BG',
			'receiver'       => 'Test',
			'phone'          => '0888000000',
		);
		$GLOBALS['wc']            = (object) array( 'session' => new SpeedySession() );
		$GLOBALS['filters']['nashenski/speedy/calculate_label'][] = array( 'Nashenski\\Admin\\Speedy', 'set_min_parcel_weight' );
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

	fixture();
	$m = new SpeedyMethod();
	$p = package( 3 );
	$m->calculate_shipping( $p );
	expect( 1 === count( $m->rates ) && '12.00' === $m->rates[0]['cost'], 'Normal connected quote must remain available' );
	expect( 1 === $api_calls, 'Normal quote must reach the courier once' );
	$reads = $p['contents'][0]['data']->reads;
	invoke( $m, 'get_content_data' );
	invoke( $m, 'get_content_data' );
	expect( $reads === $p['contents'][0]['data']->reads, 'Prepared contents must be reused' );

	fixture();
	$api_error = true;
	$m         = new SpeedyMethod();
	$m->calculate_shipping( package( 1 ) );
	expect( 1 === count( $m->rates ) && 9.0 === $m->rates[0]['cost'] && 1 === $public_quotes, 'Ordinary API errors must still use public prices' );

	foreach ( array( false, true ) as $auto ) {
		foreach ( array( 'office', 'automat' ) as $type ) {
			fixture();
			$options['speedy/auto_size'] = $auto ? 'yes' : 'no';
			$cookie['type']              = $type;
			// Even filters that re-enable APS or fixed/free prices must not revive a rejected preparation.
			$filters['nashenski/speedy/rate/disable_aps'][] = function () {
				return false;
			};
			$m                = new SpeedyMethod( $type );
			$m->fixed_price   = 5;
			$m->free_shipping = true;
			$free_coupon      = true;
			$wc->session->set( 'nashenski-speedy-label', array( 'stale' => true ) );
			if ( function_exists( 'memory_reset_peak_usage' ) ) {
				memory_reset_peak_usage(); }
			$memory = memory_get_usage();
			$m->calculate_shipping( package( '1e300', 1 ) );
			expect( ! $m->rates && 0 === $api_calls && 0 === $public_quotes, 'Huge office/locker quantities must not yield a rate through any price fallback' );
			expect( null === $wc->session->get( 'nashenski-speedy-label' ), 'Rejected calculation must clear stale labels' );
			expect( memory_get_peak_usage() - $memory < 2 * 1024 * 1024, 'Huge quantities must be rejected with bounded memory' );
			expect(
				resource_rejected(
					function () use ( $m ) {
						invoke( $m, 'get_content_data' );
					}
				),
				'Rejected preparation must not be retried via the contents cache'
			);
		}
	}

	fixture();
	$options['speedy/auto_size'] = 'yes';
	$m                           = new SpeedyMethod();
	$m->calculate_shipping( package( 3, 0.5, array( 10, 4, 3 ) ) );
	expect( 1 === count( $m->rates ), 'Normal automatic sizing must still quote' );
	$content = invoke( $m, 'get_content_data' );
	expect( ! empty( $content['content']['parcels'][0]['size'] ), 'Automatic sizing must retain parcel dimensions' );

	fixture();
	$cookie['type'] = 'automat';
	$m              = new SpeedyMethod( 'automat' );
	$m->calculate_shipping( package( 2 ) );
	expect( 1 === count( $m->rates ), 'Normal locker packing must still quote' );

	fixture();
	$m = new SpeedyMethod();
	$m->calculate_shipping( package( 11, 32 ) );
	expect( ! $m->rates && ! $api_calls, 'Commercial ten-parcel limit must run before allocation' );
	$filters['nashenski/speedy/max_parcels'][] = function () {
		return 0;
	};
	$m = new SpeedyMethod();
	$m->calculate_shipping( package( 11, 32 ) );
	expect( 1 === count( $m->rates ), 'Disabling the commercial limit must still permit more than ten safe parcels' );
	$m = new SpeedyMethod();
	$m->calculate_shipping( package( 1001, 32 ) );
	expect( ! $m->rates, 'Disabling commercial limits must not remove the safety ceiling' );

	fixture();
	$filters['nashenski/speedy/max_parcels'][] = function () {
		return 2;
	};
	$m = new SpeedyMethod();
	$m->calculate_shipping( package( 3, 32 ) );
	expect( ! $m->rates, 'Custom smaller commercial limits must be honored before allocation' );

	foreach ( array( INF, NAN, 1.0e300 ) as $weight ) {
		fixture();
		expect(
			resource_rejected(
				function () use ( $method_class, $weight ) {
					invoke( $method_class, 'generate_parcels', $weight, array(), 32 );
				}
			),
			'Nonfinite or enormous weights must be rejected'
		);
	}
	fixture();
	expect(
		resource_rejected(
			function () use ( $method_class ) {
				invoke( $method_class, 'generate_parcels', 1, array(), 1.0e-300 );
			}
		),
		'A tiny positive max weight must not cause an unbounded loop'
	);
	foreach ( array( INF, NAN, 0 ) as $min ) {
		fixture();
		$filters['nashenski/speedy/min_parcel_weight'][] = function () use ( $min ) {
			return $min;
		};
		expect(
			resource_rejected(
				function () use ( $method_class ) {
					invoke( $method_class, 'generate_parcels', 1, array(), 32 );
				}
			),
			'Unsafe minimum weights must be rejected'
		);
	}

	fixture();
	$options['speedy/declared_value'] = 'yes';
	$size                             = array(
		'width'  => 100,
		'depth'  => 100,
		'height' => 100,
	);
	$parcels                          = invoke( $method_class, 'generate_parcels', 10, $size, 32 );
	expect( 7 === count( $parcels ) && array( 1, 2, 3, 4, 5, 6, 7 ) === array_column( $parcels, 'seqNo' ), 'Volumetric splitting must retain count and numbering' );
	expect( array_fill( 0, 7, $size ) === array_column( $parcels, 'size' ), 'Volumetric splitting must retain sizes' );
	expect( abs( array_sum( array_column( $parcels, 'weight' ) ) - 10 ) < 0.01, 'Volumetric splitting must retain the shipment weight within rounding' );
	expect(
		resource_rejected(
			function () use ( $method_class ) {
				invoke(
					$method_class,
					'generate_parcels',
					1,
					array(
						'width'  => 1e300,
						'depth'  => 1e300,
						'height' => 1e300,
					),
					32
				);
			}
		),
		'Nonfinite volumetric weights must be rejected'
	);

	fixture();
	$inflated = array( 'content' => array( 'parcels' => array( array( 'weight' => 1e300 ) ) ) );
	expect(
		resource_rejected(
			function () use ( $inflated ) {
				\Nashenski\Admin\Speedy::set_min_parcel_weight( $inflated );
			}
		),
		'The later label filter must reject inflated weights before splitting'
	);
	$normal_label = \Nashenski\Admin\Speedy::set_min_parcel_weight(
		array(
			'content' => array(
				'parcels' => array(
					array(
						'weight' => 65,
						'size'   => array( 'width' => 10 ),
					),
				),
			),
		)
	);
	expect( array( 32.0, 32.0, 1.0 ) === array_column( $normal_label['content']['parcels'], 'weight' ), 'Label filter must preserve normal splitting' );
	expect( array( 1, 2, 3 ) === array_column( $normal_label['content']['parcels'], 'seqNo' ), 'Label filter must preserve numbering' );

	fixture();
	array_unshift(
		$filters['nashenski/speedy/calculate_label'],
		function ( $label ) {
			$label['content']['parcels'][0]['weight'] = INF;
			return $label;
		}
	);
	$m              = new SpeedyMethod();
	$m->fixed_price = 4;
	$free_coupon    = true;
	$m->calculate_shipping( package( 1 ) );
	expect( ! $m->rates && ! $api_calls && ! $public_quotes, 'Late filter rejection must not reach courier or any fallback/free/fixed rate' );

	$products  = array( new Product( 'A', new Size( 10, 20, 30 ) ), new Product( 'B', new Size( 20, 30, 40 ) ) );
	$unbounded = ( new Carton_Packer() )->find_best_carton( $products );
	$bounded   = ( new Carton_Packer( new Packing_Budget() ) )->find_best_carton( $products );
	expect( serialize( $unbounded ) === serialize( $bounded ), 'Adding an optional budget must not change normal carton output' );
	expect(
		resource_rejected(
			function () use ( $products ) {
				( new Carton_Packer( new Packing_Budget( 5 ) ) )->find_best_carton( $products );
			}
		),
		'Carton search must stop at its work budget'
	);
	$boxes     = array(
		array(
			'length'     => 62,
			'width'      => 36,
			'height'     => 38,
			'max_weight' => 20,
		),
	);
	$aps_items = array(
		array(
			'length'   => 1,
			'width'    => 2,
			'height'   => 3,
			'weight'   => 0.1,
			'quantity' => 3,
		),
	);
	expect( ( new APSPacker( 5000 ) )->pack( $aps_items, $boxes ) === ( new APSPacker( 5000, new Packing_Budget() ) )->pack( $aps_items, $boxes ), 'Optional budget must preserve normal APS output' );
	expect(
		resource_rejected(
			function () use ( $aps_items, $boxes ) {
				( new APSPacker( 5000, new Packing_Budget( 1 ) ) )->pack( $aps_items, $boxes );
			}
		),
		'APS packing must stop at its work budget'
	);
	$aps_items[0]['quantity'] = PHP_INT_MAX;
	expect(
		resource_rejected(
			function () use ( $aps_items, $boxes ) {
				( new APSPacker( 5000, new Packing_Budget() ) )->pack( $aps_items, $boxes );
			}
		),
		'APS normalization must reject before unit expansion'
	);
	expect(
		resource_rejected(
			function () use ( $boxes ) {
				APSPackage::package_fits_largest_box( package( 10 ), $boxes, 5000, new Packing_Budget( 1 ) );
			}
		),
		'APS boolean wrapper must not swallow resource rejection'
	);

	fixture();
	$four = array_fill( 0, 4, new Product( 'A', new Size( 1, 1, 1 ) ) );
	expect(
		resource_rejected(
			function () use ( $method_class, $four ) {
				invoke( $method_class, 'generate_parcels_from_products', $four, array_fill( 0, 4, 1 ), array(), 1, 3, new Packing_Budget() );
			}
		),
		'Group generation must enforce the cumulative parcel limit'
	);

	fixture();
	$options['speedy/declared_value'] = 'yes';
	$options['speedy/auto_size']      = $options['speedy/declared_value'];
	$m                                = new SpeedyMethod();
	$m->calculate_shipping( package( 50, 0.01 ) );
	expect( 1 === count( $m->rates ), 'A normal fifty-unit homogeneous cart must remain within the work budget' );

	foreach ( array( 'office', 'automat' ) as $type ) {
		fixture();
		$options['speedy/auto_size'] = 'yes';
		$cookie['type']              = $type;
		$p                           = package( 600, 0.001 );
		$p['contents'][]             = $p['contents'][0];
		$m                           = new SpeedyMethod( $type );
		$m->calculate_shipping( $p );
		expect( ! $m->rates && ! $api_calls, 'Expanded-unit limits must be cumulative across cart lines' );
	}

	fixture();
	$options['speedy/declared_value'] = 'yes';
	$options['speedy/auto_size']      = $options['speedy/declared_value'];
	$p                                = package( 1 );
	$p['contents']                    = array();
	for ( $i = 0; $i < 200; $i++ ) {
		$p['contents'][] = array(
			'quantity' => 1,
			'data'     => new WC_Product( 0.001, array( 1 + $i % 7, 2 + $i % 11, 3 + $i % 13 ) ),
		);
	}
	$m     = new SpeedyMethod();
	$start = microtime( true );
	$m->calculate_shipping( $p );
	expect( ! $m->rates && ! $api_calls, 'Repeated mixed-product carton searches must stop at the shared work budget' );
	expect( microtime( true ) - $start < 5, 'The bounded work fixture must finish promptly' );

	fixture();
	$filters['nashenski/speedy/rate'][] = function ( $rate, $method ) {
		try {
			$method->packing_budget()->spend( Packing_Budget::MAX_WORK + 1 );
		} catch ( \Nashenski\Shipping\Packer\Packing_Limit_Exceeded $e ) {
			// Expected: the budget limit is reached.
			unset( $e );
		}
			return $rate;
	};
	$m                                  = new SpeedyMethod();
	$m->calculate_shipping( package( 1 ) );
	expect( ! $m->rates, 'A consumed work budget must stay rejected even if a filter swallows the exception' );

	fixture();
	$filters['nashenski/speedy/max_parcels'][] = function () {
		return 2;
	};
	$label                                     = array( 'content' => array( 'parcels' => array( array( 'weight' => 33 ), array( 'weight' => 33 ) ) ) );
	expect(
		resource_rejected(
			function () use ( $label ) {
				\Nashenski\Admin\Speedy::set_min_parcel_weight( $label, new SpeedyMethod() );
			}
		),
		'Admin filter must reject cumulative split growth, not only each input parcel'
	);

	fixture();
	$filters['nashenski/speedy/max_parcel_weight'][] = function () {
		return 0;
	};
	$label = \Nashenski\Admin\Speedy::set_min_parcel_weight( array( 'content' => array( 'parcels' => array( array( 'weight' => 65 ) ) ) ) );
	expect( array( 65.0 ) === array_column( $label['content']['parcels'], 'weight' ), 'Disabling weight splitting must retain one finite parcel' );
	foreach ( array(
		'10000' => true,
		'9999'  => false,
	) as $total => $free ) {
		fixture();
		$package_total         = (int) $total / 100;
		$m                     = new SpeedyMethod();
		$m->free_shipping_over = 100;
		$m->calculate_shipping( package( 1 ) );
		expect( $m->free_shipping === $free, 'Speedy free shipping at total ' . $package_total . ' must be ' . var_export( $free, true ) );
	}
	unset( $package_total );
	echo 'PASS: ' . $checks . " Speedy assertions\n";
}
