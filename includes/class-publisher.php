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

class Publisher
{
    private static ?Publisher $instance = null;
    private array $channels = [];

    public static function init(array $channels): void
    {
        if (null === self::$instance) {
            self::$instance = new self($channels);
        }
        add_action('publish_post', [self::$instance, 'on_publish_post'], 10, 2);
        add_action('future_to_publish', [self::$instance, 'on_scheduled_publish'], 10, 1);
        add_action('convoca_publisher_async_publish', [self::$instance, 'on_async_publish'], 10, 1);
        add_action('wp_ajax_cp_test_publish', [self::$instance, 'ajax_test_publish']);
        add_action('wp_ajax_cp_clear_log', [self::$instance, 'ajax_clear_log']);
    }

    public static function instance(): ?Publisher
    {
        return self::$instance;
    }

    public function __construct(array $channels)
    {
        $this->channels = $channels;
    }

    public function on_publish_post(int $post_id, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (wp_is_post_revision($post_id)) {
            return;
        }
        if (wp_is_post_autosave($post_id)) {
            return;
        }
        if ($post->post_status !== 'publish') {
            return;
        }
        if (get_post_meta($post_id, '_convoca_publisher_published', true)) {
            return;
        }
        if (!get_option('convoca_publisher_auto_publish', true)) {
            return;
        }

        // Si tiene programación, no publicar ahora (lo hará el cron)
        $schedule_ts = (int) get_post_meta($post_id, '_convoca_publisher_schedule_time', true);
        if ($schedule_ts > 0) {
            return;
        }

        // Envío DIFERIDO: encolar para el siguiente tick de cron.
        // No bloquear el guardado del post con llamadas síncronas a las redes.
        if (!wp_next_scheduled('convoca_publisher_async_publish', [$post_id])) {
            wp_schedule_single_event(time() + 5, 'convoca_publisher_async_publish', [$post_id]);
        }
    }

    /**
     * Hook de cron para el envío diferido.
     */
    public function on_async_publish(int $post_id): void
    {
        $this->publish_post($post_id);
    }

    public function on_scheduled_publish(\WP_Post $post): void
    {
        if (!get_option('convoca_publisher_enable_scheduler', true)) {
            return;
        }
        $this->publish_post($post->ID);
    }

