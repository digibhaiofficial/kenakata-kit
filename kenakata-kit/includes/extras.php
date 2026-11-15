<?php
/**
 * Customer extras: WhatsApp / call buttons, delivery information, cart icon + side cart,
 * sticky Buy Now bar (product page, phones). The mobile bottom bar lives in bottom-bar.php.
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * WhatsApp / call links
 * ---------------------------------------------------------------------- */

/** Replace {product}, {price}, {url} with the current product (if any). */
function kk_fill_product_tokens( $text, $product = null ) {
	if ( ! $product && is_singular( 'product' ) ) {
		$product = wc_get_product( get_the_ID() );
	}
	$name  = $product ? wp_strip_all_tags( $product->get_name() ) : '';
	$price = $product && '' !== $product->get_price() ? trim( str_replace( "\xC2\xA0", ' ', wp_strip_all_tags( html_entity_decode( wc_price( wc_get_price_to_display( $product ) ) ) ) ) ) : '';
	$url   = $product ? get_permalink( $product->get_id() ) : home_url( add_query_arg( array() ) );
	return trim( str_replace( array( '{product}', '{price}', '{url}', "\xC2\xA0" ), array( $name, $price, $url, ' ' ), (string) $text ) );
}

function kk_whatsapp_url( $message = '', $number = '' ) {
	$number = kk_intl_phone( $number ? $number : kk_opt( 'wa_number' ) );
	if ( ! $number ) {
		return '';
	}
	return 'https://wa.me/' . $number . ( '' !== $message ? '?text=' . rawurlencode( $message ) : '' );
}

/**
 * Escape a link for href. WhatsApp links we build ourselves keep their %0A line breaks (esc_url() would remove them);
 * everything else goes through esc_url().
 */
function kk_esc_link( $url ) {
	if ( preg_match( '#^https://wa\.me/\d+(\?text=[A-Za-z0-9%._~!*()\x27-]*)?$#', (string) $url ) ) {
		return esc_attr( $url );
	}
	return esc_url( $url, array( 'http', 'https', 'tel' ) );
}

function kk_call_url( $number = '' ) {
	$number = $number ? $number : ( kk_opt( 'call_number' ) ? kk_opt( 'call_number' ) : kk_opt( 'wa_number' ) );
	$digits = preg_replace( '/[^\d+]/', '', (string) $number );
	return $digits ? 'tel:' . $digits : '';
}

/** [kk_whatsapp type="whatsapp|call" text="" message="" number=""] */
function kk_whatsapp_button( $atts ) {
	$a = shortcode_atts( array( 'type' => 'whatsapp', 'text' => '', 'message' => '', 'number' => '', 'class' => '' ), $atts, 'kk_whatsapp' );
	if ( 'call' === $a['type'] ) {
		$url  = kk_call_url( $a['number'] );
		$text = $a['text'] ? $a['text'] : __( 'Call us', 'kenakata-kit' );
		$icon = kk_icon( 'phone' );
	} else {
		$msg  = '' !== $a['message'] ? $a['message'] : ( is_singular( 'product' ) ? kk_opt( 'pp_wa_message' ) : kk_opt( 'wa_float_message' ) );
		$url  = kk_whatsapp_url( kk_fill_product_tokens( $msg ), $a['number'] );
		$text = $a['text'] ? $a['text'] : __( 'WhatsApp', 'kenakata-kit' );
		$icon = kk_icon( 'whatsapp' );
	}
	if ( ! $url ) {
		return kk_is_editor() ? '<p class="kk-ew-note">' . esc_html__( 'Add your WhatsApp number in Kenakata Kit → WhatsApp & Call.', 'kenakata-kit' ) . '</p>' : '';
	}
	return '<a class="kk-contact-btn kk-contact-btn--' . esc_attr( 'call' === $a['type'] ? 'call' : 'whatsapp' ) . ' ' . esc_attr( $a['class'] ) . '" href="' . kk_esc_link( $url ) . '"' . ( 'call' === $a['type'] ? '' : ' target="_blank" rel="noopener"' ) . '>' . $icon . '<span>' . esc_html( $text ) . '</span></a>';
}
add_shortcode( 'kk_whatsapp', 'kk_whatsapp_button' );

