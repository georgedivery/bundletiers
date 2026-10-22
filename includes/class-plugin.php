<?php
/**
 * Plugin bootstrap.
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the plugin modules.
 */
final class Plugin {

	/**
	 * The single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the single instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Version string for an asset: the plugin version plus the file's modification time, so a
	 * changed stylesheet or script is never served from a browser's old cache.
	 *
	 * @param string $relative Path of the file inside the plugin folder.
	 * @return string
	 */
	public static function asset_version( $relative ) {
		$path = BUNDLETIERS_DIR . $relative;
		return is_readable( $path ) ? BUNDLETIERS_VERSION . '.' . filemtime( $path ) : BUNDLETIERS_VERSION;
	}

	/**
	 * Loads the modules when WooCommerce is active. Translations load by themselves
	 * (WordPress 6.5 and later read the plugin's languages folder from its headers).
	 */
	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}

		/**
		 * Fires once BundleTiers has loaded and WooCommerce is available.
		 * Later stages hook their modules here.
		 */
		new Frontend();
		new Cart();
		new Order();

		if ( is_admin() ) {
			new Settings();
			new Product_Tab();
		}

		do_action( 'bundletiers_loaded' );
	}

	/**
	 * Admin notice shown when WooCommerce is missing.
	 */
	public function missing_woocommerce_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'BundleTiers requires WooCommerce to be installed and active.', 'bundletiers' );
		echo '</p></div>';
	}
}
