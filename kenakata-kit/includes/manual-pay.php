<?php
/**
 * Mobile payment by hand: bKash / Nagad / Rocket (3.16). Off until "mp_enable" is turned on.
 *
 * A WooCommerce payment method. The customer sends money to the shop's number from their own app, then types the
 * number they sent from and the Transaction ID (TrxID), and may add a screenshot. No API, no account, no fees to us.
 * The order waits in "On hold" until the shop checks its own bKash/Nagad/Rocket app and presses "Payment received"
 * (order screen or store dashboard). "Not received" sends the order to "Pending payment".
 *
 * Amount: the whole order, or only the delivery charge in advance (the rest is cash on delivery). The second keeps
 * fake orders away without losing customers who prefer cash.
 *
 * Classic checkout only (Kenakata Kit's checkout, Elementor's checkout widget, the [woocommerce_checkout] shortcode).
 *
 * @package KenakataKit
 */

defined( 'ABSPATH' ) || exit;

/** The wallets the shop accepts: key => array( label, number, type label, colour ). Only those with a number. */
function kk_mp_wallets() {
	$types = kk_mp_types();
	$all   = array(
		'bkash'  => array( 'bKash', '#e2136e' ),
		'nagad'  => array( 'Nagad', '#ec1c24' ),
		'rocket' => array( 'Rocket', '#8c3494' ),
		'upay'   => array( 'Upay', '#0057a8' ),
	);
	$out = array();
	foreach ( $all as $key => $w ) {
		$num = kk_normalize_phone( (string) kk_opt( 'mp_' . $key . '_number' ) );
		if ( 'rocket' === $key && '' === $num ) {
			// Rocket numbers have 12 digits (11-digit mobile + check digit).
			$raw = preg_replace( '/\D/', '', (string) kk_opt( 'mp_rocket_number' ) );
			$num = strlen( $raw ) >= 11 && strlen( $raw ) <= 12 ? $raw : '';
		}
		if ( '' === $num ) {
			continue;
		}
		$type        = (string) kk_opt( 'mp_' . $key . '_type' );
		$out[ $key ] = array(
			'label'  => $w[0],
			'number' => $num,
			'type'   => isset( $types[ $type ] ) ? $types[ $type ] : $types['personal'],
			'how'    => 'merchant' === $type ? __( 'Payment', 'kenakata-kit' ) : ( 'agent' === $type ? __( 'Cash Out', 'kenakata-kit' ) : __( 'Send Money', 'kenakata-kit' ) ),
			'color'  => $w[1],
		);
	}
	return $out;
}

function kk_mp_types() {
	return array(
		'personal' => __( 'Personal', 'kenakata-kit' ),
		'agent'    => __( 'Agent', 'kenakata-kit' ),
		'merchant' => __( 'Merchant', 'kenakata-kit' ),
	);
}

/** Amount to pay now for a cart total + delivery charge. */
function kk_mp_amount_now( $total, $shipping ) {
	if ( 'delivery' === kk_opt( 'mp_amount' ) ) {
		$fixed = (float) kk_opt( 'mp_advance_fixed' );
		$now   = $fixed > 0 ? $fixed : (float) $shipping;
		return min( (float) $total, max( 0, $now ) );
	}
	return (float) $total;
}

/**
 * Money still to collect at the door for an order paid (partly) with mobile payment, or null for other methods.
 * Not checked yet → the whole total, so a parcel is never sent with a smaller amount by mistake.
 */
function kk_mp_to_collect( WC_Order $order ) {
	if ( 'kk_mobile_pay' !== $order->get_payment_method() ) {
		return null;
	}
	$total = max( 0, (float) $order->get_total() - (float) $order->get_total_refunded() );
	if ( 'yes' !== $order->get_meta( '_kk_mp_verified' ) ) {
		return $total;
	}
	return max( 0, $total - (float) $order->get_meta( '_kk_mp_amount' ) );
}