/* Floating buttons. */
add_action( 'wp_footer', function () {
	if ( is_admin() || ( function_exists( 'is_checkout' ) && is_checkout() ) ) {
		return;
	}
	$wa   = kk_opt( 'wa_float_enable' ) ? kk_whatsapp_url( kk_opt( 'wa_float_message' ) ) : '';
	$call = kk_opt( 'call_float_enable' ) ? kk_call_url() : '';
	if ( ! $wa && ! $call ) {
		return;
	}
	$cls  = 'kk-float kk-float--' . ( 'left' === kk_opt( 'wa_float_position' ) ? 'left' : 'right' );
	$cls .= kk_opt( 'wa_float_mobile' ) ? '' : ' kk-float--no-mobile';
	$cls .= kk_opt( 'wa_float_desktop' ) ? '' : ' kk-float--no-desktop';
	echo '<div class="' . esc_attr( $cls ) . '">';
	if ( $call ) {
		echo '<a class="kk-float__btn kk-float__btn--call" href="' . esc_url( $call, array( 'tel' ) ) . '" aria-label="' . esc_attr__( 'Call us', 'kenakata-kit' ) . '">' . kk_icon( 'phone' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG
	}
	if ( $wa ) {
		$text = trim( (string) kk_opt( 'wa_float_text' ) );
		echo '<a class="kk-float__btn kk-float__btn--wa' . ( $text ? ' has-text' : '' ) . '" href="' . kk_esc_link( $wa ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $text ? $text : __( 'Chat on WhatsApp', 'kenakata-kit' ) ) . '">' . kk_icon( 'whatsapp' ) . ( $text ? '<span class="kk-float__text">' . esc_html( $text ) . '</span>' : '' ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG
	}
	echo '</div>';
}, 20 );

/* -------------------------------------------------------------------------
 * Delivery information
 * ---------------------------------------------------------------------- */
function kk_delivery_info( $atts = array() ) {
	$a     = shortcode_atts( array( 'title' => kk_opt( 'di_title' ), 'charges' => kk_opt( 'di_show_charges' ) ? 'yes' : 'no', 'lines' => kk_opt( 'di_lines' ) ), $atts, 'kk_delivery_info' );
	$items = '';
	if ( 'yes' === $a['charges'] && function_exists( 'kk_area_methods' ) ) {
		foreach ( kk_area_methods() as $m ) {
			$items .= '<li class="kk-delivery__item kk-delivery__item--charge">' . kk_icon( 'truck' ) . '<span>' . esc_html( $m['title'] ) . ': <strong>' . wp_kses_post( wc_price( $m['cost'] ) ) . '</strong></span></li>';
		}
	}
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $a['lines'] ) as $line ) {
		if ( '' !== trim( $line ) ) {
			$items .= '<li class="kk-delivery__item">' . kk_icon( 'check' ) . '<span>' . esc_html( trim( $line ) ) . '</span></li>';
		}
	}
	if ( '' === $items ) {
		return '';
	}
	return '<div class="kk-delivery">' . ( $a['title'] ? '<p class="kk-delivery__title">' . esc_html( $a['title'] ) . '</p>' : '' ) . '<ul class="kk-delivery__list">' . $items . '</ul></div>';
}
add_shortcode( 'kk_delivery_info', 'kk_delivery_info' );

/** "Order on WhatsApp" / "Call to order" buttons for the product page (as switched on in the settings). */
function kk_pp_contact_html() {
	$buttons = '';
	if ( kk_opt( 'pp_wa_enable' ) && kk_opt( 'wa_number' ) ) {
		$buttons .= kk_whatsapp_button( array( 'type' => 'whatsapp', 'text' => kk_opt( 'pp_wa_text' ), 'message' => kk_opt( 'pp_wa_message' ) ) );
	}
	if ( kk_opt( 'pp_call_enable' ) && kk_call_url() ) {
		$buttons .= kk_whatsapp_button( array( 'type' => 'call', 'text' => kk_opt( 'pp_call_text' ) ) );
	}
	return $buttons ? '<div class="kk-pp-contact">' . $buttons . '</div>' : '';
}

