<?php
/**
 * Plugin Name:       MenuDash
 * Description:       A restaurant menu for your website, kept in a spreadsheet: upload the menu as CSV and the dish photos under MenuDash, then put [menudash] on a page. Guests read it in German, English and Chinese, all at once or one at a time, and filter by diet.
 * Version:           1.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            insdash
 * License:           GPL-2.0-or-later
 * Text Domain:       menudash
 */

defined( 'ABSPATH' ) || exit;

define( 'MENUDASH_VERSION', '1.1.0' );
define( 'MENUDASH_NAME', 'MenuDash' );
define( 'MENUDASH_FILE', __FILE__ );
define( 'MENUDASH_DIR', plugin_dir_path( __FILE__ ) );
define( 'MENUDASH_URL', plugin_dir_url( __FILE__ ) );

require MENUDASH_DIR . 'includes/csv-parser.php';
require MENUDASH_DIR . 'includes/strings.php';
require MENUDASH_DIR . 'includes/menu-store.php';
require MENUDASH_DIR . 'includes/photo-store.php';
require MENUDASH_DIR . 'includes/svg-clean.php';
require MENUDASH_DIR . 'includes/icons.php';
require MENUDASH_DIR . 'includes/render.php';

if ( is_admin() ) {
	require MENUDASH_DIR . 'includes/admin.php';
}

register_activation_hook( __FILE__, 'mdash_activate' );
add_shortcode( 'menudash', 'mdash_shortcode' );
add_action( 'wp_enqueue_scripts', 'mdash_enqueue' );
