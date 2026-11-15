<?php
/**
 * Courier booking: the code that talks to couriers. Loaded only when a booking, a status check or a history check is
 * asked for (see kk_courier_load() in courier.php), never on ordinary shop pages.
 *
 * @package KenakataKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * Talking to a courier
 * ====================================================================== */

/** Base address of a courier's API (filterable, e.g. for a test server). */
function kk_courier_base_url( $provider ) {
	$urls = array(
		'steadfast' => 'https://portal.packzy.com/api/v1',
		'bdcourier' => 'https://api.bdcourier.com',
		'pathao'    => kk_opt( 'courier_pathao_sandbox' ) ? 'https://courier-api-sandbox.pathao.com' : 'https://api-hermes.pathao.com',
	);
	$url  = isset( $urls[ $provider ] ) ? $urls[ $provider ] : '';
	return untrailingslashit( (string) apply_filters( 'kk_courier_base_url', $url, $provider ) );
}

/** Headers that identify the shop to the courier. */
function kk_courier_headers( $provider ) {
	$headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' );
	if ( 'steadfast' === $provider ) {
		$headers['Api-Key']    = kk_courier_secret( 'courier_steadfast_api_key' );
		$headers['Secret-Key'] = kk_courier_secret( 'courier_steadfast_secret_key' );
	} elseif ( 'bdcourier' === $provider ) {
		$headers['Authorization'] = 'Bearer ' . kk_courier_secret( 'courier_bdcourier_token' );
	}
	return $headers;
}

/**
 * One call to a courier. Returns array( http code, decoded body ) or a WP_Error with a plain-language message.
 * Short time-out so a slow courier never freezes the shop. Credentials are never logged or returned.
 */
