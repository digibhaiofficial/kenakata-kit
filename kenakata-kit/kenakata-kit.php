<?php
/**
 * Plugin Name:       Kenakata Kit – WooCommerce Toolkit for Bangladesh
 * Plugin URI:        https://digibhai.com/
 * Description:       Everything a Bangladeshi WooCommerce store needs: Buy Now, simple checkout with delivery areas, fake-order protection, thank-you page, order tracking by phone, live search, login popup, product cards, brand/category carousels, WhatsApp, variation buttons, photo reviews, side cart, mobile bar and 15 Elementor widgets. English and Bengali (বাংলা). Settings: left menu → Kenakata Kit.
 * Version:           3.30.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   11.1
 * Elementor tested up to: 4.3
 * Author:            DigiBhai.com
 * Author URI:        https://digibhai.com/
 * License:           GPL-2.0-or-later
 * Text Domain:       kenakata-kit
 * Update URI:        https://github.com/digibhaiofficial/kenakata-kit
 */

defined( 'ABSPATH' ) || exit;

$GLOBALS['kk_load_ms'] = -microtime( true ); // time spent loading this plugin (Speed & health shows it)

define( 'KK_VERSION', '3.30.0' );
define( 'KK_FILE', __FILE__ );
define( 'KK_DIR', __DIR__ );
define( 'KK_URL', plugin_dir_url( __FILE__ ) );

/**
 * Default settings. Every key here is editable on the Kenakata Kit admin page.
 * Customer-facing texts are English by default (most stores run WordPress in English); change them freely.
 */
