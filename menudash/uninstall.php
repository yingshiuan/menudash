<?php
/**
 * Deleting the plugin removes its saved settings but keeps uploads/menudash/ (the CSV
 * files and photos), so installing it again brings the menu back as it was. One small
 * option stays with the files for the same reason: menudash_csv_names (the names the owner
 * gave the CSVs). Each add-on removes its own settings when it is deleted.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/** One site's settings. */
function mdash_uninstall_site() {
	delete_option( 'menudash_data' );
	delete_option( 'menudash_colors' );
	delete_option( 'menudash_fonts' );
	delete_option( 'menudash_qr' );
	delete_option( 'menudash_origin' );
	delete_option( 'menudash_chips_off' );
	delete_option( 'menudash_list' );
	delete_option( 'menudash_list_share' );
	delete_transient( 'menudash_menu_pages' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $mdash_site ) {
		switch_to_blog( $mdash_site );
		mdash_uninstall_site();
		restore_current_blog();
	}
} else {
	mdash_uninstall_site();
}
delete_site_transient( 'menudash_release' );
