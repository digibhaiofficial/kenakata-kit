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

/** Counter of wrong passwords for one account from all addresses (a botnet tries one password per IP). */
function kk_login_user_key( $username ) {
	$username = trim( (string) $username );
	if ( is_email( $username ) && ( $u = get_user_by( 'email', $username ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
		$username = $u->user_login; // email and username count as the same account
	}
	return 'kk_ulock_' . md5( strtolower( $username ) );
}

/*
 * Trusted browsers (3.20): a browser where this account logged in successfully gets a signed cookie for 180 days.
 * While an account is under attack (many wrong passwords from many places), the owner can still log in from such a
 * browser. The cookie only skips the pause: the password is still checked by WordPress.
 */
function kk_trusted_cookie_name() {
	return 'kk_td_' . substr( md5( home_url() ), 0, 8 );
}

function kk_trusted_ids() {
	$raw = isset( $_COOKIE[ kk_trusted_cookie_name() ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ kk_trusted_cookie_name() ] ) ) : '';
	$ids = array();
	foreach ( array_filter( explode( '.', $raw ) ) as $part ) {
		$bits = explode( '-', $part );
		if ( 3 === count( $bits ) && (int) $bits[1] > time() && hash_equals( substr( wp_hash( 'kk_td|' . $bits[0] . '|' . $bits[1], 'auth' ), 0, 20 ), $bits[2] ) ) {
			$ids[] = (int) $bits[0];
		}
	}
	return $ids;
}

function kk_trust_this_browser( $user_id ) {
	$exp   = time() + 180 * DAY_IN_SECONDS;
	$ids   = array_slice( array_unique( array_merge( array( absint( $user_id ) ), kk_trusted_ids() ) ), 0, 3 ); // a few accounts per browser
	$parts = array();
	foreach ( $ids as $id ) {
		$parts[] = $id . '-' . $exp . '-' . substr( wp_hash( 'kk_td|' . $id . '|' . $exp, 'auth' ), 0, 20 );
	}
	if ( ! headers_sent() ) {
		setcookie( kk_trusted_cookie_name(), implode( '.', $parts ), array( 'expires' => $exp, 'path' => COOKIEPATH ? COOKIEPATH : '/', 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
	}
}

/** Does this browser belong to the account being logged into? */
function kk_login_trusted_for( $username ) {
	$username = trim( (string) $username );
	if ( '' === $username ) {
		return false;
	}
	$u = is_email( $username ) ? get_user_by( 'email', $username ) : get_user_by( 'login', $username );
	return $u && in_array( (int) $u->ID, kk_trusted_ids(), true );
}

/** Remember a pause, so the Security tab can list it and lift it. */
function kk_login_note_lock( $type, $label, $key, $until ) {
	$locks = (array) get_option( 'kk_login_locks', array() );
	foreach ( $locks as $k => $l ) {
		if ( (int) ( $l['until'] ?? 0 ) < time() ) {
			unset( $locks[ $k ] );
		}
	}
	$locks[ $key ] = array( 'type' => $type, 'label' => $label, 'until' => $until );
	update_option( 'kk_login_locks', array_slice( $locks, -30, 30, true ), false );
}

if ( kk_opt( 'sec_limit_login' ) ) {
	add_action( 'wp_login_failed', function ( $username = '' ) {
		$limit = max( 3, absint( kk_opt( 'sec_login_attempts' ) ) );
		$key   = kk_login_lock_key();
		$n     = (int) get_transient( $key ) + 1;
		set_transient( $key, $n, 15 * MINUTE_IN_SECONDS );
		if ( $n === $limit ) {
			kk_login_note_lock( 'ip', kk_client_ip(), $key, time() + 15 * MINUTE_IN_SECONDS );
		}
		if ( '' !== (string) $username && ( username_exists( $username ) || ( is_email( $username ) && email_exists( $username ) ) ) ) { // only real accounts: no rows for made-up names
			$ukey = kk_login_user_key( $username );
			$un   = (int) get_transient( $ukey ) + 1;
			set_transient( $ukey, $un, HOUR_IN_SECONDS );
			if ( $un === max( 20, $limit * 5 ) ) {
				kk_login_note_lock( 'account', (string) $username, $ukey, time() + HOUR_IN_SECONDS );
			}
		}
	} );
	add_filter( 'authenticate', function ( $user, $username = '' ) {
		if ( kk_login_trusted_for( $username ) ) {
			return $user; // the owner's own browser: never paused (the password is still checked)
		}
		$limit = max( 3, absint( kk_opt( 'sec_login_attempts' ) ) );
		$tries = (int) get_transient( kk_login_lock_key() );
		// One account: a much higher limit over an hour, so a real owner is rarely affected but a spread-out attack stops.
		$utries = '' !== (string) $username ? (int) get_transient( kk_login_user_key( $username ) ) : 0;
		if ( $tries >= $limit || $utries >= max( 20, $limit * 5 ) ) {
			$msg = $utries >= max( 20, $limit * 5 )
				? __( 'This account is paused for a while because of many wrong passwords from different places (probably bots). Log in from a browser you used before, or reset your password:', 'kenakata-kit' )
				: __( 'Too many wrong passwords. For your security, login is paused for 15 minutes. Forgot your password?', 'kenakata-kit' );
			return new WP_Error( 'kk_login_locked', esc_html( $msg ) . ' <a href="' . esc_url( wp_lostpassword_url() ) . '">' . esc_html__( 'Reset password', 'kenakata-kit' ) . '</a>' );
		}
		return $user;
	}, 99, 3 );
	add_action( 'wp_login', function ( $login = '', $user = null ) {
		delete_transient( kk_login_lock_key() );
		if ( $login ) {
			delete_transient( kk_login_user_key( $login ) );
		}
		if ( $user instanceof WP_User ) {
			kk_trust_this_browser( $user->ID );
		}
	}, 10, 2 );
	// A password reset by email lifts the pause for that account.
	add_action( 'after_password_reset', function ( $user ) {
		if ( $user instanceof WP_User ) {
			delete_transient( kk_login_user_key( $user->user_login ) );
		}
	} );
}

/** Lift every pause now (Security tab button). */
add_action( 'admin_post_kk_login_unlock', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'kk_login_unlock' ) ) {
		wp_die( esc_html__( 'This link has expired. Go back and open it again.', 'kenakata-kit' ), '', array( 'response' => 403 ) );
	}
	foreach ( array_keys( (array) get_option( 'kk_login_locks', array() ) ) as $key ) {
		delete_transient( $key );
	}
	update_option( 'kk_login_locks', array(), false );
	wp_safe_redirect( add_query_arg( 'kk_unlocked', 1, admin_url( 'admin.php?page=kenakata-kit&tab=security' ) ) );
	exit;
} );

