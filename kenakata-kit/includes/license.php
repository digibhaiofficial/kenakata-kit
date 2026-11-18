<?php
/**
 * License: Free / Pro.
 *
 * A license key is a small signed note: "KK1.<payload>.<signature>". The payload (base64url JSON) says which
 * domains may use Pro and until when updates are included; the signature (Ed25519) is made with the shop owner's
 * private key in the license panel. The plugin only holds the public key, so it can check a key but never make one.
 * No license server: checking happens here, offline. A daily look at revoked.json (in the public GitHub repo)
 * switches off keys that were taken back.
 *
 * Pro = store dashboard, courier booking & check, incomplete orders. Everything else is Free.
 * When the update period ends, Pro keeps working; only new updates stop. The plugin never breaks a shop.
 *
 * @package KenakataKit
 */

defined( 'ABSPATH' ) || exit;

/** Ed25519 public key of the license panel (base64). Only the matching private key can sign valid keys. */
if ( ! defined( 'KK_LICENSE_PUBKEY' ) ) {
	define( 'KK_LICENSE_PUBKEY', 'JGQVoQMA0IEGc9qJNseU9uDPREpiFxPwvlzaAB7CQ0I=' );
}
/**
 * Ed25519 public key that checks the update zips (3.26). GitHub signs each release zip with the matching private
 * key (kept as a GitHub secret, not in the code); the updater installs a zip only when its signature is right.
 */
if ( ! defined( 'KK_RELEASE_PUBKEY' ) ) {
	define( 'KK_RELEASE_PUBKEY', 'cPoC703dXRQWV1V5e3ENKopFDcQ+F5isLcJm4nSczw0=' );
}
/** GitHub repository (owner/name) for updates and the list of taken-back keys. */
if ( ! defined( 'KK_GH_REPO' ) ) {
	define( 'KK_GH_REPO', 'digibhaiofficial/kenakata-kit' );
}

/** The site's host name, lower case, without "www." and port. */
function kk_license_host( $url = '' ) {
	$host = strtolower( (string) wp_parse_url( $url ? $url : home_url( '/' ), PHP_URL_HOST ) );
	return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
}

/** Development copies run Pro without a key: localhost, 127.0.0.1 and *.local / *.test / *.localhost names. */
function kk_license_is_dev_host( $host ) {
	if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
		return true;
	}
	return (bool) preg_match( '/\.(local|test|localhost)$/', $host );
}

