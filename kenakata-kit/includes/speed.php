<?php
/**
 * Speed & health: small speed switches (all off by default), Cloudflare Turnstile against login/registration bots,
 * a site + security checkup and a safe database clean-up.
 * Settings tab: "Speed & health" (speed).
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Speed switches
 * ====================================================================== */

// Emoji: WordPress adds a script and styles to every page so old browsers can show emoji. Phones show them anyway.
if ( kk_opt( 'speed_no_emoji' ) ) {
	add_action( 'init', function () {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'emoji_svg_url', '__return_false' );
		if ( function_exists( 'wp_enqueue_emoji_styles' ) ) { // WordPress 6.4+
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
			remove_action( 'admin_enqueue_scripts', 'wp_enqueue_emoji_styles' );
		}
	} );
}

// Embeds: the wp-embed script and the discovery links are only needed to show this site's posts inside other sites.
if ( kk_opt( 'speed_no_embeds' ) ) {
	add_action( 'init', function () {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		add_action( 'wp_footer', function () {
			wp_dequeue_script( 'wp-embed' );
		} );
	}, 20 );
}

// Heartbeat: WordPress asks the server every 15-60 s while a dashboard tab is open. Slower in the admin
// (and kept as it is in the post editor, where it saves drafts and locks posts); not on the shop pages.
if ( kk_opt( 'speed_heartbeat' ) ) {
	add_filter( 'heartbeat_settings', function ( $s ) {
		global $pagenow;
		if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
			$s['interval'] = 120;
		}
		return $s;
	} );
	add_action( 'wp_enqueue_scripts', function () {
		if ( ! is_user_logged_in() ) {
			wp_deregister_script( 'heartbeat' );
		}
	}, 99 );
}

/** Pages that need WooCommerce's own styles and scripts. */
function kk_speed_is_shop_page() {
	if ( ! function_exists( 'is_woocommerce' ) ) {
		return true;
	}
	if ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() || is_front_page() ) {
		return true; // the home page usually shows products
	}
	$post = get_post();
	if ( $post && ( has_shortcode( $post->post_content, 'products' ) || has_shortcode( $post->post_content, 'kk_products' ) || has_shortcode( $post->post_content, 'product_page' ) || false !== strpos( $post->post_content, 'woocommerce' ) || false !== strpos( (string) get_post_meta( $post->ID, '_elementor_data', true ), 'woocommerce' ) ) ) {
		return true; // a page with WooCommerce blocks, shortcodes or Elementor WooCommerce widgets
	}
	return (bool) apply_filters( 'kk_speed_is_shop_page', false );
}

// WooCommerce styles and scripts only where there is something to sell (blog posts and plain pages load faster).
if ( kk_opt( 'speed_wc_assets' ) ) {
	add_action( 'wp_enqueue_scripts', function () {
		if ( kk_speed_is_shop_page() ) {
			return;
		}
		foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'wc-blocks-style', 'wc-blocks-vendors-style' ) as $h ) {
			wp_dequeue_style( $h );
		}
		foreach ( array( 'wc-cart-fragments', 'woocommerce', 'wc-add-to-cart', 'sourcebuster-js', 'wc-order-attribution' ) as $h ) {
			wp_dequeue_script( $h );
		}
	}, 99 );
}

/* =========================================================================
 * Cloudflare Turnstile (free, no puzzles for real people) on login, registration and checkout
 * ====================================================================== */

function kk_turnstile_on( $where ) {
	return kk_opt( 'turnstile_enable' ) && '' !== trim( (string) kk_opt( 'turnstile_site' ) ) && '' !== kk_secret( 'turnstile_secret' ) && kk_opt( 'turnstile_' . $where );
}