/* Product page additions (after the add-to-cart form; Elementor templates use the widgets instead). */
add_action( 'woocommerce_single_product_summary', function () {
	global $product;
	if ( ! $product ) {
		return;
	}
	echo kk_pp_contact_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_whatsapp_button()
	if ( kk_opt( 'pp_delivery_enable' ) ) {
		echo kk_delivery_info(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_delivery_info()
	}
}, 35 );

/* Sticky Buy Now bar on product pages (phones). */
add_action( 'wp_footer', function () {
	if ( ! kk_opt( 'sticky_enable' ) || ! is_singular( 'product' ) ) {
		return;
	}
	$product = wc_get_product( get_the_ID() );
	if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return;
	}
	kk_need_js( true );
	$simple = kk_can_quick_buy( $product );
	echo '<div class="kk-sticky" hidden><div class="kk-sticky__info">' . $product->get_image( 'thumbnail', array( 'class' => 'kk-sticky__img', 'alt' => '' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce markup
		. '<span class="kk-sticky__text"><span class="kk-sticky__name">' . esc_html( wp_strip_all_tags( $product->get_name() ) ) . '</span><span class="kk-sticky__price">' . wp_kses_post( $product->get_price_html() ) . '</span></span></div><div class="kk-sticky__actions">';
	if ( kk_opt( 'sticky_show_cart' ) ) {
		echo '<button type="button" class="kk-sticky__btn kk-sticky__btn--cart" data-kk-sticky="cart" aria-label="' . esc_attr( kk_opt( 'card_cart_text' ) ) . '">' . kk_icon( 'cart' ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG
	}
	if ( kk_opt( 'buynow_enable' ) ) {
		echo '<button type="button" class="kk-sticky__btn kk-sticky__btn--buy" data-kk-sticky="buy">' . esc_html( kk_opt( 'buynow_text' ) ) . '</button>';
	} elseif ( ! kk_opt( 'sticky_show_cart' ) ) {
		echo '<button type="button" class="kk-sticky__btn kk-sticky__btn--buy" data-kk-sticky="cart">' . esc_html( kk_opt( 'card_cart_text' ) ) . '</button>';
	}
	echo '</div></div>';
	unset( $simple );
}, 15 );

/* -------------------------------------------------------------------------
 * Cart icon + side cart
 * ---------------------------------------------------------------------- */
function kk_cart_count() {
	return function_exists( 'WC' ) && WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
}

function kk_cart_icon( $atts = array() ) {
	$a = shortcode_atts( array( 'show_total' => 'no', 'label' => __( 'Cart', 'kenakata-kit' ) ), $atts, 'kk_cart_icon' );
	kk_need_js(); // kenakata-kit.js refreshes the count only when the visitor has a cart (no request per page)
	$count = kk_cart_count();
	$total = 'yes' === $a['show_total'] && function_exists( 'WC' ) && WC()->cart ? '<span class="kk-cart__total">' . wp_kses_post( WC()->cart->get_cart_subtotal() ) . '</span>' : '';
	return '<a class="kk-cart" href="' . esc_url( wc_get_cart_url() ) . '"' . ( kk_opt( 'cart_drawer_enable' ) ? ' data-kk-drawer' : '' ) . '>'
		. '<span class="kk-cart__icon">' . kk_icon( 'cart' ) . '<span class="kk-cart__count' . ( $count ? '' : ' is-empty' ) . '">' . esc_html( $count ) . '</span></span>'
		. $total . '<span class="screen-reader-text">' . esc_html( $a['label'] ) . '</span></a>';
}
add_shortcode( 'kk_cart_icon', 'kk_cart_icon' );

add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$count                            = kk_cart_count();
	$fragments['span.kk-cart__count'] = '<span class="kk-cart__count' . ( $count ? '' : ' is-empty' ) . '">' . esc_html( $count ) . '</span>';
	$fragments['span.kk-cart__total'] = '<span class="kk-cart__total">' . wp_kses_post( WC()->cart->get_cart_subtotal() ) . '</span>';
	$fragments['span.kk-bbar__count'] = '<span class="kk-bbar__count' . ( $count ? '' : ' is-empty' ) . '" aria-hidden="true">' . esc_html( $count ) . '</span>';
	/* translators: %d: number of items in the cart */
	$fragments['span.kk-bbar__sr']    = '<span class="screen-reader-text kk-bbar__sr">' . esc_html( sprintf( _n( '(%d item)', '(%d items)', $count, 'kenakata-kit' ), $count ) ) . '</span>';
	if ( kk_opt( 'cart_drawer_enable' ) ) {
		// The side cart has its own fragment, so a page builder's mini cart (e.g. Elementor Pro "Menu Cart" with its own
		// template) keeps its own markup and styles in div.widget_shopping_cart_content.
		$fragments['div.kk-drawer__content'] = '<div class="kk-drawer__content">' . kk_drawer_mini_cart() . '</div>';
	}
	return $fragments;
} );

// Fragments saved in the browser before 3.15.1 do not have the side cart's own fragment: use a new storage name.
add_filter( 'woocommerce_cart_fragment_name', function ( $name ) {
	return kk_opt( 'cart_drawer_enable' ) ? $name . '_kk2' : $name;
} );

/**
 * The side cart's contents: WooCommerce's own mini-cart markup (the theme's override or WooCommerce's default), even when
 * a page builder replaces the mini-cart template for its own widget. Only this render is affected.
 */
function kk_drawer_mini_cart() {
	if ( ! function_exists( 'woocommerce_mini_cart' ) ) {
		return '';
	}
	$GLOBALS['kk_own_mini_cart'] = true;
	ob_start();
	woocommerce_mini_cart();
	$html = (string) ob_get_clean();
	unset( $GLOBALS['kk_own_mini_cart'] );
	return $html;
}

add_action( 'wp_footer', function () {
	if ( ! kk_opt( 'cart_drawer_enable' ) || is_admin() || ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) ) {
		return;
	}
	kk_need_js( true ); // no wc-cart-fragments: kenakata-kit.js loads the cart only for visitors who have one
	$dcls = 'kk-drawer' . ( kk_opt( 'cart_drawer_thumbs' ) ? '' : ' kk-drawer--no-thumbs' ) . ( kk_opt( 'cart_drawer_remove' ) ? '' : ' kk-drawer--no-remove' );
	echo '<div class="' . esc_attr( $dcls ) . '" id="kk-drawer" hidden><div class="kk-drawer__backdrop" data-kk-drawer-close></div>'
		. '<aside class="kk-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="kk-drawer-title">'
		. '<header class="kk-drawer__head"><h2 id="kk-drawer-title">' . esc_html( kk_opt( 'cart_drawer_title' ) ) . '</h2><button type="button" class="kk-drawer__close" data-kk-drawer-close aria-label="' . esc_attr__( 'Close', 'kenakata-kit' ) . '">' . kk_icon( 'close' ) . '</button></header>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG
		. '<div class="kk-drawer__body"><div class="kk-drawer__content">' . kk_drawer_mini_cart() . '</div></div></aside></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce template output
}, 16 );

