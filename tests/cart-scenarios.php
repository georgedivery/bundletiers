<?php
/**
 * Cart/order scenarios against a real WooCommerce install (local only!).
 *
 * Run from the WordPress root:
 *   wp eval-file wp-content/plugins/bundletiers/tests/cart-scenarios.php
 *
 * Needs a variable product with at least two variations (default: id 26).
 * Temporarily changes the BundleTiers settings and creates/deletes a test
 * product, coupon and order; everything is restored at the end.
 */

use BundleTiers\Settings;
use BundleTiers\Pricing;

if ( 'local.brivanto.com' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	WP_CLI::error( 'Refusing to run: this is not the local site.' );
}

const BT_TEST_PRODUCT = 26;

$GLOBALS['bt_fail'] = 0;
$GLOBALS['bt_n']    = 0;
function bt_check( $label, $actual, $expected ) {
	++$GLOBALS['bt_n'];
	if ( $actual !== $expected ) {
		++$GLOBALS['bt_fail'];
		echo "FAIL  $label\n  expected: " . json_encode( $expected ) . "\n  actual:   " . json_encode( $actual ) . "\n";
	}
}

wc_load_cart();
$cart     = WC()->cart;
$original = get_option( Settings::OPTION, null );
$parent   = wc_get_product( BT_TEST_PRODUCT );
$vids     = $parent->get_children();
if ( count( $vids ) < 2 ) {
	WP_CLI::error( 'Test product needs two variations.' );
}
list( $xl, $xxl ) = $vids;
$orig_enabled     = $parent->get_meta( '_bundletiers_enabled' );
$orig_mode        = $parent->get_meta( '_bundletiers_mode' );

function bt_settings( array $override = array() ) {
	$tier = function ( $q, $type, $v, $d = false ) {
		return array( 'qty' => $q, 'type' => $type, 'value' => (float) $v, 'label' => '', 'badge' => '', 'default' => $d );
	};
	$base = array(
		'tiers'              => array( $tier( 1, 'percent', 0 ), $tier( 2, 'percent', 10, true ), $tier( 3, 'percent', 15 ) ),
		'count_variations'   => 'yes',
		'no_discount_coupon' => 'no',
	);
	update_option( Settings::OPTION, array_merge( $base, $override ) );
}

function bt_add( $variation_id, $qty ) {
	$v = wc_get_product( $variation_id );
	return WC()->cart->add_to_cart( $v->get_parent_id(), $qty, $variation_id, $v->get_variation_attributes() );
}

/** Line totals in minor units, in cart order, after a fresh calculation. */
function bt_lines() {
	WC()->cart->calculate_totals();
	$out = array();
	foreach ( WC()->cart->get_cart() as $item ) {
		$out[] = Pricing::to_minor( $item['line_total'] );
	}
	return $out;
}

function bt_fresh() {
	WC()->cart->empty_cart();
	WC()->cart->remove_coupons();
}

$parent->update_meta_data( '_bundletiers_enabled', 'yes' );
$parent->update_meta_data( '_bundletiers_mode', 'global' );
$parent->save();

$was  = array();
$base = Pricing::to_minor( wc_get_product( $xl )->get_price() ); // 2551 for Brivanto.

