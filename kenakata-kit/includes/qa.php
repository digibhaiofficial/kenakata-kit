<?php
/**
 * Product questions & answers: a "Questions (n)" tab on product pages, a question form (name + phone),
 * and Kenakata Kit → Questions in the admin to answer them.
 * Stored as comments: type kk_question (waits until answered) with child kk_answer by the shop team.
 * A question is shown on the site only after it has an answer.
 */

defined( 'ABSPATH' ) || exit;

function kk_qa_enabled() {
	return (bool) kk_opt( 'qa_enable' ) && function_exists( 'WC' );
}

/** Answered questions of a product: list of [question comment, answer comment]. */
function kk_qa_items( $product_id ) {
	$out = array();
	$qs  = get_comments( array( 'post_id' => $product_id, 'type' => 'kk_question', 'status' => 'approve', 'orderby' => 'comment_date_gmt', 'order' => 'DESC', 'number' => 200 ) );
	foreach ( $qs as $q ) {
		$a = get_comments( array( 'parent' => $q->comment_ID, 'type' => 'kk_answer', 'status' => 'approve', 'number' => 1, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ) );
		if ( $a ) {
			$out[] = array( $q, $a[0] );
		}
	}
	return $out;
}

function kk_qa_count( $product_id ) {
	$key = 'kk_qa_count_' . $product_id;
	$n   = wp_cache_get( $key, 'kenakata' );
	if ( false === $n ) {
		$n = count( kk_qa_items( $product_id ) );
		wp_cache_set( $key, $n, 'kenakata', 300 );
	}
	return (int) $n;
}

// Never show questions/answers in normal comment lists (reviews, recent comments): only our own queries ask for them.
add_action( 'pre_get_comments', function ( $q ) {
	$type = $q->query_vars['type'] ?? '';
	$in   = (array) ( $q->query_vars['type__in'] ?? array() );
	$mine = array( 'kk_question', 'kk_answer' );
	if ( in_array( $type, $mine, true ) || array_intersect( $in, $mine ) ) {
		return;
	}
	$q->query_vars['type__not_in'] = array_values( array_unique( array_merge( (array) ( $q->query_vars['type__not_in'] ?? array() ), $mine ) ) );
} );

