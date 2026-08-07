<?php

/**
 * Convoca Publisher
 *
 * @package    Convoca\Publisher
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */


namespace ConvocaPublisher;

defined('ABSPATH') || exit;

class Retry
{
    private const TABLE = 'convoca_publisher_retry_queue';
    private const MAX_ATTEMPTS = 5;
    private const BACKOFF_MINUTES = [5, 15, 30, 60, 120];

    public static function init(): void
    {
        add_action('init', [self::class, 'create_table']);
        add_action('convoca_publisher_retry_process', [self::class, 'process_queue']);

        if (!wp_next_scheduled('convoca_publisher_retry_process')) {
            wp_schedule_event(time(), 'hourly', 'convoca_publisher_retry_process');
        }
    }

    public static function create_table(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                post_id BIGINT UNSIGNED NOT NULL,
                channel VARCHAR(50) NOT NULL,
                error_text TEXT,
                attempts TINYINT UNSIGNED DEFAULT 0,
                last_attempt DATETIME,
                next_attempt DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_next (next_attempt),
                INDEX idx_post (post_id)
            ) {$charset};";
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }

    public static function enqueue(int $post_id, string $channel, string $error): void
    {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . self::TABLE, [
            'post_id'      => $post_id,
            'channel'      => $channel,
            'error_text'   => $error,
            'attempts'     => 0,
            'next_attempt' => current_time('mysql'),
        ]);
    }

    public static function process_queue(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE next_attempt <= %s AND attempts < %d ORDER BY next_attempt ASC LIMIT 20",
                current_time('mysql'),
                self::MAX_ATTEMPTS
            )
        );

        foreach ($items as $item) {
            $post = get_post($item->post_id);
            if (!$post || $post->post_status !== 'publish') {
                $wpdb->delete($table, ['id' => $item->id]);
                continue;
            }

            $channels = convoca_publisher()->get_channels();
            $channel = $channels[$item->channel] ?? null;
            if (!$channel) {
                $wpdb->delete($table, ['id' => $item->id]);
                continue;
            }

            $url = get_permalink($post);
            $result = $channel->publish($item->post_id, '', $url);

            if (!empty($result['success'])) {
                $wpdb->delete($table, ['id' => $item->id]);
            } else {
                $next = self::BACKOFF_MINUTES[min($item->attempts, count(self::BACKOFF_MINUTES) - 1)];
                $wpdb->update($table, [
                    'attempts'     => $item->attempts + 1,
                    'error_text'   => $result['error'] ?? $item->error_text,
                    'last_attempt' => current_time('mysql'),
                    'next_attempt' => gmdate('Y-m-d H:i:s', strtotime("+{$next} minutes")),
                ], ['id' => $item->id]);
            }
        }
    }

    public static function get_queue_stats(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        return [
            'pending' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE attempts < %d", self::MAX_ATTEMPTS)),
            'failed'  => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE attempts >= %d", self::MAX_ATTEMPTS)),
        ];
    }
}
