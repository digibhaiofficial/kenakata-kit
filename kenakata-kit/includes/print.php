<?php
/**
 * Invoices and shipping labels (3.15). Off until "print_enable" is turned on.
 *
 * - WooCommerce → Orders: bulk actions "Print invoices" / "Print labels", and two buttons on the order screen.
 * - Store dashboard: Print buttons on an order and for the orders shown in the list.
 * - Courier (Pro, only when a courier is connected): bulk "Send to <courier>" and "Sync courier status". Both run only
 *   when pressed, a few orders per press. Nothing runs in the background.
 *
 * The print page is a plain HTML page (no theme, no scripts from other plugins) served through admin-post.php,
 * so it works for the Store Manager role too. It opens the browser's print dialog by itself.
 *
 * @package KenakataKit
 */

defined( 'ABSPATH' ) || exit;

/** Most orders printed at once (one press). */
function kk_print_max() {
	return (int) apply_filters( 'kk_print_max', 100 );
}

/** Link to the print page for some orders. $kind: invoice | label. */
function kk_print_url( $kind, array $ids ) {
	return add_query_arg(
		array(
			'action'   => 'kk_print',
			'kind'     => 'label' === $kind ? 'label' : 'invoice',
			'ids'      => implode( ',', array_map( 'absint', $ids ) ),
			'_wpnonce' => wp_create_nonce( 'kk_print' ),
		),
		admin_url( 'admin-post.php' )
	);
}

/* =========================================================================
 * Code 128 (set B) barcode as SVG: tracking codes and order numbers can be scanned.
 * ====================================================================== */
function kk_code128_svg( $text, $height = 40 ) {
	$text = preg_replace( '/[^\x20-\x7E]/', '', (string) $text );
	if ( '' === $text ) {
		return '';
	}
	static $p = array( '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313', '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111', '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412', '211214', '211232', '2331112' );
	$codes = array( 104 );
	$sum   = 104;
	$len   = strlen( $text );
	for ( $i = 0; $i < $len; $i++ ) {
		$v       = ord( $text[ $i ] ) - 32;
		$codes[] = $v;
		$sum    += $v * ( $i + 1 );
	}
	$codes[] = $sum % 103;
	$codes[] = 106;
	$x       = 10; // quiet zone
	$bars    = '';
	foreach ( $codes as $c ) {
		$w = $p[ $c ];
		for ( $j = 0, $n = strlen( $w ); $j < $n; $j++ ) {
			$u = (int) $w[ $j ];
			if ( 0 === $j % 2 ) {
				$bars .= 'M' . $x . ' 0h' . $u . 'v' . (int) $height . 'h-' . $u . 'z';
			}
			$x += $u;
		}
	}
	$width = $x + 10;
	return '<svg class="kkp-bc" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $width . ' ' . (int) $height . '" preserveAspectRatio="none" role="img" aria-label="' . esc_attr( $text ) . '"><path d="' . $bars . '" fill="#000"/></svg>';
}

/* =========================================================================
 * What goes on the paper
 * ====================================================================== */

/** Shop details for the top of the invoice / the "From" of the label. */
function kk_print_shop() {
	$name = trim( (string) kk_opt( 'print_shop_name' ) );
	$addr = trim( (string) kk_opt( 'print_shop_address' ) );
	if ( '' === $addr ) {
		$parts = array_filter( array_map( 'trim', array( get_option( 'woocommerce_store_address' ), get_option( 'woocommerce_store_address_2' ), get_option( 'woocommerce_store_city' ) ) ) );
		$addr  = implode( ', ', $parts );
	}
	$logo = '';
	if ( kk_opt( 'print_logo' ) ) {
		$id   = absint( get_theme_mod( 'custom_logo' ) );
		$logo = $id ? (string) wp_get_attachment_image_url( $id, 'medium' ) : '';
	}
	return array(
		'name'  => '' !== $name ? $name : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'phone' => trim( (string) kk_opt( 'print_shop_phone' ) ),
		'addr'  => $addr,
		'logo'  => $logo,
		'web'   => preg_replace( '#^https?://#', '', untrailingslashit( home_url() ) ),
	);
}

