<?php
/**
 * Dashboard -> MenuDash: upload the CSV, upload photos, see what the menu page will show.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_CAP = 'edit_pages';

add_action( 'admin_menu', 'mdash_admin_menu' );
add_action( 'admin_post_menudash_csv', 'mdash_handle_csv' );
add_action( 'admin_post_menudash_restore', 'mdash_handle_restore' );
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
		'message'  => isset( $result['message'] ) ? $result['message'] : null, // Set for icon uploads.
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
					<p><strong>Diet icons:</strong> <?php echo esc_html( trim( $report['message'] . ' ' . $report['error'] ) ); ?></p>
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
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="menudash_csv">
					<?php wp_nonce_field( 'menudash_csv' ); ?>
					<input type="file" name="csv" accept=".csv,text/csv,text/plain" required>
					<?php submit_button( 'Upload menu', 'primary', 'submit', false ); ?>
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
		</div>

		<section class="mdash-card mdash-icons-card">
			<h2>3. Diet icons</h2>
			<p>Use your own icons for the diet marks: an SVG, or a square PNG with a transparent background. "Not spicy" is the Spicy icon, crossed out.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="menudash_icons">
				<?php wp_nonce_field( 'menudash_icons' ); ?>
				<div class="mdash-icon-grid">
					<?php $custom = mdash_icon_index(); ?>
					<?php foreach ( mdash_icon_keys() as $key => $label ) : ?>
						<div class="mdash-icon-item">
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

		<?php if ( $menu ) : ?>
			<section class="mdash-card mdash-check">
				<h2>4. Check</h2>
				<p><strong><?php echo (int) $with; ?> of <?php echo (int) $menu['dishes']; ?> dishes have a photo.</strong> <?php echo (int) count( $photos ); ?> photos uploaded.</p>

				<?php if ( $match['unmatched'] || $match['spare'] ) : ?>
					<h3>Photos not shown</h3>
					<p>Rename these to the dish number and upload again, or delete them.</p>
					<ul class="mdash-unused">
						<?php foreach ( array_merge( $match['unmatched'], array_keys( $match['spare'] ) ) as $id ) : ?>
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

				<h3>All dishes</h3>
				<table class="widefat striped mdash-table">
					<thead><tr><th>Nr.</th><th>Photo</th><th>Name</th><th>中文</th><th>Marks</th><th class="num">Price</th></tr></thead>
					<?php foreach ( $menu['sections'] as $s ) : ?>
						<tbody>
							<tr class="mdash-sec"><th colspan="6"><?php echo esc_html( implode( ' · ', array_unique( array_filter( $s['name'] ) ) ) ); ?></th></tr>
							<?php foreach ( $s['dishes'] as $d ) : ?>
								<?php $pid = isset( $match['dish'][ $d['key'] ] ) ? $match['dish'][ $d['key'] ] : null; ?>
								<tr>
									<td><?php echo esc_html( $d['no'] ); ?></td>
									<td class="mdash-thumb">
										<?php if ( $pid ) : ?>
											<img src="<?php echo esc_url( mdash_photo_url( $photos[ $pid ], 400 ) ); ?>" alt="" width="44" height="44" title="<?php echo esc_attr( $photos[ $pid ]['name'] ); ?>">
										<?php else : ?>
											<span class="mdash-missing">no photo</span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $d['name']['en'] ); ?><?php if ( $d['name']['de'] && $d['name']['de'] !== $d['name']['en'] ) : ?><br><small><?php echo esc_html( $d['name']['de'] ); ?></small><?php endif; ?></td>
									<td lang="zh-Hant"><?php echo esc_html( $d['name']['zh'] ); ?></td>
									<td class="mdash-marks"><?php foreach ( $d['flags'] as $f ) : ?><span title="<?php echo esc_attr( mdash_ui_plain( $f ) ); ?>"><?php echo mdash_icon( $f ); // phpcs:ignore ?></span><?php endforeach; ?></td>
									<td class="num"><?php echo esc_html( $d['price'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					<?php endforeach; ?>
				</table>
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
