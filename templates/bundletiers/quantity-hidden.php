<?php
/**
 * Quantity field for products that use bundles: a hidden input only.
 * The customer picks the quantity with the bundle cards, so no visible field
 * (and no theme +/- buttons) is printed. Override in your theme:
 * yourtheme/bundletiers/quantity-hidden.php
 *
 * @package BundleTiers
 */

defined( 'ABSPATH' ) || exit;

// Variables here are local to the include, not real globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
global $product;

$bundletiers_input_id = isset( $input_id ) ? $input_id : uniqid( 'quantity_' );
?>
<input type="hidden" id="<?php echo esc_attr( $bundletiers_input_id ); ?>" class="qty bt-quantity" name="quantity" value="<?php echo esc_attr( (string) \BundleTiers\Frontend::default_quantity( $product ) ); ?>" />
