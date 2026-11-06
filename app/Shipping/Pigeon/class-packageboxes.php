<?php
/**
 * Pigeon Express parcels for address and office delivery.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Pigeon;

use Nashenski\Shipping\Packer\Carton_Packer;
use Nashenski\Shipping\Packer\Product;
use Nashenski\Shipping\Packer\Size;
use Nashenski\Shipping\Packer\Packing_Budget;
use Nashenski\Shipping\Packer\Packing_Limit_Exceeded;

defined( 'ABSPATH' ) || exit;

/**
 * Packs a cart into Pigeon Express parcels for address and office delivery.
 */
class PackageBoxes {
	const MAX_LENGTH          = 200;
	const MAX_WIDTH           = 200;
	const MAX_HEIGHT          = 200;
	const MAX_WEIGHT          = 30;
	const MAX_SHIPMENT_WEIGHT = 400;
	const DEFAULT_SIZE        = 20;

	/**
	 * Hooks the label filter.
	 */
	public function __construct() {
		add_filter( 'nashenski_pigeon_calculate_label', array( __CLASS__, 'calculate_label' ), 21, 2 );
	}

	/**
	 * Sets the label packages from the packed parcels.
	 *
	 * @param array  $label  Label data.
	 * @param object $method Shipping method.
	 * @return array
	 * @throws Packing_Limit_Exceeded When the cart is over the packing limits.
	 */
	public static function calculate_label( $label, $method ) {
		if ( ! self::should_handle_method( $method ) ) {
			return $label;
		}

		$budget = $method instanceof Method ? $method->packing_budget() : new Packing_Budget();
		try {
			$packages = self::build_packages( $method->package, $method, $budget );
		} catch ( Packing_Limit_Exceeded $e ) {
			throw $e;
		} catch ( \Throwable $e ) {
			$packages = self::split_weight_into_packages( self::get_package_weight( $method->package, $method ) );
		}

		if ( empty( $packages ) ) {
			return $label;
		}

		$budget->spend( 0 );
		$label['packages'] = $packages;

		return $label;
	}

	/**
	 * Whether the package weight fits one shipment.
	 *
	 * @param array       $package Shipping package.
	 * @param object|null $method  Shipping method.
	 * @return bool
	 */
	public static function package_weight_fits_shipment( $package, $method = null ) {
		return self::get_package_weight( $package, $method ) <= self::MAX_SHIPMENT_WEIGHT;
	}

	/**
	 * Check the full expansion before allocating any unit objects.
	 *
	 * @param array               $package Shipping package.
	 * @param Packing_Budget|null $budget  Work budget.
	 */
	public static function validate_package( $package, ?Packing_Budget $budget = null ): void {
		$budget = $budget ?? new Packing_Budget();
		$budget->spend( count( $package['contents'] ?? array() ) );
		$units = 0;

		foreach ( $package['contents'] ?? array() as $item ) {
			if ( empty( $item['data'] ) || ! is_a( $item['data'], 'WC_Product' ) ) {
				continue;
			}
			$quantity = self::get_quantity( $item, $budget );
			// Preserve range(1, 0), which the old fractional-quantity path used.
			$units += $quantity > 0 ? $quantity : 2;
			$budget->check_units( $units );
		}
	}

	/**
	 * Whether the method delivers to an address or office and has a package.
	 *
	 * @param object $method Shipping method.
	 * @return bool
	 */
	private static function should_handle_method( $method ) {
		return isset( $method->delivery_type ) && 'locker' !== $method->delivery_type && ! empty( $method->package );
	}

