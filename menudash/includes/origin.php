<?php
/**
 * Where the meat and fish come from (Herkunft), as Swiss restaurants must say in writing:
 * a short list entered on the MenuDash page (product + countries, or "please ask our staff"),
 * shown under the menu and anywhere with [menudash_origin]. Products and the usual countries
 * are translated into the three menu languages, so the owner types them once.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_ORIGIN_OPTION = 'menudash_origin';
const MDASH_ORIGIN_MAX    = 15;

add_shortcode( 'menudash_origin', 'mdash_origin_shortcode' );
add_action( 'admin_post_menudash_origin', 'mdash_handle_origin' );

/** Product key => DE, EN, 中文. */
function mdash_origin_products() {
	return array(
		'chicken' => array( 'Poulet', 'Chicken', '雞肉' ),
		'turkey'  => array( 'Truthahn', 'Turkey', '火雞肉' ),
		'duck'    => array( 'Ente', 'Duck', '鴨肉' ),
		'beef'    => array( 'Rindfleisch', 'Beef', '牛肉' ),
		'veal'    => array( 'Kalbfleisch', 'Veal', '小牛肉' ),
		'pork'    => array( 'Schweinefleisch', 'Pork', '豬肉' ),
		'lamb'    => array( 'Lammfleisch', 'Lamb', '羊肉' ),
		'game'    => array( 'Wild', 'Game', '野味' ),
		'fish'    => array( 'Fisch', 'Fish', '魚' ),
		'prawns'  => array( 'Crevetten', 'Prawns', '蝦' ),
		'seafood' => array( 'Meeresfrüchte', 'Seafood', '海鮮' ),
		'eggs'    => array( 'Eier', 'Eggs', '雞蛋' ),
	);
}

/**
 * Country code => DE, EN, 中文, then other ways owners write it. Typed names are matched
 * against all of these; anything else is kept as typed.
 */
