<?php
/**
 * My account page: [kk_my_account] shortcode (also used by the "My Account" Elementor widget).
 * Adds a friendlier dashboard (greeting, latest order, quick-link cards), optional menu icons,
 * a short line under the login title, and lets you hide menu items (e.g. Downloads).
 */

defined( 'ABSPATH' ) || exit;

/** Settings of the account box being rendered right now (null outside it). */
function kk_account_current( $set = null ) {
	static $current = null;
	if ( null !== $set ) {
		$current = false === $set ? null : $set;
	}
	return $current;
}

function kk_account_defaults() {
	return array(
		'cards'       => 'yes',
		'icons'       => 'yes',
		'menu'        => 'side',      // side | top
		'hide'        => 'downloads', // comma-separated menu item keys
		'login_intro' => __( 'Sign in with your phone number or email and your password.', 'kenakata-kit' ),
	);
}

/** Menu items a shop owner may want to hide. */
function kk_account_menu_choices() {
	return array(
		'orders'          => __( 'Orders', 'kenakata-kit' ),
		'downloads'       => __( 'Downloads', 'kenakata-kit' ),
		'edit-address'    => __( 'Addresses', 'kenakata-kit' ),
		'payment-methods' => __( 'Payment methods', 'kenakata-kit' ),
		'edit-account'    => __( 'Account details', 'kenakata-kit' ),
	);
}

function kk_my_account_render( $a ) {
	$a = wp_parse_args( $a, kk_account_defaults() );
	if ( ! function_exists( 'WC' ) ) {
		return '';
	}
	$classes = array( 'kk-account', is_user_logged_in() ? 'is-member' : 'is-guest', 'kk-account--menu-' . ( 'top' === $a['menu'] ? 'top' : 'side' ) );
	if ( 'yes' === $a['icons'] ) {
		$classes[] = 'kk-account--icons';
	}
	kk_account_current( $a );
	$html = do_shortcode( '[woocommerce_my_account]' );
	kk_account_current( false );
	return kk_need_css( 'account' ) . '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">' . $html . '</div>';
}

add_shortcode( 'kk_my_account', function ( $atts ) {
	return kk_my_account_render( shortcode_atts( kk_account_defaults(), $atts, 'kk_my_account' ) );
} );

// Hide chosen menu items (only inside our account box).
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	$a = kk_account_current();
	if ( $a ) {
		foreach ( kk_list( $a['hide'] ) as $key ) {
			if ( 'dashboard' !== $key && 'customer-logout' !== $key ) {
				unset( $items[ $key ] );
			}
		}
	}
	return $items;
}, 20 );

// Short line under the "Login" title.
add_action( 'woocommerce_login_form_start', function () {
	$a = kk_account_current();
	if ( $a && '' !== trim( (string) $a['login_intro'] ) ) {
		echo '<p class="kk-acc-login-intro">' . esc_html( $a['login_intro'] ) . '</p>';
	}
} );

/* Lost password / reset password: a title, clearer text and a way back to the login card. */
function kk_account_back_link() {
	return '<p class="kk-acc-back"><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">&larr; ' . esc_html__( 'Back to login', 'kenakata-kit' ) . '</a></p>';
}
add_action( 'woocommerce_before_lost_password_form', function () {
	if ( kk_account_current() ) {
		echo '<h2 class="kk-acc-form-title">' . esc_html__( 'Forgot your password?', 'kenakata-kit' ) . '</h2>';
	}
} );
add_filter( 'woocommerce_lost_password_message', function ( $message ) {
	return kk_account_current() ? __( 'Enter the email address or username of your account. We will email you a link to set a new password.', 'kenakata-kit' ) : $message;
} );
add_action( 'woocommerce_after_lost_password_form', function () {
	if ( kk_account_current() ) {
		echo kk_account_back_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
} );
add_action( 'woocommerce_before_lost_password_confirmation_message', function () {
	if ( kk_account_current() ) {
		echo '<h2 class="kk-acc-form-title">' . esc_html__( 'Check your email', 'kenakata-kit' ) . '</h2>';
	}
} );
add_action( 'woocommerce_after_lost_password_confirmation_message', function () {
	if ( ! kk_account_current() ) {
		return;
	}
	$wa = function_exists( 'kk_whatsapp_url' ) ? kk_whatsapp_url( '' ) : '';
	echo '<p class="kk-acc-note">' . esc_html__( 'No email after a few minutes? Check your spam folder.', 'kenakata-kit' )
		. ( $wa ? ' <a href="' . kk_esc_link( $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'Or message us on WhatsApp.', 'kenakata-kit' ) . '</a>' : '' ) . '</p>'
		. kk_account_back_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
} );
add_action( 'woocommerce_before_reset_password_form', function () {
	if ( kk_account_current() ) {
		echo '<h2 class="kk-acc-form-title">' . esc_html__( 'Set a new password', 'kenakata-kit' ) . '</h2>';
	}
} );

/** Coloured status pill for an order. */
function kk_account_status_pill( $order ) {
	$status = $order->get_status();
	$tone   = in_array( $status, array( 'completed' ), true ) ? 'ok' : ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ? 'bad' : ( in_array( $status, array( 'on-hold', 'pending' ), true ) ? 'wait' : 'info' ) );
	return '<span class="kk-acc-pill kk-acc-pill--' . esc_attr( $tone ) . '">' . esc_html( wc_get_order_status_name( $status ) ) . '</span>';
}

