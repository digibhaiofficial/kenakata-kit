<?php
/**
 * Store dashboard REST API: /wp-json/kenakata/v1/sd/*
 * Cookie login + REST nonce (WordPress core). Every route needs the kk_store_dashboard capability plus the
 * WordPress/WooCommerce capability of the thing it touches (orders, products, posts, uploads).
 * Only WooCommerce CRUD functions are used, so it works with HPOS and the classic order storage.
 */

defined( 'ABSPATH' ) || exit;

if ( ! kk_opt( 'sd_enable' ) ) {
	return;
}

/** Permission callback: dashboard access + one capability. */
function kk_sd_perm( $cap ) {
	return function () use ( $cap ) {
		return is_user_logged_in() && current_user_can( KK_SD_CAP ) && current_user_can( $cap );
	};
}

function kk_sd_error( $code, $message, $status = 400 ) {
	return new WP_Error( 'kk_sd_' . $code, $message, array( 'status' => $status ) );
}

/** Plain text money ("৳ 2,350"). */
function kk_sd_money( $amount ) {
	return trim( html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount ) ), ENT_QUOTES, 'UTF-8' ) );
}

function kk_sd_date( $date ) {
	if ( ! $date instanceof WC_DateTime ) {
		return null;
	}
	return array(
		'ts'   => $date->getTimestamp(),
		'text' => wc_format_datetime( $date, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
	);
}

function kk_sd_plain( $html ) {
	return trim( html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) );
}

/**
 * HTML for the dashboard text editor. Classic content keeps paragraphs as blank lines (WordPress adds <p> when
 * showing it), so add the <p> tags here; block-editor content is returned unchanged.
 */
function kk_sd_editable_html( $content ) {
	$content = (string) $content;
	return '' === trim( $content ) || has_blocks( $content ) ? $content : wpautop( $content );
}

/** Body parameters (JSON). */
function kk_sd_body( WP_REST_Request $req ) {
	$data = $req->get_json_params();
	return is_array( $data ) ? $data : array();
}

function kk_sd_page_args( WP_REST_Request $req, $per_default = 20 ) {
	$page = max( 1, absint( $req->get_param( 'page' ) ) );
	$per  = absint( $req->get_param( 'per_page' ) );
	$per  = $per ? min( 50, $per ) : $per_default;
	return array( $page, $per );
}

/** Image URL of an attachment ('' when missing). */
function kk_sd_img( $id, $size = 'thumbnail' ) {
	$url = $id ? wp_get_attachment_image_url( absint( $id ), $size ) : '';
	return $url ? $url : '';
}

/** Attachment IDs from a list, images only. */
function kk_sd_clean_image_ids( $ids ) {
	$out = array();
	foreach ( (array) $ids as $id ) {
		$id = absint( $id );
		if ( $id && 'attachment' === get_post_type( $id ) && wp_attachment_is_image( $id ) && ! in_array( $id, $out, true ) ) {
			$out[] = $id;
		}
	}
	return $out;
}

/* =========================================================================
 * Orders
 * ====================================================================== */

/** Statuses that count as real orders / sales. */
function kk_sd_sale_statuses() {
	return array_values( array_diff( array_keys( kk_sd_statuses() ), array( 'failed', 'cancelled', 'refunded', 'trash' ) ) );
}

function kk_sd_order_area( WC_Order $order ) {
	$area = (string) $order->get_meta( '_kk_delivery_area' );
	if ( '' === $area && $order->get_meta( '_billing_delivery_area' ) && function_exists( 'kk_area_label' ) ) {
		$area = (string) kk_area_label( $order->get_meta( '_billing_delivery_area' ) );
	}
	return $area;
}

/** One-line delivery address (address lines, city, district/state name). */
function kk_sd_order_address( WC_Order $order ) {
	$state = (string) $order->get_billing_state();
	if ( '' !== $state && WC()->countries ) {
		$states = WC()->countries->get_states( $order->get_billing_country() );
		$state  = is_array( $states ) && isset( $states[ $state ] ) ? html_entity_decode( $states[ $state ], ENT_QUOTES, 'UTF-8' ) : $state;
	}
	return implode( ', ', array_filter( array_map( 'trim', array( $order->get_billing_address_1(), $order->get_billing_address_2(), $order->get_billing_city(), $state ) ) ) );
}

function kk_sd_order_brief( WC_Order $order ) {
	$names = array();
	foreach ( $order->get_items() as $item ) {
		$names[] = $item->get_name() . ' × ' . $item->get_quantity();
	}
	$couriers = kk_couriers();
	$courier  = kk_order_meta( $order, 'courier' );
	$phone    = $order->get_billing_phone();
	$name     = trim( $order->get_formatted_billing_full_name() );
	return array(
		'id'           => $order->get_id(),
		'number'       => (string) $order->get_order_number(),
		'date'         => kk_sd_date( $order->get_date_created() ),
		'status'       => $order->get_status(),
		'status_label' => kk_sd_status_label( $order->get_status() ),
		'total'        => kk_sd_money( $order->get_total() ),
		'name'         => '' !== $name ? $name : trim( $order->get_formatted_shipping_full_name() ),
		'phone'        => $phone,
		'wa'           => $phone ? 'https://wa.me/' . kk_intl_phone( $phone ) : '',
		'area'         => kk_sd_order_area( $order ),
		'items'        => $names,
		'items_count'  => (int) $order->get_item_count(),
		'payment'      => $order->get_payment_method_title(),
		'courier'      => isset( $couriers[ $courier ] ) ? $couriers[ $courier ] : '',
		'tracking_id'  => (string) kk_order_meta( $order, 'tracking_id' ),
	);
}

