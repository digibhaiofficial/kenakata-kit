<?php
/**
 * Variation buttons on product pages: each option (size, colour…) is shown as a button instead of a dropdown.
 * The original <select> stays in the form (hidden), so WooCommerce's own variation script (price, stock, image,
 * "Clear" link) keeps working unchanged; the buttons only pick a value in it (assets/kenakata-kit.js).
 */

defined( 'ABSPATH' ) || exit;

/** Turn one variation dropdown into a group of buttons (keeps the dropdown for WooCommerce). */
function kk_swatches_html( $html, $args ) {
	$product   = isset( $args['product'] ) ? $args['product'] : null;
	$attribute = isset( $args['attribute'] ) ? (string) $args['attribute'] : '';
	$options   = isset( $args['options'] ) ? (array) $args['options'] : array();
	if ( ! $product instanceof WC_Product || '' === $attribute || false !== strpos( $html, 'kk-swatches' ) ) {
		return $html;
	}
	if ( ! $options ) {
		$all     = $product->get_variation_attributes();
		$options = isset( $all[ $attribute ] ) ? (array) $all[ $attribute ] : array();
	}
	if ( ! $options ) {
		return $html;
	}

	$items = array();
	if ( taxonomy_exists( $attribute ) ) {
		foreach ( wc_get_product_terms( $product->get_id(), $attribute, array( 'fields' => 'all' ) ) as $term ) {
			if ( in_array( $term->slug, $options, true ) ) {
				$items[ $term->slug ] = apply_filters( 'woocommerce_variation_option_name', $term->name, $term, $attribute, $product );
			}
		}
	} else {
		foreach ( $options as $option ) {
			$items[ $option ] = apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute, $product );
		}
	}
	if ( ! $items ) {
		return $html;
	}

	$selected = isset( $args['selected'] ) ? (string) $args['selected'] : '';
	$label    = wc_attribute_label( $attribute, $product );
	$buttons  = '';
	foreach ( $items as $value => $text ) {
		$on       = '' !== $selected && $selected === (string) $value;
		$buttons .= '<button type="button" class="kk-swatch' . ( $on ? ' is-selected' : '' ) . '" role="radio" aria-checked="' . ( $on ? 'true' : 'false' ) . '" tabindex="' . ( $on ? '0' : '-1' ) . '" data-value="' . esc_attr( $value ) . '">' . esc_html( $text ) . '</button>';
	}
	// The dropdown stays for WooCommerce's script but is hidden from people and screen readers.
	$select = preg_replace( '/<select\b/', '<select tabindex="-1" aria-hidden="true"', $html, 1 );
	return '<div class="kk-swatches-wrap" data-kk-clear="' . ( kk_opt( 'swatches_clear' ) ? '1' : '0' ) . '">' . $select
		. '<div class="kk-swatches" role="radiogroup" aria-label="' . esc_attr( $label ) . '">' . $buttons . '</div></div>';
}

if ( kk_opt( 'swatches_enable' ) ) {
	add_filter( 'woocommerce_dropdown_variation_attribute_options_html', 'kk_swatches_html', 20, 2 );
}
