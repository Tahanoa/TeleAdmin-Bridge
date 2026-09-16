<?php
defined('WP_UNINSTALL_PLUGIN')||exit;
$settings=(array)get_option('tab_settings',array());
if(empty($settings['delete_data']))return;
if(!empty($settings['bot_token'])&&preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/',$settings['bot_token'])){
	wp_remote_post('https://api.telegram.org/bot'.$settings['bot_token'].'/deleteWebhook',array('timeout'=>5,'redirection'=>0,'body'=>array('drop_pending_updates'=>false)));
}
delete_option('tab_settings');
delete_option('tab_paid_invoices');
delete_option('tab_db_version');
foreach(array('tab_telegram_chat_id','tab_telegram_username','tab_language','tab_admin_language','tab_flow')as$key)delete_metadata('user',0,$key,'',true);
global $wpdb;
$like=$wpdb->esc_like('_transient_tab_').'%';
$timeout_like=$wpdb->esc_like('_transient_timeout_tab_').'%';
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",$like,$timeout_like)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
