<?php
/**
 * Reads an Excel workbook (.xlsx, e.g. exported from Numbers: File → Export To → Excel) into
 * plain rows per sheet, so each sheet can go through the same CSV parser as an uploaded CSV.
 * Sheets are matched by name: "menu" → the menu, "specials" → today's specials, "lunch" →
 * the lunch menu of the week (MenuDash Specials).
 *
 * An .xlsx is a ZIP of XML files, and uploads come from outside, so this reads defensively:
 * - only the few parts it needs, each read with a size cap (a "zip bomb" that claims a small
 *   size still stops at the cap); nothing is ever unpacked to disk;
 * - sheet paths must look like xl/worksheets/sheetN.xml (no "../" or absolute paths);
 * - XML with a DOCTYPE or ENTITY is refused (no entity tricks, no external files), and
 *   libxml runs without network access;
 * - at most MDASH_XLSX_MAX_ROWS rows and MDASH_XLSX_MAX_COLS columns per sheet; cells further
 *   out are ignored, so a cell at "XFD1048576" can't make huge arrays;
 * - formulas are never evaluated: a cell's saved value is used, as Excel shows it.
 * Plain PHP (ZipArchive, DOM), no WordPress, so dev/xlsx-test.php can test it alone.
 */

defined( 'ABSPATH' ) || defined( 'MENUDASH_PURE' ) || exit;

// Outside WordPress (the dev tests run this file alone), texts stay in English.
if ( defined( 'MENUDASH_PURE' ) && ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName -- stands in for WordPress's __().
		return $text;
	}
}

const MDASH_XLSX_MAX_PART  = 8388608;  // 8 MB per XML part (a 1'000-dish menu is about 0.5 MB).
const MDASH_XLSX_MAX_PARTS = 400;      // Entries in the ZIP.
const MDASH_XLSX_MAX_ROWS  = 3000;
const MDASH_XLSX_MAX_COLS  = 60;

/** "menu - main-menu-2026" → menu; "Specials" → specials; "Mittagsmenü" → lunch; else ''. */
function mdash_sheet_kind( $name ) {
	$n = mb_strtolower( trim( (string) $name ) );
	// Numbers names exported sheets "Sheet - Table": the sheet's own name comes first.
	$n = trim( preg_split( '/\s+-\s+/u', $n, 2 )[0] );
	if ( preg_match( '/lunch|mittag|午/u', $n ) ) {
		return 'lunch'; // Before "menu": "Mittagsmenü" contains "menü".
	}
	if ( preg_match( '/special|empfehl|empfohlen|推薦|今日/u', $n ) ) {
		return 'specials';
	}
	if ( preg_match( '/drink|getränk|bar\b|wein|wine|飲|酒/u', $n ) ) {
		return ''; // "Drink menu", "Getränkekarte": not the food menu.
	}
	if ( preg_match( '/menu|menü|speisekarte|菜單|菜单/u', $n ) ) {
		return 'menu';
	}
	return '';
}

/** One XML part of the workbook, size-capped and checked; null when missing or refused. */
function mdash_xlsx_part( $zip, $name, &$error ) {
	$stat = $zip->statName( $name );
	if ( false === $stat ) {
		return null;
	}
	if ( $stat['size'] > MDASH_XLSX_MAX_PART ) {
		$error = __( 'A part of the file is too large to be a menu.', 'menudash' );
		return null;
	}
	// Read one byte more than allowed: if it comes, the size in the ZIP header was a lie.
	$xml = $zip->getFromName( $name, MDASH_XLSX_MAX_PART + 1 );
	if ( false === $xml || strlen( $xml ) > MDASH_XLSX_MAX_PART ) {
		$error = __( 'A part of the file is too large to be a menu.', 'menudash' );
		return null;
	}
	if ( preg_match( '/<!DOCTYPE|<!ENTITY/i', $xml ) ) {
		$error = __( 'The file contains XML declarations a spreadsheet does not need; it was not read.', 'menudash' );
		return null;
	}
	return $xml;
}