function mdash_origin_countries() {
	return array(
		'CH'  => array( 'Schweiz', 'Switzerland', '瑞士', 'Suisse', 'Svizzera' ),
		'DE'  => array( 'Deutschland', 'Germany', '德國' ),
		'AT'  => array( 'Österreich', 'Austria', '奧地利', 'Oesterreich' ),
		'FR'  => array( 'Frankreich', 'France', '法國' ),
		'IT'  => array( 'Italien', 'Italy', '義大利' ),
		'ES'  => array( 'Spanien', 'Spain', '西班牙' ),
		'PT'  => array( 'Portugal', 'Portugal', '葡萄牙' ),
		'NL'  => array( 'Niederlande', 'Netherlands', '荷蘭', 'Holland' ),
		'BE'  => array( 'Belgien', 'Belgium', '比利時' ),
		'DK'  => array( 'Dänemark', 'Denmark', '丹麥', 'Daenemark' ),
		'IE'  => array( 'Irland', 'Ireland', '愛爾蘭' ),
		'GB'  => array( 'Grossbritannien', 'United Kingdom', '英國', 'UK', 'England', 'Großbritannien', 'Vereinigtes Königreich' ),
		'SCT' => array( 'Schottland', 'Scotland', '蘇格蘭' ),
		'PL'  => array( 'Polen', 'Poland', '波蘭' ),
		'HU'  => array( 'Ungarn', 'Hungary', '匈牙利' ),
		'CZ'  => array( 'Tschechien', 'Czechia', '捷克', 'Czech Republic' ),
		'SI'  => array( 'Slowenien', 'Slovenia', '斯洛維尼亞' ),
		'HR'  => array( 'Kroatien', 'Croatia', '克羅埃西亞' ),
		'GR'  => array( 'Griechenland', 'Greece', '希臘' ),
		'NO'  => array( 'Norwegen', 'Norway', '挪威' ),
		'SE'  => array( 'Schweden', 'Sweden', '瑞典' ),
		'FI'  => array( 'Finnland', 'Finland', '芬蘭' ),
		'IS'  => array( 'Island', 'Iceland', '冰島' ),
		'FO'  => array( 'Färöer', 'Faroe Islands', '法羅群島', 'Färöer-Inseln' ),
		'TR'  => array( 'Türkei', 'Turkey', '土耳其', 'Türkiye' ),
		'US'  => array( 'USA', 'United States', '美國', 'Vereinigte Staaten' ),
		'CA'  => array( 'Kanada', 'Canada', '加拿大' ),
		'MX'  => array( 'Mexiko', 'Mexico', '墨西哥' ),
		'BR'  => array( 'Brasilien', 'Brazil', '巴西' ),
		'AR'  => array( 'Argentinien', 'Argentina', '阿根廷' ),
		'UY'  => array( 'Uruguay', 'Uruguay', '烏拉圭' ),
		'PY'  => array( 'Paraguay', 'Paraguay', '巴拉圭' ),
		'CL'  => array( 'Chile', 'Chile', '智利' ),
		'EC'  => array( 'Ecuador', 'Ecuador', '厄瓜多' ),
		'PE'  => array( 'Peru', 'Peru', '秘魯' ),
		'AU'  => array( 'Australien', 'Australia', '澳洲' ),
		'NZ'  => array( 'Neuseeland', 'New Zealand', '紐西蘭' ),
		'VN'  => array( 'Vietnam', 'Vietnam', '越南' ),
		'TH'  => array( 'Thailand', 'Thailand', '泰國' ),
		'ID'  => array( 'Indonesien', 'Indonesia', '印尼' ),
		'MY'  => array( 'Malaysia', 'Malaysia', '馬來西亞' ),
		'IN'  => array( 'Indien', 'India', '印度' ),
		'BD'  => array( 'Bangladesch', 'Bangladesh', '孟加拉' ),
		'CN'  => array( 'China', 'China', '中國' ),
		'JP'  => array( 'Japan', 'Japan', '日本' ),
		'KR'  => array( 'Südkorea', 'South Korea', '南韓', 'Korea', 'Suedkorea' ),
		'TW'  => array( 'Taiwan', 'Taiwan', '台灣' ),
		'PH'  => array( 'Philippinen', 'Philippines', '菲律賓' ),
		'EU'  => array( 'EU', 'EU', '歐盟', 'Europäische Union', 'European Union' ),
	);
}

/** "Schweiz" / "switzerland" / "ch" => "CH"; anything unknown => null. */
function mdash_origin_country_code( $name ) {
	$n = mb_strtolower( trim( $name ) );
	foreach ( mdash_origin_countries() as $code => $names ) {
		if ( mb_strtolower( $code ) === $n ) {
			return $code;
		}
		foreach ( $names as $x ) {
			if ( mb_strtolower( $x ) === $n ) {
				return $code;
			}
		}
	}
	return null;
}

/** Saved rows and settings, cleaned; rows are product/own/countries/ask. */
function mdash_origin() {
	$saved = get_option( MDASH_ORIGIN_OPTION );
	$saved = is_array( $saved ) ? $saved : array();
	return array(
		'rows' => isset( $saved['rows'] ) && is_array( $saved['rows'] ) ? $saved['rows'] : array(),
		'menu' => ! isset( $saved['menu'] ) || ! empty( $saved['menu'] ),
	);
}

/**
 * Cleans the dashboard form. Countries are typed as text ("Schweiz, Deutschland"): known
 * names become codes, the rest is kept as typed ("raw:…"). Returns array( $origin, $problems ).
 */
