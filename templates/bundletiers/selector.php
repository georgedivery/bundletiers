<?php
/**
 * Bundle selector. Override in your theme: yourtheme/bundletiers/selector.php
 *
 * Available: $product, $tiers (with 'result', 'label', 'disabled'), $checked (index),
 * $data (state for the script), $settings, $name (radio input name).
 *
 * The script finds everything through the bt-* classes and data attributes,
 * so keep those if you change the markup.
 *
 * @package BundleTiers
 */

defined( 'ABSPATH' ) || exit;

// Variables in this template are local to the include, not real globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

use BundleTiers\Frontend;
?>
<fieldset class="bt-selector bt-style-<?php echo esc_attr( $settings['style'] ); ?>" data-bundletiers="<?php echo esc_attr( wp_json_encode( $data ) ); ?>">
	<legend class="screen-reader-text"><?php esc_html_e( 'Choose quantity', 'bundletiers' ); ?></legend>
	<div class="bt-options">
		<?php foreach ( $tiers as $i => $tier ) : ?>
			<?php
			$result   = $tier['result'];
			$has_save = $result['saved'] > 0;
			?>
			<label class="bt-option<?php echo $tier['badge'] ? ' has-badge' : ''; ?><?php echo $tier['disabled'] ? ' is-disabled' : ''; ?>" data-qty="<?php echo esc_attr( (string) $tier['qty'] ); ?>">
				<input type="radio" class="bt-radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $tier['qty'] ); ?>"<?php checked( $checked, $i ); ?><?php disabled( $tier['disabled'] ); ?> />
				<span class="bt-card">
					<?php if ( $tier['badge'] ) : ?>
						<span class="bt-badge"><?php echo esc_html( $tier['badge'] ); ?></span>
					<?php endif; ?>
					<span class="bt-label"><?php echo esc_html( $tier['label'] ); ?></span>
					<span class="bt-total"><?php echo esc_html( Frontend::money( $result['total'] ) ); ?></span>
					<span class="bt-full"<?php echo $has_save ? '' : ' hidden'; ?>><del><?php echo esc_html( Frontend::money( $result['full'] ) ); ?></del></span>
					<span class="bt-unit"<?php echo $tier['qty'] > 1 ? '' : ' hidden'; ?>><?php echo esc_html( Frontend::money( $result['unit_price'] ) . ' ' . $settings['text_per_item'] ); ?></span>
					<span class="bt-save"<?php echo $has_save ? '' : ' hidden'; ?>><?php echo esc_html( $settings['text_save'] . ' ' . Frontend::money( $result['saved'] ) ); ?></span>
				</span>
			</label>
		<?php endforeach; ?>
	</div>
	<span class="bt-live screen-reader-text" role="status" aria-live="polite"></span>
</fieldset>
