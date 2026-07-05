<?php

/**
 * Convoca Publisher
 *
 * @package    Convoca\Publisher
 * @subpackage Convoca-publisher
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

/**
 * Uninstall handler for Convoca Publisher.
 *
 * @package ConvocaPublisher
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// ─── Keep data mode ───
// Define CONVOCA_KEEP_DATA_ON_UNINSTALL in wp-config.php to preserve all data
// when uninstalling. Useful for temporary deactivation + reactivation.
if ( defined( 'CONVOCA_KEEP_DATA_ON_UNINSTALL' ) && CONVOCA_KEEP_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;

// 1. Eliminar opciones del plugin (cp_*)
$options = [
	'cp_version',
	'cp_auto_publish',
	'cp_enable_scheduler',
	'cp_message_template',
	'cp_publish_log',
	'cp_encryption_key',
	'cp_privacy_acknowledged',
	// Tokens de canales
	'cp_facebook_token',
	'cp_facebook_page_id',
	'cp_instagram_business_id',
	'cp_facebook_template',
	'cp_linkedin_token',
	'cp_linkedin_urn',
	'cp_linkedin_template',
	'cp_twitter_bearer_token',
	'cp_twitter_template',
	'cp_tiktok_token',
	'cp_tiktok_open_id',
	'cp_tiktok_template',
	'cp_gmb_token',
	'cp_gmb_location',
	'cp_gmb_template',
	'cp_telegram_token',
	'cp_telegram_chat_id',
	'cp_telegram_parse_mode',
	'cp_telegram_template',
	'cp_mastodon_server',
	'cp_mastodon_token',
	'cp_mastodon_visibility',
	'cp_mastodon_template',
];

foreach ($options as $option) {
	delete_option($option);
}

// 2. Eliminar opciones heredadas sp_*
$legacy_options = [
	'sp_auto_publish',
	'sp_enable_scheduler',
	'sp_message_template',
	'sp_publish_log',
	'sp_facebook_token',
	'sp_facebook_page_id',
	'sp_instagram_business_id',
	'sp_linkedin_token',
	'sp_linkedin_urn',
	'sp_twitter_bearer_token',
	'sp_tiktok_token',
	'sp_tiktok_open_id',
	'sp_gmb_token',
	'sp_gmb_location',
	'sp_telegram_token',
	'sp_telegram_chat_id',
	'sp_telegram_parse_mode',
	'sp_mastodon_server',
	'sp_mastodon_token',
	'sp_mastodon_visibility',
];

foreach ($legacy_options as $option) {
	delete_option($option);
}

// 3. Eliminar metadatos de posts
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_cp_published']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_cp_publish_results']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_cp_disabled_channels']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_cp_scheduled_publish']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_sp_scheduled_publish']);
// Legacy
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_sp_published']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_sp_publish_results']);

// 4. Eliminar user meta (dismissed notices)
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'cp_dismiss_%'");

// 5. Eliminar tabla de cola de reintentos
$table = $wpdb->prefix . 'cp_retry_queue';
$wpdb->query("DROP TABLE IF EXISTS {$table}");

// 6. Limpiar cron hooks
wp_clear_scheduled_hook('cp_retry_failed_posts');
wp_clear_scheduled_hook('cp_retry_process');
wp_clear_scheduled_hook('sp_retry_failed_posts');
