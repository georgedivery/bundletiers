<?php
/**
 * Admin markup for the repeatable tier rows (shared by settings and product tab).
 *
 * @package BundleTiers
 */

namespace BundleTiers;

defined( 'ABSPATH' ) || exit;

/**
 * Repeatable tier rows for the admin screens.
 */
class Tiers_Editor {

	/**
	 * Prints the editor.
	 *
	 * @param string $name  Base input name, e.g. "bundletiers_settings[tiers]".
	 * @param array  $tiers Current tiers.
	 */
	public static function render( $name, array $tiers ) {
		$next     = 0;
		$types    = Tiers::type_labels();
		$currency = get_woocommerce_currency_symbol();

		echo '<div class="bt-editor" data-name="' . esc_attr( $name ) . '">';
		echo '<table class="widefat bt-tiers"><thead><tr>';
		echo '<th class="bt-col-default">' . esc_html__( 'Default', 'bundletiers' ) . '</th>';
		echo '<th class="bt-col-qty">' . esc_html__( 'Qty', 'bundletiers' ) . '</th>';
		echo '<th>' . esc_html__( 'Discount', 'bundletiers' ) . '</th>';
		echo '<th class="bt-col-value">' . esc_html__( 'Value', 'bundletiers' ) . '</th>';
		echo '<th>' . esc_html__( 'Label', 'bundletiers' ) . '</th>';
		echo '<th>' . esc_html__( 'Badge', 'bundletiers' ) . '</th>';
		echo '<th class="bt-col-remove"></th>';
		echo '</tr></thead><tbody>';
		foreach ( array_values( $tiers ) as $i => $tier ) {
			self::row( $name, (string) $i, $tier, $types );
			$next = $i + 1;
		}
		echo '</tbody></table>';

		echo '<p class="bt-actions"><button type="button" class="button bt-add">' . esc_html__( 'Add tier', 'bundletiers' ) . '</button> ';
		echo '<span class="description">' . esc_html(
			sprintf(
				/* translators: %s: currency symbol */
				__( 'Percent: 0–100. Amounts are in %s. The default tier is preselected on the product page.', 'bundletiers' ),
				html_entity_decode( $currency, ENT_QUOTES, 'UTF-8' )
			)
		) . '</span></p>';

		echo '<script type="text/html" class="bt-row-template" data-next="' . esc_attr( (string) $next ) . '">';
		self::row(
			$name,
			'__INDEX__',
			array(
				'qty'     => '',
				'type'    => Pricing::PERCENT,
				'value'   => '',
				'label'   => '',
				'badge'   => '',
				'default' => false,
			),
			$types
		);
		echo '</script></div>';
	}

	/**
	 * One tier row.
	 *
	 * @param string $name  Base input name.
	 * @param string $key   Row key.
	 * @param array  $tier  Tier values.
	 * @param array  $types Type labels.
	 */
	private static function row( $name, $key, array $tier, array $types ) {
		$base = $name . '[' . $key . ']';
		echo '<tr class="bt-row">';
		echo '<td class="bt-col-default"><input type="radio" name="' . esc_attr( $name . '[default]' ) . '" value="' . esc_attr( $key ) . '"' . checked( ! empty( $tier['default'] ), true, false ) . ' /></td>';
		echo '<td><input type="number" min="1" step="1" class="bt-qty" name="' . esc_attr( $base . '[qty]' ) . '" value="' . esc_attr( (string) $tier['qty'] ) . '" /></td>';
		echo '<td><select name="' . esc_attr( $base . '[type]' ) . '">';
		foreach ( $types as $type => $label ) {
			echo '<option value="' . esc_attr( $type ) . '"' . selected( $tier['type'], $type, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td>';
		echo '<td><input type="text" inputmode="decimal" class="wc_input_decimal bt-value" name="' . esc_attr( $base . '[value]' ) . '" value="' . esc_attr( '' === $tier['value'] ? '' : wc_format_localized_decimal( (string) $tier['value'] ) ) . '" /></td>';
		echo '<td><input type="text" name="' . esc_attr( $base . '[label]' ) . '" value="' . esc_attr( $tier['label'] ) . '" placeholder="' . esc_attr__( 'e.g. 2 pcs', 'bundletiers' ) . '" /></td>';
		echo '<td><input type="text" name="' . esc_attr( $base . '[badge]' ) . '" value="' . esc_attr( $tier['badge'] ) . '" placeholder="' . esc_attr__( 'e.g. Best value', 'bundletiers' ) . '" /></td>';
		echo '<td class="bt-col-remove"><button type="button" class="button-link bt-remove" aria-label="' . esc_attr__( 'Remove tier', 'bundletiers' ) . '">&times;</button></td>';
		echo '</tr>';
	}
}
