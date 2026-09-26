<?php
/**
 * Dashboard -> MenuDash: upload the CSV, upload photos, see what the menu page will show.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_CAP = 'edit_pages';

add_action( 'admin_menu', 'mdash_admin_menu' );
add_action( 'admin_post_menudash_csv', 'mdash_handle_csv' );
add_action( 'admin_post_menudash_restore', 'mdash_handle_restore' );
add_action( 'admin_post_menudash_specials', 'mdash_handle_specials' );
add_action( 'admin_post_menudash_specials_restore', 'mdash_handle_specials_restore' );
add_action( 'admin_post_menudash_closed', 'mdash_handle_closed' );
add_action( 'admin_post_menudash_hours', 'mdash_handle_hours' );
add_action( 'admin_post_menudash_icons', 'mdash_handle_icons' );
add_action( 'wp_ajax_menudash_photo', 'mdash_ajax_photo' );
add_action( 'wp_ajax_menudash_photo_delete', 'mdash_ajax_photo_delete' );
add_filter( 'plugin_action_links_' . plugin_basename( MENUDASH_FILE ), 'mdash_action_links' );

function mdash_admin_menu() {
	// Sidebar and page carry the plugin's name. Not plain "Menu": WordPress's own "Menüs"
	// (navigation) sits nearby.
	$hook = add_menu_page( MENUDASH_NAME, MENUDASH_NAME, MDASH_CAP, 'menudash', 'mdash_admin_page', 'dashicons-food', 26 );
	add_action( "admin_print_styles-$hook", 'mdash_admin_assets' );
}

function mdash_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=menudash' ) ) . '">Upload menu</a>' );
	return $links;
}

function mdash_admin_assets() {
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

function mdash_back( $result ) {
	// Keep only what the notice shows, not the whole parsed menu.
	$report = array(
		'ok'       => $result['ok'],
		'error'    => isset( $result['error'] ) ? $result['error'] : '',
		'warnings' => isset( $result['warnings'] ) ? $result['warnings'] : array(),
		'name'     => isset( $result['name'] ) ? $result['name'] : '',
		'restored' => ! empty( $result['restored'] ),
		'message'  => isset( $result['message'] ) ? $result['message'] : null, // Set for icon and specials uploads.
		'specials' => ! empty( $result['specials'] ),
		'kind'     => isset( $result['kind'] ) ? $result['kind'] : '',
		'sections' => empty( $result['menu'] ) ? 0 : count( $result['menu']['sections'] ),
		'dishes'   => empty( $result['menu'] ) ? 0 : $result['menu']['dishes'],
	);
	set_transient( mdash_report_key(), $report, 300 );
	wp_safe_redirect( admin_url( 'admin.php?page=menudash' ) );
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
	if ( $f['size'] > 2 * MB_IN_BYTES ) {
		mdash_back( array( 'ok' => false, 'error' => 'That file is over 2 MB; a menu CSV is about 20 KB. Is it the right file?' ) );
	}
	$name   = sanitize_text_field( wp_unslash( $f['name'] ) );
	$result = mdash_load_csv( $f['tmp_name'], $name );
	if ( $result['ok'] ) {
		mdash_store_csv( $f['tmp_name'], $name );
		$menu                   = mdash_get_menu();
		$menu['source']['file'] = mdash_csv_files()[0];
		update_option( MDASH_OPTION, $menu, false );
	}
	mdash_back( $result + array( 'name' => $name ) );
}

function mdash_handle_specials() {
	check_admin_referer( 'menudash_specials' );
	if ( ! mdash_can() ) {
		wp_die( 'You are not allowed to change the menu.', 403 );
	}
	if ( isset( $_POST['clear'] ) ) {
		mdash_clear_specials();
		mdash_back( array( 'ok' => true, 'specials' => true, 'message' => 'Removed. The specials no longer show on the site.' ) );
	}
	$f = isset( $_FILES['csv'] ) ? $_FILES['csv'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( ! $f || UPLOAD_ERR_OK !== $f['error'] || ! is_uploaded_file( $f['tmp_name'] ) ) {
		$code = $f ? (int) $f['error'] : UPLOAD_ERR_NO_FILE;
		mdash_back( array( 'ok' => false, 'specials' => true, 'message' => '', 'error' => UPLOAD_ERR_NO_FILE === $code ? 'Choose the CSV file first.' : "The upload failed (code $code)." ) );
	}
	if ( $f['size'] > 2 * MB_IN_BYTES ) {
		mdash_back( array( 'ok' => false, 'specials' => true, 'message' => '', 'error' => 'That file is over 2 MB; a specials CSV is a few KB. Is it the right file?' ) );
	}
	$name   = sanitize_text_field( wp_unslash( $f['name'] ) );
	// Parse first without publishing, so a wrong file changes nothing; then keep the file
	// and publish that stored copy, so the site always names a file that exists.
	$check = mdash_parse_csv( (string) file_get_contents( $f['tmp_name'] ) ); // phpcs:ignore
	if ( ! $check['ok'] ) {
		$result = $check;
	} else {
		$stored = mdash_store_specials( $f['tmp_name'], $name );
		$result = $stored ? mdash_put_back_specials( $stored ) : array( 'ok' => false, 'error' => 'The file could not be saved on the server.', 'warnings' => array() );
	}
	$w      = empty( $result['warnings'] ) ? '' : ' Please check: ' . implode( ' ', $result['warnings'] );
	mdash_back(
		array(
			'ok'       => $result['ok'],
			'specials' => true,
			'message'  => $result['ok'] ? sprintf( 'Uploaded %s: %d dishes are on the site now.', $name, $result['menu']['dishes'] ) . $w : '',
			'error'    => $result['ok'] ? '' : 'The specials were not changed. ' . $result['error'],
		)
	);
}

function mdash_handle_specials_restore() {
	check_admin_referer( 'menudash_specials_restore' );
	if ( ! mdash_can() ) {
		wp_die( 'You are not allowed to change the menu.', 403 );
	}
	$file   = isset( $_POST['file'] ) ? sanitize_file_name( wp_unslash( $_POST['file'] ) ) : '';
	$result = mdash_put_back_specials( $file );
	$names  = mdash_csv_names();
	$name   = isset( $names[ $file ] ) ? $names[ $file ] : $file;
	mdash_back(
		array(
			'ok'       => $result['ok'],
			'specials' => true,
			'message'  => $result['ok'] ? sprintf( 'Put back %s: %d dishes are on the site now.', $name, $result['menu']['dishes'] ) : '',
			'error'    => $result['ok'] ? '' : 'The specials were not changed. ' . $result['error'],
		)
	);
}

function mdash_handle_hours() {
	check_admin_referer( 'menudash_hours' );
	if ( ! mdash_can() ) {
		wp_die( 'You are not allowed to change the menu.', 403 );
	}
	$rows = isset( $_POST['hours'] ) && is_array( $_POST['hours'] ) ? wp_unslash( $_POST['hours'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned in mdash_hours_clean()
	list( $hours, $problems ) = mdash_hours_clean( $rows );
	update_option( MDASH_HOURS_OPTION, $hours, false );
	mdash_purge_caches();
	mdash_back(
		array(
			'ok'      => ! $problems,
			'kind'    => 'Opening hours',
			'message' => 'Saved.',
			'error'   => implode( ' ', $problems ),
		)
	);
}

function mdash_handle_closed() {
	check_admin_referer( 'menudash_closed' );
	if ( ! mdash_can() ) {
		wp_die( 'You are not allowed to change the menu.', 403 );
	}
	$rows    = isset( $_POST['closed'] ) && is_array( $_POST['closed'] ) ? wp_unslash( $_POST['closed'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned in mdash_closed_clean()
	// A row's Delete button sends its number; the other rows are saved as they are.
	$deleted = isset( $_POST['delete'] ) ? absint( $_POST['delete'] ) : null;
	if ( null !== $deleted ) {
		unset( $rows[ $deleted ] );
	}
	$periods = mdash_closed_clean( $rows, current_time( 'Y-m-d' ) );
	update_option( MDASH_CLOSED_OPTION, $periods, false );
	mdash_purge_caches();
	mdash_back(
		array(
			'ok'      => true,
			'kind'    => 'Holidays',
			'message' => ( null !== $deleted ? 'Deleted. ' : '' ) . ( $periods ? sprintf( 'Saved: %d closed period%s.', count( $periods ), 1 === count( $periods ) ? '' : 's' ) : 'Saved: no closed days.' ),
		)
	);
}

function mdash_handle_icons() {
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

/**
 * One row of the holidays table. $i is the row's number in the form, or "__i__" in the
 * template that "+ Add holiday" copies. A saved row gets its state and a Delete button; a
 * new, unsaved row gets Remove, which only takes it off the page.
 */
