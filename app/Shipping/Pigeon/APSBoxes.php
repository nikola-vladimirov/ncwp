<?php
namespace Nashenski\Shipping\Pigeon;

use Nashenski\Admin\Fields;
use Nashenski\Shipping\Packer\APSPackage;
use Nashenski\Shipping\Packer\Packing_Budget;
use Nashenski\Shipping\Packer\Packing_Limit_Exceeded;

defined( 'ABSPATH' ) || exit;

class APSBoxes {
	const OPTION_NAME       = 'auto_size';
	const MAX_LOCKER_WEIGHT = 30;

	private static $box_sizes = array(
		array(
			'name'       => 'S',
			'length'     => 61,
			'width'      => 44,
			'height'     => 8,
			'max_weight' => 30,
		),
		array(
			'name'       => 'M',
			'length'     => 61,
			'width'      => 44,
			'height'     => 18,
			'max_weight' => 30,
		),
		array(
			'name'       => 'L',
			'length'     => 61,
			'width'      => 44,
			'height'     => 37,
			'max_weight' => 30,
		),
	);

	public function __construct() {
		if ( nashenski_get_option( 'pigeon', self::OPTION_NAME ) === 'yes' ) {
			add_filter( 'nashenski/pigeon/calculate_label', array( __CLASS__, 'calculate_label' ), 20, 2 );
		}
	}

	public static function calculate_label( $label, $method ) {
		if ( ! self::should_handle_method( $method ) ) {
			return $label;
		}

		try {
			$budget       = $method instanceof Method ? $method->packing_budget() : new Packing_Budget();
			$packed_boxes = APSPackage::pack_package( $method->package, self::$box_sizes, null, $budget );
		} catch ( Packing_Limit_Exceeded $e ) {
			throw $e;
		} catch ( \Throwable $e ) {
			return $label;
		}

		if ( empty( $packed_boxes ) ) {
			return $label;
		}

		$label['packages'] = self::build_packages( $packed_boxes );

		return $label;
	}

	public static function package_fits_largest_locker( $package, ?Packing_Budget $budget = null ) {
		return APSPackage::package_products_fit_largest_box( $package, self::$box_sizes, $budget );
	}

	public static function package_weight_fits_locker( $package ) {
		return self::get_package_weight( $package ) <= self::MAX_LOCKER_WEIGHT;
	}

	private static function should_handle_method( $method ) {
		return isset( $method->delivery_type ) && $method->delivery_type === 'locker' && ! empty( $method->package );
	}

	private static function build_packages( $packed_boxes ) {
		$packages = array();

		foreach ( $packed_boxes as $packed_box ) {
			$box        = $packed_box['box'];
			$weight     = ! empty( $packed_box['weight'] ) ? $packed_box['weight'] : 1;
			$packages[] = array(
				'weight' => $weight > 0 && $weight < 0.100 ? 0.100 : $weight,
				'width'  => $box['width'],
				'length' => $box['length'],
				'height' => $box['height'],
			);
		}

		return $packages;
	}

	private static function get_package_weight( $package ) {
		$weight = 0;

		if ( empty( $package['contents'] ) ) {
			return $weight;
		}

		foreach ( $package['contents'] as $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! is_a( $cart_item['data'], 'WC_Product' ) || ! $cart_item['data']->get_weight() ) {
				continue;
			}

			$raw_quantity = $cart_item['quantity'] ?? 1;
			if ( is_numeric( $raw_quantity ) && ! is_finite( (float) $raw_quantity ) ) {
				throw new Packing_Limit_Exceeded( 'Shipping quantity must be finite.' );
			}
			$quantity = ! empty( $raw_quantity ) ? absint( $raw_quantity ) : 1;
			$weight  += wc_get_weight( $cart_item['data']->get_weight(), 'kg' ) * $quantity;
		}

		if ( ! is_finite( (float) $weight ) ) {
			throw new Packing_Limit_Exceeded( 'Shipping weight must be finite.' );
		}
		return (float) $weight;
	}
}