/* -------------------------------------------------------------------------
 * Products imported with the full text in the short description (and an empty Description):
 * the Description tab shows the full text, and the short description next to the price shows only
 * the first lines with a "Read more" link. Nothing is saved; switch off in Product page → Product description.
 * ---------------------------------------------------------------------- */
/** The full text to use as description, or '' when the product has its own description / a short summary. */
function kk_desc_fallback_text( $product ) {
	if ( ! kk_opt( 'desc_fallback' ) || ! $product instanceof WC_Product ) {
		return '';
	}
	if ( '' !== trim( wp_strip_all_tags( (string) $product->get_description( 'edit' ) ) ) ) {
		return '';
	}
	$short = (string) $product->get_short_description( 'edit' );
	return mb_strlen( trim( wp_strip_all_tags( $short ) ) ) > 300 ? $short : '';
}

function kk_desc_fallback_product() {
	global $product;
	if ( $product instanceof WC_Product ) {
		return $product;
	}
	return is_singular( 'product' ) ? wc_get_product( get_the_ID() ) : null;
}

add_filter( 'woocommerce_product_tabs', function ( $tabs ) {
	$text = kk_desc_fallback_text( kk_desc_fallback_product() );
	if ( '' !== $text && empty( $tabs['description'] ) ) {
		$tabs['description'] = array(
			'title'    => __( 'Description', 'kenakata-kit' ),
			'priority' => 10,
			'callback' => function () use ( $text ) {
				// Formatted here (not with wc_format_content(), which runs the short-description filter and would shorten it again).
				$html = do_shortcode( shortcode_unautop( wpautop( wptexturize( wp_kses_post( $text ) ) ) ) );
				echo '<div class="kk-desc">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses-filtered above
			},
		);
	}
	return $tabs;
}, 20 );

