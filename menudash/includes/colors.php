<?php
/**
 * Colours chosen on the MenuDash page: the background of the menu boxes and the highlight
 * colour (buttons, chips, headings of the specials, the holiday notice, the gift card form).
 * They are printed as the same custom properties a theme could set itself (--mdash-bg,
 * --mdash-accent), after menu.css, so they win over its defaults. Text on the highlight
 * colour turns dark by itself when the highlight is light.
 *
 * Two ways (the owner chooses on the Design tab):
 *   theme  the active theme's own colours (its palette, as changed under Appearance →
 *          Editor → Styles), so the menu matches the site; the default when the theme has them
 *   own    the owner's own two colours. They stay saved while the theme's are in use, so
 *          switching back brings them again.
 * A theme can name its two colours with the filter menudash_theme_colors
 * ( array( 'bg' => '#…', 'accent' => '#…' ) ); otherwise they are looked up in its palette.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_COLORS_OPTION = 'menudash_colors';

/** Name => array( label, default ). */
function mdash_color_keys() {
	return array(
		'bg'     => array( 'Background', '#F5EFE6' ),
		'accent' => array( 'Highlight', '#243F66' ),
	);
}

function mdash_color_hex( $s ) {
	$s = is_scalar( $s ) ? strtoupper( trim( (string) $s ) ) : '';
	return preg_match( '/^#[0-9A-F]{6}$/', $s ) ? $s : '';
}

/** #RGB or #RRGGBB (any case) as #RRGGBB, else ''. */
function mdash_color_hex6( $s ) {
	$s = is_scalar( $s ) ? trim( (string) $s ) : '';
	if ( preg_match( '/^#([0-9a-f])([0-9a-f])([0-9a-f])$/i', $s, $m ) ) {
		$s = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
	}
	return mdash_color_hex( $s );
}

/**
 * The active theme's background and highlight colours, or null when it has none that fit.
 * From the filter menudash_theme_colors, else from the theme's palette: the highlight is
 * the first of primary, accent, brand, highlight, red, accent-1 … accent-6, contrast that stands
 * out from the background (contrast 3:1 at least, so buttons and headings stay readable); the
 * background the first of cream, base, background, paper, white (white when none).
 */
function mdash_theme_colors() {
	static $found = false;
	if ( false !== $found ) {
		return $found;
	}
	$found = null;
	$given = apply_filters( 'menudash_theme_colors', null );
	if ( is_array( $given ) && isset( $given['accent'] ) && '' !== mdash_color_hex6( $given['accent'] ) ) {
		$found = array(
			'bg'     => isset( $given['bg'] ) && '' !== mdash_color_hex6( $given['bg'] ) ? mdash_color_hex6( $given['bg'] ) : '#FFFFFF',
			'accent' => mdash_color_hex6( $given['accent'] ),
		);
		return $found;
	}
	if ( ! function_exists( 'wp_get_global_settings' ) ) {
		return $found;
	}
	$palette = wp_get_global_settings( array( 'color', 'palette' ) );
	$colors  = array();
	foreach ( array( 'theme', 'custom' ) as $origin ) {
		foreach ( isset( $palette[ $origin ] ) && is_array( $palette[ $origin ] ) ? $palette[ $origin ] : array() as $c ) {
			if ( isset( $c['slug'], $c['color'] ) && '' !== mdash_color_hex6( $c['color'] ) && ! isset( $colors[ $c['slug'] ] ) ) {
				$colors[ $c['slug'] ] = mdash_color_hex6( $c['color'] );
			}
		}
	}
	$bg = '#FFFFFF';
	foreach ( array( 'cream', 'base', 'background', 'paper', 'white' ) as $slug ) {
		if ( isset( $colors[ $slug ] ) ) {
			$bg = $colors[ $slug ];
			break;
		}
	}
	// A light background only: the menu's text is dark.
	if ( mdash_contrast( $bg, '#2B1D1A' ) < 7 ) {
		return $found;
	}
	foreach ( array( 'primary', 'accent', 'brand', 'highlight', 'red', 'accent-1', 'accent-2', 'accent-3', 'accent-4', 'accent-5', 'accent-6', 'contrast' ) as $slug ) {
		if ( isset( $colors[ $slug ] ) && mdash_contrast( $colors[ $slug ], $bg ) >= 3 ) {
			$found = array( 'bg' => $bg, 'accent' => $colors[ $slug ] );
			break;
		}
	}
	return $found;
}

