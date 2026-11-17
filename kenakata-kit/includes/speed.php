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
	if ( $post && ( has_shortcode( $post->post_content, 'products' ) || has_shortcode( $post->post_content, 'kk_products' ) || has_shortcode( $post->post_content, 'product_page' ) || false !== strpos( $post->post_content, 'woocommerce' ) || preg_match( '/woocommerce|"widgetType":"kk-/', (string) get_post_meta( $post->ID, '_elementor_data', true ) ) || has_shortcode( $post->post_content, 'kk_live_search' ) ) ) {
		return true; // a page with WooCommerce blocks, shortcodes or Elementor WooCommerce widgets
	}
	return (bool) apply_filters( 'kk_speed_is_shop_page', false );
}

/**
 * Does the site header show a cart (Elementor Pro "Menu Cart" in a header template, or a Kenakata Kit cart widget)?
 * Then the cart scripts stay on every page, or the count would not update. Checked once a day (cached).
 */
function kk_speed_header_has_cart() {
	$cached = get_option( 'kk_hdr_cart' ); // autoloaded: no extra database query on normal pages
	if ( is_array( $cached ) && isset( $cached['v'], $cached['t'] ) && $cached['t'] > time() - DAY_IN_SECONDS ) {
		return (bool) $cached['v'];
	}
	global $wpdb;
	$found = (bool) $wpdb->get_var( "SELECT 1 FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE p.post_type = 'elementor_library' AND p.post_status = 'publish' AND pm.meta_key = '_elementor_data' AND ( pm.meta_value LIKE '%woocommerce-menu-cart%' OR pm.meta_value LIKE '%\"widgetType\":\"kk-cart%' ) LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	update_option( 'kk_hdr_cart', array( 'v' => $found ? 1 : 0, 't' => time() ), true );
	return $found;
}
add_action( 'save_post_elementor_library', function () {
	delete_option( 'kk_hdr_cart' );
} );

// WooCommerce styles and scripts only where there is something to sell (blog posts and plain pages load faster).
if ( kk_opt( 'speed_wc_assets' ) ) {
	add_action( 'wp_enqueue_scripts', function () {
		if ( kk_speed_is_shop_page() ) {
			return;
		}
		foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'wc-blocks-style', 'wc-blocks-vendors-style' ) as $h ) {
			wp_dequeue_style( $h );
		}
		// Order Attribution (sourcebuster-js, wc-order-attribution) stays everywhere: it remembers where a visitor came
		// from (Google, Facebook…) on the first page they open, often a blog post, and shows it on the order (3.25).
		$scripts = array( 'woocommerce', 'wc-add-to-cart' );
		if ( ! kk_speed_header_has_cart() ) {
			$scripts[] = 'wc-cart-fragments'; // a cart icon in the header needs it on every page
		}
		foreach ( $scripts as $h ) {
			wp_dequeue_script( $h );
		}
	}, 99 );
}

// Block editor styles only where blocks are used (pages built with Elementor or shortcodes do not need them).
if ( kk_opt( 'speed_block_css' ) ) {
	$kk_block_css = function () {
		if ( is_singular() && has_blocks( get_post() ) ) {
			return;
		}
		if ( function_exists( 'is_woocommerce' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			return; // WooCommerce may use its blocks here
		}
		foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'wc-blocks-style', 'wc-blocks-vendors-style' ) as $h ) {
			wp_dequeue_style( $h );
		}
	};
	add_action( 'wp_enqueue_scripts', $kk_block_css, 100 );
	add_action( 'wp_print_styles', $kk_block_css, 1 ); // again just before printing: some plugins add it back late
}

/* =========================================================================
 * Bots: keep crawlers away from pages that cannot be cached, and stop bots that bring no customers
 * ====================================================================== */

