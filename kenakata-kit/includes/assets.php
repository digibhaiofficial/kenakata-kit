<?php
/**
 * Front-end assets. Settings become CSS variables (--kk-*); Elementor widgets override them per section.
 */

defined( 'ABSPATH' ) || exit;

function kk_css_vars( $selector = ':root' ) {
	$el  = (bool) kk_opt( 'use_elementor_colors' );
	$var = function ( $elementor, $key ) use ( $el ) {
		$hex = kk_opt( $key );
		return $el && $elementor ? "var(--e-global-color-{$elementor}, {$hex})" : $hex;
	};
	// A font list only: letters (any language), digits, spaces, commas, quotes, dots and dashes. Anything else (; { } : ( ) \) could end the CSS rule.
	$font = trim( preg_replace( '/[^\p{L}\p{N} ,\'"._-]/u', '', (string) kk_opt( 'font_family' ) ) );
	$vars = array(
		'--kk-primary'         => $var( 'primary', 'color_primary' ),
		'--kk-primary-hover'   => kk_opt( 'color_primary_hover' ),
		'--kk-accent'          => $var( 'accent', 'color_accent' ),
		'--kk-accent-hover'    => kk_opt( 'color_accent_hover' ),
		'--kk-text'            => $var( 'text', 'color_text' ),
		'--kk-muted'           => kk_opt( 'color_muted' ),
		'--kk-border'          => kk_opt( 'color_border' ),
		'--kk-soft'            => kk_opt( 'color_surface_soft' ),
		'--kk-soft-primary'    => kk_opt( 'color_surface_brand' ),
		'--kk-soft-accent'     => kk_opt( 'color_surface_accent' ),
		'--kk-badge'           => kk_opt( 'color_badge' ),
		'--kk-price'           => kk_opt( 'color_price' ),
		'--kk-radius'          => absint( kk_opt( 'radius' ) ) . 'px',
		'--kk-font'            => '' === $font ? 'inherit' : $font,
		'--kk-font-size'       => absint( kk_opt( 'font_size_base' ) ) . 'px',
		'--kk-btn-height'      => absint( kk_opt( 'btn_height' ) ) . 'px',
		'--kk-btn-radius'      => absint( kk_opt( 'btn_radius' ) ) . 'px',
		'--kk-btn-font-size'   => absint( kk_opt( 'btn_font_size' ) ) . 'px',
		'--kk-btn-weight'      => absint( kk_opt( 'btn_font_weight' ) ),
		'--kk-btn-transform'   => kk_opt( 'btn_uppercase' ) ? 'uppercase' : 'none',
		'--kk-input-height'    => absint( kk_opt( 'input_height' ) ) . 'px',
		'--kk-input-radius'    => absint( kk_opt( 'input_radius' ) ) . 'px',
		// Product cards.
		'--kk-card-ratio'      => preg_match( '#^\d+/\d+$#', (string) kk_opt( 'card_image_ratio' ) ) ? kk_opt( 'card_image_ratio' ) : '1/1',
		'--kk-card-fit'        => 'cover' === kk_opt( 'card_image_fit' ) ? 'cover' : 'contain',
		'--kk-card-lines'      => max( 1, absint( kk_opt( 'card_title_lines' ) ) ),
		'--kk-card-title-size' => absint( kk_opt( 'card_title_size' ) ) . 'px',
		'--kk-card-price-size' => absint( kk_opt( 'card_price_size' ) ) . 'px',
		'--kk-card-align'      => 'center' === kk_opt( 'card_align' ) ? 'center' : 'left',
		'--kk-cols'            => max( 1, absint( kk_opt( 'archive_columns' ) ) ),
		'--kk-cols-t'          => max( 1, absint( kk_opt( 'archive_columns_tablet' ) ) ),
		'--kk-cols-m'          => max( 1, absint( kk_opt( 'archive_columns_mobile' ) ) ),
		// Brand / category cards.
		'--kk-term-img'        => absint( kk_opt( 'cards_image_size' ) ) . 'px',
		'--kk-pv'              => max( 1, absint( kk_opt( 'cards_per_view' ) ) ),
		'--kk-pv-t'            => max( 1, absint( kk_opt( 'cards_per_view_tablet' ) ) ),
		'--kk-pv-m'            => max( 1, absint( kk_opt( 'cards_per_view_mobile' ) ) ),
		// Floating buttons / mobile bar.
		'--kk-wa'              => kk_opt( 'wa_float_color' ),
		'--kk-float-offset'    => absint( kk_opt( 'wa_float_offset' ) ) . 'px',
		'--kk-call'            => kk_opt( 'call_float_color' ) ? kk_opt( 'call_float_color' ) : 'var(--kk-primary)',
		'--kk-float-ico'       => kk_opt( 'float_icon_color' ),
		'--kk-float-size'      => absint( kk_opt( 'float_size' ) ) . 'px',
		// Text on coloured buttons, stars, badges.
		'--kk-btn-text'        => kk_opt( 'btn_text_color' ),
		'--kk-star-c'          => kk_opt( 'color_star' ),
		'--kk-badge-text'      => kk_opt( 'color_badge_text' ),
		// Product page.
		'--kk-wa-btn'          => kk_opt( 'pp_wa_color' ),
		'--kk-call-btn'        => kk_opt( 'pp_call_color' ) ? kk_opt( 'pp_call_color' ) : 'var(--kk-primary)',
		'--kk-contact-text'    => kk_opt( 'pp_btn_text_color' ),
		'--kk-di-bg'           => kk_opt( 'di_bg' ) ? kk_opt( 'di_bg' ) : 'var(--kk-soft)',
		'--kk-di-ico'          => kk_opt( 'di_icon_color' ) ? kk_opt( 'di_icon_color' ) : 'var(--kk-primary)',
		'--kk-sticky-bg'       => kk_opt( 'sticky_bg' ),
		'--kk-sw-h'            => absint( kk_opt( 'swatches_height' ) ) . 'px',
		'--kk-sw-radius'       => absint( kk_opt( 'swatches_radius' ) ) . 'px',
		'--kk-drawer-w'        => absint( kk_opt( 'cart_drawer_width' ) ) . 'px',
		'--kk-rm-color'        => kk_opt( 'rm_color' ) ? kk_opt( 'rm_color' ) : 'var(--kk-primary)',
		'--kk-wl-color'        => kk_opt( 'wl_color' ) ? kk_opt( 'wl_color' ) : '#E11D48',
	);
	$css = $selector . '{';
	foreach ( $vars as $k => $v ) {
		$css .= $k . ':' . str_replace( array( '{', '}', ';', '<' ), '', wp_strip_all_tags( (string) $v ) ) . ';';
	}
	return $css . '}';
}

