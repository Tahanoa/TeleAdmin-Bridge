<?php
defined( 'ABSPATH' ) || exit;

final class TAB_Plugin {
	const OPTION = 'tab_settings';
	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}

	public function boot() {
		load_plugin_textdomain( 'teleadmin-bridge', false, dirname( plugin_basename( TAB_FILE ) ) . '/languages' );
		require_once TAB_DIR . 'includes/class-tab-telegram.php';
		require_once TAB_DIR . 'includes/class-tab-integrations.php';
		require_once TAB_DIR . 'includes/class-tab-rest.php';
		if ( is_admin() ) { require_once TAB_DIR . 'includes/class-tab-admin.php'; TAB_Admin::boot(); }
		TAB_REST::boot();
		TAB_Integrations::boot();
	}

	public static function settings() {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), array(
			'bot_token' => '', 'bot_username' => '', 'secret' => '', 'language' => 'fa',
			'notify_orders' => 1, 'notify_invoices' => 1,
		) );
	}

	public static function activate() {
		$s = self::settings();
		if ( empty( $s['secret'] ) ) { $s['secret'] = wp_generate_password( 48, false, false ); }
		update_option( self::OPTION, $s, false );
	}

	public static function deactivate() {
		$s = self::settings();
		if ( ! empty( $s['bot_token'] ) ) { TAB_Telegram::request( 'deleteWebhook', array( 'drop_pending_updates' => false ) ); }
	}
}
