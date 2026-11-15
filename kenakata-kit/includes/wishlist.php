<?php
/**
 * Wishlist: ♡ buttons on product cards and product pages, a header/bottom-bar count, a wishlist page
 * ([kk_wishlist]) and a "Wishlist" link in My account.
 * Works without an account (the list lives in the visitor's browser); after login the list is kept on the
 * account too, so it is the same on every device. "Added to wishlist" counts show in Products → All products.
 */

defined( 'ABSPATH' ) || exit;

function kk_wl_enabled() {
	return (bool) kk_opt( 'wl_enable' ) && function_exists( 'WC' );
}

/** IDs of published, visible products only (max 100). */
function kk_wl_clean_ids( $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
	$ids = array_slice( $ids, 0, 100 );
	return array_values( array_filter( $ids, function ( $id ) {
		return 'product' === get_post_type( $id ) && 'publish' === get_post_status( $id );
	} ) );
}

function kk_wl_user_ids( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return $user_id ? kk_wl_clean_ids( (array) get_user_meta( $user_id, 'kk_wishlist', true ) ) : array();
}

/** URL of the wishlist page (setting, or the page with the slug "wishlist"). */
function kk_wl_page_url() {
	return function_exists( 'kk_page_url' ) ? kk_page_url( 'wl_page', 'wishlist' ) : '';
}

/**
 * The ♡ button. $variant: card | single | widget.
 * $args: add (text), added (text when saved), icon (bool), text (bool, show the text), class (extra classes).
 */
function kk_wl_button( $product_id, $variant = 'card', $args = array() ) {
	if ( ! kk_wl_enabled() ) {
		return '';
	}
	kk_need_js();
	$a     = wp_parse_args( $args, array( 'add' => '', 'added' => '', 'icon' => true, 'text' => 'card' !== $variant, 'class' => '' ) );
	$add   = '' !== (string) $a['add'] ? $a['add'] : ( kk_opt( 'wl_text_add' ) ? kk_opt( 'wl_text_add' ) : __( 'Add to wishlist', 'kenakata-kit' ) );
	$added = '' !== (string) $a['added'] ? $a['added'] : ( kk_opt( 'wl_text_added' ) ? kk_opt( 'wl_text_added' ) : __( 'In wishlist', 'kenakata-kit' ) );
	$text  = $a['text'] ? '<span class="kk-wl-btn__text" data-add="' . esc_attr( $add ) . '" data-added="' . esc_attr( $added ) . '">' . esc_html( $add ) . '</span>' : '';
	$icon  = $a['icon'] ? '<svg class="kk-wl-btn__ico" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M12 20.5s-7.5-4.6-7.5-10.4A4.3 4.3 0 0 1 12 7.4a4.3 4.3 0 0 1 7.5 2.7c0 5.8-7.5 10.4-7.5 10.4z"/></svg>' : '';
	return '<button type="button" class="' . esc_attr( trim( 'kk-wl-btn kk-wl-btn--' . $variant . ' ' . $a['class'] ) ) . '" data-kk-wl="' . absint( $product_id ) . '" aria-pressed="false" aria-label="' . esc_attr( $add ) . '">'
		. $icon . $text . '</button>';
}

/**
 * Product page button after Add to cart. The "Add to Cart Form" widget can force it on or off for its own form
 * ($GLOBALS['kk_wl_single_force'] = 'yes' | 'no'); otherwise the "Add to wishlist on product pages" setting decides.
 */
