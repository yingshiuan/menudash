<?php
/**
 * QR code and table cards (MenuDash → QR code): a QR code to the menu page, as a printable
 * sheet of four A6 table cards or one A4 poster (logo, a short text, the code, the web
 * address and, when filled in, a message and the Wi-Fi name and password) or as a PNG / SVG file. The codes are drawn in the
 * browser (assets/vendor/qrcode.js), so no outside service sees the site.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_QR_OPTION = 'menudash_qr';

add_filter( 'menudash_admin_tabs', 'mdash_qr_admin_tab_add', 90 );
add_action( 'menudash_admin_tab', 'mdash_qr_admin_tab' );
add_action( 'admin_post_menudash_qr', 'mdash_handle_qr' );
add_action( 'admin_post_menudash_qr_print', 'mdash_qr_print' );
add_action( 'admin_enqueue_scripts', 'mdash_qr_admin_assets' );

/** Saved settings, with defaults for anything not saved yet. */
function mdash_qr_settings() {
	$saved = get_option( MDASH_QR_OPTION );
	$saved = is_array( $saved ) ? $saved : array();
	// The heading before it came in the card's languages: now the built-in one.
	if ( isset( $saved['text'] ) && 'Speisekarte · Menu · 菜單' === $saved['text'] ) {
		$saved['text'] = '';
	}
	return array_merge(
		array(
			'url'       => '',
			'text'      => '', // Empty: "Scan for our menu" in the card's languages.
			'wifi_name' => '',
			'wifi_pass' => '',
			'note'      => '',
			'size'      => 'a6',
		),
		array_map( 'strval', array_intersect_key( $saved, array_flip( array( 'url', 'text', 'wifi_name', 'wifi_pass', 'note', 'size' ) ) ) ),
		// The built-in tip about languages and filters; on until switched off.
		array( 'tip' => ! isset( $saved['tip'] ) || ! empty( $saved['tip'] ) ),
		// Languages of the card's own words (heading, tip, Wi-Fi); all menu languages until chosen.
		array( 'langs' => isset( $saved['langs'] ) && is_array( $saved['langs'] ) && $saved['langs'] ? array_values( array_intersect( array_keys( mdash_lang_buttons() ), $saved['langs'] ) ) : array_keys( mdash_lang_buttons() ) )
	);
}

/** The address the code opens: the owner's own link, else the first published menu page, else the home page. */
function mdash_qr_url( $s = null ) {
	$s = $s ? $s : mdash_qr_settings();
	if ( '' !== $s['url'] ) {
		return $s['url'];
	}
	return mdash_qr_default_url();
}

function mdash_qr_default_url() {
	foreach ( function_exists( 'mdash_menu_pages' ) ? mdash_menu_pages() : array() as $p ) {
		if ( 'publish' === $p->post_status ) {
			return get_permalink( $p );
		}
	}
	return home_url( '/' );
}

/** "example.com/menu" for the card: no https://, no www., no slash at the end. */
function mdash_qr_short_url( $url ) {
	return untrailingslashit( preg_replace( '#^https?://(www\.)?#i', '', $url ) );
}

/** The card's own words and how their languages are joined: one line each, or " / ". */
function mdash_qr_word_keys() {
	return array( 'qr_title' => "\n", 'qr_tip' => "\n", 'wifi' => ' / ', 'password' => ' / ' );
}

/**
 * One of the card's own words in the card's languages, menu order ("WLAN / Wi-Fi / 無線網路").
 * Words that are the same in two languages print once.
 */
function mdash_qr_word( $key, $langs ) {
	$s = mdash_strings();
	$w = array();
	foreach ( array_keys( mdash_lang_buttons() ) as $l ) {
		if ( in_array( $l, $langs, true ) && isset( $s[ $key ][ $l ] ) ) {
			$w[] = $s[ $key ][ $l ];
		}
	}
	return implode( mdash_qr_word_keys()[ $key ], array_unique( $w ) );
}

