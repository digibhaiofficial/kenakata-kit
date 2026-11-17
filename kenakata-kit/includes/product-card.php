<?php
/**
 * Product card: one markup for every product list (WooCommerce loops, [kk_products], Elementor widgets).
 * Title is clamped to N lines with a matching min-height, so names never overlap the price on phones.
 */

defined( 'ABSPATH' ) || exit;

/** Card options from the settings (widgets pass their own values on top). */
function kk_card_defaults() {
	$layout = kk_opt( 'card_actions_layout' );
	if ( ! kk_opt( 'buynow_enable' ) || ! kk_opt( 'buynow_loop' ) ) {
		$layout = 'none' === $layout ? 'none' : 'cart_only';
	}
	return array(
		'style'          => kk_opt( 'card_style' ),
		'align'          => kk_opt( 'card_align' ),
		'show_image'     => true,
		'show_brand'     => (bool) kk_opt( 'card_show_brand' ),
		'show_title'     => true,
		'show_rating'    => (bool) kk_opt( 'card_show_rating' ),
		'show_price'     => true,
		'show_badge'     => (bool) kk_opt( 'card_show_badge' ),
		'show_stock'     => (bool) kk_opt( 'card_show_stock' ),
		'badge_text'     => kk_opt( 'card_badge_text' ),
		'stock_text'     => kk_opt( 'card_stock_text' ),
		'actions'        => $layout,
		'actions_mobile' => kk_opt( 'card_actions_mobile' ),
		'cart_text'      => kk_opt( 'card_cart_text' ),
		'buy_text'       => kk_opt( 'buynow_text' ),
		'title_tag'      => 'h2',
		'image_size'     => 'woocommerce_thumbnail',
	);
}

/** Discount numbers for a product on sale: [percent, amount] (largest discount for variable products). */
function kk_discount( $product ) {
	$best = array( 0, 0 );
	if ( ! $product || ! $product->is_on_sale() ) {
		return $best;
	}
	$pairs = array();
	if ( $product->is_type( 'variable' ) ) {
		$prices = $product->get_variation_prices( true );
		foreach ( $prices['regular_price'] as $vid => $reg ) {
			$pairs[] = array( (float) $reg, (float) $prices['price'][ $vid ] );
		}
	} else {
		$pairs[] = array( (float) $product->get_regular_price(), (float) $product->get_price() );
	}
	foreach ( $pairs as $p ) {
		if ( $p[0] > 0 && $p[1] < $p[0] ) {
			$percent = (int) round( ( $p[0] - $p[1] ) / $p[0] * 100 );
			if ( $percent > $best[0] ) {
				$best = array( $percent, $p[0] - $p[1] );
			}
		}
	}
	return $best;
}

/** Sale badge text from a template like "-{percent}%" ('' when not on sale). */
function kk_badge_text( $product, $template ) {
	if ( ! $product || ! $product->is_on_sale() ) {
		return '';
	}
	list( $percent, $amount ) = kk_discount( $product );
	$template = (string) $template;
	if ( '' === trim( $template ) || ( ! $percent && preg_match( '/\{(percent|amount)\}/', $template ) ) ) {
		return __( 'Sale', 'kenakata-kit' );
	}
	return str_replace( array( '{percent}', '{amount}' ), array( number_format_i18n( $percent ), wp_strip_all_tags( html_entity_decode( wc_price( $amount, array( 'decimals' => 0 ) ) ) ) ), $template );
}

/** Star rating (0–5) without icon fonts. */
function kk_stars_html( $rating, $count ) {
	if ( $count < 1 || $rating <= 0 ) {
		return '';
	}
	/* translators: %s: average rating */
	$label = sprintf( __( 'Rated %s out of 5', 'kenakata-kit' ), number_format_i18n( $rating, 1 ) );
	return '<div class="kk-pcard__rating"><span class="kk-stars" style="--kk-rating:' . esc_attr( round( (float) $rating, 2 ) ) . '" role="img" aria-label="' . esc_attr( $label ) . '"></span><span class="kk-pcard__rating-count">(' . esc_html( number_format_i18n( $count ) ) . ')</span></div>';
}

