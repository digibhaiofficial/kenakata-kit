<?php
/**
 * Product lists: [kk_products] and the "প্রোডাক্ট গ্রিড / ক্যারোসেল" Elementor widgets.
 * Source "current" = the products of the shop / category / brand / search page being viewed (with pagination).
 */

defined( 'ABSPATH' ) || exit;

/** True inside the Elementor editor or its preview. */
function kk_is_editor() {
	if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
		return false;
	}
	$el = \Elementor\Plugin::$instance;
	if ( isset( $el->editor ) && is_object( $el->editor ) && method_exists( $el->editor, 'is_edit_mode' ) && $el->editor->is_edit_mode() ) {
		return true;
	}
	return isset( $el->preview ) && is_object( $el->preview ) && method_exists( $el->preview, 'is_preview_mode' ) && $el->preview->is_preview_mode();
}

/** Is the main query a product archive we can render? */
function kk_is_product_archive() {
	return ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) || ( is_search() && 'product' === get_query_var( 'post_type' ) );
}

/** Default list options. */
function kk_products_defaults() {
	return array(
		'source'          => 'latest', // latest|featured|sale|best_selling|top_rated|category|brand|ids|related|recent|current
		'category'        => '',
		'brand'           => '',
		'ids'             => '',
		'exclude'         => '',
		'limit'           => 8,
		'orderby'         => '',
		'order'           => 'DESC',
		'hide_out_of_stock' => '',
		'layout'          => 'grid',
		'columns'         => '',
		'columns_tablet'  => '',
		'columns_mobile'  => '',
		'paginate'        => 'yes',
		'pagination'      => '', // '' (auto: numbers on shop pages) | none | numbers | ajax | loadmore
		'load_more_text'  => __( 'Load more', 'kenakata-kit' ),
		'toolbar'         => 'yes',
		'empty_text'      => __( 'No products found.', 'kenakata-kit' ),
		'title'           => '', // optional section title, shown only when there are products
		'title_tag'       => 'h2',
		'autoplay'        => '',
		'speed'           => '',
		'loop'            => '',
		'arrows'          => 'yes',
		'dots'            => 'no',
		'card'            => array(),
	);
}

/** Split "a, b,12" into a clean list. */
function kk_list( $value ) {
	if ( is_array( $value ) ) {
		return array_values( array_filter( array_map( 'trim', $value ) ) );
	}
	return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ) );
}

/** Tax query for a list of term ids or slugs. */
function kk_terms_tax_query( $taxonomy, $terms ) {
	$terms = kk_list( $terms );
	$ids   = array_filter( $terms, 'is_numeric' );
	return array(
		'taxonomy' => $taxonomy,
		'field'    => count( $ids ) === count( $terms ) ? 'term_id' : 'slug',
		'terms'    => count( $ids ) === count( $terms ) ? array_map( 'absint', $terms ) : array_map( 'sanitize_title', $terms ),
		'operator' => 'IN',
	);
}

