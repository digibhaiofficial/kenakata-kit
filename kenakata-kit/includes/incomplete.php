<?php
/**
 * Incomplete orders: checkouts that were started (a valid phone number and a cart) but never placed.
 *
 * Off by default. When on, a tiny script on the checkout page sends the customer's details to the shop a moment after they
 * type them; the shop sees them in the store dashboard (and in WooCommerce → Incomplete orders) to call and win the order.
 * Light by design: one small script on the checkout page only, one indexed table, a daily clean-up, nothing on other pages.
 *
 * @package KenakataKit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function kk_incomplete_enabled() {
	return (bool) kk_opt( 'incomplete_enable' );
}

function kk_incomplete_table() {
	global $wpdb;
	return $wpdb->prefix . 'kk_incomplete';
}

/** Create the table once (when the feature is first used). */
function kk_incomplete_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = kk_incomplete_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		token varchar(32) NOT NULL,
		phone varchar(20) NOT NULL DEFAULT '',
		name varchar(150) NOT NULL DEFAULT '',
		email varchar(190) NOT NULL DEFAULT '',
		address text NULL,
		city varchar(100) NOT NULL DEFAULT '',
		cart longtext NULL,
		cart_total decimal(14,2) NOT NULL DEFAULT 0,
		ship varchar(255) NOT NULL DEFAULT '',
		status varchar(12) NOT NULL DEFAULT 'open',
		order_id bigint(20) unsigned NOT NULL DEFAULT 0,
		note text NULL,
		created datetime NOT NULL,
		updated datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token (token),
		KEY status_updated (status,updated),
		KEY phone (phone)
	) {$charset};" );
	update_option( 'kk_incomplete_db', '2', true );
}

/** The table exists (any version). */
function kk_incomplete_db_ready() {
	return in_array( get_option( 'kk_incomplete_db' ), array( '1', '2' ), true );
}

