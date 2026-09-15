<?php
defined( 'ABSPATH' ) || exit;

final class TAB_Admin {
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) ); add_action( 'admin_init', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}
	public static function menu() { add_menu_page( 'TeleAdmin Bridge', 'TeleAdmin', 'manage_options', 'teleadmin-bridge', array( __CLASS__, 'page' ), 'dashicons-format-chat', 58 ); }
	public static function assets( $hook ) { if ( 'toplevel_page_teleadmin-bridge' === $hook ) { wp_enqueue_style( 'tab-admin', TAB_URL . 'assets/admin.css', array(), TAB_VERSION ); } }
	public static function save() {
		if ( empty( $_POST['tab_action'] ) || ! current_user_can( 'manage_options' ) ) { return; }
		check_admin_referer( 'tab_save' ); $action = sanitize_key( wp_unslash( $_POST['tab_action'] ) ); $s = TAB_Plugin::settings();
		if ( 'save' === $action ) {
			$s['bot_token'] = sanitize_text_field( wp_unslash( $_POST['bot_token'] ?? '' ) ); $s['language'] = in_array( $_POST['language'] ?? '', array( 'fa','en' ), true ) ? $_POST['language'] : 'fa';
			$s['notify_orders'] = empty( $_POST['notify_orders'] ) ? 0 : 1; $s['notify_invoices'] = empty( $_POST['notify_invoices'] ) ? 0 : 1; update_option( TAB_Plugin::OPTION, $s, false );
			$me = TAB_Telegram::request( 'getMe' ); if ( ! is_wp_error( $me ) ) { $s['bot_username'] = sanitize_text_field( $me['username'] ); update_option( TAB_Plugin::OPTION, $s, false ); self::configure( $s ); add_settings_error( 'tab', 'saved', __( 'Bot connected and webhook configured.', 'teleadmin-bridge' ), 'success' ); } else { add_settings_error( 'tab', 'api', $me->get_error_message(), 'error' ); }
		}
		if ( 'disconnect' === $action ) { delete_user_meta( get_current_user_id(), 'tab_telegram_chat_id' ); }
	}
	private static function configure( $s ) {
		$url = rest_url( 'teleadmin/v1/webhook/' . $s['secret'] ); TAB_Telegram::request( 'setWebhook', array( 'url'=>$url, 'secret_token'=>$s['secret'], 'allowed_updates'=>wp_json_encode(array('message')) ) );
		TAB_Telegram::request( 'setMyCommands', array( 'commands'=>wp_json_encode(array(array('command'=>'start','description'=>'Open admin menu'),array('command'=>'status','description'=>'Site status'))) ) );
		TAB_Telegram::request( 'setMyName', array( 'name'=>mb_substr( wp_specialchars_decode( get_bloginfo('name') ), 0, 64 ) ) );
		$logo = get_site_icon_url( 512 ); if ( $logo ) { TAB_Telegram::request( 'setChatMenuButton', array( 'menu_button'=>wp_json_encode(array('type'=>'commands')) ) ); }
	}
	public static function page() {
		$s = TAB_Plugin::settings(); $chat = get_user_meta( get_current_user_id(), 'tab_telegram_chat_id', true );
		$code = wp_generate_password( 24, false, false ); set_transient( 'tab_pair_' . sanitize_key( $code ), array( 'user_id'=>get_current_user_id() ), 10 * MINUTE_IN_SECONDS );
		$deep = ! empty( $s['bot_username'] ) ? 'https://t.me/' . rawurlencode( $s['bot_username'] ) . '?start=connect_' . rawurlencode( $code ) : '';
		settings_errors( 'tab' ); ?>
		<div class="wrap tab-wrap"><header><div><h1>TeleAdmin Bridge</h1><p><?php esc_html_e( 'Secure WordPress administration from Telegram.', 'teleadmin-bridge' ); ?></p></div><span class="tab-pill <?php echo $chat ? 'ok' : ''; ?>"><?php echo $chat ? esc_html__( 'Admin connected', 'teleadmin-bridge' ) : esc_html__( 'Not connected', 'teleadmin-bridge' ); ?></span></header>
		<div class="tab-grid"><section class="tab-card"><h2><?php esc_html_e( 'Bot configuration', 'teleadmin-bridge' ); ?></h2><p><?php esc_html_e( 'Create a bot with @BotFather, paste its token, then save. TeleAdmin configures the secure webhook, site name, and commands automatically.', 'teleadmin-bridge' ); ?></p>
		<form method="post"><?php wp_nonce_field('tab_save'); ?><input type="hidden" name="tab_action" value="save"><label>BotFather token</label><input class="regular-text" type="password" autocomplete="new-password" name="bot_token" value="<?php echo esc_attr($s['bot_token']); ?>"><label>Language</label><select name="language"><option value="fa" <?php selected($s['language'],'fa'); ?>>فارسی</option><option value="en" <?php selected($s['language'],'en'); ?>>English</option></select><label><input type="checkbox" name="notify_orders" value="1" <?php checked($s['notify_orders']); ?>> WooCommerce notifications</label><label><input type="checkbox" name="notify_invoices" value="1" <?php checked($s['notify_invoices']); ?>> Invoice payment notifications</label><?php submit_button( __( 'Save and configure bot', 'teleadmin-bridge' ) ); ?></form></section>
		<section class="tab-card"><h2><?php esc_html_e( 'Connect this administrator', 'teleadmin-bridge' ); ?></h2><?php if($chat): ?><p class="tab-success">✅ <?php esc_html_e('This WordPress administrator is connected.','teleadmin-bridge'); ?></p><form method="post"><?php wp_nonce_field('tab_save'); ?><input type="hidden" name="tab_action" value="disconnect"><?php submit_button(__('Disconnect my account','teleadmin-bridge'),'secondary'); ?></form><?php elseif($deep): ?><p><?php esc_html_e('This one-time link expires in 10 minutes.','teleadmin-bridge'); ?></p><a class="button button-primary button-hero" href="<?php echo esc_url($deep); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Connect with Telegram','teleadmin-bridge'); ?></a><?php else: ?><p><?php esc_html_e('Save a valid bot token first.','teleadmin-bridge'); ?></p><?php endif; ?></section>
		<section class="tab-card wide"><h2><?php esc_html_e('Integration status','teleadmin-bridge'); ?></h2><div class="tab-status"><span>WordPress <b>✓</b></span><span>WooCommerce <b><?php echo class_exists('WooCommerce')?'✓':'—'; ?></b></span><span>Tahanoa Invoice <b><?php echo class_exists('EZINV_DB')?'✓':'—'; ?></b></span><span>Webhook <b><?php echo !empty($s['bot_username'])?'✓':'—'; ?></b></span></div><p class="description"><?php esc_html_e('For security, only users who currently have the manage_options capability can connect or execute commands. Products are created as drafts.', 'teleadmin-bridge'); ?></p></section></div></div><?php
	}
}
