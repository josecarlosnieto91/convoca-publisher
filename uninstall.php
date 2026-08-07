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
	'convoca_publisher_version',
	'convoca_publisher_auto_publish',
	'convoca_publisher_enable_scheduler',
	'convoca_publisher_message_template',
	'convoca_publisher_publish_log',
	'convoca_publisher_encryption_key',
	'convoca_publisher_privacy_acknowledged',
	// Tokens de canales
	'convoca_publisher_facebook_token',
	'convoca_publisher_facebook_page_id',
	'convoca_publisher_instagram_business_id',
	'convoca_publisher_facebook_template',
	'convoca_publisher_linkedin_token',
	'convoca_publisher_linkedin_urn',
	'convoca_publisher_linkedin_template',
	'convoca_publisher_twitter_bearer_token',
	'convoca_publisher_twitter_template',
	'convoca_publisher_tiktok_token',
	'convoca_publisher_tiktok_open_id',
	'convoca_publisher_tiktok_template',
	'convoca_publisher_gmb_token',
	'convoca_publisher_gmb_location',
	'convoca_publisher_gmb_template',
	'convoca_publisher_telegram_token',
	'convoca_publisher_telegram_chat_id',
	'convoca_publisher_telegram_parse_mode',
	'convoca_publisher_telegram_template',
	'convoca_publisher_mastodon_server',
	'convoca_publisher_mastodon_token',
	'convoca_publisher_mastodon_visibility',
	'convoca_publisher_mastodon_template',
];

foreach ($options as $option) {
	delete_option($option);
}

// 2. Eliminar metadatos de posts
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_convoca_publisher_published']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_convoca_publisher_publish_results']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_convoca_publisher_disabled_channels']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_convoca_publisher_scheduled_publish']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_convoca_publisher_schedule_time']);
$wpdb->delete($wpdb->postmeta, ['meta_key' => '_convoca_publisher_channels']);

// 3. Eliminar user meta (dismissed notices)
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'convoca_publisher_dismiss_%'");

// 4. Eliminar tabla de cola de reintentos
$table = $wpdb->prefix . 'convoca_publisher_retry_queue';
$wpdb->query("DROP TABLE IF EXISTS {$table}");

// 5. Limpiar cron hooks
wp_clear_scheduled_hook('convoca_publisher_retry_failed_posts');
wp_clear_scheduled_hook('convoca_publisher_retry_process');
