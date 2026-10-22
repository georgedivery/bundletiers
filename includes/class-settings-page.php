<?php
/**
 * The BundleTiers tab under WooCommerce → Settings, in five sections.
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * The BundleTiers tab under WooCommerce settings.
 */
class Settings_Page extends \WC_Settings_Page {

	/**
	 * Sets the tab id and label.
	 */
	public function __construct() {
		$this->id    = 'bundletiers';
		$this->label = __( 'BundleTiers', 'bundletiers' );
		parent::__construct();
	}

	/**
	 * The sections of the tab (the first, with an empty id, is the default one).
	 *
	 * @return array
	 */
	protected function get_own_sections() {
		return array(
			''           => __( 'General', 'bundletiers' ),
			'appearance' => __( 'Appearance', 'bundletiers' ),
			'behavior'   => __( 'Behavior', 'bundletiers' ),
			'texts'      => __( 'Texts', 'bundletiers' ),
			'status'     => __( 'Status', 'bundletiers' ),
		);
	}

	/**
	 * Prints the section. Status only shows information, so it has nothing to save.
	 */
	public function output() {
		global $current_section;
		if ( 'status' === $current_section ) {
			$GLOBALS['hide_save_button'] = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WooCommerce's own flag.
		}
		parent::output();
	}

	/**
	 * Fields of one section.
	 *
	 * @param string $section_id Section id.
	 * @return array
	 */
	protected function get_settings_for_section_core( $section_id ) {
		switch ( $section_id ) {
			case 'appearance':
				return $this->appearance_fields();
			case 'behavior':
				return $this->behavior_fields();
			case 'texts':
				return $this->texts_fields();
			case 'status':
				return $this->status_fields();
			default:
				return $this->general_fields();
		}
	}

	/**
	 * General: the default tiers.
	 *
	 * @return array
	 */
	private function general_fields() {
		$o = Settings::OPTION;

		return array(
			array(
				'title' => __( 'Default tiers', 'bundletiers' ),
				'type'  => 'title',
				'desc'  => __( 'Used by every product that has BundleTiers switched on and set to "Global tiers".', 'bundletiers' ),
				'id'    => 'bundletiers_tiers_section',
			),
			array(
				'type'      => 'bundletiers_intro',
				'id'        => 'bundletiers_intro',
				'is_option' => false,
			),
			array(
				'type'      => 'bundletiers_products_notice',
				'id'        => 'bundletiers_products_notice',
				'is_option' => false,
			),
			array(
				'title' => __( 'Tiers', 'bundletiers' ),
				'type'  => 'bundletiers_tiers',
				'id'    => $o . '[tiers]',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'bundletiers_tiers_section',
			),
		);
	}