function kk_b64url_decode( $s ) {
	$s = strtr( (string) $s, '-_', '+/' );
	$p = strlen( $s ) % 4;
	if ( $p ) {
		$s .= str_repeat( '=', 4 - $p );
	}
	return base64_decode( $s, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
}

/**
 * Check a key. Returns array( 'ok' => bool, 'code' => string, 'data' => payload|null ).
 * Codes: ok, empty, format, signature, domain, nocrypto.
 */
function kk_license_check( $key, $host = null ) {
	$key  = trim( preg_replace( '/\s+/', '', (string) $key ) );
	$host = null === $host ? kk_license_host() : $host;
	if ( '' === $key ) {
		return array( 'ok' => false, 'code' => 'empty', 'data' => null );
	}
	$parts = explode( '.', $key );
	if ( 3 !== count( $parts ) || 'KK1' !== $parts[0] ) {
		return array( 'ok' => false, 'code' => 'format', 'data' => null );
	}
	$json = kk_b64url_decode( $parts[1] );
	$sig  = kk_b64url_decode( $parts[2] );
	$pub  = base64_decode( KK_LICENSE_PUBKEY, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	$data = false === $json ? null : json_decode( $json, true );
	if ( ! is_array( $data ) || false === $sig || 64 !== strlen( $sig ) || false === $pub || 32 !== strlen( $pub ) ) {
		return array( 'ok' => false, 'code' => 'format', 'data' => null );
	}
	if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
		return array( 'ok' => false, 'code' => 'nocrypto', 'data' => $data ); // WordPress 5.2+ ships it; very old sites only
	}
	try {
		$valid = sodium_crypto_sign_verify_detached( $sig, $parts[1], $pub );
	} catch ( Exception $e ) {
		$valid = false;
	}
	if ( ! $valid ) {
		return array( 'ok' => false, 'code' => 'signature', 'data' => null );
	}
	$match = false;
	foreach ( (array) ( $data['d'] ?? array() ) as $d ) {
		$d = kk_license_host( 'https://' . preg_replace( '#^https?://#i', '', trim( (string) $d ) ) );
		if ( '' !== $d && ( $host === $d || substr( $host, -strlen( '.' . $d ) ) === '.' . $d ) ) {
			$match = true; // the domain and its sub-domains (staging.shop.com, m.shop.com)
			break;
		}
	}
	if ( ! $match ) {
		return array( 'ok' => false, 'code' => 'domain', 'data' => $data );
	}
	return array( 'ok' => true, 'code' => 'ok', 'data' => $data );
}

/** Saved key + its checked state for this host (cached; re-checked when the key or the site address changes). */
function kk_license_state() {
	static $state = null;
	if ( null !== $state ) {
		return $state;
	}
	$key   = (string) get_option( 'kk_license_key', '' );
	$host  = kk_license_host();
	$cache = get_option( 'kk_license_cache', array() );
	$sig   = md5( $key . '|' . $host . '|' . KK_LICENSE_PUBKEY );
	if ( is_array( $cache ) && isset( $cache['sig'], $cache['res'] ) && $cache['sig'] === $sig ) {
		$res = $cache['res'];
	} else {
		$res = kk_license_check( $key, $host );
		update_option( 'kk_license_cache', array( 'sig' => $sig, 'res' => $res ), true );
	}
	if ( $res['ok'] && in_array( (string) ( $res['data']['id'] ?? '' ), kk_license_revoked(), true ) ) {
		$res = array( 'ok' => false, 'code' => 'revoked', 'data' => $res['data'] );
	}
	$res['key']  = $key;
	$res['host'] = $host;
	$res['dev']  = kk_license_is_dev_host( $host );
	$exp         = (string) ( $res['data']['e'] ?? '' );
	$res['expires']      = $exp; // '' = lifetime
	$res['updates_ok']   = in_array( $exp, array( '', '0' ), true ) || $exp >= gmdate( 'Y-m-d' ); // '' or 0 = no end date
	$state = $res;
	return $state;
}

/** Is Pro active on this site? */
function kk_is_pro() {
	$s = kk_license_state();
	return (bool) apply_filters( 'kk_is_pro', $s['ok'] || $s['dev'] );
}

/* -------------------------------------------------------------------------
 * Taken-back keys: revoked.json in the GitHub repo, checked once a day.
 * ---------------------------------------------------------------------- */
function kk_license_revoked() {
	$list = get_option( 'kk_license_revoked', array() );
	return is_array( $list ) ? array_map( 'strval', $list ) : array();
}

function kk_license_fetch_revoked() {
	if ( false !== strpos( KK_GH_REPO, '__' ) ) {
		return;
	}
	$res = wp_remote_get( 'https://raw.githubusercontent.com/' . KK_GH_REPO . '/main/revoked.json', array( 'timeout' => 8 ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return; // keep the last known list; a network hiccup never switches Pro off
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( is_array( $data ) ) {
		$ids = isset( $data['revoked'] ) && is_array( $data['revoked'] ) ? $data['revoked'] : $data;
		update_option( 'kk_license_revoked', array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'strval', $ids ) ) ) ), true ); // read on every request: autoload
	}
}

add_action( 'kk_license_daily', 'kk_license_fetch_revoked' );
add_action( 'init', function () {
	if ( get_option( 'kk_license_key' ) && ! wp_next_scheduled( 'kk_license_daily' ) ) {
		wp_schedule_event( time() + 300, 'daily', 'kk_license_daily' );
	}
} );

