<?php
/**
 * Deleting the plugin removes its saved settings but keeps uploads/menudash/ (the CSV
 * files and photos), so installing it again brings the menu back as it was.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'menudash_data' );