function kk_sd_order_full( WC_Order $order ) {
	$out   = kk_sd_order_brief( $order );
	$lines = array();
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		$meta    = wc_display_item_meta( $item, array( 'echo' => false, 'before' => '', 'after' => '', 'separator' => ', ', 'label_before' => '', 'label_after' => ': ', 'autop' => false ) );
		$lines[] = array(
			'name'  => $item->get_name(),
			'qty'   => (int) $item->get_quantity(),
			'total' => kk_sd_money( (float) $item->get_total() + (float) $item->get_total_tax() ),
			'meta'  => kk_sd_plain( $meta ),
			'sku'   => $product ? (string) $product->get_sku() : '',
			'image' => $product ? kk_sd_img( $product->get_image_id() ? $product->get_image_id() : ( $product->get_parent_id() ? get_post_thumbnail_id( $product->get_parent_id() ) : 0 ) ) : '',
			'product_id' => $product ? ( $product->get_parent_id() ? $product->get_parent_id() : $product->get_id() ) : 0,
		);
	}
	$notes = array();
	foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id(), 'order_by' => 'date_created', 'order' => 'DESC' ) ) as $note ) {
		$notes[] = array(
			'content'  => kk_sd_plain( $note->content ),
			'date'     => kk_sd_date( $note->date_created ),
			'customer' => (bool) $note->customer_note,
			'by'       => 'system' === $note->added_by ? '' : (string) $note->added_by,
		);
	}
	$history = array( 'total' => 0, 'completed' => 0, 'cancelled' => 0 );
	if ( $order->get_billing_phone() ) {
		foreach ( kk_orders_by_phone( $order->get_billing_phone(), 50 ) as $o ) {
			$history['total']++;
			if ( $o->has_status( 'completed' ) ) {
				$history['completed']++;
			} elseif ( $o->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ) {
				$history['cancelled']++;
			}
		}
	}
	$discount = (float) $order->get_discount_total() + (float) $order->get_discount_tax();
	$out      = array_merge( $out, array(
		'lines'         => $lines,
		'totals'        => array(
			'subtotal' => kk_sd_money( $order->get_subtotal() ),
			'shipping' => (float) $order->get_shipping_total() > 0 ? kk_sd_money( (float) $order->get_shipping_total() + (float) $order->get_shipping_tax() ) : '',
			'shipping_method' => $order->get_shipping_method(),
			'discount' => $discount > 0 ? kk_sd_money( $discount ) : '',
			'refunded' => (float) $order->get_total_refunded() > 0 ? kk_sd_money( $order->get_total_refunded() ) : '',
			'total'    => kk_sd_money( $order->get_total() ),
			'to_collect' => 'cod' === $order->get_payment_method() && ! $order->get_date_paid() ? kk_sd_money( (float) $order->get_total() - (float) $order->get_total_refunded() ) : '',
			'cod'      => 'cod' === $order->get_payment_method(),
		),
		'address'       => kk_sd_order_address( $order ),
		'email'         => $order->get_billing_email(),
		'customer_note' => $order->get_customer_note(),
		'notes'         => $notes,
		'history'       => $history,
		'courier_key'   => (string) kk_order_meta( $order, 'courier' ),
		'tracking_url'  => (string) kk_order_meta( $order, 'tracking_url' ),
		'edit_url'      => current_user_can( 'manage_woocommerce' ) ? $order->get_edit_order_url() : '',
	) );
	// One-tap courier booking: what was booked, or what the confirmation box starts with (courier code loads only here).
	$out['booking']  = null;
	$out['dispatch'] = null;
	if ( $order->get_meta( '_kk_courier_booking' ) || kk_courier_list() ) {
		kk_courier_load();
		$out['booking'] = kk_courier_booking_view( $order );
		if ( kk_courier_list() && ( ! $out['booking'] || $out['booking']['can_rebook'] ) ) {
			$out['dispatch'] = kk_courier_defaults( $order );
		}
	}
	return $out;
}

/** The order of a request, or an error. Refunds and other order types are not orders here. */
function kk_sd_get_order( $id ) {
	$order = wc_get_order( absint( $id ) );
	if ( ! $order || ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() ) {
		return kk_sd_error( 'not_found', __( 'Not found.', 'kenakata-kit' ), 404 );
	}
	return $order;
}

function kk_sd_orders_list( WP_REST_Request $req ) {
	list( $page, $per ) = kk_sd_page_args( $req );
	$statuses = kk_sd_statuses();
	$status   = sanitize_key( (string) $req->get_param( 'status' ) );
	$status   = isset( $statuses[ $status ] ) ? $status : '';
	$search   = trim( sanitize_text_field( (string) $req->get_param( 'search' ) ) );

	if ( '' !== $search ) {
		$ids   = array();
		$plain = ltrim( $search, '#' );
		if ( ctype_digit( $plain ) ) {
			$o = wc_get_order( absint( $plain ) );
			if ( $o && $o instanceof WC_Order && 'shop_order' === $o->get_type() ) {
				$ids[] = $o->get_id();
			}
		}
		$phone = kk_normalize_phone( $search );
		if ( $phone ) {
			$ids = array_merge( $ids, array_keys( kk_orders_by_phone( $phone, 100 ) ) );
		} else {
			$ids = array_merge( $ids, array_map( 'absint', (array) wc_order_search( $search ) ) );
		}
		$ids    = array_values( array_unique( array_filter( $ids ) ) );
		$orders = array();
		foreach ( $ids as $id ) {
			$o = wc_get_order( $id );
			if ( $o && $o instanceof WC_Order && 'shop_order' === $o->get_type() && ( '' === $status || $o->has_status( $status ) ) && isset( $statuses[ $o->get_status() ] ) ) {
				$orders[] = $o;
			}
		}
		usort( $orders, function ( $a, $b ) { return $b->get_id() - $a->get_id(); } );
		$total  = count( $orders );
		$orders = array_slice( $orders, ( $page - 1 ) * $per, $per );
		$pages  = (int) ceil( $total / $per );
	} else {
		$res    = wc_get_orders( array(
			'type'     => 'shop_order',
			'status'   => '' !== $status ? array( $status ) : array_keys( $statuses ),
			'limit'    => $per,
			'page'     => $page,
			'paginate' => true,
			'orderby'  => 'date',
			'order'    => 'DESC',
		) );
		$orders = $res->orders;
		$total  = (int) $res->total;
		$pages  = (int) $res->max_num_pages;
	}
	return array(
		'items' => array_map( 'kk_sd_order_brief', $orders ),
		'total' => $total,
		'pages' => $pages,
		'page'  => $page,
	);
}

