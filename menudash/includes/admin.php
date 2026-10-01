<?php
/**
 * Dashboard -> MenuDash: upload the CSV, upload photos, see what the menu page will show.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_CAP = 'edit_pages';

add_action( 'admin_menu', 'mdash_admin_menu' );
// Early, so an add-on's own admin styles can list menudash-admin as a dependency.
add_action( 'admin_enqueue_scripts', 'mdash_admin_assets', 5 );
add_action( 'admin_post_menudash_csv', 'mdash_handle_csv' );
add_action( 'admin_post_menudash_restore', 'mdash_handle_restore' );
add_action( 'admin_post_menudash_download', 'mdash_handle_download' );
add_action( 'admin_post_menudash_icons', 'mdash_handle_icons' );
add_action( 'admin_post_menudash_colors', 'mdash_handle_colors' );
add_action( 'admin_post_menudash_fonts', 'mdash_handle_fonts' );
add_action( 'wp_ajax_menudash_photo', 'mdash_ajax_photo' );
add_action( 'wp_ajax_menudash_photo_delete', 'mdash_ajax_photo_delete' );
add_filter( 'plugin_action_links_' . plugin_basename( MENUDASH_FILE ), 'mdash_action_links' );

function mdash_admin_menu() {
	// Sidebar and page carry the plugin's name. Not plain "Menu": WordPress's own "Menüs"
	// (navigation) sits nearby.
	add_menu_page( MENUDASH_NAME, MENUDASH_NAME, MDASH_CAP, 'menudash', 'mdash_admin_page', mdash_menu_icon(), 26 );
	// The same page in four parts, each in the sidebar and as a tab at the top.
	foreach ( mdash_admin_tabs() as $slug => $label ) {
		add_submenu_page( 'menudash', MENUDASH_NAME . ' – ' . $label, $label, MDASH_CAP, $slug, 'mdash_admin_page' );
	}
}

/**
 * MenuDash's icon in one colour for the left bar. WordPress paints its fill in the admin
 * colour scheme's icon colour (grey, white when open), as it does with its own icons.
 */
function mdash_menu_icon() {
	$svg = file_get_contents( MENUDASH_DIR . 'assets/brand/menu-icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $svg ? 'data:image/svg+xml;base64,' . base64_encode( $svg ) : 'dashicons-food'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
}

/** Page slug => tab name. The first is the page the sidebar's MenuDash opens. */
function mdash_admin_tabs() {
	// Add-ons add their tabs here (MenuDash Restaurant, Gift Cards); Colours, fonts & icons stays last.
	$tabs = (array) apply_filters( 'menudash_admin_tabs', array( 'menudash' => _x( 'Menu', 'restaurant menu (tab)', 'menudash' ) ) );
	unset( $tabs['menudash-design'] );
	// Not "Design": that is WordPress's own Appearance menu in German (外觀 in Chinese).
	$tabs['menudash-design'] = __( 'Colours, fonts & icons', 'menudash' );
	return $tabs;
}

function mdash_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=menudash' ) ) . '">' . esc_html__( 'Upload menu', 'menudash' ) . '</a>' );
	return $links;
}

/** On the MenuDash page and its tabs only (their hooks end in the page slug). */
function mdash_admin_assets( $hook ) {
	if ( ! preg_match( '/_page_menudash(-[a-z]+)?$/', (string) $hook ) ) {
		return;
	}
	wp_enqueue_style( 'menudash-admin', MENUDASH_URL . 'assets/admin.css', array(), MENUDASH_VERSION );
	wp_enqueue_script( 'menudash-admin', MENUDASH_URL . 'assets/admin.js', array(), MENUDASH_VERSION, true );
	wp_localize_script(
		'menudash-admin',
		'menudashAdmin',
		array(
			'ajax'      => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'menudash_photo' ),
			'maxUpload' => (int) wp_max_upload_size(),
		)
	);
	// The words assets/admin.js writes, in the owner's language.
	$i18n = array(
		/* translators: 1: number of the photo being uploaded, 2: number of photos, 3: file name. */
		'uploading'   => __( 'Uploading %1$s of %2$s: %3$s', 'menudash' ),
		'noMatch'     => __( 'uploaded, but matches no dish', 'menudash' ),
		/* translators: %s: number of photos uploaded. */
		'done'        => __( 'Done: %s uploaded', 'menudash' ),
		/* translators: %s: number of photos. Added after "Done: 5 uploaded". */
		'notMatched'  => __( ', %s not matched to a dish', 'menudash' ),
		/* translators: %s: number of photos. Added after "Done: 5 uploaded". */
		'failed'      => __( ', %s failed', 'menudash' ),
		'showCheck'   => __( 'Show the updated check', 'menudash' ),
		'shrinkFail'  => __( 'could not shrink', 'menudash' ),
		'notImage'    => __( 'not an image', 'menudash' ),
		'stillLarge'  => __( 'still larger than the server accepts after shrinking', 'menudash' ),
		/* translators: %s: HTTP status code, e.g. 500. */
		'serverSaid'  => __( 'the server answered %s', 'menudash' ),
		'uploadFail'  => __( 'failed', 'menudash' ),
		'confirmDel'  => __( 'Delete this photo?', 'menudash' ),
		'deleteFail'  => __( 'Could not delete.', 'menudash' ),
	);
	wp_add_inline_script( 'menudash-admin', 'var menudashAdminI18n = ' . wp_json_encode( $i18n, JSON_HEX_TAG | JSON_HEX_AMP ) . ';', 'before' );
}

function mdash_can() {
	return current_user_can( MDASH_CAP ) && current_user_can( 'upload_files' );
}

/** The report of the last CSV upload is shown once, on the page the upload returns to. */
function mdash_report_key() {
	return 'menudash_report_' . get_current_user_id();
}

/** The tab a handler returns to; set at the start of the handlers of the other tabs. */
function mdash_back_to( $set = null ) {
	static $page = 'menudash';
	if ( null !== $set ) {
		$page = $set;
	}
	return $page;
}

