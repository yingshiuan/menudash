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
add_action( 'admin_post_menudash_icons', 'mdash_handle_icons' );
add_action( 'admin_post_menudash_colors', 'mdash_handle_colors' );
add_action( 'wp_ajax_menudash_photo', 'mdash_ajax_photo' );
add_action( 'wp_ajax_menudash_photo_delete', 'mdash_ajax_photo_delete' );
add_filter( 'plugin_action_links_' . plugin_basename( MENUDASH_FILE ), 'mdash_action_links' );

function mdash_admin_menu() {
	// Sidebar and page carry the plugin's name. Not plain "Menu": WordPress's own "Menüs"
	// (navigation) sits nearby.
	add_menu_page( MENUDASH_NAME, MENUDASH_NAME, MDASH_CAP, 'menudash', 'mdash_admin_page', 'dashicons-food', 26 );
	// The same page in four parts, each in the sidebar and as a tab at the top.
	foreach ( mdash_admin_tabs() as $slug => $label ) {
		add_submenu_page( 'menudash', MENUDASH_NAME . ' – ' . $label, $label, MDASH_CAP, $slug, 'mdash_admin_page' );
	}
}

/** Page slug => tab name. The first is the page the sidebar's MenuDash opens. */
function mdash_admin_tabs() {
	// Add-ons add their tabs here (MenuDash Restaurant, Gift Cards); Colours & icons stays last.
	$tabs = (array) apply_filters( 'menudash_admin_tabs', array( 'menudash' => 'Menu' ) );
	unset( $tabs['menudash-design'] );
	$tabs['menudash-design'] = 'Colours & icons'; // Not "Design": that is WordPress's own Appearance menu in German.
	return $tabs;
}

function mdash_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=menudash' ) ) . '">Upload menu</a>' );
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
		wp_die( 'You are not allowed to change the menu.', 403 );
	}
	$f = isset( $_FILES['csv'] ) ? $_FILES['csv'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $f || UPLOAD_ERR_OK !== $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
		$code = $f ? (int) $f['error'] : UPLOAD_ERR_NO_FILE;
		mdash_back( array( 'ok' => false, 'error' => UPLOAD_ERR_NO_FILE === $code ? 'Choose the CSV file first.' : "The upload failed (code $code)." ) );
	}
	$name = sanitize_text_field( wp_unslash( $f['name'] ) );
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	if ( 'numbers' === $ext ) {
		mdash_back( array( 'ok' => false, 'error' => 'A Numbers file can\'t be read by a website. In Numbers, choose File → Export To → Excel (keeps the sheets menu, specials, lunch) or CSV, and upload that.' ) );
	}
	if ( in_array( $ext, array( 'xls', 'xlsm', 'xlsb', 'ods' ), true ) ) {
		mdash_back( array( 'ok' => false, 'error' => 'Save the spreadsheet as an Excel workbook (.xlsx) or as CSV, and upload that.' ) );
	}
	if ( 'xlsx' === $ext ) {
		mdash_handle_xlsx( $f['tmp_name'], $name, (int) $f['size'] );
	}
	if ( $f['size'] > 2 * MB_IN_BYTES ) {
		mdash_back( array( 'ok' => false, 'error' => 'That file is over 2 MB; a menu CSV is about 20 KB. Is it the right file?' ) );
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
		mdash_back( array( 'ok' => false, 'error' => 'That file is over 10 MB; a menu workbook is well under 1 MB. Is it the right file? (Pictures inside the workbook make it big; they are not needed.)' ) );
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
		mdash_back( array( 'ok' => false, 'error' => 'No sheet called menu, specials or lunch in this workbook (found: ' . implode( ', ', array_keys( $x['sheets'] ) ) . '). Rename the sheets in Numbers or Excel, then export again.' ) );
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
			$also[] = isset( $done[ $kind ] ) ? (string) $done[ $kind ] : sprintf( 'Sheet "%s" not used: it needs the MenuDash Specials add-on.', $s['sheet'] );
		}
	}
	foreach ( $unused as $sheet ) {
		$also[] = sprintf( 'Sheet "%s" not used (MenuDash reads the sheets menu, specials and lunch).', $sheet );
	}
	if ( isset( $files['menu'] ) ) {
		$result = mdash_apply_menu_csv( $files['menu']['path'], $files['menu']['label'] ) + array( 'name' => $files['menu']['label'] );
	} else {
		$result = array( 'ok' => true, 'kind' => 'Excel file', 'message' => 'No sheet called menu, so the menu was not changed.' );
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
		wp_die( 'You are not allowed to change the menu.', 403 );
	}
	if ( isset( $_POST['reset'] ) ) {
		delete_option( MDASH_COLORS_OPTION );
		mdash_purge_caches();
		mdash_back( array( 'ok' => true, 'kind' => 'Colours', 'message' => 'Back to the default colours.' ) );
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
	mdash_back( array( 'ok' => ! $problems, 'kind' => 'Colours', 'message' => 'theme' === $mode ? 'Saved: the menu uses the theme\'s colours.' : 'Saved: the menu uses your own colours.', 'error' => implode( ' ', $problems ) ) );
}

