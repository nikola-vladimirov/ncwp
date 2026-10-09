<?php
namespace Nashenski\Pricing;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping price from a courier's public price list.
 *
 * The courier shipping methods call fallback() right after asking the courier for a price.
 * When there is no working login, or the courier answered with an error, the price comes
 * from the price list instead, so customers still see a price and can order.
 */
class Public_Price {
	/**
	 * Delivery type of a shipping method -> price list column.
	 */
	public static function kind( $delivery_type ) {
		$map = array(
			'office' => 'office',
			'automat' => 'locker',
			'locker' => 'locker',
			'apm' => 'locker',
			'address' => 'address',
		);

		return $map[ $delivery_type ] ?? 'address';
	}

	/**
	 * Price with VAT for one parcel, or null when the list has no price for it.
	 *
	 * @param string $courier     econt, speedy, pigeon or cvc.
	 * @param string $kind        office, locker or address.
	 * @param float  $weight      Weight in kg.
	 * @param float  $cod_amount  Amount collected on delivery (0 when not cash on delivery).
	 */
	public static function calculate( $courier, $kind, $weight, $cod_amount = 0.0 ) {
		$list = Price_Lists::get( $courier );

		if ( empty( $list['bands'] ) ) {
			return null;
		}

		$weight = max( 0.0, (float) $weight );
		$price = null;

		foreach ( $list['bands'] as $band ) {
			if ( $weight <= $band['to'] ) {
				$price = $band[ $kind ] ?? null;

				// A column left empty falls back to the address price (for example CVC offices).
				if ( null === $price ) {
					$price = $band['address'] ?? null;
				}

				break;
			}
		}

		if ( null === $price ) {
			$last = end( $list['bands'] );
			$base = $list['over']['base'][ $kind ] ?? $list['over']['base']['address'] ?? null;
			$per_kg = $list['over']['per_kg'][ $kind ] ?? $list['over']['per_kg']['address'] ?? 0;

			if ( null === $base ) {
				return null;
			}

			$price = $base + (float) $per_kg * ceil( $weight - $last['to'] );
		}

		$price *= 1 + $list['surcharge'] / 100;

		if ( ! $list['vat_included'] ) {
			$price *= 1.2;
		}

		if ( $cod_amount > 0 && $list['cod']['percent'] > 0 ) {
			$price += max( $list['cod']['min'], $cod_amount * $list['cod']['percent'] / 100 );
		}

		return round( apply_filters( 'nashenski/public_price', $price, $courier, $kind, $weight, $cod_amount ), 2 );
	}

	/**
	 * Package weight in kg.
	 */
	public static function package_weight( $package ) {
		$weight = 0.0;

		foreach ( (array) ( $package['contents'] ?? array() ) as $item ) {
			if ( ! empty( $item['data'] ) && is_object( $item['data'] ) && $item['data']->get_weight() ) {
				$weight += wc_get_weight( (float) $item['data']->get_weight(), 'kg' ) * (int) $item['quantity'];
			}
		}

		return $weight;
	}

	/**
	 * Goods value of a package with tax, the amount collected on delivery.
	 */
	public static function package_value( $package ) {
		$value = 0.0;

		foreach ( (array) ( $package['contents'] ?? array() ) as $item ) {
			$value += (float) ( $item['line_total'] ?? 0 ) + (float) ( $item['line_tax'] ?? 0 );
		}

		return $value;
	}

	/**
	 * Use the public price when the courier gave none.
	 *
	 * @param string $courier      econt, speedy, pigeon or cvc.
	 * @param array  $request_data What the method got from the courier: array( 'price' => ... ) or array( 'errors' => ... ).
	 * @param object $method       The shipping method (delivery_type, package, cookie_data).
	 * @return array The same shape, with 'price' and 'public_price' => true when the list was used.
	 */
	public static function fallback( $courier, $request_data, $method ) {
		if ( ! empty( $request_data['price'] ) && empty( $request_data['errors'] ) ) {
			return $request_data;
		}

		if ( 'no' === nashenski_get_option( 'prices', 'use_public_' . $courier ) ) {
			return $request_data;
		}

		$package = is_array( $method->package ?? null ) ? $method->package : array();
		$cookie = is_array( $method->cookie_data ?? null ) ? $method->cookie_data : array();
		$cod = isset( $cookie['payment'] ) && 'cod' === $cookie['payment'] ? self::package_value( $package ) : 0.0;
		$gross = self::calculate( $courier, self::kind( $method->delivery_type ?? '' ), self::package_weight( $package ), $cod );

		if ( null === $gross ) {
			return $request_data;
		}

		return array(
			'price' => nashenski_tax_based_price( $gross ),
			'public_price' => true,
		);
	}
}