function kk_defaults() {
	return array(
		// Design.
		'use_elementor_colors'  => 1,
		'color_primary'         => '#038348',
		'color_primary_hover'   => '#026B3A',
		'color_accent'          => '#C8155A',
		'color_accent_hover'    => '#A50F49',
		'color_text'            => '#101714',
		'color_muted'           => '#5C6661',
		'color_border'          => '#E4E2DC',
		'color_surface_soft'    => '#FAF8F4',
		'color_surface_brand'   => '#EAF5EF',
		'color_surface_accent'  => '#FCEAF1',
		'color_badge'           => '#C8155A',
		'color_price'           => '#101714',
		'color_star'            => '#F5A623',
		'color_badge_text'      => '#FFFFFF',
		'btn_text_color'        => '#FFFFFF',
		'radius'                => 10,
		'font_family'           => 'inherit',
		'font_size_base'        => 16,
		'btn_height'            => 46,
		'btn_radius'            => 6,
		'btn_font_size'         => 15,
		'btn_font_weight'       => '600',
		'btn_uppercase'         => 0,
		'input_height'          => 48,
		'input_radius'          => 8,
		'compat_strong'         => 1,

		// Product cards (every product list on the site).
		'card_enable'           => 1,
		'card_style'            => 'bordered', // bordered | shadow | flat
		'card_align'            => 'left',     // left | center
		'card_image_ratio'      => '1/1',
		'card_image_fit'        => 'contain',
		'card_title_lines'      => 2,
		'card_title_size'       => 15,
		'card_price_size'       => 17,
		'card_title_tag'        => '',
		'card_show_brand'       => 1,
		'card_show_rating'      => 1,
		'card_show_badge'       => 1,
		'card_badge_text'       => '-{percent}%',
		'card_show_stock'       => 1,
		'card_stock_text'       => 'Stock out',
		'card_actions_layout'   => 'side',  // side | stack | buynow_only | cart_only | none
		'card_actions_mobile'   => 'stack', // stack | same | icon
		'card_cart_text'        => 'Add to cart',
		'archive_per_page'      => 24,
		'archive_columns'       => 4,
		'archive_columns_tablet'=> 3,
		'archive_columns_mobile'=> 2,

		// Buy Now.
		'buynow_enable'         => 1,
		'buynow_loop'           => 1,
		'buynow_single'         => 1,
		'buynow_text'           => 'Buy Now',
		'buynow_clear_cart'     => 0,
		'cart_bot_guard'        => 1,          // cart links from outside the shop (bots) do not touch the cart

		// Checkout.
		'co_enable'             => 1,
		'co_country'            => 'BD',
		'co_heading'            => 'Delivery details',
		'co_button_text'        => 'Confirm Order',
		'co_name_label'         => 'Full name',
		'co_name_placeholder'   => 'Your full name',
		'co_phone_label'        => 'Phone number',
		'co_phone_placeholder'  => '01XXXXXXXXX',
		'co_phone_mode'         => 'bd11', // bd11 = exactly 11 digits 01XXXXXXXXX | bd = also +880/880 | any
		'co_phone_error'        => 'Please enter a valid 11-digit mobile number, e.g. 01712345678.',
		'co_address_label'      => 'Full address',
		'co_address_placeholder'=> 'House, road, area, thana, district',
		'co_address_textarea'   => 1,
		'co_email_show'         => 1,
		'co_email_required'     => 0,
		'co_email_label'        => 'Email',
		'co_email_placeholder'  => 'For order updates',
		'co_area_enable'        => 1,
		'co_district_enable'    => 0,
		'co_district_label'     => 'District',
		'co_thana_mode'         => 'optional',
		'co_thana_label'        => 'Thana / Upazila',
		'co_area_label'         => 'Delivery area',
		'co_area_prompt'        => 'Select your delivery area to see the total.',
		'co_hide_notes'         => 1,
		'co_coupon'             => 'both', // both | checkout | cart | none
		'co_cart_hide_shipping' => 1,
		'co_cart_note'          => 'Choose your area at checkout',

		// Fake-order protection.
		'co_throttle_enable'    => 1,
		'co_throttle_minutes'   => 30,
		'co_throttle_ip'        => 0,
		'co_throttle_skip_admin'=> 1,
		'co_throttle_message'   => 'An order was already placed from this number a few minutes ago. Please wait {minutes} more minutes, or call us to add to or change your order.',

		// Thank-you page.
		'ty_enable'             => 1,
		'ty_page'               => 0,
		'ty_title'              => 'Thank you, {name}!',
		'ty_message'            => 'Your order has been placed. We will call you to confirm it shortly.',
		'ty_track_text'         => 'Track your order',
		'ty_shop_text'          => 'Continue shopping',

		// Order tracking.
		'tr_page'               => 0,
		'tr_shipped_status'     => 1,
		'tr_auto_shipped'       => 1,
		'tr_courier_pick'       => '{"steadfast":"","pathao":"","redx":"","paperfly":"","ecourier":"","sundarban":"","sa":"","other":""}',
		'tr_couriers'           => '', // other couriers, one per line: key|Name|tracking link with {id}
		'tr_form_label'         => 'Phone number used for the order',
		'tr_button_text'        => 'Track order',
		'tr_rate_limit'         => 20,
		'tr_require_order'      => 0,
		'tr_live_courier'       => 0,
		'tr_auto_complete'      => 0,
		'print_enable'          => 0,
		'msg_enable'            => 0,
		'msg_tpl_confirm'       => "Hello {name}, thank you for your order #{order} at {shop}. Total: {total}. We will send it soon.",
		'msg_tpl_shipped'       => "Hello {name}, your order #{order} is on its way. Please keep {to_collect} ready for the delivery person. Track: {tracking}",
		'msg_tpl_pay'           => "Hello {name}, your order #{order} is waiting for payment of {to_collect}. Reply here if you need help. - {shop}",
		'msg_tpl_delivered'     => "Hello {name}, we hope you like your order #{order}. Thank you for shopping with {shop}!",
		'msg_tpl_call'          => "Hello {name}, we tried to call you about order #{order} but could not reach you. Please call or reply here to confirm. - {shop}",
		'msg_sms_enable'        => 0,
		'msg_sms_method'        => 'get',
		'msg_sms_url'           => '',
		'msg_sms_body'          => '',
		'gd_blacklist_enable'   => 0,
		'gd_blacklist_text'     => '',
		'gd_blacklist_action'   => 'block',
		'gd_blacklist_msg'      => 'Sorry, we cannot take orders from this number online. Please call us.',
		'gd_risk_enable'        => 0,
		'gd_risk_min'           => 2,
		'gd_risk_rate'          => 50,
		'gd_risk_msg'           => 'Some of your earlier orders were not received, so this time please pay the delivery charge in advance with bKash, Nagad or Rocket.',
		'gd_skip_staff'         => 1,
		'mp_enable'             => 0,
		'mp_title'              => 'bKash / Nagad / Rocket',
		'mp_amount'             => 'full',
		'mp_bkash_number'       => '',
		'mp_bkash_type'         => 'personal',
		'mp_bkash_fee'         => '1.85',
		'mp_nagad_number'       => '',
		'mp_nagad_type'         => 'personal',
		'mp_nagad_fee'         => '1.5',
		'mp_rocket_number'      => '',
		'mp_rocket_type'        => 'personal',
		'mp_rocket_fee'        => '1.8',
		'mp_upay_number'        => '',
		'mp_upay_type'          => 'personal',
		'mp_upay_fee'          => '1.4',
		'mp_screenshot'         => 'optional',
		'mp_note'               => '',
		'print_shop_name'       => '',
		'print_shop_phone'      => '',
		'print_shop_address'    => '',
		'print_logo'            => 1,
		'print_barcode'         => 1,
		'print_show_sku'        => 0,
		'print_label_size'      => '100x150',
		'print_invoice_note'    => 'Thank you for shopping with us!',
		'tr_text_received'      => 'We have your order and will confirm it by phone.',
		'tr_text_processing'    => 'We are preparing your order for delivery.',
		'tr_text_shipped'       => 'Your parcel is on the way.',
		'tr_text_completed'     => 'Your order has been delivered.',

		// Live search.
		'search_enable'         => 1,
		'search_placeholder'    => 'Search products, brands…',
		'search_min_chars'      => 2,
		'search_limit'          => 8,

		// Account popup.
		'popup_enable'          => 1,
		'popup_register'        => 1,
		'popup_email_required'  => 0,
		'popup_login_tab'       => 'Log in',
		'popup_register_tab'    => 'Create account',

		// Brand / category cards.
		'cards_style'           => 'card', // card | minimal | circle | logo
		'cards_noimg'           => 'letter', // letter | name: what a card without a picture shows
		'cards_show_count'      => 1,
		'cards_image_size'      => 72,
		'cards_per_view'        => 6,
		'cards_per_view_tablet' => 4,
		'cards_per_view_mobile' => 2,
		'cards_autoplay'        => 0,
		'cards_autoplay_speed'  => 4,
		'cards_loop'            => 1,
		'cards_arrows'          => 1,
		'cards_dots'            => 0,

		// WhatsApp & call.
		'wa_number'             => '',
		'wa_float_enable'       => 0,
		'wa_float_text'         => 'Chat with us',
		'wa_float_message'      => 'Hello, I would like to know about a product.',
		'wa_float_position'     => 'right',
		'wa_float_offset'       => 20,
		'wa_float_mobile'       => 1,
		'wa_float_desktop'      => 1,
		'wa_float_color'        => '#25D366',
		'call_number'           => '',
		'call_float_enable'     => 0,
		'call_float_color'      => '', // '' = primary colour
		'float_icon_color'      => '#FFFFFF',
		'float_size'            => 56,

		// Product page.
		'pp_wa_enable'          => 0,
		'pp_wa_text'            => 'Order on WhatsApp',
		'pp_wa_message'         => "I want to order: {product} ({price})\n{url}",
		'pp_call_enable'        => 0,
		'pp_call_text'          => 'Call to order',
		'pp_wa_color'           => '#1FA855',
		'pp_call_color'         => '', // '' = primary colour
		'pp_btn_text_color'     => '#FFFFFF',
		'swatches_enable'       => 1,
		'swatches_height'       => 40,
		'swatches_radius'       => 8,
		'swatches_clear'        => 1,
		'pp_delivery_enable'    => 0,
		'di_title'              => 'Delivery information',
		'di_show_charges'       => 1,
		'di_bg'                 => '', // '' = soft background
		'di_icon_color'         => '', // '' = primary colour
		'di_lines'              => "Cash on delivery all over Bangladesh\nInside Dhaka: 1–2 days · Outside Dhaka: 2–5 days\nCheck the product when you receive it",
		// Free delivery above an amount.
		'fd_enable'             => 0,
		'fd_amount'             => 5000,
		'fd_text'               => 'Spend {remaining} more to unlock Free Delivery',
		'fd_done'               => 'You have unlocked Free Delivery!',
		'fd_product'            => 1,
		'fd_cart'               => 1,
		'fd_checkout'           => 1,
		'fd_drawer'             => 1,
		'sticky_enable'         => 0,
		'sticky_show_cart'      => 1,
		'sticky_bg'             => '#FFFFFF',

		// Reviews.
		'rv_enable'             => 1,
		'rv_title'              => 'Customer reviews',
		'rv_summary'            => 1,
		'rv_gallery'            => 1,
		'rv_button_text'        => 'Write a review',
		'rv_photos'             => 1,
		'rv_photos_max'         => 3,
		'rv_photos_size'        => 3,
		'rv_photos_who'         => 'everyone', // everyone | buyers | logged_in
		'rv_photos_hold'        => 1,
		'rv_photo_label'        => 'Add photos (optional)',
		'rv_tab'                => 1,
		'rv_open_all'           => 0, // allow reviews on every product, even where "Enable reviews" is off
		'desc_fallback'         => 1, // empty Description + long Short description: show it in the Description tab
		// Wishlist.
		'wl_enable'             => 1,
		'wl_card'               => 1,
		'wl_single'             => 1,
		'wl_page'               => 0,
		'wl_text_add'           => 'Add to wishlist',
		'wl_text_added'         => 'In wishlist',
		'wl_empty_text'         => 'Your wishlist is empty. Tap ♡ on any product to save it here.',
		'wl_share'              => 1,
		'wl_color'              => '#E11D48',
		// Questions & answers.
		'qa_enable'             => 1,
		'qa_tab_title'          => 'Questions',
		'qa_require_phone'      => 1,
		'qa_notify'             => 1,
		'qa_team_name'          => '',
		'qa_intro'              => 'Have a question about this product? Ask us — our team answers within a day. Answered questions appear here for everyone.',
		'qa_thanks'             => 'Thank you! Your question was sent. We will answer it soon.',
		'stock_always'          => 1, // show "In stock" also when stock is not tracked
		'stock_in_text'         => 'In stock',
		'rm_enable'             => 1, // long descriptions: first part + "Read more"
		'rm_height'             => 320,
		'rm_height_mobile'      => 260,
		'rm_more_text'          => 'Read more',
		'rm_less_text'          => 'Show less',
		'rm_color'              => '',

		// Side cart.
		'cart_drawer_enable'    => 0,
		'cart_drawer_auto_open' => 1,
		'cart_drawer_takeover'  => 0,
		'cart_drawer_title'     => 'Your cart',
		'cart_drawer_width'     => 420,
		'cart_drawer_ajax'      => 1,
		'cart_drawer_thumbs'    => 1,
		'cart_drawer_remove'    => 1,
		'cart_drawer_view'      => 1,
		'cart_drawer_view_text' => 'View cart',
		'cart_drawer_checkout_text' => 'Checkout',
		'cart_drawer_empty_text'    => 'Your cart is empty.',

		// Mobile bottom bar (key mnav_enable kept from 3.0 so existing on/off choices survive).
		'mnav_enable'           => 0,
		'bbar_items'            => '', // JSON list; '' = default items (see kk_bbar_default_items())
		'bbar_style'            => 'pill',    // classic | pill | floating
		'bbar_featured'         => '',        // id of an item shown as a raised round button ('' = none)
		'bbar_labels'           => 1,
		'bbar_breakpoint'       => 767,
		'bbar_height'           => 62,
		'bbar_icon_size'        => 22,
		'bbar_label_size'       => 11,
		'bbar_bg'               => '#FFFFFF',
		'bbar_border'           => '#E4E2DC',
		'bbar_shadow'           => 'soft',    // none | soft | strong
		'bbar_icon_color'       => '#5C6661',
		'bbar_text_color'       => '#5C6661',
		'bbar_active_color'     => '#038348',
		'bbar_active_bg'        => '#EAF5EF',
		'bbar_badge_bg'         => '#C8155A',
		'bbar_cats_url'         => '',
		'bbar_hide_checkout'    => 1,
		'bbar_hide_cart'        => 0,
		'bbar_hide_on_scroll'   => 0,

		// Store dashboard (/dashboard): simple order/product/blog management without wp-admin.
		'sd_enable'             => 0,
		'sd_slug'               => 'dashboard',
		'sd_title'              => '',
		'sd_block_admin'        => 1,
		'sd_low_stock'          => 3,
		'sd_login_redirect'     => 'managers', // managers = Store Managers only (as before) | staff = also administrators and shop managers
		'sd_see_sales'          => 1,          // shop managers and Store Managers see sales amounts (administrators always do)
		'sd_theme'              => 'auto',     // auto (shop colours) | a preset key (see kk_sd_presets()) | custom
		'sd_color_primary'      => '#038348',  // used when sd_theme = custom
		'sd_color_accent'       => '#F59E0B',  // used when sd_theme = custom
		'incomplete_enable'     => 0,          // save unfinished checkouts (off until the shop turns it on)
		'incomplete_wait'       => 15,         // minutes a checkout may sit before it counts as left
		'incomplete_keep_days'  => 30,         // days an unfinished checkout is kept
		'courier_provider'     => '',         // legacy single-courier choice from 3.8/3.9 (still honoured until the tab is saved once)
		'courier_steadfast_on'  => 0,          // courier switches: only enabled and fully set-up couriers appear on orders
		'courier_pathao_on'     => 0,
		'courier_check_enable'  => 0,          // Courier check button on orders (off until someone turns it on)
		'courier_steadfast_api_key'    => '',  // credentials live in the kk_courier_secrets option, never here
		'courier_steadfast_secret_key' => '',
		'courier_pathao_client_id'     => '',  // Pathao credentials live in the kk_courier_secrets option, never here
		'courier_pathao_client_secret' => '',
		'courier_pathao_username'      => '',
		'courier_pathao_password'      => '',
		'courier_pathao_store_id'      => '',  // the merchant store number (plain setting, not secret)
		'courier_pathao_sandbox'       => 0,   // 1 = Pathao's test server
		'courier_redx_on'              => 0,
		'courier_redx_store_id'        => '',
		'courier_redx_area_id'         => '',
		'courier_redx_sandbox'         => 0,
		'courier_ecourier_on'          => 0,
		'courier_ecourier_package'     => '',
		'courier_ecourier_zip'         => '',
		'courier_ecourier_city'        => 'Dhaka',
		'courier_ecourier_sandbox'     => 0,
		'courier_default_weight'       => '0.5', // kg per parcel (couriers that need a weight)
		'courier_bdcourier_token' => '',       // optional look-up service token (private option, never here)
		'courier_check_cache'   => 24,         // hours a looked-up phone number is remembered
		'courier_good_rate'     => 80,         // courier check: this success rate (%) or more = green
		'courier_bad_rate'      => 50,         // below this success rate (%) = red

		// Plugin language for the admin screens: auto (WordPress profile language) | en | bn.
		'admin_lang'            => 'auto',

		// Security.
		'sec_force_https'       => 0,
		'sec_hide_users'        => 0,
		'sec_disable_xmlrpc'    => 0,
		'sec_limit_login'       => 1,
		'sec_login_attempts'    => 6,
		'sec_headers'           => 1,
		'sec_hide_version'      => 1,
		'sec_no_file_edit'      => 1,
		'sec_uploads_php'       => 0,
		'speed_no_emoji'        => 0,
		'speed_no_embeds'       => 0,
		'speed_wc_assets'       => 0,
		'speed_heartbeat'       => 0,
		'speed_block_css'       => 0,
		'speed_robots_shop'     => 0,
		'speed_bot_block'       => 0,
		'speed_ai_block'        => 0,
		'speed_ai_search_block' => 0,
		// SEO & Google (product schema delivery/returns, price question block).
		'seo_schema_enable'     => 0,
		'seo_ship_local_region' => 'BD-13',
		'seo_ship_local_cost'   => '', // '' = lowest delivery charge in WooCommerce shipping
		'seo_ship_local_days'   => '1-2',
		'seo_ship_other_cost'   => '', // '' = highest delivery charge
		'seo_ship_other_days'   => '2-5',
		'seo_handling_days'     => '0-1',
		'seo_return_mode'       => 'none',
		'seo_return_days'       => 7,
		'seo_return_fees'       => 'customer',
		'seo_return_no_cats'    => '',
		'seo_return_page'       => 0,
		'seo_pricefaq_enable'   => 0,
		'seo_pricefaq_tag'      => 'h3',
		'seo_pricefaq_title'    => 'How much does {name} cost?',
		'seo_pricefaq_text'     => "{name} costs {price} at {site} right now ({stock}).\nDelivery charge: {delivery}. Cash on delivery all over Bangladesh.\nPrices can change over time; the price shown at checkout is the one you pay.",
		'turnstile_enable'      => 0,
		'turnstile_site'        => '',
		'turnstile_login'       => 1,
		'turnstile_checkout'    => 0,
	);
}

