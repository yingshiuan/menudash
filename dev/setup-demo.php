<?php
/**
 * Demo content for dev/serve.sh: a "Menu" page with [menudash], and the sample menu and
 * photos loaded the way the upload page would, unless a menu was uploaded before.
 */

require '/wordpress/wp-load.php';

// Show the front end as guests see it, without the admin bar.
update_user_meta( 1, 'show_admin_bar_front', 'false' );

// The menu sits in a wide group, so desktops get two dishes per row. Only when no page has
// the menu yet: a theme may have made one already (MenuDash Theme: /speisekarte/ in German).
$has_menu = false;
foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1 ) ) as $page ) {
	$has_menu = $has_menu || has_shortcode( $page->post_content, 'menudash' );
}
if ( ! $has_menu && ! get_page_by_path( 'menu' ) ) {
	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Menu',
			'post_name'    => 'menu',
			'post_content' => "<!-- wp:group {\"align\":\"wide\",\"layout\":{\"type\":\"constrained\",\"contentSize\":\"1120px\"}} -->\n<div class=\"wp-block-group alignwide\"><!-- wp:shortcode -->\n[menudash]\n<!-- /wp:shortcode --></div>\n<!-- /wp:group -->",
		)
	);
}

if ( ! mdash_get_menu() ) {
	$csv    = '/menudash-sample/menu-sample.csv';
	$stored = mdash_store_csv( $csv, 'menu-sample.csv' );
	mdash_load_csv( $stored ? $stored : $csv, 'menu-sample.csv' );
}

if ( ! mdash_photo_index() ) {
	foreach ( glob( '/menudash-sample/photos/*' ) as $photo ) {
		if ( preg_match( '/\.(png|jpe?g|webp)$/i', $photo ) ) {
			mdash_photo_add( $photo, basename( $photo ) );
		}
	}
}

// The demo shows MenuDash's own default colours (MenuDash → Design → "My own colours"), the
// same whatever theme the demo runs; switch to "The theme's colours" there to compare.
if ( false === get_option( 'menudash_colors' ) ) {
	update_option( 'menudash_colors', array( 'mode' => 'own', 'bg' => '', 'accent' => '' ), false );
}

// Likewise MenuDash's own fonts (DM Sans and Darker Grotesque), not the demo theme's.
if ( false === get_option( 'menudash_fonts' ) ) {
	update_option( 'menudash_fonts', 'own', false );
}