/** User-agent parts of bots that only cost server time for a Bangladeshi shop. */
function kk_bad_bots( $kind ) {
	$list = array(
		'seo' => array( 'AhrefsBot', 'SemrushBot', 'MJ12bot', 'DotBot', 'BLEXBot', 'DataForSeoBot', 'serpstatbot', 'Barkrowler', 'SeekportBot', 'MegaIndex', 'PetalBot', 'Bytespider', 'ImagesiftBot', 'Amazonbot', 'Baiduspider', 'YandexBot', 'Sogou', 'ZoominfoBot', 'trendictionbot', 'AwarioBot', 'Linguee' ),
		'ai'  => array( 'GPTBot', 'ClaudeBot', 'anthropic-ai', 'CCBot', 'Google-Extended', 'meta-externalagent', 'Diffbot', 'omgili', 'cohere-ai', 'Timpibot' ),
		// AI answer engines (3.25: a switch of their own). They fetch pages to answer a person's question and link to the shop.
		'ai_search' => array( 'PerplexityBot', 'Perplexity-User', 'OAI-SearchBot', 'ChatGPT-User', 'Claude-SearchBot', 'Claude-User', 'YouBot' ),
	);
	return (array) apply_filters( 'kk_bad_bots', isset( $list[ $kind ] ) ? $list[ $kind ] : array(), $kind );
}

/** The bots switched off in the settings. */
function kk_blocked_bots() {
	$bots = array();
	if ( kk_opt( 'speed_bot_block' ) ) {
		$bots = array_merge( $bots, kk_bad_bots( 'seo' ) );
	}
	if ( kk_opt( 'speed_ai_block' ) ) {
		$bots = array_merge( $bots, kk_bad_bots( 'ai' ) );
	}
	if ( kk_opt( 'speed_ai_search_block' ) ) {
		$bots = array_merge( $bots, kk_bad_bots( 'ai_search' ) );
	}
	return $bots;
}

// Answer blocked bots with a tiny 403 before WordPress builds a page (some, like Bytespider, ignore robots.txt).
// 3.25: AI answer engines got their own switch. Sites that blocked "AI crawlers" before keep them blocked until changed.
if ( kk_opt( 'speed_ai_block' ) ) {
	$kk_stored = (array) get_option( 'kk_settings', array() );
	if ( ! array_key_exists( 'speed_ai_search_block', $kk_stored ) ) {
		$kk_stored['speed_ai_search_block'] = 1;
		$GLOBALS['kk_settings_internal'] = true; // skip the settings form's sanitizer: this is our own value
		update_option( 'kk_settings', $kk_stored );
		$GLOBALS['kk_settings_internal'] = false;
	}
	unset( $kk_stored );
}