/** Get one setting (merged with defaults). */
function kk_opt( $key ) {
	$opts = kk_all_opts();
	return isset( $opts[ $key ] ) ? $opts[ $key ] : null;
}

/** All settings merged with defaults (cached per request; cache cleared when the option changes). */
function kk_all_opts( $reset = false ) {
	static $opts = null;
	if ( null === $opts || $reset ) {
		$opts = wp_parse_args( (array) get_option( 'kk_settings', array() ), kk_defaults() );
	}
	return $opts;
}
add_action( 'update_option_kk_settings', function () { kk_all_opts( true ); } );
add_action( 'add_option_kk_settings', function () { kk_all_opts( true ); } );

/** Courier list from settings ("key|Label" per line). */
function kk_couriers() {
	$out     = array();
	$catalog = kk_courier_catalog();
	foreach ( kk_courier_picked() as $key => $link ) {
		if ( isset( $catalog[ $key ] ) ) {
			$out[ $key ] = $catalog[ $key ]['name'];
		}
	}
	// "Other couriers" box: one per line, key|Name (older versions kept the whole list here).
	foreach ( preg_split( '/\r\n|\r|\n/', (string) kk_opt( 'tr_couriers' ) ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 3 ) );
		$key   = sanitize_key( $parts[0] );
		if ( '' !== $key && ! isset( $out[ $key ] ) ) {
			$out[ $key ] = isset( $parts[1] ) && '' !== $parts[1] ? $parts[1] : ( isset( $catalog[ $key ] ) ? $catalog[ $key ]['name'] : $parts[0] );
		}
	}
	// The courier chosen for one-tap booking is always selectable on orders.
	if ( function_exists( 'kk_courier_enabled' ) ) {
		foreach ( kk_courier_enabled() as $key => $label ) {
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = $label;
			}
		}
	}
	return $out;
}

