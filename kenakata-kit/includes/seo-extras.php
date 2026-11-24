<?php
/**
 * SEO extras for product pages (tab "SEO & Google"). Everything is off until switched on.
 *
 *  - Delivery details and the return policy in the Product schema (the "shippingDetails" and
 *    "hasMerchantReturnPolicy" fields Google asks for in Search Console → Merchant listings). Added to the Product
 *    markup of Rank Math and of WooCommerce itself, whichever prints it; fields already there are never changed.
 *  - A short "How much does X cost?" block under the product description with the live price, stock and delivery
 *    charges. The price is read when the page is built, so the text follows every price change. Also [kk_price_faq].
 */

defined( 'ABSPATH' ) || exit;

/** "1-2", "1–2", "৩" → array( min, max ) in whole days, or null. */
function kk_seo_days( $text ) {
	$text = strtr( (string) $text, array( '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9' ) );
	if ( ! preg_match_all( '/\d+/', $text, $m ) ) {
		return null;
	}
	$n = array_map( 'intval', $m[0] );
	return array( min( $n ), max( $n ) );
}

/** A charge typed in the settings ('' = not set) → float or null. */
function kk_seo_cost( $text ) {
	$text = trim( strtr( (string) $text, array( '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9', ',' => '' ) ) );
	return ( '' !== $text && is_numeric( $text ) && (float) $text >= 0 ) ? (float) $text : null;
}

/** Delivery charges of the shop's own country from WooCommerce shipping (the checkout's delivery areas). */
function kk_seo_shipping_costs() {
	$costs = array();
	if ( function_exists( 'kk_area_methods' ) ) {
		foreach ( kk_area_methods() as $m ) {
			if ( isset( $m['cost'] ) && is_numeric( $m['cost'] ) ) {
				$costs[] = (float) $m['cost'];
			}
		}
	}
	return $costs;
}

/** The shop's country code (WooCommerce → Settings → General). */
function kk_seo_country() {
	$c = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_base_country() : '';
	return $c ? $c : (string) kk_opt( 'co_country' );
}

/** OfferShippingDetails list from the settings (empty when nothing usable is set). */
function kk_seo_shipping_details() {
	static $out = null;
	if ( null !== $out ) {
		return $out;
	}
	$out      = array();
	$country  = kk_seo_country();
	$costs    = kk_seo_shipping_costs();
	$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'BDT';
	$handling = kk_seo_days( kk_opt( 'seo_handling_days' ) );
	$region   = (string) kk_opt( 'seo_ship_local_region' );
	$rows     = array();
	if ( '' !== $region && 0 === strpos( $region, $country . '-' ) ) { // a region code of another country is ignored
		$local = kk_seo_cost( kk_opt( 'seo_ship_local_cost' ) );
		if ( null === $local && $costs ) {
			$local = min( $costs );
		}
		$rows[] = array( $local, $region, kk_seo_days( kk_opt( 'seo_ship_local_days' ) ) );
	}
	$other = kk_seo_cost( kk_opt( 'seo_ship_other_cost' ) );
	if ( null === $other && $costs ) {
		$other = max( $costs );
	}
	$rows[] = array( $other, '', kk_seo_days( kk_opt( 'seo_ship_other_days' ) ) );
	foreach ( $rows as $r ) {
		list( $cost, $reg, $days ) = $r;
		if ( null === $cost || ! $country ) {
			continue; // no charge known: better no shipping data than a wrong one
		}
		$dest = array( '@type' => 'DefinedRegion', 'addressCountry' => $country );
		if ( $reg ) {
			$dest['addressRegion'] = $reg;
		}
		$item = array(
			'@type'               => 'OfferShippingDetails',
			'shippingRate'        => array( '@type' => 'MonetaryAmount', 'value' => $cost, 'currency' => $currency ),
			'shippingDestination' => $dest,
		);
		if ( $days || $handling ) {
			$time = array( '@type' => 'ShippingDeliveryTime' );
			foreach ( array( 'handlingTime' => $handling, 'transitTime' => $days ) as $k => $d ) {
				if ( $d ) {
					$time[ $k ] = array( '@type' => 'QuantitativeValue', 'minValue' => $d[0], 'maxValue' => $d[1], 'unitCode' => 'DAY' );
				}
			}
			$item['deliveryTime'] = $time;
		}
		$out[] = $item;
	}
	return $out;
}

