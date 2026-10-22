<?php
/**
 * Settings checks against a real WordPress (local only!).
 *   wp eval-file wp-content/plugins/bundletiers/tests/settings-scenarios.php
 * Saves and restores the BundleTiers settings.
 */

use BundleTiers\Settings;

require_once __DIR__ . '/helpers.php';
bt_require_local_site();

$GLOBALS['bt_fail'] = 0;
$GLOBALS['bt_n']    = 0;
function bt_check( $label, $actual, $expected ) {
	++$GLOBALS['bt_n'];
	if ( $actual !== $expected ) {
		++$GLOBALS['bt_fail'];
		echo "FAIL  $label\n  expected: " . json_encode( $expected ) . "\n  actual:   " . json_encode( $actual ) . "\n";
	}
}

$original = get_option( Settings::OPTION, null );

try {
	// Cleaning.
	bt_check( 'colour ok', Settings::clean_style( 'style_bg', '#ABCDEF' ), '#ABCDEF' );
	bt_check( 'short colour ok', Settings::clean_style( 'style_bg', '#fff' ), '#fff' );
	bt_check( 'colour with spaces', Settings::clean_style( 'style_border_color', '  #112233 ' ), '#112233' );
	bt_check( 'bad colour -> default', Settings::clean_style( 'style_bg', 'red' ), '#ffffff' );
	bt_check( 'script in colour -> default', Settings::clean_style( 'style_border_color', '#fff;}</style><script>' ), '#d5d7dd' );
	bt_check( 'array colour -> default', Settings::clean_style( 'style_bg', array( '#fff' ) ), '#ffffff' );
	bt_check( 'accent ok', Settings::clean_style( 'style_accent', '#CF3450' ), '#CF3450' );
	bt_check( 'bad accent -> default', Settings::clean_style( 'style_accent', 'blue' ), '#111111' );
	bt_check( 'width in range', Settings::clean_style( 'style_border_width', '3' ), 3 );
	bt_check( 'width too big', Settings::clean_style( 'style_border_width', '99' ), 10 );
	bt_check( 'width negative', Settings::clean_style( 'style_border_width', '-2' ), 0 );
	bt_check( 'width not a number -> default', Settings::clean_style( 'style_border_width', 'abc' ), 1 );
	bt_check( 'radius in range', Settings::clean_style( 'style_radius', 12 ), 12 );
	bt_check( 'radius too big', Settings::clean_style( 'style_radius', '500' ), 40 );
	bt_check( 'radius rounds', Settings::clean_style( 'style_radius', '7.6' ), 8 );

	// Text color: a hex color, or empty to keep the theme's.
	bt_check( 'text color ok', Settings::clean_style( 'style_text', '#FFFFFF' ), '#FFFFFF' );
	bt_check( 'bad text color -> empty (theme color)', Settings::clean_style( 'style_text', 'white' ), '' );
	bt_check( 'empty text color stays empty', Settings::clean_style( 'style_text', '' ), '' );
	update_option( Settings::OPTION, array( 'style_bg' => '#111111', 'style_text' => '#ffffff' ) );
	bt_check( 'a text color is printed with a faded soft color', false !== strpos( Settings::style_css(), '--bt-text:#ffffff;--bt-muted:color-mix(in srgb,#ffffff 62%,#111111);' ), true );
	update_option( Settings::OPTION, array( 'style_text' => 'javascript:x' ) );
	bt_check( 'a tampered text color is not printed', false === strpos( Settings::style_css(), '--bt-text' ), true );

	// CSS output with defaults and with saved values.
	delete_option( Settings::OPTION );
	bt_check( 'defaults css', Settings::style_css(), '.bt-selector{--bt-bg:#ffffff;--bt-border:#d5d7dd;--bt-accent:#111111;--bt-border-width:1px;--bt-radius:4px;}' );
	update_option( Settings::OPTION, array( 'style_bg' => '#fafafa', 'style_border_color' => '#000000', 'style_accent' => '#cf3450', 'style_border_width' => 2, 'style_radius' => 0 ) );
	bt_check( 'saved css', Settings::style_css(), '.bt-selector{--bt-bg:#fafafa;--bt-border:#000000;--bt-accent:#cf3450;--bt-border-width:2px;--bt-radius:0px;}' );
	update_option( Settings::OPTION, array( 'style_bg' => 'javascript:x', 'style_radius' => '1000' ) );
	bt_check( 'tampered option is cleaned on output', Settings::style_css(), '.bt-selector{--bt-bg:#ffffff;--bt-border:#d5d7dd;--bt-accent:#111111;--bt-border-width:1px;--bt-radius:40px;}' );

	// Saving through the real WooCommerce settings screen code path.
	delete_option( Settings::OPTION );
	require_once WP_PLUGIN_DIR . '/woocommerce/includes/admin/class-wc-admin-settings.php';
	new Settings(); // Registers the save filter (normally loaded only in wp-admin).
	$page   = new BundleTiers\Settings_Page();
	$fields = $page->get_settings_for_section( '' );
	$ids    = wp_list_pluck( $fields, 'id' );
	foreach ( array( 'style_bg', 'style_border_color', 'style_accent', 'style_border_width', 'style_radius' ) as $key ) {
		bt_check( "field exists: $key", in_array( Settings::OPTION . '[' . $key . ']', $ids, true ), true );
	}
	$_POST[ Settings::OPTION ] = array(
		'style_bg'           => '#101010',
		'style_border_color' => 'not-a-colour',
		'style_accent'       => '#cf3450',
		'style_border_width' => '4',
		'style_radius'       => '99',
	);
	WC_Admin_Settings::save_fields( $fields );
	$saved = get_option( Settings::OPTION );
	bt_check( 'saved: bg', $saved['style_bg'] ?? null, '#101010' );
	bt_check( 'saved: accent', $saved['style_accent'] ?? null, '#cf3450' );
	bt_check( 'saved: bad colour falls back', $saved['style_border_color'] ?? null, '#d5d7dd' );
	bt_check( 'saved: width', $saved['style_border_width'] ?? null, 4 );
	bt_check( 'saved: radius clamped', $saved['style_radius'] ?? null, 40 );

	// Default texts saved in English follow the language of the site; typed texts stay as typed.
	$translate = static function ( $text, $original, $domain ) {
		return 'bundletiers' === $domain ? array(
			'You save'     => 'Спестявате',
			'per item'     => 'на брой',
			'Bundle'       => 'Комплект',
			'Most popular' => 'Най-избиран',
		)[ $original ] ?? $text : $text;
	};
	add_filter( 'gettext', $translate, 10, 3 );
	update_option( Settings::OPTION, array( 'text_save' => 'You save', 'text_per_item' => '', 'text_cart_label' => 'Mix pack' ) );
	bt_check( 'a text saved as the English default is translated', Settings::get( 'text_save' ), 'Спестявате' );
	bt_check( 'an empty text becomes the translated default', Settings::get( 'text_per_item' ), 'на брой' );
	bt_check( 'a text somebody typed stays as typed', Settings::get( 'text_cart_label' ), 'Mix pack' );
	bt_check( 'the stock badge is translated', BundleTiers\Tiers::badge( array( 'badge' => 'Most popular' ) ), 'Най-избиран' );
	bt_check( 'a typed badge stays as typed', BundleTiers\Tiers::badge( array( 'badge' => 'Best seller' ) ), 'Best seller' );
	bt_check( 'no badge stays empty', BundleTiers\Tiers::badge( array() ), '' );
	remove_filter( 'gettext', $translate, 10 );
	delete_option( Settings::OPTION );
	bt_check( 'asset version changes with the file', 0 === strpos( BundleTiers\Plugin::asset_version( 'assets/css/frontend.css' ), BUNDLETIERS_VERSION . '.' ), true );

	// The style presets and the live preview.
	bt_check( 'three layouts are known', Settings::layouts(), array( 'cards', 'list', 'minimal' ) );
	foreach ( Settings::presets() as $id => $preset ) {
		bt_check( "preset $id uses a known layout", in_array( $preset['values']['style'], Settings::layouts(), true ), true );
	}
	$_POST[ Settings::OPTION ] = array( 'style' => 'minimal' );
	WC_Admin_Settings::save_fields( $fields );
	bt_check( 'the minimal layout can be saved', get_option( Settings::OPTION )['style'] ?? null, 'minimal' );
	$_POST[ Settings::OPTION ] = array( 'style' => 'fancy' );
	WC_Admin_Settings::save_fields( $fields );
	bt_check( 'an unknown layout falls back to cards', get_option( Settings::OPTION )['style'] ?? null, 'cards' );
	bt_check( 'three style presets exist', array_keys( Settings::presets() ), array( 'cards', 'list', 'minimal' ) );
	foreach ( Settings::presets() as $id => $preset ) {
		bt_check( "preset $id sets every style field", array_keys( $preset['values'] ), array( 'style', 'style_bg', 'style_border_color', 'style_accent', 'style_text', 'style_border_width', 'style_radius' ) );
		foreach ( array( 'style_bg', 'style_border_color', 'style_accent' ) as $key ) {
			bt_check( "preset $id $key is a valid color", Settings::clean_style( $key, $preset['values'][ $key ] ), $preset['values'][ $key ] );
		}
		bt_check( "preset $id width is in range", Settings::clean_style( 'style_border_width', $preset['values']['style_border_width'] ), $preset['values']['style_border_width'] );
		bt_check( "preset $id radius is in range", Settings::clean_style( 'style_radius', $preset['values']['style_radius'] ), $preset['values']['style_radius'] );
	}
	ob_start();
	( new Settings() )->output_preview_field( array( 'title' => 'Style presets and preview' ) );
	$preview = ob_get_clean();
	bt_check( 'preview prints the real selector with a card per tier', substr_count( $preview, 'class="bt-radio"' ), count( Settings::get( 'tiers' ) ) );
	bt_check( 'preview has a button per preset', substr_count( $preview, 'class="button bt-preset"' ), 3 );
	bt_check( 'preview carries the preset values for the script', false !== strpos( $preview, 'data-presets=' ) && false !== strpos( $preview, 'style_radius' ), true );
	bt_check( 'the preview field is not saved as a setting', array_key_exists( 'preview', (array) get_option( Settings::OPTION, array() ) ), false );

	// The layout is set by the presets, not by a visible drop-down.
	$style_field = array_values(
		array_filter(
			$fields,
			static function ( $f ) {
				return isset( $f['id'] ) && Settings::OPTION . '[style]' === $f['id'];
			}
		)
	);
	bt_check( 'the style field is not a visible select', $style_field[0]['type'] ?? null, 'bundletiers_style' );
	ob_start();
	( new Settings() )->output_style_field( $style_field[0] );
	$hidden = ob_get_clean();
	bt_check( 'the layout stays in the form as a hidden value', false !== strpos( $hidden, 'type="hidden"' ) && false !== strpos( $hidden, 'name="' . Settings::OPTION . '[style]"' ), true );

	// The tier editor labels every field for screen readers.
	ob_start();
	BundleTiers\Tiers_Editor::render( Settings::OPTION . '[tiers]', BundleTiers\Tiers::defaults() );
	$editor = ob_get_clean();
	bt_check( 'every cell has a label for the stacked layout', substr_count( $editor, 'data-label=' ) >= 6 * ( count( BundleTiers\Tiers::defaults() ) + 1 ), true );
	bt_check( 'every field of a tier row has an aria-label', substr_count( $editor, 'aria-label=' ) >= 6 * ( count( BundleTiers\Tiers::defaults() ) + 1 ), true );

	// Select fields only keep a choice the field offers.
	foreach ( array( 'position' => array( 'after', 'before' ), 'style' => array( 'list', 'cards' ) ) as $key => $choices ) {
		$_POST[ Settings::OPTION ] = array( $key => $choices[0] );
		WC_Admin_Settings::save_fields( $fields );
		bt_check( "saved: valid $key kept", get_option( Settings::OPTION )[ $key ] ?? null, $choices[0] );
		$_POST[ Settings::OPTION ] = array( $key => '"><script>alert(1)</script>' );
		WC_Admin_Settings::save_fields( $fields );
		bt_check( "saved: bad $key falls back to default", get_option( Settings::OPTION )[ $key ] ?? null, $choices[1] );
	}
} finally {
	unset( $_POST[ Settings::OPTION ] );
	if ( null === $original ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original );
	}
}

echo $GLOBALS['bt_fail'] ? "\n{$GLOBALS['bt_fail']} of {$GLOBALS['bt_n']} checks FAILED\n" : "All {$GLOBALS['bt_n']} checks passed\n";