function kk_wl_single_hook() {
	global $product;
	$force = $GLOBALS['kk_wl_single_force'] ?? '';
	$on    = 'yes' === $force || ( '' === $force && kk_opt( 'wl_single' ) );
	if ( $on && $product instanceof WC_Product && ( 'yes' === $force || ( is_product() && (int) $product->get_id() === (int) get_queried_object_id() ) ) ) {
		echo kk_wl_button( $product->get_id(), 'single' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
}

/** Header / anywhere: ♡ link with count. [kk_wishlist_link] */
function kk_wl_link( $atts = array() ) {
	if ( ! kk_wl_enabled() ) {
		return '';
	}
	$a = shortcode_atts( array( 'text' => '' ), $atts, 'kk_wishlist_link' );
	kk_need_js();
	return '<a class="kk-wl-link" href="' . esc_url( kk_wl_page_url() ? kk_wl_page_url() : home_url( '/' ) ) . '" aria-label="' . esc_attr__( 'Wishlist', 'kenakata-kit' ) . '">'
		. '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-7.5-10.4A4.3 4.3 0 0 1 12 7.4a4.3 4.3 0 0 1 7.5 2.7c0 5.8-7.5 10.4-7.5 10.4z"/></svg>'
		. ( '' !== $a['text'] ? '<span class="kk-wl-link__text">' . esc_html( $a['text'] ) . '</span>' : '' )
		. '<span class="kk-wl-count" data-kk-wl-count hidden>0</span></a>';
}
add_shortcode( 'kk_wishlist_link', 'kk_wl_link' );

/** The wishlist page. [kk_wishlist] — products are loaded by the browser (the list lives there). */
function kk_wl_page( $atts = array() ) {
	if ( ! kk_wl_enabled() ) {
		return '';
	}
	$a = shortcode_atts( array( 'columns' => '', 'columns_tablet' => '', 'columns_mobile' => '' ), $atts, 'kk_wishlist' );
	kk_need_js( true );
	$shared = isset( $_GET['kk_wl'] ) ? implode( ',', kk_wl_clean_ids( explode( ',', sanitize_text_field( wp_unslash( $_GET['kk_wl'] ) ) ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- public, read-only list
	$empty  = kk_opt( 'wl_empty_text' ) ? kk_opt( 'wl_empty_text' ) : __( 'Your wishlist is empty. Tap ♡ on any product to save it here.', 'kenakata-kit' );
	return '<div class="kk-wl-page" data-kk-wl-page data-shared="' . esc_attr( $shared ) . '" data-cols="' . esc_attr( wp_json_encode( array_map( 'absint', $a ) ) ) . '">'
		. '<div class="kk-wl-page__bar" hidden><span class="kk-wl-page__count"></span>'
		. ( kk_opt( 'wl_share' ) ? '<a class="kk-wl-share" href="#" target="_blank" rel="noopener">' . kk_icon( 'whatsapp' ) . '<span>' . esc_html__( 'Share on WhatsApp', 'kenakata-kit' ) . '</span></a>' : '' )
		. '</div>'
		. ( $shared ? '<p class="kk-wl-page__shared">' . esc_html__( 'A wishlist shared with you:', 'kenakata-kit' ) . '</p>' : '' )
		. '<div class="kk-wl-page__list" aria-live="polite"><p class="kk-wl-page__loading">' . esc_html__( 'Loading…', 'kenakata-kit' ) . '</p></div>'
		. '<div class="kk-wl-page__empty" hidden><p>' . esc_html( $empty ) . '</p><a class="kk-wl-page__shop" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Continue shopping', 'kenakata-kit' ) . '</a></div>'
		. '</div>';
}
add_shortcode( 'kk_wishlist', 'kk_wl_page' );

// ♡ on product cards (top-right of the picture) and on the product page (after the Add to cart button).
add_filter( 'kk_product_card_media_after', function ( $html, $product ) {
	return kk_opt( 'wl_card' ) ? $html . kk_wl_button( $product->get_id(), 'card' ) : $html;
}, 10, 2 );
add_action( 'woocommerce_after_add_to_cart_button', 'kk_wl_single_hook', 30 );

// My account → "Wishlist" (links to the wishlist page; no rewrite rules needed).
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	if ( ! kk_wl_enabled() || ! kk_wl_page_url() ) {
		return $items;
	}
	$out = array();
	foreach ( $items as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'orders' === $key ) {
			$out['kk-wishlist'] = __( 'Wishlist', 'kenakata-kit' );
		}
	}
	if ( ! isset( $out['kk-wishlist'] ) ) {
		$out = array_slice( $out, 0, 1, true ) + array( 'kk-wishlist' => __( 'Wishlist', 'kenakata-kit' ) ) + array_slice( $out, 1, null, true );
	}
	return $out;
}, 30 );
add_filter( 'woocommerce_get_endpoint_url', function ( $url, $endpoint ) {
	return 'kk-wishlist' === $endpoint && kk_wl_page_url() ? kk_wl_page_url() : $url;
}, 10, 2 );

/*
 * REST:
 *  GET  /kenakata/v1/wishlist            → { ids } of the logged-in customer
 *  POST /kenakata/v1/wishlist {ids, merge}→ save (merge = add the browser list to the account list)
 *  POST /kenakata/v1/wishlist/ping {id, op} → popularity count (+1 when added, -1 when removed)
 *  GET  /kenakata/v1/wishlist/products?ids= → { html } product grid for the wishlist page
 */
add_action( 'rest_api_init', function () {
	$logged_in = function () { return is_user_logged_in(); };
	register_rest_route( 'kenakata/v1', '/wishlist', array(
		array( 'methods' => 'GET', 'permission_callback' => $logged_in, 'callback' => function () {
			$r = rest_ensure_response( array( 'ids' => kk_wl_user_ids() ) );
			$r->header( 'Cache-Control', 'no-store' );
			return $r;
		} ),
		array( 'methods' => 'POST', 'permission_callback' => $logged_in, 'callback' => function ( WP_REST_Request $req ) {
			$ids = kk_wl_clean_ids( (array) $req->get_param( 'ids' ) );
			if ( $req->get_param( 'merge' ) ) {
				$ids = kk_wl_clean_ids( array_merge( kk_wl_user_ids(), $ids ) );
			}
			update_user_meta( get_current_user_id(), 'kk_wishlist', $ids );
			return array( 'ids' => $ids );
		} ),
	) );
	register_rest_route( 'kenakata/v1', '/wishlist/ping', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$id = absint( $req->get_param( 'id' ) );
			$op = 'remove' === $req->get_param( 'op' ) ? 'remove' : 'add';
			if ( ! kk_wl_enabled() || 'product' !== get_post_type( $id ) ) {
				return array( 'ok' => false );
			}
			// One count per visitor per product per day (cheap flood guard).
			$key = 'kk_wlp_' . md5( $id . '|' . kk_client_ip() . '|' . $op );
			if ( get_transient( $key ) ) {
				return array( 'ok' => true );
			}
			set_transient( $key, 1, DAY_IN_SECONDS );
			$n = max( 0, (int) get_post_meta( $id, '_kk_wl_count', true ) + ( 'remove' === $op ? -1 : 1 ) );
			update_post_meta( $id, '_kk_wl_count', $n );
			return array( 'ok' => true );
		},
	) );
	register_rest_route( 'kenakata/v1', '/wishlist/products', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$ids  = kk_wl_clean_ids( explode( ',', (string) $req->get_param( 'ids' ) ) );
			$cols = array_map( 'absint', (array) json_decode( (string) $req->get_param( 'cols' ), true ) );
			$html = '';
			if ( $ids ) {
				$html = kk_render_products( array(
					'source'         => 'ids',
					'ids'            => implode( ',', $ids ),
					'orderby'        => 'post__in',
					'limit'          => count( $ids ),
					'layout'         => 'grid',
					'pagination'     => 'none',
					'columns'        => $cols['columns'] ?? '',
					'columns_tablet' => $cols['columns_tablet'] ?? '',
					'columns_mobile' => $cols['columns_mobile'] ?? '',
				) );
				if ( false !== strpos( $html, 'kk-products-empty' ) ) {
					$html = '';
				}
			}
			$r = rest_ensure_response( array( 'html' => $html, 'ids' => $ids ) );
			$r->header( 'Cache-Control', 'no-store' );
			return $r;
		},
	) );
} );

// Products → All products: "♡" column (how often the product was added to a wishlist), sortable.
add_filter( 'manage_edit-product_columns', function ( $cols ) {
	if ( kk_wl_enabled() ) {
		$cols['kk_wl'] = '<span title="' . esc_attr__( 'Added to wishlists', 'kenakata-kit' ) . '">♡</span>';
	}
	return $cols;
}, 30 );
add_action( 'manage_product_posts_custom_column', function ( $col, $post_id ) {
	if ( 'kk_wl' === $col ) {
		echo esc_html( (int) get_post_meta( $post_id, '_kk_wl_count', true ) );
	}
}, 10, 2 );
add_filter( 'manage_edit-product_sortable_columns', function ( $cols ) {
	$cols['kk_wl'] = 'kk_wl';
	return $cols;
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() && $q->is_main_query() && 'kk_wl' === $q->get( 'orderby' ) ) {
		$q->set( 'meta_key', '_kk_wl_count' ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$q->set( 'orderby', 'meta_value_num' );
	}
} );
