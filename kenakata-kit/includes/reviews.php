<?php
/**
 * Product reviews: rating summary (average + 5-to-1 star bars), customer photos, tidy review cards, and a review form
 * where customers can attach photos. Everything uses WooCommerce's own reviews (comments with a rating), so
 * existing reviews, "verified owner" labels and Products → Reviews in the admin keep working.
 *
 * Photos: checked (JPG/PNG/WebP, size, count, who may add them) before the review is saved, stored in the Media
 * Library attached to the product, and listed in the comment meta "kk_review_images".
 */

defined( 'ABSPATH' ) || exit;

/*
 * "Allow reviews on every product": imported products often have their own "Enable reviews" switched off.
 * Only applies while reviews are enabled in WooCommerce → Settings → Products; the products are not changed.
 */
if ( kk_opt( 'rv_open_all' ) ) {
	add_filter( 'comments_open', function ( $open, $post_id ) {
		if ( $open || ! function_exists( 'wc_reviews_enabled' ) || ! wc_reviews_enabled() ) {
			return $open;
		}
		return 'product' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id ) ? true : $open;
	}, 20, 2 );
}

/** Reviews tab in the product tabs (off when the "Product Reviews" widget shows them elsewhere). */
add_filter( 'woocommerce_product_tabs', function ( $tabs ) {
	if ( ! kk_opt( 'rv_tab' ) ) {
		unset( $tabs['reviews'] );
	}
	return $tabs;
}, 98 );

/** Attachment IDs of the photos of one review. */
function kk_review_images( $comment_id ) {
	$ids = get_comment_meta( $comment_id, 'kk_review_images', true );
	return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : array();
}

/** Can photos be added to reviews right now? ('buyers' is checked again by email when the review is sent.) */
function kk_review_photos_allowed() {
	if ( ! kk_opt( 'rv_enable' ) || ! kk_opt( 'rv_photos' ) ) {
		return false;
	}
	return 'logged_in' === kk_opt( 'rv_photos_who' ) ? is_user_logged_in() : true;
}

/** Rating numbers for the summary. */
function kk_review_stats( WC_Product $product ) {
	$raw    = $product->get_rating_counts();
	$counts = array();
	$total  = 0;
	for ( $i = 5; $i >= 1; $i-- ) {
		$counts[ $i ] = isset( $raw[ $i ] ) ? (int) $raw[ $i ] : 0;
		$total       += $counts[ $i ];
	}
	return array( 'counts' => $counts, 'rated' => $total, 'average' => (float) $product->get_average_rating(), 'reviews' => (int) $product->get_review_count() );
}

/** Photos from approved reviews of a product, newest first: list of [attachment_id, comment]. */
function kk_product_review_photos( $product_id, $limit = 12 ) {
	$out      = array();
	$comments = get_comments( array(
		'post_id'  => $product_id,
		'status'   => 'approve',
		'meta_key' => 'kk_review_images', // phpcs:ignore WordPress.DB.SlowDBQuery
		'number'   => 60,
	) );
	foreach ( $comments as $c ) {
		foreach ( kk_review_images( $c->comment_ID ) as $att ) {
			if ( wp_attachment_is_image( $att ) ) {
				$out[] = array( $att, $c );
				if ( count( $out ) >= $limit ) {
					return $out;
				}
			}
		}
	}
	return $out;
}

/** One photo thumbnail that opens the lightbox. */
function kk_review_photo_link( $att, $group, $author ) {
	$full  = wp_get_attachment_image_url( $att, 'large' );
	/* translators: %s: reviewer name */
	$alt   = sprintf( __( 'Photo from %s', 'kenakata-kit' ), $author );
	return '<a class="kk-review-photo" href="' . esc_url( $full ) . '" data-kk-lightbox="' . esc_attr( $group ) . '" aria-label="' . esc_attr( $alt ) . '">'
		. wp_get_attachment_image( $att, 'thumbnail', false, array( 'alt' => $alt, 'loading' => 'lazy', 'class' => 'kk-review-photo__img' ) ) . '</a>';
}

/** The photo field of the review form. */
function kk_review_photo_field() {
	if ( ! kk_review_photos_allowed() ) {
		return '';
	}
	$max = max( 1, absint( kk_opt( 'rv_photos_max' ) ) );
	$mb  = max( 1, absint( kk_opt( 'rv_photos_size' ) ) );
	return '<div class="comment-form-kk-photos kk-photo-field" data-max="' . esc_attr( $max ) . '" data-max-mb="' . esc_attr( $mb ) . '">'
		. '<span class="kk-photo-field__label" id="kk-photo-label">' . esc_html( kk_opt( 'rv_photo_label' ) ) . '</span>'
		. '<input type="file" class="kk-photo-field__input" id="kk_review_images" name="kk_review_images[]" accept="image/jpeg,image/png,image/webp" multiple aria-labelledby="kk-photo-label" aria-describedby="kk-photo-hint">'
		. '<label for="kk_review_images" class="kk-photo-field__drop">' . kk_icon( 'camera' ) . '<span>' . esc_html__( 'Choose photos', 'kenakata-kit' ) . '</span></label>'
		/* translators: 1: number of photos, 2: megabytes */
		. '<p class="kk-photo-field__hint" id="kk-photo-hint">' . esc_html( sprintf( __( 'Up to %1$d photos · JPG, PNG or WebP · max %2$d MB each', 'kenakata-kit' ), $max, $mb ) ) . '</p>'
		. '<div class="kk-photo-field__preview" aria-live="polite"></div></div>';
}

