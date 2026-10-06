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
	 * Registers the order hook.
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_line_meta' ), 10, 3 );
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
				Settings::get( 'text_cart_label' ),
				Tiers::label( $bundle['tier'] ) . ' (' . Cart::describe( $bundle ) . ')',
				true
			);
		}
	}
}
