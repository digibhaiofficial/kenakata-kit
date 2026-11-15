<?php
/**
 * Simple checkout:
 *  - Fields (labels/placeholders/required from settings): name, phone, address, delivery area, email.
 *  - Country fixed to the store country; no separate shipping address; optional order notes.
 *  - Delivery area = the store country's enabled shipping methods; choosing one selects that method.
 */

defined( 'ABSPATH' ) || exit;

/** Enabled shipping methods for the store country, keyed by rate id ("flat_rate:3"). */
function kk_area_methods() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache   = array();
	$country = (string) kk_opt( 'co_country' );
	if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
		return $cache;
	}
	$zones = WC_Shipping_Zones::get_zones();
	$match = array();
	foreach ( $zones as $z ) {
		foreach ( $z['zone_locations'] as $loc ) {
			if ( 'country' === $loc->type && $loc->code === $country ) {
				$match[] = $z['shipping_methods'];
			}
		}
	}
	if ( ! $match ) {
		$match[] = ( new WC_Shipping_Zone( 0 ) )->get_shipping_methods( true );
	}
	foreach ( $match as $methods ) {
		foreach ( $methods as $m ) {
			if ( 'yes' !== $m->enabled ) {
				continue;
			}
			$cost = $m->get_option( 'cost' );
			$cache[ $m->id . ':' . $m->instance_id ] = array(
				'rate_id' => $m->id . ':' . $m->instance_id,
				'title'   => $m->get_title(),
				'cost'    => is_numeric( $cost ) ? (float) $cost : 0.0,
			);
		}
	}
	return $cache;
}

/** Human label for a stored delivery-area value (rate id, or the old inside/outside values). */
function kk_area_label( $value ) {
	$m = kk_area_methods();
	if ( isset( $m[ $value ] ) ) {
		return $m[ $value ]['title'];
	}
	return 'outside' === $value ? 'Outside Dhaka' : ( 'inside' === $value ? 'Inside Dhaka' : (string) $value );
}

/*
 * Promo code (coupon) field: shown on cart and/or checkout, or hidden. Coupons stay enabled in WooCommerce,
 * so a coupon that is already applied keeps working.
 */
$kk_coupon = (string) kk_opt( 'co_coupon' );
if ( in_array( $kk_coupon, array( 'cart', 'none' ), true ) ) {
	add_action( 'init', function () {
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
	}, 20 );
}
if ( 'both' !== $kk_coupon ) {
	add_filter( 'body_class', function ( $classes ) use ( $kk_coupon ) {
		if ( in_array( $kk_coupon, array( 'cart', 'none' ), true ) && is_checkout() ) {
			$classes[] = 'kk-no-checkout-coupon';
		}
		if ( in_array( $kk_coupon, array( 'checkout', 'none' ), true ) && is_cart() ) {
			$classes[] = 'kk-no-cart-coupon';
		}
		return $classes;
	} );
}
unset( $kk_coupon );

if ( ! kk_opt( 'co_enable' ) ) {
	return;
}