/* -------------------------------------------------------------------------
 * Saving / removing the key (Kenakata Kit → License).
 * ---------------------------------------------------------------------- */
add_action( 'admin_post_kk_license_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'kenakata-kit' ) );
	}
	check_admin_referer( 'kk_license_save' );
	$remove = ! empty( $_POST['kk_license_remove'] );
	$key    = $remove ? '' : trim( preg_replace( '/\s+/', '', sanitize_text_field( wp_unslash( $_POST['kk_license_key'] ?? '' ) ) ) );
	$back = admin_url( 'admin.php?page=kenakata-kit&tab=license' );
	if ( '' !== $key ) {
		// Check first: a key that does not work here is never saved (the working key, if any, stays).
		$check = kk_license_check( $key );
		if ( $check['ok'] && in_array( (string) ( $check['data']['id'] ?? '' ), kk_license_revoked(), true ) ) {
			$check = array( 'ok' => false, 'code' => 'revoked', 'data' => $check['data'] );
		}
		if ( ! $check['ok'] ) {
			$for = 'domain' === $check['code'] ? implode( ', ', array_map( 'strval', (array) ( $check['data']['d'] ?? array() ) ) ) : '';
			wp_safe_redirect( add_query_arg( array( 'kk_msg' => 'license-bad', 'kk_code' => $check['code'], 'kk_for' => rawurlencode( substr( $for, 0, 200 ) ) ), $back ) );
			exit;
		}
	}
	update_option( 'kk_license_key', $key, true );
	delete_option( 'kk_license_cache' );
	if ( $key ) {
		kk_license_fetch_revoked();
	}
	delete_site_transient( 'update_plugins' ); // offer an update right away when the key allows it
	wp_safe_redirect( add_query_arg( 'kk_msg', $remove ? 'license-removed' : 'license-saved', $back ) );
	exit;
} );

/** Plain-language text for a check result. */
function kk_license_message( $code ) {
	$m = array(
		'ok'        => __( 'Pro is active on this site.', 'kenakata-kit' ),
		'empty'     => __( 'No license key yet. The Free features work without one.', 'kenakata-kit' ),
		'format'    => __( 'This does not look like a Kenakata Kit license key. Copy the whole key (it starts with KK1.) and try again.', 'kenakata-kit' ),
		'signature' => __( 'This key is not valid. Please check that you copied all of it, or contact us.', 'kenakata-kit' ),
		'domain'    => __( 'This key was made for another website address. Contact us to move it to this site.', 'kenakata-kit' ),
		'revoked'   => __( 'This key was cancelled. Please contact us.', 'kenakata-kit' ),
		'nocrypto'  => __( 'This server cannot check license keys (WordPress is too old). Please update WordPress.', 'kenakata-kit' ),
	);
	return isset( $m[ $code ] ) ? $m[ $code ] : $m['format'];
}

/** WhatsApp number that sells Pro keys (country code, digits only). Empty = no "buy" button. */
if ( ! defined( 'KK_SALES_WHATSAPP' ) ) {
	define( 'KK_SALES_WHATSAPP', '8801728366779' );
}

/** wa.me link with a ready message (this site's address included), or '' when no sales number is set. */
function kk_buy_pro_url() {
	$num = preg_replace( '/\D/', '', (string) apply_filters( 'kk_sales_whatsapp', KK_SALES_WHATSAPP ) );
	if ( '' === $num ) {
		return '';
	}
	$host = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
	/* translators: %s: website address */
	$text = sprintf( __( 'Hello, I want to buy Kenakata Kit Pro. My website: %s', 'kenakata-kit' ), $host );
	return 'https://wa.me/' . $num . '?text=' . rawurlencode( $text );
}

