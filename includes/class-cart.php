<?php
/**
 * Cart: finds the tier from the total quantity and sets the discounted prices.
 *
 * The tier follows the quantity in the cart, never a stored choice, so editing
 * the quantity on the cart page or adding more of the same product just works.
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * Prices cart lines that belong to a bundle.
 */
class Cart {

	/**
	 * Current price (minor units, as shown in the shop, with or without tax) of each
	 * cart line before our discount, by cart item key. Kept for the request so
	 * repeated calculations never compound.
	 *
	 * @var int[]
	 */
	private $base = array();

	/**
	 * Stored price of each cart line before our discount, by cart item key.
	 *
	 * @var array
	 */
	private $stored = array();

	/**
	 * Displayed-to-stored price factor of each cart line, by cart item key.
	 *
	 * @var float[]
	 */
	private $factor = array();

	/**
	 * Registers the cart hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'recalculate' ), 20 );
		// WooCommerce does not recalculate totals when it loads the cart from the session
		// on an ordinary page, so item prices (mini-cart, widgets) need setting here too.
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'recalculate' ), 20 );
		add_action( 'woocommerce_cart_emptied', array( $this, 'reset_base' ) );
		add_filter( 'woocommerce_get_item_data', array( $this, 'item_data' ), 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'coupon_valid_for_product' ), 10, 4 );
	}

	/**
	 * Forgets the remembered prices (the cart was emptied).
	 */
	public function reset_base() {
		$this->base   = array();
		$this->stored = array();
		$this->factor = array();
	}

	/**
	 * Sets bundle prices on every cart line that belongs to a pack.
	 *
	 * @param \WC_Cart $cart Cart.
	 */
	public function recalculate( $cart ) {
		if ( ( is_admin() && ! wp_doing_ajax() ) || ! $cart instanceof \WC_Cart ) {
			return;
		}

		$decimals = wc_get_price_decimals();
		$together = 'yes' === Settings::get( 'count_variations' );
		$groups   = array();

		foreach ( $cart->cart_contents as $key => $item ) {
			unset( $cart->cart_contents[ $key ]['bundletiers'] );

			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}
			$tiers = Tiers::for_product( $product );
			if ( ! $tiers ) {
				continue;
			}
			if ( ! isset( $this->base[ $key ] ) ) {
				$price = $product->get_price();
				if ( '' === $price || null === $price ) {
					continue;
				}
				// Same price the product page shows, so a fixed amount means the same in both places.
				$this->stored[ $key ] = $price;
				$this->factor[ $key ] = Tax_Basis::factor( $product );
				$this->base[ $key ]   = Tax_Basis::to_display_minor( $product, $price, $this->factor[ $key ], $decimals );
			}

			$parent = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$gkey   = $together ? 'p' . $parent : 'k' . $key;
			if ( ! isset( $groups[ $gkey ] ) ) {
				$groups[ $gkey ] = array(
					'tiers' => $tiers,
					'keys'  => array(),
				);
			}
			$groups[ $gkey ]['keys'][] = $key;
		}

		foreach ( $groups as $group ) {
			$this->price_group( $cart, $group['keys'], $group['tiers'], $decimals );
		}
	}

	/**
	 * Prices one pack (all lines counted together).
	 *
	 * @param \WC_Cart $cart     Cart.
	 * @param string[] $keys     Cart item keys in the pack, in cart order.
	 * @param array    $tiers    Tiers for the product.
	 * @param int      $decimals Currency decimals.
	 */
	private function price_group( $cart, array $keys, array $tiers, $decimals ) {
		$pack_qty = 0;
		foreach ( $keys as $key ) {
			$pack_qty += (int) $cart->cart_contents[ $key ]['quantity'];
		}
		$tier = Pricing::find_tier( $tiers, $pack_qty );
		if ( ! $tier ) {
			return;
		}

		// Work per line, never per item, so a huge quantity costs nothing extra.
		$lines = array();
		foreach ( $keys as $key ) {
			$lines[] = array(
				'base' => $this->base[ $key ],
				'qty'  => (int) $cart->cart_contents[ $key ]['quantity'],
			);
		}
		$result = Pricing::apply_lines( $lines, $tier['type'], $tier['value'], $decimals, (int) $tier['qty'] );

		foreach ( $keys as $i => $key ) {
			$qty = (int) $cart->cart_contents[ $key ]['quantity'];
			if ( $qty < 1 ) {
				continue;
			}
			$line_total = $result['lines'][ $i ];
			$line_full  = $this->base[ $key ] * $qty;

			// A single price per unit: the exact line total divided by the quantity,
			// so WooCommerce's own multiplication gives back the exact line total.
			// The line total is in the shop display price, WooCommerce wants the stored basis.
			// Lines without a discount keep their stored price untouched.
			if ( $line_total >= $line_full ) {
				$unit_price = $this->stored[ $key ];
			} else {
				$unit_price = Tax_Basis::to_stored( $line_total / $qty, $this->factor[ $key ], $decimals );
			}
			$cart->cart_contents[ $key ]['data']->set_price( $unit_price );

			$cart->cart_contents[ $key ]['bundletiers'] = array(
				'tier'     => $tier,
				'pack_qty' => $pack_qty,
				'saved'    => max( 0, $line_full - $line_total ),
			);
		}
	}

	/**
	 * Text such as "−15%" or "−5.10€" for the bundle shown in cart and orders.
	 *
	 * @param array $bundle The 'bundletiers' cart item data.
	 * @return string
	 */
	public static function describe( array $bundle ) {
		$tier = $bundle['tier'];
		if ( Pricing::PERCENT === $tier['type'] ) {
			$num = rtrim( rtrim( number_format( (float) $tier['value'], 2, '.', '' ), '0' ), '.' );
			return '−' . $num . '%';
		}
		return '−' . Frontend::money( $bundle['saved'] );
	}

	/**
	 * Shows the pack under the product name (cart, checkout, mini-cart).
	 *
	 * @param array $item_data Existing rows.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function item_data( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['bundletiers'] ) && $cart_item['bundletiers']['saved'] > 0 ) {
			$item_data[] = array(
				'key'   => Settings::get( 'text_cart_label' ),
				'value' => Tiers::label( $cart_item['bundletiers']['tier'] ) . ' (' . self::describe( $cart_item['bundletiers'] ) . ')',
			);
		}
		return $item_data;
	}

	/**
	 * Optionally keeps coupons off products that already get a bundle discount.
	 *
	 * @param bool        $valid   Whether the coupon applies.
	 * @param \WC_Product $product Product.
	 * @param \WC_Coupon  $coupon  Coupon.
	 * @param array       $values  Cart item.
	 * @return bool
	 */
	public function coupon_valid_for_product( $valid, $product, $coupon, $values ) {
		if ( $valid && 'yes' === Settings::get( 'no_discount_coupon' ) && ! empty( $values['bundletiers'] ) && $values['bundletiers']['saved'] > 0 ) {
			return false;
		}
		return $valid;
	}
}
