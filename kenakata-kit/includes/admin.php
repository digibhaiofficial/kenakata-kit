<?php
/**
 * Admin page "Kenakata Kit": own top-level menu, left-hand tab list, friendly controls,
 * live preview, dashboard switches, Elementor/shortcode guides, export/import.
 * All texts are English and translatable (Bengali translation in /languages).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	$icon = KK_URL . 'assets/img/menu-icon.png'; // Kenakata Kit logo (white bag, green K) for the dark admin menu
	add_menu_page( __( 'Kenakata Kit', 'kenakata-kit' ), __( 'Kenakata Kit', 'kenakata-kit' ), 'manage_woocommerce', 'kenakata-kit', 'kk_render_settings', $icon, 56 );
	add_submenu_page( 'kenakata-kit', __( 'Dashboard', 'kenakata-kit' ), __( 'Dashboard', 'kenakata-kit' ), 'manage_woocommerce', 'kenakata-kit', 'kk_render_settings' );
	foreach ( array( 'design', 'cards', 'checkout', 'widgets', 'help' ) as $key ) {
		$tab = kk_tabs()[ $key ];
		add_submenu_page( 'kenakata-kit', $tab['label'], $tab['label'], 'manage_woocommerce', 'admin.php?page=kenakata-kit&tab=' . $key, '' );
	}
	// Old location (WooCommerce → Kenakata Kit) keeps working.
	add_submenu_page( 'woocommerce', __( 'Kenakata Kit', 'kenakata-kit' ), __( 'Kenakata Kit', 'kenakata-kit' ), 'manage_woocommerce', 'admin.php?page=kenakata-kit' );
} );

// Browser tab title follows the open section.
add_filter( 'admin_title', function ( $title ) {
	$tabs = kk_tabs();
	if ( isset( $_GET['page'], $_GET['tab'] ) && 'kenakata-kit' === $_GET['page'] && isset( $tabs[ $_GET['tab'] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		/* translators: %s: settings section name */
		return sprintf( __( '%s ‹ Kenakata Kit', 'kenakata-kit' ), $tabs[ sanitize_key( $_GET['tab'] ) ]['label'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
	return $title;
} );

add_action( 'admin_init', function () {
	register_setting( 'kk_settings_group', 'kk_settings', array( 'type' => 'array', 'sanitize_callback' => 'kk_sanitize_settings' ) );
} );
// Shop managers can open the settings page, so they must also be able to save it (options.php asks for manage_options by default).
add_filter( 'option_page_capability_kk_settings_group', function () { return 'manage_woocommerce'; } );

/** Security switches are for administrators only (shop managers can change everything else). */
function kk_can_change( $key ) {
	$key = (string) $key;
	// Security switches and the courier's API keys: administrators only.
	$admin_only = 0 === strpos( $key, 'sec_' ) || 'courier_provider' === $key || 'courier_check_enable' === $key || 'courier_pathao_store_id' === $key || 'courier_pathao_sandbox' === $key || (bool) preg_match( '/^courier_[a-z]+_on$/', $key ) || ( function_exists( 'kk_courier_is_secret_key' ) && kk_courier_is_secret_key( $key ) );
	return ! $admin_only || current_user_can( 'manage_options' );
}

/** Sanitise one value according to its field definition. */
function kk_sanitize_value( $key, $v ) {
	$fields   = kk_fields();
	$defaults = kk_defaults();
	if ( ! isset( $fields[ $key ] ) ) {
		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	}
	$f = $fields[ $key ];
	switch ( $f['type'] ) {
		case 'toggle':
			return $v ? 1 : 0;
		case 'color':
			if ( ! empty( $f['empty'] ) && '' === trim( (string) $v ) ) {
				return ''; // "use the theme/primary colour"
			}
			$hex = sanitize_hex_color( (string) $v );
			return $hex ? $hex : $defaults[ $key ];
		case 'number':
			$n = is_numeric( $v ) ? (int) round( (float) $v ) : (int) $defaults[ $key ];
			if ( isset( $f['min'] ) ) { $n = max( $f['min'], $n ); }
			if ( isset( $f['max'] ) ) { $n = min( $f['max'], $n ); }
			return $n;
		case 'textarea':
			return sanitize_textarea_field( (string) $v );
		case 'bbar_items':
			return wp_json_encode( kk_bbar_sanitize_items( (string) $v ) );
		case 'select':
		case 'choice':
		case 'theme':
			$v = (string) $v;
			if ( ! array_key_exists( $v, $f['options'] ) ) {
				return $defaults[ $key ];
			}
			return is_numeric( $v ) && is_int( $defaults[ $key ] ) ? (int) $v : sanitize_text_field( $v );
		default:
			return sanitize_text_field( (string) $v );
	}
}

/** Fields that store a value (not headings / info rows). */
function kk_value_fields() {
	return array_filter( kk_fields(), function ( $f ) {
		return ! in_array( $f['type'], array( 'heading', 'areas', 'courier_test' ), true );
	} );
}

/** Settings API callback: sanitise only the submitted tab and merge into the stored settings. */
function kk_sanitize_settings( $input ) {
	if ( ! is_array( $input ) || ! isset( $input['__tab'] ) ) {
		return is_array( $input ) ? $input : array(); // internal update (reset / import / dashboard switch)
	}
	$stored = (array) get_option( 'kk_settings', array() );
	$tab    = sanitize_key( $input['__tab'] );
	foreach ( kk_value_fields() as $key => $f ) {
		if ( $f['tab'] !== $tab || ! kk_can_change( $key ) ) {
			continue;
		}
		if ( 'secret' === $f['type'] ) {
			// API keys never go into the (autoloaded, exportable) settings: they have their own private option.
			if ( function_exists( 'kk_courier_save_secret' ) ) {
				kk_courier_save_secret( $key, isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '' );
			}
			continue;
		}
		$stored[ $key ] = kk_sanitize_value( $key, isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : null );
	}
	if ( 'courier' === ( isset( $input['__tab'] ) ? sanitize_key( $input['__tab'] ) : '' ) ) {
		$stored['courier_provider'] = ''; // 3.8/3.9 drop-down retired: the on/off switches above are now the only source
	}
	if ( ! empty( $stored['sec_force_https'] ) && ! is_ssl() && 'security' === $tab ) {
		$stored['sec_force_https'] = 0;
		add_settings_error( 'kk_settings', 'kk_https', __( 'Could not turn on https: you are in the admin over http right now. Log in again with https://… and try again.', 'kenakata-kit' ) );
	}
	return $stored;
}

/** Dashboard switches (AJAX). */
add_action( 'wp_ajax_kk_toggle', function () {
	check_ajax_referer( 'kk_admin', 'nonce' );
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'kenakata-kit' ) ), 403 );
	}
	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	$on  = ! empty( $_POST['on'] ) ? 1 : 0;
	if ( ! isset( kk_modules()[ $key ] ) ) {
		wp_send_json_error( array( 'message' => __( 'Unknown setting.', 'kenakata-kit' ) ), 400 );
	}
	if ( ! kk_can_change( $key ) ) {
		wp_send_json_error( array( 'message' => __( 'Only administrators can change security settings.', 'kenakata-kit' ) ), 403 );
	}
	if ( 'sec_force_https' === $key && $on && ! is_ssl() ) {
		wp_send_json_error( array( 'message' => __( 'You are on http right now. Log in to the admin with https://… to turn this on.', 'kenakata-kit' ) ) );
	}
	$stored         = (array) get_option( 'kk_settings', array() );
	$stored[ $key ] = $on;
	update_option( 'kk_settings', $stored );
	wp_send_json_success( array( 'on' => $on ) );
} );