/** HTML for the Security tab: pauses now in force, with one button to lift them all. */
function kk_login_locks_html() {
	$now   = array();
	foreach ( (array) get_option( 'kk_login_locks', array() ) as $key => $l ) {
		if ( (int) ( $l['until'] ?? 0 ) > time() && get_transient( $key ) ) {
			$now[] = $l;
		}
	}
	$out = isset( $_GET['kk_unlocked'] ) ? '<p class="kk-saved">' . esc_html__( 'All pauses lifted.', 'kenakata-kit' ) . '</p>' : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display only
	if ( ! $now ) {
		return $out . '<p>' . esc_html__( 'No login is paused right now.', 'kenakata-kit' ) . '</p>';
	}
	$out .= '<ul style="margin:0 0 8px;list-style:disc;padding-left:20px">';
	foreach ( $now as $l ) {
		/* translators: 1: account name or IP address 2: minutes left */
		$out .= '<li>' . esc_html( sprintf( 'account' === $l['type'] ? __( 'Account %1$s: %2$d more minutes', 'kenakata-kit' ) : __( 'Connection %1$s: %2$d more minutes', 'kenakata-kit' ), $l['label'], max( 1, (int) ceil( ( $l['until'] - time() ) / 60 ) ) ) ) . '</li>';
	}
	$out .= '</ul><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=kk_login_unlock' ), 'kk_login_unlock' ) ) . '">' . esc_html__( 'Lift all pauses now', 'kenakata-kit' ) . '</a>';
	return $out;
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
