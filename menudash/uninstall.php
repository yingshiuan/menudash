<?php
/**
 * Deleting the plugin removes its saved settings but keeps uploads/menudash/ (the CSV
 * files and photos), so installing it again brings the menu back as it was. Two small
 * options stay with the files for the same reason: menudash_csv_names (the names the owner
 * gave the CSVs) and menudash_specials_live (which specials file was on the site).
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'menudash_data' );
delete_option( 'menudash_specials' );
delete_option( 'menudash_closed' );
delete_option( 'menudash_hours' );
