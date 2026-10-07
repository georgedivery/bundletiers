<?php
/**
 * The product page selector on a simple (not variable) product (local only!).
 *   wp eval-file wp-content/plugins/bundletiers/tests/simple-product-scenarios.php
 * Creates a temporary product and deletes it.
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

$orig    = get_option( Settings::OPTION, null );
$product = null;

try {
	update_option(
		Settings::OPTION,
		array(
			'tiers' => array(
				array( 'qty' => 1, 'type' => 'percent', 'value' => 0.0, 'label' => '', 'badge' => '', 'default' => false ),
				array( 'qty' => 2, 'type' => 'percent', 'value' => 10.0, 'label' => '', 'badge' => '', 'default' => true ),
				array( 'qty' => 3, 'type' => 'percent', 'value' => 15.0, 'label' => '', 'badge' => '', 'default' => false ),
			),
		)
	);

	$product = new WC_Product_Simple();
	$product->set_name( 'BT simple test' );
	$product->set_regular_price( '5.00' );
	$product->set_status( 'publish' );
	$product->update_meta_data( '_bundletiers_enabled', 'yes' );
	$product->update_meta_data( '_bundletiers_mode', 'global' );
	$product->save();

	$frontend = new BundleTiers\Frontend();

	// Unlimited stock: all tiers available, default tier checked, prices from the product.
	$GLOBALS['product'] = $product;
	ob_start();
	$frontend->render( $product );
	$html = ob_get_clean();
	bt_check( 'selector is printed for a simple product', false !== strpos( $html, 'bt-selector' ), true );
	bt_check( 'three cards', substr_count( $html, 'class="bt-radio"' ), 3 );
	bt_check( 'no card disabled', false === strpos( $html, 'disabled' ), true );
	bt_check( 'default tier is checked', 1 === preg_match( '/value="2"\s+checked/', $html ), true );
	bt_check( 'price of 2 with 10%', false !== strpos( $html, '9.00' ), true );
	bt_check( 'data attribute says not variable', false !== strpos( $html, '&quot;variable&quot;:false' ), true );

	// Limited stock (2 in stock): the 3 card is disabled.
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 2 );
	$product->save();
	$product = wc_get_product( $product->get_id() );
	$fresh   = new BundleTiers\Frontend();
	ob_start();
	$fresh->render( $product );
	$html = ob_get_clean();
	bt_check( 'stock 2: only the 3 card is disabled', substr_count( $html, 'disabled' ) >= 1 && 1 === preg_match( '/value="3"[^>]*disabled/', $html ), true );
	bt_check( 'stock 2: card 2 still available and checked', 1 === preg_match( '/value="2"\s+checked/', $html ), true );

	// Stock 1: cards 2 and 3 are disabled, the default falls back to 1.
	$product->set_stock_quantity( 1 );
	$product->save();
	$product = wc_get_product( $product->get_id() );
	$again   = new BundleTiers\Frontend();
	ob_start();
	$again->render( $product );
	$html = ob_get_clean();
	bt_check( 'stock 1: default falls back to card 1', 1 === preg_match( '/value="1"\s+checked/', $html ), true );
} finally {
	if ( $product && $product->get_id() ) {
		$product->delete( true );
	}
	if ( null === $orig ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $orig );
	}
}

echo $GLOBALS['bt_fail'] ? "\n{$GLOBALS['bt_fail']} of {$GLOBALS['bt_n']} checks FAILED\n" : "All {$GLOBALS['bt_n']} checks passed\n";
