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
    public const CRON_HOOK = 'convoca_publisher_scheduled_publish';

    /** Gancho del recolocado por espaciado. */
    public const SPACING_HOOK = 'convoca_publisher_apply_spacing';

    public static function init(): void
    {
        add_filter('cron_schedules', [self::class, 'add_cron_interval']);
        add_action('convoca_publisher_retry_failed_posts', [self::class, 'retry_failed']);
        add_action(self::CRON_HOOK, [self::class, 'publish_scheduled']);
        // El espaciado se recalcula en cada vuelta: lo que caiga dentro del intervalo
        // se recoloca solo, sin que nadie tenga que entrar a la cola.
        add_action(self::SPACING_HOOK, [Queue::class, 'apply_spacing']);

        if (!wp_next_scheduled('convoca_publisher_retry_failed_posts')) {
            wp_schedule_event(time(), 'hourly', 'convoca_publisher_retry_failed_posts');
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'every_15min', self::CRON_HOOK);
        }
        if (!wp_next_scheduled(self::SPACING_HOOK)) {
            wp_schedule_event(time(), 'every_15min', self::SPACING_HOOK);
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
        // Espaciado: no se sueltan varias publicaciones seguidas. Si el último envío
        // programado fue hace menos que el intervalo, este turno no se publica nada;
        // los que estén dentro del intervalo se habrán recolocado solos (Queue).
        if (!Queue::can_publish_now()) {
            return;
        }

        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_value FROM {$wpdb->postmeta}
                 WHERE meta_key = %s
                 AND meta_value <= %d
                 AND meta_value > 0",
                Queue::SCHEDULE_META,
                time()
            )
        );

        // El más antiguo de los que tocan, uno por vuelta: el resto esperan su turno.
        $elegido = null;

        foreach ($rows as $row) {
            $cuando = (int) $row->meta_value;

            if (null === $elegido || $cuando < $elegido['time']) {
                $elegido = ['post_id' => (int) $row->post_id, 'time' => $cuando];
            }
        }

        if (null === $elegido) {
            return;
        }

        $post = get_post($elegido['post_id']);

        if (!$post || 'publish' !== $post->post_status) {
            delete_post_meta($elegido['post_id'], Queue::SCHEDULE_META);

            return;
        }

        $publisher = Publisher::instance();

        if (!$publisher) {
            return;
        }

        $resultado  = $publisher->publish_post($elegido['post_id'], true);
        $hubo_exito = false;

        foreach ($resultado as $cuenta => $envio) {
            if ('_' !== $cuenta[0] && !empty($envio['success'])) {
                $hubo_exito = true;
            }
        }

        if ($hubo_exito) {
            Queue::mark_published();
            Queue::forget_attempts($elegido['post_id']);
            delete_post_meta($elegido['post_id'], Queue::SCHEDULE_META);

            return;
        }

        // No salió en ninguna cuenta. Antes se borraba la marca igual y el envío se perdía
        // en silencio; ahora se le dan unas vueltas más y, agotadas, se pide una mano.
        $intentos = Queue::count_attempt($elegido['post_id']);

        if ($intentos >= Queue::MAX_ATTEMPTS) {
            Queue::give_up($elegido['post_id']);
            Notifications::ask_for_help($elegido['post_id'], $intentos);

            return;
        }

        Queue::reschedule_schedule($elegido['post_id'], time() + Queue::interval());
    }

    /**
     * Cuentas de esta entrada cuyo último envío falló (por su id, no por su nombre).
     *
     * @return string[]
     */
    private static function failed_accounts(int $post_id): array
    {
        $resultados = get_post_meta($post_id, '_convoca_publisher_publish_results', true);

        if (!is_array($resultados)) {
            return [];
        }

        $fallidas = [];

        foreach ($resultados as $cuenta => $envio) {
            if ('_' !== $cuenta[0] && empty($envio['success'])) {
                $fallidas[] = (string) $cuenta;
            }
        }

        return $fallidas;
    }

    public static function retry_failed(): void
    {
        $logs = get_option('convoca_publisher_publish_log', []);
        $failed_posts = [];

        foreach ($logs as $log) {
            if (empty($log['success']) && !empty($log['post_id'])) {
                $failed_posts[$log['post_id']] = ($failed_posts[$log['post_id']] ?? 0) + 1;
            }
        }

        foreach ($failed_posts as $post_id => $count) {
            // Antes se abandonaba a quien hubiera fallado más de una vez, que es justo el que
            // necesita otra vuelta. Ahora se reintenta, pero sólo en las cuentas que fallaron.
            $post = get_post($post_id);
            if (!$post || 'publish' !== $post->post_status) {
                continue;
            }

            $publisher = Publisher::instance();

            if (!$publisher) {
                continue;
            }

            $fallidas = self::failed_accounts((int) $post_id);

            // Sin saber qué cuenta falló, se reintenta en todas menos en las que ya salió.
            $publisher->publish_to_accounts((int) $post_id, $fallidas, true);

            if (Queue::attempts((int) $post_id) >= Queue::MAX_ATTEMPTS) {
                Notifications::ask_for_help((int) $post_id, Queue::MAX_ATTEMPTS);
            }
        }
    }
}
