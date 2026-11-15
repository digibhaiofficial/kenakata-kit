<?php
/**
 * Mobile Bottom Bar: an app-like navigation bar fixed to the bottom of the screen on phones.
 * Items (order, on/off, label, icon, link) and the whole look are set in Kenakata Kit → Bottom Bar.
 */

defined( 'ABSPATH' ) || exit;

/** Icons that can be picked for bottom bar items (key => label). */
function kk_bbar_icons() {
	return array(
		'home'     => __( 'Home', 'kenakata-kit' ),
		'store'    => __( 'Shop', 'kenakata-kit' ),
		'grid'     => __( 'Categories', 'kenakata-kit' ),
		'search'   => __( 'Search', 'kenakata-kit' ),
		'cart'     => __( 'Cart', 'kenakata-kit' ),
		'bag'      => __( 'Bag', 'kenakata-kit' ),
		'user'     => __( 'Account', 'kenakata-kit' ),
		'box'      => __( 'Orders', 'kenakata-kit' ),
		'truck'    => __( 'Delivery', 'kenakata-kit' ),
		'location' => __( 'Location', 'kenakata-kit' ),
		'whatsapp' => 'WhatsApp',
		'phone'    => __( 'Phone', 'kenakata-kit' ),
		'chat'     => __( 'Chat', 'kenakata-kit' ),
		'heart'    => __( 'Wishlist', 'kenakata-kit' ),
		'tag'      => __( 'Offers', 'kenakata-kit' ),
		'gift'     => __( 'Gift', 'kenakata-kit' ),
		'menu'     => __( 'Menu', 'kenakata-kit' ),
	);
}

/** Link types for items (key => label). */
function kk_bbar_links() {
	return array(
		'home'       => __( 'Home page', 'kenakata-kit' ),
		'shop'       => __( 'Shop page', 'kenakata-kit' ),
		'categories' => __( 'Categories page', 'kenakata-kit' ),
		'search'     => __( 'Open live search', 'kenakata-kit' ),
		'cart'       => __( 'Cart (side cart when on)', 'kenakata-kit' ),
		'account'    => __( 'My account (login popup for visitors)', 'kenakata-kit' ),
		'orders'     => __( 'My orders (tracking page for visitors)', 'kenakata-kit' ),
		'track'      => __( 'Order tracking page', 'kenakata-kit' ),
		'whatsapp'   => __( 'WhatsApp chat', 'kenakata-kit' ),
		'call'       => __( 'Phone call', 'kenakata-kit' ),
		'custom'     => __( 'Custom link', 'kenakata-kit' ),
	);
}

/** Default items (used until the owner saves their own list). */
function kk_bbar_default_items() {
	return array(
		array( 'id' => 'home', 'on' => 1, 'label' => 'Home', 'icon' => 'home', 'link' => 'home', 'url' => '' ),
		array( 'id' => 'shop', 'on' => 1, 'label' => 'Shop', 'icon' => 'store', 'link' => 'shop', 'url' => '' ),
		array( 'id' => 'cart', 'on' => 1, 'label' => 'Cart', 'icon' => 'cart', 'link' => 'cart', 'url' => '' ),
		array( 'id' => 'account', 'on' => 1, 'label' => 'Account', 'icon' => 'user', 'link' => 'account', 'url' => '' ),
		array( 'id' => 'orders', 'on' => 1, 'label' => 'Orders', 'icon' => 'box', 'link' => 'orders', 'url' => '' ),
		array( 'id' => 'categories', 'on' => 0, 'label' => 'Categories', 'icon' => 'grid', 'link' => 'categories', 'url' => '' ),
		array( 'id' => 'search', 'on' => 0, 'label' => 'Search', 'icon' => 'search', 'link' => 'search', 'url' => '' ),
		array( 'id' => 'track', 'on' => 0, 'label' => 'Track', 'icon' => 'truck', 'link' => 'track', 'url' => '' ),
		array( 'id' => 'whatsapp', 'on' => 0, 'label' => 'WhatsApp', 'icon' => 'whatsapp', 'link' => 'whatsapp', 'url' => '' ),
		array( 'id' => 'call', 'on' => 0, 'label' => 'Call', 'icon' => 'phone', 'link' => 'call', 'url' => '' ),
		array( 'id' => 'custom1', 'on' => 0, 'label' => 'Offers', 'icon' => 'tag', 'link' => 'custom', 'url' => '' ),
		array( 'id' => 'custom2', 'on' => 0, 'label' => 'Wishlist', 'icon' => 'heart', 'link' => 'custom', 'url' => '' ),
	);
}

