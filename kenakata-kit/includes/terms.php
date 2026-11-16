<?php
/**
 * Brand / category cards: [kk_brands], [kk_categories], [kk_terms taxonomy="…"] and the Elementor widgets.
 * Archive covers: sections with class "kk-archive-cover" use the current term image as background.
 */

defined( 'ABSPATH' ) || exit;

function kk_terms_defaults() {
	return array(
		'taxonomy'   => 'product_cat',
		'layout'     => 'carousel', // carousel | grid
		'style'      => '',         // card | minimal | circle | logo ('' = setting)
		'limit'      => 0,
		'orderby'    => 'count',    // count | name | include
		'exclude'    => '',
		'include'    => '',
		'parent'     => '',
		'label'      => '',
		'show_count' => '',
		'show_name'  => 'yes',
		'hide_empty' => 'yes',
		'columns'    => '',
		'columns_tablet' => '',
		'columns_mobile' => '',
		'autoplay'   => '',
		'speed'      => '',
		'loop'       => '',
		'arrows'     => '',
		'dots'       => '',
		'all_url'    => '', // "View all" link shown under a limited list (categories fall back to the shop page)
		'all_text'   => '',
	);
}

function kk_terms_render( $a ) {
	$a = wp_parse_args( $a, kk_terms_defaults() );
	if ( 'product_brand' === $a['taxonomy'] && ! taxonomy_exists( 'product_brand' ) && kk_brand_taxonomy() ) {
		$a['taxonomy'] = kk_brand_taxonomy();
	}
	if ( ! taxonomy_exists( $a['taxonomy'] ) ) {
		return kk_is_editor() ? '<p class="kk-terms-empty">' . esc_html__( 'This taxonomy does not exist on this site.', 'kenakata-kit' ) . '</p>' : '';
	}
	$args = array(
		'taxonomy'   => $a['taxonomy'],
		'hide_empty' => 'no' !== $a['hide_empty'],
		'orderby'    => 'name' === $a['orderby'] ? 'name' : 'count',
		'order'      => 'name' === $a['orderby'] ? 'ASC' : 'DESC',
		'number'     => absint( $a['limit'] ) ? absint( $a['limit'] ) + 1 : 0, // one extra tells us whether there is more to show
		'exclude'    => array_map( 'absint', kk_list( $a['exclude'] ) ),
	);
	if ( '' !== (string) $a['parent'] ) {
		$args['parent'] = absint( $a['parent'] );
	}
	if ( kk_list( $a['include'] ) ) {
		$args['include'] = array_map( 'absint', kk_list( $a['include'] ) );
		$args['orderby'] = 'include';
		$args['order']   = 'ASC'; // keep the order the items were picked in
		unset( $args['exclude'] );
	}
	$terms = get_terms( $args );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '<p class="kk-terms-empty">' . esc_html__( 'Nothing to show yet.', 'kenakata-kit' ) . '</p>';
	}
	$more = false;
	if ( absint( $a['limit'] ) && count( $terms ) > absint( $a['limit'] ) ) {
		$more  = true;
		$terms = array_slice( array_values( $terms ), 0, absint( $a['limit'] ) );
	}

	$style      = $a['style'] ? $a['style'] : kk_opt( 'cards_style' );
	$show_count = '' === $a['show_count'] ? (bool) kk_opt( 'cards_show_count' ) : 'yes' === $a['show_count'];
	$carousel   = 'grid' !== $a['layout'];
	$cards      = '';
	foreach ( array_values( $terms ) as $i => $term ) {
		$thumb_id = absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) );
		$name     = html_entity_decode( $term->name );
		$media    = $thumb_id
			? wp_get_attachment_image( $thumb_id, 'medium', false, array( 'class' => 'kk-term-card__img', 'alt' => 'no' === $a['show_name'] || 'logo' === $style ? $name : '', 'loading' => 'lazy' ) )
			: '<span class="kk-term-card__mono" aria-hidden="true">' . esc_html( mb_strtoupper( mb_substr( wp_strip_all_tags( $name ), 0, 1 ) ) ) . '</span>';
		$name_html = '<span class="kk-term-card__name' . ( 'no' === $a['show_name'] || ( 'logo' === $style && $thumb_id ) ? ' screen-reader-text' : '' ) . '">' . esc_html( $name ) . '</span>';
		/* translators: %s: number of products */
		$count = $show_count ? '<span class="kk-term-card__count">' . esc_html( sprintf( _n( '%s product', '%s products', $term->count, 'kenakata-kit' ), number_format_i18n( $term->count ) ) ) . '</span>' : '';
		$cards .= sprintf(
			'<li class="kk-term-card kk-tone-%4$d%5$s%7$s"><a href="%1$s"><span class="kk-term-card__media">%2$s</span>%3$s%6$s</a></li>',
			esc_url( get_term_link( $term ) ), $media, $name_html, $i % 3, $thumb_id ? ' has-image' : '', $count, $carousel ? ' kk-slider__slide' : ''
		);
	}

	$is_brand = false !== strpos( $a['taxonomy'], 'brand' );
	$label    = $a['label'] ? $a['label'] : ( $is_brand ? __( 'Brands', 'kenakata-kit' ) : __( 'Categories', 'kenakata-kit' ) );
	$classes  = 'kk-terms kk-terms--style-' . sanitize_html_class( $style ) . ( 'minimal' === $style ? ' kk-terms--minimal' : '' );
	$vars     = '';
	foreach ( array( 'columns' => $carousel ? '--kk-pv' : '--kk-tcols', 'columns_tablet' => $carousel ? '--kk-pv-t' : '--kk-tcols-t', 'columns_mobile' => $carousel ? '--kk-pv-m' : '--kk-tcols-m' ) as $k => $var ) {
		if ( absint( $a[ $k ] ) ) {
			$vars .= $var . ':' . absint( $a[ $k ] ) . ';';
		}
	}

	$all = '';
	if ( $more ) {
		$all_url = esc_url( $a['all_url'] );
		if ( ! $all_url && 'product_cat' === $a['taxonomy'] && function_exists( 'wc_get_page_permalink' ) ) {
			$all_url = esc_url( wc_get_page_permalink( 'shop' ) );
		}
		if ( $all_url ) {
			$all_text = $a['all_text'] ? $a['all_text'] : ( $is_brand ? __( 'View all brands', 'kenakata-kit' ) : __( 'View all categories', 'kenakata-kit' ) );
			$all      = '<p class="kk-terms-all"><a href="' . $all_url . '">' . esc_html( $all_text ) . ' &rarr;</a></p>';
		}
	}

	if ( ! $carousel ) {
		return '<ul class="' . esc_attr( $classes . ' kk-terms--grid' ) . '" aria-label="' . esc_attr( $label ) . '"' . ( $vars ? ' style="' . esc_attr( $vars ) . '"' : '' ) . '>' . $cards . '</ul>' . $all;
	}
	wp_enqueue_script( 'kenakata-kit' );
	$a['label']  = $label;
	$a['arrows'] = '' === $a['arrows'] ? ( kk_opt( 'cards_arrows' ) ? 'yes' : 'no' ) : $a['arrows'];
	$a['dots']   = '' === $a['dots'] ? ( kk_opt( 'cards_dots' ) ? 'yes' : 'no' ) : $a['dots'];
	return kk_slider_open( $a, 'kk-terms-slider kk-carousel', $vars )
		. '<ul class="' . esc_attr( $classes . ' kk-terms--carousel kk-slider__track' ) . '" tabindex="0">' . $cards . '</ul>'
		. kk_slider_close( $a )
		. $all;
}