// The tab.
add_filter( 'woocommerce_product_tabs', function ( $tabs ) {
	global $product;
	if ( ! kk_qa_enabled() || ! $product instanceof WC_Product ) {
		return $tabs;
	}
	$n     = kk_qa_count( $product->get_id() );
	$title = kk_opt( 'qa_tab_title' ) ? kk_opt( 'qa_tab_title' ) : __( 'Questions', 'kenakata-kit' );
	$tabs['kk_qa'] = array(
		'title'    => $title . ' (' . number_format_i18n( $n ) . ')',
		'priority' => 35,
		'callback' => function () use ( $product ) {
			echo kk_qa_render( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside
		},
	);
	return $tabs;
}, 40 );

function kk_qa_render( $product ) {
	kk_need_js();
	$items = kk_qa_items( $product->get_id() );
	$team  = kk_opt( 'qa_team_name' ) ? kk_opt( 'qa_team_name' ) : sprintf( /* translators: %s: shop name */ __( '%s team', 'kenakata-kit' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
	$out   = '<div class="kk-qa" data-kk-qa>';
	$out  .= '<h2 class="kk-qa__title">' . esc_html__( 'Questions & answers', 'kenakata-kit' ) . '</h2>';
	if ( count( $items ) > 3 ) {
		$out .= '<label class="kk-qa__search"><span class="screen-reader-text">' . esc_html__( 'Search questions', 'kenakata-kit' ) . '</span>'
			. '<input type="search" placeholder="' . esc_attr__( 'Search questions…', 'kenakata-kit' ) . '" data-kk-qa-search></label>';
	}
	if ( $items ) {
		$out .= '<ol class="kk-qa__list">';
		foreach ( $items as $pair ) {
			list( $q, $a ) = $pair;
			$out .= '<li class="kk-qa__item">'
				. '<div class="kk-qa__q"><span class="kk-qa__mark" aria-hidden="true">Q</span><div><p class="kk-qa__text">' . esc_html( $q->comment_content ) . '</p>'
				. '<p class="kk-qa__meta">' . esc_html( sprintf( /* translators: 1: name, 2: date */ __( 'Asked by %1$s · %2$s', 'kenakata-kit' ), $q->comment_author, mysql2date( get_option( 'date_format' ), $q->comment_date ) ) ) . '</p></div></div>'
				. '<div class="kk-qa__a"><span class="kk-qa__mark kk-qa__mark--a" aria-hidden="true">A</span><div><div class="kk-qa__text">' . wpautop( esc_html( $a->comment_content ) ) . '</div>'
				. '<p class="kk-qa__meta"><span class="kk-qa__badge">' . esc_html( $team ) . '</span> · ' . esc_html( mysql2date( get_option( 'date_format' ), $a->comment_date ) ) . '</p></div></div>'
				. '</li>';
		}
		$out .= '</ol><p class="kk-qa__none" hidden>' . esc_html__( 'No question matches your search.', 'kenakata-kit' ) . '</p>';
	} else {
		$out .= '<p class="kk-qa__empty">' . esc_html__( 'No questions yet. Be the first to ask!', 'kenakata-kit' ) . '</p>';
	}

	$req  = kk_opt( 'qa_require_phone' );
	$uid  = wp_unique_id( 'kk-qa-' );
	$user = wp_get_current_user();
	$out .= '<form class="kk-qa__form" data-kk-qa-form novalidate>'
		. '<h3>' . esc_html__( 'Ask a question', 'kenakata-kit' ) . '</h3>'
		. ( kk_opt( 'qa_intro' ) ? '<p class="kk-qa__intro">' . esc_html( kk_opt( 'qa_intro' ) ) . '</p>' : '' )
		. '<input type="hidden" name="product" value="' . absint( $product->get_id() ) . '">'
		. '<div class="kk-qa__hp" aria-hidden="true"><label>' . esc_html__( 'Leave this empty', 'kenakata-kit' ) . '<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>'
		. '<p class="kk-qa__field"><label for="' . esc_attr( $uid ) . '-q">' . esc_html__( 'Your question', 'kenakata-kit' ) . ' <span class="required">*</span></label>'
		. '<textarea id="' . esc_attr( $uid ) . '-q" name="question" rows="3" maxlength="600" required placeholder="' . esc_attr__( 'e.g. Is this suitable for oily skin?', 'kenakata-kit' ) . '"></textarea></p>'
		. '<div class="kk-qa__row"><p class="kk-qa__field"><label for="' . esc_attr( $uid ) . '-n">' . esc_html__( 'Your name', 'kenakata-kit' ) . ' <span class="required">*</span></label>'
		. '<input id="' . esc_attr( $uid ) . '-n" type="text" name="name" maxlength="60" required autocomplete="name" value="' . esc_attr( $user->exists() ? ( $user->first_name ? $user->first_name : $user->display_name ) : '' ) . '"></p>'
		. '<p class="kk-qa__field"><label for="' . esc_attr( $uid ) . '-p">' . esc_html__( 'Phone number', 'kenakata-kit' ) . ( $req ? ' <span class="required">*</span>' : '' ) . '</label>'
		. '<input id="' . esc_attr( $uid ) . '-p" type="tel" name="phone" inputmode="numeric" maxlength="14" autocomplete="tel" placeholder="01XXXXXXXXX"' . ( $req ? ' required' : '' ) . '>'
		. '<small>' . esc_html__( 'Not shown on the site. We may call you about your question.', 'kenakata-kit' ) . '</small></p></div>'
		. '<p class="kk-qa__msg" role="status" aria-live="polite"></p>'
		. '<button type="submit" class="button kk-qa__submit">' . esc_html__( 'Send question', 'kenakata-kit' ) . '</button>'
		. '</form></div>';
	return $out;
}

// POST /wp-json/kenakata/v1/question
add_action( 'rest_api_init', function () {
	register_rest_route( 'kenakata/v1', '/question', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $req ) {
			$err = function ( $msg ) { return new WP_Error( 'kk_qa', $msg, array( 'status' => 400 ) ); };
			if ( ! kk_qa_enabled() ) {
				return $err( __( 'Questions are switched off.', 'kenakata-kit' ) );
			}
			if ( '' !== trim( (string) $req->get_param( 'website' ) ) ) {
				return array( 'ok' => true ); // honeypot: silently ignore bots
			}
			$pid  = absint( $req->get_param( 'product' ) );
			$name = sanitize_text_field( (string) $req->get_param( 'name' ) );
			$text = trim( sanitize_textarea_field( (string) $req->get_param( 'question' ) ) );
			$raw  = sanitize_text_field( (string) $req->get_param( 'phone' ) );
			if ( 'product' !== get_post_type( $pid ) || 'publish' !== get_post_status( $pid ) ) {
				return $err( __( 'Product not found.', 'kenakata-kit' ) );
			}
			if ( mb_strlen( $name ) < 2 ) {
				return $err( __( 'Please enter your name.', 'kenakata-kit' ) );
			}
			if ( mb_strlen( $text ) < 5 ) {
				return $err( __( 'Please write your question.', 'kenakata-kit' ) );
			}
			$phone = function_exists( 'kk_normalize_phone' ) ? kk_normalize_phone( $raw ) : preg_replace( '/\D+/', '', $raw );
			if ( ( kk_opt( 'qa_require_phone' ) || '' !== $raw ) && ! $phone ) {
				return $err( kk_opt( 'co_phone_error' ) ? kk_opt( 'co_phone_error' ) : __( 'Please enter a valid 11-digit mobile number.', 'kenakata-kit' ) );
			}
			// Flood guard: one question per minute per connection, at most 5 a day per phone number.
			$ip = kk_client_ip(); // the real visitor behind Cloudflare, otherwise all visitors would share a few addresses
			if ( get_transient( 'kk_qa_ip_' . md5( $ip ) ) ) {
				return $err( __( 'Please wait a minute before sending another question.', 'kenakata-kit' ) );
			}
			$day_key = 'kk_qa_ph_' . md5( $phone ? $phone : $ip );
			$today   = (int) get_transient( $day_key );
			if ( $today >= 5 ) {
				return $err( __( 'You have sent several questions today. Please call or WhatsApp us instead.', 'kenakata-kit' ) );
			}
			$id = wp_insert_comment( array(
				'comment_post_ID'      => $pid,
				'comment_author'       => $name,
				'comment_author_email' => '',
				'comment_content'      => $text,
				'comment_type'         => 'kk_question',
				'comment_approved'     => 0,
				'comment_author_IP'    => $ip,
				'comment_agent'        => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 254 ) : '',
				'user_id'              => get_current_user_id(),
			) );
			if ( ! $id ) {
				return $err( __( 'Something went wrong. Please try again.', 'kenakata-kit' ) );
			}
			if ( $phone ) {
				add_comment_meta( $id, 'kk_phone', $phone, true );
			}
			set_transient( 'kk_qa_ip_' . md5( $ip ), 1, MINUTE_IN_SECONDS );
			set_transient( $day_key, $today + 1, DAY_IN_SECONDS );
			if ( kk_opt( 'qa_notify' ) ) {
				wp_mail(
					get_option( 'admin_email' ),
					/* translators: %s: product name */
					sprintf( __( 'New product question: %s', 'kenakata-kit' ), wp_strip_all_tags( get_the_title( $pid ) ) ),
					sprintf( "%s\n\n%s: %s\n%s: %s\n\n%s: %s", $text, __( 'Name', 'kenakata-kit' ), $name, __( 'Phone', 'kenakata-kit' ), $phone ? $phone : '-', __( 'Answer it here', 'kenakata-kit' ), admin_url( 'admin.php?page=kk-questions' ) )
				);
			}
			return array( 'ok' => true, 'message' => kk_opt( 'qa_thanks' ) ? kk_opt( 'qa_thanks' ) : __( 'Thank you! Your question was sent. We will answer it soon.', 'kenakata-kit' ) );
		},
	) );
} );

