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
<div class="menudash" data-lang="<?php echo esc_attr( $view ); ?>" data-ui="<?php echo esc_attr( $ui ); ?>" data-offset="<?php echo esc_attr( $offset ); ?>"<?php echo 'auto' !== $offset ? ' style="--mdash-top:' . esc_attr( $offset ) . 'px"' : ''; ?>>
<?php echo mdash_lang_script(); // phpcs:ignore -- fixed markup ?>
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
	$s = mdash_strings();
	foreach ( array( 'de', 'en', 'zh' ) as $l ) {
		printf( '<p class="mdash-t" data-l="all %1$s" lang="%2$s">%3$s %4$s</p>', esc_attr( $l ), esc_attr( mdash_html_lang( $l ) ), esc_html( $s['prices'][ $l ] ), esc_html( $s['allergy'][ $l ] ) );
	}
	?>
</footer>

<dialog class="mdash-dlg" aria-label="<?php echo esc_attr( mdash_ui_plain( 'photo' ) ); ?>">
	<form method="dialog"><button class="mdash-close" aria-label="<?php echo esc_attr( mdash_ui_plain( 'close' ) ); ?>">&times;</button></form>
	<img alt="">
	<div class="mdash-cap"></div>
</dialog>
</div>
