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
		'ecourier'  => kk_opt( 'courier_ecourier_sandbox' ) ? 'https://staging.ecourier.com.bd/api' : 'https://backoffice.ecourier.com.bd/api',
		'redx'      => kk_opt( 'courier_redx_sandbox' ) ? 'https://sandbox.redx.com.bd/v1.0.0-beta' : 'https://openapi.redx.com.bd/v1.0.0-beta',
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
	} elseif ( 'ecourier' === $provider ) {
		$headers['API-KEY']    = kk_courier_secret( 'courier_ecourier_api_key' );
		$headers['API-SECRET'] = kk_courier_secret( 'courier_ecourier_api_secret' );
		$headers['USER-ID']    = kk_courier_secret( 'courier_ecourier_user_id' );
	} elseif ( 'redx' === $provider ) {
		$token                       = kk_courier_secret( 'courier_redx_token' );
		$headers['API-ACCESS-TOKEN'] = 0 === stripos( $token, 'Bearer ' ) ? $token : 'Bearer ' . $token; // pasted with or without "Bearer"
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
		'timeout' => 'steadfast' === $provider ? 12 : 20, // Pathao / RedX / BD Courier can be slow to answer
		'user-agent' => 'KenakataKit/' . KK_VERSION . '; ' . home_url( '/' ),
		'headers' => array_merge( kk_courier_headers( $provider ), $extra_headers ),
	);
	if ( null !== $body ) {
		$args['body'] = wp_json_encode( $body );
	}
	// IPv4 only: on many shared hosts IPv6 is half-working, and couriers behind Cloudflare (Pathao) have IPv6
	// addresses, so the call hangs until the time-out. IPv4 always works.
	$v4 = function ( $handle ) {
		if ( defined( 'CURLOPT_IPRESOLVE' ) && defined( 'CURL_IPRESOLVE_V4' ) ) {
			curl_setopt( $handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_setopt
		}
	};
	add_action( 'http_api_curl', $v4 );
	$t0   = microtime( true );
	$res  = wp_remote_request( $base . $path, $args );
	remove_action( 'http_api_curl', $v4 );
	$ms   = (int) round( ( microtime( true ) - $t0 ) * 1000 );
	$logp = strtok( $path, '?' );
	if ( is_wp_error( $res ) ) {
		// The server's own reason (time-out, name not found, blocked by the hosting firewall…) for the log and the message.
		$why = sanitize_text_field( $res->get_error_message() );
		kk_courier_log_add( array( 'c' => $provider, 'm' => $method, 'p' => $logp, 'ok' => false, 'ms' => $ms, 'msg' => mb_substr( $why, 0, 200 ) ) );
		return new WP_Error(
			'kk_courier_unreachable',
			/* translators: 1: courier name 2: courier name 3: technical reason from the server */
			sprintf( __( 'Could not reach %1$s or it did not answer in time. Check your %2$s account before sending again: the parcel may already have been created. (Server says: %3$s)', 'kenakata-kit' ), $label, $label, mb_substr( $why, 0, 160 ) )
				. ( kk_opt( 'courier_' . $provider . '_sandbox' ) ? ' ' . __( 'Note: this courier\'s TEST server is switched on in the settings. For real parcels and real login details, switch it off.', 'kenakata-kit' ) : '' ),
			array( 'status' => 502 )
		);
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	$data = is_array( $data ) ? $data : array();
	kk_courier_log_add( array( 'c' => $provider, 'm' => $method, 'p' => $logp, 'ok' => $code >= 200 && $code < 300, 'code' => $code, 'ms' => $ms, 'msg' => mb_substr( kk_courier_error_text( $data ), 0, 200 ) ) );
	return array( $code, $data );
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
	} elseif ( '' !== $tracking_code && function_exists( 'kk_courier_track_link' ) ) {
		$url = kk_courier_track_link( $provider, $tracking_code, $phone ); // RedX and the tick list's own links
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

/** Where the Pathao token is kept: the test server and the real one have different tokens. */
function kk_courier_pathao_token_key() {
	return kk_opt( 'courier_pathao_sandbox' ) ? 'kk_pathao_token_sb' : 'kk_pathao_token';
}

/** A login token for Pathao's API, remembered until shortly before it expires. Returns the token or WP_Error. */
function kk_courier_pathao_token( $fresh = false ) {
	if ( ! $fresh ) {
		$saved = get_transient( kk_courier_pathao_token_key() );
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
		set_transient( kk_courier_pathao_token_key(), (string) $body['access_token'], $ttl );
		return (string) $body['access_token'];
	}
	delete_transient( kk_courier_pathao_token_key() );
	$why = kk_courier_error_text( $body );
	$msg = __( 'Pathao did not accept the login details. An administrator should check them in Kenakata Kit → Courier booking.', 'kenakata-kit' );
	if ( false !== stripos( $why, 'user credentials' ) ) {
		$msg = __( 'Pathao says the login email or password is wrong. Type the email and password you use to sign in at merchant.pathao.com again (the Client ID and secret are separate) and save.', 'kenakata-kit' );
	} elseif ( false !== stripos( $why, 'client' ) ) {
		$msg = __( 'Pathao says the Client ID or Client secret is wrong. Copy them again from merchant.pathao.com → Developers API and save.', 'kenakata-kit' );
	}
	if ( kk_opt( 'courier_pathao_sandbox' ) ) {
		$msg .= ' ' . __( 'Note: "Use Pathao\'s test server" is on. Real Pathao details only work with it off.', 'kenakata-kit' );
	}
	/* translators: %s: Pathao's own words */
	return new WP_Error( 'kk_courier_auth', $msg . ( '' !== $why ? ' ' . sprintf( __( '(Pathao: %s)', 'kenakata-kit' ), $why ) : '' ), array( 'status' => 502 ) );
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
	if ( ! kk_lock( $lock, 30 ) ) {
		return new WP_Error( 'kk_courier_busy', __( 'This order is being sent right now. Please wait a few seconds.', 'kenakata-kit' ), array( 'status' => 409 ) );
	}

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
	$res  = function_exists( $fn ) ? $fn( array( 'invoice' => $invoice, 'name' => $name, 'phone' => $phone, 'address' => $addr, 'cod_amount' => $cod, 'note' => $note, 'item_qty' => $qty, 'item_desc' => $desc, 'order' => $order ) )
		: new WP_Error( 'kk_courier_unknown', __( 'This courier is not supported.', 'kenakata-kit' ), array( 'status' => 400 ) );
	if ( is_wp_error( $res ) ) {
		kk_unlock( $lock );
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
	kk_unlock( $lock );
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
	// Optional: delivered parcel → order Completed (only from Shipped/Processing, never from a cancelled or refunded order).
	if ( kk_opt( 'tr_auto_complete' ) && kk_courier_is_delivered( (string) $b['provider'], (string) $status ) && $order->has_status( array( 'shipped', 'processing', 'on-hold' ) ) ) {
		/* translators: %s: courier name */
		$order->update_status( 'completed', sprintf( __( 'Kenakata Kit: %s says the parcel was delivered.', 'kenakata-kit' ), kk_courier_booking_view( $order )['label'] ) );
	}
	return kk_courier_booking_view( $order );
}

/* =========================================================================
 * RedX adapter (OpenAPI v1.0.0-beta). RedX needs a delivery area for every parcel: it is found from the
 * address (area names RedX knows), or the "default delivery area" setting is used.
 * ====================================================================== */

/** RedX delivery areas (id, name, district), remembered for a week. Returns the list or WP_Error. */
function kk_courier_redx_areas( $fresh = false ) {
	$saved = $fresh ? false : get_transient( 'kk_redx_areas' );
	if ( is_array( $saved ) && $saved ) {
		return $saved;
	}
	$res = kk_courier_http( 'redx', 'GET', '/areas' );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	if ( 401 === $code || 403 === $code ) {
		return new WP_Error( 'kk_courier_auth', __( 'RedX did not accept the API token. An administrator should check it in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	$out = array();
	foreach ( isset( $body['areas'] ) && is_array( $body['areas'] ) ? $body['areas'] : array() as $a ) {
		if ( ! empty( $a['id'] ) && ! empty( $a['name'] ) ) {
			$out[] = array( 'id' => (int) $a['id'], 'name' => sanitize_text_field( (string) $a['name'] ), 'district' => sanitize_text_field( (string) ( $a['district_name'] ?? '' ) ) );
		}
	}
	if ( ! $out ) {
		return new WP_Error( 'kk_courier_no_areas', __( 'RedX did not send its list of delivery areas. Please try again in a minute.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	set_transient( 'kk_redx_areas', $out, WEEK_IN_SECONDS );
	return $out;
}

/** The RedX area for an address: the longest area name found in it, else the default area. array( id, name ) or WP_Error. */
function kk_courier_redx_area( $text ) {
	$areas = kk_courier_redx_areas();
	if ( is_wp_error( $areas ) ) {
		return $areas;
	}
	$hay  = ' ' . strtolower( preg_replace( '/[^\p{L}\p{N}]+/u', ' ', (string) $text ) ) . ' ';
	$best = null;
	foreach ( $areas as $a ) {
		$needle = ' ' . strtolower( trim( preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $a['name'] ) ) ) . ' ';
		if ( strlen( $needle ) > 3 && false !== strpos( $hay, $needle ) && ( ! $best || strlen( $a['name'] ) > strlen( $best['name'] ) ) ) {
			$best = $a;
		}
	}
	if ( ! $best ) {
		$def = absint( kk_opt( 'courier_redx_area_id' ) );
		foreach ( $def ? $areas : array() as $a ) {
			if ( $a['id'] === $def ) {
				$best = $a;
			}
		}
	}
	return $best ? $best : new WP_Error( 'kk_courier_area', __( 'RedX needs the delivery area (for example "Mirpur 10" or "Uttara Sector 7"). Add the area to the address, or set a default delivery area in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 422 ) );
}

function kk_courier_redx_book( array $d ) {
	$area = kk_courier_redx_area( $d['address'] );
	if ( is_wp_error( $area ) ) {
		return $area;
	}
	$weight  = (int) round( max( 0.5, min( 10, (float) kk_opt( 'courier_default_weight' ) ) ) * 1000 ); // grams
	$payload = array(
		'customer_name'          => $d['name'],
		'customer_phone'         => $d['phone'],
		'delivery_area'          => $area['name'],
		'delivery_area_id'       => $area['id'],
		'customer_address'       => $d['address'],
		'merchant_invoice_id'    => $d['invoice'],
		'cash_collection_amount' => (string) round( $d['cod_amount'] ),
		'parcel_weight'          => $weight,
		'instruction'            => $d['note'],
		'value'                  => (int) round( $d['cod_amount'] ),
	);
	$store = absint( kk_opt( 'courier_redx_store_id' ) );
	if ( $store ) {
		$payload['pickup_store_id'] = $store;
	}
	$res = kk_courier_http( 'redx', 'POST', '/parcel', $payload );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	if ( $code >= 200 && $code < 300 && ! empty( $body['tracking_id'] ) ) {
		$tid = sanitize_text_field( (string) $body['tracking_id'] );
		return array( 'consignment_id' => $tid, 'tracking_code' => $tid, 'status' => 'pickup_pending' );
	}
	if ( $code >= 200 && $code < 300 ) {
		return new WP_Error( 'kk_courier_bad_answer', __( 'RedX sent an answer we could not read. Check your RedX account before sending again: the parcel may already have been created.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	if ( 401 === $code || 403 === $code ) {
		return new WP_Error( 'kk_courier_auth', __( 'RedX did not accept the API token. An administrator should check it in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	/* translators: 1: courier name 2: reason given by the courier */
	return new WP_Error( 'kk_courier_rejected', sprintf( __( '%1$s did not accept the order. %2$s', 'kenakata-kit' ), 'RedX', kk_courier_error_text( $body ) ), array( 'status' => 422 ) );
}

function kk_courier_redx_status( $tracking_id ) {
	$res = kk_courier_http( 'redx', 'GET', '/parcel/info/' . rawurlencode( (string) $tracking_id ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	$p = isset( $body['parcel'] ) && is_array( $body['parcel'] ) ? $body['parcel'] : array();
	if ( $code >= 200 && $code < 300 && ! empty( $p['status'] ) ) {
		return sanitize_key( str_replace( array( ' ', '-' ), '_', strtolower( (string) $p['status'] ) ) );
	}
	return new WP_Error( 'kk_courier_no_status', __( 'The courier could not tell the status right now. Please try again later.', 'kenakata-kit' ), array( 'status' => 502 ) );
}

/** RedX: check the token and list pickup stores (with numbers), without booking anything. */
function kk_courier_redx_test() {
	$res = kk_courier_http( 'redx', 'GET', '/pickup/stores' );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	if ( 401 === $code || 403 === $code ) {
		return new WP_Error( 'kk_courier_auth', __( 'RedX did not accept this API token. Check it and save again.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'kk_courier_test', __( 'The courier gave an unexpected answer. Please try again in a minute.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	$stores = array();
	foreach ( isset( $body['pickup_stores'] ) && is_array( $body['pickup_stores'] ) ? $body['pickup_stores'] : array() as $st ) {
		if ( isset( $st['id'], $st['name'] ) ) {
			$stores[] = sanitize_text_field( $st['name'] ) . ' = ' . (int) $st['id'];
		}
	}
	$areas = kk_courier_redx_areas( true );
	$msg   = __( 'Connected to RedX.', 'kenakata-kit' );
	if ( $stores ) {
		/* translators: %s: list of "store name = number" */
		$msg .= ' ' . sprintf( __( 'Pickup stores: %s', 'kenakata-kit' ), implode( ', ', $stores ) );
	}
	if ( ! is_wp_error( $areas ) ) {
		/* translators: %d: number of areas */
		$msg .= ' ' . sprintf( __( '%d delivery areas loaded.', 'kenakata-kit' ), count( $areas ) );
	}
	return array( 'ok' => true, 'message' => $msg );
}

/* =========================================================================
 * eCourier adapter (Merchant API v5.1). eCourier needs city, thana, area and postcode: they come from the order's
 * district, city/area and postcode fields; the package code comes from the settings ("Test connection" lists them).
 * ====================================================================== */

function kk_courier_ecourier_book( array $d ) {
	$o = isset( $d['order'] ) && $d['order'] instanceof WC_Order ? $d['order'] : null;
	if ( '' === trim( (string) kk_opt( 'courier_ecourier_package' ) ) ) {
		return new WP_Error( 'kk_courier_package', __( 'eCourier needs a package code. Press "Test connection" in Kenakata Kit → Courier booking, copy your package code and save it.', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	$clip     = function ( $s, $n ) { return function_exists( 'mb_substr' ) ? mb_substr( trim( (string) $s ), 0, $n ) : substr( trim( (string) $s ), 0, $n ); };
	$district = '';
	$city     = '';
	$zip      = '';
	if ( $o ) {
		$state = $o->get_billing_state();
		$names = WC()->countries ? WC()->countries->get_states( $o->get_billing_country() ) : array();
		$district = is_array( $names ) && isset( $names[ $state ] ) ? $names[ $state ] : $state;
		$city     = $o->get_billing_city();
		$zip      = $o->get_billing_postcode();
	}
	$city_name = '' !== trim( $district ) ? $district : ( '' !== trim( $city ) ? $city : (string) kk_opt( 'courier_ecourier_city' ) );
	$area      = '' !== trim( $city ) ? $city : $city_name;
	$payload   = array(
		'recipient_name'       => $clip( $d['name'], 250 ),
		'recipient_mobile'     => $d['phone'],
		'recipient_city'       => $clip( $city_name, 40 ),
		'recipient_thana'      => $clip( $area, 40 ),
		'recipient_area'       => $clip( $area, 40 ),
		'recipient_address'    => $clip( $d['address'], 250 ),
		'recipient_zip'        => $clip( '' !== trim( $zip ) ? $zip : (string) kk_opt( 'courier_ecourier_zip' ), 10 ),
		'package_code'         => trim( (string) kk_opt( 'courier_ecourier_package' ) ),
		'product_price'        => (int) round( $d['cod_amount'] ),
		'actual_product_price' => (int) round( $d['cod_amount'] ),
		'payment_method'       => 'COD',
		'parcel_type'          => 'BOX',
		'product_id'           => $clip( $d['invoice'], 20 ),
		'number_of_item'       => max( 1, (int) $d['item_qty'] ),
		'comments'             => $clip( $d['note'], 255 ),
		'parcel_detail'        => $clip( $d['item_desc'], 255 ),
	);
	$res = kk_courier_http( 'ecourier', 'POST', '/order-place', $payload );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	$id = isset( $body['ID'] ) ? (string) $body['ID'] : ( isset( $body['id'] ) ? (string) $body['id'] : '' );
	if ( $code >= 200 && $code < 300 && '' !== $id && ( ! isset( $body['success'] ) || false !== $body['success'] ) ) {
		$id = sanitize_text_field( $id );
		return array( 'consignment_id' => $id, 'tracking_code' => $id, 'status' => 'initiated' );
	}
	if ( 401 === $code || 403 === $code ) {
		return new WP_Error( 'kk_courier_auth', __( 'eCourier did not accept the API details. An administrator should check them in Kenakata Kit → Courier booking.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	$why = kk_courier_error_text( $body );
	if ( '' === $why && isset( $body['message'] ) && is_array( $body['message'] ) ) {
		$why = sanitize_text_field( implode( ' ', array_map( 'strval', array_filter( $body['message'], 'is_scalar' ) ) ) );
	}
	/* translators: 1: courier name 2: reason given by the courier */
	return new WP_Error( 'kk_courier_rejected', sprintf( __( '%1$s did not accept the order. %2$s', 'kenakata-kit' ), 'eCourier', $why ), array( 'status' => 422 ) );
}

function kk_courier_ecourier_status( $tracking_id ) {
	$res = kk_courier_http( 'ecourier', 'POST', '/track', array( 'ecr' => (string) $tracking_id ) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	$q = isset( $body['query_data'] ) && is_array( $body['query_data'] ) ? $body['query_data'] : array();
	$q = isset( $q[0] ) && is_array( $q[0] ) ? $q[0] : $q;
	$st = isset( $q['status'] ) && is_array( $q['status'] ) ? $q['status'] : array();
	$last = $st ? end( $st ) : null;
	$name = is_array( $last ) ? (string) ( $last['status'] ?? ( $last[0] ?? '' ) ) : (string) $last;
	if ( $code >= 200 && $code < 300 && '' !== $name ) {
		return sanitize_key( str_replace( array( ' ', '-' ), '_', strtolower( $name ) ) );
	}
	return new WP_Error( 'kk_courier_no_status', __( 'The courier could not tell the status right now. Please try again later.', 'kenakata-kit' ), array( 'status' => 502 ) );
}

/** eCourier: check the keys and list the packages (codes to copy into the settings). Books nothing. */
function kk_courier_ecourier_test() {
	$res = kk_courier_http( 'ecourier', 'POST', '/packages', array() );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	list( $code, $body ) = $res;
	if ( 401 === $code || 403 === $code || ( isset( $body['success'] ) && false === $body['success'] ) ) {
		return new WP_Error( 'kk_courier_auth', __( 'eCourier did not accept these API details. Check them and save again.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'kk_courier_test', __( 'The courier gave an unexpected answer. Please try again in a minute.', 'kenakata-kit' ), array( 'status' => 502 ) );
	}
	$list = array();
	foreach ( isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body as $p ) {
		if ( is_array( $p ) && ! empty( $p['package_code'] ) ) {
			$list[] = sanitize_text_field( (string) $p['package_code'] ) . ' = ' . sanitize_text_field( (string) ( $p['package_name'] ?? '' ) );
		}
	}
	$msg = __( 'Connected to eCourier.', 'kenakata-kit' );
	if ( $list ) {
		/* translators: %s: list of "code = name" */
		$msg .= ' ' . sprintf( __( 'Your packages: %s', 'kenakata-kit' ), implode( '; ', array_slice( $list, 0, 12 ) ) );
	}
	return array( 'ok' => true, 'message' => $msg );
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
	$res = kk_courier_checkup( $order, ! empty( $req->get_param( 'refresh' ) ) );
	if ( ! is_wp_error( $res ) && function_exists( 'kk_cc_store' ) ) {
		kk_cc_store( $order, $res ); // the orders list shows the result as a coloured badge
	}
	return $res;
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
	if ( 'redx' === $target ) {
		return kk_courier_redx_test();
	}
	if ( 'ecourier' === $target ) {
		return kk_courier_ecourier_test();
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
