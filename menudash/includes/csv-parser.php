<?php
/**
 * CSV -> menu. Pure PHP, no WordPress calls, so dev/parser-test.php can run it on its own.
 *
 * The restaurant keeps its menu in a spreadsheet and exports it as CSV. Columns are found by their header name,
 * never by position, so they can be reordered or new ones added. A row with a name but no
 * No. and no Price is a category heading; every other named row is a dish.
 */

defined( 'MENUDASH_PURE' ) || defined( 'ABSPATH' ) || exit;

const MDASH_LANGS = array( 'en', 'de', 'zh' );

/** Header aliases, compared after mdash_header_key(): lowercase, letters and digits only. */
function mdash_columns() {
	return array(
		'no'         => array( 'no', 'nr', 'number', 'nummer', 'num' ),
		'price'      => array( 'price', 'preis', 'pricechf', 'preischf', 'chf' ),
		'measure'    => array( 'measure', 'menge', 'unit', 'einheit', 'size', 'grösse', 'groesse', 'portion' ),
		'name_en'    => array( 'nameen', 'nameenglish', 'englishname', 'name' ),
		'name_de'    => array( 'namede', 'namedeutsch', 'germanname', 'deutschername', 'bezeichnung' ),
		'name_zh'    => array( 'namezh', 'namecn', 'namechinese', 'chinesename', 'name中文', '中文', '中文名' ),
		'desc_en'    => array( 'descriptionen', 'descen', 'descriptionenglish', 'description' ),
		'desc_de'    => array( 'descriptionde', 'descde', 'beschreibungde', 'beschreibung', 'descriptiondeutsch' ),
		'desc_zh'    => array( 'descriptionzh', 'desczh', 'descriptioncn', 'descriptionchinese' ),
		'pick'       => array( 'recommended', 'recommend', 'empfohlen', 'empfehlung', 'pick' ),
		'spicy'      => array( 'spicy', 'scharf', 'hot' ),
		'vegan'      => array( 'vegan' ),
		'vegetarian' => array( 'vegetarian', 'vegetarisch', 'veggie' ),
		'gf'         => array( 'glutenfree', 'glutenfrei', 'gf' ),
		'photo'      => array( 'photo', 'picture', 'image', 'foto', 'bild' ),
	);
}

const MDASH_FLAGS = array( 'pick', 'spicy', 'vegan', 'vegetarian', 'gf' );

function mdash_header_key( $s ) {
	$s = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s, 'UTF-8' ) : strtolower( $s );
	return preg_replace( '/[^\p{L}\p{N}]+/u', '', $s );
}

/** Lowercase, accents folded, punctuation and spaces dropped. Chinese characters survive. */
function mdash_fold( $s ) {
	if ( class_exists( 'Normalizer' ) ) {
		$s = Normalizer::normalize( $s, Normalizer::FORM_D );
		$s = preg_replace( '/\p{Mn}+/u', '', $s );
	} else {
		$s = strtr( $s, array( 'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'Ä' => 'a', 'Ö' => 'o', 'Ü' => 'u', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ç' => 'c', 'ß' => 'ss' ) );
	}
	return mdash_header_key( $s );
}

/** "Vegetable Jiaozi - 10 pcs." -> "Vegetable Jiaozi": a portion or note after " - " is not part of the name. */
function mdash_base_name( $name ) {
	return trim( preg_replace( '/\s+[-–—]\s+.*$/u', '', $name ) );
}

