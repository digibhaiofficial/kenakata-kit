<?php
/**
 * Kenakata Kit reviews section (replaces WooCommerce's single-product-reviews.php when
 * Kenakata Kit → Reviews is on). Same ids and form fields as WooCommerce, so its star picker and checks keep working.
 */

defined( 'ABSPATH' ) || exit;

global $product;
$kk_product = $product instanceof WC_Product ? $product : wc_get_product( get_the_ID() );
if ( ! $kk_product || ! comments_open() ) {
	return;
}

$kk_stats   = kk_review_stats( $kk_product );
$kk_ratings = wc_review_ratings_enabled();
$kk_title   = kk_opt( 'rv_title' ) ? kk_opt( 'rv_title' ) : __( 'Customer reviews', 'kenakata-kit' );
$kk_can     = 'no' === get_option( 'woocommerce_review_rating_verification_required' ) || wc_customer_bought_product( '', get_current_user_id(), $kk_product->get_id() );
?>
<div id="reviews" class="woocommerce-Reviews kk-reviews">
	<div class="kk-reviews__head">
		<h2 class="woocommerce-Reviews-title kk-reviews__title"><?php echo esc_html( $kk_title ); ?> <span class="kk-reviews__count">(<?php echo esc_html( number_format_i18n( $kk_stats['reviews'] ) ); ?>)</span></h2>
		<?php if ( $kk_can && ( ! kk_opt( 'rv_summary' ) || ! $kk_ratings ) ) : // with the summary the button sits next to the bars ?>
			<a class="kk-reviews__write" href="#review_form_wrapper" data-kk-write-review><?php echo kk_icon( 'star' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?><span><?php echo esc_html( kk_opt( 'rv_button_text' ) ); ?></span></a>
		<?php endif; ?>
	</div>

	<?php if ( kk_opt( 'rv_summary' ) && $kk_ratings ) : ?>
		<div class="kk-reviews__summary<?php echo $kk_stats['rated'] ? '' : ' is-empty'; ?>">
			<div class="kk-reviews__score">
				<span class="kk-reviews__avg"><?php echo esc_html( number_format_i18n( $kk_stats['average'], 1 ) ); ?></span>
				<?php /* translators: %s: average rating */ ?>
				<span class="kk-stars kk-reviews__stars" style="--kk-rating:<?php echo esc_attr( round( $kk_stats['average'], 2 ) ); ?>" role="img" aria-label="<?php echo esc_attr( sprintf( __( 'Rated %s out of 5', 'kenakata-kit' ), number_format_i18n( $kk_stats['average'], 1 ) ) ); ?>"></span>
				<span class="kk-reviews__based">
					<?php
					echo esc_html( $kk_stats['rated']
						/* translators: %s: number of ratings */
						? sprintf( _n( 'Based on %s rating', 'Based on %s ratings', $kk_stats['rated'], 'kenakata-kit' ), number_format_i18n( $kk_stats['rated'] ) )
						: __( 'No ratings yet', 'kenakata-kit' ) );
					?>
				</span>
			</div>
			<ul class="kk-reviews__bars">
				<?php foreach ( $kk_stats['counts'] as $kk_star => $kk_n ) : ?>
					<?php $kk_pct = $kk_stats['rated'] ? round( $kk_n / $kk_stats['rated'] * 100 ) : 0; ?>
					<?php /* translators: 1: star number, 2: number of ratings */ ?>
					<li class="kk-reviews__bar-row" aria-label="<?php echo esc_attr( sprintf( _n( '%1$d star: %2$s rating', '%1$d stars: %2$s ratings', $kk_n, 'kenakata-kit' ), $kk_star, number_format_i18n( $kk_n ) ) ); ?>">
						<span class="kk-reviews__bar-label" aria-hidden="true"><?php echo esc_html( number_format_i18n( $kk_star ) ); ?> <span class="kk-reviews__bar-star">&#9733;</span></span>
						<span class="kk-reviews__bar" aria-hidden="true"><span class="kk-reviews__bar-fill" style="width:<?php echo esc_attr( $kk_pct ); ?>%"></span></span>
						<span class="kk-reviews__bar-count" aria-hidden="true"><?php echo esc_html( number_format_i18n( $kk_n ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $kk_can ) : ?>
				<div class="kk-reviews__cta">
					<p><?php esc_html_e( 'Used this product? Tell other customers what you think.', 'kenakata-kit' ); ?></p>
					<a class="kk-reviews__write kk-reviews__write--big" href="#review_form_wrapper" data-kk-write-review><?php echo kk_icon( 'star' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?><span><?php echo esc_html( kk_opt( 'rv_button_text' ) ); ?></span></a>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php
	$kk_photos = kk_opt( 'rv_gallery' ) ? kk_product_review_photos( $kk_product->get_id(), 12 ) : array();
	if ( $kk_photos ) :
		?>
		<div class="kk-reviews__gallery">
			<h3 class="kk-reviews__subtitle"><?php esc_html_e( 'Photos from customers', 'kenakata-kit' ); ?></h3>
			<div class="kk-reviews__gallery-strip">
				<?php
				foreach ( $kk_photos as $kk_photo ) {
					echo kk_review_photo_link( $kk_photo[0], 'review-gallery', wp_strip_all_tags( (string) $kk_photo[1]->comment_author ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
				}
				?>
			</div>
		</div>
	<?php endif; ?>

	<div id="comments" class="kk-reviews__list-wrap">
		<?php if ( have_comments() ) : ?>
			<ol class="commentlist kk-reviews__list">
				<?php wp_list_comments( apply_filters( 'woocommerce_product_review_list_args', array( 'callback' => 'woocommerce_comments' ) ) ); ?>
			</ol>
			<?php
			if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) :
				echo '<nav class="woocommerce-pagination kk-reviews__pages">';
				paginate_comments_links(
					apply_filters(
						'woocommerce_comment_pagination_args',
						array(
							'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
							'next_text' => is_rtl() ? '&larr;' : '&rarr;',
							'type'      => 'list',
						)
					)
				);
				echo '</nav>';
			endif;
			?>
		<?php else : ?>
			<p class="woocommerce-noreviews kk-reviews__empty"><?php esc_html_e( 'There are no reviews yet. Be the first to share your experience.', 'kenakata-kit' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( $kk_can ) : ?>
		<div id="review_form_wrapper" class="kk-reviews__form">
			<div id="review_form">
				<?php
				$kk_commenter = wp_get_current_commenter();
				$kk_required  = (bool) get_option( 'require_name_email', 1 );
				$kk_form      = array(
					/* translators: %s: product name */
					'title_reply'         => have_comments() ? esc_html__( 'Write your review', 'kenakata-kit' ) : sprintf( esc_html__( 'Be the first to review “%s”', 'kenakata-kit' ), esc_html( get_the_title() ) ),
					/* translators: %s: reviewer name */
					'title_reply_to'      => esc_html__( 'Reply to %s', 'kenakata-kit' ),
					'title_reply_before'  => '<span id="reply-title" class="comment-reply-title" role="heading" aria-level="3">',
					'title_reply_after'   => '</span>',
					'comment_notes_after' => '',
					'label_submit'        => esc_html__( 'Submit review', 'kenakata-kit' ),
					'logged_in_as'        => '',
					'comment_field'       => '',
					'fields'              => array(),
				);
				$kk_fields = array(
					'author' => array( __( 'Your name', 'kenakata-kit' ), 'text', $kk_commenter['comment_author'], 'name' ),
					'email'  => array( __( 'Email', 'kenakata-kit' ), 'email', $kk_commenter['comment_author_email'], 'email' ),
				);
				foreach ( $kk_fields as $kk_key => $kk_f ) {
					$kk_form['fields'][ $kk_key ] = '<p class="comment-form-' . esc_attr( $kk_key ) . '"><label for="' . esc_attr( $kk_key ) . '">' . esc_html( $kk_f[0] ) . ( $kk_required ? '&nbsp;<span class="required">*</span>' : '' ) . '</label>'
						. '<input id="' . esc_attr( $kk_key ) . '" name="' . esc_attr( $kk_key ) . '" type="' . esc_attr( $kk_f[1] ) . '" autocomplete="' . esc_attr( $kk_f[3] ) . '" value="' . esc_attr( $kk_f[2] ) . '" size="30"' . ( $kk_required ? ' required' : '' ) . '></p>';
				}
				$kk_account = wc_get_page_permalink( 'myaccount' );
				if ( $kk_account ) {
					/* translators: 1: link start, 2: link end */
					$kk_form['must_log_in'] = '<p class="must-log-in">' . sprintf( esc_html__( 'Please %1$slog in%2$s to write a review.', 'kenakata-kit' ), '<a href="' . esc_url( $kk_account ) . '">', '</a>' ) . '</p>';
				}
				if ( $kk_ratings ) {
					$kk_form['comment_field'] = '<div class="comment-form-rating"><label for="rating" id="comment-form-rating-label">' . esc_html__( 'Your rating', 'kenakata-kit' ) . ( wc_review_ratings_required() ? '&nbsp;<span class="required">*</span>' : '' ) . '</label>'
						. '<select name="rating" id="rating" required>'
						. '<option value="">' . esc_html__( 'Rate…', 'kenakata-kit' ) . '</option>'
						. '<option value="5">' . esc_html__( 'Excellent', 'kenakata-kit' ) . '</option>'
						. '<option value="4">' . esc_html__( 'Good', 'kenakata-kit' ) . '</option>'
						. '<option value="3">' . esc_html__( 'Average', 'kenakata-kit' ) . '</option>'
						. '<option value="2">' . esc_html__( 'Not that good', 'kenakata-kit' ) . '</option>'
						. '<option value="1">' . esc_html__( 'Poor', 'kenakata-kit' ) . '</option>'
						. '</select></div>';
				}
				$kk_form['comment_field'] .= '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'Your review', 'kenakata-kit' ) . '&nbsp;<span class="required">*</span></label>'
					. '<textarea id="comment" name="comment" cols="45" rows="5" required placeholder="' . esc_attr__( 'What did you like? How did it work for you?', 'kenakata-kit' ) . '"></textarea></p>'
					. kk_review_photo_field();

				ob_start();
				comment_form( apply_filters( 'woocommerce_product_review_comment_form_args', $kk_form ) );
				$kk_html = ob_get_clean();
				if ( kk_review_photos_allowed() ) {
					$kk_html = preg_replace( '/<form\b/', '<form enctype="multipart/form-data"', $kk_html, 1 );
				}
				echo $kk_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress comment form, fields escaped above
				?>
			</div>
		</div>
	<?php else : ?>
		<p class="woocommerce-verification-required"><?php esc_html_e( 'Only logged-in customers who have bought this product can write a review.', 'kenakata-kit' ); ?></p>
	<?php endif; ?>
</div>
