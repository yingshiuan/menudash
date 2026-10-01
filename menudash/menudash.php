<?php
/**
 * Plugin Name:       MenuDash
 * Description:       A restaurant menu for your website, kept in a spreadsheet: upload the menu as CSV and the dish photos under MenuDash, then put [menudash] on a page. Guests read it in German, English and Chinese, all at once or one at a time, and filter by diet. Background and highlight colours and the diet icons are chosen on the MenuDash page, and a QR code to the menu prints as table cards. Add-ons: MenuDash Restaurant (details, opening hours, holidays, "open now"), MenuDash Specials (today's specials) and MenuDash Gift Cards (gift card orders).
 * Version:           2.5.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Plugin URI:        https://insdash.ch/projects/menudash/
 * Update URI:        https://github.com/yingshiuan/menudash
 * Author:            insdash
 * Author URI:        https://insdash.ch
 * License:           GPL-2.0-or-later
 * Text Domain:       menudash
 */

defined( 'ABSPATH' ) || exit;

define( 'MENUDASH_VERSION', '2.5.0' );
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
require MENUDASH_DIR . 'includes/colors.php';
require MENUDASH_DIR . 'includes/fonts.php';
require MENUDASH_DIR . 'includes/origin.php';
require MENUDASH_DIR . 'includes/picks.php';
require MENUDASH_DIR . 'includes/updater.php';

if ( is_admin() ) {
	require MENUDASH_DIR . 'includes/xlsx.php';
	require MENUDASH_DIR . 'includes/admin.php';
	require MENUDASH_DIR . 'includes/qr.php';
}

// The dashboard in the owner's language (languages/menudash-<locale>.mo). Priority 1: the
// Recommended dishes block is registered on init at priority 5, with a translated title.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'menudash', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	},
	1
);

register_activation_hook( __FILE__, 'mdash_activate' );
add_shortcode( 'menudash', 'mdash_shortcode' );
add_action( 'wp_enqueue_scripts', 'mdash_enqueue' );

/*
 * Add-ons (MenuDash Restaurant, Specials, Gift Cards) load on menudash_loaded. It runs once
 * every plugin file has been read, so it doesn't matter in which order WordPress loads them.
 */
add_action( 'plugins_loaded', 'mdash_loaded', 5 );
function mdash_loaded() {
	do_action( 'menudash_loaded' );
}
