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

/**
 * Cola de reintentos con backoff configurable y cola de moderación previa.
 *
 * La misma tabla `convoca_publisher_retry_queue` aloja dos flujos:
 *  - Reintentos: entradas con status `pending` que se reintentan con backoff
 *    creciente hasta agotar MAX_ATTEMPTS intentos.
 *  - Moderación: entradas con status `pending_review` que esperan aprobación
 *    manual del administrador antes de publicarse.
 */
class Retry
{
    private const TABLE = 'convoca_publisher_retry_queue';

    private const TABLE_VERSION = 2;

    private const TABLE_VERSION_OPTION = 'convoca_publisher_retry_table_version';

    private const CRON_HOOK = 'convoca_publisher_retry_event';

    private const MAX_ATTEMPTS = 5;

    /** @var array<int, int> Backoff por defecto en horas (1h, 4h, 12h, 24h, 72h). */
    private const DEFAULT_BACKOFF_HOURS = [1, 4, 12, 24, 72];

    private const STATUS_PENDING = 'pending';

    private const STATUS_PROCESSING = 'processing';

    private const STATUS_PENDING_REVIEW = 'pending_review';

    private const STATUS_FAILED = 'failed';

    public static function init(): void
    {
        add_action('init', [self::class, 'create_table']);
        add_action(self::CRON_HOOK, [self::class, 'process_queue']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }
    }

    public static function create_table(): void
    {
        // dbDelta es idempotente, pero evitamos correrlo en cada request.
        if ((int) get_option(self::TABLE_VERSION_OPTION, 0) >= self::TABLE_VERSION) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            channel VARCHAR(50) NOT NULL,
            payload TEXT NULL,
            error_text TEXT NULL,
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            last_attempt DATETIME NULL,
            next_attempt DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_next (next_attempt),
            KEY idx_status (status),
            KEY idx_post (post_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::TABLE_VERSION_OPTION, self::TABLE_VERSION, false);
    }

    /**
     * Backoff configurable vía filtro `convoca_publisher_retry_backoff`.
     *
     * @return array<int, int> Horas de espera entre reintentos.
     */
    public static function get_backoff(): array
    {
        /** @var mixed $backoff */
        $backoff = apply_filters('convoca_publisher_retry_backoff', self::DEFAULT_BACKOFF_HOURS);

        $hours = [];
        foreach ((array) $backoff as $hour) {
            $hour = (int) $hour;
            if ($hour > 0) {
                $hours[] = $hour;
            }
        }

        return $hours !== [] ? $hours : self::DEFAULT_BACKOFF_HOURS;
    }

    /**
     * Timestamp del siguiente intento para un intento dado.
     *
     * @param int      $attempt  Índice del intento (0 = primer reintento).
     * @param int|null $from_ts  Timestamp base (por defecto `time()`).
     * @return int
     */
    public static function get_next_attempt_time(int $attempt, ?int $from_ts = null): int
    {
        $backoff = self::get_backoff();
        $index = max(0, min($attempt, count($backoff) - 1));
        $hours = $backoff[$index];
        $from = $from_ts ?? time();

        return $from + ($hours * HOUR_IN_SECONDS);
    }

    /**
     * Encolar un reintento o una entrada de moderación.
     *
     * @param int    $post_id  ID del post.
     * @param string $channel  ID del canal.
     * @param string $payload  Mensaje construido para el canal.
     * @param int    $attempt  Número de intento previo (0 = aún no reintentado).
     * @param string $status   Estado de la entrada en cola.
     * @return int ID de la fila insertada.
     */
    public static function enqueue(int $post_id, string $channel, string $payload = '', int $attempt = 0, string $status = self::STATUS_PENDING): int
    {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . self::TABLE, [
            'post_id'      => $post_id,
            'channel'      => $channel,
            'payload'      => $payload,
            'error_text'   => '',
            'attempts'     => $attempt,
            'status'       => $status,
            'next_attempt' => gmdate('Y-m-d H:i:s', self::get_next_attempt_time($attempt)),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Encolar una publicación pendiente de revisión (moderación previa).
     *
     * @param int    $post_id  ID del post.
     * @param string $channel  ID del canal.
     * @param string $payload  Mensaje construido para el canal.
     * @return int ID de la fila insertada.
     */
    public static function enqueue_review(int $post_id, string $channel, string $payload = ''): int
    {
        return self::enqueue($post_id, $channel, $payload, 0, self::STATUS_PENDING_REVIEW);
    }

    /**
     * Listar entradas pendientes de revisión.
     *
     * @return array<int, object>
     */
    public static function get_review_items(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY created_at ASC, id ASC",
                self::STATUS_PENDING_REVIEW
            )
        );

        return is_array($items) ? $items : [];
    }

