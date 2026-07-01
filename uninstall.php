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
		$wpdb->esc_like( 'wpg_lockout_' ) . '%',
		$wpdb->esc_like( 'wpg_attempts_' ) . '%'
	)
);

foreach ( $option_names as $option_name ) {
	delete_option( $option_name );
}

delete_option( 'wpg_max_attempts' );
delete_option( 'wpg_lockout_duration' );
delete_option( 'wpg_show_lockout_message' );
