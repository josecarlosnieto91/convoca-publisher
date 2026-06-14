<?php

namespace ConvocaPublisher;

defined('ABSPATH') || exit;

class Notifications
{
    public static function init(): void
    {
        add_action('admin_notices', [self::class, 'show_alerts']);
        add_action('wp_ajax_cp_dismiss_notice', [self::class, 'dismiss']);
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
        if (str_contains($screen->id, 'convoca-publisher') && !get_option('cp_privacy_acknowledged', false)) {
            echo '<div class="notice notice-warning is-dismissible cp-notice" data-key="privacy">';
            echo '<p><strong>🔐 ' . esc_html__('Convoca Publisher — Aviso de privacidad', 'convoca-publisher') . '</strong></p>';
            echo '<p>' . esc_html__('Este plugin envía datos a APIs de terceros. Por favor, lee y acepta el aviso de privacidad en los ajustes.', 'convoca-publisher') . '</p>';
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

        if (!empty($unconfigured) && !get_user_meta(get_current_user_id(), 'cp_dismiss_unconfigured', true)) {
            echo '<div class="notice notice-warning is-dismissible cp-notice" data-key="unconfigured">';
            echo '<p><strong>🔌 ' . esc_html__('Convoca Publisher:', 'convoca-publisher') . '</strong> ';
            echo esc_html(sprintf(
                __('Canales sin configurar: %s', 'convoca-publisher'),
                implode(', ', $unconfigured)
            ));
            echo ' <a href="' . esc_url(admin_url('admin.php?page=convoca-publisher')) . '">' . esc_html__('Ir a ajustes', 'convoca-publisher') . '</a></p>';
            echo '</div>';
        }

        // 3. Alertar sobre reintentos pendientes
        if (class_exists(Retry::class)) {
            $stats = Retry::get_queue_stats();
            if ($stats['pending'] > 0) {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p>🔄 <strong>' . esc_html__('Convoca Publisher:', 'convoca-publisher') . '</strong> ';
                echo esc_html(sprintf(
                    __('%d publicaciones pendientes de reintentar.', 'convoca-publisher'),
                    $stats['pending']
                ));
                echo '</p></div>';
            }
            if ($stats['failed'] > 0) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p>❌ <strong>' . esc_html__('Convoca Publisher:', 'convoca-publisher') . '</strong> ';
                echo esc_html(sprintf(
                    __('%d publicaciones fallaron definitivamente. Revisa los tokens.', 'convoca-publisher'),
                    $stats['failed']
                ));
                echo '</p></div>';
            }
        }
    }

    public static function dismiss(): void
    {
        check_ajax_referer('cp_dismiss_notice', '_wpnonce');
        $key = isset($_POST['key']) ? sanitize_key($_POST['key']) : '';
        update_user_meta(get_current_user_id(), 'cp_dismiss_' . $key, true);
        wp_send_json(['success' => true]);
    }
}
