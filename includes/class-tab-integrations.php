<?php
defined( 'ABSPATH' ) || exit;

final class TAB_Integrations {
	public static function boot() {
		add_action( 'woocommerce_new_order', array( __CLASS__, 'new_order' ), 20 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'order_status' ), 20, 4 );
		add_action( 'ezinv_invoice_paid', array( __CLASS__, 'invoice_paid' ), 20, 2 );
		add_action( 'updated_option', array( __CLASS__, 'watch_invoice_cache' ), 20, 3 );
	}
	public static function new_order( $id ) { if ( TAB_Plugin::settings()['notify_orders'] ) { $o = wc_get_order( $id ); if ( $o ) { TAB_Telegram::broadcast( "🛒 New order #{$id}\n" . wp_strip_all_tags( $o->get_formatted_order_total() ) ); } } }
	public static function order_status( $id, $from, $to, $order ) { if ( TAB_Plugin::settings()['notify_orders'] ) { TAB_Telegram::broadcast( "📦 Order #{$id}: " . wc_get_order_status_name( $from ) . ' → ' . wc_get_order_status_name( $to ) ); } }
	public static function invoice_paid( $invoice_id, $invoice = null ) { if ( TAB_Plugin::settings()['notify_invoices'] ) { TAB_Telegram::broadcast( '✅ Invoice #' . absint( $invoice_id ) . ' paid.' ); } }
	public static function watch_invoice_cache( $option, $old, $new ) {
		if ( 'ezinv_cache_generation' !== $option || ! TAB_Plugin::settings()['notify_invoices'] || ! class_exists( 'EZINV_DB' ) ) { return; }
		$rows = EZINV_DB::get_recent_invoices( 20 );
		$known = (array) get_option( 'tab_paid_invoices', array() ); $changed = false;
		foreach ( $rows as $row ) { if ( 'paid' === $row->status && empty( $known[ $row->id ] ) ) { $known[ $row->id ] = time(); $changed = true; self::invoice_paid( $row->id, $row ); } }
		if ( $changed ) { update_option( 'tab_paid_invoices', array_slice( $known, -200, null, true ), false ); }
	}
}
