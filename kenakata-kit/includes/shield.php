<?php
/**
 * Fake-order shield (3.17). Both parts are off until turned on (Kenakata Kit → Fake-order protection).
 *
 * 1. Blacklist: phone numbers you blocked (from an order with one tap, or typed in the settings). At checkout they are
 *    refused, or may only pay in advance (bKash / Nagad / Rocket by hand), as you choose.
 * 2. Returns history: a customer whose earlier orders here were mostly cancelled or returned (and, when you have
 *    looked them up, the saved Courier check result) must pay in advance; cash on delivery is hidden for them.
 *
 * Everything is checked on your own site from your own orders: no outside service is asked at checkout, nothing runs
 * in the background. The answer for a phone number is kept for 10 minutes so the checkout stays fast.
 *
 * @package KenakataKit
 */

defined( 'ABSPATH' ) || exit;

/** Blocked numbers: array( phone => array( 'note' => …, 'at' => time, 'by' => name ) ). */
function kk_blacklist() {
	$list = get_option( 'kk_blacklist', array() );
	$list = is_array( $list ) ? $list : array();
	// Numbers typed in the settings (one per line, anything after the number is a note).
	foreach ( preg_split( '/\R/', (string) kk_opt( 'gd_blacklist_text' ) ) as $line ) {
		$phone = kk_normalize_phone( (string) strtok( trim( $line ), " \t,;" ) );
		if ( $phone && ! isset( $list[ $phone ] ) ) {
			$list[ $phone ] = array( 'note' => trim( (string) substr( trim( $line ), strlen( (string) strtok( trim( $line ), " \t,;" ) ) ) ), 'at' => 0, 'by' => '' );
		}
	}
	return $list;
}

/** Add (or with $remove, take off) a number. Returns the phone or WP_Error. */
function kk_blacklist_set( $phone, $note = '', $remove = false ) {
	$phone = kk_normalize_phone( (string) $phone );
	if ( ! $phone ) {
		return new WP_Error( 'kk_bl_phone', __( 'This is not a valid Bangladeshi mobile number.', 'kenakata-kit' ), array( 'status' => 400 ) );
	}
	$list = get_option( 'kk_blacklist', array() );
	$list = is_array( $list ) ? $list : array();
	if ( $remove ) {
		unset( $list[ $phone ] );
		// Also from the typed list in the settings.
		$lines = array_filter( preg_split( '/\R/', (string) kk_opt( 'gd_blacklist_text' ) ), function ( $l ) use ( $phone ) {
			return kk_normalize_phone( (string) strtok( trim( $l ), " \t,;" ) ) !== $phone;
		} );
		$s                      = (array) get_option( 'kk_settings', array() );
		$s['gd_blacklist_text'] = implode( "\n", $lines );
		if ( function_exists( 'kk_update_settings' ) ) {
			kk_update_settings( $s );
		} else {
			update_option( 'kk_settings', $s );
		}
	} else {
		$user           = wp_get_current_user();
		$list[ $phone ] = array( 'note' => sanitize_text_field( (string) $note ), 'at' => time(), 'by' => $user && $user->exists() ? $user->display_name : '' );
	}
	update_option( 'kk_blacklist', $list, false );
	delete_transient( 'kk_risk_' . md5( $phone ) );
	return $phone;
}

function kk_is_blacklisted( $phone ) {
	$phone = kk_normalize_phone( (string) $phone );
	return $phone && array_key_exists( $phone, kk_blacklist() );
}

/**
 * Delivery record of a phone number from this shop's orders (and a saved Courier check, if any).
 * Returns array( total, done, failed, rate (0–100 or null), courier_rate (or null) ). Kept 10 minutes.
 */
