<?php
/**
 * Product page and cart agree on the price when VAT is on (local only!).
 *
 * Run from the WordPress root:
 *   wp eval-file wp-content/plugins/bundletiers/tests/tax-scenarios.php
 *
 * For every combination of "Prices entered with tax" and "Display prices in the shop",
 * the amount the selector promises for a pack of two must be what the cart charges,
 * for percent, fixed amount per item and fixed pack price. Temporarily changes the tax
 * options and the BundleTiers settings and creates a tax rate and a product; everything
 * is restored at the end.
 */

use BundleTiers\Settings;
use BundleTiers\Pricing;

if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( 'local.brivanto.com', 'bundletiers.local' ), true ) ) {
	WP_CLI::error( 'Refusing to run: this is not the local site.' );
}

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
$cart = WC()->cart;

$option_names = array(
	'woocommerce_calc_taxes',
	'woocommerce_prices_include_tax',
	'woocommerce_tax_display_shop',
	'woocommerce_tax_display_cart',
	'woocommerce_tax_based_on',
	'woocommerce_default_country',
);
$orig_options = array();
foreach ( $option_names as $name ) {
	$orig_options[ $name ] = get_option( $name, null );
}
$original = get_option( Settings::OPTION, null );
$rate_id  = null;
$product  = null;
$decimals = wc_get_price_decimals();

function bt_tax_settings( $type, $value ) {
	update_option(
		Settings::OPTION,
		array(
			'tiers'              => array(
				array( 'qty' => 1, 'type' => 'percent', 'value' => 0.0, 'label' => '', 'badge' => '', 'default' => false ),
				array( 'qty' => 2, 'type' => $type, 'value' => (float) $value, 'label' => '', 'badge' => '', 'default' => true ),
			),
			'count_variations'   => 'yes',
			'no_discount_coupon' => 'no',
		)
	);
}

/** What the cart charges for the line, in the shop display price, minor units. */
function bt_cart_line( $cart, $product_id, $qty, $display ) {
	$cart->empty_cart();
	$cart->add_to_cart( $product_id, $qty );
	$cart->calculate_totals();
	$item = array_values( $cart->get_cart() )[0];
	$sum  = 'incl' === $display ? $item['line_total'] + $item['line_tax'] : $item['line_total'];
	return Pricing::to_minor( $sum );
}

try {
	update_option( 'woocommerce_calc_taxes', 'yes' );
	update_option( 'woocommerce_tax_based_on', 'base' );
	update_option( 'woocommerce_default_country', 'BG' );
	$rate_id = WC_Tax::_insert_tax_rate(
		array(
			'tax_rate_country'  => '',
			'tax_rate_state'    => '',
			'tax_rate'          => '20.0000',
			'tax_rate_name'     => 'BT test VAT',
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 0,
			'tax_rate_order'    => 1,
			'tax_rate_class'    => '',
		)
	);

	$product = new WC_Product_Simple();
	$product->set_name( 'BT tax test product' );
	$product->set_regular_price( '20.00' );
	$product->set_tax_status( 'taxable' );
	$product->set_status( 'publish' );
	$product->update_meta_data( '_bundletiers_enabled', 'yes' );
	$product->update_meta_data( '_bundletiers_mode', 'global' );
	$product->save();
	$id = $product->get_id();

	$types = array(
		'percent'    => 10,
		'fixed_item' => 2,
		'fixed_pack' => 30,
	);

	foreach ( array( 'yes', 'no' ) as $entered ) {
		foreach ( array( 'incl', 'excl' ) as $display ) {
			update_option( 'woocommerce_prices_include_tax', $entered );
			update_option( 'woocommerce_tax_display_shop', $display );
			update_option( 'woocommerce_tax_display_cart', $display );

			foreach ( $types as $type => $value ) {
				bt_tax_settings( $type, $value );

				// What the product page promises for a pack of two (see Frontend::render()).
				$shown    = wc_get_product( $id );
				$base     = Pricing::to_minor( wc_get_price_to_display( $shown ), $decimals );
				$expected = Pricing::apply( $base, 2, $type, $value, $decimals )['total'];

				$label = "entered with tax: $entered, shown $display, $type";
				bt_check( "$label: cart charges what the card promises", bt_cart_line( $cart, $id, 2, $display ), $expected );
				bt_check( "$label: one item is not discounted", bt_cart_line( $cart, $id, 1, $display ), $base );
			}
		}
	}

	// A product that is not taxable never changes basis.
	update_option( 'woocommerce_prices_include_tax', 'no' );
	update_option( 'woocommerce_tax_display_shop', 'incl' );
	update_option( 'woocommerce_tax_display_cart', 'incl' );
	$product->set_tax_status( 'none' );
	$product->save();
	bt_tax_settings( 'fixed_item', 2 );
	bt_check( 'not taxable: fixed amount off each item', bt_cart_line( $cart, $id, 2, 'incl' ), 3600 );

	// With taxes switched off the plugin behaves as before.
	update_option( 'woocommerce_calc_taxes', 'no' );
	$product->set_tax_status( 'taxable' );
	$product->save();
	bt_check( 'taxes off: fixed amount off each item', bt_cart_line( $cart, $id, 2, 'excl' ), 3600 );
} finally {
	$cart->empty_cart();
	if ( $product && $product->get_id() ) {
		$product->delete( true );
	}
	if ( $rate_id ) {
		WC_Tax::_delete_tax_rate( $rate_id );
	}
	foreach ( $orig_options as $name => $value ) {
		if ( null === $value ) {
			delete_option( $name );
		} else {
			update_option( $name, $value );
		}
	}
	if ( null === $original ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original );
	}
}

echo $GLOBALS['bt_fail'] ? "\n{$GLOBALS['bt_fail']} of {$GLOBALS['bt_n']} checks FAILED\n" : "All {$GLOBALS['bt_n']} checks passed\n";
