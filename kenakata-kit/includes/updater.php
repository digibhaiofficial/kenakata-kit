<?php
/**
 * Updates from GitHub Releases: WordPress shows "update available" for Kenakata Kit like for any plugin, and
 * "Update now" installs the zip attached to the latest release (kenakata-kit-<version>.zip).
 *
 * Who gets updates: Free sites, and Pro sites whose update period has not ended. Pro sites with an ended period
 * see a short note instead (Pro keeps working). One GitHub request every 6 hours at most.
 *
 * @package KenakataKit
 */

defined( 'ABSPATH' ) || exit;

/** Latest release: array( version, zip, url, notes, published ) or null. Cached for 6 hours. */
function kk_update_latest( $force = false ) {
	if ( false !== strpos( KK_GH_REPO, '__' ) ) {
		return null;
	}
	$cached = get_site_transient( 'kk_update_latest' );
	if ( ! $force && is_array( $cached ) ) {
		return $cached['v'] ? $cached : null;
	}
	$res  = wp_remote_get( 'https://api.github.com/repos/' . KK_GH_REPO . '/releases/latest', array(
		'timeout' => 8,
		'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'Kenakata-Kit/' . KK_VERSION ),
	) );
	$info = array( 'v' => '' );
	if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
		$r   = json_decode( wp_remote_retrieve_body( $res ), true );
		$ver = is_array( $r ) ? ltrim( (string) ( $r['tag_name'] ?? '' ), 'vV' ) : '';
		$zip = '';
		foreach ( (array) ( $r['assets'] ?? array() ) as $a ) {
			if ( preg_match( '/^kenakata-kit-[\d.]+\.zip$/', (string) ( $a['name'] ?? '' ) ) ) {
				$zip = (string) $a['browser_download_url'];
			}
		}
		if ( preg_match( '/^\d+\.\d+(\.\d+)?$/', $ver ) && $zip ) {
			$info = array(
				'v'         => $ver,
				'zip'       => esc_url_raw( $zip ),
				'url'       => esc_url_raw( (string) ( $r['html_url'] ?? '' ) ),
				'notes'     => (string) ( $r['body'] ?? '' ),
				'published' => (string) ( $r['published_at'] ?? '' ),
			);
		}
	}
	set_site_transient( 'kk_update_latest', $info, ( $info['v'] ? 6 : 1 ) * HOUR_IN_SECONDS );
	return $info['v'] ? $info : null;
}

/** May this site install updates? Free: yes. Pro with a key whose update period ended: no. */
function kk_update_allowed() {
	if ( ! function_exists( 'kk_license_state' ) ) {
		return true;
	}
	$s = kk_license_state();
	return ! $s['ok'] || $s['updates_ok'];
}

add_filter( 'pre_set_site_transient_update_plugins', function ( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}
	$file   = plugin_basename( KK_FILE );
	$latest = kk_update_latest();
	if ( ! $latest || ! version_compare( $latest['v'], KK_VERSION, '>' ) ) {
		if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
			$transient->no_update[ $file ] = (object) array( 'id' => $file, 'slug' => 'kenakata-kit', 'plugin' => $file, 'new_version' => KK_VERSION, 'url' => $latest ? $latest['url'] : '', 'package' => '' );
		}
		return $transient;
	}
	$item = (object) array(
		'id'           => $file,
		'slug'         => 'kenakata-kit',
		'plugin'       => $file,
		'new_version'  => $latest['v'],
		'url'          => $latest['url'],
		'package'      => kk_update_allowed() ? $latest['zip'] : '',
		'requires_php' => '7.4',
		'icons'        => array(),
	);
	$transient->response[ $file ] = $item;
	return $transient;
} );

// "View details" in the plugin list.
add_filter( 'plugins_api', function ( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || ! isset( $args->slug ) || 'kenakata-kit' !== $args->slug ) {
		return $result;
	}
	$latest = kk_update_latest();
	if ( ! $latest ) {
		return $result;
	}
	return (object) array(
		'name'          => 'Kenakata Kit',
		'slug'          => 'kenakata-kit',
		'version'       => $latest['v'],
		'author'        => '<a href="https://digibhai.com/">DigiBhai.com</a>',
		'homepage'      => $latest['url'],
		'requires_php'  => '7.4',
		'last_updated'  => $latest['published'],
		'download_link' => kk_update_allowed() ? $latest['zip'] : '',
		'sections'      => array( 'changelog' => wpautop( esc_html( $latest['notes'] ) ) ),
	);
}, 10, 3 );

// Pro sites whose update period ended: say why "Update now" is missing.
add_action( 'in_plugin_update_message-' . plugin_basename( KK_FILE ), function () {
	if ( ! kk_update_allowed() ) {
		echo ' <strong>' . esc_html__( 'Your Kenakata Kit update period has ended. Contact us to renew; Pro keeps working meanwhile.', 'kenakata-kit' ) . '</strong>';
	}
} );

// "Check again" on Dashboard → Updates also asks GitHub again.
add_action( 'load-update-core.php', function () {
	if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		delete_site_transient( 'kk_update_latest' );
	}
} );