function mdash_closed_row( $i, $p, $today ) {
	$n = esc_attr( $i );
	?>
	<tr>
		<td><input type="date" name="closed[<?php echo $n; ?>][from]" value="<?php echo esc_attr( $p['from'] ); ?>" aria-label="From"></td>
		<td><input type="date" name="closed[<?php echo $n; ?>][to]" value="<?php echo esc_attr( $p['to'] ); ?>" aria-label="To"></td>
		<td><input type="date" name="closed[<?php echo $n; ?>][back]" value="<?php echo esc_attr( isset( $p['back'] ) ? $p['back'] : '' ); ?>" aria-label="Open again" title="Leave empty for the day after the last closed day"></td>
		<td><input type="text" name="closed[<?php echo $n; ?>][note]" value="<?php echo esc_attr( $p['note'] ); ?>" maxlength="200" placeholder="e.g. Frohe Festtage!" aria-label="Note"></td>
		<td class="mdash-closed-state">
			<?php
			if ( '' === $p['from'] ) {
				echo '';
			} elseif ( $p['to'] < $today ) {
				echo '<span class="mdash-muted">Over</span>';
			} elseif ( $p['from'] <= $today ) {
				echo '<strong>Closed now</strong>';
			} elseif ( $p['from'] <= gmdate( 'Y-m-d', strtotime( "$today +60 days" ) ) ) {
				echo 'Shown now';
			} else {
				echo esc_html( 'Shown from ' . wp_date( get_option( 'date_format' ), strtotime( $p['from'] . ' -60 days' ) ) );
			}
			?>
		</td>
		<td>
			<?php if ( '' !== $p['from'] ) : ?>
				<button class="button-link mdash-closed-del" name="delete" value="<?php echo $n; ?>" formnovalidate onclick="return confirm('Delete this closed period?');">Delete</button>
			<?php else : ?>
				<button type="button" class="button-link mdash-closed-del mdash-closed-remove">Remove</button>
			<?php endif; ?>
		</td>
	</tr>
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
	?>
	<div class="wrap mdash-admin">
		<h1><?php echo esc_html( MENUDASH_NAME ); ?></h1>
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

		<?php if ( $report ) : ?>
			<?php if ( isset( $report['message'] ) ) : ?>
				<div class="notice <?php echo $report['ok'] ? 'notice-success' : ( $report['message'] ? 'notice-warning' : 'notice-error' ); ?>">
					<p><strong><?php echo esc_html( $report['kind'] ? $report['kind'] : ( empty( $report['specials'] ) ? 'Diet icons' : 'Specials' ) ); ?>:</strong> <?php echo esc_html( trim( $report['message'] . ' ' . $report['error'] ) ); ?></p>
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
			</div>
			<?php endif; ?>
		<?php endif; ?>

		<div class="mdash-cards">
			<section class="mdash-card">
				<h2>1. Menu file (CSV)</h2>
				<p>Export your menu spreadsheet as CSV (UTF-8, to keep the Chinese), then upload it here. It replaces the whole menu.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="mdash-upload-row">
					<input type="hidden" name="action" value="menudash_csv">
					<?php wp_nonce_field( 'menudash_csv' ); ?>
					<label class="mdash-drop" data-drop>
						<input type="file" name="csv" class="mdash-file" accept=".csv,text/csv,text/plain" required>
						<span class="button button-small">Choose CSV</span>
						<span class="mdash-drop-name" data-empty="or drop it here">or drop it here</span>
					</label>
					<div class="mdash-upload-btns"><?php submit_button( 'Upload menu', 'primary', 'submit', false ); ?></div>
				</form>
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
			</section>

			<section class="mdash-card">
				<h2>2. Dish photos</h2>
				<p>Select all the photos at once (⌘A in the folder). Name each one after the dish number, e.g. <code>22_Dumplings.png</code>, or exactly like the dish when it has no number, e.g. <code>Jasmine Rice.png</code>. A photo with the same name as an earlier one replaces it.</p>
				<label class="mdash-drop">
					<input type="file" id="mdash-photos" accept="image/png,image/jpeg,image/webp" multiple>
					<span>Choose photos or drop them here</span>
				</label>
				<div id="mdash-progress" hidden>
					<div class="mdash-meter"><span></span></div>
					<p class="mdash-status"></p>
					<ol class="mdash-log"></ol>
				</div>
				<p class="description">PNG with a transparent background looks best. Big files are shrunk in the browser before upload (this server accepts up to <?php echo esc_html( mdash_bytes( wp_max_upload_size() ) ); ?> per file).</p>
			</section>

			<section class="mdash-card">
				<h2>Today's specials (CSV)</h2>
				<p>A short list with the same columns as the menu: a row with only a name is a heading (Mocktail, Vorspeisen …). It shows wherever <code>[menudash_specials]</code> is, e.g. on the home page.</p>
				<?php $specials = mdash_get_specials(); ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="mdash-upload-row">
					<input type="hidden" name="action" value="menudash_specials">
					<?php wp_nonce_field( 'menudash_specials' ); ?>
					<label class="mdash-drop" data-drop>
						<input type="file" name="csv" class="mdash-file" accept=".csv,text/csv,text/plain">
						<span class="button button-small">Choose CSV</span>
						<span class="mdash-drop-name" data-empty="or drop it here">or drop it here</span>
					</label>
					<div class="mdash-upload-btns">
						<?php submit_button( 'Upload specials', 'primary', 'submit', false ); ?>
						<?php if ( $specials ) : ?>
							<button class="button" name="clear" value="1" formnovalidate onclick="return confirm('Remove the specials from the site?');">Remove</button>
						<?php endif; ?>
					</div>
				</form>
				<?php if ( $specials ) : ?>
					<p class="mdash-now">Live now: <strong><?php echo esc_html( $specials['source']['name'] ); ?></strong>, uploaded <?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $specials['source']['time'] ) ); ?> — <?php echo (int) $specials['dishes']; ?> dishes:
						<?php
						$list = array();
						foreach ( $specials['sections'] as $s ) {
							foreach ( $s['dishes'] as $d ) {
								$list[] = mdash_first( $d['name'], array( 'de', 'en', 'zh' ) )[0];
							}
						}
						echo esc_html( implode( ' · ', $list ) );
						?>
					</p>
				<?php else : ?>
					<p class="mdash-now">No specials on the site.</p>
				<?php endif; ?>
				<?php $sp_files = mdash_specials_files(); ?>
				<?php if ( count( $sp_files ) > ( $specials ? 1 : 0 ) ) : ?>
					<details>
						<summary>Earlier files</summary>
						<?php foreach ( $sp_files as $f ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mdash-restore">
								<input type="hidden" name="action" value="menudash_specials_restore">
								<input type="hidden" name="file" value="<?php echo esc_attr( $f ); ?>">
								<?php wp_nonce_field( 'menudash_specials_restore' ); ?>
								<span><?php echo esc_html( isset( $names[ $f ] ) ? $names[ $f ] : $f ); ?> <small>(<?php echo esc_html( preg_replace( '/^specials-(\d{4}-\d\d-\d\d)-(\d\d)(\d\d)(\d\d).*$/', '$1 $2:$3 UTC', $f ) ); ?>)</small></span>
								<?php if ( $specials && isset( $specials['source']['file'] ) && $specials['source']['file'] === $f ) : ?>
									<em>live</em>
								<?php else : ?>
									<button class="button button-small">Put back</button>
								<?php endif; ?>
							</form>
						<?php endforeach; ?>
					</details>
				<?php endif; ?>
			</section>
		</div>

		<section class="mdash-card mdash-closed-card">
			<h2>Holidays &amp; closed days</h2>
			<p>Enter the first and last closed day; for a single day, enter the same date twice or only the first. The notice ends with "we look forward to welcoming you again from …" the day after; if you open later (e.g. after a closed Monday), enter that day under Open again. The notice shows wherever <code>[menudash_closed]</code> is, from 60 days before until the last day, then disappears by itself.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="menudash_closed">
				<?php wp_nonce_field( 'menudash_closed' ); ?>
				<?php // Enter in a field submits the form's first button: make that Save, not a row's Delete. ?>
				<button type="submit" class="mdash-default-submit" tabindex="-1" aria-hidden="true">Save holidays</button>
				<?php
				$periods = mdash_closed_periods();
				$today   = current_time( 'Y-m-d' );
				// The saved periods, or one empty row to start with; "+ Add holiday" adds more.
				$rows = $periods ? $periods : array( array( 'from' => '', 'to' => '', 'back' => '', 'note' => '' ) );
				?>
				<table class="mdash-closed-table">
					<thead><tr><th>From</th><th>To</th><th>Open again <small>(optional)</small></th><th>Note (optional)</th><th>On the site</th><th><span class="screen-reader-text">Delete</span></th></tr></thead>
					<tbody>
					<?php
					foreach ( $rows as $i => $p ) {
						mdash_closed_row( $i, $p, $today );
					}
					?>
					</tbody>
				</table>
				<template id="mdash-closed-new"><?php mdash_closed_row( '__i__', array( 'from' => '', 'to' => '', 'back' => '', 'note' => '' ), $today ); ?></template>
				<p class="mdash-closed-actions">
					<button type="button" class="button" id="mdash-closed-add" data-max="<?php echo (int) MDASH_CLOSED_MAX; ?>"<?php echo count( $rows ) >= MDASH_CLOSED_MAX ? ' hidden' : ''; ?>>+ Add holiday</button>
					<?php submit_button( 'Save holidays', 'primary', 'save_closed', false ); ?>
				</p>
			</form>
		</section>

		<?php
		$saved_hours = mdash_hours();
		$hours_line  = array();
		foreach ( $saved_hours ? mdash_hours_rows( $saved_hours, 'de' ) : array() as $r ) {
			$hours_line[] = $r['label'] . ' ' . ( $r['slots'] ? implode( ' + ', array_map( function ( $x ) { return $x[0] . '–' . $x[1]; }, $r['slots'] ) ) : 'geschlossen' );
		}
		?>
		<details class="mdash-card mdash-hours-card"<?php echo $hours_line ? '' : ' open'; ?>>
			<summary><h2>Opening hours</h2> <span class="mdash-muted"><?php echo esc_html( $hours_line ? implode( ' · ', $hours_line ) : 'Not set yet' ); ?></span></summary>
			<p>Tick <em>Closed</em> for a day off. Add a second time only for a break (lunch and dinner). The hours show wherever <code>[menudash_hours]</code> is; days with the same hours are joined ("Dienstag – Freitag").</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="menudash_hours">
				<?php wp_nonce_field( 'menudash_hours' ); ?>
				<div class="mdash-hours-layout">
				<div>
				<table class="mdash-hours-table">
					<thead><tr><th>Day</th><th>Closed</th><th>Open</th><th>Close</th><th></th><th>Open again <small>(optional)</small></th><th>Close</th></tr></thead>
					<tbody>
					<?php
					$saved = mdash_hours();
					foreach ( mdash_day_names( 'de' ) as $d => $name ) :
						$slots  = isset( $saved[ $d ] ) ? $saved[ $d ] : array();
						$closed = $saved && ! $slots;
						$v      = function ( $n, $k ) use ( $slots ) { return isset( $slots[ $n ][ $k ] ) ? $slots[ $n ][ $k ] : ''; };
						?>
						<tr class="<?php echo $closed ? 'is-closed' : ''; ?>">
							<th scope="row"><?php echo esc_html( $name ); ?></th>
							<td><input type="checkbox" name="hours[<?php echo (int) $d; ?>][closed]" value="1" <?php checked( $closed ); ?> aria-label="<?php echo esc_attr( "$name closed" ); ?>"></td>
							<td><input type="time" name="hours[<?php echo (int) $d; ?>][from1]" value="<?php echo esc_attr( $v( 0, 0 ) ); ?>" step="900"></td>
							<td><input type="time" name="hours[<?php echo (int) $d; ?>][to1]" value="<?php echo esc_attr( $v( 0, 1 ) ); ?>" step="900"></td>
							<td class="mdash-muted">+</td>
							<td><input type="time" name="hours[<?php echo (int) $d; ?>][from2]" value="<?php echo esc_attr( $v( 1, 0 ) ); ?>" step="900"></td>
							<td><input type="time" name="hours[<?php echo (int) $d; ?>][to2]" value="<?php echo esc_attr( $v( 1, 1 ) ); ?>" step="900"></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><?php submit_button( 'Save opening hours', 'primary', 'save_hours', false ); ?></p>
				</div>
				<?php if ( $saved && array_filter( $saved ) ) : ?>
					<aside class="mdash-hours-preview">
						<h3>On the site</h3>
						<?php echo wp_kses( mdash_hours_shortcode( array() ), array( 'figure' => array( 'class' => true ), 'table' => array(), 'tbody' => array(), 'tr' => array( 'class' => true ), 'td' => array(), 'br' => array() ) ); ?>
					</aside>
				<?php endif; ?>
				</div>
			</form>
		</details>


		<section class="mdash-card mdash-icons-card">
			<h2>3. Diet icons</h2>
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

		<?php
		$specials = mdash_get_specials();
		$sp_match = $specials ? mdash_match_photos( $specials, $photos ) : array( 'dish' => array() );
		// A photo that only a special uses is not "unused".
		$unused   = array_diff( $match['unmatched'], array_values( $sp_match['dish'] ) );
		?>
		<?php if ( $menu || $specials ) : ?>
			<section class="mdash-card mdash-check">
				<h2>4. Check</h2>
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

				<?php if ( $specials ) : ?>
					<h3>Today's specials <small><?php echo (int) count( $sp_match['dish'] ); ?> of <?php echo (int) $specials['dishes']; ?> with a photo</small></h3>
					<?php mdash_admin_dish_table( $specials, $photos, $sp_match ); ?>
				<?php endif; ?>

				<?php if ( $menu ) : ?>
					<h3>Menu <small><?php echo (int) $with; ?> of <?php echo (int) $menu['dishes']; ?> dishes with a photo</small></h3>
					<?php mdash_admin_dish_table( $menu, $photos, $match ); ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php echo mdash_sprite(); // phpcs:ignore -- built from our own sprite and cleaned SVG ?>

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
	</div>
	<?php
}
