<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
delete_option( 'tab_settings' ); delete_option( 'tab_paid_invoices' );
$users = get_users( array( 'fields' => 'ids' ) ); foreach ( $users as $id ) { delete_user_meta( $id, 'tab_telegram_chat_id' ); delete_user_meta( $id, 'tab_telegram_username' ); delete_user_meta( $id, 'tab_language' ); delete_user_meta( $id, 'tab_flow' ); }