/* -------------------------------------------------------------------------
 * Product brand with its logo: "Brand: [logo] Name". [kk_product_brand] and the "Product Brand" widget.
 * Brands without a logo show the first letter (or only the name).
 * ---------------------------------------------------------------------- */
function kk_product_brand_html( $product, $a = array() ) {
	$a = wp_parse_args( $a, array( 'label' => __( 'Brand:', 'kenakata-kit' ), 'logo' => 'yes', 'name' => 'yes', 'link' => 'yes', 'fallback' => 'letter' ) );
	$tax = function_exists( 'kk_brand_taxonomy' ) ? kk_brand_taxonomy() : '';
	if ( ! $product instanceof WC_Product || ! $tax ) {
		return '';
	}
	$terms = get_the_terms( $product->get_id(), $tax );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$items = '';
	foreach ( $terms as $term ) {
		$name  = html_entity_decode( $term->name );
		$thumb = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
		$media = '';
		if ( 'yes' === $a['logo'] ) {
			if ( $thumb ) {
				$media = '<span class="kk-brand__logo">' . wp_get_attachment_image( $thumb, 'thumbnail', false, array( 'alt' => 'yes' === $a['name'] ? '' : $name, 'loading' => 'lazy' ) ) . '</span>';
			} elseif ( 'letter' === $a['fallback'] ) {
				$media = '<span class="kk-brand__logo kk-brand__logo--letter" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( wp_strip_all_tags( $name ), 0, 1 ) ) ) . '</span>';
			}
		}
		$inner = $media . ( 'yes' === $a['name'] || ! $media ? '<span class="kk-brand__name">' . esc_html( $name ) . '</span>' : '' );
		$link  = 'yes' === $a['link'] ? get_term_link( $term ) : '';
		$items .= $link && ! is_wp_error( $link )
			? '<a class="kk-brand__item" href="' . esc_url( $link ) . '">' . $inner . '</a>'
			: '<span class="kk-brand__item">' . $inner . '</span>';
	}
	return '<div class="kk-brand">' . ( '' !== trim( (string) $a['label'] ) ? '<span class="kk-brand__label">' . esc_html( $a['label'] ) . '</span>' : '' ) . '<span class="kk-brand__list">' . $items . '</span></div>';
}
add_shortcode( 'kk_product_brand', function ( $atts ) {
	$a = shortcode_atts( array( 'label' => __( 'Brand:', 'kenakata-kit' ), 'logo' => 'yes', 'name' => 'yes', 'link' => 'yes', 'fallback' => 'letter', 'id' => 0 ), $atts, 'kk_product_brand' );
	global $product;
	$p = $a['id'] ? wc_get_product( absint( $a['id'] ) ) : ( $product instanceof WC_Product ? $product : ( is_singular( 'product' ) ? wc_get_product( get_the_ID() ) : null ) );
	return kk_product_brand_html( $p, $a );
} );

/* -------------------------------------------------------------------------
 * Stock status on the product page. WooCommerce prints nothing for an in-stock product that does not
 * track stock ("Manage stock?" off), so a "Product Stock" widget stays empty and hidden. With this on,
 * such products show "In stock". Marked kk-stock-auto so the Add to cart form does not repeat it.
 * ---------------------------------------------------------------------- */
function kk_stock_auto_applies( $product ) {
	return kk_opt( 'stock_always' ) && $product instanceof WC_Product && function_exists( 'is_product' ) && is_product()
		&& (int) $product->get_id() === (int) get_queried_object_id() && ! $product->managing_stock() && $product->is_in_stock() && ! $product->is_on_backorder();
}
add_filter( 'woocommerce_get_availability_text', function ( $text, $product ) {
	if ( '' === (string) $text && kk_stock_auto_applies( $product ) ) {
		$text = kk_opt( 'stock_in_text' ) ? kk_opt( 'stock_in_text' ) : __( 'In stock', 'kenakata-kit' );
	}
	return $text;
}, 20, 2 );
add_filter( 'woocommerce_get_availability_class', function ( $class, $product ) {
	return kk_stock_auto_applies( $product ) ? trim( $class . ' kk-stock-auto' ) : $class;
}, 20, 2 );