function mdash_origin_clean( $in ) {
	$rows     = array();
	$problems = array();
	$products = mdash_origin_products();
	$text     = function ( $v, $max ) { return is_scalar( $v ) ? mb_substr( sanitize_text_field( (string) $v ), 0, $max ) : ''; };
	foreach ( isset( $in['rows'] ) && is_array( $in['rows'] ) ? $in['rows'] : array() as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$product = isset( $r['product'] ) && is_scalar( $r['product'] ) ? (string) $r['product'] : '';
		$own     = array();
		foreach ( array( 'de', 'en', 'zh' ) as $l ) {
			$own[ $l ] = $text( isset( $r['own'][ $l ] ) ? $r['own'][ $l ] : '', 60 );
		}
		if ( 'own' === $product ) {
			if ( '' === implode( '', $own ) ) {
				continue; // "Other" with no name: an empty row.
			}
		} elseif ( ! isset( $products[ $product ] ) ) {
			continue; // Nothing chosen.
		} else {
			$own = array( 'de' => '', 'en' => '', 'zh' => '' );
		}
		$ask       = ! empty( $r['ask'] );
		$countries = array();
		foreach ( preg_split( '/\s*[,;\/]\s*|\s+(?:und|and)\s+/u', $text( isset( $r['countries'] ) ? $r['countries'] : '', 200 ) ) as $c ) {
			if ( '' === trim( $c ) ) {
				continue;
			}
			$code        = mdash_origin_country_code( $c );
			$countries[] = $code ? $code : 'raw:' . trim( $c );
		}
		$countries = array_values( array_unique( $countries ) );
		if ( ! $countries && ! $ask ) {
			$name       = 'own' === $product ? implode( ' / ', array_filter( $own ) ) : $products[ $product ][0];
			$problems[] = sprintf( '%s: enter the countries, or tick "Ask our staff".', $name );
			$ask        = true;
		}
		$rows[] = array( 'product' => $product, 'own' => $own, 'countries' => $countries, 'ask' => $ask );
		if ( count( $rows ) >= MDASH_ORIGIN_MAX ) {
			break;
		}
	}
	return array( array( 'rows' => $rows, 'menu' => ! empty( $in['menu'] ) ), $problems );
}

/** The countries as typed back into the form: "Schweiz, Deutschland". */
function mdash_origin_countries_text( $row ) {
	$c = mdash_origin_countries();
	return implode(
		', ',
		array_map(
			function ( $x ) use ( $c ) {
				return 0 === strpos( $x, 'raw:' ) ? substr( $x, 4 ) : ( isset( $c[ $x ] ) ? $c[ $x ][0] : $x );
			},
			$row['countries']
		)
	);
}

/** One row in one language: array( product, origin ). */
function mdash_origin_row_text( $row, $lang ) {
	$i        = array_search( $lang, array( 'de', 'en', 'zh' ), true );
	$i        = false === $i ? 1 : $i;
	$products = mdash_origin_products();
	if ( 'own' === $row['product'] ) {
		// Own product: this language, else the first one filled in.
		$name = '' !== $row['own'][ $lang ] ? $row['own'][ $lang ] : current( array_filter( $row['own'] ) );
	} else {
		$name = $products[ $row['product'] ][ $i ];
	}
	if ( $row['ask'] && ! $row['countries'] ) {
		return array( $name, mdash_strings()['origin_ask'][ $lang ] );
	}
	$c     = mdash_origin_countries();
	$names = array();
	foreach ( $row['countries'] as $x ) {
		$names[] = 0 === strpos( $x, 'raw:' ) ? substr( $x, 4 ) : ( isset( $c[ $x ] ) ? $c[ $x ][ $i ] : $x );
	}
	return array( $name, implode( 'zh' === $lang ? '、' : ', ', $names ) );
}

/** The list as a table in one language; '' without rows. */
function mdash_origin_table( $lang ) {
	$o = mdash_origin();
	if ( ! $o['rows'] ) {
		return '';
	}
	$html = '<table class="mdash-origin-table"><tbody>';
	foreach ( $o['rows'] as $row ) {
		list( $name, $from ) = mdash_origin_row_text( $row, $lang );
		$html               .= '<tr><th scope="row">' . esc_html( $name ) . '</th><td>' . esc_html( $from ) . '</td></tr>';
	}
	return $html . '</tbody></table>';
}