/** Customer address as one line (street, area, city, district). */
function kk_print_address( WC_Order $order ) {
	$state = $order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state();
	$cc    = $order->get_shipping_country() ? $order->get_shipping_country() : $order->get_billing_country();
	$names = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_states( $cc ) : array();
	if ( is_array( $names ) && isset( $names[ $state ] ) ) {
		$state = $names[ $state ];
	}
	$ship  = trim( $order->get_shipping_address_1() . $order->get_shipping_city() );
	$parts = $ship
		? array( $order->get_shipping_address_1(), $order->get_shipping_address_2(), $order->get_shipping_city(), $state )
		: array( $order->get_billing_address_1(), $order->get_billing_address_2(), $order->get_billing_city(), $state );
	return implode( ', ', array_unique( array_filter( array_map( 'trim', $parts ) ) ) );
}

/** Money still to collect from the customer (cash on delivery and not paid yet), as a number. */
function kk_print_to_collect( WC_Order $order ) {
	$mp = function_exists( 'kk_mp_to_collect' ) ? kk_mp_to_collect( $order ) : null;
	if ( null !== $mp ) {
		return (float) $mp;
	}
	if ( 'cod' !== $order->get_payment_method() || $order->get_date_paid() ) {
		return 0.0;
	}
	return max( 0, (float) $order->get_total() - (float) $order->get_total_refunded() );
}

/** Courier name and tracking code of an order ('' when none). */
function kk_print_courier( WC_Order $order ) {
	$b = $order->get_meta( '_kk_courier_booking' );
	if ( is_array( $b ) && ! empty( $b['consignment_id'] ) ) {
		$code  = '' !== (string) ( $b['tracking_code'] ?? '' ) ? (string) $b['tracking_code'] : (string) $b['consignment_id'];
		$label = function_exists( 'kk_courier_label' ) ? kk_courier_label( (string) $b['provider'] ) : (string) $b['provider'];
		return array( 'name' => $label, 'code' => $code );
	}
	$key  = (string) kk_order_meta( $order, 'courier' );
	$code = (string) kk_order_meta( $order, 'tracking_id' );
	$all  = kk_couriers();
	return array( 'name' => isset( $all[ $key ] ) ? $all[ $key ] : '', 'code' => $code );
}