if ( kk_opt( 'speed_bot_block' ) || kk_opt( 'speed_ai_block' ) || kk_opt( 'speed_ai_search_block' ) ) {
	$kk_ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( '' !== $kk_ua && ! is_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		foreach ( kk_blocked_bots() as $kk_bot ) {
			if ( false !== stripos( $kk_ua, $kk_bot ) && ! preg_match( '#robots\.txt|[?&]robots=1#', (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				status_header( 403 );
				header( 'Content-Type: text/plain; charset=utf-8' );
				header( 'Cache-Control: no-store' ); // never let a cache keep this answer for a real visitor
				header( 'X-LiteSpeed-Cache-Control: no-cache' );
				echo 'Forbidden';
				exit;
			}
		}
	}
	unset( $kk_ua, $kk_bot );
}

add_filter( 'robots_txt', function ( $out, $public ) {
	if ( ! $public ) {
		return $out;
	}
	$add = '';
	if ( kk_opt( 'speed_robots_shop' ) ) {
		// Search, sort and filter pages: endless combinations, never cached, no value in Google.
		$add .= "\n# Kenakata Kit: search, sort and filter pages are for people, not crawlers\nUser-agent: *\n";
		foreach ( array( '/?s=', '/*?s=', '/*&s=', '/*?orderby=', '/*&orderby=', '/*?filter_', '/*&filter_', '/*?min_price=', '/*&min_price=', '/*?max_price=', '/*?rating_filter=', '/*?add_to_wishlist=', '/*?per_page=' ) as $p ) {
			$add .= 'Disallow: ' . $p . "\n";
		}
		// Cart, checkout and account pages at their real addresses (3.25: not every shop uses /cart/, /checkout/…).
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $k ) {
			$id   = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( $k ) : 0;
			$path = $id > 0 ? (string) wp_parse_url( (string) get_permalink( $id ), PHP_URL_PATH ) : '';
			if ( '' !== $path && '/' !== $path && false === strpos( $path, '?' ) ) {
				$add .= 'Disallow: ' . trailingslashit( $path ) . "\n";
			}
		}
	}
	$bots = kk_blocked_bots();
	if ( $bots ) {
		$add .= "\n# Kenakata Kit: bots that bring no customers\n";
		foreach ( $bots as $b ) {
			$add .= 'User-agent: ' . $b . "\n";
		}
		$add .= "Disallow: /\n";
	}
	return $out . $add;
}, 20, 2 );

/**
 * robots.txt: WordPress, WooCommerce, Rank Math and this plugin each add their own "User-agent: *" group. Search
 * engines combine them, but one tidy group is easier to read and check, so the rules are merged into the first.
 */
function kk_robots_merge_star_groups( $txt ) {
	$lines = preg_split( '/\r\n|\n/', (string) $txt );
	$n     = count( $lines );
	$start = array(); // line index => group number, for the first User-agent line of each group
	$uas   = array();
	$rules = array();
	$g     = -1;
	$prev  = false;
	for ( $i = 0; $i < $n; $i++ ) {
		$t = trim( $lines[ $i ] );
		if ( preg_match( '/^user-agent\s*:\s*(.+)$/i', $t, $m ) ) {
			if ( ! $prev ) {
				$g++;
				$start[ $i ] = $g;
				$uas[ $g ]   = array();
				$rules[ $g ] = array();
			}
			$uas[ $g ][] = trim( $m[1] );
			$prev        = true;
			continue;
		}
		$prev = false;
		if ( $g >= 0 && preg_match( '/^(allow|disallow|crawl-delay)\s*:/i', $t ) ) {
			$rules[ $g ][] = $t;
		}
	}
	$star = array();
	foreach ( $uas as $k => $list ) {
		if ( array( '*' ) === $list ) {
			$star[] = $k;
		}
	}
	if ( count( $star ) < 2 ) {
		return $txt;
	}
	$merged = array();
	foreach ( $star as $k ) {
		foreach ( $rules[ $k ] as $r ) {
			$merged[ strtolower( preg_replace( '/\s+/', '', $r ) ) ] = $r;
		}
	}
	$drop  = array_slice( $star, 1 );
	$out   = array();
	$cur   = -1;
	$in_ua = false;
	for ( $i = 0; $i < $n; $i++ ) {
		$line = $lines[ $i ];
		$t    = trim( $line );
		if ( isset( $start[ $i ] ) ) {
			$cur = $start[ $i ];
		}
		$is_ua = (bool) preg_match( '/^user-agent\s*:/i', $t );
		if ( 0 === strpos( $t, '#' ) ) { // a comment that only introduces a dropped group goes with it
			$k = $i + 1;
			while ( $k < $n && '' === trim( $lines[ $k ] ) ) {
				$k++;
			}
			if ( isset( $start[ $k ] ) && in_array( $start[ $k ], $drop, true ) ) {
				continue;
			}
		}
		if ( in_array( $cur, $drop, true ) && ( $is_ua || preg_match( '/^(allow|disallow|crawl-delay)\s*:/i', $t ) ) ) {
			continue;
		}
		if ( $cur === $star[0] && preg_match( '/^(allow|disallow|crawl-delay)\s*:/i', $t ) ) {
			if ( $merged ) {
				foreach ( $merged as $r ) {
					$out[] = $r;
				}
				$merged = array();
			}
			continue;
		}
		$out[] = $line;
	}
	return trim( preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $out ) ) ) . "\n";
}
add_filter( 'robots_txt', 'kk_robots_merge_star_groups', 999 );

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
/**
 * Cart / checkout pages built with WooCommerce's blocks (3.25). Kenakata Kit's checkout features are made for the
 * classic checkout (the [woocommerce_checkout] form, Elementor's checkout widget), so on a block checkout they are
 * silently missing. Returns array( 'checkout' => page id, 'cart' => page id ) for the pages that use blocks.
 */
function kk_block_cart_checkout() {
	$out = array();
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return $out;
	}
	foreach ( array( 'checkout', 'cart' ) as $k ) {
		$id   = (int) wc_get_page_id( $k );
		$post = $id > 0 ? get_post( $id ) : null;
		if ( $post && has_block( 'woocommerce/' . $k, $post ) && 'builder' !== get_post_meta( $id, '_elementor_edit_mode', true ) ) {
			$out[ $k ] = $id;
		}
	}
	return $out;
}

/** Link that turns a block cart / checkout page into the classic one (the old content stays as a revision). */
function kk_classic_page_url( $which ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=kk_classic_page&page_type=' . rawurlencode( $which ) ), 'kk_classic_page' );
}

