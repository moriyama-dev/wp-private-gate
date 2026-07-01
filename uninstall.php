<?php
/**
 * Removes all plugin options, including the per-IP lockout/attempt entries,
 * when the plugin is deleted from the Plugins screen.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$option_names = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'wpg_lockout_ip_' ) . '%',
		$wpdb->esc_like( 'wpg_attempts_ip_' ) . '%'
	)
);

foreach ( $option_names as $option_name ) {
	delete_option( $option_name );
}

delete_option( 'wpg_max_attempts' );
delete_option( 'wpg_lockout_duration' );
delete_option( 'wpg_show_lockout_message' );
delete_option( 'wpg_email_notifications' );
delete_option( 'wpg_db_version' );

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpg_login_log" );