/** Bytes as the owner's program wrote them -> UTF-8 text with \n line ends. */
function mdash_decode( $bytes, &$warnings ) {
	if ( substr( $bytes, 0, 3 ) === "\xEF\xBB\xBF" ) {
		$bytes = substr( $bytes, 3 );
	} elseif ( substr( $bytes, 0, 2 ) === "\xFF\xFE" || substr( $bytes, 0, 2 ) === "\xFE\xFF" ) {
		// Excel's "Unicode Text" export.
		$enc   = substr( $bytes, 0, 2 ) === "\xFF\xFE" ? 'UTF-16LE' : 'UTF-16BE';
		$bytes = mdash_convert( substr( $bytes, 2 ), $enc );
	}
	if ( ! preg_match( '//u', $bytes ) ) {
		// Not UTF-8: Windows Excel writes Windows-1252, old Mac programs MacRoman. Their
		// umlauts sit on different bytes, so count which set this file uses.
		$win = preg_match_all( '/[\xE4\xF6\xFC\xC4\xD6\xDC\xE9\xE8\xE0]/', $bytes );
		$mac = preg_match_all( '/[\x8A\x9A\x9F\x80\x85\x86\x8E\x8F\x88]/', $bytes );
		$enc = $mac > $win ? 'MACINTOSH' : 'Windows-1252';
		$out = mdash_convert( $bytes, $enc );
		if ( null === $out ) {
			$warnings[] = 'The file is not UTF-8 and this server cannot convert it. Export the CSV again as "Unicode (UTF-8)".';
			$out        = preg_replace( '/[\x80-\xFF]/', '?', $bytes );
		} else {
			$warnings[] = "The file was saved as $enc, not UTF-8, so it cannot hold Chinese characters. Export it again as \"Unicode (UTF-8)\" to keep the Chinese names.";
		}
		$bytes = $out;
	}
	return str_replace( array( "\r\n", "\r" ), "\n", $bytes );
}

function mdash_convert( $bytes, $from ) {
	if ( 'MACINTOSH' !== $from && function_exists( 'mb_convert_encoding' ) ) {
		return mb_convert_encoding( $bytes, 'UTF-8', $from );
	}
	if ( function_exists( 'iconv' ) ) {
		$out = @iconv( $from, 'UTF-8//TRANSLIT', $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false !== $out ) {
			return $out;
		}
	}
	return null;
}

/** Comma, semicolon (Swiss/German Excel) or tab: whichever the header line uses most. */
function mdash_delimiter( $text ) {
	$line = strtok( $text, "\n" );
	$line = preg_replace( '/"[^"]*"/', '', (string) $line );
	$best = ',';
	$max  = 0;
	foreach ( array( ',', ';', "\t" ) as $d ) {
		$n = substr_count( $line, $d );
		if ( $n > $max ) {
			$max  = $n;
			$best = $d;
		}
	}
	return $best;
}

/** "8.5", "18,50", "18.–", "CHF 12", "Fr. 9.-" -> 18.5; null when it isn't a price. */
function mdash_price( $s ) {
	$s = trim( preg_replace( '/^(chf|fr\.?|sfr\.?)\s*|\s*(chf|fr\.?)$/i', '', trim( $s ) ) );
	$s = str_replace( array( "'", '’', ' ' ), '', $s );
	if ( preg_match( '/^(\d+)(?:[.,](\d{1,2}|[-–—]{1,2}))?$/u', $s, $m ) ) {
		$cents = isset( $m[2] ) && ctype_digit( $m[2] ) ? str_pad( $m[2], 2, '0' ) : '00';
		return round( (float) ( $m[1] . '.' . $cents ), 2 );
	}
	// Numbers can store 26.500000000000004; anything that is a plain number is fine.
	if ( is_numeric( $s ) ) {
		return round( (float) $s, 2 );
	}
	return null;
}

/** 23.5 -> "23.5", 12 -> "12", 5.8 -> "5.8": written as on the printed menu, no trailing zeros. */
function mdash_price_text( $price ) {
	return rtrim( rtrim( number_format( $price, 2, '.', '' ), '0' ), '.' );
}

/** "021" -> "21", "22.0" -> "22", "-" -> "" (the old sheet used a dash for "no number"). */
function mdash_dish_no( $s ) {
	$s = trim( $s );
	if ( preg_match( '/^0*(\d+)(?:\.0+)?$/', $s, $m ) ) {
		return $m[1];
	}
	return preg_match( '/^[-–—]*$/u', $s ) ? '' : $s;
}

function mdash_mark( $s ) {
	$s = mdash_header_key( $s );
	return '' !== $s && ! in_array( $s, array( '0', 'no', 'nein', 'false', 'falsch' ), true );
}

function mdash_slug( $s ) {
	$s = trim( preg_replace( '/[^a-z0-9]+/', '-', mdash_fold_keep_spaces( $s ) ), '-' );
	return '' === $s ? 'x' : substr( $s, 0, 40 );
}

