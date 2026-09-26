<?php
/**
 * [menudash] -- the menu page.
 *
 * All three languages are printed into the page and CSS shows the ones the guest picked,
 * so the menu works without JavaScript and search engines read it in full. Each piece of
 * text says in data-l which views show it: "all" (every language at once), "en", "de" or
 * "zh". A text that stands in for a missing translation simply lists that view too, e.g.
 * the English description carries "zh" while the Chinese descriptions are empty.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_VIEWS = array( 'all', 'en', 'de', 'zh' );

function mdash_enqueue() {
	$post = get_post();
	if ( is_singular() && $post && has_shortcode( $post->post_content, 'menudash' ) ) {
		mdash_enqueue_assets();
	}
}

function mdash_enqueue_assets() {
	wp_enqueue_style( 'menudash', MENUDASH_URL . 'assets/menu.css', array(), MENUDASH_VERSION );
	wp_enqueue_script( 'menudash', MENUDASH_URL . 'assets/menu.js', array(), MENUDASH_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
}

function mdash_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'lang'   => 'all',  // The view a first-time guest sees.
			'offset' => 'auto', // Space above the sticky bar: auto-detected, or pixels.
		),
		$atts,
		'menudash'
	);
	mdash_enqueue_assets(); // In case the shortcode sits somewhere mdash_enqueue() can't see.

	$menu   = mdash_get_menu();
	$photos = mdash_photo_index();
	$match  = mdash_photo_matches( $menu, $photos );
	$view   = in_array( $atts['lang'], MDASH_VIEWS, true ) ? $atts['lang'] : 'all';
	$offset = 'auto' === $atts['offset'] ? 'auto' : (string) absint( $atts['offset'] );

	ob_start();
	include MENUDASH_DIR . 'templates/menu.php';
	return "<!-- menudash:start -->\n" . trim( ob_get_clean() ) . "\n<!-- menudash:end -->";
}

/**
 * First thing inside a menu or specials box: applies the guest's saved language before the
 * box is drawn, so it doesn't flash the default first. One line, so wpautop leaves it alone.
 */
function mdash_lang_script() {
	return '<script>(function (r) { try { var q = new URLSearchParams(location.search).get("lang"), s = null, n = (navigator.language || "").slice(0, 2); try { s = localStorage.getItem("menudash-lang"); } catch (e) {} var l = /^(all|en|de|zh)$/.test(q) ? q : /^(all|en|de|zh)$/.test(s) ? s : r.getAttribute("data-lang"); r.setAttribute("data-lang", l); r.setAttribute("data-ui", l !== "all" ? l : n === "zh" ? "zh" : n === "de" || n === "fr" || n === "it" ? "de" : "en"); } catch (e) {} })(document.currentScript.parentNode);</script>';
}

/** First non-empty value among $langs, as array( text, lang ). */
function mdash_first( $texts, $langs ) {
	foreach ( $langs as $l ) {
		if ( '' !== trim( (string) $texts[ $l ] ) ) {
			return array( $texts[ $l ], $l );
		}
	}
	return array( '', 'en' );
}

/** What each single-language view falls back to when its own text is missing. */
function mdash_chains() {
	return array(
		'en' => array( 'en', 'de', 'zh' ),
		'de' => array( 'de', 'en', 'zh' ),
		'zh' => array( 'zh', 'en', 'de' ),
	);
}

/**
 * Texts to print for one field, merged: the same text in the same language is printed once
 * with all the views that show it. $all lists the languages the "all" view shows, in the
 * order it shows them: German first.
 * Returns a list of array( text, lang, views[] ).
 */
function mdash_pieces( $texts, $all = array( 'de', 'en', 'zh' ) ) {
	$out = array();
	$add = function ( $text, $lang, $view ) use ( &$out ) {
		if ( '' === trim( $text ) ) {
			return;
		}
		foreach ( $out as &$p ) {
			if ( $p[1] === $lang && $p[0] === $text ) {
				$p[2][] = $view;
				return;
			}
			// "Edamame" / "Edamame": the same words in English and German show once.
			if ( 'all' === $view && mdash_fold( $p[0] ) === mdash_fold( $text ) && in_array( 'all', $p[2], true ) ) {
				return;
			}
		}
		$out[] = array( $text, $lang, array( $view ) );
	};
	foreach ( $all as $l ) {
		$add( $texts[ $l ], $l, 'all' );
	}
	foreach ( mdash_chains() as $view => $chain ) {
		list( $t, $l ) = mdash_first( $texts, $chain );
		$add( $t, $l, $view );
	}
	return $out;
}

