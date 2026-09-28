<?php
/**
 * Tests for menudash/includes/csv-parser.php, against sample/menu-sample.csv.
 *
 *   dev/test.sh
 *
 * Runs in WordPress Playground's PHP (nothing to install but Node) with the repository
 * mounted at /repo. Prints one line per check and exits 1 if any failed.
 */

define( 'MENUDASH_PURE', true );
$root = getenv( 'MENUDASH_ROOT' ) ?: '/repo';
require $root . '/menudash/includes/csv-parser.php';

$failed = 0;
function check( $ok, $what, $detail = '' ) {
	global $failed;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $what . ( $ok || '' === $detail ? '' : "\n     $detail" ) . "\n";
	$failed += $ok ? 0 : 1;
}
function key_of( $menu, $start ) {
	foreach ( $menu['sections'] as $s ) {
		foreach ( $s['dishes'] as $d ) {
			if ( 0 === strpos( $d['name']['en'], $start ) ) {
				return $d['key'];
			}
		}
	}
	return '';
}

// ---------- The sample menu ----------
$csv  = file_get_contents( "$root/sample/menu-sample.csv" );
$real = mdash_parse_csv( $csv );
check( $real['ok'], 'sample CSV parses', $real['error'] );
$menu = $real['menu'];
check( 4 === count( $menu['sections'] ), '4 categories', count( $menu['sections'] ) . ' found' );
check( 11 === $menu['dishes'], '11 dishes', $menu['dishes'] . ' found' );
check( array() === $real['warnings'], 'no warnings', implode( "\n     ", $real['warnings'] ) );

$first = $menu['sections'][0];
check( 'STARTERS' === $first['name']['en'] && 'VORSPEISEN' === $first['name']['de'] && '前菜' === $first['name']['zh'], 'first heading in three languages', json_encode( $first['name'], JSON_UNESCAPED_UNICODE ) );
$cucumber = $first['dishes'][0];
check( '1' === $cucumber['no'] && '7.5' === $cucumber['price'] && '拍黃瓜' === $cucumber['name']['zh'], 'Nr. 1 Cucumber Salad: 7.5, 拍黃瓜', json_encode( $cucumber, JSON_UNESCAPED_UNICODE ) );
check( array( 'spicy', 'vegan', 'gf' ) === $cucumber['flags'], 'Nr. 1 is spicy, vegan, gluten-free', implode( ',', $cucumber['flags'] ) );

$all = array();
foreach ( $menu['sections'] as $s ) {
	foreach ( $s['dishes'] as $d ) {
		$all[ $d['key'] ] = $d;
	}
}
check( isset( $all['jasmine-rice'] ) && '' === $all['jasmine-rice']['no'] && '4' === $all['jasmine-rice']['price'], 'dish without a number keeps its price (Jasmine Rice 4)' );
check( isset( $all['n12'] ) && '21' === $all['n12']['price'], 'prices are written as on a printed menu: 21, not 21.00' );
check( isset( $all['n2'] ) && 'Steamed, filled with cabbage and mushrooms' === $all['n2']['desc']['en'], 'commas inside quoted cells survive' );

// ---------- The same data in the shapes other programs write ----------
$rows = array();
$h    = fopen( "$root/sample/menu-sample.csv", 'r' );
while ( ( $r = fgetcsv( $h, 0, ',', '"', '' ) ) !== false ) {
	$rows[] = $r;
}
fclose( $h );
$to_csv = function ( $rows, $delim ) {
	$h = fopen( 'php://temp', 'w+' );
	foreach ( $rows as $r ) {
		fputcsv( $h, $r, $delim, '"', '' );
	}
	rewind( $h );
	// CRLF as Windows writes it (fputcsv's own eol argument needs PHP 8.1).
	return str_replace( "\n", "\r\n", stream_get_contents( $h ) );
};
$bom = mdash_parse_csv( "\xEF\xBB\xBF" . $csv );
check( $bom['ok'] && $bom['menu'] == $menu, 'UTF-8 with BOM gives the same menu' );
$semi = mdash_parse_csv( $to_csv( $rows, ';' ) );
check( $semi['ok'] && $semi['menu'] == $menu, 'semicolon + CRLF (Swiss/German Excel) gives the same menu' );
$tab = mdash_parse_csv( $to_csv( $rows, "\t" ) );
check( $tab['ok'] && $tab['menu'] == $menu, 'tab-separated gives the same menu' );

$win = mdash_parse_csv( mb_convert_encoding( $to_csv( $rows, ';' ), 'Windows-1252', 'UTF-8' ) );
check( $win['ok'] && 11 === $win['menu']['dishes'], 'Windows-1252 file still reads' );
check( (bool) preg_grep( '/not UTF-8/', $win['warnings'] ), 'Windows-1252 file gets the "export as UTF-8" warning' );
check( 'Gurke mit Knoblauch und Sesam' === $win['menu']['sections'][0]['dishes'][0]['desc']['de'] && 'Gemüse-Teigtaschen' === mdash_base_name( $win['menu']['sections'][0]['dishes'][1]['name']['de'] ), 'umlauts survive Windows-1252' );

