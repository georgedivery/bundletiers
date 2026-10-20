<?php
/**
 * Tax basis: keeps the product page and the cart on the same side of the VAT line.
 *
 * WooCommerce stores a product price as entered (with or without tax, "Prices entered
 * with tax") and shows it as set in "Display prices in the shop". The bundle is shown
 * on the product page in the displayed price, so the cart has to take its discount in
 * the same price and then hand WooCommerce a price in the stored basis again.
 *
 * Everything here is WooCommerce's own tax logic (rates, customer location, exemption,
 * tax class), asked through wc_get_price_to_display().
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * Converts between the stored price of a product and the price shown in the shop.
 */
class Tax_Basis {

	/**
	 * How many times bigger the displayed price is than the stored one.
	 *
	 * 1 when both use the same basis (for example entered and shown with tax), when
	 * taxes are off, or when the product is not taxable. Above 1 when the price is
	 * entered without tax and shown with tax, below 1 for the opposite.
	 *
	 * @param \WC_Product $product Product.
	 * @return float
	 */
	public static function factor( $product ) {
		if ( ! wc_tax_enabled() || ! $product->is_taxable() ) {
			return 1.0;
		}

		// A large probe price keeps the rounding of the displayed price out of the ratio.
		$probe = 1000000;
		$shown = (float) wc_get_price_to_display( $product, array( 'price' => $probe ) );

		/**
		 * Filters the displayed-to-stored price factor.
		 *
		 * @param float       $factor  Displayed price divided by stored price.
		 * @param \WC_Product $product Product.
		 */
		$factor = (float) apply_filters( 'bundletiers_tax_factor', $shown > 0 ? $shown / $probe : 1.0, $product );

		return $factor > 0 ? $factor : 1.0;
	}

	/**
	 * The price of a product as shown in the shop, in minor units.
	 *
	 * @param \WC_Product      $product  Product.
	 * @param float|int|string $price    Stored price (as returned by get_price()).
	 * @param float            $factor   Factor from factor().
	 * @param int              $decimals Currency decimals.
	 * @return int
	 */
	public static function to_display_minor( $product, $price, $factor, $decimals ) {
		if ( 1.0 === $factor ) {
			return Pricing::to_minor( $price, $decimals );
		}
		return Pricing::to_minor( wc_get_price_to_display( $product, array( 'price' => $price ) ), $decimals );
	}

	/**
	 * A displayed amount (minor units) turned back into a stored price (major units).
	 *
	 * @param int   $minor    Amount in the shop display basis, minor units.
	 * @param float $factor   Factor from factor().
	 * @param int   $decimals Currency decimals.
	 * @return float
	 */
	public static function to_stored( $minor, $factor, $decimals ) {
		return $minor / pow( 10, $decimals ) / $factor;
	}
}
