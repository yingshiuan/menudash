<?php
/**
 * The menu for search engines (schema.org Menu, as JSON-LD): every category and dish with
 * its names in all three languages, description, price, diet marks and photo, so Google
 * reads the dishes from the menu page as data and not only as text.
 *
 * [menudash] collects it, and so do the boxes of the Specials add-on ([menudash_specials],
 * [menudash_lunch]) with mdash_menu_schema_add(): each box is its own Menu. They are printed
 * once, in the footer: a <script> in the page content would go through wpautop and the
 * bracket encoding of MenuDash's blocks. Sites can change them with the menudash_menu_schema
 * filter, or return false to leave them out.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_footer', 'mdash_menu_schema_print', 20 );

/**
 * Remembers a box's dishes for the footer, once per kind and page.
 * $kind: "menu", "specials" or "lunch"; $title: the box's name in de/en/zh (default:
 * Speisekarte / Menu / 菜單); $idp: the prefix of the dish ids on the page, for the links.
 */
function mdash_menu_schema_add( $menu, $photos, $match, $kind = 'menu', $title = null, $idp = 'mdash-' ) {
	global $mdash_menu_schema;
	if ( ! is_array( $mdash_menu_schema ) ) {
		$mdash_menu_schema = array();
	}
	if ( $menu && ! isset( $mdash_menu_schema[ $kind ] ) && ! is_admin() ) {
		$mdash_menu_schema[ $kind ] = mdash_menu_schema( $menu, $photos, $match, $kind, $title ? $title : mdash_strings()['menu_title'], $idp );
	}
}

function mdash_menu_schema_print() {
	global $mdash_menu_schema;
	foreach ( is_array( $mdash_menu_schema ) ? $mdash_menu_schema : array() as $kind => $data ) {
		$data = $data ? apply_filters( 'menudash_menu_schema', $data, $kind ) : null;
		if ( $data ) {
			// JSON_HEX_TAG: no dish name can end the <script> early.
			echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
		}
	}
}

/**
 * Name in German (the page's first language, falling back like the German view), the other
 * languages as alternateName. Returns array( name, alternateName[] ).
 */
function mdash_schema_names( $texts ) {
	list( $name ) = mdash_first( $texts, mdash_chains()['de'] );
	$alt          = array();
	foreach ( array( 'en', 'zh' ) as $l ) {
		$t = trim( (string) $texts[ $l ] );
		if ( '' !== $t && mdash_fold( $t ) !== mdash_fold( $name ) && ! in_array( $t, $alt, true ) ) {
			$alt[] = $t;
		}
	}
	return array( $name, $alt );
}

/** The schema.org diets for a dish's marks; vegan counts as vegetarian and lactose-free, like the filters. */
function mdash_schema_diets( $flags ) {
	$vegan = in_array( 'vegan', $flags, true );
	$diets = array();
	if ( $vegan ) {
		$diets[] = 'https://schema.org/VeganDiet';
	}
	if ( $vegan || in_array( 'vegetarian', $flags, true ) ) {
		$diets[] = 'https://schema.org/VegetarianDiet';
	}
	if ( in_array( 'gf', $flags, true ) ) {
		$diets[] = 'https://schema.org/GlutenFreeDiet';
	}
	if ( $vegan || in_array( 'lf', $flags, true ) ) {
		$diets[] = 'https://schema.org/LowLactoseDiet';
	}
	return $diets;
}

function mdash_menu_schema( $menu, $photos, $match, $kind, $title, $idp ) {
	$url      = is_singular() ? get_permalink() : home_url( '/' );
	$currency = apply_filters( 'menudash_currency', 'CHF' );
	$sections = array();
	foreach ( $menu['sections'] as $sec ) {
		$titles = array();
		$notes  = array();
		foreach ( MDASH_LANGS as $l ) {
			list( $titles[ $l ], $notes[ $l ] ) = mdash_split_note( $sec['name'][ $l ] );
		}
		list( $sname, $salt ) = mdash_schema_names( $titles );
		$items                = array();
		foreach ( $sec['dishes'] as $d ) {
			list( $name, $alt ) = mdash_schema_names( $d['name'] );
			if ( '' === $name ) {
				continue;
			}
			$desc  = mdash_first( $d['desc'], mdash_chains()['de'] )[0];
			$photo = isset( $match['dish'][ $d['key'] ], $photos[ $match['dish'][ $d['key'] ] ] ) ? $photos[ $match['dish'][ $d['key'] ] ] : null;
			$price = mdash_price_show( $d['price'] );
			$items[] = array_filter(
				array(
					'@type'           => 'MenuItem',
					'@id'             => $url . '#' . $idp . $d['key'],
					'name'            => $name,
					'alternateName'   => $alt,
					'description'     => $desc,
					'image'           => $photo ? mdash_photo_url( $photo, 800 ) : '',
					'suitableForDiet' => mdash_schema_diets( $d['flags'] ),
					// Only a real price; "nach Tagespreis" and the like stay on the page.
					'offers'          => preg_match( '/^\d+(\.\d+)?$/', $price ) ? array_filter(
						array(
							'@type'         => 'Offer',
							'price'         => $price,
							'priceCurrency' => $currency,
							'description'   => $d['measure'],
						)
					) : null,
				)
			);
		}
		if ( $items ) {
			$sections[] = array_filter(
				array(
					'@type'         => 'MenuSection',
					'name'          => $sname,
					'alternateName' => $salt,
					'description'   => mdash_first( $notes, mdash_chains()['de'] )[0],
					'hasMenuItem'   => $items,
				)
			);
		}
	}
	if ( ! $sections ) {
		return null;
	}
	list( $mname, $malt ) = mdash_schema_names( array_map( 'wp_strip_all_tags', $title ) );
	return array_filter(
		array(
			'@context'       => 'https://schema.org',
			'@type'          => 'Menu',
			'@id'            => $url . '#' . $kind,
			'name'           => wp_strip_all_tags( get_bloginfo( 'name' ) ) . ' – ' . $mname,
			'alternateName'  => $malt,
			'url'            => $url,
			'inLanguage'     => array_map( 'mdash_html_lang', array( 'de', 'en', 'zh' ) ),
			'hasMenuSection' => $sections,
		)
	);
}