/** XML text → DOMXPath with the spreadsheet namespaces as m: and r:; null when broken. */
function mdash_xlsx_xpath( $xml ) {
	$doc = new DOMDocument();
	// No LIBXML_NOENT (entities stay unexpanded) and no network. The @ keeps broken XML from
	// printing warnings; libxml_use_internal_errors() crashes Playground's PHP 7.4 here.
	if ( ! @$doc->loadXML( $xml, LIBXML_NONET | LIBXML_COMPACT ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return null;
	}
	// A DOCTYPE the byte check missed (e.g. a part saved as UTF-16) is refused here too.
	if ( null !== $doc->doctype ) {
		return null;
	}
	$x = new DOMXPath( $doc );
	$x->registerNamespace( 'm', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );
	$x->registerNamespace( 'r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
	$x->registerNamespace( 'p', 'http://schemas.openxmlformats.org/package/2006/relationships' );
	return $x;
}

/** "B7" → array( row 7, column 2 ); null for anything else. */
function mdash_xlsx_ref( $ref ) {
	if ( ! preg_match( '/^([A-Z]{1,3})(\d{1,7})$/', (string) $ref, $m ) ) {
		return null;
	}
	$col = 0;
	foreach ( str_split( $m[1] ) as $ch ) {
		$col = $col * 26 + ( ord( $ch ) - 64 );
	}
	return array( (int) $m[2], $col );
}

/** A number as a spreadsheet shows it: 8.5, 12, 23.5 (never 8.5000000001). */
function mdash_xlsx_number( $v ) {
	if ( ! is_numeric( $v ) ) {
		return (string) $v;
	}
	$f = round( (float) $v, 8 );
	if ( floor( $f ) === $f && abs( $f ) < 1e15 ) {
		return (string) (int) $f;
	}
	return rtrim( rtrim( sprintf( '%.8F', $f ), '0' ), '.' );
}

/**
 * Reads the workbook. Returns array( 'ok' => bool, 'error' => string, 'sheets' => array(
 * name => rows ) ), sheets in workbook order, rows as arrays of strings starting at the
 * first filled row and column.
 */
function mdash_xlsx_read( $path ) {
	$fail = function ( $e ) { return array( 'ok' => false, 'error' => $e, 'sheets' => array() ); };
	if ( ! class_exists( 'ZipArchive' ) ) {
		return $fail( __( 'This server cannot read Excel files (PHP\'s zip extension is missing). Export the sheet as CSV instead, or ask the host to enable "zip".', 'menudash' ) );
	}
	$h     = @fopen( $path, 'rb' ); // phpcs:ignore
	$magic = $h ? fread( $h, 4 ) : '';
	if ( $h ) {
		fclose( $h );
	}
	if ( "PK\x03\x04" !== $magic ) {
		return $fail( __( 'This is not an Excel file (.xlsx). From Numbers: File → Export To → Excel.', 'menudash' ) );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path, defined( 'ZipArchive::RDONLY' ) ? ZipArchive::RDONLY : 0 ) ) {
		return $fail( __( 'The Excel file could not be opened.', 'menudash' ) );
	}
	if ( $zip->numFiles > MDASH_XLSX_MAX_PARTS ) {
		$zip->close();
		return $fail( __( 'The file has too many parts to be a menu.', 'menudash' ) );
	}
	if ( false !== $zip->statName( 'xl/vbaProject.bin' ) ) {
		$zip->close();
		return $fail( __( 'The file contains macros (.xlsm). Save it as a normal Excel workbook (.xlsx) or export CSV.', 'menudash' ) );
	}
	$error = '';
	$wb    = mdash_xlsx_part( $zip, 'xl/workbook.xml', $error );
	$rels  = mdash_xlsx_part( $zip, 'xl/_rels/workbook.xml.rels', $error );
	$wbx   = $wb ? mdash_xlsx_xpath( $wb ) : null;
	$relx  = $rels ? mdash_xlsx_xpath( $rels ) : null;
	if ( ! $wbx || ! $relx ) {
		$zip->close();
		return $fail( '' !== $error ? $error : __( 'This does not look like an Excel workbook (.xlsx).', 'menudash' ) );
	}
	// Relationship id → part inside xl/, checked.
	$targets = array();
	$shared  = 'xl/sharedStrings.xml';
	foreach ( $relx->query( '/p:Relationships/p:Relationship' ) as $r ) {
		$t = ltrim( preg_replace( '#^/?xl/#', '', $r->getAttribute( 'Target' ) ), '/' );
		if ( preg_match( '#^worksheets/sheet\d{1,4}\.xml$#', $t ) ) {
			$targets[ $r->getAttribute( 'Id' ) ] = 'xl/' . $t;
		} elseif ( 'sharedStrings.xml' === $t ) {
			$shared = 'xl/' . $t;
		}
	}
	// Shared strings: most text cells point into this list.
	$strings = array();
	$ss      = mdash_xlsx_part( $zip, $shared, $error );
	if ( '' !== $error ) {
		$zip->close();
		return $fail( $error );
	}
	if ( $ss && ( $ssx = mdash_xlsx_xpath( $ss ) ) ) {
		foreach ( $ssx->query( '/m:sst/m:si' ) as $si ) {
			$text = '';
			// All text runs, without the phonetic guides (m:rPh) Excel keeps for East Asian text.
			foreach ( $ssx->query( './/m:t[not(ancestor::m:rPh)]', $si ) as $t ) {
				$text .= $t->textContent;
			}
			$strings[] = $text;
		}
	}
	$sheets = array();
	$read   = array();
	foreach ( $wbx->query( '/m:workbook/m:sheets/m:sheet' ) as $s ) {
		$id   = $s->getAttributeNS( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id' );
		$name = mb_substr( trim( $s->getAttribute( 'name' ) ), 0, 100 );
		if ( ! isset( $targets[ $id ] ) || isset( $sheets[ $name ] ) || isset( $read[ $targets[ $id ] ] ) ) {
			continue; // Not a worksheet (a chart), a name seen already, or a sheet file read already.
		}
		// A menu workbook has a few sheets; a file listing thousands would keep the server busy.
		if ( count( $read ) >= 20 ) {
			break;
		}
		$read[ $targets[ $id ] ] = true;
		$xml = mdash_xlsx_part( $zip, $targets[ $id ], $error );
		if ( '' !== $error ) {
			$zip->close();
			return $fail( $error );
		}
		$sx = $xml ? mdash_xlsx_xpath( $xml ) : null;
		if ( ! $sx ) {
			continue;
		}
		$grid = array();
		$next = 1;
		foreach ( $sx->query( '/m:worksheet/m:sheetData/m:row' ) as $row ) {
			$r    = $row->hasAttribute( 'r' ) ? (int) $row->getAttribute( 'r' ) : $next;
			$next = $r + 1;
			if ( $r < 1 || $r > MDASH_XLSX_MAX_ROWS ) {
				continue;
			}
			$c = 0;
			foreach ( $sx->query( 'm:c', $row ) as $cell ) {
				$ref = mdash_xlsx_ref( $cell->getAttribute( 'r' ) );
				$c   = $ref ? $ref[1] : $c + 1;
				if ( $c > MDASH_XLSX_MAX_COLS ) {
					continue;
				}
				$type = $cell->getAttribute( 't' );
				$v    = $sx->query( 'm:v', $cell )->item( 0 );
				$v    = $v ? $v->textContent : '';
				if ( 's' === $type ) {
					$value = isset( $strings[ (int) $v ] ) ? $strings[ (int) $v ] : '';
				} elseif ( 'inlineStr' === $type ) {
					$value = '';
					foreach ( $sx->query( 'm:is//m:t[not(ancestor::m:rPh)]', $cell ) as $t ) {
						$value .= $t->textContent;
					}
				} elseif ( 'b' === $type ) {
					$value = '1' === $v ? 'TRUE' : 'FALSE';
				} elseif ( 'e' === $type ) {
					$value = ''; // #DIV/0! and friends.
				} elseif ( 'str' === $type ) {
					$value = $v; // A formula's text result.
				} else {
					$value = mdash_xlsx_number( $v );
				}
				if ( '' !== $value ) {
					$grid[ $r ][ $c ] = $value;
				}
			}
		}
		$sheets[ $name ] = mdash_xlsx_rows( $grid );
	}
	$zip->close();
	if ( ! $sheets ) {
		return $fail( __( 'The workbook has no sheets with cells in them.', 'menudash' ) );
	}
	return array( 'ok' => true, 'error' => '', 'sheets' => $sheets );
}

/** Row => column => value → plain rows, from the first filled row and column to the last. */
function mdash_xlsx_rows( $grid ) {
	if ( ! $grid ) {
		return array();
	}
	$first_col = PHP_INT_MAX;
	$last_col  = 0;
	foreach ( $grid as $cells ) {
		$first_col = min( $first_col, min( array_keys( $cells ) ) );
		$last_col  = max( $last_col, max( array_keys( $cells ) ) );
	}
	$rows = array();
	for ( $r = min( array_keys( $grid ) ), $last = max( array_keys( $grid ) ); $r <= $last; $r++ ) {
		$row = array();
		for ( $c = $first_col; $c <= $last_col; $c++ ) {
			$row[] = isset( $grid[ $r ][ $c ] ) ? $grid[ $r ][ $c ] : '';
		}
		$rows[] = $row;
	}
	return $rows;
}

/**
 * Rows from the header on: Numbers puts the table's name above it ("main-menu-2026"), and
 * people often add a title row. The header is the first of the top 10 rows with a Price column.
 */
function mdash_xlsx_from_header( $rows ) {
	$price = mdash_columns()['price'];
	foreach ( array_slice( $rows, 0, 10 ) as $i => $row ) {
		foreach ( $row as $cell ) {
			if ( in_array( mdash_header_key( $cell ), $price, true ) ) {
				return array_slice( $rows, $i );
			}
		}
	}
	return $rows;
}

/**
 * The sheet for one list ('menu', 'specials', 'lunch'): the one named for it, or the only
 * sheet of a one-sheet workbook (Numbers' "Export Summary" and empty sheets don't count).
 * Returns array( name, rows ), or null.
 */
function mdash_xlsx_pick_sheet( $sheets, $kind ) {
	$real = array();
	foreach ( $sheets as $name => $rows ) {
		if ( $rows && ! preg_match( '/^export summary$/i', $name ) ) {
			$real[ $name ] = $rows;
		}
	}
	foreach ( $real as $name => $rows ) {
		if ( mdash_sheet_kind( $name ) === $kind ) {
			return array( $name, $rows );
		}
	}
	return 1 === count( $real ) ? array( key( $real ), current( $real ) ) : null;
}

/** Rows → CSV text (UTF-8, commas), for the CSV parser and the stored history. */
function mdash_rows_to_csv( $rows ) {
	$h = fopen( 'php://temp', 'w+' );
	foreach ( $rows as $row ) {
		fputcsv( $h, $row, ',', '"', '' );
	}
	rewind( $h );
	$csv = stream_get_contents( $h );
	fclose( $h );
	return $csv;
}
