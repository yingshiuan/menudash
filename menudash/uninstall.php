<?php
/**
 * Deleting the plugin removes its saved settings but keeps uploads/menudash/ (the CSV
 * files and photos), so installing it again brings the menu back as it was. One small
 * option stays with the files for the same reason: menudash_csv_names (the names the owner
 * gave the CSVs). Each add-on removes its own settings when it is deleted.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'menudash_data' );
delete_option( 'menudash_colors' );