add_action( 'admin_post_kk_classic_page', function () {
	check_admin_referer( 'kk_classic_page' );
	$which = isset( $_GET['page_type'] ) ? sanitize_key( wp_unslash( $_GET['page_type'] ) ) : '';
	$pages = kk_block_cart_checkout();
	if ( ! isset( $pages[ $which ] ) || ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_post', $pages[ $which ] ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'kenakata-kit' ) );
	}
	$code = 'checkout' === $which ? '[woocommerce_checkout]' : '[woocommerce_cart]';
	wp_update_post( array( 'ID' => $pages[ $which ], 'post_content' => "<!-- wp:shortcode -->\n" . $code . "\n<!-- /wp:shortcode -->" ) ); // WordPress keeps the block version as a revision
	wp_safe_redirect( admin_url( 'admin.php?page=kenakata-kit&tab=dashboard&kk_msg=classic-' . $which ) );
	exit;
} );

// The notice: Kenakata Kit screens and the WordPress Dashboard, for people who can fix it.
add_action( 'admin_notices', function () {
	global $pagenow;
	$ours = 'admin.php' === $pagenow && isset( $_GET['page'] ) && 'kenakata-kit' === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ( ! $ours && 'index.php' !== $pagenow ) || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$pages = kk_block_cart_checkout();
	if ( ! $pages ) {
		return;
	}
	echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Kenakata Kit: your shop uses WooCommerce\'s new block cart / checkout.', 'kenakata-kit' ) . '</strong> '
		. esc_html__( 'The phone check, delivery areas, bKash / Nagad payment, incomplete orders, repeat-order block, bot check and the other checkout features work only with the classic checkout, so they are not shown to customers now.', 'kenakata-kit' ) . '</p><p>';
	foreach ( $pages as $which => $id ) {
		$label = 'checkout' === $which ? __( 'Use the classic checkout', 'kenakata-kit' ) : __( 'Use the classic cart', 'kenakata-kit' );
		echo '<a class="button button-primary" href="' . esc_url( kk_classic_page_url( $which ) ) . '">' . esc_html( $label ) . '</a> ';
		echo '<a class="button" href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . ' ✎</a> ';
	}
	echo '</p><p class="description">' . esc_html__( 'The page keeps its address and its old version is saved (Page → Revisions), so it can be brought back.', 'kenakata-kit' ) . '</p></div>';
} );

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
	if ( defined( 'LSCWP_V' ) ) { // LiteSpeed Cache: the settings that most often empty or split the cache (read only, nothing is changed)
		$ls = function ( $id ) {
			return (int) get_option( 'litespeed.conf.' . $id, 0 );
		};
		$c['site'][] = $ls( 'purge-post_all' ) ? array( false, __( 'LiteSpeed: "Purge All On Post Update" is on, so every order (stock change) empties the whole cache. Turn it off in LiteSpeed Cache → Cache → Purge; the product and its categories are still refreshed.', 'kenakata-kit' ), admin_url( 'admin.php?page=litespeed-cache#purge' ) ) : array( true, __( 'LiteSpeed: an update refreshes only the pages that changed', 'kenakata-kit' ) );
		if ( $ls( 'guest' ) ) {
			$c['site'][] = array( false, __( 'LiteSpeed: Guest Mode is on. It keeps extra copies of pages and sends an extra request for every new visitor; on a busy shared server it usually costs more than it saves. Consider turning it off in LiteSpeed Cache → General.', 'kenakata-kit' ), admin_url( 'admin.php?page=litespeed-general' ) );
		}
		if ( $ls( 'cache-mobile' ) ) {
			$c['site'][] = array( false, __( 'LiteSpeed: "Cache Mobile" keeps a separate copy of every page for phones. Only needed if your theme shows different HTML on phones (Elementor and Kenakata Kit do not); otherwise turn it off in LiteSpeed Cache → Cache.', 'kenakata-kit' ), admin_url( 'admin.php?page=litespeed-cache' ) );
		}
	}
	$as_table = $wpdb->prefix . 'actionscheduler_actions';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $as_table ) ) === $as_table ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$failed = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$as_table} WHERE status = 'failed'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
		/* translators: %s: number of tasks */
		$c['site'][] = $failed < 300 ? array( true, sprintf( __( 'Failed background tasks: %s', 'kenakata-kit' ), number_format_i18n( $failed ) ) ) : array( false, sprintf( __( '%s failed background tasks are stored, mostly left by plugins that were removed. Delete them in WooCommerce → Status → Scheduled Actions → Failed.', 'kenakata-kit' ), number_format_i18n( $failed ) ), admin_url( 'admin.php?page=wc-status&tab=action-scheduler&status=failed' ) );
	}
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
	$blocks = kk_block_cart_checkout();
	$c['site'][] = isset( $blocks['checkout'] ) ? array( false, __( 'The checkout page uses WooCommerce\'s Checkout block: Kenakata Kit\'s checkout features are not shown. Switch it to the classic checkout.', 'kenakata-kit' ), kk_classic_page_url( 'checkout' ) ) : array( true, __( 'The checkout page is the classic checkout (all checkout features work)', 'kenakata-kit' ) );
	$c['site'][] = ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY && defined( 'WP_DEBUG' ) && WP_DEBUG ) ? array( false, __( 'Errors are shown to visitors (WP_DEBUG on): turn it off in wp-config.php', 'kenakata-kit' ) ) : array( true, __( 'Errors are not shown to visitors', 'kenakata-kit' ) );

	$c['security'][] = is_ssl() ? array( true, __( 'The site runs on https (secure)', 'kenakata-kit' ) ) : array( false, __( 'You are on http: use https', 'kenakata-kit' ) );
	$c['security'][] = username_exists( 'admin' ) ? array( false, __( 'A user called "admin" exists: bots try this name first. Make a new administrator with another name and delete "admin" (move its posts to the new user).', 'kenakata-kit' ), admin_url( 'users.php' ) ) : array( true, __( 'No user called "admin"', 'kenakata-kit' ) );
	$c['security'][] = kk_opt( 'sec_limit_login' ) ? array( true, __( 'Wrong-password limit is on', 'kenakata-kit' ) ) : array( false, __( 'Wrong-password limit is off', 'kenakata-kit' ), kk_admin_url( 'security' ) );
	$c['security'][] = ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) || kk_opt( 'sec_no_file_edit' ) ? array( true, __( 'Theme / plugin file editor is off', 'kenakata-kit' ) ) : array( false, __( 'The theme / plugin file editor is on: a stolen password could change your site\'s code', 'kenakata-kit' ), kk_admin_url( 'security' ) );
	$c['security'][] = kk_opt( 'sec_disable_xmlrpc' ) ? array( true, __( 'XML-RPC (old remote login, a common attack door) is off', 'kenakata-kit' ) ) : array( false, __( 'XML-RPC is on: turn it off unless you use the old WordPress app or Jetpack', 'kenakata-kit' ), kk_admin_url( 'security' ) );
	$c['security'][] = kk_turnstile_on( 'login' ) ? array( true, __( 'Cloudflare Turnstile protects the login', 'kenakata-kit' ) ) : array( false, __( 'Login has no bot check: turn on Cloudflare Turnstile on this page (free)', 'kenakata-kit' ) );
	$sealed = function_exists( 'kk_secrets_sealed' ) ? kk_secrets_sealed() : null;
	if ( null !== $sealed ) {
		/* translators: %s: a line to add to wp-config.php */
		$unreadable = false;
		foreach ( array( 'kk_secrets', 'kk_courier_secrets' ) as $opt ) {
			foreach ( (array) get_option( $opt, array() ) as $v ) {
				if ( 0 === strpos( (string) $v, 'kkenc1:' ) && '' === kk_secret_open( $v ) ) {
					$unreadable = true;
				}
			}
		}
		if ( $unreadable ) {
			$c['security'][] = array( false, __( 'Some saved API keys cannot be read: KK_ENCRYPTION_KEY in wp-config.php is missing or was changed. Put the old line back, or paste the keys again on the Courier / Security tabs.', 'kenakata-kit' ) );
		} else {
			$c['security'][] = $sealed ? array( true, __( 'Saved API keys (courier, Turnstile) are encrypted', 'kenakata-kit' ) ) : array( false, sprintf( __( 'Saved API keys (courier, Turnstile) are stored as plain text in the database. To encrypt them, add this line to wp-config.php (above "That\'s all, stop editing") and open this page again: %s', 'kenakata-kit' ), "define( 'KK_ENCRYPTION_KEY', '" . wp_generate_password( 48, false ) . "' );" ) );
		}
	}
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