/**
 * "theme" (the theme's colours) or "own". The theme's, unless the owner chose their own or
 * the theme has none; a site that saved colours before there was a choice keeps them.
 */
function mdash_colors_mode() {
	$saved = get_option( MDASH_COLORS_OPTION );
	if ( ! mdash_theme_colors() ) {
		return 'own';
	}
	if ( is_array( $saved ) && isset( $saved['mode'] ) ) {
		return 'own' === $saved['mode'] ? 'own' : 'theme';
	}
	return is_array( $saved ) && array_filter( array_intersect_key( $saved, mdash_color_keys() ) ) ? 'own' : 'theme';
}

/** The owner's own colours as saved, with MenuDash's defaults for anything not chosen. */
function mdash_colors_own() {
	$saved = get_option( MDASH_COLORS_OPTION );
	$out   = array();
	foreach ( mdash_color_keys() as $k => $c ) {
		$v         = is_array( $saved ) && isset( $saved[ $k ] ) ? mdash_color_hex( $saved[ $k ] ) : '';
		$out[ $k ] = '' !== $v ? $v : $c[1];
	}
	return $out;
}

/** The colours in use: the theme's or the owner's own. */
function mdash_colors() {
	return 'theme' === mdash_colors_mode() ? mdash_theme_colors() : mdash_colors_own();
}

/**
 * True when the owner's own colours are in use: chosen instead of the theme's (even when
 * they are MenuDash's defaults), or, with a theme that has no colours, changed from them.
 */
function mdash_colors_custom() {
	if ( 'own' !== mdash_colors_mode() ) {
		return false;
	}
	$saved = get_option( MDASH_COLORS_OPTION );
	return (bool) mdash_theme_colors() || ( is_array( $saved ) && array_filter( array_intersect_key( $saved, mdash_color_keys() ) ) );
}

/** Relative luminance (WCAG) of #RRGGBB, 0 = black .. 1 = white. */
function mdash_luminance( $hex ) {
	$l = array();
	foreach ( array( 1, 3, 5 ) as $i ) {
		$c   = hexdec( substr( $hex, $i, 2 ) ) / 255;
		$l[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
}

function mdash_contrast( $a, $b ) {
	$x = mdash_luminance( $a );
	$y = mdash_luminance( $b );
	return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
}

/** Cream or dark text on the highlight colour, whichever reads better. */
function mdash_on_accent( $accent ) {
	return mdash_contrast( $accent, '#FAF8F1' ) >= mdash_contrast( $accent, '#2B1D1A' ) ? '#FAF8F1' : '#2B1D1A';
}

/** Warnings about a colour pair that would be hard to read. */
function mdash_color_problems( $c ) {
	$p = array();
	if ( mdash_contrast( $c['bg'], '#2B1D1A' ) < 7 ) {
		$p[] = 'The background is dark, so the menu text (dark brown) is hard to read. A light background works best.';
	}
	if ( mdash_contrast( $c['accent'], $c['bg'] ) < 3 ) {
		$p[] = 'The highlight colour is close to the background, so buttons and headings hardly stand out.';
	}
	return $p;
}

/** The CSS that applies the colours in use; empty when they are MenuDash's defaults. */
function mdash_colors_css( $selector = '.menudash' ) {
	if ( 'theme' !== mdash_colors_mode() && ! mdash_colors_custom() ) {
		return '';
	}
	$c = mdash_colors();
	return sprintf( '%4$s{--mdash-bg:%1$s;--mdash-accent:%2$s;--mdash-on-accent:%3$s}', $c['bg'], $c['accent'], mdash_on_accent( $c['accent'] ), $selector );
}
