<?php
/**
 * Buy Now: add the product and go straight to checkout.
 *  - Product cards: buttons come from the product card (product-card.php).
 *  - Product page: Buy Now submits the same form (quantity and variation respected).
 *  - [kk_buy_now id="123"] and the "Buy Now বাটন" Elementor widget for any page.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The variation a variable product opens with: the one picked by its "Default Form Values" (Product data → Variations),
 * when every option has a default and that variation is in stock. Returns array( variation_id, attributes ) or null.
 */
function kk_default_variation( $product ) {
	static $cache = array();
	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) ) {
		return null;
	}
	$pid = $product->get_id();
	if ( array_key_exists( $pid, $cache ) ) {
		return $cache[ $pid ];
	}
	$cache[ $pid ] = null;
	$defaults      = $product->get_default_attributes();
	if ( ! $defaults || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return null;
	}
	$attrs = array();
	foreach ( array_keys( $product->get_variation_attributes() ) as $name ) {
		$key = sanitize_title( $name );
		if ( ! isset( $defaults[ $key ] ) || '' === (string) $defaults[ $key ] ) {
			return null; // one option has no default: the customer has to choose on the product page
		}
		$attrs[ 'attribute_' . $key ] = (string) $defaults[ $key ];
	}
	$vid       = WC_Data_Store::load( 'product' )->find_matching_product_variation( $product, $attrs );
	$variation = $vid ? wc_get_product( $vid ) : null;
	if ( ! $variation || ! $variation->is_purchasable() || ! $variation->is_in_stock() || ! $variation->variation_is_visible() ) {
		return null;
	}
	$cache[ $pid ] = array( (int) $vid, $attrs );
	return $cache[ $pid ];
}

/** Query args that add a product (and its default variation, for variable products) to the cart. */
function kk_cart_args( $product ) {
	$args = array();
	$dv   = kk_default_variation( $product );
	if ( $dv ) {
		$args['variation_id'] = $dv[0];
		foreach ( $dv[1] as $k => $v ) {
			$args[ $k ] = rawurlencode( $v );
		}
	}
	return $args;
}

function kk_buy_now_url( $product_id ) {
	$product = wc_get_product( $product_id );
	$args    = array( 'kk_buy_now' => absint( $product_id ) ) + ( $product ? kk_cart_args( $product ) : array() );
	return add_query_arg( $args, wc_get_checkout_url() );
}

/** Can the product go to the cart in one click? Simple products, and variable products with a default variation. */
function kk_can_quick_buy( $product ) {
	if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		return false;
	}
	return $product->is_type( 'simple' ) || null !== kk_default_variation( $product );
}

/**
 * AJAX add-to-cart for variable products on product cards. Only the product ID is sent; the variation is the
 * product's default variation, worked out here again, so posted data can never pick something else.
 * Responds like WooCommerce's own add_to_cart endpoint (fragments + cart hash), so mini carts and the side cart update.
 */
add_action( 'wc_ajax_kk_add_variation', function () {
	$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- same as WooCommerce add_to_cart (public, cache-safe)
	$quantity   = isset( $_POST['quantity'] ) ? max( 1, wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
	$product    = wc_get_product( $product_id );
	$dv         = $product && 'publish' === get_post_status( $product_id ) ? kk_default_variation( $product ) : null;
	if ( $dv ) {
		$passed = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $dv[0], $dv[1] );
		if ( $passed && false !== WC()->cart->add_to_cart( $product_id, $quantity, $dv[0], $dv[1] ) ) {
			do_action( 'woocommerce_ajax_added_to_cart', $product_id );
			if ( 'yes' === get_option( 'woocommerce_cart_redirect_after_add' ) ) {
				wc_add_to_cart_message( array( $product_id => $quantity ), true );
			}
			if ( method_exists( 'WC_AJAX', 'get_refreshed_fragments' ) ) {
				WC_AJAX::get_refreshed_fragments(); // sends the JSON response and exits
			}
			// Fallback for a future WooCommerce without that helper: the browser then reloads the cart normally.
			wp_send_json( array( 'fragments' => apply_filters( 'woocommerce_add_to_cart_fragments', array() ), 'cart_hash' => WC()->cart->get_cart_hash() ) );
		}
	}
	wp_send_json( array(
		'error'       => true,
		'product_url' => apply_filters( 'woocommerce_cart_redirect_after_error', $product ? $product->get_permalink() : wc_get_page_permalink( 'shop' ), $product_id ),
	) );
} );