/**
 * Couriers of Bangladesh. 'link' = the public tracking page, {id} = tracking ID, {phone} = customer phone
 * ('' when the courier has no page that opens one parcel by link: the customer then gets the ID and the site).
 * 'api' = parcels can also be sent from the order with one tap (Pro: Courier booking).
 */
function kk_courier_catalog() {
	return apply_filters( 'kk_courier_catalog', array(
		'steadfast'   => array( 'name' => 'Steadfast Courier', 'site' => 'https://steadfast.com.bd', 'link' => 'https://steadfast.com.bd/t/{id}', 'api' => true ),
		'pathao'      => array( 'name' => 'Pathao Courier', 'site' => 'https://merchant.pathao.com', 'link' => 'https://merchant.pathao.com/tracking?consignment_id={id}&phone={phone}', 'api' => true ),
		'redx'        => array( 'name' => 'RedX', 'site' => 'https://redx.com.bd', 'link' => 'https://redx.com.bd/track-parcel/?trackingId={id}', 'api' => true ),
		'paperfly'    => array( 'name' => 'Paperfly', 'site' => 'https://paperfly.com.bd', 'link' => '' ),
		'ecourier'    => array( 'name' => 'eCourier', 'site' => 'https://ecourier.com.bd', 'link' => '', 'api' => true ),
		'carrybee'    => array( 'name' => 'CarryBee', 'site' => 'https://carrybee.com', 'link' => '' ),
		'deliverytiger' => array( 'name' => 'Delivery Tiger', 'site' => 'https://deliverytiger.com.bd', 'link' => '' ),
		'sundarban'   => array( 'name' => 'Sundarban Courier', 'site' => 'https://sundarbancourierltd.com', 'link' => '' ),
		'sa'          => array( 'name' => 'SA Paribahan', 'site' => 'https://saparibahan.com', 'link' => '' ),
		'karatoa'     => array( 'name' => 'Karatoa Courier (KCS)', 'site' => 'https://kcsbd.com', 'link' => '' ),
		'janani'      => array( 'name' => 'Janani Express', 'site' => '', 'link' => '' ),
		'continental' => array( 'name' => 'Continental Courier', 'site' => '', 'link' => '' ),
		'afs'         => array( 'name' => 'AFS (Associated Fast Service)', 'site' => '', 'link' => '' ),
		'sheba'       => array( 'name' => 'Sheba Delivery', 'site' => '', 'link' => '' ),
		'navana'      => array( 'name' => 'Navana Courier', 'site' => '', 'link' => '' ),
		'post'        => array( 'name' => 'Bangladesh Post (EMS)', 'site' => 'https://bdpost.gov.bd', 'link' => '' ),
		'own'         => array( 'name' => 'Own delivery person', 'site' => '', 'link' => '' ),
		'other'       => array( 'name' => 'Other courier', 'site' => '', 'link' => '' ),
	) );
}

