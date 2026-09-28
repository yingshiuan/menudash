<?php
/**
 * Colours chosen on the MenuDash page: the background of the menu boxes and the highlight
 * colour (buttons, chips, headings of the specials, the holiday notice, the gift card form).
 * They are printed as the same custom properties a theme could set itself (--mdash-bg,
 * --mdash-accent), after menu.css, so they win over its defaults. Text on the highlight
 * colour turns dark by itself when the highlight is light.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_COLORS_OPTION = 'menudash_colors';

/** Name => array( label, default ). */
function mdash_color_keys() {
	return array(
		'bg'     => array( 'Background', '#FAF8F1' ),
		'accent' => array( 'Highlight', '#A31E2C' ),
	);
}

function mdash_color_hex( $s ) {
	$s = is_scalar( $s ) ? strtoupper( trim( (string) $s ) ) : '';
	return preg_match( '/^#[0-9A-F]{6}$/', $s ) ? $s : '';
}

/** Saved colours, with the defaults filled in for anything not chosen. */
function mdash_colors() {
	$saved = get_option( MDASH_COLORS_OPTION );
	$out   = array();
	foreach ( mdash_color_keys() as $k => $c ) {
		$v         = is_array( $saved ) && isset( $saved[ $k ] ) ? mdash_color_hex( $saved[ $k ] ) : '';
		$out[ $k ] = '' !== $v ? $v : $c[1];
	}
	return $out;
}

/** True when the owner changed at least one colour. */
function mdash_colors_custom() {
	$saved = get_option( MDASH_COLORS_OPTION );
	return is_array( $saved ) && array_filter( $saved );
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

/** The CSS that applies the chosen colours; empty when the defaults are in use. */
function mdash_colors_css() {
	if ( ! mdash_colors_custom() ) {
		return '';
	}
	$c = mdash_colors();
	return sprintf( '.menudash{--mdash-bg:%1$s;--mdash-accent:%2$s;--mdash-on-accent:%3$s}', $c['bg'], $c['accent'], mdash_on_accent( $c['accent'] ) );
}