/** Is the product in one of the "no returns" categories (slugs, comma separated)? */
function kk_seo_no_return_product( $product ) {
	$slugs = array_filter( array_map( 'sanitize_title', explode( ',', (string) kk_opt( 'seo_return_no_cats' ) ) ) );
	if ( ! $slugs || ! $product instanceof WC_Product ) {
		return false;
	}
	$id = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	return has_term( $slugs, 'product_cat', $id );
}

/** MerchantReturnPolicy for one product, or null when the setting is "don't add". */
function kk_seo_return_policy( $product ) {
	$mode    = (string) kk_opt( 'seo_return_mode' );
	$country = kk_seo_country();
	if ( ! in_array( $mode, array( 'days', 'no' ), true ) || ! $country ) {
		return null;
	}
	$policy = array( '@type' => 'MerchantReturnPolicy', 'applicableCountry' => $country );
	if ( 'no' === $mode || kk_seo_no_return_product( $product ) ) {
		$policy['returnPolicyCategory'] = 'https://schema.org/MerchantReturnNotPermitted';
	} else {
		$policy['returnPolicyCategory'] = 'https://schema.org/MerchantReturnFiniteReturnWindow';
		$policy['merchantReturnDays']   = max( 1, (int) kk_opt( 'seo_return_days' ) );
		$policy['returnMethod']         = 'https://schema.org/ReturnByMail';
		$policy['returnFees']           = 'free' === kk_opt( 'seo_return_fees' ) ? 'https://schema.org/FreeReturn' : 'https://schema.org/ReturnFeesCustomerResponsibility';
	}
	$page = (int) kk_opt( 'seo_return_page' );
	if ( ! $page ) {
		$page = (int) get_option( 'woocommerce_refund_returns_page_id' );
	}
	if ( $page && 'publish' === get_post_status( $page ) ) {
		$policy['merchantReturnLink'] = get_permalink( $page );
	}
	return $policy;
}

/** Add the missing fields to one Offer / AggregateOffer array. */
function kk_seo_fill_offer( $offer, $product ) {
	if ( ! is_array( $offer ) ) {
		return $offer;
	}
	$ship = kk_seo_shipping_details();
	if ( $ship && ! isset( $offer['shippingDetails'] ) ) {
		$offer['shippingDetails'] = $ship;
	}
	$ret = kk_seo_return_policy( $product );
	if ( $ret && ! isset( $offer['hasMerchantReturnPolicy'] ) ) {
		$offer['hasMerchantReturnPolicy'] = $ret;
	}
	return $offer;
}

if ( kk_opt( 'seo_schema_enable' ) ) {
	// Rank Math's Product schema (Rank Math turns WooCommerce's own markup off).
	add_filter( 'rank_math/snippet/rich_snippet_product_entity', function ( $entity ) {
		if ( ! is_array( $entity ) || empty( $entity['offers'] ) || ! is_array( $entity['offers'] ) ) {
			return $entity;
		}
		$product = function_exists( 'is_product' ) && is_product() ? wc_get_product( get_queried_object_id() ) : null;
		if ( isset( $entity['offers']['@type'] ) ) {
			$entity['offers'] = kk_seo_fill_offer( $entity['offers'], $product ); // one Offer or an AggregateOffer
		} else {
			foreach ( $entity['offers'] as $i => $offer ) {
				$entity['offers'][ $i ] = kk_seo_fill_offer( $offer, $product ); // a list of offers
			}
		}
		return $entity;
	}, 20 );
	// WooCommerce's own Product markup (shops without an SEO plugin, or with Yoast/others that keep it).
	add_filter( 'woocommerce_structured_data_product_offer', function ( $offer, $product ) {
		return kk_seo_fill_offer( $offer, $product );
	}, 20, 2 );
}

/** "Inside Dhaka ৳80, Outside Dhaka ৳150" from the shipping settings. */
function kk_seo_delivery_text() {
	$parts = array();
	if ( function_exists( 'kk_area_methods' ) ) {
		foreach ( kk_area_methods() as $m ) {
			if ( isset( $m['cost'] ) && is_numeric( $m['cost'] ) ) {
				$parts[] = trim( $m['title'] ) . ' ' . kk_seo_plain_price( $m['cost'] );
			}
		}
	}
	return implode( ', ', $parts );
}