/* ------------------------------------------------------------------ Admin: Kenakata Kit → Questions */
function kk_qa_waiting() {
	return (int) get_comments( array( 'type' => 'kk_question', 'status' => 'hold', 'count' => true ) );
}

add_action( 'admin_menu', function () {
	if ( ! kk_qa_enabled() ) {
		return;
	}
	$n = kk_qa_waiting();
	add_submenu_page( 'kenakata-kit', __( 'Product questions', 'kenakata-kit' ), __( 'Questions', 'kenakata-kit' ) . ( $n ? ' <span class="awaiting-mod">' . $n . '</span>' : '' ), 'moderate_comments', 'kk-questions', 'kk_qa_admin_page' );
}, 30 );

add_action( 'admin_post_kk_qa_answer', function () {
	if ( ! current_user_can( 'moderate_comments' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'kenakata-kit' ) );
	}
	$qid = absint( $_POST['question'] ?? 0 );
	check_admin_referer( 'kk_qa_' . $qid );
	$q = get_comment( $qid );
	if ( ! $q || 'kk_question' !== $q->comment_type ) {
		wp_die( esc_html__( 'Question not found.', 'kenakata-kit' ) );
	}
	$do = sanitize_key( wp_unslash( $_POST['do'] ?? 'answer' ) );
	if ( 'trash' === $do ) {
		foreach ( get_comments( array( 'parent' => $qid, 'type' => 'kk_answer' ) ) as $a ) {
			wp_trash_comment( $a->comment_ID );
		}
		wp_trash_comment( $qid );
	} else {
		$text   = trim( sanitize_textarea_field( wp_unslash( $_POST['answer'] ?? '' ) ) );
		$qtext  = trim( sanitize_textarea_field( wp_unslash( $_POST['qtext'] ?? '' ) ) );
		$answer = get_comments( array( 'parent' => $qid, 'type' => 'kk_answer', 'number' => 1 ) );
		if ( '' !== $qtext && $qtext !== $q->comment_content ) {
			wp_update_comment( array( 'comment_ID' => $qid, 'comment_content' => $qtext ) ); // fix spelling / remove personal data
		}
		if ( '' !== $text ) {
			$user = wp_get_current_user();
			if ( $answer ) {
				wp_update_comment( array( 'comment_ID' => $answer[0]->comment_ID, 'comment_content' => $text, 'comment_approved' => 1 ) );
			} else {
				wp_insert_comment( array( 'comment_post_ID' => $q->comment_post_ID, 'comment_parent' => $qid, 'comment_type' => 'kk_answer', 'comment_approved' => 1, 'comment_content' => $text, 'user_id' => $user->ID, 'comment_author' => $user->display_name, 'comment_author_email' => $user->user_email ) );
			}
			wp_set_comment_status( $qid, 'approve' );
		} elseif ( 'hide' === $do ) {
			wp_set_comment_status( $qid, 'hold' );
		}
	}
	wp_cache_delete( 'kk_qa_count_' . $q->comment_post_ID, 'kenakata' );
	wp_safe_redirect( add_query_arg( array( 'page' => 'kk-questions', 'view' => sanitize_key( wp_unslash( $_POST['view'] ?? 'waiting' ) ), 'done' => $do ), admin_url( 'admin.php' ) ) );
	exit;
} );