/** Admin language switch (AJAX). */
add_action( 'wp_ajax_kk_set_lang', function () {
	check_ajax_referer( 'kk_admin', 'nonce' );
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'kenakata-kit' ) ), 403 );
	}
	$lang = isset( $_POST['lang'] ) ? sanitize_key( wp_unslash( $_POST['lang'] ) ) : 'auto';
	$stored               = (array) get_option( 'kk_settings', array() );
	$stored['admin_lang'] = in_array( $lang, array( 'auto', 'en', 'bn' ), true ) ? $lang : 'auto';
	update_option( 'kk_settings', $stored );
	wp_send_json_success();
} );

/** Reset one tab / everything, export and import. */
add_action( 'admin_post_kk_reset_tab', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'kenakata-kit' ) );
	}
	check_admin_referer( 'kk_reset_tab' );
	$tab    = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
	$stored = (array) get_option( 'kk_settings', array() );
	foreach ( kk_fields() as $key => $f ) {
		if ( ( 'all' === $tab || $f['tab'] === $tab ) && kk_can_change( $key ) ) {
			unset( $stored[ $key ] );
		}
	}
	if ( 'all' === $tab ) {
		$keep = array_filter( $stored, function ( $k ) { return 'admin_lang' === $k || ! kk_can_change( $k ); }, ARRAY_FILTER_USE_KEY );
		$stored = $keep;
	}
	update_option( 'kk_settings', $stored );
	wp_safe_redirect( admin_url( 'admin.php?page=kenakata-kit&tab=' . ( 'all' === $tab ? 'tools' : $tab ) . '&kk_msg=reset' ) );
	exit;
} );

add_action( 'admin_post_kk_export', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'kenakata-kit' ) );
	}
	check_admin_referer( 'kk_export' );
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=kenakata-kit-settings-' . gmdate( 'Y-m-d' ) . '.json' );
	echo wp_json_encode( array( 'plugin' => 'kenakata-kit', 'version' => KK_VERSION, 'settings' => kk_all_opts() ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	exit;
} );

/** Validate + store imported settings. Returns number of keys imported. */
function kk_import_settings( array $data ) {
	$settings = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : $data;
	$stored   = (array) get_option( 'kk_settings', array() );
	$count    = 0;
	foreach ( kk_value_fields() as $key => $f ) {
		if ( array_key_exists( $key, $settings ) && kk_can_change( $key ) ) {
			$stored[ $key ] = kk_sanitize_value( $key, $settings[ $key ] );
			$count++;
		}
	}
	if ( isset( $settings['admin_lang'] ) && in_array( $settings['admin_lang'], array( 'auto', 'en', 'bn' ), true ) ) {
		$stored['admin_lang'] = $settings['admin_lang'];
	}
	if ( ! empty( $stored['sec_force_https'] ) && ! is_ssl() ) {
		$stored['sec_force_https'] = 0;
	}
	update_option( 'kk_settings', $stored );
	return $count;
}

