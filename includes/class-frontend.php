<?php
/**
 * Product page: bundle selector markup and assets.
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * Bundle selector on the product page.
 */
class Frontend {

	/**
	 * Product ids already rendered (a theme may fire the hooks twice).
	 *
	 * @var int[]
	 */
	private $rendered = array();

	/**
	 * Registers the product page hooks.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'wc_get_template', array( $this, 'quantity_template' ), 10, 3 );

		// Simple products: inside the form around the button.
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'maybe_render_before' ), 5 );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'maybe_render_after' ), 15 );
		// Variable products: these two sit outside the hidden variation wrapper,
		// so the cards stay visible before a variation is chosen.
		add_action( 'woocommerce_after_variations_table', array( $this, 'maybe_render_before_variable' ), 5 );
		add_action( 'woocommerce_after_variations_form', array( $this, 'maybe_render_after_variable' ), 5 );
	}

	/**
	 * Simple products, position "before".
	 */
	public function maybe_render_before() {
		$this->maybe_render( 'before', false );
	}

	/**
	 * Simple products, position "after".
	 */
	public function maybe_render_after() {
		$this->maybe_render( 'after', false );
	}

	/**
	 * Variable products, position "before".
	 */
	public function maybe_render_before_variable() {
		$this->maybe_render( 'before', true );
	}

	/**
	 * Variable products, position "after".
	 */
	public function maybe_render_after_variable() {
		$this->maybe_render( 'after', true );
	}

	/**
	 * Renders the selector when the hook matches the configured position.
	 *
	 * @param string $position  before|after.
	 * @param bool   $variable  Whether the calling hook belongs to variable products.
	 */
	private function maybe_render( $position, $variable ) {
		global $product;
		if ( ! $product instanceof \WC_Product || Settings::get( 'position' ) !== $position ) {
			return;
		}
		if ( $product->is_type( 'variable' ) !== $variable ) {
			return;
		}
		$this->render( $product );
	}

	/**
	 * Whether a product gets the selector.
	 *
	 * @param \WC_Product $product Product.
	 * @return bool
	 */
	public static function supports( $product ) {
		return $product instanceof \WC_Product
			&& $product->is_type( array( 'simple', 'variable' ) )
			&& ! $product->is_sold_individually()
			&& (bool) Tiers::for_product( $product );
	}

	/**
	 * Quantity of the default tier, used as the start value of the hidden field.
	 *
	 * @param \WC_Product $product Product.
	 * @return int
	 */
	public static function default_quantity( $product ) {
		$tiers = Tiers::for_product( $product );
		foreach ( $tiers as $tier ) {
			if ( ! empty( $tier['default'] ) ) {
				return (int) $tier['qty'];
			}
		}
		return $tiers ? (int) $tiers[0]['qty'] : 1;
	}

	/**
	 * Swaps the visible quantity field for a hidden one on the product page of
	 * a bundle product. The cart page and other products keep their own field.
	 *
	 * @param string $template      Template path WooCommerce found.
	 * @param string $template_name Template name.
	 * @param array  $args          Template variables.
	 * @return string
	 */
	public function quantity_template( $template, $template_name, $args ) {
		global $product;
		if ( 'global/quantity-input.php' !== $template_name || ! is_array( $args ) || ! isset( $args['input_name'] ) || 'quantity' !== $args['input_name'] ) {
			return $template;
		}
		if ( ! self::supports( $product ) ) {
			return $template;
		}
		$path = locate_template( 'bundletiers/quantity-hidden.php' );
		return $path ? $path : BUNDLETIERS_DIR . 'templates/bundletiers/quantity-hidden.php';
	}

