<?php
/**
 * The guest's list ("Meine Liste"): with it switched on, each dish of the menu gets a + button.
 * A tap puts the dish on a list that stays on the guest's phone only (the browser tab's
 * sessionStorage, gone when the tab is closed). The list shows each dish with its number, its
 * name in the guest's language and the German name under it, so the guest can show it to the
 * staff when ordering, and a dish can be marked with a question for the staff. Nothing is ordered or sent: MenuDash Statistics, when
 * installed, counts only how often each dish was added. The script is in assets/menu.js.
 *
 * Switched on or off on the Menu tab (card "Guest list", beside the menu it belongs to); off by default. Phones only: on screens
 * wider than 781 px menu.css hides the + and the bar.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_LIST_OPTION       = 'menudash_list';
const MDASH_LIST_SHARE_OPTION = 'menudash_list_share'; // '1' (the default) or '0'

add_action( 'admin_post_menudash_list', 'mdash_handle_list' );
// The menu page, kept for a day (mdash_list_menu_url()): looked up again once a page is saved,
// published, unpublished or put in the bin.
add_action( 'transition_post_status', 'mdash_list_forget_menu_page', 10, 3 );

function mdash_list_forget_menu_page( $new_status, $old_status, $post ) {
	if ( 'page' === $post->post_type ) {
		delete_transient( 'menudash_menu_pages' );
	}
}

/** True when the + buttons and the list show on the menu. */
function mdash_list_on() {
	return '1' === get_option( MDASH_LIST_OPTION, '0' );
}

/**
 * True when guests can share their list as a picture (Share → Save as picture / Send with
 * link). On by default; it needs the list.
 */
function mdash_list_share_on() {
	return '0' !== get_option( MDASH_LIST_SHARE_OPTION, '1' );
}

/**
 * The Guest list card's preview of the picture: the menu page opened with ?mdash-list-preview=1 by
 * someone who may edit the menu draws a sample list as a picture in place of the page.
 * Anyone else gets the menu as usual.
 */
function mdash_list_previewing() {
	// The same right as the MenuDash page (mdash_can() in admin.php, which loads in the dashboard only).
	return isset( $_GET['mdash-list-preview'] ) && current_user_can( 'edit_pages' ) && current_user_can( 'upload_files' ); // phpcs:ignore WordPress.Security.NonceVerification -- shows only, changes nothing
}

/**
 * True when the boxes of MenuDash Specials (specials, lunch) show the + buttons: on every page,
 * with or without the menu, as each box can bring the list (mdash_list_markup()).
 */
function mdash_list_here() {
	return mdash_list_on();
}

/**
 * The list itself, once per page, inside the first box with + buttons (menu, specials or lunch):
 * the "Meine Liste" bar (menu.js moves it to the end of the page; a fixed bar can't sit in the
 * box) and the list, which the script fills. The list is kept per browser tab, so it follows the
 * guest from page to page.
 */
function mdash_list_markup() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	ob_start();
	?>