function kk_courier_http( $provider, $method, $path, $body = null, array $extra_headers = array() ) {
	$label = kk_courier_label( $provider );
	$base  = kk_courier_base_url( $provider );
	if ( '' === $base ) {
		return new WP_Error( 'kk_courier_unknown', __( 'This courier is not supported.', 'kenakata-kit' ), array( 'status' => 400 ) );
	}
	$args = array(
		'method'  => $method,
		'timeout' => 'bdcourier' === $provider ? 15 : 12,
		'headers' => array_merge( kk_courier_headers( $provider ), $extra_headers ),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	$res = wp_remote_request( $base . $path, $args );
	if ( is_wp_error( $res ) ) {
		return new WP_Error(
			'kk_courier_unreachable',
			/* translators: %s: courier name */
			sprintf( __( 'Could not reach %s or it did not answer in time. Check your %s account before sending again: the parcel may already have been created.', 'kenakata-kit' ), $label, $label ),
			array( 'status' => 502 )
		);
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	return array( $code, is_array( $data ) ? $data : array() );
}

/** The most useful sentence from a courier's error answer. */
function kk_courier_error_text( $data ) {
	$parts = array();
	if ( ! empty( $data['message'] ) && is_string( $data['message'] ) ) {
		$parts[] = $data['message'];
	}
	if ( ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
		foreach ( $data['errors'] as $e ) {
			foreach ( (array) $e as $line ) {
				if ( is_string( $line ) ) {
					$parts[] = $line;
				}
			}
		}
	}
	$text = sanitize_text_field( implode( ' ', array_unique( $parts ) ) );
	return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 240 ) : substr( $text, 0, 240 );
}

/* =========================================================================
 * Steadfast adapter
 * ====================================================================== */

/** Wording for Steadfast delivery statuses. */
function kk_courier_status_label( $provider, $code ) {
	$code = (string) $code;
	if ( 'steadfast' === $provider ) {
		$map = array(
			'pending'                          => __( 'Waiting for the courier', 'kenakata-kit' ),
			'in_review'                        => __( 'Being reviewed by the courier', 'kenakata-kit' ),
			'hold'                             => __( 'On hold', 'kenakata-kit' ),
			'delivered'                        => __( 'Delivered', 'kenakata-kit' ),
			'partial_delivered'                => __( 'Partly delivered', 'kenakata-kit' ),
			'cancelled'                        => __( 'Cancelled', 'kenakata-kit' ),
			'delivered_approval_pending'       => __( 'Delivered (waiting for approval)', 'kenakata-kit' ),
			'partial_delivered_approval_pending' => __( 'Partly delivered (waiting for approval)', 'kenakata-kit' ),
			'cancelled_approval_pending'       => __( 'Cancelled (waiting for approval)', 'kenakata-kit' ),
			'unknown_approval_pending'         => __( 'Waiting for approval', 'kenakata-kit' ),
			'unknown'                          => __( 'Unknown', 'kenakata-kit' ),
		);
		if ( isset( $map[ $code ] ) ) {
			return $map[ $code ];
		}
	}
	return '' === $code ? '' : ucfirst( str_replace( '_', ' ', $code ) );
}

function kk_courier_tracking_url( $provider, $tracking_code, $phone = '' ) {
	$url = '';
	if ( 'steadfast' === $provider && '' !== $tracking_code ) {
		$url = 'https://steadfast.com.bd/t/' . rawurlencode( $tracking_code );
	} elseif ( 'pathao' === $provider && '' !== $tracking_code ) {
		$url = add_query_arg( array( 'consignment_id' => $tracking_code, 'phone' => $phone ), 'https://merchant.pathao.com/tracking' );
	}
	return (string) apply_filters( 'kk_courier_tracking_url', $url, $provider, $tracking_code, $phone );
}

/** Create a parcel. $d: invoice, name, phone, address, cod_amount, note. Returns array( consignment_id, tracking_code, status ) or WP_Error. */
function kk_courier_steadfast_book( array $d ) {
	$res = kk_courier_http( 'steadfast', 'POST', '/create_order', array(
		'invoice'           => $d['invoice'],
		'recipient_name'    => $d['name'],
		'recipient_phone'   => $d['phone'],
		'recipient_address' => $d['address'],
		'cod_amount'        => $d['cod_amount'],
		'note'              => $d['note'],
	) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	$c = isset( $body['consignment'] ) && is_array( $body['consignment'] ) ? $body['consignment'] : array();
	if ( $code >= 200 && $code < 300 && ! empty( $c['consignment_id'] ) ) {
		return array(
			'consignment_id' => sanitize_text_field( (string) $c['consignment_id'] ),
			'tracking_code'  => sanitize_text_field( (string) ( $c['tracking_code'] ?? '' ) ),
			'status'         => sanitize_key( (string) ( $c['status'] ?? 'in_review' ) ),
		);
	}
	$why = kk_courier_error_text( $body );
	if ( $code >= 200 && $code < 300 ) {
		return new WP_Error( 'kk_courier_bad_answer', __( 'Steadfast sent an answer we could not read. Check your Steadfast account before sending again: the parcel may already have been created.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	if ( 401 === $code || 403 === $code ) {
		return new WP_Error( 'kk_courier_auth', __( 'Steadfast did not accept the API keys. An administrator should check them in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	/* translators: 1: courier name 2: reason given by the courier */
	return new WP_Error( 'kk_courier_rejected', sprintf( __( '%1$s did not accept the order. %2$s', 'kenakata-kit' ), 'Steadfast', $why ), array( 'status' => 422 ) );
}

/** Current delivery status of a booked parcel. Returns the status code or WP_Error. */
function kk_courier_steadfast_status( $consignment_id ) {
	$res = kk_courier_http( 'steadfast', 'GET', '/status_by_cid/' . rawurlencode( (string) $consignment_id ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	if ( $code >= 200 && $code < 300 && ! empty( $body['delivery_status'] ) ) {
		return sanitize_key( (string) $body['delivery_status'] );
	}
	if ( 401 === $code || 403 === $code ) {
		return new WP_Error( 'kk_courier_auth', __( 'Steadfast did not accept the API keys. An administrator should check them in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	return new WP_Error( 'kk_courier_no_status', __( 'The courier could not tell the status right now. Please try again later.', 'kenakata-kit' ), array( 'status' => 502 ) );
}



/* =========================================================================
 * Pathao adapter
 * ====================================================================== */

/** A login token for Pathao's API, remembered until shortly before it expires. Returns the token or WP_Error. */
function kk_courier_pathao_token( $fresh = false ) {
	if ( ! $fresh ) {
		$saved = get_transient( 'kk_pathao_token' );
		if ( is_string( $saved ) && '' !== $saved ) {
			return $saved;
		}
	}
	$res = kk_courier_http( 'pathao', 'POST', '/aladdin/api/v1/issue-token', array(
		'client_id'     => kk_courier_secret( 'courier_pathao_client_id' ),
		'client_secret' => kk_courier_secret( 'courier_pathao_client_secret' ),
		'username'      => kk_courier_secret( 'courier_pathao_username' ),
		'password'      => kk_courier_secret( 'courier_pathao_password' ),
		'grant_type'    => 'password',
	) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	if ( $code >= 200 && $code < 300 && ! empty( $body['access_token'] ) ) {
		$ttl = isset( $body['expires_in'] ) && is_numeric( $body['expires_in'] ) ? max( 120, (int) $body['expires_in'] - 300 ) : HOUR_IN_SECONDS;
		set_transient( 'kk_pathao_token', (string) $body['access_token'], $ttl );
		return (string) $body['access_token'];
	}
	delete_transient( 'kk_pathao_token' );
	return new WP_Error( 'kk_courier_auth', __( 'Pathao did not accept the login details. An administrator should check them in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 502 ) );
}

/** A call to Pathao with a valid token; if the token was refused it is renewed once and the call repeated. */
function kk_courier_pathao_call( $method, $path, $body = null ) {
	for ( $try = 0; $try < 2; $try++ ) {
		$token = kk_courier_pathao_token( 1 === $try );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$res = kk_courier_http( 'pathao', $method, $path, $body, array( 'Authorization' => 'Bearer ' . $token ) );
		if ( is_wp_error( $res ) || 401 !== $res[0] ) {
			return $res;
		}
	}
	return new WP_Error( 'kk_courier_auth', __( 'Pathao did not accept the login details. An administrator should check them in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 502 ) );
}

function kk_courier_pathao_book( array $d ) {
	$len = function ( $s ) { return function_exists( 'mb_strlen' ) ? mb_strlen( $s ) : strlen( $s ); };
	// Pathao's own limits, checked first so the message is clear and nothing is sent.
	if ( $len( $d['name'] ) < 3 ) {
		return new WP_Error( 'kk_courier_name', __( 'Pathao needs a customer name of at least 3 characters.', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	if ( $len( $d['address'] ) < 10 ) {
		return new WP_Error( 'kk_courier_address', __( 'Pathao needs a fuller address (at least 10 characters). Add the house, road and area.', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	if ( $len( $d['address'] ) > 220 ) {
		return new WP_Error( 'kk_courier_address_long', __( 'The address is too long for Pathao (220 characters at most). Please shorten it.', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	$weight = max( 0.5, min( 10, (float) kk_opt( 'courier_default_weight' ) ) );
	$payload = array(
		'store_id'            => (int) kk_opt( 'courier_pathao_store_id' ),
		'merchant_order_id'   => $d['invoice'],
		'recipient_name'      => $d['name'],
		'recipient_phone'     => $d['phone'],
		'recipient_address'   => $d['address'],
		'delivery_type'       => 48,
		'item_type'           => 2,
		'special_instruction' => $d['note'],
		'item_quantity'       => max( 1, (int) $d['item_qty'] ),
		'item_weight'         => $weight,
		'item_description'    => $d['item_desc'],
		'amount_to_collect'   => (int) round( $d['cod_amount'] ),
	);
	// City, zone and area are left out on purpose: Pathao finds them from the address.
	$res = kk_courier_pathao_call( 'POST', '/aladdin/api/v1/orders', $payload );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	$c = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
	if ( $code >= 200 && $code < 300 && ! empty( $c['consignment_id'] ) ) {
		return array(
			'consignment_id' => sanitize_text_field( (string) $c['consignment_id'] ),
			'tracking_code'  => sanitize_text_field( (string) $c['consignment_id'] ),
			'status'         => sanitize_key( str_replace( ' ', '_', strtolower( (string) ( $c['order_status'] ?? 'pending' ) ) ) ),
		);
	}
	if ( $code >= 200 && $code < 300 ) {
		return new WP_Error( 'kk_courier_bad_answer', __( 'Pathao sent an answer we could not read. Check your Pathao account before sending again: the parcel may already have been created.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	$why = kk_courier_error_text( $body );
	if ( ! empty( $body['errors'] ) && is_array( $body['errors'] ) ) {
		$lines = array();
		foreach ( $body['errors'] as $field => $msgs ) {
			$lines[] = ( is_string( $field ) ? $field . ': ' : '' ) . implode( ' ', array_map( 'sanitize_text_field', (array) $msgs ) );
		}
		$why = trim( $why . ' ' . implode( ' ', $lines ) );
	}
	/* translators: 1: courier name 2: reason given by the courier */
	return new WP_Error( 'kk_courier_rejected', sprintf( __( '%1$s did not accept the order. %2$s', 'kenakata-kit' ), 'Pathao', $why ), array( 'status' => 422 ) );
}

function kk_courier_pathao_status( $consignment_id ) {
	$res = kk_courier_pathao_call( 'GET', '/aladdin/api/v1/orders/' . rawurlencode( (string) $consignment_id ) . '/info' );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	$d = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
	if ( $code >= 200 && $code < 300 && ( ! empty( $d['order_status_slug'] ) || ! empty( $d['order_status'] ) ) ) {
		return sanitize_key( str_replace( ' ', '_', strtolower( (string) ( ! empty( $d['order_status_slug'] ) ? $d['order_status_slug'] : $d['order_status'] ) ) ) );
	}
	return new WP_Error( 'kk_courier_no_status', __( 'The courier could not tell the status right now. Please try again later.', 'kenakata-kit' ), array( 'status' => 502 ) );
}

/** Pathao: sign in, then list the shop's stores (so the store number can be picked), without booking anything. */
function kk_courier_pathao_test() {
	$res = kk_courier_pathao_call( 'GET', '/aladdin/api/v1/stores' );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	$rows = isset( $body['data']['data'] ) && is_array( $body['data']['data'] ) ? $body['data']['data'] : array();
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'kk_courier_test', __( 'The courier gave an unexpected answer. Please try again in a minute.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	$mine  = (int) kk_opt( 'courier_pathao_store_id' );
	$names = array();
	$found = false;
	foreach ( $rows as $r ) {
		if ( ! is_array( $r ) || empty( $r['store_id'] ) ) {
			continue;
		}
		$found   = $found || (int) $r['store_id'] === $mine;
		$names[] = (int) $r['store_id'] . ' = ' . sanitize_text_field( (string) ( $r['store_name'] ?? '' ) );
	}
	$msg = __( 'Connected. Pathao accepted your login details.', 'kenakata-kit' );
	if ( $names ) {
		/* translators: %s: list like "123 = My store" */
		$msg .= ' ' . sprintf( __( 'Your stores: %s.', 'kenakata-kit' ), implode( '; ', $names ) );
	}
	if ( $mine && $names && ! $found ) {
		$msg .= ' ' . __( 'The Store ID you saved is not in this list. Please correct it.', 'kenakata-kit' );
	}
	return array( 'ok' => true, 'message' => $msg );
}

/* =========================================================================
 * BD Courier adapter (look-up only): one customer's record across all couriers
 * ====================================================================== */

/**
 * The customer's parcel record from BD Courier, as one "source" for the Courier check.
 * Remembered for a while (setting) so the same number never uses your plan's calls twice.
 * Returns the source array or WP_Error.
 */
function kk_courier_bdcourier_source( $phone, $force = false ) {
	$hours = max( 1, min( 168, (int) kk_opt( 'courier_check_cache' ) ) );
	$key   = 'kk_bdc_' . md5( $phone );
	if ( ! $force ) {
		$saved = get_transient( $key );
		if ( is_array( $saved ) ) {
			return $saved;
		}
	}
	$res = kk_courier_http( 'bdcourier', 'POST', '/courier-check', array( 'phone' => $phone ) );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'kk_courier_bdc_unreachable', __( 'BD Courier did not answer. The record of other shops could not be loaded; try again in a minute.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	list( $code, $body ) = $res;
	if ( 401 === $code || 403 === $code ) {
		return new WP_Error( 'kk_courier_bdc_auth', __( 'BD Courier did not accept the API token. An administrator can check it in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	if ( $code < 200 || $code >= 300 ) {
		$why = kk_courier_error_text( $body );
		/* translators: %s: reason given by BD Courier (may be empty) */
		return new WP_Error( 'kk_courier_bdc_error', trim( sprintf( __( 'BD Courier could not give the record right now. %s', 'kenakata-kit' ), $why ) ), array( 'status' => 502 ) );
	}
	$data     = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : array();
	$summary  = isset( $data['summary'] ) && is_array( $data['summary'] ) ? $data['summary'] : array();
	$couriers = array();
	$sum_t = 0; $sum_s = 0; $sum_c = 0;
	foreach ( $data as $ckey => $row ) {
		if ( 'summary' === $ckey || ! is_array( $row ) ) {
			continue;
		}
		$t = max( 0, (int) ( $row['total_parcel'] ?? 0 ) );
		$s = max( 0, (int) ( $row['success_parcel'] ?? 0 ) );
		$c = isset( $row['cancelled_parcel'] ) ? max( 0, (int) $row['cancelled_parcel'] ) : max( 0, $t - $s );
		$sum_t += $t; $sum_s += $s; $sum_c += $c;
		if ( $t > 0 ) {
			$couriers[] = array(
				'name'      => sanitize_text_field( (string) ( $row['name'] ?? ucfirst( (string) $ckey ) ) ),
				'total'     => $t,
				'success'   => $s,
				'cancelled' => $c,
				'rate'      => ( $s + $c ) > 0 ? (int) round( $s * 100 / ( $s + $c ) ) : null,
			);
		}
	}
	$total   = isset( $summary['total_parcel'] ) ? max( 0, (int) $summary['total_parcel'] ) : $sum_t;
	$success = isset( $summary['success_parcel'] ) ? max( 0, (int) $summary['success_parcel'] ) : $sum_s;
	$cancel  = isset( $summary['cancelled_parcel'] ) ? max( 0, (int) $summary['cancelled_parcel'] ) : ( $summary ? max( 0, $total - $success ) : $sum_c );
	$rate    = isset( $summary['success_ratio'] ) && is_numeric( $summary['success_ratio'] ) ? (int) round( (float) $summary['success_ratio'] )
		: ( ( $success + $cancel ) > 0 ? (int) round( $success * 100 / ( $success + $cancel ) ) : null );
	if ( 0 === $total + $success + $cancel ) {
		$rate = null;
	}
	$reports = array();
	foreach ( array_slice( isset( $body['reports'] ) && is_array( $body['reports'] ) ? $body['reports'] : array(), 0, 5 ) as $r ) {
		if ( ! is_array( $r ) ) {
			continue;
		}
		$reports[] = array(
			'courier' => sanitize_text_field( (string) ( $r['courierName'] ?? '' ) ),
			'name'    => sanitize_text_field( (string) ( $r['name'] ?? '' ) ),
			'details' => function_exists( 'mb_substr' ) ? mb_substr( sanitize_text_field( (string) ( $r['details'] ?? '' ) ), 0, 200 ) : substr( sanitize_text_field( (string) ( $r['details'] ?? '' ) ), 0, 200 ),
			'date'    => isset( $r['created_at'] ) ? sanitize_text_field( substr( (string) $r['created_at'], 0, 10 ) ) : '',
		);
	}
	$source = array(
		'key'       => 'bdcourier',
		'label'     => __( 'All couriers (BD Courier)', 'kenakata-kit' ),
		'total'     => $total,
		'success'   => $success,
		'cancelled' => $cancel,
		'open'      => 0,
		'rate'      => $rate,
		'couriers'  => $couriers,
		'reports'   => $reports,
		'fetched'   => time(),
	);
	set_transient( $key, $source, $hours * HOUR_IN_SECONDS );
	return $source;
}

/* =========================================================================
 * Booking an order
 * ====================================================================== */

/** Address as one line: street, area, city, district. */
function kk_courier_order_address( WC_Order $order ) {
	$state = $order->get_billing_state();
	$names = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_states( $order->get_billing_country() ) : array();
	if ( is_array( $names ) && isset( $names[ $state ] ) ) {
		$state = $names[ $state ];
	}
	$parts = array_filter( array_map( 'trim', array( $order->get_billing_address_1(), $order->get_billing_address_2(), $order->get_billing_city(), $state ) ) );
	return implode( ', ', array_unique( $parts ) );
}

/** What to show in the confirmation box: the order's details, ready to be edited before sending. */
function kk_courier_defaults( WC_Order $order ) {
	// Cash on delivery orders sit in "Processing" until the parcel is delivered, so Woo's is_paid() is not usable here:
	// the money is still to be collected until a payment date has been recorded.
	$cod = 'cod' === $order->get_payment_method() && ! $order->get_date_paid() ? max( 0, (float) $order->get_total() - (float) $order->get_total_refunded() ) : 0;
	return array(
		'name'       => trim( $order->get_formatted_billing_full_name() ),
		'phone'      => (string) $order->get_billing_phone(),
		'address'    => kk_courier_order_address( $order ),
		'cod_amount' => round( $cod, 2 ),
		'note'       => '',
	);
}

/** The saved booking of an order, ready for the screens, or null. */
function kk_courier_booking_view( WC_Order $order ) {
	$b = $order->get_meta( '_kk_courier_booking' );
	if ( ! is_array( $b ) || empty( $b['consignment_id'] ) ) {
		return null;
	}
	$providers = kk_courier_providers();
	$provider  = (string) ( $b['provider'] ?? '' );
	return array(
		'provider'       => $provider,
		'label'          => isset( $providers[ $provider ] ) ? $providers[ $provider ]['label'] : $provider,
		'consignment_id' => (string) $b['consignment_id'],
		'tracking_code'  => (string) ( $b['tracking_code'] ?? '' ),
		'status'         => (string) ( $b['status'] ?? '' ),
		'status_label'   => kk_courier_status_label( $provider, (string) ( $b['status'] ?? '' ) ),
		'booked'         => ! empty( $b['booked_at'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $b['booked_at'] ) : '',
		'can_rebook'     => kk_courier_is_cancelled( $provider, (string) ( $b['status'] ?? '' ) ),
	);
}

/**
 * Send an order to the chosen courier. $over may replace name, phone, address, cod_amount, note.
 * On success the order gets the tracking, becomes Shipped and the customer is told (as for any tracking link).
 * Returns the booking (see kk_courier_booking_view) or WP_Error.
 */
function kk_courier_book_order( WC_Order $order, array $over = array() ) {
	$enabled = kk_courier_enabled();
	if ( ! $enabled ) {
		return new WP_Error( 'kk_courier_off', __( 'No courier is set up yet. An administrator can add one in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 400 ) );
	}
	$provider = isset( $over['courier'] ) ? sanitize_key( (string) $over['courier'] ) : '';
	if ( '' === $provider && 1 === count( $enabled ) ) {
		$provider = (string) key( $enabled );
	}
	if ( '' === $provider ) {
		return new WP_Error( 'kk_courier_choose', __( 'Please choose which courier to send this order to.', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	if ( ! isset( $enabled[ $provider ] ) ) {
		return new WP_Error( 'kk_courier_not_enabled', __( 'That courier is not turned on. An administrator can turn it on in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 400 ) );
	}
	$label = $enabled[ $provider ];
	$old       = $order->get_meta( '_kk_courier_booking' );
	if ( is_array( $old ) && ! empty( $old['consignment_id'] ) && ! kk_courier_is_cancelled( (string) ( $old['provider'] ?? '' ), (string) ( $old['status'] ?? '' ) ) ) {
		return new WP_Error(
			'kk_courier_already',
			/* translators: 1: courier name 2: tracking code */
			sprintf( __( 'This order was already sent to %1$s (tracking %2$s).', 'kenakata-kit' ), $label, (string) ( $old['tracking_code'] ?: $old['consignment_id'] ) ),
			array( 'status' => 409 )
		);
	}

	$d     = array_merge( kk_courier_defaults( $order ), array_intersect_key( $over, array_flip( array( 'name', 'phone', 'address', 'cod_amount', 'note' ) ) ) );
	$name  = trim( sanitize_text_field( (string) $d['name'] ) );
	$addr  = trim( sanitize_textarea_field( (string) $d['address'] ) );
	$addr  = trim( preg_replace( '/\s+/', ' ', $addr ) );
	$phone = kk_normalize_phone( (string) $d['phone'] );
	$len   = function ( $s ) { return function_exists( 'mb_strlen' ) ? mb_strlen( $s ) : strlen( $s ); };
	if ( '' === $name ) {
		return new WP_Error( 'kk_courier_name', __( 'Please enter the customer name.', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	if ( '' === $phone ) {
		return new WP_Error( 'kk_courier_phone', __( 'The phone number is not a valid Bangladeshi mobile number. Please correct it and try again.', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	if ( '' === $addr ) {
		return new WP_Error( 'kk_courier_address', __( 'Please enter the delivery address.', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	if ( $len( $addr ) > 250 ) {
		return new WP_Error( 'kk_courier_address_long', __( 'The address is too long for the courier (250 characters at most). Please shorten it.', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	$cod = is_numeric( $d['cod_amount'] ) && (float) $d['cod_amount'] >= 0 ? round( (float) $d['cod_amount'], 2 ) : -1;
	if ( $cod < 0 ) {
		return new WP_Error( 'kk_courier_cod', __( 'Please enter the amount to collect as a number (0 if the customer has paid).', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	$note = trim( sanitize_text_field( (string) $d['note'] ) );

	// One send at a time per order: a double tap must never create two parcels.
	$lock = 'kk_cb_' . $order->get_id();
	if ( get_transient( $lock ) ) {
		return new WP_Error( 'kk_courier_busy', __( 'This order is being sent right now. Please wait a few seconds.', 'kenakata-kit' ), array( 'status' => 409 ) );
	}
	set_transient( $lock, 1, 30 );

	$attempt = (int) $order->get_meta( '_kk_courier_attempts' );
	$invoice = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $order->get_order_number() );
	$invoice = ( '' === $invoice ? (string) $order->get_id() : $invoice ) . ( $attempt > 0 ? '-' . ( $attempt + 1 ) : '' );

	$qty   = 0;
	$names = array();
	foreach ( $order->get_items() as $item ) {
		$qty     += (int) $item->get_quantity();
		$names[]  = $item->get_name();
	}
	$desc = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( implode( ', ', $names ) ) ) );
	$desc = function_exists( 'mb_substr' ) ? mb_substr( $desc, 0, 100 ) : substr( $desc, 0, 100 );
	$fn   = 'kk_courier_' . $provider . '_book';
	$res  = function_exists( $fn ) ? $fn( array( 'invoice' => $invoice, 'name' => $name, 'phone' => $phone, 'address' => $addr, 'cod_amount' => $cod, 'note' => $note, 'item_qty' => $qty, 'item_desc' => $desc ) )
		: new WP_Error( 'kk_courier_unknown', __( 'This courier is not supported.', 'kenakata-kit' ), array( 'status' => 400 ) );
	if ( is_wp_error( $res ) ) {
		delete_transient( $lock );
		return $res;
	}

	$code = '' !== $res['tracking_code'] ? $res['tracking_code'] : $res['consignment_id'];
	$order->update_meta_data( '_kk_courier_booking', array(
		'provider'       => $provider,
		'consignment_id' => $res['consignment_id'],
		'tracking_code'  => $res['tracking_code'],
		'status'         => $res['status'],
		'booked_at'      => time(),
		'by'             => get_current_user_id(),
		'invoice'        => $invoice,
		'cod_amount'     => $cod,
	) );
	$order->update_meta_data( '_kk_courier_attempts', $attempt + 1 );
	// Saves the order, tells the customer (when "notify" is on) and fills courier / tracking link like a manual entry.
	kk_save_order_tracking( $order, $provider, $code, kk_courier_tracking_url( $provider, $res['tracking_code'], $phone ) );

	$order = wc_get_order( $order->get_id() );
	$user  = wp_get_current_user();
	/* translators: 1: courier name 2: consignment id 3: collect amount 4: user name */
	$order->add_order_note( sprintf( __( 'Sent to %1$s (consignment %2$s, collect %3$s) by %4$s.', 'kenakata-kit' ), $label, $res['consignment_id'], kk_plain_price( $cod ), $user && $user->exists() ? $user->display_name : '—' ) );
	if ( ! $order->has_status( 'shipped' ) && $order->has_status( array( 'processing', 'on-hold', 'pending' ) ) && array_key_exists( 'wc-shipped', wc_get_order_statuses() ) ) {
		$order->update_status( 'shipped', __( 'Sent to the courier.', 'kenakata-kit' ) );
	}
	$order->save();
	delete_transient( $lock );
	if ( function_exists( 'kk_sd_flush_summary' ) ) {
		kk_sd_flush_summary();
	}
	return kk_courier_booking_view( wc_get_order( $order->get_id() ) );
}

/** Ask the courier for the parcel's current status and remember it. Returns the booking view or WP_Error. */
function kk_courier_refresh_status( WC_Order $order ) {
	$b = $order->get_meta( '_kk_courier_booking' );
	if ( ! is_array( $b ) || empty( $b['consignment_id'] ) ) {
		return new WP_Error( 'kk_courier_none', __( 'This order has not been sent to a courier from here.', 'kenakata-kit' ), array( 'status' => 404 ) );
	}
	$fn = 'kk_courier_' . (string) $b['provider'] . '_status';
	if ( ! function_exists( $fn ) ) {
		return new WP_Error( 'kk_courier_unknown', __( 'This courier is not supported.', 'kenakata-kit' ), array( 'status' => 400 ) );
	}
	$status = $fn( $b['consignment_id'] );
	if ( is_wp_error( $status ) ) {
		return $status;
	}
	$b['status']     = $status;
	$b['checked_at'] = time();
	$order->update_meta_data( '_kk_courier_booking', $b );
	$order->save();
	return kk_courier_booking_view( $order );
}

/* =========================================================================
 * Courier check: what this phone number's orders did before
 * ====================================================================== */

/**
 * History of a phone number in this shop: delivered, cancelled, still on the way. $skip = the order being looked at.
 * (Records from other shops and couriers can be added as extra "sources" by a later provider.)
 */
function kk_courier_shop_history( $phone, $skip = 0 ) {
	$done = 0; $lost = 0; $open = 0;
	foreach ( kk_orders_by_phone( $phone, 100 ) as $o ) {
		if ( ! $o instanceof WC_Order || (int) $o->get_id() === (int) $skip ) {
			continue;
		}
		if ( $o->has_status( 'completed' ) ) {
			$done++;
		} elseif ( $o->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ) {
			$lost++;
		} else {
			$open++;
		}
	}
	$decided = $done + $lost;
	return array(
		'key'       => 'shop',
		'label'     => __( 'In this shop', 'kenakata-kit' ),
		'total'     => $done + $lost + $open,
		'success'   => $done,
		'cancelled' => $lost,
		'open'      => $open,
		'rate'      => $decided ? (int) round( $done * 100 / $decided ) : null,
	);
}

/** Traffic-light advice from the best source: new / good / caution / risky. */
function kk_courier_verdict( array $sources ) {
	$best = null;
	foreach ( $sources as $s ) {
		if ( ( $s['success'] + $s['cancelled'] ) > ( $best ? $best['success'] + $best['cancelled'] : 0 ) ) {
			$best = $s;
		}
	}
	if ( ! $best ) {
		return array( 'level' => 'new', 'text' => __( 'No delivery history yet. Treat this as a new customer.', 'kenakata-kit' ) );
	}
	$good = max( 1, min( 100, (int) kk_opt( 'courier_good_rate' ) ) );
	$bad  = max( 0, min( 100, (int) kk_opt( 'courier_bad_rate' ) ) );
	$rate = (int) $best['rate'];
	if ( $rate >= $good ) {
		return array( 'level' => 'good', 'text' => __( 'Good record. Cash on delivery is fine.', 'kenakata-kit' ) );
	}
	if ( $rate < $bad ) {
		return array( 'level' => 'risky', 'text' => __( 'Risky. Ask for the delivery charge (or full payment) first.', 'kenakata-kit' ) );
	}
	return array( 'level' => 'caution', 'text' => __( 'Be careful. Call to confirm the order before sending.', 'kenakata-kit' ) );
}

/** Courier check for an order's phone number. */
function kk_courier_checkup( WC_Order $order, $force = false ) {
	if ( ! kk_courier_check_enabled() ) {
		return new WP_Error( 'kk_courier_check_off', __( 'Courier check is turned off. An administrator can turn it on in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 403 ) );
	}
	$phone = kk_normalize_phone( (string) $order->get_billing_phone() );
	if ( '' === $phone ) {
		return new WP_Error( 'kk_courier_phone', __( 'The phone number is not a valid Bangladeshi mobile number. Please correct it and try again.', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	$sources  = array( kk_courier_shop_history( $phone, $order->get_id() ) );
	$warnings = array();
	if ( '' !== kk_courier_check_active() ) {
		$ext = kk_courier_bdcourier_source( $phone, $force );
		if ( is_wp_error( $ext ) ) {
			$warnings[] = $ext->get_error_message(); // the shop's own record still shows
		} else {
			$ext['ago'] = human_time_diff( (int) $ext['fetched'], time() );
			array_unshift( $sources, $ext );
		}
	}
	$sources = apply_filters( 'kk_courier_checkup_sources', $sources, $phone, $order );
	return array(
		'phone'    => $phone,
		'sources'  => array_values( $sources ),
		'warnings' => $warnings,
		'verdict'  => kk_courier_verdict( $sources ),
		'good'    => (int) kk_opt( 'courier_good_rate' ),
		'bad'     => (int) kk_opt( 'courier_bad_rate' ),
	);
}

/* =========================================================================
 * REST callbacks
 * ====================================================================== */

function kk_courier_rest_order( WP_REST_Request $req ) {
	$order = wc_get_order( absint( $req['id'] ) );
	if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
		return new WP_Error( 'kk_courier_not_found', __( 'Not found.', 'kenakata-kit' ), array( 'status' => 404 ) );
	}
	return $order;
}

function kk_courier_rest_book( WP_REST_Request $req ) {
	$order = kk_courier_rest_order( $req );
	if ( is_wp_error( $order ) ) {
		return $order;
	}
	$body = $req->get_json_params();
	$body = is_array( $body ) ? $body : array();
	$res  = kk_courier_book_order( $order, $body );
	return is_wp_error( $res ) ? $res : array( 'ok' => true, 'booking' => $res );
}

function kk_courier_rest_status( WP_REST_Request $req ) {
	$order = kk_courier_rest_order( $req );
	if ( is_wp_error( $order ) ) {
		return $order;
	}
	$res = kk_courier_refresh_status( $order );
	return is_wp_error( $res ) ? $res : array( 'ok' => true, 'booking' => $res );
}

function kk_courier_rest_checkup( WP_REST_Request $req ) {
	$order = kk_courier_rest_order( $req );
	if ( is_wp_error( $order ) ) {
		return $order;
	}
	return kk_courier_checkup( $order, ! empty( $req->get_param( 'refresh' ) ) );
}

/** "Test connection" on the settings screen. $target: a courier key (its account is asked for a balance or store list) or 'check' (BD Courier plan). Books and counts nothing. */
function kk_courier_rest_test( $target = '' ) {
	if ( 'check' === $target ) {
		if ( '' === kk_courier_secret( 'courier_bdcourier_token' ) ) {
			return new WP_Error( 'kk_courier_off', __( 'Save the BD Courier API token first.', 'kenakata-kit' ), array( 'status' => 400 ) );
		}
		$res = kk_courier_http( 'bdcourier', 'GET', '/check-connection' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		list( $code, $body ) = $res;
		if ( 401 === $code || 403 === $code ) {
			return new WP_Error( 'kk_courier_auth', __( 'BD Courier did not accept this API token. Check it and save again.', 'kenakata-kit' ), array( 'status' => 502 ) );
		}
		if ( $code < 200 || $code >= 300 || empty( $body['data']['connected'] ) ) {
			return new WP_Error( 'kk_courier_test', __( 'BD Courier gave an unexpected answer. Please try again in a minute.', 'kenakata-kit' ), array( 'status' => 502 ) );
		}
		$msg  = __( 'Connected to BD Courier.', 'kenakata-kit' );
		$plan = kk_courier_http( 'bdcourier', 'GET', '/my-plan' );
		if ( ! is_wp_error( $plan ) && $plan[0] >= 200 && $plan[0] < 300 && ! empty( $plan[1]['data'] ) && is_array( $plan[1]['data'] ) ) {
			$d = $plan[1]['data'];
			/* translators: 1: plan name 2: free look-ups left */
			$msg .= ' ' . sprintf( __( 'Plan: %1$s. Free look-ups left: %2$d.', 'kenakata-kit' ), sanitize_text_field( (string) ( $d['plan_name'] ?? '—' ) ), (int) ( $d['remaining_free_calls'] ?? 0 ) );
		}
		return array( 'ok' => true, 'message' => $msg );
	}
	$providers = kk_courier_providers();
	if ( ! isset( $providers[ $target ] ) || ! kk_courier_is_configured( $target ) ) {
		return new WP_Error( 'kk_courier_off', __( 'Fill in all the details of this courier and save first.', 'kenakata-kit' ), array( 'status' => 400 ) );
	}
	if ( 'pathao' === $target ) {
		return kk_courier_pathao_test();
	}
	$res = kk_courier_http( $target, 'GET', '/get_balance' );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	if ( 401 === $code || 403 === $code ) {
		return new WP_Error( 'kk_courier_auth', __( 'The courier did not accept these API keys. Check them and save again.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	if ( $code >= 200 && $code < 300 ) {
		$msg = __( 'Connected. The courier accepted your API keys.', 'kenakata-kit' );
		if ( isset( $body['current_balance'] ) && is_numeric( $body['current_balance'] ) ) {
			/* translators: %s: account balance */
			$msg .= ' ' . sprintf( __( 'Balance: %s', 'kenakata-kit' ), kk_plain_price( (float) $body['current_balance'] ) );
		}
		return array( 'ok' => true, 'message' => $msg );
	}
	return new WP_Error( 'kk_courier_test', __( 'The courier gave an unexpected answer. Please try again in a minute.', 'kenakata-kit' ), array( 'status' => 502 ) );
}