add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	$b = $fields['billing'];
	$billing = array(
		'billing_country' => array( 'type' => 'hidden', 'default' => kk_opt( 'co_country' ), 'priority' => 1, 'required' => false ),
		'billing_first_name' => array_merge( $b['billing_first_name'], array(
			'label' => kk_opt( 'co_name_label' ), 'placeholder' => kk_opt( 'co_name_placeholder' ),
			'class' => array( 'form-row-wide' ), 'priority' => 10, 'required' => true, 'autocomplete' => 'name',
		) ),
		'billing_phone' => array_merge( $b['billing_phone'], array(
			'label' => kk_opt( 'co_phone_label' ), 'placeholder' => kk_opt( 'co_phone_placeholder' ),
			'class' => array( 'form-row-wide' ), 'priority' => 20, 'required' => true,
			'validate' => array(), // Kenakata Kit checks the number itself (one clear message instead of two)
			'custom_attributes' => kk_phone_input_attrs(),
		) ),
		'billing_address_1' => array_merge( $b['billing_address_1'], array(
			'type' => kk_opt( 'co_address_textarea' ) ? 'textarea' : 'text',
			'label' => kk_opt( 'co_address_label' ), 'placeholder' => kk_opt( 'co_address_placeholder' ),
			'class' => array( 'form-row-wide' ), 'priority' => 30, 'required' => true,
			'custom_attributes' => array( 'rows' => '3' ),
		) ),
	);

	// District → thana (3.19, off until turned on). The thana list follows the district (kk_bd_thanas()).
	if ( kk_opt( 'co_district_enable' ) && 'BD' === kk_opt( 'co_country' ) ) {
		$billing['billing_state'] = array(
			'type' => 'state', 'country' => 'BD', 'label' => kk_opt( 'co_district_label' ), 'required' => true,
			'class' => array( 'form-row-wide', 'kk-district' ), 'priority' => 24, 'placeholder' => __( 'Choose your district', 'kenakata-kit' ),
		);
		$mode = (string) kk_opt( 'co_thana_mode' );
		if ( in_array( $mode, array( 'optional', 'required' ), true ) ) {
			$state   = function_exists( 'WC' ) && WC()->customer ? (string) WC()->customer->get_billing_state() : '';
			$options = array( '' => __( 'Choose your thana / upazila', 'kenakata-kit' ) ) + kk_thana_options( $state );
			$billing['billing_city'] = array(
				'type' => 'select', 'label' => kk_opt( 'co_thana_label' ), 'required' => 'required' === $mode,
				'class' => array( 'form-row-wide', 'kk-thana' ), 'priority' => 26, 'options' => $options,
				'input_class' => array( 'kk-thana__select' ),
			);
		}
	}

	if ( kk_opt( 'co_area_enable' ) && kk_area_methods() ) {
		$options = array();
		foreach ( kk_area_methods() as $id => $m ) {
			$options[ $id ] = $m['title'] . ( $m['cost'] > 0 ? ' — ' . wp_strip_all_tags( html_entity_decode( wc_price( $m['cost'] ) ) ) : '' );
		}
		$chosen = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods' ) : array();
		$billing['billing_delivery_area'] = array(
			'type' => 'radio', 'label' => kk_opt( 'co_area_label' ), 'required' => true, 'options' => $options,
			'class' => array( 'form-row-wide', 'kk-delivery-area' ), 'priority' => 40,
			'default' => WC()->session && WC()->session->get( 'kk_area_chosen' ) && isset( $options[ $chosen[0] ?? '' ] ) ? $chosen[0] : '',
		);
	}

	if ( kk_opt( 'co_email_show' ) ) {
		$billing['billing_email'] = array_merge( $b['billing_email'], array(
			'label' => kk_opt( 'co_email_label' ), 'placeholder' => kk_opt( 'co_email_placeholder' ),
			'class' => array( 'form-row-wide' ), 'priority' => 50, 'required' => (bool) kk_opt( 'co_email_required' ),
		) );
	}

	$fields['billing']  = $billing;
	$fields['shipping'] = array();
	if ( kk_opt( 'co_hide_notes' ) ) {
		$fields['order'] = array();
	}
	return $fields;
}, 20 );

add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );

// "Full name is a required field." instead of WooCommerce's "Billing Full name is …" (there is only one address).
add_filter( 'woocommerce_checkout_required_field_notice', function ( $notice, $label, $key = '' ) {
	if ( 0 !== strpos( (string) $key, 'billing_' ) ) {
		return $notice;
	}
	/* translators: %s: field name */
	return sprintf( __( '%s is a required field.', 'woocommerce' ), '<strong>' . esc_html( $label ) . '</strong>' );
}, 10, 3 );
if ( kk_opt( 'co_hide_notes' ) ) {
	add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
}

// Form heading ("Billing details" / "Billing & Shipping").
add_filter( 'gettext', function ( $text, $original, $domain ) {
	if ( 'woocommerce' === $domain && in_array( $original, array( 'Billing &amp; Shipping', 'Billing details' ), true ) && kk_opt( 'co_heading' ) ) {
		return kk_opt( 'co_heading' );
	}
	return $text;
}, 10, 3 );

add_filter( 'woocommerce_order_button_text', function ( $t ) {
	return kk_opt( 'co_button_text' ) ? kk_opt( 'co_button_text' ) : $t;
} );

/* ---------- Delivery area -> chosen shipping method ---------- */
function kk_choose_area( $rate_id ) {
	if ( ! WC()->session ) {
		return;
	}
	$valid = isset( kk_area_methods()[ $rate_id ] );
	WC()->session->set( 'kk_area_chosen', $valid ? 1 : 0 );
	if ( $valid ) {
		WC()->session->set( 'chosen_shipping_methods', array( $rate_id ) );
	}
}

