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
function mdash_strings() {
	return array( 'origin_ask' => array( 'de' => 'Fragen Sie bitte unser Personal', 'en' => 'Please ask our staff', 'zh' => '請向我們的員工查詢' ) );
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
$GLOBALS['mdash_test_option'] = false;
check( '' === mdash_origin_table( 'de' ), 'nothing saved: no table' );

echo $failed ? "$failed check(s) failed\n" : "all checks passed\n";
exit( $failed ? 1 : 0 );
