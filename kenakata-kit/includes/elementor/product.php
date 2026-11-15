<?php
/**
 * Product page widgets: "Add to Cart Form" (variation buttons, quantity, Add to cart + Buy Now, WhatsApp/call,
 * delivery info) and "Product Reviews" (rating summary, photos, review cards, review form).
 * On a single product template they use the product of the page; elsewhere a product ID can be chosen.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;

abstract class KK_EW_Product_Base extends KK_EW_Base {

	protected function add_product_controls() {
		$this->add_control( 'source', array( 'label' => __( 'Product', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'current', 'options' => array( 'current' => __( 'Product of this page', 'kenakata-kit' ), 'id' => __( 'By product ID', 'kenakata-kit' ) ) ) );
		$this->add_control( 'product_id', array( 'label' => __( 'Product ID', 'kenakata-kit' ), 'type' => Controls_Manager::NUMBER, 'condition' => array( 'source' => 'id' ) ) );
	}

	/** The product to show: chosen ID, the product of the page, or (in the editor) a sample product. */
	protected function get_product( array $s ) {
		if ( 'id' === ( $s['source'] ?? 'current' ) ) {
			$p = wc_get_product( absint( $s['product_id'] ?? 0 ) );
			return $p ? $p : null;
		}
		global $product;
		if ( $product instanceof WC_Product ) {
			return $product;
		}
		if ( is_singular( 'product' ) ) {
			return wc_get_product( get_the_ID() );
		}
		if ( kk_is_editor() ) {
			$ids = wc_get_products( array( 'limit' => 1, 'status' => 'publish', 'type' => array( 'variable' ), 'return' => 'ids' ) );
			if ( ! $ids ) {
				$ids = wc_get_products( array( 'limit' => 1, 'status' => 'publish', 'return' => 'ids' ) );
			}
			return $ids ? wc_get_product( $ids[0] ) : null;
		}
		return null;
	}

	/** Make $product / $post point at the product while WooCommerce templates run. Returns the old values. */
	protected function use_product( WC_Product $p ) {
		global $product, $post;
		$old     = array( $product, $post );
		$post    = get_post( $p->get_id() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$product = $p; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		return $old;
	}

	protected function restore_product( array $old ) {
		global $product, $post;
		$product = $old[0]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post    = $old[1]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $post ) {
			setup_postdata( $post );
		} else {
			wp_reset_postdata();
		}
	}

	/** Normal / hover / active colour tabs for a clickable element. */
	protected function add_state_colors( $prefix, $selector, array $states ) {
		$this->start_controls_tabs( $prefix . '_tabs' );
		foreach ( $states as $state => $label ) {
			$sel = $this->w( $selector, 'normal' === $state ? '' : $state );
			$key = $prefix . ( 'normal' === $state ? '' : '_' . str_replace( '-', '_', sanitize_key( $state ) ) );
			$this->start_controls_tab( $key . '_tab', array( 'label' => $label ) );
			$this->add_control( $key . '_color', array( 'label' => __( 'Text colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'color: {{VALUE}};' ) ) );
			$this->add_control( $key . '_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( $key . '_bc', array( 'label' => __( 'Border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'border-color: {{VALUE}};' ) ) );
			$this->end_controls_tab();
		}
		$this->end_controls_tabs();
	}
}

