<?php
/**
 * Lockout list + login attempt log. Rendered by WPG_Admin::render_settings_page()
 * right after the settings form, so everything lives on one screen.
 */

defined( 'ABSPATH' ) || exit;

$wpg_active_lockouts = WPG_Lockout::get_active_lockouts();
$wpg_recent_entries  = WPG_Login_Logger::get_recent_entries( 100 );

$wpg_result_labels = array(
	'success' => __( '成功', 'wp-private-gate' ),
	'failure' => __( '失敗', 'wp-private-gate' ),
	'lockout' => __( 'ロックアウト', 'wp-private-gate' ),
);
?>
<hr />

<h2><?php esc_html_e( '現在ロックアウト中のIP', 'wp-private-gate' ); ?></h2>

<?php if ( empty( $wpg_active_lockouts ) ) : ?>
	<p><?php esc_html_e( '現在ロックアウト中のIPアドレスはありません。', 'wp-private-gate' ); ?></p>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'IPアドレス', 'wp-private-gate' ); ?></th>
				<th><?php esc_html_e( '解除予定日時', 'wp-private-gate' ); ?></th>
				<th><?php esc_html_e( '操作', 'wp-private-gate' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $wpg_active_lockouts as $wpg_lockout ) : ?>
			<tr>
				<td><?php echo esc_html( $wpg_lockout['ip'] ); ?></td>
				<td>
					<?php
					echo esc_html(
						wp_date(
							get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
							$wpg_lockout['until']
						)
					);
					?>
				</td>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wpg_unlock_ip" />
						<input type="hidden" name="wpg_option_name" value="<?php echo esc_attr( $wpg_lockout['option_name'] ); ?>" />
						<?php wp_nonce_field( 'wpg_unlock_ip_' . $wpg_lockout['option_name'], 'wpg_unlock_nonce' ); ?>
						<button type="submit" class="button">
							<?php esc_html_e( 'ロック解除', 'wp-private-gate' ); ?>
						</button>
					</form>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>

<h2><?php esc_html_e( 'ログイン試行ログ（直近100件）', 'wp-private-gate' ); ?></h2>

<?php if ( empty( $wpg_recent_entries ) ) : ?>
	<p><?php esc_html_e( 'ログイン試行の記録はまだありません。', 'wp-private-gate' ); ?></p>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( '日時', 'wp-private-gate' ); ?></th>
				<th><?php esc_html_e( 'IPアドレス', 'wp-private-gate' ); ?></th>
				<th><?php esc_html_e( 'ユーザー名', 'wp-private-gate' ); ?></th>
				<th><?php esc_html_e( '結果', 'wp-private-gate' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $wpg_recent_entries as $wpg_entry ) : ?>
			<tr>
				<td><?php echo esc_html( $wpg_entry->attempted_at ); ?></td>
				<td><?php echo esc_html( $wpg_entry->ip_address ); ?></td>
				<td><?php echo esc_html( $wpg_entry->username ); ?></td>
				<td>
					<?php
					echo isset( $wpg_result_labels[ $wpg_entry->result ] )
						? esc_html( $wpg_result_labels[ $wpg_entry->result ] )
						: esc_html( $wpg_entry->result );
					?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
