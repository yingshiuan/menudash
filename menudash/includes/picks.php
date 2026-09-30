<?php
/**
 * Recommended dishes: a row of dishes with their photos, e.g. on the home page. Each links
 * to that dish on the menu page.
 *
 *   Block      menudash/picks (category "MenuDash"), options in the sidebar, preview drawn
 *              by the server (assets/picks-editor.js).
 *   Shortcode  [menudash_picks] for classic themes and page builders:
 *              dishes="22, 45, Sambal Udang"  numbers or names, in this order (empty: the
 *                                             dishes marked Recommended in the menu)
 *              max="12" layout="grid|row" lang="de|en|zh" number="yes" second="yes" diet="no"
 *              button="yes" button_text="…"
 *
 * Which dishes: those marked Recommended in the menu (with a photo first), or the ones the
 * owner chose. A chosen dish is kept as its key and its name, so it is found again after a
 * new menu is uploaded, even when its number has changed; a dish that is gone is left out
 * (the editor says which). The photos are always the ones under MenuDash → Dish photos,
 * so a dish looks the same here as on the menu.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'mdash_picks_register', 5 ); // Before the theme's patterns, which check for it.
add_action( 'enqueue_block_editor_assets', 'mdash_picks_editor_data' );
// After the add-ons (priority 10), which add the same category for their blocks.
add_filter( 'block_categories_all', 'mdash_picks_category', 20 );
add_shortcode( 'menudash_picks', 'mdash_picks_shortcode' );

function mdash_picks_register() {
	wp_register_script( 'menudash-picks-editor', MENUDASH_URL . 'assets/picks-editor.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ), MENUDASH_VERSION, true );
	wp_register_style( 'menudash-picks', MENUDASH_URL . 'assets/picks.css', array(), MENUDASH_VERSION );
	wp_register_script( 'menudash-picks', MENUDASH_URL . 'assets/picks.js', array(), MENUDASH_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	$colors = mdash_colors_css( '.menudash-picks' );
	if ( '' !== $colors ) {
		wp_add_inline_style( 'menudash-picks', $colors );
	}
	register_block_type(
		'menudash/picks',
		array(
			'api_version'     => 3,
			'title'           => 'Recommended dishes',
			'description'     => 'Dishes with their photos, each linking to the menu: the ones marked Recommended in the menu, or the ones you choose.',
			'category'        => 'menudash',
			'icon'            => 'star-filled',
			'keywords'        => array( 'dishes', 'recommended', 'photos', 'empfehlungen', 'gerichte', 'menu' ),
			'attributes'      => array(
				'source'     => array( 'type' => 'string', 'default' => 'pick' ),
				'dishes'     => array(
					'type'    => 'array',
					'default' => array(),
					'items'   => array(
						'type'       => 'object',
						'properties' => array(
							'key'  => array( 'type' => 'string' ),
							'name' => array( 'type' => 'string' ),
						),
					),
				),
				// Split into groups (tabs or headings): by diet for the Recommended dishes, or
				// groups of chosen dishes, each with its title.
				'split'      => array( 'type' => 'boolean', 'default' => false ),
				'groups'     => array(
					'type'    => 'array',
					'default' => array(),
					'items'   => array(
						'type'       => 'object',
						'properties' => array(
							'title'  => array( 'type' => 'string' ),
							'dishes' => array(
								'type'  => 'array',
								'items' => array(
									'type'       => 'object',
									'properties' => array(
										'key'  => array( 'type' => 'string' ),
										'name' => array( 'type' => 'string' ),
									),
								),
							),
						),
					),
				),
				'groupStyle' => array( 'type' => 'string', 'default' => 'tabs' ),
				'max'        => array( 'type' => 'number', 'default' => 12 ),
				'layout'     => array( 'type' => 'string', 'default' => 'grid' ),
				'lang'       => array( 'type' => 'string', 'default' => '' ),
				'number'     => array( 'type' => 'boolean', 'default' => true ),
				'second'     => array( 'type' => 'boolean', 'default' => true ),
				'diet'       => array( 'type' => 'boolean', 'default' => false ),
				'button'     => array( 'type' => 'boolean', 'default' => true ),
				'buttonText' => array( 'type' => 'string', 'default' => '' ),
				'className'  => array( 'type' => 'string', 'default' => '' ),
			),
			'supports'        => array(
				'html'    => false,
				'align'   => array( 'wide', 'full' ),
				'spacing' => array( 'margin' => true ),
			),
			'editor_script'   => 'menudash-picks-editor',
			'style'           => 'menudash-picks',
			'render_callback' => 'mdash_picks_block',
		)
	);
}

function mdash_picks_category( $categories ) {
	foreach ( $categories as $c ) {
		if ( 'menudash' === $c['slug'] ) {
			return $categories;
		}
	}
	return array_merge( $categories, array( array( 'slug' => 'menudash', 'title' => 'MenuDash', 'icon' => null ) ) );
}

/** The site's language as de, en or zh. */
function mdash_picks_site_lang() {
	$l = substr( get_locale(), 0, 2 );
	return in_array( $l, array( 'de', 'zh' ), true ) ? $l : 'en';
}