/** Body classes describing layout choices (used by the CSS). */
function kk_body_classes( $classes ) {
	if ( kk_opt( 'card_enable' ) ) {
		$classes[] = 'kk-cards';
	}
	if ( kk_opt( 'cart_drawer_enable' ) && kk_opt( 'cart_drawer_takeover' ) && ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
		$classes[] = 'kk-drawer-take'; // the header's own cart panel is hidden: the side cart opens instead
	}
	// The side cart opens by itself after "Add to cart": WooCommerce's extra "View cart" link next to the button is then not needed.
	if ( kk_opt( 'cart_drawer_enable' ) && kk_opt( 'cart_drawer_auto_open' ) && ! ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
		$classes[] = 'kk-drawer-auto';
	}
	return $classes;
}

/**
 * Feature styles split out of kenakata-kit.css in 3.29: part name => whether this page needs it.
 * The settings screen loads all of them for its previews.
 *
 * @param bool $all True for every part (admin previews).
 * @return array
 */
function kk_feature_css_parts( $all = false ) {
	return array(
		'wishlist' => $all || kk_opt( 'wl_enable' ),
		'search'   => $all || kk_opt( 'search_enable' ),
		'popup'    => $all || kk_opt( 'popup_enable' ),
		'bbar'     => $all || kk_opt( 'mnav_enable' ),
		'sticky'   => $all || ( kk_opt( 'sticky_enable' ) && is_singular( 'product' ) ),
		'readmore' => $all || kk_opt( 'rm_enable' ),
	);
}

