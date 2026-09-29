<?php
/**
 * Tests for menudash/includes/origin.php: typed countries become codes and come back in
 * each menu language; empty and unknown input is handled.
 *
 *   dev/test.sh
 */

define( 'ABSPATH', '/' );
// The few WordPress functions origin.php uses, as plain PHP.
function add_shortcode() {}
function add_action() {}
function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( $s ) ) ); }
function esc_html( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
$GLOBALS['mdash_test_option'] = false;
function get_option() { return $GLOBALS['mdash_test_option']; }
function shortcode_atts( $defaults, $atts ) { return array_merge( $defaults, array_intersect_key( (array) $atts, $defaults ) ); }
function wp_enqueue_style() {}
define( 'MENUDASH_URL', '/' );
define( 'MENUDASH_VERSION', 'test' );
function mdash_html_lang( $l ) { return 'zh' === $l ? 'zh-Hant' : $l; }
function mdash_tel( $p ) { return preg_replace( '/[^0-9+]/', '', $p ); }
$GLOBALS['mdash_test_phone'] = '';
function mdash_detail() { return $GLOBALS['mdash_test_phone']; }
function mdash_strings() {
	return array(
		'origin_ask'    => array( 'de' => 'Fragen Sie bitte unser Personal', 'en' => 'Please ask our staff', 'zh' => '請向我們的員工查詢' ),
		'origin_title'  => array( 'de' => 'Herkunft', 'en' => 'Origin', 'zh' => '產地' ),
		'allergy_title' => array( 'de' => 'Allergiehinweis', 'en' => 'Allergy information', 'zh' => '過敏原說明' ),
		'allergy'       => array( 'de' => 'Fragen Sie unsere Mitarbeitenden.', 'en' => 'Ask our staff.', 'zh' => '請向員工查詢。' ),
		'allergy_phone' => array( 'de' => 'Fragen Sie unsere Mitarbeitenden oder unter %s.', 'en' => 'Ask our staff or call %s.', 'zh' => '請向員工查詢，或致電 %s。' ),
	);
}
require '/repo/menudash/includes/origin.php';

$failed = 0;
function check( $ok, $what, $detail = '' ) {
	global $failed;
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $what . ( $ok || '' === $detail ? '' : "\n     $detail" ) . "\n";
	$failed += $ok ? 0 : 1;
}

check( 'CH' === mdash_origin_country_code( 'Schweiz' ) && 'CH' === mdash_origin_country_code( ' switzerland ' ) && 'CH' === mdash_origin_country_code( 'ch' ), 'Schweiz / switzerland / ch are Switzerland' );
check( 'GB' === mdash_origin_country_code( 'England' ) && 'TR' === mdash_origin_country_code( 'Türkiye' ), 'other ways of writing a country' );
check( null === mdash_origin_country_code( 'Atlantis' ), 'unknown country: no code' );

list( $o, $problems ) = mdash_origin_clean(
	array(
		'menu' => '1',
		'rows' => array(
			array( 'product' => 'beef', 'countries' => 'Paraguay, Deutschland und Schweiz' ),
			array( 'product' => 'chicken', 'countries' => 'Schweiz; schweiz' ),
			array( 'product' => 'fish', 'countries' => '', 'ask' => '1' ),
			array( 'product' => 'own', 'own' => array( 'de' => 'Frösche', 'en' => '', 'zh' => '' ), 'countries' => 'Atlantis / FR' ),
			array( 'product' => 'pork', 'countries' => '' ),
			array( 'product' => '', 'countries' => 'Schweiz' ),
			array( 'product' => 'own', 'own' => array( 'de' => '', 'en' => '', 'zh' => '' ), 'countries' => 'CH' ),
			array( 'product' => 'lion', 'countries' => 'CH' ),
			'not a row',
		),
	)
);
check( 5 === count( $o['rows'] ), 'empty, unchosen and unknown products are left out (5 rows)', count( $o['rows'] ) );
check( array( 'PY', 'DE', 'CH' ) === $o['rows'][0]['countries'], '"Paraguay, Deutschland und Schweiz" → PY, DE, CH', implode( ',', $o['rows'][0]['countries'] ) );
check( array( 'CH' ) === $o['rows'][1]['countries'], 'the same country twice counts once' );
check( array( 'raw:Atlantis', 'FR' ) === $o['rows'][3]['countries'], 'unknown country kept as typed' );
check( $o['rows'][4]['ask'] && 1 === count( $problems ) && false !== strpos( $problems[0], 'Schweinefleisch' ), 'no countries and no tick: "ask our staff" and a warning', implode( ' ', $problems ) );
check( true === $o['menu'], 'shown under the menu' );

$GLOBALS['mdash_test_option'] = $o;
check( array( 'Rindfleisch', 'Paraguay, Deutschland, Schweiz' ) === mdash_origin_row_text( $o['rows'][0], 'de' ), 'German: Rindfleisch – Paraguay, Deutschland, Schweiz' );
check( array( 'Beef', 'Paraguay, Germany, Switzerland' ) === mdash_origin_row_text( $o['rows'][0], 'en' ), 'English: Beef – Paraguay, Germany, Switzerland' );
check( array( '牛肉', '巴拉圭、德國、瑞士' ) === mdash_origin_row_text( $o['rows'][0], 'zh' ), 'Chinese joined with 、' );
check( array( 'Fish', 'Please ask our staff' ) === mdash_origin_row_text( $o['rows'][2], 'en' ), 'fish: please ask our staff' );
check( array( 'Frösche', 'Atlantis, France' ) === mdash_origin_row_text( $o['rows'][3], 'en' ), 'own product without English: the German name' );
check( 'Paraguay, Deutschland, Schweiz' === mdash_origin_countries_text( $o['rows'][0] ), 'the form shows the countries in German again' );

list( $x ) = mdash_origin_clean( array( 'rows' => array( array( 'product' => 'own', 'own' => array( 'de' => '<b>Wachtel</b>' ), 'countries' => '<script>x</script>Frankreich' ) ) ) );
check( 'Wachtel' === $x['rows'][0]['own']['de'] && false === $x['menu'], 'tags removed; "under the menu" off when not ticked' );
$GLOBALS['mdash_test_option'] = array( 'rows' => array( array( 'product' => 'own', 'own' => array( 'de' => 'A&B', 'en' => '', 'zh' => '' ), 'countries' => array( 'raw:<i>' ), 'ask' => false ) ) );
$t = mdash_origin_table( 'de' );
check( false !== strpos( $t, 'A&amp;B' ) && false !== strpos( $t, '&lt;i&gt;' ), 'table output is escaped', $t );

$GLOBALS['mdash_test_option'] = array( 'rows' => array( array( 'product' => 'beef', 'own' => array(), 'countries' => array( 'CH' ), 'ask' => false ) ) );
$h = mdash_origin_shortcode( array( 'lang' => 'de', 'title' => 'no' ) );
check( false !== strpos( $h, 'Rindfleisch' ) && false === strpos( $h, 'Allergiehinweis' ) && false === strpos( $h, '<h3' ), 'shortcode: no heading, no allergy note unless asked', $h );
$h = mdash_origin_shortcode( array( 'lang' => 'de', 'title' => 'no', 'allergy' => 'yes' ) );
check( strpos( $h, '</table>' ) < strpos( $h, 'Allergiehinweis' ) && false !== strpos( $h, 'Fragen Sie unsere Mitarbeitenden.' ) && false === strpos( $h, 'Allergy' ), 'shortcode allergy="yes": German note under the list', $h );
$GLOBALS['mdash_test_phone'] = '044 123 45 67';
$h = mdash_origin_shortcode( array( 'allergy' => 'yes' ) );
check( 3 === substr_count( $h, 'mdash-allergy' ) && false !== strpos( $h, '<a href="tel:0441234567">044 123 45 67</a>' ), 'all three languages, with the phone number', $h );
$GLOBALS['mdash_test_phone'] = '';
$GLOBALS['mdash_test_option'] = false;
check( '' === mdash_origin_table( 'de' ), 'nothing saved: no table' );

echo $failed ? "$failed check(s) failed\n" : "all checks passed\n";
exit( $failed ? 1 : 0 );
