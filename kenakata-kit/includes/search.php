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

/**
 * Product ids for a live search, best first (3.17). Names are what people type, so the product name decides:
 * exact name → name starts with the words → a word in the name starts with them → the name contains them;
 * then the SKU; and only when that is not enough, WordPress' own search (description and short description).
 * Every typed word must appear. Returns array( ids, total ).
 */
function kk_search_ids( $q, $limit ) {
	global $wpdb;
	$words = array_values( array_filter( preg_split( '/\s+/u', trim( $q ) ) ) );
	if ( ! $words ) {
		return array( array(), 0 );
	}
	$words  = array_slice( $words, 0, 5 );
	$term   = get_term_by( 'name', 'exclude-from-search', 'product_visibility' );
	$hidden = $term ? (int) $term->term_taxonomy_id : 0;
	$not    = $hidden ? $wpdb->prepare( " AND p.ID NOT IN ( SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d )", $hidden ) : '';
	$all    = array();
	$score  = array();
	$full   = $wpdb->esc_like( trim( $q ) );
	foreach ( $words as $w ) {
		$all[] = $wpdb->prepare( 'p.post_title LIKE %s', '%' . $wpdb->esc_like( $w ) . '%' );
	}
	$first   = $wpdb->esc_like( $words[0] );
	$score[] = $wpdb->prepare( 'WHEN p.post_title = %s THEN 100', trim( $q ) );
	$score[] = $wpdb->prepare( 'WHEN p.post_title LIKE %s THEN 90', $full . '%' );
	$score[] = $wpdb->prepare( 'WHEN p.post_title LIKE %s OR p.post_title LIKE %s OR p.post_title LIKE %s THEN 70', '% ' . $full . '%', '%-' . $full . '%', '%(' . $full . '%' );
	$score[] = $wpdb->prepare( 'WHEN p.post_title LIKE %s THEN 60', $first . '%' );
	$score[] = $wpdb->prepare( 'WHEN p.post_title LIKE %s THEN 50', '% ' . $first . '%' );
	$where   = "p.post_type = 'product' AND p.post_status = 'publish'" . $not;
	$sql     = "SELECT p.ID, CASE " . implode( ' ', $score ) . " ELSE 10 END AS rel FROM {$wpdb->posts} p WHERE {$where} AND " . implode( ' AND ', $all )
		. ' ORDER BY rel DESC, CHAR_LENGTH(p.post_title) ASC, p.menu_order ASC, p.ID DESC LIMIT ' . (int) ( $limit + 1 );
	$ids     = array_map( 'absint', (array) $wpdb->get_col( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- built from prepared parts above
	$count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$where} AND " . implode( ' AND ', $all ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	// SKU (exact start), e.g. a code from a prescription or a box.
	if ( count( $ids ) < $limit && strlen( $q ) >= 2 && $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}wc_product_meta_lookup'" ) ) {
		$sku = $wpdb->get_col( $wpdb->prepare( "SELECT l.product_id FROM {$wpdb->prefix}wc_product_meta_lookup l JOIN {$wpdb->posts} p ON p.ID = l.product_id WHERE {$where} AND l.sku LIKE %s LIMIT %d", $full . '%', $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = array_values( array_unique( array_merge( $ids, array_map( 'absint', $sku ) ) ) );
	}
	// Still room: WordPress' own search (words in the description).
	if ( count( $ids ) < $limit ) {
		$more  = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			's'              => $q,
			'posts_per_page' => $limit,
			'fields'         => 'ids',
			'post__not_in'   => $ids ? $ids : array( 0 ),
			'no_found_rows'  => false,
			'tax_query'      => array( array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'exclude-from-search' ), 'operator' => 'NOT IN' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		$ids   = array_merge( $ids, array_map( 'absint', $more->posts ) );
		$count = max( $count, count( $ids ) ) + (int) $more->found_posts - count( $more->posts );
	}
	$count = max( $count, count( $ids ) );
	return array( array_slice( $ids, 0, $limit ), $count );
}

function kk_search_endpoint( WP_REST_Request $request ) {
	$q   = trim( mb_substr( (string) $request->get_param( 'q' ), 0, 64 ) ); // longer text is never a product search
	$min = max( 1, absint( kk_opt( 'search_min_chars' ) ) );
	if ( mb_strlen( $q ) < $min ) {
		return array( 'items' => array(), 'total' => 0 );
	}
	$limit     = max( 3, absint( kk_opt( 'search_limit' ) ) );
	$cache_key = 'kk_srch2_' . md5( strtolower( $q ) . '|' . $limit );
	$cached    = kk_search_cache_get( $cache_key );
	if ( false !== $cached ) {
		return kk_search_response( $cached );
	}
	list( $ids, $total ) = kk_search_ids( $q, $limit );
	$items = array();
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$items[] = array(
			'name'     => html_entity_decode( wp_strip_all_tags( $p->get_name() ), ENT_QUOTES, 'UTF-8' ), // the script escapes it
			'url'      => get_permalink( $id ),
			'price'    => '' !== $p->get_price() ? kk_plain_price( $p->get_price() ) : '',
			'regular'  => $p->is_on_sale() ? kk_plain_price( $p->get_regular_price() ) : '',
			'img'      => get_the_post_thumbnail_url( $id, 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
			'in_stock' => $p->is_in_stock(),
		);
	}
	$result = array(
		'items' => $items,
		'total' => (int) $total,
		'all'   => add_query_arg( array( 's' => rawurlencode( $q ), 'post_type' => 'product' ), home_url( '/' ) ), // add_query_arg() does not encode: & # + would break the link
	);
	kk_search_cache_set( $cache_key, $result );
	return kk_search_response( $result );
}

/**
 * Visitors' search answers may be kept by the browser and by LiteSpeed / Cloudflare for 10 minutes: the same word
 * then costs the server nothing. (Without this every typed word starts all of WordPress, which takes seconds on
 * slow hosting.) Product changes clear LiteSpeed's copies.
 */
function kk_search_response( $data ) {
	$res = new WP_REST_Response( $data );
	if ( ! is_user_logged_in() ) {
		$res->header( 'Cache-Control', 'public, max-age=300' );
		$res->header( 'X-LiteSpeed-Cache-Control', 'public,max-age=600' );
		$res->header( 'X-LiteSpeed-Tag', 'kk_search' );
	}
	return $res;
}

add_action( 'save_post_product', function () {
	do_action( 'litespeed_purge', 'kk_search' ); // LiteSpeed Cache: forget cached search answers
} );

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
			. '<a class="kk-search__all" href="' . esc_url( add_query_arg( 'post_type', 'product', home_url( '/' ) ) ) . '" hidden>' . esc_html__( 'See all products', 'kenakata-kit' ) . '</a></div></div>';
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
		. '<a class="kk-search__all" href="' . esc_url( add_query_arg( 'post_type', 'product', home_url( '/' ) ) ) . '" hidden>' . esc_html__( 'See all products', 'kenakata-kit' ) . '</a>'
		. '</div></div></div>';
}
add_shortcode( 'kk_live_search', 'kk_live_search_shortcode' );
add_shortcode( 'ss_live_search', 'kk_live_search_shortcode' ); // backward compatibility
