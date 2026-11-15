<?php
/**
 * Hardening switches: force https, hide user names, disable XML-RPC, login protection,
 * security headers, hide version, no code editor, no PHP in uploads.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The visitor's IP address, used for rate limits. REMOTE_ADDR is trusted; the Cloudflare header is
 * used only when the request really comes from Cloudflare (otherwise anyone could fake it).
 */
function kk_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && kk_ip_in_ranges( $ip, kk_cloudflare_ranges() ) ) {
		$cf = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
			$ip = $cf;
		}
	}
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

/** Cloudflare's published IP ranges (https://www.cloudflare.com/ips/). */
function kk_cloudflare_ranges() {
	return apply_filters( 'kk_cloudflare_ranges', array(
		'173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18', '108.162.192.0/18',
		'190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
		'104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
		'2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
	) );
}

/** Is an IP inside one of the CIDR ranges (IPv4 and IPv6)? */
function kk_ip_in_ranges( $ip, array $ranges ) {
	$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( false === $bin ) {
		return false;
	}
	foreach ( $ranges as $range ) {
		list( $net, $bits ) = array_pad( explode( '/', $range, 2 ), 2, null );
		$nbin = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $nbin || strlen( $nbin ) !== strlen( $bin ) ) {
			continue;
		}
		$bits  = null === $bits ? strlen( $bin ) * 8 : (int) $bits;
		$bytes = intdiv( $bits, 8 );
		$rest  = $bits % 8;
		if ( substr( $bin, 0, $bytes ) !== substr( $nbin, 0, $bytes ) ) {
			continue;
		}
		if ( 0 === $rest || ( ( ord( $bin[ $bytes ] ) ^ ord( $nbin[ $bytes ] ) ) & ( 0xFF << ( 8 - $rest ) ) & 0xFF ) === 0 ) {
			return true;
		}
	}
	return false;
}

/** Is the current request served over https (also behind proxies / Cloudflare)? */
function kk_request_is_https() {
	if ( is_ssl() ) {
		return true;
	}
	$proto = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) : '';
	$cf    = isset( $_SERVER['HTTP_CF_VISITOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_VISITOR'] ) ) : '';
	return 'https' === $proto || false !== strpos( $cf, 'https' );
}