function mdash_fold_keep_spaces( $s ) {
	return implode( ' ', array_map( 'mdash_fold', preg_split( '/\s+/u', $s ) ) );
}

/**
 * Parse CSV bytes into the menu structure.
 *
 * Returns array( 'ok' => bool, 'error' => string, 'menu' => array, 'warnings' => string[] ).
 * The menu is only usable when ok is true: the name and price columns were found and at
 * least one dish was read. That keeps a wrong file (the gift-card CSV) off the live page.
 */
function mdash_parse_csv( $bytes ) {
	$warnings = array();
	$text     = mdash_decode( $bytes, $warnings );
	$delim    = mdash_delimiter( $text );

	$h = fopen( 'php://temp', 'w+' );
	fwrite( $h, $text );
	rewind( $h );
	$rows = array();
	while ( ( $row = fgetcsv( $h, 0, $delim, '"', '' ) ) !== false ) {
		$rows[] = $row;
	}
	fclose( $h );

	$fail = function ( $error ) use ( &$warnings ) {
		return array( 'ok' => false, 'error' => $error, 'menu' => null, 'warnings' => $warnings );
	};
	if ( ! $rows ) {
		return $fail( 'The file is empty.' );
	}

	// Map header cells to fields. The first matching column wins; a plain "Name" or
	// "Description" only counts when no language-specific column claimed that field.
	$header = array_map( 'trim', $rows[0] );
	$col    = array();
	foreach ( mdash_columns() as $field => $aliases ) {
		foreach ( $aliases as $alias ) {
			foreach ( $header as $i => $cell ) {
				if ( mdash_header_key( $cell ) === $alias && ! in_array( $i, $col, true ) ) {
					$col[ $field ] = $i;
					break 2;
				}
			}
		}
	}
	$has_name = isset( $col['name_en'] ) || isset( $col['name_de'] ) || isset( $col['name_zh'] );
	if ( ! $has_name || ! isset( $col['price'] ) ) {
		$seen = implode( ', ', array_filter( $header, 'strlen' ) );
		return $fail( 'This does not look like the menu file: it needs a "Price" column and at least one of "Name (EN)", "Name (DE)", "Name (ZH)". Columns found: ' . ( '' === $seen ? 'none' : $seen ) . '.' );
	}
	foreach ( array( 'name_en' => 'Name (EN)', 'name_de' => 'Name (DE)', 'name_zh' => 'Name (ZH)' ) as $field => $label ) {
		if ( ! isset( $col[ $field ] ) ) {
			$warnings[] = "No \"$label\" column; that language falls back to the others.";
		}
	}

	// A menu cell is a few words; 1000 characters keeps the text handling on the page fast
	// whatever the file holds.
	$cell = function ( $row, $field ) use ( $col ) {
		$s = isset( $col[ $field ], $row[ $col[ $field ] ] ) ? trim( $row[ $col[ $field ] ] ) : '';
		return strlen( $s ) > 1000 ? trim( preg_replace( '/^(.{0,1000}).*$/su', '$1', $s ) ) : $s;
	};

	$sections = array();
	$current  = null;
	$keys     = array();
	$numbers  = array();
	$count    = 0;
	foreach ( array_slice( $rows, 1 ) as $n => $row ) {
		$line = $n + 2; // Spreadsheet row number, header is row 1.
		$name = array();
		foreach ( MDASH_LANGS as $l ) {
			$name[ $l ] = $cell( $row, "name_$l" );
		}
		$no        = mdash_dish_no( $cell( $row, 'no' ) );
		$price_raw = $cell( $row, 'price' );

		if ( '' === implode( '', $name ) ) {
			if ( '' !== $no || '' !== $price_raw ) {
				$warnings[] = "Row $line has a number or price but no name, so it was skipped.";
			}
			continue;
		}

		if ( '' === $no && '' === $price_raw ) {
			if ( null !== $current ) {
				$sections[] = $current;
			}
			$current = array(
				'id'     => mdash_unique( 'sec-' . mdash_slug( $name['en'] ? $name['en'] : ( $name['de'] ? $name['de'] : 'section' ) ), $keys ),
				'name'   => $name,
				'dishes' => array(),
			);
			continue;
		}

		$price = '' === $price_raw ? null : mdash_price( $price_raw );
		if ( '' !== $price_raw && null === $price ) {
			$warnings[] = "Row $line (" . mdash_label( $name, $no ) . "): the price \"$price_raw\" is not a number; it is shown as written.";
		}
		$dish = array(
			'no'      => $no,
			'price'   => null === $price ? $price_raw : mdash_price_text( $price ),
			'measure' => $cell( $row, 'measure' ),
			'name'    => $name,
			'desc'    => array(),
			'flags'   => array(),
			'photo'   => $cell( $row, 'photo' ),
			'row'     => $line,
		);
		foreach ( MDASH_LANGS as $l ) {
			$dish['desc'][ $l ] = $cell( $row, "desc_$l" );
		}
		foreach ( MDASH_FLAGS as $f ) {
			if ( mdash_mark( $cell( $row, $f ) ) ) {
				$dish['flags'][] = $f;
			}
		}
		$dish['key'] = mdash_unique( '' !== $no ? 'n' . mdash_slug( $no ) : mdash_slug( mdash_base_name( $name['en'] ? $name['en'] : ( $name['de'] ? $name['de'] : $name['zh'] ) ) ), $keys );

		if ( '' !== $no ) {
			if ( isset( $numbers[ $no ] ) ) {
				$warnings[] = "Nr. $no is used twice (rows {$numbers[$no]} and $line). Both are shown; a photo named \"{$no}_…\" goes to the first.";
			} else {
				$numbers[ $no ] = $line;
			}
		}
		if ( null === $current ) {
			$warnings[] = "Row $line (" . mdash_label( $name, $no ) . ') comes before the first category heading.';
			$current    = array( 'id' => 'sec-menu', 'name' => array( 'en' => '', 'de' => '', 'zh' => '' ), 'dishes' => array() );
		}
		$current['dishes'][] = $dish;
		++$count;
	}
	if ( null !== $current ) {
		$sections[] = $current;
	}
	$sections = array_values( array_filter( $sections, function ( $s ) { return (bool) $s['dishes']; } ) );

	if ( ! $count ) {
		return $fail( 'No dishes were found. A dish row needs a name and a price or number.' );
	}

	mdash_check_twins( $sections, $warnings );

	return array(
		'ok'       => true,
		'error'    => '',
		'warnings' => $warnings,
		'menu'     => array(
			'sections' => $sections,
			'dishes'   => $count,
		),
	);
}