	/**
	 * Appearance: presets, preview, colors and shape.
	 *
	 * @return array
	 */
	private function appearance_fields() {
		$o = Settings::OPTION;

		return array(
			array(
				'title' => __( 'Appearance', 'bundletiers' ),
				'type'  => 'title',
				'desc'  => __( 'The cards use the font of your theme. Colors and shape can be set here.', 'bundletiers' ),
				'id'    => 'bundletiers_look_section',
			),
			array(
				'title'     => __( 'Style presets and preview', 'bundletiers' ),
				'type'      => 'bundletiers_preview',
				'id'        => $o . '[preview]',
				'is_option' => false,
			),
			array(
				'title'    => __( 'Position', 'bundletiers' ),
				'type'     => 'select',
				'default'  => 'before',
				'options'  => array(
					'before' => __( 'Before the add to cart button', 'bundletiers' ),
					'after'  => __( 'After the add to cart button', 'bundletiers' ),
				),
				'desc_tip' => __( 'Where the cards sit next to the add to cart button.', 'bundletiers' ),
				'id'       => $o . '[position]',
			),
			array(
				'type'    => 'bundletiers_style',
				'default' => 'cards',
				'id'      => $o . '[style]',
			),
			array(
				'title'    => __( 'Card background', 'bundletiers' ),
				'type'     => 'color',
				'default'  => '#ffffff',
				'css'      => 'width:6em;',
				'desc_tip' => __( 'Background of every card.', 'bundletiers' ),
				'id'       => $o . '[style_bg]',
			),
			array(
				'title'    => __( 'Border color', 'bundletiers' ),
				'type'     => 'color',
				'default'  => '#d5d7dd',
				'css'      => 'width:6em;',
				'desc_tip' => __( 'Border of the cards that are not selected.', 'bundletiers' ),
				'id'       => $o . '[style_border_color]',
			),
			array(
				'title'    => __( 'Accent color', 'bundletiers' ),
				'type'     => 'color',
				'default'  => '#111111',
				'css'      => 'width:6em;',
				'desc_tip' => __( 'Border of the selected card and the badge.', 'bundletiers' ),
				'id'       => $o . '[style_accent]',
			),
			array(
				'title'    => __( 'Text color', 'bundletiers' ),
				'type'     => 'color',
				'default'  => '',
				'css'      => 'width:6em;',
				'desc_tip' => __( 'Text on the cards. Leave empty to use the color of your theme.', 'bundletiers' ),
				'id'       => $o . '[style_text]',
			),
			array(
				'title'    => __( 'Savings color', 'bundletiers' ),
				'type'     => 'color',
				'default'  => '',
				'css'      => 'width:6em;',
				'desc_tip' => __( 'The "you save" pill. Leave empty for the soft green.', 'bundletiers' ),
				'id'       => $o . '[style_save]',
			),
			array(
				'title'             => __( 'Border width (px)', 'bundletiers' ),
				'type'              => 'number',
				'default'           => 1,
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min'  => 0,
					'max'  => 10,
					'step' => 1,
				),
				'id'                => $o . '[style_border_width]',
			),
			array(
				'title'             => __( 'Corner radius (px)', 'bundletiers' ),
				'type'              => 'number',
				'default'           => 4,
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min'  => 0,
					'max'  => 40,
					'step' => 1,
				),
				'id'                => $o . '[style_radius]',
			),
			array(
				'title'    => __( 'On narrow screens', 'bundletiers' ),
				'type'     => 'select',
				'default'  => 'stack',
				'options'  => array(
					'stack'   => __( 'One tier per row', 'bundletiers' ),
					'columns' => __( 'Keep the columns', 'bundletiers' ),
				),
				'desc_tip' => __( 'Choose "Keep the columns" if your theme lays out the cards itself.', 'bundletiers' ),
				'id'       => $o . '[narrow]',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'bundletiers_look_section',
			),
		);
	}

	/**
	 * Behavior: how bundles are counted and priced, and what happens to the data.
	 *
	 * @return array
	 */
	private function behavior_fields() {
		$o = Settings::OPTION;

		return array(
			array(
				'title' => __( 'In the cart', 'bundletiers' ),
				'type'  => 'title',
				'desc'  => __( 'How the quantities are counted and how coupons treat bundles.', 'bundletiers' ),
				'id'    => 'bundletiers_cart_section',
			),
			array(
				'title'   => __( 'Count variations together', 'bundletiers' ),
				'desc'    => __( 'Add up the quantities of all variations of a product to find the tier (1 × S + 2 × M = pack of 3).', 'bundletiers' ),
				'type'    => 'checkbox',
				'default' => 'yes',
				'id'      => $o . '[count_variations]',
			),
			array(
				'title'   => __( 'Coupons', 'bundletiers' ),
				'desc'    => __( 'Do not allow coupons on products that get a bundle discount', 'bundletiers' ),
				'type'    => 'checkbox',
				'default' => 'no',
				'id'      => $o . '[no_discount_coupon]',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'bundletiers_cart_section',
			),

			array(
				'title' => __( 'Data', 'bundletiers' ),
				'type'  => 'title',
				'id'    => 'bundletiers_data_section',
			),
			array(
				'title'   => __( 'Delete data on uninstall', 'bundletiers' ),
				'desc'    => __( 'Remove all BundleTiers settings and product data when the plugin is deleted', 'bundletiers' ),
				'type'    => 'checkbox',
				'default' => 'no',
				'id'      => $o . '[delete_data]',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'bundletiers_data_section',
			),
		);
	}

	/**
	 * Texts shown in the cards, the cart and orders.
	 *
	 * @return array
	 */
	private function texts_fields() {
		$o = Settings::OPTION;

		return array(
			array(
				'title' => __( 'Texts', 'bundletiers' ),
				'type'  => 'title',
				'desc'  => __( 'Leave a text empty to use the one in the language of your site.', 'bundletiers' ),
				'id'    => 'bundletiers_texts_section',
			),
			array(
				'title'    => __( 'Text: savings', 'bundletiers' ),
				'type'     => 'text',
				'default'  => __( 'You save', 'bundletiers' ),
				'desc_tip' => __( 'Shown before the saved amount.', 'bundletiers' ),
				'id'       => $o . '[text_save]',
			),
			array(
				'title'    => __( 'Text: per item', 'bundletiers' ),
				'type'     => 'text',
				'default'  => __( 'per item', 'bundletiers' ),
				'desc_tip' => __( 'Shown after the price per item.', 'bundletiers' ),
				'id'       => $o . '[text_per_item]',
			),
			array(
				'title'    => __( 'Text: cart label', 'bundletiers' ),
				'type'     => 'text',
				'default'  => __( 'Bundle', 'bundletiers' ),
				'desc_tip' => __( 'Label of the bundle in the cart and in orders.', 'bundletiers' ),
				'id'       => $o . '[text_cart_label]',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'bundletiers_texts_section',
			),
		);
	}

	/**
	 * Status: versions and what the plugin found on this site.
	 *
	 * @return array
	 */
	private function status_fields() {
		return array(
			array(
				'title' => __( 'Status', 'bundletiers' ),
				'type'  => 'title',
				'desc'  => __( 'What BundleTiers found on this site. Nothing here is sent anywhere; copy it when you ask for support.', 'bundletiers' ),
				'id'    => 'bundletiers_status_section',
			),
			array(
				'type'      => 'bundletiers_status',
				'id'        => 'bundletiers_status',
				'is_option' => false,
			),
			array(
				'type' => 'sectionend',
				'id'   => 'bundletiers_status_section',
			),
		);
	}
}
