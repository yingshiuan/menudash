<?php
/**
 * Dish photos: uploads/menudash/photos/.
 *
 * Each upload is re-encoded at 200, 400 and 800 px (WebP when the server can, otherwise PNG so
 * the cut-outs keep their transparency) and the original is thrown away. Every upload gets
 * a new file name, so a replaced photo gets a new URL and no cache can show the old one.
 * photos/index-<random>.json (mdash_index_file()) maps each photo to the name the owner gave it, which is what the
 * matching reads; it lives beside the files so a reinstall finds them again.
 */

defined( 'ABSPATH' ) || exit;

const MDASH_SIZES = array( 800, 400, 200 );
// 40 megapixels, e.g. 7300 × 5500. Opening a larger image could run the server out of memory.
const MDASH_MAX_PIXELS = 40000000;

function mdash_photo_index() {
	$f   = mdash_index_file( 'photos' );
	$idx = file_exists( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : array(); // phpcs:ignore
	return is_array( $idx ) ? $idx : array();
}

function mdash_photo_save_index( $idx ) {
	ksort( $idx );
	file_put_contents( mdash_index_file( 'photos' ), wp_json_encode( $idx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), LOCK_EX ); // phpcs:ignore
	mdash_purge_caches();
}

/** A photo's id is stable for a given file name, so uploading "22_Jiaozi.png" again replaces it. */
function mdash_photo_id( $name ) {
	$base = preg_replace( '/\.[a-z0-9]{2,5}$/i', '', $name );
	return mdash_slug( $base ) . '-' . substr( md5( mdash_fold( $base ) ), 0, 6 );
}

function mdash_photo_url( $p, $size ) {
	return mdash_url( "photos/{$p['file']}-$size.{$p['ext']}" );
}

/** srcset for a dish photo: 200, 400 and 800 px, or 400 and 800 while the 200 isn't made yet. */
function mdash_photo_srcset( $p ) {
	$sizes = ! empty( $p['small'] ) ? array( 200, 400, 800 ) : array( 400, 800 );
	return implode(
		', ',
		array_map(
			function ( $size ) use ( $p ) {
				return mdash_photo_url( $p, $size ) . " {$size}w";
			},
			$sizes
		)
	);
}

/** True when the image file has an alpha channel, i.e. it is a cut-out rather than a square photo. */
function mdash_has_alpha( $path, $mime ) {
	$head = (string) file_get_contents( $path, false, null, 0, 64 ); // phpcs:ignore
	if ( 'image/png' === $mime ) {
		$type = ord( substr( $head, 25, 1 ) );
		// Colour types 4 and 6 carry alpha; palette images may add a tRNS chunk.
		return 4 === $type || 6 === $type || false !== strpos( (string) file_get_contents( $path, false, null, 0, 4096 ), 'tRNS' ); // phpcs:ignore
	}
	if ( 'image/webp' === $mime ) {
		$chunk = substr( $head, 12, 4 );
		return 'VP8L' === $chunk ? (bool) ( ord( substr( $head, 24, 1 ) ) & 0x10 ) : ( 'VP8X' === $chunk && (bool) ( ord( substr( $head, 20, 1 ) ) & 0x10 ) );
	}
	return false;
}

/** The format the resized copies are written in. */
function mdash_photo_format( $alpha ) {
	if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		return array( 'webp', 'image/webp' );
	}
	return $alpha ? array( 'png', 'image/png' ) : array( 'jpg', 'image/jpeg' );
}

/**
 * Add or replace one photo from an uploaded temp file.
 * Returns array( 'ok' => bool, 'error' => string, 'id' => string ).
 */