function mdash_back( $result ) {
	$page = mdash_back_to();
	// Keep only what the notice shows, not the whole parsed menu.
	$report = array(
		'ok'       => $result['ok'],
		'error'    => isset( $result['error'] ) ? $result['error'] : '',
		'warnings' => isset( $result['warnings'] ) ? $result['warnings'] : array(),
		'name'     => isset( $result['name'] ) ? $result['name'] : '',
		'restored' => ! empty( $result['restored'] ),
		'message'  => isset( $result['message'] ) ? $result['message'] : null, // Set for icon and add-on uploads.
		'kind'     => isset( $result['kind'] ) ? $result['kind'] : '',
		'also'     => isset( $result['also'] ) ? array_map( 'strval', (array) $result['also'] ) : array(), // Other sheets of an Excel upload.
		'sections' => empty( $result['menu'] ) ? 0 : count( $result['menu']['sections'] ),
		'dishes'   => empty( $result['menu'] ) ? 0 : $result['menu']['dishes'],
	);
	set_transient( mdash_report_key(), $report, 300 );
	wp_safe_redirect( admin_url( 'admin.php?page=' . ( isset( mdash_admin_tabs()[ $page ] ) ? $page : 'menudash' ) ) );
	exit;
}

function mdash_handle_csv() {
	check_admin_referer( 'menudash_csv' );
	if ( ! mdash_can() ) {
		wp_die( esc_html__( 'You are not allowed to change the menu.', 'menudash' ), 403 );
	}
	$f = isset( $_FILES['csv'] ) ? $_FILES['csv'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $f || UPLOAD_ERR_OK !== $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
		$code = $f ? (int) $f['error'] : UPLOAD_ERR_NO_FILE;
		$error = UPLOAD_ERR_NO_FILE === $code
			? __( 'Choose the CSV file first.', 'menudash' )
			/* translators: %d: PHP upload error code. */
			: sprintf( __( 'The upload failed (code %d).', 'menudash' ), $code );
		mdash_back( array( 'ok' => false, 'error' => $error ) );
	}
	$name = sanitize_text_field( wp_unslash( $f['name'] ) );
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( 'numbers' === $ext ) {
		mdash_back( array( 'ok' => false, 'error' => __( 'A Numbers file can\'t be read by a website. In Numbers, choose File → Export To → Excel (keeps the sheets menu, specials, lunch) or CSV, and upload that.', 'menudash' ) ) );
	}
	if ( in_array( $ext, array( 'xls', 'xlsm', 'xlsb', 'ods' ), true ) ) {
		mdash_back( array( 'ok' => false, 'error' => __( 'Save the spreadsheet as an Excel workbook (.xlsx) or as CSV, and upload that.', 'menudash' ) ) );
	}
	if ( 'xlsx' === $ext ) {
		mdash_handle_xlsx( $f['tmp_name'], $name, (int) $f['size'] );
	}
	if ( $f['size'] > 2 * MB_IN_BYTES ) {
		mdash_back( array( 'ok' => false, 'error' => __( 'That file is over 2 MB; a menu CSV is about 20 KB. Is it the right file?', 'menudash' ) ) );
	}
	mdash_back( mdash_apply_menu_csv( $f['tmp_name'], $name ) + array( 'name' => $name ) );
}

/** Parse a menu CSV, and when it is fine make it the live menu and keep it for "Put back". */
function mdash_apply_menu_csv( $path, $name ) {
	$result = mdash_load_csv( $path, $name );
	if ( $result['ok'] ) {
		mdash_store_csv( $path, $name );
		$menu                   = mdash_get_menu();
		$menu['source']['file'] = mdash_csv_files()[0];
		update_option( MDASH_OPTION, $menu, false );
	}
	return $result;
}

/**
 * An Excel workbook: the sheet named menu becomes the menu; sheets named specials and lunch
 * go to MenuDash Specials (filter menudash_xlsx_sheets); other sheets are listed as not used.
 * Each sheet is turned into CSV text and read by the same parser as an uploaded CSV, and that
 * CSV (never the workbook) is what is kept for "Put back". See includes/xlsx.php.
 */
