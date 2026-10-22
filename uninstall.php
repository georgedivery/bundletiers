<?php
/**
 * Removes BundleTiers data when the plugin is deleted, but only if
 * "Delete data on uninstall" was switched on in the settings.
 *
 * Bundle details already saved on past order lines are kept, so order
 * history stays complete. On a multisite network every site is checked
 * against its own setting.
 *
 * @package BundleTiers
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! function_exists( 'bundletiers_uninstall_site' ) ) {
	/**
	 * Removes the data of the current site when its own setting asks for it.
	 */
	function bundletiers_uninstall_site() {
		$bundletiers_settings = get_option( 'bundletiers_settings' );

		if ( is_array( $bundletiers_settings ) && isset( $bundletiers_settings['delete_data'] ) && 'yes' === $bundletiers_settings['delete_data'] ) {
			delete_option( 'bundletiers_settings' );
			delete_option( 'bundletiers_intro_dismissed' );

			delete_post_meta_by_key( '_bundletiers_enabled' );
			delete_post_meta_by_key( '_bundletiers_mode' );
			delete_post_meta_by_key( '_bundletiers_tiers' );
		}
	}
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $bundletiers_site_id ) {
		switch_to_blog( $bundletiers_site_id );
		bundletiers_uninstall_site();
		restore_current_blog();
	}
} else {
	bundletiers_uninstall_site();
}
