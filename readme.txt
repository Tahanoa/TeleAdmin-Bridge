=== TeleAdmin Bridge ===
Contributors: tahanoa
Tags: telegram, woocommerce, admin, notifications, bot
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Secure bilingual Telegram administration for WordPress administrators, WooCommerce, and Tahanoa Invoice Links for ZarinPal.

== Description ==

TeleAdmin Bridge connects individual WordPress administrators to a site-specific Telegram bot. Every incoming command is checked against the administrator's current `manage_options` capability.

Features include WooCommerce order notifications and recent orders, draft product creation, invoice creation with Tahanoa Invoice Links for ZarinPal, payment notifications, Persian and English menus, and a secure one-time pairing flow.

No Telegram token or administrator identifier is shipped with the plugin. Data is sent to Telegram only after an administrator configures and connects a bot.

== Installation ==

1. Upload and activate the plugin.
2. Create a Telegram bot using @BotFather.
3. Open TeleAdmin in WordPress, paste the token, and save.
4. Use the one-time Connect with Telegram button for each authorized administrator.

The site must use HTTPS and its REST API must be publicly reachable by Telegram.

== Security ==

Webhook requests require both an unguessable URL component and Telegram's secret-token header. Updates are deduplicated. Pairing links expire after ten minutes. Only users with `manage_options` can configure, pair, or run bot actions. WooCommerce products are deliberately created as drafts.

== Changelog ==

= 1.2.1 =
* Automatically upgrades existing webhooks to receive inline button callback queries.

= 1.2.0 =
* Added inline bot navigation controls and final confirmation.
* Added Telegram image uploads, categories, simple and variable products, dimensions, weight, stock status, and product editing.
* Added direct WordPress links for products, invoices, and orders.
* Split administration into dedicated submenu pages and replaced emoji with WordPress Dashicons.
* Added configurable complete data and webhook removal during uninstall.

= 1.1.0 =
* Added a bilingual, tabbed administration interface.
* Added step-by-step product and invoice creation with validation and confirmation.
* Hidden unavailable bot actions when WooCommerce or the invoice plugin is inactive.
* Added separate dependency and input validation messages.

= 1.0.1 =
* Fixed Telegram API URL validation errors on some WordPress hosting environments.

= 1.0.0 =
* Initial release.