/** The first published page with the menu on it, or /menu/. */
function mdash_picks_menu_url() {
	static $url = null;
	if ( null === $url ) {
		$url   = home_url( '/menu/' );
		$pages = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 20, 's' => '[menudash', 'orderby' => 'menu_order ID', 'order' => 'ASC' ) );
		foreach ( $pages as $p ) {
			if ( has_shortcode( $p->post_content, 'menudash' ) ) {
				$url = get_permalink( $p );
				break;
			}
		}
	}
	return $url;
}

/** The two diet groups' default titles: meat and fish, vegetarian and vegan. */
function mdash_picks_diet_titles( $lang ) {
	$s = mdash_strings();
	return array( $s['group_meat'][ $lang ], $s['group_veg'][ $lang ] );
}

/** A dish's name for matching: the English (else German, else Chinese) name, folded. */
function mdash_picks_fold( $name ) {
	$n = ! empty( $name['en'] ) ? $name['en'] : ( ! empty( $name['de'] ) ? $name['de'] : ( isset( $name['zh'] ) ? $name['zh'] : '' ) );
	return mdash_fold( mdash_base_name( $n ) );
}

/** Every dish of the live menu, in menu order: key => dish (with 'sec', the section's name). */
function mdash_picks_all( $menu ) {
	$all = array();
	foreach ( $menu['sections'] as $sec ) {
		foreach ( $sec['dishes'] as $d ) {
			$d['sec']         = $sec['name'];
			$all[ $d['key'] ] = $d;
		}
	}
	return $all;
}

/**
 * The chosen dishes, found again in the live menu: by key when the name still fits, else by
 * name (the dish got a new number), else by key (the dish was renamed).
 * Returns array( dishes, names of the chosen dishes that are gone ).
 */
function mdash_picks_find( $refs, $all ) {
	$by_name = array();
	foreach ( $all as $k => $d ) {
		$f = mdash_picks_fold( $d['name'] );
		if ( '' !== $f && ! isset( $by_name[ $f ] ) ) {
			$by_name[ $f ] = $k;
		}
	}
	$found = array();
	$gone  = array();
	foreach ( (array) $refs as $ref ) {
		$key  = isset( $ref['key'] ) ? (string) $ref['key'] : '';
		$name = isset( $ref['name'] ) ? (string) $ref['name'] : '';
		$f    = mdash_fold( mdash_base_name( $name ) );
		if ( isset( $all[ $key ] ) && ( '' === $f || mdash_picks_fold( $all[ $key ]['name'] ) === $f ) ) {
			$k = $key;
		} elseif ( '' !== $f && isset( $by_name[ $f ] ) ) {
			$k = $by_name[ $f ];
		} elseif ( isset( $all[ $key ] ) ) {
			$k = $key;
		} else {
			$gone[] = '' !== $name ? $name : $key;
			continue;
		}
		$found[ $k ] = $all[ $k ];
	}
	return array( array_values( $found ), $gone );
}

function mdash_picks_block( $a ) {
	return mdash_picks_render( $a, get_block_wrapper_attributes( array( 'class' => 'menudash-picks' ) ) );
}

/**
 * The dishes as a list. $a: the block's attributes; $wrap: the attributes of the outer <div>
 * (the block's, with WordPress's alignment and spacing classes).
 */
