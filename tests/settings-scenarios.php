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

	// Savings color: a hex color, or empty for the soft green.
	bt_check( 'savings color ok', Settings::clean_style( 'style_save', '#B3123B' ), '#B3123B' );
	bt_check( 'bad savings color -> empty (soft green)', Settings::clean_style( 'style_save', 'red' ), '' );
	update_option( Settings::OPTION, array( 'style_bg' => '#ffffff', 'style_save' => '#b3123b' ) );
	bt_check( 'a savings color is printed with a soft background', false !== strpos( Settings::style_css(), '--bt-save-text:#b3123b;--bt-save-bg:color-mix(in srgb,#b3123b 12%,#ffffff);' ), true );
	update_option( Settings::OPTION, array( 'style_save' => 'javascript:x' ) );
	bt_check( 'a tampered savings color is not printed', false === strpos( Settings::style_css(), '--bt-save' ), true );
	delete_option( Settings::OPTION );
	bt_check( 'the default pill color is not printed', false === strpos( Settings::style_css(), '--bt-save' ), true );

	// Text on the accent color (badge, selected dot): black or white, whichever reads better.
	foreach ( array( '#111111' => '#ffffff', '#ffffff' => '#111111', '#4f46e5' => '#ffffff', '#cf3450' => '#ffffff', '#facc15' => '#111111', '#818cf8' => '#111111', '#fff' => '#111111', '#000' => '#ffffff', 'zzz' => '#ffffff' ) as $accent => $on ) {
		bt_check( "text on $accent is $on", Settings::contrast_color( $accent ), $on );
	}
	update_option( Settings::OPTION, array( 'style_accent' => '#facc15' ) );
	bt_check( 'a light accent gets dark text on the badge', false !== strpos( Settings::style_css(), '--bt-on-accent:#111111;' ), true );
	delete_option( Settings::OPTION );

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
	bt_check( 'defaults css', Settings::style_css(), '.bt-selector{--bt-bg:#ffffff;--bt-border:#d5d7dd;--bt-accent:#111111;--bt-border-width:1px;--bt-radius:4px;--bt-on-accent:#ffffff;}' );
	update_option( Settings::OPTION, array( 'style_bg' => '#fafafa', 'style_border_color' => '#000000', 'style_accent' => '#cf3450', 'style_border_width' => 2, 'style_radius' => 0 ) );
	bt_check( 'saved css', Settings::style_css(), '.bt-selector{--bt-bg:#fafafa;--bt-border:#000000;--bt-accent:#cf3450;--bt-border-width:2px;--bt-radius:0px;--bt-on-accent:#ffffff;}' );
	update_option( Settings::OPTION, array( 'style_bg' => 'javascript:x', 'style_radius' => '1000' ) );
	bt_check( 'tampered option is cleaned on output', Settings::style_css(), '.bt-selector{--bt-bg:#ffffff;--bt-border:#d5d7dd;--bt-accent:#111111;--bt-border-width:1px;--bt-radius:40px;--bt-on-accent:#ffffff;}' );

	// Saving through the real WooCommerce settings screen code path.
	delete_option( Settings::OPTION );
	require_once WP_PLUGIN_DIR . '/woocommerce/includes/admin/class-wc-admin-settings.php';
	new Settings(); // Registers the save filter (normally loaded only in wp-admin).
	$page   = new BundleTiers\Settings_Page();
	$fields   = array();
	$by_field = array();
	foreach ( array_keys( $page->get_sections() ) as $section ) {
		foreach ( $page->get_settings_for_section( $section ) as $field ) {
			$fields[] = $field;
			if ( isset( $field['id'] ) && ! in_array( $field['type'], array( 'title', 'sectionend' ), true ) ) {
				$by_field[ $field['id'] ][] = $section;
			}
		}
	}
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

	// Five sections; every field lives in exactly one of them.
	bt_check( 'five sections', array_keys( $page->get_sections() ), array( '', 'appearance', 'behavior', 'texts', 'status' ) );
	bt_check( 'no field appears in two sections', count( array_filter( $by_field, static function ( $where ) {
		return count( $where ) > 1;
	} ) ), 0 );
	$where = static function ( $key ) use ( $by_field ) {
		return $by_field[ Settings::OPTION . '[' . $key . ']' ] ?? array();
	};
	bt_check( 'tiers are in General', $where( 'tiers' ), array( '' ) );
	bt_check( 'colors and the preview are in Appearance', array( $where( 'style_bg' ), $where( 'style_text' ), $where( 'preview' ), $where( 'style' ), $where( 'position' ) ), array( array( 'appearance' ), array( 'appearance' ), array( 'appearance' ), array( 'appearance' ), array( 'appearance' ) ) );
	bt_check( 'variations, coupons and data are in Behavior', array( $where( 'count_variations' ), $where( 'no_discount_coupon' ), $where( 'delete_data' ) ), array( array( 'behavior' ), array( 'behavior' ), array( 'behavior' ) ) );
	bt_check( 'texts are in Texts', array( $where( 'text_save' ), $where( 'text_per_item' ), $where( 'text_cart_label' ) ), array( array( 'texts' ), array( 'texts' ), array( 'texts' ) ) );
	bt_check( 'Status has no setting to save', array_values( array_filter( $page->get_settings_for_section( 'status' ), static function ( $f ) {
		return ! empty( $f['id'] ) && false !== strpos( $f['id'], Settings::OPTION . '[' );
	} ) ), array() );

	// Status: facts from this site.
	$rows = BundleTiers\Status::rows();
	bt_check( 'status lists the plugin version first', $rows[0]['value'], BUNDLETIERS_VERSION );
	bt_check( 'status rows all have a label, a value and a state', count( array_filter( $rows, static function ( $r ) {
		return '' !== $r['label'] && '' !== (string) $r['value'] && isset( $r['state'] );
	} ) ), count( $rows ) );
	bt_check( 'WooCommerce is reported as supported', $rows[1]['state'], 'ok' );
	$products = BundleTiers\Status::bundle_products();
	bt_check( 'product counts are whole numbers', is_int( $products['with'] ) && is_int( $products['total'] ) && $products['with'] <= $products['total'], true );
	bt_check( 'cart page kind is known', in_array( BundleTiers\Status::page_kind( 'cart' ), array( 'blocks', 'shortcode', 'missing' ), true ), true );
	ob_start();
	( new Settings() )->output_status_field();
	$status_html = ob_get_clean();
	bt_check( 'the Status table has a row per fact', substr_count( $status_html, '<th scope="row">' ), count( $rows ) );

	// The tiers table sits under its label in one full-width cell.
	ob_start();
	( new Settings() )->output_tiers_field( array( 'title' => 'Tiers', 'id' => Settings::OPTION . '[tiers]' ) );
	$tiers_html = ob_get_clean();
	bt_check( 'the tiers editor is in a full-width cell', false !== strpos( $tiers_html, 'colspan="2" class="bt-tiers-cell"' ) && false !== strpos( $tiers_html, 'class="bt-editor"' ), true );
	bt_check( 'the label comes before the table', strpos( $tiers_html, 'bt-field-title' ) < strpos( $tiers_html, '<table' ), true );
	bt_check( 'the tiers row has no two-column heading cell', false === strpos( $tiers_html, 'class="titledesc"' ), true );

	// First-run steps and the products notice.
	$settings_obj = new Settings();
	delete_option( Settings::INTRO_OPTION );
	ob_start();
	$settings_obj->output_intro_field();
	$intro = ob_get_clean();
	bt_check( 'the three steps are shown at first', substr_count( $intro, '<li>' ), 3 );
	bt_check( 'the steps have a hide link with a nonce', false !== strpos( $intro, 'bundletiers_dismiss_intro' ) && false !== strpos( $intro, '_wpnonce=' ), true );
	wp_set_current_user( 0 );
	bt_check( 'a visitor cannot hide the steps', $settings_obj->do_dismiss_intro(), false );
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( (int) $admins[0] );
	$_REQUEST['_wpnonce'] = wp_create_nonce( 'bundletiers_dismiss_intro' );
	bt_check( 'an administrator with the nonce hides the steps', $settings_obj->do_dismiss_intro(), true );
	unset( $_REQUEST['_wpnonce'] );
	wp_set_current_user( 0 );
	ob_start();
	$settings_obj->output_intro_field();
	bt_check( 'the steps stay hidden afterwards', ob_get_clean(), '' );
	delete_option( Settings::INTRO_OPTION );
	ob_start();
	$settings_obj->output_products_notice();
	$notice = ob_get_clean();
	$expected_notice = $products['with'] > 0
		? esc_html( sprintf( __( '%1$d of %2$d products use bundles.', 'bundletiers' ), $products['with'], $products['total'] ) )
		: esc_html__( 'No product uses bundles yet. Open a product, go to Product data, BundleTiers, and tick "Enable bundles".', 'bundletiers' );
	bt_check( 'the notice says how many products use bundles, or invites to start', false !== strpos( $notice, $expected_notice ), true );

	// On narrow screens: one tier per row, or keep the columns (the theme lays the cards out).
	$_POST[ Settings::OPTION ] = array( 'narrow' => 'columns' );
	WC_Admin_Settings::save_fields( $fields );
	bt_check( 'keep the columns can be saved', get_option( Settings::OPTION )['narrow'] ?? null, 'columns' );
	$_POST[ Settings::OPTION ] = array( 'narrow' => 'nonsense' );
	WC_Admin_Settings::save_fields( $fields );
	bt_check( 'an unknown narrow setting falls back to one tier per row', get_option( Settings::OPTION )['narrow'] ?? null, 'stack' );
	bt_check( 'the narrow setting is in Appearance', ( $by_field[ Settings::OPTION . '[narrow]' ] ?? array() ), array( 'appearance' ) );
	bt_check( 'the savings color is in Appearance', ( $by_field[ Settings::OPTION . '[style_save]' ] ?? array() ), array( 'appearance' ) );

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
		bt_check( "preset $id sets every style field", array_keys( $preset['values'] ), array( 'style', 'style_bg', 'style_border_color', 'style_accent', 'style_text', 'style_save', 'style_border_width', 'style_radius' ) );
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