/** Ticked couriers: array( key => own tracking link or '' for the standard one ). */
function kk_courier_picked() {
	$v = json_decode( (string) kk_opt( 'tr_courier_pick' ), true );
	return is_array( $v ) ? $v : array();
}

/** Clean the "Couriers I use" table from the settings form. */
function kk_courier_pick_sanitize( $rows ) {
	$out     = array();
	$catalog = kk_courier_catalog();
	foreach ( is_array( $rows ) ? $rows : array() as $key => $row ) {
		$key = sanitize_key( $key );
		if ( ! isset( $catalog[ $key ] ) || empty( $row['on'] ) ) {
			continue;
		}
		$link = isset( $row['link'] ) ? trim( sanitize_text_field( (string) $row['link'] ) ) : '';
		// Only an https link of our own form; the standard link is not stored (it can be improved in updates).
		if ( '' !== $link && ( ! preg_match( '#^https://[^\s"<>]+$#i', $link ) || $link === $catalog[ $key ]['link'] ) ) {
			$link = '';
		}
		$out[ $key ] = $link;
	}
	return $out;
}

/**
 * 3.12 moved the courier list from a text box to a tick list. Once, in the admin: couriers of the old box that are
 * in the new list become ticks; anything else stays in the "Other couriers" box.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'kk_courier_pick_mig' ) ) {
		return;
	}
	update_option( 'kk_courier_pick_mig', 1, false );
	$stored = (array) get_option( 'kk_settings', array() );
	if ( ! isset( $stored['tr_couriers'] ) || isset( $stored['tr_courier_pick'] ) ) {
		return; // never saved (the new defaults apply) or already moved
	}
	$catalog = kk_courier_catalog();
	$pick    = array();
	$rest    = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $stored['tr_couriers'] ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 3 ) );
		$key   = sanitize_key( $parts[0] );
		if ( '' === $key ) {
			continue;
		}
		if ( isset( $catalog[ $key ] ) ) {
			$pick[ $key ] = '';
		} else {
			$rest[] = $line;
		}
	}
	$stored['tr_courier_pick'] = wp_json_encode( $pick );
	$stored['tr_couriers']     = implode( "\n", $rest );
	if ( function_exists( 'kk_update_settings' ) ) {
		kk_update_settings( $stored );
	} else {
		update_option( 'kk_settings', $stored );
	}
} );

/** The tracking page of one parcel, '' when not known. */
function kk_courier_track_link( $courier, $tracking_id, $phone = '' ) {
	$courier = sanitize_key( (string) $courier );
	$picked  = kk_courier_picked();
	$catalog = kk_courier_catalog();
	$tpl     = ! empty( $picked[ $courier ] ) ? $picked[ $courier ] : ( isset( $catalog[ $courier ] ) ? $catalog[ $courier ]['link'] : '' );
	foreach ( preg_split( '/\r\n|\r|\n/', (string) kk_opt( 'tr_couriers' ) ) as $line ) { // "key|Name|link" in the Other box
		$parts = array_map( 'trim', explode( '|', $line, 3 ) );
		if ( sanitize_key( $parts[0] ) === $courier && ! empty( $parts[2] ) && 0 === stripos( $parts[2], 'https://' ) ) {
			$tpl = $parts[2];
		}
	}
	if ( '' === (string) $tracking_id || '' === $tpl ) {
		return '';
	}
	return esc_url_raw( str_replace( array( '{id}', '{phone}' ), array( rawurlencode( (string) $tracking_id ), rawurlencode( (string) $phone ) ), $tpl ) );
}