<button type="button" class="mdash-listbar" hidden aria-haspopup="dialog"><?php echo mdash_ui( 'list_title' ); // phpcs:ignore -- fixed strings ?> <span class="mdash-listbar-n">0</span></button>
<?php // A dish taken off with its + on the menu: a few seconds to put it back. ?>
<p class="mdash-undo" hidden role="status"><span class="mdash-undo-t"><?php echo mdash_ui( 'list_gone' ); // phpcs:ignore ?></span> <button type="button" class="mdash-undo-b"><?php echo mdash_ui( 'list_undo' ); // phpcs:ignore ?></button></p>
<dialog class="mdash-dlg mdash-list-dlg" aria-labelledby="mdash-list-title"<?php if ( mdash_list_share_on() ) : ?> data-site="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" data-logo="<?php echo esc_url( mdash_list_logo() ); ?>" data-place="<?php echo esc_attr( wp_json_encode( mdash_list_place() ) ); ?>" data-menu="<?php echo esc_url( mdash_list_menu_url() ); ?>" data-qr="<?php echo esc_url( MENUDASH_URL . 'assets/vendor/qrcode.js?ver=1.4.4' ); ?>" data-pic="<?php echo esc_url( MENUDASH_URL . 'assets/list-picture.js?ver=' . MENUDASH_VERSION ); ?>"<?php echo mdash_list_previewing() ? ' data-preview' : ''; ?> data-msg="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_msg'] ) ); ?>"<?php endif; ?> data-sure="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_sure'] ) ); ?>">
	<form method="dialog"><button class="mdash-close" aria-label="<?php echo esc_attr( mdash_ui_plain( 'close' ) ); ?>">&times;</button></form>
	<?php if ( mdash_list_share_on() ) : ?>
	<?php // While the picture shows: ‹ back to the list, top left (the ✕ stays top right). ?>
	<button type="button" class="mdash-list-back" hidden aria-label="<?php echo esc_attr( mdash_ui_plain( 'list_back' ) ); ?>"></button>
	<?php endif; ?>
	<h2 class="mdash-list-title" id="mdash-list-title"><?php echo mdash_ui( 'list_title' ); // phpcs:ignore ?></h2>
	<p class="mdash-list-tip"><?php echo mdash_ui( 'list_tip' ); // phpcs:ignore ?></p>
	<ol class="mdash-list-items"></ol>
	<p class="mdash-list-empty" hidden><?php echo mdash_ui( 'list_empty' ); // phpcs:ignore ?></p>
	<p class="mdash-list-total" hidden><span><?php echo mdash_ui( 'list_total' ); // phpcs:ignore ?></span> <strong></strong></p>
	<p class="mdash-list-price" hidden><?php echo mdash_ui( 'list_price' ); // phpcs:ignore ?></p>
	<p class="mdash-list-own"><?php echo mdash_ui( 'list_own' ); // phpcs:ignore ?></p>
	<?php if ( mdash_list_share_on() ) : ?>
	<?php // "Share": the list drawn as a picture on the phone (menu.js), shown below in its place. ?>
	<p class="mdash-list-share-row"><button type="button" class="mdash-list-share" hidden><?php echo mdash_ui( 'list_share' ); // phpcs:ignore ?></button></p>
	<?php // The picture; then save it to the phone, or send it with the menu's link (‹ at the top goes back). ?>
	<div class="mdash-list-pic" hidden>
		<img class="mdash-list-pic-img" alt="">
		<p class="mdash-list-pic-hold" hidden><?php echo mdash_ui( 'list_hold' ); // phpcs:ignore ?></p>
		<?php // Side by side: save on the left, share (with the menu's link) on the right. ?>
		<p class="mdash-list-pic-btns"><button type="button" class="mdash-list-save"><?php echo mdash_ui( 'list_save' ); // phpcs:ignore ?></button><button type="button" class="mdash-list-send"><?php echo mdash_ui( 'list_send' ); // phpcs:ignore ?></button></p>
	</div>
	<?php endif; ?>
	<p class="mdash-list-actions"><button type="button" class="mdash-list-clear"><?php echo mdash_ui( 'list_clear' ); // phpcs:ignore ?></button></p>
	<?php // Labels the script puts on each row's buttons, in the three languages. ?>
	<span class="mdash-list-labels" hidden data-more="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_more'] ) ); ?>" data-less="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_less'] ) ); ?>" data-ask="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_ask'] ) ); ?>" data-qword="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_q'] ) ); ?>" data-remove="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_remove'] ) ); ?>"></span>
</dialog>
	<?php
	return ob_get_clean();
}

/**
 * The small logo at the top of the list's picture: the site's logo (Appearance → Site Identity),
 * else its Site Icon. A theme with its own logo hands it over with the menudash_list_logo
 * filter. It must come from this site: a picture from elsewhere can't be drawn into the PNG.
 */
function mdash_list_logo() {
	$id   = (int) get_theme_mod( 'custom_logo' );
	$logo = $id ? wp_get_attachment_image_url( $id, 'medium' ) : get_site_icon_url( 192 );
	return (string) apply_filters( 'menudash_list_logo', $logo ? $logo : '' );
}

/**
 * The menu page, for the QR code on the list's picture and the link sent with it when the list
 * is on a page without the menu (the specials on the home page). On the menu's own page,
 * menu.js takes that page's address. Looked up once a day: by MenuDash Restaurant when it is
 * installed (it clears the day's answer when a page is saved), else here, the same way and
 * under the same name.
 */
