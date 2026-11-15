<?php
/**
 * REST: /wp-json/kenakata/v1/settings (GET = all settings, POST = update some keys).
 * Shop managers only. Useful for setting up several stores the same way or for remote support.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', function () {
	register_rest_route( 'kenakata/v1', '/settings', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
			'callback'            => function () {
				return array( 'version' => KK_VERSION, 'settings' => kk_all_opts() );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => function () { return current_user_can( 'manage_woocommerce' ); },
			'callback'            => function ( WP_REST_Request $request ) {
				$data = $request->get_json_params();
				if ( ! is_array( $data ) ) {
					return new WP_Error( 'kk_bad_json', 'JSON object expected.', array( 'status' => 400 ) );
				}
				$count = kk_import_settings( $data );
				return array( 'updated' => $count, 'settings' => kk_all_opts( true ) );
			},
		),
	) );

	// POST /wp-json/kenakata/v1/elementor-css: rebuild Elementor's CSS files (same as Elementor → Tools →
	// Regenerate CSS & Data). Needed after a template is changed outside the editor. Administrators only.
	register_rest_route( 'kenakata/v1', '/elementor-css', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function () {
			$fm = did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ? \Elementor\Plugin::$instance->files_manager : null;
			if ( ! $fm || ! method_exists( $fm, 'clear_cache' ) ) {
				return new WP_Error( 'kk_no_elementor', 'Elementor is not active.', array( 'status' => 400 ) );
			}
			$fm->clear_cache();
			return array( 'cleared' => true );
		},
	) );

	// GET /wp-json/kenakata/v1/diagnose: administrators only. Shows which files and values the shop uses,
	// to find conflicts with other plugins (mini-cart template, free delivery numbers, active hooks).
	register_rest_route( 'kenakata/v1', '/diagnose', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function ( WP_REST_Request $req ) {
			$out = array( 'version' => KK_VERSION );
			if ( function_exists( 'wc_locate_template' ) ) {
				$located = wc_locate_template( 'cart/mini-cart.php' );
				$out['mini_cart_located']  = $located;
				$out['mini_cart_filtered'] = apply_filters( 'wc_get_template', $located, 'cart/mini-cart.php', array(), '', '' );
				$hooks = array();
				foreach ( array( 'woocommerce_locate_template', 'wc_get_template', 'wc_get_template_part' ) as $h ) {
					global $wp_filter;
					if ( isset( $wp_filter[ $h ] ) ) {
						foreach ( $wp_filter[ $h ]->callbacks as $prio => $cbs ) {
							foreach ( $cbs as $cb ) {
								$f = $cb['function'];
								$hooks[ $h ][] = $prio . ' ' . ( is_array( $f ) ? ( is_object( $f[0] ) ? get_class( $f[0] ) : $f[0] ) . '::' . $f[1] : ( is_string( $f ) ? $f : 'closure' ) );
							}
						}
					}
				}
				$out['template_hooks']         = $hooks;
				$out['elementor_mini_cart_opt'] = get_option( 'elementor_use_mini_cart_template' );
			}
			$pid = absint( $req->get_param( 'product' ) );
			if ( $pid && function_exists( 'wc_get_product' ) && ( $p = wc_get_product( $pid ) ) ) {
				$price = (float) wc_get_price_to_display( $p, array( 'qty' => 1 ) );
				$goal  = function_exists( 'kk_fd_amount' ) ? kk_fd_amount() : null;
				$out['fd'] = array( 'raw_price' => $p->get_price(), 'display_price' => $price, 'goal' => $goal, 'left' => null === $goal ? null : $goal - $price, 'left_formatted' => null === $goal ? null : wp_strip_all_tags( wc_price( $goal - $price ) ), 'decimals' => wc_get_price_decimals() );
			}
			return $out;
		},
	) );
} );