// Reordered and renamed columns, with German headers.
$map = array( 3 => 'Name', 1 => 'Preis', 0 => 'Nr.', 4 => 'Name (DE)', 5 => 'Chinese Name', 13 => 'Glutenfrei', 11 => 'vegan', 12 => 'Vegetarisch', 10 => 'Scharf', 9 => 'Empfohlen', 6 => 'Description (EN)', 7 => 'Beschreibung (DE)' );
$re  = array();
foreach ( $rows as $i => $r ) {
	$out = array();
	foreach ( $map as $src => $label ) {
		$out[] = 0 === $i ? $label : $r[ $src ];
	}
	$re[] = $out;
}
$reordered = mdash_parse_csv( $to_csv( $re, ',' ) );
$d1        = $reordered['ok'] ? $reordered['menu']['sections'][0]['dishes'][0] : null;
check( $reordered['ok'] && 11 === $reordered['menu']['dishes'] && '7.5' === $d1['price'] && array( 'spicy', 'vegan', 'gf' ) === $d1['flags'] && 'Gurkensalat' === $d1['name']['de'], 'reordered columns with German headers read the same', $reordered['error'] );

// ---------- Wrong files are refused, so they never replace a live menu ----------
check( ! mdash_parse_csv( "Date,Guest,Table\n2026-01-01,Anna,4\n" )['ok'], 'a CSV without menu columns is rejected' );
check( ! mdash_parse_csv( '' )['ok'], 'empty file is rejected' );
check( ! mdash_parse_csv( "No.,Price,Name (EN)\n,,SOUP\n" )['ok'], 'headings without dishes are rejected' );
$twins = mdash_parse_csv( "No.,Price,Name (EN),Vegan\n,,SOUP\n,5,Miso Soup,X\n,,MORE\n,5,Miso Soup,\n" );
check( (bool) preg_grep( '/listed twice/', $twins['warnings'] ), 'the same dish twice with different marks is flagged' );

foreach ( array( '8.5' => 8.5, '18,50' => 18.5, '18.–' => 18.0, '18.-' => 18.0, 'CHF 12' => 12.0, 'Fr. 9.-' => 9.0, '26.500000000000004' => 26.5, "1'250" => 1250.0, 'ab 12' => null, '' => null ) as $in => $want ) {
	$got = mdash_price( (string) $in );
	check( $got === $want, "price \"$in\" -> " . var_export( $want, true ), 'got ' . var_export( $got, true ) );
}
foreach ( array( array( 23.5, '23.5' ), array( 12.0, '12' ), array( 5.8, '5.8' ), array( 18.75, '18.75' ) ) as $case ) {
	check( mdash_price_text( $case[0] ) === $case[1], "price shown as {$case[1]}", mdash_price_text( $case[0] ) );
}

// ---------- Photos ----------
$photos = array();
$t      = 0;
foreach ( scandir( "$root/sample/photos" ) as $f ) {
	if ( preg_match( '/\.(png|jpe?g|webp)$/i', $f ) ) {
		$photos[ 'p' . ( ++$t ) ] = array( 'name' => $f, 'time' => $t );
	}
}
$m       = mdash_match_photos( $menu, $photos );
$name_of = function ( $key ) use ( $m, $photos ) { return isset( $m['dish'][ $key ] ) ? $photos[ $m['dish'][ $key ] ]['name'] : '(none)'; };
check( 6 === count( $m['dish'] ) && ! $m['unmatched'], 'all 6 sample photos find their dish', count( $m['dish'] ) . ' matched' );
check( '21_Coconut Curry.png' === $name_of( 'n21' ), 'by number: 21_Coconut Curry.png -> Nr. 21', $name_of( 'n21' ) );
check( 'Jasmine Rice.png' === $name_of( 'jasmine-rice' ), 'by name, for a dish without a number', $name_of( 'jasmine-rice' ) );

$stale = mdash_match_photos( $menu, array(
	'a' => array( 'name' => '99_Mango Pudding.png', 'time' => 1 ),
	'b' => array( 'name' => '11_Something else.png', 'time' => 2 ),
) );
check( isset( $stale['dish']['n30'] ) && 'a' === $stale['dish']['n30'], 'a number from an old menu loses to an exact dish name (99_Mango Pudding -> Nr. 30)' );
check( isset( $stale['dish']['n11'] ) && 'b' === $stale['dish']['n11'], 'a number with a name that matches no dish still counts' );

$two = mdash_match_photos( $menu, array(
	'a' => array( 'name' => '10_Noodles.png', 'time' => 1 ),
	'b' => array( 'name' => '10_Dan Dan Noodles.png', 'time' => 2 ),
) );
check( 'b' === $two['dish']['n10'] && array( 'a' => 'n10' ) === $two['spare'], 'two photos for one dish: the newer is used, the older reported as spare' );

$with_photo = mdash_parse_csv( "No.,Price,Name (EN),Photo\n,,SOUP\n1,8.5,Miso Soup,Jasmine Rice.png\n" );
$o          = mdash_match_photos( $with_photo['menu'], $photos );
check( isset( $o['dish']['n1'] ) && 'Jasmine Rice.png' === $photos[ $o['dish']['n1'] ]['name'], 'the Photo column overrides the automatic match' );

echo $failed ? "\n$failed check(s) failed\n" : "\nall checks passed\n";
exit( $failed ? 1 : 0 );
