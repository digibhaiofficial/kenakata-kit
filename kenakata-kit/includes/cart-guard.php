<?php
/**
 * Bot guard for the cart: keeps link-following bots from running WooCommerce's cart code on every hit.
 *
 * - ?add-to-cart= / ?kk_buy_now= links opened from outside the shop (no referrer from this site, e.g. a crawler
 *   replaying a list of links) do not touch the cart: the visitor is sent to the clean, cacheable product page.
 *   People clicking inside the shop come with this site as referrer and are not affected; product cards and
 *   Buy Now no longer contain such links at all (they POST via kenakata-kit.js).
 * - robots.txt asks well-behaved crawlers to skip cart links.
 *
 * Setting: Kenakata Kit → Fake-order protection → "Ignore cart links from outside the shop" (on by default).
 *
 * @package KenakataKit
 */

defined( 'ABSPATH' ) || exit;

/** Is the referrer a page of this site? */
function kk_guard_same_site_referer() {
	$ref = wp_get_raw_referer();
	if ( ! $ref ) {
		return false;
	}
	$a = strtolower( (string) wp_parse_url( $ref, PHP_URL_HOST ) );
	$b = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
	return '' !== $a && preg_replace( '/^www\./', '', $a ) === preg_replace( '/^www\./', '', $b );
}

// Before WooCommerce (wp_loaded 20) and Buy Now (15) read the link.
add_action( 'wp_loaded', function () {
	if ( ! kk_opt( 'cart_bot_guard' ) || is_admin() || wp_doing_ajax() ) {
		return;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return; // forms (POST) are real clicks on a page of the shop
	}
	$keys = array( 'add-to-cart', 'kk_buy_now', 'ss_buy_now' );
	$hit  = false;
	foreach ( $keys as $k ) {
		if ( isset( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$hit = true;
		}
	}
	if ( ! $hit || kk_guard_same_site_referer() ) {
		return;
	}
	// Old Buy Now links point to the checkout (never cached): send those to the product page instead.
	$buy_id = 0;
	foreach ( array( 'kk_buy_now', 'ss_buy_now' ) as $k ) {
		if ( ! empty( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$buy_id = absint( wp_unslash( $_GET[ $k ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}
	$buy_url = $buy_id && 'product' === get_post_type( $buy_id ) ? get_permalink( $buy_id ) : '';
	foreach ( $keys as $k ) {
		unset( $_GET[ $k ], $_REQUEST[ $k ] );
	}
	if ( $buy_url ) {
		nocache_headers();
		wp_safe_redirect( $buy_url, 302, 'Kenakata Kit' );
		exit;
	}
	// Send the visitor to the same page without the cart part (cached, cheap). 302: the link itself stays valid for people.
	$uri   = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$clean = remove_query_arg( array_merge( $keys, array( 'quantity', 'variation_id' ) ), $uri ); // relative: same host, works in sub-folder installs too
	foreach ( array_keys( $_GET ) as $k ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 === strpos( (string) $k, 'attribute_' ) ) {
			$clean = remove_query_arg( $k, $clean );
		}
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex', true );
	wp_safe_redirect( $clean, 302, 'Kenakata Kit' );
	exit;
}, 1 );

// robots.txt (only used when the site has no robots.txt file of its own).
add_filter( 'robots_txt', function ( $output, $public ) {
	if ( ! $public || ! kk_opt( 'cart_bot_guard' ) ) {
		return $output;
	}
	$lines = "\n# Kenakata Kit: cart links are for people, not crawlers\nUser-agent: *\nDisallow: /*?add-to-cart=\nDisallow: /*&add-to-cart=\nDisallow: /*?kk_buy_now=\nDisallow: /*&kk_buy_now=\nDisallow: /*?wc-ajax=\n";
	return rtrim( (string) $output ) . "\n" . $lines;
}, 20, 2 );