function kk_print_money( $amount, WC_Order $order ) {
	return html_entity_decode( wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' );
}

/** One invoice (one A4 page). */
function kk_print_invoice_html( WC_Order $order, array $shop ) {
	$num     = (string) $order->get_order_number();
	$date    = $order->get_date_created() ? $order->get_date_created()->date_i18n( get_option( 'date_format' ) ) : '';
	$collect = kk_print_to_collect( $order );
	$area    = $order->get_shipping_method();
	$cour    = kk_print_courier( $order );
	$sku     = (bool) kk_opt( 'print_show_sku' );
	$o       = '<section class="kkp-inv">';
	$o      .= '<header class="kkp-inv__top"><div class="kkp-shop">';
	$o      .= $shop['logo'] ? '<img class="kkp-logo" src="' . esc_url( $shop['logo'] ) . '" alt="">' : '';
	$o      .= '<strong class="kkp-shop__name">' . esc_html( $shop['name'] ) . '</strong>';
	foreach ( array( $shop['addr'], $shop['phone'], $shop['web'] ) as $line ) {
		$o .= '' !== $line ? '<span>' . esc_html( $line ) . '</span>' : '';
	}
	$o .= '</div><div class="kkp-inv__meta"><h1>' . esc_html__( 'Invoice', 'kenakata-kit' ) . '</h1>';
	$o .= '<dl><dt>' . esc_html__( 'Order', 'kenakata-kit' ) . '</dt><dd>#' . esc_html( $num ) . '</dd>';
	$o .= '<dt>' . esc_html__( 'Date', 'kenakata-kit' ) . '</dt><dd>' . esc_html( $date ) . '</dd>';
	$o .= '<dt>' . esc_html__( 'Payment', 'kenakata-kit' ) . '</dt><dd>' . esc_html( $order->get_payment_method_title() ? $order->get_payment_method_title() : '—' ) . '</dd>';
	if ( '' !== $cour['code'] ) {
		$o .= '<dt>' . esc_html__( 'Courier', 'kenakata-kit' ) . '</dt><dd>' . esc_html( trim( $cour['name'] . ' ' . $cour['code'] ) ) . '</dd>';
	}
	$o .= '</dl>' . ( kk_opt( 'print_barcode' ) ? kk_code128_svg( $num, 34 ) : '' ) . '</div></header>';

	$o .= '<div class="kkp-inv__to"><h2>' . esc_html__( 'Bill to', 'kenakata-kit' ) . '</h2>';
	$o .= '<strong>' . esc_html( trim( $order->get_formatted_billing_full_name() ) ) . '</strong>';
	$o .= '<span>' . esc_html( (string) $order->get_billing_phone() ) . '</span>';
	$o .= '<span>' . esc_html( kk_print_address( $order ) ) . '</span>';
	$o .= $area ? '<span>' . esc_html( $area ) . '</span>' : '';
	$o .= '</div>';

	$o .= '<table class="kkp-items"><thead><tr><th>' . esc_html__( 'Product', 'kenakata-kit' ) . '</th><th class="n">' . esc_html__( 'Qty', 'kenakata-kit' ) . '</th><th class="n">' . esc_html__( 'Price', 'kenakata-kit' ) . '</th><th class="n">' . esc_html__( 'Total', 'kenakata-kit' ) . '</th></tr></thead><tbody>';
	foreach ( $order->get_items() as $item ) {
		$qty  = (int) $item->get_quantity();
		$meta = array();
		foreach ( $item->get_formatted_meta_data( '_', false ) as $m ) {
			$meta[] = wp_strip_all_tags( $m->display_key ) . ': ' . trim( wp_strip_all_tags( $m->display_value ) );
		}
		$prod = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
		if ( $sku && $prod && $prod->get_sku() ) {
			$meta[] = __( 'SKU', 'kenakata-kit' ) . ': ' . $prod->get_sku();
		}
		$o .= '<tr><td>' . esc_html( $item->get_name() ) . ( $meta ? '<small>' . esc_html( implode( ' · ', $meta ) ) . '</small>' : '' ) . '</td>';
		$o .= '<td class="n">' . esc_html( (string) $qty ) . '</td>';
		$o .= '<td class="n">' . esc_html( kk_print_money( $qty ? (float) $item->get_subtotal() / $qty : 0, $order ) ) . '</td>';
		$o .= '<td class="n">' . esc_html( kk_print_money( (float) $item->get_subtotal(), $order ) ) . '</td></tr>';
	}
	$o .= '</tbody></table>';

	$rows   = array( array( __( 'Subtotal', 'kenakata-kit' ), (float) $order->get_subtotal() ) );
	$rows[] = array( $area ? $area : __( 'Delivery', 'kenakata-kit' ), (float) $order->get_shipping_total() + (float) $order->get_shipping_tax() );
	if ( (float) $order->get_total_discount() > 0 ) {
		$rows[] = array( __( 'Discount', 'kenakata-kit' ), - (float) $order->get_total_discount() );
	}
	foreach ( $order->get_fees() as $fee ) {
		$rows[] = array( $fee->get_name(), (float) $fee->get_total() );
	}
	if ( (float) $order->get_total_tax() - (float) $order->get_shipping_tax() > 0 ) {
		$rows[] = array( __( 'Tax', 'kenakata-kit' ), (float) $order->get_total_tax() - (float) $order->get_shipping_tax() );
	}
	if ( (float) $order->get_total_refunded() > 0 ) {
		$rows[] = array( __( 'Refunded', 'kenakata-kit' ), - (float) $order->get_total_refunded() );
	}
	$o .= '<table class="kkp-sum">';
	foreach ( $rows as $r ) {
		$o .= '<tr><th>' . esc_html( $r[0] ) . '</th><td>' . esc_html( kk_print_money( $r[1], $order ) ) . '</td></tr>';
	}
	$o .= '<tr class="kkp-sum__total"><th>' . esc_html__( 'Total', 'kenakata-kit' ) . '</th><td>' . esc_html( kk_print_money( (float) $order->get_total(), $order ) ) . '</td></tr>';
	$o .= $collect > 0
		? '<tr class="kkp-sum__cod"><th>' . esc_html__( 'Cash on delivery: collect', 'kenakata-kit' ) . '</th><td>' . esc_html( kk_print_money( $collect, $order ) ) . '</td></tr>'
		: '<tr><th>' . esc_html__( 'Paid', 'kenakata-kit' ) . '</th><td>' . esc_html( kk_print_money( (float) $order->get_total() - (float) $order->get_total_refunded(), $order ) ) . '</td></tr>';
	$o .= '</table>';

	if ( $order->get_customer_note() ) {
		$o .= '<p class="kkp-note"><strong>' . esc_html__( 'Customer note', 'kenakata-kit' ) . ':</strong> ' . esc_html( $order->get_customer_note() ) . '</p>';
	}
	$foot = trim( (string) kk_opt( 'print_invoice_note' ) );
	$o   .= '' !== $foot ? '<footer class="kkp-foot">' . nl2br( esc_html( $foot ) ) . '</footer>' : '';
	return $o . '</section>';
}

/** One shipping label. */
function kk_print_label_html( WC_Order $order, array $shop ) {
	$num     = (string) $order->get_order_number();
	$collect = kk_print_to_collect( $order );
	$cour    = kk_print_courier( $order );
	$qty     = 0;
	$names   = array();
	foreach ( $order->get_items() as $item ) {
		$qty    += (int) $item->get_quantity();
		$names[] = $item->get_name() . ' × ' . (int) $item->get_quantity();
	}
	$items = implode( ', ', $names );
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $items ) > 140 ) {
		$items = mb_substr( $items, 0, 137 ) . '…';
	}
	$code = '' !== $cour['code'] ? $cour['code'] : $num;
	$o    = '<section class="kkp-lab">';
	$o   .= '<div class="kkp-lab__from"><span>' . esc_html__( 'Sender', 'kenakata-kit' ) . '</span> <strong>' . esc_html( $shop['name'] ) . '</strong>' . ( $shop['phone'] ? ' · ' . esc_html( $shop['phone'] ) : '' ) . '</div>';
	$o   .= '<div class="kkp-lab__to"><span>' . esc_html__( 'Receiver', 'kenakata-kit' ) . '</span>';
	$o   .= '<strong class="kkp-lab__name">' . esc_html( trim( $order->get_formatted_billing_full_name() ) ) . '</strong>';
	$o   .= '<strong class="kkp-lab__phone">' . esc_html( (string) $order->get_billing_phone() ) . '</strong>';
	$o   .= '<span class="kkp-lab__addr">' . esc_html( kk_print_address( $order ) ) . '</span>';
	$o   .= $order->get_shipping_method() ? '<span class="kkp-lab__area">' . esc_html( $order->get_shipping_method() ) . '</span>' : '';
	$o   .= '</div>';
	$o   .= '<div class="kkp-lab__cod">' . ( $collect > 0
		? '<span>' . esc_html__( 'Collect (COD)', 'kenakata-kit' ) . '</span><strong>' . esc_html( kk_print_money( $collect, $order ) ) . '</strong>'
		: '<strong>' . esc_html__( 'Paid', 'kenakata-kit' ) . '</strong>' ) . '</div>';
	$o   .= '<div class="kkp-lab__ref"><span>' . esc_html__( 'Order', 'kenakata-kit' ) . ' <b>#' . esc_html( $num ) . '</b></span>';
	$o   .= '' !== $cour['code'] ? '<span>' . esc_html( trim( $cour['name'] ) ) . ' <b>' . esc_html( $cour['code'] ) . '</b></span>' : '';
	/* translators: %d: number of items */
	$o   .= '<span>' . esc_html( sprintf( _n( '%d item', '%d items', $qty, 'kenakata-kit' ), $qty ) ) . '</span></div>';
	$o   .= kk_opt( 'print_barcode' ) ? '<div class="kkp-lab__bc">' . kk_code128_svg( $code, 40 ) . '<span>' . esc_html( $code ) . '</span></div>' : '';
	$o   .= '<p class="kkp-lab__items">' . esc_html( $items ) . '</p>';
	$o   .= $order->get_customer_note() ? '<p class="kkp-lab__note">' . esc_html( $order->get_customer_note() ) . '</p>' : '';
	return $o . '</section>';
}

