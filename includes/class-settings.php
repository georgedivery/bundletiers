<?php
/**
 * Global settings (WooCommerce → Settings → BundleTiers) and admin assets.
 *
 * Everything is stored in one option, "bundletiers_settings".
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * Global settings and admin assets.
 */
class Settings {

	const OPTION = 'bundletiers_settings';

	/**
	 * Registers the settings hooks.
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'register_page' ) );
		add_action( 'woocommerce_admin_field_bundletiers_tiers', array( $this, 'output_tiers_field' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_' . self::OPTION, array( $this, 'sanitize_option' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'tiers'              => Tiers::defaults(),
			'count_variations'   => 'yes',
			'position'           => 'before',
			'style'              => 'cards',
			'style_bg'           => '#ffffff',
			'style_border_color' => '#d5d7dd',
			'style_accent'       => '#111111',
			'style_border_width' => 1,
			'style_radius'       => 4,
			'text_save'          => __( 'You save', 'bundletiers' ),
			'text_per_item'      => __( 'per item', 'bundletiers' ),
			'text_cart_label'    => __( 'Bundle', 'bundletiers' ),
			'no_discount_coupon' => 'no',
			'delete_data'        => 'no',
		);
	}

	/**
	 * Reads settings merged over the defaults.
	 *
	 * @param string|null $key Single key, or null for all.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$saved = get_option( self::OPTION, array() );
		$all   = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		if ( empty( $all['tiers'] ) || ! is_array( $all['tiers'] ) ) {
			$all['tiers'] = Tiers::defaults();
		}
		if ( null === $key ) {
			return $all;
		}
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Cleans one style setting: a hex colour, or a whole number inside its range.
	 *
	 * @param string $key   Setting key (style_bg, style_border_color, style_accent, style_border_width, style_radius).
	 * @param mixed  $value Raw value.
	 * @return string|int Valid value, or the default when the input is not valid.
	 */
	public static function clean_style( $key, $value ) {
		$defaults = self::defaults();
		$ranges   = array(
			'style_border_width' => array( 0, 10 ),
			'style_radius'       => array( 0, 40 ),
		);

		if ( isset( $ranges[ $key ] ) ) {
			if ( ! is_numeric( $value ) ) {
				return $defaults[ $key ];
			}
			return (int) max( $ranges[ $key ][0], min( $ranges[ $key ][1], round( (float) $value ) ) );
		}

		$color = sanitize_hex_color( is_string( $value ) ? trim( $value ) : '' );
		return $color ? $color : $defaults[ $key ];
	}

	/**
	 * CSS variables for the card look, printed after the plugin stylesheet.
	 *
	 * @return string
	 */
	public static function style_css() {
		return sprintf(
			'.bt-selector{--bt-bg:%1$s;--bt-border:%2$s;--bt-accent:%3$s;--bt-border-width:%4$dpx;--bt-radius:%5$dpx;}',
			self::clean_style( 'style_bg', self::get( 'style_bg' ) ),
			self::clean_style( 'style_border_color', self::get( 'style_border_color' ) ),
			self::clean_style( 'style_accent', self::get( 'style_accent' ) ),
			self::clean_style( 'style_border_width', self::get( 'style_border_width' ) ),
			self::clean_style( 'style_radius', self::get( 'style_radius' ) )
		);
	}

	/**
	 * Adds the settings tab.
	 *
	 * @param array $pages Settings pages.
	 * @return array
	 */
	public function register_page( $pages ) {
		$pages[] = new Settings_Page();
		return $pages;
	}

	/**
	 * Prints the tiers row of the settings table.
	 *
	 * @param array $field Field definition.
	 */
	public function output_tiers_field( $field ) {
		echo '<tr valign="top"><th scope="row" class="titledesc"><label>' . esc_html( $field['title'] ) . '</label></th><td class="forminp">';
		Tiers_Editor::render( $field['id'], self::get( 'tiers' ) );
		echo '</td></tr>';
	}

	/**
	 * Validates the tiers field when the settings page is saved. Other fields
	 * keep the value WooCommerce already sanitised.
	 *
	 * @param mixed $value     Sanitised value.
	 * @param array $option    Field definition.
	 * @param mixed $raw_value Raw posted value.
	 * @return mixed
	 */
	public function sanitize_option( $value, $option, $raw_value ) {
		foreach ( array( 'style_bg', 'style_border_color', 'style_accent', 'style_border_width', 'style_radius' ) as $style_key ) {
			if ( self::OPTION . '[' . $style_key . ']' === $option['id'] ) {
				return self::clean_style( $style_key, $raw_value );
			}
		}
		if ( self::OPTION . '[tiers]' !== $option['id'] ) {
			return $value;
		}
		$messages = array();
		$tiers    = Tiers::sanitize( $raw_value, $messages );
		if ( ! $tiers ) {
			\WC_Admin_Settings::add_error( __( 'BundleTiers: at least one tier is required. Previous tiers were kept.', 'bundletiers' ) );
			return self::get( 'tiers' );
		}
		foreach ( array_unique( $messages ) as $message ) {
			\WC_Admin_Settings::add_error( 'BundleTiers: ' . $message );
		}
		return $tiers;
	}

	/**
	 * Loads the editor CSS/JS on the settings tab and the product edit screen.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$on_settings = 'woocommerce_page_wc-settings' === $hook && isset( $_GET['tab'] ) && 'bundletiers' === sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$screen      = get_current_screen();
		$on_product  = $screen && 'product' === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		if ( ! $on_settings && ! $on_product ) {
			return;
		}
		wp_enqueue_style( 'bundletiers-admin', BUNDLETIERS_URL . 'assets/css/admin.css', array(), BUNDLETIERS_VERSION );
		wp_enqueue_script( 'bundletiers-admin', BUNDLETIERS_URL . 'assets/js/admin.js', array(), BUNDLETIERS_VERSION, true );
	}
}
