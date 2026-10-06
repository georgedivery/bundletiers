<?php
/**
 * The quantity field on the product page (local only!).
 *   wp eval-file wp-content/plugins/bundletiers/tests/quantity-scenarios.php
 */

use BundleTiers\Settings;

if ( 'local.brivanto.com' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
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

new BundleTiers\Frontend(); // Registers the filter (normally done by the plugin on load).

$product = wc_get_product( 26 );
$meta    = $product->get_meta( '_bundletiers_enabled' );
$mode    = $product->get_meta( '_bundletiers_mode' );
$orig    = get_option( Settings::OPTION, null );
$other   = null;

try {
	$product->update_meta_data( '_bundletiers_enabled', 'yes' );
	$product->update_meta_data( '_bundletiers_mode', 'global' );
	$product->save();
	update_option(
		Settings::OPTION,
		array(
			'tiers' => array(
				array( 'qty' => 1, 'type' => 'percent', 'value' => 0.0, 'label' => '', 'badge' => '', 'default' => false ),
				array( 'qty' => 2, 'type' => 'percent', 'value' => 10.0, 'label' => '', 'badge' => '', 'default' => true ),
			),
		)
	);

	// Product page of a bundle product: a hidden field with the default tier, nothing else.
	$GLOBALS['product'] = $product;
	$html               = woocommerce_quantity_input( array( 'input_name' => 'quantity' ), $product, false );
	bt_check( 'hidden input is printed', false !== strpos( $html, 'type="hidden"' ), true );
	bt_check( 'start value is the default tier', false !== strpos( $html, 'value="2"' ), true );
	bt_check( 'field is named quantity', false !== strpos( $html, 'name="quantity"' ), true );
	bt_check( 'no visible wrapper or buttons', false === strpos( $html, 'class="quantity"' ) && false === strpos( $html, 'plus' ), true );
	bt_check( 'default quantity helper', BundleTiers\Frontend::default_quantity( $product ), 2 );

	// The cart page field is untouched.
	$html = woocommerce_quantity_input( array( 'input_name' => 'cart[abc][qty]' ), $product, false );
	bt_check( 'cart field is the normal one', false !== strpos( $html, 'class="quantity"' ), true );
	bt_check( 'cart field is not hidden', false === strpos( $html, 'type="hidden"' ), true );

	// A product without bundles keeps its normal field.
	$other = new WC_Product_Simple();
	$other->set_name( 'BT qty test' );
	$other->set_regular_price( '5' );
	$other->set_status( 'publish' );
	$other->save();
	$GLOBALS['product'] = $other;
	$html               = woocommerce_quantity_input( array( 'input_name' => 'quantity' ), $other, false );
	bt_check( 'normal product keeps the normal field', false !== strpos( $html, 'class="quantity"' ) && false === strpos( $html, 'type="hidden"' ), true );

	// Switched off: normal field again.
	$GLOBALS['product'] = $product;
	$product->update_meta_data( '_bundletiers_enabled', 'no' );
	$product->save();
	$html = woocommerce_quantity_input( array( 'input_name' => 'quantity' ), wc_get_product( 26 ), false );
	bt_check( 'switched off: normal field', false !== strpos( $html, 'class="quantity"' ), true );
} finally {
	if ( $other && $other->get_id() ) {
		$other->delete( true );
	}
	$product = wc_get_product( 26 );
	$product->update_meta_data( '_bundletiers_enabled', $meta );
	$product->update_meta_data( '_bundletiers_mode', $mode );
	$product->save();
	if ( null === $orig ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $orig );
	}
}

echo $GLOBALS['bt_fail'] ? "\n{$GLOBALS['bt_fail']} of {$GLOBALS['bt_n']} checks FAILED\n" : "All {$GLOBALS['bt_n']} checks passed\n";
