<?php
/**
 * After purchase: thank-you page, "Shipped" status + courier box, order tracking by phone.
 * Meta keys: _kk_courier, _kk_tracking_id, _kk_tracking_url (older _ss_* keys are still read).
 */

defined( 'ABSPATH' ) || exit;

function kk_order_meta( $order, $key ) {
	$v = $order->get_meta( '_kk_' . $key );
	return '' !== $v ? $v : $order->get_meta( '_ss_' . $key );
}

/* =========================================================================
 * Thank-you page
 * ====================================================================== */
if ( kk_opt( 'ty_enable' ) ) {
	add_filter( 'woocommerce_get_checkout_order_received_url', function ( $url, $order ) {
		$page = kk_page_url( 'ty_page', 'thank-you' );
		return $page ? add_query_arg( array( 'order' => $order->get_id(), 'key' => $order->get_order_key() ), $page ) : $url;
	}, 20, 2 );
}

function kk_thank_you_shortcode() {
	return kk_need_css( 'order' ) . kk_thank_you_html();
}

/** The thank-you box itself. */
function kk_thank_you_html() {
	$order_id = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$key      = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$order    = $order_id ? wc_get_order( $order_id ) : false;
	$track    = kk_page_url( 'tr_page', 'track-order' );

	if ( ! $order || ! $key || ! hash_equals( $order->get_order_key(), $key ) ) {
		return '<div class="kk-box kk-thanks kk-thanks--empty"><h2>' . esc_html__( 'Order not found', 'kenakata-kit' ) . '</h2><p>'
			. esc_html__( 'You can check any order with your phone number.', 'kenakata-kit' ) . '</p>'
			. ( $track ? '<a class="kk-btn kk-btn--primary" href="' . esc_url( $track ) . '">' . esc_html( kk_opt( 'ty_track_text' ) ) . '</a>' : '' ) . '</div>';
	}

	remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
	ob_start();
	do_action( 'woocommerce_thankyou', $order->get_id() );
	$hooks = ob_get_clean();

	$items = '';
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		$items  .= sprintf(
			'<li class="kk-line"><span class="kk-line__img">%s</span><span class="kk-line__name">%s <small>× %d</small></span><span class="kk-line__total">%s</span></li>',
			$product ? $product->get_image( 'thumbnail', array( 'alt' => '' ) ) : '', esc_html( $item->get_name() ), (int) $item->get_quantity(), wp_kses_post( $order->get_formatted_line_subtotal( $item ) )
		);
	}
	$totals = '';
	foreach ( $order->get_order_item_totals() as $row ) {
		$totals .= '<div class="kk-total-row"><span>' . wp_kses_post( $row['label'] ) . '</span><span>' . wp_kses_post( $row['value'] ) . '</span></div>';
	}
	$area  = $order->get_meta( '_kk_delivery_area' );
	$area  = $area ? $area : ( $order->get_meta( '_billing_delivery_area' ) ? kk_area_label( $order->get_meta( '_billing_delivery_area' ) ) : '' );
	$title = str_replace( '{name}', $order->get_billing_first_name(), kk_opt( 'ty_title' ) );

	ob_start();
	?>
	<div class="kk-thanks">
		<div class="kk-box kk-thanks__hero">
			<span class="kk-thanks__icon" aria-hidden="true">✓</span>
			<h2><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( kk_opt( 'ty_message' ) ); ?></p>
			<dl class="kk-thanks__meta">
				<div><dt><?php esc_html_e( 'Order number', 'kenakata-kit' ); ?></dt><dd>#<?php echo esc_html( $order->get_order_number() ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Date', 'kenakata-kit' ); ?></dt><dd><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Total', 'kenakata-kit' ); ?></dt><dd><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Payment', 'kenakata-kit' ); ?></dt><dd><?php echo esc_html( $order->get_payment_method_title() ); ?></dd></div>
			</dl>
			<div class="kk-thanks__actions">
				<?php if ( $track ) : ?><a class="kk-btn kk-btn--primary" href="<?php echo esc_url( add_query_arg( array( 'kk_phone' => rawurlencode( $order->get_billing_phone() ), 'kk_order' => rawurlencode( $order->get_order_number() ) ), $track ) ); ?>"><?php echo esc_html( kk_opt( 'ty_track_text' ) ); ?></a><?php endif; ?>
				<a class="kk-btn kk-btn--outline" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php echo esc_html( kk_opt( 'ty_shop_text' ) ); ?></a>
			</div>
		</div>
		<div class="kk-thanks__grid">
			<div class="kk-box">
				<h3><?php esc_html_e( 'Order summary', 'kenakata-kit' ); ?></h3>
				<ul class="kk-lines"><?php echo $items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></ul>
				<div class="kk-totals"><?php echo $totals; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></div>
			</div>
			<div class="kk-box">
				<h3><?php esc_html_e( 'Delivery details', 'kenakata-kit' ); ?></h3>
				<p class="kk-address"><strong><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></strong><br><?php echo esc_html( $order->get_billing_phone() ); ?><br><?php echo nl2br( esc_html( $order->get_billing_address_1() ) ); ?>
					<?php if ( $area ) : ?><br><span class="kk-badge"><?php echo esc_html( $area ); ?></span><?php endif; ?></p>
				<?php if ( $order->get_billing_email() ) : ?><p class="kk-muted"><?php /* translators: %s: email */ printf( esc_html__( 'A confirmation has been sent to %s.', 'kenakata-kit' ), esc_html( $order->get_billing_email() ) ); ?></p><?php endif; ?>
			</div>
		</div>
		<?php echo $hooks; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output of other plugins ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'kk_thank_you', 'kk_thank_you_shortcode' );