function kk_buy_now_link( $product, $text = '', $class = '' ) {
	$text = $text ? $text : kk_opt( 'buynow_text' );
	return sprintf(
		'<a href="%1$s" class="button kk-buy-now %4$s" rel="nofollow" aria-label="%2$s">%3$s</a>',
		esc_url( kk_buy_now_url( $product->get_id() ) ),
		/* translators: %s: product name */
		esc_attr( sprintf( __( 'Buy %s now', 'kenakata-kit' ), wp_strip_all_tags( $product->get_name() ) ) ),
		esc_html( $text ),
		esc_attr( $class )
	);
}

if ( ! kk_opt( 'buynow_enable' ) ) {
	add_shortcode( 'kk_buy_now', '__return_empty_string' );
	return;
}

/** Handle Buy Now before WooCommerce's own add-to-cart handler (priority 20). Accepts the old ss_buy_now name too. */
add_action( 'wp_loaded', function () {
	$key = ! empty( $_REQUEST['kk_buy_now'] ) ? 'kk_buy_now' : ( ! empty( $_REQUEST['ss_buy_now'] ) ? 'ss_buy_now' : '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $key || is_admin() || ! function_exists( 'WC' ) ) {
		return;
	}
	unset( $_REQUEST['add-to-cart'], $_POST['add-to-cart'], $_GET['add-to-cart'] );

	$product_id   = absint( wp_unslash( $_REQUEST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$variation_id = isset( $_REQUEST['variation_id'] ) ? absint( wp_unslash( $_REQUEST['variation_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$quantity     = isset( $_REQUEST['quantity'] ) ? max( 1, wc_stock_amount( wp_unslash( $_REQUEST['quantity'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
	$product      = wc_get_product( $variation_id ? $variation_id : $product_id );

	if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
		wc_add_notice( __( 'Sorry, this product cannot be purchased right now.', 'kenakata-kit' ), 'error' );
		wp_safe_redirect( $product_id ? get_permalink( $product_id ) : wc_get_page_permalink( 'shop' ) );
		exit;
	}
	if ( null === WC()->cart ) {
		wc_load_cart();
	}
	if ( kk_opt( 'buynow_clear_cart' ) ) {
		WC()->cart->empty_cart();
	}

	$in_cart = false;
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( (int) $item['product_id'] === $product_id && (int) $item['variation_id'] === $variation_id ) {
			$in_cart = true;
			break;
		}
	}
	if ( ! $in_cart ) {
		$attributes = array();
		if ( $variation_id ) {
			foreach ( $_REQUEST as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification
				if ( 0 === strpos( $k, 'attribute_' ) ) {
					$attributes[ sanitize_title( $k ) ] = wc_clean( wp_unslash( $v ) );
				}
			}
		}
		if ( ! WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes ) ) {
			wp_safe_redirect( get_permalink( $product_id ) );
			exit;
		}
	}
	wc_clear_notices();
	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}, 15 );

// Theme loops when the Kenakata card is off: add Buy Now next to Add to cart.
if ( kk_opt( 'buynow_loop' ) && ! kk_opt( 'card_enable' ) ) {
	add_action( 'init', function () {
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		add_action( 'woocommerce_after_shop_loop_item', 'kk_loop_actions', 10 );
	} );
}

function kk_loop_actions() {
	global $product;
	$quick  = kk_can_quick_buy( $product );
	$layout = kk_opt( 'card_actions_layout' );
	$single = ! $quick || in_array( $layout, array( 'buynow_only', 'cart_only' ), true );
	echo '<div class="kk-card-actions kk-card-actions--' . esc_attr( $layout ) . ( $single ? ' kk-card-actions--single' : '' ) . '">';
	if ( ! $quick || 'buynow_only' !== $layout ) {
		woocommerce_template_loop_add_to_cart();
	}
	if ( $quick && 'cart_only' !== $layout ) {
		echo kk_buy_now_link( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_buy_now_link()
	}
	echo '</div>';
}

if ( kk_opt( 'buynow_single' ) ) {
	add_action( 'woocommerce_after_add_to_cart_button', function () {
		global $product;
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() || ! ( $product->is_type( 'simple' ) || $product->is_type( 'variable' ) ) ) {
			return;
		}
		printf( '<button type="submit" name="kk_buy_now" value="%1$d" class="button alt kk-buy-now kk-buy-now--single">%2$s</button>', absint( $product->get_id() ), esc_html( kk_opt( 'buynow_text' ) ) );
	} );
}

add_shortcode( 'kk_buy_now', function ( $atts ) {
	$a       = shortcode_atts( array( 'id' => 0, 'text' => '', 'class' => '' ), $atts, 'kk_buy_now' );
	$product = wc_get_product( absint( $a['id'] ) ? absint( $a['id'] ) : get_the_ID() );
	return $product && kk_can_quick_buy( $product ) ? kk_buy_now_link( $product, $a['text'], 'kk-buy-now--standalone ' . $a['class'] ) : ''; // variable products: only with a default variation
} );
