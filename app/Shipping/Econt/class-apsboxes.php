<?php
/**
 * Econt automat box sizes.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Econt;

use Nashenski\Shipping\Packer\APSPackage;
use Nashenski\Shipping\Packer\Packing_Budget;
use Nashenski\Shipping\Packer\Packing_Limit_Exceeded;

defined( 'ABSPATH' ) || exit;

/**
 * Packs automat parcels into the Econt automat box sizes and sends them as packs.
 */
class APSBoxes {
	const OPTION_NAME = 'auto_size';

	/**
	 * Econt automat boxes, in cm and kg.
	 *
	 * @var array
	 */
	private static $box_sizes = array(
		array(
			'name'       => 'small',
			'length'     => 44,
			'width'      => 61,
			'height'     => 8,
			'max_weight' => 4.99,
		),
		array(
			'name'       => 'medium',
			'length'     => 44,
			'width'      => 61,
			'height'     => 18,
			'max_weight' => 30.5,
		),
		array(
			'name'       => 'big',
			'length'     => 44,
			'width'      => 61,
			'height'     => 37,
			'max_weight' => 30.5,
		),
	);

	/**
	 * Registers the label filter when automatic sizes are on.
	 */
	public function __construct() {
		if ( nashenski_get_option( 'econt', self::OPTION_NAME ) === 'yes' ) {
			add_filter( 'nashenski_econt_calculate_label', array( __CLASS__, 'calculate_label' ), 20, 2 );
		}
	}

	/**
	 * Replaces the label dimensions with the packed boxes.
	 *
	 * @param array  $request_body Label request.
	 * @param object $method       Shipping method.
	 * @return array
	 * @throws Packing_Limit_Exceeded When the package is too big to pack.
	 */
	public static function calculate_label( $request_body, $method ) {
		if ( ! self::should_handle_method( $method ) || empty( $request_body['label'] ) ) {
			return $request_body;
		}

		try {
			$budget       = $method instanceof Method ? $method->packing_budget() : new Packing_Budget();
			$packed_boxes = APSPackage::pack_package( $method->package, self::$box_sizes, null, $budget );
		} catch ( Packing_Limit_Exceeded $e ) {
			throw $e;
		} catch ( \Throwable $e ) {
			return $request_body;
		}

		if ( empty( $packed_boxes ) ) {
			return $request_body;
		}

		$request_body['label'] = self::apply_packed_boxes_to_label( $request_body['label'], $packed_boxes );

		return $request_body;
	}

	/**
	 * Whether the package fits the largest automat box.
	 *
	 * @param array               $package Shipping package.
	 * @param Packing_Budget|null $budget  Work limit of the packer.
	 * @return bool
	 */
	public static function package_fits_largest_automat( $package, ?Packing_Budget $budget = null ) {
		return APSPackage::package_fits_largest_box( $package, self::$box_sizes, null, $budget );
	}

	/**
	 * Whether the method sends to an automat and has a package.
	 *
	 * @param object $method Shipping method.
	 * @return bool
	 */
	private static function should_handle_method( $method ) {
		return isset( $method->delivery_type ) && 'automat' === $method->delivery_type && ! empty( $method->package );
	}

	/**
	 * Puts the packed boxes on the label as packs.
	 *
	 * @param array $label        Label.
	 * @param array $packed_boxes Packed boxes.
	 * @return array
	 */
	private static function apply_packed_boxes_to_label( $label, $packed_boxes ) {
		$packs = self::build_packs( $packed_boxes );

		if ( empty( $packs ) ) {
			return $label;
		}

		$label['packCount'] = count( $packs );
		$label['packs']     = $packs;

		unset( $label['shipmentDimensionsW'], $label['shipmentDimensionsL'], $label['shipmentDimensionsH'] );

		return $label;
	}

	/**
	 * Packs for the label, one per box.
	 *
	 * @param array $packed_boxes Packed boxes.
	 * @return array
	 */
	private static function build_packs( $packed_boxes ) {
		$packs = array();

		foreach ( $packed_boxes as $packed_box ) {
			if ( empty( $packed_box['box'] ) ) {
				continue;
			}

			$size   = self::get_packed_size( $packed_box );
			$weight = ! empty( $packed_box['weight'] ) ? $packed_box['weight'] : 1;

			$packs[] = array(
				'width'  => $size['width'],
				'height' => $size['height'],
				'length' => $size['length'],
				'weight' => $weight > 0 && $weight < 0.100 ? 0.100 : $weight,
			);
		}

		return $packs;
	}

	/**
	 * Size the items take in a box, else the box size.
	 *
	 * @param array $packed_box Packed box.
	 * @return array
	 */
	private static function get_packed_size( $packed_box ) {
		$box = $packed_box['box'];

		if ( empty( $packed_box['placements'] ) ) {
			return array(
				'width'  => $box['width'],
				'height' => $box['height'],
				'length' => $box['length'],
			);
		}

		$size = array(
			'width'  => 0,
			'height' => 0,
			'length' => 0,
		);

		foreach ( $packed_box['placements'] as $placement ) {
			$size['length'] = max( $size['length'], $placement['origin']['x'] + $placement['dimensions']['length'] );
			$size['width']  = max( $size['width'], $placement['origin']['y'] + $placement['dimensions']['width'] );
			$size['height'] = max( $size['height'], $placement['origin']['z'] + $placement['dimensions']['height'] );
		}

		return $size;
	}
}
