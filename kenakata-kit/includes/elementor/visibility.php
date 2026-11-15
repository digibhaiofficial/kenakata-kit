<?php
/**
 * "Who can see this" for every Elementor container, section, column and widget (Advanced tab):
 * Everyone / Visitors who are not logged in / Logged-in customers. Handy on the My account page
 * (a welcome panel only for visitors) — no code or custom CSS needed. The editor always shows everything.
 */

defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;

function kk_visibility_controls( $element ) {
	if ( ! is_object( $element ) || ! method_exists( $element, 'start_controls_section' ) ) {
		return;
	}
	$element->start_controls_section( 'kk_visibility', array( 'label' => __( 'Who can see this · Kenakata Kit', 'kenakata-kit' ), 'tab' => Controls_Manager::TAB_ADVANCED ) );
	$element->add_control( 'kk_show_to', array(
		'label'       => __( 'Show to', 'kenakata-kit' ),
		'type'        => Controls_Manager::SELECT,
		'default'     => '',
		'options'     => array(
			''        => __( 'Everyone', 'kenakata-kit' ),
			'guests'  => __( 'Visitors who are not logged in', 'kenakata-kit' ),
			'members' => __( 'Logged-in customers only', 'kenakata-kit' ),
		),
		'description' => __( 'The editor always shows it. Check the live page logged in and logged out.', 'kenakata-kit' ),
	) );
	$element->end_controls_section();
}
add_action( 'elementor/element/container/section_layout/after_section_end', 'kk_visibility_controls' );
add_action( 'elementor/element/section/section_advanced/after_section_end', 'kk_visibility_controls' );
add_action( 'elementor/element/column/section_advanced/after_section_end', 'kk_visibility_controls' );
add_action( 'elementor/element/common/_section_style/after_section_end', 'kk_visibility_controls' );

function kk_visibility_should_render( $should, $element ) {
	if ( ! $should || ! is_object( $element ) || ! method_exists( $element, 'get_settings' ) || kk_is_editor() ) {
		return $should;
	}
	$who = $element->get_settings( 'kk_show_to' );
	if ( 'guests' === $who ) {
		return ! is_user_logged_in();
	}
	if ( 'members' === $who ) {
		return is_user_logged_in();
	}
	return $should;
}
// Elementor's element cache (3.22+) would otherwise store the first visitor's version and show it to everyone:
// elements with a "Show to" choice are rendered fresh on every request.
add_filter( 'elementor/element/is_dynamic_content', function ( $dynamic, $raw_data ) {
	return $dynamic || ! empty( $raw_data['settings']['kk_show_to'] );
}, 10, 2 );

foreach ( array( 'widget', 'container', 'section', 'column' ) as $kk_type ) {
	add_filter( "elementor/frontend/{$kk_type}/should_render", 'kk_visibility_should_render', 10, 2 );
}
unset( $kk_type );