/** URL of a page chosen in settings, falling back to a page with the given slug. */
function kk_page_url( $setting, $fallback_slug ) {
	$id = absint( kk_opt( $setting ) );
	if ( $id && 'publish' === get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	$page = get_page_by_path( $fallback_slug );
	return $page && 'publish' === $page->post_status ? get_permalink( $page ) : '';
}

/**
 * A short lock that two requests at the same moment cannot both get (a double tap on "Send to courier" must never
 * create two parcels). A transient check-then-set is not enough for that. Uses the object cache's atomic add when
 * there is one, otherwise a unique row in wp_options. Returns true when this request holds the lock.
 */
function kk_lock( $name, $ttl = 30 ) {
	global $wpdb;
	$name = 'kk_lock_' . sanitize_key( $name );
	if ( wp_using_ext_object_cache() ) {
		return wp_cache_add( $name, 1, 'kk_locks', $ttl );
	}
	for ( $i = 0; $i < 2; $i++ ) {
		$ok = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) ( time() + $ttl ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $ok ) {
			return true;
		}
		$until = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $until >= time() ) {
			return false;
		}
		// Left over from a request that died: remove it (only if it is still the same stale one) and try once more.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, (string) $until ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	return false;
}

function kk_unlock( $name ) {
	global $wpdb;
	$name = 'kk_lock_' . sanitize_key( $name );
	if ( wp_using_ext_object_cache() ) {
		wp_cache_delete( $name, 'kk_locks' );
		return;
	}
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Private keys of Free features (e.g. Cloudflare Turnstile secret). Stored in their own option: not autoloaded,
 * never in the settings export, never printed. (Courier credentials have their own store in courier.php.)
 */
function kk_core_secret_keys() {
	return array( 'turnstile_secret' );
}

function kk_secret( $key ) {
	$all = get_option( 'kk_secrets', array() );
	return is_array( $all ) && isset( $all[ $key ] ) ? kk_secret_open( (string) $all[ $key ] ) : '';
}

/**
 * Optional encryption of saved API keys (3.25). Off unless wp-config.php defines KK_ENCRYPTION_KEY: the key then
 * lives outside the database, so a leaked database or backup does not leak the courier / Turnstile keys. Without
 * the constant everything works as before (plain text), so nothing can lock a shop out of its couriers.
 */
function kk_secret_box_key() {
	if ( ! defined( 'KK_ENCRYPTION_KEY' ) || '' === (string) KK_ENCRYPTION_KEY || ! function_exists( 'sodium_crypto_secretbox' ) ) {
		return '';
	}
	return sodium_crypto_generichash( 'kenakata-kit|' . (string) KK_ENCRYPTION_KEY, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
}

/** Value to store: encrypted ("kkenc1:…") when a key is set, else unchanged. */
function kk_secret_seal( $value ) {
	$key = kk_secret_box_key();
	if ( '' === $key || '' === (string) $value || 0 === strpos( (string) $value, 'kkenc1:' ) ) {
		return (string) $value;
	}
	$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
	return 'kkenc1:' . base64_encode( $nonce . sodium_crypto_secretbox( (string) $value, $nonce, $key ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/** Stored value → the real one ('' when it is encrypted and the key is missing or different). */
function kk_secret_open( $stored ) {
	$stored = (string) $stored;
	if ( 0 !== strpos( $stored, 'kkenc1:' ) ) {
		return $stored; // plain (saved before encryption was set up)
	}
	$key = kk_secret_box_key();
	$raw = base64_decode( substr( $stored, 7 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	if ( '' === $key || false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
		return '';
	}
	$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $key );
	return false === $plain ? '' : $plain;
}

/** With KK_ENCRYPTION_KEY newly set: encrypt the keys that were saved in plain text (admin screens, once per change). */
add_action( 'admin_init', function () {
	if ( '' === kk_secret_box_key() ) {
		return;
	}
	foreach ( array( 'kk_secrets', 'kk_courier_secrets' ) as $opt ) {
		$all = get_option( $opt, array() );
		if ( ! is_array( $all ) || ! $all ) {
			continue;
		}
		$sealed = array_map( 'kk_secret_seal', array_map( 'strval', $all ) );
		if ( $sealed !== $all ) {
			update_option( $opt, $sealed, false );
		}
	}
} );

/** Are all saved API keys encrypted? null when no key is saved. */
function kk_secrets_sealed() {
	$vals = array();
	foreach ( array( 'kk_secrets', 'kk_courier_secrets' ) as $opt ) {
		$all  = get_option( $opt, array() );
		$vals = array_merge( $vals, is_array( $all ) ? array_values( array_filter( array_map( 'strval', $all ) ) ) : array() );
	}
	if ( ! $vals ) {
		return null;
	}
	foreach ( $vals as $v ) {
		if ( 0 !== strpos( $v, 'kkenc1:' ) ) {
			return false;
		}
	}
	return true;
}

/** Remove one saved key ("Remove" tick box next to the field). */
function kk_delete_secret( $key ) {
	foreach ( array( 'kk_secrets', 'kk_courier_secrets' ) as $opt ) {
		$all = get_option( $opt, array() );
		if ( is_array( $all ) && isset( $all[ $key ] ) ) {
			unset( $all[ $key ] );
			update_option( $opt, $all, false );
		}
	}
	if ( 0 === strpos( (string) $key, 'courier_pathao_' ) ) {
		delete_transient( 'kk_pathao_token' );
		delete_transient( 'kk_pathao_token_sb' );
	}
}

/** Save one key. Empty input keeps the saved one. */
function kk_save_secret( $key, $value ) {
	$value = trim( preg_replace( '/[\x00-\x1F\x7F]/', '', wp_check_invalid_utf8( (string) $value ) ) );
	if ( '' === $value || ! in_array( $key, kk_core_secret_keys(), true ) ) {
		return;
	}
	$all         = get_option( 'kk_secrets', array() );
	$all         = is_array( $all ) ? $all : array();
	$all[ $key ] = kk_secret_seal( $value );
	update_option( 'kk_secrets', $all, false );
}

/** "•••• 1234" for a saved key ('' when nothing is saved): Free keys and courier credentials. */
function kk_secret_hint( $key ) {
	if ( in_array( $key, kk_core_secret_keys(), true ) ) {
		$v = kk_secret( $key );
	} elseif ( function_exists( 'kk_courier_secret' ) ) {
		$v = kk_courier_secret( $key );
	} else {
		$v = '';
	}
	return '' === $v ? '' : '•••• ' . substr( $v, -4 );
}

/** Bengali (০-৯) and Arabic-Indic digits to 0-9: many customers type their number with a Bengali keyboard. */
function kk_digits( $s ) {
	return strtr( (string) $s, array(
		'০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	) );
}

/** Normalise a Bangladeshi mobile number to 01XXXXXXXXX ('' if invalid). */
function kk_normalize_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', kk_digits( $phone ) );
	if ( 0 === strpos( $digits, '880' ) ) {
		$digits = substr( $digits, 2 );
	} elseif ( 0 === strpos( $digits, '1' ) && 10 === strlen( $digits ) ) {
		$digits = '0' . $digits;
	}
	return preg_match( '/^01[3-9]\d{8}$/', $digits ) ? $digits : '';
}

/**
 * Clean + validate a phone number according to the "Phone number check" setting.
 *  bd11 : exactly 11 digits, 01[3-9]XXXXXXXX (spaces and dashes ignored; +880 / 10 / 12 digits rejected)
 *  bd   : Bangladeshi mobile, +880 / 880 prefixes accepted and saved as 01XXXXXXXXX
 *  any  : at least 6 digits
 * Returns the cleaned number, or '' when invalid.
 */
function kk_clean_phone( $phone ) {
	$raw  = trim( kk_digits( $phone ) );
	$mode = kk_opt( 'co_phone_mode' );
	if ( 'bd11' === $mode ) {
		$compact = preg_replace( '/[\s\-().]+/', '', $raw );
		return preg_match( '/^01[3-9]\d{8}$/', $compact ) ? $compact : '';
	}
	if ( 'bd' === $mode ) {
		return kk_normalize_phone( $raw );
	}
	$digits = preg_replace( '/[^\d+]/', '', $raw );
	return strlen( preg_replace( '/\D/', '', $digits ) ) >= 6 ? $digits : '';
}

/** Backward-compatible wrapper. */
function kk_phone_ok( $phone ) {
	return '' !== kk_clean_phone( $phone );
}

/** International number for wa.me / tel: links. "01711-000000" → "8801711000000". */
function kk_intl_phone( $phone ) {
	$digits = preg_replace( '/\D+/', '', (string) $phone );
	if ( preg_match( '/^01\d{9}$/', $digits ) ) {
		return '88' . $digits;
	}
	return $digits;
}

/** Escaped HTML attribute string (with a leading space) from an array. */
function kk_attr_string( array $attrs ) {
	$out = '';
	foreach ( $attrs as $k => $v ) {
		$out .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
	}
	return $out;
}

/** HTML attributes for phone inputs (limits typing to 11 digits in bd11 mode). */
function kk_phone_input_attrs() {
	return 'bd11' === kk_opt( 'co_phone_mode' )
		? array( 'inputmode' => 'numeric', 'maxlength' => '11', 'pattern' => '01[3-9][0-9]{8}', 'autocomplete' => 'tel', 'data-kk-phone' => 'bd11' )
		: array( 'inputmode' => 'tel', 'maxlength' => '16', 'autocomplete' => 'tel' );
}

/** The product brand taxonomy in use (WooCommerce Brands or a popular brand plugin), or ''. */
function kk_brand_taxonomy() {
	foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'berocket_brand' ) as $tax ) {
		if ( taxonomy_exists( $tax ) ) {
			return $tax;
		}
	}
	return '';
}

/** Small inline SVG icons used by several components. */
function kk_icon( $name ) {
	$icons = array(
		'search'   => '<path d="m20 20-3.5-3.5"/><circle cx="11" cy="11" r="7"/>',
		'cart'     => '<path d="M3 4h2l2.4 11.2a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.5L21 8H6.2"/><circle cx="10" cy="20" r="1.4"/><circle cx="17" cy="20" r="1.4"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
		'home'     => '<path d="m3 11 9-7 9 7"/><path d="M5 10v10h14V10"/>',
		'grid'     => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
		'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
		'truck'    => '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
		'check'    => '<path d="m5 12 4.5 4.5L19 7"/>',
		'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
		'prev'     => '<path d="m15 5-7 7 7 7"/>',
		'next'     => '<path d="m9 5 7 7-7 7"/>',
		'store'    => '<path d="M4 9h16l-1-4H5z"/><path d="M5 9v10h14V9"/><path d="M10 19v-5h4v5"/>',
		'box'      => '<path d="m3 7 9-4 9 4-9 4z"/><path d="M3 7v10l9 4 9-4V7"/><path d="M12 11v10"/>',
		'bag'      => '<path d="M5 8h14l-1 12H6z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
		'heart'    => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
		'tag'      => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.3"/>',
		'gift'     => '<rect x="4" y="9" width="16" height="11" rx="1.5"/><path d="M3 9h18v-3H3zM12 6v14M12 6c-1.5-3-5-3-5-1s3 1 5 1c2 0 5 1 5-1s-3.5-2-5 1"/>',
		'chat'     => '<path d="M5 5h14v10H9l-4 4z"/>',
		'menu'     => '<path d="M4 7h16M4 12h16M4 17h16"/>',
		'location' => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'camera'   => '<path d="M4 8h3.2l1.8-2.5h6l1.8 2.5H20v11H4z"/><circle cx="12" cy="13" r="3.4"/>',
		'star'     => '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/>',
	);
	if ( 'whatsapp' === $name ) {
		return '<svg class="kk-ico kk-ico--whatsapp" viewBox="0 0 24 24" width="24" height="24" fill="currentColor" aria-hidden="true" focusable="false"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4a.4.4 0 0 0 0-.4l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .1-1.2c0-.2-.2-.2-.5-.4Z"/></svg>';
	}
	return isset( $icons[ $name ] ) ? '<svg class="kk-ico kk-ico--' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icons[ $name ] . '</svg>' : '';
}

/**
 * Translations. Admin screens follow the "Plugin language" choice on the dashboard (or the WordPress profile language);
 * the shop front end follows the site language. Bengali ships in /languages.
 */
add_action( 'init', function () {
	$pref = kk_opt( 'admin_lang' );
	// The store dashboard (/dashboard) is a back-office screen too: same language choice as the admin screens.
	$back = is_admin() || ( kk_opt( 'sd_enable' ) && function_exists( 'kk_sd_is_dashboard_uri' ) && kk_sd_is_dashboard_uri() );
	if ( $back && in_array( $pref, array( 'en', 'bn' ), true ) ) {
		$bn = 'bn' === $pref;
	} else {
		$bn = 0 === strpos( $back && ! is_admin() && is_user_logged_in() ? get_user_locale() : determine_locale(), 'bn' );
	}
	$GLOBALS['kk_lang_bn'] = $bn; // the store dashboard tells its script which language to use for dates
	if ( $bn ) {
		// Register the Bengali file under the locale WordPress is using now, so it also applies when the profile language is English.
		load_textdomain( 'kenakata-kit', KK_DIR . '/languages/kenakata-kit-bn_BD.mo', determine_locale() );
	}
}, 0 );

/**
 * URL of a plugin asset: the minified copy (name.min.css / name.min.js, made by dev/minify.sh) when it exists,
 * the readable file with SCRIPT_DEBUG on.
 */
function kk_asset( $file ) {
	$min = preg_replace( '/\.(css|js)$/', '.min.$1', $file );
	if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) && $min !== $file && file_exists( KK_DIR . '/assets/' . $min ) ) {
		$file = $min;
	}
	return KK_URL . 'assets/' . $file;
}

/** Settings screens: field list + admin code. Loaded on demand outside the admin (e.g. settings import by REST). */
function kk_load_admin() {
	require_once KK_DIR . '/includes/settings-schema.php';
	require_once KK_DIR . '/includes/admin.php';
}

/** Files that make up the Pro features. */
function kk_pro_modules() {
	return array( 'courier', 'incomplete', 'store-dashboard' );
}

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Kenakata Kit needs the WooCommerce plugin to be installed and active.', 'kenakata-kit' ) . '</p></div>';
		} );
		return;
	}
	// Too old WooCommerce: stay switched off instead of breaking the shop.
	if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.0', '<' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Kenakata Kit is paused: it needs WooCommerce 7.0 or newer. Please update WooCommerce.', 'kenakata-kit' ) . '</p></div>';
		} );
		return;
	}
	require_once KK_DIR . '/includes/license.php';
	// Pro modules (store dashboard, courier, incomplete orders) load only with a valid license key.
	$pro = kk_is_pro();
	// The settings screens (about 140 KB of code) and the GitHub update check are loaded only where they are used:
	// the admin, admin-ajax / admin-post, WP-Cron and WP-CLI. Shop pages skip them (faster without PHP OPcache).
	$back = is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI );
	if ( $back ) {
		kk_load_admin();
		require_once KK_DIR . '/includes/updater.php';
	}
	foreach ( array( 'rest', 'assets', 'product-card', 'products', 'buy-now', 'cart-guard', 'checkout', 'free-delivery', 'order-guard', 'shield', 'orders', 'courier', 'print', 'manual-pay', 'messaging', 'incomplete', 'search', 'account', 'my-account', 'wishlist', 'qa', 'terms', 'extras', 'variations', 'reviews', 'bottom-bar', 'security', 'speed', 'seo-extras', 'cron-health', 'store-dashboard', 'elementor' ) as $file ) {
		if ( ! $pro && in_array( $file, kk_pro_modules(), true ) ) {
			continue;
		}
		require_once KK_DIR . '/includes/' . $file . '.php';
	}
	$GLOBALS['kk_load_ms'] = (int) round( ( microtime( true ) + $GLOBALS['kk_load_ms'] ) * 1000 );
} );

