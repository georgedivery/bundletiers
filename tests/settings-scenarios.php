<?php
/**
 * Settings checks against a real WordPress (local only!).
 *   wp eval-file wp-content/plugins/bundletiers/tests/settings-scenarios.php
 * Saves and restores the BundleTiers settings.
 */

use BundleTiers\Settings;

if ( 'local.brivanto.com' !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
	WP_CLI::error( 'Refusing to run: this is not the local site.' );
}

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
} finally {
	unset( $_POST[ Settings::OPTION ] );
	if ( null === $original ) {
		delete_option( Settings::OPTION );
	} else {
		update_option( Settings::OPTION, $original );
	}
}

echo $GLOBALS['bt_fail'] ? "\n{$GLOBALS['bt_fail']} of {$GLOBALS['bt_n']} checks FAILED\n" : "All {$GLOBALS['bt_n']} checks passed\n";