/**
 * The allergy note in one language, escaped: "ask our staff", or "… or call <phone>" when the
 * Restaurant add-on has a phone number. Used under the menu and by [menudash_origin allergy="yes"].
 */
function mdash_allergy_note( $l ) {
	$s     = mdash_strings();
	$phone = function_exists( 'mdash_detail' ) ? trim( (string) mdash_detail( 'phone' ) ) : '';
	$tel   = '' !== $phone ? mdash_tel( $phone ) : '';
	if ( '' === $tel ) {
		return esc_html( $s['allergy'][ $l ] );
	}
	return str_replace( '%s', '<a href="tel:' . esc_attr( $tel ) . '">' . esc_html( $phone ) . '</a>', esc_html( $s['allergy_phone'][ $l ] ) );
}

/**
 * [menudash_origin]: the list on any page. lang="de|en|zh" for one language (default: all
 * three, each with its heading); title="no" leaves the heading out; allergy="yes" adds the
 * allergy note under the list.
 */
function mdash_origin_shortcode( $atts ) {
	$a     = shortcode_atts( array( 'lang' => '', 'title' => 'yes', 'allergy' => 'no' ), $atts, 'menudash_origin' );
	$langs = in_array( $a['lang'], array( 'de', 'en', 'zh' ), true ) ? array( $a['lang'] ) : array( 'de', 'en', 'zh' );
	$s     = mdash_strings();
	$out   = '';
	foreach ( $langs as $l ) {
		$table = mdash_origin_table( $l );
		if ( '' === $table ) {
			return '';
		}
		$head  = 'no' !== $a['title'] ? '<h3 class="mdash-origin-title">' . esc_html( $s['origin_title'][ $l ] ) . '</h3>' : '';
		$note  = 'yes' === $a['allergy'] ? '<p class="mdash-allergy"><strong>' . esc_html( $s['allergy_title'][ $l ] ) . '</strong><br>' . mdash_allergy_note( $l ) . '</p>' : '';
		$out  .= '<div class="mdash-origin" lang="' . esc_attr( mdash_html_lang( $l ) ) . '">' . $head . $table . $note . '</div>';
	}
	wp_enqueue_style( 'menudash', MENUDASH_URL . 'assets/menu.css', array(), MENUDASH_VERSION );
	return '<div class="menudash-origin">' . $out . '</div>';
}

