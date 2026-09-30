<?php
/**
 * Fonts of the menu and of the other MenuDash boxes (specials, gift card form, recommended
 * dishes), chosen on the Design tab:
 *
 *   theme  the theme's own fonts: the text font and the heading font (with its weight) chosen
 *          in the theme, also after the owner changes them under Appearance → Editor → Styles.
 *          The default. (A classic theme has none to give: MenuDash's own are used.)
 *   own    MenuDash's own fonts, bundled: DM Sans for text, Darker Grotesque (weight 800) for
 *          headings, the same pair as MenuDash Theme.
 *
 * Chinese always uses the system's Chinese font (menu.css, --mdash-zh), as the theme's font
 * usually has no Chinese.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_FONTS_OPTION = 'menudash_fonts';

/** "theme" or "own". */
function mdash_fonts_mode() {
	return 'own' === get_option( MDASH_FONTS_OPTION ) ? 'own' : 'theme';
}

/** A font value from theme.json / Styles as CSS: "var:preset|font-family|x" → var(--wp--preset--font-family--x). */
function mdash_font_css_value( $v ) {
	$v = is_string( $v ) ? trim( $v ) : '';
	if ( preg_match( '/^var:preset\|font-family\|([a-z0-9-]+)$/i', $v, $m ) ) {
		return 'var(--wp--preset--font-family--' . strtolower( $m[1] ) . ')';
	}
	// Only what a font list or a variable can contain: letters, digits, spaces (no line
	// breaks), quotes in pairs, commas, brackets, dots and dashes; nothing that ends the rule.
	if ( ! preg_match( '/^[\w "\',().-]+$/u', $v ) || 0 !== substr_count( $v, '"' ) % 2 || 0 !== substr_count( $v, "'" ) % 2 ) {
		return '';
	}
	return $v;
}

/**
 * The theme's fonts: array( text, headings, heading weight ), from the theme's global styles
 * (block themes, as changed in the Site Editor), or null: a classic theme, or a block theme
 * without fonts of its own. Then MenuDash's own fonts are used.
 */
function mdash_theme_fonts() {
	if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() || ! function_exists( 'wp_get_global_styles' ) ) {
		return null;
	}
	$text    = mdash_font_css_value( wp_get_global_styles( array( 'typography', 'fontFamily' ) ) );
	$heading = mdash_font_css_value( wp_get_global_styles( array( 'elements', 'heading', 'typography', 'fontFamily' ) ) );
	$weight  = wp_get_global_styles( array( 'elements', 'heading', 'typography', 'fontWeight' ) );
	if ( '' === $text && '' === $heading ) {
		return null;
	}
	return array(
		'' !== $text ? $text : 'inherit',
		'' !== $heading ? $heading : $text,
		is_scalar( $weight ) && preg_match( '/^[1-9]00$/', (string) $weight ) ? (string) $weight : '700',
	);
}

/** The CSS for the fonts in use; empty for MenuDash's own (menu.css has them). */
function mdash_fonts_css( $selector = '.menudash' ) {
	$fonts = 'own' === mdash_fonts_mode() ? null : mdash_theme_fonts();
	if ( ! $fonts ) {
		return '';
	}
	list( $text, $heading, $weight ) = $fonts;
	return sprintf( '%1$s{--mdash-sans:%2$s;--mdash-display:%3$s;--mdash-display-weight:%4$s}', $selector, $text, $heading, $weight );
}
