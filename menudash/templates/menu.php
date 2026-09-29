<?php
/**
 * Menu markup. Variables from mdash_shortcode(): $menu, $photos, $match, $view, $offset.
 *
 * @package menudash
 */

defined( 'ABSPATH' ) || exit;

$chips = array(
	'pick'  => 'pick',
	'veg'   => 'vegetarian',
	'vegan' => 'vegan',
	'gf'    => 'gf',
	'spicy' => 'spicy',
	'mild'  => 'spicy',
);
$marks = array( 'spicy', 'vegan', 'vegetarian', 'gf' ); // Recommended sits in front of the number instead.
$ui    = 'all' === $view ? 'de' : $view;
?>
<div class="menudash" id="menudash-menu" data-jump="menu" data-lang="<?php echo esc_attr( $view ); ?>" data-ui="<?php echo esc_attr( $ui ); ?>" data-offset="<?php echo esc_attr( $offset ); ?>"<?php echo 'auto' !== $offset ? ' style="--mdash-top:' . esc_attr( $offset ) . 'px"' : ''; ?>>
<?php echo mdash_lang_script(); // phpcs:ignore -- fixed markup ?>
<span class="mdash-jump-label" hidden data-nav="<?php echo esc_attr( mdash_ui_plain( 'on_page' ) ); ?>" data-top="<?php echo esc_attr( mdash_ui_plain( 'to_top' ) ); ?>"><?php echo mdash_ui( 'menu_title' ); // phpcs:ignore -- fixed strings ?></span>
<?php
// The diet icons, once per page, drawn below with <use>.
echo mdash_sprite(); // phpcs:ignore -- built from our own sprite and cleaned SVG
?>

<?php if ( ! $menu ) : ?>
	<p class="mdash-empty-menu"><?php echo mdash_ui( 'empty' ); // phpcs:ignore ?></p>
</div>
	<?php
	return;
endif;
?>

<div class="mdash-langs" role="group" aria-label="<?php echo esc_attr( mdash_ui_plain( 'language' ) ); ?>">
	<button type="button" data-set-lang="all" aria-pressed="<?php echo 'all' === $view ? 'true' : 'false'; ?>"><?php echo mdash_ui( 'all' ); // phpcs:ignore ?></button>
	<?php foreach ( mdash_lang_buttons() as $code => $label ) : ?>
		<button type="button" data-set-lang="<?php echo esc_attr( $code ); ?>" lang="<?php echo esc_attr( mdash_html_lang( $code ) ); ?>" aria-pressed="<?php echo $code === $view ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
	<?php endforeach; ?>
</div>

<div class="mdash-bar">
	<div class="mdash-chips" role="group" aria-label="<?php echo esc_attr( mdash_ui_plain( 'filter' ) ); ?>">
		<?php foreach ( $chips as $key => $icon ) : ?>
			<button type="button" class="mdash-chip" data-filter="<?php echo esc_attr( $key ); ?>" aria-pressed="false">
				<span class="mdash-chip-ico<?php echo 'mild' === $key ? ' mdash-not' : ''; ?>"><?php echo mdash_icon( $icon ); // phpcs:ignore ?></span>
				<?php echo mdash_ui( $key ); // phpcs:ignore ?>
			</button>
		<?php endforeach; ?>
	</div>
	<nav class="mdash-cats" aria-label="<?php echo esc_attr( mdash_ui_plain( 'categories' ) ); ?>">
		<?php
		foreach ( $menu['sections'] as $sec ) :
			$titles = array();
			foreach ( MDASH_LANGS as $l ) {
				$titles[ $l ] = mdash_split_note( $sec['name'][ $l ] )[0];
			}
			?>
			<a href="#mdash-<?php echo esc_attr( $sec['id'] ); ?>" data-sec="<?php echo esc_attr( $sec['id'] ); ?>">
				<?php
				// Category tabs stay short: one language, the interface one.
				foreach ( mdash_chains() as $l => $chain ) {
					list( $t, $tl ) = mdash_first( $titles, $chain );
					printf( '<span class="mdash-u" data-l="%s" lang="%s">%s</span>', esc_attr( $l ), esc_attr( mdash_html_lang( $tl ) ), esc_html( $t ) );
				}
				?>
			</a>
		<?php endforeach; ?>
	</nav>
</div>

<div class="mdash-sections">
<?php
foreach ( $menu['sections'] as $sec ) {
	include MENUDASH_DIR . 'templates/section.php';
}
?>
</div>

<div class="mdash-nomatch" hidden>
	<p><?php echo mdash_ui( 'none' ); // phpcs:ignore ?></p>
	<button type="button" class="mdash-reset"><?php echo mdash_ui( 'reset' ); // phpcs:ignore ?></button>
</div>

<footer class="mdash-foot">
	<?php
	$s     = mdash_strings();
	$phone = function_exists( 'mdash_detail' ) ? trim( (string) mdash_detail( 'phone' ) ) : ''; // Restaurant tab, if the add-on is on.
	$call  = '' !== $phone && '' !== mdash_tel( $phone ) ? '<a href="tel:' . esc_attr( mdash_tel( $phone ) ) . '">' . esc_html( $phone ) . '</a>' : '';
	$origin = mdash_origin()['menu']; // Meat and fish origin under the allergy note, when switched on.
	foreach ( array( 'de', 'en', 'zh' ) as $l ) {
		$note  = $call ? str_replace( '%s', $call, esc_html( $s['allergy_phone'][ $l ] ) ) : esc_html( $s['allergy'][ $l ] );
		$table = $origin ? mdash_origin_table( $l ) : '';
		printf(
			'<div class="mdash-t" data-l="all %1$s" lang="%2$s"><p>%3$s</p><p class="mdash-allergy"><strong>%4$s</strong><br>%5$s</p>%6$s</div>',
			esc_attr( $l ),
			esc_attr( mdash_html_lang( $l ) ),
			esc_html( $s['prices'][ $l ] ),
			esc_html( $s['allergy_title'][ $l ] ),
			$note, // phpcs:ignore -- escaped above
			'' !== $table ? '<div class="mdash-origin"><p class="mdash-origin-title"><strong>' . esc_html( $s['origin_title'][ $l ] ) . '</strong></p>' . $table . '</div>' : '' // phpcs:ignore -- escaped in mdash_origin_table()
		);
	}
	?>
</footer>

<dialog class="mdash-dlg" aria-label="<?php echo esc_attr( mdash_ui_plain( 'photo' ) ); ?>">
	<form method="dialog"><button class="mdash-close" aria-label="<?php echo esc_attr( mdash_ui_plain( 'close' ) ); ?>">&times;</button></form>
	<img alt="">
	<div class="mdash-cap"></div>
</dialog>
</div>
