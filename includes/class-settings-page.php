<?php
/**
 * The BundleTiers tab under WooCommerce → Settings.
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
	 * Fields of the settings tab.
	 *
	 * @param string $section_id Section id (single section, so unused).
	 * @return array
	 */
	protected function get_settings_for_section_core( $section_id ) {
		$o = Settings::OPTION;

		return array(
			array(
				'title' => __( 'Default tiers', 'bundletiers' ),
				'type'  => 'title',
				'desc'  => __( 'Used by every product that has BundleTiers switched on and set to "Global tiers".', 'bundletiers' ),
				'id'    => 'bundletiers_tiers_section',
			),
			array(
				'title' => __( 'Tiers', 'bundletiers' ),
				'type'  => 'bundletiers_tiers',
				'id'    => $o . '[tiers]',
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
				'id'   => 'bundletiers_tiers_section',
			),

			array(
				'title' => __( 'Appearance', 'bundletiers' ),
				'type'  => 'title',
				'desc'  => __( 'The cards use the font of your theme. Colours and shape can be set here.', 'bundletiers' ),
				'id'    => 'bundletiers_look_section',
			),
			array(
				'title'   => __( 'Position', 'bundletiers' ),
				'type'    => 'select',
				'default' => 'before',
				'options' => array(
					'before' => __( 'Before the add to cart button', 'bundletiers' ),
					'after'  => __( 'After the add to cart button', 'bundletiers' ),
				),
				'id'      => $o . '[position]',
			),
			array(
				'title'   => __( 'Style', 'bundletiers' ),
				'type'    => 'select',
				'default' => 'cards',
				'options' => array(
					'cards' => __( 'Cards', 'bundletiers' ),
					'list'  => __( 'List', 'bundletiers' ),
				),
				'id'      => $o . '[style]',
			),
			array(
				'title'   => __( 'Card background', 'bundletiers' ),
				'type'    => 'color',
				'default' => '#ffffff',
				'css'     => 'width:6em;',
				'id'      => $o . '[style_bg]',
			),
			array(
				'title'   => __( 'Border colour', 'bundletiers' ),
				'type'    => 'color',
				'default' => '#d5d7dd',
				'css'     => 'width:6em;',
				'id'      => $o . '[style_border_color]',
			),
			array(
				'title'    => __( 'Accent colour', 'bundletiers' ),
				'type'     => 'color',
				'default'  => '#111111',
				'css'      => 'width:6em;',
				'desc_tip' => __( 'Border of the selected card and the badge.', 'bundletiers' ),
				'id'       => $o . '[style_accent]',
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
				'id'   => 'bundletiers_look_section',
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
}