function mdash_picks_render( $a, $wrap = '' ) {
	$a      = wp_parse_args( $a, array( 'source' => 'pick', 'dishes' => array(), 'split' => false, 'groups' => array(), 'groupStyle' => 'tabs', 'max' => 12, 'layout' => 'grid', 'lang' => '', 'number' => true, 'second' => true, 'diet' => false, 'button' => true, 'buttonText' => '', 'className' => '' ) );
	$editor = defined( 'REST_REQUEST' ) && REST_REQUEST;
	$note   = function ( $text ) use ( $editor ) {
		return $editor ? '<p class="mdash-picks-note"><em>' . esc_html( $text ) . '</em></p>' : '';
	};
	$menu = mdash_get_menu();
	if ( ! $menu ) {
		return $note( 'Recommended dishes: no menu yet. Upload one under MenuDash → Menu.' );
	}
	$all    = mdash_picks_all( $menu );
	$photos = mdash_photo_index();
	$match  = mdash_photo_matches( $menu, $photos );
	$photo  = function ( $d ) use ( $match, $photos ) {
		return isset( $match['dish'][ $d['key'] ], $photos[ $match['dish'][ $d['key'] ] ] ) ? $photos[ $match['dish'][ $d['key'] ] ] : null;
	};
	$lang = in_array( $a['lang'], array( 'de', 'en', 'zh' ), true ) ? $a['lang'] : mdash_picks_site_lang();
	$s    = mdash_strings();
	$max  = max( 1, min( 48, (int) $a['max'] ) );
	$gone = array();
	// The groups to show: array( title, dishes ). Without "split", one group without a title.
	$groups = array();
	if ( 'chosen' === $a['source'] ) {
		$lists = $a['split'] ? (array) $a['groups'] : array( array( 'title' => '', 'dishes' => $a['dishes'] ) );
		foreach ( $lists as $g ) {
			list( $found, $lost ) = mdash_picks_find( isset( $g['dishes'] ) ? $g['dishes'] : array(), $all );
			$gone                 = array_merge( $gone, $lost );
			$groups[]             = array( isset( $g['title'] ) ? (string) $g['title'] : '', $found );
		}
	} else {
		// Marked Recommended: those with a photo first, so a short row still looks full.
		$with    = array();
		$without = array();
		foreach ( $all as $d ) {
			if ( in_array( 'pick', (array) $d['flags'], true ) ) {
				if ( $photo( $d ) ) {
					$with[] = $d;
				} else {
					$without[] = $d;
				}
			}
		}
		$dishes = array_merge( $with, $without );
		if ( ! $dishes ) {
			return $note( 'Recommended dishes: no dish is marked Recommended in the menu. Mark some in the spreadsheet, or choose the dishes in the sidebar.' );
		}
		if ( $a['split'] ) {
			// Split by the diet marks: meat and fish, then vegetarian and vegan.
			$titles = mdash_picks_diet_titles( $lang );
			$g      = (array) $a['groups'];
			$meat   = array();
			$veg    = array();
			foreach ( $dishes as $d ) {
				if ( array_intersect( array( 'vegan', 'vegetarian' ), (array) $d['flags'] ) ) {
					$veg[] = $d;
				} else {
					$meat[] = $d;
				}
			}
			$groups[] = array( ! empty( $g[0]['title'] ) ? $g[0]['title'] : $titles[0], $meat );
			$groups[] = array( ! empty( $g[1]['title'] ) ? $g[1]['title'] : $titles[1], $veg );
		} else {
			$groups[] = array( '', $dishes );
		}
	}
	$groups = array_values( array_filter( $groups, function ( $g ) {
		return (bool) $g[1];
	} ) );
	if ( ! $groups && ! $gone ) {
		return $note( 'Recommended dishes: choose the dishes in the sidebar.' );
	}

	$url  = mdash_picks_menu_url();
	$list = function ( $dishes ) use ( $a, $s, $lang, $url, $photo, $max ) {
		$html = '';
		foreach ( array_slice( $dishes, 0, $max ) as $d ) {
			list( $name, $nl ) = mdash_first( $d['name'], array_unique( array( $lang, 'de', 'en', 'zh' ) ) );
			// The second name: Chinese, or on a Chinese site the German or English one.
			list( $second, $sl ) = $a['second'] ? mdash_first( $d['name'], 'zh' === $lang ? array( 'de', 'en' ) : array( 'zh' ) ) : array( '', '' );
			$second              = $sl === $nl ? '' : $second;
			$p                   = $photo( $d );
			$html               .= '<li class="mdash-pick"><a href="' . esc_url( $url . '#mdash-' . $d['key'] ) . '">';
			if ( $p ) {
				// A square photo (not a cut-out) is cropped to a round plate.
				$html .= '<span class="mdash-pick-photo' . ( empty( $p['alpha'] ) ? ' is-round' : '' ) . '"><img src="' . esc_url( mdash_photo_url( $p, 400 ) ) . '" alt="" width="400" height="400" loading="lazy" decoding="async"></span>';
			} else {
				$html .= '<span class="mdash-pick-photo is-none" aria-hidden="true"></span>';
			}
			// "No. 400 · Vegan": the number, and with "diet" the vegan or vegetarian mark.
			$top = array();
			if ( $a['number'] && '' !== (string) $d['no'] ) {
				$top[] = sprintf( $s['dish_no'][ $lang ], $d['no'] );
			}
			if ( $a['diet'] ) {
				$flags = (array) $d['flags'];
				$mark  = in_array( 'vegan', $flags, true ) ? 'vegan' : ( in_array( 'vegetarian', $flags, true ) ? 'vegetarian' : '' );
				if ( '' !== $mark ) {
					$top[] = $s[ $mark ][ $lang ];
				}
			}
			if ( $top ) {
				$html .= '<span class="mdash-pick-no">' . esc_html( implode( ' · ', $top ) ) . '</span>';
			}
			$html .= '<strong class="mdash-pick-name" lang="' . esc_attr( mdash_html_lang( $nl ) ) . '">' . esc_html( mdash_base_name( $name ) ) . '</strong>';
			if ( '' !== $second ) {
				$html .= '<span class="mdash-pick-second" lang="' . esc_attr( mdash_html_lang( $sl ) ) . '">' . esc_html( mdash_base_name( $second ) ) . '</span>';
			}
			$html .= '</a></li>';
		}
		return '<ul class="mdash-picks-list" role="list">' . $html . '</ul>';
	};
	// Tabs: the buttons are printed here, so the editor's preview has them too; assets/picks.js
	// (on the page) and the editor switch between them. Without JavaScript the tabs are hidden
	// and every group shows under its heading.
	$html = '';
	// "slide": all groups in one row that slides sideways; a tab slides it to its group.
	$slide = count( $groups ) > 1 && 'slide' === $a['groupStyle'];
	$tabs  = $slide || ( count( $groups ) > 1 && 'tabs' === $a['groupStyle'] );
	if ( $tabs ) {
		static $n = 0;
		$id    = 'mdash-picks-' . ( ++$n );
		$html .= '<div class="mdash-picks-tabs" role="tablist">';
		foreach ( $groups as $i => $g ) {
			$html .= '<button type="button" class="mdash-picks-tab" role="tab" id="' . $id . '-t' . $i . '" aria-controls="' . $id . '-g' . $i . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '"' . ( 0 === $i ? '' : ' tabindex="-1"' ) . '>' . esc_html( '' !== $g[0] ? $g[0] : (string) ( $i + 1 ) ) . '</button>';
		}
		$html .= '</div>';
		wp_enqueue_script( 'menudash-picks' );
	}
	foreach ( $groups as $i => $g ) {
		if ( count( $groups ) < 2 && '' === $g[0] ) {
			$html .= $list( $g[1] );
			continue;
		}
		$html .= '<div class="mdash-picks-group' . ( $tabs && ! $slide && 0 === $i ? ' is-active' : '' ) . '"'
			. ( $tabs ? ' id="' . $id . '-g' . $i . '" role="tabpanel" aria-labelledby="' . $id . '-t' . $i . '"' : '' ) . '>'
			. ( '' !== $g[0] ? '<h3 class="mdash-picks-title">' . esc_html( $g[0] ) . '</h3>' : '' ) . $list( $g[1] ) . '</div>';
	}
	if ( $slide ) {
		$cut  = strpos( $html, '<div class="mdash-picks-group' );
		$html = substr( $html, 0, $cut ) . '<div class="mdash-picks-strip">' . substr( $html, $cut ) . '</div>';
	}
	$more = '';
	if ( $a['button'] ) {
		$text = '' !== trim( $a['buttonText'] ) ? $a['buttonText'] : $s['whole_menu'][ $lang ];
		$more = '<p class="mdash-picks-more wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></p>';
	}
	$class = ( 'row' === $a['layout'] || $slide ? 'is-row' : 'is-grid' ) . ( $tabs ? ' has-tabs' : '' ) . ( $slide ? ' has-slide' : '' );
	$wrap  = '' !== $wrap ? $wrap : 'class="menudash-picks' . ( '' !== $a['className'] ? ' ' . esc_attr( $a['className'] ) : '' ) . '"';
	$wrap  = preg_replace( '/class="/', 'class="' . $class . ' ', $wrap, 1 );
	$gone  = $gone ? $note( sprintf( 'Not on the menu any more, so not shown: %s. Choose them again or remove them in the sidebar.', implode( ', ', $gone ) ) ) : '';
	wp_enqueue_style( 'menudash-picks' );
	return '<div ' . $wrap . ' data-nosnippet>' . $gone . $html . $more . '</div>';
}

