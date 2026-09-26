<?php
/**
 * Opening hours: chosen on the MenuDash page with time pickers (no format to get wrong),
 * shown wherever [menudash_hours] is. Days with the same hours next to each other are
 * grouped ("Dienstag – Freitag"). The table carries the hours as data-hours JSON, which a
 * theme can read for an "open now" badge.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_HOURS_OPTION = 'menudash_hours';

add_shortcode( 'menudash_hours', 'mdash_hours_shortcode' );

/** Monday first, as the week is written in Switzerland. Keys are ISO day numbers 1..7. */
function mdash_day_names( $lang ) {
	$names = array(
		'de' => array( 1 => 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag' ),
		'en' => array( 1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ),
		'zh' => array( 1 => '星期一', '星期二', '星期三', '星期四', '星期五', '星期六', '星期日' ),
	);
	return $names[ isset( $names[ $lang ] ) ? $lang : 'de' ];
}

/** Saved hours: ISO day => list of array( 'HH:MM', 'HH:MM' ); an empty list is a closed day. */
function mdash_hours() {
	$h = get_option( MDASH_HOURS_OPTION );
	if ( ! is_array( $h ) ) {
		return array();
	}
	$out = array();
	for ( $d = 1; $d <= 7; $d++ ) {
		$out[ $d ] = isset( $h[ $d ] ) && is_array( $h[ $d ] ) ? $h[ $d ] : array();
	}
	return $out;
}

function mdash_hours_time( $s ) {
	$s = is_scalar( $s ) ? trim( (string) $s ) : '';
	return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $s ) ? $s : '';
}

/**
 * Clean what the form sent. Returns array( hours, problems[] ): a slot needs both times and
 * must close after it opens; a day marked closed keeps no slots.
 */
function mdash_hours_clean( $rows ) {
	$names    = mdash_day_names( 'en' );
	$hours    = array();
	$problems = array();
	for ( $d = 1; $d <= 7; $d++ ) {
		$r           = isset( $rows[ $d ] ) && is_array( $rows[ $d ] ) ? $rows[ $d ] : array();
		$hours[ $d ] = array();
		if ( ! empty( $r['closed'] ) ) {
			continue;
		}
		foreach ( array( 1, 2 ) as $n ) {
			$from = mdash_hours_time( isset( $r[ "from$n" ] ) ? $r[ "from$n" ] : '' );
			$to   = mdash_hours_time( isset( $r[ "to$n" ] ) ? $r[ "to$n" ] : '' );
			if ( '' === $from && '' === $to ) {
				continue;
			}
			if ( '' === $from || '' === $to || $to <= $from ) {
				$problems[] = "$names[$d]: times $n were left out (both needed, closing after opening).";
				continue;
			}
			$hours[ $d ][] = array( $from, $to );
		}
		usort( $hours[ $d ], function ( $a, $b ) { return strcmp( $a[0], $b[0] ); } );
	}
	return array( $hours, $problems );
}

/** Rows for the table: consecutive days with the same hours as one row. */
function mdash_hours_rows( $hours, $lang ) {
	$names = mdash_day_names( $lang );
	$rows  = array();
	for ( $d = 1; $d <= 7; $d++ ) {
		$last = count( $rows ) - 1;
		if ( $last >= 0 && $rows[ $last ]['slots'] === $hours[ $d ] && $rows[ $last ]['to'] === $d - 1 ) {
			$rows[ $last ]['to'] = $d;
		} else {
			$rows[] = array( 'from' => $d, 'to' => $d, 'slots' => $hours[ $d ] );
		}
	}
	foreach ( $rows as &$r ) {
		$span = $r['to'] - $r['from'];
		if ( 0 === $span ) {
			$r['label'] = $names[ $r['from'] ];
		} elseif ( 1 === $span ) {
			$r['label'] = $names[ $r['from'] ] . ( 'zh' === $lang ? '、' : ', ' ) . $names[ $r['to'] ];
		} else {
			$r['label'] = $names[ $r['from'] ] . ( 'zh' === $lang ? '至' : ' – ' ) . $names[ $r['to'] ];
		}
	}
	return $rows;
}

/**
 * Attributes:
 *   lang   de (default), en or zh: the day names and the word for closed.
 *   class  extra class on the table's <figure>, for the theme's styling.
 * Prints nothing until hours are saved.
 */
function mdash_hours_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'lang' => 'de', 'class' => '' ), $atts, 'menudash_hours' );
	$hours = mdash_hours();
	if ( ! $hours || ! array_filter( $hours ) ) {
		return '';
	}
	$lang   = in_array( $atts['lang'], array( 'de', 'en', 'zh' ), true ) ? $atts['lang'] : 'de';
	$closed = array( 'de' => 'Geschlossen', 'en' => 'Closed', 'zh' => '休息' );
	// For scripts: JavaScript day numbers (0 = Sunday) => slots.
	$js = array();
	foreach ( $hours as $d => $slots ) {
		$js[ $d % 7 ] = $slots;
	}
	ksort( $js );
	$html = '';
	foreach ( mdash_hours_rows( $hours, $lang ) as $r ) {
		$times = $r['slots'] ? implode( '<br>', array_map( function ( $s ) { return esc_html( $s[0] . ' – ' . $s[1] ); }, $r['slots'] ) ) : esc_html( $closed[ $lang ] );
		$html .= '<tr' . ( $r['slots'] ? '' : ' class="closed"' ) . '><td>' . esc_html( $r['label'] ) . '</td><td>' . $times . '</td></tr>';
	}
	$class = trim( 'wp-block-table menudash-hours ' . implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', $atts['class'] ) ) ) );
	return '<figure class="' . esc_attr( $class ) . '" data-hours="' . esc_attr( wp_json_encode( $js ) ) . '"><table><tbody>' . $html . '</tbody></table></figure>';
}