add_shortcode( 'ss_thank_you', 'kk_thank_you_shortcode' ); // backward compatibility

/* =========================================================================
 * "Shipped" status + courier box
 * ====================================================================== */
if ( kk_opt( 'tr_shipped_status' ) ) {
	add_action( 'init', function () {
		register_post_status( 'wc-shipped', array(
			'label'                     => _x( 'Shipped', 'Order status', 'kenakata-kit' ),
			'public'                    => false,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of orders */
			'label_count'               => _n_noop( 'Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>', 'kenakata-kit' ),
		) );
	} );
	add_filter( 'wc_order_statuses', function ( $statuses ) {
		$out = array();
		foreach ( $statuses as $k => $label ) {
			$out[ $k ] = $label;
			if ( 'wc-processing' === $k ) {
				$out['wc-shipped'] = _x( 'Shipped', 'Order status', 'kenakata-kit' );
			}
		}
		return $out;
	} );
	add_filter( 'woocommerce_order_is_paid_statuses', function ( $s ) { $s[] = 'shipped'; return $s; } );
	add_filter( 'woocommerce_reports_order_statuses', function ( $s ) { if ( is_array( $s ) ) { $s[] = 'shipped'; } return $s; } );
	add_filter( 'bulk_actions-woocommerce_page_wc-orders', function ( $a ) { $a['mark_shipped'] = __( 'Change status to shipped', 'kenakata-kit' ); return $a; } );
	add_filter( 'bulk_actions-edit-shop_order', function ( $a ) { $a['mark_shipped'] = __( 'Change status to shipped', 'kenakata-kit' ); return $a; } );
}

add_action( 'add_meta_boxes', function () {
	$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	add_meta_box( 'kk-courier', __( 'Courier & tracking', 'kenakata-kit' ), 'kk_courier_metabox', $screen, 'side', 'high' );
} );