function mdash_handle_icons() {
	mdash_back_to( 'menudash-design' );
	check_admin_referer( 'menudash_icons' );
	if ( ! mdash_can() ) {
		wp_die( 'You are not allowed to change the menu.', 403 );
	}
	$labels = mdash_icon_keys();
	$done   = array();
	$errors = array();
	if ( isset( $_POST['reset'] ) ) {
		$key = sanitize_key( wp_unslash( $_POST['reset'] ) );
		if ( isset( $labels[ $key ] ) ) {
			mdash_icon_reset( $key );
			$done[] = "$labels[$key] is back to the default icon";
		}
	}
	foreach ( $labels as $key => $label ) {
		$f = isset( $_FILES[ "icon_$key" ] ) ? $_FILES[ "icon_$key" ] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! $f || UPLOAD_ERR_NO_FILE === $f['error'] ) {
			continue;
		}
		if ( UPLOAD_ERR_OK !== $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
			$errors[] = "$label: the upload failed (code {$f['error']}).";
			continue;
		}
		if ( $f['size'] > 2 * MB_IN_BYTES ) {
			$errors[] = "$label: the file is over 2 MB; an icon is usually a few KB.";
			continue;
		}
		$r = mdash_icon_set( $key, $f['tmp_name'], wp_unslash( $f['name'] ) );
		if ( $r['ok'] ) {
			$done[] = "$label icon replaced";
		} else {
			$errors[] = "$label: " . $r['error'];
		}
	}
	if ( ! $done && ! $errors ) {
		$errors[] = 'Choose an icon file first.';
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
		wp_die( 'You are not allowed to change the menu.', 403 );
	}
	$file   = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
	$result = mdash_restore_csv( $file );
	mdash_back( $result + array( 'name' => $file, 'restored' => true ) );
}