function mdash_list_menu_url() {
	if ( function_exists( 'mdash_menu_pages_public' ) ) {
		$ids = mdash_menu_pages_public();
	} else {
		$ids = get_transient( 'menudash_menu_pages' );
		if ( ! is_array( $ids ) ) {
			$ids = array();
			foreach ( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 20, 's' => '[menudash' ) ) as $p ) {
				if ( has_shortcode( $p->post_content, 'menudash' ) ) {
					$ids[] = $p->ID;
				}
			}
			set_transient( 'menudash_menu_pages', $ids, DAY_IN_SECONDS );
		}
	}
	return $ids ? (string) get_permalink( $ids[0] ) : '';
}

/**
 * The menu page for the Guest list card's preview: the first published page with the menu (the
 * dashboard knows them without MenuDash Restaurant), else MenuDash Restaurant's.
 */
function mdash_list_menu_url_any() {
	foreach ( function_exists( 'mdash_menu_pages' ) ? mdash_menu_pages() : array() as $p ) {
		if ( 'publish' === $p->post_status ) {
			return (string) get_permalink( $p );
		}
	}
	return mdash_list_menu_url();
}

/**
 * The foot of the list's picture, like a flyer: the restaurant's name, address and phone from
 * MenuDash Restaurant (Restaurant tab), when it is installed; else the site's name only.
 */
