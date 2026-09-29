<?php
/**
 * Tests for menudash/includes/xlsx.php: a normal workbook reads like its CSV would, sheets are
 * matched by name, and hostile files are refused or cut short: zip bomb, XML entities, paths
 * outside the workbook, a far-away cell, too many rows, macros, not a ZIP at all.
 *
 *   dev/test.sh
 */

define( 'MENUDASH_PURE', true );
require '/repo/menudash/includes/csv-parser.php';
require '/repo/menudash/includes/xlsx.php';

$failed = 0;
function check( $ok, $what, $detail = '' ) {
	global $failed;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $what . ( $ok || '' === $detail ? '' : "\n     $detail" ) . "\n";
	$failed += $ok ? 0 : 1;
}

$tmp = sys_get_temp_dir();
/** Builds an .xlsx from sheet name => sheet XML body (<sheetData>…), plus optional extra parts. */
function book( $file, $sheets, $strings = array(), $extra = array(), $rels_extra = '' ) {
	@unlink( $file );
	$z = new ZipArchive();
	$z->open( $file, ZipArchive::CREATE );
	$z->addFromString( '[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>' );
	$wb   = '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
	$rels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rS" Type="x/sharedStrings" Target="sharedStrings.xml"/>' . $rels_extra;
	$i    = 0;
	foreach ( $sheets as $name => $body ) {
		$i++;
		$wb   .= '<sheet name="' . htmlspecialchars( $name ) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
		$rels .= '<Relationship Id="rId' . $i . '" Type="x/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
		$z->addFromString( "xl/worksheets/sheet$i.xml", '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $body . '</sheetData></worksheet>' );
	}
	$z->addFromString( 'xl/workbook.xml', $wb . '</sheets></workbook>' );
	$z->addFromString( 'xl/_rels/workbook.xml.rels', $rels . '</Relationships>' );
	$sst = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
	foreach ( $strings as $s ) {
		$sst .= '<si>' . $s . '</si>';
	}
	$z->addFromString( 'xl/sharedStrings.xml', $sst . '</sst>' );
	foreach ( $extra as $n => $data ) {
		$z->addFromString( $n, $data );
	}
	$z->close();
	return $file;
}
$s = function ( $ref, $i ) { return '<c r="' . $ref . '" t="s"><v>' . $i . '</v></c>'; };
$n = function ( $ref, $v ) { return '<c r="' . $ref . '"><v>' . $v . '</v></c>'; };

// A normal workbook: header in row 2 from column B (Numbers leaves such margins), shared,
// rich-text and inline strings, a price saved as 23.500000001, a phonetic guide to leave out.
$strings = array( '<t>No.</t>', '<t>Price</t>', '<t>Name (DE)</t>', '<t>Name (ZH)</t>', '<t>SUPPEN</t>',
	'<r><t>Wan</t></r><r><t>ton</t></r>', '<t>雲吞</t><rPh><t>ワンタン</t></rPh>', '<t>Tagessuppe</t>' );
$menu    = '<row r="2">' . $s( 'B2', 0 ) . $s( 'C2', 1 ) . $s( 'D2', 2 ) . $s( 'E2', 3 ) . '</row>'
	. '<row r="3">' . $s( 'D3', 4 ) . '</row>'
	. '<row r="4">' . $n( 'B4', 2 ) . $n( 'C4', '23.500000001' ) . $s( 'D4', 5 ) . $s( 'E4', 6 ) . '</row>'
	. '<row r="5">' . $n( 'C5', 8 ) . '<c r="D5" t="inlineStr"><is><t>Frühlingsrolle, "gross"</t></is></c><c r="E5" t="e"><v>#N/A</v></c></row>';
$lunch   = '<row r="1">' . $s( 'A1', 1 ) . $s( 'B1', 2 ) . '</row><row r="2">' . $s( 'B2', 7 ) . '</row><row r="3">' . $n( 'A3', 6.5 ) . $s( 'B3', 7 ) . '</row>';
$f = book( "$tmp/good.xlsx", array( 'Export Summary' => '', 'menu - main-menu-2026' => $menu, 'Mittagsmenü' => $lunch, 'drinks - bar-list' => $menu ), $strings );
$r = mdash_xlsx_read( $f );
check( $r['ok'], 'a normal workbook is read', $r['error'] );
check( array( 'Export Summary', 'menu - main-menu-2026', 'Mittagsmenü', 'drinks - bar-list' ) === array_keys( $r['sheets'] ), 'all sheets, in workbook order', implode( ' | ', array_keys( $r['sheets'] ) ) );
$rows = $r['sheets']['menu - main-menu-2026'];
check( array( 'No.', 'Price', 'Name (DE)', 'Name (ZH)' ) === $rows[0], 'rows start at the first filled row and column (B2)', json_encode( $rows[0] ) );
check( array( '2', '23.5', 'Wanton', '雲吞' ) === $rows[2], 'numbers as shown (23.5), rich text joined, no phonetic guide', json_encode( $rows[2], JSON_UNESCAPED_UNICODE ) );
check( array( '', '8', 'Frühlingsrolle, "gross"', '' ) === $rows[3], 'inline string with a comma and quotes; an error cell (#N/A) is empty', json_encode( $rows[3], JSON_UNESCAPED_UNICODE ) );
$p = mdash_parse_csv( mdash_rows_to_csv( $rows ) );
check( $p['ok'] && 2 === $p['menu']['dishes'] && 'Frühlingsrolle, "gross"' === $p['menu']['sections'][0]['dishes'][1]['name']['de'], 'the sheet goes through the CSV parser: 1 heading, 2 dishes', $p['ok'] ? '' : $p['error'] );

$titled = array_merge( array( array( 'Our menu', '', '', '' ) ), $rows );
check( $rows === mdash_xlsx_from_header( $titled ), 'a title row above the header is skipped' );
check( 'menu' === mdash_sheet_kind( 'menu - main-menu-2026' ) && 'menu' === mdash_sheet_kind( 'Speisekarte' ) && 'menu' === mdash_sheet_kind( '菜單' ), 'menu / Speisekarte / 菜單 → the menu' );
check( 'specials' === mdash_sheet_kind( 'specials - heute-empfehlung' ) && 'specials' === mdash_sheet_kind( 'Heute empfohlen' ), 'specials / Heute empfohlen → specials' );
check( 'lunch' === mdash_sheet_kind( 'Mittagsmenü' ) && 'lunch' === mdash_sheet_kind( 'Lunch menu' ), 'Mittagsmenü / Lunch menu → lunch (not the menu)' );
check( '' === mdash_sheet_kind( 'drinks - bar-list' ) && '' === mdash_sheet_kind( 'Drink menu' ) && '' === mdash_sheet_kind( 'Getränkekarte' ) && '' === mdash_sheet_kind( 'Export Summary' ), 'drinks and other sheets → not used' );

// Hostile files.
file_put_contents( "$tmp/not.xlsx", "No.,Price\n1,2\n" );
$r = mdash_xlsx_read( "$tmp/not.xlsx" );
check( ! $r['ok'] && false !== strpos( $r['error'], 'not an Excel file' ), 'a CSV renamed to .xlsx: refused', $r['error'] );

$xxe = book( "$tmp/xxe.xlsx", array( 'menu' => '' ), array(), array( 'xl/sharedStrings.xml' => '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>&e;</t></si></sst>' ) );
$r   = mdash_xlsx_read( $xxe );
check( ! $r['ok'] && false !== strpos( $r['error'], 'XML declarations' ), 'external entity (reading a server file): refused', $r['error'] );

$laughs = '<?xml version="1.0"?><!DOCTYPE l [<!ENTITY a "aaaaaaaaaa"><!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;">]><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>&b;</t></is></c></row></sheetData></worksheet>';
$lf     = book( "$tmp/laughs.xlsx", array( 'menu' => '' ), array(), array( 'xl/worksheets/sheet1.xml' => $laughs ) );
$r      = mdash_xlsx_read( $lf );
check( ! $r['ok'], '"billion laughs" entities in a sheet: refused', $r['error'] );

$bomb = book( "$tmp/bomb.xlsx", array( 'menu' => '<row r="1"><c r="A1" t="inlineStr"><is><t>' . str_repeat( 'x', MDASH_XLSX_MAX_PART + 10 ) . '</t></is></c></row>' ) );
check( filesize( $bomb ) < 100000, 'the zip bomb itself is small (' . filesize( $bomb ) . ' bytes)' );
$r = mdash_xlsx_read( $bomb );
check( ! $r['ok'] && false !== strpos( $r['error'], 'too large' ), 'zip bomb (8 MB+ of XML): refused before parsing', $r['error'] );

$trav = book( "$tmp/trav.xlsx", array(), array(), array( 'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="menu" sheetId="1" r:id="rX"/></sheets></workbook>' ), '<Relationship Id="rX" Type="x/worksheet" Target="../../../../etc/passwd"/>' );
$r    = mdash_xlsx_read( $trav );
check( ! $r['ok'] && ! $r['sheets'], 'a sheet path outside the workbook (../../etc/passwd): not read', json_encode( $r ) );

$far = book( "$tmp/far.xlsx", array( 'menu' => '<row r="1">' . $s( 'A1', 0 ) . $s( 'XFD1', 1 ) . '</row><row r="1048576">' . $s( 'A1048576', 0 ) . '</row>' ), $strings );
$t0  = microtime( true );
$r   = mdash_xlsx_read( $far );
check( $r['ok'] && array( array( 'No.' ) ) === $r['sheets']['menu'] && microtime( true ) - $t0 < 1, 'cells at XFD1 and row 1048576: ignored, no huge grid', json_encode( $r['sheets'] ) );

$many = '';
for ( $i = 1; $i <= MDASH_XLSX_MAX_ROWS + 50; $i++ ) {
	$many .= '<row r="' . $i . '">' . $n( "A$i", $i ) . '</row>';
}
$r = mdash_xlsx_read( book( "$tmp/many.xlsx", array( 'menu' => $many ) ) );
check( $r['ok'] && MDASH_XLSX_MAX_ROWS === count( $r['sheets']['menu'] ), 'more than ' . MDASH_XLSX_MAX_ROWS . ' rows: cut at ' . MDASH_XLSX_MAX_ROWS, $r['ok'] ? count( $r['sheets']['menu'] ) : $r['error'] );

$r = mdash_xlsx_read( book( "$tmp/macro.xlsx", array( 'menu' => $menu ), $strings, array( 'xl/vbaProject.bin' => 'VBA' ) ) );
check( ! $r['ok'] && false !== strpos( $r['error'], 'macros' ), 'a workbook with macros: refused', $r['error'] );

$r = mdash_xlsx_read( book( "$tmp/formula.xlsx", array( 'menu' => '<row r="1"><c r="A1" t="str"><f>HYPERLINK("http://evil","x")</f><v>x</v></c><c r="B1"><f>1+1</f><v>2</v></c></row>' ) ) );
check( $r['ok'] && array( 'x', '2' ) === $r['sheets']['menu'][0], 'formulas are not run: the saved value is used', json_encode( $r['sheets'] ) );

foreach ( array( 'good', 'not', 'xxe', 'laughs', 'bomb', 'trav', 'far', 'many', 'macro', 'formula' ) as $x ) {
	@unlink( "$tmp/$x.xlsx" );
}
echo $failed ? "$failed check(s) failed\n" : "all checks passed\n";
exit( $failed ? 1 : 0 );