function mdash_handle_xlsx( $tmp, $name, $size ) {
	if ( $size > 10 * MB_IN_BYTES ) {
		mdash_back( array( 'ok' => false, 'error' => __( 'That file is over 10 MB; a menu workbook is well under 1 MB. Is it the right file? (Pictures inside the workbook make it big; they are not needed.)', 'menudash' ) ) );
	}
	$x = mdash_xlsx_read( $tmp );
	if ( ! $x['ok'] ) {
		mdash_back( array( 'ok' => false, 'error' => $x['error'] ) );
	}
	$found  = array(); // kind => array( 'sheet' => …, 'rows' => … ).
	$unused = array();
	foreach ( $x['sheets'] as $sheet => $rows ) {
		if ( ! $rows || preg_match( '/^export summary$/i', $sheet ) ) {
			continue; // Empty, or the summary page Numbers adds to every export.
		}
		$kind = mdash_sheet_kind( $sheet );
		if ( '' !== $kind && ! isset( $found[ $kind ] ) ) {
			$found[ $kind ] = array( 'sheet' => $sheet, 'rows' => $rows );
		} else {
			$unused[] = $sheet;
		}
	}
	// A workbook with a single sheet, whatever its name, is the menu.
	if ( ! $found && 1 === count( $unused ) ) {
		$found['menu'] = array( 'sheet' => $unused[0], 'rows' => $x['sheets'][ $unused[0] ] );
		$unused        = array();
	}
	if ( ! $found ) {
		/* translators: %s: the names of the workbook's sheets, comma-separated. The sheet names menu, specials and lunch stay in English. */
		mdash_back( array( 'ok' => false, 'error' => sprintf( __( 'No sheet called menu, specials or lunch in this workbook (found: %s). Rename the sheets in Numbers or Excel, then export again.', 'menudash' ), implode( ', ', array_keys( $x['sheets'] ) ) ) ) );
	}
	// Each sheet as a CSV file of its own, for the parser and for "Put back".
	$files = array();
	foreach ( $found as $kind => $s ) {
		$path = wp_tempnam( 'menudash-' . $kind );
		file_put_contents( $path, mdash_rows_to_csv( mdash_xlsx_from_header( $s['rows'] ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$files[ $kind ] = array( 'path' => $path, 'sheet' => $s['sheet'], 'label' => $name . ' › ' . $s['sheet'] );
	}
	$also = array();
	$rest = array_diff_key( $files, array( 'menu' => true ) );
	if ( $rest ) {
		/**
		 * The workbook's other sheets, for add-ons: kind => array( path, sheet, label ). Return
		 * kind => one line for the report ("Specials (sheet specials): 8 dishes are on the site").
		 */
		$done = (array) apply_filters( 'menudash_xlsx_sheets', array(), $rest );
		foreach ( $rest as $kind => $s ) {
			/* translators: %s: sheet name. */
			$also[] = isset( $done[ $kind ] ) ? (string) $done[ $kind ] : sprintf( __( 'Sheet "%s" not used: it needs the MenuDash Specials add-on.', 'menudash' ), $s['sheet'] );
		}
	}
	foreach ( $unused as $sheet ) {
		/* translators: %s: sheet name. The sheet names menu, specials and lunch stay in English. */
		$also[] = sprintf( __( 'Sheet "%s" not used (MenuDash reads the sheets menu, specials and lunch).', 'menudash' ), $sheet );
	}
	if ( isset( $files['menu'] ) ) {
		$result = mdash_apply_menu_csv( $files['menu']['path'], $files['menu']['label'] ) + array( 'name' => $files['menu']['label'] );
	} else {
		$result = array( 'ok' => true, 'kind' => __( 'Excel file', 'menudash' ), 'message' => __( 'No sheet called menu, so the menu was not changed.', 'menudash' ) );
	}
	foreach ( $files as $s ) {
		@unlink( $s['path'] ); // phpcs:ignore
	}
	mdash_back( $result + array( 'also' => $also ) );
}

function mdash_handle_colors() {
	mdash_back_to( 'menudash-design' );
	check_admin_referer( 'menudash_colors' );
	if ( ! mdash_can() ) {
		wp_die( esc_html__( 'You are not allowed to change the menu.', 'menudash' ), 403 );
	}
	if ( isset( $_POST['reset'] ) ) {
		delete_option( MDASH_COLORS_OPTION );
		mdash_purge_caches();
		mdash_back( array( 'ok' => true, 'kind' => __( 'Colours', 'menudash' ), 'message' => __( 'Back to the default colours.', 'menudash' ) ) );
	}
	$in    = isset( $_POST['colors'] ) && is_array( $_POST['colors'] ) ? wp_unslash( $_POST['colors'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- checked by mdash_color_hex()
	$mode  = isset( $_POST['colors_mode'] ) && 'theme' === $_POST['colors_mode'] && mdash_theme_colors() ? 'theme' : 'own';
	$saved = array( 'mode' => $mode );
	foreach ( mdash_color_keys() as $k => $c ) {
		$v           = isset( $in[ $k ] ) ? mdash_color_hex( $in[ $k ] ) : '';
		$saved[ $k ] = $v === $c[1] ? '' : $v; // The default is stored as "not chosen".
	}
	// The own colours are kept while the theme's are in use, so switching back brings them.
	update_option( MDASH_COLORS_OPTION, $saved, false );
	mdash_purge_caches();
	$problems = mdash_color_problems( mdash_colors() );
	mdash_back( array( 'ok' => ! $problems, 'kind' => __( 'Colours', 'menudash' ), 'message' => 'theme' === $mode ? __( 'Saved: the menu uses the theme\'s colours.', 'menudash' ) : __( 'Saved: the menu uses your own colours.', 'menudash' ), 'error' => implode( ' ', $problems ) ) );
}

function mdash_handle_fonts() {
	mdash_back_to( 'menudash-design' );
	check_admin_referer( 'menudash_fonts' );
	if ( ! mdash_can() ) {
		wp_die( esc_html__( 'You are not allowed to change the menu.', 'menudash' ), 403 );
	}
	$mode = isset( $_POST['fonts_mode'] ) && 'own' === $_POST['fonts_mode'] ? 'own' : 'theme';
	update_option( MDASH_FONTS_OPTION, $mode, false );
	mdash_purge_caches();
	mdash_back( array( 'ok' => true, 'kind' => __( 'Fonts', 'menudash' ), 'message' => 'theme' === $mode ? __( 'Saved: the menu uses the theme\'s fonts.', 'menudash' ) : __( 'Saved: the menu uses MenuDash\'s fonts.', 'menudash' ) ) );
}

function mdash_handle_icons() {
	mdash_back_to( 'menudash-design' );
	check_admin_referer( 'menudash_icons' );
	if ( ! mdash_can() ) {
		wp_die( esc_html__( 'You are not allowed to change the menu.', 'menudash' ), 403 );
	}
	$labels = mdash_icon_labels();
	$done   = array();
	$errors = array();
	if ( isset( $_POST['reset'] ) ) {
		$key = sanitize_key( wp_unslash( $_POST['reset'] ) );
		if ( isset( $labels[ $key ] ) ) {
			mdash_icon_reset( $key );
			/* translators: %s: icon name, e.g. Spicy. No full stop: several of these are joined with ". ". */
			$done[] = sprintf( __( '%s is back to the default icon', 'menudash' ), $labels[ $key ] );
		}
	}
	foreach ( $labels as $key => $label ) {
		$f = isset( $_FILES[ "icon_$key" ] ) ? $_FILES[ "icon_$key" ] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $f || UPLOAD_ERR_NO_FILE === $f['error'] ) {
			continue;
		}
		if ( UPLOAD_ERR_OK !== $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
			/* translators: 1: icon name, e.g. Spicy, 2: PHP upload error code. */
			$errors[] = sprintf( __( '%1$s: the upload failed (code %2$d).', 'menudash' ), $label, $f['error'] );
			continue;
		}
		if ( $f['size'] > 2 * MB_IN_BYTES ) {
			/* translators: %s: icon name, e.g. Spicy. */
			$errors[] = sprintf( __( '%s: the file is over 2 MB; an icon is usually a few KB.', 'menudash' ), $label );
			continue;
		}
		$r = mdash_icon_set( $key, $f['tmp_name'], wp_unslash( $f['name'] ) );
		if ( $r['ok'] ) {
			/* translators: %s: icon name, e.g. Spicy. No full stop: several of these are joined with ". ". */
			$done[] = sprintf( __( '%s icon replaced', 'menudash' ), $label );
		} else {
			$errors[] = "$label: " . $r['error'];
		}
	}
	if ( ! $done && ! $errors ) {
		$errors[] = __( 'Choose an icon file first.', 'menudash' );
	}
	mdash_back(
		array(
			'ok'      => ! $errors,
			'message' => $done ? implode( '. ', $done ) . '.' : '',
			'error'   => implode( ' ', $errors ),
		)
	);
}

function mdash_handle_restore() {
	check_admin_referer( 'menudash_restore' );
	if ( ! mdash_can() ) {
		wp_die( esc_html__( 'You are not allowed to change the menu.', 'menudash' ), 403 );
	}
	$file   = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
	$result = mdash_restore_csv( $file );
	mdash_back( $result + array( 'name' => $file, 'restored' => true ) );
}

/**
 * Send a stored menu CSV (the live one by default) as a download, e.g. to print the menu
 * with another program. It is the file as uploaded, or the menu sheet of an Excel upload,
 * with a UTF-8 BOM in front so Excel shows the Chinese; the CSV parser skips the BOM.
 */
function mdash_handle_download() {
	check_admin_referer( 'menudash_download' );
	if ( ! mdash_can() ) {
		wp_die( esc_html__( 'You are not allowed to change the menu.', 'menudash' ), 403 );
	}
	$files = mdash_csv_files();
	$menu  = mdash_get_menu();
	$file  = isset( $_GET['file'] ) ? sanitize_file_name( wp_unslash( $_GET['file'] ) ) : ( $menu ? $menu['source']['file'] : '' );
	if ( ! in_array( $file, $files, true ) ) {
		wp_die( esc_html__( 'That file no longer exists.', 'menudash' ), 404 );
	}
	$bytes = file_get_contents( mdash_dir( 'csv' ) . "/$file" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $bytes ) {
		wp_die( esc_html__( 'The file could not be read.', 'menudash' ), 500 );
	}
	if ( substr( $bytes, 0, 3 ) !== "\xEF\xBB\xBF" ) {
		$bytes = "\xEF\xBB\xBF" . $bytes;
	}
	// Named like the upload (dinner.xlsx → dinner.csv), not like the stored date name.
	// A file without a name of its own (one put back by its stored name) is named by its date
	// only: the random part of the stored name is no use to anyone.
	$names = mdash_csv_names();
	if ( isset( $names[ $file ] ) ) {
		$name = $names[ $file ];
	} elseif ( $menu && $menu['source']['file'] === $file && ! empty( $menu['source']['name'] ) ) {
		$name = $menu['source']['name'];
	} else {
		$name = $file;
	}
	$name = preg_replace( '/^(menu-\d{4}-\d\d-\d\d)-\d{6}-[A-Za-z0-9]{12}(-\d+)?\.csv$/', '$1.csv', $name );
	$base = sanitize_file_name( pathinfo( $name, PATHINFO_FILENAME ) );
	$base = '' === $base ? 'menu' : $base;
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', remove_accents( $base ) ) . '.csv"; filename*=UTF-8\'\'' . rawurlencode( $base . '.csv' ) );
	header( 'Content-Length: ' . strlen( $bytes ) );
	echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a file download, not HTML.
	exit;
}

/** Link that downloads a stored menu CSV; without $file, the live one. */
function mdash_download_url( $file = '' ) {
	$args = array( 'action' => 'menudash_download' );
	if ( '' !== $file ) {
		$args['file'] = $file;
	}
	return wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'menudash_download' );
}

function mdash_ajax_photo() {
	check_ajax_referer( 'menudash_photo' );
	if ( ! mdash_can() ) {
		wp_send_json_error( array( 'error' => __( 'You are not allowed to upload photos.', 'menudash' ) ), 403 );
	}
	$f = isset( $_FILES['photo'] ) ? $_FILES['photo'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $f || UPLOAD_ERR_OK !== $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
		$code = $f ? (int) $f['error'] : UPLOAD_ERR_NO_FILE;
		$why  = in_array( $code, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
			? __( 'the file is larger than this server accepts', 'menudash' )
			/* translators: %d: PHP upload error code. */
			: sprintf( __( 'upload error %d', 'menudash' ), $code );
		wp_send_json_error( array( 'error' => $why ) );
	}
	// The browser may have shrunk the photo and renamed it; the owner's name comes separately.
	$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : $f['name'];
	$result = mdash_photo_add( $f['tmp_name'], $name );
	if ( ! $result['ok'] ) {
		wp_send_json_error( $result );
	}
	// Tell the uploader which dish it landed on.
	$menu   = mdash_get_menu();
	$match  = mdash_photo_matches( $menu );
	$dish   = array_search( $result['id'], $match['dish'], true );
	$result['dish'] = $dish ? mdash_dish_label( $menu, $dish ) : '';
	wp_send_json_success( $result );
}

function mdash_ajax_photo_delete() {
	check_ajax_referer( 'menudash_photo' );
	if ( ! mdash_can() ) {
		wp_send_json_error( array( 'error' => __( 'Not allowed.', 'menudash' ) ), 403 );
	}
	$id = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
	mdash_photo_delete( $id ) ? wp_send_json_success() : wp_send_json_error( array( 'error' => __( 'No such photo.', 'menudash' ) ) );
}

function mdash_dish_label( $menu, $key ) {
	foreach ( $menu['sections'] as $s ) {
		foreach ( $s['dishes'] as $d ) {
			if ( $d['key'] === $key ) {
				return mdash_label( $d['name'], $d['no'] );
			}
		}
	}
	return $key;
}

/** Pages that show the menu, so the owner can open one after an upload. */
function mdash_menu_pages() {
	$pages = get_posts(
		array(
			// Pages only, oldest first: a post an Author writes with [menudash] never becomes
			// the menu page (the QR code's default target).
			'post_type'      => 'page',
			'post_status'    => array( 'publish', 'draft', 'private' ),
			's'              => '[menudash',
			'posts_per_page' => 5,
			'orderby'        => 'menu_order ID',
			'order'          => 'ASC',
		)
	);
	return array_values( array_filter( $pages, function ( $p ) { return has_shortcode( $p->post_content, 'menudash' ); } ) );
}

function mdash_bytes( $n ) {
	return size_format( $n, $n < MB_IN_BYTES ? 0 : 1 );
}

/** The check table for the menu or the specials: number, photo, names, marks, price. */
function mdash_admin_dish_table( $list, $photos, $match ) {
	?>
	<table class="widefat striped mdash-table">
		<thead><tr><th><?php echo esc_html_x( 'Nr.', 'dish number (column heading)', 'menudash' ); ?></th><th><?php esc_html_e( 'Photo', 'menudash' ); ?></th><th><?php esc_html_e( 'Name', 'menudash' ); ?></th><th>中文</th><th><?php esc_html_e( 'Marks', 'menudash' ); ?></th><th class="num"><?php esc_html_e( 'Price', 'menudash' ); ?></th></tr></thead>
		<?php foreach ( $list['sections'] as $s ) : ?>
			<tbody>
				<tr class="mdash-sec"><th colspan="6"><?php echo esc_html( implode( ' · ', array_unique( array_filter( $s['name'] ) ) ) ); ?></th></tr>
				<?php foreach ( $s['dishes'] as $d ) : ?>
					<?php
					$pid   = isset( $match['dish'][ $d['key'] ] ) ? $match['dish'][ $d['key'] ] : null;
					$main  = '' !== $d['name']['en'] ? $d['name']['en'] : $d['name']['de'];
					$other = '' !== $d['name']['en'] && $d['name']['de'] !== $d['name']['en'] ? $d['name']['de'] : '';
					?>
					<tr>
						<td><?php echo esc_html( $d['no'] ); ?></td>
						<td class="mdash-thumb">
							<?php if ( $pid ) : ?>
								<img src="<?php echo esc_url( mdash_photo_url( $photos[ $pid ], 400 ) ); ?>" alt="" width="44" height="44" title="<?php echo esc_attr( $photos[ $pid ]['name'] ); ?>">
							<?php else : ?>
								<span class="mdash-missing"><?php esc_html_e( 'no photo', 'menudash' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $main ); ?><?php if ( '' !== $other ) : ?><br><small><?php echo esc_html( $other ); ?></small><?php endif; ?></td>
						<td lang="zh-Hant"><?php echo esc_html( $d['name']['zh'] ); ?></td>
						<td class="mdash-marks"><?php foreach ( $d['flags'] as $f ) : ?><span title="<?php echo esc_attr( mdash_ui_plain( $f ) ); ?>"><?php echo mdash_icon( $f ); // phpcs:ignore ?></span><?php endforeach; ?></td>
						<td class="num"><?php echo esc_html( $d['price'] ); ?><?php if ( '' !== $d['measure'] ) : ?> <small><?php echo esc_html( $d['measure'] ); ?></small><?php endif; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		<?php endforeach; ?>
	</table>
	<?php
}

function mdash_admin_page() {
	if ( ! mdash_can() ) {
		return;
	}
	$menu    = mdash_get_menu();
	$photos  = mdash_photo_index();
	$match   = mdash_photo_matches( $menu, $photos );
	$report  = get_transient( mdash_report_key() );
	delete_transient( mdash_report_key() );
	$pages   = mdash_menu_pages();
	$files   = mdash_csv_files();
	$names   = mdash_csv_names();
	$with    = count( $match['dish'] );
	$page    = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'menudash'; // phpcs:ignore WordPress.Security.NonceVerification
	$page    = isset( mdash_admin_tabs()[ $page ] ) ? $page : 'menudash';
	?>
	<div class="wrap mdash-admin">
		<h1><?php echo esc_html( MENUDASH_NAME ); ?></h1>
		<nav class="nav-tab-wrapper mdash-tabs" aria-label="<?php esc_attr_e( 'MenuDash sections', 'menudash' ); ?>">
			<?php foreach ( mdash_admin_tabs() as $slug => $label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="nav-tab<?php echo $slug === $page ? ' nav-tab-active' : ''; ?>"<?php echo $slug === $page ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php // WordPress moves notices under this line, so they stay below the tabs. ?>
		<hr class="wp-header-end">
		<?php if ( $report ) : ?>
			<?php if ( isset( $report['message'] ) ) : ?>
				<div class="notice <?php echo $report['ok'] ? 'notice-success' : ( $report['message'] ? 'notice-warning' : 'notice-error' ); ?>">
					<p><strong><?php echo esc_html( $report['kind'] ? $report['kind'] : __( 'Diet icons', 'menudash' ) ); ?>:</strong> <?php echo esc_html( trim( $report['message'] . ' ' . $report['error'] ) ); ?></p>
					<?php if ( ! empty( $report['also'] ) ) : ?>
						<ul class="mdash-also"><?php foreach ( $report['also'] as $w ) : ?><li><?php echo esc_html( $w ); ?></li><?php endforeach; ?></ul>
					<?php endif; ?>
				</div>
			<?php else : ?>
			<div class="notice <?php echo $report['ok'] ? ( empty( $report['warnings'] ) ? 'notice-success' : 'notice-warning' ) : 'notice-error'; ?>">
				<?php if ( $report['ok'] ) : ?>
					<?php
					$done_text = ! empty( $report['restored'] )
						/* translators: %s: file name. */
						? __( 'Restored %s.', 'menudash' )
						/* translators: %s: file name. */
						: __( 'Uploaded %s.', 'menudash' );
					?>
					<p><strong><?php echo esc_html( sprintf( $done_text, $report['name'] ) ); ?></strong>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: "12 categories", 2: "85 dishes". */
							__( 'The menu now has %1$s and %2$s.', 'menudash' ),
							/* translators: %d: number of categories. */
							sprintf( _n( '%d category', '%d categories', (int) $report['sections'], 'menudash' ), (int) $report['sections'] ),
							/* translators: %d: number of dishes. */
							sprintf( _n( '%d dish', '%d dishes', (int) $report['dishes'], 'menudash' ), (int) $report['dishes'] )
						)
					);
					?>
					</p>
				<?php else : ?>
					<p><strong><?php esc_html_e( 'The menu was not changed.', 'menudash' ); ?></strong> <?php echo esc_html( $report['error'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $report['warnings'] ) ) : ?>
					<p><?php esc_html_e( 'Please check:', 'menudash' ); ?></p>
					<ul class="mdash-warn"><?php foreach ( $report['warnings'] as $w ) : ?><li><?php echo esc_html( $w ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<?php if ( ! empty( $report['also'] ) ) : ?>
					<p><?php esc_html_e( 'From the same file:', 'menudash' ); ?></p>
					<ul class="mdash-also"><?php foreach ( $report['also'] as $w ) : ?><li><?php echo esc_html( $w ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( 'menudash' === $page ) : ?>
		<p class="mdash-lead">
			<?php if ( $pages ) : ?>
				<?php esc_html_e( 'The menu is on:', 'menudash' ); ?>
				<?php foreach ( $pages as $i => $p ) : ?>
					<?php echo $i ? ' · ' : ''; ?><a href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $p ) ); ?></a><?php echo 'publish' !== $p->post_status ? ' (' . esc_html( $p->post_status ) . ')' : ''; ?>
				<?php endforeach; ?>
			<?php else : ?>
				<?php
				/* translators: %s: the shortcode [menudash]. */
				printf( esc_html__( 'To show the menu, add the shortcode %s to a page.', 'menudash' ), '<code>[menudash]</code>' );
				?>
			<?php endif; ?>
			<?php esc_html_e( 'Changes here show there straight away.', 'menudash' ); ?>
		</p>

		<div class="mdash-cards">
			<section class="mdash-card">
				<h2><?php esc_html_e( '1. Menu', 'menudash' ); ?></h2>
				<div class="mdash-card-howto">
				<p>
				<?php
				$howto = has_filter( 'menudash_xlsx_sheets' )
					? __( 'Your menu spreadsheet, exported as <strong>Excel</strong> (.xlsx; from Numbers: File → Export To → Excel) or as <strong>CSV</strong> (UTF-8, to keep the Chinese). It replaces the whole menu. In an Excel file, the sheet named <em>menu</em> is the menu, and sheets named <em>specials</em> and <em>lunch</em> update cards 3 and 4 in the same upload.', 'menudash' )
					: __( 'Your menu spreadsheet, exported as <strong>Excel</strong> (.xlsx; from Numbers: File → Export To → Excel) or as <strong>CSV</strong> (UTF-8, to keep the Chinese). It replaces the whole menu. In an Excel file, the sheet named <em>menu</em> is the menu.', 'menudash' );
				echo wp_kses( $howto, array( 'strong' => array(), 'em' => array() ) );
				?>
				</p>
				</div>
				<div class="mdash-card-upload">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="mdash-upload-row">
					<input type="hidden" name="action" value="menudash_csv">
					<?php wp_nonce_field( 'menudash_csv' ); ?>
					<label class="mdash-drop" data-drop>
						<input type="file" name="csv" class="mdash-file" accept=".csv,text/csv,text/plain,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
						<span class="button button-small"><?php esc_html_e( 'Choose file', 'menudash' ); ?></span>
						<span class="mdash-drop-types"><?php esc_html_e( 'CSV or Excel (.xlsx)', 'menudash' ); ?></span>
						<span class="mdash-drop-name" data-empty="<?php esc_attr_e( 'or drop it here', 'menudash' ); ?>"><?php esc_html_e( 'or drop it here', 'menudash' ); ?></span>
					</label>
					<div class="mdash-upload-btns"><?php submit_button( __( 'Upload menu', 'menudash' ), 'primary', 'submit', false ); ?></div>
				</form>
				</div>
				<div class="mdash-card-status">
				<?php if ( $menu ) : ?>
					<p class="mdash-now">
					<?php
					printf(
						/* translators: 1: file name, 2: date and time, 3: "12 categories", 4: "85 dishes". */
						esc_html__( 'Live now: %1$s, uploaded %2$s — %3$s, %4$s.', 'menudash' ),
						'<strong>' . esc_html( $menu['source']['name'] ) . '</strong>',
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $menu['source']['time'] ) ),
						/* translators: %d: number of categories. */
						esc_html( sprintf( _n( '%d category', '%d categories', count( $menu['sections'] ), 'menudash' ), count( $menu['sections'] ) ) ),
						/* translators: %d: number of dishes. */
						esc_html( sprintf( _n( '%d dish', '%d dishes', (int) $menu['dishes'], 'menudash' ), (int) $menu['dishes'] ) )
					);
					?>
					</p>
					<?php if ( in_array( $menu['source']['file'], $files, true ) ) : ?>
						<p class="mdash-download">
							<a class="button button-small" href="<?php echo esc_url( mdash_download_url() ); ?>"><?php esc_html_e( 'Download menu (CSV)', 'menudash' ); ?></a>
							<span class="description"><?php esc_html_e( 'The live menu as a spreadsheet file: download it to change the menu and upload it again, or to print it with a menu designer.', 'menudash' ); ?></span>
						</p>
					<?php endif; ?>
				<?php else : ?>
					<p class="mdash-now"><?php esc_html_e( 'No menu uploaded yet.', 'menudash' ); ?></p>
				<?php endif; ?>
				<?php if ( count( $files ) > 1 ) : ?>
					<details>
						<summary><?php esc_html_e( 'Earlier files', 'menudash' ); ?></summary>
						<?php foreach ( $files as $f ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mdash-restore">
								<input type="hidden" name="action" value="menudash_restore">
								<input type="hidden" name="file" value="<?php echo esc_attr( $f ); ?>">
								<?php wp_nonce_field( 'menudash_restore' ); ?>
								<span><?php echo esc_html( isset( $names[ $f ] ) ? $names[ $f ] : $f ); ?> <small>(<?php echo esc_html( preg_replace( '/^menu-(\d{4}-\d\d-\d\d)-(\d\d)(\d\d)(\d\d).*$/', '$1 $2:$3 UTC', $f ) ); ?>)</small></span>
								<?php if ( $menu && $menu['source']['file'] === $f ) : ?>
									<em><?php esc_html_e( 'live', 'menudash' ); ?></em>
								<?php else : ?>
									<button class="button button-small"><?php esc_html_e( 'Put back', 'menudash' ); ?></button>
								<?php endif; ?>
								<a href="<?php echo esc_url( mdash_download_url( $f ) ); ?>"><?php esc_html_e( 'Download', 'menudash' ); ?></a>
							</form>
						<?php endforeach; ?>
					</details>
				<?php endif; ?>
				</div>
			</section>

			<section class="mdash-card">
				<h2><?php esc_html_e( '2. Dish photos', 'menudash' ); ?></h2>
				<div class="mdash-card-howto">
				<p>
				<?php
				printf(
					/* translators: 1: example file name 22_Dumplings.png, 2: example file name Jasmine Rice.png, 3: upload size limit, e.g. 8 MB. */
					esc_html__( 'Select all the photos at once (⌘A in the folder); they upload straight away. Name each one after the dish number, e.g. %1$s, or exactly like the dish when it has no number, e.g. %2$s. A photo with the same name as an earlier one replaces it. PNG with a transparent background looks best; big files are shrunk in the browser first (this server accepts up to %3$s per file).', 'menudash' ),
					'<code>22_Dumplings.png</code>',
					'<code>Jasmine Rice.png</code>',
					esc_html( mdash_bytes( wp_max_upload_size() ) )
				);
				?>
				</p>
				</div>
				<div class="mdash-card-upload">
				<label class="mdash-drop mdash-drop-photos">
					<input type="file" id="mdash-photos" class="mdash-file" accept="image/png,image/jpeg,image/webp" multiple>
					<span class="button button-small"><?php esc_html_e( 'Choose photos', 'menudash' ); ?></span>
					<span class="mdash-drop-types"><?php esc_html_e( 'PNG, JPG or WebP', 'menudash' ); ?></span>
					<span class="mdash-drop-name"><?php esc_html_e( 'or drop them here', 'menudash' ); ?></span>
				</label>
				</div>
				<div class="mdash-card-status">
				<div id="mdash-progress" hidden>
					<div class="mdash-meter"><span></span></div>
					<p class="mdash-status"></p>
					<ol class="mdash-log"></ol>
				</div>
				</div>
			</section>

			<?php
			/** More cards beside the menu and photos, e.g. MenuDash Specials' upload. */
			do_action( 'menudash_admin_menu_cards', $names );
			?>
		</div>
		<?php
		// A photo that an add-on shows (e.g. for a special) is not "unused".
		$used   = (array) apply_filters( 'menudash_admin_used_photos', array(), $photos );
		$unused = array_diff( $match['unmatched'], $used );
		?>
		<?php if ( $menu || apply_filters( 'menudash_admin_show_check', false ) ) : ?>
			<section class="mdash-card mdash-check">
				<?php
				// Numbered after the add-ons' cards (Specials: two); an add-on that doesn't say counts one.
				$extra = (int) apply_filters( 'menudash_admin_menu_card_count', 0 );
				$extra = ! $extra && has_action( 'menudash_admin_menu_cards' ) ? 1 : $extra;
				?>
				<?php /* translators: %d: the card's number, e.g. 3. */ ?>
				<h2><?php echo esc_html( sprintf( __( '%d. Check', 'menudash' ), 3 + $extra ) ); ?></h2>
				<?php /* translators: %d: number of photos. */ ?>
				<p><?php echo esc_html( sprintf( _n( '%d photo uploaded.', '%d photos uploaded.', count( $photos ), 'menudash' ), count( $photos ) ) ); ?></p>

				<?php if ( $menu && ( $unused || $match['spare'] ) ) : ?>
					<h3><?php esc_html_e( 'Photos not shown', 'menudash' ); ?></h3>
					<p><?php esc_html_e( 'Rename these to the dish number and upload again, or delete them.', 'menudash' ); ?></p>
					<ul class="mdash-unused">
						<?php foreach ( array_merge( $unused, array_keys( $match['spare'] ) ) as $id ) : ?>
							<?php $p = $photos[ $id ]; ?>
							<li>
								<img src="<?php echo esc_url( mdash_photo_url( $p, 400 ) ); ?>" alt="" width="56" height="56">
								<span><?php echo esc_html( $p['name'] ); ?><br>
									<?php /* translators: %s: dish number and name. */ ?>
									<small><?php echo esc_html( isset( $match['spare'][ $id ] ) ? sprintf( __( 'A newer photo is used for %s', 'menudash' ), mdash_dish_label( $menu, $match['spare'][ $id ] ) ) : __( 'Matches no dish', 'menudash' ) ); ?></small></span>
								<button type="button" class="button-link mdash-del" data-id="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Delete', 'menudash' ); ?></button>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php do_action( 'menudash_admin_check', $photos ); ?>

				<?php if ( $menu ) : ?>
					<h3><?php echo esc_html_x( 'Menu', 'restaurant menu (heading)', 'menudash' ); ?> <small>
					<?php
					/* translators: 1: dishes with a photo, 2: all dishes. */
					echo esc_html( sprintf( _n( '%1$d of %2$d dish with a photo', '%1$d of %2$d dishes with a photo', (int) $menu['dishes'], 'menudash' ), $with, (int) $menu['dishes'] ) );
					?>
					</small></h3>
					<?php mdash_admin_dish_table( $menu, $photos, $match ); ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>
		<?php mdash_origin_admin_card(); ?>
		<details class="mdash-card mdash-diag">
			<summary><?php esc_html_e( 'Server details', 'menudash' ); ?></summary>
			<?php
			$editor = _wp_image_editor_choose( array( 'mime_type' => 'image/png' ) );
			$rows   = array(
				'PHP'                                     => PHP_VERSION,
				__( 'Image editor', 'menudash' )          => $editor ? $editor : __( 'none — photos cannot be resized', 'menudash' ),
				__( 'WebP output', 'menudash' )           => wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ? __( 'yes', 'menudash' ) : __( 'no (PNG/JPEG used instead)', 'menudash' ),
				__( 'Upload limit per file', 'menudash' ) => mdash_bytes( wp_max_upload_size() ),
				'memory_limit'                            => ini_get( 'memory_limit' ),
				__( 'Menu folder', 'menudash' )           => sprintf(
					wp_is_writable( mdash_dir() )
						/* translators: %s: folder path on the server. */
						? __( '%s (writable)', 'menudash' )
						/* translators: %s: folder path on the server. */
						: __( '%s (NOT writable)', 'menudash' ),
					mdash_dir()
				),
				__( 'Plugin version', 'menudash' )        => MENUDASH_VERSION,
			);
			?>
			<table class="widefat striped"><?php foreach ( $rows as $k => $v ) : ?><tr><th><?php echo esc_html( $k ); ?></th><td><?php echo esc_html( $v ); ?></td></tr><?php endforeach; ?></table>
		</details>
		<?php elseif ( 'menudash-design' !== $page ) : ?>
		<?php /** An add-on's tab. */ do_action( 'menudash_admin_tab', $page ); ?>
		<?php elseif ( 'menudash-design' === $page ) : ?>
		<?php
		$colors = mdash_colors();
		$own    = mdash_colors_own();
		$themec = mdash_theme_colors();
		$mode   = mdash_colors_mode();
		$keys   = mdash_color_keys();
		?>
		<section class="mdash-card mdash-colors-card">
			<h2><?php esc_html_e( 'Colours', 'menudash' ); ?></h2>
			<p><?php esc_html_e( 'The background of the menu, the specials and the gift card form, and the highlight colour of buttons, filters, headings, the Recommended icon and the holiday notice. Text on the highlight colour turns dark by itself when the colour is light.', 'menudash' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="menudash_colors">
				<?php wp_nonce_field( 'menudash_colors' ); ?>
				<?php if ( $themec ) : ?>
					<fieldset class="mdash-colors-mode">
						<legend class="screen-reader-text"><?php esc_html_e( 'Which colours', 'menudash' ); ?></legend>
						<label><input type="radio" name="colors_mode" value="theme" <?php checked( 'theme', $mode ); ?> data-theme-bg="<?php echo esc_attr( $themec['bg'] ); ?>" data-theme-accent="<?php echo esc_attr( $themec['accent'] ); ?>">
							<strong><?php esc_html_e( 'The theme\'s colours', 'menudash' ); ?></strong>
							<span class="mdash-swatch" style="background:<?php echo esc_attr( $themec['bg'] ); ?>"></span><span class="mdash-swatch" style="background:<?php echo esc_attr( $themec['accent'] ); ?>"></span>
							<?php /* translators: %s: theme name. */ ?>
							<span class="mdash-muted"><?php echo esc_html( sprintf( __( 'from %s. They follow the theme: change them under Appearance → Editor → Styles.', 'menudash' ), wp_get_theme()->get( 'Name' ) ) ); ?></span></label>
						<label><input type="radio" name="colors_mode" value="own" <?php checked( 'own', $mode ); ?>>
							<strong><?php esc_html_e( 'My own colours', 'menudash' ); ?></strong> <span class="mdash-muted"><?php esc_html_e( 'chosen below and saved here; they stay saved when you switch to the theme\'s.', 'menudash' ); ?></span></label>
					</fieldset>
				<?php endif; ?>
				<div class="mdash-colors-layout">
					<div class="mdash-colors-pick"<?php echo $themec && 'theme' === $mode ? ' data-off' : ''; ?>>
						<?php $color_labels = mdash_color_labels(); ?>
						<?php foreach ( $keys as $k => $c ) : ?>
							<label class="mdash-color">
								<input type="color" name="colors[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( strtolower( $own[ $k ] ) ); ?>" data-color="<?php echo esc_attr( $k ); ?>">
								<span><strong><?php echo esc_html( $color_labels[ $k ] ); ?></strong><br><code class="mdash-color-hex"><?php echo esc_html( $own[ $k ] ); ?></code><?php echo $own[ $k ] === $c[1] ? ' <small class="mdash-muted">' . esc_html__( 'default', 'menudash' ) . '</small>' : ''; ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="mdash-colors-preview" style="--p-bg: <?php echo esc_attr( $colors['bg'] ); ?>; --p-accent: <?php echo esc_attr( $colors['accent'] ); ?>; --p-on: <?php echo esc_attr( mdash_on_accent( $colors['accent'] ) ); ?>;" aria-hidden="true">
						<span class="p-title">Heute empfohlen</span>
						<span class="p-row"><span class="p-btn is-on">Alle</span><span class="p-btn">DE</span><span class="p-btn">EN</span></span>
						<span class="p-row"><span class="p-chip is-on">Vegetarisch</span><span class="p-chip">Scharf</span></span>
						<span class="p-dish"><b>12</b> Nasi Lemak <i>18.5</i></span>
					</div>
				</div>
				<p class="mdash-closed-actions">
					<?php submit_button( __( 'Save colours', 'menudash' ), 'primary', 'save_colors', false ); ?>
					<?php if ( ! $themec && mdash_colors_custom() ) : ?>
						<button class="button" name="reset" value="1"><?php esc_html_e( 'Back to default', 'menudash' ); ?></button>
					<?php endif; ?>
				</p>
			</form>
		</section>
		<?php $tf = mdash_theme_fonts(); ?>
		<section class="mdash-card mdash-fonts-card">
			<h2><?php esc_html_e( 'Fonts', 'menudash' ); ?></h2>
			<p><?php esc_html_e( 'The fonts of the menu, the specials, the gift card form and the recommended dishes. Chinese always uses the device\'s Chinese font.', 'menudash' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="menudash_fonts">
				<?php wp_nonce_field( 'menudash_fonts' ); ?>
				<fieldset class="mdash-colors-mode">
					<legend class="screen-reader-text"><?php esc_html_e( 'Which fonts', 'menudash' ); ?></legend>
					<label><input type="radio" name="fonts_mode" value="theme" <?php checked( 'theme', mdash_fonts_mode() ); ?> <?php disabled( ! $tf ); ?>>
						<strong><?php esc_html_e( 'The theme\'s fonts', 'menudash' ); ?></strong>
						<?php /* translators: %s: theme name. */ ?>
						<span class="mdash-muted"><?php echo esc_html( sprintf( $tf ? __( 'from %s. They follow the theme: change them under Appearance → Editor → Styles → Typography.', 'menudash' ) : __( '%s has no fonts to give (only block themes do), so MenuDash uses its own.', 'menudash' ), wp_get_theme()->get( 'Name' ) ) ); ?></span></label>
					<label><input type="radio" name="fonts_mode" value="own" <?php checked( 'own' === mdash_fonts_mode() || ! $tf ); ?>>
						<strong><?php esc_html_e( 'MenuDash\'s fonts', 'menudash' ); ?></strong>
						<span class="mdash-muted"><?php esc_html_e( 'DM Sans for the text, Darker Grotesque for headings (the same as MenuDash Theme), included with MenuDash.', 'menudash' ); ?></span></label>
				</fieldset>
				<p class="mdash-closed-actions"><?php submit_button( __( 'Save fonts', 'menudash' ), 'primary', 'save_fonts', false ); ?></p>
			</form>
		</section>
		<section class="mdash-card mdash-icons-card">
			<h2><?php esc_html_e( 'Diet icons', 'menudash' ); ?></h2>
			<p><?php esc_html_e( 'Use your own icons for the diet marks: an SVG, or a square PNG with a transparent background. "Not spicy" is the Spicy icon, crossed out.', 'menudash' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="menudash_icons">
				<?php wp_nonce_field( 'menudash_icons' ); ?>
				<div class="mdash-icon-grid">
					<?php $custom = mdash_icon_index(); ?>
					<?php foreach ( mdash_icon_labels() as $key => $label ) : ?>
						<div class="mdash-icon-item" data-drop title="<?php esc_attr_e( 'Drop an icon file here', 'menudash' ); ?>">
							<span class="mdash-icon-prev"><?php echo mdash_icon( $key ); // phpcs:ignore ?></span>
							<span class="mdash-icon-name"><strong><?php echo esc_html( $label ); ?></strong><br>
								<?php /* translators: %s: file name of the uploaded icon. */ ?>
								<small><?php echo esc_html( isset( $custom[ $key ] ) ? sprintf( __( 'Your icon: %s', 'menudash' ), $custom[ $key ]['name'] ) : __( 'Default icon', 'menudash' ) ); ?></small></span>
							<?php /* translators: %s: icon name, e.g. Spicy. */ ?>
							<input type="file" name="icon_<?php echo esc_attr( $key ); ?>" accept=".svg,image/svg+xml,image/png,image/jpeg,image/webp" aria-label="<?php echo esc_attr( sprintf( __( 'New %s icon', 'menudash' ), $label ) ); ?>">
							<?php if ( isset( $custom[ $key ] ) ) : ?>
								<button class="button-link" name="reset" value="<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Back to default', 'menudash' ); ?></button>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<?php submit_button( __( 'Save icons', 'menudash' ), 'secondary', 'save_icons', false ); ?>
			</form>
		</section>
		<?php endif; ?>
		<p class="mdash-by">
		<?php
		printf(
			/* translators: %s: link to insdash, the maker of MenuDash. */
			esc_html__( 'MenuDash by %s · add-ons, set-up and help', 'menudash' ),
			'<a href="https://insdash.ch/projects/menudash/" target="_blank" rel="noopener">insdash</a>'
		);
		?>
		</p>
		<?php echo mdash_sprite(); // phpcs:ignore -- built from our own sprite and cleaned SVG ?>
	</div>
	<?php
}
