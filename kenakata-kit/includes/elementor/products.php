<?php
/**
 * "প্রোডাক্ট গ্রিড" and "প্রোডাক্ট ক্যারোসেল" widgets.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

class KK_EW_Products extends KK_EW_Base {
	protected $layout = 'grid';

	public function get_name() { return 'kk-products'; }
	public function get_title() { return __( 'Products Grid', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-products'; }
	public function get_keywords() { return array( 'products', 'product grid', 'shop', 'archive', 'woocommerce', 'kenakata', __( 'Product', 'kenakata-kit' ), 'গ্রিড' ); }

	protected function is_carousel() { return 'carousel' === $this->layout; }

	protected function register_controls() {
		$d = kk_card_defaults();

		/* ---------- Content: query ---------- */
		$this->start_controls_section( 'query', array( 'label' => __( 'Products to show', 'kenakata-kit' ) ) );
		$this->help_box(
			$this->is_carousel() ? __( 'Products Carousel', 'kenakata-kit' ) : __( 'Products Grid', 'kenakata-kit' ),
			$this->is_carousel()
				? __( 'A product slider for Featured / On sale / Best selling sections on the home page, or "Related products" on a product page.', 'kenakata-kit' )
				: __( 'On shop, category and brand templates choose "Products of this page" – page numbers and sorting work automatically. On the home page choose Featured, On sale, a category, etc.', 'kenakata-kit' )
		);
		$sources = array(
			'current'      => __( 'Products of this page (shop/category/brand/search)', 'kenakata-kit' ),
			'latest'       => __( 'Latest products', 'kenakata-kit' ),
			'featured'     => __( 'Featured products', 'kenakata-kit' ),
			'sale'         => __( 'On sale', 'kenakata-kit' ),
			'best_selling' => __( 'Best selling', 'kenakata-kit' ),
			'top_rated'    => __( 'Top rated', 'kenakata-kit' ),
			'category'     => __( 'Selected categories', 'kenakata-kit' ),
			'brand'        => __( 'Selected brands', 'kenakata-kit' ),
			'ids'          => __( 'Selected products (by ID)', 'kenakata-kit' ),
			'related'      => __( 'Related products (on a product page)', 'kenakata-kit' ),
			'recent'       => __( 'Recently viewed by the visitor', 'kenakata-kit' ),
		);
		if ( $this->is_carousel() ) {
			unset( $sources['current'] );
		}
		$this->add_control( 'source', array( 'label' => __( 'Source', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => $this->is_carousel() ? 'featured' : 'current', 'options' => $sources ) );
		$this->add_control( 'category', array( 'label' => __( 'Categories', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => $this->term_options( 'product_cat' ), 'condition' => array( 'source' => 'category' ) ) );
		$this->add_control( 'brand', array( 'label' => __( 'Brands', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => $this->term_options( kk_brand_taxonomy() ), 'condition' => array( 'source' => 'brand' ) ) );
		$this->add_control( 'ids', array( 'label' => __( 'Product ID', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'placeholder' => '12, 34, 56', 'description' => __( 'Hover a product name in Products to see its ID.', 'kenakata-kit' ), 'condition' => array( 'source' => 'ids' ) ) );
		$this->add_control( 'limit', array( 'label' => __( 'Number of products', 'kenakata-kit' ), 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 100, 'default' => 8, 'description' => __( 'With pagination this is the number per page.', 'kenakata-kit' ), 'condition' => array( 'source!' => 'current' ) ) );
		$this->add_control( 'recent_note', array( 'type' => Controls_Manager::RAW_HTML, 'raw' => '<div class="kk-ew-help">' . esc_html__( 'Shows the products this visitor opened most recently (newest first, the current product is left out). Nothing is shown to a first-time visitor, so place it lower on the page. The editor shows the newest products as a sample.', 'kenakata-kit' ) . '</div>', 'condition' => array( 'source' => 'recent' ) ) );
		$this->add_control( 'current_note', array( 'type' => Controls_Manager::RAW_HTML, 'raw' => '<div class="kk-ew-help">' . esc_html__( 'Set how many products show per page in Kenakata Kit → Product cards → "Products per page". The editor shows the newest products as a sample.', 'kenakata-kit' ) . '</div>', 'condition' => array( 'source' => 'current' ) ) );
		$this->add_control( 'orderby', array( 'label' => __( 'Order by', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'Default', 'kenakata-kit' ), 'date' => __( 'Newest first', 'kenakata-kit' ), 'price' => __( 'Price', 'kenakata-kit' ), 'popularity' => __( 'Popularity', 'kenakata-kit' ), 'rating' => __( 'Rating', 'kenakata-kit' ), 'title' => __( 'Name (A–Z)', 'kenakata-kit' ), 'rand' => __( 'Random', 'kenakata-kit' ), 'menu_order' => __( 'Custom order', 'kenakata-kit' ) ), 'condition' => array( 'source!' => 'current' ) ) );
		$this->add_control( 'order', array( 'label' => __( 'Sort order', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'DESC', 'options' => array( 'DESC' => __( 'High to low / newest first', 'kenakata-kit' ), 'ASC' => __( 'Low to high / oldest first', 'kenakata-kit' ) ), 'condition' => array( 'source!' => 'current', 'orderby' => array( '', 'date', 'price', 'title' ) ) ) );
		$this->add_control( 'hide_out_of_stock', array( 'label' => __( 'Out-of-stock products', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'Follow WooCommerce setting', 'kenakata-kit' ), 'yes' => __( 'Hide', 'kenakata-kit' ), 'no' => __( 'Show', 'kenakata-kit' ) ), 'condition' => array( 'source!' => 'current' ) ) );
		$this->add_control( 'exclude', array( 'label' => __( 'Exclude these IDs', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'placeholder' => '12, 34', 'condition' => array( 'source!' => 'current' ) ) );
		$this->end_controls_section();

		/* ---------- Content: layout ---------- */
		$this->start_controls_section( 'layout_sec', array( 'label' => __( 'Layout', 'kenakata-kit' ) ) );
		$this->add_responsive_control( 'columns', array(
			'label'          => $this->is_carousel() ? __( 'Slides visible at once', 'kenakata-kit' ) : __( 'Columns', 'kenakata-kit' ),
			'type'           => Controls_Manager::NUMBER,
			'min'            => 1,
			'max'            => 8,
			'default'        => 4,
			'tablet_default' => 3,
			'mobile_default' => 2,
		) );
		$this->add_responsive_control( 'gap', array( 'label' => __( 'Gap between cards', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .kk-products' => '--kk-gap: {{SIZE}}{{UNIT}};' ) ) );
		if ( ! $this->is_carousel() ) {
			$this->add_responsive_control( 'row_gap', array( 'label' => __( 'Gap between rows', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'selectors' => array( '{{WRAPPER}} .kk-products' => '--kk-row-gap: {{SIZE}}{{UNIT}};' ) ) );
			$this->add_control( 'paginate', array( 'type' => Controls_Manager::HIDDEN, 'default' => 'yes' ) ); // kept for widgets saved before 3.3
			$this->add_control( 'pagination', array(
				'label'       => __( 'Pagination', 'kenakata-kit' ),
				'type'        => Controls_Manager::SELECT,
				'default'     => '',
				'options'     => array(
					''         => __( 'Automatic (page numbers on shop pages)', 'kenakata-kit' ),
					'none'     => __( 'None', 'kenakata-kit' ),
					'numbers'  => __( 'Page numbers (1, 2, 3…)', 'kenakata-kit' ),
					'ajax'     => __( 'Page numbers without reloading (AJAX)', 'kenakata-kit' ),
					'loadmore' => __( '"Load more" button (AJAX)', 'kenakata-kit' ),
				),
				'description' => __( 'AJAX loads the next products without reloading the page.', 'kenakata-kit' ),
				'condition'   => array( 'source!' => array( 'related', 'recent' ) ),
			) );
			$this->add_control( 'load_more_text', array( 'label' => __( '"Load more" text', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => __( 'Load more', 'kenakata-kit' ), 'condition' => array( 'pagination' => 'loadmore', 'source!' => array( 'related', 'recent' ) ) ) );
			$this->add_control( 'toolbar', array( 'label' => __( 'Result count and sorting above', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes', 'condition' => array( 'source' => 'current' ) ) );
		}
		$this->add_control( 'section_title', array( 'label' => __( 'Section title (optional)', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => '', 'label_block' => true, 'placeholder' => __( 'e.g. Related products', 'kenakata-kit' ), 'description' => __( 'Shown above the products, and hidden together with them when there is nothing to show (useful for "Recently viewed" and "Related").', 'kenakata-kit' ), 'dynamic' => array( 'active' => true ) ) );
		$this->add_control( 'section_title_tag', array( 'label' => __( 'Title HTML tag', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'h2', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p' ), 'condition' => array( 'section_title!' => '' ) ) );
		$this->add_control( 'empty_text', array( 'label' => __( 'Text when there are no products', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => 'No products found in this section yet.', 'label_block' => true ) );
		$this->end_controls_section();

		/* ---------- Content: card ---------- */
		$this->start_controls_section( 'card_sec', array( 'label' => __( 'Card content', 'kenakata-kit' ) ) );
		$yes = function ( $v ) { return $v ? 'yes' : ''; };
		$this->add_control( 'show_image', array( 'label' => __( 'Image', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'show_brand', array( 'label' => __( 'Brand name', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => $yes( $d['show_brand'] ) ) );
		$this->add_control( 'show_title', array( 'label' => __( 'Product name', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'show_rating', array( 'label' => __( 'Rating stars', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => $yes( $d['show_rating'] ) ) );
		$this->add_control( 'show_price', array( 'label' => __( 'Price', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'show_badge', array( 'label' => __( 'Discount badge', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => $yes( $d['show_badge'] ) ) );
		$this->add_control( 'badge_text', array( 'label' => __( 'Badge text', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => $d['badge_text'], 'description' => __( '{percent} = discount %, {amount} = amount saved', 'kenakata-kit' ), 'condition' => array( 'show_badge' => 'yes' ) ) );
		$this->add_control( 'show_stock', array( 'label' => __( '"Out of stock" badge', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => $yes( $d['show_stock'] ) ) );
		$this->add_control( 'stock_text', array( 'label' => __( 'Out-of-stock badge text', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => $d['stock_text'], 'condition' => array( 'show_stock' => 'yes' ) ) );
		$this->add_control( 'title_tag', array( 'label' => __( 'Name HTML tag', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'h3', 'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4', 'p' => 'p' ), 'condition' => array( 'show_title' => 'yes' ) ) );
		$this->add_responsive_control( 'title_lines', array( 'label' => __( 'Name lines', 'kenakata-kit' ), 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 4, 'description' => __( 'Long names are cut with "…" and all cards stay the same height.', 'kenakata-kit' ), 'selectors' => array( '{{WRAPPER}} .kk-pcard' => '--kk-card-lines: {{VALUE}};' ), 'condition' => array( 'show_title' => 'yes' ) ) );
		$this->add_control( 'image_ratio', array( 'label' => __( 'Image shape', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'From settings', 'kenakata-kit' ), '1/1' => __( 'Square (1:1)', 'kenakata-kit' ), '4/5' => __( 'Portrait (4:5)', 'kenakata-kit' ), '3/4' => __( 'Tall (3:4)', 'kenakata-kit' ), '4/3' => __( 'Landscape (4:3)', 'kenakata-kit' ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard' => '--kk-card-ratio: {{VALUE}};' ), 'condition' => array( 'show_image' => 'yes' ) ) );
		$this->add_control( 'image_fit', array( 'label' => __( 'Image fit', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'From settings', 'kenakata-kit' ), 'contain' => __( 'Whole image', 'kenakata-kit' ), 'cover' => __( 'Fill the box', 'kenakata-kit' ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard' => '--kk-card-fit: {{VALUE}};' ), 'condition' => array( 'show_image' => 'yes' ) ) );
		$this->add_control( 'card_type', array( 'label' => __( 'Card style', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => $d['style'], 'options' => array( 'bordered' => __( 'Bordered', 'kenakata-kit' ), 'shadow' => __( 'Shadow', 'kenakata-kit' ), 'flat' => __( 'Flat', 'kenakata-kit' ) ), 'separator' => 'before' ) );
		$this->add_control( 'card_align', array( 'label' => __( 'Text alignment', 'kenakata-kit' ), 'type' => Controls_Manager::CHOOSE, 'default' => $d['align'], 'options' => array( 'left' => array( 'title' => __( 'Left', 'kenakata-kit' ), 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => __( 'Center', 'kenakata-kit' ), 'icon' => 'eicon-text-align-center' ) ) ) );
		$this->add_control( 'actions', array( 'label' => __( 'Button', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => $d['actions'], 'separator' => 'before', 'options' => array( 'side' => __( 'Add to cart + Buy Now side by side', 'kenakata-kit' ), 'stack' => __( 'Add to cart + Buy Now stacked', 'kenakata-kit' ), 'buynow_only' => __( 'Buy Now only', 'kenakata-kit' ), 'cart_only' => __( 'Add to cart only', 'kenakata-kit' ), 'none' => __( 'No buttons', 'kenakata-kit' ) ) ) );
		$this->add_control( 'actions_mobile', array( 'label' => __( 'Buttons on mobile', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => $d['actions_mobile'], 'options' => array( 'stack' => __( 'Stacked', 'kenakata-kit' ), 'icon' => __( 'Cart icon + Buy Now', 'kenakata-kit' ), 'same' => __( 'Same as desktop', 'kenakata-kit' ) ), 'condition' => array( 'actions' => array( 'side', 'stack' ) ) ) );
		$this->add_control( 'cart_text', array( 'label' => __( 'Add to cart text', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => $d['cart_text'], 'condition' => array( 'actions!' => array( 'none', 'buynow_only' ) ) ) );
		$this->add_control( 'buy_text', array( 'label' => __( 'Buy Now text', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'default' => $d['buy_text'], 'condition' => array( 'actions' => array( 'side', 'stack', 'buynow_only' ) ) ) );
		$this->end_controls_section();

		if ( $this->is_carousel() ) {
			$this->add_carousel_controls();
		}

		/* ---------- Style ---------- */
		$this->start_controls_section( 'section_title_style', array( 'label' => __( 'Section title', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'section_title!' => '' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'section_title_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-products-title' ) );
		$this->add_control( 'section_title_color', array( 'label' => __( 'Colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-products-title' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'section_title_align', array( 'label' => __( 'Alignment', 'kenakata-kit' ), 'type' => Controls_Manager::CHOOSE, 'options' => array( 'left' => array( 'title' => __( 'Left', 'kenakata-kit' ), 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => __( 'Center', 'kenakata-kit' ), 'icon' => 'eicon-text-align-center' ), 'right' => array( 'title' => __( 'Right', 'kenakata-kit' ), 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .kk-products-title' => 'text-align: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'section_title_gap', array( 'label' => __( 'Space below', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 60 ) ), 'selectors' => array( '{{WRAPPER}} .kk-products-title' => 'margin-bottom: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'section_box_style', array( 'label' => __( 'Section spacing', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_control( 'section_box_note', array( 'type' => Controls_Manager::RAW_HTML, 'raw' => '<div class="kk-ew-help">' . esc_html__( 'Space around the title and products. It disappears together with them when there is nothing to show, so use it instead of container padding for "Recently viewed" and "Related".', 'kenakata-kit' ) . '</div>' ) );
		$this->add_responsive_control( 'section_box_padding', array( 'label' => __( 'Padding', 'kenakata-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em', '%' ), 'selectors' => array( '{{WRAPPER}} .kk-products-wrap' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'section_box_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-products-wrap' => 'background-color: {{VALUE}};' ) ) );
		$this->end_controls_section();
		$this->add_box_style( 'card', __( 'Card', 'kenakata-kit' ), '.kk-pcard', '.kk-pcard:hover' );
		$this->start_controls_section( 'body_style', array( 'label' => __( 'Card content area', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'body_padding', array( 'label' => __( 'Padding', 'kenakata-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px' ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'body_gap', array( 'label' => __( 'Space between parts', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 24 ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__body' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'image_style', array( 'label' => __( 'Image', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'show_image' => 'yes' ) ) );
		$this->add_control( 'image_bg', array( 'label' => __( 'Image background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pcard__media' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'image_padding', array( 'label' => __( 'Image padding', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__img' => 'padding: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'image_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__media' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_control( 'image_zoom', array( 'label' => __( 'Zoom image on hover', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => '1.05', 'options' => array( '1.05' => __( 'Yes', 'kenakata-kit' ), '1' => __( 'No', 'kenakata-kit' ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard' => '--kk-zoom: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'badge_style', array( 'label' => __( 'Badges', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'badge_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-pcard__badge' ) );
		$this->add_control( 'badge_color', array( 'label' => __( 'Discount badge: text', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pcard__badge' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'badge_bg', array( 'label' => __( 'Discount badge: background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pcard__badge' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'stock_color', array( 'label' => __( 'Out of stock: text', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pcard__badge--stock' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'stock_bg', array( 'label' => __( 'Out of stock: background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pcard__badge--stock' => 'background-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'badge_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__badge' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'badge_position', array( 'label' => __( 'Position', 'kenakata-kit' ), 'type' => Controls_Manager::CHOOSE, 'options' => array( 'left' => array( 'title' => __( 'Left', 'kenakata-kit' ), 'icon' => 'eicon-h-align-left' ), 'right' => array( 'title' => __( 'Right', 'kenakata-kit' ), 'icon' => 'eicon-h-align-right' ) ), 'selectors_dictionary' => array( 'left' => 'left: 10px; right: auto; align-items: flex-start;', 'right' => 'right: 10px; left: auto; align-items: flex-end;' ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__badges' => '{{VALUE}}' ) ) );
		$this->end_controls_section();

		$this->add_text_style( 'brand', __( 'Brand name', 'kenakata-kit' ), '.kk-pcard__brand', '.kk-pcard__brand:hover', array( 'show_brand' => 'yes' ) );
		$this->start_controls_section( 'title_style', array( 'label' => __( 'Product name', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'show_title' => 'yes' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'title_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-pcard__title, {{WRAPPER}} .kk-pcard__title a', 'exclude' => array( 'line_height' ) ) );
		$this->add_control( 'title_lh', array( 'label' => __( 'Line height', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'size_units' => array( 'em' ), 'range' => array( 'em' => array( 'min' => 1, 'max' => 2, 'step' => 0.05 ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard' => '--kk-title-lh: {{SIZE}};' ) ) );
		$this->add_control( 'title_color', array( 'label' => __( 'Colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pcard__title a' => 'color: {{VALUE}} !important;' ) ) );
		$this->add_control( 'title_color_h', array( 'label' => __( 'Hover colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pcard__title a:hover' => 'color: {{VALUE}} !important;' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'rating_style', array( 'label' => __( 'Rating', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'show_rating' => 'yes' ) ) );
		$this->add_control( 'star_color', array( 'label' => __( 'Star colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-stars' => '--kk-star: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'star_size', array( 'label' => __( 'Star size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 10, 'max' => 28 ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__rating .kk-stars' => 'font-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'star_gap', array( 'label' => __( 'Space between stars and count', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 16 ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__rating' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'rating_count_typo', 'label' => __( 'Review count (12)', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-pcard__rating-count', 'separator' => 'before' ) );
		$this->add_control( 'rating_count_color', array( 'label' => __( 'Review count colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pcard__rating-count' => 'color: {{VALUE}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'price_style', array( 'label' => __( 'Price', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'show_price' => 'yes' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'price_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-pcard__price .price' ) );
		$this->add_control( 'price_color', array( 'label' => __( 'Price colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pcard__price .price, {{WRAPPER}} .kk-pcard__price ins' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'old_price_color', array( 'label' => __( 'Old price colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pcard__price del' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'old_price_gap', array( 'label' => __( 'Space before old price', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 20 ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__price del' => 'margin-inline-start: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'old_price_size', array( 'label' => __( 'Old price size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 10, 'max' => 22 ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__price del' => 'font-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();

		$this->start_controls_section( 'buttons_style', array( 'label' => __( 'Buttons (both)', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'actions!' => 'none' ) ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'btns_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-pcard__btn' ) );
		$this->add_responsive_control( 'btns_height', array( 'label' => __( 'Height', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 28, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__btn' => 'min-height: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'btns_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 32 ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__btn' => 'border-radius: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_responsive_control( 'btns_gap', array( 'label' => __( 'Gap between buttons', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 20 ) ), 'selectors' => array( '{{WRAPPER}} .kk-pcard__actions' => 'gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->add_button_style( '.kk-pcard__btn--cart', 'cartbtn', __( 'Add to cart button', 'kenakata-kit' ), array( 'actions!' => array( 'none', 'buynow_only' ) ), true, 'colors' );
		$this->add_button_style( '.kk-pcard__btn--buy', 'buybtn', __( 'Buy Now Button', 'kenakata-kit' ), array( 'actions' => array( 'side', 'stack', 'buynow_only' ) ), true, 'colors' );

		if ( ! $this->is_carousel() ) {
			$this->start_controls_section( 'pagination_style', array( 'label' => __( 'Page numbers & sorting', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => array( 'source!' => array( 'related', 'recent' ) ) ) );
			$this->add_control( 'page_color', array( 'label' => __( 'Number colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pagination .page-numbers' => 'color: {{VALUE}};' ) ) );
			$this->add_control( 'page_bg', array( 'label' => __( 'Number background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pagination .page-numbers' => 'background-color: {{VALUE}};' ) ) );
			$this->add_control( 'page_active_color', array( 'label' => __( 'Current page: text', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pagination .page-numbers.current' => 'color: {{VALUE}};' ) ) );
			$this->add_control( 'page_active_bg', array( 'label' => __( 'Current page: background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-pagination .page-numbers.current' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ) ) );
			$this->add_responsive_control( 'page_align', array( 'label' => __( 'Page numbers alignment', 'kenakata-kit' ), 'type' => Controls_Manager::CHOOSE, 'options' => array( 'flex-start' => array( 'title' => __( 'Left', 'kenakata-kit' ), 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => __( 'Center', 'kenakata-kit' ), 'icon' => 'eicon-text-align-center' ), 'flex-end' => array( 'title' => __( 'Right', 'kenakata-kit' ), 'icon' => 'eicon-text-align-right' ) ), 'selectors' => array( '{{WRAPPER}} .kk-pagination ul' => 'justify-content: {{VALUE}};' ) ) );
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => 'toolbar_typo', 'label' => __( 'Result count text', 'kenakata-kit' ), 'selector' => '{{WRAPPER}} .kk-products-toolbar, {{WRAPPER}} .kk-products-toolbar select', 'separator' => 'before' ) );
			$this->add_control( 'toolbar_color', array( 'label' => __( 'Colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-products-toolbar' => 'color: {{VALUE}};' ) ) );
			$this->end_controls_section();
			$this->add_button_style( '.kk-loadmore__btn', 'more', __( 'Load more button', 'kenakata-kit' ), array( 'pagination' => 'loadmore' ) );
		} else {
			$this->add_carousel_style();
		}
	}

	protected function render() {
		$s    = $this->get_settings_for_display();
		$yes  = function ( $k ) use ( $s ) { return isset( $s[ $k ] ) && 'yes' === $s[ $k ]; };
		$card = array(
			'style'          => $s['card_type'] ?? 'bordered',
			'align'          => $s['card_align'] ?? 'left',
			'show_image'     => $yes( 'show_image' ),
			'show_brand'     => $yes( 'show_brand' ),
			'show_title'     => $yes( 'show_title' ),
			'show_rating'    => $yes( 'show_rating' ),
			'show_price'     => $yes( 'show_price' ),
			'show_badge'     => $yes( 'show_badge' ),
			'show_stock'     => $yes( 'show_stock' ),
			'badge_text'     => $s['badge_text'] ?? '',
			'stock_text'     => $s['stock_text'] ?? '',
			'actions'        => $s['actions'] ?? 'side',
			'actions_mobile' => $s['actions_mobile'] ?? 'stack',
			'cart_text'      => $s['cart_text'] ?? '',
			'buy_text'       => ( $s['buy_text'] ?? '' ) ? $s['buy_text'] : kk_opt( 'buynow_text' ),
			'title_tag'      => $s['title_tag'] ?? 'h3',
		);
		$args = array(
			'source'            => $s['source'],
			'category'          => isset( $s['category'] ) ? (array) $s['category'] : '',
			'brand'             => isset( $s['brand'] ) ? (array) $s['brand'] : '',
			'ids'               => $s['ids'] ?? '',
			'exclude'           => $s['exclude'] ?? '',
			'limit'             => $s['limit'] ?? 8,
			'orderby'           => $s['orderby'] ?? '',
			'order'             => $s['order'] ?? 'DESC',
			'hide_out_of_stock' => $s['hide_out_of_stock'] ?? '',
			'layout'            => $this->layout,
			'columns'           => $s['columns'] ?? 4,
			'columns_tablet'    => $s['columns_tablet'] ?? 3,
			'columns_mobile'    => $s['columns_mobile'] ?? 2,
			'paginate'          => $yes( 'paginate' ) ? 'yes' : 'no',
			'toolbar'           => $yes( 'toolbar' ) ? 'yes' : 'no',
			'pagination'        => $s['pagination'] ?? '',
			'load_more_text'    => $s['load_more_text'] ?? '',
			'empty_text'        => $s['empty_text'] ?? '',
			'title'             => $s['section_title'] ?? '',
			'title_tag'         => $s['section_title_tag'] ?? 'h2',
			'card'              => $card,
		);
		if ( $this->is_carousel() ) {
			$args = array_merge( $args, $this->carousel_args( $s ), array( 'label' => $this->get_title() ) );
		}
		echo kk_render_products( $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_render_products()
	}
}

class KK_EW_Products_Carousel extends KK_EW_Products {
	protected $layout = 'carousel';
	public function get_name() { return 'kk-products-carousel'; }
	public function get_title() { return __( 'Products Carousel', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-product-related'; }
	public function get_keywords() { return array( 'products', 'carousel', 'slider', 'related', 'featured', 'kenakata', __( 'Product', 'kenakata-kit' ), 'ক্যারোসেল', 'স্লাইডার' ); }
}