function mdash_unique( $key, &$taken ) {
	$k = $key;
	for ( $i = 2; isset( $taken[ $k ] ); $i++ ) {
		$k = "$key-$i";
	}
	$taken[ $k ] = true;
	return $k;
}

function mdash_label( $name, $no = '' ) {
	$n = $name['en'] ? $name['en'] : ( $name['de'] ? $name['de'] : $name['zh'] );
	return ( '' !== $no ? "Nr. $no " : '' ) . $n;
}

/** The same dish listed twice with different diet marks: one of the two is probably wrong. */
function mdash_check_twins( $sections, &$warnings ) {
	$seen = array();
	foreach ( $sections as $s ) {
		foreach ( $s['dishes'] as $d ) {
			$k = mdash_fold( mdash_base_name( $d['name']['en'] ? $d['name']['en'] : $d['name']['de'] ) );
			if ( isset( $seen[ $k ] ) ) {
				list( $row, $flags ) = $seen[ $k ];
				if ( $flags !== $d['flags'] ) {
					$warnings[] = sprintf(
						'"%s" is listed twice (rows %d and %d) with different marks: %s vs %s.',
						mdash_label( $d['name'] ),
						$row,
						$d['row'],
						$flags ? implode( ', ', $flags ) : 'none',
						$d['flags'] ? implode( ', ', $d['flags'] ) : 'none'
					);
				}
			} else {
				$seen[ $k ] = array( $d['row'], $d['flags'] );
			}
		}
	}
}