// Uploads protection (.htaccess) follows the setting on activation and is removed on deactivation.
register_activation_hook( __FILE__, function () {
	if ( function_exists( 'kk_sync_uploads_protection' ) ) {
		kk_sync_uploads_protection();
	}
} );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'kk_cron_heartbeat' );
	wp_clear_scheduled_hook( 'kk_license_daily' );
	wp_clear_scheduled_hook( 'kk_incomplete_cleanup' );
	$up = wp_upload_dir( null, false );
	$f  = trailingslashit( $up['basedir'] ) . '.htaccess';
	if ( file_exists( $f ) ) {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		insert_with_markers( $f, 'Kenakata Kit', array() );
	}
} );

// Declare compatibility with WooCommerce High-Performance Order Storage.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', KK_FILE, true );
		// The checkout features are built on the classic checkout: WooCommerce then warns in the block editor and offers the switch.
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', KK_FILE, false );
	}
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	array_unshift(
		$links,
		'<a href="' . esc_url( admin_url( 'admin.php?page=kenakata-kit' ) ) . '">' . esc_html__( 'Settings', 'kenakata-kit' ) . '</a>',
		'<a href="' . esc_url( admin_url( 'admin.php?page=kenakata-kit&tab=help' ) ) . '">' . esc_html__( 'Guide', 'kenakata-kit' ) . '</a>'
	);
	return $links;
} );

/* --- Open Graph locale for Bengali (Facebook has no bn_BD) --- */
add_filter(
	'wpseo_og_locale',
	function () {
		return ( 0 === strpos( get_locale(), 'bn' ) ) ? 'bn_IN' : '';
	}
);
