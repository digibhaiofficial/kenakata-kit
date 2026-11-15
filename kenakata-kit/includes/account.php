<?php
/**
 * Login / registration popup (name, phone, email). Login works with phone or email,
 * also on the normal WooCommerce / WordPress login forms.
 */

defined( 'ABSPATH' ) || exit;

function kk_user_by_identifier( $identifier ) {
	$identifier = trim( (string) $identifier );
	if ( is_email( $identifier ) ) {
		return get_user_by( 'email', $identifier );
	}
	$phone = kk_normalize_phone( $identifier );
	if ( $phone ) {
		$user = get_user_by( 'login', $phone );
		if ( $user ) {
			return $user;
		}
		$found = get_users( array( 'meta_key' => 'billing_phone', 'meta_value' => $phone, 'number' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		return $found ? $found[0] : false;
	}
	return get_user_by( 'login', $identifier );
}

// Phone numbers work on every login form.
add_filter( 'authenticate', function ( $user, $username, $password ) {
	if ( $user instanceof WP_User || ! $username || ! kk_normalize_phone( $username ) ) {
		return $user;
	}
	$found = kk_user_by_identifier( $username );
	return $found ? wp_authenticate_username_password( null, $found->user_login, $password ) : $user;
}, 30, 3 );

if ( ! kk_opt( 'popup_enable' ) ) {
	return;
}

function kk_auth_rate_limited() {
	$key  = 'kk_auth_' . md5( kk_client_ip() );
	$hits = (int) get_transient( $key );
	set_transient( $key, $hits + 1, 15 * MINUTE_IN_SECONDS );
	return $hits >= 10;
}

add_action( 'wp_ajax_nopriv_kk_login', function () {
	check_ajax_referer( 'kk_auth', 'nonce' );
	if ( kk_auth_rate_limited() ) {
		wp_send_json_error( array( 'message' => __( 'Too many attempts. Please wait 15 minutes and try again.', 'kenakata-kit' ) ), 429 );
	}
	$user     = kk_user_by_identifier( isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '' );
	$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitised
	$signed   = $user ? wp_signon( array( 'user_login' => $user->user_login, 'user_password' => $password, 'remember' => ! empty( $_POST['remember'] ) ), is_ssl() ) : null;
	if ( is_wp_error( $signed ) && 'kk_login_locked' === $signed->get_error_code() ) {
		wp_send_json_error( array( 'message' => $signed->get_error_message() ), 429 );
	}
	if ( ! $user ) {
		do_action( 'wp_login_failed', '', new WP_Error( 'invalid_username' ) ); // unknown phone/email also counts as a wrong attempt
	}
	if ( ! $user || is_wp_error( $signed ) ) {
		wp_send_json_error( array( 'message' => __( 'Phone number / email or password is incorrect.', 'kenakata-kit' ) ) );
	}
	wp_send_json_success();
} );

add_action( 'wp_ajax_nopriv_kk_register', function () {
	check_ajax_referer( 'kk_auth', 'nonce' );
	if ( ! kk_opt( 'popup_register' ) ) {
		wp_send_json_error( array( 'message' => __( 'Registration is closed.', 'kenakata-kit' ) ) );
	}
	if ( kk_auth_rate_limited() ) {
		wp_send_json_error( array( 'message' => __( 'Too many attempts. Please wait 15 minutes and try again.', 'kenakata-kit' ) ), 429 );
	}
	$name     = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$phone_in = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
	$phone    = kk_clean_phone( $phone_in );
	$email    = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( mb_strlen( $name ) < 2 ) {
		wp_send_json_error( array( 'message' => __( 'Please enter your full name.', 'kenakata-kit' ) ) );
	}
	if ( ! $phone ) {
		wp_send_json_error( array( 'message' => kk_opt( 'co_phone_error' ) ) );
	}
	if ( kk_opt( 'popup_email_required' ) && ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'kenakata-kit' ) ) );
	}
	if ( strlen( $password ) < 6 ) {
		wp_send_json_error( array( 'message' => __( 'Password must be at least 6 characters.', 'kenakata-kit' ) ) );
	}
	if ( kk_user_by_identifier( $phone ) ) {
		wp_send_json_error( array( 'message' => __( 'An account with this phone number already exists. Please log in.', 'kenakata-kit' ) ) );
	}
	if ( $email && email_exists( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'An account with this email already exists. Please log in.', 'kenakata-kit' ) ) );
	}
	$user_id = wp_insert_user( array( 'user_login' => $phone, 'user_pass' => $password, 'user_email' => $email, 'display_name' => $name, 'first_name' => $name, 'role' => 'customer' ) );
	if ( is_wp_error( $user_id ) ) {
		wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
	}
	update_user_meta( $user_id, 'billing_first_name', $name );
	update_user_meta( $user_id, 'billing_phone', $phone );
	update_user_meta( $user_id, 'billing_country', kk_opt( 'co_country' ) );
	if ( $email ) {
		update_user_meta( $user_id, 'billing_email', $email );
	}
	do_action( 'woocommerce_created_customer', $user_id, array( 'user_login' => $phone, 'user_email' => $email ), false );
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );
	wp_send_json_success();
} );