function mdash_list_place() {
	if ( ! function_exists( 'mdash_detail' ) ) {
		return array( 'name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	}
	return array(
		'name'    => mdash_detail( 'name' ),
		'address' => mdash_detail( 'address' ),
		'phone'   => mdash_detail( 'phone' ),
	);
}

/**
 * In a photo view (menu, specials, lunch): "+ Add to my list" under the photo, or "✓ On my list"
 * once the dish is on it. menu.js shows it for a dish with a + and fills in the dish; a tap adds
 * or takes it off, as the + does, and closes the photo.
 */
function mdash_list_photo_button() {
	return '<p class="mdash-dlg-add-row"><button type="button" class="mdash-dlg-add" hidden aria-pressed="false">'
		. '<span class="mdash-dlg-add-off"><span aria-hidden="true">+</span> ' . mdash_ui( 'list_add' ) . '</span>'
		. '<span class="mdash-dlg-add-on"><span aria-hidden="true">✓</span> ' . mdash_ui( 'list_on' ) . '</span></button></p>';
}

function mdash_handle_list() {
	check_admin_referer( 'menudash_list' );
	if ( ! mdash_can() ) {
		wp_die( esc_html__( 'You are not allowed to change the menu.', 'menudash' ), 403 );
	}
	$on    = isset( $_POST['list_on'] ) && '1' === $_POST['list_on']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared only
	$share = isset( $_POST['list_share'] ) && '1' === $_POST['list_share']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared only
	update_option( MDASH_LIST_OPTION, $on ? '1' : '0', true ); // Read on every menu page.
	if ( $on ) { // Unchanged while the list is off: the box is greyed out then and sends nothing.
		update_option( MDASH_LIST_SHARE_OPTION, $share ? '1' : '0', true );
	}
	mdash_purge_caches();
	mdash_back(
		array(
			'ok'      => true,
			'kind'    => __( 'Guest selection', 'menudash' ),
			'message' => $on ? ( mdash_list_share_on() ? __( 'The + buttons are on the menu; guests can share their selection as a picture.', 'menudash' ) : __( 'The + buttons are on the menu; sharing the selection is off.', 'menudash' ) ) : __( 'The + buttons are off.', 'menudash' ),
		)
	);
}

/**
 * Up to three dishes for the Guest list card's example phones, those with a photo first:
 * array( no, name in $lang (else German), photo URL or '' ).
 */
function mdash_list_admin_dishes( $lang ) {
	$menu   = mdash_get_menu();
	$photos = mdash_photo_index();
	$match  = mdash_photo_matches( $menu, $photos );
	$with   = array();
	$without = array();
	foreach ( $menu ? $menu['sections'] : array() as $sec ) {
		foreach ( $sec['dishes'] as $d ) {
			$pid  = isset( $match['dish'][ $d['key'] ] ) ? $match['dish'][ $d['key'] ] : '';
			$item = array( $d['no'], '' !== $d['name'][ $lang ] ? $d['name'][ $lang ] : $d['name']['de'], $pid && isset( $photos[ $pid ] ) ? mdash_photo_url( $photos[ $pid ], 400 ) : '' );
			if ( $item[2] ) {
				$with[] = $item;
			} else {
				$without[] = $item;
			}
			if ( count( $with ) >= 3 ) {
				break 2;
			}
		}
	}
	$out = array_slice( array_merge( $with, $without ), 0, 3 );
	return $out ? $out : array( array( '12', 'Nasi Lemak', '' ) );
}

/** A dish's photo in the example phones, or an empty round space. */
function mdash_list_admin_thumb( $d ) {
	return $d[2] ? '<img src="' . esc_url( $d[2] ) . '" alt="" width="40" height="40">' : '<i></i>';
}

/**
 * Menu tab, card "Guest list": how guests use it as three phones of the same size, in the
 * order guests go (the menu with +, their list, the shared picture: the real preview, drawn by
 * the menu page), then the switches under them. The first two phones are drawn here in the
 * menu's colours with dishes of the menu: no pictures to load or ship.
 */
function mdash_list_admin_card() {
	$menu_url = mdash_list_menu_url_any();
	$preview  = '';
	// In the owner's language (the dashboard's), as a guest reading the menu in it would see it.
	$lang = substr( get_user_locale(), 0, 2 );
	$lang = in_array( $lang, array( 'de', 'en', 'zh' ), true ) ? $lang : 'de';
	if ( mdash_list_on() && mdash_list_share_on() && $menu_url ) {
		$preview = add_query_arg(
			array(
				'mdash-list-preview' => '1',
				'lang'               => $lang,
			),
			$menu_url
		);
	}
	$dishes = mdash_list_admin_dishes( $lang );
	$colors = mdash_colors();
	$words  = mdash_strings();
	$style  = sprintf( '--p-bg: %s; --p-accent: %s; --p-on: %s;', $colors['bg'], $colors['accent'], mdash_on_accent( $colors['accent'] ) );
	?>
	<section class="mdash-card mdash-list-card" id="mdash-guest-list">
		<h2><?php esc_html_e( 'Guest selection', 'menudash' ); ?></h2>
		<?php /* translators: %s: what guests see as its name on the menu, e.g. "Meine Auswahl". */ ?>
		<p><?php echo esc_html( sprintf( __( 'Guests tap + beside a dish to add it to their selection (on the menu: "%s"), then show it to your staff when they order. It shows each dish with its number, in the language the guest reads the menu in and in German for your staff, and guests can mark a dish they have a question about. Nothing is ordered online and nothing is sent: the selection stays on the guest\'s phone until they close the page.', 'menudash' ), $words['list_title'][ $lang ] ) ); ?></p>
		<?php if ( defined( 'MDASH_STATS_VERSION' ) ) : ?>
			<p class="mdash-list-stats"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span> <a href="<?php echo esc_url( admin_url( 'admin.php?page=menudash-stats' ) ); ?>"><?php esc_html_e( 'Which dishes guests choose: Statistics', 'menudash' ); ?></a></p>
		<?php endif; ?>

		<h3 class="mdash-list-story-h"><?php esc_html_e( 'How guests use it', 'menudash' ); ?></h3>
		<ol class="mdash-list-story" style="<?php echo esc_attr( $style ); ?>">
			<li>
				<div class="mdash-story-phone" aria-hidden="true">
					<span class="s-head"><?php echo esc_html_x( 'Menu', 'restaurant menu (tab)', 'menudash' ); ?></span>
					<?php foreach ( $dishes as $i => $d ) : ?>
						<span class="s-dish"><b><?php echo esc_html( $d[0] ); ?></b><span class="s-name"><?php echo esc_html( $d[1] ); ?></span><span class="s-pic"><?php echo mdash_list_admin_thumb( $d ); // phpcs:ignore -- escaped there ?><span class="s-add<?php echo $i ? '' : ' is-on'; ?>"><?php echo $i ? '+' : ''; ?></span></span></span>
					<?php endforeach; ?>
					<span class="s-bar"><?php echo esc_html( $words['list_title'][ $lang ] ); ?> <em>1</em></span>
				</div>
				<p><?php esc_html_e( 'Tap + beside a dish.', 'menudash' ); ?></p>
			</li>
			<li>
				<div class="mdash-story-phone" aria-hidden="true">
					<span class="s-title"><?php echo esc_html( $words['list_title'][ $lang ] ); ?></span>
					<?php foreach ( array_slice( $dishes, 0, 2 ) as $i => $d ) : ?>
						<span class="s-row"><b><?php echo esc_html( $d[0] ); ?></b><span class="s-name"><?php echo esc_html( $d[1] ); ?></span><?php echo mdash_list_admin_thumb( $d ); // phpcs:ignore -- escaped there ?><span class="s-x">×</span></span>
						<span class="s-ctl"><span>−</span> <?php echo $i ? 1 : 2; ?> <span>+</span></span>
					<?php endforeach; ?>
					<?php if ( mdash_list_share_on() ) : ?>
						<span class="s-share"><?php echo esc_html( $words['list_share'][ $lang ] ); ?></span>
					<?php endif; ?>
				</div>
				<p><?php esc_html_e( 'Show the selection to your staff when ordering.', 'menudash' ); ?></p>
			</li>
			<?php if ( $preview ) : ?>
			<li class="mdash-list-preview">
				<div class="mdash-story-phone mdash-story-shot">
					<iframe src="<?php echo esc_url( $preview ); ?>" title="<?php esc_attr_e( 'Example of a shared selection', 'menudash' ); ?>" loading="lazy" width="270" height="480" tabindex="-1"></iframe>
				</div>
				<p><?php esc_html_e( 'Or share it with friends as a picture. This one is made from your menu:', 'menudash' ); ?>
					<span class="mdash-muted"><?php function_exists( 'mdash_detail' ) ? esc_html_e( 'Three dishes of your menu as an example, with your logo, colours, address and the QR code to the menu. It changes with them.', 'menudash' ) : esc_html_e( 'Three dishes of your menu as an example, with your logo (or site name), colours and the QR code to the menu. It changes with them.', 'menudash' ); ?></span>
					<a href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open it large', 'menudash' ); ?></a></p>
			</li>
			<?php endif; ?>
		</ol>

		<form class="mdash-list-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="menudash_list">
			<?php wp_nonce_field( 'menudash_list' ); ?>
			<p><label><input type="checkbox" name="list_on" value="1" <?php checked( mdash_list_on() ); ?>> <strong><?php esc_html_e( 'Show the + buttons and the selection on the menu', 'menudash' ); ?></strong></label>
				<span class="mdash-muted"><?php esc_html_e( 'Only on phones (screens up to 781 pixels wide), where guests read the menu at the table; computers and large tablets show the menu without the + buttons.', 'menudash' ); ?></span></p>
			<p class="mdash-list-share-opt"><label><input type="checkbox" name="list_share" value="1" <?php checked( mdash_list_share_on() ); ?> <?php disabled( ! mdash_list_on() ); ?>> <strong><?php esc_html_e( 'Guests can share their selection as a picture', 'menudash' ); ?></strong></label>
				<span class="mdash-muted"><?php
				echo esc_html(
					sprintf(
						function_exists( 'mdash_detail' )
							/* translators: %s: the button's name on the menu, e.g. "Als Bild teilen". */
							? __( 'A "%s" button under the selection makes a picture of it like a small flyer: your logo, the dishes (without prices), your address and phone (from MenuDash Restaurant), and a QR code to the menu. Guests save it to their photos or send it to friends with the menu link. It is made on the guest\'s phone; nothing is sent to you.', 'menudash' )
							/* translators: %s: the button's name on the menu, e.g. "Als Bild teilen". */
							: __( 'A "%s" button under the selection makes a picture of it like a small flyer: your logo, the dishes (without prices), your site\'s name (with MenuDash Restaurant also your address and phone), and a QR code to the menu. Guests save it to their photos or send it to friends with the menu link. It is made on the guest\'s phone; nothing is sent to you.', 'menudash' ),
						$words['list_share'][ $lang ]
					)
				);
				?><?php if ( defined( 'MDASH_STATS_VERSION' ) && version_compare( MDASH_STATS_VERSION, '1.8.0', '>=' ) ) : // Statistics counts shared pictures from 1.8.0 on. ?> <?php esc_html_e( 'Visits from a shared picture show as "Shared selection" in MenuDash Statistics.', 'menudash' ); ?><?php endif; ?></span></p>
			<p><?php submit_button( __( 'Save', 'menudash' ), 'primary', 'save_list', false ); ?></p>
		</form>
	</section>
	<?php
}