/**
 * The text of a Wi-Fi QR code (the format phone cameras understand: "Join network"):
 * WIFI:T:WPA;S:name;P:password;; with \ ; , : " escaped. Without a password: an open network.
 */
function mdash_qr_wifi_code( $name, $pass ) {
	$esc = function ( $v ) { return preg_replace( '/([\\\\;,:"])/', '\\\\$1', $v ); };
	return '' === $pass ? 'WIFI:T:nopass;S:' . $esc( $name ) . ';;' : 'WIFI:T:WPA;S:' . $esc( $name ) . ';P:' . $esc( $pass ) . ';;';
}

/** The logo for the card: the Site Logo, else the Site Icon; '' when there is neither. */
function mdash_qr_logo_url() {
	$id = (int) get_theme_mod( 'custom_logo' );
	$id = $id && wp_attachment_is_image( $id ) ? $id : (int) get_option( 'site_icon' );
	$url = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
	return $url ? $url : '';
}

function mdash_qr_admin_tab_add( $tabs ) {
	return $tabs + array( 'menudash-qr' => 'QR code' );
}

function mdash_qr_admin_assets( $hook ) {
	if ( false === strpos( (string) $hook, 'menudash-qr' ) ) {
		return;
	}
	wp_enqueue_script( 'menudash-qrcode', MENUDASH_URL . 'assets/vendor/qrcode.js', array(), '1.4.4', true );
	wp_enqueue_script( 'menudash-qr', MENUDASH_URL . 'assets/qr.js', array( 'menudash-qrcode' ), MENUDASH_VERSION, true );
}