/** Clean an items list (from JSON or array). Unknown ids/icons/links are dropped or reset. */
function kk_bbar_sanitize_items( $value ) {
	$list = is_array( $value ) ? $value : json_decode( (string) $value, true );
	if ( ! is_array( $list ) ) {
		return kk_bbar_default_items();
	}
	$defaults = array();
	foreach ( kk_bbar_default_items() as $d ) {
		$defaults[ $d['id'] ] = $d;
	}
	$icons = kk_bbar_icons();
	$links = kk_bbar_links();
	$out   = array();
	$seen  = array();
	foreach ( $list as $it ) {
		if ( ! is_array( $it ) || empty( $it['id'] ) ) {
			continue;
		}
		$id = sanitize_key( $it['id'] );
		if ( ! isset( $defaults[ $id ] ) || isset( $seen[ $id ] ) ) {
			continue;
		}
		$seen[ $id ] = true;
		$out[]       = array(
			'id'    => $id,
			'on'    => empty( $it['on'] ) ? 0 : 1,
			'label' => isset( $it['label'] ) ? mb_substr( sanitize_text_field( $it['label'] ), 0, 24 ) : $defaults[ $id ]['label'],
			'icon'  => isset( $it['icon'] ) && isset( $icons[ $it['icon'] ] ) ? $it['icon'] : $defaults[ $id ]['icon'],
			'link'  => isset( $it['link'] ) && isset( $links[ $it['link'] ] ) ? $it['link'] : $defaults[ $id ]['link'],
			'url'   => isset( $it['url'] ) ? esc_url_raw( $it['url'] ) : '',
		);
	}
	foreach ( $defaults as $id => $d ) { // items added in later versions appear (switched off) at the end
		if ( ! isset( $seen[ $id ] ) ) {
			$d['on'] = 0;
			$out[]   = $d;
		}
	}
	return $out;
}

/** The saved items list. */
function kk_bbar_items() {
	$saved = kk_opt( 'bbar_items' );
	return '' === (string) $saved ? kk_bbar_default_items() : kk_bbar_sanitize_items( $saved );
}

/** URL, extra attributes and "is current page" for one item. */
function kk_bbar_target( array $it ) {
	$attrs  = array();
	$active = false;
	switch ( $it['link'] ) {
		case 'home':
			$url    = home_url( '/' );
			$active = is_front_page();
			break;
		case 'shop':
			$url    = wc_get_page_permalink( 'shop' );
			$active = function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() || is_product() );
			break;
		case 'categories':
			$url    = kk_opt( 'bbar_cats_url' ) ? kk_opt( 'bbar_cats_url' ) : ( kk_page_url( 'none', 'categories' ) ? kk_page_url( 'none', 'categories' ) : wc_get_page_permalink( 'shop' ) );
			$active = is_product_category();
			break;
		case 'search':
			$url                          = add_query_arg( array( 's' => '', 'post_type' => 'product' ), home_url( '/' ) );
			$attrs['data-kk-search-open'] = '';
			$attrs['aria-haspopup']       = 'dialog';
			$active                       = is_search();
			break;
		case 'cart':
			$url    = wc_get_cart_url();
			$active = is_cart();
			if ( kk_opt( 'cart_drawer_enable' ) ) {
				$attrs['data-kk-drawer'] = '';
			}
			break;
		case 'account':
			$url    = wc_get_page_permalink( 'myaccount' );
			$active = is_account_page() && ! is_wc_endpoint_url( 'orders' ) && ! is_wc_endpoint_url( 'view-order' );
			if ( ! is_user_logged_in() ) {
				$attrs['data-kk-login'] = '';
			}
			break;
		case 'orders':
			$track = kk_page_url( 'tr_page', 'track-order' );
			if ( is_user_logged_in() || ! $track ) {
				$url    = wc_get_account_endpoint_url( 'orders' );
				$active = is_wc_endpoint_url( 'orders' ) || is_wc_endpoint_url( 'view-order' );
			} else {
				$url    = $track;
				$active = is_page( url_to_postid( $track ) );
			}
			break;
		case 'track':
			$url    = kk_page_url( 'tr_page', 'track-order' );
			$active = $url && is_page( url_to_postid( $url ) );
			break;
		case 'whatsapp':
			$url             = kk_whatsapp_url( kk_fill_product_tokens( is_singular( 'product' ) ? kk_opt( 'pp_wa_message' ) : kk_opt( 'wa_float_message' ) ) );
			$attrs['target'] = '_blank';
			$attrs['rel']    = 'noopener';
			break;
		case 'call':
			$url = kk_call_url();
			break;
		default:
			$url = $it['url'];
			if ( $url ) {
				$here   = home_url( add_query_arg( array() ) );
				$active = untrailingslashit( strtok( $url, '?#' ) ) === untrailingslashit( strtok( $here, '?#' ) );
			}
	}
	return array( (string) $url, $attrs, $active );
}

