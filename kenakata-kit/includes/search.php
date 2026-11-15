<?php
/**
 * Live (AJAX) product search: [kk_live_search] + REST endpoint /wp-json/kenakata/v1/search?q=
 */

defined( 'ABSPATH' ) || exit;

if ( ! kk_opt( 'search_enable' ) ) {
	add_shortcode( 'kk_live_search', '__return_empty_string' );
	add_shortcode( 'ss_live_search', '__return_empty_string' );
	return;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'kenakata/v1', '/search', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array( 'q' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ),
		'callback'            => 'kk_search_endpoint',
	) );
} );

function kk_plain_price( $amount ) {
	return trim( str_replace( "\xC2\xA0", ' ', html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' ) ) );
}

function kk_search_endpoint( WP_REST_Request $request ) {
	$q   = trim( mb_substr( (string) $request->get_param( 'q' ), 0, 64 ) ); // longer text is never a product search
	$min = max( 1, absint( kk_opt( 'search_min_chars' ) ) );
	if ( mb_strlen( $q ) < $min ) {
		return array( 'items' => array(), 'total' => 0 );
	}
	$limit     = max( 3, absint( kk_opt( 'search_limit' ) ) );
	$cache_key = 'kk_srch_' . md5( strtolower( $q ) . '|' . $limit );
	$cached    = kk_search_cache_get( $cache_key );
	if ( false !== $cached ) {
		return $cached;
	}
	$query = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		's'              => $q,
		'posts_per_page' => $limit,
		'fields'         => 'ids',
		'tax_query'      => array( array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'exclude-from-search' ), 'operator' => 'NOT IN' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	$items = array();
	foreach ( $query->posts as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$items[] = array(
			'name'     => wp_strip_all_tags( $p->get_name() ),
			'url'      => get_permalink( $id ),
			'price'    => '' !== $p->get_price() ? kk_plain_price( $p->get_price() ) : '',
			'regular'  => $p->is_on_sale() ? kk_plain_price( $p->get_regular_price() ) : '',
			'img'      => get_the_post_thumbnail_url( $id, 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
			'in_stock' => $p->is_in_stock(),
		);
	}
	$result = array(
		'items' => $items,
		'total' => (int) $query->found_posts,
		'all'   => add_query_arg( array( 's' => $q, 'post_type' => 'product' ), home_url( '/' ) ),
	);
	kk_search_cache_set( $cache_key, $result );
	return $result;
}

/**
 * Search cache. With an object cache (Redis/Memcached/LiteSpeed) every search is cached there. Without one, a
 * transient is a row in wp_options, so only searches that found something are stored, and at most 300 new ones an
 * hour: a bot typing random words cannot fill the database.
 */
function kk_search_cache_get( $key ) {
	return wp_using_ext_object_cache() ? wp_cache_get( $key, 'kk_search' ) : get_transient( $key );
}

function kk_search_cache_set( $key, $result ) {
	if ( wp_using_ext_object_cache() ) {
		wp_cache_set( $key, $result, 'kk_search', 10 * MINUTE_IN_SECONDS );
		return;
	}
	if ( empty( $result['items'] ) ) {
		return;
	}
	$count = (int) get_transient( 'kk_srch_count' );
	if ( $count >= 300 ) {
		return;
	}
	set_transient( 'kk_srch_count', $count + 1, HOUR_IN_SECONDS );
	set_transient( $key, $result, 10 * MINUTE_IN_SECONDS );
}

function kk_live_search_shortcode( $atts ) {
	$a = shortcode_atts( array( 'placeholder' => kk_opt( 'search_placeholder' ), 'mode' => 'icon', 'button_text' => '' ), $atts, 'kk_live_search' );
	wp_enqueue_script( 'kenakata-kit' );
	$uid  = wp_unique_id( 'kk-search-' );
	$icon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
	if ( 'bar' === $a['mode'] ) {
		return '<div class="kk-search kk-search--bar">'
			. '<form class="kk-search__form" role="search" action="' . esc_url( home_url( '/' ) ) . '" method="get"><input type="hidden" name="post_type" value="product">'
			. '<label for="' . esc_attr( $uid ) . '-input" class="screen-reader-text">' . esc_html__( 'Search products', 'kenakata-kit' ) . '</label>'
			. '<input class="kk-search__input" id="' . esc_attr( $uid ) . '-input" type="search" name="s" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="' . esc_attr( $uid ) . '-list" placeholder="' . esc_attr( $a['placeholder'] ) . '" value="' . esc_attr( get_search_query() ) . '">'
			. '<button type="submit" class="kk-search__submit">' . $icon . ( $a['button_text'] ? '<span>' . esc_html( $a['button_text'] ) . '</span>' : '<span class="screen-reader-text">' . esc_html__( 'Search', 'kenakata-kit' ) . '</span>' ) . '</button></form>'
			. '<div class="kk-search__drop" hidden><p class="kk-search__status" role="status" aria-live="polite"></p>'
			. '<ul class="kk-search__results" id="' . esc_attr( $uid ) . '-list" role="listbox"></ul>'
			. '<a class="kk-search__all" href="#" hidden></a></div></div>';
	}
	return '<div class="kk-search">'
		. '<button type="button" class="kk-search__toggle" aria-expanded="false" aria-controls="' . esc_attr( $uid ) . '" aria-label="' . esc_attr__( 'Search products', 'kenakata-kit' ) . '">' . $icon . '</button>'
		. '<div class="kk-search__panel" id="' . esc_attr( $uid ) . '" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Search products', 'kenakata-kit' ) . '" hidden><div class="kk-search__inner">'
		. '<form class="kk-search__form" role="search" action="' . esc_url( home_url( '/' ) ) . '" method="get"><input type="hidden" name="post_type" value="product">'
		. str_replace( '<svg ', '<svg class="kk-search__icon" ', $icon )
		. '<input class="kk-search__input" type="search" name="s" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="' . esc_attr( $uid ) . '-list" placeholder="' . esc_attr( $a['placeholder'] ) . '" aria-label="' . esc_attr__( 'Search products', 'kenakata-kit' ) . '">'
		. '<button type="button" class="kk-search__close" aria-label="' . esc_attr__( 'Close search', 'kenakata-kit' ) . '">&times;</button></form>'
		. '<p class="kk-search__status" role="status" aria-live="polite"></p>'
		. '<ul class="kk-search__results" id="' . esc_attr( $uid ) . '-list" role="listbox"></ul>'
		. '<a class="kk-search__all" href="#" hidden></a>'
		. '</div></div></div>';
}
add_shortcode( 'kk_live_search', 'kk_live_search_shortcode' );
add_shortcode( 'ss_live_search', 'kk_live_search_shortcode' ); // backward compatibility
