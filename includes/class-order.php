<?php
/**
 * Order: records the bundle on each order line.
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * Records the bundle on order lines.
 */
class Order {

	/**
	 * Meta key of the visible bundle line. The text shown to people is the "cart label"
	 * setting, applied when the line is displayed, so changing that text never changes
	 * what is stored on orders.
	 */
	const PACK_KEY = 'bundletiers_pack';

	/**
	 * Registers the order hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_line_meta' ), 10, 3 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'display_key' ), 10, 2 );
	}

	/**
	 * Shows the cart label as the name of the visible bundle line (admin, emails, order pages).
	 *
	 * @param string        $display_key Name that would be shown.
	 * @param \WC_Meta_Data $meta        Order item meta.
	 * @return string
	 */
	public function display_key( $display_key, $meta ) {
		if ( self::PACK_KEY !== $meta->key ) {
			return $display_key;
		}
		$label = trim( (string) Settings::get( 'text_cart_label' ) );
		return '' === $label ? __( 'Bundle', 'bundletiers' ) : $label;
	}

	/**
	 * Adds the bundle data to an order line.
	 *
	 * @param \WC_Order_Item_Product $item          Order line.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values        Cart item.
	 */
	public function add_line_meta( $item, $cart_item_key, $values ) {
		if ( empty( $values['bundletiers'] ) ) {
			return;
		}
		$bundle = $values['bundletiers'];

		// Hidden record for reports and later use.
		$item->add_meta_data(
			'_bundletiers_tier',
			array(
				'qty'      => (int) $bundle['tier']['qty'],
				'type'     => $bundle['tier']['type'],
				'value'    => (float) $bundle['tier']['value'],
				'pack_qty' => (int) $bundle['pack_qty'],
				'saved'    => (int) $bundle['saved'],
			),
			true
		);

		// Visible line in the admin and in emails, only when there is a discount.
		if ( $bundle['saved'] > 0 ) {
			$item->add_meta_data(
				self::PACK_KEY,
				Tiers::label( $bundle['tier'] ) . ' (' . Cart::describe( $bundle ) . ')',
				true
			);
		}
	}
}
