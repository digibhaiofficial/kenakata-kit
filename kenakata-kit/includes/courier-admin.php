<?php
/**
 * Courier check inside WooCommerce: a "Courier check" column next to the status on WooCommerce → Orders, and a
 * Courier check box under the billing / shipping addresses on the order screen. A click opens a side panel with
 * the customer's delivery record (this shop + all couriers through BD Courier when a token is saved).
 * The last result is kept on the order (_kk_cc) so the list shows a coloured badge without asking again.
 *
 * @package KenakataKit
 */

defined( 'ABSPATH' ) || exit;

/** Short summary kept on the order after a check: rate (0–100 or null), level (good/caution/risky/new), time. */
function kk_cc_store( WC_Order $order, array $res ) {
	$best = null;
	foreach ( (array) ( $res['sources'] ?? array() ) as $s ) {
		if ( ( $s['success'] + $s['cancelled'] ) > ( $best ? $best['success'] + $best['cancelled'] : 0 ) ) {
			$best = $s;
		}
	}
	$order->update_meta_data( '_kk_cc', array(
		'rate'  => $best ? $best['rate'] : null,
		'total' => $best ? (int) $best['total'] : 0,
		'level' => (string) ( $res['verdict']['level'] ?? 'new' ),
		'at'    => time(),
	) );
	$order->save_meta_data();
}

/** Badge text + level for the saved summary, or null when the order was never checked. */
function kk_cc_badge( WC_Order $order ) {
	$cc = $order->get_meta( '_kk_cc' );
	if ( ! is_array( $cc ) || empty( $cc['at'] ) ) {
		return null;
	}
	$level = in_array( $cc['level'] ?? '', array( 'good', 'caution', 'risky', 'new' ), true ) ? $cc['level'] : 'new';
	$text  = null === $cc['rate'] ? __( 'New', 'kenakata-kit' ) : $cc['rate'] . '%';
	return array( 'level' => $level, 'text' => $text, 'total' => (int) ( $cc['total'] ?? 0 ) );
}

function kk_cc_button( WC_Order $order, $big = false ) {
	$b     = kk_cc_badge( $order );
	$label = $b
		/* translators: 1: success rate, e.g. 85% 2: number of parcels */
		? sprintf( __( '%1$s · %2$d parcels', 'kenakata-kit' ), $b['text'], $b['total'] )
		: __( 'Courier check', 'kenakata-kit' );
	return sprintf(
		'<button type="button" class="kk-cc-btn%1$s%2$s" data-kk-cc="%3$d" title="%4$s">%5$s</button>',
		$b ? ' kk-cc-btn--' . esc_attr( $b['level'] ) : '',
		$big ? ' button' : '',
		absint( $order->get_id() ),
		esc_attr__( 'See this customer\'s delivery record', 'kenakata-kit' ),
		esc_html( $label )
	);
}

/* ---- Orders list (classic and HPOS) ---- */
function kk_cc_add_column( $cols ) {
	if ( ! kk_courier_check_enabled() || ! current_user_can( 'edit_shop_orders' ) ) {
		return $cols;
	}
	$out = array();
	foreach ( $cols as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'order_status' === $k ) {
			$out['kk_cc'] = __( 'Courier check', 'kenakata-kit' );
		}
	}
	if ( ! isset( $out['kk_cc'] ) ) {
		$out['kk_cc'] = __( 'Courier check', 'kenakata-kit' );
	}
	return $out;
}
add_filter( 'manage_edit-shop_order_columns', 'kk_cc_add_column', 20 );
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'kk_cc_add_column', 20 );

add_action( 'manage_shop_order_posts_custom_column', function ( $col, $post_id ) {
	if ( 'kk_cc' === $col ) {
		$order = wc_get_order( $post_id );
		echo $order ? kk_cc_button( $order ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_cc_button()
	}
}, 10, 2 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', function ( $col, $order ) {
	if ( 'kk_cc' === $col && $order instanceof WC_Order ) {
		echo kk_cc_button( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_cc_button()
	}
}, 10, 2 );

/* ---- Single order screen: under the billing / shipping addresses ---- */
add_action( 'woocommerce_admin_order_data_after_shipping_address', function ( $order ) {
	if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
		return;
	}
	echo '<div class="kk-cc-box"><h3>' . esc_html__( 'Courier check', 'kenakata-kit' ) . '</h3>';
	if ( ! kk_courier_check_enabled() ) {
		echo '<p class="description">' . esc_html__( 'Courier check is turned off.', 'kenakata-kit' );
		if ( current_user_can( 'manage_options' ) ) {
			echo ' <a href="' . esc_url( admin_url( 'admin.php?page=kenakata-kit&tab=courier' ) ) . '">' . esc_html__( 'Turn it on', 'kenakata-kit' ) . '</a>';
		}
		echo '</p></div>';
		return;
	}
	echo '<p>' . kk_cc_button( $order, true ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_cc_button()
} );

/* ---- Script + styles on the order screens only ---- */
add_action( 'admin_enqueue_scripts', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$ids    = array( 'edit-shop_order', 'shop_order', 'woocommerce_page_wc-orders' );
	if ( ! $screen || ! in_array( $screen->id, $ids, true ) || ! kk_courier_check_enabled() ) {
		return;
	}
	wp_enqueue_style( 'kenakata-kit-cc', KK_URL . 'assets/courier-check.css', array(), KK_VERSION );
	wp_enqueue_script( 'kenakata-kit-cc', KK_URL . 'assets/courier-check.js', array(), KK_VERSION, true );
	wp_localize_script( 'kenakata-kit-cc', 'kkCC', array(
		'url'   => esc_url_raw( rest_url( 'kenakata/v1/courier/checkup/' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
		'i18n'  => array(
			'title'     => __( 'Courier check', 'kenakata-kit' ),
			'close'     => __( 'Close', 'kenakata-kit' ),
			'loading'   => __( 'Checking…', 'kenakata-kit' ),
			'again'     => __( 'Check again', 'kenakata-kit' ),
			'failed'    => __( 'Could not check right now. Please try again.', 'kenakata-kit' ),
			'total'     => __( 'Total', 'kenakata-kit' ),
			'delivered' => __( 'Delivered', 'kenakata-kit' ),
			'returned'  => __( 'Returned / cancelled', 'kenakata-kit' ),
			'open'      => __( 'On the way', 'kenakata-kit' ),
			'rate'      => __( 'Success', 'kenakata-kit' ),
			'byCourier' => __( 'By courier', 'kenakata-kit' ),
			'reports'   => __( 'Reports from other shops', 'kenakata-kit' ),
			'parcels'   => __( '%1$s · %2$d parcels', 'kenakata-kit' ),
			'new'       => __( 'New', 'kenakata-kit' ),
			'checked'   => __( 'Checked %s ago', 'kenakata-kit' ),
		),
	) );
} );
