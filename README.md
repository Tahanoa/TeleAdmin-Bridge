# TeleAdmin Bridge

Secure, bilingual Telegram administration for WordPress. Integrates with WooCommerce and Tahanoa Invoice Links for ZarinPal.

## First setup

1. Create a bot with [@BotFather](https://t.me/BotFather) and copy its token.
2. Activate TeleAdmin Bridge and open **WordPress Admin → TeleAdmin**.
3. Paste the token and save. The plugin configures the webhook, commands, and bot display name.
4. Click **Connect with Telegram**. The one-time administrator pairing link expires after ten minutes.

> Telegram's Bot API does not provide a supported method for a bot to replace its own profile photo. Set the site logo once in BotFather with `/setuserpic`. The plugin automatically applies the WordPress site name as the bot display name.

## Requirements

- WordPress 6.4+
- PHP 7.4+
- HTTPS and a publicly reachable WordPress REST API
- Optional: WooCommerce
- Optional: Tahanoa Invoice Links for ZarinPal 1.6+

## Privacy and security

Only currently authorized `manage_options` users can pair and execute commands. Webhooks use Telegram's secret header plus a random secret path. Products are created as drafts. Bot tokens remain in WordPress options and are never embedded in code or committed to Git.

Licensed under GPL-2.0-or-later.
