<?php
/**
 * Messaging (3.21, off until turned on): ready message templates for an order, sent by hand.
 * - WhatsApp: one tap opens WhatsApp with the message filled in (nothing is sent by the site).
 * - Email: sent as a WooCommerce "customer note" (the normal WooCommerce email, kept in the order notes).
 * - SMS (optional, off): only through the shop's own SMS gateway URL. Nothing runs in the background.
 *
 * @package KenakataKit
 */

defined( 'ABSPATH' ) || exit;

/** Template keys with their default text. */
function kk_msg_defaults() {
	return array(
		'confirm'   => array( __( 'Order confirmed', 'kenakata-kit' ), __( "Hello {name}, thank you for your order #{order} at {shop}. Total: {total}. We will send it soon.", 'kenakata-kit' ) ),
		'shipped'   => array( __( 'Sent with courier', 'kenakata-kit' ), __( "Hello {name}, your order #{order} is on its way. Please keep {to_collect} ready for the delivery person. Track: {tracking}", 'kenakata-kit' ) ),
		'pay'       => array( __( 'Payment reminder', 'kenakata-kit' ), __( "Hello {name}, your order #{order} is waiting for payment of {to_collect}. Reply here if you need help. – {shop}", 'kenakata-kit' ) ),
		'delivered' => array( __( 'Thank you', 'kenakata-kit' ), __( "Hello {name}, we hope you like your order #{order}. Thank you for shopping with {shop}!", 'kenakata-kit' ) ),
		'call'      => array( __( 'Could not reach you', 'kenakata-kit' ), __( "Hello {name}, we tried to call you about order #{order} but could not reach you. Please call or reply here to confirm. – {shop}", 'kenakata-kit' ) ),
	);
}

/** Templates as saved in the settings (empty text = not offered). */
function kk_msg_templates() {
	$out = array();
	foreach ( kk_msg_defaults() as $key => $d ) {
		$text = trim( (string) kk_opt( 'msg_tpl_' . $key ) );
		if ( '' !== $text ) {
			$out[ $key ] = array( 'label' => $d[0], 'text' => $text );
		}
	}
	return $out;
}

/** Fill the {placeholders} of a template for one order (plain text). */
function kk_msg_render( WC_Order $order, $text ) {
	$collect = function_exists( 'kk_print_to_collect' ) ? kk_print_to_collect( $order ) : (float) $order->get_total();
	$items   = array();
	foreach ( $order->get_items() as $item ) {
		$items[] = $item->get_name() . ' × ' . $item->get_quantity();
	}
	$money = function ( $v ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $v ) ), ENT_QUOTES, 'UTF-8' );
	};
	$map = array(
		'{name}'       => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
		'{order}'      => $order->get_order_number(),
		'{total}'      => $money( $order->get_total() ),
		'{to_collect}' => $money( $collect ),
		'{items}'      => implode( ', ', $items ),
		'{status}'     => wc_get_order_status_name( $order->get_status() ),
		'{shop}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'{tracking}'   => function_exists( 'kk_order_meta' ) ? (string) kk_order_meta( $order, 'tracking_url' ) : '',
		'{site}'       => home_url( '/' ),
	);
	$out = strtr( (string) $text, $map );
	return trim( preg_replace( "/[ \t]+\n/", "\n", preg_replace( '/ {2,}/', ' ', $out ) ) );
}

function kk_msg_sms_ready() {
	return kk_opt( 'msg_sms_enable' ) && '' !== trim( (string) kk_opt( 'msg_sms_url' ) );
}

/** What the store dashboard shows for one order (null when messaging is off). */
function kk_msg_for_order( WC_Order $order ) {
	if ( ! kk_opt( 'msg_enable' ) ) {
		return null;
	}
	$phone = function_exists( 'kk_intl_phone' ) ? kk_intl_phone( $order->get_billing_phone() ) : '';
	$list  = array();
	foreach ( kk_msg_templates() as $key => $t ) {
		$list[] = array( 'key' => $key, 'label' => $t['label'], 'text' => kk_msg_render( $order, $t['text'] ) );
	}
	return array(
		'templates' => $list,
		'wa'        => $phone ? 'https://wa.me/' . $phone : '',
		'email'     => (bool) $order->get_billing_email(),
		'sms'       => kk_msg_sms_ready() && '' !== (string) $order->get_billing_phone(),
	);
}

