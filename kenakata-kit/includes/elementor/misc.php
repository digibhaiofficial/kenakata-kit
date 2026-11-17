<?php
/**
 * Smaller widgets: Buy Now, live search, cart icon, account link, WhatsApp/call button, delivery info,
 * checkout form, order tracking, order confirmation.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;

/* ---------------------------------------------------------------- Buy Now */
class KK_EW_Buy_Now extends KK_EW_Base {
	public function get_name() { return 'kk-buy-now'; }
	public function get_title() { return __( 'Buy Now Button', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-button'; }
	public function get_keywords() { return array( 'buy', 'order', 'checkout', 'kenakata', __( 'Button', 'kenakata-kit' ), __( 'Orders', 'kenakata-kit' ) ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Button', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Buy Now Button', 'kenakata-kit' ), __( 'Adds the product to the cart and opens checkout. On a product template keep "Product of this page"; on a landing page enter the product ID. (Simple products, and variable products with default options.)', 'kenakata-kit' ) );
		$this->add_control( 'source', array( 'label' => __( 'Product', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'current', 'options' => array( 'current' => __( 'Product of this page', 'kenakata-kit' ), 'id' => __( 'By product ID', 'kenakata-kit' ) ) ) );
		$this->add_control( 'product_id', array( 'label' => __( 'Product ID', 'kenakata-kit' ), 'type' => Controls_Manager::NUMBER, 'condition' => array( 'source' => 'id' ) ) );
		$this->add_control( 'text', array( 'label' => __( 'Text', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => kk_opt( 'buynow_text' ), 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'icon', array( 'label' => __( 'Icon', 'kenakata-kit' ), 'type' => Controls_Manager::ICONS, 'skin' => 'inline', 'label_block' => false ) );
		$this->add_control( 'full_width', array( 'label' => __( 'Full width', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'selectors' => array( '{{WRAPPER}} .kk-el-buy-now' => 'width: 100%;' ) ) );
		$this->add_align_control( '.kk-el-buy-now-wrap' );
		$this->end_controls_section();
		$this->add_button_style( '.kk-el-buy-now' );
	}

	protected function render() {
		$s       = $this->get_settings_for_display();
		$id      = 'id' === $s['source'] ? absint( $s['product_id'] ) : get_the_ID();
		$product = $id ? wc_get_product( $id ) : false;
		if ( ! $product || ! kk_opt( 'buynow_enable' ) || ! kk_can_quick_buy( $product ) ) {
			$this->editor_note( __( 'Buy Now: choose an in-stock simple product or a variable product with default options (or place the widget on a product template). Check that Buy Now is on in Kenakata Kit.', 'kenakata-kit' ) );
			return;
		}
		kk_need_js();
		echo '<div class="kk-el-buy-now-wrap"><a class="kk-el-buy-now kk-buy-now" href="' . esc_url( $product->get_permalink() ) . '" data-kk-buy="' . absint( $product->get_id() ) . '" rel="nofollow" aria-label="' . esc_attr( sprintf( /* translators: %s: product name */ __( 'Buy %s now', 'kenakata-kit' ), wp_strip_all_tags( $product->get_name() ) ) ) . '">';
		if ( ! empty( $s['icon']['value'] ) ) {
			\Elementor\Icons_Manager::render_icon( $s['icon'], array( 'aria-hidden' => 'true' ) );
		}
		echo '<span>' . esc_html( $s['text'] ? $s['text'] : kk_opt( 'buynow_text' ) ) . '</span></a></div>';
	}
}

/* ---------------------------------------------------------------- Live search */
class KK_EW_Live_Search extends KK_EW_Base {
	public function get_name() { return 'kk-live-search'; }
	public function get_title() { return __( 'Live Search', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-search'; }
	public function get_keywords() { return array( 'search', 'ajax', 'header', 'kenakata', __( 'Search', 'kenakata-kit' ) ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Search', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Live Search', 'kenakata-kit' ), __( 'Shows products with image and price while typing. "Icon" opens a big search on click (good for mobile headers). "Search bar" shows a full search box in the header.', 'kenakata-kit' ) );
		$this->add_control( 'mode', array( 'label' => __( 'Type', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'icon', 'options' => array( 'icon' => __( 'Icon (opens on click)', 'kenakata-kit' ), 'bar' => __( 'Search bar', 'kenakata-kit' ) ) ) );
		$this->add_control( 'placeholder', array( 'label' => __( 'Placeholder', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => kk_opt( 'search_placeholder' ) ) );
		$this->add_control( 'button_text', array( 'label' => __( 'Button text (empty = icon only)', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'condition' => array( 'mode' => 'bar' ) ) );
		$this->add_align_control( '.kk-search', __( 'Alignment', 'kenakata-kit' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'icon_style', array( 'label' => __( 'Search icon', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'mode' => 'icon' ) ) );
		$this->add_responsive_control( 'box_size', array( 'label' => __( 'Button size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 28, 'max' => 72 ) ), 'selectors' => array( '{{WRAPPER}} .kk-search__toggle' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;' ) ) );
		$this->add_responsive_control( 'icon_size', array( 'label' => __( 'Icon size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 12, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-search__toggle svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'icon_color', array( 'label' => __( 'Icon colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-search__toggle' => 'color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'icon_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-search__toggle' => 'background-color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'icon_color_h', array( 'label' => __( 'Icon hover colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-search__toggle:hover' => 'color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'icon_bg_h', array( 'label' => __( 'Hover background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-search__toggle:hover' => 'background-color: {{VALUE}} !important;' ) ) );
		$this->add_responsive_control( 'icon_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 50 ) ), 'selectors' => array( '{{WRAPPER}} .kk-search__toggle' => 'border-radius: {{SIZE}}{{UNIT}} !important;' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'bar_style', array( 'label' => __( 'Search bar', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'mode' => 'bar' ) ) );
		$this->add_responsive_control( 'bar_width', array( 'label' => __( 'Width', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', '%' ), 'range' => array( 'px' => array( 'min' => 160, 'max' => 900 ), '%' => array( 'min' => 20, 'max' => 100 ) ), 'selectors' => array( '{{WRAPPER}} .kk-search--bar' => 'width: {{SIZE}}{{UNIT}}; max-width: 100%;' ) ) );
		$this->add_responsive_control( 'bar_height', array( 'label' => __( 'Height', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 32, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .kk-search--bar' => '--kk-bar-h: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'bar_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-search--bar .kk-search__input' ) );
		$this->add_control( 'bar_text', array( 'label' => __( 'Text colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-search--bar .kk-search__input' => 'color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'bar_bg', array( 'label' => __( 'Field background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-search--bar .kk-search__input' => 'background-color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'bar_border', array( 'label' => __( 'Border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-search--bar .kk-search__input' => 'border-color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'bar_focus', array( 'label' => __( 'Border while typing', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-search--bar .kk-search__input:focus' => 'border-color: {{VALUE}} !important;' ) ) );
		$this->add_responsive_control( 'bar_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-search--bar' => '--kk-bar-r: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'bar_btn_bg', array( 'label' => __( 'Button background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'separator' => 'before', 'selectors' => array( '{{WRAPPER}} .kk-search__submit' => 'background-color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'bar_btn_color', array( 'label' => __( 'Button text/icon', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-search__submit' => 'color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'bar_btn_bg_h', array( 'label' => __( 'Button hover background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-search__submit:hover' => 'background-color: {{VALUE}} !important;' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		if ( ! kk_opt( 'search_enable' ) ) {
			$this->editor_note( __( 'Live search is off: turn it on in Kenakata Kit → Live search.', 'kenakata-kit' ) );
			return;
		}
		echo kk_live_search_shortcode( array( 'placeholder' => $s['placeholder'], 'mode' => $s['mode'], 'button_text' => $s['button_text'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
}

/* ---------------------------------------------------------------- Cart icon */
class KK_EW_Cart_Icon extends KK_EW_Base {
	public function get_name() { return 'kk-cart-icon'; }
	public function get_title() { return __( 'Cart Icon', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-cart-medium'; }
	public function get_keywords() { return array( 'cart', 'mini cart', 'header', 'kenakata', 'কার্ট' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Cart Icon', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Cart Icon', 'kenakata-kit' ), __( 'Shows the number of items in the cart and updates without reloading. With "Side cart" on, a click opens the cart from the side; otherwise it goes to the cart page.', 'kenakata-kit' ) );
		$this->add_control( 'show_total', array( 'label' => __( 'Show total', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => '' ) );
		$this->add_align_control( '.kk-el-cart-wrap' );
		$this->end_controls_section();

		$this->start_controls_section( 'cart_style', array( 'label' => __( 'Icon', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'icon_size', array( 'label' => __( 'Icon size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 14, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .kk-cart__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'icon_color', array( 'label' => __( 'Colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-cart' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'icon_color_h', array( 'label' => __( 'Hover colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-cart:hover' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'icon_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-cart' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'icon_pad', array( 'label' => __( 'Padding', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 24 ) ), 'selectors' => array( '{{WRAPPER}} .kk-cart' => 'padding: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'icon_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 50 ) ), 'selectors' => array( '{{WRAPPER}} .kk-cart' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'count_bg', array( 'label' => __( 'Count badge colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'separator' => 'before', 'selectors' => array( '{{WRAPPER}} .kk-cart__count' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'count_color', array( 'label' => __( 'Count colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-cart__count' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'total_typo', 'label' => __( 'Total typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-cart__total', 'condition' => array( 'show_total' => 'yes' ) ) );
		$this->end_controls_section();

		/* Side cart: printed once at the end of the page, so these styles apply site-wide (put the widget in the header). */
		$this->kk_global = true;
		$d = '.kk-drawer ';
		$this->start_controls_section( 'drawer_note', array( 'label' => __( 'Side cart (applies to the whole site)', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'drawer_help', array( 'type' => Controls_Manager::RAW_HTML, 'raw' => '<div class="kk-ew-help">' . esc_html__( 'The sections below style the side cart that opens from this icon. Turn the side cart on and choose what it shows (pictures, remove button, button texts) in Kenakata Kit → Side cart.', 'kenakata-kit' ) . '</div>' ) );
		$this->add_control( 'drawer_preview', array( 'label' => __( 'Preview side cart in the editor', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'description' => __( 'Shows an open side cart with sample products below the icon while you edit, so you can see every style change. Turn it off again when you are done. Visitors never see this preview.', 'kenakata-kit' ) ) );
		$this->add_responsive_control( 'drawer_width', array( 'label' => __( 'Width', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'px', 'vw' ), 'range' => array( 'px' => array( 'min' => 280, 'max' => 720 ), 'vw' => array( 'min' => 50, 'max' => 100 ) ), 'selectors' => array( 'body .kk-drawer .kk-drawer__panel' => 'width: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'drawer_backdrop', array( 'label' => __( 'Page overlay colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( 'body .kk-drawer .kk-drawer__backdrop' => 'background-color: {{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'drawer_panel', __( 'Side cart: panel', 'kenakata-kit' ), $d . '.kk-drawer__panel' );
		$this->add_box_style( 'drawer_head', __( 'Side cart: header', 'kenakata-kit' ), $d . '.kk-drawer__head' );
		$this->add_text_style( 'drawer_title', __( 'Side cart: title', 'kenakata-kit' ), $d . '.kk-drawer__head h2' );
		$this->add_button_style( $d . '.kk-drawer__close', 'drawer_close', __( 'Side cart: close (×) button', 'kenakata-kit' ), array(), true );

		$item = $d . '.kk-drawer__body .woocommerce-mini-cart-item';
		$this->start_controls_section( 'drawer_items_style', array( 'label' => __( 'Side cart: products', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'drawer_img', array( 'label' => __( 'Picture size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 32, 'max' => 120 ) ), 'selectors' => array( 'body ' . $item . ' img' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;', 'body ' . $item => 'padding-left: calc({{SIZE}}{{UNIT}} + 12px) !important; min-height: calc({{SIZE}}{{UNIT}} + 24px);' ) ) );
		$this->add_control( 'drawer_img_fit', array( 'label' => __( 'Picture fit', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'Default (whole picture)', 'kenakata-kit' ), 'cover' => __( 'Fill (crop)', 'kenakata-kit' ), 'contain' => __( 'Whole picture', 'kenakata-kit' ) ), 'selectors' => array( 'body ' . $item . ' img' => 'object-fit: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'drawer_img_radius', array( 'label' => __( 'Picture corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( 'body ' . $item . ' img' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'drawer_img_bg', array( 'label' => __( 'Picture background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( 'body ' . $item . ' img' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'drawer_item_pad', array( 'label' => __( 'Space above / below each product', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 4, 'max' => 32 ) ), 'selectors' => array( 'body ' . $item => 'padding-top: {{SIZE}}{{UNIT}} !important; padding-bottom: {{SIZE}}{{UNIT}} !important;', 'body ' . $item . ' img, body ' . $item . ' .remove' => 'top: {{SIZE}}{{UNIT}} !important;' ) ) );
		$this->add_control( 'drawer_divider', array( 'label' => __( 'Line between products', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( 'body ' . $item => 'border-bottom-color: {{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_text_style( 'drawer_name', __( 'Side cart: product name', 'kenakata-kit' ), $item . ' a:not(.remove)', $item . ' a:not(.remove):hover' );
		$this->add_text_style( 'drawer_meta', __( 'Side cart: options (size, colour)', 'kenakata-kit' ), $item . ' dl.variation, ' . $item . ' dl.variation dt, ' . $item . ' dl.variation dd, ' . $item . ' dl.variation p' );
		$this->add_text_style( 'drawer_qty', __( 'Side cart: quantity × price', 'kenakata-kit' ), $item . ' .quantity, ' . $item . ' .quantity .amount' );
		$this->add_button_style( $item . ' a.remove', 'drawer_remove', __( 'Side cart: remove (×) button', 'kenakata-kit' ), array(), true );
		$this->add_box_style( 'drawer_total', __( 'Side cart: subtotal row', 'kenakata-kit' ), $d . '.woocommerce-mini-cart__total' );
		$this->add_text_style( 'drawer_total_text', __( 'Side cart: subtotal text', 'kenakata-kit' ), $d . '.woocommerce-mini-cart__total, ' . $d . '.woocommerce-mini-cart__total strong, ' . $d . '.woocommerce-mini-cart__total .amount' );
		$this->add_button_style( $d . '.woocommerce-mini-cart__buttons a:not(.checkout)', 'drawer_view', __( 'Side cart: View cart button', 'kenakata-kit' ), array(), true );
		$this->add_button_style( $d . '.woocommerce-mini-cart__buttons a.checkout', 'drawer_checkout', __( 'Side cart: Checkout button', 'kenakata-kit' ), array(), true );
		$this->add_text_style( 'drawer_empty', __( 'Side cart: empty-cart message', 'kenakata-kit' ), $d . '.woocommerce-mini-cart__empty-message' );
		$this->kk_global = false;
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$preview = kk_is_editor() && 'yes' === ( $s['drawer_preview'] ?? '' );
		echo '<div class="kk-el-cart-wrap' . ( $preview ? ' kk-cart-wrap--preview' : '' ) . '">' . kk_cart_icon( array( 'show_total' => 'yes' === $s['show_total'] ? 'yes' : 'no' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
		if ( $preview ) {
			echo kk_drawer_preview_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
		}
		echo '</div>';
	}
}

/* ---------------------------------------------------------------- Account link */
class KK_EW_Account_Link extends KK_EW_Base {
	public function get_name() { return 'kk-account-link'; }
	public function get_title() { return __( 'Login / My Account', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-lock-user'; }
	public function get_keywords() { return array( 'login', 'account', 'register', 'kenakata', 'লগইন' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Link', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Login / My Account', 'kenakata-kit' ), __( 'Visitors get the login/registration popup; logged-in customers go to My account.', 'kenakata-kit' ) );
		$this->add_control( 'login_text', array( 'label' => __( 'Text when logged out', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Log in', 'kenakata-kit' ) ) );
		$this->add_control( 'account_text', array( 'label' => __( 'Text when logged in', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'My account', 'kenakata-kit' ) ) );
		$this->add_control( 'icon', array( 'label' => __( 'Icon', 'kenakata-kit' ), 'type' => Controls_Manager::ICONS, 'default' => array( 'value' => 'far fa-user', 'library' => 'fa-regular' ) ) );
		$this->add_control( 'hide_text', array( 'label' => __( 'Icon only', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER ) );
		$this->add_align_control( '.kk-el-account-wrap' );
		$this->end_controls_section();
		$this->add_button_style( '.kk-el-account', 'link', __( 'Link', 'kenakata-kit' ) );
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$text = is_user_logged_in() ? $s['account_text'] : $s['login_text'];
		if ( '' === trim( (string) $text ) ) {
			$text = is_user_logged_in() ? __( 'My account', 'kenakata-kit' ) : __( 'Log in', 'kenakata-kit' ); // icon-only links still need a name for screen readers and audits
		}
		echo '<div class="kk-el-account-wrap"><a class="kk-el-account" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" data-kk-login' . ( 'yes' === $s['hide_text'] ? ' aria-label="' . esc_attr( $text ) . '"' : '' ) . '>';
		if ( ! empty( $s['icon']['value'] ) ) {
			\Elementor\Icons_Manager::render_icon( $s['icon'], array( 'aria-hidden' => 'true' ) );
		}
		if ( 'yes' !== $s['hide_text'] ) {
			echo '<span>' . esc_html( $text ) . '</span>';
		}
		echo '</a></div>';
	}
}

/* ---------------------------------------------------------------- WhatsApp / call */
class KK_EW_Contact_Button extends KK_EW_Base {
	public function get_name() { return 'kk-contact-button'; }
	public function get_title() { return __( 'WhatsApp / Call Button', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-headphones'; }
	public function get_keywords() { return array( 'whatsapp', 'call', 'phone', 'contact', 'order', 'kenakata', 'হোয়াটসঅ্যাপ', 'কল' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Button', 'kenakata-kit' ) ) );
		$this->help_box( __( 'WhatsApp / Call Button', 'kenakata-kit' ), __( 'Leave the number empty to use the one in Kenakata Kit → WhatsApp & Call. On a product page, {product}, {price} and {url} in the message are filled in.', 'kenakata-kit' ) );
		$this->add_control( 'contact_type', array( 'label' => __( 'Type', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'whatsapp', 'options' => array( 'whatsapp' => __( 'WhatsApp chat', 'kenakata-kit' ), 'call' => __( 'Phone call', 'kenakata-kit' ) ) ) );
		$this->add_control( 'text', array( 'label' => __( 'Text', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'Order on WhatsApp' ) );
		$this->add_control( 'number', array( 'label' => __( 'Number (optional)', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'placeholder' => '01XXXXXXXXX' ) );
		$this->add_control( 'message', array( 'label' => __( 'Pre-filled message (optional)', 'kenakata-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 3, 'placeholder' => 'I want to order: {product} ({price})', 'condition' => array( 'contact_type' => 'whatsapp' ) ) );
		$this->add_control( 'full_width', array( 'label' => __( 'Full width', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'selectors' => array( '{{WRAPPER}} .kk-contact-btn' => 'width: 100%;' ) ) );
		$this->add_align_control( '.kk-el-contact-wrap' );
		$this->end_controls_section();
		$this->add_button_style( '.kk-contact-btn', 'btn', __( 'Button', 'kenakata-kit' ), array(), true );
		$this->start_controls_section( 'icon_style', array( 'label' => __( 'Icon', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'icon_size', array( 'label' => __( 'Size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 12, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-contact-btn svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo '<div class="kk-el-contact-wrap">' . kk_whatsapp_button( array( 'type' => $s['contact_type'], 'text' => $s['text'], 'number' => $s['number'], 'message' => $s['message'] ?? '' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
}

/* ---------------------------------------------------------------- Delivery info */
class KK_EW_Delivery_Info extends KK_EW_Base {
	public function get_name() { return 'kk-delivery-info'; }
	public function get_title() { return __( 'Delivery Info', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-info-box'; }
	public function get_keywords() { return array( 'delivery', 'shipping', 'cod', 'info', 'kenakata', 'ডেলিভারি' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Information', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Delivery Info', 'kenakata-kit' ), __( 'Delivery charges come from the WooCommerce shipping settings and update automatically.', 'kenakata-kit' ) );
		$this->add_control( 'title', array( 'label' => __( 'Title', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => kk_opt( 'di_title' ) ) );
		$this->add_control( 'charges', array( 'label' => __( 'Show delivery charges', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'lines', array( 'label' => __( 'Extra lines (one per line)', 'kenakata-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 5, 'default' => kk_opt( 'di_lines' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'box', __( 'Box', 'kenakata-kit' ), '.kk-delivery' );
		$this->add_text_style( 'dtitle', __( 'Title', 'kenakata-kit' ), '.kk-delivery__title' );
		$this->add_text_style( 'item', __( 'Lines', 'kenakata-kit' ), '.kk-delivery__item' );
		$this->start_controls_section( 'dicon_style', array( 'label' => __( 'Icon', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'icon_color', array( 'label' => __( 'Colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-delivery__item svg' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'icon_size', array( 'label' => __( 'Size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 12, 'max' => 32 ) ), 'selectors' => array( '{{WRAPPER}} .kk-delivery__item svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'items_gap', array( 'label' => __( 'Space between lines', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 24 ) ), 'selectors' => array( '{{WRAPPER}} .kk-delivery__list' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		echo kk_delivery_info( array( 'title' => $s['title'], 'charges' => 'yes' === $s['charges'] ? 'yes' : 'no', 'lines' => $s['lines'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
}

/* ---------------------------------------------------------------- Free delivery bar */
class KK_EW_Free_Delivery extends KK_EW_Base {
	public function get_name() { return 'kk-free-delivery'; }
	public function get_title() { return __( 'Free Delivery Bar', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-skill-bar'; }
	public function get_keywords() { return array( 'free', 'delivery', 'shipping', 'progress', 'kenakata' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Free delivery', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Free Delivery Bar', 'kenakata-kit' ), __( 'Shows how much more the customer needs to buy for free delivery. Turn the feature on and set the amount in Kenakata Kit → Checkout → Free delivery. The bar updates by itself when products are added.', 'kenakata-kit' ) );
		$this->end_controls_section();
		$this->add_box_style( 'fdbox', __( 'Box', 'kenakata-kit' ), '.kk-fd' );
		$this->add_text_style( 'fdtext', __( 'Text', 'kenakata-kit' ), '.kk-fd__text' );
		$this->start_controls_section( 'fdbar_style', array( 'label' => __( 'Progress bar', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'bar_color', array( 'label' => __( 'Bar colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-fd__bar' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'track_color', array( 'label' => __( 'Track colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-fd__track' => 'background: {{VALUE}};' ) ) );
		$this->add_control( 'icon_color', array( 'label' => __( 'Icon colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-fd__text svg' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'bar_height', array( 'label' => __( 'Bar height', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 2, 'max' => 16 ) ), 'selectors' => array( '{{WRAPPER}} .kk-fd__track' => 'height: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		if ( kk_is_editor() ) {
			echo '<div class="kk-fd" role="status"><p class="kk-fd__text">' . kk_icon( 'truck' ) . '<span>' . wp_kses_post( str_replace( array( '{remaining}', '{amount}' ), array( wc_price( max( 0, kk_fd_amount() * 0.4 ) ), wc_price( kk_fd_amount() ) ), esc_html( (string) kk_opt( 'fd_text' ) ) ) ) . '</span></p><div class="kk-fd__track" aria-hidden="true"><span class="kk-fd__bar" style="width:60%"></span></div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG
			if ( ! kk_fd_active() ) { $this->editor_note( __( 'Free delivery is switched off in Kenakata Kit → Checkout, so visitors will not see this bar yet.', 'kenakata-kit' ) ); }
			return;
		}
		echo kk_free_delivery_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
}

/* ---------------------------------------------------------------- Checkout */
class KK_EW_Checkout extends KK_EW_Base {
	public function get_name() { return 'kk-checkout'; }
	public function get_title() { return __( 'Checkout Form', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-checkout'; }
	public function get_keywords() { return array( 'checkout', 'order', 'form', 'kenakata', __( 'Checkout', 'kenakata-kit' ) ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Checkout', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Checkout Form', 'kenakata-kit' ), __( 'Use on the Checkout page only. Fields and labels are set in Kenakata Kit → Checkout; change the design in the Style tab here. The editor shows a sample form.', 'kenakata-kit' ) );
		$this->end_controls_section();

		$this->add_box_style( 'sections', __( 'Boxes (form and order summary)', 'kenakata-kit' ), '.woocommerce-billing-fields, #order_review' );
		$this->add_text_style( 'co_heading', __( 'Title', 'kenakata-kit' ), 'form.checkout h3, #order_review_heading' );
		$this->add_text_style( 'co_label', __( 'Field labels', 'kenakata-kit' ), 'form.checkout .form-row > label' );
		$this->start_controls_section( 'inputs_style', array( 'label' => __( 'Input fields', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$sel = '{{WRAPPER}} form.checkout .form-row input.input-text, {{WRAPPER}} form.checkout .form-row textarea';
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'input_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => $sel ) );
		$this->add_control( 'input_color', array( 'label' => __( 'Text colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'input_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'background-color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'input_border', array( 'label' => __( 'Border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'border-color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'input_focus', array( 'label' => __( 'Border while typing', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} form.checkout .form-row input.input-text:focus, {{WRAPPER}} form.checkout .form-row textarea:focus' => 'border-color: {{VALUE}} !important; box-shadow: 0 0 0 3px color-mix(in srgb, {{VALUE}} 18%, transparent) !important;' ) ) );
		$this->add_responsive_control( 'input_height', array( 'label' => __( 'Height', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 36, 'max' => 70 ) ), 'selectors' => array( '{{WRAPPER}} form.checkout .form-row input.input-text' => 'min-height: {{SIZE}}{{UNIT}} !important;' ) ) );
		$this->add_responsive_control( 'input_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ), 'selectors' => array( $sel => 'border-radius: {{SIZE}}{{UNIT}} !important;' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'area_style', array( 'label' => __( 'Delivery area buttons', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$a = '{{WRAPPER}} .kk-delivery-area label.radio';
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'area_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => $a ) );
		$this->add_control( 'area_color', array( 'label' => __( 'Text colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $a => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'area_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $a => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'area_border', array( 'label' => __( 'Border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $a => 'border-color: {{VALUE}};' ) ) );
		$this->add_control( 'area_on_color', array( 'label' => __( 'Selected: text', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'separator' => 'before', 'selectors' => array( '{{WRAPPER}} .kk-delivery-area input[type=radio]:checked + label.radio' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'area_on_bg', array( 'label' => __( 'Selected: background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-delivery-area input[type=radio]:checked + label.radio' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'area_on_border', array( 'label' => __( 'Selected: border', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-delivery-area input[type=radio]:checked + label.radio' => 'border-color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'summary_style', array( 'label' => __( 'Order summary', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'summary_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} #order_review table.shop_table th, {{WRAPPER}} #order_review table.shop_table td' ) );
		$this->add_control( 'summary_color', array( 'label' => __( 'Text colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} #order_review table.shop_table' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'total_typo', 'label' => __( 'Total', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} #order_review table.shop_table tr.order-total th, {{WRAPPER}} #order_review table.shop_table tr.order-total td' ) );
		$this->add_control( 'total_color', array( 'label' => __( 'Total colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} #order_review table.shop_table tr.order-total th, {{WRAPPER}} #order_review table.shop_table tr.order-total td' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_button_style( '#place_order', 'place', __( 'Order button', 'kenakata-kit' ), array(), true );
	}

	protected function render() {
		echo kk_need_css( 'checkout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a <link> built from our own URL
		if ( kk_is_editor() ) {
			echo $this->mock(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup
			return;
		}
		echo do_shortcode( '[woocommerce_checkout]' );
	}

	/** Sample checkout for the editor (same classes as the real one, so styles preview correctly). */
	protected function mock() {
		$field = function ( $label, $ph, $textarea = false ) {
			return '<p class="form-row form-row-wide"><label>' . esc_html( $label ) . ' <abbr class="required">*</abbr></label><span class="woocommerce-input-wrapper">'
				. ( $textarea ? '<textarea class="input-text" rows="3" placeholder="' . esc_attr( $ph ) . '"></textarea>' : '<input type="text" class="input-text" placeholder="' . esc_attr( $ph ) . '">' ) . '</span></p>';
		};
		$areas = function_exists( 'kk_area_methods' ) && kk_area_methods() ? array_values( kk_area_methods() ) : array( array( 'title' => 'Inside Dhaka', 'cost' => 100 ), array( 'title' => 'Outside Dhaka', 'cost' => 150 ) );
		$radios = '';
		foreach ( $areas as $i => $m ) {
			$id      = 'kk-mock-area-' . $this->get_id() . '-' . $i;
			$radios .= '<input type="radio" class="input-radio" name="kk_mock_area_' . esc_attr( $this->get_id() ) . '" id="' . esc_attr( $id ) . '"' . ( 0 === $i ? ' checked' : '' ) . '><label class="radio" for="' . esc_attr( $id ) . '">' . esc_html( $m['title'] ) . ' — ' . wp_kses_post( wc_price( $m['cost'] ) ) . '</label>';
		}
		return '<div class="woocommerce"><form class="checkout woocommerce-checkout kk-co-mock" onsubmit="return false"><div id="customer_details" class="col2-set"><div class="col-1"><div class="woocommerce-billing-fields"><h3>' . esc_html( kk_opt( 'co_heading' ) ) . '</h3>'
			. $field( kk_opt( 'co_name_label' ), kk_opt( 'co_name_placeholder' ) )
			. $field( kk_opt( 'co_phone_label' ), kk_opt( 'co_phone_placeholder' ) )
			. $field( kk_opt( 'co_address_label' ), kk_opt( 'co_address_placeholder' ), true )
			. '<p class="form-row form-row-wide kk-delivery-area"><label>' . esc_html( kk_opt( 'co_area_label' ) ) . ' <abbr class="required">*</abbr></label><span class="woocommerce-input-wrapper">' . $radios . '</span></p>'
			. '</div></div></div><h3 id="order_review_heading">Your order</h3><div id="order_review"><table class="shop_table"><tbody><tr><td>Sample product × 1</td><td>1,250.00৳</td></tr></tbody><tfoot><tr><th>Subtotal</th><td>1,250.00৳</td></tr><tr><th>Delivery</th><td>100.00৳</td></tr><tr class="order-total"><th>Total</th><td><strong>1,350.00৳</strong></td></tr></tfoot></table>'
			. '<div id="payment"><div class="form-row place-order"><button type="button" class="button alt" id="place_order">' . esc_html( kk_opt( 'co_button_text' ) ) . '</button></div></div></div></form></div>';
	}
}

/* ---------------------------------------------------------------- Cart page / My account */
class KK_EW_Cart extends KK_EW_Base {
	public function get_name() { return 'kk-cart'; }
	public function get_title() { return __( 'Cart Page', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-cart-medium'; }
	public function get_keywords() { return array( 'cart', 'basket', 'kenakata', 'কার্ট' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Cart', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Cart Page', 'kenakata-kit' ), __( 'Use on the Cart page only. It shows the products in the cart, quantities and totals. The editor shows a short note instead of the real cart.', 'kenakata-kit' ) );
		$this->end_controls_section();

		$this->add_box_style( 'cart_box', __( 'Boxes (product list and totals)', 'kenakata-kit' ), 'table.shop_table, .cart_totals' );
		$this->add_text_style( 'cart_heading', __( 'Totals title', 'kenakata-kit' ), '.cart_totals h2' );
		$this->add_text_style( 'cart_name', __( 'Product name', 'kenakata-kit' ), 'td.product-name a' );
		$this->add_button_style( '.wc-proceed-to-checkout .checkout-button', 'go', __( 'Checkout button', 'kenakata-kit' ), array(), true );
	}

	protected function render() {
		if ( kk_is_editor() || ! function_exists( 'WC' ) || ! WC()->cart ) {
			echo '<div class="woocommerce kk-ew-note"><p class="cart-empty woocommerce-info">' . esc_html__( 'The customer\'s cart appears here on the live page.', 'kenakata-kit' ) . '</p></div>';
			return;
		}
		echo kk_need_css( 'checkout' ) . do_shortcode( '[woocommerce_cart]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

class KK_EW_My_Account extends KK_EW_Base {
	public function get_name() { return 'kk-my-account'; }
	public function get_title() { return __( 'My Account', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-my-account'; }
	public function get_keywords() { return array( 'account', 'login', 'orders', 'dashboard', 'kenakata', 'অ্যাকাউন্ট' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'My Account', 'kenakata-kit' ) ) );
		$this->help_box( __( 'My Account', 'kenakata-kit' ), __( 'Use on the My account page. Visitors see the login card; customers see their dashboard, orders and details. Change colours and sizes in the Style tab. Use "Preview in the editor" to style both views.', 'kenakata-kit' ) );
		$this->add_control( 'preview', array( 'label' => __( 'Preview in the editor', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'guest', 'options' => array( 'guest' => __( 'Login card (visitor)', 'kenakata-kit' ), 'member' => __( 'Dashboard (customer)', 'kenakata-kit' ) ) ) );
		$this->add_control( 'login_intro', array( 'label' => __( 'Line under the login title', 'kenakata-kit' ), 'type' => Controls_Manager::TEXTAREA, 'rows' => 2, 'default' => kk_account_defaults()['login_intro'], 'separator' => 'before' ) );
		$this->add_control( 'cards', array( 'label' => __( 'Dashboard: greeting, latest order and quick links', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'menu', array( 'label' => __( 'Account menu', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'side', 'options' => array( 'side' => __( 'At the side', 'kenakata-kit' ), 'top' => __( 'On top (tabs)', 'kenakata-kit' ) ) ) );
		$this->add_control( 'icons', array( 'label' => __( 'Icons in the menu', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'hide', array( 'label' => __( 'Hide these menu items', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'default' => array( 'downloads' ), 'options' => kk_account_menu_choices() ) );
		$this->end_controls_section();

		$this->add_box_style( 'acc_login', __( 'Login card', 'kenakata-kit' ), '.kk-account.is-guest > .woocommerce' );
		$this->add_text_style( 'acc_title', __( 'Titles', 'kenakata-kit' ), '.kk-account h2, .kk-account h3' );
		$this->add_text_style( 'acc_text', __( 'Text', 'kenakata-kit' ), '.kk-account p, .kk-acc-login-intro, .kk-acc-card__text span' );
		$this->add_text_style( 'acc_label', __( 'Field labels', 'kenakata-kit' ), '.kk-account .form-row > label' );

		$this->start_controls_section( 'acc_input_style', array( 'label' => __( 'Input fields', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$in = '{{WRAPPER}} .kk-account form .input-text, {{WRAPPER}} .kk-account form select';
		$this->add_control( 'acc_input_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $in => 'background-color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'acc_input_border', array( 'label' => __( 'Border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $in => 'border-color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'acc_input_focus', array( 'label' => __( 'Border while typing', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-account form .input-text:focus' => 'border-color: {{VALUE}} !important; box-shadow: 0 0 0 3px color-mix(in srgb, {{VALUE}} 18%, transparent) !important;' ) ) );
		$this->add_responsive_control( 'acc_input_height', array( 'label' => __( 'Height', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 36, 'max' => 70 ) ), 'selectors' => array( '{{WRAPPER}} .kk-account form .input-text' => 'min-height: {{SIZE}}{{UNIT}} !important;' ) ) );
		$this->add_responsive_control( 'acc_input_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ), 'selectors' => array( $in => 'border-radius: {{SIZE}}{{UNIT}} !important;' ) ) );
		$this->end_controls_section();

		$this->add_button_style( '.kk-account .button, .kk-account button.button', 'acc_btn', __( 'Buttons', 'kenakata-kit' ), array(), true );

		$this->add_box_style( 'acc_nav', __( 'Menu box', 'kenakata-kit' ), '.kk-account .woocommerce-MyAccount-navigation' );
		$this->start_controls_section( 'acc_nav_items', array( 'label' => __( 'Menu items', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$a = '{{WRAPPER}} .kk-account .woocommerce-MyAccount-navigation li a';
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'acc_nav_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => $a ) );
		$this->add_control( 'acc_nav_color', array( 'label' => __( 'Text colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $a => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'acc_nav_on_color', array( 'label' => __( 'Active / hover: text', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $a . ':hover, {{WRAPPER}} .kk-account .woocommerce-MyAccount-navigation li.is-active a' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'acc_nav_on_bg', array( 'label' => __( 'Active / hover: background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $a . ':hover, {{WRAPPER}} .kk-account .woocommerce-MyAccount-navigation li.is-active a' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'acc_nav_logout', array( 'label' => __( 'Log out colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-account .woocommerce-MyAccount-navigation-link--customer-logout a' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->add_box_style( 'acc_content', __( 'Content box', 'kenakata-kit' ), '.kk-account .woocommerce-MyAccount-content' );
		$this->add_box_style( 'acc_cards', __( 'Dashboard cards', 'kenakata-kit' ), '.kk-acc-card', '.kk-acc-card:hover', array( 'cards' => 'yes' ) );
		$this->start_controls_section( 'acc_card_icon', array( 'label' => __( 'Dashboard card icons', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'cards' => 'yes' ) ) );
		$this->add_control( 'acc_icon_color', array( 'label' => __( 'Icon colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-acc-card__icon' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'acc_icon_bg', array( 'label' => __( 'Icon background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-acc-card__icon' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'acc_hello_bg', array( 'label' => __( 'Greeting background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'separator' => 'before', 'selectors' => array( '{{WRAPPER}} .kk-acc-hello' => 'background: {{VALUE}};' ) ) );
		// !important: the default greeting is white text on the primary colour and its title / log-out link use !important.
		$this->add_control( 'acc_hello_color', array( 'label' => __( 'Greeting text', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'description' => __( 'With a light greeting background choose a dark text colour; it is also used for the round initial and the "Log out" button.', 'kenakata-kit' ), 'selectors' => array( '{{WRAPPER}} .kk-acc-hello, {{WRAPPER}} .kk-acc-hello h2, {{WRAPPER}} .kk-acc-hello p, {{WRAPPER}} .kk-acc-avatar, {{WRAPPER}} .kk-acc-logout' => 'color: {{VALUE}} !important;', '{{WRAPPER}} .kk-acc-logout' => 'border-color: {{VALUE}};' ) ) );
		$this->add_control( 'acc_avatar_bg', array( 'label' => __( 'Initial circle background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-acc-avatar' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'acc_logout_hover_bg', array( 'label' => __( '"Log out" hover background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-acc-logout:hover' => 'background-color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	protected function args() {
		$s = $this->get_settings_for_display();
		return array(
			'cards'       => 'yes' === ( $s['cards'] ?? '' ) ? 'yes' : 'no',
			'icons'       => 'yes' === ( $s['icons'] ?? '' ) ? 'yes' : 'no',
			'menu'        => $s['menu'] ?? 'side',
			'hide'        => implode( ',', array_map( 'sanitize_key', (array) ( $s['hide'] ?? array() ) ) ),
			'login_intro' => $s['login_intro'] ?? '',
		);
	}

	protected function render() {
		$a = $this->args();
		if ( kk_is_editor() ) {
			echo $this->mock( $a, $this->get_settings_for_display()['preview'] ?? 'guest' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static sample markup
			return;
		}
		echo kk_my_account_render( $a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce output
	}

	/** Sample markup with the real classes, so the Style tab previews correctly in the editor. */
	protected function mock( $a, $view ) {
		$icons = 'yes' === $a['icons'] ? ' kk-account--icons' : '';
		$menu  = 'top' === $a['menu'] ? 'top' : 'side';
		if ( 'member' !== $view ) {
			$field = function ( $label, $type ) {
				return '<p class="woocommerce-form-row form-row"><label>' . esc_html( $label ) . ' <span class="required">*</span></label><input type="' . esc_attr( $type ) . '" class="input-text" disabled></p>';
			};
			return '<div class="kk-account is-guest kk-account--menu-' . $menu . $icons . '"><div class="woocommerce"><h2>' . esc_html__( 'Login', 'kenakata-kit' ) . '</h2><form class="woocommerce-form woocommerce-form-login login" onsubmit="return false">'
				. ( '' !== trim( $a['login_intro'] ) ? '<p class="kk-acc-login-intro">' . esc_html( $a['login_intro'] ) . '</p>' : '' )
				. $field( __( 'Username or email address', 'kenakata-kit' ), 'text' ) . $field( __( 'Password', 'kenakata-kit' ), 'password' )
				. '<p class="form-row"><label class="woocommerce-form__label woocommerce-form-login__rememberme"><input type="checkbox" disabled> <span>' . esc_html__( 'Remember me', 'kenakata-kit' ) . '</span></label><button type="button" class="woocommerce-button button woocommerce-form-login__submit">' . esc_html__( 'Log in', 'kenakata-kit' ) . '</button></p>'
				. '<p class="woocommerce-LostPassword lost_password"><a href="#">' . esc_html__( 'Lost your password?', 'kenakata-kit' ) . '</a></p></form></div></div>';
		}
		$items = array( 'dashboard' => __( 'Dashboard', 'kenakata-kit' ) ) + kk_account_menu_choices() + array( 'customer-logout' => __( 'Log out', 'kenakata-kit' ) );
		foreach ( kk_list( $a['hide'] ) as $k ) {
			unset( $items[ $k ] );
		}
		$nav = '';
		foreach ( $items as $k => $label ) {
			$nav .= '<li class="woocommerce-MyAccount-navigation-link woocommerce-MyAccount-navigation-link--' . esc_attr( $k ) . ( 'dashboard' === $k ? ' is-active' : '' ) . '"><a href="#">' . esc_html( $label ) . '</a></li>';
		}
		$card = function ( $icon, $title, $text ) {
			return '<li><a class="kk-acc-card" href="#"><span class="kk-acc-card__icon">' . kk_icon( $icon ) . '</span><span class="kk-acc-card__text"><strong>' . esc_html( $title ) . '</strong><span>' . esc_html( $text ) . '</span></span></a></li>';
		};
		$dash = 'yes' === $a['cards']
			? '<div class="kk-acc-dash"><div class="kk-acc-hello"><span class="kk-acc-avatar">R</span><div class="kk-acc-hello__text"><h2>' . esc_html__( 'Hello, Rahima', 'kenakata-kit' ) . '</h2><p>' . esc_html__( 'Welcome back! Your orders, addresses and account details are all here.', 'kenakata-kit' ) . '</p></div><a class="kk-acc-logout" href="#">' . esc_html__( 'Log out', 'kenakata-kit' ) . '</a></div>'
				. '<div class="kk-acc-last"><div class="kk-acc-last__head"><span class="kk-acc-last__label">' . esc_html__( 'Latest order', 'kenakata-kit' ) . '</span><span class="kk-acc-pill kk-acc-pill--info">' . esc_html__( 'Processing', 'kenakata-kit' ) . '</span></div><div class="kk-acc-last__body"><strong>#1234</strong><span>October 6, 2026</span><span>1,578.00৳</span></div><div class="kk-acc-last__actions"><a class="button" href="#">' . esc_html__( 'View order', 'kenakata-kit' ) . '</a><a class="button kk-acc-btn-ghost" href="#">' . esc_html__( 'Track order', 'kenakata-kit' ) . '</a></div></div>'
				. '<ul class="kk-acc-cards">' . $card( 'box', __( 'My orders', 'kenakata-kit' ), __( '3 orders', 'kenakata-kit' ) ) . $card( 'truck', __( 'Track an order', 'kenakata-kit' ), __( 'See where your parcel is', 'kenakata-kit' ) ) . $card( 'location', __( 'Addresses', 'kenakata-kit' ), __( 'Delivery address for checkout', 'kenakata-kit' ) ) . $card( 'user', __( 'Account details', 'kenakata-kit' ), __( 'Name, email and password', 'kenakata-kit' ) ) . $card( 'bag', __( 'Continue shopping', 'kenakata-kit' ), __( 'Find your next favourite', 'kenakata-kit' ) ) . $card( 'whatsapp', __( 'Need help?', 'kenakata-kit' ), __( 'Chat with us on WhatsApp', 'kenakata-kit' ) ) . '</ul></div>'
			: '<p>' . esc_html__( 'Hello Rahima (not Rahima? Log out)', 'kenakata-kit' ) . '</p>';
		return '<div class="kk-account is-member kk-account--menu-' . $menu . $icons . '"><div class="woocommerce"><nav class="woocommerce-MyAccount-navigation"><ul>' . $nav . '</ul></nav><div class="woocommerce-MyAccount-content">' . $dash . '</div></div></div>';
	}
}

/* ---------------------------------------------------------------- Wishlist icon / page */
class KK_EW_Wishlist_Icon extends KK_EW_Base {
	public function get_name() { return 'kk-wishlist-icon'; }
	public function get_title() { return __( 'Wishlist Icon', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-heart-o'; }
	public function get_keywords() { return array( 'wishlist', 'favourite', 'heart', 'kenakata' ); }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Wishlist Icon', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Wishlist Icon', 'kenakata-kit' ), __( 'A ♡ link to the wishlist page with the number of saved products. Put it in the header next to the cart.', 'kenakata-kit' ) );
		$this->add_control( 'text', array( 'label' => __( 'Text next to the icon (optional)', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => '' ) );
		$this->add_align_control( '.kk-wl-link-wrap' );
		$this->end_controls_section();
		$this->start_controls_section( 'icon_style', array( 'label' => __( 'Icon', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'icon_size', array( 'label' => __( 'Icon size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 14, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-wl-link svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'icon_color', array( 'label' => __( 'Colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-wl-link' => 'color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'icon_color_h', array( 'label' => __( 'Hover colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-wl-link:hover' => 'color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'count_bg', array( 'label' => __( 'Count background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'separator' => 'before', 'selectors' => array( '{{WRAPPER}} .kk-wl-count' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'count_color', array( 'label' => __( 'Count text', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-wl-count' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_text_style( 'wl_text', __( 'Text', 'kenakata-kit' ), '.kk-wl-link__text' );
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		if ( ! function_exists( 'kk_wl_link' ) || ! kk_opt( 'wl_enable' ) ) {
			$this->editor_note( __( 'Wishlist is off: turn it on in Kenakata Kit → Wishlist.', 'kenakata-kit' ) );
			return;
		}
		echo '<div class="kk-wl-link-wrap" style="display:flex">' . kk_wl_link( array( 'text' => $s['text'] ?? '' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
}

class KK_EW_Wishlist_Page extends KK_EW_Base {
	public function get_name() { return 'kk-wishlist-page'; }
	public function get_title() { return __( 'Wishlist Page', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-heart'; }
	public function get_keywords() { return array( 'wishlist', 'favourite', 'saved', 'kenakata' ); }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Wishlist Page', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Wishlist Page', 'kenakata-kit' ), __( 'Shows the products the visitor saved with ♡, as product cards. Use it on the page with the slug "wishlist" (or choose the page in Kenakata Kit → Wishlist). The cards look like your other product lists.', 'kenakata-kit' ) );
		$this->add_responsive_control( 'columns', array( 'label' => __( 'Products per row', 'kenakata-kit' ), 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 6, 'default' => 4, 'tablet_default' => 3, 'mobile_default' => 2 ) );
		$this->end_controls_section();
		$this->add_text_style( 'count', __( 'Count text ("3 saved products")', 'kenakata-kit' ), '.kk-wl-page__count' );
		$this->add_box_style( 'empty', __( 'Empty-list box', 'kenakata-kit' ), '.kk-wl-page__empty' );
	}
	protected function render() {
		$s = $this->get_settings_for_display();
		if ( ! function_exists( 'kk_wl_page' ) || ! kk_opt( 'wl_enable' ) ) {
			$this->editor_note( __( 'Wishlist is off: turn it on in Kenakata Kit → Wishlist.', 'kenakata-kit' ) );
			return;
		}
		if ( kk_is_editor() ) {
			echo '<p class="kk-ew-note">' . esc_html__( 'The visitor\'s saved products appear here as product cards on the live page.', 'kenakata-kit' ) . '</p>';
			return;
		}
		echo kk_wl_page( array( 'columns' => $s['columns'] ?? '', 'columns_tablet' => $s['columns_tablet'] ?? '', 'columns_mobile' => $s['columns_mobile'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
}

/* ---------------------------------------------------------------- Order tracking / Thank you */
abstract class KK_EW_Box_Widget extends KK_EW_Base {
	protected function register_box_controls() {
		$this->add_box_style( 'box', __( 'Box', 'kenakata-kit' ), '.kk-box' );
		$this->add_text_style( 'heading', __( 'Title', 'kenakata-kit' ), '.kk-box h2, .kk-box h3' );
		$this->add_text_style( 'body', __( 'Text', 'kenakata-kit' ), '.kk-box p, .kk-box li, .kk-box dd, .kk-box label' );
		$this->add_button_style( '.kk-btn', 'btn', __( 'Button', 'kenakata-kit' ), array(), true );
	}
}

class KK_EW_Order_Tracking extends KK_EW_Box_Widget {
	public function get_name() { return 'kk-order-tracking'; }
	public function get_title() { return __( 'Order Tracking', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-map-pin'; }
	public function get_keywords() { return array( 'track', 'tracking', 'order', 'courier', 'kenakata', 'ট্র্যাকিং' ); }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Order Tracking', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Order Tracking', 'kenakata-kit' ), __( 'Customers see order status and the courier link by phone number. Change the texts in Kenakata Kit → Order tracking & courier.', 'kenakata-kit' ) );
		$this->end_controls_section();
		$this->register_box_controls();
		$this->start_controls_section( 'tr_input_style', array( 'label' => __( 'Phone number field', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'tr_input_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-track-form input' ) );
		$this->add_control( 'tr_input_color', array( 'label' => __( 'Text colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-track-form input' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'tr_input_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-track-form input' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'tr_input_border', array( 'label' => __( 'Border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-track-form input' => 'border-color: {{VALUE}};' ) ) );
		$this->add_control( 'tr_input_focus', array( 'label' => __( 'Border colour when typing', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-track-form input:focus' => 'border-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'tr_input_h', array( 'label' => __( 'Height', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 32, 'max' => 72 ) ), 'selectors' => array( '{{WRAPPER}} .kk-track-form input, {{WRAPPER}} .kk-track-form .kk-btn' => 'min-height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'tr_input_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-track-form input' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->start_controls_section( 'tr_status_style', array( 'label' => __( 'Order status and steps', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'tr_status_color', array( 'label' => __( 'Status label text', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-status' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'tr_status_bg', array( 'label' => __( 'Status label background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-status' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'tr_step_color', array( 'label' => __( 'Step text', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'separator' => 'before', 'selectors' => array( '{{WRAPPER}} .kk-step' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'tr_step_done', array( 'label' => __( 'Completed / current step colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-steps' => '--kk-primary: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'tr_step_typo', 'label' => __( 'Step typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-step' ) );
		$this->end_controls_section();
	}
	protected function render() { echo kk_tracking_shortcode(); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
}

class KK_EW_Thank_You extends KK_EW_Box_Widget {
	public function get_name() { return 'kk-thank-you'; }
	public function get_title() { return __( 'Order Confirmation', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-check-circle'; }
	public function get_keywords() { return array( 'thank you', 'order received', 'confirmation', 'kenakata', 'থ্যাংক' ); }
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Order Confirmation', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Order Confirmation', 'kenakata-kit' ), __( 'Use on the thank-you page. Shows order number, products and address after ordering. Change the texts in Kenakata Kit → Thank-you page.', 'kenakata-kit' ) );
		$this->end_controls_section();
		$this->register_box_controls();
		$this->start_controls_section( 'ty_icon_style', array( 'label' => __( 'Tick icon', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'ty_icon_color', array( 'label' => __( 'Colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-thanks__icon' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'ty_icon_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-thanks__icon' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'ty_icon_size', array( 'label' => __( 'Size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 32, 'max' => 120 ) ), 'selectors' => array( '{{WRAPPER}} .kk-thanks__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: calc({{SIZE}}{{UNIT}} / 2);' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'ty_hero', __( 'Top box (thank-you message)', 'kenakata-kit' ), '.kk-thanks__hero' );
		$this->add_text_style( 'ty_meta', __( 'Order number, date and total', 'kenakata-kit' ), '.kk-thanks__meta, .kk-thanks__meta dt, .kk-thanks__meta dd' );
		$this->add_text_style( 'ty_lines', __( 'Products list', 'kenakata-kit' ), '.kk-line__name, .kk-line__total' );
		$this->add_text_style( 'ty_totals', __( 'Totals', 'kenakata-kit' ), '.kk-total-row' );
		$this->add_button_style( '.kk-thanks__actions .kk-btn--outline', 'ty_btn2', __( 'Second button (Continue shopping)', 'kenakata-kit' ), array(), true );
	}
	protected function render() { echo kk_thank_you_shortcode(); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
}
