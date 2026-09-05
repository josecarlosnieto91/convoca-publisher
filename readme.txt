=== Convoca Publisher ===
Contributors: josecarlosnietoramos
Tags: social-media, publishing, scheduling, telegram, mastodon
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.4.2
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-publish WordPress posts to social media.

== Description ==

Automatically publish your WordPress posts to social media. No subscriptions, no external dependencies. Tokens are encrypted with AES-256-GCM.

Supported networks: Facebook, Instagram, LinkedIn, Twitter/X, TikTok, Google My Business, Telegram, Mastodon.

* Automatic publishing when a post is published
* Metabox with per-network checkboxes, status, and scheduling
* Customizable message templates per channel
* Variables: {title}, {excerpt}, {url}, {hashtags}, {date}, {author}
* Automatic retry queue (max 2 attempts)
* History of the last 200 posts
* REST API for external integrations
* Tokens encrypted with AES-256-GCM

PRO features (require a license):
* Social media post scheduling
* Scheduled publishing queue and advanced retries
* 8 simultaneous channels

= External Services =

This plugin connects to the APIs of the configured social networks (Facebook, Instagram, LinkedIn, Twitter/X, TikTok, Google My Business, Telegram, Mastodon) to publish content. Credentials are stored encrypted locally. It may also contact getconvoca.app to validate PRO licenses.

== Installation ==

1. Upload the `convoca-publisher` folder to `/wp-content/plugins/`
2. Activate the plugin from the Plugins menu
3. Connect your social networks in Settings > Convoca Publisher

== Changelog ==

= 1.4.2 =
* Security: cifrado de tokens limitado a opciones propias (convoca_publisher_*) — antes cifraba cualquier opción *_token de terceros sin filtro de descifrado.

= 1.4.1 =
* Fix: callback de activación correcto (convoca_publisher_activation_check)

= 1.4.0 =
* New: Social media post scheduling
* New: Pre-publish validations (title, featured image)
* New: TikTok channel
* New: Google My Business channel
* Improvement: 42 unit tests, 148 assertions
* Improvement: Detailed guide per channel in the admin dashboard

== Screenshots ==

1. Publishing metabox with per-network checkboxes
2. Channel settings with token configuration
3. Publishing history
4. Message template editor

== Frequently Asked Questions ==

= Does it require Convoca Core? =

Yes. Convoca Publisher requires Convoca Core to be active.

= Which networks are supported? =

Facebook, Instagram, LinkedIn, Twitter/X, TikTok, Google My Business, Telegram, and Mastodon.

= How are tokens stored? =

Tokens are encrypted with AES-256-GCM. No third-party service stores your credentials.

== Upgrade Notice ==

= 1.4.0 =
* New features and compatibility improvements. Recommended update.