/** The widget. Loads Cloudflare's script only on pages that show it. */
function kk_turnstile_html() {
	wp_enqueue_script( 'kk-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, array( 'strategy' => 'defer', 'in_footer' => true ) ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
	return '<div class="cf-turnstile kk-turnstile" data-sitekey="' . esc_attr( trim( (string) kk_opt( 'turnstile_site' ) ) ) . '" data-size="flexible" data-language="auto"></div>';
}

/**
 * Check the answer with Cloudflare. If Cloudflare cannot be reached the visitor is let through (a shop must never
 * lose an order because another service is down); the attempt limits still apply.
 */
function kk_turnstile_ok( $token = null ) {
	$token = null === $token ? ( isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '' ) : (string) $token; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the caller
	if ( '' === $token ) {
		return false;
	}
	$res = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
		'timeout' => 8,
		'body'    => array( 'secret' => kk_secret( 'turnstile_secret' ), 'response' => $token, 'remoteip' => kk_client_ip() ),
	) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return true;
	}
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	return ! empty( $body['success'] );
}

function kk_turnstile_error() {
	return __( 'Please complete the security check (the box above the button) and try again.', 'kenakata-kit' );
}

// wp-login.php and the WooCommerce My Account login / register forms.
add_action( 'login_form', function () {
	if ( kk_turnstile_on( 'login' ) ) {
		echo kk_turnstile_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
} );
add_filter( 'login_form_middle', function ( $html ) { // forms made with wp_login_form() (some themes and widgets)
	return kk_turnstile_on( 'login' ) ? $html . kk_turnstile_html() : $html;
} );
add_action( 'woocommerce_login_form', function () {
	if ( kk_turnstile_on( 'login' ) ) {
		echo kk_turnstile_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
} );
add_action( 'woocommerce_register_form', function () {
	if ( kk_turnstile_on( 'login' ) ) {
		echo kk_turnstile_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
} );
add_filter( 'authenticate', function ( $user, $username = '' ) {
	// Only real form logins: not the REST API / application passwords / XML-RPC / the store dashboard's own checks.
	$form_post = isset( $_POST['pwd'] ) || isset( $_POST['password'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( ! kk_turnstile_on( 'login' ) || '' === (string) $username || ! $form_post || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $user;
	}
	if ( is_wp_error( $user ) && 'kk_login_locked' === $user->get_error_code() ) {
		return $user;
	}
	return kk_turnstile_ok() ? $user : new WP_Error( 'kk_turnstile', kk_turnstile_error() );
}, 30, 2 );
add_filter( 'woocommerce_process_registration_errors', function ( $errors ) {
	if ( kk_turnstile_on( 'login' ) && ! kk_turnstile_ok() ) {
		$errors->add( 'kk_turnstile', kk_turnstile_error() );
	}
	return $errors;
} );

// Checkout (classic checkout form). Placed after the customer details, outside the order summary that WooCommerce
// redraws on every change. An answer works only once, so it is renewed after a refused checkout.
add_action( 'woocommerce_checkout_after_customer_details', function () {
	if ( kk_turnstile_on( 'checkout' ) ) {
		echo kk_turnstile_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_add_inline_script( 'kk-turnstile', "jQuery(function($){ $(document.body).on('checkout_error', function(){ if (window.turnstile) { try { window.turnstile.reset(); } catch (e) {} } }); });" );
	}
} );
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	if ( kk_turnstile_on( 'checkout' ) && ! kk_turnstile_ok() ) {
		$errors->add( 'kk_turnstile', kk_turnstile_error() );
	}
}, 5, 2 );

/* =========================================================================
 * Site checkup, security checkup, database clean-up (admin only)
 * ====================================================================== */

/** Size of the settings WordPress loads on every page (autoloaded options), in KB. */
function kk_health_autoload_kb() {
	global $wpdb;
	$sql = "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on','auto-on','auto')";
	return (int) round( (float) $wpdb->get_var( $sql ) / 1024 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
}

/** What the clean-up would remove (counts only). */
function kk_health_cleanup_counts() {
	global $wpdb;
	$now = time();
	return array(
		'transients' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( '_transient_timeout_' ) . '%', $now ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		'sessions'   => $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}woocommerce_sessions'" ) ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_sessions WHERE session_expiry < %d", $now ) ) : 0, // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		'locks'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( 'kk_lock_' ) . '%', $now ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		'spam'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam' AND comment_date_gmt < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)" ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	);
}

/**
 * Safe clean-up: only things WordPress would throw away anyway (expired temporary data, expired cart sessions,
 * our own finished locks, spam comments older than 30 days). Posts, products, orders, pages and revisions are
 * never touched.
 */
add_action( 'admin_post_kk_health_cleanup', function () {
	check_admin_referer( 'kk_health_cleanup' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'kenakata-kit' ), 403 );
	}
	global $wpdb;
	$before = array_sum( kk_health_cleanup_counts() );
	delete_expired_transients( true );
	if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}woocommerce_sessions'" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}woocommerce_sessions WHERE session_expiry < %d", time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d", $wpdb->esc_like( 'kk_lock_' ) . '%', time() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$spam = $wpdb->get_col( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam' AND comment_date_gmt < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY) LIMIT 2000" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $spam as $cid ) {
		wp_delete_comment( (int) $cid, true );
	}
	$done = max( 0, $before - array_sum( kk_health_cleanup_counts() ) );
	wp_safe_redirect( add_query_arg( 'kk_msg', 'cleaned-' . $done, kk_admin_url( 'speed' ) ) );
	exit;
} );

