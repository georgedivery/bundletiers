<?php
/**
 * WooCommerce feature compatibility declarations.
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * Declares compatibility with WooCommerce features.
 */
class Compat {

	/**
	 * Declares HPOS and Cart/Checkout Blocks compatibility.
	 */
	public static function declare_features() {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BUNDLETIERS_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BUNDLETIERS_FILE, true );
	}
}
