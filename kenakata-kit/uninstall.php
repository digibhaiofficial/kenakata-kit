<?php
/**
 * Runs only when the plugin is deleted from Plugins → Delete (not on deactivation, not on "Replace current").
 *
 * Always removed: scheduled tasks, temporary data and locks. They are useless without the plugin.
 * Removed only when "Delete all data when the plugin is deleted" is ticked (Tools tab): settings, license key,
 * private keys, logs and lists, wishlists, the incomplete-orders table and the Store Manager role. Orders, products, pages and the order
 * notes/tracking saved on orders are never touched.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

foreach ( array( 'kk_cron_heartbeat', 'kk_license_daily', 'kk_incomplete_cleanup' ) as $hook ) {
	wp_clear_scheduled_hook( $hook );
}

// Temporary data: transients (and their timeouts) and short locks made by the plugin.
$like = array( '_transient_kk_%', '_transient_timeout_kk_%', '_site_transient_kk_%', '_site_transient_timeout_kk_%', 'kk_lock_%' );
foreach ( $like as $pattern ) {
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}
delete_option( 'kk_cron_last' );
delete_option( 'kk_license_cache' );
delete_option( 'kk_license_revoked' );
delete_option( 'kk_autoload_fix' );
delete_option( 'kk_hdr_cart' );

if ( ! get_option( 'kk_uninstall_wipe' ) ) {
	return; // keep the settings: reinstalling brings everything back
}

foreach ( array( 'kk_settings', 'kk_license_key', 'kk_secrets', 'kk_courier_secrets', 'kk_incomplete_db', 'kk_sd_role_version', 'kk_uninstall_wipe', 'kk_blacklist', 'kk_courier_log', 'kk_courier_pick_mig', 'kk_login_locks', 'kk_shield_count', 'kk_sd_rep_gen', 'kk_fake_calls' ) as $opt ) {
	delete_option( $opt );
}
// Wishlists, wishlist counts and "update later" choices (3.25). Review photos stay: they belong to the reviews.
delete_metadata( 'user', 0, 'kk_wishlist', '', true );
delete_metadata( 'user', 0, 'kk_update_later', '', true );
delete_metadata( 'post', 0, '_kk_wl_count', '', true );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}kk_incomplete" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// Staff who had only the Store Manager role become Customers: they keep their account but lose shop access.
$role = get_role( 'kk_store_manager' );
if ( $role ) {
	foreach ( get_users( array( 'role' => 'kk_store_manager', 'fields' => 'all' ) ) as $u ) {
		$u->remove_role( 'kk_store_manager' );
		if ( ! $u->roles ) {
			$u->add_role( 'customer' );
		}
	}
	remove_role( 'kk_store_manager' );
}
// The uploads .htaccess rule was already removed when the plugin was deactivated.