add_shortcode( 'kk_account_link', function ( $atts ) {
	$a = shortcode_atts( array( 'login_text' => __( 'Log in', 'kenakata-kit' ), 'account_text' => __( 'My account', 'kenakata-kit' ) ), $atts, 'kk_account_link' );
	return '<a class="kk-account-link" href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '" data-kk-login>' . esc_html( is_user_logged_in() ? $a['account_text'] : $a['login_text'] ) . '</a>';
} );

add_action( 'wp_footer', function () {
	if ( is_user_logged_in() ) {
		return;
	}
	wp_enqueue_script( 'kenakata-kit' );
	$email_label = kk_opt( 'popup_email_required' ) ? __( 'Email', 'kenakata-kit' ) : __( 'Email (optional)', 'kenakata-kit' );
	?>
	<div class="kk-modal" id="kk-auth" role="dialog" aria-modal="true" aria-labelledby="kk-auth-title" hidden>
		<div class="kk-modal__backdrop" data-kk-close></div>
		<div class="kk-modal__card">
			<button type="button" class="kk-modal__close" data-kk-close aria-label="<?php esc_attr_e( 'Close', 'kenakata-kit' ); ?>">&times;</button>
			<h2 id="kk-auth-title" class="screen-reader-text"><?php esc_html_e( 'Account', 'kenakata-kit' ); ?></h2>
			<?php if ( kk_opt( 'popup_register' ) ) : ?>
			<div class="kk-tabs" role="tablist">
				<button type="button" role="tab" id="kk-tab-login" aria-selected="true" aria-controls="kk-pane-login" class="kk-tab is-active"><?php echo esc_html( kk_opt( 'popup_login_tab' ) ); ?></button>
				<button type="button" role="tab" id="kk-tab-register" aria-selected="false" aria-controls="kk-pane-register" class="kk-tab" tabindex="-1"><?php echo esc_html( kk_opt( 'popup_register_tab' ) ); ?></button>
			</div>
			<?php endif; ?>
			<form class="kk-auth-form" id="kk-pane-login" role="tabpanel" aria-labelledby="kk-tab-login" data-action="kk_login" novalidate>
				<p class="kk-field"><label for="kk-login-id"><?php esc_html_e( 'Phone number or email', 'kenakata-kit' ); ?></label><input id="kk-login-id" name="identifier" type="text" autocomplete="username" required></p>
				<p class="kk-field"><label for="kk-login-pass"><?php esc_html_e( 'Password', 'kenakata-kit' ); ?></label><input id="kk-login-pass" name="password" type="password" autocomplete="current-password" required></p>
				<p class="kk-field kk-field--row"><label><input type="checkbox" name="remember" value="1" checked> <?php esc_html_e( 'Remember me', 'kenakata-kit' ); ?></label><a href="<?php echo esc_url( wc_lostpassword_url() ); ?>"><?php esc_html_e( 'Forgot password?', 'kenakata-kit' ); ?></a></p>
				<p class="kk-form-msg" role="alert"></p>
				<button type="submit" class="kk-btn kk-btn--primary kk-btn--block"><?php echo esc_html( kk_opt( 'popup_login_tab' ) ); ?></button>
			</form>
			<?php if ( kk_opt( 'popup_register' ) ) : ?>
			<form class="kk-auth-form" id="kk-pane-register" role="tabpanel" aria-labelledby="kk-tab-register" data-action="kk_register" novalidate hidden>
				<p class="kk-field"><label for="kk-reg-name"><?php echo esc_html( kk_opt( 'co_name_label' ) ); ?></label><input id="kk-reg-name" name="name" type="text" autocomplete="name" required></p>
				<p class="kk-field"><label for="kk-reg-phone"><?php echo esc_html( kk_opt( 'co_phone_label' ) ); ?></label><input id="kk-reg-phone" name="phone" type="tel"<?php echo kk_attr_string( kk_phone_input_attrs() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_attr_string() ?> placeholder="<?php echo esc_attr( kk_opt( 'co_phone_placeholder' ) ); ?>" required></p>
				<p class="kk-field"><label for="kk-reg-email"><?php echo esc_html( $email_label ); ?></label><input id="kk-reg-email" name="email" type="email" autocomplete="email" <?php echo kk_opt( 'popup_email_required' ) ? 'required' : ''; ?>></p>
				<p class="kk-field"><label for="kk-reg-pass"><?php esc_html_e( 'Password', 'kenakata-kit' ); ?></label><input id="kk-reg-pass" name="password" type="password" autocomplete="new-password" minlength="6" required></p>
				<p class="kk-form-msg" role="alert"></p>
				<button type="submit" class="kk-btn kk-btn--primary kk-btn--block"><?php echo esc_html( kk_opt( 'popup_register_tab' ) ); ?></button>
			</form>
			<?php endif; ?>
		</div>
	</div>
	<script>window.kkAuth = <?php echo wp_json_encode( array( 'nonce' => wp_create_nonce( 'kk_auth' ) ) ); ?>;</script>
	<?php
}, 5 );
