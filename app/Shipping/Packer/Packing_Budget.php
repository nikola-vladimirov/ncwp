<?php // phpcs:disable WordPress.Files.FileName -- PSR-4 autoloading needs the class name as the file name.
/**
 * Packing work limits.
 *
 * @package Nashenski_Commerce
 */

namespace Nashenski\Shipping\Packer;

defined( 'ABSPATH' ) || exit;

/**
 * Optional per-calculation limits used by Speedy. Other couriers retain their
 * existing packing behavior unless they explicitly supply a budget.
 *
 * Work accounts for unit expansion, carton candidates, sorting estimates and
 * orientation checks. Exhaustion rejects the whole quote, never a partial cart.
 */
class Packing_Budget {
	const MAX_UNITS      = 1000;
	const MAX_PARCELS    = 1000;
	const MAX_CANDIDATES = 2048;
	const MAX_WORK       = 2000000;

	/**
	 * Work left.
	 *
	 * @var int
	 */
	private $remaining;

	/**
	 * Whether a limit was crossed; every later call then throws.
	 *
	 * @var bool
	 */
	private $rejected = false;

	/**
	 * Sets up the budget.
	 *
	 * @param int $work Work allowed, at most MAX_WORK.
	 */
	public function __construct( int $work = self::MAX_WORK ) {
		$this->remaining = max( 0, min( self::MAX_WORK, $work ) );
	}

	/**
	 * Spends work.
	 *
	 * @param int $work Work to spend.
	 * @throws Packing_Limit_Exceeded When the budget is used up or was rejected.
	 */
	public function spend( int $work = 1 ): void {
		if ( $this->rejected || $work < 0 || $work > $this->remaining ) {
			$this->rejected = true;
			throw new Packing_Limit_Exceeded( 'Shipping calculation exceeds its safe work limit.' );
		}

		$this->remaining -= $work;
	}

	/**
	 * Rejects a unit count that is not a finite number up to MAX_UNITS.
	 *
	 * @param mixed $count Unit count.
	 * @throws Packing_Limit_Exceeded When the count is out of range.
	 */
	public function check_units( $count ): void {
		if ( ! is_numeric( $count ) || ! is_finite( (float) $count ) || $count < 0 || $count > self::MAX_UNITS ) {
			$this->rejected = true;
		}

		$this->spend( 0 );
	}

	/**
	 * Rejects more than MAX_CANDIDATES carton candidates.
	 *
	 * @param int $count Candidate count.
	 * @throws Packing_Limit_Exceeded When the count is too high.
	 */
	public function check_candidates( int $count ): void {
		if ( $count > self::MAX_CANDIDATES ) {
			$this->rejected = true;
		}

		$this->spend( 0 );
	}

	/**
	 * Validate before casting a ratio to int or allocating parcel arrays.
	 *
	 * @param mixed $count            Parcel count.
	 * @param int   $commercial_limit Courier's parcel limit; 0 for none.
	 * @throws Packing_Limit_Exceeded When the count is out of range.
	 */
	public static function check_parcels( $count, int $commercial_limit = 0 ): void {
		$limit = $commercial_limit > 0 ? min( $commercial_limit, self::MAX_PARCELS ) : self::MAX_PARCELS;

		if ( ! is_numeric( $count ) || ! is_finite( (float) $count ) || $count < 1 || $count > $limit ) {
			throw new Packing_Limit_Exceeded( 'Shipping calculation exceeds its safe parcel limit.' );
		}
	}
}
