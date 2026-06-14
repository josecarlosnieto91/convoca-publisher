<?php

namespace ConvocaPublisher;

defined('ABSPATH') || exit;

class Scheduler
{
    public static function init(): void
    {
        add_filter('cron_schedules', [self::class, 'add_cron_interval']);
        add_action('cp_retry_failed_posts', [self::class, 'retry_failed']);

        if (!wp_next_scheduled('cp_retry_failed_posts')) {
            wp_schedule_event(time(), 'hourly', 'cp_retry_failed_posts');
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