/** WP_Query args for a source (null when the source cannot be resolved, e.g. related outside a product page). */
function kk_products_query_args( array $a ) {
	$args = array(
		'post_type'           => 'product',
		'post_status'         => 'publish',
		'posts_per_page'      => max( 1, min( 100, absint( $a['limit'] ) ) ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'tax_query'           => array( 'relation' => 'AND' ), // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_query'          => array(), // phpcs:ignore WordPress.DB.SlowDBQuery
	);
	$hidden = array( 'exclude-from-catalog' );
	$hide   = '' === $a['hide_out_of_stock'] ? 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) : 'yes' === $a['hide_out_of_stock'];
	if ( $hide ) {
		$hidden[] = 'outofstock';
	}
	$args['tax_query'][] = array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => $hidden, 'operator' => 'NOT IN' );
	if ( $a['exclude'] ) {
		$args['post__not_in'] = array_map( 'absint', kk_list( $a['exclude'] ) );
	}
	$orderby = $a['orderby'];

	switch ( $a['source'] ) {
		case 'featured':
			$args['tax_query'][] = array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'featured' ), 'operator' => 'IN' );
			break;
		case 'sale':
			$ids              = wc_get_product_ids_on_sale();
			$args['post__in'] = $ids ? array_map( 'absint', $ids ) : array( 0 );
			break;
		case 'best_selling':
			$orderby = $orderby ? $orderby : 'popularity';
			break;
		case 'top_rated':
			$orderby = $orderby ? $orderby : 'rating';
			break;
		case 'category':
			if ( $a['category'] ) {
				$args['tax_query'][] = kk_terms_tax_query( 'product_cat', $a['category'] );
			}
			break;
		case 'brand':
			if ( $a['brand'] && kk_brand_taxonomy() ) {
				$args['tax_query'][] = kk_terms_tax_query( kk_brand_taxonomy(), $a['brand'] );
			}
			break;
		case 'ids':
			$ids = array_map( 'absint', kk_list( $a['ids'] ) );
			if ( ! $ids ) {
				return null;
			}
			$args['post__in'] = $ids;
			$orderby          = $orderby ? $orderby : 'post__in';
			break;
		case 'recent': // ids come from the visitor's browser (see the kenakata/v1/recent route)
			$ids = array_map( 'absint', kk_list( $a['ids'] ) );
			if ( ! $ids ) {
				return null;
			}
			$args['post__in'] = $ids;
			$orderby          = 'post__in';
			break;
		case 'related':
			$pid = is_singular( 'product' ) ? get_the_ID() : 0;
			$ids = $pid ? wc_get_related_products( $pid, max( 1, absint( $a['limit'] ) ) ) : array();
			if ( ! $ids ) {
				return null;
			}
			$args['post__in'] = array_map( 'absint', $ids );
			$orderby          = $orderby ? $orderby : 'rand';
			break;
	}

	$order = 'ASC' === strtoupper( (string) $a['order'] ) ? 'ASC' : 'DESC';
	switch ( $orderby ? $orderby : 'date' ) {
		case 'price':
			$args['meta_key'] = '_price'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']  = array( 'meta_value_num' => $order, 'ID' => 'DESC' );
			break;
		case 'popularity':
			$args['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' );
			break;
		case 'rating':
			$args['meta_key'] = '_wc_average_rating'; // phpcs:ignore WordPress.DB.SlowDBQuery
			$args['orderby']  = array( 'meta_value_num' => 'DESC', 'ID' => 'DESC' );
			break;
		case 'title':
			$args['orderby'] = 'title';
			$args['order']   = $order;
			break;
		case 'rand':
			$args['orderby'] = 'rand';
			break;
		case 'menu_order':
			$args['orderby'] = array( 'menu_order' => 'ASC', 'title' => 'ASC' );
			break;
		case 'post__in':
			$args['orderby'] = 'post__in';
			break;
		default:
			$args['orderby'] = array( 'date' => $order, 'ID' => $order );
	}
	return apply_filters( 'kk_products_query_args', $args, $a );
}