function kk_courier_metabox( $post_or_order ) {
	$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
	if ( ! $order ) {
		return;
	}
	wp_nonce_field( 'kk_courier_save', 'kk_courier_nonce' );
	// When a courier is turned on for one-tap booking, courier / tracking ID / link are filled in by the booking itself: no typing.
	if ( function_exists( 'kk_courier_enabled' ) && kk_courier_enabled() ) {
		kk_courier_metabox_booking( $order );
		return;
	}
	$courier = kk_order_meta( $order, 'courier' );
	?>
	<p><label for="kk_courier"><strong><?php esc_html_e( 'Courier', 'kenakata-kit' ); ?></strong></label><br>
		<select name="kk_courier" id="kk_courier" style="width:100%">
			<option value=""><?php esc_html_e( '— Select —', 'kenakata-kit' ); ?></option>
			<?php foreach ( kk_couriers() as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $courier, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
		</select></p>
	<p><label for="kk_tracking_id"><strong><?php esc_html_e( 'Tracking / consignment ID', 'kenakata-kit' ); ?></strong></label><br>
		<input type="text" name="kk_tracking_id" id="kk_tracking_id" value="<?php echo esc_attr( kk_order_meta( $order, 'tracking_id' ) ); ?>" style="width:100%"></p>
	<p><label for="kk_tracking_url"><strong><?php esc_html_e( 'Tracking link', 'kenakata-kit' ); ?></strong></label><br>
		<input type="url" name="kk_tracking_url" id="kk_tracking_url" placeholder="https://" value="<?php echo esc_attr( kk_order_meta( $order, 'tracking_url' ) ); ?>" style="width:100%"></p>
	<?php if ( kk_opt( 'tr_auto_shipped' ) ) : ?><p class="description"><?php esc_html_e( 'Saving a tracking link marks a Processing order as Shipped and notifies the customer.', 'kenakata-kit' ); ?></p>
	<?php endif; ?>
	<?php
}

/** One-tap courier booking inside the WooCommerce order screen: one button per turned-on courier, tracking shown read-only. */
function kk_courier_metabox_booking( WC_Order $order ) {
	$list = function_exists( 'kk_courier_list' ) ? kk_courier_list() : array();
	if ( ! $list || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}
	kk_courier_load();
	$booking = kk_courier_booking_view( $order );
	if ( $booking ) {
		/* translators: %s: courier name */
		echo '<p><strong>' . esc_html( sprintf( __( 'Sent to %s', 'kenakata-kit' ), $booking['label'] ) ) . '</strong><br>' . esc_html( $booking['tracking_code'] ? $booking['tracking_code'] : $booking['consignment_id'] );
		if ( $booking['status_label'] ) {
			echo '<br>' . esc_html( $booking['status_label'] );
		}
		echo '</p>';
		$url = (string) kk_order_meta( $order, 'tracking_url' );
		if ( '' !== $url ) {
			echo '<p><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open tracking page', 'kenakata-kit' ) . '</a></p>';
		}
		if ( ! $booking['can_rebook'] ) {
			return;
		}
	} elseif ( 'cod' !== $order->get_payment_method() && ! $order->has_status( array( 'processing', 'on-hold', 'pending' ) ) ) {
		echo '<p class="description">' . esc_html__( 'Tracking is filled in automatically when the order is sent to a courier.', 'kenakata-kit' ) . '</p>';
	}
	$d    = kk_courier_defaults( $order );
	$cod  = kk_plain_price( $d['cod_amount'] );
	$asks = array();
	echo '<p class="description" id="kk-courier-hint">' . esc_html__( 'Courier, tracking ID and link are filled in automatically when you send the order.', 'kenakata-kit' ) . '</p>';
	foreach ( $list as $c ) {
		/* translators: %s: courier name */
		$label = sprintf( __( 'Send to %s', 'kenakata-kit' ), $c['label'] );
		$asks[ $c['key'] ] = sprintf(
			/* translators: 1: courier name 2: customer name 3: phone 4: address 5: amount to collect */
			__( "Send this order to %1\$s?\n\nName: %2\$s\nPhone: %3\$s\nAddress: %4\$s\nCollect: %5\$s", 'kenakata-kit' ),
			$c['label'], $d['name'], $d['phone'], $d['address'], $cod
		);
		echo '<p><button type="button" class="button button-primary kk-courier-send" data-courier="' . esc_attr( $c['key'] ) . '" style="width:100%">' . esc_html( $label ) . '</button></p>';
	}
	echo '<p class="description" id="kk-courier-msg" role="status" aria-live="polite"></p>';
	?>
	<script>
	(function () {
		var asks = <?php echo wp_json_encode( $asks ); ?>, msg = document.getElementById('kk-courier-msg');
		var btns = document.querySelectorAll('.kk-courier-send');
		btns.forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!window.confirm(asks[btn.getAttribute('data-courier')])) { return; }
				btns.forEach(function (b) { b.disabled = true; }); msg.textContent = '…';
				fetch(<?php echo wp_json_encode( esc_url_raw( rest_url( 'kenakata/v1/courier/book/' . $order->get_id() ) ) ); ?>, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>, 'Content-Type': 'application/json' }, body: JSON.stringify({ courier: btn.getAttribute('data-courier') }) })
					.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
					.then(function (res) { if (res.ok) { window.location.reload(); } else { btns.forEach(function (b) { b.disabled = false; }); msg.textContent = (res.d && res.d.message) || 'Error'; } })
					.catch(function () { btns.forEach(function (b) { b.disabled = false; }); msg.textContent = 'Network error'; });
			});
		});
	}());
	</script>
	<?php
}