	/**
	 * Prints the selector for a product.
	 *
	 * @param \WC_Product $product Product.
	 */
	public function render( $product ) {
		if ( ! self::supports( $product ) || in_array( $product->get_id(), $this->rendered, true ) ) {
			return;
		}
		$this->rendered[] = $product->get_id();

		$decimals  = wc_get_price_decimals();
		$variable  = $product->is_type( 'variable' );
		$price     = $variable ? $product->get_variation_price( 'min', true ) : wc_get_price_to_display( $product );
		$base      = Pricing::to_minor( $price, $decimals );
		$max       = $variable ? -1 : (int) $product->get_max_purchase_quantity();
		$min       = max( 1, (int) $product->get_min_purchase_quantity() );
		$settings  = Settings::get();
		$tiers_raw = Tiers::for_product( $product );

		$tiers = array();
		foreach ( $tiers_raw as $tier ) {
			$result  = Pricing::apply( $base, $tier['qty'], $tier['type'], $tier['value'], $decimals );
			$tiers[] = array_merge(
				$tier,
				array(
					'result'   => $result,
					'label'    => Tiers::label( $tier ),
					'disabled' => ( $max >= 0 && $tier['qty'] > $max ) || $tier['qty'] < $min,
				)
			);
		}

		// Never start on a tier that cannot be bought.
		$checked = null;
		foreach ( $tiers as $i => $tier ) {
			if ( $tier['default'] && ! $tier['disabled'] ) {
				$checked = $i;
				break;
			}
		}
		if ( null === $checked ) {
			foreach ( $tiers as $i => $tier ) {
				if ( ! $tier['disabled'] ) {
					$checked = $i;
					break;
				}
			}
		}

		$data = array(
			'base'     => $base,
			'variable' => $variable,
			'min'      => $min,
			'max'      => $max,
			'tiers'    => array_map(
				static function ( $t ) {
					return array(
						'qty'   => (int) $t['qty'],
						'type'  => $t['type'],
						'value' => (float) $t['value'],
					);
				},
				$tiers
			),
		);

		$args = array(
			'product'  => $product,
			'tiers'    => $tiers,
			'checked'  => $checked,
			'data'     => $data,
			'settings' => $settings,
			'name'     => 'bundletiers_tier',
		);
		self::get_template( 'selector.php', $args );
	}

	/**
	 * Loads a template, letting the theme override it in yourtheme/bundletiers/.
	 *
	 * @param string $file Template file name.
	 * @param array  $args Variables for the template.
	 */
	public static function get_template( $file, array $args = array() ) {
		$path = locate_template( 'bundletiers/' . $file );
		if ( ! $path ) {
			$path = BUNDLETIERS_DIR . 'templates/bundletiers/' . $file;
		}
		$path = apply_filters( 'bundletiers_template_path', $path, $file );
		if ( is_readable( $path ) ) {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			extract( $args, EXTR_SKIP );
			include $path;
		}
	}

	/**
	 * Plain-text price for cards: the same value WooCommerce shows, no markup.
	 *
	 * @param int $minor Amount in minor units.
	 * @return string
	 */
	public static function money( $minor ) {
		$text = wp_strip_all_tags( wc_price( Pricing::from_minor( $minor, wc_get_price_decimals() ) ) );
		return html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Loads the selector assets on product pages that use bundles.
	 */
	public function enqueue() {
		if ( ! is_product() || ! self::supports( wc_get_product( get_queried_object_id() ) ) ) {
			return;
		}
		wp_enqueue_style( 'bundletiers', BUNDLETIERS_URL . 'assets/css/frontend.css', array(), BUNDLETIERS_VERSION );
		wp_add_inline_style( 'bundletiers', Settings::style_css() );
		wp_enqueue_script( 'bundletiers', BUNDLETIERS_URL . 'assets/js/frontend.js', array( 'jquery' ), BUNDLETIERS_VERSION, true );
		wp_localize_script(
			'bundletiers',
			'bundletiersConfig',
			array(
				'decimals'    => wc_get_price_decimals(),
				'decimalSep'  => wc_get_price_decimal_separator(),
				'thousandSep' => wc_get_price_thousand_separator(),
				'priceFormat' => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, 'UTF-8' ),
				'symbol'      => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				'textSave'    => Settings::get( 'text_save' ),
				'textPerItem' => Settings::get( 'text_per_item' ),
			)
		);
	}
}
