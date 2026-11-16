<?php
/**
 * "ব্র্যান্ড" and "ক্যাটাগরি" widgets (carousel or grid), plus the older generic "kk-terms" widget.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;

abstract class KK_EW_Terms_Base extends KK_EW_Base {
	abstract protected function taxonomy();
	abstract protected function help();

	protected function register_controls() {
		$tax = $this->taxonomy();

		$this->start_controls_section( 'content', array( 'label' => __( 'Content', 'kenakata-kit' ) ) );
		$this->help_box( $this->get_title(), $this->help() );
		if ( 'generic' === $tax ) {
			$options = array( 'product_cat' => __( 'Categories', 'kenakata-kit' ), 'product_tag' => __( 'Tags', 'kenakata-kit' ) );
			if ( kk_brand_taxonomy() ) {
				$options = array( kk_brand_taxonomy() => __( 'Brands', 'kenakata-kit' ) ) + $options;
			}
			$this->add_control( 'taxonomy', array( 'label' => __( 'Content', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'product_cat', 'options' => $options ) );
		}
		$this->add_control( 'limit', array( 'label' => __( 'How many (0 = all)', 'kenakata-kit' ), 'type' => Controls_Manager::NUMBER, 'min' => 0, 'default' => 12 ) );
		$this->add_control( 'all_url', array( 'label' => __( '"View all" link', 'kenakata-kit' ), 'type' => Controls_Manager::URL, 'placeholder' => 'https://', 'description' => __( 'Shown under the list when there are more items than "How many". Categories use the shop page if this is empty.', 'kenakata-kit' ) ) );
		$this->add_control( 'all_text', array( 'label' => __( '"View all" text', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'placeholder' => __( 'View all brands', 'kenakata-kit' ) ) );
		$this->add_control( 'orderby', array( 'label' => __( 'Order by', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'count', 'options' => array( 'count' => __( 'Most products first', 'kenakata-kit' ), 'name' => __( 'Name (A–Z)', 'kenakata-kit' ) ) ) );
		if ( 'generic' !== $tax ) {
			$opts = $this->term_options( $tax );
			$this->add_control( 'include', array( 'label' => __( 'Show only these', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => $opts, 'description' => __( 'Leave empty to show all. Selected items show in the order you choose.', 'kenakata-kit' ) ) );
			$this->add_control( 'exclude', array( 'label' => __( 'Hide these', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT2, 'multiple' => true, 'label_block' => true, 'options' => $opts ) );
		} else {
			$this->add_control( 'include', array( 'label' => __( 'Only these IDs', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'placeholder' => '12, 34' ) );
			$this->add_control( 'exclude', array( 'label' => __( 'Hide these IDs', 'kenakata-kit' ), 'type' => Controls_Manager::TEXT, 'placeholder' => '16, 22' ) );
		}
		if ( 'product_cat' === $tax ) {
			$this->add_control( 'top_only', array( 'label' => __( 'Top-level categories only', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => '' ) );
		}
		$this->add_control( 'hide_empty', array( 'label' => __( 'Hide empty ones', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->end_controls_section();

		$this->start_controls_section( 'layout_sec', array( 'label' => __( 'Layout & card', 'kenakata-kit' ) ) );
		$this->add_control( 'layout', array( 'label' => __( 'Layout', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => 'carousel', 'options' => array( 'carousel' => __( 'Carousel (slider)', 'kenakata-kit' ), 'grid' => __( 'Grid (all at once)', 'kenakata-kit' ) ) ) );
		$this->add_responsive_control( 'columns', array( 'label' => __( 'Items per view / per row', 'kenakata-kit' ), 'type' => Controls_Manager::NUMBER, 'min' => 1, 'max' => 10, 'default' => max( 1, absint( kk_opt( 'cards_per_view' ) ) ), 'tablet_default' => max( 1, absint( kk_opt( 'cards_per_view_tablet' ) ) ), 'mobile_default' => max( 1, absint( kk_opt( 'cards_per_view_mobile' ) ) ) ) );
		$this->add_responsive_control( 'gap', array( 'label' => __( 'Gap between cards', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 48 ) ), 'selectors' => array( '{{WRAPPER}} .kk-terms' => '--kk-gap: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'style', array( 'label' => __( 'Card style', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => kk_opt( 'cards_style' ), 'options' => array( 'card' => __( 'Card with border', 'kenakata-kit' ), 'minimal' => __( 'No border', 'kenakata-kit' ), 'circle' => __( 'Round image + name', 'kenakata-kit' ), 'logo' => __( 'Logo only', 'kenakata-kit' ) ) ) );
		$this->add_control( 'show_name', array( 'label' => __( 'Show name', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'show_count', array( 'label' => __( 'Show product count', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => kk_opt( 'cards_show_count' ) ? 'yes' : '' ) );
		$this->end_controls_section();

		$this->add_carousel_controls( array( 'layout' => 'carousel' ) );

		$this->add_box_style( 'card', __( 'Card', 'kenakata-kit' ), '.kk-term-card > a', '.kk-term-card > a:hover' );
		$this->start_controls_section( 'media_style', array( 'label' => __( 'Image / logo / letter', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'media_size', array( 'label' => __( 'Size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 32, 'max' => 200 ) ), 'selectors' => array( '{{WRAPPER}} .kk-terms' => '--kk-term-img: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'media_shape', array( 'label' => __( 'Shape', 'kenakata-kit' ), 'type' => Controls_Manager::SELECT, 'default' => '', 'options' => array( '' => __( 'From card style', 'kenakata-kit' ), '50%' => __( 'Circle', 'kenakata-kit' ), '12px' => __( 'Rounded square', 'kenakata-kit' ), '0' => __( 'Square', 'kenakata-kit' ) ), 'selectors' => array( '{{WRAPPER}} .kk-term-card__media' => 'border-radius: {{VALUE}};' ) ) );
		$this->add_control( 'media_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-term-card__media' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'media_border', array( 'label' => __( 'Border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-term-card__media' => 'border: 1px solid {{VALUE}};' ) ) );
		$this->add_responsive_control( 'media_padding', array( 'label' => __( 'Logo padding', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 0, 'max' => 30 ) ), 'selectors' => array( '{{WRAPPER}} .kk-term-card__img' => 'padding: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'mono_color', array( 'label' => __( 'Letter colour (when there is no image)', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-term-card__media' => 'color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'mono_size', array( 'label' => __( 'Letter size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 12, 'max' => 64 ) ), 'selectors' => array( '{{WRAPPER}} .kk-term-card__mono' => 'font-size: {{SIZE}}{{UNIT}};' ) ) );
		$this->end_controls_section();
		$this->add_text_style( 'name', __( 'Name', 'kenakata-kit' ), '.kk-term-card__name', '.kk-term-card > a:hover .kk-term-card__name', array( 'show_name' => 'yes' ) );
		$this->add_text_style( 'count', __( 'Product count', 'kenakata-kit' ), '.kk-term-card__count', '', array( 'show_count' => 'yes' ) );
		$this->start_controls_section( 'align_style', array( 'label' => __( 'Text alignment', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE ) );
		$this->add_responsive_control( 'text_align', array( 'label' => __( 'Alignment', 'kenakata-kit' ), 'type' => Controls_Manager::CHOOSE, 'options' => array( 'flex-start' => array( 'title' => __( 'Left', 'kenakata-kit' ), 'icon' => 'eicon-text-align-left' ), 'center' => array( 'title' => __( 'Center', 'kenakata-kit' ), 'icon' => 'eicon-text-align-center' ) ), 'selectors_dictionary' => array( 'flex-start' => 'align-items: flex-start; text-align: left;', 'center' => 'align-items: center; text-align: center;' ), 'selectors' => array( '{{WRAPPER}} .kk-term-card > a' => '{{VALUE}}' ) ) );
		$this->end_controls_section();
		$this->add_carousel_style( array( 'layout' => 'carousel' ) );
	}

	protected function render() {
		$s   = $this->get_settings_for_display();
		$tax = 'generic' === $this->taxonomy() ? $s['taxonomy'] : $this->taxonomy();
		$ids = function ( $v ) { return implode( ',', array_map( 'absint', is_array( $v ) ? $v : kk_list( $v ) ) ); };
		$a   = array(
			'taxonomy'       => $tax,
			'layout'         => $s['layout'],
			'style'          => $s['style'],
			'limit'          => absint( $s['limit'] ),
			'orderby'        => $s['orderby'],
			'include'        => $ids( $s['include'] ?? '' ),
			'exclude'        => $ids( $s['exclude'] ?? '' ),
			'parent'         => ! empty( $s['top_only'] ) && 'yes' === $s['top_only'] ? 0 : '',
			'hide_empty'     => 'yes' === ( $s['hide_empty'] ?? 'yes' ) ? 'yes' : 'no',
			'show_name'      => 'yes' === ( $s['show_name'] ?? 'yes' ) ? 'yes' : 'no',
			'show_count'     => 'yes' === ( $s['show_count'] ?? '' ) ? 'yes' : 'no',
			'columns'        => $s['columns'] ?? '',
			'columns_tablet' => $s['columns_tablet'] ?? '',
			'columns_mobile' => $s['columns_mobile'] ?? '',
			'label'          => $this->get_title(),
			'all_url'        => is_array( $s['all_url'] ?? null ) ? (string) ( $s['all_url']['url'] ?? '' ) : '',
			'all_text'       => sanitize_text_field( (string) ( $s['all_text'] ?? '' ) ),
		);
		if ( 'product_cat' === $tax && '' === $a['exclude'] && '' === $a['include'] ) {
			$a['exclude'] = (string) get_option( 'default_product_cat' ); // hide "Uncategorized"
		}
		echo kk_terms_render( array_merge( $a, 'carousel' === $s['layout'] ? $this->carousel_args( $s ) : array() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_terms_render()
	}
}

class KK_EW_Brands extends KK_EW_Terms_Base {
	public function get_name() { return 'kk-brands'; }
	public function get_title() { return __( 'Brands', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-logo'; }
	public function get_keywords() { return array( 'brand', 'brands', 'logo', 'carousel', 'kenakata', __( 'Brands', 'kenakata-kit' ) ); }
	protected function taxonomy() { return kk_brand_taxonomy() ? kk_brand_taxonomy() : 'product_brand'; }
	protected function help() { return __( 'Brand logo or name cards. To add a logo: Products → Brands → edit the brand → Thumbnail. Without a logo the first letter of the name is shown.', 'kenakata-kit' ); }
}

class KK_EW_Categories extends KK_EW_Terms_Base {
	public function get_name() { return 'kk-categories'; }
	public function get_title() { return __( 'Categories', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-product-categories'; }
	public function get_keywords() { return array( 'category', 'categories', 'carousel', 'grid', 'kenakata', __( 'Categories', 'kenakata-kit' ) ); }
	protected function taxonomy() { return 'product_cat'; }
	protected function help() { return __( 'Category image or name cards. To add an image: Products → Categories → edit the category → Thumbnail. "Uncategorized" is hidden automatically.', 'kenakata-kit' ); }
}

/** Older generic widget (kept so existing pages keep working; hidden from the panel). */
class KK_EW_Terms extends KK_EW_Terms_Base {
	public function get_name() { return 'kk-terms'; }
	public function get_title() { return __( 'Brand/Category (old)', 'kenakata-kit' ); }
	public function get_icon() { return 'eicon-gallery-grid'; }
	public function show_in_panel() { return false; }
	protected function taxonomy() { return 'generic'; }
	protected function help() { return __( 'Use the new "Brands" and "Categories" widgets.', 'kenakata-kit' ); }
}
