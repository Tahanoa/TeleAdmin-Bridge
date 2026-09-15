<?php
defined( 'ABSPATH' ) || exit;

final class TAB_Telegram {
	public static function request( $method, $body = array() ) {
		$s = TAB_Plugin::settings();
		if ( empty( $s['bot_token'] ) || ! preg_match( '/^\d+:[A-Za-z0-9_-]{20,}$/', $s['bot_token'] ) ) {
			return new WP_Error( 'tab_token', __( 'Telegram bot token is not configured.', 'teleadmin-bridge' ) );
		}
		$url = 'https://api.telegram.org/bot' . rawurlencode( $s['bot_token'] ) . '/' . sanitize_key( $method );
		$response = wp_safe_remote_post( $url, array( 'timeout' => 15, 'body' => $body ) );
		if ( is_wp_error( $response ) ) { return $response; }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $data['ok'] ) ) {
			return new WP_Error( 'tab_api', isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : __( 'Telegram API error.', 'teleadmin-bridge' ) );
		}
		return $data['result'];
	}

	public static function send( $chat_id, $text, $keyboard = null ) {
		$body = array( 'chat_id' => (string) $chat_id, 'text' => wp_strip_all_tags( $text ), 'parse_mode' => 'HTML' );
		if ( $keyboard ) { $body['reply_markup'] = wp_json_encode( $keyboard ); }
		return self::request( 'sendMessage', $body );
	}

	public static function broadcast( $text ) {
		$users = get_users( array( 'meta_key' => 'tab_telegram_chat_id', 'fields' => array( 'ID' ) ) );
		foreach ( $users as $user ) {
			if ( user_can( $user->ID, 'manage_options' ) ) { self::send( get_user_meta( $user->ID, 'tab_telegram_chat_id', true ), $text ); }
		}
	}

	public static function menu( $lang = 'fa' ) {
		$fa = 'fa' === $lang;
		return array( 'keyboard' => array(
			array( array( 'text' => $fa ? '📊 وضعیت سایت' : '📊 Site status' ), array( 'text' => $fa ? '🧾 صورتحساب جدید' : '🧾 New invoice' ) ),
			array( array( 'text' => $fa ? '📦 سفارش‌های اخیر' : '📦 Recent orders' ), array( 'text' => $fa ? '➕ محصول جدید' : '➕ New product' ) ),
			array( array( 'text' => $fa ? '🌐 English' : '🌐 فارسی' ) ),
		), 'resize_keyboard' => true );
	}
}
