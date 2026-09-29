<?php
/**
 * Updates from GitHub. MenuDash is not on wordpress.org, so WordPress asks us instead: the
 * "Update URI" header in menudash.php points to the GitHub repo, and WordPress (5.8+) then
 * calls the update_plugins_github.com filter when it checks for updates (twice a day, or on
 * "Check again"). We answer with the newest GitHub Release, and WordPress shows the usual
 * "new version available" notice, one-click update and auto-updates. The zip it installs is
 * the release's menudash.zip asset, never "Code → Download ZIP".
 *
 * GitHub only sees a request from the site's server, no site address (own user agent).
 * A site can switch this off with add_filter( 'menudash_github_updates', '__return_false' ).
 */

defined( 'ABSPATH' ) || exit;

define( 'MDASH_REPO', 'yingshiuan/menudash' );

add_filter( 'update_plugins_github.com', 'mdash_update_check', 10, 3 );
add_filter( 'plugins_api', 'mdash_update_details', 10, 3 );
add_action( 'upgrader_process_complete', 'mdash_update_forget', 10, 0 );

/** The newest release as { version, package, url, notes, date }, or null. Cached 12 h (1 h after a failure). */
function mdash_latest_release( $fresh = false ) {
	if ( ! apply_filters( 'menudash_github_updates', true ) ) {
		return null;
	}
	$cached = $fresh ? false : get_site_transient( 'menudash_release' );
	if ( is_array( $cached ) ) {
		return empty( $cached['version'] ) ? null : $cached;
	}

	$res = wp_remote_get(
		'https://api.github.com/repos/' . MDASH_REPO . '/releases/latest',
		array(
			'timeout'    => 10,
			'user-agent' => 'MenuDash/' . MENUDASH_VERSION,
			'headers'    => array( 'Accept' => 'application/vnd.github+json' ),
		)
	);
	$release = null;
	if ( ! is_wp_error( $res ) && 200 === wp_remote_retrieve_response_code( $res ) ) {
		$release = mdash_parse_release( json_decode( wp_remote_retrieve_body( $res ), true ) );
	}
	set_site_transient( 'menudash_release', $release ? $release : array(), $release ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
	return $release;
}

/** Picks what we need from GitHub's answer. Only a menudash.zip from this repo's releases counts. */
function mdash_parse_release( $data ) {
	if ( ! is_array( $data ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
		return null;
	}
	$version = ltrim( (string) ( $data['tag_name'] ?? '' ), 'vV' );
	if ( ! preg_match( '/^\d+(\.\d+){1,3}$/', $version ) ) {
		return null;
	}
	$prefix  = 'https://github.com/' . MDASH_REPO . '/releases/download/';
	$package = '';
	foreach ( (array) ( $data['assets'] ?? array() ) as $asset ) {
		$link = (string) ( $asset['browser_download_url'] ?? '' );
		if ( 'menudash.zip' === ( $asset['name'] ?? '' ) && 0 === strpos( $link, $prefix ) ) {
			$package = $link;
			break;
		}
	}
	if ( '' === $package ) {
		return null;
	}
	return array(
		'version' => $version,
		'package' => $package,
		'url'     => 'https://github.com/' . MDASH_REPO . '/releases/tag/' . rawurlencode( (string) $data['tag_name'] ),
		'notes'   => substr( (string) ( $data['body'] ?? '' ), 0, 20000 ),
		'date'    => (string) ( $data['published_at'] ?? '' ),
	);
}

/** WordPress's update check. It compares the versions itself and only needs ours. */
function mdash_update_check( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( MENUDASH_FILE ) !== $plugin_file ) {
		return $update;
	}
	// "Check again" on Dashboard → Updates should really ask GitHub again.
	$release = mdash_latest_release( is_admin() && isset( $_GET['force-check'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $release ) {
		return $update;
	}
	return array(
		'slug'         => 'menudash',
		'version'      => $release['version'],
		'package'      => $release['package'],
		'url'          => $release['url'],
		'requires'     => '6.3',
		'requires_php' => '7.4',
	);
}

/** The "View version details" window, which would otherwise look MenuDash up on wordpress.org. */
function mdash_update_details( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || 'menudash' !== ( $args->slug ?? '' ) ) {
		return $result;
	}
	$release = mdash_latest_release();
	if ( ! $release ) {
		return $result;
	}
	$notes = '' === trim( $release['notes'] )
		? '<p><a href="' . esc_url( $release['url'] ) . '">' . esc_html( $release['url'] ) . '</a></p>'
		: mdash_update_notes_html( $release['notes'] );
	return (object) array(
		'name'          => 'MenuDash',
		'slug'          => 'menudash',
		'version'       => $release['version'],
		'author'        => '<a href="https://insdash.ch">insdash</a>',
		'homepage'      => 'https://insdash.ch/projects/menudash/',
		'requires'      => '6.3',
		'requires_php'  => '7.4',
		'last_updated'  => $release['date'],
		'download_link' => $release['package'],
		'sections'      => array(
			'changelog' => $notes,
		),
	);
}

/** The release notes (GitHub Markdown) as simple HTML: headings, lists, bold, code, links. */
function mdash_update_notes_html( $md ) {
	$html = '';
	$list = false;
	foreach ( preg_split( '/\R/', $md ) as $line ) {
		$line = trim( $line );
		$item = preg_match( '/^[-*]\s+(.*)$/', $line, $m );
		if ( $list && ! $item ) {
			$html .= '</ul>';
			$list  = false;
		}
		if ( '' === $line ) {
			continue;
		}
		$text = esc_html( $item ? $m[1] : ltrim( $line, '# ' ) );
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\((https?:[^)\s]+)\)/',
			function ( $l ) {
				return '<a href="' . esc_url( html_entity_decode( $l[2] ) ) . '">' . $l[1] . '</a>';
			},
			$text
		);
		if ( $item ) {
			$html .= ( $list ? '' : '<ul>' ) . '<li>' . $text . '</li>';
			$list  = true;
		} elseif ( '#' === $line[0] ) {
			$html .= '<h4>' . $text . '</h4>';
		} else {
			$html .= '<p>' . $text . '</p>';
		}
	}
	return $html . ( $list ? '</ul>' : '' );
}

/** After any update, check fresh next time, so an old answer doesn't linger. */
function mdash_update_forget() {
	delete_site_transient( 'menudash_release' );
}
