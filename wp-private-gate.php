<?php
/**
 * Plugin Name:       WP Private Gate
 * Plugin URI:        https://github.com/moriyama-dev/wp-private-gate
 * Description:       Lock down your private WordPress site. Force login for all visitors, block REST API and XML-RPC, and lock out repeated failed login attempts.
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Yoshiro Moriyama (Takumi Web Services)
 * Author URI:        https://takumi.ca
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-private-gate
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WPG_VERSION', '1.2.0' );
define( 'WPG_DB_VERSION', '1.1.0' );
define( 'WPG_PLUGIN_FILE', __FILE__ );
define( 'WPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPG_PLUGIN_DIR . 'includes/class-wpg-ip-whitelist.php';
require_once WPG_PLUGIN_DIR . 'includes/class-wpg-access-control.php';
require_once WPG_PLUGIN_DIR . 'includes/class-wpg-api-blocker.php';
require_once WPG_PLUGIN_DIR . 'includes/class-wpg-login-logger.php';
require_once WPG_PLUGIN_DIR . 'includes/class-wpg-lockout.php';
require_once WPG_PLUGIN_DIR . 'includes/class-wpg-two-factor.php';
require_once WPG_PLUGIN_DIR . 'includes/class-wpg-admin.php';

/**
 * Sets default options on activation so the settings screen always has a value to render.
 */
function wpg_activate_single_site() {
	add_option( 'wpg_max_attempts', 5 );
	add_option( 'wpg_lockout_duration', 30 );
	add_option( 'wpg_show_lockout_message', false );
	add_option( 'wpg_email_notifications', true );
	add_option( 'wpg_ip_whitelist', '' );
	WPG_Login_Logger::create_table();
	update_option( 'wpg_db_version', WPG_DB_VERSION );
}

/**
 * On a multisite network activation WordPress does not loop over every site
 * for us — it calls this hook once with $network_wide = true and leaves
 * per-site setup to the plugin. Without this, every site would silently be
 * missing its login-log table (and defaults) until its first page load
 * triggers wpg_maybe_upgrade_db() below.
 */
function wpg_activate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
			switch_to_blog( $site_id );
			wpg_activate_single_site();
			restore_current_blog();
		}
		return;
	}
	wpg_activate_single_site();
}
register_activation_hook( WPG_PLUGIN_FILE, 'wpg_activate' );

/**
 * Runs the same per-site setup when a new site is created on a network
 * where the plugin is already network-activated.
 */
function wpg_new_site_init( $new_site ) {
	if ( ! is_multisite() ) {
		return;
	}
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! is_plugin_active_for_network( plugin_basename( WPG_PLUGIN_FILE ) ) ) {
		return;
	}

	switch_to_blog( $new_site->blog_id );
	wpg_activate_single_site();
	restore_current_blog();
}
add_action( 'wp_initialize_site', 'wpg_new_site_init' );

function wpg_load_textdomain() {
	load_plugin_textdomain( 'wp-private-gate', false, dirname( plugin_basename( WPG_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'wpg_load_textdomain' );

/**
 * Creates/updates the login-log table when the plugin is upgraded in place
 * (e.g. via the Plugins screen) without going through the activation hook.
 */
function wpg_maybe_upgrade_db() {
	if ( get_option( 'wpg_db_version' ) !== WPG_DB_VERSION ) {
		WPG_Login_Logger::create_table();
		update_option( 'wpg_db_version', WPG_DB_VERSION );
	}
}
add_action( 'plugins_loaded', 'wpg_maybe_upgrade_db' );

function wpg_init() {
	WPG_Access_Control::init();
	WPG_Api_Blocker::init();
	WPG_Lockout::init();
	WPG_Two_Factor::init();
	WPG_Admin::init();
}
add_action( 'plugins_loaded', 'wpg_init' );
