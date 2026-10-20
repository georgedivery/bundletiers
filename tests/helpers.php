<?php
/**
 * Fixtures shared by the scenario scripts (local only!). Loaded with require_once.
 */

/**
 * Stops the script unless the site is a local development site (.local, .test, .localhost or local.*).
 */
function bt_require_local_site() {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	if ( ! preg_match( '/(\.(local|test|localhost)|^local\..+)$/', $host ) ) {
		WP_CLI::error( 'Refusing to run: this is not a local site.' );
	}
}

/**
 * Creates a published variable product with two variations (sizes XL and XXL).
 *
 * @param string $price Regular price of both variations.
 * @return WC_Product_Variable
 */
function bt_create_variable_product( $price = '25.51' ) {
	$attribute = new WC_Product_Attribute();
	$attribute->set_name( 'Size' );
	$attribute->set_options( array( 'XL', 'XXL' ) );
	$attribute->set_visible( true );
	$attribute->set_variation( true );

	$parent = new WC_Product_Variable();
	$parent->set_name( 'BT test variable product' );
	$parent->set_status( 'publish' );
	$parent->set_attributes( array( $attribute ) );
	$parent->save();

	foreach ( array( 'XL', 'XXL' ) as $size ) {
		$variation = new WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_attributes( array( 'size' => $size ) );
		$variation->set_regular_price( $price );
		$variation->set_status( 'publish' );
		$variation->save();
	}

	WC_Product_Variable::sync( $parent->get_id() );
	return wc_get_product( $parent->get_id() );
}

/**
 * Deletes a product made by a fixture (and its variations).
 *
 * @param WC_Product|null $product Product.
 */
function bt_delete_product( $product ) {
	if ( $product && $product->get_id() ) {
		$product->delete( true );
	}
}