/** Saved payment details of an order for the screens, or null. */
function kk_mp_details( WC_Order $order ) {
	if ( 'kk_mobile_pay' !== $order->get_payment_method() ) {
		return null;
	}
	$wallets = array( 'bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'upay' => 'Upay' );
	$w       = (string) $order->get_meta( '_kk_mp_wallet' );
	$shot    = absint( $order->get_meta( '_kk_mp_shot' ) );
	$state   = (string) $order->get_meta( '_kk_mp_verified' );
	return array(
		'wallet'   => isset( $wallets[ $w ] ) ? $wallets[ $w ] : $w,
		'to'       => (string) $order->get_meta( '_kk_mp_to' ),
		'sender'   => (string) $order->get_meta( '_kk_mp_sender' ),
		'trx'      => (string) $order->get_meta( '_kk_mp_trx' ),
		'amount'   => (float) $order->get_meta( '_kk_mp_amount' ),
		'advance'  => 'delivery' === $order->get_meta( '_kk_mp_mode' ),
		'shot'     => $shot ? kk_mp_shot_url( $order->get_id() ) : '',
		'state'    => in_array( $state, array( 'yes', 'no' ), true ) ? $state : 'wait',
		'collect'  => kk_mp_to_collect( $order ),
	);
}

/* =========================================================================
 * Screenshots: kept outside the Media Library in uploads/kk-private/ (not reachable from the web); only staff
 * who can edit orders see them, through admin-post.php.
 * ====================================================================== */
function kk_mp_private_dir() {
	$up  = wp_upload_dir( null, false );
	$dir = trailingslashit( $up['basedir'] ) . 'kk-private';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}
	return $dir;
}

function kk_mp_shot_url( $order_id ) {
	return add_query_arg( array( 'action' => 'kk_mp_shot', 'order' => absint( $order_id ), '_wpnonce' => wp_create_nonce( 'kk_mp_shot' ) ), admin_url( 'admin-post.php' ) );
}

/**
 * POST ?wc-ajax=kk_mp_upload (file "shot"): checks the picture and keeps it for 2 days until an order uses it.
 * Returns { token } or { error }. At most 10 uploads per visitor per hour.
 */