// Dashboard: greeting, latest order and quick-link cards (replaces WooCommerce's two text paragraphs visually).
add_action( 'woocommerce_account_dashboard', function () {
	$a = kk_account_current();
	if ( ! $a || 'yes' !== $a['cards'] || ! is_user_logged_in() ) {
		return;
	}
	$user  = wp_get_current_user();
	$name  = $user->first_name ? $user->first_name : $user->display_name;
	$count = wc_get_customer_order_count( $user->ID );
	$last  = wc_get_orders( array( 'customer_id' => $user->ID, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
	$last  = $last ? $last[0] : null;
	$track = function_exists( 'kk_page_url' ) ? kk_page_url( 'tr_page', 'track-order' ) : '';
	$hide  = kk_list( $a['hide'] );

	$out  = '<div class="kk-acc-dash">';
	$out .= '<div class="kk-acc-hello"><span class="kk-acc-avatar" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( wp_strip_all_tags( $name ), 0, 1 ) ) ) . '</span><div class="kk-acc-hello__text">'
		/* translators: %s: customer name */
		. '<h2>' . esc_html( sprintf( __( 'Hello, %s', 'kenakata-kit' ), $name ) ) . '</h2>'
		. '<p>' . esc_html__( 'Welcome back! Your orders, addresses and account details are all here.', 'kenakata-kit' ) . '</p></div>'
		. '<a class="kk-acc-logout" href="' . esc_url( wc_logout_url() ) . '">' . esc_html__( 'Log out', 'kenakata-kit' ) . '</a></div>';

	if ( $last ) {
		$out .= '<div class="kk-acc-last"><div class="kk-acc-last__head"><span class="kk-acc-last__label">' . esc_html__( 'Latest order', 'kenakata-kit' ) . '</span>' . kk_account_status_pill( $last ) . '</div>'
			. '<div class="kk-acc-last__body"><strong>#' . esc_html( $last->get_order_number() ) . '</strong>'
			. '<span>' . esc_html( wc_format_datetime( $last->get_date_created() ) ) . '</span>'
			. '<span>' . wp_kses_post( $last->get_formatted_order_total() ) . '</span></div>'
			. '<div class="kk-acc-last__actions"><a class="button" href="' . esc_url( $last->get_view_order_url() ) . '">' . esc_html__( 'View order', 'kenakata-kit' ) . '</a>'
			. ( $track ? '<a class="button kk-acc-btn-ghost" href="' . esc_url( $track ) . '">' . esc_html__( 'Track order', 'kenakata-kit' ) . '</a>' : '' ) . '</div></div>';
	}

	$cards = array();
	if ( ! in_array( 'orders', $hide, true ) ) {
		/* translators: %s: number of orders */
		$cards[] = array( 'box', __( 'My orders', 'kenakata-kit' ), $count ? sprintf( _n( '%s order', '%s orders', $count, 'kenakata-kit' ), number_format_i18n( $count ) ) : __( 'No orders yet', 'kenakata-kit' ), wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) );
	}
	if ( $track ) {
		$cards[] = array( 'truck', __( 'Track an order', 'kenakata-kit' ), __( 'See where your parcel is', 'kenakata-kit' ), $track );
	}
	if ( ! in_array( 'edit-address', $hide, true ) ) {
		$cards[] = array( 'location', __( 'Addresses', 'kenakata-kit' ), __( 'Delivery address for checkout', 'kenakata-kit' ), wc_get_endpoint_url( 'edit-address', '', wc_get_page_permalink( 'myaccount' ) ) );
	}
	if ( ! in_array( 'edit-account', $hide, true ) ) {
		$cards[] = array( 'user', __( 'Account details', 'kenakata-kit' ), __( 'Name, email and password', 'kenakata-kit' ), wc_get_endpoint_url( 'edit-account', '', wc_get_page_permalink( 'myaccount' ) ) );
	}
	$cards[] = array( 'bag', __( 'Continue shopping', 'kenakata-kit' ), __( 'Find your next favourite', 'kenakata-kit' ), wc_get_page_permalink( 'shop' ) );
	$wa = function_exists( 'kk_whatsapp_url' ) ? kk_whatsapp_url( '' ) : '';
	if ( $wa ) {
		$cards[] = array( 'whatsapp', __( 'Need help?', 'kenakata-kit' ), __( 'Chat with us on WhatsApp', 'kenakata-kit' ), $wa );
	}
	$out .= '<ul class="kk-acc-cards">';
	foreach ( $cards as $c ) {
		$out .= '<li><a class="kk-acc-card" href="' . kk_esc_link( $c[3] ) . '"' . ( 'whatsapp' === $c[0] ? ' target="_blank" rel="noopener"' : '' ) . '><span class="kk-acc-card__icon">' . kk_icon( $c[0] ) . '</span><span class="kk-acc-card__text"><strong>' . esc_html( $c[1] ) . '</strong><span>' . esc_html( $c[2] ) . '</span></span></a></li>';
	}
	$out .= '</ul></div>';
	echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
} );
