<?php
/**
 * One menu category: heading, note and dish rows. Used by the menu (templates/menu.php)
 * and the specials (templates/specials.php), so both look the same.
 *
 * Variables: $sec, $photos, $match, $marks; optional $htag (heading tag, default h2) and
 * $idp (prefix for the ids, default "mdash-").
 *
 * @package menudash
 */

defined( 'ABSPATH' ) || exit;

$htag = isset( $htag ) ? $htag : 'h2';
$idp  = isset( $idp ) ? $idp : 'mdash-';
$titles = array();
$notes  = array();
foreach ( MDASH_LANGS as $l ) {
	list( $titles[ $l ], $notes[ $l ] ) = mdash_split_note( $sec['name'][ $l ] );
}
?>
<section class="mdash-section" id="<?php echo esc_attr( $idp . $sec['id'] ); ?>" data-sec="<?php echo esc_attr( $sec['id'] ); ?>">
	<<?php echo tag_escape( $htag ); ?> class="mdash-h">
		<?php
		$first = true;
		foreach ( mdash_pieces( $titles ) as $p ) {
			$in_all = in_array( 'all', $p[2], true );
			if ( $in_all && ! $first ) {
				echo '<span class="mdash-t mdash-sep" data-l="all" aria-hidden="true"> · </span>';
			}
			mdash_print_pieces( array( $p ), 'span', $in_all && ! $first ? 'mdash-h2' : '' );
			$first = $first && ! $in_all;
		}
		?>
	</<?php echo tag_escape( $htag ); ?>>
	<?php if ( '' !== implode( '', $notes ) ) : ?>
		<p class="mdash-note"><?php mdash_print_pieces( mdash_pieces( $notes ), 'span' ); ?></p>
	<?php endif; ?>

	<ul class="mdash-list">
	<?php
	foreach ( $sec['dishes'] as $d ) :
		$n     = $d['name'];
		$photo = isset( $match['dish'][ $d['key'] ], $photos[ $match['dish'][ $d['key'] ] ] ) ? $photos[ $match['dish'][ $d['key'] ] ] : null;

		// Main line: the view's own name. The Chinese follows after a slash, as on the
		// printed menu, except in the Chinese view where it is the name itself.
		$main = array();
		foreach ( array( 'all' => mdash_chains()['de'] ) + mdash_chains() as $v => $chain ) {
			$main[ $v ] = mdash_first( $n, $chain );
		}
		$main_pieces = array();
		foreach ( $main as $v => $tl ) {
			$main_pieces[] = array( $tl[0], $tl[1], array( $v ) );
		}
		$merged = array();
		foreach ( $main_pieces as $p ) {
			$k = $p[1] . "\0" . $p[0];
			if ( isset( $merged[ $k ] ) ) {
				$merged[ $k ][2] = array_merge( $merged[ $k ][2], $p[2] );
			} else {
				$merged[ $k ] = $p;
			}
		}
		$zh_after = array();
		foreach ( $main as $v => $tl ) {
			if ( 'zh' !== $tl[1] && '' !== $n['zh'] ) {
				$zh_after[] = $v;
			}
		}
		// Second line: English under the German in the all-languages view, English
		// under the Chinese in the Chinese view.
		$sub = array();
		if ( '' !== $n['en'] && 'en' !== $main['all'][1] && mdash_fold( $n['en'] ) !== mdash_fold( $main['all'][0] ) ) {
			$sub[] = array( $n['en'], 'en', array( 'all' ) );
		}
		if ( 'zh' === $main['zh'][1] ) {
			list( $t, $tl ) = mdash_first( $n, array( 'en', 'de' ) );
			if ( '' !== $t ) {
				$sub[] = array( $t, $tl, array( 'zh' ) );
			}
		}
		$label = mdash_first( $n, array( 'de', 'en', 'zh' ) )[0];
		?>
		<li class="mdash-dish" id="<?php echo esc_attr( $idp . $d['key'] ); ?>" data-f="<?php echo esc_attr( mdash_filter_tokens( $d['flags'] ) ); ?>">
			<div class="mdash-body">
				<h3 class="mdash-name">
					<?php if ( in_array( 'pick', $d['flags'], true ) || '' !== $d['no'] ) : ?>
						<span class="mdash-no"><?php if ( in_array( 'pick', $d['flags'], true ) ) : ?><span class="mdash-pick" role="img" aria-label="<?php echo esc_attr( mdash_ui_plain( 'pick' ) ); ?>" title="<?php echo esc_attr( mdash_ui_plain( 'pick' ) ); ?>"><?php echo mdash_icon( 'pick' ); // phpcs:ignore ?></span><?php endif; ?><?php echo esc_html( $d['no'] ); ?></span>
					<?php endif; ?>
					<?php mdash_print_pieces( array_values( $merged ), 'span', '', 'mdash_name_html' ); ?>
					<?php if ( $zh_after ) : ?>
						<span class="mdash-t mdash-zh" data-l="<?php echo esc_attr( implode( ' ', $zh_after ) ); ?>" lang="zh-Hant"><span class="mdash-slash" aria-hidden="true">/ </span><?php echo esc_html( $n['zh'] ); ?></span>
					<?php endif; ?>
				</h3>
				<?php if ( $sub ) : ?>
					<p class="mdash-sub"><?php mdash_print_pieces( $sub, 'span', '', 'mdash_name_html' ); ?></p>
				<?php endif; ?>
				<?php mdash_print_pieces( mdash_pieces( $d['desc'] ), 'p', 'mdash-desc' ); ?>
			</div>
			<div class="mdash-side">
				<div class="mdash-tag">
					<?php
					$icons = array_values( array_intersect( $marks, $d['flags'] ) );
					if ( $icons ) :
						?>
						<span class="mdash-icons">
							<?php foreach ( $icons as $mark ) : ?>
								<span class="mdash-mark" role="img" aria-label="<?php echo esc_attr( mdash_ui_plain( $mark ) ); ?>" title="<?php echo esc_attr( mdash_ui_plain( $mark ) ); ?>"><?php echo mdash_icon( $mark ); // phpcs:ignore ?></span>
							<?php endforeach; ?>
						</span>
					<?php endif; ?>
					<p class="mdash-price">
						<?php echo esc_html( mdash_price_show( $d['price'] ) ); ?>
						<?php if ( '' !== $d['measure'] ) : ?>
							<small><?php echo esc_html( $d['measure'] ); ?></small>
						<?php endif; ?>
					</p>
				</div>
				<?php if ( $photo ) : ?>
					<button type="button" class="mdash-photo<?php echo $photo['alpha'] ? '' : ' mdash-square'; ?>" data-full="<?php echo esc_url( mdash_photo_url( $photo, 800 ) ); ?>" aria-label="<?php echo esc_attr( mdash_ui_plain( 'photo' ) . ': ' . $label ); ?>">
						<img src="<?php echo esc_url( mdash_photo_url( $photo, 400 ) ); ?>" srcset="<?php echo esc_url( mdash_photo_url( $photo, 400 ) ); ?> 400w, <?php echo esc_url( mdash_photo_url( $photo, 800 ) ); ?> 800w" sizes="104px" width="400" height="400" alt="" loading="lazy" decoding="async">
					</button>
				<?php endif; ?>
			</div>
		</li>
	<?php endforeach; ?>
	</ul>
</section>
