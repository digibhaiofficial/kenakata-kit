<?php
/**
 * Fake-order protection:
 *  - The checkout phone must pass the "Phone number check" (default: exactly 11 digits).
 *  - The same phone (and optionally the same IP) cannot place another order within X minutes.
 */

defined( 'ABSPATH' ) || exit;

if ( ! kk_opt( 'co_throttle_enable' ) ) {
	return;
}

/** Latest qualifying order for a phone / IP within the window, or null. */
function kk_recent_order( $phone, $ip, $minutes ) {
	$since    = time() - $minutes * MINUTE_IN_SECONDS;
	$statuses = array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-cancelled', 'wc-failed', 'wc-refunded', 'wc-checkout-draft' ) );
	$base     = array( 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC', 'status' => $statuses, 'date_created' => '>' . $since );
	$latest   = null;

	if ( $phone ) {
		foreach ( array_unique( array( $phone, '+88' . $phone, '88' . $phone ) ) as $variant ) {
			foreach ( wc_get_orders( $base + array( 'billing_phone' => $variant ) ) as $o ) {
				$same = kk_clean_phone( $o->get_billing_phone() ) === $phone || kk_normalize_phone( $o->get_billing_phone() ) === kk_normalize_phone( $phone );
				if ( $same && ( ! $latest || $o->get_date_created() > $latest->get_date_created() ) ) {
					$latest = $o;
				}
			}
		}
	}
	if ( $ip && kk_opt( 'co_throttle_ip' ) ) {
		foreach ( wc_get_orders( $base + array( 'customer_ip_address' => $ip ) ) as $o ) {
			if ( $o->get_customer_ip_address() === $ip && ( ! $latest || $o->get_date_created() > $latest->get_date_created() ) ) {
				$latest = $o;
			}
		}
	}
	return $latest;
}

add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	if ( $errors->has_errors() ) {
		return; // let the customer fix other problems first
	}
	if ( kk_opt( 'co_throttle_skip_admin' ) && current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$minutes = max( 1, absint( kk_opt( 'co_throttle_minutes' ) ) );
	$phone   = kk_clean_phone( isset( $data['billing_phone'] ) ? $data['billing_phone'] : '' );
	$ip      = WC_Geolocation::get_ip_address();
	$recent  = kk_recent_order( $phone, $ip, $minutes );
	if ( ! $recent ) {
		return;
	}
	$left = max( 1, (int) ceil( ( $recent->get_date_created()->getTimestamp() + $minutes * MINUTE_IN_SECONDS - time() ) / MINUTE_IN_SECONDS ) );
	$msg  = str_replace( array( '{minutes}', '{order}' ), array( $left, $recent->get_order_number() ), kk_opt( 'co_throttle_message' ) );
	$errors->add( 'kk_throttle', $msg );
}, 20, 2 );
