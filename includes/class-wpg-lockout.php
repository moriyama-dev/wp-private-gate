<?php
/**
 * Tracks failed login attempts per IP address and locks out an IP once it
 * crosses the configured threshold. Lockout state lives in wp_options
 * (no dedicated DB table) keyed by an md5 hash of the IP address; the raw
 * IP is kept in the option value so the admin screen can list it.
 */

defined( 'ABSPATH' ) || exit;

class WPG_Lockout {

	// Deliberately not "wpg_lockout_" — that would collide with the
	// wpg_lockout_duration setting when matched via a LIKE/prefix search.
	const LOCKOUT_OPTION_PREFIX  = 'wpg_lockout_ip_';
	const ATTEMPTS_OPTION_PREFIX = 'wpg_attempts_ip_';

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
		if ( '' === $ip || WPG_Ip_Whitelist::is_whitelisted( $ip ) ) {
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

	/**
	 * Fires on every failed login (including attempts made while already
	 * locked out, since wp_signon() calls wp_login_failed whenever the
	 * authenticate chain resolves to a WP_Error, regardless of which
	 * filter produced it).
	 */
	public static function record_failed_attempt( $username ) {
		$ip = self::get_client_ip();
		if ( '' === $ip || WPG_Ip_Whitelist::is_whitelisted( $ip ) ) {
			return;
		}

		$username = sanitize_text_field( $username );

		if ( self::is_locked_out( $ip ) ) {
			WPG_Login_Logger::log( $ip, $username, 'lockout' );
			return;
		}

		$attempts_key = self::ATTEMPTS_OPTION_PREFIX . md5( $ip );
		$attempts     = get_option( $attempts_key, array( 'count' => 0 ) );
		$attempts['ip']    = $ip;
		$attempts['count'] = isset( $attempts['count'] ) ? (int) $attempts['count'] + 1 : 1;

		$max_attempts = max( 1, (int) get_option( 'wpg_max_attempts', 5 ) );

		if ( $attempts['count'] >= $max_attempts ) {
			$lockout_minutes = max( 1, (int) get_option( 'wpg_lockout_duration', 30 ) );
			$until           = time() + ( $lockout_minutes * MINUTE_IN_SECONDS );

			update_option(
				self::LOCKOUT_OPTION_PREFIX . md5( $ip ),
				array( 'ip' => $ip, 'until' => $until ),
				false
			);
			delete_option( $attempts_key );

			WPG_Login_Logger::log( $ip, $username, 'lockout' );
			self::maybe_send_lockout_email( $ip, $until );
			return;
		}

		update_option( $attempts_key, $attempts, false );
		WPG_Login_Logger::log( $ip, $username, 'failure' );
	}

	public static function clear_attempts_on_success( $user_login ) {
		$ip = self::get_client_ip();
		if ( '' === $ip ) {
			return;
		}
		delete_option( self::ATTEMPTS_OPTION_PREFIX . md5( $ip ) );
		WPG_Login_Logger::log( $ip, sanitize_text_field( $user_login ), 'success' );
	}

	private static function maybe_send_lockout_email( $ip, $until ) {
		if ( ! get_option( 'wpg_email_notifications', true ) ) {
			return;
		}

		$to = get_option( 'admin_email' );
		if ( ! $to ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name. */
			__( '[%s] IPアドレスがロックアウトされました', 'wp-private-gate' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$body = sprintf(
			/* translators: 1: IP address, 2: lockout expiry date/time. */
			__( "IPアドレス %1\$s がログイン失敗の上限を超えたため、%2\$s までロックアウトされました。", 'wp-private-gate' ),
			$ip,
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $until )
		);

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Returns every currently active lockout as an array of
	 * [ 'option_name' => string, 'ip' => string, 'until' => int ].
	 */
	public static function get_active_lockouts() {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( self::LOCKOUT_OPTION_PREFIX ) . '%'
			)
		);

		$lockouts = array();
		$now      = time();

		foreach ( $rows as $row ) {
			$value = maybe_unserialize( $row->option_value );
			if ( ! is_array( $value ) || ! isset( $value['until'] ) || $value['until'] <= $now ) {
				continue;
			}
			$lockouts[] = array(
				'option_name' => $row->option_name,
				'ip'          => isset( $value['ip'] ) ? $value['ip'] : __( '(不明)', 'wp-private-gate' ),
				'until'       => (int) $value['until'],
			);
		}

		return $lockouts;
	}

	/**
	 * Deletes a lockout option after validating it actually matches our
	 * naming pattern, so a crafted request can't be used to delete an
	 * arbitrary option.
	 */
	public static function unlock_by_option_name( $option_name ) {
		if ( ! preg_match( '/^' . self::LOCKOUT_OPTION_PREFIX . '[a-f0-9]{32}$/', $option_name ) ) {
			return false;
		}
		return delete_option( $option_name );
	}
}
