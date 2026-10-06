<?php
/**
 * Tier data: defaults, validation and per-product lookup.
 *
 * A tier is: qty (int >= 1), type (percent|fixed_item|fixed_pack), value (float),
 * label (string), badge (string), default (bool, exactly one per list).
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * Tier data: defaults, validation and lookup.
 */
class Tiers {

	const META_ENABLED = '_bundletiers_enabled';
	const META_MODE    = '_bundletiers_mode';
	const META_TIERS   = '_bundletiers_tiers';

	/**
	 * Allowed discount types.
	 *
	 * @return string[]
	 */
	public static function types() {
		return array( Pricing::PERCENT, Pricing::FIXED_ITEM, Pricing::FIXED_PACK );
	}

	/**
	 * Labels for the discount types.
	 *
	 * @return array
	 */
	public static function type_labels() {
		return array(
			Pricing::PERCENT    => __( 'Percent off', 'bundletiers' ),
			Pricing::FIXED_ITEM => __( 'Amount off each item', 'bundletiers' ),
			Pricing::FIXED_PACK => __( 'Fixed pack price', 'bundletiers' ),
		);
	}

	/**
	 * Tiers used when nothing has been saved yet.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			array(
				'qty'     => 1,
				'type'    => Pricing::PERCENT,
				'value'   => 0.0,
				'label'   => '',
				'badge'   => '',
				'default' => false,
			),
			array(
				'qty'     => 2,
				'type'    => Pricing::PERCENT,
				'value'   => 10.0,
				'label'   => '',
				'badge'   => __( 'Most popular', 'bundletiers' ),
				'default' => true,
			),
			array(
				'qty'     => 3,
				'type'    => Pricing::PERCENT,
				'value'   => 15.0,
				'label'   => '',
				'badge'   => '',
				'default' => false,
			),
		);
	}

	/**
	 * Validates submitted tiers and returns a clean, sorted list.
	 *
	 * Expected input: rows keyed by any unique key, each an array with
	 * qty/type/value/label/badge, plus an optional 'default' entry holding the
	 * key of the default row.
	 *
	 * Rules: qty >= 1 and unique (first one wins), known type, value within
	 * range (percent 0-100, amounts >= 0), exactly one default, and a qty 1
	 * tier always present.
	 *
	 * @param mixed    $raw      Raw (unslashed) input.
	 * @param string[] $messages Receives human-readable notes about what was corrected.
	 * @return array
	 */
	public static function sanitize( $raw, &$messages = array() ) {
		$messages = array();
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$default_key = isset( $raw['default'] ) ? (string) $raw['default'] : null;
		unset( $raw['default'] );

		$tiers = array();
		$seen  = array();
		foreach ( $raw as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$qty = isset( $row['qty'] ) ? (int) $row['qty'] : 0;
			if ( $qty < 1 ) {
				$messages[] = __( 'A tier with a quantity below 1 was removed.', 'bundletiers' );
				continue;
			}
			if ( isset( $seen[ $qty ] ) ) {
				/* translators: %d: quantity */
				$messages[] = sprintf( __( 'Duplicate tier for quantity %d was removed.', 'bundletiers' ), $qty );
				continue;
			}
			$seen[ $qty ] = true;

			$type = isset( $row['type'] ) ? (string) $row['type'] : '';
			if ( ! in_array( $type, self::types(), true ) ) {
				$type = Pricing::PERCENT;
			}

			$value = isset( $row['value'] ) ? self::parse_number( $row['value'] ) : 0.0;
			$value = max( 0.0, $value );
			if ( Pricing::PERCENT === $type ) {
				$value = min( 100.0, $value );
			}

			$tiers[] = array(
				'qty'     => $qty,
				'type'    => $type,
				'value'   => $value,
				'label'   => isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '',
				'badge'   => isset( $row['badge'] ) ? sanitize_text_field( (string) $row['badge'] ) : '',
				'default' => ( (string) $key === $default_key ),
			);
		}

		if ( ! isset( $seen[1] ) && $tiers ) {
			array_unshift(
				$tiers,
				array(
					'qty'     => 1,
					'type'    => Pricing::PERCENT,
					'value'   => 0.0,
					'label'   => '',
					'badge'   => '',
					'default' => false,
				)
			);
			$messages[] = __( 'A tier for quantity 1 (no discount) was added; it is required.', 'bundletiers' );
		}

		usort(
			$tiers,
			static function ( $a, $b ) {
				return $a['qty'] <=> $b['qty'];
			}
		);

		// Exactly one default.
		$has_default = false;
		foreach ( $tiers as $i => $tier ) {
			if ( $tier['default'] && ! $has_default ) {
				$has_default = true;
			} else {
				$tiers[ $i ]['default'] = false;
			}
		}
		if ( $tiers && ! $has_default ) {
			$tiers[0]['default'] = true;
		}

		return $tiers;
	}

	/**
	 * Display name of a tier: its own label, or "N items".
	 *
	 * @param array $tier Tier.
	 * @return string
	 */
	public static function label( array $tier ) {
		if ( ! empty( $tier['label'] ) ) {
			return $tier['label'];
		}
		return sprintf(
			/* translators: %d: number of items in the pack */
			_n( '%d item', '%d items', (int) $tier['qty'], 'bundletiers' ),
			(int) $tier['qty']
		);
	}

	/**
	 * Parses a typed number. A lone comma with no dot is a decimal comma
	 * ("45,92"); otherwise WooCommerce's own decimal handling applies.
	 *
	 * @param mixed $input Typed value.
	 * @return float
	 */
	private static function parse_number( $input ) {
		$input = trim( (string) $input );
		if ( false === strpos( $input, '.' ) && 1 === substr_count( $input, ',' ) ) {
			$input = str_replace( ',', '.', $input );
		}
		return (float) wc_format_decimal( $input );
	}

	/**
	 * Tiers that apply to a product, or an empty array when the feature is off.
	 *
	 * @param \WC_Product|int $product Product (a variation resolves to its parent).
	 * @return array
	 */
	public static function for_product( $product ) {
		$product = wc_get_product( $product );
		if ( ! $product ) {
			return array();
		}
		if ( $product->is_type( 'variation' ) ) {
			$product = wc_get_product( $product->get_parent_id() );
			if ( ! $product ) {
				return array();
			}
		}
		if ( 'yes' !== $product->get_meta( self::META_ENABLED ) ) {
			return array();
		}
		if ( 'custom' === $product->get_meta( self::META_MODE ) ) {
			$custom = $product->get_meta( self::META_TIERS );
			if ( is_array( $custom ) && $custom ) {
				return $custom;
			}
		}
		return Settings::get( 'tiers' );
	}
}