/** The whole print page. */
function kk_print_page( $kind, array $orders ) {
	$shop  = kk_print_shop();
	$size  = (string) kk_opt( 'print_label_size' );
	$size  = in_array( $size, array( '100x150', '75x100', 'a4' ), true ) ? $size : '100x150';
	$label = 'label' === $kind;
	$title = $label ? __( 'Shipping labels', 'kenakata-kit' ) : __( 'Invoices', 'kenakata-kit' );
	$page  = $label ? ( 'a4' === $size ? 'A4' : str_replace( 'x', 'mm ', $size ) . 'mm' ) : 'A4';
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	echo '<!DOCTYPE html><html lang="' . esc_attr( ! empty( $GLOBALS['kk_lang_bn'] ) ? 'bn' : get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
	echo '<title>' . esc_html( $title . ' · ' . $shop['name'] ) . '</title><style>' . kk_print_css( $page, $size ) . '</style></head>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- our own static CSS
	echo '<body class="kkp kkp--' . esc_attr( $label ? 'label kkp--' . $size : 'invoice' ) . '">';
	/* translators: 1: Invoices / Shipping labels 2: number of orders */
	echo '<div class="kkp-bar"><strong>' . esc_html( sprintf( __( '%1$s: %2$d orders', 'kenakata-kit' ), $title, count( $orders ) ) ) . '</strong><button type="button" onclick="window.print()">' . esc_html__( 'Print', 'kenakata-kit' ) . '</button></div>';
	echo '<main class="kkp-sheet">';
	foreach ( $orders as $order ) {
		echo $label ? kk_print_label_html( $order, $shop ) : kk_print_invoice_html( $order, $shop ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
	echo '</main><script>window.addEventListener("load",function(){setTimeout(function(){window.print();},300);});</script></body></html>';
}

function kk_print_css( $page, $size ) {
	$lab = array(
		'100x150' => '.kkp-lab{width:100mm;height:150mm;padding:5mm;font-size:12.5px}.kkp-lab__name{font-size:19px}.kkp-lab__phone{font-size:21px}.kkp-lab__cod strong{font-size:24px}',
		'75x100'  => '.kkp-lab{width:75mm;height:100mm;padding:3.5mm;font-size:10.5px;gap:1.8mm}.kkp-lab__name{font-size:15px}.kkp-lab__phone{font-size:16px}.kkp-lab__cod strong{font-size:18px}.kkp-lab__bc svg{height:11mm}.kkp-lab__items{-webkit-line-clamp:2}',
		'a4'      => '.kkp--a4 .kkp-sheet{display:grid;grid-template-columns:1fr 1fr;gap:0;width:210mm}.kkp-lab{width:105mm;height:99mm;padding:5mm;font-size:11.5px;border:.2mm dashed #999}.kkp-lab:nth-child(6n){break-after:page}.kkp-lab__name{font-size:16px}.kkp-lab__phone{font-size:18px}.kkp-lab__cod strong{font-size:20px}.kkp-lab__bc svg{height:12mm}',
	);
	return '@page{size:' . $page . ';margin:0}'
		. '*{box-sizing:border-box}html,body{margin:0;background:#e9e9ee;color:#111;font:14px/1.45 system-ui,-apple-system,"Segoe UI","Noto Sans Bengali","Nirmala UI",Vrinda,Roboto,Arial,sans-serif}'
		. '.kkp-bar{position:sticky;top:0;z-index:2;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 16px;background:#111;color:#fff}.kkp-bar button{font:inherit;font-weight:700;padding:8px 22px;border:0;border-radius:8px;background:#fff;color:#111;cursor:pointer}'
		. '.kkp-sheet{margin:16px auto;display:flex;flex-direction:column;align-items:center;gap:16px}'
		. '.kkp-inv{width:210mm;min-height:297mm;padding:14mm 14mm 12mm;background:#fff;display:flex;flex-direction:column;gap:7mm;break-after:page}'
		. '.kkp-inv__top{display:flex;justify-content:space-between;gap:10mm;border-bottom:.5mm solid #111;padding-bottom:5mm}.kkp-shop{display:flex;flex-direction:column;gap:1px;font-size:12.5px;color:#444;max-width:60%}.kkp-shop__name{font-size:19px;color:#111}.kkp-logo{max-height:16mm;max-width:50mm;object-fit:contain;align-self:flex-start;margin-bottom:2mm}'
		. '.kkp-inv__meta{text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:2mm}.kkp-inv__meta h1{margin:0;font-size:24px;letter-spacing:.06em;text-transform:uppercase}.kkp-inv__meta dl{display:grid;grid-template-columns:auto auto;gap:1px 10px;margin:0;font-size:12.5px}.kkp-inv__meta dt{color:#666}.kkp-inv__meta dd{margin:0;font-weight:600}.kkp-inv__meta .kkp-bc{width:46mm;height:9mm}'
		. '.kkp-inv__to{display:flex;flex-direction:column;gap:1px}.kkp-inv__to h2{margin:0 0 1mm;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#666}.kkp-inv__to strong{font-size:16px}'
		. '.kkp-items{width:100%;border-collapse:collapse;font-size:13px}.kkp-items th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#555;border-bottom:.4mm solid #111;padding:2mm 1.5mm}.kkp-items td{padding:2.4mm 1.5mm;border-bottom:.2mm solid #ddd;vertical-align:top}.kkp-items small{display:block;color:#666;font-size:11px}.kkp-items .n{text-align:right;white-space:nowrap}'
		. '.kkp-sum{margin-left:auto;min-width:80mm;border-collapse:collapse;font-size:13px}.kkp-sum th{text-align:left;font-weight:400;color:#444;padding:1.2mm 6mm 1.2mm 0}.kkp-sum td{text-align:right;white-space:nowrap;padding:1.2mm 0}.kkp-sum__total th,.kkp-sum__total td{font-weight:700;font-size:15px;color:#111;border-top:.4mm solid #111;padding-top:2mm}.kkp-sum__cod th,.kkp-sum__cod td{font-weight:700;color:#111;background:#f1f1f1;padding:2mm}'
		. '.kkp-note{margin:0;padding:3mm;border:.2mm solid #ccc;border-radius:2mm;font-size:12.5px}.kkp-foot{margin-top:auto;padding-top:4mm;border-top:.2mm solid #ccc;text-align:center;color:#555;font-size:12px}'
		. '.kkp-lab{background:#fff;display:flex;flex-direction:column;gap:2.4mm;overflow:hidden;break-inside:avoid}.kkp-lab span{color:#333}.kkp-lab__from{font-size:.92em;border-bottom:.3mm solid #111;padding-bottom:1.6mm}.kkp-lab__from>span,.kkp-lab__to>span:first-child{text-transform:uppercase;font-size:.78em;letter-spacing:.08em;color:#666}'
		. '.kkp-lab__to{display:flex;flex-direction:column;gap:.6mm}.kkp-lab__name,.kkp-lab__phone{line-height:1.2;color:#000}.kkp-lab__addr{font-size:1.08em;color:#000!important;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.kkp-lab__area{font-weight:600}'
		. '.kkp-lab__cod{display:flex;justify-content:space-between;align-items:center;border:.5mm solid #000;border-radius:1.5mm;padding:1.6mm 2.4mm}.kkp-lab__cod strong{color:#000}'
		. '.kkp-lab__ref{display:flex;flex-wrap:wrap;gap:1mm 4mm;font-size:.95em}.kkp-lab__bc{display:flex;flex-direction:column;align-items:center;gap:.5mm}.kkp-lab__bc svg{width:100%;height:14mm}.kkp-lab__bc span{font:600 .95em/1 ui-monospace,Consolas,monospace;letter-spacing:.08em}'
		. '.kkp-lab__items{margin:0;font-size:.88em;color:#333;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}.kkp-lab__note{margin:0;font-size:.88em;font-style:italic;color:#333}'
		. ( isset( $lab[ $size ] ) ? $lab[ $size ] : '' )
		. '@media print{html,body{background:#fff}.kkp-bar{display:none}.kkp-sheet{margin:0;gap:0;display:block}.kkp--a4 .kkp-sheet{display:grid}.kkp-inv{min-height:296mm}.kkp-lab{break-after:page}.kkp-inv:last-child,.kkp-lab:last-child{break-after:auto}.kkp--a4 .kkp-lab{break-after:auto}.kkp--a4 .kkp-lab:nth-child(6n){break-after:page}}';
}

/* =========================================================================
 * The print page itself
 * ====================================================================== */
add_action( 'admin_post_kk_print', function () {
	if ( ! kk_opt( 'print_enable' ) ) {
		wp_die( esc_html__( 'Printing is turned off. An administrator can turn it on in Kenakata Kit → Invoice & label.', 'kenakata-kit' ), '', array( 'response' => 403 ) );
	}
	if ( ! current_user_can( 'edit_shop_orders' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'kk_print' ) ) {
		wp_die( esc_html__( 'This link has expired. Go back and press Print again.', 'kenakata-kit' ), '', array( 'response' => 403 ) );
	}
	$kind   = isset( $_GET['kind'] ) && 'label' === $_GET['kind'] ? 'label' : 'invoice';
	$ids    = isset( $_GET['ids'] ) ? array_slice( array_unique( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['ids'] ) ) ) ) ) ), 0, kk_print_max() ) : array();
	$orders = array();
	foreach ( $ids as $id ) {
		$o = wc_get_order( $id );
		if ( $o instanceof WC_Order && 'shop_order' === $o->get_type() ) {
			$orders[] = $o;
		}
	}
	if ( ! $orders ) {
		wp_die( esc_html__( 'No orders to print.', 'kenakata-kit' ), '', array( 'response' => 404 ) );
	}
	kk_print_page( $kind, $orders );
	exit;
} );