/** Should the bar be printed on this request? */
function kk_bbar_visible() {
	if ( ! kk_opt( 'mnav_enable' ) || is_admin() || wp_doing_ajax() || is_feed() || is_embed() ) {
		return false;
	}
	if ( kk_is_editor() ) {
		return false;
	}
	if ( kk_opt( 'bbar_hide_checkout' ) && function_exists( 'is_checkout' ) && is_checkout() ) {
		return false;
	}
	if ( kk_opt( 'bbar_hide_cart' ) && function_exists( 'is_cart' ) && is_cart() ) {
		return false;
	}
	return (bool) apply_filters( 'kk_bottom_bar_visible', true );
}

/** Size/colour CSS for the bar, including the breakpoint media query. */
function kk_bbar_css() {
	$bp      = max( 360, min( 1200, absint( kk_opt( 'bbar_breakpoint' ) ) ) );
	$shadows = array( 'none' => 'none', 'soft' => '0 -6px 22px -14px rgba(16,23,20,.35)', 'strong' => '0 -10px 30px -10px rgba(16,23,20,.45)' );
	$shadow  = isset( $shadows[ kk_opt( 'bbar_shadow' ) ] ) ? $shadows[ kk_opt( 'bbar_shadow' ) ] : $shadows['soft'];
	$vars    = array(
		'--kk-bbar-h'      => max( 48, min( 90, absint( kk_opt( 'bbar_height' ) ) ) ) . 'px',
		'--kk-bbar-icon'   => max( 16, min( 34, absint( kk_opt( 'bbar_icon_size' ) ) ) ) . 'px',
		'--kk-bbar-label'  => max( 9, min( 16, absint( kk_opt( 'bbar_label_size' ) ) ) ) . 'px',
		'--kk-bbar-bg'     => kk_opt( 'bbar_bg' ),
		'--kk-bbar-border' => kk_opt( 'bbar_border' ),
		'--kk-bbar-shadow' => $shadow,
		'--kk-bbar-icon-c' => kk_opt( 'bbar_icon_color' ),
		'--kk-bbar-text-c' => kk_opt( 'bbar_text_color' ),
		'--kk-bbar-active' => kk_opt( 'bbar_active_color' ),
		'--kk-bbar-act-bg' => kk_opt( 'bbar_active_bg' ),
		'--kk-bbar-badge'  => kk_opt( 'bbar_badge_bg' ),
	);
	$css = ':root{';
	foreach ( $vars as $k => $v ) {
		$css .= $k . ':' . str_replace( array( '{', '}', ';', '<' ), '', (string) $v ) . ';';
	}
	$css .= '}';
	// Shown only below the breakpoint; the spacer keeps page content above the bar.
	$css .= '@media (max-width:' . $bp . 'px){.kk-bbar{display:flex}.kk-bbar-spacer{display:block}body.kk-has-bbar .kk-float{bottom:calc(var(--kk-float-offset,20px) + var(--kk-bbar-h) + env(safe-area-inset-bottom))}}';
	return $css;
}

