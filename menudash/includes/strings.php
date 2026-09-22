<?php
/**
 * Words on the menu page itself, in the three menu languages.
 *
 * WordPress translations follow the site's one language, so they can't switch these with
 * the guest's choice; every label is printed in all three and CSS shows the chosen one.
 *
 * A site changes any of them with the menudash_strings filter, e.g. another currency:
 *
 *   add_filter( 'menudash_strings', function ( $s ) {
 *       $s['prices'] = array( 'de' => 'Alle Preise in EUR.', 'en' => 'All prices in EUR.', 'zh' => '價格以歐元計。' );
 *       return $s;
 *   } );
 */

defined( 'ABSPATH' ) || exit;

function mdash_strings() {
	static $strings = null;
	if ( null !== $strings ) {
		return $strings;
	}
	$strings = apply_filters( 'menudash_strings', array(
		'all'        => array( 'de' => 'Alle', 'en' => 'All', 'zh' => '全部' ),
		'language'   => array( 'de' => 'Sprache', 'en' => 'Language', 'zh' => '語言' ),
		'filter'     => array( 'de' => 'Filter', 'en' => 'Filter', 'zh' => '篩選' ),
		'categories' => array( 'de' => 'Kategorien', 'en' => 'Categories', 'zh' => '分類' ),
		'pick'       => array( 'de' => 'Empfohlen', 'en' => 'Recommended', 'zh' => '推薦' ),
		'veg'        => array( 'de' => 'Vegetarisch', 'en' => 'Vegetarian', 'zh' => '素食' ),
		'vegan'      => array( 'de' => 'Vegan', 'en' => 'Vegan', 'zh' => '純素' ),
		'gf'         => array( 'de' => 'Glutenfrei', 'en' => 'Gluten-free', 'zh' => '無麩質' ),
		'mild'       => array( 'de' => 'Nicht scharf', 'en' => 'Not spicy', 'zh' => '不辣' ),
		'spicy'      => array( 'de' => 'Scharf', 'en' => 'Spicy', 'zh' => '辣' ),
		'vegetarian' => array( 'de' => 'Vegetarisch', 'en' => 'Vegetarian', 'zh' => '素食' ),
		'none'       => array( 'de' => 'Keine Gerichte passen zu diesen Filtern.', 'en' => 'No dishes match these filters.', 'zh' => '沒有符合篩選條件的菜式。' ),
		'reset'      => array( 'de' => 'Filter zurücksetzen', 'en' => 'Clear filters', 'zh' => '清除篩選' ),
		'close'      => array( 'de' => 'Schliessen', 'en' => 'Close', 'zh' => '關閉' ),
		'photo'      => array( 'de' => 'Foto', 'en' => 'Photo', 'zh' => '照片' ),
		'prices'     => array( 'de' => 'Alle Preise in CHF inkl. MwSt.', 'en' => 'All prices in CHF incl. VAT.', 'zh' => '所有價格以瑞士法郎計，已含增值稅。' ),
		'allergy'    => array( 'de' => 'Fragen Sie unser Team nach Allergenen.', 'en' => 'Please ask our team about allergens.', 'zh' => '如有食物過敏，請告知我們的員工。' ),
		'empty'      => array( 'de' => 'Die Speisekarte wird gerade aktualisiert.', 'en' => 'The menu is being updated.', 'zh' => '菜單更新中。' ),
	) );
	return $strings;
}

/** Language buttons in their order on the page: code => label. German first. */
function mdash_lang_buttons() {
	return array( 'de' => 'DE', 'en' => 'EN', 'zh' => '中文' );
}

/** HTML lang attribute per menu language; the Chinese on this menu is Traditional. */
function mdash_html_lang( $l ) {
	$map = array( 'en' => 'en', 'de' => 'de', 'zh' => 'zh-Hant' );
	return $map[ $l ];
}