/** [menudash_picks]: the same as the block, for classic themes and page builders. */
function mdash_picks_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'dishes' => '', 'max' => 12, 'layout' => 'grid', 'lang' => '', 'number' => 'yes', 'second' => 'yes', 'diet' => 'no', 'button' => 'yes', 'button_text' => '', 'class' => '' ), $atts, 'menudash_picks' );
	$yes  = function ( $v ) {
		return ! in_array( strtolower( trim( (string) $v ) ), array( 'no', 'nein', '0', 'false', 'off' ), true );
	};
	$refs = array();
	$menu = '' !== trim( $atts['dishes'] ) ? mdash_get_menu() : null;
	if ( $menu ) {
		$all = mdash_picks_all( $menu );
		foreach ( array_filter( array_map( 'trim', explode( ',', $atts['dishes'] ) ), 'strlen' ) as $want ) {
			$no = mdash_dish_no( $want );
			foreach ( $all as $d ) {
				if ( ( '' !== $no && (string) $d['no'] === $no ) || mdash_picks_fold( $d['name'] ) === mdash_fold( $want ) ) {
					$refs[] = array( 'key' => $d['key'], 'name' => '' );
					continue 2;
				}
			}
			$refs[] = array( 'key' => '', 'name' => $want );
		}
	}
	return mdash_picks_render(
		array(
			'source'     => $refs ? 'chosen' : 'pick',
			'dishes'     => $refs,
			'max'        => (int) $atts['max'],
			'layout'     => (string) $atts['layout'],
			'lang'       => (string) $atts['lang'],
			'number'     => $yes( $atts['number'] ),
			'second'     => $yes( $atts['second'] ),
			'diet'       => $yes( $atts['diet'] ),
			'button'     => $yes( $atts['button'] ),
			'buttonText' => (string) $atts['button_text'],
			'className'  => sanitize_html_class( (string) $atts['class'] ),
		)
	);
}