add_action( 'wp_enqueue_scripts', function () {
	if ( kk_opt( 'mnav_enable' ) ) {
		wp_add_inline_style( 'kenakata-kit', kk_bbar_css() );
	}
}, 30 );

add_filter( 'body_class', function ( $classes ) {
	if ( kk_bbar_visible() ) {
		$classes[] = 'kk-has-bbar';
	}
	return $classes;
} );

/** The bar markup (also used by the admin preview). */
function kk_bbar_render( array $items = null, array $opts = array() ) {
	$items    = null === $items ? kk_bbar_items() : $items;
	$style    = isset( $opts['style'] ) ? $opts['style'] : kk_opt( 'bbar_style' );
	$featured = isset( $opts['featured'] ) ? $opts['featured'] : kk_opt( 'bbar_featured' );
	$labels   = isset( $opts['labels'] ) ? $opts['labels'] : kk_opt( 'bbar_labels' );
	$count    = kk_cart_count();
	$cls      = 'kk-bbar kk-bbar--' . sanitize_html_class( $style ) . ( $labels ? '' : ' kk-bbar--no-labels' ) . ( kk_opt( 'bbar_hide_on_scroll' ) ? ' kk-bbar--autohide' : '' );
	$html     = '';
	$n        = 0;
	foreach ( $items as $it ) {
		if ( empty( $it['on'] ) ) {
			continue;
		}
		list( $url, $attrs, $active ) = kk_bbar_target( $it );
		if ( ! $url ) {
			continue; // e.g. WhatsApp without a number
		}
		$n++;
		$item_cls = 'kk-bbar__item' . ( $active ? ' is-active' : '' ) . ( $featured === $it['id'] ? ' kk-bbar__item--featured' : '' );
		$attr_str = '';
		foreach ( $attrs as $k => $v ) {
			$attr_str .= ' ' . esc_attr( $k ) . ( '' === $v ? '' : '="' . esc_attr( $v ) . '"' );
		}
		$badge = 'cart' === $it['link'] ? '<span class="kk-bbar__count' . ( $count ? '' : ' is-empty' ) . '" aria-hidden="true">' . esc_html( $count ) . '</span>' : '';
		$label = '' !== $it['label'] ? $it['label'] : ( kk_bbar_icons()[ $it['icon'] ] ?? '' );
		$sr    = 'cart' === $it['link'] ? ' <span class="screen-reader-text kk-bbar__sr">' . esc_html( sprintf( /* translators: %d: number of items in the cart */ _n( '(%d item)', '(%d items)', $count, 'kenakata-kit' ), $count ) ) . '</span>' : '';
		$html .= '<a class="' . esc_attr( $item_cls ) . '" href="' . kk_esc_link( $url ) . '"' . $attr_str . ( $active ? ' aria-current="page"' : '' ) . ( $labels ? '' : ' aria-label="' . esc_attr( $label ) . '"' ) . '>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
			. '<span class="kk-bbar__icon">' . kk_icon( $it['icon'] ) . $badge . '</span>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG
			. ( $labels ? '<span class="kk-bbar__label">' . esc_html( $label ) . '</span>' : '' ) . $sr . '</a>';
	}
	if ( ! $n ) {
		return '';
	}
	return '<nav class="' . esc_attr( $cls ) . '" style="--kk-bbar-n:' . (int) $n . '" aria-label="' . esc_attr__( 'Mobile navigation', 'kenakata-kit' ) . '">' . $html . '</nav>';
}

add_action( 'wp_footer', function () {
	if ( ! kk_bbar_visible() ) {
		return;
	}
	$bar = kk_bbar_render();
	if ( '' === $bar ) {
		return;
	}
	kk_need_js();
	echo '<div class="kk-bbar-spacer" aria-hidden="true"></div>' . $bar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_bbar_render()
	$uses_search = false;
	foreach ( kk_bbar_items() as $it ) {
		$uses_search = $uses_search || ( $it['on'] && 'search' === $it['link'] );
	}
	if ( $uses_search && kk_opt( 'search_enable' ) ) {
		echo '<div class="kk-bbar-search" hidden>' . kk_live_search_shortcode( array() ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the shortcode
	}
}, 17 );
