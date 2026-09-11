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

class Notifications
{
    public static function init(): void
    {
        add_action('admin_notices', [self::class, 'show_alerts']);
        add_action('wp_ajax_cp_dismiss_notice', [self::class, 'dismiss']);
    }

    /**
     * Pedir una mano por correo cuando un envío no sale y ya se ha insistido bastante.
     *
     * En pantalla el aviso solo lo ve quien entra; el correo llega aunque nadie mire, que es
     * justo el caso en el que un envío se queda perdido.
     */
    public static function ask_for_help(int $post_id, int $attempts): void
    {
        $post = get_post($post_id);

        if (!$post) {
            return;
        }

        update_option(
            'convoca_publisher_needs_help',
            // Con `+` y no con `array_merge`: la clave es el id de la entrada (numerica), y
            // array_merge reindexa las claves numericas — el aviso se quedaba sin saber de qué
            // entrada hablaba. Y `true` en el slice por lo mismo.
            array_slice(
                [(string) $post_id => ['title' => $post->post_title, 'tries' => $attempts, 'time' => current_time('mysql')]]
                + (array) get_option('convoca_publisher_needs_help', []),
                0,
                20,
                true
            )
        );

        if (!get_option('convoca_publisher_email_alerts', '1')) {
            return;
        }

        $resultados = get_post_meta($post_id, '_convoca_publisher_publish_results', true);
        $motivos    = [];

        if (is_array($resultados)) {
            foreach ($resultados as $cuenta => $envio) {
                if ('_' === $cuenta[0] || !empty($envio['success'])) {
                    continue;
                }

                $motivos[] = $cuenta . ': ' . (string) ($envio['error'] ?? __('sin detalle', 'convoca-publisher'));
            }
        }

        self::email_admins(
            sprintf(
                /* translators: %s: título de la entrada */
                __('[%s] Un envío no ha salido', 'convoca-publisher'),
                wp_specialchars_decode((string) get_option('blogname'), ENT_QUOTES)
            ),
            sprintf(
                /* translators: 1: título, 2: número de intentos, 3: URL de la entrada, 4: detalle */
                __("La entrada «%1\$s» no ha salido después de %2\$d intentos.\n\nEntrada: %3\$s\n\nQué dijo cada red:\n%4\$s\n\nSe puede reintentar a mano desde la cola del plugin.", 'convoca-publisher'),
                $post->post_title,
                $attempts,
                (string) get_permalink($post),
                [] === $motivos ? __('(sin detalle)', 'convoca-publisher') : implode("\n", $motivos)
            )
        );
    }

    /**
     * Mandar un correo a quien administra el sitio.
     */
    public static function email_admins(string $subject, string $body): bool
    {
        $destino = (string) get_option('admin_email');

        if ('' === $destino) {
            return false;
        }

        return (bool) wp_mail($destino, $subject, $body);
    }

    public static function show_alerts(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        // 1. Privacy notice reminder (only on plugin pages)
        if (str_contains($screen->id, 'convoca-publisher') && !get_option('convoca_publisher_privacy_acknowledged', false)) {
            echo '<div class="notice notice-warning is-dismissible cp-notice" data-key="privacy">';
            echo '<p><strong>🔐 ' . esc_html__('Convoca Publisher — Aviso de privacidad', 'convoca-publisher') . '</strong></p>';
            echo '<p>' . esc_html__('Este plugin envía datos a APIs de terceros. Por favor, lee y acepta el aviso de privacidad en Configuración.', 'convoca-publisher') . '</p>';
            echo '</div>';
        }

        // 2. Alertar sobre canales sin configurar
        $channels = convoca_publisher()->get_channels();
        $unconfigured = [];
        foreach ($channels as $id => $ch) {
            if (!$ch->is_available()) {
                $unconfigured[] = $ch->get_name();
            }
        }

        if (!empty($unconfigured) && !get_user_meta(get_current_user_id(), 'convoca_publisher_dismiss_unconfigured', true)) {
            echo '<div class="notice notice-warning is-dismissible cp-notice" data-key="unconfigured">';
            echo '<p><strong>🔌 ' . esc_html__('Convoca Publisher:', 'convoca-publisher') . '</strong> ';
            echo esc_html(sprintf(
                /* translators: %s: comma-separated list of unconfigured channel names */
                __('Canales sin configurar: %s', 'convoca-publisher'),
                implode(', ', $unconfigured)
            ));
            echo ' <a href="' . esc_url(admin_url('admin.php?page=convoca-publisher')) . '">' . esc_html__('Ir a Configuración', 'convoca-publisher') . '</a></p>';
            echo '</div>';
        }

        // 3. Alertar sobre reintentos pendientes
        if (class_exists(Retry::class)) {
            $stats = Retry::get_queue_stats();
            if ($stats['pending'] > 0) {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p>🔄 <strong>' . esc_html__('Convoca Publisher:', 'convoca-publisher') . '</strong> ';
                echo esc_html(sprintf(
                    /* translators: %d: number of pending retry publications */
                    __('%d publicaciones pendientes de reintentar.', 'convoca-publisher'),
                    $stats['pending']
                ));
                echo '</p></div>';
            }
            if ($stats['failed'] > 0) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>❌ <strong>' . esc_html__('Convoca Publisher:', 'convoca-publisher') . '</strong> ';
                echo esc_html(sprintf(
                    /* translators: %d: number of permanently failed publications */
                    __('%d publicaciones fallaron definitivamente. Revisa los tokens.', 'convoca-publisher'),
                    $stats['failed']
                ));
                echo '</p></div>';
            }
        }
    }

    public static function dismiss(): void
    {
        check_ajax_referer('convoca_publisher_dismiss_notice', '_wpnonce');
        $key = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
        update_user_meta(get_current_user_id(), 'convoca_publisher_dismiss_' . $key, true);
        wp_send_json(['success' => true]);
    }
}
