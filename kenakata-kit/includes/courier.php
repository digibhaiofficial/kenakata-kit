<?php
/**
 * Courier booking: pick a courier in the admin, paste its API keys, then send an order to it with one tap
 * (store dashboard or the WooCommerce order screen). This small file only holds the registry, the key storage and
 * the REST routes; the code that talks to couriers lives in courier-core.php and loads only when it is needed,
 * so ordinary shop pages never touch it.
 *
 * @package KenakataKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Couriers that can take a booking. Each has one adapter in courier-core.php.
 * 'secrets' = the settings keys holding its API credentials (stored apart from the normal settings).
 */
function kk_courier_providers() {
	return apply_filters( 'kk_courier_providers', array(
		'steadfast' => array(
			'label'   => 'Steadfast',
			'secrets' => array( 'courier_steadfast_api_key', 'courier_steadfast_secret_key' ),
		),
		'pathao'    => array(
			'label'   => 'Pathao',
			'secrets' => array( 'courier_pathao_client_id', 'courier_pathao_client_secret', 'courier_pathao_username', 'courier_pathao_password' ),
			'options' => array( 'courier_pathao_store_id' ), // plain settings this courier also needs
		),
	) );
}

/** Is this status one where the courier cancelled the parcel (so the order may be sent again)? */
function kk_courier_is_cancelled( $provider, $status ) {
	$status = strtolower( str_replace( '-', '_', (string) $status ) );
	$list   = array( 'steadfast' => array( 'cancelled' ), 'pathao' => array( 'cancelled', 'pickup_cancelled' ) );
	return isset( $list[ $provider ] ) && in_array( $status, $list[ $provider ], true );
}

/**
 * Services that only look a customer up (no parcels): they add records from other shops and couriers to the Courier check.
 * Each has one adapter in courier-core.php.
 */
function kk_courier_check_providers() {
	return apply_filters( 'kk_courier_check_providers', array(
		'bdcourier' => array(
			'label'   => 'BD Courier',
			'secrets' => array( 'courier_bdcourier_token' ),
		),
	) );
}

/** Is this settings key an API credential (kept out of the exported, autoloaded settings)? */
function kk_courier_is_secret_key( $key ) {
	foreach ( array_merge( kk_courier_providers(), kk_courier_check_providers() ) as $p ) {
		if ( in_array( $key, $p['secrets'], true ) ) {
			return true;
		}
	}
	return false;
}

/** Name of a booking courier or look-up service for messages. */
function kk_courier_label( $key ) {
	$all = array_merge( kk_courier_check_providers(), kk_courier_providers() );
	return isset( $all[ $key ] ) ? $all[ $key ]['label'] : (string) $key;
}

/** Key of the look-up service when its token is saved, else ''. */
function kk_courier_check_active() {
	return kk_courier_check_enabled() && '' !== kk_courier_secret( 'courier_bdcourier_token' ) ? 'bdcourier' : '';
}

function kk_courier_secret( $key ) {
	$all = get_option( 'kk_courier_secrets', array() );
	return is_array( $all ) && isset( $all[ $key ] ) ? (string) $all[ $key ] : '';
}

/** Save one credential. Empty input keeps the saved one. Not autoloaded, never part of the settings export. */
function kk_courier_save_secret( $key, $value ) {
	$value = trim( sanitize_text_field( (string) $value ) );
	if ( '' === $value || ! kk_courier_is_secret_key( $key ) ) {
		return;
	}
	$all = get_option( 'kk_courier_secrets', array() );
	$all = is_array( $all ) ? $all : array();
	$all[ $key ] = $value;
	update_option( 'kk_courier_secrets', $all, false );
}

/** "•••• 1234" for a saved credential, '' when nothing is saved. */
function kk_courier_secret_hint( $key ) {
	$v = kk_courier_secret( $key );
	return '' === $v ? '' : '•••• ' . substr( $v, -4 );
}

/** Is this courier turned on in the settings? (Older installs that picked one courier in a drop-down count as "on".) */
function kk_courier_is_on( $key ) {
	if ( kk_opt( 'courier_' . $key . '_on' ) ) {
		return true;
	}
	return $key === (string) kk_opt( 'courier_provider' );
}

/** Are all of this courier's credentials and ids saved? */
function kk_courier_is_configured( $key ) {
	$providers = kk_courier_providers();
	if ( ! isset( $providers[ $key ] ) ) {
		return false;
	}
	foreach ( $providers[ $key ]['secrets'] as $s ) {
		if ( '' === kk_courier_secret( $s ) ) {
			return false;
		}
	}
	foreach ( isset( $providers[ $key ]['options'] ) ? $providers[ $key ]['options'] : array() as $o ) {
		if ( 0 === absint( kk_opt( $o ) ) ) { // numeric ids
			return false;
		}
	}
	return true;
}

/** Couriers that are turned on AND fully set up: array( key => label ). Only these appear on orders. */
function kk_courier_enabled() {
	$out = array();
	foreach ( kk_courier_providers() as $key => $p ) {
		if ( kk_courier_is_on( $key ) && kk_courier_is_configured( $key ) ) {
			$out[ $key ] = $p['label'];
		}
	}
	return $out;
}

/** The same as a list for the screens: array( array( key, label ), … ). */
function kk_courier_list() {
	$out = array();
	foreach ( kk_courier_enabled() as $key => $label ) {
		$out[] = array( 'key' => $key, 'label' => $label );
	}
	return $out;
}

/** First enabled courier (kept for older callers), or null. */
function kk_courier_info() {
	$list = kk_courier_list();
	return $list ? $list[0] : null;
}

/** Is the "Courier check" switched on? It shows on orders only then. */
function kk_courier_check_enabled() {
	return (bool) kk_opt( 'courier_check_enable' );
}

/** Load the part that talks to couriers (only when a booking, a status check or a history check is asked for). */
function kk_courier_load() {
	require_once KK_DIR . '/includes/courier-core.php';
}

require_once KK_DIR . '/includes/courier-admin.php'; // Courier check on WooCommerce → Orders and the order screen

add_action( 'rest_api_init', function () {
	$ns   = 'kenakata/v1';
	$can  = function () {
		return is_user_logged_in() && current_user_can( 'edit_shop_orders' );
	};
	$id   = array( 'id' => array( 'validate_callback' => function ( $v ) { return is_numeric( $v ); } ) );
	register_rest_route( $ns, '/courier/book/(?P<id>\d+)', array(
		'methods'             => 'POST',
		'permission_callback' => $can,
		'args'                => $id,
		'callback'            => function ( WP_REST_Request $req ) {
			kk_courier_load();
			return kk_courier_rest_book( $req );
		},
	) );
	register_rest_route( $ns, '/courier/status/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'permission_callback' => $can,
		'args'                => $id,
		'callback'            => function ( WP_REST_Request $req ) {
			kk_courier_load();
			return kk_courier_rest_status( $req );
		},
	) );
	register_rest_route( $ns, '/courier/checkup/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'permission_callback' => $can,
		'args'                => $id,
		'callback'            => function ( WP_REST_Request $req ) {
			kk_courier_load();
			return kk_courier_rest_checkup( $req );
		},
	) );
	register_rest_route( $ns, '/courier/test', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in() && current_user_can( 'manage_woocommerce' );
		},
		'callback'            => function ( WP_REST_Request $req ) {
			kk_courier_load();
			return kk_courier_rest_test( sanitize_key( (string) $req->get_param( 'target' ) ) );
		},
	) );
} );