/**
 * Styles that only some pages need (3.13): 'checkout', 'account', 'order' (thank-you + tracking), 'product' (Q&A + reviews);
 * since 3.30 also 'carousel' (any slider) and 'terms' (brand / category cards), asked for by their renderers.
 * Pages known in advance get the file in <head>. A shortcode or widget shown somewhere else calls this while it is
 * being drawn and puts the returned <link> in front of its own HTML, so the style is there before the content
 * (no flash of unstyled content, and it also works inside cached Elementor widgets and the Elementor editor).
 *
 * @param string $part Part name.
 * @return string '' or a <link> tag to print before the component's HTML.
 */
function kk_need_css( $part ) {
	static $sent = array();
	$parts = array_merge( array( 'checkout', 'account', 'order', 'product', 'carousel', 'terms' ), array_keys( kk_feature_css_parts( true ) ) );
	if ( ! in_array( $part, $parts, true ) || isset( $sent[ $part ] ) ) {
		return '';
	}
	$handle = 'kenakata-kit-' . $part;
	if ( ! wp_style_is( $handle, 'registered' ) ) {
		wp_register_style( $handle, kk_asset( 'kenakata-kit-' . $part . '.css' ), array( 'kenakata-kit' ), KK_VERSION );
	}
	if ( wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'done' ) ) {
		$sent[ $part ] = true;
		return '';
	}
	// Styles of the <head> not printed yet (also block themes, which draw the content before the head): the normal way.
	if ( ! did_action( 'wp_print_styles' ) && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		$sent[ $part ] = true;
		if ( did_action( 'wp_enqueue_scripts' ) ) {
			wp_enqueue_style( $handle );
		} else {
			// Too early (a block theme draws the content first): our wp_enqueue_scripts step adds it, in the usual order.
			$GLOBALS['kk_css_parts'][ $part ] = true;
		}
		return '';
	}
	$sent[ $part ] = true;
	if ( ! wp_style_is( 'kenakata-kit', 'done' ) && ! wp_style_is( 'kenakata-kit', 'enqueued' ) ) {
		return ''; // no Kenakata Kit styles on this page at all (e.g. an admin screen)
	}
	return '<link rel="stylesheet" id="' . esc_attr( $handle ) . '-css" href="' . esc_url( add_query_arg( 'ver', KK_VERSION, kk_asset( 'kenakata-kit-' . $part . '.css' ) ) ) . '" media="all">' . "\n";
}

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'kenakata-kit', kk_asset( 'kenakata-kit.css' ), array(), KK_VERSION );
	wp_add_inline_style( 'kenakata-kit', kk_css_vars() );
	// Page-only styles asked for while a block theme drew the content, then the pages we can recognise.
	foreach ( array_keys( isset( $GLOBALS['kk_css_parts'] ) ? (array) $GLOBALS['kk_css_parts'] : array() ) as $part ) {
		wp_enqueue_style( 'kenakata-kit-' . $part );
	}
	if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
		kk_need_css( 'checkout' );
	}
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		kk_need_css( 'account' );
	}
	if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
		kk_need_css( 'order' );
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		kk_need_css( 'product' );
	}
	// Elementor editor / preview: widgets are drawn and re-drawn by AJAX, so every component style is there from the start.
	if ( function_exists( 'kk_is_editor' ) && kk_is_editor() ) {
		foreach ( array( 'carousel', 'terms', 'product' ) as $part ) {
			kk_need_css( $part );
		}
	}
	// Styles of features that are switched off are not loaded at all (3.29). Each of these features prints nothing
	// while its switch is off, so its styles are needed only when it is on.
	foreach ( kk_feature_css_parts() as $part => $on ) {
		if ( $on ) {
			wp_enqueue_style( 'kenakata-kit-' . $part, kk_asset( 'kenakata-kit-' . $part . '.css' ), array( 'kenakata-kit' ), KK_VERSION );
		}
	}
	if ( is_singular() ) {
		$post = get_post();
		$text = $post ? (string) $post->post_content : '';
		if ( false !== strpos( $text, 'layout="carousel' ) || false !== strpos( $text, "layout='carousel" ) ) {
			kk_need_css( 'carousel' );
		}
		foreach ( array( 'kk_brands' => 'terms', 'kk_categories' => 'terms', 'kk_terms' => 'terms', 'ss_terms' => 'terms', 'kk_my_account' => 'account', 'kk_order_tracking' => 'order', 'ss_order_tracking' => 'order', 'kk_thank_you' => 'order', 'ss_thank_you' => 'order', 'woocommerce_checkout' => 'checkout', 'woocommerce_cart' => 'checkout' ) as $code => $part ) {
			if ( false !== strpos( $text, '[' . $code ) ) {
				kk_need_css( $part );
			}
		}
	}
	// "Strong styles" come last, as before 3.13 (after the page-only styles too).
	if ( kk_opt( 'compat_strong' ) ) {
		$deps = array( 'kenakata-kit' );
		foreach ( array_merge( array( 'checkout', 'account', 'order', 'product', 'carousel', 'terms' ), array_keys( kk_feature_css_parts( true ) ) ) as $part ) {
			if ( wp_style_is( 'kenakata-kit-' . $part, 'enqueued' ) ) {
				$deps[] = 'kenakata-kit-' . $part;
			}
		}
		wp_enqueue_style( 'kenakata-kit-compat', kk_asset( 'kenakata-kit-compat.css' ), $deps, KK_VERSION );
	}
	add_filter( 'body_class', 'kk_body_classes' );

	wp_register_script( 'kenakata-kit', kk_asset( 'kenakata-kit.js' ), array(), KK_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script( 'kenakata-kit', 'kkData', array(
		'searchUrl'  => esc_url_raw( rest_url( 'kenakata/v1/search' ) ),
		'minChars'   => max( 1, absint( kk_opt( 'search_min_chars' ) ) ),
		'ajax'       => admin_url( 'admin-ajax.php' ),
		'account'    => wc_get_page_permalink( 'myaccount' ),
		'cartUrl'    => wc_get_cart_url(),
		'varAdd'     => 'yes' === get_option( 'woocommerce_enable_ajax_add_to_cart' ) ? WC_AJAX::get_endpoint( 'kk_add_variation' ) : '',
		'cartRedirect' => 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ? 1 : 0,
		'restUrl'    => esc_url_raw( rest_url( 'kenakata/v1/' ) ),
		'shopSearch' => add_query_arg( array( 'post_type' => 'product', 's' => '' ), home_url( '/' ) ),
		'popup'      => kk_opt( 'popup_enable' ) && ! is_user_logged_in() ? 1 : 0,
		'drawer'     => kk_opt( 'cart_drawer_enable' ) ? 1 : 0,
		'drawerAuto' => kk_opt( 'cart_drawer_enable' ) && kk_opt( 'cart_drawer_auto_open' ) ? 1 : 0,
		'drawerTake' => kk_opt( 'cart_drawer_enable' ) && kk_opt( 'cart_drawer_takeover' ) ? 1 : 0,
		'ajaxSingle' => kk_opt( 'cart_drawer_enable' ) && kk_opt( 'cart_drawer_ajax' ) ? 1 : 0,
		'wcAjax'     => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( '%%endpoint%%' ) : '',
		'slider'     => array(
			'autoplay' => kk_opt( 'cards_autoplay' ) ? 1 : 0,
			'speed'    => max( 2, absint( kk_opt( 'cards_autoplay_speed' ) ) ) * 1000,
			'loop'     => kk_opt( 'cards_loop' ) ? 1 : 0,
		),
		'wishlist'   => kk_opt( 'wl_enable' ) ? array(
			'user'  => is_user_logged_in() ? 1 : 0,
			'nonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'page'  => function_exists( 'kk_wl_page_url' ) ? kk_wl_page_url() : '',
			'add'   => kk_opt( 'wl_text_add' ) ? kk_opt( 'wl_text_add' ) : __( 'Add to wishlist', 'kenakata-kit' ),
			'added' => kk_opt( 'wl_text_added' ) ? kk_opt( 'wl_text_added' ) : __( 'In wishlist', 'kenakata-kit' ),
			/* translators: %d: number of products */
			'count' => __( '%d saved products', 'kenakata-kit' ),
			'one'   => __( '1 saved product', 'kenakata-kit' ),
			'saved' => __( 'Saved to your wishlist', 'kenakata-kit' ),
			'gone'  => __( 'Removed from your wishlist', 'kenakata-kit' ),
			'share' => __( 'My wishlist at %s:', 'kenakata-kit' ),
			'site'  => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'remove' => __( 'Remove', 'kenakata-kit' ),
		) : 0,
		'readMore'   => kk_opt( 'rm_enable' ) ? array(
			'h'    => max( 120, absint( kk_opt( 'rm_height' ) ) ),
			'hm'   => max( 120, absint( kk_opt( 'rm_height_mobile' ) ) ),
			'more' => esc_html( kk_opt( 'rm_more_text' ) ? kk_opt( 'rm_more_text' ) : __( 'Read more', 'kenakata-kit' ) ),
			'less' => esc_html( kk_opt( 'rm_less_text' ) ? kk_opt( 'rm_less_text' ) : __( 'Show less', 'kenakata-kit' ) ),
		) : 0,
		'i18n'       => array(
			'typeMore'   => __( 'Type at least %d letters…', 'kenakata-kit' ),
			'searching'  => __( 'Searching…', 'kenakata-kit' ),
			'none'       => __( 'No products found for "%s".', 'kenakata-kit' ),
			'found'      => __( '%d products found', 'kenakata-kit' ),
			'foundOne'   => __( '1 product found', 'kenakata-kit' ),
			'seeAll'     => __( 'See all %d results →', 'kenakata-kit' ),
			'outOfStock' => __( 'out of stock', 'kenakata-kit' ),
			'error'      => __( 'Search is not available right now. Press Enter to search.', 'kenakata-kit' ),
			'success'    => __( 'Success! Please wait…', 'kenakata-kit' ),
			'failed'     => __( 'Something went wrong. Please try again.', 'kenakata-kit' ),
			'network'    => __( 'Network error. Please try again.', 'kenakata-kit' ),
			'phone11'    => kk_opt( 'co_phone_error' ),
			'slide'      => __( 'Go to slide %d', 'kenakata-kit' ),
			/* translators: %d: number of photos */
			'photoMax'   => __( 'Only the first %d photos will be added.', 'kenakata-kit' ),
			/* translators: %d: megabytes */
			'photoBad'   => __( 'Some files were left out: only JPG, PNG or WebP photos up to %d MB.', 'kenakata-kit' ),
			'photo'      => __( 'Photo', 'kenakata-kit' ),
			'close'      => __( 'Close', 'kenakata-kit' ),
			'prev'       => __( 'Previous', 'kenakata-kit' ),
			'next'       => __( 'Next', 'kenakata-kit' ),
		),
	) );

	// Checkout needs the delivery-area script; product pages remember "recently viewed" and run the variation buttons.
	if ( ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url() ) || ( function_exists( 'is_product' ) && is_product() ) ) {
		wp_enqueue_script( 'kenakata-kit' );
	}
}, 20 );

/** Load the front-end JS (+ WooCommerce AJAX add-to-cart) for components that need it. */
function kk_need_js( $add_to_cart = false ) {
	wp_enqueue_script( 'kenakata-kit' );
	if ( $add_to_cart && 'yes' === get_option( 'woocommerce_enable_ajax_add_to_cart' ) ) {
		wp_enqueue_script( 'wc-add-to-cart' );
	}
}
