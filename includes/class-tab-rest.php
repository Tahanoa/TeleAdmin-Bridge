<?php
defined( 'ABSPATH' ) || exit;

final class TAB_REST {
	public static function boot() { add_action( 'rest_api_init', array( __CLASS__, 'routes' ) ); }
	public static function routes() {
		register_rest_route( 'teleadmin/v1', '/webhook/(?P<secret>[A-Za-z0-9]+)', array(
			'methods' => 'POST', 'callback' => array( __CLASS__, 'webhook' ),
			'permission_callback' => array( __CLASS__, 'authorize' ),
		) );
	}
	public static function authorize( WP_REST_Request $request ) {
		$s = TAB_Plugin::settings();
		$header = (string) $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );
		return ! empty( $s['secret'] ) && hash_equals( $s['secret'], (string) $request['secret'] ) && hash_equals( $s['secret'], $header );
	}
	public static function webhook( WP_REST_Request $request ) {
		$update = $request->get_json_params();
		if ( ! is_array( $update ) || empty( $update['update_id'] ) ) { return new WP_REST_Response( array( 'ok' => false ), 400 ); }
		$key = 'tab_update_' . absint( $update['update_id'] );
		if ( get_transient( $key ) ) { return array( 'ok' => true ); }
		set_transient( $key, 1, DAY_IN_SECONDS );
		self::handle( $update );
		return array( 'ok' => true );
	}
	private static function handle( $update ) {
		$m = isset( $update['message'] ) ? $update['message'] : array();
		$chat = isset( $m['chat']['id'] ) ? (string) $m['chat']['id'] : '';
		$text = isset( $m['text'] ) ? sanitize_text_field( $m['text'] ) : '';
		if ( ! $chat || ! $text ) { return; }
		if ( 0 === strpos( $text, '/start connect_' ) ) { self::connect( $chat, substr( $text, 15 ), $m ); return; }
		$user = self::authorized_user( $chat );
		if ( ! $user ) { TAB_Telegram::send( $chat, '⛔ Access denied. Connect from WordPress → TeleAdmin Bridge.' ); return; }
		$lang = get_user_meta( $user->ID, 'tab_language', true ) ?: TAB_Plugin::settings()['language'];
		if ( false !== strpos( $text, 'English' ) ) { update_user_meta( $user->ID, 'tab_language', 'en' ); TAB_Telegram::send( $chat, 'Language changed.', TAB_Telegram::menu( 'en' ) ); return; }
		if ( false !== strpos( $text, 'فارسی' ) ) { update_user_meta( $user->ID, 'tab_language', 'fa' ); TAB_Telegram::send( $chat, 'زبان تغییر کرد.', TAB_Telegram::menu( 'fa' ) ); return; }
		if ( false !== strpos( $text, 'وضعیت' ) || false !== stripos( $text, 'status' ) ) { self::status( $chat, $lang ); return; }
		if ( false !== strpos( $text, 'سفارش' ) || false !== stripos( $text, 'orders' ) ) { self::orders( $chat, $lang ); return; }
		if ( false !== strpos( $text, 'صورتحساب جدید' ) || false !== stripos( $text, 'new invoice' ) ) { update_user_meta( $user->ID, 'tab_flow', 'invoice' ); TAB_Telegram::send( $chat, 'fa' === $lang ? "اطلاعات را بفرستید:\nعنوان | مبلغ تومان | نام مشتری | موبایل" : "Send: Title | Amount (Toman) | Customer | Mobile" ); return; }
		if ( false !== strpos( $text, 'محصول جدید' ) || false !== stripos( $text, 'new product' ) ) { update_user_meta( $user->ID, 'tab_flow', 'product' ); TAB_Telegram::send( $chat, 'fa' === $lang ? "اطلاعات را بفرستید:\nنام محصول | قیمت | موجودی" : "Send: Product name | Price | Stock" ); return; }
		$flow = get_user_meta( $user->ID, 'tab_flow', true );
		if ( 'invoice' === $flow ) { self::invoice( $user, $chat, $text, $lang ); return; }
		if ( 'product' === $flow ) { self::product( $user, $chat, $text, $lang ); return; }
		TAB_Telegram::send( $chat, 'fa' === $lang ? 'یک گزینه را انتخاب کنید.' : 'Choose an option.', TAB_Telegram::menu( $lang ) );
	}
	private static function connect( $chat, $code, $message ) {
		$record = get_transient( 'tab_pair_' . sanitize_key( $code ) );
		if ( ! is_array( $record ) || empty( $record['user_id'] ) || ! user_can( $record['user_id'], 'manage_options' ) ) { TAB_Telegram::send( $chat, 'Invalid or expired connection code.' ); return; }
		update_user_meta( $record['user_id'], 'tab_telegram_chat_id', $chat );
		update_user_meta( $record['user_id'], 'tab_telegram_username', sanitize_text_field( $message['from']['username'] ?? '' ) );
		delete_transient( 'tab_pair_' . sanitize_key( $code ) );
		TAB_Telegram::send( $chat, '✅ ' . sprintf( 'Connected to %s', wp_specialchars_decode( get_bloginfo( 'name' ) ) ), TAB_Telegram::menu( TAB_Plugin::settings()['language'] ) );
	}
	private static function authorized_user( $chat ) {
		$users = get_users( array( 'meta_key' => 'tab_telegram_chat_id', 'meta_value' => $chat, 'number' => 1 ) );
		return ! empty( $users ) && user_can( $users[0]->ID, 'manage_options' ) ? $users[0] : false;
	}
	private static function status( $chat, $lang ) {
		$orders = class_exists( 'WooCommerce' ) ? count( wc_get_orders( array( 'limit' => 20, 'status' => array( 'wc-processing', 'wc-on-hold' ), 'return' => 'ids' ) ) ) : 0;
		$text = ( 'fa' === $lang ? '✅ سایت فعال است' : '✅ Site is online' ) . "\nWordPress: " . get_bloginfo( 'version' ) . "\nWooCommerce: " . ( class_exists( 'WooCommerce' ) ? 'ON' : 'OFF' ) . "\nOpen orders: " . $orders;
		TAB_Telegram::send( $chat, $text );
	}
	private static function orders( $chat, $lang ) {
		if ( ! function_exists( 'wc_get_orders' ) ) { TAB_Telegram::send( $chat, 'WooCommerce is not active.' ); return; }
		$lines = array( 'fa' === $lang ? '📦 سفارش‌های اخیر' : '📦 Recent orders' );
		foreach ( wc_get_orders( array( 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC' ) ) as $o ) { $lines[] = '#' . $o->get_id() . ' — ' . wp_strip_all_tags( $o->get_formatted_order_total() ) . ' — ' . wc_get_order_status_name( $o->get_status() ); }
		TAB_Telegram::send( $chat, implode( "\n", $lines ) );
	}
	private static function invoice( $user, $chat, $text, $lang ) {
		delete_user_meta( $user->ID, 'tab_flow' );
		$p = array_map( 'trim', explode( '|', $text ) );
		if ( count( $p ) < 2 || ! class_exists( 'EZINV_DB' ) ) { TAB_Telegram::send( $chat, 'fa' === $lang ? 'اطلاعات نامعتبر است یا افزونه صورتحساب فعال نیست.' : 'Invalid data or invoice plugin inactive.' ); return; }
		$amount = absint( preg_replace( '/\D+/', '', $p[1] ) );
		if ( ! $amount ) { TAB_Telegram::send( $chat, 'Invalid amount.' ); return; }
		$token = bin2hex( random_bytes( 24 ) ); $now = current_time( 'mysql' );
		$id = EZINV_DB::insert_invoice( array( 'token'=>$token, 'title'=>sanitize_text_field($p[0]), 'customer_name'=>sanitize_text_field($p[2] ?? ''), 'mobile'=>preg_replace('/[^0-9+]/','',$p[3] ?? ''), 'email'=>'', 'items_json'=>null, 'subtotal_irr'=>$amount*10, 'shipping_irr'=>0, 'amount_irr'=>$amount*10, 'description'=>'Created by TeleAdmin Bridge', 'status'=>'pending', 'created_at'=>$now, 'updated_at'=>$now ) );
		if ( is_wp_error( $id ) ) { TAB_Telegram::send( $chat, $id->get_error_message() ); return; }
		$url = class_exists( 'EZINV_Invoice' ) ? EZINV_Invoice::invoice_url( $token ) : home_url( '/?ezinv_invoice=' . rawurlencode( $token ) );
		TAB_Telegram::send( $chat, ( 'fa' === $lang ? '✅ صورتحساب ساخته شد:' : '✅ Invoice created:' ) . "\n" . esc_url_raw( $url ), TAB_Telegram::menu( $lang ) );
	}
	private static function product( $user, $chat, $text, $lang ) {
		delete_user_meta( $user->ID, 'tab_flow' ); $p = array_map( 'trim', explode( '|', $text ) );
		if ( count( $p ) < 2 || ! class_exists( 'WC_Product_Simple' ) ) { TAB_Telegram::send( $chat, 'Invalid data or WooCommerce inactive.' ); return; }
		$product = new WC_Product_Simple(); $product->set_name( sanitize_text_field( $p[0] ) ); $product->set_regular_price( wc_format_decimal( $p[1] ) ); $product->set_status( 'draft' );
		if ( isset( $p[2] ) && '' !== $p[2] ) { $product->set_manage_stock( true ); $product->set_stock_quantity( absint( $p[2] ) ); }
		$id = $product->save(); TAB_Telegram::send( $chat, ( 'fa' === $lang ? '✅ محصول پیش‌نویس ساخته شد: #' : '✅ Draft product created: #' ) . $id, TAB_Telegram::menu( $lang ) );
	}
}