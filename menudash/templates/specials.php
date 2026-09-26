<?php
/**
 * Specials markup: the same rows as the menu (templates/section.php), with the language
 * switch and the photo view, but without the filter bar and category tabs.
 * Variables from mdash_specials_shortcode(): $specials, $photos, $match, $view, $title, $switch, $jump.
 *
 * @package menudash
 */

defined( 'ABSPATH' ) || exit;

$marks = array( 'spicy', 'vegan', 'vegetarian', 'gf' );
$ui    = 'all' === $view ? 'de' : $view;
$htag  = '' !== $title ? 'h3' : 'h2';
$idp   = 'mdash-sp-';
?>
<div class="menudash menudash-specials" data-lite data-lang="<?php echo esc_attr( $view ); ?>" data-ui="<?php echo esc_attr( $ui ); ?>">
<?php echo mdash_lang_script(); // phpcs:ignore -- fixed markup ?>
<?php echo mdash_sprite(); // phpcs:ignore -- built from our own sprite and cleaned SVG ?>

<div class="mdash-sp-head">
	<?php if ( '' !== $title ) : ?>
		<h2 class="mdash-sp-title"><?php echo $title; // phpcs:ignore -- escaped in mdash_specials_shortcode() ?></h2>
	<?php endif; ?>
	<?php if ( $switch ) : ?>
	<div class="mdash-langs" role="group" aria-label="<?php echo esc_attr( mdash_ui_plain( 'language' ) ); ?>">
		<button type="button" data-set-lang="all" aria-pressed="<?php echo 'all' === $view ? 'true' : 'false'; ?>"><?php echo mdash_ui( 'all' ); // phpcs:ignore ?></button>
		<?php foreach ( mdash_lang_buttons() as $code => $label ) : ?>
			<button type="button" data-set-lang="<?php echo esc_attr( $code ); ?>" lang="<?php echo esc_attr( mdash_html_lang( $code ) ); ?>" aria-pressed="<?php echo $code === $view ? 'true' : 'false'; ?>"><?php echo esc_html( $label ); ?></button>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>
</div>

<div class="mdash-sections">
<?php
foreach ( $specials['sections'] as $sec ) {
	include MENUDASH_DIR . 'templates/section.php';
}
?>
</div>

<?php if ( '' !== $jump ) : ?>
	<p class="mdash-sp-jump"><a href="<?php echo esc_url( $jump ); ?>"><?php echo mdash_ui( 'to_menu' ); // phpcs:ignore ?> <span aria-hidden="true">↓</span></a></p>
<?php endif; ?>

<dialog class="mdash-dlg" aria-label="<?php echo esc_attr( mdash_ui_plain( 'photo' ) ); ?>">
	<form method="dialog"><button class="mdash-close" aria-label="<?php echo esc_attr( mdash_ui_plain( 'close' ) ); ?>">&times;</button></form>
	<img alt="">
	<div class="mdash-cap"></div>
</dialog>
</div>
