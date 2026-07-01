<?php
/**
 * Registers the plugin's single settings screen under Settings > WP Private Gate.
 */

defined( 'ABSPATH' ) || exit;

class WPG_Admin {

	const OPTION_GROUP = 'wpg_settings_group';
	const PAGE_SLUG     = 'wp-private-gate';

	private static $settings_page_hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add_settings_page() {
		self::$settings_page_hook = add_options_page(
			__( 'WP Private Gate', 'wp-private-gate' ),
			__( 'WP Private Gate', 'wp-private-gate' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function enqueue_assets( $hook_suffix ) {
		if ( $hook_suffix !== self::$settings_page_hook ) {
			return;
		}

		wp_enqueue_style(
			'wpg-admin',
			WPG_PLUGIN_URL . 'admin/css/wp-private-gate-admin.css',
			array(),
			WPG_VERSION
		);

		wp_enqueue_script(
			'wpg-admin',
			WPG_PLUGIN_URL . 'admin/js/wp-private-gate-admin.js',
			array(),
			WPG_VERSION,
			true
		);
	}

	public static function register_settings() {
		register_setting( self::OPTION_GROUP, 'wpg_max_attempts', array(
			'type'              => 'integer',
			'sanitize_callback' => array( __CLASS__, 'sanitize_max_attempts' ),
			'default'           => 5,
		) );

		register_setting( self::OPTION_GROUP, 'wpg_lockout_duration', array(
			'type'              => 'integer',
			'sanitize_callback' => array( __CLASS__, 'sanitize_lockout_duration' ),
			'default'           => 30,
		) );

		register_setting( self::OPTION_GROUP, 'wpg_show_lockout_message', array(
			'type'              => 'boolean',
			'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
			'default'           => false,
		) );

		add_settings_section(
			'wpg_main_section',
			__( 'ロックアウト設定', 'wp-private-gate' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'wpg_max_attempts',
			__( 'ログイン失敗許容回数', 'wp-private-gate' ),
			array( __CLASS__, 'render_max_attempts_field' ),
			self::PAGE_SLUG,
			'wpg_main_section'
		);

		add_settings_field(
			'wpg_lockout_duration',
			__( 'ロック時間（分）', 'wp-private-gate' ),
			array( __CLASS__, 'render_lockout_duration_field' ),
			self::PAGE_SLUG,
			'wpg_main_section'
		);

		add_settings_field(
			'wpg_show_lockout_message',
			__( 'ロックアウトメッセージを表示', 'wp-private-gate' ),
			array( __CLASS__, 'render_show_lockout_message_field' ),
			self::PAGE_SLUG,
			'wpg_main_section'
		);
	}

	public static function sanitize_max_attempts( $value ) {
		return max( 1, absint( $value ) );
	}

	public static function sanitize_lockout_duration( $value ) {
		return max( 1, absint( $value ) );
	}

	public static function sanitize_checkbox( $value ) {
		return (bool) $value;
	}

	public static function render_max_attempts_field() {
		printf(
			'<input type="number" min="1" step="1" name="wpg_max_attempts" value="%s" class="small-text" />',
			esc_attr( get_option( 'wpg_max_attempts', 5 ) )
		);
	}

	public static function render_lockout_duration_field() {
		printf(
			'<input type="number" min="1" step="1" name="wpg_lockout_duration" value="%s" class="small-text" /> %s',
			esc_attr( get_option( 'wpg_lockout_duration', 30 ) ),
			esc_html__( '分', 'wp-private-gate' )
		);
	}

	public static function render_show_lockout_message_field() {
		printf(
			'<label><input type="checkbox" name="wpg_show_lockout_message" value="1" %s /> %s</label>',
			checked( (bool) get_option( 'wpg_show_lockout_message', false ), true, false ),
			esc_html__( 'ロック中である旨をログインフォームに表示する（デフォルトは非表示）', 'wp-private-gate' )
		);
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require WPG_PLUGIN_DIR . 'admin/views/settings.php';
	}
}