/** The green "buy on WhatsApp" button (nothing when no sales number is set or the key already works). */
function kk_buy_pro_button() {
	$url = kk_buy_pro_url();
	if ( '' === $url || ( function_exists( 'kk_is_pro' ) && kk_license_state()['ok'] ) ) {
		return '';
	}
	return '<a class="button kk-buy-pro" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" style="background:#25D366;border-color:#1EBE5A;color:#fff">'
		. esc_html__( 'No key yet? Order on WhatsApp', 'kenakata-kit' ) . '</a>';
}

/** The License tab in Kenakata Kit settings. */
function kk_render_license_page() {
	$s     = kk_license_state();
	$data  = is_array( $s['data'] ) ? $s['data'] : array();
	$is_ok = $s['ok'];
	$admin = current_user_can( 'manage_options' );
	$logo = '<img src="' . esc_url( KK_URL . 'assets/img/logo-128.png' ) . '" alt="">';
	if ( $is_ok ) {
		$domains = implode( ', ', array_map( 'strval', (array) ( $data['d'] ?? array() ) ) );
		echo '<div class="kk-lic-hero">' . $logo . '<div><h3>' . esc_html__( 'Kenakata Kit PRO is activated', 'kenakata-kit' ) . ' ✓</h3><p>' . esc_html__( 'All Pro features are open on this website.', 'kenakata-kit' ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $logo escaped above
		echo '<div class="kk-lic-grid">';
		echo '<div><span>' . esc_html__( 'Key number', 'kenakata-kit' ) . '</span><b>' . esc_html( (string) ( $data['id'] ?? '' ) ) . '</b></div>';
		echo '<div><span>' . esc_html__( 'Website(s)', 'kenakata-kit' ) . '</span><b>' . esc_html( $domains ) . '</b></div>';
		echo '<div><span>' . esc_html__( 'Updates until', 'kenakata-kit' ) . '</span><b>' . esc_html( '' === $s['expires'] ? __( 'Lifetime', 'kenakata-kit' ) : date_i18n( get_option( 'date_format' ), strtotime( $s['expires'] . ' 12:00:00' ) ) ) . '</b></div>';
		echo '</div>';
		if ( ! $s['updates_ok'] ) {
			echo '<p class="kk-notice-err">' . esc_html__( 'Your update period has ended. Pro keeps working, but new versions are not offered. Contact us to renew.', 'kenakata-kit' ) . '</p>';
		}
	} else {
		$bad = 'empty' !== $s['code'];
		$for = $bad && 'domain' === $s['code'] ? ' ' . sprintf( /* translators: %s: website(s) the key was made for */ __( 'The saved key is for: %s.', 'kenakata-kit' ), implode( ', ', array_map( 'strval', (array) ( $data['d'] ?? array() ) ) ) ) : '';
		echo '<div class="kk-lic-hero kk-lic-hero--free' . ( $bad ? ' kk-lic-hero--bad' : '' ) . '">' . $logo . '<div><h3>' . esc_html( $bad ? __( 'License key not working', 'kenakata-kit' ) : __( 'Kenakata Kit Free', 'kenakata-kit' ) ) . '</h3><p>' . esc_html( kk_license_message( $s['code'] ) . $for ) . '</p></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $logo escaped above
		if ( $s['dev'] ) {
			echo '<p class="kk-card__desc">' . esc_html__( 'This is a test copy (localhost / .local / .test), so all Pro features are open here without a key.', 'kenakata-kit' ) . '</p>';
		}
	}
	echo '<div class="kk-card">';
	if ( ! $admin ) {
		echo '<p class="kk-card__desc">' . esc_html__( 'Only an administrator can add or change the license key.', 'kenakata-kit' ) . '</p></div>';
		return;
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'kk_license_save' );
	echo '<input type="hidden" name="action" value="kk_license_save">';
	echo '<p><label for="kk_license_key"><strong>' . esc_html__( 'License key', 'kenakata-kit' ) . '</strong></label><br>';
	echo '<textarea id="kk_license_key" name="kk_license_key" rows="4" class="large-text code" spellcheck="false" autocomplete="off" placeholder="KK1.">' . esc_textarea( $s['key'] ) . '</textarea></p>';
	echo '<p class="kk-card__desc">' . sprintf(
		/* translators: %s: this site's address */
		esc_html__( 'The key must be made for this address: %s (sub-domains are included).', 'kenakata-kit' ),
		'<code>' . esc_html( $s['host'] ) . '</code>'
	) . '</p>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save key', 'kenakata-kit' ) . '</button> ';
	if ( '' !== $s['key'] ) {
		echo '<button type="submit" name="kk_license_remove" value="1" class="button">' . esc_html__( 'Remove key', 'kenakata-kit' ) . '</button>';
	}
	echo kk_buy_pro_button(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	if ( $is_ok ) {
		echo '<p class="kk-card__desc">' . esc_html__( 'Moving to another website? Remove the key here, then ask us to move it to the new address.', 'kenakata-kit' ) . '</p>';
	}
	echo '</p></form></div>';

	// Updates: installed version, newest on GitHub, check now.
	$latest = function_exists( 'kk_update_latest' ) ? kk_update_latest() : null;
	echo '<div class="kk-card"><h3>' . esc_html__( 'Updates', 'kenakata-kit' ) . '</h3><p class="kk-card__desc">';
	/* translators: %s: version */
	echo esc_html( sprintf( __( 'Installed: %s.', 'kenakata-kit' ), KK_VERSION ) ) . ' ';
	if ( $latest && version_compare( $latest['v'], KK_VERSION, '>' ) ) {
		/* translators: %s: version */
		echo '<strong>' . esc_html( sprintf( __( 'Newest: %s (see the box above to update).', 'kenakata-kit' ), $latest['v'] ) ) . '</strong>';
	} elseif ( $latest ) {
		echo esc_html__( 'You have the newest version.', 'kenakata-kit' );
	}
	echo '</p>';
	if ( current_user_can( 'update_plugins' ) && function_exists( 'kk_update_check_url' ) ) {
		echo '<p><a class="button" href="' . esc_url( kk_update_check_url() ) . '">' . esc_html__( 'Check for updates now', 'kenakata-kit' ) . '</a></p>';
	}
	echo '</div>';

	echo '<div class="kk-card"><h3>' . esc_html__( 'Free and Pro', 'kenakata-kit' ) . '</h3>';
	echo '<p class="kk-card__desc">' . esc_html__( 'Free: product cards, Buy Now, checkout, delivery charges, fake-order protection, side cart, search, wishlist, reviews, questions, order tracking, security and all Elementor widgets.', 'kenakata-kit' ) . '</p>';
	echo '<p class="kk-card__desc">' . esc_html__( 'Pro: store dashboard, one-tap courier booking and courier check, incomplete orders.', 'kenakata-kit' ) . '</p>';
	$buy = kk_buy_pro_button();
	echo '' !== $buy ? '<p>' . $buy . '</p></div>' : '<p class="kk-card__desc">' . esc_html__( 'To buy a key, contact DigiBhai.com. Tell us your website address.', 'kenakata-kit' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
}

/** Shown instead of a Pro tab's settings when there is no valid key. */
function kk_render_pro_locked( $label ) {
	echo '<div class="kk-card"><p><span class="kk-pro-badge">PRO</span> <strong>' . sprintf(
		/* translators: %s: feature name */
		esc_html__( '"%s" is a Pro feature.', 'kenakata-kit' ),
		esc_html( $label )
	) . '</strong></p>';
	echo '<p class="kk-card__desc">' . esc_html__( 'Add a license key to turn it on. Your other settings and data are kept.', 'kenakata-kit' ) . '</p>';
	echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=kenakata-kit&tab=license' ) ) . '">' . esc_html__( 'Add license key', 'kenakata-kit' ) . '</a> ' . kk_buy_pro_button() . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
}