/** <tag class="mdash-t …" data-l="…" lang="…">text</tag> for each piece. */
function mdash_print_pieces( $pieces, $tag, $class = '', $inner = null ) {
	foreach ( $pieces as $p ) {
		list( $text, $lang, $views ) = $p;
		printf(
			'<%1$s class="mdash-t%2$s" data-l="%3$s" lang="%4$s">%5$s</%1$s>',
			$tag,
			$class ? ' ' . esc_attr( $class ) : '',
			esc_attr( implode( ' ', array_unique( $views ) ) ),
			esc_attr( mdash_html_lang( $lang ) ),
			$inner ? $inner( $text, $lang ) : esc_html( $text ) // phpcs:ignore WordPress.Security.EscapeOutput
		);
	}
}

/** A UI word in all three languages; CSS shows the one for the page's interface language. */
function mdash_ui( $key ) {
	$s   = mdash_strings();
	$out = '';
	foreach ( array( 'de', 'en', 'zh' ) as $l ) {
		$out .= sprintf( '<span class="mdash-u" data-l="%s" lang="%s">%s</span>', $l, mdash_html_lang( $l ), esc_html( $s[ $key ][ $l ] ) );
	}
	return $out;
}

/** The same word in all three, for an aria-label or title. */
function mdash_ui_plain( $key ) {
	$s = mdash_strings();
	return implode( ' · ', array_unique( array( $s[ $key ]['de'], $s[ $key ]['en'], $s[ $key ]['zh'] ) ) );
}

/** "DUCK (Homemade Grilled Duck)" -> array( "DUCK", "Homemade Grilled Duck" ). */
function mdash_split_note( $s ) {
	if ( preg_match( '/^(.*?)\s*[(（](.+)[)）]\s*$/u', $s, $m ) && '' !== trim( $m[1] ) ) {
		return array( trim( $m[1] ), trim( $m[2] ) );
	}
	return array( $s, '' );
}

/** "Vegetable Jiaozi - 10 pcs." -> array( "Vegetable Jiaozi", "10 pcs." ). */
function mdash_split_qty( $s ) {
	if ( preg_match( '/^(.*?)\s+[-–—]\s+(.+)$/u', $s, $m ) ) {
		return array( $m[1], $m[2] );
	}
	return array( $s, '' );
}

/** Name with its portion ("10 pcs.") set smaller. */
function mdash_name_html( $text ) {
	list( $name, $qty ) = mdash_split_qty( $text );
	return esc_html( $name ) . ( '' !== $qty ? ' <span class="mdash-qty">' . esc_html( $qty ) . '</span>' : '' );
}

/** A stored price as shown; menus saved by version 1.0 kept "23.50", this shows "23.5". */
function mdash_price_show( $price ) {
	return preg_match( '/^\d+\.\d+$/', $price ) ? rtrim( rtrim( $price, '0' ), '.' ) : $price;
}

/** Diet marks -> the tokens the filter chips look for. */
function mdash_filter_tokens( $flags ) {
	$t = array();
	if ( in_array( 'pick', $flags, true ) ) {
		$t[] = 'pick';
	}
	if ( in_array( 'vegan', $flags, true ) || in_array( 'vegetarian', $flags, true ) ) {
		$t[] = 'veg'; // Vegan dishes are vegetarian too, though the sheet marks only one.
	}
	if ( in_array( 'vegan', $flags, true ) ) {
		$t[] = 'vegan';
	}
	if ( in_array( 'gf', $flags, true ) ) {
		$t[] = 'gf';
	}
	$t[] = in_array( 'spicy', $flags, true ) ? 'spicy' : 'mild';
	return implode( ' ', $t );
}
