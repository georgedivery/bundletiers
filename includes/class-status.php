<?php
/**
 * Status: what BundleTiers found on this site, for the Status section and for support.
 *
 * Everything is read from this site; nothing is sent anywhere.
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * Facts about the site and the plugin.
 */
class Status {

	/**
	 * Lowest versions the plugin supports.
	 */
	const MIN_WP  = '6.5';
	const MIN_WC  = '8.5';
	const MIN_PHP = '7.4';

	/**
	 * How many products have bundles switched on, and how many products there are.
	 *
	 * @return array array( 'with' => int, 'total' => int ).
	 */
	public static function bundle_products() {
		$statuses = array( 'publish', 'draft', 'pending', 'private' );
		$query    = new \WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => $statuses,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => Tiers::META_ENABLED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$counts   = (array) wp_count_posts( 'product' );
		$total    = 0;
		foreach ( $statuses as $status ) {
			$total += isset( $counts[ $status ] ) ? (int) $counts[ $status ] : 0;
		}
		return array(
			'with'  => (int) $query->found_posts,
			'total' => $total,
		);
	}

	/**
	 * How a WooCommerce page (cart or checkout) is built: blocks, shortcode or missing.
	 *
	 * @param string $page cart|checkout.
	 * @return string blocks|shortcode|missing
	 */
	public static function page_kind( $page ) {
		$id   = wc_get_page_id( $page );
		$post = $id > 0 ? get_post( $id ) : null;
		if ( ! $post ) {
			return 'missing';
		}
		return has_block( 'woocommerce/' . $page, $post ) ? 'blocks' : 'shortcode';
	}

	/**
	 * Sentence about how prices are entered and shown.
	 *
	 * @return string
	 */
	public static function prices_text() {
		if ( ! wc_tax_enabled() ) {
			return __( 'Taxes are off in WooCommerce, so prices have no tax.', 'bundletiers' );
		}
		$with    = wc_prices_include_tax();
		$display = 'incl' === get_option( 'woocommerce_tax_display_shop' );
		if ( $with && $display ) {
			return __( 'Prices are entered with tax and shown with tax.', 'bundletiers' );
		}
		if ( $with ) {
			return __( 'Prices are entered with tax and shown without tax.', 'bundletiers' );
		}
		return $display ? __( 'Prices are entered without tax and shown with tax.', 'bundletiers' ) : __( 'Prices are entered without tax and shown without tax.', 'bundletiers' );
	}

	/**
	 * The rows of the Status table.
	 *
	 * @return array[] Each row: array( 'label' => text, 'value' => text, 'state' => ok|warn|info|'', 'note' => text ).
	 */
	public static function rows() {
		$theme    = wp_get_theme();
		$kind     = array(
			'blocks'    => __( 'blocks', 'bundletiers' ),
			'shortcode' => __( 'shortcode', 'bundletiers' ),
			'missing'   => __( 'not set', 'bundletiers' ),
		);
		$products = self::bundle_products();
		$hpos     = class_exists( '\\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		$wc_ok  = version_compare( WC()->version, self::MIN_WC, '>=' );
		$wp_ok  = version_compare( get_bloginfo( 'version' ), self::MIN_WP, '>=' );
		$php_ok = version_compare( PHP_VERSION, self::MIN_PHP, '>=' );

		return array(
			array(
				'label' => __( 'BundleTiers', 'bundletiers' ),
				'value' => BUNDLETIERS_VERSION,
				'state' => '',
				'note'  => '',
			),
			array(
				'label' => __( 'WooCommerce', 'bundletiers' ),
				'value' => WC()->version,
				'state' => $wc_ok ? 'ok' : 'warn',
				/* translators: %s: version number */
				'note'  => $wc_ok ? __( 'Supported', 'bundletiers' ) : sprintf( __( 'Needs %s or later', 'bundletiers' ), self::MIN_WC ),
			),
			array(
				'label' => __( 'WordPress', 'bundletiers' ),
				'value' => get_bloginfo( 'version' ),
				'state' => $wp_ok ? 'ok' : 'warn',
				/* translators: %s: version number */
				'note'  => $wp_ok ? __( 'Supported', 'bundletiers' ) : sprintf( __( 'Needs %s or later', 'bundletiers' ), self::MIN_WP ),
			),
			array(
				'label' => __( 'PHP', 'bundletiers' ),
				'value' => PHP_VERSION,
				'state' => $php_ok ? 'ok' : 'warn',
				/* translators: %s: version number */
				'note'  => $php_ok ? __( 'Supported', 'bundletiers' ) : sprintf( __( 'Needs %s or later', 'bundletiers' ), self::MIN_PHP ),
			),
			array(
				'label' => __( 'Active theme', 'bundletiers' ),
				'value' => $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' ),
				'state' => 'info',
				'note'  => wp_is_block_theme() ? __( 'Block theme', 'bundletiers' ) : __( 'Classic theme', 'bundletiers' ),
			),
			array(
				'label' => __( 'Cart page', 'bundletiers' ),
				'value' => $kind[ self::page_kind( 'cart' ) ],
				'state' => 'info',
				'note'  => __( 'Both the shortcode and the blocks work', 'bundletiers' ),
			),
			array(
				'label' => __( 'Checkout page', 'bundletiers' ),
				'value' => $kind[ self::page_kind( 'checkout' ) ],
				'state' => 'info',
				'note'  => __( 'Both the shortcode and the blocks work', 'bundletiers' ),
			),
			array(
				'label' => __( 'Order storage', 'bundletiers' ),
				'value' => $hpos ? __( 'High-performance order storage (HPOS)', 'bundletiers' ) : __( 'WordPress posts', 'bundletiers' ),
				'state' => 'ok',
				'note'  => __( 'Supported', 'bundletiers' ),
			),
			array(
				'label' => __( 'Prices and tax', 'bundletiers' ),
				'value' => self::prices_text(),
				'state' => 'info',
				'note'  => __( 'Bundle amounts follow the price shown in the shop', 'bundletiers' ),
			),
			array(
				'label' => __( 'Products with bundles', 'bundletiers' ),
				/* translators: 1: products with bundles, 2: all products */
				'value' => sprintf( __( '%1$d of %2$d', 'bundletiers' ), $products['with'], $products['total'] ),
				'state' => $products['with'] > 0 ? 'ok' : 'warn',
				'note'  => $products['with'] > 0 ? '' : __( 'None yet: switch bundles on in a product', 'bundletiers' ),
			),
		);
	}
}
