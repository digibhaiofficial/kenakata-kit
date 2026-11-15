<?php
/**
 * Shared helpers for Kenakata Kit Elementor widgets: help box, alignment, button / box / text style groups,
 * carousel controls.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Widget_Base;

abstract class KK_EW_Base extends Widget_Base {
	public function get_categories() { return array( 'kenakata-kit' ); }
	public function get_script_depends() { return array( 'kenakata-kit' ); }
	public function get_style_depends() { return array( 'kenakata-kit' ); }
	public function get_custom_help_url() { return KK_URL . 'docs/index.html#widgets'; }
	/** Our widgets show live data (cart, prices, account, stock), so Elementor's element cache must never store them. */
	public function is_dynamic_content(): bool { return true; }

	/** Bengali help box at the top of the Content tab. */
	protected function help_box( $title, $text ) {
		$this->add_control( 'kk_help', array(
			'type'            => Controls_Manager::RAW_HTML,
			'raw'             => '<div class="kk-ew-help"><b>' . esc_html( $title ) . '</b>' . esc_html( $text ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=kenakata-kit' ) ) . '" target="_blank">' . esc_html__( 'Settings ↗', 'kenakata-kit' ) . '</a></div>',
			'content_classes' => 'kk-ew-help-wrap',
		) );
	}

	/**
	 * "{{WRAPPER}} a, {{WRAPPER}} b" for a comma-separated selector list, so every part is scoped to this widget.
	 * $suffix (e.g. ':hover') is added to each part.
	 */
	protected function w( $selector, $suffix = '' ) {
		$parts  = array_filter( array_map( 'trim', explode( ',', $selector ) ) );
		$prefix = $this->kk_global ? 'body' : '{{WRAPPER}}';
		return implode( ', ', array_map( function ( $p ) use ( $suffix, $prefix ) { return $prefix . ' ' . $p . $suffix; }, $parts ) );
	}

	/**
	 * When true, style helpers target elements outside the widget (e.g. the side cart, which is printed once
	 * at the end of the page). Used for site-wide parts that belong to a widget placed in the header.
	 */
	protected $kk_global = false;

	protected function add_align_control( $selector, $label = '', $name = 'align' ) {
		$label = $label ? $label : __( 'Alignment', 'kenakata-kit' );
		$this->add_responsive_control( $name, array(
			'label'                => $label,
			'type'                 => Controls_Manager::CHOOSE,
			'options'              => array(
				'flex-start' => array( 'title' => __( 'Left', 'kenakata-kit' ), 'icon' => 'eicon-text-align-left' ),
				'center'     => array( 'title' => __( 'Center', 'kenakata-kit' ), 'icon' => 'eicon-text-align-center' ),
				'flex-end'   => array( 'title' => __( 'Right', 'kenakata-kit' ), 'icon' => 'eicon-text-align-right' ),
			),
			'selectors_dictionary' => array(
				'flex-start' => 'justify-content: flex-start; text-align: left;',
				'center'     => 'justify-content: center; text-align: center;',
				'flex-end'   => 'justify-content: flex-end; text-align: right;',
			),
			'selectors'            => array( $this->w( $selector ) => '{{VALUE}}' ),
		) );
	}

	/** Colour + typography for a piece of text, with optional hover colour. */
	protected function add_text_style( $prefix, $label, $selector, $hover_selector = '', $condition = array(), $typo_args = array() ) {
		$this->start_controls_section( $prefix . '_style', array( 'label' => $label, 'tab' => Controls_Manager::TAB_STYLE, 'condition' => $condition ) );
		$this->add_group_control( Group_Control_Typography::get_type(), array_merge( array( 'name' => $prefix . '_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => $this->w( $selector ) ), $typo_args ) );
		$this->add_control( $prefix . '_color', array( 'label' => __( 'Colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $this->w( $selector ) => 'color: {{VALUE}};' ) ) );
		if ( $hover_selector ) {
			$this->add_control( $prefix . '_color_h', array( 'label' => __( 'Hover colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $this->w( $hover_selector ) => 'color: {{VALUE}};' ) ) );
		}
		$this->extra_text_controls( $prefix );
		$this->end_controls_section();
	}

	/** Hook for subclasses to add controls inside a text section. */
	protected function extra_text_controls( $prefix ) {}

	/**
	 * Button style section. $parts = 'full' (typography, colours, border, corners, padding, height, shadow)
	 * or 'colors' (colours, border, shadow only – used when a shared "Buttons" section sets size and text).
	 */
	protected function add_button_style( $selector, $prefix = 'btn', $label = '', $condition = array(), $important = false, $parts = 'full' ) {
		$label = $label ? $label : __( 'Button', 'kenakata-kit' );
		$imp   = $important ? ' !important' : '';
		$sel   = $this->w( $selector );
		$hover = $this->w( $selector, ':hover' ) . ', ' . $this->w( $selector, ':focus-visible' );
		$this->start_controls_section( $prefix . '_style', array( 'label' => $label, 'tab' => Controls_Manager::TAB_STYLE, 'condition' => $condition ) );
		if ( 'full' === $parts ) {
			$this->add_group_control( Group_Control_Typography::get_type(), array( 'name' => $prefix . '_typo', 'label' => __( 'Typography', 'kenakata-kit' ), 'selector' => $sel ) );
		}
		$this->start_controls_tabs( $prefix . '_tabs' );
		$this->start_controls_tab( $prefix . '_normal', array( 'label' => __( 'Normal', 'kenakata-kit' ) ) );
		$this->add_control( $prefix . '_color', array( 'label' => __( 'Text colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'color: {{VALUE}}' . $imp . ';' ) ) );
		$this->add_control( $prefix . '_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'background-color: {{VALUE}}' . $imp . ';' ) ) );
		$this->end_controls_tab();
		$this->start_controls_tab( $prefix . '_hover', array( 'label' => __( 'Hover', 'kenakata-kit' ) ) );
		$this->add_control( $prefix . '_color_h', array( 'label' => __( 'Text colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $hover => 'color: {{VALUE}}' . $imp . ';' ) ) );
		$this->add_control( $prefix . '_bg_h', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $hover => 'background-color: {{VALUE}}' . $imp . ';' ) ) );
		$this->add_control( $prefix . '_border_h', array( 'label' => __( 'Border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $this->w( $selector, ':hover' ) => 'border-color: {{VALUE}}' . $imp . ';' ) ) );
		$this->end_controls_tab();
		$this->end_controls_tabs();
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => $prefix . '_border', 'label' => __( 'Border', 'kenakata-kit' ), 'selector' => $sel, 'separator' => 'before' ) );
		if ( 'full' === $parts ) {
			$this->add_responsive_control( $prefix . '_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' . $imp . ';' ) ) );
			$this->add_responsive_control( $prefix . '_padding', array( 'label' => __( 'Padding', 'kenakata-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}' . $imp . ';' ) ) );
			$this->add_responsive_control( $prefix . '_height', array( 'label' => __( 'Height', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 24, 'max' => 90 ) ), 'selectors' => array( $sel => 'min-height: {{SIZE}}{{UNIT}}' . $imp . ';' ) ) );
		}
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => $prefix . '_shadow', 'label' => __( 'Shadow', 'kenakata-kit' ), 'selector' => $sel ) );
		$this->end_controls_section();
	}

	/** Background, border, radius, padding, shadow for a box. */
	protected function add_box_style( $prefix, $label, $selector, $hover_selector = '', $condition = array() ) {
		$sel = $this->w( $selector );
		$this->start_controls_section( $prefix . '_style', array( 'label' => $label, 'tab' => Controls_Manager::TAB_STYLE, 'condition' => $condition ) );
		$this->add_control( $prefix . '_bg', array( 'label' => __( 'Background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $sel => 'background-color: {{VALUE}};' ) ) );
		if ( $hover_selector ) {
			$this->add_control( $prefix . '_bg_h', array( 'label' => __( 'Hover background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $this->w( $hover_selector ) => 'background-color: {{VALUE}};' ) ) );
		}
		$this->add_group_control( Group_Control_Border::get_type(), array( 'name' => $prefix . '_border', 'label' => __( 'Border', 'kenakata-kit' ), 'selector' => $sel ) );
		if ( $hover_selector ) {
			$this->add_control( $prefix . '_border_h', array( 'label' => __( 'Hover border colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( $this->w( $hover_selector ) => 'border-color: {{VALUE}};' ) ) );
		}
		$this->add_responsive_control( $prefix . '_radius', array( 'label' => __( 'Corner radius', 'kenakata-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', '%' ), 'selectors' => array( $sel => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_responsive_control( $prefix . '_padding', array( 'label' => __( 'Padding', 'kenakata-kit' ), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => array( 'px', 'em' ), 'selectors' => array( $sel => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ) ) );
		$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => $prefix . '_shadow', 'label' => __( 'Shadow', 'kenakata-kit' ), 'selector' => $sel ) );
		if ( $hover_selector ) {
			$this->add_group_control( Group_Control_Box_Shadow::get_type(), array( 'name' => $prefix . '_shadow_h', 'label' => __( 'Hover shadow', 'kenakata-kit' ), 'selector' => $this->w( $hover_selector ) ) );
		}
		$this->extra_box_controls( $prefix );
		$this->end_controls_section();
	}
	/** Hook for subclasses to add controls inside a box section. */
	protected function extra_box_controls( $prefix ) {}

	/** Carousel behaviour controls (Content tab). */
	protected function add_carousel_controls( $condition = array() ) {
		$this->start_controls_section( 'carousel', array( 'label' => __( 'Carousel (slider)', 'kenakata-kit' ), 'condition' => $condition ) );
		$this->add_control( 'autoplay', array( 'label' => __( 'Autoplay', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => kk_opt( 'cards_autoplay' ) ? 'yes' : '', 'description' => __( 'Pauses on hover or touch.', 'kenakata-kit' ) ) );
		$this->add_control( 'speed', array( 'label' => __( 'Seconds between slides', 'kenakata-kit' ), 'type' => Controls_Manager::NUMBER, 'min' => 2, 'max' => 15, 'default' => max( 2, absint( kk_opt( 'cards_autoplay_speed' ) ) ), 'condition' => array( 'autoplay' => 'yes' ) ) );
		$this->add_control( 'loop', array( 'label' => __( 'Loop', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => kk_opt( 'cards_loop' ) ? 'yes' : '' ) );
		$this->add_control( 'arrows', array( 'label' => __( 'Arrows', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes' ) );
		$this->add_control( 'dots', array( 'label' => __( 'Dots', 'kenakata-kit' ), 'type' => Controls_Manager::SWITCHER, 'default' => kk_opt( 'cards_dots' ) ? 'yes' : '' ) );
		$this->end_controls_section();
	}

	/** Arrow + dot styles (Style tab). */
	protected function add_carousel_style( $condition = array() ) {
		$this->start_controls_section( 'arrows_style', array( 'label' => __( 'Carousel arrows & dots', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => $condition ) );
		$this->add_responsive_control( 'arrow_size', array( 'label' => __( 'Arrow size', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => 28, 'max' => 72 ) ), 'selectors' => array( '{{WRAPPER}} .kk-slider__arrow' => 'width: {{SIZE}}{{UNIT}} !important; height: {{SIZE}}{{UNIT}} !important;' ) ) );
		$this->add_control( 'arrow_color', array( 'label' => __( 'Arrow colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-slider__arrow' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'arrow_bg', array( 'label' => __( 'Arrow background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-slider__arrow' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'arrow_color_h', array( 'label' => __( 'Arrow hover colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-slider__arrow:hover, {{WRAPPER}} .kk-slider__arrow:focus-visible' => 'color: {{VALUE}};' ) ) );
		$this->add_control( 'arrow_bg_h', array( 'label' => __( 'Hover background', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-slider__arrow:hover, {{WRAPPER}} .kk-slider__arrow:focus-visible' => 'background-color: {{VALUE}}; border-color: {{VALUE}};' ) ) );
		$this->add_responsive_control( 'arrow_offset', array( 'label' => __( 'Arrow distance from edge', 'kenakata-kit' ), 'type' => Controls_Manager::SLIDER, 'range' => array( 'px' => array( 'min' => -40, 'max' => 40 ) ), 'selectors' => array( '{{WRAPPER}} .kk-slider__arrow--prev' => 'left: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .kk-slider__arrow--next' => 'right: {{SIZE}}{{UNIT}};' ) ) );
		$this->add_control( 'dot_color', array( 'label' => __( 'Dot colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'separator' => 'before', 'selectors' => array( '{{WRAPPER}} .kk-slider__dot' => 'background-color: {{VALUE}};' ) ) );
		$this->add_control( 'dot_active', array( 'label' => __( 'Active dot colour', 'kenakata-kit' ), 'type' => Controls_Manager::COLOR, 'selectors' => array( '{{WRAPPER}} .kk-slider__dot[aria-current="true"]' => 'background-color: {{VALUE}};' ) ) );
		$this->end_controls_section();
	}

	/** Carousel settings → renderer args. */
	protected function carousel_args( array $s ) {
		return array(
			'autoplay' => 'yes' === ( $s['autoplay'] ?? '' ) ? 'yes' : 'no',
			'speed'    => max( 2, absint( $s['speed'] ?? 4 ) ) * 1000,
			'loop'     => 'yes' === ( $s['loop'] ?? '' ) ? 'yes' : 'no',
			'arrows'   => 'yes' === ( $s['arrows'] ?? '' ) ? 'yes' : 'no',
			'dots'     => 'yes' === ( $s['dots'] ?? '' ) ? 'yes' : 'no',
		);
	}

	protected function editor_note( $text ) {
		if ( kk_is_editor() ) {
			echo '<div class="kk-ew-note">' . esc_html( $text ) . '</div>';
		}
	}

	/** Term options for SELECT2 controls. */
	protected function term_options( $taxonomy ) {
		$out = array();
		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return $out;
		}
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false, 'number' => 500, 'orderby' => 'name' ) );
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$out[ $t->term_id ] = html_entity_decode( $t->name ) . ' (' . $t->count . ')';
			}
		}
		return $out;
	}
}
