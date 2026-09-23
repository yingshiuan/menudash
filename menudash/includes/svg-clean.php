<?php
/**
 * Uploaded SVG icon -> safe drawing. Pure PHP (DOM extension, no WordPress), so the tests
 * can run it on its own.
 *
 * SVG is a document format: it can carry scripts, event handlers, links, embedded HTML and
 * external references, which is why WordPress refuses SVG uploads by default. Only plain
 * shapes with their paint survive here: elements from an allowlist, attributes from an
 * allowlist, no url()/javascript: values. Everything else is dropped, and a file that
 * isn't clean XML (or declares a DOCTYPE/entities) is refused whole.
 */

defined( 'MENUDASH_PURE' ) || defined( 'ABSPATH' ) || exit;

const MDASH_SVG_SHAPES = array( 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon' );
const MDASH_SVG_ATTRS  = array(
	'd', 'fill', 'fill-rule', 'clip-rule', 'fill-opacity', 'opacity',
	'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity',
	'transform', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'width', 'height', 'x1', 'y1', 'x2', 'y2', 'points',
);

/**
 * Returns array( 'ok' => bool, 'error' => string, 'viewBox' => string, 'body' => string ):
 * the drawing as markup for a <symbol>, and the viewBox it was drawn in.
 */
function mdash_clean_svg( $svg ) {
	$fail = function ( $error ) {
		return array( 'ok' => false, 'error' => $error, 'viewBox' => '', 'body' => '' );
	};
	if ( ! class_exists( 'DOMDocument' ) ) {
		return $fail( 'This server cannot read SVG files; upload a PNG instead.' );
	}
	if ( strlen( $svg ) > 200 * 1024 ) {
		return $fail( 'The SVG is over 200 KB; an icon is usually a few KB.' );
	}
	if ( preg_match( '/<!DOCTYPE|<!ENTITY/i', $svg ) ) {
		return $fail( 'The SVG declares a DOCTYPE or entities, which icons never need.' );
	}

	// @ rather than libxml_use_internal_errors(): same result, and the latter crashes some
	// PHP builds (WordPress Playground's PHP 7.4) on broken XML.
	$doc  = new DOMDocument();
	$ok   = @$doc->loadXML( $svg, LIBXML_NONET ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	// The text check above misses a DOCTYPE written in another encoding (UTF-16), so ask
	// the parser too.
	if ( $ok && null !== $doc->doctype ) {
		return $fail( 'The SVG declares a DOCTYPE or entities, which icons never need.' );
	}
	$root = $ok ? $doc->documentElement : null;
	if ( ! $root || 'svg' !== strtolower( $root->localName ) ) {
		return $fail( 'This is not an SVG file.' );
	}

	$box = trim( (string) $root->getAttribute( 'viewBox' ) );
	if ( ! preg_match( '/^-?[\d.]+([ ,]+-?[\d.]+){3}$/', $box ) ) {
		$w = (float) $root->getAttribute( 'width' );
		$h = (float) $root->getAttribute( 'height' );
		if ( $w <= 0 || $h <= 0 ) {
			return $fail( 'The SVG has no size (no viewBox, width or height).' );
		}
		$box = "0 0 $w $h";
	}

	$body = mdash_svg_children( $root );
	if ( '' === $body ) {
		return $fail( 'Nothing drawable was found in the SVG.' );
	}
	return array( 'ok' => true, 'error' => '', 'viewBox' => preg_replace( '/[ ,]+/', ' ', $box ), 'body' => $body );
}

/** The allowed shapes inside $node, as markup. Groups are kept, other containers dropped. */
function mdash_svg_children( $node ) {
	$out = '';
	foreach ( $node->childNodes as $child ) {
		if ( XML_ELEMENT_NODE !== $child->nodeType ) {
			continue;
		}
		$tag = strtolower( $child->localName );
		if ( 'g' === $tag ) {
			$inner = mdash_svg_children( $child );
			if ( '' !== $inner ) {
				$out .= '<g' . mdash_svg_attrs( $child ) . '>' . $inner . '</g>';
			}
		} elseif ( in_array( $tag, MDASH_SVG_SHAPES, true ) ) {
			$out .= '<' . $tag . mdash_svg_attrs( $child ) . '/>';
		}
		// Everything else (script, style, a, image, use, foreignObject, defs, mask,
		// clipPath, text…) is dropped with its content.
	}
	return $out;
}

function mdash_svg_attrs( $el ) {
	$keep  = array();
	$style = array();
	foreach ( $el->attributes as $attr ) {
		$name = strtolower( $attr->nodeName );
		if ( 'style' === $name ) {
			// Many editors write the paint as style="fill:#e33;stroke:none". Those
			// declarations become attributes; as in CSS, they win over plain attributes.
			foreach ( explode( ';', $attr->nodeValue ) as $decl ) {
				$pair = array_map( 'trim', explode( ':', $decl, 2 ) );
				if ( 2 === count( $pair ) ) {
					$style[ strtolower( $pair[0] ) ] = $pair[1];
				}
			}
			continue;
		}
		$keep[ $name ] = trim( $attr->nodeValue );
	}
	$keep = array_merge( $keep, $style );
	$out = '';
	foreach ( $keep as $name => $value ) {
		if ( ! in_array( $name, MDASH_SVG_ATTRS, true ) || preg_match( '/url\s*\(|javascript:|expression\s*\(|[<>"]/i', $value ) ) {
			continue;
		}
		$out .= ' ' . $name . '="' . htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '"';
	}
	return $out;
}
