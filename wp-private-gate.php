<?php
/**
 * Plugin Name:       WP Private Gate
 * Plugin URI:        https://github.com/moriyama-dev/wp-private-gate
 * Description:       Lock down your private WordPress site. Force login for all visitors, block REST API and XML-RPC, and lock out repeated failed login attempts.
 * Version:           1.0.0
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

define( 'WPG_VERSION', '1.0.0' );
define( 'WPG_PLUGIN_FILE', __FILE__ );
define( 'WPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPG_PLUGIN_DIR . 'includes/class-wpg-access-control.php';
require_once WPG_PLUGIN_DIR . 'includes/class-wpg-api-blocker.php';
require_once WPG_PLUGIN_DIR . 'includes/class-wpg-lockout.php';
require_once WPG_PLUGIN_DIR . 'includes/class-wpg-admin.php';

/**
 * Sets default options on activation so the settings screen always has a value to render.
 */
function wpg_activate() {
	add_option( 'wpg_max_attempts', 5 );
	add_option( 'wpg_lockout_duration', 30 );
	add_option( 'wpg_show_lockout_message', false );
}
register_activation_hook( WPG_PLUGIN_FILE, 'wpg_activate' );

function wpg_load_textdomain() {
	load_plugin_textdomain( 'wp-private-gate', false, dirname( plugin_basename( WPG_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'init', 'wpg_load_textdomain' );

function wpg_init() {
	WPG_Access_Control::init();
	WPG_Api_Blocker::init();
	WPG_Lockout::init();
	WPG_Admin::init();
}
add_action( 'plugins_loaded', 'wpg_init' );