/**
 * Pair photos with dishes.
 *
 * $photos: id => array( 'name' => original file name, 'time' => upload time ).
 * A photo goes to the dish named in that dish's Photo column; otherwise to the dish whose
 * number starts the file name ("22_Vegetable Jiaozi.png"); otherwise to the dish whose
 * EN, DE or ZH name the file name is ("Sambal Udang.png"). One exception: when the name
 * part is exactly another dish's name, the number is taken to be left over from an older
 * menu and the name wins ("03_Congee.png" goes to Congee, now Nr. 4, not to Nr. 3).
 * When two photos fit one dish, the newer upload wins and the other is reported as spare.
 *
 * Returns array( 'dish' => key => photo id, 'unmatched' => ids, 'spare' => id => dish key ).
 */
function mdash_match_photos( $menu, $photos ) {
	$by_no    = array(); // number => first dish with it
	$by_name  = array(); // folded name => first dish with it
	$names_of = array(); // dish key => its folded names
	$wanted   = array(); // folded Photo-column file name => dish keys
	foreach ( $menu['sections'] as $s ) {
		foreach ( $s['dishes'] as $d ) {
			if ( '' !== $d['no'] && ! isset( $by_no[ $d['no'] ] ) ) {
				$by_no[ $d['no'] ] = $d['key'];
			}
			foreach ( MDASH_LANGS as $l ) {
				$k = mdash_fold( mdash_base_name( $d['name'][ $l ] ) );
				if ( '' !== $k ) {
					$names_of[ $d['key'] ][ $k ] = true;
					if ( ! isset( $by_name[ $k ] ) ) {
						$by_name[ $k ] = $d['key'];
					}
				}
			}
			if ( '' !== $d['photo'] ) {
				$wanted[ mdash_fold( preg_replace( '/\.[a-z0-9]{2,5}$/i', '', $d['photo'] ) ) ][] = $d['key'];
			}
		}
	}

	$target = array(); // photo id => dish key
	foreach ( $photos as $id => $p ) {
		$base = preg_replace( '/\.[a-z0-9]{2,5}$/i', '', $p['name'] );
		$file = mdash_fold( $base );
		if ( isset( $wanted[ $file ] ) ) {
			$target[ $id ] = $wanted[ $file ][0];
			continue;
		}
		$no   = '';
		$rest = $base;
		if ( preg_match( '/^0*(\d+)(?:$|[\s_.\-]+(.*)$)/u', $base, $m ) ) {
			$no   = $m[1];
			$rest = isset( $m[2] ) ? $m[2] : '';
		}
		// "117_Homemade Egg Tofu with Vegetables : 時蔬蛋豆腐" names the dish twice.
		$named = null;
		$keys  = array();
		foreach ( preg_split( '/\s*[:\/|]\s*/u', $rest ) as $part ) {
			$k = mdash_fold( mdash_base_name( $part ) );
			if ( '' !== $k ) {
				$keys[] = $k;
				if ( ! $named && isset( $by_name[ $k ] ) ) {
					$named = $by_name[ $k ];
				}
			}
		}
		$numbered = '' !== $no && isset( $by_no[ $no ] ) ? $by_no[ $no ] : null;
		if ( $numbered && $named && $numbered !== $named ) {
			$same = array_intersect( $keys, array_keys( $names_of[ $numbered ] ) );
			$target[ $id ] = $same ? $numbered : $named;
		} elseif ( $numbered || $named ) {
			$target[ $id ] = $numbered ? $numbered : $named;
		}
	}

	$cands = array();
	foreach ( $target as $id => $key ) {
		$cands[ $key ][] = $id;
	}
	$dish  = array();
	$spare = array();
	foreach ( $cands as $key => $ids ) {
		usort( $ids, function ( $a, $b ) use ( $photos ) { return $photos[ $b ]['time'] <=> $photos[ $a ]['time']; } );
		$dish[ $key ] = $ids[0];
		foreach ( array_slice( $ids, 1 ) as $id ) {
			$spare[ $id ] = $key;
		}
	}
	$unmatched = array_values( array_diff( array_keys( $photos ), array_keys( $target ) ) );
	return array( 'dish' => $dish, 'unmatched' => $unmatched, 'spare' => $spare );
}