function mdash_photo_add( $tmp, $name ) {
	$name = sanitize_text_field( wp_basename( $name ) );
	$info = function_exists( 'wp_getimagesize' ) ? wp_getimagesize( $tmp ) : @getimagesize( $tmp ); // phpcs:ignore
	$mime = $info ? $info['mime'] : '';
	if ( ! in_array( $mime, array( 'image/png', 'image/jpeg', 'image/webp' ), true ) ) {
		/* translators: %s: file name. */
		return array( 'ok' => false, 'error' => sprintf( __( '%s is not a PNG, JPEG or WebP image.', 'menudash' ), $name ) );
	}
	if ( $info[0] * $info[1] > MDASH_MAX_PIXELS ) {
		/* translators: 1: file name, 2: width in pixels, 3: height in pixels. */
		return array( 'ok' => false, 'error' => sprintf( __( '%1$s is too large (%2$d × %3$d px); save it at most 6000 px wide.', 'menudash' ), $name, $info[0], $info[1] ) );
	}
	$alpha               = mdash_has_alpha( $tmp, $mime );
	list( $ext, $out )   = mdash_photo_format( $alpha );
	$id                  = mdash_photo_id( $name );
	// New name on every upload, even of the same file: a URL is never reused, so no cache
	// (Cloudflare, browser) can hold an old photo or an old "not found" for it.
	$hash                = substr( md5( md5_file( $tmp ) . microtime() ), 0, 8 );
	$file                = "$id-$hash";
	$dir                 = mdash_dir( 'photos' );

	wp_raise_memory_limit( 'image' );
	$editor = wp_get_image_editor( $tmp );
	if ( is_wp_error( $editor ) ) {
		/* translators: 1: file name, 2: the server's error message. */
		return array( 'ok' => false, 'error' => sprintf( __( '%1$s could not be opened: %2$s', 'menudash' ), $name, $editor->get_error_message() ) );
	}
	$editor->set_quality( 82 );
	$made = array();
	foreach ( MDASH_SIZES as $size ) {
		// resize() refuses to enlarge; a small photo is then saved at its own size.
		$editor->resize( $size, $size, false );
		$saved = $editor->save( "$dir/$file-$size.$ext", $out );
		if ( is_wp_error( $saved ) ) {
			foreach ( $made as $m ) {
				@unlink( $m ); // phpcs:ignore
			}
			/* translators: 1: file name, 2: the server's error message. */
			return array( 'ok' => false, 'error' => sprintf( __( '%1$s could not be saved: %2$s', 'menudash' ), $name, $saved->get_error_message() ) );
		}
		$made[] = $saved['path'];
	}

	$idx = mdash_photo_index();
	if ( isset( $idx[ $id ] ) && $idx[ $id ]['file'] . $idx[ $id ]['ext'] !== $file . $ext ) {
		mdash_photo_unlink( $idx[ $id ] );
	}
	$idx[ $id ] = array(
		'name'  => $name,
		'time'  => time(),
		'file'  => $file,
		'ext'   => $ext,
		'alpha' => $alpha,
		'small' => true,
	);
	mdash_photo_save_index( $idx );
	return array( 'ok' => true, 'error' => '', 'id' => $id );
}

/**
 * Photos uploaded before 2.9.1 have no 200 px copy; make it from the 400 px one, a few
 * seconds' worth per dashboard visit. Until a photo has it, the menu uses the 400 px copy.
 */
add_action( 'admin_init', 'mdash_photo_fill_small' );
function mdash_photo_fill_small() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$idx  = mdash_photo_index();
	$todo = array_filter(
		$idx,
		function ( $p ) {
			return empty( $p['small'] );
		}
	);
	if ( ! $todo ) {
		return;
	}
	$dir   = mdash_dir( 'photos' );
	$start = microtime( true );
	$made  = 0;
	wp_raise_memory_limit( 'image' );
	foreach ( $todo as $id => $p ) {
		if ( microtime( true ) - $start > 5 ) {
			break;
		}
		// The same file-name check as mdash_photo_unlink(): nothing can point outside the folder.
		if ( ! isset( $p['file'], $p['ext'] ) || ! preg_match( '/^[a-z0-9-]+$/', (string) $p['file'] ) || ! preg_match( '/^[a-z0-9]+$/', (string) $p['ext'] ) ) {
			continue;
		}
		$src    = "$dir/{$p['file']}-400.{$p['ext']}";
		$editor = file_exists( $src ) ? wp_get_image_editor( $src ) : null;
		if ( ! $editor || is_wp_error( $editor ) ) {
			continue;
		}
		$editor->set_quality( 82 );
		$editor->resize( 200, 200, false );
		list( , $out ) = mdash_photo_format( ! empty( $p['alpha'] ) );
		if ( ! is_wp_error( $editor->save( "$dir/{$p['file']}-200.{$p['ext']}", $out ) ) ) {
			$idx[ $id ]['small'] = true;
			++$made;
		}
	}
	if ( $made ) {
		mdash_photo_save_index( $idx ); // Also empties the page caches, so the menu gets the new srcset.
	}
}

function mdash_photo_unlink( $p ) {
	// Only file names the store itself makes (a slug and a hash); nothing from index.json
	// can point outside the photos folder.
	if ( ! isset( $p['file'], $p['ext'] ) || ! preg_match( '/^[a-z0-9-]+$/', (string) $p['file'] ) || ! preg_match( '/^[a-z0-9]+$/', (string) $p['ext'] ) ) {
		return;
	}
	$dir = mdash_dir( 'photos' );
	foreach ( MDASH_SIZES as $size ) {
		$f = "$dir/{$p['file']}-$size.{$p['ext']}";
		if ( file_exists( $f ) ) {
			@unlink( $f ); // phpcs:ignore
		}
	}
}

function mdash_photo_delete( $id ) {
	$idx = mdash_photo_index();
	if ( ! isset( $idx[ $id ] ) ) {
		return false;
	}
	mdash_photo_unlink( $idx[ $id ] );
	unset( $idx[ $id ] );
	mdash_photo_save_index( $idx );
	return true;
}

/** Which photo each dish shows, worked out fresh from the live menu and the photo index. */
function mdash_photo_matches( $menu = null, $idx = null ) {
	$menu = $menu ? $menu : mdash_get_menu();
	$idx  = null === $idx ? mdash_photo_index() : $idx;
	if ( ! $menu ) {
		return array( 'dish' => array(), 'unmatched' => array_keys( $idx ), 'spare' => array() );
	}
	return mdash_match_photos( $menu, $idx );
}