/* =========================================================================
 * WooCommerce → Orders: bulk actions (classic and HPOS) + buttons on the order screen
 * ====================================================================== */

/** Bulk "Send to courier" actions: one per connected courier (Pro). */
function kk_bulk_courier_actions() {
	if ( ! function_exists( 'kk_courier_enabled' ) || ! function_exists( 'kk_courier_load' ) || ! current_user_can( 'edit_shop_orders' ) ) {
		return array();
	}
	$out = array();
	foreach ( kk_courier_enabled() as $key => $label ) {
		/* translators: %s: courier name */
		$out[ 'kk_send_' . $key ] = sprintf( __( 'Send to %s', 'kenakata-kit' ), $label );
	}
	if ( $out ) {
		$out['kk_courier_sync'] = __( 'Sync courier status', 'kenakata-kit' );
	}
	return $out;
}

function kk_bulk_add( $actions ) {
	if ( ! current_user_can( 'edit_shop_orders' ) ) {
		return $actions;
	}
	if ( kk_opt( 'print_enable' ) ) {
		$actions['kk_print_invoice'] = __( 'Print invoices', 'kenakata-kit' );
		$actions['kk_print_label']   = __( 'Print shipping labels', 'kenakata-kit' );
	}
	return array_merge( $actions, kk_bulk_courier_actions() );
}
add_filter( 'bulk_actions-edit-shop_order', 'kk_bulk_add', 30 );
add_filter( 'bulk_actions-woocommerce_page_wc-orders', 'kk_bulk_add', 30 );

