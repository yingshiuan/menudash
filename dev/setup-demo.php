<?php
/**
 * Demo content for dev/serve.sh: a "Menu" page with [menudash], and the sample menu and
 * photos loaded the way the upload page would, unless a menu was uploaded before. Also the
 * sample specials, example opening hours and a "Today" page (?pagename=today) with
 * [menudash_closed], [menudash_specials] and [menudash_hours].
 */

require '/wordpress/wp-load.php';

// Show the front end as guests see it, without the admin bar.
update_user_meta( 1, 'show_admin_bar_front', 'false' );

// The menu sits in a wide group, so desktops get two dishes per row.
if ( ! get_page_by_path( 'menu' ) ) {
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

// Today's specials from the sample file, and example opening hours, unless set before.
if ( ! mdash_get_specials() && ! mdash_specials_files() ) {
	$stored = mdash_store_specials( '/menudash-sample/specials-sample.csv', 'specials-sample.csv' );
	if ( $stored ) {
		mdash_put_back_specials( $stored );
	}
}
if ( ! array_filter( mdash_hours() ) ) {
	$evening = array( array( '17:00', '22:00' ) );
	$weekend = array( array( '11:30', '14:00' ), array( '17:00', '22:00' ) );
	update_option( MDASH_HOURS_OPTION, array( 1 => array(), 2 => $evening, 3 => $evening, 4 => $evening, 5 => $evening, 6 => $weekend, 7 => $weekend ), false );
}

// A "Today" page with the other three shortcodes: the holiday notice (shown when one is
// entered under MenuDash -> Holidays), the specials with a button to the menu, the hours.
if ( ! get_page_by_path( 'today' ) ) {
	$block = function ( $code ) {
		return "<!-- wp:shortcode -->\n$code\n<!-- /wp:shortcode -->";
	};
	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Today',
			'post_name'    => 'today',
			'post_content' => implode(
				"\n\n",
				array(
					$block( '[menudash_closed]' ),
					"<!-- wp:group {\"align\":\"wide\",\"layout\":{\"type\":\"constrained\",\"contentSize\":\"1120px\"}} -->\n<div class=\"wp-block-group alignwide\">" . $block( '[menudash_specials jump="?pagename=menu"]' ) . '</div>' . "\n<!-- /wp:group -->",
					"<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Opening hours</h2>\n<!-- /wp:heading -->",
					$block( '[menudash_hours lang="en"]' ),
				)
			),
		)
	);
}
