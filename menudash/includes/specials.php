<?php
/**
 * [menudash_specials] -- today's specials, e.g. on the home page.
 *
 * The specials are a second, short CSV with the same columns as the menu: a row with a name
 * but no No. and no Price is a heading ("Mocktail", "Vorspeisen"), every other named row is
 * a dish. They live apart from the menu, so uploading them never touches the menu. Like the
 * menu's, the newest five uploads are kept in uploads/menudash/csv/ (specials-*.csv) and any
 * of them can be put back; the menudash_specials_live option says which one is on the site
 * (kept on uninstall, like the files), so a reinstall brings the specials back. "Remove" takes them off the site and keeps the files.
 * A dish photo whose name matches a special is shown with it, the same way as on the menu.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_SPECIALS_OPTION = 'menudash_specials';

add_shortcode( 'menudash_specials', 'mdash_specials_shortcode' );

function mdash_get_specials() {
	$s = get_option( MDASH_SPECIALS_OPTION );
	return is_array( $s ) && ! empty( $s['sections'] ) ? $s : null;
}

/** Parse a specials CSV and, only if it holds at least one dish, make it the live one. */
function mdash_load_specials( $path, $original_name ) {
	$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $bytes ) {
		return array( 'ok' => false, 'error' => 'The file could not be read.', 'warnings' => array() );
	}
	$result = mdash_parse_csv( $bytes );
	if ( ! $result['ok'] ) {
		return $result;
	}
	$specials           = $result['menu'];
	$specials['source'] = array(
		'name' => $original_name,
		'file' => basename( $path ),
		'time' => time(),
	);
	update_option( MDASH_SPECIALS_OPTION, $specials, false );
	mdash_purge_caches();
	return $result;
}

/** Stored specials CSVs, newest first. */
function mdash_specials_files() {
	$files = glob( mdash_dir( 'csv' ) . '/specials-*.csv' );
	$files = array_map( 'basename', $files ? $files : array() );
	rsort( $files );
	return $files;
}

/** Which stored file is on the site ('' after "Remove"). */
function mdash_specials_live() {
	return (string) get_option( 'menudash_specials_live', '' );
}

function mdash_specials_set_live( $file ) {
	update_option( 'menudash_specials_live', (string) $file, false );
}

/**
 * Keep an uploaded specials CSV in csv/ under a dated name, drop all but the newest few, and
 * make it the live one. Returns the stored file's name, or false.
 */
function mdash_store_specials( $tmp_path, $original_name ) {
	$dir  = mdash_dir( 'csv' );
	$dest = "$dir/specials-" . gmdate( 'Y-m-d-His' ) . '-' . wp_generate_password( 12, false ) . '.csv';
	if ( ! @copy( $tmp_path, $dest ) ) { // phpcs:ignore
		return false;
	}
	// Remember what the owner called it, next to the menu files' names.
	$names                      = mdash_csv_names();
	$names[ basename( $dest ) ] = sanitize_text_field( $original_name );
	foreach ( array_slice( mdash_specials_files(), MDASH_KEEP_CSV ) as $old ) {
		@unlink( "$dir/$old" ); // phpcs:ignore
		unset( $names[ $old ] );
	}
	mdash_save_csv_names( $names );
	return basename( $dest );
}

/** Put an earlier specials file back on the site. */
function mdash_put_back_specials( $file ) {
	$file = basename( $file );
	if ( ! in_array( $file, mdash_specials_files(), true ) ) {
		return array( 'ok' => false, 'error' => 'That file no longer exists.', 'warnings' => array() );
	}
	$names  = mdash_csv_names();
	$result = mdash_load_specials( mdash_dir( 'csv' ) . "/$file", isset( $names[ $file ] ) ? $names[ $file ] : $file );
	if ( $result['ok'] ) {
		mdash_specials_set_live( $file );
	}
	return $result;
}

/** On activation, bring back the specials that were on the site before a reinstall. */
function mdash_restore_specials() {
	$live = mdash_specials_live();
	if ( ! mdash_get_specials() && '' !== $live && in_array( $live, mdash_specials_files(), true ) ) {
		mdash_put_back_specials( $live );
	}
}

/** Take the specials off the site; the files stay, to be put back later. */
function mdash_clear_specials() {
	mdash_specials_set_live( '' );
	delete_option( MDASH_SPECIALS_OPTION );
	mdash_purge_caches();
}

/**
 * Attributes:
 *   title  heading above the list; title="" leaves it out. Default "Heute empfohlen" / "Today’s
 *          specials" / "今日推薦", following the language the guest reads the menu in.
 *   lang   the view a first-time guest sees: all (default), de, en or zh, as on [menudash].
 *          A guest's own choice, made here or on the menu page, is remembered for both.
 *   switch "no" leaves out the All / DE / EN / 中文 buttons, e.g. above [menudash], whose
 *          own buttons then switch the specials too.
 *   jump   a link for a "To the menu" button under the specials, e.g. jump="#menu" when the
 *          menu follows on the same page. Without it there is no button.
 * With no specials uploaded (or after "Remove") it prints nothing at all.
 */
function mdash_specials_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'title'  => null,
			'lang'   => 'all',
			'switch' => 'yes',
			'jump'   => '',
		),
		$atts,
		'menudash_specials'
	);
	$specials = mdash_get_specials();
	if ( ! $specials ) {
		return '';
	}
	mdash_enqueue_assets();

	$view   = in_array( $atts['lang'], MDASH_VIEWS, true ) ? $atts['lang'] : 'all';
	$jump   = (string) $atts['jump'];
	$switch = ! in_array( strtolower( (string) $atts['switch'] ), array( 'no', '0', 'false', 'off' ), true );
	$title  = null === $atts['title'] ? mdash_ui( 'specials_title' ) : esc_html( $atts['title'] );
	$photos = mdash_photo_index();
	$match  = mdash_match_photos( $specials, $photos );

	ob_start();
	include MENUDASH_DIR . 'templates/specials.php';
	// No line breaks: the Shortcode block and text widgets run wpautop over the output,
	// which would turn each one into a <br>.
	$html = preg_replace( '/\s*\n\s*/', ' ', trim( ob_get_clean() ) );
	return '<!-- menudash_specials:start -->' . $html . '<!-- menudash_specials:end -->';
}