/** One check: array( ok, text, optional fix link ). */
function kk_health_checks() {
	global $wpdb;
	$c = array();
	/* translators: %s: PHP version */
	$c['site'][] = version_compare( PHP_VERSION, '8.1', '>=' ) ? array( true, sprintf( __( 'PHP %s (fast and supported)', 'kenakata-kit' ), PHP_VERSION ) ) : array( false, sprintf( __( 'PHP %s is old: ask your hosting for PHP 8.2 or newer (faster and safer)', 'kenakata-kit' ), PHP_VERSION ) );
	$opc = false;
	if ( function_exists( 'opcache_get_status' ) ) {
		$st  = @opcache_get_status( false ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- some hosts forbid the call
		$opc = is_array( $st ) ? ! empty( $st['opcache_enabled'] ) : (bool) ini_get( 'opcache.enable' );
	}
	$c['site'][] = $opc ? array( true, __( 'PHP OPcache is on', 'kenakata-kit' ) ) : array( false, __( 'PHP OPcache is OFF: every page has to read all of WordPress\'s code again, which can make pages 2–5× slower for logged-in users, the cart and the checkout. Ask your hosting to turn on OPcache (cPanel → Select PHP Version → Extensions → opcache).', 'kenakata-kit' ) );
	$build = function_exists( 'timer_float' ) ? (int) round( timer_float() * 1000 ) : 0;
	if ( $build ) {
		/* translators: %s: milliseconds */
		$c['site'][] = $build < 1500 ? array( true, sprintf( __( 'This admin page was built in %s ms', 'kenakata-kit' ), number_format_i18n( $build ) ) ) : array( false, sprintf( __( 'This admin page took %s ms to build (good: under 1,500 ms). The server is slow for pages that are not cached: see OPcache / object cache above, or ask your hosting about CPU limits.', 'kenakata-kit' ), number_format_i18n( $build ) ) );
	}
	if ( isset( $GLOBALS['kk_load_ms'] ) && $GLOBALS['kk_load_ms'] >= 0 ) {
		/* translators: %s: milliseconds */
		$c['site'][] = array( $GLOBALS['kk_load_ms'] < 150, sprintf( __( 'Kenakata Kit itself loads in %s ms', 'kenakata-kit' ), number_format_i18n( (int) $GLOBALS['kk_load_ms'] ) ) );
	}
	$c['site'][] = wp_using_ext_object_cache() ? array( true, __( 'Object cache is on (Redis / Memcached / LiteSpeed)', 'kenakata-kit' ) ) : array( false, __( 'No object cache: if your hosting offers Redis or LiteSpeed Object Cache, turn it on (less database work on every page)', 'kenakata-kit' ) );
	$page_cache = defined( 'LSCWP_V' ) || defined( 'WP_ROCKET_VERSION' ) || defined( 'W3TC' ) || class_exists( 'WpFastestCache' ) || defined( 'WPCACHEHOME' ) || class_exists( 'Swift_Performance' ) || defined( 'BREEZE_VERSION' );
	$c['site'][] = $page_cache ? array( true, __( 'A page cache plugin is active', 'kenakata-kit' ) ) : array( false, __( 'No page cache plugin found: LiteSpeed Cache (free, on LiteSpeed hosting) makes pages much faster', 'kenakata-kit' ) );
	$kb          = kk_health_autoload_kb();
	/* translators: %d: size in KB */
	$c['site'][] = $kb < 800 ? array( true, sprintf( __( 'Settings loaded on every page: %d KB (good)', 'kenakata-kit' ), $kb ) ) : array( false, sprintf( __( 'Settings loaded on every page: %d KB. Over 800 KB slows every page: remove plugins you no longer use, then run the clean-up below.', 'kenakata-kit' ), $kb ) );
	if ( function_exists( 'kk_cron_status' ) ) {
		$cron        = kk_cron_status();
		if ( null === $cron['last_run'] && ! $cron['overdue_tasks'] ) {
			$c['site'][] = array( true, __( 'Scheduled tasks (cron): first check within an hour', 'kenakata-kit' ) );
		} else {
			$c['site'][] = $cron['healthy'] ? array( true, __( 'Scheduled tasks (cron) run normally', 'kenakata-kit' ) ) : array( false, __( 'Scheduled tasks (cron) are not running regularly: add a server cron job that opens your-site/wp-cron.php every 5 minutes', 'kenakata-kit' ) );
		}
	}
	$c['site'][] = ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY && defined( 'WP_DEBUG' ) && WP_DEBUG ) ? array( false, __( 'Errors are shown to visitors (WP_DEBUG on): turn it off in wp-config.php', 'kenakata-kit' ) ) : array( true, __( 'Errors are not shown to visitors', 'kenakata-kit' ) );

	$c['security'][] = is_ssl() ? array( true, __( 'The site runs on https (secure)', 'kenakata-kit' ) ) : array( false, __( 'You are on http: use https', 'kenakata-kit' ) );
	$c['security'][] = username_exists( 'admin' ) ? array( false, __( 'A user called "admin" exists: bots try this name first. Make a new administrator with another name and delete "admin" (move its posts to the new user).', 'kenakata-kit' ), admin_url( 'users.php' ) ) : array( true, __( 'No user called "admin"', 'kenakata-kit' ) );
	$c['security'][] = kk_opt( 'sec_limit_login' ) ? array( true, __( 'Wrong-password limit is on', 'kenakata-kit' ) ) : array( false, __( 'Wrong-password limit is off', 'kenakata-kit' ), kk_admin_url( 'security' ) );
	$c['security'][] = ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) || kk_opt( 'sec_no_file_edit' ) ? array( true, __( 'Theme / plugin file editor is off', 'kenakata-kit' ) ) : array( false, __( 'The theme / plugin file editor is on: a stolen password could change your site\'s code', 'kenakata-kit' ), kk_admin_url( 'security' ) );
	$c['security'][] = kk_opt( 'sec_disable_xmlrpc' ) ? array( true, __( 'XML-RPC (old remote login, a common attack door) is off', 'kenakata-kit' ) ) : array( false, __( 'XML-RPC is on: turn it off unless you use the old WordPress app or Jetpack', 'kenakata-kit' ), kk_admin_url( 'security' ) );
	$c['security'][] = kk_turnstile_on( 'login' ) ? array( true, __( 'Cloudflare Turnstile protects the login', 'kenakata-kit' ) ) : array( false, __( 'Login has no bot check: turn on Cloudflare Turnstile on this page (free)', 'kenakata-kit' ) );
	$admins          = count( get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 50 ) ) );
	/* translators: %d: number of administrators */
	$c['security'][] = $admins <= 3 ? array( true, sprintf( _n( '%d administrator', '%d administrators', $admins, 'kenakata-kit' ), $admins ) ) : array( false, sprintf( __( '%d administrators: give staff the "Store Manager" or "Shop manager" role instead', 'kenakata-kit' ), $admins ), admin_url( 'users.php?role=administrator' ) );
	$updates         = get_site_transient( 'update_plugins' );
	$n_up            = $updates && ! empty( $updates->response ) ? count( (array) $updates->response ) : 0;
	/* translators: %d: number of plugins */
	$c['security'][] = 0 === $n_up ? array( true, __( 'All plugins are up to date', 'kenakata-kit' ) ) : array( false, sprintf( _n( '%d plugin has an update: old plugins are the most common way sites get hacked', '%d plugins have updates: old plugins are the most common way sites get hacked', $n_up, 'kenakata-kit' ), $n_up ), admin_url( 'plugins.php?plugin_status=upgrade' ) );
	unset( $wpdb );
	return $c;
}

