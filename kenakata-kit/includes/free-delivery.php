<?php
/**
 * Free delivery above an amount ("Spend ৳1,250 more to unlock free delivery").
 *  - When the cart subtotal reaches the amount, every delivery area costs 0.
 *  - A progress bar on product pages, cart, checkout and side cart; it updates when products are added.
 *  - Elementor widget "Free Delivery Bar" (and shortcode [kk_free_delivery]) to place it anywhere.
 */

defined( 'ABSPATH' ) || exit;

/** Cart amount that counts toward free delivery (products after coupons, as shown to the customer). */
function kk_fd_cart_total() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return 0.0;
	}
	$cart  = WC()->cart;
	$total = 0.0;
	foreach ( $cart->get_cart() as $item ) {
		if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
			$total += (float) wc_get_price_to_display( $item['data'], array( 'qty' => (float) $item['quantity'] ) );
		}
	}
	$disc  = (float) $cart->get_discount_total();
	if ( $cart->display_prices_including_tax() ) {
		$disc += (float) $cart->get_discount_tax();
	}
	return max( 0.0, $total - $disc );
}

function kk_fd_amount() {
	return max( 0, (float) kk_opt( 'fd_amount' ) );
}

function kk_fd_active() {
	return kk_opt( 'fd_enable' ) && kk_fd_amount() > 0;
}

/* Delivery costs nothing once the amount is reached. */
add_filter( 'woocommerce_package_rates', function ( $rates ) {
	if ( ! kk_fd_active() || kk_fd_cart_total() < kk_fd_amount() ) {
		return $rates;
	}
	foreach ( $rates as $rate ) {
		$rate->set_cost( 0 );
		$taxes = $rate->get_taxes();
		if ( $taxes ) {
			$rate->set_taxes( array_map( '__return_zero', $taxes ) );
		}
	}
	return $rates;
}, 50 );

/* Shipping rates are cached per cart contents; make sure they are recalculated when the subtotal crosses the line. */
add_filter( 'woocommerce_cart_shipping_packages', function ( $packages ) {
	if ( kk_fd_active() ) {
		foreach ( $packages as $i => $p ) {
			$packages[ $i ]['kk_free_delivery'] = kk_fd_cart_total() >= kk_fd_amount() ? 1 : 0;
		}
	}
	return $packages;
} );

/** The progress bar markup (based on the cart). */
function kk_free_delivery_html( $atts = array() ) {
	if ( ! kk_fd_active() ) {
		return '';
	}
	$a      = shortcode_atts( array( 'class' => '' ), $atts, 'kk_free_delivery' );
	$goal   = kk_fd_amount();
	$have   = kk_fd_cart_total();
	$left   = max( 0, $goal - $have );
	$pct    = $goal > 0 ? min( 100, round( $have / $goal * 100 ) ) : 100;
	$done   = $left <= 0;
	$text   = $done ? (string) kk_opt( 'fd_done' ) : str_replace( array( '{remaining}', '{amount}' ), array( wc_price( $left ), wc_price( $goal ) ), esc_html( (string) kk_opt( 'fd_text' ) ) );
	$cls    = trim( 'kk-fd' . ( $done ? ' is-done' : '' ) . ' ' . $a['class'] );
	return '<div class="' . esc_attr( $cls ) . '" role="status" aria-live="polite">'
		. '<p class="kk-fd__text">' . kk_icon( $done ? 'check' : 'truck' ) . '<span>' . wp_kses_post( $done ? esc_html( $text ) : $text ) . '</span></p>'
		. '<div class="kk-fd__track" aria-hidden="true"><span class="kk-fd__bar" style="width:' . esc_attr( $pct ) . '%"></span></div>'
		. '</div>';
}
add_shortcode( 'kk_free_delivery', 'kk_free_delivery_html' );

/* Keep every bar on the page up to date after "Add to cart" (WooCommerce cart fragments). */
add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	if ( kk_fd_active() ) {
		$fragments['div.kk-fd'] = kk_free_delivery_html();
	}
	return $fragments;
} );

/* Automatic places (each can be switched off; Elementor pages can use the widget instead). */
add_action( 'woocommerce_single_product_summary', function () {
	if ( kk_opt( 'fd_product' ) ) {
		echo kk_free_delivery_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
}, 31 );
add_action( 'woocommerce_before_cart', function () {
	if ( kk_opt( 'fd_cart' ) ) {
		echo kk_free_delivery_html( array( 'class' => 'kk-fd--wide' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
} );
add_action( 'woocommerce_before_checkout_form', function () {
	if ( kk_opt( 'fd_checkout' ) ) {
		echo kk_free_delivery_html( array( 'class' => 'kk-fd--wide' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
}, 5 );
add_action( 'woocommerce_before_mini_cart_contents', function () {
	if ( kk_opt( 'fd_drawer' ) && ! WC()->cart->is_empty() ) {
		echo kk_free_delivery_html( array( 'class' => 'kk-fd--drawer' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
} );
/* Product cards and the product page load the cart fragments script so the bar can refresh. */
add_action( 'wp_enqueue_scripts', function () {
	if ( kk_fd_active() && function_exists( 'is_product' ) && is_product() ) {
		wp_enqueue_script( 'wc-cart-fragments' );
	}
}, 30 );
