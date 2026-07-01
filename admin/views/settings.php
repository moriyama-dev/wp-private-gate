<?php
/**
 * Settings screen markup. Rendered by WPG_Admin::render_settings_page().
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap wpg-settings">
	<h1><?php esc_html_e( 'WP Private Gate', 'wp-private-gate' ); ?></h1>
	<p><?php esc_html_e( '未ログインユーザーからのサイト全体へのアクセスをブロックし、REST APIとXML-RPCを無効化します。ログイン失敗の許容回数とロック時間はこちらから変更できます。', 'wp-private-gate' ); ?></p>

	<form method="post" action="options.php">
		<?php
		settings_fields( WPG_Admin::OPTION_GROUP );
		do_settings_sections( WPG_Admin::PAGE_SLUG );
		submit_button();
		?>
	</form>
</div>