/** Most parcels sent / statuses asked per press (each is one call to the courier). */
function kk_bulk_courier_max( $sync = false ) {
	return (int) apply_filters( 'kk_bulk_courier_max', $sync ? 40 : 15, $sync );
}

/**
 * Send orders to a courier, or refresh their courier status. Returns array( done, skipped, errors[] ).
 * Errors: "#number: message", at most 5 kept.
 */
function kk_bulk_courier_run( $action, array $ids ) {
	$res   = array( 'done' => 0, 'skipped' => 0, 'errors' => array() );
	if ( ! function_exists( 'kk_courier_load' ) ) {
		return $res;
	}
	kk_courier_load();
	$sync  = 'kk_courier_sync' === $action;
	$max   = kk_bulk_courier_max( $sync );
	$tried = 0;
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- not allowed on every host
	}
	$courier = $sync ? '' : substr( $action, 8 );
	foreach ( $ids as $id ) {
		if ( $tried >= $max ) {
			$res['skipped']++; // over the limit for one press: press again for the rest
			continue;
		}
		$order = wc_get_order( absint( $id ) );
		if ( ! $order instanceof WC_Order ) {
			continue;
		}
		$b    = $order->get_meta( '_kk_courier_booking' );
		$prov = is_array( $b ) ? (string) ( $b['provider'] ?? '' ) : '';
		$stat = is_array( $b ) ? (string) ( $b['status'] ?? '' ) : '';
		if ( $sync ) {
			if ( ! is_array( $b ) || empty( $b['consignment_id'] ) || kk_courier_is_delivered( $prov, $stat ) || kk_courier_is_cancelled( $prov, $stat ) ) {
				$res['skipped']++;
				continue;
			}
			$tried++;
			$r = kk_courier_refresh_status( $order );
		} else {
			if ( ! $order->has_status( array( 'processing', 'on-hold', 'pending' ) ) || ( is_array( $b ) && ! empty( $b['consignment_id'] ) && ! kk_courier_is_cancelled( $prov, $stat ) ) ) {
				$res['skipped']++; // only new orders that were not sent yet
				continue;
			}
			$tried++;
			$r = kk_courier_book_order( $order, array( 'courier' => $courier ) );
		}
		if ( is_wp_error( $r ) ) {
			if ( count( $res['errors'] ) < 5 ) {
				$res['errors'][] = '#' . $order->get_order_number() . ': ' . wp_strip_all_tags( $r->get_error_message() );
			}
		} else {
			$res['done']++;
		}
	}
	return $res;
}