function kk_render_health() {
	$checks = kk_health_checks();
	$counts = kk_health_cleanup_counts();
	$titles = array( 'site' => __( 'Site checkup', 'kenakata-kit' ), 'security' => __( 'Security checkup', 'kenakata-kit' ) );
	echo '<div class="kk-health">';
	foreach ( $checks as $group => $list ) {
		$bad = count( array_filter( $list, function ( $x ) { return ! $x[0]; } ) );
		echo '<section class="kk-card"><h3 class="kk-card__title">' . esc_html( $titles[ $group ] ) . ' <span class="kk-health__score' . ( $bad ? ' is-bad' : '' ) . '">' . esc_html( ( count( $list ) - $bad ) . '/' . count( $list ) ) . '</span></h3><ul class="kk-checks">';
		foreach ( $list as $x ) {
			echo '<li class="' . ( $x[0] ? 'is-ok' : 'is-bad' ) . '"><span class="dashicons dashicons-' . ( $x[0] ? 'yes-alt' : 'warning' ) . '" aria-hidden="true"></span><span>' . esc_html( $x[1] )
				. ( ! $x[0] && ! empty( $x[2] ) ? ' <a href="' . esc_url( $x[2] ) . '">' . esc_html__( 'Fix →', 'kenakata-kit' ) . '</a>' : '' ) . '</span></li>';
		}
		echo '</ul></section>';
	}
	echo '<section class="kk-card"><h3 class="kk-card__title">' . esc_html__( 'Database clean-up', 'kenakata-kit' ) . '</h3>'
		. '<p class="kk-card__desc">' . esc_html__( 'Removes only leftovers that WordPress no longer needs. Products, orders, pages, posts and revisions are never touched.', 'kenakata-kit' ) . '</p><ul class="kk-checks">'
		/* translators: %d: count */
		. '<li><span>' . esc_html( sprintf( __( 'Expired temporary data: %d', 'kenakata-kit' ), $counts['transients'] ) ) . '</span></li>'
		/* translators: %d: count */
		. '<li><span>' . esc_html( sprintf( __( 'Expired cart sessions: %d', 'kenakata-kit' ), $counts['sessions'] ) ) . '</span></li>'
		/* translators: %d: count */
		. '<li><span>' . esc_html( sprintf( __( 'Spam comments older than 30 days: %d', 'kenakata-kit' ), $counts['spam'] ) ) . '</span></li>'
		. '</ul>';
	if ( current_user_can( 'manage_options' ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="kk_health_cleanup">';
		wp_nonce_field( 'kk_health_cleanup' );
		echo '<button type="submit" class="button button-primary"' . disabled( 0 === array_sum( $counts ), true, false ) . '>' . esc_html__( 'Clean up now', 'kenakata-kit' ) . '</button></form>';
	}
	echo '</section></div>';
}