/** The live menu for the block's sidebar: every dish with its number, names and photo. */
function mdash_picks_editor_data() {
	$menu = mdash_get_menu();
	$list = array();
	if ( $menu ) {
		$photos = mdash_photo_index();
		$match  = mdash_photo_matches( $menu, $photos );
		$lang   = mdash_picks_site_lang();
		foreach ( mdash_picks_all( $menu ) as $d ) {
			$pid    = isset( $match['dish'][ $d['key'] ] ) ? $match['dish'][ $d['key'] ] : '';
			$list[] = array(
				'key'   => $d['key'],
				'no'    => (string) $d['no'],
				'name'  => mdash_base_name( mdash_first( $d['name'], array_unique( array( $lang, 'en', 'de', 'zh' ) ) )[0] ),
				'match' => mdash_picks_fold( $d['name'] ),
				'all'   => trim( implode( ' ', array_filter( array( $d['name']['de'], $d['name']['en'], $d['name']['zh'] ) ) ) ),
				'sec'   => mdash_first( $d['sec'], array_unique( array( $lang, 'en', 'de', 'zh' ) ) )[0],
				'pick'  => in_array( 'pick', (array) $d['flags'], true ),
				'veg'   => (bool) array_intersect( array( 'vegan', 'vegetarian' ), (array) $d['flags'] ),
				'photo' => $pid && isset( $photos[ $pid ] ) ? mdash_photo_url( $photos[ $pid ], 400 ) : '',
			);
		}
	}
	wp_add_inline_script( 'menudash-picks-editor', 'var menudashPicks = ' . wp_json_encode( array( 'dishes' => $list, 'titles' => mdash_picks_diet_titles( mdash_picks_site_lang() ) ) ) . ';', 'before' );
}