function kk_terms_shortcode( $atts ) {
	return kk_terms_render( shortcode_atts( kk_terms_defaults(), $atts, 'kk_terms' ) );
}
add_shortcode( 'kk_terms', 'kk_terms_shortcode' );
add_shortcode( 'ss_terms', 'kk_terms_shortcode' ); // backward compatibility

add_shortcode( 'kk_brands', function ( $atts ) {
	$atts             = (array) $atts;
	$atts['taxonomy'] = kk_brand_taxonomy() ? kk_brand_taxonomy() : 'product_brand';
	return kk_terms_render( shortcode_atts( kk_terms_defaults(), $atts, 'kk_brands' ) );
} );
add_shortcode( 'kk_categories', function ( $atts ) {
	$atts             = (array) $atts;
	$atts['taxonomy'] = 'product_cat';
	if ( ! isset( $atts['exclude'] ) ) {
		$atts['exclude'] = (string) get_option( 'default_product_cat' ); // hide "Uncategorized"
	}
	return kk_terms_render( shortcode_atts( kk_terms_defaults(), $atts, 'kk_categories' ) );
} );

add_action( 'wp_head', function () {
	if ( ! is_tax() ) {
		return;
	}
	$term     = get_queried_object();
	$thumb_id = $term && isset( $term->term_id ) ? absint( get_term_meta( $term->term_id, 'thumbnail_id', true ) ) : 0;
	$url      = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'full' ) : '';
	if ( $url ) {
		printf( '<style id="kk-cover-img">:root{--kk-cover-img:url("%s")}</style>' . "\n", esc_url( $url ) );
	}
} );
