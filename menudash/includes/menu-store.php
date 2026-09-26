<?php
/**
 * Where the menu lives.
 *
 * Every uploaded CSV is kept in uploads/menudash/csv/ (the newest five), and the parsed
 * menu is cached in the menudash_data option, which is not autoloaded. Deleting the
 * plugin removes the option but keeps the folder, and activating it again re-reads the
 * newest CSV, so an update or reinstall never loses the menu or the photos.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_OPTION   = 'menudash_data';
const MDASH_KEEP_CSV = 5;

/** uploads/menudash/<sub>, created on first use, with an index.php so it can't be listed. */
function mdash_dir( $sub = '' ) {
	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( $up['basedir'] ) . 'menudash' . ( $sub ? "/$sub" : '' );
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( "$dir/index.php" ) ) {
		@file_put_contents( "$dir/index.php", "<?php // Silence is golden.\n" ); // phpcs:ignore
	}
	// Only PHP reads the CSVs; on Apache the web can't fetch them at all.
	if ( 'csv' === $sub && ! file_exists( "$dir/.htaccess" ) ) {
		@file_put_contents( "$dir/.htaccess", "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\n" ); // phpcs:ignore
	}
	return $dir;
}

function mdash_url( $path = '' ) {
	$up = wp_upload_dir( null, false );
	return set_url_scheme( trailingslashit( $up['baseurl'] ) . 'menudash/' . ltrim( $path, '/' ) );
}

function mdash_get_menu() {
	$menu = get_option( MDASH_OPTION );
	return is_array( $menu ) && ! empty( $menu['sections'] ) ? $menu : null;
}

/**
 * Parse a CSV file and, only if it is a usable menu, make it the live one.
 * Returns the parser result plus the source details.
 */
function mdash_load_csv( $path, $original_name ) {
	$bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $bytes ) {
		return array( 'ok' => false, 'error' => 'The file could not be read.', 'warnings' => array() );
	}
	$result = mdash_parse_csv( $bytes );
	if ( ! $result['ok'] ) {
		return $result;
	}
	$menu             = $result['menu'];
	$menu['warnings'] = $result['warnings'];
	$menu['source']   = array(
		'name' => $original_name,
		'file' => basename( $path ),
		'time' => time(),
	);
	update_option( MDASH_OPTION, $menu, false );
	mdash_purge_caches();
	return $result;
}

/** Keep an uploaded CSV in csv/ under a dated name, then drop all but the newest few. */
function mdash_store_csv( $tmp_path, $original_name ) {
	$dir  = mdash_dir( 'csv' );
	// The random part keeps the file from being guessed by its upload time: the CSV may
	// hold columns the menu never shows (costs, notes). csv/ is also closed to the web.
	$base = 'menu-' . gmdate( 'Y-m-d-His' ) . '-' . wp_generate_password( 12, false );
	$dest = "$dir/$base.csv";
	for ( $i = 2; file_exists( $dest ); $i++ ) {
		$dest = "$dir/$base-$i.csv";
	}
	if ( ! @copy( $tmp_path, $dest ) ) { // phpcs:ignore
		return false;
	}
	// Remember what the owner called it; the stored name is only a date.
	$names                    = mdash_csv_names();
	$names[ basename( $dest ) ] = sanitize_text_field( $original_name );
	$files                    = mdash_csv_files();
	foreach ( array_slice( $files, MDASH_KEEP_CSV ) as $old ) {
		@unlink( "$dir/$old" ); // phpcs:ignore
		unset( $names[ $old ] );
	}
	mdash_save_csv_names( $names );
	return $dest;
}

/** Stored CSV files, newest first. */
function mdash_csv_files() {
	$files = glob( mdash_dir( 'csv' ) . '/menu-*.csv' );
	$files = array_map( 'basename', $files ? $files : array() );
	rsort( $files );
	return $files;
}

/**
 * The names the owner gave the stored CSVs (stored name => original name). They live in an
 * option, not in a file beside the CSVs: a file with a fixed name in csv/ could be fetched
 * on servers that ignore .htaccess (nginx) and would list the random CSV names. 1.1 kept
 * them in csv/names.json; that file is read once, moved here and deleted.
 * Uninstall keeps this option, so a reinstall still shows the original names.
 */
function mdash_csv_names() {
	$names = get_option( 'menudash_csv_names' );
	if ( ! is_array( $names ) ) {
		$f     = mdash_dir( 'csv' ) . '/names.json';
		$names = file_exists( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : array(); // phpcs:ignore
		$names = is_array( $names ) ? $names : array();
		update_option( 'menudash_csv_names', $names, false );
		if ( file_exists( $f ) ) {
			@unlink( $f ); // phpcs:ignore
		}
	}
	return $names;
}

function mdash_save_csv_names( $names ) {
	update_option( 'menudash_csv_names', $names, false );
}

/** Put an older CSV back live. */
function mdash_restore_csv( $file ) {
	$file = basename( $file );
	if ( ! in_array( $file, mdash_csv_files(), true ) ) {
		return array( 'ok' => false, 'error' => 'That file no longer exists.', 'warnings' => array() );
	}
	$names = mdash_csv_names();
	return mdash_load_csv( mdash_dir( 'csv' ) . "/$file", isset( $names[ $file ] ) ? $names[ $file ] : $file );
}

/** On activation, a reinstall picks up the menu that was live before. */
function mdash_activate() {
	mdash_restore_specials();
	if ( mdash_get_menu() ) {
		return;
	}
	$files = mdash_csv_files();
	if ( $files ) {
		mdash_restore_csv( $files[0] );
	}
}

/**
 * Tell a page-cache plugin that the menu page changed. Does nothing when none is installed.
 * Photos don't need this: every upload gets a new file name.
 */
function mdash_purge_caches() {
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
	}
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache();
	}
	do_action( 'litespeed_purge_all' );
	do_action( 'menudash_changed' );
}