function mdash_handle_origin() {
	check_admin_referer( 'menudash_origin' );
	if ( ! mdash_can() ) {
		wp_die( 'You are not allowed to change the menu.', 403 );
	}
	$in = isset( $_POST['origin'] ) && is_array( $_POST['origin'] ) ? wp_unslash( $_POST['origin'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned in mdash_origin_clean()
	list( $origin, $problems ) = mdash_origin_clean( $in );
	update_option( MDASH_ORIGIN_OPTION, $origin, false );
	mdash_purge_caches();
	mdash_back( array( 'ok' => ! $problems, 'kind' => 'Meat and fish origin', 'message' => 'Saved.', 'error' => implode( ' ', $problems ) ) );
}

/** One row of the dashboard table; $i is the row number or "__i__" in the template. */
function mdash_origin_admin_row( $i, $row ) {
	$f = 'origin[rows][' . $i . ']';
	?>
	<tr>
		<td>
			<select name="<?php echo esc_attr( $f ); ?>[product]" class="mdash-origin-product" aria-label="Product">
				<option value="">Choose …</option>
				<?php foreach ( mdash_origin_products() as $key => $names ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $row['product'], $key ); ?>><?php echo esc_html( $names[0] . ' · ' . $names[1] ); ?></option>
				<?php endforeach; ?>
				<option value="own" <?php selected( $row['product'], 'own' ); ?>>Other (type the name) …</option>
			</select>
			<span class="mdash-origin-own"<?php echo 'own' === $row['product'] ? '' : ' hidden'; ?>>
				<?php foreach ( array( 'de' => 'DE', 'en' => 'EN', 'zh' => '中文' ) as $l => $label ) : ?>
					<input type="text" name="<?php echo esc_attr( $f . '[own][' . $l . ']' ); ?>" value="<?php echo esc_attr( $row['own'][ $l ] ); ?>" placeholder="<?php echo esc_attr( $label ); ?>" maxlength="60" aria-label="<?php echo esc_attr( 'Name ' . $label ); ?>">
				<?php endforeach; ?>
			</span>
		</td>
		<td><input type="text" name="<?php echo esc_attr( $f ); ?>[countries]" value="<?php echo esc_attr( mdash_origin_countries_text( $row ) ); ?>" class="regular-text" maxlength="200" placeholder="Schweiz, Deutschland" aria-label="Countries"></td>
		<td><label><input type="checkbox" name="<?php echo esc_attr( $f ); ?>[ask]" value="1" <?php checked( $row['ask'] ); ?>> Ask our staff</label></td>
		<td><button type="button" class="button-link mdash-origin-remove">Remove</button></td>
	</tr>
	<?php
}

/** Menu tab: the list, with a preview in German. */
function mdash_origin_admin_card() {
	$o     = mdash_origin();
	$empty = array( 'product' => '', 'own' => array( 'de' => '', 'en' => '', 'zh' => '' ), 'countries' => array(), 'ask' => false );
	$rows  = $o['rows'] ? $o['rows'] : array( $empty );
	?>
	<section class="mdash-card mdash-origin-card">
		<h2>Meat and fish origin (Herkunft)</h2>
		<p>In Switzerland, restaurants must say in writing where their meat comes from. Choose each product and type its countries in German or English ("Schweiz, Deutschland"); MenuDash writes them in all three menu languages. Countries it doesn't know stay as you typed them. The list shows under the menu, and on any page with <code>[menudash_origin]</code> (<code>lang="de"</code> for German only, <code>title="no"</code> without the heading, <code>allergy="yes"</code> with the allergy note under it).</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="menudash_origin">
			<?php wp_nonce_field( 'menudash_origin' ); ?>
			<button type="submit" class="mdash-default-submit" tabindex="-1" aria-hidden="true">Save</button>
			<div class="mdash-origin-layout">
				<div>
					<table class="mdash-origin-admin">
						<thead><tr><th>Product</th><th>Countries</th><th></th><th><span class="screen-reader-text">Remove</span></th></tr></thead>
						<tbody>
						<?php
						foreach ( array_values( $rows ) as $i => $row ) {
							mdash_origin_admin_row( $i, $row );
						}
						?>
						</tbody>
					</table>
					<template id="mdash-origin-new"><?php mdash_origin_admin_row( '__i__', $empty ); ?></template>
					<p><button type="button" class="button" id="mdash-origin-add" data-max="<?php echo (int) MDASH_ORIGIN_MAX; ?>"<?php echo count( $rows ) >= MDASH_ORIGIN_MAX ? ' hidden' : ''; ?>>+ Add product</button></p>
					<p><input type="hidden" name="origin[menu]" value=""><label><input type="checkbox" name="origin[menu]" value="1" <?php checked( $o['menu'] ); ?>> Show the list under the menu</label></p>
					<p><?php submit_button( 'Save origin', 'primary', 'save_origin', false ); ?></p>
				</div>
				<?php if ( $o['rows'] ) : ?>
					<aside class="mdash-origin-preview">
						<h3>On the site (DE)</h3>
						<?php echo mdash_origin_table( 'de' ); // phpcs:ignore -- escaped inside ?>
					</aside>
				<?php endif; ?>
			</div>
		</form>
	</section>
	<?php
}
