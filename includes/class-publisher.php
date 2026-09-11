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

    /**
     * El mensaje propio de esta entrada (meta), por delante de la plantilla de la cuenta.
     */
    public const MESSAGE_META = '_convoca_publisher_message';

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
     * Modo de moderación previa configurado (off|all|canal).
     */
    public function moderation_mode(): string
    {
        $mode = get_option('convoca_publisher_moderation', 'off');

        return in_array($mode, ['off', 'all', 'canal'], true) ? $mode : 'off';
    }

    /**
     * Indica si un canal concreto requiere moderación previa.
     */
    public function channel_needs_moderation(string $channel_id): bool
    {
        $mode = $this->moderation_mode();

        if ($mode === 'all') {
            return true;
        }
        if ($mode === 'canal') {
            $channels = get_option('convoca_publisher_moderation_channels', []);
            return is_array($channels) && in_array($channel_id, $channels, true);
        }

        return false;
    }

    /**
     * Publicar un post en todos los canales configurados.
     *
     * @param int  $post_id
     * @param bool $force    Ignorar si ya fue publicado
     * @param bool $approved Si es true, se salta la moderación previa (envío aprobado).
     * @return array
     */
    public function publish_post(int $post_id, bool $force = false, bool $approved = false): array
    {
        return $this->publish_to_accounts($post_id, [], $force, $approved);
    }

    /**
     * Publicar en unas cuentas concretas, o en las que le toquen a la entrada si no se dice
     * ninguna.
     *
     * Un solo camino para todo: compartir a mano en una cuenta no puede ser una copia del
     * envío normal, o se queda sin el registro, sin el reintento y sin el recorte por red.
     *
     * @param string[] $account_ids Cuentas destino (ids de cuenta); vacío = las de la entrada.
     */
    public function publish_to_accounts(int $post_id, array $account_ids = [], bool $force = false, bool $approved = false, string $message_override = ''): array
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            return [];
        }

        if (!$force && get_post_meta($post_id, '_convoca_publisher_published', true)) {
            return [];
        }

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

        $sent_any = false;
        $pending_review = false;

        // A qué cuentas va: la regla vive en Queue (desmarcada en el editor o sin
        // credenciales, no recibe la entrada).
        $cuentas = Queue::accounts_for_post($post->ID, $this->channels);

        if ([] !== $account_ids) {
            $cuentas = array_intersect_key($cuentas, array_flip($account_ids));
        }

        foreach ($cuentas as $channel_id => $channel) {
            $message = $this->build_channel_message($post, $channel, $url, $hashtags, $message_override);

            // Lo que admite la red: se recorta lo que no cabe y se avisa de lo que solo conviene.
            $red  = $this->network_of($channel);
            $cabe = Platform_Rules::check($red, $message);
            $avisos = [];

            if (!$cabe['fit']) {
                $avisos['trimmed'] = ['from' => mb_strlen($message), 'to' => mb_strlen($cabe['message'])];
                $warnings[]        = sprintf(
                    /* translators: 1: nombre del canal, 2: caracteres que tenía, 3: caracteres que se enviaron */
                    __('El mensaje no cabía en %1$s: se recortó de %2$d a %3$d caracteres.', 'convoca-publisher'),
                    $channel->get_name(),
                    mb_strlen($message),
                    mb_strlen($cabe['message'])
                );
            }

            if ([] !== $cabe['warnings']) {
                $avisos['warnings'] = $cabe['warnings'];
            }

            $message = $cabe['message'];

            // D15 — Moderación previa: encolar pendiente de revisión en vez de enviar.
            if ($this->channel_needs_moderation($channel_id) && !$approved) {
                Retry::enqueue_review($post_id, $channel_id, $message);
                $pending_review = true;
                $results[$channel_id] = ['success' => false, 'pending_review' => true];

                $this->log_publish([
                    'post_id'  => $post_id,
                    'title'    => $post->post_title,
                    'channel'  => $channel->get_name(),
                    'success'  => false,
                    'time'     => current_time('mysql'),
                    'response' => __('Pendiente de revisión.', 'convoca-publisher'),
                ]);
                continue;
            }

            $result               = $channel->publish($post_id, $message, $url, $image_url);
            $results[$channel_id] = array_merge($result, $avisos);
            $sent_any = true;

            $this->log_publish([
                'post_id'  => $post_id,
                'title'    => $post->post_title,
                'channel'  => $channel->get_name(),
                'success'  => $result['success'],
                'time'     => current_time('mysql'),
                'response' => $result['post_id'] ?? $result['error'] ?? '',
            ]);

            // D14 — Si la red falla, encolar reintento con backoff.
            if (empty($result['success'])) {
                Retry::enqueue($post_id, $channel_id, $message, 0);
            }
        }

        if ($pending_review) {
            update_post_meta($post_id, '_convoca_publisher_moderation', 'pending');
        }

        if ($sent_any) {
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
     * Cómo queda el mensaje de esta entrada en una cuenta, con lo que se mande para este
     * envío concreto por delante (es lo que enseña y usa «compartir ahora en esta cuenta»).
     */
    public function preview_message(int $post_id, string $account_id, string $override = ''): string
    {
        $post = get_post($post_id);
        $canal = $this->channels[$account_id] ?? null;

        if (!$post || !$canal) {
            return '';
        }

        return $this->build_channel_message($post, $canal, (string) get_permalink($post), $this->get_post_hashtags($post), $override);
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
     * De qué red es este canal.
     *
     * Con cuentas (perfiles) el canal es un envoltorio que dice a qué red pertenece; un canal
     * suelto es ya la red. Preguntar por el envoltorio en cada sitio invita a que uno se olvide
     * y acabe aplicando las reglas de otra red (o de ninguna).
     */
    private function network_of(object $channel): string
    {
        if (method_exists($channel, 'get_channel_id')) {
            return (string) $channel->get_channel_id();
        }

        return (string) $channel->get_id();
    }

    /**
     * Construir mensaje específico para un canal usando su plantilla.
     */
    private function build_channel_message(\WP_Post $post, object $channel, string $url, string $hashtags, string $override = ''): string
    {
        // Con cuentas (perfiles), el id del canal es el de la cuenta: la red va aparte.
        $network_id   = $this->network_of($channel);
        $template_key = 'convoca_publisher_' . $network_id . '_template';
        $default_templates = [
            'facebook'        => '{title} — {url} {hashtags}',
            'linkedin'        => '{title} — {url} {hashtags}',
            'twitter'         => '{title} {url} {hashtags}',
            'tiktok'          => '{title}',
            'googlemybusiness' => '{excerpt} — {url}',
            'telegram'        => '{title} — {url} {hashtags}',
            'mastodon'        => '{title} — {url} {hashtags}',
        ];

        $default = $default_templates[$network_id] ?? '{title} — {url}';

        // De lo más concreto a lo más general: lo que se escribe para este envío, lo que se
        // escribe para esta entrada, la plantilla de la cuenta, la de la red y la global.
        $candidatas = array_filter(
            [
                $override,
                (string) get_post_meta($post->ID, self::MESSAGE_META, true),
                $channel instanceof Channel_Profile ? $channel->get_template() : '',
                (string) get_option($template_key, ''),
                (string) get_option('convoca_publisher_message_template', ''),
            ],
            static fn(string $candidata): bool => '' !== trim($candidata)
        );

        $template = '' === trim($override) && [] !== $candidatas
            ? (string) array_values($candidatas)[0]
            : ($override ?: $default);

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