/** Add-to-cart + Buy Now buttons for a card. */
function kk_card_buttons( $product, $a ) {
	if ( function_exists( 'kk_need_js' ) ) {
		kk_need_js(); // the buttons work through kenakata-kit.js
	}
	$layout  = $a['actions'];
	if ( 'none' === $layout ) {
		return '';
	}
	$buyable  = $product->is_purchasable() && $product->is_in_stock();
	$variable = $product->is_type( 'variable' );
	$quick    = function_exists( 'kk_can_quick_buy' ) && kk_can_quick_buy( $product ); // simple, or variable with a default variation
	// Variable products show the same two buttons. Without a default variation they open the product page to choose.
	$can_buy  = kk_opt( 'buynow_enable' ) && ( $quick || ( $variable && $buyable ) );
	$show_buy = $can_buy && in_array( $layout, array( 'side', 'stack', 'buynow_only' ), true );
	$show_cart = ! $show_buy || 'buynow_only' !== $layout;
	if ( 'cart_only' === $layout ) {
		$show_buy = false;
	}
	$name = wp_strip_all_tags( $product->get_name() );
	$html = '';
	if ( $show_cart ) {
		$classes = array( 'kk-pcard__btn', 'kk-pcard__btn--cart', 'product_type_' . $product->get_type() );
		$url     = $product->add_to_cart_url();
		$label   = wp_strip_all_tags( $product->add_to_cart_description() );
		$add     = 0;
		if ( $buyable && ( $product->is_type( 'simple' ) || ( $variable && $quick ) ) ) {
			// No add-to-cart address in the link (bots follow links, people click): the link points to the product page,
			// the click is handled in kenakata-kit.js (POST, adds without leaving the page, opens the side cart).
			$add = $product->get_id();
			$url = $product->get_permalink();
			if ( $variable ) {
				/* translators: %s: product name */
				$label = sprintf( __( 'Add “%s” to your cart', 'kenakata-kit' ), $name );
			}
		}
		$text = $buyable && ( $product->is_type( 'simple' ) || $variable ) && $a['cart_text'] ? $a['cart_text'] : $product->add_to_cart_text();
		$html .= sprintf(
			'<a href="%1$s"%8$s data-quantity="1" data-product_id="%2$d" data-product_sku="%3$s" class="%4$s" aria-label="%5$s" rel="nofollow">%6$s<span class="kk-pcard__btn-text">%7$s</span></a>',
			esc_url( $url ),
			$product->get_id(),
			esc_attr( $product->get_sku() ),
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( $label ),
			kk_icon( 'cart' ),
			esc_html( $text ),
			$add ? ' data-kk-add="' . absint( $add ) . '"' : ''
		);
	}
	if ( $show_buy ) {
		$html .= sprintf(
			'<a href="%1$s"%4$s class="kk-pcard__btn kk-pcard__btn--buy kk-buy-now" rel="nofollow" aria-label="%2$s"><span class="kk-pcard__btn-text">%3$s</span></a>',
			esc_url( $product->get_permalink() ),
			/* translators: %s: product name */
			esc_attr( sprintf( __( 'Buy %s now', 'kenakata-kit' ), wp_strip_all_tags( $product->get_name() ) ) ),
			esc_html( $a['buy_text'] ),
			$quick ? ' data-kk-buy="' . absint( $product->get_id() ) . '"' : ''
		);
	}
	$single = ! ( $show_cart && $show_buy );
	$cls    = 'kk-pcard__actions kk-pcard__actions--' . sanitize_html_class( $single ? 'single' : $layout ) . ' kk-pcard__actions--m-' . sanitize_html_class( $a['actions_mobile'] );
	return '<div class="' . esc_attr( $cls ) . '">' . $html . '</div>';
}

/**
 * Image attributes for a card. The first cards of a normal page view are usually above the fold, so they load at
 * once (the first with high priority, which helps Largest Contentful Paint); the rest load lazily.
 * Cards loaded later by AJAX are always lazy.
 */
function kk_card_img_attr() {
	static $n = 0;
	$n++;
	$attr  = array( 'class' => 'kk-pcard__img', 'alt' => '', 'decoding' => 'async', 'loading' => 'lazy' );
	$eager = (int) apply_filters( 'kk_card_eager_images', 4 );
	if ( $n <= $eager && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! is_admin() ) {
		$attr['loading'] = false; // WordPress leaves the attribute out
		if ( 1 === $n ) {
			$attr['fetchpriority'] = 'high';
		}
	}
	return $attr;
}