function kk_risk_record( $phone ) {
	$phone = kk_normalize_phone( (string) $phone );
	if ( ! $phone ) {
		return null;
	}
	$key  = 'kk_risk_' . md5( $phone );
	$save = get_transient( $key );
	if ( is_array( $save ) ) {
		return $save;
	}
	$done   = 0;
	$failed = 0;
	$cc     = null;
	foreach ( kk_orders_by_phone( $phone, 50 ) as $o ) {
		if ( $o->has_status( 'completed' ) ) {
			$done++;
		} elseif ( $o->has_status( array( 'cancelled', 'failed', 'refunded', 'returned' ) ) ) {
			$failed++;
		}
		$c = $o->get_meta( '_kk_cc' );
		if ( null === $cc && is_array( $c ) && isset( $c['rate'] ) && null !== $c['rate'] && (int) ( $c['total'] ?? 0 ) > 0 ) {
			$cc = array( 'rate' => (int) $c['rate'], 'total' => (int) $c['total'] ); // newest saved Courier check
		}
	}
	$rec = array(
		'total'        => $done + $failed,
		'done'         => $done,
		'failed'       => $failed,
		'rate'         => $done + $failed > 0 ? (int) round( 100 * $done / ( $done + $failed ) ) : null,
		'courier_rate' => $cc ? $cc['rate'] : null,
		'courier_total'=> $cc ? $cc['total'] : 0,
	);
	set_transient( $key, $rec, 10 * MINUTE_IN_SECONDS );
	return $rec;
}

/**
 * What the shield says about a phone at checkout: '' (fine), 'advance' (pay first) or 'block'. Second value: the reason
 * shown to the customer.
 */
function kk_shield_verdict( $phone ) {
	$phone = kk_normalize_phone( (string) $phone );
	if ( ! $phone || ( kk_opt( 'gd_skip_staff' ) && current_user_can( 'edit_shop_orders' ) ) ) {
		return array( '', '' );
	}
	if ( kk_opt( 'gd_blacklist_enable' ) && kk_is_blacklisted( $phone ) ) {
		return array( 'advance' === kk_opt( 'gd_blacklist_action' ) ? 'advance' : 'block', (string) kk_opt( 'gd_blacklist_msg' ) );
	}
	if ( kk_opt( 'gd_risk_enable' ) ) {
		$r     = kk_risk_record( $phone );
		$min   = max( 1, absint( kk_opt( 'gd_risk_min' ) ) );
		$limit = absint( kk_opt( 'gd_risk_rate' ) );
		$own   = $r && $r['total'] >= $min && null !== $r['rate'] && $r['rate'] < $limit;
		$cour  = $r && null !== $r['courier_rate'] && $r['courier_total'] >= $min && $r['courier_rate'] < $limit;
		if ( $own || $cour ) {
			return array( 'advance', (string) kk_opt( 'gd_risk_msg' ) );
		}
	}
	return array( '', '' );
}

/** Can the customer pay in advance at all (mobile payment on, with a number)? */
function kk_shield_can_prepay() {
	return function_exists( 'kk_mp_wallets' ) && kk_opt( 'mp_enable' ) && kk_mp_wallets();
}

/** The phone typed at checkout so far (the checkout sends it with every update). */
function kk_shield_checkout_phone() {
	if ( isset( $_POST['billing_phone'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- read only; WooCommerce checks the checkout nonce
		return sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- the checkout update sends the whole form
		parse_str( wp_unslash( $_POST['post_data'] ), $form ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $form['billing_phone'] ) && is_string( $form['billing_phone'] ) ) {
			return sanitize_text_field( $form['billing_phone'] );
		}
	}
	return function_exists( 'WC' ) && WC()->customer ? (string) WC()->customer->get_billing_phone() : '';
}

if ( kk_opt( 'gd_blacklist_enable' ) || kk_opt( 'gd_risk_enable' ) ) {
	// "Pay first": cash on delivery is not offered (only when another way to pay exists).
	add_filter( 'woocommerce_available_payment_gateways', function ( $gateways ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $gateways;
		}
		if ( ! function_exists( 'is_checkout' ) || ! ( is_checkout() || wp_doing_ajax() ) ) {
			return $gateways;
		}
		list( $v ) = kk_shield_verdict( kk_shield_checkout_phone() );
		if ( 'advance' === $v && isset( $gateways['cod'] ) && count( $gateways ) > 1 ) {
			unset( $gateways['cod'] );
		}
		return $gateways;
	}, 50 );

	// Payment choices follow the phone number: refresh them when it changes.
	add_action( 'wp_enqueue_scripts', function () {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
			wp_add_inline_script( 'wc-checkout', 'jQuery(function($){$(document.body).on("change","#billing_phone",function(){$(document.body).trigger("update_checkout");});});' );
		}
	}, 30 );

	// A short note next to the payment choices, so the customer knows why cash on delivery is missing. (This hook is
	// inside the payment box, which WooCommerce redraws when the phone changes.)
	add_action( 'woocommerce_review_order_before_submit', function () {
		list( $v, $msg ) = kk_shield_verdict( kk_shield_checkout_phone() );
		if ( 'advance' === $v && '' !== trim( $msg ) && kk_shield_can_prepay() ) {
			echo '<div class="woocommerce-info kk-shield-note" role="status">' . esc_html( $msg ) . '</div>';
		}
	} );

	// The final word when the order is placed (the payment list could be old or changed by hand).
	add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
		if ( $errors->has_errors() ) {
			return;
		}
		list( $v, $msg ) = kk_shield_verdict( isset( $data['billing_phone'] ) ? $data['billing_phone'] : '' );
		$method          = isset( $data['payment_method'] ) ? (string) $data['payment_method'] : '';
		if ( 'block' === $v || ( 'advance' === $v && ! kk_shield_can_prepay() ) ) {
			$errors->add( 'kk_shield', '' !== trim( $msg ) ? $msg : __( 'Sorry, we cannot take this order online. Please call us.', 'kenakata-kit' ) );
			kk_shield_log( $data, 'block' );
		} elseif ( 'advance' === $v && 'cod' === $method ) {
			$errors->add( 'kk_shield', '' !== trim( $msg ) ? $msg : __( 'Please pay in advance with bKash, Nagad or Rocket.', 'kenakata-kit' ) );
			kk_shield_log( $data, 'advance' );
		}
	}, 30, 2 );
}