if ( kk_opt( 'sec_force_https' ) ) {
	add_action( 'template_redirect', function () {
		if ( kk_request_is_https() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : wp_parse_url( home_url(), PHP_URL_HOST );
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		if ( wp_parse_url( home_url(), PHP_URL_HOST ) !== $host ) {
			return; // only redirect our own host name
		}
		wp_safe_redirect( 'https://' . $host . $uri, 301 );
		exit;
	}, 0 );
}

if ( kk_opt( 'sec_hide_users' ) ) {
	// REST: no user list for visitors.
	add_filter( 'rest_endpoints', function ( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( preg_match( '#^/wp/v2/users(/|$)#', $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	} );
	// ?author=1 and /author/name/ pages.
	add_action( 'template_redirect', function () {
		if ( is_user_logged_in() ) {
			return;
		}
		if ( isset( $_GET['author'] ) || is_author() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}, 1 );
	// Core + Yoast author sitemaps, oEmbed author data.
	add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) { return 'users' === $name ? false : $provider; }, 10, 2 );
	add_filter( 'wpseo_sitemap_exclude_author', function () { return array(); } );
	add_filter( 'oembed_response_data', function ( $data ) {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	} );
}

if ( kk_opt( 'sec_disable_xmlrpc' ) ) {
	add_filter( 'xmlrpc_enabled', '__return_false' );
	add_filter( 'xmlrpc_methods', function () { return array(); } ); // also pingback.ping, often abused for attacks
	add_filter( 'wp_headers', function ( $headers ) { unset( $headers['X-Pingback'] ); return $headers; } );
}

/* -------------------------------------------------------------------------
 * Login protection: after too many wrong passwords from one connection, every login form
 * (wp-login.php, WooCommerce My account, the login popup, XML-RPC) is locked for that
 * connection for 15 minutes. Application passwords (REST API) are not affected.
 * ---------------------------------------------------------------------- */
function kk_login_lock_key() {
	return 'kk_lock_' . md5( kk_client_ip() );
}

if ( kk_opt( 'sec_limit_login' ) ) {
	add_action( 'wp_login_failed', function () {
		$key = kk_login_lock_key();
		set_transient( $key, (int) get_transient( $key ) + 1, 15 * MINUTE_IN_SECONDS );
	} );
	add_filter( 'authenticate', function ( $user ) {
		$tries = (int) get_transient( kk_login_lock_key() );
		if ( $tries >= max( 3, absint( kk_opt( 'sec_login_attempts' ) ) ) ) {
			return new WP_Error( 'kk_login_locked', __( 'Too many wrong passwords. For your security, login is paused for 15 minutes.', 'kenakata-kit' ) );
		}
		return $user;
	}, 99 );
	add_action( 'wp_login', function () {
		delete_transient( kk_login_lock_key() );
	} );
}

/* -------------------------------------------------------------------------
 * Security headers for visitors (browser protections), and fewer version details.
 * ---------------------------------------------------------------------- */
function kk_security_headers() {
	if ( headers_sent() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' ); // other sites cannot show the shop inside a frame (click-jacking)
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self)' );
	if ( function_exists( 'header_remove' ) ) {
		header_remove( 'X-Powered-By' ); // hides the PHP version
	}
	if ( kk_opt( 'sec_force_https' ) && is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=15552000' ); // 180 days; no subdomains, no preload
	}
}
if ( kk_opt( 'sec_headers' ) ) {
	add_action( 'send_headers', 'kk_security_headers' );
	add_action( 'login_init', 'kk_security_headers' );
}

if ( kk_opt( 'sec_hide_version' ) ) {
	remove_action( 'wp_head', 'wp_generator' );
	add_filter( 'the_generator', '__return_empty_string' );
}

// The theme/plugin code editor in the admin is a favourite target after a password leak.
if ( kk_opt( 'sec_no_file_edit' ) ) {
	add_filter( 'map_meta_cap', function ( $caps, $cap ) {
		return in_array( $cap, array( 'edit_themes', 'edit_plugins', 'edit_files' ), true ) ? array( 'do_not_allow' ) : $caps;
	}, 10, 2 );
}

/* -------------------------------------------------------------------------
 * Block PHP files in the uploads folder (Apache / LiteSpeed .htaccess). Uploaded files are never
 * supposed to run; this stops a malicious file from running even if one gets uploaded.
 * The rules are written / removed when the setting changes (between "# BEGIN Kenakata Kit" markers).
 * ---------------------------------------------------------------------- */
function kk_sync_uploads_protection() {
	$up = wp_upload_dir( null, false );
	if ( empty( $up['basedir'] ) || ! is_dir( $up['basedir'] ) ) {
		return false;
	}
	$file = trailingslashit( $up['basedir'] ) . '.htaccess';
	if ( ! function_exists( 'insert_with_markers' ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
	}
	$rules = kk_opt( 'sec_uploads_php' ) ? array(
		'<FilesMatch "\.(?i:php[0-9]?|phtml|pht|phar)$">',
		'<IfModule mod_authz_core.c>',
		'Require all denied',
		'</IfModule>',
		'<IfModule !mod_authz_core.c>',
		'Order allow,deny',
		'Deny from all',
		'</IfModule>',
		'</FilesMatch>',
	) : array();
	if ( ! $rules && ! file_exists( $file ) ) {
		return true;
	}
	return insert_with_markers( $file, 'Kenakata Kit', $rules );
}
add_action( 'update_option_kk_settings', function ( $old, $new ) {
	if ( (int) ( $old['sec_uploads_php'] ?? 0 ) !== (int) ( $new['sec_uploads_php'] ?? 0 ) ) {
		kk_all_opts( true );
		kk_sync_uploads_protection();
	}
}, 20, 2 );