function mdash_ajax_photo() {
	check_ajax_referer( 'menudash_photo' );
	if ( ! mdash_can() ) {
		wp_send_json_error( array( 'error' => 'You are not allowed to upload photos.' ), 403 );
	}
	$f = isset( $_FILES['photo'] ) ? $_FILES['photo'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $f || UPLOAD_ERR_OK !== $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
		$code = $f ? (int) $f['error'] : UPLOAD_ERR_NO_FILE;
		$why  = in_array( $code, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ? 'the file is larger than this server accepts' : "upload error $code";
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
		wp_send_json_error( array( 'error' => 'Not allowed.' ), 403 );
	}
	$id = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
	mdash_photo_delete( $id ) ? wp_send_json_success() : wp_send_json_error( array( 'error' => 'No such photo.' ) );
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
			'post_type'      => array( 'page', 'post' ),
			'post_status'    => array( 'publish', 'draft', 'private' ),
			's'              => '[menudash',
			'posts_per_page' => 5,
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
		<thead><tr><th>Nr.</th><th>Photo</th><th>Name</th><th>中文</th><th>Marks</th><th class="num">Price</th></tr></thead>
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
								<span class="mdash-missing">no photo</span>
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
		<nav class="nav-tab-wrapper mdash-tabs" aria-label="MenuDash sections">
			<?php foreach ( mdash_admin_tabs() as $slug => $label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $slug ) ); ?>" class="nav-tab<?php echo $slug === $page ? ' nav-tab-active' : ''; ?>"<?php echo $slug === $page ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php // WordPress moves notices under this line, so they stay below the tabs. ?>
		<hr class="wp-header-end">
		<?php if ( $report ) : ?>
			<?php if ( isset( $report['message'] ) ) : ?>
				<div class="notice <?php echo $report['ok'] ? 'notice-success' : ( $report['message'] ? 'notice-warning' : 'notice-error' ); ?>">
					<p><strong><?php echo esc_html( $report['kind'] ? $report['kind'] : 'Diet icons' ); ?>:</strong> <?php echo esc_html( trim( $report['message'] . ' ' . $report['error'] ) ); ?></p>
					<?php if ( ! empty( $report['also'] ) ) : ?>
						<ul class="mdash-also"><?php foreach ( $report['also'] as $w ) : ?><li><?php echo esc_html( $w ); ?></li><?php endforeach; ?></ul>
					<?php endif; ?>
				</div>
			<?php else : ?>
			<div class="notice <?php echo $report['ok'] ? ( empty( $report['warnings'] ) ? 'notice-success' : 'notice-warning' ) : 'notice-error'; ?>">
				<?php if ( $report['ok'] ) : ?>
					<p><strong><?php echo ! empty( $report['restored'] ) ? 'Restored' : 'Uploaded'; ?> <?php echo esc_html( $report['name'] ); ?>.</strong>
					The menu now has <?php echo (int) $report['sections']; ?> categories and <?php echo (int) $report['dishes']; ?> dishes.</p>
				<?php else : ?>
					<p><strong>The menu was not changed.</strong> <?php echo esc_html( $report['error'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $report['warnings'] ) ) : ?>
					<p>Please check:</p>
					<ul class="mdash-warn"><?php foreach ( $report['warnings'] as $w ) : ?><li><?php echo esc_html( $w ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<?php if ( ! empty( $report['also'] ) ) : ?>
					<p>From the same file:</p>
					<ul class="mdash-also"><?php foreach ( $report['also'] as $w ) : ?><li><?php echo esc_html( $w ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
			</div>
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( 'menudash' === $page ) : ?>
		<p class="mdash-lead">
			<?php if ( $pages ) : ?>
				The menu is on:
				<?php foreach ( $pages as $i => $p ) : ?>
					<?php echo $i ? ' · ' : ''; ?><a href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $p ) ); ?></a><?php echo 'publish' !== $p->post_status ? ' (' . esc_html( $p->post_status ) . ')' : ''; ?>
				<?php endforeach; ?>
			<?php else : ?>
				To show the menu, add the shortcode <code>[menudash]</code> to a page.
			<?php endif; ?>
			Changes here show there straight away.
		</p>

		<div class="mdash-cards">
			<section class="mdash-card">
				<h2>1. Menu</h2>
				<div class="mdash-card-howto">
				<p>Your menu spreadsheet, exported as <strong>Excel</strong> (.xlsx; from Numbers: File → Export To → Excel) or as <strong>CSV</strong> (UTF-8, to keep the Chinese). It replaces the whole menu. In an Excel file, the sheet named <em>menu</em> is the menu<?php echo has_filter( 'menudash_xlsx_sheets' ) ? ', and sheets named <em>specials</em> and <em>lunch</em> update cards 3 and 4 in the same upload' : ''; ?>.</p>
				</div>
				<div class="mdash-card-upload">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="mdash-upload-row">
					<input type="hidden" name="action" value="menudash_csv">
					<?php wp_nonce_field( 'menudash_csv' ); ?>
					<label class="mdash-drop" data-drop>
						<input type="file" name="csv" class="mdash-file" accept=".csv,text/csv,text/plain,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
						<span class="button button-small">Choose file</span>
						<span class="mdash-drop-types">CSV or Excel (.xlsx)</span>
						<span class="mdash-drop-name" data-empty="or drop it here">or drop it here</span>
					</label>
					<div class="mdash-upload-btns"><?php submit_button( 'Upload menu', 'primary', 'submit', false ); ?></div>
				</form>
				</div>
				<div class="mdash-card-status">
				<?php if ( $menu ) : ?>
					<p class="mdash-now">Live now: <strong><?php echo esc_html( $menu['source']['name'] ); ?></strong>, uploaded <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $menu['source']['time'] ) ); ?> — <?php echo (int) count( $menu['sections'] ); ?> categories, <?php echo (int) $menu['dishes']; ?> dishes.</p>
				<?php else : ?>
					<p class="mdash-now">No menu uploaded yet.</p>
				<?php endif; ?>
				<?php if ( count( $files ) > 1 ) : ?>
					<details>
						<summary>Earlier files</summary>
						<?php foreach ( $files as $f ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mdash-restore">
								<input type="hidden" name="action" value="menudash_restore">
								<input type="hidden" name="file" value="<?php echo esc_attr( $f ); ?>">
								<?php wp_nonce_field( 'menudash_restore' ); ?>
								<span><?php echo esc_html( isset( $names[ $f ] ) ? $names[ $f ] : $f ); ?> <small>(<?php echo esc_html( preg_replace( '/^menu-(\d{4}-\d\d-\d\d)-(\d\d)(\d\d)(\d\d).*$/', '$1 $2:$3 UTC', $f ) ); ?>)</small></span>
								<?php if ( $menu && $menu['source']['file'] === $f ) : ?>
									<em>live</em>
								<?php else : ?>
									<button class="button button-small">Put back</button>
								<?php endif; ?>
							</form>
						<?php endforeach; ?>
					</details>
				<?php endif; ?>
				</div>
			</section>

			<section class="mdash-card">
				<h2>2. Dish photos</h2>
				<div class="mdash-card-howto">
				<p>Select all the photos at once (⌘A in the folder); they upload straight away. Name each one after the dish number, e.g. <code>22_Dumplings.png</code>, or exactly like the dish when it has no number, e.g. <code>Jasmine Rice.png</code>. A photo with the same name as an earlier one replaces it. PNG with a transparent background looks best; big files are shrunk in the browser first (this server accepts up to <?php echo esc_html( mdash_bytes( wp_max_upload_size() ) ); ?> per file).</p>
				</div>
				<div class="mdash-card-upload">
				<label class="mdash-drop mdash-drop-photos">
					<input type="file" id="mdash-photos" class="mdash-file" accept="image/png,image/jpeg,image/webp" multiple>
					<span class="button button-small">Choose photos</span>
					<span class="mdash-drop-types">PNG, JPG or WebP</span>
					<span class="mdash-drop-name">or drop them here</span>
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
				<h2><?php echo (int) ( 3 + $extra ); ?>. Check</h2>
				<p><?php echo (int) count( $photos ); ?> photos uploaded.</p>

				<?php if ( $menu && ( $unused || $match['spare'] ) ) : ?>
					<h3>Photos not shown</h3>
					<p>Rename these to the dish number and upload again, or delete them.</p>
					<ul class="mdash-unused">
						<?php foreach ( array_merge( $unused, array_keys( $match['spare'] ) ) as $id ) : ?>
							<?php $p = $photos[ $id ]; ?>
							<li>
								<img src="<?php echo esc_url( mdash_photo_url( $p, 400 ) ); ?>" alt="" width="56" height="56">
								<span><?php echo esc_html( $p['name'] ); ?><br>
									<small><?php echo isset( $match['spare'][ $id ] ) ? 'A newer photo is used for ' . esc_html( mdash_dish_label( $menu, $match['spare'][ $id ] ) ) : 'Matches no dish'; ?></small></span>
								<button type="button" class="button-link mdash-del" data-id="<?php echo esc_attr( $id ); ?>">Delete</button>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php do_action( 'menudash_admin_check', $photos ); ?>

				<?php if ( $menu ) : ?>
					<h3>Menu <small><?php echo (int) $with; ?> of <?php echo (int) $menu['dishes']; ?> dishes with a photo</small></h3>
					<?php mdash_admin_dish_table( $menu, $photos, $match ); ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>
		<?php mdash_origin_admin_card(); ?>
		<details class="mdash-card mdash-diag">
			<summary>Server details</summary>
			<?php
			$editor = _wp_image_editor_choose( array( 'mime_type' => 'image/png' ) );
			$rows   = array(
				'PHP'                      => PHP_VERSION,
				'Image editor'             => $editor ? $editor : 'none — photos cannot be resized',
				'WebP output'              => wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ? 'yes' : 'no (PNG/JPEG used instead)',
				'Upload limit per file'    => mdash_bytes( wp_max_upload_size() ),
				'memory_limit'             => ini_get( 'memory_limit' ),
				'Menu folder'              => mdash_dir() . ( wp_is_writable( mdash_dir() ) ? ' (writable)' : ' (NOT writable)' ),
				'Plugin version'           => MENUDASH_VERSION,
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
			<h2>Colours</h2>
			<p>The background of the menu, the specials and the gift card form, and the highlight colour of buttons, filters, headings, the Recommended icon and the holiday notice. Text on the highlight colour turns dark by itself when the colour is light.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="menudash_colors">
				<?php wp_nonce_field( 'menudash_colors' ); ?>
				<?php if ( $themec ) : ?>
					<fieldset class="mdash-colors-mode">
						<legend class="screen-reader-text">Which colours</legend>
						<label><input type="radio" name="colors_mode" value="theme" <?php checked( 'theme', $mode ); ?> data-theme-bg="<?php echo esc_attr( $themec['bg'] ); ?>" data-theme-accent="<?php echo esc_attr( $themec['accent'] ); ?>">
							<strong>The theme's colours</strong>
							<span class="mdash-swatch" style="background:<?php echo esc_attr( $themec['bg'] ); ?>"></span><span class="mdash-swatch" style="background:<?php echo esc_attr( $themec['accent'] ); ?>"></span>
							<span class="mdash-muted">from <?php echo esc_html( wp_get_theme()->get( 'Name' ) ); ?>. They follow the theme: change them under Appearance → Editor → Styles.</span></label>
						<label><input type="radio" name="colors_mode" value="own" <?php checked( 'own', $mode ); ?>>
							<strong>My own colours</strong> <span class="mdash-muted">chosen below and saved here; they stay saved when you switch to the theme's.</span></label>
					</fieldset>
				<?php endif; ?>
				<div class="mdash-colors-layout">
					<div class="mdash-colors-pick"<?php echo $themec && 'theme' === $mode ? ' data-off' : ''; ?>>
						<?php foreach ( $keys as $k => $c ) : ?>
							<label class="mdash-color">
								<input type="color" name="colors[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( strtolower( $own[ $k ] ) ); ?>" data-color="<?php echo esc_attr( $k ); ?>">
								<span><strong><?php echo esc_html( $c[0] ); ?></strong><br><code class="mdash-color-hex"><?php echo esc_html( $own[ $k ] ); ?></code><?php echo $own[ $k ] === $c[1] ? ' <small class="mdash-muted">default</small>' : ''; ?></span>
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
					<?php submit_button( 'Save colours', 'primary', 'save_colors', false ); ?>
					<?php if ( ! $themec && mdash_colors_custom() ) : ?>
						<button class="button" name="reset" value="1">Back to default</button>
					<?php endif; ?>
				</p>
			</form>
		</section>
		<section class="mdash-card mdash-icons-card">
			<h2>Diet icons</h2>
			<p>Use your own icons for the diet marks: an SVG, or a square PNG with a transparent background. "Not spicy" is the Spicy icon, crossed out.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="menudash_icons">
				<?php wp_nonce_field( 'menudash_icons' ); ?>
				<div class="mdash-icon-grid">
					<?php $custom = mdash_icon_index(); ?>
					<?php foreach ( mdash_icon_keys() as $key => $label ) : ?>
						<div class="mdash-icon-item" data-drop title="Drop an icon file here">
							<span class="mdash-icon-prev"><?php echo mdash_icon( $key ); // phpcs:ignore ?></span>
							<span class="mdash-icon-name"><strong><?php echo esc_html( $label ); ?></strong><br>
								<small><?php echo isset( $custom[ $key ] ) ? 'Your icon: ' . esc_html( $custom[ $key ]['name'] ) : 'Default icon'; ?></small></span>
							<input type="file" name="icon_<?php echo esc_attr( $key ); ?>" accept=".svg,image/svg+xml,image/png,image/jpeg,image/webp" aria-label="<?php echo esc_attr( "New $label icon" ); ?>">
							<?php if ( isset( $custom[ $key ] ) ) : ?>
								<button class="button-link" name="reset" value="<?php echo esc_attr( $key ); ?>">Back to default</button>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<?php submit_button( 'Save icons', 'secondary', 'save_icons', false ); ?>
			</form>
		</section>
		<?php endif; ?>
		<p class="mdash-by">MenuDash by <a href="https://insdash.ch/projects/menudash/" target="_blank" rel="noopener">insdash</a> · add-ons, set-up and help</p>
		<?php echo mdash_sprite(); // phpcs:ignore -- built from our own sprite and cleaned SVG ?>
	</div>
	<?php
}
