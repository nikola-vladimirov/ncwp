<?php
namespace Nashenski\Shipping\Packer;

defined( 'ABSPATH' ) || exit;

class APSPackage {
	public static function pack_package( $package, array $box_sizes, $volumetric_weight_divisor = null, ?Packing_Budget $budget = null ): array {
		$products = self::get_products_from_package( $package, $budget );

		if ( empty( $products ) ) {
			return array();
		}

		return ( new APSPacker( $volumetric_weight_divisor, $budget ) )->pack( $products, $box_sizes );
	}

	public static function package_fits_largest_box( $package, array $box_sizes, $volumetric_weight_divisor = null, ?Packing_Budget $budget = null ): bool {
		$products = self::get_products_from_package( $package, $budget );

		if ( empty( $products ) ) {
			return true;
		}

		try {
			$packed_boxes = ( new APSPacker( $volumetric_weight_divisor, $budget ) )->pack( $products, array( self::get_largest_box_size( $box_sizes ) ) );
		} catch ( Packing_Limit_Exceeded $e ) {
			throw $e;
		} catch ( \Throwable $e ) {
			return false;
		}

		return count( $packed_boxes ) === 1;
	}

	public static function package_products_fit_largest_box( $package, array $box_sizes, ?Packing_Budget $budget = null ): bool {
		if ( $budget ) {
			$contents = self::get_package_contents( $package );
			$budget->check_units( count( $contents ) );
			$budget->spend( count( $contents ) );
			foreach ( $contents as $item ) {
				$quantity = $item['quantity'] ?? 1;
				if ( is_numeric( $quantity ) && ! is_finite( (float) $quantity ) ) {
					throw new Packing_Limit_Exceeded( 'Shipping quantity must be finite.' );
				}
			}
		}
		// This checks one of each product, not the aggregate shipment quantity.
		$products = self::get_products_from_package( $package );

		if ( empty( $products ) ) {
			return true;
		}

		$largest_box = self::get_largest_box_size( $box_sizes );
		$packer      = new APSPacker( null, $budget );

		foreach ( $products as $product ) {
			$product['quantity'] = 1;

			try {
				$packed_boxes = $packer->pack( array( $product ), array( $largest_box ) );
			} catch ( Packing_Limit_Exceeded $e ) {
				throw $e;
			} catch ( \Throwable $e ) {
				return false;
			}

			if ( count( $packed_boxes ) !== 1 ) {
				return false;
			}
		}

		return true;
	}

	private static function get_largest_box_size( array $box_sizes ): array {
		if ( empty( $box_sizes ) ) {
			return array();
		}

		usort(
			$box_sizes,
			static function ( array $a, array $b ): int {
				return ( $a['length'] * $a['width'] * $a['height'] ) <=> ( $b['length'] * $b['width'] * $b['height'] );
			}
		);

		return end( $box_sizes );
	}

	private static function get_products_from_package( $package, ?Packing_Budget $budget = null ): array {
		$products       = array();
		$default_weight = 0;
		$dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );
		$contents       = self::get_package_contents( $package );
		if ( $budget ) {
			// Bound conversion itself, before constructing one descriptor per row.
			$budget->check_units( count( $contents ) );
			$budget->spend( count( $contents ) );
		}

		foreach ( $contents as $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				return array();
			}

			$product = $cart_item['data'];
			if ( $budget && $product->get_length() && $product->get_width() && $product->get_height() ) {
				$raw_quantity = $cart_item['quantity'] ?? 1;
				$budget->check_units( $raw_quantity === '' ? 1 : $raw_quantity );
			}
			$weight   = $product->get_weight() ? wc_get_weight( $product->get_weight(), 'kg' ) : 0;
			$quantity = ! empty( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;

			if ( ! $product->get_length() || ! $product->get_width() || ! $product->get_height() ) {
				$default_weight += $weight * $quantity;
				continue;
			}

			$products[] = array(
				'name'     => $product->get_name(),
				'length'   => wc_get_dimension( $product->get_length(), 'cm', $dimension_unit ),
				'width'    => wc_get_dimension( $product->get_width(), 'cm', $dimension_unit ),
				'height'   => wc_get_dimension( $product->get_height(), 'cm', $dimension_unit ),
				'weight'   => $weight,
				'quantity' => $quantity,
			);
		}

		if ( empty( $products ) ) {
			$products[] = array(
				'name'     => 'Default package',
				'length'   => 20,
				'width'    => 20,
				'height'   => 20,
				'weight'   => $default_weight > 0 ? $default_weight : 1,
				'quantity' => 1,
			);
		}

		return $products;
	}

	private static function get_package_contents( $package ): array {
		if ( empty( $package['contents'] ) || ! is_array( $package['contents'] ) ) {
			return array();
		}

		return $package['contents'];
	}
}
