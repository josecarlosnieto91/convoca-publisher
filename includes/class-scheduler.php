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

/**
 * Scheduler — publish posts to social media at a scheduled time.
 */
namespace ConvocaPublisher;

defined('ABSPATH') || exit;

class Scheduler
{
    const CRON_HOOK = 'cp_scheduled_publish';

    public static function init(): void
    {
        add_filter('cron_schedules', [self::class, 'add_cron_interval']);
        add_action('cp_retry_failed_posts', [self::class, 'retry_failed']);
        add_action(self::CRON_HOOK, [self::class, 'publish_scheduled']);

        if (!wp_next_scheduled('cp_retry_failed_posts')) {
            wp_schedule_event(time(), 'hourly', 'cp_retry_failed_posts');
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'every_15min', self::CRON_HOOK);
        }
    }

    public static function add_cron_interval(array $schedules): array
    {
        $schedules['every_15min'] = [
            'interval' => 900,
            'display'  => __('Cada 15 minutos', 'convoca-publisher'),
        ];
        return $schedules;
    }

    /**
     * Publish posts whose scheduled time has arrived.
     */
    public static function publish_scheduled(): void
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key = '_cp_schedule_time'
                 AND meta_value <= %d
                 AND meta_value > 0",
                time()
            )
        );

        foreach ($rows as $row) {
            $post_id = (int) $row->post_id;
            $post = get_post($post_id);
            if (!$post || $post->post_status !== 'publish') {
                continue;
            }
            $publisher = Publisher::instance();
            if ($publisher) {
                $publisher->publish_post($post_id, true);
            }
            delete_post_meta($post_id, '_cp_schedule_time');
        }
    }

    public static function retry_failed(): void
    {
        $logs = get_option('cp_publish_log', []);
        $failed_posts = [];

        foreach ($logs as $log) {
            if (empty($log['success']) && !empty($log['post_id'])) {
                $failed_posts[$log['post_id']] = ($failed_posts[$log['post_id']] ?? 0) + 1;
            }
        }

        foreach ($failed_posts as $post_id => $count) {
            if ($count > 1) {
                continue;
            }
            $post = get_post($post_id);
            if ($post && $post->post_status === 'publish') {
                $publisher = Publisher::instance();
                if ($publisher) {
                    $publisher->publish_post($post_id, true);
                }
            }
        }
    }
}
