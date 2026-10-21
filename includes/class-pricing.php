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
		return self::apply_mixed( array_fill( 0, $qty, $base_minor ), $type, $value, $decimals, $qty );
	}

	/**
	 * Applies a discount to a pack whose items may have different current prices
	 * (e.g. an XL at 20.00 and an XXL at 30.00 counted together).
	 *
	 * Percent and fixed_item work per item. The fixed_pack type fixes the price of a pack
	 * of $tier_qty items, which makes every item cost the pack price divided by $tier_qty;
	 * with more items than the tier needs, each extra item costs the same. The total is
	 * shared between the items in proportion to their prices.
	 *
	 * @param int[]            $bases    Current unit price of each item, minor units.
	 * @param string           $type     percent | fixed_item | fixed_pack.
	 * @param float|int|string $value    See apply().
	 * @param int              $decimals Currency decimals.
	 * @param int              $tier_qty Items the fixed_pack price is for (the tier's quantity). 0 means all the items given.
	 * @return array Same shape as apply(): units, total, full, saved, unit_price.
	 */
	public static function apply_mixed( array $bases, $type, $value, $decimals = 2, $tier_qty = 0 ) {
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
				// The pack price is for $tier_qty items; every item costs the same share of it.
				$pack_qty = (int) $tier_qty >= 1 ? (int) $tier_qty : $count;
				$pack     = max( 0, self::to_minor( $value, $decimals ) );
				// A discount can never raise the price.
				$total = min( $full, self::pack_share( $pack, $count, $pack_qty ) );
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
	 * Applies a discount to lines of a cart, without making an entry for every item.
	 *
	 * Gives the same line totals as apply_mixed() would for the items spread out
	 * (the same rounding, the same fixed_pack sharing), but its work and memory grow
	 * with the number of lines, never with the quantity.
	 *
	 * @param array[]          $lines    Lines, each array( 'base' => unit price in minor units, 'qty' => items ).
	 * @param string           $type     percent | fixed_item | fixed_pack.
	 * @param float|int|string $value    See apply().
	 * @param int              $decimals Currency decimals.
	 * @param int              $tier_qty See apply_mixed().
	 * @return array {
	 *     @type int[] $lines      Total of each line in minor units, in the order given.
	 *     @type int   $total      Total of all lines.
	 *     @type int   $full       Total without discount.
	 *     @type int   $saved      $full - $total (never negative).
	 *     @type int   $unit_price Price per item, $total / items rounded half up.
	 * }
	 */
	public static function apply_lines( array $lines, $type, $value, $decimals = 2, $tier_qty = 0 ) {
		$clean = array();
		$count = 0;
		$full  = 0;
		foreach ( array_values( $lines ) as $line ) {
			$base    = max( 0, (int) $line['base'] );
			$qty     = max( 0, (int) $line['qty'] );
			$clean[] = array( $base, $qty );
			$count  += $qty;
			$full   += $base * $qty;
		}
		$divisor = max( 1, $count );

		$totals = array();
		switch ( $type ) {
			case self::PERCENT:
				$bp = (int) round( min( 100, max( 0, (float) $value ) ) * 100 );
				foreach ( $clean as $line ) {
					$totals[] = $line[1] * self::div_round( $line[0] * ( 10000 - $bp ), 10000 );
				}
				break;

			case self::FIXED_ITEM:
				$off = max( 0, self::to_minor( $value, $decimals ) );
				foreach ( $clean as $line ) {
					$totals[] = $line[1] * max( 0, $line[0] - $off );
				}
				break;

			case self::FIXED_PACK:
				$pack_qty = (int) $tier_qty >= 1 ? (int) $tier_qty : $divisor;
				$pack     = max( 0, self::to_minor( $value, $decimals ) );
				$total    = min( $full, self::pack_share( $pack, $divisor, $pack_qty ) );
				$totals   = self::allocate_lines( $total, $clean );
				break;

			default:
				foreach ( $clean as $line ) {
					$totals[] = $line[0] * $line[1];
				}
		}

		$sum = array_sum( $totals );

		return array(
			'lines'      => $totals,
			'total'      => $sum,
			'full'       => $full,
			'saved'      => max( 0, $full - $sum ),
			'unit_price' => self::div_round( $sum, $divisor ),
		);
	}

	/**
	 * Same sharing as allocate() for lines of equal items: every item of a line gets
	 * the same part and the leftover cents go to the same items allocate() picks,
	 * but the work is per line, not per item.
	 *
	 * @param int     $total Amount to share, minor units.
	 * @param array[] $lines Lines, each array( unit price, items ).
	 * @return int[] Total of each line.
	 */
	private static function allocate_lines( $total, array $lines ) {
		$sum   = 0;
		$max_w = 0;
		$items = 0;
		foreach ( $lines as $line ) {
			$sum   += $line[0] * $line[1];
			$max_w  = max( $max_w, $line[0] );
			$items += $line[1];
		}
		if ( 0 === $items ) {
			return array_fill( 0, count( $lines ), 0 );
		}
		$even = $sum <= 0;
		if ( $even ) {
			$sum   = $items;
			$max_w = 1;
		}

		// Beyond what whole-number maths can hold, share by ratio and give the rest to the last line.
		if ( (float) $total * (float) $max_w >= (float) PHP_INT_MAX ) {
			$parts = array();
			$given = 0;
			$last  = 0;
			foreach ( $lines as $i => $line ) {
				$weight      = $even ? $line[1] : $line[0] * $line[1];
				$parts[ $i ] = (int) floor( (float) $total * ( (float) $weight / (float) $sum ) );
				$given      += $parts[ $i ];
				$last        = $line[1] > 0 ? $i : $last;
			}
			$parts[ $last ] += $total - $given;
			return $parts;
		}

		$parts      = array();
		$remainders = array();
		$given      = 0;
		foreach ( $lines as $i => $line ) {
			$w                = $even ? 1 : $line[0];
			$each             = intdiv( $total * $w, $sum );
			$remainders[ $i ] = ( $total * $w ) % $sum;
			$parts[ $i ]      = $each * $line[1];
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
			foreach ( $order as $i ) {
				if ( $left <= 0 ) {
					break;
				}
				$take         = min( $left, $lines[ $i ][1] );
				$parts[ $i ] += $take;
				$left        -= $take;
			}
		}
		return $parts;
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
	 * Price of $count items when a pack of $pack_qty costs $pack (minor units), rounded half up.
	 * Falls back to float maths only when the whole-number product would overflow.
	 *
	 * @param int $pack     Pack price, minor units.
	 * @param int $count    Items.
	 * @param int $pack_qty Items the pack price is for.
	 * @return int
	 */
	private static function pack_share( $pack, $count, $pack_qty ) {
		if ( $pack > 0 && $count > intdiv( PHP_INT_MAX - $pack_qty, 2 * $pack ) ) {
			return (int) min( (float) PHP_INT_MAX, round( (float) $pack * (float) $count / (float) $pack_qty ) );
		}
		return self::div_round( $pack * $count, $pack_qty );
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
