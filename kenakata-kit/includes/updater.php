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
	if ( ! $info['v'] ) {
		// GitHub's API allows only 60 questions an hour per server address, and on shared hosting many sites share
		// one address, so it often says no. The normal release page is not limited that way: its "latest" address
		// redirects to the newest tag, and the zip's address follows from the tag.
		$page = wp_remote_head( 'https://github.com/' . KK_GH_REPO . '/releases/latest', array( 'timeout' => 8, 'redirection' => 0, 'headers' => array( 'User-Agent' => 'Kenakata-Kit/' . KK_VERSION ) ) );
		$loc  = is_wp_error( $page ) ? '' : (string) wp_remote_retrieve_header( $page, 'location' );
		if ( preg_match( '#/releases/tag/(v?(\d+\.\d+(?:\.\d+)?))$#', $loc, $m ) ) {
			$info = array(
				'v'         => $m[2],
				'zip'       => 'https://github.com/' . KK_GH_REPO . '/releases/download/' . $m[1] . '/kenakata-kit-' . $m[2] . '.zip',
				'url'       => 'https://github.com/' . KK_GH_REPO . '/releases/tag/' . $m[1],
				'notes'     => '',
				'published' => '',
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

/**
 * "A new version is ready" box on the Kenakata Kit screens and the WordPress Dashboard, with "Update now"
 * (WordPress installs the zip from GitHub), "Download from GitHub" (to upload by hand) and "What's new".
 * Hidden per version with "Later".
 */
add_action( 'admin_notices', function () {
	global $pagenow;
	if ( ! current_user_can( 'update_plugins' ) && ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$ours = 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'kenakata-kit' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $ours && 'index.php' !== $pagenow ) {
		return;
	}
	$latest = kk_update_latest();
	if ( ! $latest || ! version_compare( $latest['v'], KK_VERSION, '>' ) ) {
		return;
	}
	if ( ! $ours && get_user_meta( get_current_user_id(), 'kk_update_later', true ) === $latest['v'] ) {
		return;
	}
	$file   = plugin_basename( KK_FILE );
	$update = kk_update_allowed() && current_user_can( 'update_plugins' ) ? wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $file ) ), 'upgrade-plugin_' . $file ) : '';
	$later  = wp_nonce_url( add_query_arg( array( 'kk_update_later' => rawurlencode( $latest['v'] ) ) ), 'kk_update_later' );
	echo '<div class="notice notice-info inline kk-update-notice" style="margin:12px 20px 0 0"><p><strong>'
		/* translators: 1: new version 2: installed version */
		. esc_html( sprintf( __( 'Kenakata Kit %1$s is ready (you have %2$s).', 'kenakata-kit' ), $latest['v'], KK_VERSION ) ) . '</strong> '
		. esc_html__( 'Update to get the newest fixes and features. Your settings stay as they are.', 'kenakata-kit' ) . '</p><p>';
	if ( $update ) {
		echo '<a class="button button-primary" href="' . esc_url( $update ) . '">' . esc_html__( 'Update now', 'kenakata-kit' ) . '</a> ';
	}
	echo '<a class="button" href="' . esc_url( $latest['zip'] ) . '">' . esc_html__( 'Download the zip', 'kenakata-kit' ) . '</a> '
		. '<a class="button button-link" href="' . esc_url( $latest['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'What\'s new (GitHub)', 'kenakata-kit' ) . ' ↗</a>';
	if ( ! $ours ) {
		echo ' <a class="button button-link" href="' . esc_url( $later ) . '">' . esc_html__( 'Later', 'kenakata-kit' ) . '</a>';
	}
	echo '</p><p class="description">' . esc_html__( 'Manual update: download the zip, then Plugins → Add New → Upload Plugin → choose the file → "Replace current with uploaded".', 'kenakata-kit' );
	if ( ! kk_update_allowed() ) {
		echo ' <strong>' . esc_html__( 'Your Kenakata Kit update period has ended. Contact us to renew; Pro keeps working meanwhile.', 'kenakata-kit' ) . '</strong>';
	}
	echo '</p></div>';
} );

add_action( 'admin_init', function () {
	if ( isset( $_GET['kk_update_later'] ) && check_admin_referer( 'kk_update_later' ) ) {
		update_user_meta( get_current_user_id(), 'kk_update_later', sanitize_text_field( wp_unslash( $_GET['kk_update_later'] ) ) );
		wp_safe_redirect( remove_query_arg( array( 'kk_update_later', '_wpnonce' ) ) );
		exit;
	}
	// "Check for updates" (License tab): ask GitHub now instead of waiting up to 6 hours.
	if ( isset( $_GET['kk_update_check'] ) && check_admin_referer( 'kk_update_check' ) && current_user_can( 'update_plugins' ) ) {
		delete_site_transient( 'kk_update_latest' );
		delete_site_transient( 'update_plugins' );
		$l = kk_update_latest( true );
		wp_safe_redirect( add_query_arg( 'kk_msg', $l && version_compare( $l['v'], KK_VERSION, '>' ) ? 'update-found' : 'update-none', remove_query_arg( array( 'kk_update_check', '_wpnonce' ) ) ) );
		exit;
	}
} );

/** Link for the "Check for updates" button. */
function kk_update_check_url() {
	return wp_nonce_url( admin_url( 'admin.php?page=kenakata-kit&tab=license&kk_update_check=1' ), 'kk_update_check' );
}