function mdash_handle_qr() {
	mdash_back_to( 'menudash-qr' );
	check_admin_referer( 'menudash_qr' );
	if ( ! mdash_can() ) {
		wp_die( 'You are not allowed to change the menu.', 403 );
	}
	$in       = isset( $_POST['qr'] ) && is_array( $_POST['qr'] ) ? wp_unslash( $_POST['qr'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned below
	$get      = function ( $k, $max ) use ( $in ) {
		return isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? mb_substr( sanitize_text_field( (string) $in[ $k ] ), 0, $max ) : '';
	};
	$problems = array();
	$url      = $get( 'url', 500 );
	if ( '' !== $url ) {
		$url = esc_url_raw( preg_match( '#^https?://#i', $url ) ? $url : 'https://' . $url, array( 'http', 'https' ) );
		if ( '' === $url ) {
			$problems[] = 'The link is not a web address; the menu page is used.';
		}
	}
	$s = array(
		'url'       => $url,
		'text'      => $get( 'text', 80 ),
		'tip'       => ! empty( $in['tip'] ),
		'wifi_name' => $get( 'wifi_name', 64 ),
		'wifi_pass' => $get( 'wifi_pass', 64 ),
		// A few short lines, e.g. one per language; at most 4 so the card never overflows.
		'note'      => implode( "\n", array_slice( array_filter( array_map( 'trim', explode( "\n", isset( $in['note'] ) && is_scalar( $in['note'] ) ? mb_substr( sanitize_textarea_field( (string) $in['note'] ), 0, 400 ) : '' ) ), 'strlen' ), 0, 4 ) ),
		'size'      => isset( $in['size'] ) && 'a4' === $in['size'] ? 'a4' : 'a6',
		'langs'     => isset( $in['langs'] ) && is_array( $in['langs'] ) ? array_values( array_intersect( array_keys( mdash_lang_buttons() ), array_map( 'strval', $in['langs'] ) ) ) : array(),
	);
	if ( ! $s['langs'] ) {
		$s['langs']  = array_keys( mdash_lang_buttons() );
		$problems[] = 'Choose at least one language; all are used.';
	}
	update_option( MDASH_QR_OPTION, $s, false );
	if ( isset( $_POST['do'] ) && 'print' === $_POST['do'] ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked above
		// Not wp_nonce_url(): it writes &amp; for HTML, which breaks a redirect.
		wp_safe_redirect( add_query_arg( array( 'action' => 'menudash_qr_print', '_wpnonce' => wp_create_nonce( 'menudash_qr_print' ) ), admin_url( 'admin-post.php' ) ) );
		exit;
	}
	mdash_back( array( 'ok' => ! $problems, 'kind' => 'QR code', 'message' => 'Saved.', 'error' => implode( ' ', $problems ) ) );
}

/** One table card. The QR code is drawn into .mdash-qr-code by assets/qr.js. */
function mdash_qr_card( $s ) {
	$url   = mdash_qr_url( $s );
	$logo  = mdash_qr_logo_url();
	$word  = function ( $k ) use ( $s ) { return mdash_qr_word( $k, $s['langs'] ); };
	$name  = wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES );
	ob_start();
	?>
	<div class="mdash-qr-card" data-words="<?php echo esc_attr( wp_json_encode( array_intersect_key( mdash_strings(), mdash_qr_word_keys() ) ) ); ?>">
		<div class="mdash-qr-top">
			<?php if ( $logo ) : ?>
				<img class="mdash-qr-logo" src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $name ); ?>">
			<?php else : ?>
				<span class="mdash-qr-name"><?php echo esc_html( $name ); ?></span>
			<?php endif; ?>
		</div>
		<p class="mdash-qr-text" data-qr-show="text" data-qr-own="<?php echo esc_attr( $s['text'] ); ?>"><?php echo esc_html( '' !== $s['text'] ? $s['text'] : $word( 'qr_title' ) ); ?></p>
		<div class="mdash-qr-code" data-url="<?php echo esc_attr( $url ); ?>" role="img" aria-label="<?php echo esc_attr( 'QR code: ' . $url ); ?>"></div>
		<p class="mdash-qr-url" data-qr-show="url"><?php echo esc_html( mdash_qr_short_url( $url ) ); ?></p>
		<p class="mdash-qr-tip" data-qr-word="qr_tip"<?php echo $s['tip'] ? '' : ' hidden'; ?>><?php echo esc_html( $word( 'qr_tip' ) ); ?></p>
		<p class="mdash-qr-note" data-qr-show="note"<?php echo '' === $s['note'] ? ' hidden' : ''; ?>><?php echo esc_html( $s['note'] ); ?></p>
		<span class="mdash-qr-gap"></span>
		<div class="mdash-qr-wifi" data-qr-show="wifi"<?php echo '' === $s['wifi_name'] ? ' hidden' : ''; ?>>
			<div class="mdash-qr-wcode" data-url="<?php echo esc_attr( '' !== $s['wifi_name'] ? mdash_qr_wifi_code( $s['wifi_name'], $s['wifi_pass'] ) : '' ); ?>" role="img" aria-label="<?php echo esc_attr( 'Wi-Fi QR code' ); ?>"></div>
			<p>
				<span><span data-qr-word="wifi"><?php echo esc_html( $word( 'wifi' ) ); ?></span>: <strong data-qr-show="wifi_name"><?php echo esc_html( $s['wifi_name'] ); ?></strong></span>
				<span data-qr-show="wifi_pass_line"<?php echo '' === $s['wifi_pass'] ? ' hidden' : ''; ?>><span data-qr-word="password"><?php echo esc_html( $word( 'password' ) ); ?></span>: <strong data-qr-show="wifi_pass"><?php echo esc_html( $s['wifi_pass'] ); ?></strong></span>
			</p>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/** The card's look, shared by the preview on the tab and the print sheet. */
function mdash_qr_card_css() {
	$c = mdash_colors();
	return '
@font-face { font-family: "MenuDash Display"; src: url("' . esc_url( MENUDASH_URL . 'assets/fonts/AbrilFatface-Regular.woff2' ) . '") format("woff2"); }
@font-face { font-family: "MenuDash Sans"; src: url("' . esc_url( MENUDASH_URL . 'assets/fonts/DMSans-Variable.woff2' ) . '") format("woff2"); font-weight: 100 900; }
/* All sizes in --u: 1mm for the A6 card, 2mm for the A4 poster (exactly twice as big). A full
   card (three languages, a message, Wi-Fi) shrinks its text and codes a little with --k
   (assets/qr.js), instead of cutting anything off. */
.mdash-qr-card { --u: 1mm; --k: 1; box-sizing: border-box; width: calc(var(--u) * 105); height: calc(var(--u) * 148); padding: calc(var(--u) * 10) calc(var(--u) * 10) calc(var(--u) * 9); display: flex; flex-direction: column; align-items: center; text-align: center; background: #fff; color: #222; font-family: "MenuDash Sans", Arial, sans-serif; overflow: hidden; }
.mdash-qr-card [hidden] { display: none !important; }
.mdash-qr-card p { margin-left: 0; margin-right: 0; }
.mdash-qr-top { flex: 0 0 auto; height: calc(var(--u) * 18 * var(--k)); display: flex; align-items: center; justify-content: center; }
.mdash-qr-logo { max-width: calc(var(--u) * 60); max-height: calc(var(--u) * 18 * var(--k)); width: auto; height: auto; }
.mdash-qr-name { font-family: "MenuDash Display", Georgia, serif; font-size: calc(var(--u) * 9 * var(--k)); line-height: 1; color: ' . esc_attr( $c['accent'] ) . '; }
.mdash-qr-text { margin: calc(var(--u) * 4 * var(--k)) 0 0; font-size: calc(var(--u) * 4.4 * var(--k)); font-weight: 700; line-height: 1.22; color: ' . esc_attr( $c['accent'] ) . '; white-space: pre-line; }
.mdash-qr-code { flex: 0 0 auto; margin: calc(var(--u) * 4 * var(--k)) 0 0; width: calc(var(--u) * 54 * var(--k)); height: calc(var(--u) * 54 * var(--k)); }
.mdash-qr-code svg, .mdash-qr-wcode svg { display: block; width: 100%; height: 100%; }
.mdash-qr-url { margin: calc(var(--u) * 2.5 * var(--k)) 0 0; font-size: calc(var(--u) * 3.5 * var(--k)); font-weight: 600; letter-spacing: .02em; word-break: break-all; }
.mdash-qr-tip, .mdash-qr-note { margin: calc(var(--u) * 3 * var(--k)) 0 0; font-size: calc(var(--u) * 3.1 * var(--k)); line-height: 1.35; color: #555; white-space: pre-line; }
.mdash-qr-wifi { margin: 0; padding-top: calc(var(--u) * 2.5 * var(--k)); border-top: calc(var(--u) * .3) solid #ddd; width: 100%; display: flex; align-items: center; justify-content: center; gap: calc(var(--u) * 3.5); text-align: left; }
.mdash-qr-gap { flex: 1 0 calc(var(--u) * 3); }
.mdash-qr-wcode { flex: 0 0 auto; width: calc(var(--u) * 17 * var(--k)); height: calc(var(--u) * 17 * var(--k)); }
.mdash-qr-wifi p { margin: 0; font-size: calc(var(--u) * 3.3 * var(--k)); line-height: 1.4; display: flex; flex-direction: column; }
.mdash-qr-a4 .mdash-qr-card { --u: 2mm; }
';
}

/** The tab: settings on the left, the card on the right. */
function mdash_qr_admin_tab( $page ) {
	if ( 'menudash-qr' !== $page ) {
		return;
	}
	$s = mdash_qr_settings();
	?>
	<style><?php echo mdash_qr_card_css(); // phpcs:ignore -- built from fixed CSS and checked colours ?></style>
	<section class="mdash-card mdash-qr-admin">
		<h2>QR code and table cards</h2>
		<p>A QR code that opens your menu on the guest's phone. Print it as table cards (four A6 cards on an A4 sheet, cut along the grey lines), or download it for a flyer, a sticker or the window.</p>
		<div class="mdash-qr-layout">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="mdash-qr-form">
				<input type="hidden" name="action" value="menudash_qr">
				<?php wp_nonce_field( 'menudash_qr' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="mdash-qr-url">Link</label></th><td>
						<input id="mdash-qr-url" name="qr[url]" type="text" class="large-text" maxlength="500" value="<?php echo esc_attr( $s['url'] ); ?>" placeholder="<?php echo esc_attr( mdash_qr_default_url() ); ?>" data-default="<?php echo esc_attr( mdash_qr_default_url() ); ?>">
						<p class="description">Empty: your menu page. Printed cards keep working as long as this address does, so change it only when you have to.</p>
					</td></tr>
					<tr><th scope="row"><label for="mdash-qr-text">Own heading</label></th><td>
						<input id="mdash-qr-text" name="qr[text]" type="text" class="regular-text" maxlength="80" value="<?php echo esc_attr( $s['text'] ); ?>" placeholder="<?php echo esc_attr( mdash_strings()['qr_title']['en'] ); ?>">
						<p class="description">Optional. Empty: "Speisekarte scannen / Scan for our menu / 掃描查看菜單", in the languages ticked below.</p>
					</td></tr>
					<tr><th scope="row"><label for="mdash-qr-wifi-name">Wi-Fi name</label></th><td>
						<input id="mdash-qr-wifi-name" name="qr[wifi_name]" type="text" class="regular-text" maxlength="64" value="<?php echo esc_attr( $s['wifi_name'] ); ?>" autocomplete="off">
					</td></tr>
					<tr><th scope="row"><label for="mdash-qr-wifi-pass">Wi-Fi password</label></th><td>
						<input id="mdash-qr-wifi-pass" name="qr[wifi_pass]" type="text" class="regular-text" maxlength="64" value="<?php echo esc_attr( $s['wifi_pass'] ); ?>" autocomplete="off">
						<p class="description">Both optional: without a Wi-Fi name the card shows no Wi-Fi part. With one, the card also gets a small Wi-Fi code: guests scan it with the phone camera and join without typing (no password: an open network). Only the printed card shows them, never the website.</p>
					</td></tr>
					<tr><th scope="row">Tip</th><td>
						<input type="hidden" name="qr[tip]" value=""><label><input type="checkbox" name="qr[tip]" value="1" <?php checked( $s['tip'] ); ?>> Show the tip "Choose your language and filter by vegetarian, vegan, gluten-free or spicy."</label>
						<p class="description">Under the web address, in the languages ticked below.</p>
					</td></tr>
					<tr><th scope="row"><label for="mdash-qr-note">Own message</label></th><td>
						<textarea id="mdash-qr-note" name="qr[note]" rows="2" class="large-text" maxlength="400" placeholder="e.g. Free Wi-Fi for our guests"><?php echo esc_textarea( $s['note'] ); ?></textarea>
						<p class="description">Optional, under the tip: up to 4 short lines, in any language.</p>
					</td></tr>
					<tr><th scope="row">Languages on the card</th><td>
						<?php foreach ( mdash_lang_buttons() as $code => $label ) : ?>
							<label class="mdash-qr-lang"><input type="checkbox" name="qr[langs][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $s['langs'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
						<p class="description">The heading, the tip and the Wi-Fi words are printed in each ticked language, in the menu's order. A full card makes its text a little smaller by itself.</p>
					</td></tr>
					<tr><th scope="row">Print size</th><td>
						<label><input type="radio" name="qr[size]" value="a6" <?php checked( 'a4' !== $s['size'] ); ?>> 4 table cards (A6) on one A4 sheet</label><br>
						<label><input type="radio" name="qr[size]" value="a4" <?php checked( 'a4' === $s['size'] ); ?>> 1 poster on A4 (the same card, twice as big: door, window, counter)</label>
					</td></tr>
					<tr><th scope="row">Logo</th><td><p class="description">
						<?php if ( mdash_qr_logo_url() ) : ?>
							The site's logo is used.
						<?php else : ?>
							No logo yet, so the card shows the site name.
						<?php endif; ?>
						<?php if ( has_action( 'admin_post_menudash_details' ) ) : ?>
							Change it under <a href="<?php echo esc_url( admin_url( 'admin.php?page=menudash-restaurant' ) ); ?>">Restaurant → Logo and icon</a>.
						<?php else : ?>
							It is WordPress's own Site Logo (Site Editor or Customizer).
						<?php endif; ?>
					</p></td></tr>
				</table>
				<p class="mdash-closed-actions">
					<?php submit_button( 'Save', 'secondary', 'save_qr', false ); ?>
					<button type="submit" class="button button-primary" name="do" value="print" formtarget="_blank">Save and print table cards</button>
				</p>
				<p class="mdash-qr-files">Download the code alone:
					<button type="button" class="button-link" data-qr-download="svg">SVG</button> ·
					<button type="button" class="button-link" data-qr-download="png">PNG</button>
					<span class="description">(SVG for print shops, PNG for anything else)</span>
				</p>
			</form>
			<div class="mdash-qr-preview">
				<h3>Preview</h3>
				<?php echo mdash_qr_card( $s ); // phpcs:ignore -- escaped inside ?>
				<p class="description mdash-qr-test">Test it: scan the card on the screen with your phone.</p>
			</div>
		</div>
	</section>
	<?php
}

/** The print sheet: four cards on A4, opened in a new tab by "Save and print table cards". */
function mdash_qr_print() {
	check_admin_referer( 'menudash_qr_print' );
	if ( ! mdash_can() ) {
		wp_die( 'You are not allowed to change the menu.', 403 );
	}
	$s    = mdash_qr_settings();
	$card = mdash_qr_card( $s );
	$a4   = 'a4' === $s['size'];
	nocache_headers();
	?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( substr( get_locale(), 0, 2 ) ); ?>">
<head>
<meta charset="utf-8">
<title><?php echo esc_html( 'Table cards – ' . wp_specialchars_decode( (string) get_option( 'blogname' ), ENT_QUOTES ) ); ?></title>
<style>
@page { size: A4 portrait; margin: 0; }
html, body { margin: 0; padding: 0; background: #e9e9e9; }
.mdash-qr-sheet { width: 210mm; height: 296mm; margin: 0 auto; display: grid; grid-template-columns: 105mm 105mm; grid-template-rows: 148mm 148mm; background: #fff; position: relative; }
/* Cut lines through the middle of the sheet. */
.mdash-qr-a4.mdash-qr-sheet { grid-template-columns: 210mm; grid-template-rows: 296mm; }
.mdash-qr-sheet:not(.mdash-qr-a4)::before, .mdash-qr-sheet:not(.mdash-qr-a4)::after { content: ""; position: absolute; }
.mdash-qr-sheet::before { left: 105mm; top: 0; bottom: 0; border-left: .2mm dashed #bbb; }
.mdash-qr-sheet::after { top: 148mm; left: 0; right: 0; border-top: .2mm dashed #bbb; }
.mdash-qr-hint { font: 14px/1.5 Arial, sans-serif; text-align: center; padding: 12px; }
@media print { html, body { background: #fff; } .mdash-qr-hint { display: none; } }
<?php echo mdash_qr_card_css(); // phpcs:ignore -- fixed CSS and checked colours ?>
</style>
</head>
<body>
<?php if ( $a4 ) : ?>
<p class="mdash-qr-hint">Print on A4 at 100 % ("Actual size", not "Fit to page").</p>
<?php else : ?>
<p class="mdash-qr-hint">Print on A4 at 100 % ("Actual size", not "Fit to page"), then cut along the grey lines. Tip: thicker paper (160–250 g/m²) stands better.</p>
<?php endif; ?>
<div class="mdash-qr-sheet<?php echo $a4 ? ' mdash-qr-a4' : ''; ?>"><?php echo str_repeat( $card, $a4 ? 1 : 4 ); // phpcs:ignore -- escaped inside ?></div>
<script src="<?php echo esc_url( MENUDASH_URL . 'assets/vendor/qrcode.js?ver=1.4.4' ); ?>"></script>
<script src="<?php echo esc_url( MENUDASH_URL . 'assets/qr.js?ver=' . MENUDASH_VERSION ); ?>"></script>
<script>window.addEventListener("load", function () { setTimeout(function () { window.print(); }, 300); });</script>
</body>
</html>
	<?php
	exit;
}