/** Render a product list (grid or carousel). */
function kk_render_products( array $a ) {
	$a = wp_parse_args( $a, kk_products_defaults() );

	// "Recently viewed": the product IDs live in the visitor's browser, so the list is loaded after the page
	// (also works with page caching). In the editor the newest products are shown as a sample.
	if ( 'recent' === $a['source'] && empty( $a['_recent'] ) ) {
		if ( kk_is_editor() ) {
			$a['source'] = 'latest';
		} else {
			kk_need_js( true );
			$q = base64_encode( wp_json_encode( $a ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- transport only, signed below
			return '<div class="kk-products-wrap kk-recent" data-kk-recent="' . esc_attr( $q ) . '" data-kk-sig="' . esc_attr( kk_sign( $q ) ) . '" data-kk-exclude="' . esc_attr( is_singular( 'product' ) ? get_the_ID() : 0 ) . '" hidden></div>';
		}
	}

	$main = 'current' === $a['source'] && kk_is_product_archive() && ! kk_is_editor();
	if ( 'current' === $a['source'] && ! $main ) {
		$a['source'] = 'latest'; // editor / non-archive page: show the newest products as a preview
	}

	// Pagination: '' = automatic (page numbers on shop/category/brand pages when "paginate" is on).
	$mode = (string) $a['pagination'];
	if ( '' === $mode ) {
		$mode = $main && 'yes' === $a['paginate'] ? 'numbers' : 'none';
	}
	if ( 'carousel' === $a['layout'] || in_array( $a['source'], array( 'related', 'recent' ), true ) || ! in_array( $mode, array( 'none', 'numbers', 'ajax', 'loadmore' ), true ) ) {
		$mode = 'none';
	}
	// Each list gets a short id: its page parameter (?kkp_xxxxxx=2) and the AJAX loader use it.
	$gid   = $main ? 'main' : substr( md5( wp_json_encode( array( $a['source'], $a['category'], $a['brand'], $a['ids'], $a['limit'], $a['orderby'], $a['order'], $a['exclude'], $a['hide_out_of_stock'] ) ) ), 0, 6 );
	$param = 'kkp_' . $gid;
	$paged = 1;

	if ( $main ) {
		global $wp_query;
		$query = $wp_query;
		$paged = max( 1, (int) get_query_var( 'paged' ) );
	} else {
		$args = kk_products_query_args( $a );
		if ( null === $args ) {
			return kk_is_editor() ? '<p class="kk-products-empty">' . esc_html__( 'Nothing to show here (e.g. "Related" only works on a product page).', 'kenakata-kit' ) . '</p>' : '';
		}
		if ( 'none' !== $mode ) {
			$paged                 = isset( $_GET[ $param ] ) ? max( 1, absint( wp_unslash( $_GET[ $param ] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification -- page number only
			$args['paged']         = $paged;
			$args['no_found_rows'] = false;
		}
		$query = new WP_Query( $args );
	}
	kk_need_js( true );

	$card  = wp_parse_args( (array) $a['card'], kk_card_defaults() );
	$items = '';
	foreach ( $query->posts as $post ) {
		$product = wc_get_product( $post );
		if ( $product && $product->is_visible() ) {
			$items .= '<li class="kk-products__item' . ( 'carousel' === $a['layout'] ? ' kk-slider__slide' : '' ) . '">' . kk_render_product_card( $product, $card ) . '</li>';
		}
	}

	$vars = '';
	if ( 'carousel' === $a['layout'] ) {
		foreach ( array( 'columns' => 4, 'columns_tablet' => 3, 'columns_mobile' => 2 ) as $k => $default ) {
			$a[ $k ] = '' === (string) $a[ $k ] ? $default : $a[ $k ];
		}
	}
	foreach ( array( 'columns' => '--kk-cols', 'columns_tablet' => '--kk-cols-t', 'columns_mobile' => '--kk-cols-m' ) as $k => $var ) {
		if ( '' !== (string) $a[ $k ] && absint( $a[ $k ] ) ) {
			$vars .= $var . ':' . absint( $a[ $k ] ) . ';';
			if ( 'carousel' === $a['layout'] ) {
				$vars .= str_replace( '--kk-cols', '--kk-pv', $var ) . ':' . absint( $a[ $k ] ) . ';';
			}
		}
	}

	$out = '';
	if ( $main && 'yes' === $a['toolbar'] && $query->found_posts ) {
		wc_setup_loop( array( 'is_paginated' => true ) );
		ob_start();
		woocommerce_result_count();
		woocommerce_catalog_ordering();
		$out .= '<div class="kk-products-toolbar">' . ob_get_clean() . '</div>';
	}

	if ( '' !== $items && '' !== trim( (string) $a['title'] ) ) {
		$tag = in_array( $a['title_tag'], array( 'h2', 'h3', 'h4', 'p', 'div' ), true ) ? $a['title_tag'] : 'h2';
		$out = '<' . $tag . ' class="kk-products-title">' . esc_html( $a['title'] ) . '</' . $tag . '>' . $out;
	}
	if ( '' === $items ) {
		if ( in_array( $a['source'], array( 'related', 'recent' ), true ) && ! kk_is_editor() ) {
			return ''; // nothing related / viewed yet: show nothing at all
		}
		$out .= '<p class="kk-products-empty">' . esc_html( $a['empty_text'] ) . '</p>';
	} elseif ( 'carousel' === $a['layout'] ) {
		$out .= kk_slider_open( $a, 'kk-products-slider', $vars )
			. '<ul class="kk-slider__track kk-products kk-products--carousel" tabindex="0" aria-label="' . esc_attr__( 'Products', 'kenakata-kit' ) . '">' . $items . '</ul>'
			. kk_slider_close( $a );
	} else {
		$out .= '<ul class="kk-products kk-products--grid"' . ( $vars ? ' style="' . esc_attr( $vars ) . '"' : '' ) . '>' . $items . '</ul>';
	}

	$total = (int) $query->max_num_pages;
	if ( 'none' !== $mode && $total > 1 && '' !== $items ) {
		$base = $main
			? str_replace( 999999999, '%#%', remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) ) )
			: add_query_arg( $param, '%#%', remove_query_arg( array( 'add-to-cart', $param ) ) );
		if ( 'loadmore' === $mode ) {
			if ( $paged < $total ) {
				$next = str_replace( '%#%', $paged + 1, $base );
				$out .= '<div class="kk-loadmore"><a class="kk-loadmore__btn" href="' . esc_url( $next ) . '" data-kk-more rel="nofollow">' . esc_html( $a['load_more_text'] ? $a['load_more_text'] : __( 'Load more', 'kenakata-kit' ) ) . '</a></div>';
			}
		} else {
			$links = paginate_links( array(
				'base'      => esc_url_raw( $base ),
				'format'    => '',
				'current'   => $paged,
				'total'     => $total,
				'prev_text' => '<span aria-hidden="true">&larr;</span><span class="screen-reader-text">' . esc_html__( 'Previous page', 'kenakata-kit' ) . '</span>',
				'next_text' => '<span class="screen-reader-text">' . esc_html__( 'Next page', 'kenakata-kit' ) . '</span><span aria-hidden="true">&rarr;</span>',
				'type'      => 'list',
				'end_size'  => 1,
				'mid_size'  => 1,
			) );
			$out .= '<nav class="kk-pagination"' . ( 'ajax' === $mode ? ' data-kk-ajax="1"' : '' ) . ' aria-label="' . esc_attr__( 'Product pages', 'kenakata-kit' ) . '">' . $links . '</nav>';
		}
	}
	if ( ! $main ) {
		wp_reset_postdata();
	}
	return '<div class="kk-products-wrap" data-kk-grid="' . esc_attr( $gid ) . '">' . $out . '</div>';
}

/** Short signature for data sent to the browser and back (recently viewed lists). */
function kk_sign( $data ) {
	return substr( hash_hmac( 'sha256', (string) $data, wp_salt( 'nonce' ) ), 0, 20 );
}

/**
 * GET /wp-json/kenakata/v1/recent?ids=12,34&q=…&sig=… → { html }
 * Renders a "Recently viewed" list. q holds the list options from the page (signed, so they can't be changed);
 * ids are the products the visitor opened, kept in their browser.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'kenakata/v1', '/recent', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$q   = (string) $req->get_param( 'q' );
			$sig = (string) $req->get_param( 'sig' );
			if ( '' === $q || ! hash_equals( kk_sign( $q ), $sig ) ) {
				return new WP_Error( 'kk_bad_request', 'Bad request', array( 'status' => 400 ) );
			}
			$a = json_decode( base64_decode( $q ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( ! is_array( $a ) ) {
				return new WP_Error( 'kk_bad_request', 'Bad request', array( 'status' => 400 ) );
			}
			$exclude = absint( $req->get_param( 'exclude' ) );
			$ids     = array_slice( array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $req->get_param( 'ids' ) ) ) ) ) ), 0, 30 );
			$ids     = array_values( array_diff( $ids, array( $exclude ) ) );
			$limit   = max( 1, min( 24, absint( $a['limit'] ?? 8 ) ) );
			$html    = '';
			if ( $ids ) {
				$a['source']  = 'recent';
				$a['_recent'] = 1;
				$a['ids']     = implode( ',', array_slice( $ids, 0, $limit ) );
				$a['limit']   = $limit;
				$html         = kk_render_products( $a );
				if ( false !== strpos( $html, 'kk-products-empty' ) ) {
					$html = ''; // nothing left (e.g. products removed or out of stock)
				}
			}
			$res = rest_ensure_response( array( 'html' => $html ) );
			$res->header( 'Cache-Control', 'no-store' );
			return $res;
		},
	) );
} );

/** Opening markup of a carousel (shared by products and brand/category cards). */
function kk_slider_open( array $a, $class, $style = '' ) {
	$auto  = '' === (string) $a['autoplay'] ? ( kk_opt( 'cards_autoplay' ) ? 1 : 0 ) : ( 'yes' === $a['autoplay'] ? 1 : 0 );
	$speed = '' === (string) $a['speed'] ? max( 2, absint( kk_opt( 'cards_autoplay_speed' ) ) ) * 1000 : max( 1500, absint( $a['speed'] ) );
	$loop  = '' === (string) $a['loop'] ? ( kk_opt( 'cards_loop' ) ? 1 : 0 ) : ( 'yes' === $a['loop'] ? 1 : 0 );
	return '<div class="kk-slider ' . esc_attr( $class ) . '" role="region" aria-roledescription="carousel" aria-label="' . esc_attr( isset( $a['label'] ) && $a['label'] ? $a['label'] : __( 'Carousel', 'kenakata-kit' ) ) . '"'
		. ' data-autoplay="' . esc_attr( $auto ) . '" data-speed="' . esc_attr( $speed ) . '" data-loop="' . esc_attr( $loop ) . '"'
		. ( $style ? ' style="' . esc_attr( $style ) . '"' : '' ) . '>';
}

/** Arrows + dots + closing markup. */
function kk_slider_close( array $a ) {
	$out = '';
	if ( 'no' !== $a['arrows'] ) {
		$out .= '<button type="button" class="kk-slider__arrow kk-slider__arrow--prev" aria-label="' . esc_attr__( 'Previous', 'kenakata-kit' ) . '">' . kk_icon( 'prev' ) . '</button>'
			. '<button type="button" class="kk-slider__arrow kk-slider__arrow--next" aria-label="' . esc_attr__( 'Next', 'kenakata-kit' ) . '">' . kk_icon( 'next' ) . '</button>';
	}
	if ( 'yes' === $a['dots'] ) {
		$out .= '<div class="kk-slider__dots" role="group" aria-label="' . esc_attr__( 'Choose slide', 'kenakata-kit' ) . '"></div>';
	}
	return $out . '</div>';
}

/** [kk_products] */
function kk_products_shortcode( $atts ) {
	$atts = (array) $atts;
	$a    = shortcode_atts( array_diff_key( kk_products_defaults(), array( 'card' => 1 ) ) + array( 'buttons' => '', 'brand_name' => '', 'rating' => '', 'badge' => '' ), $atts, 'kk_products' );
	$card = array();
	if ( $a['buttons'] ) {
		$card['actions'] = sanitize_key( $a['buttons'] );
	}
	foreach ( array( 'brand_name' => 'show_brand', 'rating' => 'show_rating', 'badge' => 'show_badge' ) as $att => $key ) {
		if ( '' !== $a[ $att ] ) {
			$card[ $key ] = 'yes' === $a[ $att ];
		}
	}
	$a['card'] = $card;
	return kk_render_products( $a );
}
add_shortcode( 'kk_products', 'kk_products_shortcode' );
