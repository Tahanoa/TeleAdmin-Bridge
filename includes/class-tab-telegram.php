<?php
defined( 'ABSPATH' ) || exit;

final class TAB_Telegram {
	public static function request( $method, $body = array() ) {
		$s = TAB_Plugin::settings();
		if ( empty( $s['bot_token'] ) || ! preg_match( '/^\d+:[A-Za-z0-9_-]{20,}$/', $s['bot_token'] ) ) {
			return new WP_Error( 'tab_token', __( 'Telegram bot token is not configured.', 'teleadmin-bridge' ) );
		}
		/*
		 * The endpoint host is fixed by the plugin and the token is validated above.
		 * Do not URL-encode the colon in Telegram bot tokens: some WordPress hosts
		 * reject the resulting path as an invalid URL before making the request.
		 */
		$url = 'https://api.telegram.org/bot' . $s['bot_token'] . '/' . sanitize_key( $method );
		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 0,
				'body'        => $body,
			)
		);
		if ( is_wp_error( $response ) ) { return $response; }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || empty( $data['ok'] ) ) {
			return new WP_Error( 'tab_api', isset( $data['description'] ) ? sanitize_text_field( $data['description'] ) : __( 'Telegram API error.', 'teleadmin-bridge' ) );
		}
		return $data['result'];
	}

	public static function send( $chat_id, $text, $keyboard = null ) {
		$body = array( 'chat_id' => (string) $chat_id, 'text' => wp_strip_all_tags( $text ) );
		if ( $keyboard ) { $body['reply_markup'] = wp_json_encode( $keyboard ); }
		return self::request( 'sendMessage', $body );
	}

	public static function edit( $chat_id, $message_id, $text, $keyboard = null ) {
		$body = array( 'chat_id'=>(string)$chat_id, 'message_id'=>absint($message_id), 'text'=>wp_strip_all_tags($text) );
		if($keyboard)$body['reply_markup']=wp_json_encode($keyboard);
		return self::request('editMessageText',$body);
	}

	public static function answer_callback( $id ) { return self::request('answerCallbackQuery',array('callback_query_id'=>sanitize_text_field($id))); }

	public static function inline( $rows ) { return array( 'inline_keyboard'=>$rows ); }

	public static function file_url( $file_id ) {
		$file=self::request('getFile',array('file_id'=>sanitize_text_field($file_id))); if(is_wp_error($file)||empty($file['file_path']))return new WP_Error('tab_file',__('Could not retrieve the Telegram file.','teleadmin-bridge'));
		$s=TAB_Plugin::settings(); return 'https://api.telegram.org/file/bot'.$s['bot_token'].'/'.ltrim($file['file_path'],'/');
	}

	public static function broadcast( $text ) {
		$users = get_users( array( 'meta_key' => 'tab_telegram_chat_id', 'fields' => array( 'ID' ) ) );
		foreach ( $users as $user ) {
			if ( user_can( $user->ID, 'manage_options' ) ) { self::send( get_user_meta( $user->ID, 'tab_telegram_chat_id', true ), $text ); }
		}
	}

	public static function menu( $lang = 'fa' ) {
		$fa = 'fa' === $lang;
		$rows = array( array( array( 'text' => $fa ? 'وضعیت سایت' : 'Site status', 'callback_data'=>'menu:status' ) ) );
		if ( class_exists( 'WooCommerce' ) ) {
			$rows[] = array( array( 'text' => $fa ? 'سفارش‌های اخیر' : 'Recent orders', 'callback_data'=>'menu:orders' ), array( 'text' => $fa ? 'محصول جدید' : 'New product', 'callback_data'=>'product:new' ) );
			$rows[] = array( array( 'text' => $fa ? 'ویرایش محصول' : 'Edit product', 'callback_data'=>'product:edit' ) );
		}
		if(class_exists('EZINV_DB'))$rows[]=array(array('text'=>$fa?'صورتحساب جدید':'New invoice','callback_data'=>'invoice:new'));
		$rows[] = array( array( 'text' => $fa ? 'English' : 'فارسی', 'callback_data'=>$fa?'lang:en':'lang:fa' ) );
		return self::inline($rows);
	}
}