try {
	// 1. Single item: no discount.
	bt_settings();
	bt_fresh();
	bt_add( $xl, 1 );
	bt_check( '1 item: full price', bt_lines(), array( $base ) );

	// 2. Two of the same: 10%.
	bt_fresh();
	bt_add( $xl, 2 );
	bt_check( '2 same: -10%', bt_lines(), array( 2 * Pricing::apply( $base, 1, 'percent', 10 )['total'] ) );

	// 3. Variations counted together: 1 XL + 2 XXL is a pack of 3 (15%).
	bt_fresh();
	bt_add( $xl, 1 );
	bt_add( $xxl, 2 );
	$unit = Pricing::apply( $base, 1, 'percent', 15 )['total'];
	bt_check( '1 XL + 2 XXL = pack of 3', bt_lines(), array( $unit, 2 * $unit ) );

	// 4. Re-calculating many times never compounds.
	WC()->cart->calculate_totals();
	WC()->cart->calculate_totals();
	bt_check( 'repeat calculation is stable', bt_lines(), array( $unit, 2 * $unit ) );

	// 5. Editing the quantity re-prices.
	$keys = array_keys( WC()->cart->get_cart() );
	WC()->cart->set_quantity( $keys[1], 1 );
	bt_check( 'quantity change to pack of 2', bt_lines(), array( Pricing::apply( $base, 1, 'percent', 10 )['total'], Pricing::apply( $base, 1, 'percent', 10 )['total'] ) );

	// 6. Variations counted separately.
	bt_settings( array( 'count_variations' => 'no' ) );
	bt_fresh();
	bt_add( $xl, 1 );
	bt_add( $xxl, 2 );
	bt_check( 'separate: XL x1 full, XXL x2 -10%', bt_lines(), array( $base, 2 * Pricing::apply( $base, 1, 'percent', 10 )['total'] ) );

	// 7. Fixed pack price is shared exactly.
	bt_settings(
		array(
			'tiers' => array(
				array( 'qty' => 1, 'type' => 'percent', 'value' => 0.0, 'label' => '', 'badge' => '', 'default' => true ),
				array( 'qty' => 3, 'type' => 'fixed_pack', 'value' => 65.05, 'label' => '', 'badge' => '', 'default' => false ),
			),
		)
	);
	bt_fresh();
	bt_add( $xl, 1 );
	bt_add( $xxl, 2 );
	$lines = bt_lines();
	bt_check( 'fixed pack 65.05 total', array_sum( $lines ), 6505 );
	bt_check( 'fixed pack split by line', $lines, array( 2169, 4336 ) );

	// 8. A real sale price: the discount is taken from the current (sale) price.
	$sale = array( $xl => '20.00', $xxl => '22.00' );
	foreach ( $sale as $id => $price ) {
		$v          = wc_get_product( $id );
		$was[ $id ] = $v->get_sale_price( 'edit' );
		$v->set_sale_price( $price );
		$v->save();
	}
	bt_settings();
	bt_fresh();
	bt_add( $xl, 1 );
	bt_add( $xxl, 2 );
	bt_check( 'discount applies to the sale price (15%)', bt_lines(), array( 1700, 3740 ) );
	foreach ( $was as $id => $price ) {
		$v = wc_get_product( $id );
		$v->set_sale_price( $price );
		$v->save();
	}

	// 9. A product without bundles is untouched, and does not join the pack.
	$other = new WC_Product_Simple();
	$other->set_name( 'BT test product' );
	$other->set_regular_price( '10.00' );
	$other->set_status( 'publish' );
	$other->save();
	bt_fresh();
	bt_add( $xl, 2 );
	$cart->add_to_cart( $other->get_id(), 3 );
	bt_check( 'other product keeps its price', bt_lines(), array( 2 * Pricing::apply( $base, 1, 'percent', 10 )['total'], 3000 ) );

	// 10. Coupons: stack by default, blocked when the setting says so.
	$coupon = new WC_Coupon();
	$coupon->set_code( 'bttest10' );
	$coupon->set_discount_type( 'percent' );
	$coupon->set_amount( 10 );
	$coupon->save();
	bt_settings();
	bt_fresh();
	bt_add( $xl, 2 );
	$cart->apply_coupon( 'bttest10' );
	$cart->calculate_totals();
	$disc = Pricing::to_minor( $cart->get_discount_total() );
	bt_check( 'coupon applies on the already discounted price', $disc, (int) round( 2 * Pricing::apply( $base, 1, 'percent', 10 )['total'] * 0.10 ) );
	bt_settings( array( 'no_discount_coupon' => 'yes' ) );
	bt_fresh();
	bt_add( $xl, 2 );
	$ok = $cart->apply_coupon( 'bttest10' );
	wc_clear_notices();
	$cart->calculate_totals();
	bt_check( 'coupon blocked on bundle items when enabled', Pricing::to_minor( $cart->get_discount_total() ), 0 );

	// 10b. A cart loaded from the session without recalculating totals (normal page view)
	// still shows the bundle price on each item (mini-cart).
	bt_settings();
	bt_fresh();
	bt_add( $xl, 3 );
	$cart->calculate_totals();
	$session_cart = $cart->get_cart();
	$fresh        = array();
	foreach ( $session_cart as $key => $item ) {
		$fresh[ $key ] = array(
			'key'          => $key,
			'product_id'   => $item['product_id'],
			'variation_id' => $item['variation_id'],
			'variation'    => $item['variation'],
			'quantity'     => $item['quantity'],
			'data'         => wc_get_product( $item['variation_id'] ), // unmodified product, as after loading from the session
			'data_hash'    => $item['data_hash'],
		);
	}
	$cart->set_cart_contents( $fresh );
	do_action( 'woocommerce_cart_loaded_from_session', $cart );
	$shown = array_values( $cart->get_cart() )[0]['data']->get_price();
	bt_check( 'price per item after loading from session', Pricing::to_minor( $shown ), Pricing::apply( $base, 1, 'percent', 15 )['total'] );

	// 11. Order keeps the bundle on the line.
	bt_settings();
	bt_fresh();
	bt_add( $xl, 3 );
	$cart->calculate_totals();
	$order_id = WC()->checkout()->create_order(
		array(
			'billing_email'  => 'bt-test@example.com',
			'payment_method' => 'cod',
		)
	);
	$order = is_wp_error( $order_id ) ? null : wc_get_order( $order_id );
	bt_check( 'order created', (bool) $order, true );
	if ( $order ) {
		$line = array_values( $order->get_items() )[0];
		bt_check( 'order line total', Pricing::to_minor( $line->get_total() ), 3 * Pricing::apply( $base, 1, 'percent', 15 )['total'] );
		$meta = $line->get_meta( '_bundletiers_tier' );
		bt_check( 'order meta tier qty', $meta['qty'] ?? null, 3 );
		bt_check( 'order meta pack qty', $meta['pack_qty'] ?? null, 3 );
		bt_check( 'order meta saved', $meta['saved'] ?? null, 3 * $base - 3 * Pricing::apply( $base, 1, 'percent', 15 )['total'] );
		$visible = wc_display_item_meta( $line, array( 'echo' => false ) );
		bt_check( 'visible line shows the pack', false !== strpos( wp_strip_all_tags( $visible ), '−15%' ), true );
		$order->delete( true );
	}
} finally {
	bt_fresh();
	foreach ( $was as $id => $price ) {
		$v = wc_get_product( $id );
		$v->set_sale_price( $price );
		$v->save();
	}
	if ( isset( $other ) && $other->get_id() ) {
		$other->delete( true );
	}
	if ( isset( $coupon ) && $coupon->get_id() ) {
		$coupon->delete( true );
	}
	if ( null === $original ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original );
	}
	$parent->update_meta_data( '_bundletiers_enabled', $orig_enabled );
	$parent->update_meta_data( '_bundletiers_mode', $orig_mode );
	$parent->save();
}

echo $GLOBALS['bt_fail'] ? "\n{$GLOBALS['bt_fail']} of {$GLOBALS['bt_n']} checks FAILED\n" : "All {$GLOBALS['bt_n']} checks passed\n";