/** Count of refused checkouts (shown in the settings, for peace of mind). */
function kk_shield_log( $data, $kind ) {
	$n          = (array) get_option( 'kk_shield_count', array() );
	$n[ $kind ] = (int) ( $n[ $kind ] ?? 0 ) + 1;
	update_option( 'kk_shield_count', $n, false );
}

/* =========================================================================
 * Block / unblock from an order: WooCommerce order screen (and the store dashboard via REST, store-dashboard-api.php)
 * ====================================================================== */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_orders' ) || ! kk_opt( 'gd_blacklist_enable' ) ) {
		return;
	}
	$phone = kk_normalize_phone( (string) $order->get_billing_phone() );
	if ( ! $phone ) {
		return;
	}
	$on  = kk_is_blacklisted( $phone );
	$url = wp_nonce_url( add_query_arg( array( 'action' => 'kk_blacklist', 'order' => $order->get_id(), 'do' => $on ? 'remove' : 'add' ), admin_url( 'admin-post.php' ) ), 'kk_blacklist_' . $order->get_id() );
	echo '<p class="kk-bl">' . ( $on ? '<strong style="color:#b32d2e">' . esc_html__( 'This number is blocked.', 'kenakata-kit' ) . '</strong> ' : '' )
		. '<a class="button' . ( $on ? '' : ' button-link-delete' ) . '" href="' . esc_url( $url ) . '"' . ( $on ? '' : ' onclick="return confirm(\'' . esc_js( __( 'Block this phone number for future orders?', 'kenakata-kit' ) ) . '\')"' ) . '>'
		. esc_html( $on ? __( 'Unblock this number', 'kenakata-kit' ) : __( 'Block this number', 'kenakata-kit' ) ) . '</a></p>';
} );

add_action( 'admin_post_kk_blacklist', function () {
	$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
	if ( ! current_user_can( 'edit_shop_orders' ) || ! check_admin_referer( 'kk_blacklist_' . $id ) ) {
		wp_die( esc_html__( 'This link has expired. Go back and open it again.', 'kenakata-kit' ), '', array( 'response' => 403 ) );
	}
	$order = wc_get_order( $id );
	if ( $order ) {
		$remove = isset( $_GET['do'] ) && 'remove' === $_GET['do'];
		/* translators: %s: order number */
		kk_blacklist_set( $order->get_billing_phone(), sprintf( __( 'Order #%s', 'kenakata-kit' ), $order->get_order_number() ), $remove );
		$user = wp_get_current_user();
		/* translators: %s: user name */
		$order->add_order_note( sprintf( $remove ? __( 'Phone number unblocked by %s.', 'kenakata-kit' ) : __( 'Phone number blocked by %s.', 'kenakata-kit' ), $user->display_name ) );
	}
	wp_safe_redirect( $order ? $order->get_edit_order_url() : admin_url() );
	exit;
} );
