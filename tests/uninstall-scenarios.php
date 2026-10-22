<?php
/**
 * Uninstall removes the data only when asked to (local only!).
 *
 *   wp eval-file wp-content/plugins/bundletiers/tests/uninstall-scenarios.php
 *
 * Runs uninstall.php for real, so it first saves the BundleTiers option and post
 * meta and puts them back at the end.
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

global $wpdb;
$keys   = array( '_bundletiers_enabled', '_bundletiers_mode', '_bundletiers_tiers' );
$marks  = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
$backup = sys_get_temp_dir() . '/bundletiers-uninstall-test-backup-' . md5( home_url() ) . '.json';

/**
 * Puts back the option and post meta saved in the backup file, then removes the file.
 *
 * @param string $file Backup file.
 */
function bt_restore_backup( $file ) {
	if ( ! is_readable( $file ) ) {
		return;
	}
	$data = json_decode( (string) file_get_contents( $file ), true );
	if ( is_array( $data ) ) {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_bundletiers_enabled','_bundletiers_mode','_bundletiers_tiers')" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $data['meta'] as $row ) {
			add_post_meta( (int) $row['post_id'], $row['meta_key'], maybe_unserialize( $row['meta_value'] ) );
		}
		if ( null === ( $data['intro'] ?? null ) ) {
			delete_option( 'bundletiers_intro_dismissed' );
		} else {
			update_option( 'bundletiers_intro_dismissed', $data['intro'], false );
		}
		if ( null === $data['option'] ) {
			delete_option( Settings::OPTION );
		} else {
			update_option( Settings::OPTION, maybe_unserialize( $data['option'] ) );
		}
	}
	unlink( $file );
}

// A run that stopped half way left its data in the backup file: put it back before starting.
bt_restore_backup( $backup );

$saved = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ($marks)", $keys ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$raw    = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", Settings::OPTION ) );
file_put_contents( $backup, wp_json_encode( array( 'meta' => $saved, 'option' => $raw, 'intro' => get_option( 'bundletiers_intro_dismissed', null ) ) ) );
$product = null;

function bt_uninstall() {
	if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
		define( 'WP_UNINSTALL_PLUGIN', 'bundletiers/bundletiers.php' );
	}
	include dirname( __DIR__ ) . '/uninstall.php';
}

try {
	$product = new WC_Product_Simple();
	$product->set_name( 'BT uninstall test' );
	$product->set_regular_price( '5' );
	$product->set_status( 'publish' );
	$product->update_meta_data( '_bundletiers_enabled', 'yes' );
	$product->update_meta_data( '_bundletiers_mode', 'global' );
	$product->save();

	// Switched off (the default): nothing is removed.
	update_option( Settings::OPTION, array( 'delete_data' => 'no' ) );
	bt_uninstall();
	bt_check( 'delete_data off: the option stays', false !== get_option( Settings::OPTION, false ), true );
	bt_check( 'delete_data off: the product meta stays', get_post_meta( $product->get_id(), '_bundletiers_enabled', true ), 'yes' );

	// Switched on: option and meta go.
	update_option( Settings::OPTION, array( 'delete_data' => 'yes' ) );
	update_option( 'bundletiers_intro_dismissed', 'yes', false );
	bt_uninstall();
	bt_check( 'delete_data on: the hidden first-run steps are forgotten too', get_option( 'bundletiers_intro_dismissed', false ), false );
	bt_check( 'delete_data on: the option is removed', get_option( Settings::OPTION, false ), false );
	bt_check( 'delete_data on: the product meta is removed', get_post_meta( $product->get_id(), '_bundletiers_enabled', true ), '' );
	bt_check( 'delete_data on: no BundleTiers meta is left', (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN ($marks)", $keys ) ), 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
} finally {
	if ( $product && $product->get_id() ) {
		$product->delete( true );
	}
	bt_restore_backup( $backup );
}

echo $GLOBALS['bt_fail'] ? "\n{$GLOBALS['bt_fail']} of {$GLOBALS['bt_n']} checks FAILED\n" : "All {$GLOBALS['bt_n']} checks passed\n";
