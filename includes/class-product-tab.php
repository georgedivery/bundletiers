<?php
/**
 * "BundleTiers" tab in Product data.
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * The BundleTiers tab in Product data.
 */
class Product_Tab {

	const NONCE_ACTION = 'bundletiers_product';
	const NONCE_FIELD  = 'bundletiers_nonce';

	/**
	 * Registers the product tab hooks.
	 */
	public function __construct() {
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );
	}

	/**
	 * Adds the tab to the list.
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public function add_tab( $tabs ) {
		$tabs['bundletiers'] = array(
			'label'    => __( 'BundleTiers', 'bundletiers' ),
			'target'   => 'bundletiers_product_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 75,
		);
		return $tabs;
	}

	/**
	 * Prints the tab panel.
	 */
	public function render_panel() {
		global $post;
		$product = wc_get_product( $post->ID );
		if ( ! $product ) {
			return;
		}
		$mode   = 'custom' === $product->get_meta( Tiers::META_MODE ) ? 'custom' : 'global';
		$custom = $product->get_meta( Tiers::META_TIERS );
		if ( ! is_array( $custom ) || ! $custom ) {
			$custom = Settings::get( 'tiers' );
		}

		echo '<div id="bundletiers_product_data" class="panel woocommerce_options_panel hidden">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		echo '<div class="options_group">';
		woocommerce_wp_checkbox(
			array(
				'id'          => Tiers::META_ENABLED,
				'label'       => __( 'Enable bundles', 'bundletiers' ),
				'description' => __( 'Offer quantity bundles with a discount for this product', 'bundletiers' ),
				'value'       => $product->get_meta( Tiers::META_ENABLED ),
			)
		);
		woocommerce_wp_select(
			array(
				'id'      => Tiers::META_MODE,
				'label'   => __( 'Tiers', 'bundletiers' ),
				'value'   => $mode,
				'options' => array(
					'global' => __( 'Global tiers (WooCommerce → Settings → BundleTiers)', 'bundletiers' ),
					'custom' => __( 'Custom tiers for this product', 'bundletiers' ),
				),
			)
		);
		echo '</div>';
		echo '<div class="options_group bt-custom-wrap" style="' . ( 'custom' === $mode ? '' : 'display:none' ) . '"><div class="bt-product-editor">';
		Tiers_Editor::render( Tiers::META_TIERS, $custom );
		echo '</div></div>';
		echo '</div>';
	}

	/**
	 * Saves the tab. WooCommerce has already checked its own nonce and that the
	 * user may edit the product; we check ours and the capability again.
	 *
	 * @param \WC_Product $product Product being saved.
	 */
	public function save( $product ) {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $product->get_id() ) ) {
			return;
		}

		$product->update_meta_data( Tiers::META_ENABLED, isset( $_POST[ Tiers::META_ENABLED ] ) ? 'yes' : 'no' );

		$mode = isset( $_POST[ Tiers::META_MODE ] ) && 'custom' === sanitize_key( wp_unslash( $_POST[ Tiers::META_MODE ] ) ) ? 'custom' : 'global';

		if ( 'custom' === $mode ) {
			$raw      = isset( $_POST[ Tiers::META_TIERS ] ) ? wp_unslash( $_POST[ Tiers::META_TIERS ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in Tiers::sanitize().
			$messages = array();
			$tiers    = Tiers::sanitize( $raw, $messages );
			if ( ! $tiers ) {
				$mode = 'global';
				\WC_Admin_Meta_Boxes::add_error( __( 'BundleTiers: custom tiers were empty, so global tiers are used.', 'bundletiers' ) );
			} else {
				$product->update_meta_data( Tiers::META_TIERS, $tiers );
				foreach ( array_unique( $messages ) as $message ) {
					\WC_Admin_Meta_Boxes::add_error( 'BundleTiers: ' . $message );
				}
			}
		}
		$product->update_meta_data( Tiers::META_MODE, $mode );
	}
}
