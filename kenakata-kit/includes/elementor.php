<?php
/**
 * Elementor integration: a "Kenakata Kit · DigiBhai" widget category with 15 styleable widgets.
 * Loaded only when Elementor is active.
 *
 * Safety: before loading the widgets we check that the Elementor version and every Elementor class the widgets use
 * exist. If a future Elementor release removes or renames one, the widgets are paused (with a notice in the admin)
 * instead of breaking the site. The rest of the plugin keeps working.
 */

defined( 'ABSPATH' ) || exit;

define( 'KK_ELEMENTOR_MIN', '3.5.0' ); // first version with the elementor/widgets/register hook

/** '' when the widgets can load, otherwise the reason they are paused. */
function kk_elementor_problem() {
	if ( ! defined( 'ELEMENTOR_VERSION' ) || version_compare( ELEMENTOR_VERSION, KK_ELEMENTOR_MIN, '<' ) ) {
		/* translators: %s: version number */
		return sprintf( __( 'Elementor %s or newer is needed.', 'kenakata-kit' ), KK_ELEMENTOR_MIN );
	}
	$needed = array( '\Elementor\Widget_Base', '\Elementor\Controls_Manager', '\Elementor\Group_Control_Typography', '\Elementor\Group_Control_Border', '\Elementor\Group_Control_Box_Shadow', '\Elementor\Icons_Manager' );
	foreach ( $needed as $class ) {
		if ( ! class_exists( $class ) ) {
			/* translators: %s: PHP class name */
			return sprintf( __( 'This Elementor version no longer has %s.', 'kenakata-kit' ), ltrim( $class, '\\' ) );
		}
	}
	return '';
}

/** Remember an integration error (shown once to administrators). */
function kk_integration_error( $message ) {
	set_transient( 'kk_integration_error', wp_strip_all_tags( (string) $message ), DAY_IN_SECONDS );
}

add_action( 'elementor/elements/categories_registered', function ( $manager ) {
	if ( is_object( $manager ) && method_exists( $manager, 'add_category' ) ) {
		$manager->add_category( 'kenakata-kit', array( 'title' => __( 'Kenakata Kit · DigiBhai', 'kenakata-kit' ), 'icon' => 'eicon-cart' ) );
	}
} );

// "Who can see this" (visitors / logged-in customers) for containers, sections, columns and widgets.
add_action( 'elementor/init', function () {
	if ( '' === kk_elementor_problem() ) {
		require_once KK_DIR . '/includes/elementor/visibility.php';
	}
} );

add_action( 'elementor/widgets/register', function ( $widgets ) {
	$problem = kk_elementor_problem();
	if ( $problem ) {
		kk_integration_error( $problem );
		return;
	}
	try {
		require_once KK_DIR . '/includes/elementor/base.php';
		require_once KK_DIR . '/includes/elementor/products.php';
		require_once KK_DIR . '/includes/elementor/terms.php';
		require_once KK_DIR . '/includes/elementor/misc.php';
		require_once KK_DIR . '/includes/elementor/product.php';
	} catch ( \Throwable $e ) {
		kk_integration_error( $e->getMessage() );
		return;
	}
	$classes = array(
		'KK_EW_Products', 'KK_EW_Products_Carousel', 'KK_EW_Brands', 'KK_EW_Categories', 'KK_EW_Terms',
		'KK_EW_Add_To_Cart', 'KK_EW_Wishlist_Button', 'KK_EW_Product_Reviews', 'KK_EW_Product_Brand',
		'KK_EW_Buy_Now', 'KK_EW_Live_Search', 'KK_EW_Cart_Icon', 'KK_EW_Wishlist_Icon', 'KK_EW_Wishlist_Page', 'KK_EW_Account_Link', 'KK_EW_Contact_Button',
		'KK_EW_Delivery_Info', 'KK_EW_Free_Delivery', 'KK_EW_Checkout', 'KK_EW_Cart', 'KK_EW_My_Account', 'KK_EW_Order_Tracking', 'KK_EW_Thank_You',
	);
	foreach ( $classes as $class ) {
		try {
			if ( class_exists( $class ) ) {
				$widgets->register( new $class() );
			}
		} catch ( \Throwable $e ) {
			kk_integration_error( $class . ': ' . $e->getMessage() );
		}
	}
} );

// Tell administrators (once a day at most) when the widgets had to be paused.
add_action( 'admin_notices', function () {
	$error = get_transient( 'kk_integration_error' );
	if ( ! $error || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="notice notice-warning is-dismissible"><p><strong>Kenakata Kit:</strong> '
		. esc_html__( 'the Elementor widgets were paused so your site keeps working.', 'kenakata-kit' ) . ' ' . esc_html( $error ) . ' '
		. esc_html__( 'Please update Kenakata Kit (or tell DigiBhai.com).', 'kenakata-kit' ) . '</p></div>';
	delete_transient( 'kk_integration_error' );
} );

// Make sure our CSS/JS are loaded inside the Elementor editor preview too.
add_action( 'elementor/preview/enqueue_scripts', function () {
	wp_enqueue_script( 'kenakata-kit' );
} );

// Look of our help boxes in the editor panel.
add_action( 'elementor/editor/after_enqueue_styles', function () {
	wp_add_inline_style( 'elementor-editor', '.kk-ew-help{padding:10px 12px;border-radius:6px;background:#eaf5ef;color:#1d2b25;font-size:12px;line-height:1.6}.kk-ew-help b{display:block;margin-bottom:4px;font-size:13px}.kk-ew-help a{color:#038348;font-weight:600}' );
} );