/** The card. */
function kk_render_product_card( $product, $args = array() ) {
	if ( ! $product instanceof WC_Product ) {
		$product = wc_get_product( $product );
	}
	if ( ! $product ) {
		return '';
	}
	$a     = wp_parse_args( $args, kk_card_defaults() );
	$link  = $product->get_permalink();
	$name  = wp_strip_all_tags( $product->get_name() );
	$tag   = in_array( $a['title_tag'], array( 'h2', 'h3', 'h4', 'p', 'div' ), true ) ? $a['title_tag'] : 'h2';
	$site  = (string) kk_opt( 'card_title_tag' ); // site-wide choice (3.25), '' = each list's own
	if ( in_array( $site, array( 'h2', 'h3', 'h4', 'p' ), true ) ) {
		$tag = $site;
	}
	$stock = ! $product->is_in_stock();
	$cls   = 'kk-pcard kk-pcard--' . sanitize_html_class( $a['style'] ) . ' kk-pcard--align-' . sanitize_html_class( $a['align'] ) . ( $stock ? ' is-out-of-stock' : '' ) . ( $product->is_on_sale() ? ' is-on-sale' : '' );

	$out = '<div class="' . esc_attr( $cls ) . '" data-product-id="' . esc_attr( $product->get_id() ) . '">';
	if ( $a['show_image'] ) {
		$badges = '';
		if ( $a['show_badge'] && ( $text = kk_badge_text( $product, $a['badge_text'] ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
			$badges .= '<span class="kk-pcard__badge">' . esc_html( $text ) . '</span>';
		}
		if ( $a['show_stock'] && $stock ) {
			$badges .= '<span class="kk-pcard__badge kk-pcard__badge--stock">' . esc_html( $a['stock_text'] ) . '</span>';
		}
		$out .= '<a class="kk-pcard__media" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true">'
			. $product->get_image( $a['image_size'], array_merge( kk_card_img_attr(), array( 'alt' => wp_strip_all_tags( $product->get_name() ) ) ) ) // the link is hidden from screen readers; the alt is for image search
			. ( $badges ? '<span class="kk-pcard__badges">' . $badges . '</span>' : '' ) . '</a>';
		$out .= apply_filters( 'kk_product_card_media_after', '', $product ); // e.g. the wishlist ♡
	}
	$out .= '<div class="kk-pcard__body">';
	if ( $a['show_brand'] && ( $tax = kk_brand_taxonomy() ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
		$terms = get_the_terms( $product->get_id(), $tax );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$out .= '<a class="kk-pcard__brand" href="' . esc_url( get_term_link( $terms[0] ) ) . '">' . esc_html( html_entity_decode( $terms[0]->name ) ) . '</a>';
		}
	}
	if ( $a['show_title'] ) {
		$out .= '<' . $tag . ' class="kk-pcard__title"><a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a></' . $tag . '>';
	}
	if ( $a['show_rating'] ) {
		$out .= kk_stars_html( (float) $product->get_average_rating(), (int) $product->get_rating_count() );
	}
	if ( $a['show_price'] && ( $price = $product->get_price_html() ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found
		$out .= '<div class="kk-pcard__price"><span class="price">' . wp_kses_post( $price ) . '</span></div>';
	}
	ob_start();
	do_action( 'kk_card_after_price', $product );
	$out .= ob_get_clean();
	$out .= kk_card_buttons( $product, $a );
	$out .= '</div></div>';
	return $out;
}

/* -------------------------------------------------------------------------
 * Use the card for every WooCommerce product loop (shop, categories, related, Elementor "Products").
 * ---------------------------------------------------------------------- */
if ( kk_opt( 'card_enable' ) ) {
	add_filter( 'wc_get_template_part', function ( $template, $slug, $name ) {
		return 'content' === $slug && 'product' === $name ? KK_DIR . '/templates/content-product.php' : $template;
	}, 20, 3 );
	add_filter( 'woocommerce_product_loop_start', function ( $html ) {
		return preg_replace( '/class="products/', 'class="products kk-loop', $html, 1 );
	}, 20 );
	add_filter( 'loop_shop_columns', function () { return max( 1, absint( kk_opt( 'archive_columns' ) ) ); }, 30 );
	add_filter( 'loop_shop_per_page', function () { return max( 1, absint( kk_opt( 'archive_per_page' ) ) ); }, 30 );
	add_action( 'woocommerce_before_shop_loop', function () { kk_need_js( true ); } );
}

// Sale badge on product pages / theme loops uses the same text ("-12%").
add_filter( 'woocommerce_sale_flash', function ( $html, $post, $product ) {
	if ( ! kk_opt( 'card_enable' ) || ! kk_opt( 'card_show_badge' ) ) {
		return $html;
	}
	$text = kk_badge_text( $product, kk_opt( 'card_badge_text' ) );
	return $text ? '<span class="onsale kk-onsale">' . esc_html( $text ) . '</span>' : $html;
}, 20, 3 );