    /**
     * Aprobar una publicación pendiente y disparar el envío al canal.
     *
     * @param int $id ID de la entrada en cola.
     * @return array{success: bool, error?: string, post_id?: string}
     */
    public static function approve(int $id): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$item || ($item->status ?? '') !== self::STATUS_PENDING_REVIEW) {
            return ['success' => false, 'error' => __('Entrada de moderación no encontrada.', 'convoca-publisher')];
        }

        $post = get_post((int) $item->post_id);
        $channel = convoca_publisher()->get_channel((string) $item->channel);

        if (!$post || !$channel) {
            $wpdb->delete($table, ['id' => $id]);
            delete_post_meta((int) $item->post_id, '_convoca_publisher_moderation');
            return ['success' => false, 'error' => __('Post o canal no disponibles.', 'convoca-publisher')];
        }

        $url = get_permalink($post);
        $message = (string) ($item->payload ?? '');
        $result = $channel->publish((int) $item->post_id, $message, $url);

        $wpdb->delete($table, ['id' => $id]);
        delete_post_meta((int) $item->post_id, '_convoca_publisher_moderation');

        // Si falla al aprobar, reintenta con el backoff normal.
        if (empty($result['success'])) {
            self::enqueue((int) $item->post_id, (string) $item->channel, $message, 0);
        }

        return $result;
    }

    /**
     * Rechazar una publicación pendiente y marcarla en el historial.
     *
     * @param int $id ID de la entrada en cola.
     * @return bool
     */
    public static function reject(int $id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        if (!$item) {
            return false;
        }

        $wpdb->delete($table, ['id' => $id]);
        delete_post_meta((int) $item->post_id, '_convoca_publisher_moderation');

        self::log_rejection((int) $item->post_id, (string) $item->channel);

        return true;
    }

    /**
     * Procesar reintentos debidos (next_attempt <= now).
     */
    public static function process_queue(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE (status = %s OR (status = %s AND last_attempt < %s))
                   AND next_attempt <= %s
                 ORDER BY next_attempt ASC LIMIT 20",
                self::STATUS_PENDING,
                self::STATUS_PROCESSING,
                gmdate('Y-m-d H:i:s', time() - 2 * HOUR_IN_SECONDS), // recuperar claims huérfanos (>2h)
                current_time('mysql')
            )
        );

        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            // Claim atómico: transicionar pending -> processing solo si nadie
            // más lo reclamó (evita doble publicación si dos crons se solapan).
            $claimed = $wpdb->update(
                $table,
                [
                    'status'       => self::STATUS_PROCESSING,
                    'last_attempt' => current_time('mysql'),
                ],
                ['id' => $item->id, 'status' => self::STATUS_PENDING]
            );

            if ($claimed === false || $claimed === 0) {
                // Otro proceso ya lo reclamó (o cambió de estado): saltar.
                continue;
            }

            $post = get_post((int) $item->post_id);
            $channel = convoca_publisher()->get_channel((string) $item->channel);

            if (!$post || $post->post_status !== 'publish' || !$channel) {
                // Post eliminado, no publicado o canal desaparecido: descartar.
                $wpdb->delete($table, ['id' => $item->id]);
                continue;
            }

            $url = get_permalink($post);
            $message = (string) ($item->payload ?? '');
            $result = $channel->publish((int) $item->post_id, $message, $url);

            if (!empty($result['success'])) {
                $wpdb->delete($table, ['id' => $item->id]);
                continue;
            }

            $attempt = (int) $item->attempts + 1;
            $error = (string) ($result['error'] ?? $item->error_text ?? '');

            if ($attempt >= self::MAX_ATTEMPTS) {
                // Agotados los reintentos: fallo definitivo + notificación.
                $wpdb->update(
                    $table,
                    [
                        'status'       => self::STATUS_FAILED,
                        'attempts'     => $attempt,
                        'error_text'   => $error,
                        'last_attempt' => current_time('mysql'),
                        'next_attempt' => null,
                    ],
                    ['id' => $item->id]
                );

                self::notify_failed($item, $error);
                continue;
            }

            $wpdb->update(
                $table,
                [
                    'attempts'     => $attempt,
                    'error_text'   => $error,
                    'status'       => self::STATUS_PENDING,
                    'last_attempt' => current_time('mysql'),
                    'next_attempt' => gmdate('Y-m-d H:i:s', self::get_next_attempt_time($attempt)),
                ],
                ['id' => $item->id]
            );
        }
    }

    /**
     * Notificar al administrador un fallo definitivo de reintentos.
     *
     * @param object $item  Fila de la cola.
     * @param string $error Último error registrado.
     */
    private static function notify_failed(object $item, string $error): void
    {
        // Hook para integraciones (email, Slack, etc.).
        do_action('convoca_publisher_retry_failed', $item);

        if (!apply_filters('convoca_publisher_retry_failed_notify_email', true, $item)) {
            return;
        }

        $admin_email = get_option('admin_email');
        if (!$admin_email) {
            return;
        }

        $post = get_post((int) $item->post_id);
        $post_title = $post ? $post->post_title : '';

        $subject = sprintf(
            /* translators: %s: nombre del canal */
            __('[Convoca Publisher] Fallo definitivo al publicar en %s', 'convoca-publisher'),
            (string) $item->channel
        );

        $body = sprintf(
            /* translators: 1: título del post, 2: id del post, 3: canal, 4: error */
            __("No se pudo publicar «%1\$s» (ID %2\$d) en %3\$s tras 5 intentos.\n\nÚltimo error: %4\$s", 'convoca-publisher'),
            (string) $post_title,
            (int) $item->post_id,
            (string) $item->channel,
            $error
        );

        wp_mail((string) $admin_email, $subject, $body);
    }

    /**
     * Registrar un rechazo en el historial de publicaciones.
     */
    private static function log_rejection(int $post_id, string $channel): void
    {
        $post = get_post($post_id);

        $logs = get_option('convoca_publisher_publish_log', []);
        $logs[] = [
            'post_id'  => $post_id,
            'title'    => $post ? $post->post_title : '',
            'channel'  => $channel,
            'success'  => false,
            'time'     => current_time('mysql'),
            'response' => __('Rechazado por moderación.', 'convoca-publisher'),
        ];
        if (count($logs) > 200) {
            $logs = array_slice($logs, -200);
        }
        update_option('convoca_publisher_publish_log', $logs, false);
    }

    /**
     * @return array{pending: int, pending_review: int, failed: int}
     */
    public static function get_queue_stats(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        return [
            'pending'        => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", self::STATUS_PENDING)),
            'pending_review' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", self::STATUS_PENDING_REVIEW)),
            'failed'         => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", self::STATUS_FAILED)),
        ];
    }
}