	/**
	 * Groups the products into parcels, or splits by weight when they cannot be packed.
	 *
	 * @param array          $package Shipping package.
	 * @param object         $method  Shipping method.
	 * @param Packing_Budget $budget  Work budget.
	 * @return array
	 */
	private static function build_packages( $package, $method, Packing_Budget $budget ) {
		$items = self::get_product_items( $package, $budget );

		if ( empty( $items ) ) {
			return self::split_weight_into_packages( self::get_package_weight( $package, $method ) );
		}

		// Carton search has its own work budget: a big cart that cannot be searched in time is priced by weight, not hidden.
		$packer   = new Carton_Packer( new Packing_Budget() );
		$groups   = array();
		$products = array();
		$weight   = 0;

		try {
			foreach ( $items as $item ) {
				$candidate_products = array_merge( $products, array( $item['product'] ) );
				$candidate_weight   = $weight + $item['weight'];

				if ( self::group_fits( $packer, $candidate_products, $candidate_weight ) ) {
					$products = $candidate_products;
					$weight   = $candidate_weight;
					continue;
				}

				if ( empty( $products ) ) {
					return self::split_weight_into_packages( self::get_package_weight( $package, $method ) );
				}

				Packing_Budget::check_parcels( count( $groups ) + 1 );
				$groups[] = array(
					'products' => $products,
					'weight'   => $weight,
				);

				$products = array( $item['product'] );
				$weight   = $item['weight'];

				if ( ! self::group_fits( $packer, $products, $weight ) ) {
					return self::split_weight_into_packages( self::get_package_weight( $package, $method ) );
				}
			}

			if ( ! empty( $products ) ) {
				Packing_Budget::check_parcels( count( $groups ) + 1 );
				$groups[] = array(
					'products' => $products,
					'weight'   => $weight,
				);
			}

			return self::groups_to_packages( $packer, $groups );
		} catch ( Packing_Limit_Exceeded $e ) {
			// The unit and parcel caps still apply: the weight split enforces its own.
			return self::split_weight_into_packages( self::get_package_weight( $package, $method ) );
		}
	}

	/**
	 * One item per product unit, with its size and weight.
	 *
	 * @param array               $package Shipping package.
	 * @param Packing_Budget|null $budget  Work budget.
	 * @return array
	 * @throws Packing_Limit_Exceeded When a weight is not finite.
	 */
	private static function get_product_items( $package, ?Packing_Budget $budget = null ) {
		$budget = $budget ?? new Packing_Budget();
		self::validate_package( $package, $budget );
		$items          = array();
		$dimension_unit = get_option( 'woocommerce_dimension_unit' );

		if ( empty( $package['contents'] ) ) {
			return $items;
		}

		foreach ( $package['contents'] as $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
				continue;
			}

			$product  = $cart_item['data'];
			$quantity = self::get_quantity( $cart_item, $budget );
			$name     = nashenski_normalize_text_for_label( $product->get_name() );
			$length   = $product->get_length() ? wc_get_dimension( $product->get_length(), 'mm', $dimension_unit ) : wc_get_dimension( self::DEFAULT_SIZE, 'mm', 'cm' );
			$width    = $product->get_width() ? wc_get_dimension( $product->get_width(), 'mm', $dimension_unit ) : wc_get_dimension( self::DEFAULT_SIZE, 'mm', 'cm' );
			$height   = $product->get_height() ? wc_get_dimension( $product->get_height(), 'mm', $dimension_unit ) : wc_get_dimension( self::DEFAULT_SIZE, 'mm', 'cm' );
			$weight   = $product->get_weight() ? wc_get_weight( $product->get_weight(), 'kg' ) : 0;
			if ( ! is_finite( (float) $weight ) ) {
				throw new Packing_Limit_Exceeded( 'Shipping weight must be finite.' );
			}
			$budget->spend( $quantity > 0 ? $quantity : 2 );

			foreach ( range( 1, $quantity ) as $i ) {
				$items[] = array(
					'product' => new Product(
						$name,
						new Size(
							(int) ceil( $length ),
							(int) ceil( $width ),
							(int) ceil( $height )
						)
					),
					'weight'  => (float) $weight,
				);
			}
		}