function kk_sd_order_update( WP_REST_Request $req ) {
	$order = kk_sd_get_order( $req['id'] );
	if ( is_wp_error( $order ) ) {
		return $order;
	}
	$data = kk_sd_body( $req );

	if ( array_key_exists( 'tracking_url', $data ) || array_key_exists( 'courier', $data ) || array_key_exists( 'tracking_id', $data ) ) {
		$url = trim( (string) ( $data['tracking_url'] ?? '' ) );
		if ( '' !== $url && ! wp_http_validate_url( $url ) ) {
			return kk_sd_error( 'bad_url', __( 'Please enter a full link starting with https://', 'kenakata-kit' ) );
		}
		kk_save_order_tracking( $order, $data['courier'] ?? '', $data['tracking_id'] ?? '', $url );
		$order = wc_get_order( $order->get_id() );
	}
	if ( ! empty( $data['status'] ) ) {
		$status = sanitize_key( (string) $data['status'] );
		if ( ! isset( kk_sd_statuses()[ $status ] ) ) {
			return kk_sd_error( 'bad_status', __( 'Unknown order status.', 'kenakata-kit' ) );
		}
		if ( ! $order->has_status( $status ) ) {
			$order->update_status( $status, __( 'Status changed in the store dashboard.', 'kenakata-kit' ), true );
		}
	}
	$note = isset( $data['note'] ) ? trim( sanitize_textarea_field( (string) $data['note'] ) ) : '';
	if ( '' !== $note ) {
		$order->add_order_note( $note, ! empty( $data['note_customer'] ) ? 1 : 0, true );
	}
	kk_sd_flush_summary();
	return kk_sd_order_full( wc_get_order( $order->get_id() ) );
}

/* =========================================================================
 * Summary (home screen)
 * ====================================================================== */

