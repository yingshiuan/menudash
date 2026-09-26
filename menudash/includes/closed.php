<?php
/**
 * Holidays and closed days: the owner enters date ranges on the MenuDash page, and
 * [menudash_closed] shows a notice for them in the menu's languages. It appears a while
 * before the first day ("closed from … to …"), changes while the restaurant is closed
 * ("closed until …") and disappears by itself after the last day.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_CLOSED_OPTION = 'menudash_closed';
const MDASH_CLOSED_MAX    = 8;

add_shortcode( 'menudash_closed', 'mdash_closed_shortcode' );

/** Saved periods, sorted by first day: array of array( from, to, back, note ), dates as Y-m-d; back may be ''. */
function mdash_closed_periods() {
	$p = get_option( MDASH_CLOSED_OPTION );
	return is_array( $p ) ? $p : array();
}

/** Clean what the form sent: valid dates only, "to" never before "from", ended ones dropped after a month. */
function mdash_closed_clean( $rows, $today ) {
	$out = array();
	foreach ( (array) $rows as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$from = isset( $r['from'] ) ? mdash_closed_date( $r['from'] ) : '';
		$to   = isset( $r['to'] ) ? mdash_closed_date( $r['to'] ) : '';
		if ( '' === $from && '' === $to ) {
			continue;
		}
		$from = '' === $from ? $to : $from;
		$to   = '' === $to ? $from : $to;
		if ( $to < $from ) {
			list( $from, $to ) = array( $to, $from );
		}
		if ( $to < gmdate( 'Y-m-d', strtotime( "$today -31 days" ) ) ) {
			continue;
		}
		// "Open again" is optional; it only counts when it comes after the last closed day.
		$back  = isset( $r['back'] ) ? mdash_closed_date( $r['back'] ) : '';
		$back  = $back > $to ? $back : '';
		$note  = isset( $r['note'] ) && is_scalar( $r['note'] ) ? sanitize_text_field( (string) $r['note'] ) : '';
		$out[] = array(
			'from' => $from,
			'to'   => $to,
			'back' => $back,
			'note' => function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 200 ) : substr( $note, 0, 200 ),
		);
	}
	usort( $out, function ( $a, $b ) { return strcmp( $a['from'], $b['from'] ); } );
	return array_slice( $out, 0, MDASH_CLOSED_MAX );
}

function mdash_closed_date( $s ) {
	$s = is_scalar( $s ) ? trim( (string) $s ) : '';
	return preg_match( '/^\d{4}-\d\d-\d\d$/', $s ) && checkdate( (int) substr( $s, 5, 2 ), (int) substr( $s, 8, 2 ), (int) substr( $s, 0, 4 ) ) ? $s : '';
}

/**
 * "2026-12-20" in each menu language: 20. Dezember 2026 · 20 December 2026 · 2026年12月20日.
 * With $weekday: Sonntag, 20. Dezember 2026 · Sunday, 20 December 2026 · 2026年12月20日（星期日）.
 */
function mdash_closed_format( $ymd, $lang, $weekday = false ) {
	list( $y, $m, $d ) = array_map( 'intval', explode( '-', $ymd ) );
	if ( $weekday ) {
		$w     = (int) gmdate( 'w', gmmktime( 12, 0, 0, $m, $d, $y ) );
		$names = array(
			'de' => array( 'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag' ),
			'en' => array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
			'zh' => array( '日', '一', '二', '三', '四', '五', '六' ),
		);
		$date = mdash_closed_format( $ymd, $lang );
		return 'zh' === $lang ? $date . '（星期' . $names['zh'][ $w ] . '）' : $names[ $lang ][ $w ] . ', ' . $date;
	}
	$months = array(
		'de' => array( 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember' ),
		'en' => array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ),
	);
	if ( 'zh' === $lang ) {
		return "{$y}年{$m}月{$d}日";
	}
	return 'de' === $lang ? "$d. {$months['de'][ $m - 1 ]} $y" : "$d {$months['en'][ $m - 1 ]} $y";
}

/**
 * The periods to show today: those not yet over whose first day is at most $days away.
 * Each gets 'now' => true while the restaurant is closed.
 */
function mdash_closed_current( $days, $today ) {
	$until = gmdate( 'Y-m-d', strtotime( "$today +$days days" ) );
	$show  = array();
	foreach ( mdash_closed_periods() as $p ) {
		if ( $p['to'] >= $today && $p['from'] <= $until ) {
			$p['now'] = $p['from'] <= $today;
			$show[]   = $p;
		}
	}
	return $show;
}

/** The sentence for one period, per language. */
function mdash_closed_text( $p, $lang ) {
	$s    = mdash_strings();
	$from = mdash_closed_format( $p['from'], $lang );
	$to   = mdash_closed_format( $p['to'], $lang );
	if ( $p['from'] === $p['to'] ) {
		$key = $p['now'] ? 'closed_today' : 'closed_day';
	} else {
		$key = $p['now'] ? 'closed_until' : 'closed_range';
	}
	$text = sprintf( $s[ $key ][ $lang ], $p['now'] ? $to : $from, $to );
	// Then when guests are welcome again: the "open again" date, or the day after the last.
	$back = ! empty( $p['back'] ) ? $p['back'] : gmdate( 'Y-m-d', strtotime( $p['to'] . ' +1 day' ) );
	// The two sentences are joined with a unit separator; mdash_closed_html() sets the second in bold.
	return $text . "\x1F" . sprintf( $s['closed_back'][ $lang ], mdash_closed_format( $back, $lang, true ) );
}

/** One language's text for the page: the closed sentence, then the welcome-back sentence in bold. */
function mdash_closed_html( $text, $lang ) {
	$parts = explode( "\x1F", $text, 2 );
	$join  = 'zh' === $lang ? '' : ' ';
	return esc_html( $parts[0] ) . ( isset( $parts[1] ) ? $join . '<strong>' . esc_html( $parts[1] ) . '</strong>' : '' );
}

/**
 * Attributes:
 *   days  how many days before the first closed day the notice appears (default 60).
 *   lang  first view, as on [menudash]: all (default), de, en or zh; a guest's own choice wins.
 * Prints nothing when no closed day is coming up.
 */
function mdash_closed_shortcode( $atts ) {
	$atts  = shortcode_atts( array( 'days' => 60, 'lang' => 'all' ), $atts, 'menudash_closed' );
	$today = current_time( 'Y-m-d' );
	$show  = mdash_closed_current( max( 0, (int) $atts['days'] ), $today );
	if ( ! $show ) {
		return '';
	}
	mdash_enqueue_assets();
	$view = in_array( $atts['lang'], MDASH_VIEWS, true ) ? $atts['lang'] : 'all';
	$ui   = 'all' === $view ? 'de' : $view;

	ob_start();
	include MENUDASH_DIR . 'templates/closed.php';
	// One line: the Shortcode block runs wpautop, which would add a <br> at each line break.
	return preg_replace( '/\s*\n\s*/', ' ', trim( ob_get_clean() ) );
}
