<?php
/**
 * Pure pricing maths. No WordPress or WooCommerce dependencies, so it can be
 * tested with plain PHP (see tests/pricing-test.php).
 *
 * All amounts inside this class are integers in minor units (e.g. cents).
 * Use to_minor() / from_minor() at the boundary with WooCommerce prices.
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * Integer-based discount maths.
 */
class Pricing {

	const PERCENT    = 'percent';
	const FIXED_ITEM = 'fixed_item';
	const FIXED_PACK = 'fixed_pack';

	/**
	 * Converts a price in major units (e.g. 25.51) to integer minor units.
	 *
	 * @param float|int|string $amount   Price in major units.
	 * @param int              $decimals Currency decimals.
	 * @return int
	 */
	public static function to_minor( $amount, $decimals = 2 ) {
		return (int) round( (float) $amount * pow( 10, $decimals ) );
	}

	/**
	 * Converts integer minor units back to a float in major units.
	 *
	 * @param int $minor    Amount in minor units.
	 * @param int $decimals Currency decimals.
	 * @return float
	 */
	public static function from_minor( $minor, $decimals = 2 ) {
		return round( $minor / pow( 10, $decimals ), $decimals );
	}

	/**
	 * Finds the tier that applies to a quantity: the one with the highest
	 * count that is not above the quantity. Returns null if none fits.
	 *
	 * @param array $tiers Tiers, each with an integer 'qty' key.
	 * @param int   $qty   Total quantity.
	 * @return array|null
	 */
	public static function find_tier( array $tiers, $qty ) {
		$found = null;
		foreach ( $tiers as $tier ) {
			$count = isset( $tier['qty'] ) ? (int) $tier['qty'] : 0;
			if ( $count >= 1 && $count <= $qty && ( null === $found || $count > (int) $found['qty'] ) ) {
				$found = $tier;
			}
		}
		return $found;
	}

	/**
	 * Applies a discount to $qty items at the same current unit price.
	 *
	 * @param int              $base_minor Current unit price in minor units (sale price, role price, etc.).
	 * @param int              $qty        Number of items in the pack (>= 1).
	 * @param string           $type       percent | fixed_item | fixed_pack.
	 * @param float|int|string $value      percent: 0-100. fixed_item: amount off each item. fixed_pack: price of the whole pack. Money in major units.
	 * @param int              $decimals   Currency decimals.
	 * @return array {
	 *     @type int[] $units       Price of each item in minor units; sums exactly to $total.
	 *     @type int   $total       Pack total in minor units.
	 *     @type int   $full        Total without discount.
	 *     @type int   $saved       $full - $total (never negative).
	 *     @type int   $unit_price  Price per item, $total / $qty rounded half up.
	 * }
	 */
	public static function apply( $base_minor, $qty, $type, $value, $decimals = 2 ) {
		$base_minor = max( 0, (int) $base_minor );
		$qty        = max( 1, (int) $qty );
		return self::apply_mixed( array_fill( 0, $qty, $base_minor ), $type, $value, $decimals );
	}

	/**
	 * Applies a discount to a pack whose items may have different current prices
	 * (e.g. an XL at 20.00 and an XXL at 30.00 counted together).
	 *
	 * Percent and fixed_item work per item. The fixed_pack type fixes the price of the
	 * whole pack and shares it between the items in proportion to their prices.
	 *
	 * @param int[]            $bases    Current unit price of each item, minor units.
	 * @param string           $type     percent | fixed_item | fixed_pack.
	 * @param float|int|string $value    See apply().
	 * @param int              $decimals Currency decimals.
	 * @return array Same shape as apply(): units, total, full, saved, unit_price.
	 */
	public static function apply_mixed( array $bases, $type, $value, $decimals = 2 ) {
		$bases = array_map(
			static function ( $b ) {
				return max( 0, (int) $b );
			},
			array_values( $bases )
		);
		$count = max( 1, count( $bases ) );
		$full  = array_sum( $bases );

		switch ( $type ) {
			case self::PERCENT:
				$bp    = (int) round( min( 100, max( 0, (float) $value ) ) * 100 );
				$units = array();
				foreach ( $bases as $b ) {
					$units[] = self::div_round( $b * ( 10000 - $bp ), 10000 );
				}
				break;

			case self::FIXED_ITEM:
				$off   = max( 0, self::to_minor( $value, $decimals ) );
				$units = array();
				foreach ( $bases as $b ) {
					$units[] = max( 0, $b - $off );
				}
				break;

			case self::FIXED_PACK:
				// A discount can never raise the price.
				$total = min( $full, max( 0, self::to_minor( $value, $decimals ) ) );
				$units = self::allocate( $total, $bases );
				break;

			default:
				$units = $bases;
		}

		$total = array_sum( $units );

		return array(
			'units'      => $units,
			'total'      => $total,
			'full'       => $full,
			'saved'      => max( 0, $full - $total ),
			'unit_price' => self::div_round( $total, $count ),
		);
	}

	/**
	 * Splits $total between items in proportion to their weights so the parts
	 * add up exactly (largest remainder; ties go to the earlier item).
	 *
	 * @param int   $total   Amount to share, minor units.
	 * @param int[] $weights Weight of each item (their current prices).
	 * @return int[]
	 */
	public static function allocate( $total, array $weights ) {
		$weights = array_values( $weights );
		$sum     = array_sum( $weights );
		$n       = count( $weights );
		if ( 0 === $n ) {
			return array();
		}
		if ( $sum <= 0 ) {
			// No price information: share evenly.
			$weights = array_fill( 0, $n, 1 );
			$sum     = $n;
		}

		$parts      = array();
		$remainders = array();
		$given      = 0;
		foreach ( $weights as $i => $w ) {
			$parts[ $i ]      = intdiv( $total * $w, $sum );
			$remainders[ $i ] = ( $total * $w ) % $sum;
			$given           += $parts[ $i ];
		}
		$left = $total - $given;
		if ( $left > 0 ) {
			$order = array_keys( $remainders );
			usort(
				$order,
				static function ( $a, $b ) use ( $remainders ) {
					$by_remainder = $remainders[ $b ] <=> $remainders[ $a ];
					return 0 !== $by_remainder ? $by_remainder : $a <=> $b;
				}
			);
			foreach ( array_slice( $order, 0, $left ) as $i ) {
				++$parts[ $i ];
			}
		}
		return $parts;
	}

	/**
	 * Integer division rounded half up (for non-negative numbers).
	 *
	 * @param int $a Numerator.
	 * @param int $b Denominator.
	 * @return int
	 */
	private static function div_round( $a, $b ) {
		return intdiv( 2 * $a + $b, 2 * $b );
	}
}
