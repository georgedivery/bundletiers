<?php
/**
 * Plugin Name:       BundleTiers – Bundle Discounts for WooCommerce
 * Description:       Quantity-tier bundle discounts (1 / 2 / 3 pcs) for WooCommerce products, with variations counted together.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.5
 * Author:            Webbeb
 * Author URI:        https://webbeb.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bundletiers
 * Domain Path:       /languages
 *
 * @package BundleTiers
 */

defined( 'ABSPATH' ) || exit;

define( 'BUNDLETIERS_VERSION', '0.1.0' );
define( 'BUNDLETIERS_FILE', __FILE__ );
define( 'BUNDLETIERS_DIR', plugin_dir_path( __FILE__ ) );
define( 'BUNDLETIERS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Maps BundleTiers\Foo_Bar to includes/class-foo-bar.php.
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'BundleTiers\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$name = strtolower( str_replace( '_', '-', substr( $class_name, strlen( $prefix ) ) ) );
		$file = BUNDLETIERS_DIR . 'includes/class-' . $name . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

// Declare compatibility before WooCommerce initialises, regardless of load order.
add_action( 'before_woocommerce_init', array( 'BundleTiers\\Compat', 'declare_features' ) );

add_action( 'plugins_loaded', array( 'BundleTiers\\Plugin', 'instance' ) );
