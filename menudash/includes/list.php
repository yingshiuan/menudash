<?php
/**
 * The guest's list ("Meine Liste"): with it switched on, each dish of the menu gets a + button.
 * A tap puts the dish on a list that stays on the guest's phone only (the browser tab's
 * sessionStorage, gone when the tab is closed). The list shows each dish with its number, its
 * name in the guest's language and the German name under it, so the guest can show it to the
 * staff when ordering, and a dish can be marked with a question for the staff. Nothing is ordered or sent: MenuDash Statistics, when
 * installed, counts only how often each dish was added. The script is in assets/menu.js.
 *
 * Switched on or off on the Menu tab (card "Guest list"); off by default. Phones only: on screens
 * wider than 781 px menu.css hides the + and the bar.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_LIST_OPTION = 'menudash_list';

add_action( 'admin_post_menudash_list', 'mdash_handle_list' );

/** True when the + buttons and the list show on the menu. */
function mdash_list_on() {
	return '1' === get_option( MDASH_LIST_OPTION, '0' );
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
<dialog class="mdash-dlg mdash-list-dlg" aria-labelledby="mdash-list-title" data-sure="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_sure'] ) ); ?>">
	<form method="dialog"><button class="mdash-close" aria-label="<?php echo esc_attr( mdash_ui_plain( 'close' ) ); ?>">&times;</button></form>
	<h2 class="mdash-list-title" id="mdash-list-title"><?php echo mdash_ui( 'list_title' ); // phpcs:ignore ?></h2>
	<p class="mdash-list-tip"><?php echo mdash_ui( 'list_tip' ); // phpcs:ignore ?></p>
	<ol class="mdash-list-items"></ol>
	<p class="mdash-list-empty" hidden><?php echo mdash_ui( 'list_empty' ); // phpcs:ignore ?></p>
	<p class="mdash-list-total" hidden><span><?php echo mdash_ui( 'list_total' ); // phpcs:ignore ?></span> <strong></strong></p>
	<p class="mdash-list-price" hidden><?php echo mdash_ui( 'list_price' ); // phpcs:ignore ?></p>
	<p class="mdash-list-own"><?php echo mdash_ui( 'list_own' ); // phpcs:ignore ?></p>
	<p class="mdash-list-actions"><button type="button" class="mdash-list-clear"><?php echo mdash_ui( 'list_clear' ); // phpcs:ignore ?></button></p>
	<?php // Labels the script puts on each row's buttons, in the three languages. ?>
	<span class="mdash-list-labels" hidden data-more="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_more'] ) ); ?>" data-less="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_less'] ) ); ?>" data-ask="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_ask'] ) ); ?>" data-qword="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_q'] ) ); ?>" data-remove="<?php echo esc_attr( wp_json_encode( mdash_strings()['list_remove'] ) ); ?>"></span>
</dialog>
	<?php
	return ob_get_clean();
}

/**
 * In a photo view (menu, specials, lunch): "+ Add to my list" under the photo. menu.js shows it
 * for a dish with a + and fills in the dish; a tap adds it and closes the photo.
 */
function mdash_list_photo_button() {
	return '<p class="mdash-dlg-add-row"><button type="button" class="mdash-dlg-add" hidden><span aria-hidden="true">+</span> ' . mdash_ui( 'list_add' ) . '</button></p>';
}

function mdash_handle_list() {
	check_admin_referer( 'menudash_list' );
	if ( ! mdash_can() ) {
		wp_die( esc_html__( 'You are not allowed to change the menu.', 'menudash' ), 403 );
	}
	$on = isset( $_POST['list_on'] ) && '1' === $_POST['list_on']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared only
	update_option( MDASH_LIST_OPTION, $on ? '1' : '0', true ); // Read on every menu page.
	mdash_purge_caches();
	mdash_back(
		array(
			'ok'      => true,
			'kind'    => __( 'Guest list', 'menudash' ),
			'message' => $on ? __( 'The + buttons are on the menu.', 'menudash' ) : __( 'The + buttons are off.', 'menudash' ),
		)
	);
}

/** Menu tab: one switch. */
function mdash_list_admin_card() {
	?>
	<section class="mdash-card mdash-list-card">
		<h2><?php esc_html_e( 'Guest list', 'menudash' ); ?></h2>
		<p><?php esc_html_e( 'Guests tap + beside a dish to put it on their own list, then show the list to your staff when they order. The list shows each dish with its number, in the language the guest reads the menu in and in German for your staff, and guests can mark a dish they have a question about. Nothing is ordered online and nothing is sent: the list stays on the guest\'s phone until they close the page.', 'menudash' ); ?></p>
		<p class="mdash-muted"><?php esc_html_e( 'Only on phones (screens up to 781 pixels wide), where guests read the menu at the table; computers and large tablets show the menu without the + buttons.', 'menudash' ); ?></p>
		<?php if ( defined( 'MDASH_STATS_VERSION' ) ) : ?>
			<p class="mdash-muted"><?php esc_html_e( 'MenuDash Statistics counts how often each dish is added and marked with a question (totals only), on its Statistics tab.', 'menudash' ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="menudash_list">
			<?php wp_nonce_field( 'menudash_list' ); ?>
			<p><label><input type="checkbox" name="list_on" value="1" <?php checked( mdash_list_on() ); ?>> <?php esc_html_e( 'Show the + buttons and the guest list on the menu', 'menudash' ); ?></label></p>
			<p><?php submit_button( __( 'Save', 'menudash' ), 'primary', 'save_list', false ); ?></p>
		</form>
	</section>
	<?php
}
