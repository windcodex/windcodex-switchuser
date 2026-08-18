<?php
/**
 * Uninstall SwitchUser free.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
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
