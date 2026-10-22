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
		add_action( 'woocommerce_admin_field_bundletiers_preview', array( $this, 'output_preview_field' ) );
		add_action( 'woocommerce_admin_field_bundletiers_style', array( $this, 'output_style_field' ) );
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
			'style_text'         => '',
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
	 * The layouts a selector can have (the style presets choose one).
	 *
	 * @return string[]
	 */
	public static function layouts() {
		return array( 'cards', 'list', 'minimal' );
	}

	/**
	 * Ready-made looks for the cards: each one sets the style fields in one click.
	 *
	 * @return array[] Preset id => array( 'label' => text, 'values' => array of style settings ).
	 */
	public static function presets() {
		return array(
			'cards'   => array(
				'label'  => __( 'Cards', 'bundletiers' ),
				'values' => array(
					'style'              => 'cards',
					'style_bg'           => '#ffffff',
					'style_border_color' => '#e2e4ea',
					'style_accent'       => '#4f46e5',
					'style_text'         => '#1f2937',
					'style_border_width' => 1,
					'style_radius'       => 14,
				),
			),
			'list'    => array(
				'label'  => __( 'List', 'bundletiers' ),
				'values' => array(
					'style'              => 'list',
					'style_bg'           => '#ffffff',
					'style_border_color' => '#e2e4ea',
					'style_accent'       => '#0f6e56',
					'style_text'         => '#1f2937',
					'style_border_width' => 1,
					'style_radius'       => 12,
				),
			),
			'minimal' => array(
				'label'  => __( 'Minimal', 'bundletiers' ),
				'values' => array(
					'style'              => 'minimal',
					'style_bg'           => '#ffffff',
					'style_border_color' => '#d5d7dd',
					'style_accent'       => '#111111',
					'style_text'         => '',
					'style_border_width' => 1,
					'style_radius'       => 4,
				),
			),
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
		// A text left at its English default (or empty) follows the language of the site instead of staying English.
		$defaults = self::defaults();
		foreach ( array(
			'text_save'       => 'You save',
			'text_per_item'   => 'per item',
			'text_cart_label' => 'Bundle',
		) as $text_key => $english ) {
			if ( ! is_scalar( $all[ $text_key ] ) || '' === trim( (string) $all[ $text_key ] ) || $english === $all[ $text_key ] ) {
				$all[ $text_key ] = $defaults[ $text_key ];
			}
		}
		if ( null === $key ) {
			return $all;
		}
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Cleans one style setting: a hex color, or a whole number inside its range.
	 *
	 * @param string $key   Setting key (style_bg, style_border_color, style_accent, style_text, style_border_width, style_radius).
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
		$bg   = self::clean_style( 'style_bg', self::get( 'style_bg' ) );
		$text = self::clean_style( 'style_text', self::get( 'style_text' ) );
		// With a text color set, the softer lines (old price, per item) are that color faded into the card.
		$text_css = $text ? sprintf( '--bt-text:%1$s;--bt-muted:color-mix(in srgb,%1$s 62%%,%2$s);', $text, $bg ) : '';
		return sprintf(
			'.bt-selector{--bt-bg:%1$s;--bt-border:%2$s;--bt-accent:%3$s;--bt-border-width:%4$dpx;--bt-radius:%5$dpx;%6$s}',
			$bg,
			self::clean_style( 'style_border_color', self::get( 'style_border_color' ) ),
			self::clean_style( 'style_accent', self::get( 'style_accent' ) ),
			self::clean_style( 'style_border_width', self::get( 'style_border_width' ) ),
			self::clean_style( 'style_radius', self::get( 'style_radius' ) ),
			$text_css
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
	 * Keeps the layout (cards or list) in the form. It is not shown: the style presets set it.
	 *
	 * @param array $field Field definition.
	 */
	public function output_style_field( $field ) {
		$style = in_array( self::get( 'style' ), self::layouts(), true ) ? self::get( 'style' ) : 'cards';
		echo '<tr class="bt-hidden-field" style="display:none"><td colspan="2"><input type="hidden" name="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $style ) . '" /></td></tr>';
	}

	/**
	 * Prints the style presets and a live preview of the cards (the script in
	 * assets/js/admin-preview.js keeps it in step with the fields below).
	 *
	 * @param array $field Field definition.
	 */
	public function output_preview_field( $field ) {
		$settings = self::get();
		$decimals = wc_get_price_decimals();
		$base     = Pricing::to_minor( 24, $decimals );
		$tiers    = array();
		$checked  = 0;
		foreach ( $settings['tiers'] as $i => $tier ) {
			$tiers[] = array_merge(
				$tier,
				array(
					'result'   => Pricing::apply( $base, $tier['qty'], $tier['type'], $tier['value'], $decimals ),
					'label'    => Tiers::label( $tier ),
					'badge'    => Tiers::badge( $tier ),
					'disabled' => false,
				)
			);
			if ( ! empty( $tier['default'] ) ) {
				$checked = $i;
			}
		}
		$values = array();
		foreach ( self::presets() as $id => $preset ) {
			$values[ $id ] = $preset['values'];
		}

		echo '<tr valign="top"><th scope="row" class="titledesc"><label>' . esc_html( $field['title'] ) . '</label></th><td class="forminp">';
		echo '<div class="bt-preview" data-name="' . esc_attr( self::OPTION ) . '" data-presets="' . esc_attr( wp_json_encode( $values ) ) . '">';
		echo '<p class="bt-presets">';
		foreach ( self::presets() as $id => $preset ) {
			echo '<button type="button" class="button bt-preset" data-preset="' . esc_attr( $id ) . '">' . esc_html( $preset['label'] ) . '</button> ';
		}
		echo '</p><div class="bt-preview-box">';
		Frontend::get_template(
			'selector.php',
			array(
				'tiers'    => $tiers,
				'checked'  => $checked,
				'data'     => array(),
				'settings' => $settings,
				'name'     => 'bundletiers_preview_tier',
			)
		);
		echo '</div><p class="description">' . esc_html__( 'Choose a style to start from, then adjust the colors below. The preview shows your default tiers and updates as you type; the font comes from your theme.', 'bundletiers' ) . '</p></div>';
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
		foreach ( array( 'style_bg', 'style_border_color', 'style_accent', 'style_text', 'style_border_width', 'style_radius' ) as $style_key ) {
			if ( self::OPTION . '[' . $style_key . ']' === $option['id'] ) {
				return self::clean_style( $style_key, $raw_value );
			}
		}
		// The layout is one of the known looks; anything else becomes the default.
		if ( self::OPTION . '[style]' === $option['id'] ) {
			return in_array( $value, self::layouts(), true ) ? $value : 'cards';
		}
		// Select fields only accept the choices the field itself offers.
		if ( 'select' === ( $option['type'] ?? '' ) && ! empty( $option['options'] ) && 0 === strpos( $option['id'], self::OPTION . '[' ) ) {
			return is_scalar( $value ) && array_key_exists( (string) $value, $option['options'] ) ? $value : ( $option['default'] ?? '' );
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
		wp_enqueue_style( 'bundletiers-admin', BUNDLETIERS_URL . 'assets/css/admin.css', array(), Plugin::asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'bundletiers-admin', BUNDLETIERS_URL . 'assets/js/admin.js', array(), Plugin::asset_version( 'assets/js/admin.js' ), true );
		if ( $on_settings ) {
			// The preview is the real selector markup and stylesheet.
			wp_enqueue_style( 'bundletiers', BUNDLETIERS_URL . 'assets/css/frontend.css', array(), Plugin::asset_version( 'assets/css/frontend.css' ) );
			wp_enqueue_script( 'bundletiers-admin-preview', BUNDLETIERS_URL . 'assets/js/admin-preview.js', array( 'jquery' ), Plugin::asset_version( 'assets/js/admin-preview.js' ), true );
		}
	}
}