    /**
     * Publicar un post en todos los canales configurados.
     *
     * @param int  $post_id
     * @param bool $force   Ignorar si ya fue publicado
     * @return array
     */
    public function publish_post(int $post_id, bool $force = false): array
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            return [];
        }

        if (!$force && get_post_meta($post_id, '_convoca_publisher_published', true)) {
            return [];
        }

        $disabled_channels = get_post_meta($post_id, '_convoca_publisher_disabled_channels', true) ?: [];
        $url = get_permalink($post);
        $image_url = $this->get_featured_image($post);
        $hashtags = $this->get_post_hashtags($post);
        $results = [];

        // Validaciones previas a la publicación
        $warnings = [];
        if (empty(trim($post->post_title))) {
            $warnings[] = __('El título del post está vacío.', 'convoca-publisher');
        }
        if (empty($image_url)) {
            $warnings[] = __('No hay imagen destacada. Algunas redes (Facebook, Twitter) requieren imagen.', 'convoca-publisher');
        }

        foreach ($this->channels as $channel_id => $channel) {
            if (!$channel->is_available()) {
                continue;
            }
            if (in_array($channel_id, $disabled_channels, true)) {
                continue;
            }

            $message = $this->build_channel_message($post, $channel, $url, $hashtags);

            $result = $channel->publish($post_id, $message, $url, $image_url);
            $results[$channel_id] = $result;

            $this->log_publish([
                'post_id'  => $post_id,
                'title'    => $post->post_title,
                'channel'  => $channel->get_name(),
                'success'  => $result['success'],
                'time'     => current_time('mysql'),
                'response' => $result['post_id'] ?? $result['error'] ?? '',
            ]);
        }

        if (!empty($results)) {
            update_post_meta($post_id, '_convoca_publisher_published', true);
            update_post_meta($post_id, '_convoca_publisher_publish_results', $results);
        }

        // Adjuntar warnings al resultado si los hay
        if (!empty($warnings)) {
            $results['_warnings'] = $warnings;
            foreach ($warnings as $w) {
                $this->log_publish([
                    'post_id'  => $post_id,
                    'title'    => $post->post_title,
                    'channel'  => 'VALIDACIÓN',
                    'success'  => false,
                    'time'     => current_time('mysql'),
                    'response' => $w,
                ]);
            }
        }

        return $results;
    }

    /**
     * Obtener el mensaje formateado para un canal específico.
     * Método público que envuelve build_channel_message() para testing.
     */
    public function get_channel_message(int $post_id, string $channel_id): string
    {
        $post = get_post($post_id);
        if (!$post) {
            return '';
        }
        $channel = $this->channels[$channel_id] ?? null;
        if (!$channel) {
            return '';
        }
        $url = get_permalink($post);
        $hashtags = $this->get_post_hashtags($post);
        return $this->build_channel_message($post, $channel, $url, $hashtags);
    }

    /**
     * Construir mensaje específico para un canal usando su plantilla.
     */
    private function build_channel_message(\WP_Post $post, object $channel, string $url, string $hashtags): string
    {
        $template_key = 'convoca_publisher_' . $channel->get_id() . '_template';
        $default_templates = [
            'facebook'        => '{title} — {url} {hashtags}',
            'linkedin'        => '{title} — {url} {hashtags}',
            'twitter'         => '{title} {url} {hashtags}',
            'tiktok'          => '{title}',
            'googlemybusiness' => '{excerpt} — {url}',
            'telegram'        => '{title} — {url} {hashtags}',
            'mastodon'        => '{title} — {url} {hashtags}',
        ];

        $default = $default_templates[$channel->get_id()] ?? '{title} — {url}';
        $template = get_option($template_key, '');
        if (empty($template)) {
            $template = get_option('convoca_publisher_message_template', $default);
        }
        if (empty($template)) {
            $template = $default;
        }

        $excerpt = get_the_excerpt($post);
        if (empty($excerpt)) {
            $excerpt = wp_trim_words($post->post_content, 30, '…');
        }

        $replacements = [
            '{title}'      => $post->post_title,
            '{excerpt}'    => wp_trim_words($excerpt, 25, '…'),
            '{url}'        => $url,
            '{hashtags}'   => $hashtags,
            '{permalink}'  => $url,
            '{date}'       => get_the_date('', $post),
            '{author}'     => get_the_author_meta('display_name', (int) $post->post_author),
            '{featured_image}' => $this->get_featured_image($post),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Obtener hashtags de las primeras 5 etiquetas del post.
     */
    private function get_post_hashtags(\WP_Post $post): string
    {
        $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);
        if (empty($tags)) {
            return '';
        }

        $tags = array_slice($tags, 0, 5);
        $hashtags = array_map(function (string $tag): string {
            $tag = sanitize_title($tag);
            $tag = str_replace(['-', '_', ' '], '', $tag);
            return '#' . $tag;
        }, $tags);

        return implode(' ', $hashtags);
    }

    private function get_featured_image(\WP_Post $post): string
    {
        $thumb_id = get_post_thumbnail_id($post);
        if (!$thumb_id) {
            return '';
        }
        $image = wp_get_attachment_image_src($thumb_id, 'large');
        return $image ? $image[0] : '';
    }

    private function log_publish(array $entry): void
    {
        $logs = get_option('convoca_publisher_publish_log', []);
        $logs[] = $entry;
        if (count($logs) > 200) {
            $logs = array_slice($logs, -200);
        }
        update_option('convoca_publisher_publish_log', $logs, false);
    }

    public function ajax_test_publish(): void
    {
        check_ajax_referer('convoca_publisher_test_publish', '_wpnonce');
        if (!current_user_can('manage_options')) {
            wp_die('-1');
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $results = $this->publish_post($post_id, true);
        wp_send_json($results);
    }

    public function ajax_clear_log(): void
    {
        check_ajax_referer('convoca_publisher_clear_log', '_wpnonce');
        if (!current_user_can('manage_options')) {
            wp_die('-1');
        }
        delete_option('convoca_publisher_publish_log');
        wp_send_json(['success' => true]);
    }
}