add_action( 'woocommerce_checkout_update_order_review', function ( $post_data ) {
	parse_str( (string) $post_data, $data );
	if ( isset( $data['billing_delivery_area'] ) ) {
		$rate_id = wc_clean( wp_unslash( $data['billing_delivery_area'] ) );
		kk_choose_area( $rate_id );
		// WooCommerce applies the order summary's shipping radios after this hook; make them agree with the area.
		if ( isset( kk_area_methods()[ $rate_id ] ) ) {
			$_POST['shipping_method'] = array( $rate_id ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the update_order_review nonce
		}
	}
	if ( WC()->customer ) {
		WC()->customer->set_billing_country( kk_opt( 'co_country' ) );
		WC()->customer->set_shipping_country( kk_opt( 'co_country' ) );
	}
} );

add_action( 'woocommerce_checkout_process', function () {
	if ( isset( $_POST['billing_delivery_area'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verified the checkout nonce
		kk_choose_area( wc_clean( wp_unslash( $_POST['billing_delivery_area'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
} );

/** The chosen delivery area (rate id) once the customer picked one, else ''. */
function kk_chosen_area() {
	if ( ! WC()->session || ! WC()->session->get( 'kk_area_chosen' ) ) {
		return '';
	}
	$chosen = (array) WC()->session->get( 'chosen_shipping_methods' );
	return isset( $chosen[0], kk_area_methods()[ $chosen[0] ] ) ? $chosen[0] : '';
}

add_filter( 'woocommerce_cart_shipping_packages', function ( $packages ) {
	foreach ( $packages as &$package ) {
		$package['destination']['country'] = kk_opt( 'co_country' );
		$package['kk_area']                = kk_opt( 'co_area_enable' ) ? kk_chosen_area() : ''; // changes the package hash, so rates refresh
	}
	return $packages;
} );

// After an area is chosen, the order summary only shows that area's delivery charge.
add_filter( 'woocommerce_package_rates', function ( $rates, $package ) {
	$area = isset( $package['kk_area'] ) ? $package['kk_area'] : '';
	return $area && isset( $rates[ $area ] ) ? array( $area => $rates[ $area ] ) : $rates;
}, 20, 2 );

add_filter( 'woocommerce_checkout_posted_data', function ( $data ) {
	$data['billing_country']  = kk_opt( 'co_country' );
	$data['shipping_country'] = kk_opt( 'co_country' );
	if ( ! empty( $data['billing_phone'] ) && kk_clean_phone( $data['billing_phone'] ) ) {
		$data['billing_phone'] = kk_clean_phone( $data['billing_phone'] );
	}
	if ( ! empty( $data['billing_delivery_area'] ) && isset( kk_area_methods()[ $data['billing_delivery_area'] ] ) ) {
		$data['shipping_method'] = array( $data['billing_delivery_area'] );
	}
	return $data;
} );

add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	if ( empty( $data['billing_phone'] ) || ! kk_phone_ok( $data['billing_phone'] ) ) {
		$errors->add( 'validation', kk_opt( 'co_phone_error' ) );
	}
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order', function ( $order, $data ) {
	if ( ! empty( $data['billing_delivery_area'] ) ) {
		$order->update_meta_data( '_kk_delivery_area', kk_area_label( $data['billing_delivery_area'] ) );
	}
}, 10, 2 );

add_action( 'woocommerce_admin_order_data_after_billing_address', function ( $order ) {
	$label = $order->get_meta( '_kk_delivery_area' );
	$label = $label ? $label : ( $order->get_meta( '_billing_delivery_area' ) ? kk_area_label( $order->get_meta( '_billing_delivery_area' ) ) : '' );
	if ( $label ) {
		printf( '<p><strong>%s:</strong> %s</p>', esc_html( kk_opt( 'co_area_label' ) ), esc_html( $label ) );
	}
} );

// Prompt shown in the order summary until an area is chosen (read by the JS).
add_action( 'woocommerce_review_order_before_payment', function () {
	if ( kk_opt( 'co_area_enable' ) && kk_area_methods() ) {
		echo '<p class="kk-area-prompt" role="status">' . esc_html( kk_opt( 'co_area_prompt' ) ) . '</p>';
	}
} );

/* ---------- Cart: delivery charges as information ---------- */
if ( kk_opt( 'co_cart_hide_shipping' ) ) {
	add_filter( 'woocommerce_cart_ready_to_calc_shipping', function ( $ready ) {
		return is_cart() ? false : $ready;
	} );
	add_action( 'woocommerce_cart_totals_before_order_total', function () {
		$parts = array();
		foreach ( kk_area_methods() as $m ) {
			$parts[] = esc_html( $m['title'] ) . ' ' . wc_price( $m['cost'] );
		}
		if ( $parts ) {
			// data-title: WooCommerce shows it as the row label on phones, where the <th> is hidden.
			printf( '<tr class="kk-delivery-note"><th>%1$s</th><td data-title="%1$s">%2$s<br><small>%3$s</small></td></tr>', esc_html__( 'Delivery charge', 'kenakata-kit' ), wp_kses_post( implode( ' · ', $parts ) ), esc_html( kk_opt( 'co_cart_note' ) ) );
		}
	} );
}

/* =========================================================================
 * District → thana data (3.19)
 * ====================================================================== */

/** All districts (WooCommerce state codes) with their thanas: code => array( bengali name, array( array( en, bn ), … ) ). */
function kk_bd_thanas() {
	static $data = null;
	if ( null === $data ) {
		$data = (array) apply_filters( 'kk_bd_thanas', include KK_DIR . '/includes/data/bd-thanas.php' );
	}
	return $data;
}

/** Use the Bengali names on a Bengali site. */
function kk_bd_names_bn() {
	return 0 === strpos( determine_locale(), 'bn' );
}

/** Thana options for a district (value = English name, saved in the order's "city"), plus "Other". */
function kk_thana_options( $state ) {
	$all = kk_bd_thanas();
	$out = array();
	if ( isset( $all[ $state ] ) ) {
		$bn = kk_bd_names_bn();
		foreach ( $all[ $state ][1] as $t ) {
			$out[ $t[0] ] = $bn ? $t[1] : $t[0];
		}
	}
	$out['Other'] = __( 'Other (write it in the address)', 'kenakata-kit' );
	return $out;
}

if ( kk_opt( 'co_district_enable' ) ) {
	// The thana list for each district, for the checkout script (only on the checkout page).
	add_action( 'wp_enqueue_scripts', function () {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		$bn   = kk_bd_names_bn();
		$list = array();
		foreach ( kk_bd_thanas() as $code => $d ) {
			$list[ $code ] = array_map( function ( $t ) use ( $bn ) { return array( $t[0], $bn ? $t[1] : $t[0] ); }, $d[1] );
		}
		$js = 'window.kkThanas=' . wp_json_encode( array( 'list' => $list, 'choose' => __( 'Choose your thana / upazila', 'kenakata-kit' ), 'other' => __( 'Other (write it in the address)', 'kenakata-kit' ) ) ) . ';'
			. '(function(){function fill(){var d=document.getElementById("billing_state"),t=document.getElementById("billing_city");if(!d||!t||t.tagName!=="SELECT")return;var keep=t.value,L=kkThanas.list[d.value]||[];t.innerHTML="";var o=document.createElement("option");o.value="";o.textContent=kkThanas.choose;t.appendChild(o);L.forEach(function(x){var e=document.createElement("option");e.value=x[0];e.textContent=x[1];t.appendChild(e);});var e2=document.createElement("option");e2.value="Other";e2.textContent=kkThanas.other;t.appendChild(e2);t.value=keep;if(t.value!==keep)t.value="";if(window.jQuery)jQuery(t).trigger("change.select2");}'
			. 'document.addEventListener("change",function(e){if(e.target&&e.target.id==="billing_state")fill();});if(window.jQuery){jQuery(document.body).on("change","#billing_state",fill).on("updated_checkout",fill);}document.addEventListener("DOMContentLoaded",fill);})();';
		wp_register_script( 'kenakata-kit-thana', '', array(), KK_VERSION, true );
		wp_enqueue_script( 'kenakata-kit-thana' );
		wp_add_inline_script( 'kenakata-kit-thana', $js );
	}, 30 );

	// A thana that does not belong to the chosen district is not kept (except "Other").
	add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
		$mode = (string) kk_opt( 'co_thana_mode' );
		if ( ! in_array( $mode, array( 'optional', 'required' ), true ) || empty( $data['billing_city'] ) ) {
			return;
		}
		$opts = kk_thana_options( (string) ( $data['billing_state'] ?? '' ) );
		if ( ! isset( $opts[ $data['billing_city'] ] ) ) {
			$errors->add( 'kk_thana', __( 'Please choose your thana / upazila again: it does not match the district.', 'kenakata-kit' ) );
		}
	}, 15, 2 );
}