add_action( 'woocommerce_process_shop_order_meta', function ( $order_id ) {
	if ( empty( $_POST['kk_courier_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['kk_courier_nonce'] ) ), 'kk_courier_save' ) || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}
	$order = wc_get_order( $order_id );
	if ( ! $order || ! ( isset( $_POST['kk_courier'] ) || isset( $_POST['kk_tracking_id'] ) || isset( $_POST['kk_tracking_url'] ) ) ) {
		return; // fields hidden (courier booking is on): nothing to save, and nothing may be wiped
	}
	kk_save_order_tracking(
		$order,
		isset( $_POST['kk_courier'] ) ? wp_unslash( $_POST['kk_courier'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized inside
		isset( $_POST['kk_tracking_id'] ) ? wp_unslash( $_POST['kk_tracking_id'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized inside
		isset( $_POST['kk_tracking_url'] ) ? wp_unslash( $_POST['kk_tracking_url'] ) : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized inside
	);
}, 50 );

/**
 * Save courier, tracking ID and tracking link on an order (order edit screen and store dashboard).
 * A new tracking link notifies the customer and, when enabled, marks the order as Shipped. Saves the order.
 */
function kk_save_order_tracking( WC_Order $order, $courier, $tracking_id, $tracking_url ) {
	$couriers = kk_couriers();
	$courier  = sanitize_key( (string) $courier );
	$courier  = isset( $couriers[ $courier ] ) ? $courier : '';
	$id       = sanitize_text_field( (string) $tracking_id );
	$url      = esc_url_raw( (string) $tracking_url );
	if ( '' === $url && $courier && '' !== $id ) { // the courier's own tracking page, made from the ID
		$url = kk_courier_track_link( $courier, $id, $order->get_billing_phone() );
	}
	$old_url  = kk_order_meta( $order, 'tracking_url' );

	$order->update_meta_data( '_kk_courier', $courier );
	$order->update_meta_data( '_kk_tracking_id', $id );
	$order->update_meta_data( '_kk_tracking_url', $url );

	if ( $url && $url !== $old_url && kk_opt( 'tr_auto_shipped' ) ) {
		$name = $courier ? $couriers[ $courier ] : __( 'our courier partner', 'kenakata-kit' );
		/* translators: 1: courier name 2: tracking link */
		$order->add_order_note( sprintf( __( 'Your parcel has been handed to %1$s. Track it here: %2$s', 'kenakata-kit' ), $name, $url ), true );
		if ( kk_opt( 'tr_shipped_status' ) && $order->has_status( array( 'processing', 'on-hold', 'pending' ) ) ) {
			$order->set_status( 'shipped', __( 'Courier tracking added.', 'kenakata-kit' ) );
		}
	}
	$order->save();
}

/**
 * Orders placed with a phone number (any common spelling: 01…, +8801…, 8801…, 1…), newest first.
 * Only orders whose phone really matches are returned. Used by order tracking and the store dashboard.
 */
function kk_orders_by_phone( $phone, $limit = 10 ) {
	$phone = kk_normalize_phone( $phone );
	if ( ! $phone ) {
		return array();
	}
	$orders = array();
	foreach ( array_unique( array( $phone, '+88' . $phone, '88' . $phone, ltrim( $phone, '0' ) ) ) as $variant ) {
		foreach ( wc_get_orders( array( 'billing_phone' => $variant, 'limit' => $limit, 'orderby' => 'date', 'order' => 'DESC', 'status' => array_keys( wc_get_order_statuses() ) ) ) as $o ) {
			$their = kk_clean_phone( $o->get_billing_phone() );
			$their = $their ? $their : kk_normalize_phone( $o->get_billing_phone() );
			if ( $their === $phone ) {
				$orders[ $o->get_id() ] = $o;
			}
		}
	}
	krsort( $orders );
	return array_slice( $orders, 0, $limit, true );
}

/* =========================================================================
 * Order tracking by phone
 * ====================================================================== */
function kk_status_view( WC_Order $order ) {
	switch ( $order->get_status() ) {
		case 'pending':
		case 'on-hold':
			return array( 'received', __( 'Order received', 'kenakata-kit' ), kk_opt( 'tr_text_received' ) );
		case 'processing':
			return array( 'processing', __( 'Processing', 'kenakata-kit' ), kk_opt( 'tr_text_processing' ) );
		case 'shipped':
			return array( 'shipped', __( 'With courier', 'kenakata-kit' ), kk_opt( 'tr_text_shipped' ) );
		case 'completed':
			return array( 'done', __( 'Delivered', 'kenakata-kit' ), kk_opt( 'tr_text_completed' ) );
		case 'cancelled':
		case 'failed':
			return array( 'cancelled', __( 'Cancelled', 'kenakata-kit' ), __( 'This order was cancelled.', 'kenakata-kit' ) );
		case 'refunded':
			return array( 'cancelled', __( 'Refunded', 'kenakata-kit' ), __( 'This order was refunded.', 'kenakata-kit' ) );
		default:
			return array( 'received', wc_get_order_status_name( $order->get_status() ), '' );
	}
}

function kk_tracking_shortcode() {
	return kk_need_css( 'order' ) . kk_tracking_html();
}

/** The tracking form and results. */
function kk_tracking_html() {
	wp_enqueue_script( 'kenakata-kit' ); // live phone check
	$param = isset( $_GET['kk_phone'] ) ? 'kk_phone' : 'ss_phone'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$raw   = isset( $_GET[ $param ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$phone = kk_clean_phone( $raw );
	$need  = (bool) kk_opt( 'tr_require_order' );
	$ono   = isset( $_GET['kk_order'] ) ? preg_replace( '/[^0-9A-Za-z-]/', '', kk_digits( sanitize_text_field( wp_unslash( $_GET['kk_order'] ) ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$ono   = ltrim( $ono, '#' );
	$out   = '<form class="kk-box kk-track-form" method="get" action="' . esc_url( get_permalink() ) . '">'
		. '<label for="kk_phone">' . esc_html( kk_opt( 'tr_form_label' ) ) . '</label>'
		. '<div class="kk-track-form__row"><input id="kk_phone" name="kk_phone" type="tel"' . kk_attr_string( kk_phone_input_attrs() ) . ' required placeholder="' . esc_attr( kk_opt( 'co_phone_placeholder' ) ) . '" value="' . esc_attr( $raw ) . '">'
		. ( $need ? '' : '<button type="submit" class="kk-btn kk-btn--primary">' . esc_html( kk_opt( 'tr_button_text' ) ) . '</button>' ) . '</div>'
		. ( $need ? '<label for="kk_order">' . esc_html__( 'Order number', 'kenakata-kit' ) . '</label><div class="kk-track-form__row"><input id="kk_order" name="kk_order" type="text" inputmode="numeric" required autocomplete="off" value="' . esc_attr( $ono ) . '">'
			. '<button type="submit" class="kk-btn kk-btn--primary">' . esc_html( kk_opt( 'tr_button_text' ) ) . '</button></div>' : '' )
		. '</form>';

	if ( ! $raw || ( $need && '' === $ono ) ) {
		return $out;
	}
	if ( ! $phone ) {
		return $out . '<p class="kk-notice kk-notice--error" role="alert">' . esc_html( kk_opt( 'co_phone_error' ) ) . '</p>';
	}
	$ip_key = 'kk_track_' . md5( kk_client_ip() );
	$hits   = (int) get_transient( $ip_key );
	if ( $hits >= absint( kk_opt( 'tr_rate_limit' ) ) ) {
		return $out . '<p class="kk-notice kk-notice--error" role="alert">' . esc_html__( 'Too many attempts. Please try again in a few minutes.', 'kenakata-kit' ) . '</p>';
	}
	set_transient( $ip_key, $hits + 1, 10 * MINUTE_IN_SECONDS );

	$variants = array_unique( array( $phone, '+88' . $phone, '88' . $phone, ltrim( $phone, '0' ) ) );
	$orders   = array();
	foreach ( $variants as $variant ) {
		foreach ( wc_get_orders( array( 'billing_phone' => $variant, 'limit' => 10, 'orderby' => 'date', 'order' => 'DESC', 'status' => array_keys( wc_get_order_statuses() ) ) ) as $o ) {
			$their = kk_clean_phone( $o->get_billing_phone() );
			$their = $their ? $their : kk_normalize_phone( $o->get_billing_phone() );
			if ( $their === $phone ) { // never show an order unless its phone really matches
				$orders[ $o->get_id() ] = $o;
			}
		}
	}
	if ( $need && ctype_digit( $ono ) && ( $direct = wc_get_order( absint( $ono ) ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.Found -- an older order than the last 10
		$their = kk_clean_phone( $direct->get_billing_phone() );
		$their = $their ? $their : kk_normalize_phone( $direct->get_billing_phone() );
		if ( $their === $phone && ! $direct instanceof WC_Order_Refund ) {
			$orders[ $direct->get_id() ] = $direct;
		}
	}
	if ( $need ) { // only the order with this number (order numbers may differ from IDs with sequential-number plugins)
		$orders = array_filter( $orders, function ( $o ) use ( $ono ) { return (string) $o->get_order_number() === $ono || (string) $o->get_id() === $ono; } );
	}
	krsort( $orders );
	$orders   = array_slice( $orders, 0, 10 );
	$couriers = kk_couriers();
	$steps    = array( 'received' => __( 'Received', 'kenakata-kit' ), 'processing' => __( 'Processing', 'kenakata-kit' ), 'shipped' => __( 'With courier', 'kenakata-kit' ), 'done' => __( 'Delivered', 'kenakata-kit' ) );

	$out .= '<div class="kk-track-results" aria-live="polite">';
	if ( ! $orders ) {
		$out .= '<p class="kk-notice">' . esc_html__( 'No orders found for this phone number.', 'kenakata-kit' ) . '</p>';
	}
	$live_left = 3; // at most 3 courier look-ups per page view
	foreach ( $orders as $order ) {
		$live = kk_tracking_live_status( $order, $live_left );
		if ( $live && ! empty( $live['refreshed'] ) ) {
			$order = wc_get_order( $order->get_id() ); // the status may just have become Completed
		}
		list( $state, $label, $text ) = kk_status_view( $order );
		$courier = kk_order_meta( $order, 'courier' );
		$t_url   = kk_order_meta( $order, 'tracking_url' );
		$t_id    = kk_order_meta( $order, 'tracking_id' );
		$names   = array();
		foreach ( $order->get_items() as $item ) {
			$names[] = $item->get_name() . ' × ' . $item->get_quantity();
		}
		$reach = array_search( $state, array_keys( $steps ), true );

		$out .= '<article class="kk-box kk-order kk-order--' . esc_attr( $state ) . '">';
		$out .= '<header class="kk-order__head"><div><h3>' . esc_html__( 'Order', 'kenakata-kit' ) . ' #' . esc_html( $order->get_order_number() ) . '</h3><span class="kk-muted">' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . ' · ' . wp_kses_post( $order->get_formatted_order_total() ) . '</span></div>'
			. '<span class="kk-status kk-status--' . esc_attr( $state ) . '">' . esc_html( $label ) . '</span></header>';
		if ( 'cancelled' !== $state ) {
			$out .= '<ol class="kk-steps">';
			$i    = 0;
			foreach ( $steps as $step ) {
				$cls  = false !== $reach && $i <= $reach ? ' is-done' : '';
				$cls .= false !== $reach && $i === $reach ? ' is-current' : '';
				$out .= '<li class="kk-step' . $cls . '"' . ( $i === $reach ? ' aria-current="step"' : '' ) . '>' . esc_html( $step ) . '</li>';
				$i++;
			}
			$out .= '</ol>';
		}
		$out .= $text ? '<p>' . esc_html( $text ) . '</p>' : '';
		$out .= '<p class="kk-muted kk-order__items">' . esc_html( implode( ', ', $names ) ) . '</p>';
		if ( $t_url ) {
			$out .= '<div class="kk-order__courier"><div><strong>' . esc_html( isset( $couriers[ $courier ] ) ? $couriers[ $courier ] : __( 'Courier', 'kenakata-kit' ) ) . '</strong>'
				. ( $t_id ? '<br><span class="kk-muted">' . esc_html__( 'Tracking ID', 'kenakata-kit' ) . ': ' . esc_html( $t_id ) . '</span>' : '' )
				. ( $live && '' !== $live['label'] ? '<br><span class="kk-order__live">' . esc_html__( 'Courier says', 'kenakata-kit' ) . ': <strong>' . esc_html( $live['label'] ) . '</strong></span>' : '' ) . '</div>'
				. '<a class="kk-btn kk-btn--primary" href="' . esc_url( $t_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Track parcel', 'kenakata-kit' ) . ' <span class="screen-reader-text">' . esc_html__( '(opens courier website in a new tab)', 'kenakata-kit' ) . '</span></a></div>';
		}
		$out .= '</article>';
	}
	return $out . '</div>';
}
/**
 * Live courier status for the tracking page (setting "Live courier status"): asks the courier only when the customer
 * looks, at most once per 30 minutes per order, and only for parcels booked through Courier booking (Pro).
 *
 * @param WC_Order $order Order.
 * @param int      $left  Look-ups still allowed on this page view (counted down).
 * @return array|null array( label, refreshed ) or null.
 */
function kk_tracking_live_status( WC_Order $order, &$left ) {
	if ( ! kk_opt( 'tr_live_courier' ) || ! function_exists( 'kk_courier_load' ) ) {
		return null;
	}
	$b = $order->get_meta( '_kk_courier_booking' );
	if ( ! is_array( $b ) || empty( $b['consignment_id'] ) || empty( $b['provider'] ) ) {
		return null;
	}
	kk_courier_load();
	$refreshed = false;
	$fresh     = ! empty( $b['checked_at'] ) && ( time() - (int) $b['checked_at'] ) < 30 * MINUTE_IN_SECONDS;
	$final     = $order->has_status( array( 'completed', 'cancelled', 'refunded', 'failed' ) );
	if ( ! $fresh && ! $final && $left > 0 && kk_lock( 'kk_trk_' . $order->get_id(), 60 ) ) {
		$left--;
		$view = kk_courier_refresh_status( $order );
		kk_unlock( 'kk_trk_' . $order->get_id() );
		if ( ! is_wp_error( $view ) ) {
			$b['status'] = $view['status'];
			$refreshed   = true;
		}
	}
	return array(
		'label'     => kk_courier_status_label( (string) $b['provider'], (string) ( $b['status'] ?? '' ) ),
		'refreshed' => $refreshed,
	);
}

// Tracking results are personal and change: never keep them in a page cache.
// The thank-you page (?order=…&key=…) shows the customer's name, phone and address: never in a page cache either (3.25.2).
add_action( 'template_redirect', function () {
	if ( isset( $_GET['kk_phone'] ) || isset( $_GET['ss_phone'] ) || ( isset( $_GET['order'], $_GET['key'] ) && 0 === strpos( (string) wp_unslash( $_GET['key'] ), 'wc_order_' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'Kenakata Kit order tracking' );
		nocache_headers();
	}
} );

add_shortcode( 'kk_order_tracking', 'kk_tracking_shortcode' );
add_shortcode( 'ss_order_tracking', 'kk_tracking_shortcode' ); // backward compatibility
