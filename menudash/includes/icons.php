<?php
/**
 * Diet icons: the defaults in assets/icons.svg, or the restaurant's own.
 *
 * A custom SVG is cleaned (includes/svg-clean.php) and drawn through the same <symbol>
 * sprite as the defaults. A custom PNG/JPEG/WebP is resized to 96 px and shown as an
 * <img>. Custom icons live in uploads/menudash/icons/ with an index.json, beside the
 * photos, so updating or reinstalling the plugin keeps them.
 */

defined( 'ABSPATH' ) || exit;

/** The marks that have an icon, with their label in the admin. "Not spicy" draws Spicy, crossed out. */
function mdash_icon_keys() {
	return array(
		'pick'       => 'Recommended',
		'spicy'      => 'Spicy',
		'vegan'      => 'Vegan',
		'vegetarian' => 'Vegetarian',
		'gf'         => 'Gluten-free',
	);
}

function mdash_icon_index( $fresh = false ) {
	static $idx = null;
	if ( null === $idx || $fresh ) {
		$f   = mdash_dir( 'icons' ) . '/index.json';
		$idx = file_exists( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : array(); // phpcs:ignore
		$idx = is_array( $idx ) ? array_intersect_key( $idx, mdash_icon_keys() ) : array();
	}
	return $idx;
}

function mdash_icon_save_index( $idx ) {
	file_put_contents( mdash_dir( 'icons' ) . '/index.json', wp_json_encode( $idx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), LOCK_EX ); // phpcs:ignore
	mdash_icon_index( true );
	mdash_purge_caches();
}

/** Drop a custom icon's file, if it has one. */
function mdash_icon_unlink( $icon ) {
	if ( ! empty( $icon['file'] ) ) {
		$f = mdash_dir( 'icons' ) . '/' . basename( $icon['file'] );
		if ( file_exists( $f ) ) {
			@unlink( $f ); // phpcs:ignore
		}
	}
}

/**
 * Replace one icon from an uploaded file.
 * Returns array( 'ok' => bool, 'error' => string ).
 */
function mdash_icon_set( $key, $tmp, $name ) {
	$keys = mdash_icon_keys();
	if ( ! isset( $keys[ $key ] ) ) {
		return array( 'ok' => false, 'error' => 'Unknown icon.' );
	}
	$name = sanitize_text_field( wp_basename( $name ) );
	$head = (string) file_get_contents( $tmp, false, null, 0, 512 ); // phpcs:ignore
	$idx  = mdash_icon_index( true );

	if ( preg_match( '/\.svgz?$/i', $name ) || preg_match( '/<svg[\s>]/i', $head ) ) {
		$clean = mdash_clean_svg( (string) file_get_contents( $tmp ) ); // phpcs:ignore
		if ( ! $clean['ok'] ) {
			return array( 'ok' => false, 'error' => "$name: " . $clean['error'] );
		}
		$new = array( 'type' => 'svg', 'viewBox' => $clean['viewBox'], 'body' => $clean['body'] );
	} else {
		$info = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $tmp ) : @getimagesize( $tmp ); // phpcs:ignore
		if ( ! $info || ! in_array( $info['mime'], array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ) {
			return array( 'ok' => false, 'error' => "$name is not an SVG, PNG, JPEG or WebP image." );
		}
		$editor = wp_get_image_editor( $tmp );
		if ( is_wp_error( $editor ) ) {
			return array( 'ok' => false, 'error' => "$name could not be opened: " . $editor->get_error_message() );
		}
		$editor->resize( 96, 96, false ); // Fits the icon into 96 px; smaller images stay as they are.
		list( $ext, $mime ) = mdash_photo_format( true );
		$file  = $key . '-' . substr( md5( md5_file( $tmp ) . microtime() ), 0, 8 ) . ".$ext";
		$saved = $editor->save( mdash_dir( 'icons' ) . "/$file", $mime );
		if ( is_wp_error( $saved ) ) {
			return array( 'ok' => false, 'error' => "$name could not be saved: " . $saved->get_error_message() );
		}
		$new = array( 'type' => 'img', 'file' => $file );
	}

	if ( isset( $idx[ $key ] ) ) {
		mdash_icon_unlink( $idx[ $key ] );
	}
	$idx[ $key ] = $new + array( 'name' => $name, 'time' => time() );
	mdash_icon_save_index( $idx );
	return array( 'ok' => true, 'error' => '' );
}

function mdash_icon_reset( $key ) {
	$idx = mdash_icon_index( true );
	if ( isset( $idx[ $key ] ) ) {
		mdash_icon_unlink( $idx[ $key ] );
		unset( $idx[ $key ] );
		mdash_icon_save_index( $idx );
	}
}

/** The <svg> sprite the page draws its icons from, with custom SVG icons swapped in. */
function mdash_sprite() {
	$sprite = (string) file_get_contents( MENUDASH_DIR . 'assets/icons.svg' ); // phpcs:ignore
	foreach ( mdash_icon_index() as $key => $icon ) {
		if ( 'svg' !== $icon['type'] ) {
			continue;
		}
		$symbol = '<symbol id="mdash-' . esc_attr( $key ) . '" viewBox="' . esc_attr( $icon['viewBox'] ) . '">' . $icon['body'] . '</symbol>';
		$sprite = preg_replace_callback(
			'#<symbol id="mdash-' . preg_quote( $key, '#' ) . '"[^>]*>.*?</symbol>#s',
			function () use ( $symbol ) {
				return $symbol;
			},
			$sprite
		);
	}
	return $sprite;
}

/** One icon: from the sprite, or the custom picture. */
function mdash_icon( $name, $class = 'mdash-ico' ) {
	$idx = mdash_icon_index();
	if ( isset( $idx[ $name ] ) && 'img' === $idx[ $name ]['type'] ) {
		return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( mdash_url( 'icons/' . $idx[ $name ]['file'] ) ) . '" alt="" aria-hidden="true" width="48" height="48" decoding="async">';
	}
	return '<svg class="' . esc_attr( $class ) . '" aria-hidden="true" focusable="false"><use href="#mdash-' . esc_attr( $name ) . '"></use></svg>';
}