/** Send one message by email (customer note) or SMS. Returns true or WP_Error. */
function kk_msg_send( WC_Order $order, $channel, $text ) {
	$text = trim( sanitize_textarea_field( (string) $text ) );
	if ( '' === $text ) {
		return new WP_Error( 'kk_msg_empty', __( 'The message is empty.', 'kenakata-kit' ), array( 'status' => 400 ) );
	}
	if ( strlen( $text ) > 2000 ) {
		return new WP_Error( 'kk_msg_long', __( 'The message is too long.', 'kenakata-kit' ), array( 'status' => 400 ) );
	}
	$who = wp_get_current_user()->display_name;
	if ( 'email' === $channel ) {
		if ( ! $order->get_billing_email() ) {
			return new WP_Error( 'kk_msg_no_email', __( 'This order has no email address.', 'kenakata-kit' ), array( 'status' => 400 ) );
		}
		// A customer note: WooCommerce emails it to the customer (its "Customer note" email) and keeps it in the order.
		$order->add_order_note( $text, 1, true );
		return true;
	}
	if ( 'sms' === $channel ) {
		if ( ! kk_msg_sms_ready() ) {
			return new WP_Error( 'kk_msg_no_sms', __( 'SMS is turned off.', 'kenakata-kit' ), array( 'status' => 400 ) );
		}
		$res = kk_msg_sms_request( $order->get_billing_phone(), $text );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		/* translators: 1: user name 2: message */
		$order->add_order_note( sprintf( __( 'SMS sent by %1$s: %2$s', 'kenakata-kit' ), $who, $text ) );
		return true;
	}
	if ( 'wa' === $channel ) { // only noted: WhatsApp itself is opened by the staff member's phone
		/* translators: 1: user name 2: message */
		$order->add_order_note( sprintf( __( 'WhatsApp message opened by %1$s: %2$s', 'kenakata-kit' ), $who, $text ) );
		return true;
	}
	return new WP_Error( 'kk_msg_channel', 'Unknown channel.', array( 'status' => 400 ) );
}

/**
 * Call the shop's own SMS gateway. URL (and POST body) may use {phone}, {phone88} (8801…) and {message}.
 * Any 2xx answer counts as sent.
 */
function kk_msg_sms_request( $phone, $text ) {
	$phone   = preg_replace( '/\D+/', '', (string) $phone );
	$intl    = function_exists( 'kk_intl_phone' ) ? kk_intl_phone( $phone ) : $phone;
	$local   = preg_replace( '/^88/', '', $intl );
	$is_post = 'post' === kk_opt( 'msg_sms_method' );
	$fill    = function ( $tpl, $encode ) use ( $local, $intl, $text ) {
		$e = $encode ? 'rawurlencode' : 'strval';
		return strtr( (string) $tpl, array( '{phone}' => $e( $local ), '{phone88}' => $e( $intl ), '{message}' => $e( $text ) ) );
	};
	$url = $fill( trim( (string) kk_opt( 'msg_sms_url' ) ), true );
	if ( ! wp_http_validate_url( $url ) ) {
		return new WP_Error( 'kk_msg_sms_url', __( 'The SMS gateway address is not valid.', 'kenakata-kit' ), array( 'status' => 400 ) );
	}
	$args = array( 'timeout' => 15, 'method' => $is_post ? 'POST' : 'GET' );
	if ( $is_post ) {
		$body = (string) kk_opt( 'msg_sms_body' );
		$json = '' !== $body && '{' === substr( ltrim( $body ), 0, 1 );
		$args['body'] = $json ? $fill( $body, false ) : $fill( $body, true );
		if ( $json ) {
			// JSON body: insert the values JSON-escaped.
			$args['body']    = strtr( $body, array( '{phone}' => trim( wp_json_encode( $local ), '"' ), '{phone88}' => trim( wp_json_encode( $intl ), '"' ), '{message}' => trim( wp_json_encode( $text ), '"' ) ) );
			$args['headers'] = array( 'Content-Type' => 'application/json' );
		} else {
			$args['headers'] = array( 'Content-Type' => 'application/x-www-form-urlencoded' );
		}
	}
	$res = wp_safe_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'kk_msg_sms_fail', __( 'The SMS gateway did not answer.', 'kenakata-kit' ) . ' ' . $res->get_error_message(), array( 'status' => 502 ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	if ( $code < 200 || $code >= 300 ) {
		/* translators: %d: HTTP status code */
		return new WP_Error( 'kk_msg_sms_fail', sprintf( __( 'The SMS gateway refused the message (code %d).', 'kenakata-kit' ), $code ), array( 'status' => 502 ) );
	}
	return true;
}

/* WooCommerce order screen: the same templates as one-tap WhatsApp links (classic and HPOS screens). */
add_action( 'add_meta_boxes', function () {
	if ( ! kk_opt( 'msg_enable' ) || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}
	$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	add_meta_box( 'kk-messages', __( 'Message the customer', 'kenakata-kit' ), 'kk_msg_metabox', $screen, 'side', 'default' );
} );

function kk_msg_metabox( $post_or_order ) {
	$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order instanceof WP_Post ? $post_or_order->ID : 0 );
	if ( ! $order ) {
		return;
	}
	$data = kk_msg_for_order( $order );
	if ( ! $data || ! $data['templates'] ) {
		echo '<p>' . esc_html__( 'No message templates yet.', 'kenakata-kit' ) . '</p>';
		return;
	}
	if ( ! $data['wa'] ) {
		echo '<p>' . esc_html__( 'This order has no valid phone number.', 'kenakata-kit' ) . '</p>';
		return;
	}
	echo '<ul style="margin:0">';
	foreach ( $data['templates'] as $t ) {
		$href = $data['wa'] . '?text=' . rawurlencode( $t['text'] );
		echo '<li style="margin:0 0 6px"><a class="button" target="_blank" rel="noopener" href="' . esc_attr( $href ) . '">WhatsApp: ' . esc_html( $t['label'] ) . '</a></li>';
	}
	echo '</ul><p class="description">' . esc_html__( 'Opens WhatsApp with the message filled in; you press Send.', 'kenakata-kit' ) . '</p>';
}
