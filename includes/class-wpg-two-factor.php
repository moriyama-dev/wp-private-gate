<?php
/**
 * Optional per-user TOTP (RFC 6238) two-factor authentication. Each user
 * enrolls their own account from their profile screen; no QR image is
 * generated locally or fetched from a third party, since that would mean
 * sending the secret to an external service. Users add the account to an
 * authenticator app (Google Authenticator, Authy, 1Password, etc.) by typing
 * in the "manual entry" secret shown on screen.
 */

defined( 'ABSPATH' ) || exit;

class WPG_Two_Factor {

	const SECRET_META_KEY  = '_wpg_2fa_secret';
	const ENABLED_META_KEY = 'wpg_2fa_enabled';

	public static function init() {
		add_filter( 'authenticate', array( __CLASS__, 'verify_code_on_login' ), 40, 3 );
		add_action( 'login_form', array( __CLASS__, 'render_login_code_field' ) );

		add_action( 'show_user_profile', array( __CLASS__, 'render_profile_section' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_profile_section' ) );

		add_action( 'admin_post_wpg_2fa_enable', array( __CLASS__, 'handle_enable' ) );
		add_action( 'admin_post_wpg_2fa_disable', array( __CLASS__, 'handle_disable' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
	}

	public static function is_enabled( $user_id ) {
		return (bool) get_user_meta( $user_id, self::ENABLED_META_KEY, true );
	}

	/* ---------------------------------------------------------------------
	 * Login-time enforcement
	 * ------------------------------------------------------------------- */

	public static function render_login_code_field() {
		?>
		<p>
			<label for="wpg_2fa_code"><?php esc_html_e( '認証アプリのコード（2FAを有効にしている場合のみ入力）', 'wp-private-gate' ); ?></label>
			<input type="text" name="wpg_2fa_code" id="wpg_2fa_code" class="input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" />
		</p>
		<?php
	}

	public static function verify_code_on_login( $user, $username, $password ) {
		if ( empty( $username ) || empty( $password ) ) {
			return $user;
		}
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return $user;
		}
		if ( ! self::is_enabled( $user->ID ) ) {
			return $user;
		}

		$secret = get_user_meta( $user->ID, self::SECRET_META_KEY, true );
		$code   = isset( $_POST['wpg_2fa_code'] ) ? sanitize_text_field( wp_unslash( $_POST['wpg_2fa_code'] ) ) : '';

		if ( '' === $secret || ! self::verify_totp( $secret, $code ) ) {
			return new WP_Error(
				'wpg_2fa_failed',
				__( '<strong>エラー</strong>: 認証アプリのコードが正しくありません。', 'wp-private-gate' )
			);
		}

		return $user;
	}

	/* ---------------------------------------------------------------------
	 * Profile screen: enroll / disable
	 * ------------------------------------------------------------------- */

	public static function render_profile_section( $profile_user ) {
		$is_own_profile = ( get_current_user_id() === (int) $profile_user->ID );
		if ( ! $is_own_profile && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$enabled = self::is_enabled( $profile_user->ID );
		?>
		<h2><?php esc_html_e( 'WP Private Gate: 二要素認証（2FA）', 'wp-private-gate' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( '状態', 'wp-private-gate' ); ?></th>
				<td>
					<?php if ( $enabled ) : ?>
						<p><strong style="color:#2271b1;"><?php esc_html_e( '有効', 'wp-private-gate' ); ?></strong></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="wpg_2fa_disable" />
							<input type="hidden" name="wpg_user_id" value="<?php echo esc_attr( $profile_user->ID ); ?>" />
							<?php wp_nonce_field( 'wpg_2fa_disable_' . $profile_user->ID, 'wpg_2fa_nonce' ); ?>
							<button type="submit" class="button"><?php esc_html_e( '2FAを無効にする', 'wp-private-gate' ); ?></button>
						</form>
					<?php elseif ( $is_own_profile ) : ?>
						<?php
						$secret = get_user_meta( $profile_user->ID, self::SECRET_META_KEY, true );
						if ( '' === $secret ) {
							$secret = self::generate_secret();
							update_user_meta( $profile_user->ID, self::SECRET_META_KEY, $secret );
						}
						$otpauth_uri = self::get_otpauth_uri( $secret, $profile_user->user_login );
						?>
						<p><?php esc_html_e( '有効になっていません。認証アプリ（Google Authenticator, Authy, 1Passwordなど）に以下のキーを手動で追加し、表示された6桁のコードを入力して有効化してください。', 'wp-private-gate' ); ?></p>
						<p><code style="font-size:1.1em;"><?php echo esc_html( self::format_secret_for_display( $secret ) ); ?></code></p>
						<p><small><?php esc_html_e( 'セットアップ用URI（対応アプリに直接貼り付ける場合）:', 'wp-private-gate' ); ?> <code><?php echo esc_html( $otpauth_uri ); ?></code></small></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="wpg_2fa_enable" />
							<?php wp_nonce_field( 'wpg_2fa_enable_' . $profile_user->ID, 'wpg_2fa_nonce' ); ?>
							<label for="wpg_2fa_confirm_code"><?php esc_html_e( '確認コード:', 'wp-private-gate' ); ?></label>
							<input type="text" name="wpg_2fa_confirm_code" id="wpg_2fa_confirm_code" inputmode="numeric" maxlength="6" class="small-text" />
							<button type="submit" class="button button-primary"><?php esc_html_e( '2FAを有効にする', 'wp-private-gate' ); ?></button>
						</form>
					<?php else : ?>
						<p><?php esc_html_e( '無効', 'wp-private-gate' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function handle_enable() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_die( esc_html__( 'この操作を行う権限がありません。', 'wp-private-gate' ) );
		}
		check_admin_referer( 'wpg_2fa_enable_' . $user_id, 'wpg_2fa_nonce' );

		$secret = get_user_meta( $user_id, self::SECRET_META_KEY, true );
		$code   = isset( $_POST['wpg_2fa_confirm_code'] ) ? sanitize_text_field( wp_unslash( $_POST['wpg_2fa_confirm_code'] ) ) : '';

		$ok = ( '' !== $secret && self::verify_totp( $secret, $code ) );
		if ( $ok ) {
			update_user_meta( $user_id, self::ENABLED_META_KEY, true );
		}

		self::redirect_to_profile( $user_id, $ok ? 'enabled' : 'enable_failed' );
	}

	public static function handle_disable() {
		$user_id = isset( $_POST['wpg_user_id'] ) ? absint( $_POST['wpg_user_id'] ) : 0;
		$current = get_current_user_id();

		if ( ! $user_id || ( $user_id !== $current && ! current_user_can( 'manage_options' ) ) ) {
			wp_die( esc_html__( 'この操作を行う権限がありません。', 'wp-private-gate' ) );
		}
		check_admin_referer( 'wpg_2fa_disable_' . $user_id, 'wpg_2fa_nonce' );

		delete_user_meta( $user_id, self::ENABLED_META_KEY );
		delete_user_meta( $user_id, self::SECRET_META_KEY );

		self::redirect_to_profile( $user_id, 'disabled' );
	}

	private static function redirect_to_profile( $user_id, $status ) {
		$base = ( get_current_user_id() === $user_id ) ? 'profile.php' : 'user-edit.php?user_id=' . $user_id;
		wp_safe_redirect( add_query_arg( 'wpg_2fa_status', $status, admin_url( $base ) ) );
		exit;
	}

	public static function render_notice() {
		if ( ! isset( $_GET['wpg_2fa_status'] ) ) {
			return;
		}

		$messages = array(
			'enabled'       => array( 'success', __( '2FAを有効にしました。', 'wp-private-gate' ) ),
			'enable_failed' => array( 'error', __( '確認コードが正しくないため、2FAを有効にできませんでした。', 'wp-private-gate' ) ),
			'disabled'      => array( 'success', __( '2FAを無効にしました。', 'wp-private-gate' ) ),
		);

		$status = sanitize_key( wp_unslash( $_GET['wpg_2fa_status'] ) );
		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}

		list( $type, $text ) = $messages[ $status ];
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $text ) );
	}

	/* ---------------------------------------------------------------------
	 * TOTP (RFC 6238) primitives — SHA1, 6 digits, 30s step.
	 * ------------------------------------------------------------------- */

	private static function generate_secret( $bytes = 20 ) {
		return self::base32_encode( random_bytes( $bytes ) );
	}

	private static function format_secret_for_display( $secret ) {
		return trim( chunk_split( $secret, 4, ' ' ) );
	}

	private static function get_otpauth_uri( $secret, $user_login ) {
		$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$label     = rawurlencode( $site_name . ':' . $user_login );
		$issuer    = rawurlencode( $site_name );

		return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
	}

	private static function verify_totp( $secret, $code, $window = 1 ) {
		$code = preg_replace( '/\s+/', '', (string) $code );
		if ( ! preg_match( '/^\d{6}$/', $code ) ) {
			return false;
		}

		$timeslice = (int) floor( time() / 30 );

		for ( $offset = -$window; $offset <= $window; $offset++ ) {
			if ( hash_equals( self::totp_at( $secret, $timeslice + $offset ), $code ) ) {
				return true;
			}
		}
		return false;
	}

	private static function totp_at( $secret, $timeslice ) {
		$key    = self::base32_decode( $secret );
		$time   = pack( 'N*', 0 ) . pack( 'N*', $timeslice );
		$hash   = hash_hmac( 'sha1', $time, $key, true );
		$offset = ord( substr( $hash, -1 ) ) & 0x0F;

		$truncated = (
			( ( ord( $hash[ $offset ] ) & 0x7F ) << 24 ) |
			( ( ord( $hash[ $offset + 1 ] ) & 0xFF ) << 16 ) |
			( ( ord( $hash[ $offset + 2 ] ) & 0xFF ) << 8 ) |
			( ord( $hash[ $offset + 3 ] ) & 0xFF )
		);

		return str_pad( (string) ( $truncated % 1000000 ), 6, '0', STR_PAD_LEFT );
	}

	private static function base32_encode( $data ) {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$bits     = '';
		foreach ( str_split( $data ) as $byte ) {
			$bits .= str_pad( decbin( ord( $byte ) ), 8, '0', STR_PAD_LEFT );
		}

		$output = '';
		foreach ( str_split( $bits, 5 ) as $chunk ) {
			$chunk   = str_pad( $chunk, 5, '0', STR_PAD_RIGHT );
			$output .= $alphabet[ bindec( $chunk ) ];
		}
		return $output;
	}

	private static function base32_decode( $secret ) {
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$secret   = strtoupper( preg_replace( '/[^A-Z2-7]/i', '', (string) $secret ) );

		$bits = '';
		foreach ( str_split( $secret ) as $char ) {
			$pos = strpos( $alphabet, $char );
			if ( false === $pos ) {
				continue;
			}
			$bits .= str_pad( decbin( $pos ), 5, '0', STR_PAD_LEFT );
		}

		$bytes = '';
		foreach ( str_split( $bits, 8 ) as $byte ) {
			if ( 8 !== strlen( $byte ) ) {
				continue;
			}
			$bytes .= chr( bindec( $byte ) );
		}
		return $bytes;
	}
}
