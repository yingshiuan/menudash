<?php
/**
 * Holiday notice. Variables from mdash_closed_shortcode(): $show, $view, $ui.
 * Each sentence is printed in all three languages; the menu's CSS shows the guest's.
 *
 * @package menudash
 */

defined( 'ABSPATH' ) || exit;

$now = (bool) array_filter( wp_list_pluck( $show, 'now' ) );
?>
<div class="menudash menudash-closed<?php echo $now ? ' is-now' : ''; ?>" data-lite data-lang="<?php echo esc_attr( $view ); ?>" data-ui="<?php echo esc_attr( $ui ); ?>" role="status">
<?php echo mdash_lang_script(); // phpcs:ignore -- fixed markup ?>
<p class="mdash-closed-title"><?php echo mdash_ui( 'closed_title' ); // phpcs:ignore ?></p>
<?php foreach ( $show as $p ) : ?>
	<p class="mdash-closed-line">
		<?php
		$texts = array();
		foreach ( MDASH_LANGS as $l ) {
			$texts[ $l ] = mdash_closed_text( $p, $l );
		}
		mdash_print_pieces( mdash_pieces( $texts ), 'span', '', 'mdash_closed_html' );
		?>
		<?php if ( '' !== $p['note'] ) : ?>
			<span class="mdash-closed-note"><?php echo esc_html( $p['note'] ); ?></span>
		<?php endif; ?>
	</p>
<?php endforeach; ?>
</div>