add_action( 'wc_ajax_kk_mp_upload', function () {
	if ( ! kk_opt( 'mp_enable' ) || 'off' === kk_opt( 'mp_screenshot' ) ) {
		wp_send_json( array( 'error' => __( 'Screenshots are not accepted.', 'kenakata-kit' ) ), 403 );
	}
	$key  = 'kk_mpu_' . md5( kk_client_ip() );
	$hits = (int) get_transient( $key );
	if ( $hits >= 10 ) {
		wp_send_json( array( 'error' => __( 'Too many attempts. Please try again in a few minutes.', 'kenakata-kit' ) ), 429 );
	}
	set_transient( $key, $hits + 1, HOUR_IN_SECONDS );
	$f = isset( $_FILES['shot'] ) && is_array( $_FILES['shot'] ) ? $_FILES['shot'] : null; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- validated below (public like checkout)
	if ( ! $f || ! empty( $f['error'] ) || empty( $f['tmp_name'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
		wp_send_json( array( 'error' => __( 'The picture could not be uploaded. Please try again.', 'kenakata-kit' ) ), 400 );
	}
	if ( (int) $f['size'] > 4 * MB_IN_BYTES ) {
		wp_send_json( array( 'error' => __( 'The picture is too big (4 MB at most).', 'kenakata-kit' ) ), 400 );
	}
	$mimes = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
	$check = wp_check_filetype_and_ext( $f['tmp_name'], sanitize_file_name( (string) $f['name'] ), $mimes );
	$info  = @getimagesize( $f['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( empty( $check['ext'] ) || false === $info || ! in_array( $info['mime'] ?? '', $mimes, true ) ) {
		wp_send_json( array( 'error' => __( 'Please choose a JPG, PNG or WebP picture.', 'kenakata-kit' ) ), 400 );
	}
	$token = wp_generate_password( 32, false, false );
	$dest  = kk_mp_private_dir() . '/tmp-' . $token . '.' . $check['ext'];
	if ( ! @move_uploaded_file( $f['tmp_name'], $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		wp_send_json( array( 'error' => __( 'The picture could not be uploaded. Please try again.', 'kenakata-kit' ) ), 500 );
	}
	// Old unused uploads go away (no cron: cleaned here, at most once an hour).
	if ( ! get_transient( 'kk_mp_gc' ) ) {
		set_transient( 'kk_mp_gc', 1, HOUR_IN_SECONDS );
		foreach ( (array) glob( kk_mp_private_dir() . '/tmp-*' ) as $old ) {
			if ( is_file( $old ) && filemtime( $old ) < time() - 2 * DAY_IN_SECONDS ) {
				@unlink( $old ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
	}
	wp_send_json( array( 'token' => $token ) );
} );

/** Path of an uploaded (not yet used) screenshot by its token, or ''. */
function kk_mp_tmp_file( $token ) {
	$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $token );
	if ( 32 !== strlen( $token ) ) {
		return '';
	}
	foreach ( array( 'jpg', 'png', 'webp' ) as $ext ) {
		$p = kk_mp_private_dir() . '/tmp-' . $token . '.' . $ext;
		if ( is_file( $p ) ) {
			return $p;
		}
	}
	return '';
}

// Staff only: show an order's screenshot.
add_action( 'admin_post_kk_mp_shot', function () {
	$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
	if ( ! current_user_can( 'edit_shop_orders' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'kk_mp_shot' ) ) {
		wp_die( esc_html__( 'This link has expired. Go back and open it again.', 'kenakata-kit' ), '', array( 'response' => 403 ) );
	}
	$order = wc_get_order( $id );
	$file  = $order ? (string) $order->get_meta( '_kk_mp_shot_file' ) : '';
	$path  = $file ? kk_mp_private_dir() . '/' . basename( $file ) : '';
	if ( '' === $path || ! is_file( $path ) ) {
		wp_die( esc_html__( 'No screenshot.', 'kenakata-kit' ), '', array( 'response' => 404 ) );
	}
	$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	nocache_headers();
	header( 'Content-Type: ' . ( $info ? $info['mime'] : 'application/octet-stream' ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	exit;
} );

/* =========================================================================
 * The payment method
 * ====================================================================== */
add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class KK_Gateway_Mobile_Pay extends WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'kk_mobile_pay';
			$this->has_fields         = true;
			$this->method_title       = __( 'bKash / Nagad / Rocket (manual)', 'kenakata-kit' );
			$this->method_description = __( 'The customer sends money to your number and types the Transaction ID. Settings: Kenakata Kit → Mobile payment.', 'kenakata-kit' );
			$this->title              = (string) kk_opt( 'mp_title' );
			$this->description        = '';
			$this->enabled            = kk_opt( 'mp_enable' ) && kk_mp_wallets() ? 'yes' : 'no';
			$this->supports           = array( 'products' );
			$this->form_fields        = array();
		}

		/** WooCommerce's own settings screen for this method points to ours. */
		public function admin_options() {
			echo '<h2>' . esc_html( $this->method_title ) . '</h2><p>' . esc_html( $this->method_description ) . '</p><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=kenakata-kit&tab=mobilepay' ) ) . '">' . esc_html__( 'Open the settings', 'kenakata-kit' ) . '</a></p>';
		}

		public function process_admin_options() {
			return true;
		}

		public function is_available() {
			return 'yes' === $this->enabled && parent::is_available();
		}

		/** Amounts for the checkout now (cart). */
		private function cart_amounts() {
			$cart  = WC()->cart;
			$total = $cart ? (float) $cart->get_total( 'edit' ) : 0;
			$ship  = $cart ? (float) $cart->get_shipping_total() + (float) $cart->get_shipping_tax() : 0;
			return array( $total, kk_mp_amount_now( $total, $ship ) );
		}

		public function payment_fields() {
			$wallets = kk_mp_wallets();
			list( $total, $now ) = $this->cart_amounts();
			$advance = 'delivery' === kk_opt( 'mp_amount' ) && $now < $total;
			$shot    = (string) kk_opt( 'mp_screenshot' );
			echo '<div class="kk-mp" data-upload="' . esc_url( WC_AJAX::get_endpoint( 'kk_mp_upload' ) ) . '">';
			echo '<p class="kk-mp__amount">' . ( $advance
				/* translators: 1: amount to pay now 2: amount to pay on delivery */
				? sprintf( esc_html__( 'Pay %1$s now (delivery charge). The rest, %2$s, is paid in cash on delivery.', 'kenakata-kit' ), '<strong>' . wp_kses_post( wc_price( $now ) ) . '</strong>', '<strong>' . wp_kses_post( wc_price( $total - $now ) ) . '</strong>' )
				/* translators: %s: amount */
				: sprintf( esc_html__( 'Pay %s now.', 'kenakata-kit' ), '<strong>' . wp_kses_post( wc_price( $now ) ) . '</strong>' ) ) . '</p>';
			$first = (string) key( $wallets );
			$pick  = isset( $_POST['kk_mp_wallet'] ) ? sanitize_key( wp_unslash( $_POST['kk_mp_wallet'] ) ) : $first; // phpcs:ignore WordPress.Security.NonceVerification -- redisplay after checkout update
			$pick  = isset( $wallets[ $pick ] ) ? $pick : $first;
			echo '<div class="kk-mp__wallets" role="radiogroup" aria-label="' . esc_attr__( 'Pay with', 'kenakata-kit' ) . '">';
			foreach ( $wallets as $key => $w ) {
				echo '<label class="kk-mp__wallet" style="--kk-mp:' . esc_attr( $w['color'] ) . '"><input type="radio" name="kk_mp_wallet" value="' . esc_attr( $key ) . '"' . checked( $pick, $key, false ) . '><span>' . esc_html( $w['label'] ) . '</span></label>';
			}
			echo '</div>';
			foreach ( $wallets as $key => $w ) {
				echo '<div class="kk-mp__how" data-wallet="' . esc_attr( $key ) . '"' . ( $pick === $key ? '' : ' hidden' ) . '><ol>';
				/* translators: 1: wallet name 2: "Send Money" / "Cash Out" / "Payment" */
				echo '<li>' . esc_html( sprintf( __( 'Open your %1$s app and choose "%2$s".', 'kenakata-kit' ), $w['label'], $w['how'] ) ) . '</li>';
				/* translators: 1: wallet number 2: Personal / Agent / Merchant */
				echo '<li>' . sprintf( esc_html__( 'Number: %1$s (%2$s)', 'kenakata-kit' ), '<strong class="kk-mp__num">' . esc_html( $w['number'] ) . '</strong> <button type="button" class="kk-mp__copy" data-copy="' . esc_attr( $w['number'] ) . '">' . esc_html__( 'Copy', 'kenakata-kit' ) . '</button>', esc_html( $w['type'] ) ) . '</li>';
				/* translators: %s: amount */
				echo '<li>' . sprintf( esc_html__( 'Amount: %s', 'kenakata-kit' ), '<strong>' . wp_kses_post( wc_price( $now ) ) . '</strong>' ) . '</li>';
				echo '<li>' . esc_html__( 'Then type below the number you sent from and the Transaction ID (TrxID) from the confirmation message.', 'kenakata-kit' ) . '</li>';
				echo '</ol></div>';
			}
			$note = trim( (string) kk_opt( 'mp_note' ) );
			echo '' !== $note ? '<p class="kk-mp__note">' . nl2br( esc_html( $note ) ) . '</p>' : '';
			$sender = isset( $_POST['kk_mp_sender'] ) ? sanitize_text_field( wp_unslash( $_POST['kk_mp_sender'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$trx    = isset( $_POST['kk_mp_trx'] ) ? sanitize_text_field( wp_unslash( $_POST['kk_mp_trx'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			echo '<p class="form-row form-row-wide"><label for="kk_mp_sender">' . esc_html__( 'Number you sent from', 'kenakata-kit' ) . ' <abbr class="required" title="required">*</abbr></label><input type="tel" inputmode="tel" class="input-text" id="kk_mp_sender" name="kk_mp_sender" autocomplete="off" maxlength="14" placeholder="01XXXXXXXXX" value="' . esc_attr( $sender ) . '"></p>';
			echo '<p class="form-row form-row-wide"><label for="kk_mp_trx">' . esc_html__( 'Transaction ID (TrxID)', 'kenakata-kit' ) . ' <abbr class="required" title="required">*</abbr></label><input type="text" class="input-text kk-mp__trx" id="kk_mp_trx" name="kk_mp_trx" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="24" placeholder="8N7A6D5E4F" value="' . esc_attr( $trx ) . '"></p>';
			if ( 'off' !== $shot ) {
				echo '<p class="form-row form-row-wide kk-mp__shot"><label for="kk_mp_file">' . esc_html__( 'Screenshot of the payment', 'kenakata-kit' ) . ( 'required' === $shot ? ' <abbr class="required" title="required">*</abbr>' : ' <span class="optional">(' . esc_html__( 'optional', 'kenakata-kit' ) . ')</span>' ) . '</label>'
					. '<input type="file" id="kk_mp_file" accept="image/jpeg,image/png,image/webp"><input type="hidden" name="kk_mp_shot" value=""><span class="kk-mp__shot-state" role="status" aria-live="polite"></span></p>';
			}
			echo '</div>';
		}

		public function validate_fields() {
			// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce checked the checkout nonce
			$wallets = kk_mp_wallets();
			$wallet  = isset( $_POST['kk_mp_wallet'] ) ? sanitize_key( wp_unslash( $_POST['kk_mp_wallet'] ) ) : '';
			$sender  = isset( $_POST['kk_mp_sender'] ) ? kk_normalize_phone( sanitize_text_field( wp_unslash( $_POST['kk_mp_sender'] ) ) ) : '';
			$trx     = isset( $_POST['kk_mp_trx'] ) ? kk_mp_clean_trx( sanitize_text_field( wp_unslash( $_POST['kk_mp_trx'] ) ) ) : '';
			$shot    = isset( $_POST['kk_mp_shot'] ) ? sanitize_text_field( wp_unslash( $_POST['kk_mp_shot'] ) ) : '';
			// phpcs:enable
			$ok = true;
			if ( ! isset( $wallets[ $wallet ] ) ) {
				wc_add_notice( __( 'Please choose bKash, Nagad or Rocket.', 'kenakata-kit' ), 'error' );
				$ok = false;
			}
			if ( '' === $sender ) {
				wc_add_notice( __( 'Please enter the mobile number you sent the money from (11 digits).', 'kenakata-kit' ), 'error' );
				$ok = false;
			}
			if ( ! preg_match( '/^[A-Z0-9]{8,20}$/', $trx ) ) {
				wc_add_notice( __( 'Please enter the Transaction ID (TrxID) from the payment message: 8 to 20 letters and numbers.', 'kenakata-kit' ), 'error' );
				$ok = false;
			} elseif ( kk_mp_trx_used( $trx ) ) {
				wc_add_notice( __( 'This Transaction ID was already used for another order. Please check it, or contact us.', 'kenakata-kit' ), 'error' );
				$ok = false;
			}
			if ( 'required' === kk_opt( 'mp_screenshot' ) && '' === kk_mp_tmp_file( $shot ) ) {
				wc_add_notice( __( 'Please add a screenshot of the payment.', 'kenakata-kit' ), 'error' );
				$ok = false;
			}
			return $ok;
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return array( 'result' => 'failure' );
			}
			// phpcs:disable WordPress.Security.NonceVerification -- WooCommerce checked the checkout nonce
			$wallets = kk_mp_wallets();
			$wallet  = isset( $_POST['kk_mp_wallet'] ) ? sanitize_key( wp_unslash( $_POST['kk_mp_wallet'] ) ) : '';
			$sender  = isset( $_POST['kk_mp_sender'] ) ? kk_normalize_phone( sanitize_text_field( wp_unslash( $_POST['kk_mp_sender'] ) ) ) : '';
			$trx     = isset( $_POST['kk_mp_trx'] ) ? kk_mp_clean_trx( sanitize_text_field( wp_unslash( $_POST['kk_mp_trx'] ) ) ) : '';
			$token   = isset( $_POST['kk_mp_shot'] ) ? sanitize_text_field( wp_unslash( $_POST['kk_mp_shot'] ) ) : '';
			// phpcs:enable
			$ship = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
			$now  = kk_mp_amount_now( (float) $order->get_total(), $ship );
			$order->update_meta_data( '_kk_mp_wallet', $wallet );
			$order->update_meta_data( '_kk_mp_to', isset( $wallets[ $wallet ] ) ? $wallets[ $wallet ]['number'] : '' );
			$order->update_meta_data( '_kk_mp_sender', $sender );
			$order->update_meta_data( '_kk_mp_trx', $trx );
			$order->update_meta_data( '_kk_mp_amount', $now );
			$order->update_meta_data( '_kk_mp_mode', 'delivery' === kk_opt( 'mp_amount' ) && $now < (float) $order->get_total() ? 'delivery' : 'full' );
			$order->update_meta_data( '_kk_mp_verified', 'wait' );
			$tmp = kk_mp_tmp_file( $token );
			if ( '' !== $tmp ) {
				$name = 'order-' . $order->get_id() . '-' . wp_generate_password( 12, false, false ) . '.' . pathinfo( $tmp, PATHINFO_EXTENSION );
				if ( @rename( $tmp, kk_mp_private_dir() . '/' . $name ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					$order->update_meta_data( '_kk_mp_shot_file', $name );
					$order->update_meta_data( '_kk_mp_shot', 1 );
				}
			}
			$order->set_transaction_id( $trx );
			$label = isset( $wallets[ $wallet ] ) ? $wallets[ $wallet ]['label'] : $wallet;
			/* translators: 1: wallet 2: amount 3: sender number 4: TrxID */
			$note = sprintf( __( 'Customer says they paid by %1$s: %2$s from %3$s, TrxID %4$s. Check your %1$s app, then press "Payment received" or "Not received".', 'kenakata-kit' ), $label, kk_plain_price( $now ), $sender, $trx );
			$order->update_status( 'on-hold', $note );
			wc_maybe_reduce_stock_levels( $order->get_id() );
			if ( WC()->cart ) {
				WC()->cart->empty_cart();
			}
			return array( 'result' => 'success', 'redirect' => $this->get_return_url( $order ) );
		}
	}

	add_filter( 'woocommerce_payment_gateways', function ( $gateways ) {
		if ( kk_opt( 'mp_enable' ) ) {
			$gateways[] = 'KK_Gateway_Mobile_Pay';
		}
		return $gateways;
	} );
}, 20 );

/** TrxID as typed: letters and numbers only, upper case. */
function kk_mp_clean_trx( $trx ) {
	return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $trx ) );
}

/** Was this TrxID already given for another order (not cancelled / failed)? */
function kk_mp_trx_used( $trx, $except = 0 ) {
	$ids = wc_get_orders( array(
		'type'       => 'shop_order',
		'limit'      => 2,
		'return'     => 'ids',
		'status'     => array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-cancelled', 'wc-failed', 'wc-trash' ) ),
		'meta_query' => array( array( 'key' => '_kk_mp_trx', 'value' => $trx ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	) );
	return (bool) array_diff( array_map( 'absint', (array) $ids ), array( absint( $except ) ) );
}

/**
 * Staff decision: 'yes' (money arrived) or 'no' (not found). Returns true or WP_Error.
 * Received: the order moves on to Processing; with full payment it is marked paid.
 * Not received: Pending payment, with a note. Either can be changed later.
 */
function kk_mp_decide( WC_Order $order, $decision ) {
	if ( 'kk_mobile_pay' !== $order->get_payment_method() ) {
		return new WP_Error( 'kk_mp_not', __( 'This order was not paid by bKash / Nagad / Rocket.', 'kenakata-kit' ), array( 'status' => 400 ) );
	}
	$user = wp_get_current_user();
	$who  = $user && $user->exists() ? $user->display_name : '—';
	$d    = kk_mp_details( $order );
	if ( 'yes' === $decision ) {
		$order->update_meta_data( '_kk_mp_verified', 'yes' );
		$order->save();
		/* translators: 1: amount 2: user name */
		$msg = sprintf( __( 'Payment of %1$s checked and received (by %2$s).', 'kenakata-kit' ), kk_plain_price( $d['amount'] ), $who );
		if ( $d['advance'] ) {
			$order->add_order_note( $msg );
			if ( $order->has_status( array( 'on-hold', 'pending' ) ) ) {
				$order->update_status( 'processing' );
			}
		} else {
			$order->add_order_note( $msg );
			$order->payment_complete( $d['trx'] );
		}
	} elseif ( 'no' === $decision ) {
		$order->update_meta_data( '_kk_mp_verified', 'no' );
		$order->save();
		/* translators: %s: user name */
		$order->update_status( 'pending', sprintf( __( 'Payment not found in our account (checked by %s). Ask the customer to check the TrxID.', 'kenakata-kit' ), $who ) );
	} else {
		return new WP_Error( 'kk_mp_bad', 'Bad decision', array( 'status' => 400 ) );
	}
	if ( function_exists( 'kk_sd_flush_summary' ) ) {
		kk_sd_flush_summary();
	}
	return true;
}

/* =========================================================================
 * Order screen box + decisions
 * ====================================================================== */
add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}
	$d = kk_mp_details( $order );
	if ( ! $d ) {
		return;
	}
	$states = array( 'wait' => __( 'Not checked yet', 'kenakata-kit' ), 'yes' => __( 'Received', 'kenakata-kit' ), 'no' => __( 'Not received', 'kenakata-kit' ) );
	echo '<div class="kk-mp-box" style="margin-top:12px;padding:10px 12px;border:1px solid #dcdcde;border-radius:6px;background:#fff"><h3 style="margin-top:0">' . esc_html( $d['wallet'] ) . ' · ' . esc_html( $states[ $d['state'] ] ) . '</h3>';
	echo '<p style="margin:0">' . esc_html__( 'Amount', 'kenakata-kit' ) . ': <strong>' . esc_html( kk_plain_price( $d['amount'] ) ) . '</strong>' . ( $d['advance'] ? ' (' . esc_html__( 'delivery charge in advance', 'kenakata-kit' ) . ')' : '' ) . '<br>'
		. esc_html__( 'Sent from', 'kenakata-kit' ) . ': <strong>' . esc_html( $d['sender'] ) . '</strong> → ' . esc_html( $d['to'] ) . '<br>'
		. 'TrxID: <strong style="font-family:monospace;font-size:14px">' . esc_html( $d['trx'] ) . '</strong></p>';
	if ( $d['shot'] ) {
		echo '<p><a href="' . esc_url( $d['shot'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'See the screenshot', 'kenakata-kit' ) . '</a></p>';
	}
	$url = function ( $dec ) use ( $order ) {
		return wp_nonce_url( add_query_arg( array( 'action' => 'kk_mp_decide', 'order' => $order->get_id(), 'd' => $dec ), admin_url( 'admin-post.php' ) ), 'kk_mp_decide_' . $order->get_id() );
	};
	echo '<p>' . ( 'yes' !== $d['state'] ? '<a class="button button-primary" href="' . esc_url( $url( 'yes' ) ) . '">' . esc_html__( 'Payment received', 'kenakata-kit' ) . '</a> ' : '' )
		. ( 'no' !== $d['state'] ? '<a class="button" href="' . esc_url( $url( 'no' ) ) . '">' . esc_html__( 'Not received', 'kenakata-kit' ) . '</a>' : '' ) . '</p></div>';
} );

add_action( 'admin_post_kk_mp_decide', function () {
	$id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
	if ( ! current_user_can( 'edit_shop_orders' ) || ! check_admin_referer( 'kk_mp_decide_' . $id ) ) {
		wp_die( esc_html__( 'This link has expired. Go back and open it again.', 'kenakata-kit' ), '', array( 'response' => 403 ) );
	}
	$order = wc_get_order( $id );
	if ( $order ) {
		kk_mp_decide( $order, isset( $_GET['d'] ) ? sanitize_key( wp_unslash( $_GET['d'] ) ) : '' );
	}
	wp_safe_redirect( $order ? $order->get_edit_order_url() : admin_url() );
	exit;
} );

/* Checkout: styles + a small script (copy number, show the chosen wallet, upload the screenshot). */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! kk_opt( 'mp_enable' ) || ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
		return;
	}
	wp_enqueue_style( 'kenakata-kit-mp', kk_asset( 'manual-pay.css' ), array(), KK_VERSION );
	wp_enqueue_script( 'kenakata-kit-mp', kk_asset( 'manual-pay.js' ), array(), KK_VERSION, true );
	wp_localize_script( 'kenakata-kit-mp', 'kkMP', array(
		'uploading' => __( 'Uploading…', 'kenakata-kit' ),
		'done'      => __( 'Picture added ✓', 'kenakata-kit' ),
		'failed'    => __( 'The picture could not be uploaded. Please try again.', 'kenakata-kit' ),
		'copied'    => __( 'Copied', 'kenakata-kit' ),
	) );
}, 30 );

/* Thank-you page and emails: tell the customer the payment is being checked. */
function kk_mp_customer_text( WC_Order $order ) {
	$d = kk_mp_details( $order );
	if ( ! $d || 'wait' !== $d['state'] ) {
		return '';
	}
	/* translators: 1: wallet 2: TrxID */
	return sprintf( __( 'We received your %1$s payment details (TrxID %2$s). We will check the payment and confirm your order soon.', 'kenakata-kit' ), $d['wallet'], $d['trx'] );
}
add_action( 'woocommerce_thankyou_kk_mobile_pay', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	$text  = $order ? kk_mp_customer_text( $order ) : '';
	echo '' !== $text ? '<p class="kk-mp-thanks">' . esc_html( $text ) . '</p>' : '';
} );
add_action( 'woocommerce_email_before_order_table', function ( $order, $sent_to_admin, $plain ) {
	if ( $sent_to_admin || ! $order instanceof WC_Order ) {
		return;
	}
	$text = kk_mp_customer_text( $order );
	if ( '' !== $text ) {
		echo $plain ? esc_html( $text ) . "\n\n" : '<p>' . esc_html( $text ) . '</p>';
	}
}, 10, 3 );