function kk_qa_admin_page() {
	$view   = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'waiting'; // phpcs:ignore WordPress.Security.NonceVerification
	$status = 'answered' === $view ? 'approve' : ( 'all' === $view ? 'all' : 'hold' );
	$qs     = get_comments( array( 'type' => 'kk_question', 'status' => $status, 'number' => 100, 'orderby' => 'comment_date_gmt', 'order' => 'DESC' ) );
	$tabs   = array( 'waiting' => __( 'Waiting for an answer', 'kenakata-kit' ) . ' (' . kk_qa_waiting() . ')', 'answered' => __( 'Answered', 'kenakata-kit' ), 'all' => __( 'All', 'kenakata-kit' ) );
	echo '<div class="wrap kk-qa-admin"><h1>' . esc_html__( 'Product questions', 'kenakata-kit' ) . '</h1>';
	echo '<p>' . esc_html__( 'Write an answer and click "Publish answer": the question and answer then appear in the product\'s Questions tab. Questions without an answer are never shown on the site.', 'kenakata-kit' ) . '</p>';
	if ( isset( $_GET['done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Saved.', 'kenakata-kit' ) . '</p></div>';
	}
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $label ) {
		echo '<a class="nav-tab' . ( $k === $view ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( array( 'page' => 'kk-questions', 'view' => $k ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $label ) . '</a>';
	}
	echo '</nav>';
	if ( ! $qs ) {
		echo '<p style="margin-top:20px">' . esc_html__( 'Nothing here.', 'kenakata-kit' ) . '</p></div>';
		return;
	}
	echo '<div class="kk-qa-admin__list">';
	foreach ( $qs as $q ) {
		$answer = get_comments( array( 'parent' => $q->comment_ID, 'type' => 'kk_answer', 'number' => 1 ) );
		$phone  = get_comment_meta( $q->comment_ID, 'kk_phone', true );
		$wa     = $phone && function_exists( 'kk_whatsapp_url' ) ? kk_whatsapp_url( '', $phone ) : '';
		echo '<form class="kk-qa-admin__card" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'kk_qa_' . $q->comment_ID );
		echo '<input type="hidden" name="action" value="kk_qa_answer"><input type="hidden" name="question" value="' . absint( $q->comment_ID ) . '"><input type="hidden" name="view" value="' . esc_attr( $view ) . '">';
		echo '<p class="kk-qa-admin__product"><a href="' . esc_url( get_permalink( $q->comment_post_ID ) ) . '" target="_blank">' . esc_html( get_the_title( $q->comment_post_ID ) ) . '</a> · '
			. esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $q->comment_date ) ) . ' · '
			. ( '1' === (string) $q->comment_approved ? '<span class="kk-qa-admin__ok">' . esc_html__( 'Published', 'kenakata-kit' ) . '</span>' : '<span class="kk-qa-admin__wait">' . esc_html__( 'Waiting', 'kenakata-kit' ) . '</span>' ) . '</p>';
		echo '<p><label><strong>' . esc_html__( 'Question', 'kenakata-kit' ) . '</strong> — ' . esc_html( $q->comment_author )
			. ( $phone ? ' · <a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a>' . ( $wa ? ' · <a href="' . kk_esc_link( $wa ) . '" target="_blank" rel="noopener">WhatsApp</a>' : '' ) : '' )
			. '<textarea name="qtext" rows="2" class="large-text">' . esc_textarea( $q->comment_content ) . '</textarea></label></p>';
		echo '<p><label><strong>' . esc_html__( 'Answer', 'kenakata-kit' ) . '</strong><textarea name="answer" rows="3" class="large-text" placeholder="' . esc_attr__( 'Write the answer customers will see…', 'kenakata-kit' ) . '">' . esc_textarea( $answer ? $answer[0]->comment_content : '' ) . '</textarea></label></p>';
		echo '<p class="kk-qa-admin__actions"><button class="button button-primary" name="do" value="answer">' . esc_html( $answer ? __( 'Update answer', 'kenakata-kit' ) : __( 'Publish answer', 'kenakata-kit' ) ) . '</button> ';
		if ( '1' === (string) $q->comment_approved ) {
			echo '<button class="button" name="do" value="hide">' . esc_html__( 'Hide from site', 'kenakata-kit' ) . '</button> ';
		}
		echo '<button class="button-link-delete" name="do" value="trash" onclick="return confirm(\'' . esc_js( __( 'Move this question to the bin?', 'kenakata-kit' ) ) . '\')">' . esc_html__( 'Move to bin', 'kenakata-kit' ) . '</button></p>';
		echo '</form>';
	}
	echo '</div></div>';
	echo '<style>.kk-qa-admin__list{display:grid;gap:14px;margin-top:18px;max-width:900px}.kk-qa-admin__card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 18px}.kk-qa-admin__card textarea{margin-top:6px}.kk-qa-admin__product{margin:0 0 6px;color:#646970}.kk-qa-admin__ok{color:#008a20;font-weight:600}.kk-qa-admin__wait{color:#b26200;font-weight:600}.kk-qa-admin__actions{display:flex;gap:10px;align-items:center;margin-bottom:0}</style>';
}