/** Products (and variations) that are low on stock or out of stock. */
function kk_sd_stock_query( $which, $page = 1, $per = 20 ) {
	if ( 'out' === $which ) {
		$meta = array( array( 'key' => '_stock_status', 'value' => 'outofstock' ) );
	} else {
		$limit = absint( kk_opt( 'sd_low_stock' ) );
		if ( $limit < 1 ) {
			return null;
		}
		$meta = array(
			'relation' => 'AND',
			array( 'key' => '_manage_stock', 'value' => 'yes' ),
			array( 'key' => '_stock', 'value' => $limit, 'compare' => '<=', 'type' => 'NUMERIC' ),
			array( 'key' => '_stock', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
		);
	}
	return new WP_Query( array(
		'post_type'      => array( 'product', 'product_variation' ),
		'post_status'    => array( 'publish', 'private' ),
		'posts_per_page' => $per,
		'paged'          => $page,
		'fields'         => 'ids',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// A variable product is "out of stock" when all its options are; list the options, not the product twice.
		'tax_query'      => array( array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => array( 'variable' ), 'operator' => 'NOT IN' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
	) );
}

function kk_sd_summary() {
	$cached = get_transient( 'kk_sd_summary' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$day    = current_datetime()->setTime( 0, 0 );
	$starts = array(
		'today'  => $day->getTimestamp(),
		'last7'  => $day->modify( '-6 days' )->getTimestamp(),
		'last30' => $day->modify( '-29 days' )->getTimestamp(),
	);
	$sums = array();
	foreach ( $starts as $k => $ts ) {
		$sums[ $k ] = array( 'orders' => 0, 'sales' => 0.0 );
	}
	$ids = wc_get_orders( array( 'type' => 'shop_order', 'status' => kk_sd_sale_statuses(), 'limit' => 3000, 'date_created' => '>=' . $starts['last30'] ) ); // objects: WooCommerce reads them in one batch instead of one lookup per order
	foreach ( $ids as $o ) {
		if ( ! $o instanceof WC_Order || ! $o->get_date_created() ) {
			continue;
		}
		$ts    = $o->get_date_created()->getTimestamp();
		$value = (float) $o->get_total() - (float) $o->get_total_refunded();
		foreach ( $starts as $k => $start ) {
			if ( $ts >= $start ) {
				$sums[ $k ]['orders']++;
				$sums[ $k ]['sales'] += $value;
			}
		}
	}
	$periods = array();
	foreach ( $sums as $k => $s ) {
		$periods[ $k ] = array( 'orders' => $s['orders'], 'sales' => kk_sd_money( $s['sales'] ) );
	}
	$counts = array();
	foreach ( array_keys( kk_sd_statuses() ) as $status ) {
		$counts[ $status ] = (int) wc_orders_count( $status );
	}
	$low = kk_sd_stock_query( 'low', 1, 1 );
	$out = kk_sd_stock_query( 'out', 1, 1 );
	$data = array(
		'periods'   => $periods,
		'counts'    => $counts,
		'low_stock' => $low ? (int) $low->found_posts : 0,
		'out_stock' => (int) $out->found_posts,
		'incomplete' => function_exists( 'kk_incomplete_count_open' ) ? kk_incomplete_count_open() : 0,
	);
	set_transient( 'kk_sd_summary', $data, 2 * MINUTE_IN_SECONDS );
	return $data;
}

/* =========================================================================
 * Insight blocks (home screen): loaded only when someone taps a button, cached for 5 minutes
 * ====================================================================== */

/** Orders of the last $days days (sales statuses). Returns array( orders, truncated ). One query, capped. */
function kk_sd_report_orders( $days ) {
	$cap   = 3000;
	$start = current_datetime()->setTime( 0, 0 )->modify( '-' . ( max( 1, (int) $days ) - 1 ) . ' days' )->getTimestamp();
	$list  = wc_get_orders( array(
		'type'         => 'shop_order',
		'status'       => kk_sd_sale_statuses(),
		'limit'        => $cap,
		'orderby'      => 'date',
		'order'        => 'DESC',
		'date_created' => '>=' . $start,
	) );
	return array( is_array( $list ) ? $list : array(), is_array( $list ) && count( $list ) >= $cap );
}

/** Sales per day for the last 7 or 30 days. */
function kk_sd_report_trend( $days ) {
	$days  = 30 === (int) $days ? 30 : 7;
	$key   = 'kk_sd_rep_trend_' . $days;
	$saved = get_transient( $key );
	if ( is_array( $saved ) ) {
		return $saved;
	}
	list( $orders, $truncated ) = kk_sd_report_orders( $days );
	$day    = current_datetime()->setTime( 0, 0 );
	$series = array();
	for ( $i = $days - 1; $i >= 0; $i-- ) {
		$series[ $day->modify( '-' . $i . ' days' )->format( 'Y-m-d' ) ] = array( 'orders' => 0, 'sales' => 0.0 );
	}
	$total_orders = 0;
	$total_sales  = 0.0;
	foreach ( $orders as $o ) {
		if ( ! $o instanceof WC_Order || ! $o->get_date_created() ) {
			continue;
		}
		$d = $o->get_date_created()->date( 'Y-m-d' );
		if ( ! isset( $series[ $d ] ) ) {
			continue;
		}
		$value = max( 0.0, (float) $o->get_total() - (float) $o->get_total_refunded() );
		$series[ $d ]['orders']++;
		$series[ $d ]['sales'] += $value;
		$total_orders++;
		$total_sales += $value;
	}
	$rows = array();
	foreach ( $series as $d => $v ) {
		$rows[] = array( 'd' => $d, 'orders' => $v['orders'], 'sales' => round( $v['sales'], 2 ), 'text' => kk_sd_money( $v['sales'] ) );
	}
	$data = array(
		'days'      => $days,
		'series'    => $rows,
		'orders'    => $total_orders,
		'sales'     => kk_sd_money( $total_sales ),
		'avg'       => $total_orders ? kk_sd_money( $total_sales / $total_orders ) : kk_sd_money( 0 ),
		'truncated' => $truncated,
	);
	set_transient( $key, $data, 5 * MINUTE_IN_SECONDS );
	return $data;
}

/** Five best-selling products of the last 30 days (by quantity). */
function kk_sd_report_top() {
	$saved = get_transient( 'kk_sd_rep_top' );
	if ( is_array( $saved ) ) {
		return $saved;
	}
	list( $orders, $truncated ) = kk_sd_report_orders( 30 );
	$by = array();
	foreach ( $orders as $o ) {
		if ( ! $o instanceof WC_Order ) {
			continue;
		}
		foreach ( $o->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$pid = $item->get_product_id();
			if ( ! isset( $by[ $pid ] ) ) {
				$by[ $pid ] = array( 'name' => $item->get_name(), 'qty' => 0, 'total' => 0.0 );
			}
			$by[ $pid ]['qty']   += (int) $item->get_quantity();
			$by[ $pid ]['total'] += (float) $item->get_total();
		}
	}
	uasort( $by, function ( $a, $b ) {
		return $b['qty'] <=> $a['qty'];
	} );
	$rows = array();
	foreach ( array_slice( $by, 0, 5, true ) as $pid => $v ) {
		$product = wc_get_product( $pid );
		$rows[]  = array(
			'id'    => (int) $pid,
			'name'  => $product ? $product->get_name() : $v['name'],
			'qty'   => $v['qty'],
			'total' => kk_sd_money( $v['total'] ),
		);
	}
	$data = array( 'items' => $rows, 'truncated' => $truncated );
	set_transient( 'kk_sd_rep_top', $data, 5 * MINUTE_IN_SECONDS );
	return $data;
}

/** Cash-on-delivery money still with couriers: Shipped orders paid by cash on delivery. */
function kk_sd_report_cod() {
	$saved = get_transient( 'kk_sd_rep_cod' );
	if ( is_array( $saved ) ) {
		return $saved;
	}
	$statuses = kk_sd_statuses();
	if ( ! isset( $statuses['shipped'] ) ) {
		return array( 'available' => false );
	}
	$list   = wc_get_orders( array( 'type' => 'shop_order', 'status' => array( 'shipped' ), 'limit' => 500, 'orderby' => 'date', 'order' => 'ASC' ) );
	$amount = 0.0;
	$count  = 0;
	$oldest = 0;
	$now    = time();
	foreach ( (array) $list as $o ) {
		if ( ! $o instanceof WC_Order || 'cod' !== $o->get_payment_method() ) {
			continue;
		}
		$amount += max( 0.0, (float) $o->get_total() - (float) $o->get_total_refunded() );
		$count++;
		if ( $o->get_date_created() ) {
			$oldest = max( $oldest, (int) floor( ( $now - $o->get_date_created()->getTimestamp() ) / DAY_IN_SECONDS ) );
		}
	}
	$data = array( 'available' => true, 'orders' => $count, 'amount' => kk_sd_money( $amount ), 'oldest_days' => $oldest );
	set_transient( 'kk_sd_rep_cod', $data, 5 * MINUTE_IN_SECONDS );
	return $data;
}

/* =========================================================================
 * Products
 * ====================================================================== */

function kk_sd_price_text( WC_Product $p ) {
	if ( $p->is_type( 'variable' ) ) {
		$min = $p->get_variation_price( 'min' );
		$max = $p->get_variation_price( 'max' );
		if ( '' === (string) $min ) {
			return array( 'price' => '', 'regular' => '' );
		}
		return array( 'price' => (float) $min === (float) $max ? kk_sd_money( $min ) : kk_sd_money( $min ) . ' – ' . kk_sd_money( $max ), 'regular' => '' );
	}
	if ( '' === (string) $p->get_price() ) {
		return array( 'price' => '', 'regular' => '' );
	}
	return array(
		'price'   => kk_sd_money( $p->get_price() ),
		'regular' => $p->is_on_sale() && '' !== (string) $p->get_regular_price() ? kk_sd_money( $p->get_regular_price() ) : '',
	);
}

function kk_sd_stock_label( WC_Product $p ) {
	$labels = array_merge( wc_get_product_stock_status_options(), array( 'instock' => __( 'In stock', 'kenakata-kit' ), 'outofstock' => __( 'Out of stock', 'kenakata-kit' ), 'onbackorder' => __( 'On backorder', 'kenakata-kit' ) ) );
	$label  = isset( $labels[ $p->get_stock_status() ] ) ? $labels[ $p->get_stock_status() ] : $p->get_stock_status();
	if ( $p->managing_stock() && ! $p->is_type( 'variable' ) ) {
		/* translators: %d: number of items in stock */
		$label = 'outofstock' === $p->get_stock_status() ? $label : sprintf( __( '%d in stock', 'kenakata-kit' ), (int) $p->get_stock_quantity() );
	}
	return $label;
}

function kk_sd_product_brief( WC_Product $p ) {
	$parent = $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : null;
	$image  = $p->get_image_id() ? $p->get_image_id() : ( $parent ? $parent->get_image_id() : 0 );
	return array_merge( array(
		'id'           => $p->get_id(),
		'edit_id'      => $parent ? $parent->get_id() : $p->get_id(),
		'name'         => kk_sd_plain( $p->get_name() ),
		'type'         => $p->get_type(),
		'status'       => $parent ? $parent->get_status() : $p->get_status(),
		'image'        => kk_sd_img( $image ),
		'sku'          => (string) $p->get_sku(),
		'stock_status' => $p->get_stock_status(),
		'stock_label'  => kk_sd_stock_label( $p ),
		'variations'   => $p->is_type( 'variable' ) ? count( $p->get_children() ) : 0,
	), kk_sd_price_text( $p ) );
}

function kk_sd_variation_data( WC_Product_Variation $v ) {
	return array(
		'id'             => $v->get_id(),
		'label'          => kk_sd_plain( wc_get_formatted_variation( $v, true, true, false ) ),
		'image'          => kk_sd_img( $v->get_image_id() ),
		'sku'            => (string) $v->get_sku(),
		'regular_price'  => (string) $v->get_regular_price( 'edit' ),
		'sale_price'     => (string) $v->get_sale_price( 'edit' ),
		'manage_stock'   => true === $v->get_manage_stock( 'edit' ),
		'stock_quantity' => null === $v->get_stock_quantity( 'edit' ) ? '' : (int) $v->get_stock_quantity( 'edit' ),
		'stock_status'   => $v->get_stock_status( 'edit' ),
		'status'         => $v->get_status( 'edit' ),
	);
}

function kk_sd_product_full( WC_Product $p ) {
	$images = array();
	foreach ( array_merge( array( $p->get_image_id() ), $p->get_gallery_image_ids() ) as $id ) {
		$id = absint( $id );
		if ( $id && kk_sd_img( $id ) ) {
			$images[] = array( 'id' => $id, 'url' => kk_sd_img( $id, 'medium' ) );
		}
	}
	$variations = array();
	if ( $p->is_type( 'variable' ) ) {
		foreach ( $p->get_children() as $child ) {
			$v = wc_get_product( $child );
			if ( $v instanceof WC_Product_Variation ) {
				$variations[] = kk_sd_variation_data( $v );
			}
		}
	}
	return array(
		'id'                => $p->get_id(),
		'type'              => $p->get_type(),
		'editable_type'     => in_array( $p->get_type(), array( 'simple', 'variable' ), true ),
		'name'              => $p->get_name( 'edit' ),
		'status'            => $p->get_status( 'edit' ),
		'sku'               => (string) $p->get_sku( 'edit' ),
		'regular_price'     => (string) $p->get_regular_price( 'edit' ),
		'sale_price'        => (string) $p->get_sale_price( 'edit' ),
		'manage_stock'      => true === $p->get_manage_stock( 'edit' ),
		'stock_quantity'    => null === $p->get_stock_quantity( 'edit' ) ? '' : (int) $p->get_stock_quantity( 'edit' ),
		'stock_status'      => $p->get_stock_status( 'edit' ),
		'short_description' => kk_sd_editable_html( $p->get_short_description( 'edit' ) ),
		'description'       => kk_sd_editable_html( $p->get_description( 'edit' ) ),
		'category_ids'      => array_map( 'absint', $p->get_category_ids( 'edit' ) ),
		'images'            => $images,
		'permalink'         => 'publish' === $p->get_status() ? get_permalink( $p->get_id() ) : '',
		'variations'        => $variations,
		'edit_url'          => current_user_can( 'manage_woocommerce' ) ? (string) get_edit_post_link( $p->get_id(), 'raw' ) : '',
	);
}

/** The product of a request (simple, variable or other parent product), or an error. */
function kk_sd_get_product( $id ) {
	$p = wc_get_product( absint( $id ) );
	if ( ! $p || $p->is_type( 'variation' ) ) {
		return kk_sd_error( 'not_found', __( 'Not found.', 'kenakata-kit' ), 404 );
	}
	if ( ! current_user_can( 'edit_post', $p->get_id() ) ) {
		return kk_sd_error( 'forbidden', __( 'You do not have permission for this.', 'kenakata-kit' ), 403 );
	}
	return $p;
}

function kk_sd_products_list( WP_REST_Request $req ) {
	list( $page, $per ) = kk_sd_page_args( $req );
	$filter = sanitize_key( (string) $req->get_param( 'filter' ) );
	$search = trim( sanitize_text_field( (string) $req->get_param( 'search' ) ) );

	if ( in_array( $filter, array( 'low', 'out' ), true ) ) {
		$q = kk_sd_stock_query( $filter, $page, $per );
		if ( ! $q ) {
			return array( 'items' => array(), 'total' => 0, 'pages' => 0, 'page' => $page );
		}
	} else {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'draft' === $filter ? array( 'draft', 'pending' ) : array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page' => $per,
			'paged'          => $page,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( '' !== $search ) {
			$args['s'] = $search;
		}
		$q = new WP_Query( $args );
		if ( '' !== $search && ! $q->posts ) { // maybe a SKU
			$by_sku = wc_get_product_id_by_sku( $search );
			if ( $by_sku ) {
				$q->posts       = array( $by_sku );
				$q->found_posts = 1;
				$q->max_num_pages = 1;
			}
		}
	}
	$items = array();
	foreach ( $q->posts as $id ) {
		$p = wc_get_product( $id );
		if ( $p ) {
			$items[] = kk_sd_product_brief( $p );
		}
	}
	return array( 'items' => $items, 'total' => (int) $q->found_posts, 'pages' => (int) $q->max_num_pages, 'page' => $page );
}

/** Validate prices and stock fields shared by products and variations; returns WP_Error or the clean values. */
function kk_sd_clean_price_stock( array $data, $require_price ) {
	$out = array();
	if ( $require_price && '' === trim( (string) ( $data['regular_price'] ?? '' ) ) ) {
		return kk_sd_error( 'no_price', __( 'Please enter a price.', 'kenakata-kit' ) );
	}
	if ( array_key_exists( 'regular_price', $data ) || array_key_exists( 'sale_price', $data ) ) {
		$regular = trim( (string) ( $data['regular_price'] ?? '' ) );
		$sale    = trim( (string) ( $data['sale_price'] ?? '' ) );
		$regular = '' === $regular ? '' : wc_format_decimal( $regular );
		$sale    = '' === $sale ? '' : wc_format_decimal( $sale );
		if ( ( '' !== $regular && (float) $regular < 0 ) || ( '' !== $sale && (float) $sale < 0 ) ) {
			return kk_sd_error( 'bad_price', __( 'Prices cannot be negative.', 'kenakata-kit' ) );
		}
		if ( $require_price && '' === $regular ) {
			return kk_sd_error( 'no_price', __( 'Please enter a price.', 'kenakata-kit' ) );
		}
		if ( '' !== $sale && ( '' === $regular || (float) $sale >= (float) $regular ) ) {
			return kk_sd_error( 'bad_sale', __( 'The sale price must be lower than the regular price.', 'kenakata-kit' ) );
		}
		$out['regular_price'] = $regular;
		$out['sale_price']    = $sale;
	}
	if ( array_key_exists( 'manage_stock', $data ) ) {
		$out['manage_stock'] = ! empty( $data['manage_stock'] );
		if ( $out['manage_stock'] ) {
			$out['stock_quantity'] = wc_stock_amount( $data['stock_quantity'] ?? 0 );
		} else {
			$status = sanitize_key( (string) ( $data['stock_status'] ?? 'instock' ) );
			$out['stock_status'] = in_array( $status, array( 'instock', 'outofstock', 'onbackorder' ), true ) ? $status : 'instock';
		}
	}
	return $out;
}

function kk_sd_apply_price_stock( WC_Product $p, array $clean ) {
	if ( array_key_exists( 'regular_price', $clean ) ) {
		$p->set_regular_price( $clean['regular_price'] );
		$p->set_sale_price( $clean['sale_price'] );
		if ( '' === $clean['sale_price'] ) {
			$p->set_date_on_sale_from( '' );
			$p->set_date_on_sale_to( '' );
		}
	}
	if ( array_key_exists( 'manage_stock', $clean ) ) {
		$p->set_manage_stock( $clean['manage_stock'] );
		if ( $clean['manage_stock'] ) {
			$p->set_stock_quantity( $clean['stock_quantity'] );
		} else {
			$p->set_stock_status( $clean['stock_status'] );
		}
	}
}

function kk_sd_product_save( WP_REST_Request $req ) {
	$creating = empty( $req['id'] );
	if ( $creating ) {
		$p = new WC_Product_Simple();
	} else {
		$p = kk_sd_get_product( $req['id'] );
		if ( is_wp_error( $p ) ) {
			return $p;
		}
		if ( ! in_array( $p->get_type(), array( 'simple', 'variable' ), true ) ) {
			return kk_sd_error( 'type', __( 'This kind of product can only be edited in WordPress admin.', 'kenakata-kit' ) );
		}
	}
	$data = kk_sd_body( $req );

	if ( $creating || array_key_exists( 'name', $data ) ) {
		$name = trim( sanitize_text_field( (string) ( $data['name'] ?? '' ) ) );
		if ( '' === $name ) {
			return kk_sd_error( 'no_name', __( 'Please enter a name.', 'kenakata-kit' ) );
		}
		$p->set_name( $name );
	}
	if ( array_key_exists( 'status', $data ) || $creating ) {
		$status = 'publish' === ( $data['status'] ?? '' ) ? 'publish' : 'draft';
		if ( 'publish' === $status && ! current_user_can( 'publish_products' ) ) {
			$status = 'pending';
		}
		$p->set_status( $status );
	}
	if ( ! $p->is_type( 'variable' ) ) {
		$clean = kk_sd_clean_price_stock( $data, $creating || ( 'publish' === $p->get_status( 'edit' ) && array_key_exists( 'regular_price', $data ) ) );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		kk_sd_apply_price_stock( $p, $clean );
	}
	if ( array_key_exists( 'sku', $data ) ) {
		try {
			$p->set_sku( wc_clean( (string) $data['sku'] ) );
		} catch ( WC_Data_Exception $e ) {
			return kk_sd_error( 'sku', $e->getMessage() );
		}
	}
	if ( array_key_exists( 'short_description', $data ) ) {
		$p->set_short_description( wp_kses_post( (string) $data['short_description'] ) );
	}
	if ( array_key_exists( 'description', $data ) ) {
		$p->set_description( wp_kses_post( (string) $data['description'] ) );
	}
	if ( array_key_exists( 'category_ids', $data ) && current_user_can( 'assign_product_terms' ) ) {
		$cats = array();
		foreach ( (array) $data['category_ids'] as $id ) {
			$term = get_term( absint( $id ), 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$cats[] = (int) $term->term_id;
			}
		}
		$p->set_category_ids( $cats );
	}
	if ( array_key_exists( 'image_ids', $data ) ) {
		$images = kk_sd_clean_image_ids( $data['image_ids'] );
		$p->set_image_id( $images ? $images[0] : '' );
		$p->set_gallery_image_ids( array_slice( $images, 1 ) );
	}
	$p->save();
	if ( ! $p->get_id() ) {
		return kk_sd_error( 'save', __( 'Something went wrong. Please try again.', 'kenakata-kit' ), 500 );
	}
	kk_sd_flush_summary();
	return kk_sd_product_full( wc_get_product( $p->get_id() ) );
}

function kk_sd_variation_save( WP_REST_Request $req ) {
	$v = wc_get_product( absint( $req['id'] ) );
	if ( ! $v instanceof WC_Product_Variation ) {
		return kk_sd_error( 'not_found', __( 'Not found.', 'kenakata-kit' ), 404 );
	}
	if ( ! current_user_can( 'edit_post', $v->get_parent_id() ) ) {
		return kk_sd_error( 'forbidden', __( 'You do not have permission for this.', 'kenakata-kit' ), 403 );
	}
	$data  = kk_sd_body( $req );
	$clean = kk_sd_clean_price_stock( $data, array_key_exists( 'regular_price', $data ) );
	if ( is_wp_error( $clean ) ) {
		return $clean;
	}
	kk_sd_apply_price_stock( $v, $clean );
	if ( array_key_exists( 'sku', $data ) ) {
		try {
			$v->set_sku( wc_clean( (string) $data['sku'] ) );
		} catch ( WC_Data_Exception $e ) {
			return kk_sd_error( 'sku', $e->getMessage() );
		}
	}
	$v->save();
	WC_Product_Variable::sync( $v->get_parent_id() );
	wc_delete_product_transients( $v->get_parent_id() );
	kk_sd_flush_summary();
	return kk_sd_product_full( wc_get_product( $v->get_parent_id() ) );
}

function kk_sd_categories() {
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$by_parent = array();
	foreach ( $terms as $t ) {
		$by_parent[ (int) $t->parent ][] = $t;
	}
	$out  = array();
	$walk = function ( $parent, $depth ) use ( &$walk, &$out, $by_parent ) {
		foreach ( $by_parent[ $parent ] ?? array() as $t ) {
			$out[] = array( 'id' => (int) $t->term_id, 'name' => html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ), 'depth' => $depth, 'count' => (int) $t->count );
			$walk( (int) $t->term_id, $depth + 1 );
		}
	};
	$walk( 0, 0 );
	return $out;
}

/* =========================================================================
 * Pictures
 * ====================================================================== */

function kk_sd_upload( WP_REST_Request $req ) {
	$files = $req->get_file_params();
	$file  = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : null;
	if ( ! $file || ! empty( $file['error'] ) || empty( $file['tmp_name'] ) ) {
		return kk_sd_error( 'no_file', __( 'The picture could not be uploaded. Please try again.', 'kenakata-kit' ) );
	}
	if ( (int) $file['size'] > wp_max_upload_size() ) {
		/* translators: %s: maximum size, e.g. 8 MB */
		return kk_sd_error( 'too_big', sprintf( __( 'The picture is too large. Maximum: %s.', 'kenakata-kit' ), size_format( wp_max_upload_size() ) ) );
	}
	$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
	if ( empty( $check['type'] ) || ! in_array( $check['type'], array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true ) ) {
		return kk_sd_error( 'type', __( 'Please choose a JPG, PNG, WebP or GIF picture.', 'kenakata-kit' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_sideload(
		array(
			'name'     => sanitize_file_name( $file['name'] ),
			'type'     => $check['type'],
			'tmp_name' => $file['tmp_name'],
			'error'    => 0,
			'size'     => (int) $file['size'],
		),
		0
	);
	if ( is_wp_error( $id ) ) {
		return kk_sd_error( 'upload', $id->get_error_message() );
	}
	return array( 'id' => (int) $id, 'url' => kk_sd_img( $id, 'medium' ) );
}

/* =========================================================================
 * Blog posts
 * ====================================================================== */

function kk_sd_post_brief( WP_Post $post ) {
	return array(
		'id'     => $post->ID,
		'title'  => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
		'status' => $post->post_status,
		'date'   => array( 'ts' => (int) get_post_time( 'U', true, $post ), 'text' => get_the_date( '', $post ) ),
		'image'  => kk_sd_img( get_post_thumbnail_id( $post ) ),
		'link'   => 'publish' === $post->post_status ? get_permalink( $post ) : '',
	);
}

function kk_sd_post_full( WP_Post $post ) {
	$thumb = get_post_thumbnail_id( $post );
	return array_merge( kk_sd_post_brief( $post ), array(
		'title_raw'   => $post->post_title,
		'content'     => kk_sd_editable_html( $post->post_content ),
		'excerpt'     => $post->post_excerpt,
		'featured_id' => $thumb ? (int) $thumb : 0,
		'featured'    => kk_sd_img( $thumb, 'medium' ),
		'edit_url'    => current_user_can( 'manage_options' ) ? (string) get_edit_post_link( $post->ID, 'raw' ) : '',
	) );
}

function kk_sd_get_post( $id ) {
	$post = get_post( absint( $id ) );
	if ( ! $post || 'post' !== $post->post_type ) {
		return kk_sd_error( 'not_found', __( 'Not found.', 'kenakata-kit' ), 404 );
	}
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return kk_sd_error( 'forbidden', __( 'You do not have permission for this.', 'kenakata-kit' ), 403 );
	}
	return $post;
}

function kk_sd_posts_list( WP_REST_Request $req ) {
	list( $page, $per ) = kk_sd_page_args( $req );
	$args = array(
		'post_type'      => 'post',
		'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
		'posts_per_page' => $per,
		'paged'          => $page,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	$search = trim( sanitize_text_field( (string) $req->get_param( 'search' ) ) );
	if ( '' !== $search ) {
		$args['s'] = $search;
	}
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		$args['author'] = get_current_user_id();
	}
	$q = new WP_Query( $args );
	return array( 'items' => array_map( 'kk_sd_post_brief', $q->posts ), 'total' => (int) $q->found_posts, 'pages' => (int) $q->max_num_pages, 'page' => $page );
}

function kk_sd_post_save( WP_REST_Request $req ) {
	$creating = empty( $req['id'] );
	$post     = null;
	if ( ! $creating ) {
		$post = kk_sd_get_post( $req['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
	}
	$data  = kk_sd_body( $req );
	$title = trim( sanitize_text_field( (string) ( $data['title'] ?? ( $post ? $post->post_title : '' ) ) ) );
	if ( '' === $title ) {
		return kk_sd_error( 'no_title', __( 'Please enter a title.', 'kenakata-kit' ) );
	}
	if ( array_key_exists( 'status', $data ) || ! $post ) {
		$status = 'publish' === ( $data['status'] ?? '' ) ? 'publish' : 'draft';
		if ( 'publish' === $status && ! current_user_can( 'publish_posts' ) ) {
			$status = 'pending';
		}
	} else {
		$status = $post->post_status; // keep a published post published when only the text changes
	}
	$arr = array(
		'post_type'   => 'post',
		'post_title'  => $title,
		'post_status' => $status,
	);
	if ( $creating || array_key_exists( 'content', $data ) ) {
		$arr['post_content'] = wp_kses_post( (string) ( $data['content'] ?? '' ) );
	}
	if ( $creating || array_key_exists( 'excerpt', $data ) ) {
		$arr['post_excerpt'] = sanitize_textarea_field( (string) ( $data['excerpt'] ?? '' ) );
	}
	if ( $post ) {
		$arr['ID'] = $post->ID;
		$id        = wp_update_post( wp_slash( $arr ), true );
	} else {
		$arr['post_author'] = get_current_user_id();
		$id                 = wp_insert_post( wp_slash( $arr ), true );
	}
	if ( is_wp_error( $id ) ) {
		return kk_sd_error( 'save', $id->get_error_message() );
	}
	if ( array_key_exists( 'featured_id', $data ) ) {
		$img = kk_sd_clean_image_ids( array( $data['featured_id'] ) );
		if ( $img ) {
			set_post_thumbnail( $id, $img[0] );
		} else {
			delete_post_thumbnail( $id );
		}
	}
	return kk_sd_post_full( get_post( $id ) );
}

/* =========================================================================
 * Routes
 * ====================================================================== */
add_action( 'rest_api_init', function () {
	$ns    = 'kenakata/v1';
	$route = function ( $path, $method, $cap, $callback ) use ( $ns ) {
		register_rest_route( $ns, '/sd/' . $path, array(
			'methods'             => $method,
			'permission_callback' => kk_sd_perm( $cap ),
			'callback'            => $callback,
		) );
	};
	$id = '(?P<id>\d+)';

	$route( 'summary', 'GET', 'read', function () {
		$data = kk_sd_summary();
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			unset( $data['periods'], $data['counts'] );
		} elseif ( ! kk_sd_can_money() && isset( $data['periods'] ) ) {
			foreach ( $data['periods'] as $k => $p ) {
				unset( $data['periods'][ $k ]['sales'] ); // order counts only
			}
		}
		return $data;
	} );

	// Insight blocks: money figures, so they follow the same rule as the sales amounts on the home screen.
	$money = function () {
		return is_user_logged_in() && current_user_can( KK_SD_CAP ) && current_user_can( 'edit_shop_orders' ) && kk_sd_can_money();
	};
	register_rest_route( $ns, '/sd/report/trend', array(
		'methods'             => 'GET',
		'permission_callback' => $money,
		'callback'            => function ( WP_REST_Request $req ) {
			return kk_sd_report_trend( absint( $req->get_param( 'days' ) ) );
		},
	) );
	register_rest_route( $ns, '/sd/report/top', array(
		'methods'             => 'GET',
		'permission_callback' => $money,
		'callback'            => 'kk_sd_report_top',
	) );
	register_rest_route( $ns, '/sd/report/cod', array(
		'methods'             => 'GET',
		'permission_callback' => $money,
		'callback'            => 'kk_sd_report_cod',
	) );

	$route( 'orders', 'GET', 'edit_shop_orders', 'kk_sd_orders_list' );
	$route( 'orders/' . $id, 'GET', 'edit_shop_orders', function ( WP_REST_Request $req ) {
		$order = kk_sd_get_order( $req['id'] );
		return is_wp_error( $order ) ? $order : kk_sd_order_full( $order );
	} );
	$route( 'orders/' . $id, 'POST', 'edit_shop_orders', 'kk_sd_order_update' );

	$route( 'products', 'GET', 'edit_products', 'kk_sd_products_list' );
	$route( 'products', 'POST', 'edit_products', 'kk_sd_product_save' );
	$route( 'products/' . $id, 'GET', 'edit_products', function ( WP_REST_Request $req ) {
		$p = kk_sd_get_product( $req['id'] );
		return is_wp_error( $p ) ? $p : kk_sd_product_full( $p );
	} );
	$route( 'products/' . $id, 'POST', 'edit_products', 'kk_sd_product_save' );
	$route( 'variations/' . $id, 'POST', 'edit_products', 'kk_sd_variation_save' );
	$route( 'categories', 'GET', 'edit_products', 'kk_sd_categories' );

	$route( 'media', 'POST', 'upload_files', 'kk_sd_upload' );

	$route( 'posts', 'GET', 'edit_posts', 'kk_sd_posts_list' );
	$route( 'posts', 'POST', 'edit_posts', 'kk_sd_post_save' );
	$route( 'posts/' . $id, 'GET', 'edit_posts', function ( WP_REST_Request $req ) {
		$post = kk_sd_get_post( $req['id'] );
		return is_wp_error( $post ) ? $post : kk_sd_post_full( $post );
	} );
	$route( 'posts/' . $id, 'POST', 'edit_posts', 'kk_sd_post_save' );
} );