add_filter( 'woocommerce_short_description', function ( $excerpt ) {
	$product = kk_desc_fallback_product();
	if ( ! is_singular( 'product' ) || '' === kk_desc_fallback_text( $product ) || (string) $excerpt !== (string) $product->get_short_description( 'edit' ) ) {
		return $excerpt;
	}
	// First lines only, without a leading "Product Description" style heading.
	$plain = preg_replace( '#^\s*<h[1-6][^>]*>.*?</h[1-6]>#is', '', (string) $excerpt );
	$plain = wp_trim_words( wp_strip_all_tags( $plain ), 32, '…' );
	return esc_html( $plain ) . ' <a class="kk-more-link" href="#tab-description">' . esc_html__( 'Read more', 'kenakata-kit' ) . '</a>';
}, 5 );
/* Side cart buttons and texts (Kenakata Kit → Side cart). */
add_action( 'init', function () {
	if ( ! kk_opt( 'cart_drawer_enable' ) ) {
		return;
	}
	remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10 );
	remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout', 20 );
	add_action( 'woocommerce_widget_shopping_cart_buttons', function () {
		if ( kk_opt( 'cart_drawer_view' ) ) {
			$t = kk_opt( 'cart_drawer_view_text' ) ? kk_opt( 'cart_drawer_view_text' ) : __( 'View cart', 'woocommerce' );
			echo '<a href="' . esc_url( wc_get_cart_url() ) . '" class="button wc-forward">' . esc_html( $t ) . '</a>';
		}
	}, 10 );
	add_action( 'woocommerce_widget_shopping_cart_buttons', function () {
		$t = kk_opt( 'cart_drawer_checkout_text' ) ? kk_opt( 'cart_drawer_checkout_text' ) : __( 'Checkout', 'woocommerce' );
		echo '<a href="' . esc_url( wc_get_checkout_url() ) . '" class="button checkout wc-forward">' . esc_html( $t ) . '</a>';
	}, 20 );
}, 20 );
add_filter( 'gettext', function ( $translation, $text, $domain ) {
	if ( 'woocommerce' === $domain && 'No products in the cart.' === $text && kk_opt( 'cart_drawer_enable' ) && kk_opt( 'cart_drawer_empty_text' ) ) {
		return kk_opt( 'cart_drawer_empty_text' );
	}
	return $translation;
}, 20, 3 );
/*
 * The side cart needs WooCommerce's own mini-cart markup (remove buttons, classes the styles target).
 * Some page builders (Elementor Pro "Menu Cart" template) replace it site-wide; for the side cart only
 * (kk_drawer_mini_cart), use the theme's override or WooCommerce's default instead. Before 3.15.1 this applied
 * to every mini cart, which broke the builder's own cart panel.
 */
