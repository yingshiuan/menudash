<?php
/**
 * Tests for menudash/includes/svg-clean.php: uploaded SVG icons keep their drawing and
 * lose everything that could run or load something.
 *
 *   dev/test.sh
 */

define( 'MENUDASH_PURE', true );
require '/repo/menudash/includes/svg-clean.php';

$failed = 0;
function check( $ok, $what, $detail = '' ) {
	global $failed;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $what . ( $ok || '' === $detail ? '' : "\n     $detail" ) . "\n";
	$failed += $ok ? 0 : 1;
}

// A Figma export: the drawing wrapped in a mask that clips nothing, with its own colours.
$figma = '<svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
<mask id="mask0_1_2" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="48" height="48"><rect width="48" height="48" fill="#D9D9D9"/></mask>
<g mask="url(#mask0_1_2)"><path d="M24 42C16 42 10 35 10 28S16 6 24 6s14 14 14 22-6 14-14 14Z" fill="#1C1B1F"/><circle cx="24" cy="29" r="8" fill="#F2C94C"/></g>
</svg>';
$r = mdash_clean_svg( $figma );
check( $r['ok'] && '0 0 48 48' === $r['viewBox'], 'Figma export is accepted, viewBox kept', $r['error'] );
check( false !== strpos( $r['body'], 'fill="#1C1B1F"' ) && false !== strpos( $r['body'], '<circle cx="24" cy="29" r="8" fill="#F2C94C"/>' ), 'shapes keep their own colours', $r['body'] );
check( false === stripos( $r['body'], 'mask' ) && false === strpos( $r['body'], '#D9D9D9' ), 'the mask and its rectangle are dropped', $r['body'] );

// Everything that could run code or fetch something.
$evil = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24" onload="alert(1)">
<script>alert(1)</script>
<style>path { fill: red }</style>
<path d="M1 1h2" onclick="steal()" onmouseover="steal()"/>
<a href="javascript:alert(1)"><path d="M9 9h1"/></a>
<foreignObject width="10" height="10"><div xmlns="http://www.w3.org/1999/xhtml">hi</div></foreignObject>
<image href="https://example.com/track.png" width="1" height="1"/>
<use xlink:href="#other"/>
<path d="M2 2h2" fill="url(https://example.com/x)" stroke="javascript:alert(1)"/>
<path d="M3 3h2" fill="&quot; onload=&quot;alert(1)"/>
<text x="0" y="10">hello</text>
</svg>';
$r = mdash_clean_svg( $evil );
check( $r['ok'], 'hostile SVG with a real drawing is accepted after cleaning', $r['error'] );
$bad = array();
foreach ( array( 'script', 'style', 'onload', 'onclick', 'onmouseover', 'javascript', 'href', 'foreignobject', 'image', 'use', 'url(', 'text', 'hello', 'alert', '<a', '"' . ' on' ) as $needle ) {
	if ( false !== stripos( $r['body'], $needle ) ) {
		$bad[] = $needle;
	}
}
check( ! $bad, 'no script, handler, link, style, embedded HTML, image or url() survives', 'found: ' . implode( ', ', $bad ) . "\n     " . $r['body'] );
check( '<path d="M1 1h2"/><path d="M2 2h2"/><path d="M3 3h2"/>' === $r['body'], 'exactly the three plain paths remain (link content dropped)', $r['body'] );

// Entity tricks (XXE, "billion laughs") are refused outright.
$xxe = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><path d="M0 0" fill="&x;"/></svg>';
check( ! mdash_clean_svg( $xxe )['ok'], 'DOCTYPE / entities are refused' );
$utf16 = "\xFF\xFE" . mb_convert_encoding( '<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE svg [<!ENTITY x "#e33">]><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><path d="M0 0" fill="&x;"/></svg>', 'UTF-16LE', 'UTF-8' );
check( ! mdash_clean_svg( $utf16 )['ok'], 'a DOCTYPE hidden in UTF-16 is refused too' );

// CSS escapes: browsers read "\75rl(" in a paint value as "url(".
$esc = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1h2" fill="\75rl(https://example.com/a.svg#p)"/><path d="M2 2h2" style="fill:u\rl(#x);stroke:#e33;stroke-width:1.5"/><rect width="4" height="4" fill="rgb(10, 20, 30)" transform="translate(2 3) rotate(-45)"/></svg>';
$r = mdash_clean_svg( $esc );
check( $r['ok'] && false === strpos( $r['body'], '\\' ) && false === stripos( $r['body'], 'rl(' ), 'CSS-escaped url() values are dropped', $r['body'] );
check( false !== strpos( $r['body'], 'stroke="#e33"' ) && false !== strpos( $r['body'], 'stroke-width="1.5"' ) && false !== strpos( $r['body'], 'fill="rgb(10, 20, 30)"' ) && false !== strpos( $r['body'], 'transform="translate(2 3) rotate(-45)"' ), 'ordinary colours and transforms are kept', $r['body'] );

check( ! mdash_clean_svg( '<html><body>not an icon</body></html>' )['ok'], 'HTML is refused' );
check( ! mdash_clean_svg( 'not xml at all' )['ok'], 'broken XML is refused' );
check( ! mdash_clean_svg( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><script>x</script></svg>' )['ok'], 'an SVG with nothing to draw is refused' );
check( ! mdash_clean_svg( str_repeat( ' ', 210 * 1024 ) . '<svg/>' )['ok'], 'a file over 200 KB is refused' );

$sized = mdash_clean_svg( '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="20"><rect width="32" height="20"/></svg>' );
check( $sized['ok'] && '0 0 32 20' === $sized['viewBox'], 'no viewBox: it is taken from width and height', $sized['viewBox'] );

$styled = mdash_clean_svg( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h5" fill="#000" style="fill:#e33;stroke:none;stroke-width:2"/><path d="M1 1" style="fill:url(#g)"/></svg>' );
check( $styled['ok'] && false !== strpos( $styled['body'], 'fill="#e33"' ) && false === strpos( $styled['body'], '#000' ) && false !== strpos( $styled['body'], 'stroke-width="2"' ), 'paint in style="" is kept and wins over the attribute, as in CSS', $styled['body'] );
check( false === strpos( $styled['body'], 'url(' ), 'a url() inside style="" is dropped', $styled['body'] );

$again = mdash_clean_svg( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . $r['viewBox'] . '">' . $r['body'] . '</svg>' );
check( $again['ok'] && $again['body'] === $r['body'], 'cleaning is stable: the output cleans to itself' );

echo $failed ? "\n$failed check(s) failed\n" : "\nall checks passed\n";
exit( $failed ? 1 : 0 );