function kk_bulk_handle( $redirect, $action, $ids ) {
	$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
	if ( ! $ids || ! current_user_can( 'edit_shop_orders' ) ) {
		return $redirect;
	}
	if ( in_array( $action, array( 'kk_print_invoice', 'kk_print_label' ), true ) ) {
		if ( ! kk_opt( 'print_enable' ) ) {
			return add_query_arg( 'kk_print_off', 1, $redirect );
		}
		return kk_print_url( 'kk_print_label' === $action ? 'label' : 'invoice', array_slice( $ids, 0, kk_print_max() ) );
	}
	if ( ! array_key_exists( $action, kk_bulk_courier_actions() ) ) {
		return $redirect;
	}
	$res = kk_bulk_courier_run( $action, $ids );
	set_transient( 'kk_bulk_res_' . get_current_user_id(), array( 'sync' => 'kk_courier_sync' === $action ) + $res, 300 );
	return add_query_arg( 'kk_bulk', 1, remove_query_arg( array( 'kk_bulk', 'kk_print_off' ), $redirect ) );
}
add_filter( 'handle_bulk_actions-edit-shop_order', 'kk_bulk_handle', 10, 3 );
add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', 'kk_bulk_handle', 10, 3 );

add_action( 'admin_notices', function () {
	if ( isset( $_GET['kk_print_off'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Printing is turned off. Turn it on in Kenakata Kit → Invoice & label.', 'kenakata-kit' ) . '</p></div>';
	}
	if ( ! isset( $_GET['kk_bulk'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
		return;
	}
	$res = get_transient( 'kk_bulk_res_' . get_current_user_id() );
	if ( ! is_array( $res ) ) {
		return;
	}
	delete_transient( 'kk_bulk_res_' . get_current_user_id() );
	$msg = $res['sync']
		/* translators: 1: orders updated 2: orders skipped */
		? sprintf( __( 'Courier status updated for %1$d orders. Skipped %2$d (not sent from here, already delivered or cancelled, or over the limit for one press).', 'kenakata-kit' ), $res['done'], $res['skipped'] )
		/* translators: 1: orders sent 2: orders skipped */
		: sprintf( __( 'Sent %1$d orders to the courier. Skipped %2$d (already sent, not Processing / On hold / Pending, or over the limit for one press).', 'kenakata-kit' ), $res['done'], $res['skipped'] );
	$cls = $res['errors'] ? 'notice-warning' : 'notice-success';
	echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p>';
	if ( $res['errors'] ) {
		echo '<ul style="list-style:disc;margin-left:20px">';
		foreach ( $res['errors'] as $e ) {
			echo '<li>' . esc_html( $e ) . '</li>';
		}
		echo '</ul>';
	}
	echo '</div>';
} );

// Order screen: two buttons under the shipping address.
add_action( 'woocommerce_admin_order_data_after_shipping_address', function ( $order ) {
	if ( ! $order instanceof WC_Order || ! kk_opt( 'print_enable' ) || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}
	echo '<p class="kk-print-btns" style="clear:both;padding-top:8px">'
		. '<a class="button" target="_blank" rel="noopener" href="' . esc_url( kk_print_url( 'invoice', array( $order->get_id() ) ) ) . '">' . esc_html__( 'Print invoice', 'kenakata-kit' ) . '</a> '
		. '<a class="button" target="_blank" rel="noopener" href="' . esc_url( kk_print_url( 'label', array( $order->get_id() ) ) ) . '">' . esc_html__( 'Print label', 'kenakata-kit' ) . '</a></p>';
}, 20 );
