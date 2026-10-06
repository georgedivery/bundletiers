<?php
/**
 * Removes BundleTiers data when the plugin is deleted, but only if
 * "Delete data on uninstall" was switched on in the settings.
 *
 * Bundle details already saved on past order lines are kept, so order
 * history stays complete.
 *
 * @package BundleTiers
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$bundletiers_settings = get_option( 'bundletiers_settings' );

if ( is_array( $bundletiers_settings ) && isset( $bundletiers_settings['delete_data'] ) && 'yes' === $bundletiers_settings['delete_data'] ) {
	delete_option( 'bundletiers_settings' );

	delete_post_meta_by_key( '_bundletiers_enabled' );
	delete_post_meta_by_key( '_bundletiers_mode' );
	delete_post_meta_by_key( '_bundletiers_tiers' );
}