/* ---------------------------------------------------------------- Product Brand (logo + name) */
class KK_EW_Product_Brand extends KK_EW_Product_Base {
	public function get_name() { return 'kk-product-brand'; }
	public function get_title() { return __( 'Product Brand', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-logo'; }
	public function get_keywords() { return array( 'brand', 'logo', 'product', 'kenakata', 'ব্র্যান্ড' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Product Brand', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Product Brand', 'kenakata-kit' ), __( 'Shows the brand of the product with its small logo, e.g. "Brand: [logo] COSRX". Logos come from Products → Brands → Thumbnail; brands without one show their first letter.', 'kenakata-kit' ) );
		$this->add_product_controls();
		$this->add_control( 'label', array( 'label' => __( 'Text before', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Brand:', 'kenakata-kit' ), 'separator' => 'before' ) );
		$this->add_control( 'logo', array( 'label' => __( 'Show logo', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'fallback', array( 'label' => __( 'When a brand has no logo', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'letter', 'options' => array( 'letter' => __( 'Show its first letter', 'kenakata-kit' ), 'none' => __( 'Show only the name', 'kenakata-kit' ) ), 'condition' => array( 'logo' => 'yes' ) ) );
		$this->add_control( 'name', array( 'label' => __( 'Show name', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'link', array( 'label' => __( 'Link to the brand page', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_align_control( '.kk-brand' );
		$this->end_controls_section();

		$this->add_text_style( 'label', __( 'Text before', 'kenakata-kit' ), '.kk-brand__label' );
		$this->add_text_style( 'bname', __( 'Brand name', 'kenakata-kit' ), '.kk-brand__name', '.kk-brand__item:hover .kk-brand__name' );

		$this->start_controls_section( 'logo_style', array( 'label' => __( 'Logo', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'logo' => 'yes' ) ) );
		$this->add_responsive_control( 'logo_size', array( 'label' => __( 'Size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 16, 'max' => 120 ) ), 'selectors' => array( '{{WRAPPER}} .kk-brand' => '--kk-brand-logo: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'logo_shape', array( 'label' => __( 'Shape', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'Rounded square', 'kenakata-kit' ), '50%' => __( 'Circle', 'kenakata-kit' ), '0' => __( 'Square', 'kenakata-kit' ) ), 'selectors' => array( '{{WRAPPER}} .kk-brand__logo' => 'border-radius: {{VALUE}};' ) ) );
		$this->add_control( 'logo_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-brand__logo' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'logo_border', array( 'label' => __( 'Border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-brand__logo' => 'border-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'logo_pad', array( 'label' => __( 'Space inside', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 12 ) ), 'selectors' => array( '{{WRAPPER}} .kk-brand__logo' => 'padding: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'letter_color', array( 'label' => __( 'Letter colour (no logo)', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-brand__logo--letter' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => __( 'Space between parts', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 24 ) ), 'separator' => 'before', 'selectors' => array( '{{WRAPPER}} .kk-brand' => '--kk-brand-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$p = $this->get_product( $s );
		$html = kk_product_brand_html( $p, array(
			'label'    => $s['label'] ?? '',
			'logo'     => 'yes' === ( $s['logo'] ?? '' ) ? 'yes' : 'no',
			'name'     => 'yes' === ( $s['name'] ?? '' ) ? 'yes' : 'no',
			'link'     => 'yes' === ( $s['link'] ?? '' ) ? 'yes' : 'no',
			'fallback' => $s['fallback'] ?? 'letter',
		) );
		if ( '' === $html && kk_is_editor() ) {
			$this->editor_note( __( 'This product has no brand. Choose one in the product edit screen (Brands box).', 'kenakata-kit' ) );
			return;
		}
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
}

/* ---------------------------------------------------------------- Add to Cart Form */
class KK_EW_Add_To_Cart extends KK_EW_Product_Base {
	public function get_name() { return 'kk-add-to-cart'; }
	public function get_title() { return __( 'Add to Cart Form', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-product-add-to-cart'; }
	public function get_keywords() { return array( 'add to cart', 'buy now', 'variation', 'swatch', 'size', 'quantity', 'product', 'kenakata' ); }
	public function get_script_depends() { return array( 'kenakata-kit', 'wc-add-to-cart-variation' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Add to Cart Form', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Add to Cart Form', 'kenakata-kit' ), __( 'For the product page template: variation options as buttons, quantity, Add to cart and Buy Now – plus the WhatsApp/call buttons and delivery box from the settings. Use it instead of the theme\'s "Add to Cart" widget.', 'kenakata-kit' ) );
		$this->add_product_controls();
		$this->add_control( 'swatches', array( 'label' => __( 'Variation options', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'From settings', 'kenakata-kit' ), 'buttons' => __( 'Buttons', 'kenakata-kit' ), 'dropdown' => __( 'Dropdown', 'kenakata-kit' ) ) ) );
		$this->add_control( 'layout', array( 'label' => __( 'Buttons', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'inline', 'options' => array( 'inline' => __( 'Quantity and buttons in one row', 'kenakata-kit' ), 'stack' => __( 'Full-width buttons, one per row', 'kenakata-kit' ) ) ) );
		$this->add_control( 'mobile_layout', array( 'label' => __( 'Buttons on mobile', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'side', 'options' => array( 'side' => __( 'Add to cart + Buy Now side by side', 'kenakata-kit' ), 'stack' => __( 'One per row', 'kenakata-kit' ) ), 'condition' => array( 'layout' => 'inline' ) ) );
		$this->add_control( 'show_qty', array( 'label' => __( 'Quantity box', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'show_buy', array( 'label' => __( 'Buy Now button', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'description' => __( 'Needs "Show on product pages" in Kenakata Kit → Buy Now Button.', 'kenakata-kit' ) ) );
		$this->add_control( 'show_contact', array( 'label' => __( 'WhatsApp / call buttons', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'description' => __( 'Shown when switched on in Kenakata Kit → Product page.', 'kenakata-kit' ) ) );
		$this->add_control( 'show_delivery', array( 'label' => __( 'Delivery information box', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => '' ) );
		$this->add_control( 'show_wishlist', array(
			'label'       => __( '"Add to wishlist" button', 'kenakata-kit' ),
			'type'        => Controls_Manager::SELECT,
			'default'     => '',
			'options'     => array( '' => __( 'From settings', 'kenakata-kit' ), 'yes' => __( 'Show', 'kenakata-kit' ), 'no' => __( 'Hide', 'kenakata-kit' ) ),
			'description' => __( 'Hide it here and add the "Wishlist Button" widget anywhere else on the page to place it yourself.', 'kenakata-kit' ),
		) );
		$this->end_controls_section();

		// Variation options.
		$this->start_controls_section( 'var_label_style', array( 'label' => __( 'Variation name (Size, Colour…)', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'var_label_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} table.variations th.label label, {{WRAPPER}} table.variations td.label label' ) );
		$this->add_control( 'var_label_color', array( 'label' => __( 'Colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} table.variations th.label label, {{WRAPPER}} table.variations td.label label' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'var_current_color', array( 'label' => __( 'Chosen value colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-swatch-current' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'var_row_gap', array( 'label' => __( 'Space between options rows', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} form.kk-sw-form table.variations tr' => 'margin-bottom: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'swatch_style', array( 'label' => __( 'Option buttons', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'swatches!' => 'dropdown' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'swatch_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-swatches .kk-swatch' ) );
		$this->add_responsive_control( 'swatch_h', array( 'label' => __( 'Height', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 24, 'max' => 72 ) ), 'selectors' => array( '{{WRAPPER}} .kk-swatches .kk-swatch' => 'min-height: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'swatch_pad', array( 'label' => __( 'Padding', 'kenakata-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'selectors' => array( '{{WRAPPER}} .kk-swatches .kk-swatch' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'swatch_gap', array( 'label' => __( 'Gap', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 24 ) ), 'selectors' => array( '{{WRAPPER}} .kk-swatches' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'swatch_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-swatches .kk-swatch' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'swatch_bw', array( 'label' => __( 'Border width', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 4, 'step' => 0.5 ) ), 'selectors' => array( '{{WRAPPER}} .kk-swatches .kk-swatch' => 'border-width: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_state_colors( 'swatch', '.kk-swatches .kk-swatch', array( 'normal' => __( 'Normal', 'kenakata-kit' ), ':hover' => __( 'Hover', 'kenakata-kit' ), '.is-selected' => __( 'Chosen', 'kenakata-kit' ) ) );
		$this->add_control( 'swatch_off_opacity', array( 'label' => __( 'Unavailable options: opacity', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'separator' => 'before', 'range' => array( 'px' => array( 'min' => 0.1, 'max' => 1, 'step' => 0.05 ) ), 'selectors' => array( '{{WRAPPER}} .kk-swatches .kk-swatch.is-disabled' => 'opacity: {{SIZE}};' ) ) );
		$this->add_control( 'clear_color', array( 'label' => __( '"Clear" link colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .reset_variations' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'vprice_style', array( 'label' => __( 'Price and stock of the chosen option', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'vprice_typo', 'label' => __( 'Price', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .woocommerce-variation-price .price' ) );
		$this->add_control( 'vprice_color', array( 'label' => __( 'Price colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .woocommerce-variation-price .price, {{WRAPPER}} .woocommerce-variation-price .price ins' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'stock_in_color', array( 'label' => __( '"In stock" colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .stock.in-stock' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'stock_out_color', array( 'label' => __( '"Out of stock" colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .stock.out-of-stock' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'qty_style', array( 'label' => __( 'Quantity box', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'show_qty' => 'yes' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'qty_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-atc .quantity .qty' ) );
		$this->add_responsive_control( 'qty_w', array( 'label' => __( 'Width', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 40, 'max' => 160 ) ), 'selectors' => array( '{{WRAPPER}} .kk-atc' => '--kk-atc-qty-w: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'qty_h', array( 'label' => __( 'Height', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 30, 'max' => 72 ) ), 'selectors' => array( '{{WRAPPER}} .kk-atc .quantity .qty' => 'min-height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'qty_color', array( 'label' => __( 'Text colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-atc .quantity .qty' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'qty_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-atc .quantity .qty' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'qty_bc', array( 'label' => __( 'Border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-atc .quantity .qty' => 'border-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'qty_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-atc .quantity .qty' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'atc_layout_style', array( 'label' => __( 'Buttons: spacing', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'atc_gap', array( 'label' => __( 'Gap between quantity and buttons', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ), 'selectors' => array( '{{WRAPPER}} .kk-atc' => '--kk-atc-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->add_button_style( 'button.single_add_to_cart_button.button', 'atc', __( 'Add to cart button', 'kenakata-kit' ), array(), true );
		$this->add_button_style( 'button.kk-buy-now--single.button', 'buy', __( 'Buy Now Button', 'kenakata-kit' ), array( 'show_buy' => 'yes' ), true );
		$this->add_button_style( '.kk-pp-contact .kk-contact-btn--whatsapp', 'wa', __( 'WhatsApp button', 'kenakata-kit' ), array( 'show_contact' => 'yes' ), true );
		$this->add_button_style( '.kk-pp-contact .kk-contact-btn--call', 'call', __( 'Call button', 'kenakata-kit' ), array( 'show_contact' => 'yes' ), true );
		$this->add_button_style( '.kk-wl-btn--single', 'wl', __( '"Add to wishlist" button', 'kenakata-kit' ), array( 'show_wishlist!' => 'no' ), true );
		$this->start_controls_section( 'wl_saved_style', array( 'label' => __( '"Add to wishlist": saved state & icon', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'show_wishlist!' => 'no' ) ) );
		$this->add_control( 'wl_on_color', array( 'label' => __( 'Text colour when saved', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--single.is-on' => 'color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'wl_on_bg', array( 'label' => __( 'Background when saved', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--single.is-on' => 'background-color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'wl_on_border', array( 'label' => __( 'Border colour when saved', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--single.is-on' => 'border-color: {{VALUE}} !important;' ) ) );
		$this->add_responsive_control( 'wl_icon_size', array( 'label' => __( 'Icon size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'separator' => 'before', 'range' => array( 'px' => array( 'min' => 12, 'max' => 36 ) ), 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--single .kk-wl-btn__ico' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'wl_width', array( 'label' => __( 'Width', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'size_units' => array( '%', 'px' ), 'range' => array( '%' => array( 'min' => 10, 'max' => 100 ), 'px' => array( 'min' => 80, 'max' => 600 ) ), 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--single' => 'width: {{SIZE}}{{UNIT}} !important;' ) ) );
		$this->add_responsive_control( 'wl_space', array( 'label' => __( 'Space above', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--single' => 'margin-top: {{SIZE}}{{UNIT}} !important;' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$p = $this->get_product( $s );
		if ( ! $p ) {
			$this->editor_note( __( 'Add to Cart Form: place it on a product page template, or choose a product ID.', 'kenakata-kit' ) );
			return;
		}
		$old  = $this->use_product( $p );
		$mode = $s['swatches'] ?? '';
		$had  = (bool) has_filter( 'woocommerce_dropdown_variation_attribute_options_html', 'kk_swatches_html' );
		if ( 'buttons' === $mode && ! $had ) {
			add_filter( 'woocommerce_dropdown_variation_attribute_options_html', 'kk_swatches_html', 20, 2 );
		} elseif ( 'dropdown' === $mode && $had ) {
			remove_filter( 'woocommerce_dropdown_variation_attribute_options_html', 'kk_swatches_html', 20 );
		}
		$cls = array( 'kk-atc', 'kk-atc--' . ( 'stack' === ( $s['layout'] ?? '' ) ? 'stack' : 'inline' ) );
		if ( 'stack' === ( $s['mobile_layout'] ?? '' ) ) {
			$cls[] = 'kk-atc--stack-mobile';
		}
		if ( 'yes' !== ( $s['show_qty'] ?? '' ) ) {
			$cls[] = 'kk-atc--no-qty';
		}
		if ( 'yes' !== ( $s['show_buy'] ?? '' ) ) {
			$cls[] = 'kk-atc--no-buy';
		}
		echo '<div class="' . esc_attr( implode( ' ', $cls ) ) . '">';
		$GLOBALS['kk_wl_single_force'] = in_array( $s['show_wishlist'] ?? '', array( 'yes', 'no' ), true ) ? $s['show_wishlist'] : '';
		woocommerce_template_single_add_to_cart();
		$GLOBALS['kk_wl_single_force'] = '';
		if ( 'yes' === ( $s['show_contact'] ?? '' ) ) {
			echo kk_pp_contact_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
		}
		if ( 'yes' === ( $s['show_delivery'] ?? '' ) ) {
			echo kk_delivery_info(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
		}
		echo '</div>';
		if ( 'buttons' === $mode && ! $had ) {
			remove_filter( 'woocommerce_dropdown_variation_attribute_options_html', 'kk_swatches_html', 20 );
		} elseif ( 'dropdown' === $mode && $had ) {
			add_filter( 'woocommerce_dropdown_variation_attribute_options_html', 'kk_swatches_html', 20, 2 );
		}
		$this->restore_product( $old );
	}
}

/* ---------------------------------------------------------------- Product Reviews */
class KK_EW_Product_Reviews extends KK_EW_Product_Base {
	public function get_name() { return 'kk-product-reviews'; }
	public function get_title() { return __( 'Product Reviews', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-review'; }
	public function get_keywords() { return array( 'reviews', 'rating', 'stars', 'comments', 'photos', 'product', 'kenakata' ); }
	public function get_script_depends() { return array( 'kenakata-kit', 'wc-single-product' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Product Reviews', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Product Reviews', 'kenakata-kit' ), __( 'Rating summary, customer photos, review cards and the review form. To avoid showing reviews twice, turn off "Show reviews in the product tabs" in Kenakata Kit → Reviews. Texts and photo rules are set there too.', 'kenakata-kit' ) );
		$this->add_product_controls();
		$this->add_control( 'show_summary', array( 'label' => __( 'Rating summary', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'show_gallery', array( 'label' => __( 'Customer photos strip', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'show_form', array( 'label' => __( 'Review form', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_responsive_control( 'section_gap', array( 'label' => __( 'Space between parts', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'selectors' => array( '{{WRAPPER}} #reviews.kk-reviews' => '--kk-rv-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$rv = '#reviews.kk-reviews ';
		$this->add_text_style( 'rv_title', __( 'Title', 'kenakata-kit' ), $rv . '.kk-reviews__title' );
		$this->add_box_style( 'summary', __( 'Rating summary', 'kenakata-kit' ), $rv . '.kk-reviews__summary' );
		$this->start_controls_section( 'score_style', array( 'label' => __( 'Average, stars and bars', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'avg_typo', 'label' => __( 'Average number', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} ' . $rv . '.kk-reviews__avg' ) );
		$this->add_control( 'avg_color', array( 'label' => __( 'Average number colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $rv . '.kk-reviews__avg' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'star_color', array( 'label' => __( 'Star colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} #reviews.kk-reviews' => '--kk-star-c: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'star_size', array( 'label' => __( 'Summary star size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 12, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} ' . $rv . '.kk-reviews__stars' => 'font-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'bar_bg', array( 'label' => __( 'Bar background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $rv . '.kk-reviews__bar' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'bar_h', array( 'label' => __( 'Bar height', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 4, 'max' => 20 ) ), 'selectors' => array( '{{WRAPPER}} ' . $rv . '.kk-reviews__bar' => 'height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'bars_typo', 'label' => __( 'Bar labels', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} ' . $rv . '.kk-reviews__bar-row' ) );
		$this->end_controls_section();
		$this->add_button_style( '.kk-reviews__write', 'write', __( '"Write a review" button', 'kenakata-kit' ) );

		$card = '#reviews.kk-reviews #comments ol.commentlist li .comment_container';
		$this->add_box_style( 'card', __( 'Review card', 'kenakata-kit' ), $card );
		$this->start_controls_section( 'card_text_style', array( 'label' => __( 'Review text', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'author_typo', 'label' => __( 'Name', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} ' . $rv . '.woocommerce-review__author' ) );
		$this->add_control( 'author_color', array( 'label' => __( 'Name colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $rv . '.woocommerce-review__author' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'date_color', array( 'label' => __( 'Date colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $rv . '.woocommerce-review__published-date' => 'color: {{VALUE}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'text_typo', 'label' => __( 'Review', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} ' . $card . ' .description p' ) );
		$this->add_control( 'text_color', array( 'label' => __( 'Review colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $card . ' .description p' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'verified_color', array( 'label' => __( '"Verified owner": text', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $rv . '.woocommerce-review__verified' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'verified_bg', array( 'label' => __( '"Verified owner": background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} ' . $rv . '.woocommerce-review__verified' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'card_star_size', array( 'label' => __( 'Star size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 10, 'max' => 28 ) ), 'selectors' => array( '{{WRAPPER}} ' . $rv . '.kk-review__rating' => 'font-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'photo_size', array( 'label' => __( 'Photo size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'separator' => 'before', 'range' => array( 'px' => array( 'min' => 40, 'max' => 160 ) ), 'selectors' => array( '{{WRAPPER}} .kk-review-photo' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'photo_radius', array( 'label' => __( 'Photo corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-review-photo' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->add_box_style( 'form', __( 'Review form', 'kenakata-kit' ), '.kk-reviews__form' );
		$this->start_controls_section( 'fields_style', array( 'label' => __( 'Form fields', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$fields = '.kk-reviews__form input[type=text], .kk-reviews__form input[type=email], .kk-reviews__form textarea';
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'label_typo', 'label' => __( 'Labels', 'kenakata-kit' ), 'selector' => $this->w( '.kk-reviews__form label' ) ) );
		$this->add_control( 'field_bg', array( 'label' => __( 'Field background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $this->w( $fields ) => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'field_bc', array( 'label' => __( 'Field border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $this->w( $fields ) => 'border-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'field_radius', array( 'label' => __( 'Field corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ), 'selectors' => array( $this->w( $fields ) => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->add_button_style( '.kk-reviews__form .form-submit input[type=submit]', 'submit', __( 'Submit button', 'kenakata-kit' ), array(), true );
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		$p = $this->get_product( $s );
		if ( ! $p ) {
			$this->editor_note( __( 'Product Reviews: place it on a product page template, or choose a product ID.', 'kenakata-kit' ) );
			return;
		}
		if ( ! comments_open( $p->get_id() ) ) {
			$this->editor_note( __( 'Reviews are closed for this product (Product data → Advanced → Enable reviews).', 'kenakata-kit' ) );
			return;
		}
		$old = $this->use_product( $p );
		$GLOBALS['withcomments'] = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- lets comments_template() run inside a widget
		$cls = 'kk-rv-widget';
		foreach ( array( 'show_summary' => 'summary', 'show_gallery' => 'gallery', 'show_form' => 'form' ) as $k => $part ) {
			if ( 'yes' !== ( $s[ $k ] ?? '' ) ) {
				$cls .= ' kk-rv-no-' . $part;
			}
		}
		echo '<div class="' . esc_attr( $cls ) . '">';
		comments_template();
		echo '</div>';
		$this->restore_product( $old );
	}
}

/* ---------------------------------------------------------------- Wishlist Button */
class KK_EW_Wishlist_Button extends KK_EW_Product_Base {
	public function get_name() { return 'kk-wishlist-button'; }
	public function get_title() { return __( 'Wishlist Button', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-heart-o'; }
	public function get_keywords() { return array( 'wishlist', 'favourite', 'heart', 'save', 'product', 'kenakata' ); }

	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Wishlist Button', 'kenakata-kit' ) ) );
		$this->help_box( __( 'Wishlist Button', 'kenakata-kit' ), __( '"Add to wishlist" for one product, anywhere on the page. On a product page template it uses the product of the page. Tip: hide the button in the "Add to Cart Form" widget so it does not show twice.', 'kenakata-kit' ) );
		$this->add_product_controls();
		$this->add_control( 'add_text', array( 'label' => __( 'Text', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'placeholder' => kk_opt( 'wl_text_add' ), 'description' => __( 'Empty = text from Kenakata Kit → Wishlist.', 'kenakata-kit' ) ) );
		$this->add_control( 'added_text', array( 'label' => __( 'Text when saved', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'placeholder' => kk_opt( 'wl_text_added' ) ) );
		$this->add_control( 'show_text', array( 'label' => __( 'Show text', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'description' => __( 'Off = heart icon only.', 'kenakata-kit' ) ) );
		$this->add_control( 'show_icon', array( 'label' => __( 'Show heart icon', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'icon_pos', array( 'label' => __( 'Icon position', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'row', 'options' => array( 'row' => __( 'Before text', 'kenakata-kit' ), 'row-reverse' => __( 'After text', 'kenakata-kit' ) ), 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--widget' => 'flex-direction: {{VALUE}};' ), 'condition' => array( 'show_icon' => 'yes', 'show_text' => 'yes' ) ) );
		$this->add_control( 'full_width', array( 'label' => __( 'Full width', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--widget' => 'width: 100%;' ) ) );
		$this->add_align_control( '.kk-wlb-wrap' );
		$this->end_controls_section();

		$this->add_button_style( '.kk-wl-btn--widget', 'wlb', __( 'Button', 'kenakata-kit' ) );

		$this->start_controls_section( 'wlb_saved_style', array( 'label' => __( 'Saved state', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'wlb_on_color', array( 'label' => __( 'Text and icon colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--widget.is-on' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'wlb_on_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--widget.is-on' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'wlb_on_border', array( 'label' => __( 'Border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--widget.is-on' => 'border-color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'wlb_icon_style', array( 'label' => __( 'Icon', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'show_icon' => 'yes' ) ) );
		$this->add_responsive_control( 'wlb_icon_size', array( 'label' => __( 'Size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 12, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--widget .kk-wl-btn__ico' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'wlb_icon_color', array( 'label' => __( 'Colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--widget .kk-wl-btn__ico' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'wlb_icon_gap', array( 'label' => __( 'Space between icon and text', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 24 ) ), 'selectors' => array( '{{WRAPPER}} .kk-wl-btn--widget' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
	}

	protected function render() {
		$s = $this->get_settings_for_display();
		if ( ! function_exists( 'kk_wl_button' ) || ! kk_opt( 'wl_enable' ) ) {
			$this->editor_note( __( 'Wishlist is off: turn it on in Kenakata Kit → Wishlist.', 'kenakata-kit' ) );
			return;
		}
		$p = $this->get_product( $s );
		if ( ! $p ) {
			$this->editor_note( __( 'Wishlist Button: place it on a product page template, or choose a product ID.', 'kenakata-kit' ) );
			return;
		}
		echo '<div class="kk-wlb-wrap" style="display:flex">' . kk_wl_button( $p->get_id(), 'widget', array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
			'add'   => $s['add_text'] ?? '',
			'added' => $s['added_text'] ?? '',
			'icon'  => 'yes' === ( $s['show_icon'] ?? 'yes' ),
			'text'  => 'yes' === ( $s['show_text'] ?? 'yes' ),
		) ) . '</div>';
	}
}
