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
	'mild'  => 'spicy',
);
$marks = array( 'spicy', 'vegan', 'vegetarian', 'gf' ); // Recommended sits in front of the number instead.
$ui    = 'all' === $view ? 'de' : $view;
?>
<div class="menudash" data-lang="<?php echo esc_attr( $view ); ?>" data-ui="<?php echo esc_attr( $ui ); ?>" data-offset="<?php echo esc_attr( $offset ); ?>"<?php echo 'auto' !== $offset ? ' style="--mdash-top:' . esc_attr( $offset ) . 'px"' : ''; ?>>
<script>
/* Apply the guest's language before the menu is drawn, so it doesn't flash the default first. */
(function (r) {
	try {
		var q = new URLSearchParams(location.search).get("lang"), s = null, n = (navigator.language || "").slice(0, 2);
		try { s = localStorage.getItem("menudash-lang"); } catch (e) {}
		var l = /^(all|en|de|zh)$/.test(q) ? q : /^(all|en|de|zh)$/.test(s) ? s : r.getAttribute("data-lang");
		r.setAttribute("data-lang", l);
		r.setAttribute("data-ui", l !== "all" ? l : n === "zh" ? "zh" : n === "de" || n === "fr" || n === "it" ? "de" : "en");
	} catch (e) {}
})(document.currentScript.parentNode);
</script>
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
foreach ( $menu['sections'] as $sec ) :
	$titles = array();
	$notes  = array();
	foreach ( MDASH_LANGS as $l ) {
		list( $titles[ $l ], $notes[ $l ] ) = mdash_split_note( $sec['name'][ $l ] );
	}
	?>
	<section class="mdash-section" id="mdash-<?php echo esc_attr( $sec['id'] ); ?>" data-sec="<?php echo esc_attr( $sec['id'] ); ?>">
		<h2 class="mdash-h">
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
		</h2>
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
			<li class="mdash-dish" id="mdash-<?php echo esc_attr( $d['key'] ); ?>" data-f="<?php echo esc_attr( mdash_filter_tokens( $d['flags'] ) ); ?>">
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
<?php endforeach; ?>
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
