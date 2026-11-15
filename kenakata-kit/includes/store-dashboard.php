<?php
/**
 * Store dashboard: a simple shop-management screen at /dashboard (orders, customers' phone/WhatsApp, courier,
 * products, stock, blog) for people who should not use wp-admin.
 *
 * - Role "Store Manager (Kenakata)" (kk_store_manager): only what the dashboard needs, no wp-admin (optional).
 * - Administrators and WooCommerce shop managers can use the dashboard too (capability kk_store_dashboard).
 * - The page is a standalone, theme-free document (no theme, Elementor or other plugin assets): one CSS + one JS file.
 * - Data comes from /wp-json/kenakata/v1/sd/* (see store-dashboard-api.php), cookie login + REST nonce.
 */

defined( 'ABSPATH' ) || exit;

define( 'KK_SD_CAP', 'kk_store_dashboard' );
define( 'KK_SD_ROLE', 'kk_store_manager' );
define( 'KK_SD_ROLE_VERSION', '2' ); // bump when the role's capabilities change

// The dashboard's data API (43 KB of code) is needed only for REST requests, not on every page.
add_action( 'rest_api_init', function () {
	require_once KK_DIR . '/includes/store-dashboard-api.php';
}, 0 );

/** Capabilities of the Store Manager role. */
function kk_sd_role_caps() {
	$caps = array(
		'read', 'upload_files', KK_SD_CAP,
		// Blog.
		'edit_posts', 'edit_published_posts', 'publish_posts', 'edit_others_posts', 'read_private_posts',
		// Products (WooCommerce).
		'edit_products', 'edit_published_products', 'edit_others_products', 'edit_private_products', 'read_private_products', 'publish_products', 'assign_product_terms', 'manage_product_terms',
		// Orders (WooCommerce).
		'edit_shop_orders', 'edit_others_shop_orders', 'edit_published_shop_orders', 'edit_private_shop_orders', 'read_private_shop_orders',
	);
	return array_fill_keys( $caps, true );
}

/** Create / update the role once per role version; give administrators and shop managers the dashboard capability. */
add_action( 'init', function () {
	if ( get_option( 'kk_sd_role_version' ) === KK_SD_ROLE_VERSION ) {
		return;
	}
	remove_role( KK_SD_ROLE );
	add_role( KK_SD_ROLE, __( 'Store Manager (Kenakata)', 'kenakata-kit' ), kk_sd_role_caps() );
	foreach ( array( 'administrator', 'shop_manager' ) as $name ) {
		$role = get_role( $name );
		if ( $role ) {
			$role->add_cap( KK_SD_CAP );
		}
	}
	update_option( 'kk_sd_role_version', KK_SD_ROLE_VERSION, true ); // read on every request: autoload (no extra query)
}, 5 );

/** Dashboard slug ("dashboard" unless changed in settings). */
function kk_sd_slug() {
	$slug = sanitize_title( (string) kk_opt( 'sd_slug' ) );
	return '' !== $slug ? $slug : 'dashboard';
}

function kk_sd_url( $hash = '' ) {
	// "Plain" permalinks have no pretty paths: use ?kk-dashboard=1 instead.
	$url = get_option( 'permalink_structure' ) ? home_url( '/' . kk_sd_slug() . '/' ) : add_query_arg( 'kk-dashboard', '1', home_url( '/' ) );
	return $url . ( $hash ? '#/' . ltrim( $hash, '#/' ) : '' );
}

/**
 * Early check (before WordPress parses the request) whether this is the dashboard page or its REST API.
 * Used to pick the dashboard language like the admin screens ("Plugin language" setting / profile language).
 */
