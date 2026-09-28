<?php
/**
 * Uninstall SwitchUser free.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * SwitchUser Pro shares this plugin's data. If Pro is active, this free plugin
 * is just a deactivated leftover on disk - deleting it must not touch anything
 * Pro is using (including in-progress switch locks and re-auth grants).
 */
if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}
if ( is_plugin_active( 'windcodex-switchuser-pro/windcodex-switchuser-pro.php' ) ) {
	return;
}

global $wpdb;
if ( isset( $wpdb ) ) {
	$switchuser_patterns = array(
		'_transient_switchuser_target_lock_%',
		'_transient_timeout_switchuser_target_lock_%',
		'_transient_switchuser_recent_reauth_%',
		'_transient_timeout_switchuser_recent_reauth_%',
	);
	foreach ( $switchuser_patterns as $switchuser_pattern ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup requires wildcard option-name deletion.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $switchuser_pattern ) );
	}
}