/** wc_price() as plain text ("৳ 450.00"). */
function kk_seo_plain_price( $amount ) {
	return trim( html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' ) );
}

/** The "How much does X cost?" block for a product ('' when it has no price). */
function kk_price_faq_html( $product ) {
	if ( ! $product instanceof WC_Product || '' === (string) $product->get_price() ) {
		return '';
	}
	if ( $product->is_type( 'variable' ) && $product->get_variation_price( 'min' ) !== $product->get_variation_price( 'max' ) ) {
		$price = kk_seo_plain_price( $product->get_variation_price( 'min' ) ) . ' – ' . kk_seo_plain_price( $product->get_variation_price( 'max' ) );
	} else {
		$price = kk_seo_plain_price( $product->get_price() );
	}
	if ( $product->is_in_stock() ) {
		$stock = $product->is_on_backorder() ? __( 'available on order', 'kenakata-kit' ) : __( 'in stock', 'kenakata-kit' );
	} else {
		$stock = __( 'out of stock right now', 'kenakata-kit' );
	}
	$vars = array(
		'{name}'     => esc_html( $product->get_name() ),
		'{price}'    => '<strong>' . esc_html( $price ) . '</strong>',
		'{stock}'    => esc_html( $stock ),
		'{site}'     => esc_html( get_bloginfo( 'name' ) ),
		'{delivery}' => esc_html( kk_seo_delivery_text() ),
	);
	$tag   = in_array( kk_opt( 'seo_pricefaq_tag' ), array( 'h2', 'h3', 'h4' ), true ) ? kk_opt( 'seo_pricefaq_tag' ) : 'h3';
	$title = strtr( esc_html( (string) kk_opt( 'seo_pricefaq_title' ) ), array( '{name}' => $vars['{name}'] ) );
	$body  = '';
	foreach ( preg_split( '/\r\n|\r|\n/', (string) kk_opt( 'seo_pricefaq_text' ) ) as $line ) {
		if ( '' !== trim( $line ) ) {
			$body .= '<p>' . strtr( esc_html( trim( $line ) ), $vars ) . '</p>';
		}
	}
	if ( '' === $body ) {
		return '';
	}
	return '<div class="kk-price-faq">' . ( '' !== trim( $title ) ? '<' . $tag . ' class="kk-price-faq__title">' . $title . '</' . $tag . '>' : '' ) . $body . '</div>';
}

if ( kk_opt( 'seo_pricefaq_enable' ) ) {
	add_shortcode( 'kk_price_faq', function ( $atts ) {
		$a  = shortcode_atts( array( 'id' => 0 ), $atts, 'kk_price_faq' );
		$id = absint( $a['id'] ) ? absint( $a['id'] ) : get_the_ID();
		$GLOBALS['kk_price_faq_done'] = true; // placed by hand: don't add it again under the description
		return kk_price_faq_html( wc_get_product( $id ) );
	} );
	// Under the description of the product being viewed, once per page. Not inside <head>: SEO plugins run
	// the_content there to build descriptions, and excerpts are built from the_content too (the theme's
	// "Post excerpt" block, meta descriptions); those passes must not use up the one block. (Block themes build the
	// page body before wp_head, so "after wp_head" would be wrong.)
	add_filter( 'the_content', function ( $content ) {
		if ( ! empty( $GLOBALS['kk_price_faq_done'] ) || is_admin() || wp_doing_ajax() || is_feed() || ! function_exists( 'is_product' ) || ! is_product() || doing_action( 'wp_head' ) || doing_filter( 'get_the_excerpt' ) || doing_filter( 'wp_trim_excerpt' ) || (int) get_the_ID() !== (int) get_queried_object_id() || false !== strpos( $content, 'kk-price-faq' ) ) {
			return $content;
		}
		$html = kk_price_faq_html( wc_get_product( get_the_ID() ) );
		if ( '' !== $html ) {
			$GLOBALS['kk_price_faq_done'] = true;
			$content .= $html;
		}
		return $content;
	}, 30 );
	// A product without a description has no description tab: show the block after the summary instead (classic
	// templates; in an Elementor template place [kk_price_faq] where it should go).
	add_action( 'woocommerce_after_single_product_summary', function () {
		if ( empty( $GLOBALS['kk_price_faq_done'] ) && '' === trim( (string) get_post_field( 'post_content', get_the_ID() ) ) ) {
			$html = kk_price_faq_html( wc_get_product( get_the_ID() ) );
			if ( '' !== $html ) {
				$GLOBALS['kk_price_faq_done'] = true;
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_price_faq_html()
			}
		}
	}, 25 );
} else {
	add_shortcode( 'kk_price_faq', '__return_empty_string' );
}