function kk_sd_is_dashboard_uri() {
	if ( isset( $_GET['kk-dashboard'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return true;
	}
	if ( isset( $_GET['rest_route'] ) && 0 === strpos( (string) wp_unslash( $_GET['rest_route'] ), '/kenakata/v1/sd/' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return true;
	}
	$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( '' !== $home && '/' !== $home && 0 === strpos( $path, $home ) ) {
		$path = substr( $path, strlen( $home ) - 1 );
	}
	return trim( $path, '/' ) === kk_sd_slug() || false !== strpos( $path, '/kenakata/v1/sd/' );
}

/** Is the current request the dashboard page? */
function kk_sd_is_request( $wp ) {
	if ( isset( $_GET['kk-dashboard'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only selects the page
		return true;
	}
	return trim( (string) $wp->request, '/' ) === kk_sd_slug();
}

/** True for users who only use the dashboard (Store Manager role, no shop-manager or admin rights). */
function kk_sd_is_dashboard_only_user( $user = null ) {
	$user = $user ? $user : wp_get_current_user();
	return $user && $user->exists() && in_array( KK_SD_ROLE, (array) $user->roles, true ) && ! user_can( $user, 'manage_woocommerce' ) && ! user_can( $user, 'manage_options' );
}

/** Does this user go to the dashboard right after logging in? Only people who may use it; setting decides which roles. */
function kk_sd_redirects_after_login( $user ) {
	if ( ! $user instanceof WP_User || ! user_can( $user, KK_SD_CAP ) ) {
		return false;
	}
	return kk_sd_is_dashboard_only_user( $user ) || 'staff' === kk_opt( 'sd_login_redirect' );
}

/** May the current user see money amounts (sales, cash with courier)? Administrators always; others by setting. */
function kk_sd_can_money() {
	return current_user_can( 'manage_options' ) || (bool) kk_opt( 'sd_see_sales' );
}

if ( kk_opt( 'sd_enable' ) ) {
	// Serve /dashboard before WordPress looks for a page (no rewrite rules to flush, works with any permalink setup).
	add_action( 'parse_request', function ( $wp ) {
		if ( is_admin() || ! kk_sd_is_request( $wp ) ) {
			return;
		}
		kk_sd_render_page();
		exit;
	}, 1 );

	// Staff go straight to the dashboard after login. Customers are never touched: they do not have the dashboard capability.
	add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof WP_User || ! kk_sd_redirects_after_login( $user ) ) {
			return $redirect_to;
		}
		$redirect_to = (string) $redirect_to;
		$requested   = (string) $requested;
		// Store Managers: wp-admin is never their start page. Others: only when they did not ask for a specific page.
		if ( kk_sd_is_dashboard_only_user( $user ) ) {
			$go = '' === $requested || false !== strpos( $redirect_to, '/wp-admin' );
		} else {
			$go = '' === $requested || untrailingslashit( $redirect_to ) === untrailingslashit( admin_url() );
		}
		return $go ? kk_sd_url() : $redirect_to;
	}, 20, 3 );
	add_filter( 'woocommerce_login_redirect', function ( $redirect, $user ) {
		if ( ! $user instanceof WP_User || ! kk_sd_redirects_after_login( $user ) ) {
			return $redirect;
		}
		if ( kk_sd_is_dashboard_only_user( $user ) ) {
			return kk_sd_url();
		}
		// Administrators / shop managers: only from the plain My Account or home page, not when they were sent back to e.g. checkout.
		$plain = array( untrailingslashit( home_url( '/' ) ), untrailingslashit( (string) wc_get_page_permalink( 'myaccount' ) ) );
		return in_array( untrailingslashit( (string) $redirect ), $plain, true ) ? kk_sd_url() : $redirect;
	}, 20, 2 );
	add_filter( 'show_admin_bar', function ( $show ) {
		return kk_sd_is_dashboard_only_user() ? false : $show;
	}, 20 );
	add_action( 'admin_init', function () {
		if ( ! kk_opt( 'sd_block_admin' ) || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! kk_sd_is_dashboard_only_user() ) {
			return;
		}
		global $pagenow;
		// Their own profile stays open (password, name, language).
		if ( in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php', 'profile.php' ), true ) ) {
			return;
		}
		wp_safe_redirect( kk_sd_url() );
		exit;
	}, 1 );
	// Administrators get a shortcut in the admin bar.
	add_action( 'admin_bar_menu', function ( $bar ) {
		if ( current_user_can( KK_SD_CAP ) ) {
			$bar->add_node( array( 'id' => 'kk-store-dashboard', 'title' => esc_html__( 'Store dashboard', 'kenakata-kit' ), 'href' => kk_sd_url(), 'parent' => 'site-name' ) );
		}
	}, 80 );
	// Keep new orders and stock changes visible right away.
	foreach ( array( 'woocommerce_new_order', 'woocommerce_order_status_changed', 'woocommerce_product_set_stock', 'woocommerce_variation_set_stock' ) as $hook ) {
		add_action( $hook, 'kk_sd_flush_summary' );
	}
}

/** Most options (variations) one product may get from the dashboard: keeps a save quick on small hosting. */
function kk_sd_max_variations() {
	return max( 10, (int) apply_filters( 'kk_sd_max_variations', 100 ) );
}

function kk_sd_flush_summary() {
	delete_transient( 'kk_sd_summary' );
	update_option( 'kk_sd_rep_gen', (int) get_option( 'kk_sd_rep_gen', 0 ) + 1, false ); // date-range reports are cached under this number
	foreach ( array( 'trend_7', 'trend_30', 'top', 'cod' ) as $name ) {
		delete_transient( 'kk_sd_rep_' . $name ); // insight blocks (loaded only when someone taps them)
	}
}

/**
 * Ready-made colour sets for the dashboard and the plugin's settings screens: key => [ label, primary, accent ].
 * Everything else (white, black, greys, status colours) stays fixed so the screens always read well.
 */
function kk_sd_presets() {
	return array(
		'purple'    => array( __( 'Purple & amber', 'kenakata-kit' ), '#6D28D9', '#F59E0B' ),
		'redyellow' => array( __( 'Red & yellow', 'kenakata-kit' ), '#C8102E', '#F5B700' ),
		'blue'      => array( __( 'Blue & orange', 'kenakata-kit' ), '#1D4ED8', '#F97316' ),
		'green'     => array( __( 'Green & yellow', 'kenakata-kit' ), '#047857', '#F59E0B' ),
		'orange'    => array( __( 'Orange & teal', 'kenakata-kit' ), '#EA580C', '#0F766E' ),
		'pink'      => array( __( 'Pink & violet', 'kenakata-kit' ), '#DB2777', '#7C3AED' ),
		'blackgold' => array( __( 'Black & gold', 'kenakata-kit' ), '#1F2937', '#D4A017' ),
	);
}

/** The two colours chosen for the dashboard: array( primary, accent ) as #RRGGBB. */
function kk_sd_theme_colors() {
	$theme   = (string) kk_opt( 'sd_theme' );
	$presets = kk_sd_presets();
	if ( isset( $presets[ $theme ] ) ) {
		return array( $presets[ $theme ][1], $presets[ $theme ][2] );
	}
	if ( 'custom' === $theme ) {
		$p = sanitize_hex_color( (string) kk_opt( 'sd_color_primary' ) );
		$a = sanitize_hex_color( (string) kk_opt( 'sd_color_accent' ) );
		return array( $p ? $p : '#038348', $a ? $a : '#F59E0B' );
	}
	// "auto": follow the shop. Elementor kit "Primary" if there is one, else the Kenakata primary colour.
	$primary = '';
	$kit     = absint( get_option( 'elementor_active_kit' ) );
	if ( $kit ) {
		$settings = get_post_meta( $kit, '_elementor_page_settings', true );
		if ( is_array( $settings ) && ! empty( $settings['system_colors'] ) && is_array( $settings['system_colors'] ) ) {
			foreach ( $settings['system_colors'] as $c ) {
				if ( isset( $c['_id'], $c['color'] ) && 'primary' === $c['_id'] && sanitize_hex_color( $c['color'] ) ) {
					$primary = sanitize_hex_color( $c['color'] );
					break;
				}
			}
		}
	}
	if ( '' === $primary ) {
		$primary = sanitize_hex_color( (string) kk_opt( 'color_primary' ) );
	}
	$accent = sanitize_hex_color( (string) kk_opt( 'color_accent' ) );
	return array( $primary ? $primary : '#038348', $accent ? $accent : '#C8155A' );
}

/** Brand (primary) colour of the dashboard. */
function kk_sd_brand_color() {
	$c = kk_sd_theme_colors();
	return $c[0];
}

/** WCAG contrast ratio between two #RRGGBB colours (1 to 21). */
function kk_sd_contrast_ratio( $a, $b ) {
	$lum = function ( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$out = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$v     = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$out[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $out[0] + 0.7152 * $out[1] + 0.0722 * $out[2];
	};
	$l1 = $lum( $a );
	$l2 = $lum( $b );
	return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
}

/** Readable text colour (white or near-black) for a button or badge with this background. */
function kk_sd_on_color( $hex ) {
	return kk_sd_contrast_ratio( $hex, '#FFFFFF' ) >= kk_sd_contrast_ratio( $hex, '#111827' ) ? '#FFFFFF' : '#111827';
}

/** CSS variables for the chosen colours (a few dozen bytes; no extra file, no library). */
function kk_sd_theme_css() {
	list( $primary, $accent ) = kk_sd_theme_colors();
	return ':root{--sd-brand:' . $primary . ';--sd-accent:' . $accent . ';--sd-on-brand:' . kk_sd_on_color( $primary ) . ';--sd-on-accent:' . kk_sd_on_color( $accent ) . '}';
}

/**
 * Order statuses for the dashboard: key without "wc-" => label.
 * WooCommerce's labels follow the site language; when a core status still has its English default name, the
 * dashboard's own (translatable) name is used, so a Bengali dashboard on an English site reads Bengali.
 * Statuses renamed by the shop or by another plugin keep their name.
 */
function kk_sd_statuses() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$own = array(
		'pending'    => array( 'Pending payment', __( 'Pending payment', 'kenakata-kit' ) ),
		'processing' => array( 'Processing', __( 'Processing', 'kenakata-kit' ) ),
		'on-hold'    => array( 'On hold', __( 'On hold', 'kenakata-kit' ) ),
		'completed'  => array( 'Completed', __( 'Completed', 'kenakata-kit' ) ),
		'cancelled'  => array( 'Cancelled', __( 'Cancelled', 'kenakata-kit' ) ),
		'refunded'   => array( 'Refunded', __( 'Refunded', 'kenakata-kit' ) ),
		'failed'     => array( 'Failed', __( 'Failed', 'kenakata-kit' ) ),
		'shipped'    => array( 'Shipped', _x( 'Shipped', 'Order status', 'kenakata-kit' ) ),
	);
	$out = array();
	foreach ( wc_get_order_statuses() as $key => $label ) {
		$k = 'wc-' === substr( $key, 0, 3 ) ? substr( $key, 3 ) : $key;
		if ( 'checkout-draft' === $k ) {
			continue;
		}
		$out[ $k ] = isset( $own[ $k ] ) && $label === $own[ $k ][0] ? $own[ $k ][1] : $label;
	}
	return $out;
}

/** Dashboard name of one order status. */
function kk_sd_status_label( $status ) {
	$all = kk_sd_statuses();
	return isset( $all[ $status ] ) ? $all[ $status ] : wc_get_order_status_name( $status );
}

/** Texts used by the dashboard script (translatable like the rest of the plugin). */
function kk_sd_strings() {
	return array(
		'home'            => __( 'Home', 'kenakata-kit' ),
		'orders'          => __( 'Orders', 'kenakata-kit' ),
		'products'        => __( 'Products', 'kenakata-kit' ),
		'blog'            => __( 'Blog', 'kenakata-kit' ),
		'more'            => __( 'More', 'kenakata-kit' ),
		'viewSite'        => __( 'View shop', 'kenakata-kit' ),
		'wpAdmin'         => __( 'WordPress admin', 'kenakata-kit' ),
		'logout'          => __( 'Log out', 'kenakata-kit' ),
		'profile'         => __( 'My profile & password', 'kenakata-kit' ),
		'today'           => __( 'Today', 'kenakata-kit' ),
		'last7'           => __( 'Last 7 days', 'kenakata-kit' ),
		'last30'          => __( 'Last 30 days', 'kenakata-kit' ),
		'ordersCount'     => __( 'orders', 'kenakata-kit' ),
		'sales'           => __( 'Sales', 'kenakata-kit' ),
		'needsAction'     => __( 'Needs your attention', 'kenakata-kit' ),
		'newOrders'       => __( 'New orders to confirm', 'kenakata-kit' ),
		'lowStock'        => __( 'Low stock', 'kenakata-kit' ),
		'outOfStock'      => __( 'Out of stock', 'kenakata-kit' ),
		'quickActions'    => __( 'Quick actions', 'kenakata-kit' ),
		'addProduct'      => __( 'Add product', 'kenakata-kit' ),
		'newPost'         => __( 'Write a blog post', 'kenakata-kit' ),
		'all'             => __( 'All', 'kenakata-kit' ),
		'search'          => __( 'Search', 'kenakata-kit' ),
		'searchOrders'    => __( 'Order number, name or phone', 'kenakata-kit' ),
		'searchProducts'  => __( 'Product name or SKU', 'kenakata-kit' ),
		'searchPosts'     => __( 'Post title', 'kenakata-kit' ),
		'loadMore'        => __( 'Load more', 'kenakata-kit' ),
		'nothing'         => __( 'Nothing here yet.', 'kenakata-kit' ),
		'loading'         => __( 'Loading…', 'kenakata-kit' ),
		'error'           => __( 'Something went wrong. Please try again.', 'kenakata-kit' ),
		'offline'         => __( 'No internet connection.', 'kenakata-kit' ),
		'sessionEnded'    => __( 'Your login has ended. Please log in again.', 'kenakata-kit' ),
		'back'            => __( 'Back', 'kenakata-kit' ),
		'save'            => __( 'Save', 'kenakata-kit' ),
		'saving'          => __( 'Saving…', 'kenakata-kit' ),
		'saved'           => __( 'Saved', 'kenakata-kit' ),
		'cancel'          => __( 'Cancel', 'kenakata-kit' ),
		'unsaved'         => __( 'You have unsaved changes. Leave without saving?', 'kenakata-kit' ),
		'order'           => __( 'Order', 'kenakata-kit' ),
		'customer'        => __( 'Customer', 'kenakata-kit' ),
		'call'            => __( 'Call', 'kenakata-kit' ),
		'whatsapp'        => __( 'WhatsApp', 'kenakata-kit' ),
		'copy'            => __( 'Copy', 'kenakata-kit' ),
		'copied'          => __( 'Copied', 'kenakata-kit' ),
		'copyCourier'     => __( 'Copy details for courier', 'kenakata-kit' ),
		'address'         => __( 'Address', 'kenakata-kit' ),
		'area'            => __( 'Delivery area', 'kenakata-kit' ),
		'email'           => __( 'Email', 'kenakata-kit' ),
		'customerNote'    => __( 'Note from customer', 'kenakata-kit' ),
		'history'         => __( 'Order history of this number', 'kenakata-kit' ),
		'historyTotal'    => __( 'orders in total', 'kenakata-kit' ),
		'historyDone'     => __( 'delivered', 'kenakata-kit' ),
		'historyCancel'   => __( 'cancelled / failed', 'kenakata-kit' ),
		'firstOrder'      => __( 'First order from this number', 'kenakata-kit' ),
		'items'           => __( 'Items', 'kenakata-kit' ),
		'subtotal'        => __( 'Subtotal', 'kenakata-kit' ),
		'delivery'        => __( 'Delivery', 'kenakata-kit' ),
		'discount'        => __( 'Discount', 'kenakata-kit' ),
		'total'           => __( 'Total', 'kenakata-kit' ),
		'toCollect'       => __( 'Cash to collect', 'kenakata-kit' ),
		'payment'         => __( 'Payment', 'kenakata-kit' ),
		'status'          => __( 'Status', 'kenakata-kit' ),
		'changeStatus'    => __( 'Change status', 'kenakata-kit' ),
		'courier'         => __( 'Courier & tracking', 'kenakata-kit' ),
		'courierName'     => __( 'Courier', 'kenakata-kit' ),
		'selectCourier'   => __( '— Select —', 'kenakata-kit' ),
		'trackingId'      => __( 'Tracking / consignment ID', 'kenakata-kit' ),
		'trackingUrl'     => __( 'Tracking link', 'kenakata-kit' ),
		'trackingHelp'    => __( 'Saving a new tracking link sends it to the customer and marks the order as Shipped.', 'kenakata-kit' ),
		'notes'           => __( 'Notes', 'kenakata-kit' ),
		'addNote'         => __( 'Add note', 'kenakata-kit' ),
		'notePrivate'     => __( 'Private (team only)', 'kenakata-kit' ),
		'noteCustomer'    => __( 'Send to customer', 'kenakata-kit' ),
		'noteFromTeam'    => __( 'to customer', 'kenakata-kit' ),
		'system'          => __( 'System', 'kenakata-kit' ),
		'openInAdmin'     => __( 'Open in WordPress admin', 'kenakata-kit' ),
		'product'         => __( 'Product', 'kenakata-kit' ),
		'newProduct'      => __( 'New product', 'kenakata-kit' ),
		'editProduct'     => __( 'Edit product', 'kenakata-kit' ),
		'name'            => __( 'Name', 'kenakata-kit' ),
		'regularPrice'    => __( 'Regular price', 'kenakata-kit' ),
		'salePrice'       => __( 'Sale price (optional)', 'kenakata-kit' ),
		'salePriceError'  => __( 'The sale price must be lower than the regular price.', 'kenakata-kit' ),
		'sku'             => __( 'SKU (optional)', 'kenakata-kit' ),
		'stock'           => __( 'Stock', 'kenakata-kit' ),
		'trackStock'      => __( 'Count stock', 'kenakata-kit' ),
		'quantity'        => __( 'Quantity in stock', 'kenakata-kit' ),
		'inStock'         => __( 'In stock', 'kenakata-kit' ),
		'onBackorder'     => __( 'On backorder', 'kenakata-kit' ),
		'categories'      => __( 'Categories', 'kenakata-kit' ),
		'images'          => __( 'Pictures', 'kenakata-kit' ),
		'addImages'       => __( 'Add pictures', 'kenakata-kit' ),
		'mainImage'       => __( 'Main picture', 'kenakata-kit' ),
		'makeMain'        => __( 'Make main', 'kenakata-kit' ),
		'remove'          => __( 'Remove', 'kenakata-kit' ),
		'uploading'       => __( 'Uploading…', 'kenakata-kit' ),
		'shortDesc'       => __( 'Short description', 'kenakata-kit' ),
		'description'     => __( 'Description', 'kenakata-kit' ),
		'visibility'      => __( 'Visible on the shop', 'kenakata-kit' ),
		'published'       => __( 'Published', 'kenakata-kit' ),
		'draft'           => __( 'Draft', 'kenakata-kit' ),
		'pending'         => __( 'Waiting for review', 'kenakata-kit' ),
		'private'         => __( 'Private', 'kenakata-kit' ),
		'scheduled'       => __( 'Scheduled', 'kenakata-kit' ),
		'variations'      => __( 'Options (variations)', 'kenakata-kit' ),
		'variationsHelp'  => __( 'Each option has its own price and stock. To add new options or attributes, use WordPress admin.', 'kenakata-kit' ),
		'variableNote'    => __( 'Prices and stock of this product are set per option below.', 'kenakata-kit' ),
		'viewProduct'     => __( 'View on shop', 'kenakata-kit' ),
		'requiredName'    => __( 'Please enter a name.', 'kenakata-kit' ),
		'requiredPrice'   => __( 'Please enter a price.', 'kenakata-kit' ),
		'post'            => __( 'Post', 'kenakata-kit' ),
		'newPostTitle'    => __( 'New blog post', 'kenakata-kit' ),
		'title'           => __( 'Title', 'kenakata-kit' ),
		'content'         => __( 'Post text', 'kenakata-kit' ),
		'excerpt'         => __( 'Summary (optional)', 'kenakata-kit' ),
		'featuredImage'   => __( 'Cover picture', 'kenakata-kit' ),
		'choosePicture'   => __( 'Choose picture', 'kenakata-kit' ),
		'publish'         => __( 'Publish', 'kenakata-kit' ),
		'saveDraft'       => __( 'Save as draft', 'kenakata-kit' ),
		'heading'         => __( 'Heading', 'kenakata-kit' ),
		'bold'            => __( 'Bold', 'kenakata-kit' ),
		'italic'          => __( 'Italic', 'kenakata-kit' ),
		'list'            => __( 'List', 'kenakata-kit' ),
		'link'            => __( 'Link', 'kenakata-kit' ),
		'linkPrompt'      => __( 'Link address (https://…)', 'kenakata-kit' ),
		'viewPost'        => __( 'View post', 'kenakata-kit' ),
		'justNow'         => __( 'just now', 'kenakata-kit' ),
		'hello'           => __( 'Hello', 'kenakata-kit' ),
		'paragraph'       => __( 'Paragraph', 'kenakata-kit' ),
		'olist'           => __( 'Numbered list', 'kenakata-kit' ),
		'insertImage'     => __( 'Insert picture', 'kenakata-kit' ),
		'submitReview'    => __( 'Send for review', 'kenakata-kit' ),
		'update'          => __( 'Update', 'kenakata-kit' ),
		'toDraft'         => __( 'Move to draft', 'kenakata-kit' ),
		'noteAdded'       => __( 'Note added', 'kenakata-kit' ),
		'byStatus'        => __( 'Orders by status', 'kenakata-kit' ),
		'openShop'        => __( 'Open the shop', 'kenakata-kit' ),
		'blogPosts'       => __( 'Blog posts', 'kenakata-kit' ),
		'drafts'          => __( 'Drafts', 'kenakata-kit' ),
		'stockStatus'     => __( 'Stock status', 'kenakata-kit' ),
		'adminOnlyType'   => __( 'This kind of product can only be edited in WordPress admin.', 'kenakata-kit' ),
		'menu'            => __( 'Menu', 'kenakata-kit' ),
		'close'           => __( 'Close', 'kenakata-kit' ),
		'none'            => __( 'None', 'kenakata-kit' ),
		'retry'           => __( 'Try again', 'kenakata-kit' ),
		'priceStock'      => __( 'Price & stock', 'kenakata-kit' ),
		'optionsCount'    => __( 'options', 'kenakata-kit' ),
		'refunded'        => __( 'Refunded', 'kenakata-kit' ),
		'noAccess'        => __( 'You do not have permission for this.', 'kenakata-kit' ),
		'notFound'        => __( 'Not found.', 'kenakata-kit' ),
		'insights'       => __( 'More insights', 'kenakata-kit' ),
		'insightsHelp'    => __( 'These load only when you tap them, so the dashboard stays fast.', 'kenakata-kit' ),
		'salesTrend'      => __( 'Sales trend', 'kenakata-kit' ),
		'showIt' => __( 'Show', 'kenakata-kit' ),
		'blockNumber'   => __( 'Block this number', 'kenakata-kit' ),
		'unblockNumber' => __( 'Unblock this number', 'kenakata-kit' ),
		'blockConfirm'  => __( 'Block this phone number for future orders?', 'kenakata-kit' ),
		'numberBlocked' => __( 'This number is blocked.', 'kenakata-kit' ),
		'blocked'       => __( 'Number blocked', 'kenakata-kit' ),
		'unblocked'     => __( 'Number unblocked', 'kenakata-kit' ),
		'mpCheck'       => __( 'Check payment', 'kenakata-kit' ),
		'mpWait'        => __( 'not checked yet', 'kenakata-kit' ),
		'mpYes'         => __( 'received', 'kenakata-kit' ),
		'mpNo'          => __( 'not received', 'kenakata-kit' ),
		'mpReceived'    => __( 'Payment received', 'kenakata-kit' ),
		'mpNotReceived' => __( 'Not received', 'kenakata-kit' ),
		'mpNoConfirm'   => __( 'Mark the payment as not received? The order goes to Pending payment.', 'kenakata-kit' ),
		'mpAmount'      => __( 'Amount', 'kenakata-kit' ),
		'mpAdvance'     => __( 'delivery charge in advance', 'kenakata-kit' ),
		'mpFrom'        => __( 'Sent from', 'kenakata-kit' ),
		'mpTo'          => __( 'To your number', 'kenakata-kit' ),
		'mpShot'        => __( 'See the screenshot', 'kenakata-kit' ),
		'mpHelp'        => __( 'Open your bKash / Nagad / Rocket app, find this Transaction ID and check the amount, then press a button.', 'kenakata-kit' ),
		'printInvoice'  => __( 'Invoice', 'kenakata-kit' ),
		'printLabel'    => __( 'Label', 'kenakata-kit' ),
		'printInvoices' => __( 'Print invoices', 'kenakata-kit' ),
		'printLabels'   => __( 'Print labels', 'kenakata-kit' ),
		'printListHelp' => __( 'Prints the orders shown below. Pick a status first, e.g. Processing.', 'kenakata-kit' ),
		'nothingToPrint'=> __( 'No orders to print.', 'kenakata-kit' ),
		'syncCourier'   => __( 'Sync courier', 'kenakata-kit' ),
		'syncHelp'      => __( 'Asks the courier for the latest status of the orders sent from here (up to 40, newest first).', 'kenakata-kit' ),
		'syncing'       => __( 'Syncing…', 'kenakata-kit' ),
		'syncDone'      => __( 'Updated {done} orders, skipped {skipped}.', 'kenakata-kit' ),
		'hideIt' => __( 'Hide', 'kenakata-kit' ),
		'noBrandsYet' => __( 'No brands yet.', 'kenakata-kit' ),
		'newBrandName' => __( 'New brand name', 'kenakata-kit' ),
		'newCategoryName' => __( 'New category name', 'kenakata-kit' ),
		'noParent' => __( 'Main category (no parent)', 'kenakata-kit' ),
		'parentCategory' => __( 'Parent category', 'kenakata-kit' ),
		'addBrand' => __( 'Add brand', 'kenakata-kit' ),
		'addCategory' => __( 'Add category', 'kenakata-kit' ),
		'newBrand' => __( 'New brand', 'kenakata-kit' ),
		'newCategory' => __( 'New category', 'kenakata-kit' ),
		'termExisted' => __( 'It already existed and is now ticked.', 'kenakata-kit' ),
		'termAdded' => __( 'Added and ticked.', 'kenakata-kit' ),
		'optionsTitle' => __( 'Options (size, colour…)', 'kenakata-kit' ),
		'typeSimple' => __( 'Simple product', 'kenakata-kit' ),
		'typeVariable' => __( 'With options (size, colour…)', 'kenakata-kit' ),
		'productType' => __( 'Product type', 'kenakata-kit' ),
		'typeHelp' => __( 'Choose "With options" when the customer picks a size, colour or other choice, each with its own price and stock.', 'kenakata-kit' ),
		'typeConvertHelp' => __( 'You can add options to this product. After saving with options it cannot become a simple product again here.', 'kenakata-kit' ),
		'brand' => __( 'Brand', 'kenakata-kit' ),
		'brandHelp' => __( 'Optional: leave empty if the product has no brand.', 'kenakata-kit' ),
		'anyValue' => __( 'Any', 'kenakata-kit' ),
		'optionNameHint' => __( 'e.g. Size, Colour, Weight', 'kenakata-kit' ),
		'optionName' => __( 'Option name', 'kenakata-kit' ),
		'optionValueHint' => __( 'Type a value and press Enter (S, M, L…)', 'kenakata-kit' ),
		'optionValues' => __( 'Option values', 'kenakata-kit' ),
		'add' => __( 'Add', 'kenakata-kit' ),
		'addOptionType' => __( 'Add another option (e.g. Colour)', 'kenakata-kit' ),
		'combosInfo' => __( '{n} combinations possible · {have} added below', 'kenakata-kit' ),
		'optionsStart' => __( 'Give the option a name and at least one value, then press "Make all combinations".', 'kenakata-kit' ),
		'stockBlank' => __( 'optional', 'kenakata-kit' ),
		'optionPicture' => __( 'Picture for this option', 'kenakata-kit' ),
		'optionStale' => __( 'not in the values above', 'kenakata-kit' ),
		'makeCombos' => __( 'Make all combinations', 'kenakata-kit' ),
		'tooManyOptions' => __( 'At most {n} options per product.', 'kenakata-kit' ),
		'combosAdded' => __( '{n} new options added. Set their prices, then save.', 'kenakata-kit' ),
		'combosNone' => __( 'All combinations are already there.', 'kenakata-kit' ),
		'applyAll' => __( 'Apply to all', 'kenakata-kit' ),
		'sameForAll' => __( 'Same price or stock for all options', 'kenakata-kit' ),
		'optionsHelp' => __( 'Each combination (for example M / Red) is an option with its own price, stock and picture.', 'kenakata-kit' ),
		'optionNameMissing' => __( 'An option has values but no name.', 'kenakata-kit' ),
		'makeCombosFirst' => __( 'Press "Make all combinations" first.', 'kenakata-kit' ),
		'optionNeedsPrice' => __( 'Please enter a price for {option}.', 'kenakata-kit' ),
		'yesterday' => __( 'Yesterday', 'kenakata-kit' ),
		'thisMonth' => __( 'This month', 'kenakata-kit' ),
		'lastMonth' => __( 'Last month', 'kenakata-kit' ),
		'period' => __( 'Period', 'kenakata-kit' ),
		'customRange' => __( 'Choose dates', 'kenakata-kit' ),
		'dateFrom' => __( 'From', 'kenakata-kit' ),
		'dateTo' => __( 'To', 'kenakata-kit' ),
		'show' => __( 'Show', 'kenakata-kit' ),
		'itemsSold' => __( 'Items sold', 'kenakata-kit' ),
		'salesReport' => __( 'Sales report', 'kenakata-kit' ),
		'topProducts'     => __( 'Best-selling products', 'kenakata-kit' ),
		'cashAtCourier'   => __( 'Cash with couriers', 'kenakata-kit' ),
		'showIt'          => _x( 'Show', 'Dashboard button: open a block', 'kenakata-kit' ),
		'hideIt'          => _x( 'Hide', 'Dashboard button: close a block', 'kenakata-kit' ),
		'avgOrder'        => __( 'Average order', 'kenakata-kit' ),
		'sold'            => __( 'sold', 'kenakata-kit' ),
		'noSales'         => __( 'No sales in this period.', 'kenakata-kit' ),
		'approxOrders'    => __( 'Only the latest 3,000 orders are counted.', 'kenakata-kit' ),
		'cashHelp'        => __( 'Cash on delivery orders marked Shipped: the money your courier still has to hand over.', 'kenakata-kit' ),
		'parcelsOut'      => __( 'parcels on the way', 'kenakata-kit' ),
		'oldestParcel'    => __( 'Oldest parcel (days ago)', 'kenakata-kit' ),
		'noShippedStatus' => __( 'The Shipped status is turned off, so this cannot be calculated.', 'kenakata-kit' ),
		'chartLabel'      => __( 'Daily sales chart', 'kenakata-kit' ),
		'phone'           => __( 'Phone', 'kenakata-kit' ),
		'incomplete'      => __( 'Incomplete orders', 'kenakata-kit' ),
		'incompleteHelp'  => __( 'Customers who reached the checkout and typed their phone number but did not order. Call them: many only need a nudge.', 'kenakata-kit' ),
		'incOpen'         => __( 'To call', 'kenakata-kit' ),
		'incCalled'       => __( 'Called', 'kenakata-kit' ),
		'incRecovered'    => __( 'Ordered', 'kenakata-kit' ),
		'incDismissed'    => __( 'Dismissed', 'kenakata-kit' ),
		'incAll'          => __( 'All', 'kenakata-kit' ),
		'incNone'         => __( 'Nothing here. Good news: nobody left in the middle of the checkout.', 'kenakata-kit' ),
		'incMarkCalled'   => __( 'Mark as called', 'kenakata-kit' ),
		'incReopen'       => __( 'Back to the list', 'kenakata-kit' ),
		'incDismiss'      => __( 'Dismiss', 'kenakata-kit' ),
		'incMakeOrder'    => __( 'Make an order', 'kenakata-kit' ),
		'incMakeTitle'    => __( 'Make an order from this cart?', 'kenakata-kit' ),
		'incMakeHelp'     => __( 'Do this after the customer confirmed on the phone. It creates a Cash on Delivery order in Processing and takes the stock.', 'kenakata-kit' ),
		'incMade'         => __( 'Order created.', 'kenakata-kit' ),
		'incSeeOrder'     => __( 'Order', 'kenakata-kit' ),
		'incLeft'         => __( 'checkouts left unfinished', 'kenakata-kit' ),
		'incCart'         => __( 'Cart', 'kenakata-kit' ),
		'incSearch'       => __( 'Phone or name', 'kenakata-kit' ),
		'incUpdated'      => __( 'Last active', 'kenakata-kit' ),
		'openTracking'    => __( 'Open tracking page', 'kenakata-kit' ),
		'trackingAuto'    => __( 'Tracking is filled in automatically when the order is sent to a courier.', 'kenakata-kit' ),
		'sendTo'          => __( 'Send to {courier}', 'kenakata-kit' ),
		'sendConfirmTitle' => __( 'Send to {courier}?', 'kenakata-kit' ),
		'sendConfirmHelp' => __( 'Check the details. You can change them here before sending.', 'kenakata-kit' ),
		'sendNow'         => __( 'Send now', 'kenakata-kit' ),
		'sending'         => __( 'Sending…', 'kenakata-kit' ),
		'cancelIt'        => __( 'Cancel', 'kenakata-kit' ),
		'recipient'       => __( 'Customer name', 'kenakata-kit' ),
		'collectAmount'   => __( 'Amount to collect', 'kenakata-kit' ),
		'courierNote'     => __( 'Note for the courier (optional)', 'kenakata-kit' ),
		'sentOk'          => __( 'Sent to the courier. The order is now Shipped.', 'kenakata-kit' ),
		'sentTo'          => __( 'Sent to {courier}', 'kenakata-kit' ),
		'consignment'     => __( 'Consignment', 'kenakata-kit' ),
		'courierStatus'   => __( 'Courier status', 'kenakata-kit' ),
		'refreshStatus'   => __( 'Refresh status', 'kenakata-kit' ),
		'statusUpdated'   => __( 'Status updated.', 'kenakata-kit' ),
		'sendAgain'       => __( 'The earlier parcel was cancelled. You can send this order again.', 'kenakata-kit' ),
		'courierCheck'    => __( 'Courier check', 'kenakata-kit' ),
		'checkTitle'      => __( 'How did this customer\'s earlier orders go?', 'kenakata-kit' ),
		'checkTotal'      => __( 'Orders', 'kenakata-kit' ),
		'checkSuccess'    => __( 'Successful', 'kenakata-kit' ),
		'checkCancelled'  => __( 'Cancelled', 'kenakata-kit' ),
		'checkOpen'       => __( 'On the way', 'kenakata-kit' ),
		'checkRate'       => __( 'Success rate', 'kenakata-kit' ),
		'checkNoRate'     => __( 'Not enough data', 'kenakata-kit' ),
		'checkLights'     => __( 'Green from {good}% · red below {bad}%', 'kenakata-kit' ),
		'checkByCourier'  => __( 'By courier', 'kenakata-kit' ),
		'checkReports'    => __( 'Reports from other shops', 'kenakata-kit' ),
		'checkRefresh'    => __( 'Refresh', 'kenakata-kit' ),
		'checkUpdated'    => __( 'Updated {ago} ago', 'kenakata-kit' ),
	);
}

/** Language of the dashboard page (Bengali when the plugin's Bengali texts are used). */
function kk_sd_lang() {
	return ! empty( $GLOBALS['kk_lang_bn'] ) ? 'bn-BD' : get_bloginfo( 'language' );
}

/** Script configuration for the current user. */
function kk_sd_config() {
	$user  = wp_get_current_user();
	$logo  = absint( get_theme_mod( 'custom_logo' ) );
	$admin = current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	return array(
		'rest'      => esc_url_raw( rest_url( 'kenakata/v1/sd/' ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'site'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		'home'      => home_url( '/' ),
		'logo'      => $logo ? (string) wp_get_attachment_image_url( $logo, 'medium' ) : '',
		'user'      => array( 'name' => $user->display_name ),
		'caps'      => array(
			'orders'         => current_user_can( 'edit_shop_orders' ),
			'products'       => current_user_can( 'edit_products' ),
			'publishProduct' => current_user_can( 'publish_products' ),
			'posts'          => current_user_can( 'edit_posts' ),
			'publishPost'    => current_user_can( 'publish_posts' ),
			'upload'         => current_user_can( 'upload_files' ),
			'terms'          => current_user_can( 'manage_product_terms' ),
			'admin'          => $admin,
			'money'          => kk_sd_can_money(),
		),
		'adminUrl'  => $admin ? admin_url() : '',
		'incomplete' => function_exists( 'kk_incomplete_enabled' ) && kk_incomplete_enabled() && current_user_can( 'edit_shop_orders' ),
		'courierList' => function_exists( 'kk_courier_list' ) ? kk_courier_list() : array(), // turned-on couriers for one-tap booking (C.couriers is the older manual drop-down)
		'courierCheck' => function_exists( 'kk_courier_check_enabled' ) && kk_courier_check_enabled(),
		'courierRest' => esc_url_raw( rest_url( 'kenakata/v1/courier/' ) ),
		'courierSync' => function_exists( 'kk_bulk_courier_actions' ) && (bool) kk_bulk_courier_actions(),
		'print'       => kk_opt( 'print_enable' ) && function_exists( 'kk_print_url' ) && current_user_can( 'edit_shop_orders' ) ? esc_url_raw( add_query_arg( array( 'action' => 'kk_print', '_wpnonce' => wp_create_nonce( 'kk_print' ) ), admin_url( 'admin-post.php' ) ) ) : '',
		'logoutUrl' => wp_logout_url( kk_sd_url() ),
		'profileUrl'=> admin_url( 'profile.php' ),
		'statuses'  => kk_sd_statuses(),
		'couriers'  => kk_couriers(),
		'currency'  => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
		'lowStock'  => max( 0, absint( kk_opt( 'sd_low_stock' ) ) ),
		'brands'    => '' !== kk_brand_taxonomy(),
		'maxVariations' => kk_sd_max_variations(),
		'lang'      => kk_sd_lang(),
		'i18n'      => kk_sd_strings(),
	);
}

/** Output the dashboard document (or send the visitor to the login page). */
function kk_sd_render_page() {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // page caches (LiteSpeed, WP Rocket, …) must never store this page
	}
	do_action( 'litespeed_control_set_nocache', 'Kenakata Kit store dashboard' ); // LiteSpeed Cache's own switch
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
	header( 'X-Frame-Options: SAMEORIGIN', true );
	header( 'Referrer-Policy: same-origin', true );

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( kk_sd_url() ) );
		exit;
	}
	$lang  = kk_sd_lang();
	$title = trim( (string) kk_opt( 'sd_title' ) );
	$title = '' !== $title ? $title : __( 'Store dashboard', 'kenakata-kit' );
	$color = kk_sd_brand_color();

	if ( ! current_user_can( KK_SD_CAP ) ) {
		status_header( 403 );
		echo '<!doctype html><html lang="' . esc_attr( $lang ) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . esc_html( $title ) . '</title></head>'
			. '<body style="font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;margin:0;background:#f6f5f2;color:#1d1b20"><main style="max-width:420px;padding:32px;text-align:center">'
			. '<h1 style="font-size:22px">' . esc_html__( 'You do not have access to the store dashboard.', 'kenakata-kit' ) . '</h1>'
			. '<p>' . esc_html__( 'Ask the store owner to give your account the "Store Manager (Kenakata)" role.', 'kenakata-kit' ) . '</p>'
			. '<p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to the shop', 'kenakata-kit' ) . '</a> · <a href="' . esc_url( wp_logout_url( kk_sd_url() ) ) . '">' . esc_html__( 'Log out', 'kenakata-kit' ) . '</a></p>'
			. '</main></body></html>';
		return;
	}
	status_header( 200 );
	$ver = KK_VERSION;
	?>
<!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>">
<head>
<meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="<?php echo esc_attr( $color ); ?>">
<title><?php echo esc_html( $title . ' · ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?></title>
<?php $icon = get_site_icon_url( 192 ); if ( $icon ) : ?><link rel="icon" href="<?php echo esc_url( $icon ); ?>"><link rel="apple-touch-icon" href="<?php echo esc_url( $icon ); ?>"><?php endif; ?>
<link rel="stylesheet" href="<?php echo esc_url( kk_asset( 'store-dashboard.css' ) . '?ver=' . $ver ); ?>">
<style><?php echo esc_html( kk_sd_theme_css() ); ?></style>
</head>
<body>
<div id="sd-app" class="sd" aria-live="polite"><p class="sd-boot"><?php esc_html_e( 'Loading…', 'kenakata-kit' ); ?></p></div>
<noscript><p class="sd-boot"><?php esc_html_e( 'Please turn on JavaScript to use the store dashboard.', 'kenakata-kit' ); ?></p></noscript>
<script>window.kkSD = <?php echo wp_json_encode( kk_sd_config(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE ); ?>;</script>
<script src="<?php echo esc_url( kk_asset( 'store-dashboard.js' ) . '?ver=' . $ver ); ?>" defer></script>
</body>
</html>
	<?php
}
