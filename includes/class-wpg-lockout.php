<?php
/**
 * Tracks failed login attempts per IP address and locks out an IP once it
 * crosses the configured threshold. Lockout state lives in wp_options
 * (no dedicated DB table) keyed by an md5 hash of the IP address.
 */

defined( 'ABSPATH' ) || exit;

class WPG_Lockout {

	const LOCKOUT_OPTION_PREFIX  = 'wpg_lockout_';
	const ATTEMPTS_OPTION_PREFIX = 'wpg_attempts_';

	public static function init() {
		add_filter( 'authenticate', array( __CLASS__, 'block_if_locked_out' ), 30, 3 );
		add_action( 'wp_login_failed', array( __CLASS__, 'record_failed_attempt' ) );
		add_action( 'wp_login', array( __CLASS__, 'clear_attempts_on_success' ) );
	}

	private static function get_client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	private static function get_lockout( $ip ) {
		return get_option( self::LOCKOUT_OPTION_PREFIX . md5( $ip ) );
	}

	private static function is_locked_out( $ip ) {
		$lockout = self::get_lockout( $ip );
		return $lockout && isset( $lockout['until'] ) && $lockout['until'] > time();
	}

	public static function block_if_locked_out( $user, $username, $password ) {
		if ( empty( $username ) && empty( $password ) ) {
			return $user;
		}

		$ip = self::get_client_ip();
		if ( '' === $ip ) {
			return $user;
		}

		$lockout = self::get_lockout( $ip );
		if ( ! $lockout || ! isset( $lockout['until'] ) || $lockout['until'] <= time() ) {
			return $user;
		}

		if ( get_option( 'wpg_show_lockout_message', false ) ) {
			$minutes_left = (int) ceil( ( $lockout['until'] - time() ) / MINUTE_IN_SECONDS );
			return new WP_Error(
				'wpg_too_many_attempts',
				sprintf(
					/* translators: %d: minutes remaining until the IP is unlocked. */
					__( 'ログイン試行回数が上限を超えました。%d分後に再試行してください。', 'wp-private-gate' ),
					$minutes_left
				)
			);
		}

		// Default: mimic a normal invalid-credentials error so an attacker
		// can't distinguish a lockout from a wrong password.
		return new WP_Error(
			'wpg_too_many_attempts',
			__( '<strong>エラー</strong>: ユーザー名またはパスワードが正しくありません。', 'wp-private-gate' )
		);
	}

	public static function record_failed_attempt( $username ) {
		$ip = self::get_client_ip();
		if ( '' === $ip || self::is_locked_out( $ip ) ) {
			return;
		}

		$attempts_key = self::ATTEMPTS_OPTION_PREFIX . md5( $ip );
		$attempts     = get_option( $attempts_key, array( 'count' => 0 ) );
		$attempts['count'] = isset( $attempts['count'] ) ? (int) $attempts['count'] + 1 : 1;

		$max_attempts = max( 1, (int) get_option( 'wpg_max_attempts', 5 ) );

		if ( $attempts['count'] >= $max_attempts ) {
			$lockout_minutes = max( 1, (int) get_option( 'wpg_lockout_duration', 30 ) );
			update_option(
				self::LOCKOUT_OPTION_PREFIX . md5( $ip ),
				array( 'until' => time() + ( $lockout_minutes * MINUTE_IN_SECONDS ) ),
				false
			);
			delete_option( $attempts_key );
			return;
		}

		update_option( $attempts_key, $attempts, false );
	}

	public static function clear_attempts_on_success( $user_login ) {
		$ip = self::get_client_ip();
		if ( '' === $ip ) {
			return;
		}
		delete_option( self::ATTEMPTS_OPTION_PREFIX . md5( $ip ) );
	}
}