add_action( 'admin_post_kk_import', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'kenakata-kit' ) );
	}
	check_admin_referer( 'kk_import' );
	$json = isset( $_POST['kk_import_json'] ) ? trim( (string) wp_unslash( $_POST['kk_import_json'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded and sanitised per field
	if ( ! $json && ! empty( $_FILES['kk_import_file']['tmp_name'] ) && is_uploaded_file( $_FILES['kk_import_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$json = (string) file_get_contents( $_FILES['kk_import_file']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
	$data = json_decode( $json, true );
	$msg  = is_array( $data ) ? 'imported-' . kk_import_settings( $data ) : 'import-failed';
	wp_safe_redirect( admin_url( 'admin.php?page=kenakata-kit&tab=tools&kk_msg=' . $msg ) );
	exit;
} );

/* -------------------------------------------------------------------------
 * Assets
 * ---------------------------------------------------------------------- */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'toplevel_page_kenakata-kit' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_style( 'kenakata-kit', KK_URL . 'assets/kenakata-kit.css', array(), KK_VERSION );
	wp_add_inline_style( 'kenakata-kit', kk_css_vars( '.kk-preview' ) . kk_bbar_css() );
	wp_enqueue_style( 'kenakata-kit-admin', KK_URL . 'assets/admin.css', array( 'kenakata-kit' ), KK_VERSION );
	wp_enqueue_script( 'kenakata-kit-admin', KK_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ), KK_VERSION, true );
	wp_localize_script( 'kenakata-kit-admin', 'kkAdmin', array(
		'ajax'   => admin_url( 'admin-ajax.php' ),
		'nonce'  => wp_create_nonce( 'kk_admin' ),
		'copied' => __( 'Copied!', 'kenakata-kit' ),
		'saved'  => __( 'saved', 'kenakata-kit' ),
		'error'  => __( 'Could not save. Please try again.', 'kenakata-kit' ),
		'on'     => __( 'On', 'kenakata-kit' ),
		'off'    => __( 'Off', 'kenakata-kit' ),
		'icons'  => array_map( 'kk_icon', array_combine( array_keys( kk_bbar_icons() ), array_keys( kk_bbar_icons() ) ) ),
		'tooMany'=> __( 'More than 5 items are switched on. Labels may get cramped on small phones.', 'kenakata-kit' ),
	) );
} );

/* -------------------------------------------------------------------------
 * Page
 * ---------------------------------------------------------------------- */
function kk_admin_url( $tab ) {
	return admin_url( 'admin.php?page=kenakata-kit&tab=' . $tab );
}

/** Inline colour variables for the settings screens (empty for the automatic colour set: the default look stays). */
function kk_admin_theme_attr() {
	if ( ! function_exists( 'kk_sd_theme_colors' ) || 'auto' === kk_opt( 'sd_theme' ) ) {
		return '';
	}
	list( $primary, $accent ) = kk_sd_theme_colors();
	$css = '--a-green:' . $primary . ';--a-accent:' . $accent . ';--a-on:' . kk_sd_on_color( $primary );
	return ' style="' . esc_attr( $css ) . '"';
}

function kk_render_settings() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$tabs = kk_tabs();
	$tab  = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$t    = $tabs[ $tab ];
	$msg  = isset( $_GET['kk_msg'] ) ? sanitize_key( $_GET['kk_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap kk-admin"<?php echo kk_admin_theme_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from validated colours ?>>
		<header class="kk-admin__top">
			<div class="kk-admin__brand">
				<img class="kk-admin__logo" src="<?php echo esc_url( KK_URL . 'assets/img/logo.png' ); ?>" width="46" height="46" alt="">
				<?php $kk_pro = function_exists( 'kk_is_pro' ) && kk_is_pro(); ?>
				<div><h1><?php esc_html_e( 'Kenakata Kit', 'kenakata-kit' ); ?><?php if ( $kk_pro ) : ?> <a class="kk-pro-title" href="<?php echo esc_url( kk_admin_url( 'license' ) ); ?>" title="<?php esc_attr_e( 'Pro license active', 'kenakata-kit' ); ?>">PRO</a><?php endif; ?></h1><span class="kk-admin__ver"><?php /* translators: %s: version number */ printf( esc_html__( 'Version %s · E-commerce toolkit for Bangladesh', 'kenakata-kit' ), esc_html( KK_VERSION ) ); ?><?php if ( $kk_pro ) : ?> · <strong class="kk-pro-on">✓ <?php esc_html_e( 'Pro activated', 'kenakata-kit' ); ?></strong><?php endif; ?> · <?php /* translators: %s: company name */ printf( esc_html__( 'by %s', 'kenakata-kit' ), '<a href="https://digibhai.com/" target="_blank" rel="noopener">DigiBhai.com</a>' ); ?></span></div>
			</div>
			<div class="kk-admin__top-actions">
				<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View site ↗', 'kenakata-kit' ); ?></a>
				<a class="button button-primary" href="<?php echo esc_url( kk_admin_url( 'help' ) ); ?>"><?php esc_html_e( 'Full guide', 'kenakata-kit' ); ?></a>
			</div>
		</header>
		<h2 class="screen-reader-text"><?php echo esc_html( $t['label'] ); ?></h2>
		<?php settings_errors( 'kk_settings' ); ?>
		<div class="kk-admin__layout">
			<nav class="kk-admin__nav" aria-label="<?php esc_attr_e( 'Settings sections', 'kenakata-kit' ); ?>">
				<?php
				$group = '';
				foreach ( $tabs as $k => $item ) {
					if ( $item['group'] !== $group ) {
						$group = $item['group'];
						echo '<span class="kk-admin__nav-group">' . esc_html( $group ) . '</span>';
					}
					printf(
						'<a href="%1$s" class="%2$s"%3$s><span class="dashicons dashicons-%4$s" aria-hidden="true"></span>%5$s%6$s</a>',
						esc_url( kk_admin_url( $k ) ),
						$k === $tab ? 'is-active' : '',
						$k === $tab ? ' aria-current="page"' : '',
						esc_attr( $item['icon'] ),
						esc_html( $item['label'] ),
						! empty( $item['pro'] ) && ! ( function_exists( 'kk_is_pro' ) && kk_is_pro() ) ? ' <span class="kk-pro-badge">PRO</span>' : '' // with Pro active, the header shows PRO once
					);
				}
				?>
			</nav>
			<main class="kk-admin__main">
				<div class="kk-admin__head">
					<h2><span class="dashicons dashicons-<?php echo esc_attr( $t['icon'] ); ?>" aria-hidden="true"></span><?php echo esc_html( $t['label'] ); ?></h2>
					<p><?php echo esc_html( $t['intro'] ); ?></p>
				</div>
				<?php kk_admin_notice( $msg ); ?>
				<?php
				switch ( $tab ) {
					case 'dashboard':
						kk_render_dashboard();
						break;
					case 'widgets':
						kk_render_widget_guide();
						break;
					case 'shortcodes':
						kk_render_shortcode_help();
						break;
					case 'help':
						kk_render_help();
						break;
					case 'tools':
						kk_render_tools();
						break;
					case 'license':
						kk_render_license_page();
						break;
					default:
						if ( ! empty( $t['pro'] ) && function_exists( 'kk_is_pro' ) && ! kk_is_pro() ) {
							kk_render_pro_locked( $t['label'] );
							break;
						}
						kk_render_form( $tab, ! empty( $t['preview'] ) );
				}
				?>
			</main>
		</div>
	</div>
	<?php
}

function kk_admin_notice( $msg ) {
	if ( isset( $_GET['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="kk-notice-ok" role="status">✓ ' . esc_html__( 'Settings saved. Check the site (clear the cache if you use a cache plugin).', 'kenakata-kit' ) . '</div>';
	}
	if ( 'reset' === $msg ) {
		echo '<div class="kk-notice-ok" role="status">✓ ' . esc_html__( 'Settings were reset to their defaults.', 'kenakata-kit' ) . '</div>';
	} elseif ( 0 === strpos( $msg, 'imported-' ) ) {
		/* translators: %d: number of settings */
		echo '<div class="kk-notice-ok" role="status">✓ ' . esc_html( sprintf( __( '%d settings imported.', 'kenakata-kit' ), absint( substr( $msg, 9 ) ) ) ) . '</div>';
	} elseif ( 'import-failed' === $msg ) {
		echo '<div class="kk-notice-err" role="alert">' . esc_html__( 'Could not read that file. Use a .json file exported from Kenakata Kit.', 'kenakata-kit' ) . '</div>';	} elseif ( 'license-saved' === $msg ) {
		echo '<div class="kk-notice-ok" role="status">✓ ' . esc_html__( 'License key saved.', 'kenakata-kit' ) . '</div>';
	} elseif ( 'license-removed' === $msg ) {
		echo '<div class="kk-notice-ok" role="status">✓ ' . esc_html__( 'License key removed. The Free features keep working.', 'kenakata-kit' ) . '</div>';
	}
}

/** One settings form (a tab). Each heading starts a new card. */
function kk_render_form( $tab, $preview ) {
	$fields = array_filter( kk_fields(), function ( $f ) use ( $tab ) { return $f['tab'] === $tab; } );
	?>
	<div class="kk-admin__form-wrap<?php echo $preview ? ' has-preview' : ''; ?>">
		<form method="post" action="options.php" class="kk-form" data-tab="<?php echo esc_attr( $tab ); ?>">
			<?php settings_fields( 'kk_settings_group' ); ?>
			<input type="hidden" name="kk_settings[__tab]" value="<?php echo esc_attr( $tab ); ?>">
			<div class="kk-card">
				<?php
				$first = true;
				foreach ( $fields as $key => $f ) {
					if ( 'heading' === $f['type'] ) {
						if ( ! $first ) {
							echo '</div><div class="kk-card">';
						}
						echo '<h3 class="kk-card__title">' . esc_html( $f['label'] ) . '</h3>';
						if ( $f['desc'] ) {
							echo '<p class="kk-card__desc">' . esc_html( $f['desc'] ) . '</p>';
						}
						$first = false;
						continue;
					}
					$first = false;
					kk_render_field( $key, $f );
				}
				?>
			</div>
			<div class="kk-form__bar">
				<?php submit_button( __( 'Save settings', 'kenakata-kit' ), 'primary large', 'submit', false ); ?>
				<span class="kk-form__dirty" hidden><?php esc_html_e( 'You have unsaved changes', 'kenakata-kit' ); ?></span>
			</div>
		</form>
		<?php if ( $preview ) { 'mobile' === $tab ? kk_render_bbar_preview() : kk_render_preview( $tab ); } ?>
	</div>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kk-reset" data-confirm="<?php esc_attr_e( 'All settings on this page will go back to their defaults. Are you sure?', 'kenakata-kit' ); ?>">
		<input type="hidden" name="action" value="kk_reset_tab">
		<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
		<?php wp_nonce_field( 'kk_reset_tab' ); ?>
		<button type="submit" class="button-link kk-reset__btn"><?php esc_html_e( 'Reset this page to defaults', 'kenakata-kit' ); ?></button>
	</form>
	<?php
}

function kk_render_field( $key, $f ) {
	$id    = 'kk_' . $key;
	$name  = 'kk_settings[' . $key . ']';
	$val   = kk_opt( $key );
	$attrs = ! empty( $f['show_if'] ) ? ' data-show-if="' . esc_attr( $f['show_if'] ) . '"' : '';
	echo '<div class="kk-row kk-row--' . esc_attr( $f['type'] ) . '"' . $attrs . ' data-key="' . esc_attr( $key ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
	echo '<div class="kk-row__label"><label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label>';
	if ( $f['desc'] ) {
		echo '<p class="kk-row__desc" id="' . esc_attr( $id ) . '-desc">' . esc_html( $f['desc'] ) . '</p>';
	}
	echo '</div><div class="kk-row__control">';
	$describe = $f['desc'] ? ' aria-describedby="' . esc_attr( $id ) . '-desc"' : '';
	switch ( $f['type'] ) {
		case 'toggle':
			echo '<label class="kk-switch"><input type="hidden" name="' . esc_attr( $name ) . '" value="0"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( $val, 1, false ) . $describe . '><span class="kk-switch__ui" aria-hidden="true"></span><span class="kk-switch__text">' . esc_html( $val ? __( 'On', 'kenakata-kit' ) : __( 'Off', 'kenakata-kit' ) ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			break;
		case 'color':
			echo '<input type="text" class="kk-color" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" data-default-color="' . esc_attr( kk_defaults()[ $key ] ) . '"' . $describe . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			break;
		case 'number':
			$min  = isset( $f['min'] ) ? $f['min'] : 0;
			$max  = isset( $f['max'] ) ? $f['max'] : 100;
			$unit = isset( $f['unit'] ) ? $f['unit'] : '';
			echo '<div class="kk-range"><input type="range" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '" value="' . esc_attr( $val ) . '" aria-hidden="true" tabindex="-1">';
			echo '<input type="number" class="small-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" min="' . esc_attr( $min ) . '" max="' . esc_attr( $max ) . '"' . $describe . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $unit ? '<span class="kk-range__unit">' . esc_html( $unit ) . '</span>' : '';
			echo '</div>';
			break;
		case 'textarea':
			echo '<textarea class="large-text" rows="4" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $describe . '>' . esc_textarea( $val ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			break;
		case 'select':
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $describe . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			foreach ( $f['options'] as $ov => $ol ) {
				echo '<option value="' . esc_attr( $ov ) . '"' . selected( (string) $val, (string) $ov, false ) . '>' . esc_html( $ol ) . '</option>';
			}
			echo '</select>';
			break;
		case 'secret':
			$hint = function_exists( 'kk_courier_secret_hint' ) ? kk_courier_secret_hint( $key ) : '';
			echo '<input type="password" class="regular-text" autocomplete="new-password" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="" placeholder="' . esc_attr( '' !== $hint ? $hint : __( 'Paste the key here', 'kenakata-kit' ) ) . '"' . $describe . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
			if ( '' !== $hint ) {
				echo ' <span class="kk-saved">' . esc_html__( 'Saved', 'kenakata-kit' ) . '</span>';
			}
			break;
		case 'courier_test':
			echo '<button type="button" class="button" data-kk-courier-test="' . esc_url( add_query_arg( 'target', isset( $f['target'] ) ? sanitize_key( $f['target'] ) : 'booking', rest_url( 'kenakata/v1/courier/test' ) ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wp_rest' ) ) . '">' . esc_html__( 'Test connection', 'kenakata-kit' ) . '</button> <span class="kk-test-result" role="status" aria-live="polite"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
			break;
		case 'theme':
			$sets = function_exists( 'kk_sd_presets' ) ? kk_sd_presets() : array();
			echo '<div class="kk-themes" role="radiogroup" aria-labelledby="' . esc_attr( $id ) . '-l">';
			foreach ( $f['options'] as $ov => $ol ) {
				$oid  = $id . '_' . sanitize_html_class( $ov );
				$dots = '';
				if ( isset( $sets[ $ov ] ) ) {
					$dots = '<span class="kk-themes__dots" aria-hidden="true"><i style="background:' . esc_attr( $sets[ $ov ][1] ) . '"></i><i style="background:' . esc_attr( $sets[ $ov ][2] ) . '"></i></span>';
				} elseif ( 'custom' === $ov ) {
					$dots = '<span class="kk-themes__dots kk-themes__dots--pick" aria-hidden="true">+</span>';
				}
				echo '<input type="radio" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $ov ) . '"' . checked( (string) $val, (string) $ov, false ) . '><label for="' . esc_attr( $oid ) . '">' . $dots . '<span>' . esc_html( $ol ) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
			}
			echo '</div><span id="' . esc_attr( $id ) . '-l" class="screen-reader-text">' . esc_html( $f['label'] ) . '</span>';
			break;
		case 'choice':
			echo '<div class="kk-choice" role="radiogroup" aria-labelledby="' . esc_attr( $id ) . '-l">';
			foreach ( $f['options'] as $ov => $ol ) {
				$oid = $id . '_' . sanitize_html_class( $ov );
				echo '<input type="radio" id="' . esc_attr( $oid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $ov ) . '"' . checked( (string) $val, (string) $ov, false ) . '><label for="' . esc_attr( $oid ) . '">' . esc_html( $ol ) . '</label>';
			}
			echo '</div><span id="' . esc_attr( $id ) . '-l" class="screen-reader-text">' . esc_html( $f['label'] ) . '</span>';
			break;
		case 'bbar_items':
			$icons = kk_bbar_icons();
			$links = kk_bbar_links();
			echo '<input type="hidden" class="kk-bbar-json" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_json_encode( kk_bbar_items() ) ) . '">';
			echo '<ol class="kk-bbar-items">';
			foreach ( kk_bbar_items() as $it ) {
				$rid = 'kk-bbi-' . $it['id'];
				echo '<li class="kk-bbar-item' . ( $it['on'] ? ' is-on' : '' ) . '" data-id="' . esc_attr( $it['id'] ) . '">';
				echo '<span class="kk-bbar-item__handle dashicons dashicons-menu" title="' . esc_attr__( 'Drag to reorder', 'kenakata-kit' ) . '" aria-hidden="true"></span>';
				echo '<label class="kk-switch"><input type="checkbox" class="kk-bbi-on"' . checked( $it['on'], 1, false ) . ' aria-label="' . esc_attr( sprintf( /* translators: %s: item label */ __( 'Show "%s"', 'kenakata-kit' ), $it['label'] ) ) . '"><span class="kk-switch__ui" aria-hidden="true"></span></label>';
				echo '<span class="kk-bbar-item__icon" aria-hidden="true">' . kk_icon( $it['icon'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG
				echo '<span class="kk-bbar-item__fields">';
				echo '<label><span>' . esc_html__( 'Label', 'kenakata-kit' ) . '</span><input type="text" class="kk-bbi-label" maxlength="24" value="' . esc_attr( $it['label'] ) . '"></label>';
				echo '<label><span>' . esc_html__( 'Icon', 'kenakata-kit' ) . '</span><select class="kk-bbi-icon">';
				foreach ( $icons as $k => $l ) {
					echo '<option value="' . esc_attr( $k ) . '"' . selected( $it['icon'], $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select></label>';
				echo '<label><span>' . esc_html__( 'Link', 'kenakata-kit' ) . '</span><select class="kk-bbi-link">';
				foreach ( $links as $k => $l ) {
					echo '<option value="' . esc_attr( $k ) . '"' . selected( $it['link'], $k, false ) . '>' . esc_html( $l ) . '</option>';
				}
				echo '</select></label>';
				echo '<label class="kk-bbi-url-wrap"' . ( 'custom' === $it['link'] ? '' : ' hidden' ) . '><span>' . esc_html__( 'URL', 'kenakata-kit' ) . '</span><input type="url" class="kk-bbi-url" placeholder="https://" value="' . esc_attr( $it['url'] ) . '"></label>';
				echo '</span>';
				echo '<span class="kk-bbar-item__move"><button type="button" class="button-link kk-bbi-up" aria-label="' . esc_attr__( 'Move up', 'kenakata-kit' ) . '">↑</button><button type="button" class="button-link kk-bbi-down" aria-label="' . esc_attr__( 'Move down', 'kenakata-kit' ) . '">↓</button></span>';
				echo '</li>';
			}
			echo '</ol><p class="kk-bbar-warn kk-warn" hidden></p>';
			echo '<button type="button" class="button-link kk-bbar-reset">' . esc_html__( 'Restore the default items', 'kenakata-kit' ) . '</button>';
			echo '<script type="application/json" class="kk-bbar-defaults">' . wp_json_encode( kk_bbar_default_items() ) . '</script>';
			break;
		case 'areas':
			$areas = function_exists( 'kk_area_methods' ) ? kk_area_methods() : array();
			if ( $areas ) {
				echo '<ul class="kk-areas">';
				foreach ( $areas as $m ) {
					echo '<li><strong>' . esc_html( $m['title'] ) . '</strong> — ' . wp_kses_post( wc_price( $m['cost'] ) ) . '</li>';
				}
				echo '</ul>';
			} else {
				echo '<p class="kk-warn">' . esc_html__( 'No shipping methods are enabled yet.', 'kenakata-kit' ) . '</p>';
			}
			echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ) . '">' . esc_html__( 'Open shipping settings (change names and charges)', 'kenakata-kit' ) . '</a>';
			break;
		default:
			$ph = isset( $f['placeholder'] ) ? ' placeholder="' . esc_attr( $f['placeholder'] ) . '"' : '';
			echo '<input type="text" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '"' . $ph . $describe . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	echo '</div></div>';
}

/** Live preview (design + product card tabs). Updated by admin.js as fields change. */
function kk_render_preview( $tab ) {
	$img = 'data:image/svg+xml;utf8,' . rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 300"><rect width="300" height="300" fill="#f3f1ec"/><rect x="112" y="60" width="76" height="180" rx="14" fill="#cfe7da"/><rect x="122" y="40" width="56" height="30" rx="6" fill="#038348"/><rect x="124" y="120" width="52" height="40" rx="4" fill="#fff"/></svg>' );
	?>
	<aside class="kk-preview-wrap" aria-label="<?php esc_attr_e( 'Live preview', 'kenakata-kit' ); ?>">
		<div class="kk-preview-box">
			<p class="kk-preview-box__title"><?php esc_html_e( 'Live preview', 'kenakata-kit' ); ?> <span><?php esc_html_e( 'See changes before you save', 'kenakata-kit' ); ?></span></p>
			<div class="kk-preview">
				<div class="kk-products kk-products--grid kk-preview__grid" style="--kk-cols:1;--kk-cols-t:1;--kk-cols-m:1">
					<div class="kk-pcard">
						<a class="kk-pcard__media" href="#" tabindex="-1"><img src="<?php echo esc_attr( $img ); ?>" alt=""><span class="kk-pcard__badge">-12%</span></a>
						<div class="kk-pcard__body">
							<span class="kk-pcard__brand">COSRX</span>
							<h3 class="kk-pcard__title"><a href="#" tabindex="-1">Advanced Snail 96 Mucin Power Essence 100ml – Hydrating Repair Serum for Dry Skin</a></h3>
							<div class="kk-pcard__rating"><span class="kk-stars" style="--kk-rating:4.5" aria-hidden="true"></span><span class="kk-pcard__rating-count">(12)</span></div>
							<div class="kk-pcard__price"><span class="price"><del><?php echo esc_html( '1,650.00' . "\u{09F3}" ); ?></del> <ins><?php echo esc_html( '1,450.00' . "\u{09F3}" ); ?></ins></span></div>
							<div class="kk-pcard__actions">
								<a class="kk-pcard__btn kk-pcard__btn--cart" href="#" tabindex="-1"><?php echo kk_icon( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( kk_opt( 'card_cart_text' ) ); ?></span></a>
								<a class="kk-pcard__btn kk-pcard__btn--buy" href="#" tabindex="-1"><span><?php echo esc_html( kk_opt( 'buynow_text' ) ); ?></span></a>
							</div>
						</div>
					</div>
				</div>
				<?php if ( 'design' === $tab ) : ?>
				<div class="kk-preview__form">
					<p class="kk-field"><label>Phone number</label><input type="tel" placeholder="01XXXXXXXXX" tabindex="-1"></p>
					<div class="kk-preview__areas"><span class="is-on">Inside Dhaka — <?php echo esc_html( "\u{09F3}100" ); ?></span><span>Outside Dhaka — <?php echo esc_html( "\u{09F3}150" ); ?></span></div>
					<button type="button" class="kk-btn kk-btn--primary kk-btn--block" tabindex="-1">Confirm Order</button>
				</div>
				<?php endif; ?>
			</div>
			<p class="kk-preview-box__note"><?php esc_html_e( 'The preview is only a guide. Fonts and sizes on the real site may differ slightly because of your theme.', 'kenakata-kit' ); ?></p>
		</div>
	</aside>
	<?php
}

/** Live preview of the bottom bar on a phone outline. admin.js rebuilds it as settings change. */
function kk_render_bbar_preview() {
	?>
	<aside class="kk-preview-wrap" aria-label="<?php esc_attr_e( 'Live preview', 'kenakata-kit' ); ?>">
		<div class="kk-preview-box">
			<p class="kk-preview-box__title"><?php esc_html_e( 'Live preview', 'kenakata-kit' ); ?> <span><?php esc_html_e( 'How the bar looks on a phone', 'kenakata-kit' ); ?></span></p>
			<div class="kk-phone">
				<div class="kk-phone__screen">
					<div class="kk-phone__content" aria-hidden="true"><span></span><span></span><span></span><span class="is-short"></span><span></span><span class="is-short"></span></div>
					<div class="kk-phone__bar"><?php echo kk_bbar_render( kk_bbar_items() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in kk_bbar_render() ?></div>
				</div>
			</div>
			<p class="kk-preview-box__note"><?php esc_html_e( 'The first item is shown as the current page. The bar appears on phones only.', 'kenakata-kit' ); ?></p>
		</div>
	</aside>
	<?php
}

/* -------------------------------------------------------------------------
 * Dashboard
 * ---------------------------------------------------------------------- */
function kk_render_dashboard() {
	$checks = array();
	/* translators: %s: WooCommerce version */
	$checks[] = array( true, sprintf( __( 'WooCommerce %s is active', 'kenakata-kit' ), defined( 'WC_VERSION' ) ? WC_VERSION : '' ) );
	/* translators: %s: Elementor version */
	$checks[] = defined( 'ELEMENTOR_VERSION' ) ? array( true, sprintf( __( 'Elementor %s is active: 15 widgets are in the "Kenakata Kit" category', 'kenakata-kit' ), ELEMENTOR_VERSION ) ) : array( false, __( 'Elementor is not active: use the shortcodes instead of widgets', 'kenakata-kit' ), kk_admin_url( 'shortcodes' ) );
	$areas = function_exists( 'kk_area_methods' ) ? kk_area_methods() : array();
	/* translators: 1: number of delivery areas, 2: their names */
	$checks[] = $areas ? array( true, sprintf( _n( '%1$d delivery area found (%2$s)', '%1$d delivery areas found (%2$s)', count( $areas ), 'kenakata-kit' ), count( $areas ), implode( ', ', wp_list_pluck( $areas, 'title' ) ) ) ) : array( false, __( 'No shipping methods enabled: add "Inside Dhaka" / "Outside Dhaka" in WooCommerce → Settings → Shipping', 'kenakata-kit' ), admin_url( 'admin.php?page=wc-settings&tab=shipping' ) );
	$checks[] = kk_page_url( 'ty_page', 'thank-you' ) ? array( true, __( 'Thank-you page found', 'kenakata-kit' ) ) : array( false, __( 'No thank-you page: create a page with the slug "thank-you" and add the "Order Confirmation" widget', 'kenakata-kit' ), kk_admin_url( 'thankyou' ) );
	$checks[] = kk_page_url( 'tr_page', 'track-order' ) ? array( true, __( 'Order tracking page found', 'kenakata-kit' ) ) : array( false, __( 'No tracking page: create a page with the slug "track-order" and add the "Order Tracking" widget', 'kenakata-kit' ), kk_admin_url( 'tracking' ) );
	/* translators: %s: taxonomy name */
	$checks[] = kk_brand_taxonomy() ? array( true, sprintf( __( 'Brands found (%s)', 'kenakata-kit' ), kk_brand_taxonomy() ) ) : array( false, __( 'No brands: turn on WooCommerce → Products → Brands', 'kenakata-kit' ) );
	$checks[] = is_ssl() ? array( true, __( 'The site runs on https (secure)', 'kenakata-kit' ) ) : array( false, __( 'You are on http: use https', 'kenakata-kit' ) );
	if ( function_exists( 'kk_cron_status' ) ) {
		$cron = kk_cron_status();
		if ( $cron['healthy'] ) {
			/* translators: %d: minutes */
			$checks[] = array( true, sprintf( __( 'Scheduled tasks (cron) run normally: last run %d minutes ago', 'kenakata-kit' ), $cron['minutes_since_last'] ) );
		} elseif ( null === $cron['last_run'] ) {
			$checks[] = array( false, __( 'Scheduled tasks (cron): waiting for the first run. Check again in 10 minutes.', 'kenakata-kit' ) );
		} else {
			/* translators: %d: minutes */
			$checks[] = array( false, sprintf( __( 'Scheduled tasks (cron) are not running: last run %d minutes ago. Backups, scheduled sales and emails may not run. Add a server cron job (hosting panel → Cron Jobs, every 5 minutes) that opens your-site/wp-cron.php.', 'kenakata-kit' ), $cron['minutes_since_last'] ) );
		}
	}
	/* translators: %s: phone number */
	$checks[] = kk_opt( 'wa_number' ) ? array( true, sprintf( __( 'WhatsApp number set: %s', 'kenakata-kit' ), kk_opt( 'wa_number' ) ) ) : array( false, __( 'No WhatsApp number', 'kenakata-kit' ), kk_admin_url( 'whatsapp' ) );
	$lang = kk_opt( 'admin_lang' );
	?>
	<div class="kk-dash">
		<section class="kk-card">
			<h3 class="kk-card__title"><?php esc_html_e( 'Features on / off', 'kenakata-kit' ); ?></h3>
			<p class="kk-card__desc"><?php esc_html_e( 'Switches save immediately. Click a feature name to see its detailed settings.', 'kenakata-kit' ); ?></p>
			<div class="kk-modules">
				<?php foreach ( kk_modules() as $key => $m ) : $on = (int) kk_opt( $key ); ?>
				<div class="kk-module<?php echo $on ? ' is-on' : ''; ?>">
					<div class="kk-module__text">
						<a href="<?php echo esc_url( kk_admin_url( $m[1] ) ); ?>"><?php echo esc_html( $m[0] ); ?></a>
						<span><?php echo esc_html( $m[2] ); ?></span>
					</div>
					<label class="kk-switch kk-switch--ajax">
						<input type="checkbox" data-module="<?php echo esc_attr( $key ); ?>" <?php checked( $on, 1 ); ?> aria-label="<?php echo esc_attr( $m[0] ); ?>">
						<span class="kk-switch__ui" aria-hidden="true"></span>
					</label>
				</div>
				<?php endforeach; ?>
			</div>
			<p class="kk-module-status" role="status" aria-live="polite"></p>
		</section>
		<div class="kk-dash__side">
			<section class="kk-card">
				<h3 class="kk-card__title"><label for="kk-admin-lang"><?php esc_html_e( 'Plugin language', 'kenakata-kit' ); ?></label></h3>
				<p class="kk-card__desc"><?php esc_html_e( 'Language of these settings pages and the Elementor widgets. "Automatic" follows your WordPress profile language.', 'kenakata-kit' ); ?></p>
				<select id="kk-admin-lang" class="kk-lang">
					<option value="auto" <?php selected( $lang, 'auto' ); ?>><?php esc_html_e( 'Automatic (WordPress language)', 'kenakata-kit' ); ?></option>
					<option value="en" <?php selected( $lang, 'en' ); ?>>English</option>
					<option value="bn" <?php selected( $lang, 'bn' ); ?>>বাংলা</option>
				</select>
			</section>
			<section class="kk-card">
				<h3 class="kk-card__title"><?php esc_html_e( 'Site status', 'kenakata-kit' ); ?></h3>
				<ul class="kk-checks">
					<?php foreach ( $checks as $c ) : ?>
					<li class="<?php echo $c[0] ? 'is-ok' : 'is-bad'; ?>"><span class="dashicons dashicons-<?php echo $c[0] ? 'yes-alt' : 'warning'; ?>" aria-hidden="true"></span>
						<span><?php echo esc_html( $c[1] ); ?><?php if ( ! $c[0] && ! empty( $c[2] ) ) : ?> <a href="<?php echo esc_url( $c[2] ); ?>"><?php esc_html_e( 'Fix →', 'kenakata-kit' ); ?></a><?php endif; ?></span></li>
					<?php endforeach; ?>
				</ul>
			</section>
			<section class="kk-card kk-about">
				<h3 class="kk-card__title"><?php esc_html_e( 'About', 'kenakata-kit' ); ?></h3>
				<p class="kk-card__desc"><?php /* translators: 1: plugin name, 2: version */ printf( esc_html__( '%1$s %2$s is made and supported by DigiBhai.com.', 'kenakata-kit' ), 'Kenakata Kit', esc_html( KK_VERSION ) ); ?></p>
				<div class="kk-quick"><a class="button" href="https://digibhai.com/" target="_blank" rel="noopener"><?php esc_html_e( 'DigiBhai.com ↗', 'kenakata-kit' ); ?></a><a class="button" href="<?php echo esc_url( kk_admin_url( 'help' ) ); ?>"><?php esc_html_e( 'Full guide', 'kenakata-kit' ); ?></a></div>
			</section>
			<section class="kk-card">
				<h3 class="kk-card__title"><?php esc_html_e( 'Quick links', 'kenakata-kit' ); ?></h3>
				<div class="kk-quick">
					<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order' ) ); ?>"><?php esc_html_e( 'Orders', 'kenakata-kit' ); ?></a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping' ) ); ?>"><?php esc_html_e( 'Delivery charges', 'kenakata-kit' ); ?></a>
					<a class="button" href="<?php echo esc_url( kk_admin_url( 'widgets' ) ); ?>"><?php esc_html_e( 'Elementor widget guide', 'kenakata-kit' ); ?></a>
					<a class="button" href="<?php echo esc_url( kk_admin_url( 'shortcodes' ) ); ?>"><?php esc_html_e( 'Shortcodes', 'kenakata-kit' ); ?></a>
					<a class="button" href="<?php echo esc_url( kk_admin_url( 'tools' ) ); ?>"><?php esc_html_e( 'Export / Import', 'kenakata-kit' ); ?></a>
				</div>
			</section>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Elementor widget guide
 * ---------------------------------------------------------------------- */
function kk_widget_list() {
	return array(
		array( 'grid-view', __( 'Products Grid', 'kenakata-kit' ), __( 'A grid of products. On shop, category and brand templates choose "Products of this page"; on the home page choose Featured, On sale, Best selling, a category or a brand.', 'kenakata-kit' ), __( 'Shop/category/brand templates, home page', 'kenakata-kit' ), __( 'Which products (incl. Recently viewed), how many, columns (separate for mobile), sorting, page numbers / AJAX / Load more, section title, what the card shows, plus full styling of the card, image, name, price, rating, badge and buttons', 'kenakata-kit' ) ),
		array( 'slides', __( 'Products Carousel', 'kenakata-kit' ), __( 'The same product card as a slider.', 'kenakata-kit' ), __( 'Home page, "Related products" on the product page', 'kenakata-kit' ), __( 'All grid options plus how many at once, autoplay, loop, arrows, dots', 'kenakata-kit' ) ),
		array( 'awards', __( 'Brands', 'kenakata-kit' ), __( 'Brand logo/name cards, as a carousel or a grid.', 'kenakata-kit' ), __( 'Home page, All Brands page', 'kenakata-kit' ), __( 'Which brands, how many, order, card style, logo size, carousel options, full styling', 'kenakata-kit' ) ),
		array( 'category', __( 'Categories', 'kenakata-kit' ), __( 'Category image/name cards, as a carousel or a grid.', 'kenakata-kit' ), __( 'Home page, All Categories page, shop page', 'kenakata-kit' ), __( 'Same as Brands', 'kenakata-kit' ) ),
		array( 'products', __( 'Add to Cart Form', 'kenakata-kit' ), __( 'The buying area of the product page: variation options as buttons, quantity, Add to cart, Buy Now, WhatsApp/call buttons and the delivery box.', 'kenakata-kit' ), __( 'Single product template', 'kenakata-kit' ), __( 'Option buttons (normal/hover/chosen colours, size, corners), quantity box, every button, price and stock colours', 'kenakata-kit' ) ),
		array( 'star-filled', __( 'Product Reviews', 'kenakata-kit' ), __( 'Rating summary with star bars, customer photos, review cards and a review form with photo upload.', 'kenakata-kit' ), __( 'Single product template', 'kenakata-kit' ), __( 'Parts on/off, summary box, stars and bars, review cards, texts, photos, form and button', 'kenakata-kit' ) ),
		array( 'cart', __( 'Buy Now Button', 'kenakata-kit' ), __( 'A direct-to-checkout button for any product.', 'kenakata-kit' ), __( 'Product template, landing pages', 'kenakata-kit' ), __( 'Product, text, icon, full width, button style', 'kenakata-kit' ) ),
		array( 'search', __( 'Live Search', 'kenakata-kit' ), __( 'An icon (opens a big search) or a full search bar in the header.', 'kenakata-kit' ), __( 'Header', 'kenakata-kit' ), __( 'Type (icon/bar), texts, bar width, colours, border, corners', 'kenakata-kit' ) ),
		array( 'cart', __( 'Cart Icon', 'kenakata-kit' ), __( 'Cart icon with the number of items. Opens the side cart when that is on.', 'kenakata-kit' ), __( 'Header', 'kenakata-kit' ), __( 'Icon size, colours, count badge colours, show total or not', 'kenakata-kit' ) ),
		array( 'admin-users', __( 'Login / My Account', 'kenakata-kit' ), __( 'Opens the login popup for visitors; goes to My account when logged in.', 'kenakata-kit' ), __( 'Header', 'kenakata-kit' ), __( 'Texts, icon, icon only, style', 'kenakata-kit' ) ),
		array( 'whatsapp', __( 'WhatsApp / Call Button', 'kenakata-kit' ), __( 'WhatsApp chat or phone call button. On a product page the message includes the product name.', 'kenakata-kit' ), __( 'Any page, product template', 'kenakata-kit' ), __( 'Type, number, message, text, style', 'kenakata-kit' ) ),
		array( 'car', __( 'Delivery Info', 'kenakata-kit' ), __( 'Delivery charges (taken from the shipping settings), delivery time and COD information.', 'kenakata-kit' ), __( 'Product template, home page, checkout', 'kenakata-kit' ), __( 'Title, lines, icon colour, box style', 'kenakata-kit' ) ),
		array( 'forms', __( 'Checkout Form', 'kenakata-kit' ), __( 'The Kenakata Kit simple checkout, styleable from Elementor.', 'kenakata-kit' ), __( 'Checkout page', 'kenakata-kit' ), __( 'Boxes, headings, labels, fields, delivery area buttons, order summary, order button', 'kenakata-kit' ) ),
		array( 'location', __( 'Order Tracking', 'kenakata-kit' ), __( 'Find orders by phone number, with status and courier link.', 'kenakata-kit' ), __( 'Track Order page', 'kenakata-kit' ), __( 'Box and button styles (texts: "Order tracking & courier" tab)', 'kenakata-kit' ) ),
		array( 'yes-alt', __( 'Order Confirmation', 'kenakata-kit' ), __( 'Order number, products and address after the order.', 'kenakata-kit' ), __( 'Thank You page', 'kenakata-kit' ), __( 'Box and button styles (texts: "Thank-you page" tab)', 'kenakata-kit' ) ),
	);
}

function kk_render_widget_guide() {
	?>
	<div class="kk-card">
		<h3 class="kk-card__title"><?php esc_html_e( 'How to use', 'kenakata-kit' ); ?></h3>
		<ol class="kk-steps-list">
			<li><?php esc_html_e( 'Open the page or template with "Edit with Elementor" (header and shop pages: Templates → Theme Builder).', 'kenakata-kit' ); ?></li>
			<li><?php esc_html_e( 'Type "Kenakata" or the widget name in the search box of the left panel, or scroll down to the "Kenakata Kit" category.', 'kenakata-kit' ); ?></li>
			<li><?php esc_html_e( 'Drag the widget onto the page. Choose what it shows in the Content tab and change colours, sizes and corners in the Style tab.', 'kenakata-kit' ); ?></li>
			<li><?php esc_html_e( 'To adjust mobile separately, click the mobile icon at the bottom. Columns, sizes and spacing can be set per device.', 'kenakata-kit' ); ?></li>
			<li><?php esc_html_e( 'Click Update / Publish.', 'kenakata-kit' ); ?></li>
		</ol>
	</div>
	<div class="kk-widgets">
		<?php foreach ( kk_widget_list() as $w ) : ?>
		<article class="kk-widget-card">
			<h3><span class="dashicons dashicons-<?php echo esc_attr( $w[0] ); ?>" aria-hidden="true"></span><?php echo esc_html( $w[1] ); ?></h3>
			<p><?php echo esc_html( $w[2] ); ?></p>
			<dl><dt><?php esc_html_e( 'Where to use', 'kenakata-kit' ); ?></dt><dd><?php echo esc_html( $w[3] ); ?></dd><dt><?php esc_html_e( 'What you can change', 'kenakata-kit' ); ?></dt><dd><?php echo esc_html( $w[4] ); ?></dd></dl>
		</article>
		<?php endforeach; ?>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * Shortcodes
 * ---------------------------------------------------------------------- */
function kk_shortcode_list() {
	return array(
		array( '[kk_products source="featured" limit="8" columns="4"]', __( 'Products grid / carousel', 'kenakata-kit' ), 'source="latest|featured|sale|best_selling|top_rated|category|brand|ids|related|current" · category="slug,slug" · brand="slug" · ids="12,34" · limit="8" · columns="4" columns_tablet="3" columns_mobile="2" · layout="grid|carousel" · orderby="date|price|popularity|rating|title|rand" order="DESC|ASC" · paginate="yes" · buttons="side|stack|buynow_only|cart_only|none"' ),
		array( '[kk_brands layout="carousel" limit="18"]', __( 'Brand cards', 'kenakata-kit' ), 'layout="carousel|grid" · limit="18" (0 = all) · orderby="count|name" · style="card|minimal|circle|logo" · include="5,6" exclude="12" · show_count="yes|no"' ),
		array( '[kk_categories layout="grid"]', __( 'Category cards', 'kenakata-kit' ), __( 'Same options as brands', 'kenakata-kit' ) . ' · parent="0"' ),
		array( '[kk_live_search mode="bar"]', __( 'Live Search', 'kenakata-kit' ), 'mode="icon|bar" · placeholder="…"' ),
		array( '[kk_cart_icon show_total="yes"]', __( 'Cart Icon', 'kenakata-kit' ), 'show_total="yes|no"' ),
		array( '[kk_account_link]', __( 'Login / account link', 'kenakata-kit' ), 'login_text="Log in" account_text="My account"' ),
		array( '[kk_buy_now id="123"]', __( 'Buy Now Button', 'kenakata-kit' ), 'id="123" · text="Order now"' ),
		array( '[kk_whatsapp text="Order on WhatsApp"]', __( 'WhatsApp / Call Button', 'kenakata-kit' ), 'type="whatsapp|call" · number="01XXXXXXXXX" · message="…" ({product}, {price}, {url}) · text="…"' ),
		array( '[kk_delivery_info]', __( 'Delivery Info', 'kenakata-kit' ), 'title="…" · charges="yes|no"' ),
		array( '[kk_order_tracking]', __( 'Order tracking form', 'kenakata-kit' ), '—' ),
		array( '[kk_thank_you]', __( 'Order confirmation (on the thank-you page)', 'kenakata-kit' ), '—' ),
	);
}

function kk_render_shortcode_help() {
	echo '<div class="kk-card"><p class="kk-card__desc" style="margin:0">' . esc_html__( 'Click a shortcode to copy it. Paste it into Elementor\'s "Shortcode" widget or the block editor\'s "Shortcode" block. Older [ss_…] shortcodes keep working.', 'kenakata-kit' ) . '</p></div>';
	echo '<div class="kk-shortcodes">';
	foreach ( kk_shortcode_list() as $s ) {
		echo '<article class="kk-sc"><h3>' . esc_html( $s[1] ) . '</h3><button type="button" class="kk-copy" data-copy="' . esc_attr( $s[0] ) . '"><code>' . esc_html( $s[0] ) . '</code><span class="dashicons dashicons-admin-page" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Copy', 'kenakata-kit' ) . '</span></button><p class="kk-sc__opts"><strong>' . esc_html__( 'Options:', 'kenakata-kit' ) . '</strong> ' . esc_html( $s[2] ) . '</p></article>';
	}
	echo '</div>';
	echo '<div class="kk-card"><h3 class="kk-card__title">' . esc_html__( 'CSS class for page builders', 'kenakata-kit' ) . '</h3><p><button type="button" class="kk-copy" data-copy="kk-archive-cover"><code>kk-archive-cover</code><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button> ' . esc_html__( 'Add this class to a section and, on category/brand pages, that category\'s or brand\'s image becomes its background.', 'kenakata-kit' ) . '</p></div>';
}

/* -------------------------------------------------------------------------
 * Tools + help
 * ---------------------------------------------------------------------- */
function kk_render_tools() {
	?>
	<div class="kk-tools">
		<section class="kk-card">
			<h3 class="kk-card__title"><?php esc_html_e( 'Export (download settings)', 'kenakata-kit' ); ?></h3>
			<p class="kk-card__desc"><?php esc_html_e( 'Downloads all settings as a .json file. Import it on another site with Kenakata Kit to get exactly the same settings. Also useful as a backup.', 'kenakata-kit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="kk_export"><?php wp_nonce_field( 'kk_export' ); ?>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Download settings file', 'kenakata-kit' ); ?></button>
			</form>
		</section>
		<section class="kk-card">
			<h3 class="kk-card__title"><?php esc_html_e( 'Import (load settings)', 'kenakata-kit' ); ?></h3>
			<p class="kk-card__desc"><?php esc_html_e( 'Choose an exported file, or paste its contents in the box below.', 'kenakata-kit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="kk-import">
				<input type="hidden" name="action" value="kk_import"><?php wp_nonce_field( 'kk_import' ); ?>
				<p><input type="file" name="kk_import_file" accept=".json,application/json" aria-label="<?php esc_attr_e( 'Settings file', 'kenakata-kit' ); ?>"></p>
				<p><textarea name="kk_import_json" rows="4" class="large-text code" placeholder='{"plugin":"kenakata-kit", ...}' aria-label="<?php esc_attr_e( 'Settings text', 'kenakata-kit' ); ?>"></textarea></p>
				<button type="submit" class="button"><?php esc_html_e( 'Import', 'kenakata-kit' ); ?></button>
			</form>
		</section>
		<section class="kk-card kk-card--danger">
			<h3 class="kk-card__title"><?php esc_html_e( 'Reset all settings', 'kenakata-kit' ); ?></h3>
			<p class="kk-card__desc"><?php esc_html_e( 'Every plugin setting goes back to its default. Orders, pages and products are not touched. Export first.', 'kenakata-kit' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="kk-reset" data-confirm="<?php esc_attr_e( 'All settings will go back to their defaults. Are you sure?', 'kenakata-kit' ); ?>">
				<input type="hidden" name="action" value="kk_reset_tab"><input type="hidden" name="tab" value="all"><?php wp_nonce_field( 'kk_reset_tab' ); ?>
				<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Reset everything', 'kenakata-kit' ); ?></button>
			</form>
		</section>
	</div>
	<?php
}

function kk_render_help() {
	$url = KK_URL . 'docs/index.html?v=' . KK_VERSION;
	?>
	<div class="kk-card kk-help">
		<p class="kk-card__desc"><?php esc_html_e( 'The complete guide (in Bengali) is below.', 'kenakata-kit' ); ?> <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open in a new tab ↗', 'kenakata-kit' ); ?></a></p>
		<iframe class="kk-help__frame" src="<?php echo esc_url( $url ); ?>" title="<?php esc_attr_e( 'Kenakata Kit guide', 'kenakata-kit' ); ?>" loading="lazy"></iframe>
	</div>
	<?php
}
