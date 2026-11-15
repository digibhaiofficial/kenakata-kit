<?php
/**
 * Scheduled tasks (WP-Cron) health: a tiny "heartbeat" task runs every 5 minutes and remembers when it last ran.
 * Shown in Dashboard → Site status and in GET /wp-json/kenakata/v1/status (administrators), so you can see whether
 * backups, scheduled sales, emails and other timed jobs really run (e.g. after setting up a server cron job).
 */

defined( 'ABSPATH' ) || exit;

// Health check of WP-Cron: once an hour is enough (a 5-minute event made WordPress call itself every 5 minutes).
add_action( 'init', function () {
	$next = wp_get_scheduled_event( 'kk_cron_heartbeat' );
	if ( $next && 'hourly' !== $next->schedule ) {
		wp_clear_scheduled_hook( 'kk_cron_heartbeat' ); // older versions: every 5 minutes
		$next = false;
	}
	if ( ! $next ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'kk_cron_heartbeat' );
	}
} );

add_action( 'kk_cron_heartbeat', function () {
	update_option( 'kk_cron_last', time(), false );
} );

/** Cron facts: is WP-Cron switched off in wp-config, when did tasks last run, how many tasks are overdue. */
function kk_cron_status() {
	$now     = time();
	$last    = (int) get_option( 'kk_cron_last', 0 );
	$overdue = 0;
	$oldest  = 0;
	foreach ( (array) _get_cron_array() as $ts => $hooks ) {
		if ( $ts < $now - 15 * MINUTE_IN_SECONDS ) {
			$overdue += count( (array) $hooks );
			$oldest   = $oldest ? min( $oldest, $ts ) : $ts;
		}
	}
	return array(
		'wp_cron_disabled'   => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
		'last_run'           => $last ? gmdate( 'c', $last ) : null,
		'minutes_since_last' => $last ? (int) floor( ( $now - $last ) / 60 ) : null,
		'overdue_tasks'      => $overdue,
		'oldest_overdue'     => $oldest ? gmdate( 'c', $oldest ) : null,
		'healthy'            => $last && ( $now - $last ) < 90 * MINUTE_IN_SECONDS,
	);
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'kenakata/v1', '/status', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function () {
			return array( 'version' => KK_VERSION, 'cron' => kk_cron_status() );
		},
	) );
} );

// 3.12: two small options that are read on every request are now autoloaded (one query less each, per page).
add_action( 'admin_init', function () {
	if ( '312' === get_option( 'kk_autoload_fix' ) || ! function_exists( 'wp_set_option_autoload_values' ) ) {
		return;
	}
	wp_set_option_autoload_values( array( 'kk_sd_role_version' => true, 'kk_license_revoked' => true ) );
	update_option( 'kk_autoload_fix', '312', true );
} );