add_action( 'init', function () {
	if ( kk_incomplete_enabled() ) {
		if ( '2' !== get_option( 'kk_incomplete_db' ) ) {
			kk_incomplete_install(); // 2: + delivery charge column (dbDelta adds it to an existing table)
		}
		if ( ! wp_next_scheduled( 'kk_incomplete_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'kk_incomplete_cleanup' );
		}
	}
} );
add_action( 'kk_incomplete_cleanup', 'kk_incomplete_cleanup' );

/** Delete old rows: anything untouched for the keep-days setting, and finished (recovered / dismissed) rows after a week. */
function kk_incomplete_cleanup() {
	global $wpdb;
	if ( ! kk_incomplete_db_ready() ) {
		return;
	}
	$t    = kk_incomplete_table();
	$days = max( 1, min( 365, (int) kk_opt( 'incomplete_keep_days' ) ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE updated < %s", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is ours
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE status IN ('recovered','dismissed') AND updated < %s", gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/* =========================================================================
 * The checkout script (checkout page only)
 * ====================================================================== */

add_action( 'wp_enqueue_scripts', function () {
	if ( ! kk_incomplete_enabled() || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) {
		return;
	}
	if ( current_user_can( 'edit_shop_orders' ) ) {
		return; // the team testing the checkout must not fill the list
	}
	wp_enqueue_script( 'kenakata-kit-incomplete', kk_asset( 'kenakata-kit-incomplete.js' ), array(), KK_VERSION, true );
	wp_add_inline_script( 'kenakata-kit-incomplete', 'window.kkIC=' . wp_json_encode( array( 'url' => esc_url_raw( rest_url( 'kenakata/v1/incomplete' ) ) ) ) . ';', 'before' );
} );

/* =========================================================================
 * Capturing (public route, guarded)
 * ====================================================================== */

/** Best guess of the visitor's address, only used to limit how fast one visitor can write. */
function kk_incomplete_visitor_key() {
	// kk_client_ip() ignores X-Forwarded-For (anyone can send it), so the limit cannot be dodged with a fake header.
	$ip = function_exists( 'kk_client_ip' ) ? kk_client_ip() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
	return 'kk_ic_rl_' . md5( (string) $ip );
}

/** The cart in the visitor's session as a small snapshot, or null when it is empty. */
function kk_incomplete_cart_snapshot() {
	if ( ! function_exists( 'WC' ) ) {
		return null;
	}
	if ( function_exists( 'wc_load_cart' ) ) {
		wc_load_cart(); // REST requests do not load the shopper's session by themselves
	}
	$cart = WC()->cart;
	if ( ! $cart || $cart->is_empty() ) {
		return null;
	}
	$items = array();
	foreach ( array_slice( $cart->get_cart(), 0, 20 ) as $row ) {
		$product = isset( $row['data'] ) && $row['data'] instanceof WC_Product ? $row['data'] : null;
		$items[] = array(
			'pid'   => (int) ( $row['product_id'] ?? 0 ),
			'vid'   => (int) ( $row['variation_id'] ?? 0 ),
			'name'  => $product ? wp_strip_all_tags( $product->get_name() ) : '',
			'qty'   => max( 1, (int) ( $row['quantity'] ?? 1 ) ),
			'total' => round( (float) ( $row['line_total'] ?? 0 ), 2 ),
		);
	}
	// The delivery charge the customer saw (chosen delivery area), so "Make order" can add the same charge.
	$ship = array();
	if ( WC()->session && $cart->needs_shipping() ) {
		$chosen = (array) WC()->session->get( 'chosen_shipping_methods', array() );
		$id     = isset( $chosen[0] ) ? (string) $chosen[0] : '';
		$title  = '';
		foreach ( (array) WC()->shipping()->get_packages() as $pkg ) {
			if ( $id && isset( $pkg['rates'][ $id ] ) ) {
				$title = wp_strip_all_tags( $pkg['rates'][ $id ]->get_label() );
			}
		}
		if ( '' !== $id ) {
			$ship = array( 'id' => kk_incomplete_clip( $id, 60 ), 'title' => kk_incomplete_clip( $title, 100 ), 'cost' => round( (float) $cart->get_shipping_total(), 2 ) );
		}
	}
	return array( 'items' => $items, 'total' => round( (float) $cart->get_total( 'edit' ), 2 ), 'ship' => $ship );
}

function kk_incomplete_clip( $s, $n ) {
	return function_exists( 'mb_substr' ) ? mb_substr( (string) $s, 0, $n ) : substr( (string) $s, 0, $n );
}

function kk_incomplete_rest_capture( WP_REST_Request $req ) {
	global $wpdb;
	if ( ! kk_incomplete_enabled() ) {
		return new WP_Error( 'kk_incomplete_off', __( 'Not found.', 'kenakata-kit' ), array( 'status' => 404 ) );
	}
	if ( current_user_can( 'edit_shop_orders' ) ) {
		return array( 'ok' => false, 'skipped' => 'staff' );
	}
	$key = kk_incomplete_visitor_key();
	$n   = (int) get_transient( $key );
	if ( $n >= 60 ) { // 60 saves per 10 minutes per visitor is far more than a person can type
		return new WP_Error( 'kk_incomplete_slow', __( 'Too many requests.', 'kenakata-kit' ), array( 'status' => 429 ) );
	}
	set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );

	$p     = $req->get_json_params();
	$p     = is_array( $p ) ? $p : array();
	$phone = kk_normalize_phone( kk_incomplete_clip( sanitize_text_field( (string) ( $p['phone'] ?? '' ) ), 30 ) );
	$email = sanitize_email( kk_incomplete_clip( (string) ( $p['email'] ?? '' ), 190 ) );
	if ( '' === $phone ) {
		return array( 'ok' => false, 'skipped' => 'no-phone' );
	}
	$cart = kk_incomplete_cart_snapshot();
	if ( ! $cart ) {
		return array( 'ok' => false, 'skipped' => 'empty-cart' );
	}
	$row = array(
		'phone'      => $phone,
		'name'       => kk_incomplete_clip( trim( sanitize_text_field( (string) ( $p['name'] ?? '' ) ) ), 150 ),
		'email'      => is_email( $email ) ? $email : '',
		'address'    => kk_incomplete_clip( trim( sanitize_textarea_field( (string) ( $p['address'] ?? '' ) ) ), 500 ),
		'city'       => kk_incomplete_clip( trim( sanitize_text_field( (string) ( $p['city'] ?? '' ) ) ), 100 ),
		'cart'       => wp_json_encode( $cart['items'] ),
		'cart_total' => $cart['total'],
	);
	if ( '2' === get_option( 'kk_incomplete_db' ) ) {
		$row['ship'] = $cart['ship'] ? (string) wp_json_encode( $cart['ship'] ) : '';
	}
	$t     = kk_incomplete_table();
	$now   = gmdate( 'Y-m-d H:i:s' );
	$token = isset( $p['token'] ) && preg_match( '/^[a-f0-9]{32}$/', (string) $p['token'] ) ? (string) $p['token'] : '';
	$found = null;
	if ( '' !== $token ) {
		$found = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, token FROM {$t} WHERE token = %s", $token ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	if ( ! $found ) {
		// Cleared browser data: the same phone within a day is still the same checkout. Anyone can type any phone
		// number, so here we only fill in what is still empty, and the row's token is never handed out.
		$same = $wpdb->get_row( $wpdb->prepare( "SELECT id, status, name, email, address, city FROM {$t} WHERE phone = %s AND status IN ('open','called') AND updated > %s ORDER BY updated DESC LIMIT 1", $phone, gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $same ) {
			$fill = array( 'updated' => $now );
			foreach ( array( 'name', 'email', 'address', 'city' ) as $k ) {
				if ( '' === (string) $same->$k && '' !== $row[ $k ] ) {
					$fill[ $k ] = $row[ $k ];
				}
			}
			$wpdb->update( $t, $fill, array( 'id' => (int) $same->id ) );
			return array( 'ok' => true, 'token' => '' );
		}
	}
	if ( $found && 'dismissed' === $found->status ) {
		return array( 'ok' => true, 'token' => (string) $found->token ); // the shop dismissed it: do not bring it back
	}
	if ( $found && in_array( $found->status, array( 'open', 'called' ), true ) ) {
		// A later save may know less than an earlier one (a field cleared, a half-filled form): never wipe what was already typed.
		$keep = array_filter( $row, function ( $v, $k ) { return ! in_array( $k, array( 'name', 'email', 'address', 'city' ), true ) || '' !== $v; }, ARRAY_FILTER_USE_BOTH );
		$wpdb->update( $t, array_merge( $keep, array( 'updated' => $now ) ), array( 'id' => (int) $found->id ) );
		return array( 'ok' => true, 'token' => (string) $found->token );
	}
	// New checkout. Keep the table bounded even under abuse.
	if ( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ) >= 20000 ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DELETE FROM {$t} ORDER BY updated ASC LIMIT 1000" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
	$token = bin2hex( random_bytes( 16 ) );
	$wpdb->insert( $t, array_merge( $row, array( 'token' => $token, 'status' => 'open', 'created' => $now, 'updated' => $now ) ) );
	return array( 'ok' => true, 'token' => $token );
}

/* =========================================================================
 * Recovered: an order was placed
 * ====================================================================== */

/** The customer finished: close their open rows (same phone, last two weeks). */
function kk_incomplete_mark_recovered( $order ) {
	global $wpdb;
	if ( ! kk_incomplete_enabled() || ! $order instanceof WC_Order ) {
		return;
	}
	$phone = kk_normalize_phone( (string) $order->get_billing_phone() );
	if ( '' === $phone ) {
		return;
	}
	$t = kk_incomplete_table();
	$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'recovered', order_id = %d, updated = %s WHERE phone = %s AND status IN ('open','called') AND updated > %s", $order->get_id(), gmdate( 'Y-m-d H:i:s' ), $phone, gmdate( 'Y-m-d H:i:s', time() - 14 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
add_action( 'woocommerce_checkout_order_processed', function ( $order_id, $posted = null, $order = null ) {
	kk_incomplete_mark_recovered( $order instanceof WC_Order ? $order : wc_get_order( $order_id ) );
}, 10, 3 );
add_action( 'woocommerce_store_api_checkout_order_processed', 'kk_incomplete_mark_recovered' );

/* =========================================================================
 * The team's side: list, update, make an order
 * ====================================================================== */

/** Rows younger than this (minutes) are still being typed: not shown, not counted. */
function kk_incomplete_wait() {
	return max( 1, min( 240, (int) kk_opt( 'incomplete_wait' ) ) );
}

/** How many open checkouts are waiting for a call (cached with the dashboard summary). */
function kk_incomplete_count_open() {
	global $wpdb;
	if ( ! kk_incomplete_enabled() || ! kk_incomplete_db_ready() ) {
		return 0;
	}
	$t = kk_incomplete_table();
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE status = 'open' AND updated <= %s AND updated > %s", gmdate( 'Y-m-d H:i:s', time() - kk_incomplete_wait() * MINUTE_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

function kk_incomplete_view( $r ) {
	$items = json_decode( (string) $r->cart, true );
	$items = is_array( $items ) ? $items : array();
	$ts    = strtotime( $r->updated . ' UTC' );
	$view  = array(
		'id'       => (int) $r->id,
		'name'     => (string) $r->name,
		'phone'    => (string) $r->phone,
		'wa'       => $r->phone ? 'https://wa.me/' . kk_intl_phone( $r->phone ) : '',
		'email'    => (string) $r->email,
		'address'  => (string) $r->address,
		'city'     => (string) $r->city,
		'items'    => array(),
		'total'    => kk_plain_price( (float) $r->cart_total ),
		'status'   => (string) $r->status,
		'order_id' => (int) $r->order_id,
		'note'     => (string) $r->note,
		'ts'       => $ts ? $ts : 0,
		'date'     => $ts ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) : '',
	);
	foreach ( $items as $it ) {
		$view['items'][] = array( 'name' => (string) ( $it['name'] ?? '' ), 'qty' => (int) ( $it['qty'] ?? 1 ), 'total' => kk_plain_price( (float) ( $it['total'] ?? 0 ) ) );
	}
	if ( $r->order_id ) {
		$o = wc_get_order( (int) $r->order_id );
		$view['order_number'] = $o ? (string) $o->get_order_number() : '';
	}
	return $view;
}

function kk_incomplete_rest_list( WP_REST_Request $req ) {
	global $wpdb;
	$t      = kk_incomplete_table();
	$status = sanitize_key( (string) $req->get_param( 'status' ) );
	$status = in_array( $status, array( 'open', 'called', 'recovered', 'dismissed', 'all' ), true ) ? $status : 'open';
	$search = trim( sanitize_text_field( (string) $req->get_param( 'search' ) ) );
	$page   = max( 1, absint( $req->get_param( 'page' ) ) );
	$per    = 20;
	$ready  = gmdate( 'Y-m-d H:i:s', time() - kk_incomplete_wait() * MINUTE_IN_SECONDS );
	$where  = 'updated <= %s';
	$args   = array( $ready );
	if ( 'all' !== $status ) {
		$where .= ' AND status = %s';
		$args[] = $status;
	}
	if ( '' !== $search ) {
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$where .= ' AND (phone LIKE %s OR name LIKE %s)';
		$args[] = $like;
		$args[] = $like;
	}
	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE {$where}", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE {$where} ORDER BY updated DESC LIMIT %d OFFSET %d", array_merge( $args, array( $per, ( $page - 1 ) * $per ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	$counts = array( 'open' => 0, 'called' => 0, 'recovered' => 0, 'dismissed' => 0 );
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM {$t} WHERE updated <= %s GROUP BY status", $ready ) ) as $c ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts[ $c->status ] = (int) $c->n;
	}
	return array(
		'items'  => array_map( 'kk_incomplete_view', (array) $rows ),
		'total'  => $total,
		'pages'  => (int) ceil( $total / $per ),
		'counts' => $counts,
	);
}

function kk_incomplete_get( $id ) {
	global $wpdb;
	$t = kk_incomplete_table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", absint( $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

function kk_incomplete_rest_update( WP_REST_Request $req ) {
	global $wpdb;
	$row = kk_incomplete_get( $req['id'] );
	if ( ! $row ) {
		return new WP_Error( 'kk_incomplete_missing', __( 'Not found.', 'kenakata-kit' ), array( 'status' => 404 ) );
	}
	$data = array();
	$st   = $req->get_param( 'status' );
	if ( null !== $st ) {
		$st = sanitize_key( (string) $st );
		if ( ! in_array( $st, array( 'open', 'called', 'dismissed' ), true ) || 'recovered' === $row->status ) {
			return new WP_Error( 'kk_incomplete_status', __( 'This status cannot be set.', 'kenakata-kit' ), array( 'status' => 422 ) );
		}
		$data['status'] = $st;
	}
	if ( null !== $req->get_param( 'note' ) ) {
		$data['note'] = kk_incomplete_clip( sanitize_textarea_field( (string) $req->get_param( 'note' ) ), 500 );
	}
	if ( $data ) {
		$wpdb->update( kk_incomplete_table(), $data, array( 'id' => (int) $row->id ) );
		if ( function_exists( 'kk_sd_flush_summary' ) ) {
			kk_sd_flush_summary();
		}
	}
	return array( 'ok' => true, 'item' => kk_incomplete_view( kk_incomplete_get( $row->id ) ) );
}

/** Turn an unfinished checkout into a real order (cash on delivery, Processing) after the shop has called the customer. */
function kk_incomplete_rest_order( WP_REST_Request $req ) {
	global $wpdb;
	$row = kk_incomplete_get( $req['id'] );
	if ( ! $row ) {
		return new WP_Error( 'kk_incomplete_missing', __( 'Not found.', 'kenakata-kit' ), array( 'status' => 404 ) );
	}
	if ( 'recovered' === $row->status || (int) $row->order_id ) {
		return new WP_Error( 'kk_incomplete_done', __( 'An order was already made from this.', 'kenakata-kit' ), array( 'status' => 409 ) );
	}
	$lock = 'kk_ic_mk_' . (int) $row->id;
	if ( ! kk_lock( $lock, 30 ) ) {
		return new WP_Error( 'kk_incomplete_busy', __( 'This order is being created right now. Please wait a few seconds.', 'kenakata-kit' ), array( 'status' => 409 ) );
	}
	$items = json_decode( (string) $row->cart, true );
	$order = wc_create_order( array( 'status' => 'pending' ) );
	if ( is_wp_error( $order ) ) {
		kk_unlock( $lock );
		return $order;
	}
	$added = 0;
	foreach ( is_array( $items ) ? $items : array() as $it ) {
		$product = wc_get_product( ! empty( $it['vid'] ) ? (int) $it['vid'] : (int) ( $it['pid'] ?? 0 ) );
		if ( $product && 'publish' === $product->get_status() && $product->is_purchasable() ) {
			$qty  = max( 1, (int) ( $it['qty'] ?? 1 ) );
			$line = isset( $it['total'] ) && (float) $it['total'] > 0 ? (float) $it['total'] : null;
			// The price the customer saw in the cart (with any discount), not today's price.
			$order->add_product( $product, $qty, null === $line ? array() : array( 'subtotal' => $line, 'total' => $line ) );
			$added++;
		}
	}
	if ( 0 === $added ) {
		$order->delete( true );
		kk_unlock( $lock );
		return new WP_Error( 'kk_incomplete_nostock', __( 'None of these products can be ordered any more.', 'kenakata-kit' ), array( 'status' => 422 ) );
	}
	$parts = preg_split( '/\s+/', trim( (string) $row->name ), 2 );
	$order->set_address( array(
		'first_name' => isset( $parts[0] ) ? $parts[0] : '',
		'last_name'  => isset( $parts[1] ) ? $parts[1] : '',
		'phone'      => (string) $row->phone,
		'email'      => (string) $row->email,
		'address_1'  => (string) $row->address,
		'city'       => (string) $row->city,
		'country'    => function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_base_country() : 'BD',
	), 'billing' );
	$ship = isset( $row->ship ) ? json_decode( (string) $row->ship, true ) : null;
	if ( is_array( $ship ) && isset( $ship['cost'] ) ) {
		$si = new WC_Order_Item_Shipping();
		$si->set_method_title( ! empty( $ship['title'] ) ? (string) $ship['title'] : __( 'Delivery charge', 'kenakata-kit' ) );
		$parts_id = explode( ':', (string) ( $ship['id'] ?? '' ) );
		$si->set_method_id( sanitize_key( $parts_id[0] ) );
		$si->set_instance_id( isset( $parts_id[1] ) ? absint( $parts_id[1] ) : 0 );
		$si->set_total( wc_format_decimal( max( 0, (float) $ship['cost'] ) ) );
		$order->add_item( $si );
	}
	$order->set_payment_method( 'cod' );
	$order->set_payment_method_title( __( 'Cash on delivery', 'kenakata-kit' ) );
	$order->calculate_totals();
	$user = wp_get_current_user();
	/* translators: %s: user name */
	$order->add_order_note( sprintf( __( 'Made from an incomplete checkout by %s.', 'kenakata-kit' ), $user && $user->exists() ? $user->display_name : '—' ) );
	$order->update_status( 'processing' );
	$order->save();
	$wpdb->update( kk_incomplete_table(), array( 'status' => 'recovered', 'order_id' => $order->get_id(), 'updated' => gmdate( 'Y-m-d H:i:s' ) ), array( 'id' => (int) $row->id ) );
	kk_unlock( $lock );
	if ( function_exists( 'kk_sd_flush_summary' ) ) {
		kk_sd_flush_summary();
	}
	return array( 'ok' => true, 'order_id' => $order->get_id(), 'order_number' => (string) $order->get_order_number(), 'item' => kk_incomplete_view( kk_incomplete_get( $row->id ) ) );
}

add_action( 'rest_api_init', function () {
	$ns  = 'kenakata/v1';
	$can = function () {
		return is_user_logged_in() && current_user_can( 'edit_shop_orders' ) && kk_incomplete_enabled();
	};
	$id = array( 'id' => array( 'validate_callback' => function ( $v ) { return is_numeric( $v ); } ) );
	register_rest_route( $ns, '/incomplete', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true', // public on purpose (guests at checkout); guarded inside: switch, staff skip, rate limit, cart needed
		'callback'            => 'kk_incomplete_rest_capture',
	) );
	register_rest_route( $ns, '/sd/incomplete', array( 'methods' => 'GET', 'permission_callback' => $can, 'callback' => 'kk_incomplete_rest_list' ) );
	register_rest_route( $ns, '/sd/incomplete/(?P<id>\d+)', array( 'methods' => 'POST', 'permission_callback' => $can, 'args' => $id, 'callback' => 'kk_incomplete_rest_update' ) );
	register_rest_route( $ns, '/sd/incomplete/(?P<id>\d+)/order', array( 'methods' => 'POST', 'permission_callback' => $can, 'args' => $id, 'callback' => 'kk_incomplete_rest_order' ) );
} );

/* =========================================================================
 * WooCommerce → Incomplete orders (a plain list for shops that do not use the dashboard)
 * ====================================================================== */

add_action( 'admin_menu', function () {
	if ( ! kk_incomplete_enabled() ) {
		return;
	}
	add_submenu_page( 'woocommerce', __( 'Incomplete orders', 'kenakata-kit' ), __( 'Incomplete orders', 'kenakata-kit' ), 'edit_shop_orders', 'kk-incomplete', function () {
		global $wpdb;
		$t    = kk_incomplete_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE status IN ('open','called') AND updated <= %s ORDER BY updated DESC LIMIT 100", gmdate( 'Y-m-d H:i:s', time() - kk_incomplete_wait() * MINUTE_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		echo '<div class="wrap"><h1>' . esc_html__( 'Incomplete orders', 'kenakata-kit' ) . '</h1>';
		echo '<p>' . esc_html__( 'Customers who reached the checkout and typed their phone number but did not place the order. Call them: many only needed a nudge.', 'kenakata-kit' ) . '</p>';
		if ( function_exists( 'kk_sd_url' ) && kk_opt( 'sd_enable' ) ) {
			echo '<p><a class="button button-primary" href="' . esc_url( kk_sd_url() . '#/incomplete' ) . '">' . esc_html__( 'Open in the store dashboard', 'kenakata-kit' ) . '</a></p>';
		}
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'When', 'kenakata-kit' ) . '</th><th>' . esc_html__( 'Customer', 'kenakata-kit' ) . '</th><th>' . esc_html__( 'Cart', 'kenakata-kit' ) . '</th><th>' . esc_html__( 'Total', 'kenakata-kit' ) . '</th><th>' . esc_html__( 'Status', 'kenakata-kit' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Nothing here. Good news: nobody left in the middle of the checkout.', 'kenakata-kit' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$v = kk_incomplete_view( $r );
			$names = implode( ', ', array_map( function ( $i ) { return $i['name'] . ' × ' . $i['qty']; }, $v['items'] ) );
			echo '<tr><td>' . esc_html( human_time_diff( (int) $v['ts'], time() ) ) . ' ' . esc_html__( 'ago', 'kenakata-kit' ) . '</td><td><strong>' . esc_html( $v['name'] ) . '</strong><br><a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $v['phone'] ) ) . '">' . esc_html( $v['phone'] ) . '</a>' . ( $v['wa'] ? ' · <a href="' . esc_url( $v['wa'] ) . '" target="_blank" rel="noopener">WhatsApp</a>' : '' ) . '<br>' . esc_html( $v['address'] ) . '</td><td>' . esc_html( $names ) . '</td><td>' . esc_html( $v['total'] ) . '</td><td>' . esc_html( $v['status'] ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	} );
} );

/* =========================================================================
 * Privacy: policy text, export and erase (by email, and the phone numbers that email used)
 * ====================================================================== */

add_action( 'admin_init', function () {
	if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
		wp_add_privacy_policy_content( 'Kenakata Kit', wp_kses_post( '<p>' . __( 'When a visitor types a phone number on the checkout page, the shop may keep the name, phone number, email, address and cart so that the shop can contact them about the unfinished order. These details are removed automatically after the number of days set by the shop, or when asked.', 'kenakata-kit' ) . '</p>' ) );
	}
} );
/**
 * WordPress privacy requests come by email, but most rows here have only a phone number: also match the phone
 * saved on that customer's account and on their orders.
 */
function kk_incomplete_privacy_where( $email ) {
	global $wpdb;
	$phones = array();
	$user   = get_user_by( 'email', $email );
	if ( $user ) {
		$phones[] = kk_normalize_phone( (string) get_user_meta( $user->ID, 'billing_phone', true ) );
	}
	foreach ( wc_get_orders( array( 'billing_email' => $email, 'limit' => 20, 'return' => 'objects' ) ) as $o ) {
		$phones[] = kk_normalize_phone( (string) $o->get_billing_phone() );
	}
	$phones = array_values( array_unique( array_filter( $phones ) ) );
	$sql    = $wpdb->prepare( 'email = %s', $email );
	foreach ( $phones as $ph ) {
		$sql .= $wpdb->prepare( ' OR phone = %s', $ph );
	}
	return '(' . $sql . ')';
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $e ) {
	$e['kenakata-kit-incomplete'] = array( 'exporter_friendly_name' => __( 'Incomplete checkouts', 'kenakata-kit' ), 'callback' => function ( $email ) {
		global $wpdb;
		$out = array();
		if ( kk_incomplete_db_ready() && is_email( $email ) ) {
			$t = kk_incomplete_table();
			$where = kk_incomplete_privacy_where( $email );
			foreach ( (array) $wpdb->get_results( "SELECT * FROM {$t} WHERE {$where}" ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared
				$out[] = array( 'group_id' => 'kk-incomplete', 'group_label' => __( 'Incomplete checkouts', 'kenakata-kit' ), 'item_id' => 'kk-incomplete-' . $r->id, 'data' => array(
					array( 'name' => __( 'Name', 'kenakata-kit' ), 'value' => $r->name ),
					array( 'name' => __( 'Phone', 'kenakata-kit' ), 'value' => $r->phone ),
					array( 'name' => __( 'Address', 'kenakata-kit' ), 'value' => $r->address ),
					array( 'name' => __( 'Last seen', 'kenakata-kit' ), 'value' => $r->updated ),
				) );
			}
		}
		return array( 'data' => $out, 'done' => true );
	} );
	return $e;
} );
add_filter( 'wp_privacy_personal_data_erasers', function ( $e ) {
	$e['kenakata-kit-incomplete'] = array( 'eraser_friendly_name' => __( 'Incomplete checkouts', 'kenakata-kit' ), 'callback' => function ( $email ) {
		global $wpdb;
		$n = 0;
		if ( kk_incomplete_db_ready() && is_email( $email ) ) {
			$t     = kk_incomplete_table();
			$where = kk_incomplete_privacy_where( $email );
			$n     = (int) $wpdb->query( "DELETE FROM {$t} WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is prepared
		}
		return array( 'items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
	} );
	return $e;
} );