/* -------------------------------------------------------------------------
 * Saving photos with a review
 * ---------------------------------------------------------------------- */

/** Uploaded review photos as a clean list of single-file arrays (empty slots skipped). */
function kk_review_uploaded_files() {
	if ( empty( $_FILES['kk_review_images'] ) || ! is_array( $_FILES['kk_review_images']['name'] ?? null ) ) { // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		return array();
	}
	$f   = $_FILES['kk_review_images']; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- each file is validated below
	$out = array();
	foreach ( array_keys( (array) $f['name'] ) as $i ) {
		if ( UPLOAD_ERR_NO_FILE === (int) $f['error'][ $i ] ) {
			continue;
		}
		$out[] = array(
			'name'     => sanitize_file_name( (string) $f['name'][ $i ] ),
			'type'     => (string) $f['type'][ $i ],
			'tmp_name' => (string) $f['tmp_name'][ $i ],
			'error'    => (int) $f['error'][ $i ],
			'size'     => (int) $f['size'][ $i ],
		);
	}
	return $out;
}

function kk_review_mimes() {
	return array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp' );
}

function kk_review_photo_error( $message ) {
	wp_die( esc_html( $message ), esc_html__( 'Review not sent', 'kenakata-kit' ), array( 'response' => 400, 'back_link' => true ) );
}

// Check the photos before the review is saved (a review with bad photos is not saved at all).
add_filter( 'preprocess_comment', function ( $data ) {
	$files = kk_review_uploaded_files();
	if ( ! $files ) {
		return $data;
	}
	$post_id = isset( $data['comment_post_ID'] ) ? absint( $data['comment_post_ID'] ) : 0;
	if ( 'product' !== get_post_type( $post_id ) || ! kk_review_photos_allowed() ) {
		kk_review_photo_error( __( 'Photos cannot be added to this review.', 'kenakata-kit' ) );
	}
	if ( 'buyers' === kk_opt( 'rv_photos_who' ) && ! wc_customer_bought_product( (string) ( $data['comment_author_email'] ?? '' ), get_current_user_id(), $post_id ) ) {
		kk_review_photo_error( __( 'Only customers who bought this product can add photos. Please send your review without photos.', 'kenakata-kit' ) );
	}
	// At most 10 reviews with photos per connection per hour (stops someone filling the server with pictures).
	$rate_key = 'kk_rvp_' . md5( kk_client_ip() );
	$sent     = (int) get_transient( $rate_key );
	if ( $sent >= 10 && ! current_user_can( 'moderate_comments' ) ) {
		kk_review_photo_error( __( 'Too many reviews with photos from this connection. Please try again in an hour.', 'kenakata-kit' ) );
	}
	set_transient( $rate_key, $sent + 1, HOUR_IN_SECONDS );
	$max = max( 1, absint( kk_opt( 'rv_photos_max' ) ) );
	if ( count( $files ) > $max ) {
		/* translators: %d: number of photos */
		kk_review_photo_error( sprintf( __( 'You can add up to %d photos.', 'kenakata-kit' ), $max ) );
	}
	$limit = max( 1, absint( kk_opt( 'rv_photos_size' ) ) ) * MB_IN_BYTES;
	foreach ( $files as $file ) {
		if ( UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			kk_review_photo_error( __( 'A photo could not be uploaded. Please try again.', 'kenakata-kit' ) );
		}
		if ( $file['size'] > $limit ) {
			/* translators: %d: megabytes */
			kk_review_photo_error( sprintf( __( 'Each photo must be smaller than %d MB.', 'kenakata-kit' ), absint( kk_opt( 'rv_photos_size' ) ) ) );
		}
		$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], kk_review_mimes() );
		if ( empty( $check['type'] ) || false === @getimagesize( $file['tmp_name'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			kk_review_photo_error( __( 'Only JPG, PNG or WebP photos can be added.', 'kenakata-kit' ) );
		}
	}
	$GLOBALS['kk_review_files'] = $files;
	return $data;
}, 5 );

// Reviews with photos wait for approval (when that setting is on).
add_filter( 'pre_comment_approved', function ( $approved ) {
	if ( ! empty( $GLOBALS['kk_review_files'] ) && kk_opt( 'rv_photos_hold' ) && 1 === $approved ) {
		return 0;
	}
	return $approved;
}, 20 );