add_filter( 'woocommerce_locate_template', function ( $template, $name ) {
	if ( 'cart/mini-cart.php' !== $name || empty( $GLOBALS['kk_own_mini_cart'] ) || ! function_exists( 'WC' ) ) {
		return $template;
	}
	$own = array( wp_normalize_path( WC()->plugin_path() ), wp_normalize_path( get_stylesheet_directory() ), wp_normalize_path( get_template_directory() ) );
	$tpl = wp_normalize_path( (string) $template );
	foreach ( $own as $dir ) {
		if ( $dir && 0 === strpos( $tpl, $dir ) ) {
			return $template;
		}
	}
	$theme = locate_template( array( trailingslashit( WC()->template_path() ) . $name ) );
	return $theme ? $theme : WC()->plugin_path() . '/templates/' . $name;
}, 999, 2 );
/* Some builders swap the file later through wc_get_template; apply the same rule there. */
add_filter( 'wc_get_template', function ( $template, $name ) {
	if ( 'cart/mini-cart.php' !== $name || empty( $GLOBALS['kk_own_mini_cart'] ) || ! function_exists( 'WC' ) ) {
		return $template;
	}
	$tpl = wp_normalize_path( (string) $template );
	foreach ( array( WC()->plugin_path(), get_stylesheet_directory(), get_template_directory() ) as $dir ) {
		if ( $dir && 0 === strpos( $tpl, wp_normalize_path( $dir ) ) ) {
			return $template;
		}
	}
	$theme = locate_template( array( trailingslashit( WC()->template_path() ) . $name ) );
	return $theme ? $theme : WC()->plugin_path() . '/templates/' . $name;
}, 999, 2 );
/** Editor-only: a static open side cart with sample products, so the Cart Icon widget's styles can be previewed. */
function kk_drawer_preview_html() {
	$ids   = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'limit' => 2, 'status' => 'publish', 'return' => 'ids' ) ) : array();
	$items = '';
	$sum   = 0;
	foreach ( $ids as $i => $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		$qty  = $i + 1;
		$sum += (float) $p->get_price() * $qty;
		$items .= '<li class="woocommerce-mini-cart-item mini_cart_item">'
			. ( kk_opt( 'cart_drawer_remove' ) ? '<a href="#" class="remove remove_from_cart_button" aria-label="' . esc_attr__( 'Remove this item', 'woocommerce' ) . '">&times;</a>' : '' )
			. '<a href="#">' . ( kk_opt( 'cart_drawer_thumbs' ) ? $p->get_image( 'woocommerce_thumbnail' ) : '' ) . esc_html( $p->get_name() ) . '</a>'
			. '<dl class="variation"><dt>' . esc_html__( 'Colour', 'kenakata-kit' ) . ':</dt><dd><p>' . esc_html__( 'Cream', 'kenakata-kit' ) . '</p></dd></dl>'
			. '<span class="quantity">' . $qty . ' &times; ' . wp_kses_post( wc_price( $p->get_price() ) ) . '</span></li>';
	}
	$cls = 'kk-drawer kk-drawer--preview is-open' . ( kk_opt( 'cart_drawer_thumbs' ) ? '' : ' kk-drawer--no-thumbs' ) . ( kk_opt( 'cart_drawer_remove' ) ? '' : ' kk-drawer--no-remove' );
	return '<div class="' . esc_attr( $cls ) . '"><aside class="kk-drawer__panel">'
		. '<header class="kk-drawer__head"><h2>' . esc_html( kk_opt( 'cart_drawer_title' ) ) . '</h2><button type="button" class="kk-drawer__close" aria-label="' . esc_attr__( 'Close', 'kenakata-kit' ) . '">' . kk_icon( 'close' ) . '</button></header>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG
		. '<div class="kk-drawer__body"><div class="kk-drawer__content">'
		. ( function_exists( 'kk_free_delivery_html' ) && kk_opt( 'fd_drawer' ) ? kk_free_delivery_html( array( 'class' => 'kk-fd--drawer' ) ) : '' )
		. '<ul class="woocommerce-mini-cart cart_list product_list_widget">' . $items . '</ul>'
		. '<p class="woocommerce-mini-cart__total total"><strong>' . esc_html__( 'Subtotal:', 'woocommerce' ) . '</strong> ' . wp_kses_post( wc_price( $sum ) ) . '</p>'
		. '<p class="woocommerce-mini-cart__buttons buttons">'
		. ( kk_opt( 'cart_drawer_view' ) ? '<a href="#" class="button wc-forward">' . esc_html( kk_opt( 'cart_drawer_view_text' ) ? kk_opt( 'cart_drawer_view_text' ) : __( 'View cart', 'woocommerce' ) ) . '</a>' : '' )
		. '<a href="#" class="button checkout wc-forward">' . esc_html( kk_opt( 'cart_drawer_checkout_text' ) ? kk_opt( 'cart_drawer_checkout_text' ) : __( 'Checkout', 'woocommerce' ) ) . '</a></p>'
		. '<p class="woocommerce-mini-cart__empty-message">' . esc_html( kk_opt( 'cart_drawer_empty_text' ) ) . ' <small>(' . esc_html__( 'shown when the cart is empty', 'kenakata-kit' ) . ')</small></p>'
		. '</div></div></aside></div>';
}