		return $items;
	}

	/**
	 * Whether the products fit one parcel.
	 *
	 * @param Carton_Packer $packer   Packer.
	 * @param array         $products Products.
	 * @param float         $weight   Weight in kg.
	 * @return bool
	 * @throws Packing_Limit_Exceeded When the search is over its limits.
	 */
	private static function group_fits( $packer, $products, $weight ) {
		if ( $weight > self::MAX_WEIGHT ) {
			return false;
		}

		try {
			$packer->find_best_carton( $products, self::get_max_carton() );
		} catch ( Packing_Limit_Exceeded $e ) {
			throw $e;
		} catch ( \Throwable $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Label packages from the product groups.
	 *
	 * @param Carton_Packer $packer Packer.
	 * @param array         $groups Product groups.
	 * @return array
	 */
	private static function groups_to_packages( $packer, $groups ) {
		$packages = array();
		Packing_Budget::check_parcels( count( $groups ) );

		foreach ( $groups as $group ) {
			$result = $packer->find_best_carton( $group['products'], self::get_max_carton() );

			$packages[] = array(
				'weight' => self::normalize_weight( $group['weight'] ),
				'width'  => wc_get_dimension( $result->width, 'cm', 'mm' ),
				'length' => wc_get_dimension( $result->length, 'cm', 'mm' ),
				'height' => wc_get_dimension( $result->height, 'cm', 'mm' ),
			);
		}

		return $packages;
	}

	/**
	 * Largest parcel in mm.
	 *
	 * @return array
	 */
	private static function get_max_carton() {
		return array(
			'length' => wc_get_dimension( self::MAX_LENGTH, 'mm', 'cm' ),
			'width'  => wc_get_dimension( self::MAX_WIDTH, 'mm', 'cm' ),
			'height' => wc_get_dimension( self::MAX_HEIGHT, 'mm', 'cm' ),
		);
	}

	/**
	 * Largest-size parcels for a weight, at most the parcel weight each.
	 *
	 * @param float $weight Weight in kg.
	 * @return array
	 * @throws Packing_Limit_Exceeded When the weight is not finite.
	 */
	private static function split_weight_into_packages( $weight ) {
		if ( ! is_numeric( $weight ) || ! is_finite( (float) $weight ) ) {
			throw new Packing_Limit_Exceeded( 'Shipping weight must be finite.' );
		}
		$packages = array();
		$weight   = max( self::normalize_weight( $weight ), 0.100 );
		Packing_Budget::check_parcels( ceil( $weight / self::MAX_WEIGHT ) );

		while ( $weight > self::MAX_WEIGHT ) {
			$packages[] = self::max_size_package( self::MAX_WEIGHT );
			$weight    -= self::MAX_WEIGHT;
		}

		$packages[] = self::max_size_package( $weight );

		return $packages;
	}

	/**
	 * A largest-size parcel.
	 *
	 * @param float $weight Weight in kg.
	 * @return array
	 */
	private static function max_size_package( $weight ) {
		return array(
			'weight' => self::normalize_weight( $weight ),
			'width'  => self::MAX_WIDTH,
			'length' => self::MAX_LENGTH,
			'height' => self::MAX_HEIGHT,
		);
	}

	/**
	 * Package weight in kg.
	 *
	 * @param array  $package Shipping package.
	 * @param object $method  Shipping method.
	 * @return float
	 * @throws Packing_Limit_Exceeded When the weight is not finite.
	 */
	private static function get_package_weight( $package, $method ) {
		$weight = 0;

		if ( ! empty( $package['contents'] ) ) {
			foreach ( $package['contents'] as $cart_item ) {
				if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) ) {
					continue;
				}

				if ( $cart_item['data']->get_weight() ) {
					$quantity = self::get_quantity( $cart_item );
					$weight  += wc_get_weight( $cart_item['data']->get_weight(), 'kg' ) * $quantity;
				}
			}
		}

		if ( ! $weight ) {
			$weight = \Nashenski\Hooks::filter( 'nashenski_pigeon_label_weight', 'nashenski/pigeon/label/weight', 1, $package, $method );
		}

		if ( ! is_numeric( $weight ) || ! is_finite( (float) $weight ) ) {
			throw new Packing_Limit_Exceeded( 'Shipping weight must be finite.' );
		}
		return (float) $weight;
	}

	/**
	 * Item quantity, checked against the budget.
	 *
	 * @param array               $item   Cart item.
	 * @param Packing_Budget|null $budget Work budget.
	 * @return int
	 * @throws Packing_Limit_Exceeded When the quantity is not finite.
	 */
	private static function get_quantity( $item, ?Packing_Budget $budget = null ): int {
		$quantity = $item['quantity'] ?? 1;
		if ( is_float( $quantity ) && ! is_finite( $quantity ) ) {
			throw new Packing_Limit_Exceeded( 'Shipping quantity must be finite.' );
		}
		if ( empty( $quantity ) ) {
			return 1;
		}
		if ( ! is_numeric( $quantity ) || ! is_finite( (float) $quantity ) ) {
			throw new Packing_Limit_Exceeded( 'Shipping quantity must be finite.' );
		}
		if ( $budget ) {
			$budget->check_units( abs( (float) $quantity ) );
		}
		return absint( $quantity );
	}

	/**
	 * Weight rounded to grams, at least 100 g when above zero.
	 *
	 * @param float $weight Weight in kg.
	 * @return float
	 */
	private static function normalize_weight( $weight ) {
		$weight = (float) $weight;

		if ( $weight > 0 && $weight < 0.100 ) {
			return 0.100;
		}

		return round( $weight, 3 );
	}
}