// Store the photos once the review exists.
add_action( 'comment_post', function ( $comment_id, $approved, $data ) {
	if ( empty( $GLOBALS['kk_review_files'] ) ) {
		return;
	}
	$files = $GLOBALS['kk_review_files'];
	unset( $GLOBALS['kk_review_files'] );
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$product_id = absint( $data['comment_post_ID'] ?? 0 );
	$author     = wp_strip_all_tags( (string) ( $data['comment_author'] ?? '' ) );
	$ids        = array();
	foreach ( $files as $n => $file ) {
		$_FILES['kk_review_image'] = $file; // media_handle_upload() reads one file from $_FILES
		/* translators: 1: reviewer name, 2: photo number */
		$title = sprintf( __( 'Review photo by %1$s (%2$d)', 'kenakata-kit' ), $author, $n + 1 );
		$att   = media_handle_upload( 'kk_review_image', $product_id, array( 'post_title' => $title ), array( 'test_form' => false, 'mimes' => kk_review_mimes() ) );
		if ( ! is_wp_error( $att ) ) {
			update_post_meta( $att, '_kk_review_comment', (int) $comment_id );
			$ids[] = (int) $att;
		}
	}
	unset( $_FILES['kk_review_image'] );
	if ( $ids ) {
		update_comment_meta( $comment_id, 'kk_review_images', $ids );
	}
}, 10, 3 );

// A review deleted for good takes its photos with it.
add_action( 'delete_comment', function ( $comment_id ) {
	foreach ( kk_review_images( $comment_id ) as $att ) {
		if ( (int) get_post_meta( $att, '_kk_review_comment', true ) === (int) $comment_id ) {
			wp_delete_attachment( $att, true );
		}
	}
} );

// Admin (Products → Reviews, Comments): show the photos under the review text.
add_filter( 'comment_text', function ( $text, $comment = null ) {
	if ( ! is_admin() || ! $comment instanceof WP_Comment ) {
		return $text;
	}
	$ids = kk_review_images( $comment->comment_ID );
	if ( ! $ids ) {
		return $text;
	}
	$thumbs = '';
	foreach ( $ids as $att ) {
		$thumbs .= '<a href="' . esc_url( wp_get_attachment_url( $att ) ) . '" target="_blank" rel="noopener">' . wp_get_attachment_image( $att, array( 60, 60 ), false, array( 'style' => 'width:60px;height:60px;object-fit:cover;border-radius:6px;margin:6px 6px 0 0' ) ) . '</a>';
	}
	return $text . '<p class="kk-admin-review-photos">' . $thumbs . '</p>';
}, 20, 2 );

/* -------------------------------------------------------------------------
 * Front end: review cards and the reviews section
 * ---------------------------------------------------------------------- */
if ( ! kk_opt( 'rv_enable' ) ) {
	return;
}

add_filter( 'comments_template', function ( $template ) {
	return 'product' === get_post_type() ? KK_DIR . '/templates/single-product-reviews.php' : $template;
}, 50 );

add_action( 'init', function () {
	// Initials instead of grey default avatars, and the same stars as the product cards.
	remove_action( 'woocommerce_review_before', 'woocommerce_review_display_gravatar', 10 );
	remove_action( 'woocommerce_review_before_comment_meta', 'woocommerce_review_display_rating', 10 );
}, 20 );

add_action( 'woocommerce_review_before', function ( $comment ) {
	$name     = trim( wp_strip_all_tags( (string) $comment->comment_author ) );
	$initials = '' === $name ? '?' : mb_strtoupper( mb_substr( $name, 0, 1 ) );
	$parts    = preg_split( '/\s+/u', $name );
	if ( count( $parts ) > 1 ) {
		$initials .= mb_strtoupper( mb_substr( end( $parts ), 0, 1 ) );
	}
	$hue = absint( crc32( $name ) ) % 360;
	echo '<span class="kk-review__avatar" style="--kk-av-h:' . esc_attr( $hue ) . '" aria-hidden="true">' . esc_html( $initials ) . '</span>';
}, 10 );

add_action( 'woocommerce_review_before_comment_meta', function ( $comment ) {
	$rating = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
	if ( $rating && wc_review_ratings_enabled() ) {
		/* translators: %d: rating */
		echo '<div class="kk-review__rating"><span class="kk-stars" style="--kk-rating:' . esc_attr( $rating ) . '" role="img" aria-label="' . esc_attr( sprintf( __( 'Rated %d out of 5', 'kenakata-kit' ), $rating ) ) . '"></span></div>';
	}
}, 10 );

add_action( 'woocommerce_review_after_comment_text', function ( $comment ) {
	$ids = kk_review_images( $comment->comment_ID );
	if ( ! $ids ) {
		return;
	}
	echo '<div class="kk-review__photos">';
	foreach ( $ids as $att ) {
		echo kk_review_photo_link( $att, 'review-' . $comment->comment_ID, wp_strip_all_tags( (string) $comment->comment_author ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
	}
	echo '</div>';
}, 10 );
