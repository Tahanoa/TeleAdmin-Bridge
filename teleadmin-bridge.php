<?php
/**
 * Plugin Name:       TeleAdmin Bridge
 * Plugin URI:        https://github.com/Tahanoa/TeleAdmin-Bridge
 * Description:       Secure Telegram administration bridge for WordPress, WooCommerce, and Tahanoa Invoice Links for ZarinPal.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Tahanoa
 * Author URI:        https://github.com/Tahanoa
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       teleadmin-bridge
 * Domain Path:       /languages
 * Update URI:        false
 */

defined( 'ABSPATH' ) || exit;

define( 'TAB_VERSION', '1.0.0' );
define( 'TAB_FILE', __FILE__ );
define( 'TAB_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAB_URL', plugin_dir_url( __FILE__ ) );

require_once TAB_DIR . 'includes/class-tab-plugin.php';

register_activation_hook( __FILE__, array( 'TAB_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TAB_Plugin', 'deactivate' ) );
TAB_Plugin::instance()->boot();
