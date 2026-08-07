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

class Admin
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'add_menu_page']);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_action('admin_action_cp_delete_log', [self::class, 'handle_delete_log']);
        add_action('admin_post_cp_verify_channel', [self::class, 'handle_verify_channel']);
    }

    public static function add_menu_page(): void
    {
        add_menu_page(
            esc_html__('Convoca Publisher', 'convoca-publisher'),
            esc_html__('Convoca Publisher', 'convoca-publisher'),
            'manage_options',
            'convoca-publisher',
            [self::class, 'render_page'],
            'dashicons-share',
            80
        );

        add_submenu_page(
            'convoca-publisher',
            esc_html__('Historial', 'convoca-publisher'),
            esc_html__('Historial', 'convoca-publisher'),
            'manage_options',
            'convoca-publisher-log',
            [self::class, 'render_log_page']
        );

        add_submenu_page(
            'convoca-publisher',
            esc_html__('Acerca de', 'convoca-publisher'),
            esc_html__('Acerca de', 'convoca-publisher'),
            'manage_options',
            'convoca-publisher-about',
            [self::class, 'render_about_page']
        );
    }

    public static function register_settings(): void
    {
        $channels = convoca_publisher()->get_channels();
        foreach ($channels as $channel) {
            foreach ($channel->get_settings_fields() as $key => $field) {
                if (str_ends_with($key, '_template')) {
                    register_setting('convoca_publisher_settings', $key, [
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'show_in_rest'      => false,
                        'default'           => '',
                    ]);
                } else {
                    register_setting('convoca_publisher_settings', $key, [
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'show_in_rest'      => false,
                        'default'           => '',
                    ]);
                }
            }
        }

        register_setting('convoca_publisher_settings', 'convoca_publisher_message_template', [
            'type' => 'string', 'default' => '{title} — {url}',
        ]);
        register_setting('convoca_publisher_settings', 'convoca_publisher_auto_publish', [
            'type' => 'boolean', 'default' => true,
        ]);
        register_setting('convoca_publisher_settings', 'convoca_publisher_enable_scheduler', [
            'type' => 'boolean', 'default' => true,
        ]);
        register_setting('convoca_publisher_settings', 'convoca_publisher_privacy_acknowledged', [
            'type' => 'boolean', 'default' => false,
        ]);
    }

    public static function enqueue_assets(string $hook): void
    {
        if (str_contains($hook, 'convoca-publisher')) {
            wp_add_inline_style('dashicons', '
                .cp-settings-section { background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #c3c4c7; border-radius: 4px; }
                .cp-settings-section h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
                .cp-channel-card { background: #f0f6fc; padding: 15px; border-left: 4px solid #2271b1; margin: 10px 0; border-radius: 0 4px 4px 0; }
                .cp-channel-card.active { border-left-color: #46b450; }
                .cp-channel-card.inactive { border-left-color: #dc3232; }
                .cp-log-row { padding: 8px 0; border-bottom: 1px solid #f0f0f0; display: flex; gap: 15px; align-items: center; }
                .cp-log-row .cp-status { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; text-transform: uppercase; font-weight: 600; }
                .cp-log-row .cp-status.ok { background: #46b450; color: #fff; }
                .cp-log-row .cp-status.fail { background: #dc3232; color: #fff; }
                .cp-template-help { background: #f6f7f7; padding: 10px 15px; border-radius: 4px; margin: 10px 0; font-size: 12px; }
                .cp-template-help code { background: #e8e8e8; padding: 2px 6px; border-radius: 3px; }
                .cp-channel-template { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #ddd; }
                .cp-privacy-notice { background: #fff4e5; padding: 15px; border-left: 4px solid #f0b849; margin: 20px 0; border-radius: 0 4px 4px 0; }
            ');
        }
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'settings';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Convoca Publisher', 'convoca-publisher'); ?></h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=convoca-publisher&amp;tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Configuración', 'convoca-publisher'); ?>
                </a>
                <a href="?page=convoca-publisher&amp;tab=channels" class="nav-tab <?php echo $active_tab === 'channels' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Canales', 'convoca-publisher'); ?>
                </a>
                <a href="?page=convoca-publisher&amp;tab=test" class="nav-tab <?php echo $active_tab === 'test' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Probar', 'convoca-publisher'); ?>
                </a>
                <a href="?page=convoca-publisher&amp;tab=templates" class="nav-tab <?php echo $active_tab === 'templates' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Plantillas', 'convoca-publisher'); ?>
                </a>
                <a href="?page=convoca-publisher&amp;tab=guide" class="nav-tab <?php echo $active_tab === 'guide' ? 'nav-tab-active' : ''; ?>">
                    📖 <?php echo esc_html__('Guía', 'convoca-publisher'); ?>
                </a>
            </nav>
            
            <?php
            if ($active_tab === 'channels') {
                self::render_channels_tab();
            } elseif ($active_tab === 'test') {
                self::render_test_tab();
            } elseif ($active_tab === 'templates') {
                self::render_templates_tab();
            } elseif ($active_tab === 'guide') {
                self::render_guide_tab();
            } else {
                self::render_settings_tab();
            }
        ?>
        </div>
        <?php
    }

    private static function render_settings_tab(): void
    {
        // Handle privacy ack
        if (isset($_POST['convoca_publisher_privacy_ack']) && isset($_POST['_convoca_publisher_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_convoca_publisher_settings_nonce'])), 'convoca_publisher_settings')) {
            update_option('convoca_publisher_privacy_acknowledged', !empty($_POST['convoca_publisher_privacy_ack']));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Aviso de privacidad actualizado.', 'convoca-publisher') . '</p></div>';
        }

        if (isset($_POST['submit'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Ajustes guardados.', 'convoca-publisher') . '</p></div>';
        }
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('convoca_publisher_settings'); ?>
            
            <!-- Aviso de privacidad -->
            <div class="cp-privacy-notice">
                <h3><?php echo esc_html__('🔐 Aviso de privacidad', 'convoca-publisher'); ?></h3>
                <p><?php echo esc_html__('Este plugin envía el título, extracto, URL, imagen destacada y etiquetas de tus entradas a APIs de terceros (Meta, LinkedIn, Twitter/X, TikTok, Google, Telegram, Mastodon). Los tokens de acceso se almacenan cifrados en la base de datos de WordPress (AES-256-GCM).', 'convoca-publisher'); ?></p>
                <p>
                    <label>
                        <input type="checkbox" name="convoca_publisher_privacy_ack" value="1" <?php checked(get_option('convoca_publisher_privacy_acknowledged', false)); ?> />
                        <?php echo esc_html__('He leído y acepto este aviso', 'convoca-publisher'); ?>
                    </label>
                </p>
                <?php wp_nonce_field('convoca_publisher_settings', '_convoca_publisher_settings_nonce'); ?>
            </div>
            
            <div class="cp-settings-section">
                <h2><?php echo esc_html__('Configuración general', 'convoca-publisher'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Publicación automática', 'convoca-publisher'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="convoca_publisher_auto_publish" value="1" <?php checked(get_option('convoca_publisher_auto_publish', true)); ?> />
                                <?php echo esc_html__('Publicar automáticamente al publicar una entrada', 'convoca-publisher'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Programación', 'convoca-publisher'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="convoca_publisher_enable_scheduler" value="1" <?php checked(get_option('convoca_publisher_enable_scheduler', true)); ?> />
                                <?php echo esc_html__('Activar programación de entradas', 'convoca-publisher'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('Publica automáticamente las entradas programadas cuando se publican.', 'convoca-publisher'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php
            $channels = convoca_publisher()->get_channels();
        foreach ($channels as $channel) {
            echo '<div class="cp-settings-section">';
            echo '<h2>' . esc_html($channel->get_name()) . '</h2>';
            echo '<table class="form-table">';
            foreach ($channel->get_settings_fields() as $key => $field) {
                if (str_ends_with($key, '_template')) {
                    continue; // Templates go in the Templates tab
                }
                $value = get_option($key, '');
                echo '<tr>';
                echo '<th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($field['title']) . '</label></th>';
                echo '<td>';
                if (($field['type'] ?? 'text') === 'password') {
                    echo '<input type="password" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                } else {
                    echo '<input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                }
                if (!empty($field['description'])) {
                    echo '<p class="description">' . esc_html($field['description']) . '</p>';
                }
                echo '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';
        }

        submit_button();
        ?>
        </form>
        <?php
    }

    private static function render_templates_tab(): void
    {
        $channels = convoca_publisher()->get_channels();
        ?>
        <div class="cp-settings-section">
            <h2><?php echo esc_html__('Plantillas de mensaje', 'convoca-publisher'); ?></h2>
            
            <div class="cp-template-help">
                <p><strong><?php echo esc_html__('Variables disponibles:', 'convoca-publisher'); ?></strong></p>
                <p>
                    <code>{title}</code> — <?php echo esc_html__('Título de la entrada', 'convoca-publisher'); ?><br>
                    <code>{excerpt}</code> — <?php echo esc_html__('Extracto de la entrada', 'convoca-publisher'); ?><br>
                    <code>{url}</code> — <?php echo esc_html__('Enlace permanente de la entrada', 'convoca-publisher'); ?><br>
                    <code>{hashtags}</code> — <?php echo esc_html__('Primeras 5 etiquetas como hashtags', 'convoca-publisher'); ?><br>
                    <code>{date}</code> — <?php echo esc_html__('Fecha de publicación', 'convoca-publisher'); ?><br>
                    <code>{author}</code> — <?php echo esc_html__('Nombre del autor', 'convoca-publisher'); ?><br>
                    <code>{featured_image}</code> — <?php echo esc_html__('URL de la imagen destacada', 'convoca-publisher'); ?>
                </p>
                <p><?php echo esc_html__('Puedes configurar una plantilla global y/o plantillas específicas por canal.', 'convoca-publisher'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('convoca_publisher_settings'); ?>
                
                <h3><?php echo esc_html__('Plantilla global', 'convoca-publisher'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Mensaje por defecto', 'convoca-publisher'); ?></th>
                        <td>
                            <input type="text" name="convoca_publisher_message_template" value="<?php echo esc_attr(get_option('convoca_publisher_message_template', '{title} — {url}')); ?>" class="regular-text" style="width:100%;max-width:500px;" />
                            <p class="description"><?php echo esc_html__('Se usa cuando un canal no tiene su propia plantilla.', 'convoca-publisher'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php echo esc_html__('Plantillas por canal', 'convoca-publisher'); ?></h3>
                <p><?php echo esc_html__('Déjalo vacío para usar la plantilla global.', 'convoca-publisher'); ?></p>
                
                <?php foreach ($channels as $channel):
                    $tkey = 'convoca_publisher_' . $channel->get_id() . '_template';
                    $tval = get_option($tkey, '');
                    ?>
                <div class="cp-channel-template">
                    <h4><?php echo esc_html($channel->get_name()); ?></h4>
                    <input type="text" name="<?php echo esc_attr($tkey); ?>" value="<?php echo esc_attr($tval); ?>" class="regular-text" style="width:100%;max-width:500px;" placeholder="<?php echo esc_attr__('Usar plantilla global', 'convoca-publisher'); ?>" />
                    <p class="description"><?php echo esc_html__('Plantilla específica para ', 'convoca-publisher') . esc_html($channel->get_name()); ?></p>
                </div>
                <?php endforeach; ?>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private static function render_channels_tab(): void
    {
        $channels = convoca_publisher()->get_channels();

        // Show verification result transient if present
        $verify_result = get_transient('convoca_publisher_verify_result_' . get_current_user_id());
        if (false !== $verify_result) {
            delete_transient('convoca_publisher_verify_result_' . get_current_user_id());
            $class = $verify_result['success'] ? 'notice-success' : 'notice-error';
            $icon = $verify_result['success'] ? '✅' : '❌';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($icon) . ' ' . esc_html($verify_result['message']) . '</p></div>';
        }

        if (empty($channels)) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('No hay canales disponibles.', 'convoca-publisher') . '</p></div>';
            echo '<p>' . esc_html__('Configura al menos un token en la pestaña de Ajustes.', 'convoca-publisher') . '</p>';
        }
        foreach ($channels as $channel) {
            $available = $channel->is_available();
            $class = $available ? 'active' : 'inactive';
            echo '<div class="cp-channel-card ' . esc_attr($class) . '">';
            echo '<h3>' . esc_html($channel->get_name()) . '</h3>';
            echo '<p>' . ($available
                ? '<span style="color:#46b450;">✅ ' . esc_html__('Configurado y operativo', 'convoca-publisher') . '</span>'
                : '<span style="color:#dc3232;">❌ ' . esc_html__('No configurado — ve a Ajustes', 'convoca-publisher') . '</span>')
                . '</p>';
            // Verify button
            $verify_url = wp_nonce_url(
                admin_url('admin-post.php?action=cp_verify_channel&channel=' . $channel->get_id()),
                'convoca_publisher_verify_channel'
            );
            echo '<p><a href="' . esc_url($verify_url) . '" class="button">🔍 ' . esc_html__('Verificar conexión', 'convoca-publisher') . '</a></p>';
            echo '</div>';
        }
    }

    private static function render_test_tab(): void
    {
        $channels = convoca_publisher()->get_channels();
        ?>
        <div class="cp-settings-section">
            <h2><?php echo esc_html__('Prueba de publicación', 'convoca-publisher'); ?></h2>
            <p><?php echo esc_html__('Selecciona una entrada reciente y haz clic en "Publicar en redes" para probar la integración.', 'convoca-publisher'); ?></p>
            
            <form method="post">
                <?php wp_nonce_field('convoca_publisher_test_publish', 'convoca_publisher_test_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Entrada', 'convoca-publisher'); ?></th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'post_type'         => 'post',
                                'name'              => 'convoca_publisher_test_post_id',
                                'show_option_none'  => esc_html__('Seleccionar entrada...', 'convoca-publisher'),
                                'option_none_value' => '',
                                'selected'          => isset($_POST['convoca_publisher_test_post_id']) ? intval($_POST['convoca_publisher_test_post_id']) : 0,
                            ]);
        ?>
                        </td>
                    </tr>
                </table>
                <p class="sp-test-publish">
                    <button type="submit" name="convoca_publisher_do_test" class="button button-primary">
                        <?php echo esc_html__('🚀 Publicar en redes', 'convoca-publisher'); ?>
                    </button>
                </p>
            </form>
            
            <?php
            if (isset($_POST['convoca_publisher_do_test']) && check_admin_referer('convoca_publisher_test_publish', 'convoca_publisher_test_nonce')) {
                $post_id = isset($_POST['convoca_publisher_test_post_id']) ? intval($_POST['convoca_publisher_test_post_id']) : 0;
                if ($post_id > 0) {
                    echo '<h3>' . esc_html__('Resultado:', 'convoca-publisher') . '</h3>';
                    $publisher = Publisher::instance();
                    if ($publisher) {
                        $results = $publisher->publish_post($post_id, true);
                        foreach ($results as $channel_id => $result) {
                            $icon = $result['success'] ? '✅' : '❌';
                            echo '<p>' . esc_html($icon) . ' <strong>' . esc_html($channel_id) . '</strong>: ';
                            if ($result['success']) {
                                echo esc_html__('Publicado! ID: ', 'convoca-publisher') . esc_html($result['post_id'] ?? '');
                            } else {
                                echo esc_html($result['error'] ?? __('Error desconocido', 'convoca-publisher'));
                            }
                            echo '</p>';
                        }
                    }
                }
            }
        ?>
        </div>
        <?php
    }

    public static function render_log_page(): void
    {
        $logs = get_option('convoca_publisher_publish_log', []);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Historial de publicaciones', 'convoca-publisher'); ?></h1>
            <?php if (empty($logs)): ?>
                <p><?php echo esc_html__('No hay publicaciones registradas todavía.', 'convoca-publisher'); ?></p>
            <?php else: ?>
                <p>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=convoca_publisher_delete_log'), 'convoca_publisher_delete_log')); ?>" class="button" onclick="return confirm('<?php echo esc_js(__('¿Borrar todo el historial?', 'convoca-publisher')); ?>');">
                        <?php echo esc_html__('Limpiar historial', 'convoca-publisher'); ?>
                    </a>
                </p>
                <div style="max-height:500px;overflow-y:auto;background:#fff;padding:15px;border:1px solid #c3c4c7;">
                    <?php foreach (array_reverse($logs) as $log): ?>
                        <div class="cp-log-row">
                            <span class="cp-status <?php echo !empty($log['success']) ? 'ok' : 'fail'; ?>">
                                <?php echo !empty($log['success']) ? 'OK' : 'FAIL'; ?>
                            </span>
                            <span style="min-width:120px;color:#666;"><?php echo isset($log['time']) ? esc_html($log['time']) : ''; ?></span>
                            <span style="min-width:80px;font-weight:600;"><?php echo isset($log['channel']) ? esc_html($log['channel']) : ''; ?></span>
                            <span style="flex:1;"><?php echo isset($log['title']) ? esc_html($log['title']) : ''; ?></span>
                            <span style="min-width:80px;font-size:11px;color:#999;"><?php echo esc_html__('Post #', 'convoca-publisher') . (isset($log['post_id']) ? intval($log['post_id']) : ''); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Retry queue stats -->
            <?php if (class_exists(Retry::class)):
                $stats = Retry::get_queue_stats();
                if ($stats['pending'] > 0 || $stats['failed'] > 0): ?>
                <div class="cp-settings-section" style="margin-top:20px;">
                    <h2><?php echo esc_html__('Cola de reintentos', 'convoca-publisher'); ?></h2>
                    <p><?php echo esc_html__('Pendientes: ', 'convoca-publisher') . intval($stats['pending']); ?> | 
                    <?php echo esc_html__('Fallidos: ', 'convoca-publisher') . intval($stats['failed']); ?></p>
                </div>
            <?php endif; endif; ?>
        </div>
        <?php
    }

    public static function render_about_page(): void
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Acerca de Convoca Publisher', 'convoca-publisher'); ?></h1>
            <div class="cp-settings-section">
                <h2><?php echo esc_html__('Convoca Publisher', 'convoca-publisher'); ?> v<?php echo esc_html(CONVOCA_PUBLISHER_VERSION); ?></h2>
                <p><?php echo esc_html__('Plugin de publicación automática en redes sociales para WordPress.', 'convoca-publisher'); ?></p>
                <p><?php echo esc_html__('Parte del ecosistema Convoca.', 'convoca-publisher'); ?></p>
                <ul>
                    <li>📘 <a href="https://github.com/josecarlosnieto91/convoca-publisher" target="_blank">GitHub</a></li>
                    <li>🐛 <a href="https://github.com/josecarlosnieto91/convoca-publisher/issues" target="_blank">Reportar un problema</a></li>
                </ul>
                <h3><?php echo esc_html__('Canales disponibles', 'convoca-publisher'); ?></h3>
                <ul>
                    <li>✅ Facebook / Instagram</li>
                    <li>✅ LinkedIn</li>
                    <li>✅ Twitter / X</li>
                    <li>✅ TikTok</li>
                    <li>✅ Google My Business</li>
                    <li>✅ Telegram</li>
                    <li>✅ Mastodon</li>
                </ul>
            </div>
        </div>
        <?php
    }

    public static function handle_delete_log(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }
        check_admin_referer('convoca_publisher_delete_log');
        delete_option('convoca_publisher_publish_log');
        wp_safe_redirect(admin_url('admin.php?page=convoca-publisher-log'));
        exit;
    }

    /**
     * Handle the verify channel admin-post action.
     */
    public static function handle_verify_channel(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }
        check_admin_referer('convoca_publisher_verify_channel');
        $channel_id = isset($_GET['channel']) ? sanitize_text_field(wp_unslash($_GET['channel'])) : '';
        $channel = convoca_publisher()->get_channel($channel_id);
        if (!$channel) {
            wp_die(esc_html__('Canal no encontrado.', 'convoca-publisher'));
        }
        $result = $channel->verify_connection();
        set_transient('convoca_publisher_verify_result_' . get_current_user_id(), $result, 30);
        wp_safe_redirect(add_query_arg('convoca_publisher_verified', $channel_id, wp_get_referer()));
        exit;
    }

    /**
     * Render the setup guide tab with step-by-step instructions for each channel.
     */
    private static function render_guide_tab(): void
    {
        ?>
        <div class="cp-settings-section">
            <h2><?php echo esc_html__('📖 Guía de configuración', 'convoca-publisher'); ?></h2>
            <p><?php echo esc_html__('Sigue estos pasos para configurar cada red social. Necesitarás una cuenta de desarrollador en cada plataforma para obtener los tokens de acceso.', 'convoca-publisher'); ?></p>
        </div>

        <!-- Facebook / Instagram -->
        <div class="cp-settings-section">
            <h2>📘 <?php echo esc_html__('Facebook / Instagram', 'convoca-publisher'); ?></h2>
            <p><strong><?php echo esc_html__('Requiere:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Página de Facebook, App de Facebook Developer', 'convoca-publisher'); ?></p>
            <ol>
                <li><?php echo sprintf(
                    /* translators: %s: URL/link to the developer portal */
                    esc_html__('Ve a %s', 'convoca-publisher'),
                    '<a href="https://developers.facebook.com/apps/" target="_blank">developers.facebook.com/apps/</a>'
                ); ?></li>
                <li><?php echo esc_html__('Crea una app tipo "Business" o "Sin integración"', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('En Productos, añade "Facebook Login" y "Instagram Graph API"', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Ve a Herramientas → "Generar token de página"', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Selecciona tu página y copia el token', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Copia también el ID de página (Page ID)', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Pega ambos en Ajustes → Facebook / Instagram', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Page Access Token, Page ID, Instagram Business ID (opcional)', 'convoca-publisher'); ?></p>
            <p><a href="https://developers.facebook.com/docs/pages/publishing/" target="_blank">📄 <?php echo esc_html__('Documentación oficial de Meta', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- LinkedIn -->
        <div class="cp-settings-section">
            <h2>💼 <?php echo esc_html__('LinkedIn', 'convoca-publisher'); ?></h2>
            <p><strong><?php echo esc_html__('Requiere:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Cuenta de LinkedIn, App de LinkedIn Developer', 'convoca-publisher'); ?></p>
            <ol>
                <li><?php echo sprintf(
                    /* translators: %s: URL/link to the developer portal */
                    esc_html__('Ve a %s', 'convoca-publisher'),
                    '<a href="https://www.linkedin.com/developers/apps" target="_blank">linkedin.com/developers/apps</a>'
                ); ?></li>
                <li><?php echo esc_html__('Crea una nueva app', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Solicita los permisos (products): "Share on LinkedIn" y "Sign In with LinkedIn"', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Ve a la pestaña Auth y genera un Access Token de prueba (OAuth 2.0)', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Copia el token y pégalo en Ajustes → LinkedIn', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Para la URN: usa tu perfil (urn:li:person:...) o página de empresa (urn:li:organization:...)', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Access Token (OAuth 2.0), URN de perfil/página', 'convoca-publisher'); ?></p>
            <p><a href="https://learn.microsoft.com/en-us/linkedin/marketing/" target="_blank">📄 <?php echo esc_html__('Documentación oficial de LinkedIn', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- Twitter / X -->
        <div class="cp-settings-section">
            <h2>🐦 <?php echo esc_html__('Twitter / X', 'convoca-publisher'); ?></h2>
            <p><strong><?php echo esc_html__('Requiere:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Cuenta de desarrollador de X (antes Twitter), Proyecto en Developer Portal', 'convoca-publisher'); ?></p>
            <ol>
                <li><?php echo sprintf(
                    /* translators: %s: URL/link to the developer portal */
                    esc_html__('Ve a %s', 'convoca-publisher'),
                    '<a href="https://developer.twitter.com/en/portal/dashboard" target="_blank">developer.twitter.com</a>'
                ); ?></li>
                <li><?php echo esc_html__('Crea un proyecto y una app (OAuth 2.0)', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('En "Keys and Tokens", genera un Bearer Token (OAuth 2.0)', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Asegúrate de que la app tenga permisos tweet.read y tweet.write', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Copia el Bearer Token y pégalo en Ajustes → Twitter / X', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Bearer Token (OAuth 2.0)', 'convoca-publisher'); ?></p>
            <p><a href="https://developer.twitter.com/en/docs/twitter-api" target="_blank">📄 <?php echo esc_html__('Documentación oficial de X API', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- TikTok -->
        <div class="cp-settings-section">
            <h2>🎵 <?php echo esc_html__('TikTok', 'convoca-publisher'); ?></h2>
            <p><strong><?php echo esc_html__('Requiere:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Cuenta de desarrollador de TikTok, App en TikTok Developer Portal', 'convoca-publisher'); ?></p>
            <ol>
                <li><?php echo sprintf(
                    /* translators: %s: URL/link to the developer portal */
                    esc_html__('Ve a %s', 'convoca-publisher'),
                    '<a href="https://developers.tiktok.com/" target="_blank">developers.tiktok.com</a>'
                ); ?></li>
                <li><?php echo esc_html__('Crea una app y selecciona los permisos "video.publish" y "user.info.basic"', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Completa el flujo OAuth para obtener un Access Token', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Anota el Open ID (identificador único del usuario)', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Pega ambos en Ajustes → TikTok', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Access Token, Open ID', 'convoca-publisher'); ?></p>
            <p><a href="https://developers.tiktok.com/documentation" target="_blank">📄 <?php echo esc_html__('Documentación oficial de TikTok', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- Google My Business -->
        <div class="cp-settings-section">
            <h2>🏪 <?php echo esc_html__('Google My Business', 'convoca-publisher'); ?></h2>
            <p><strong><?php echo esc_html__('Requiere:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Cuenta de Google, Proyecto en Google Cloud Console, Perfil de empresa en Google', 'convoca-publisher'); ?></p>
            <ol>
                <li><?php echo sprintf(
                    /* translators: %s: URL/link to the developer portal */
                    esc_html__('Ve a %s', 'convoca-publisher'),
                    '<a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a>'
                ); ?></li>
                <li><?php echo esc_html__('Crea un proyecto y habilita la API "Google My Business API"', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Crea credenciales OAuth 2.0 y obtén un token de acceso', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Para obtener el Location ID: usa la API de GMB o la herramienta de administración de Google', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('El formato del Location ID es: accounts/{accountId}/locations/{locationId}', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Pega ambos en Ajustes → Google My Business', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Access Token (OAuth 2.0), Location ID', 'convoca-publisher'); ?></p>
            <p><a href="https://developers.google.com/my-business" target="_blank">📄 <?php echo esc_html__('Documentación oficial de GMB API', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- Telegram -->
        <div class="cp-settings-section">
            <h2>💬 <?php echo esc_html__('Telegram', 'convoca-publisher'); ?></h2>
            <p><strong><?php echo esc_html__('Requiere:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Un bot de Telegram', 'convoca-publisher'); ?></p>
            <ol>
                <li><?php echo esc_html__('Abre Telegram y busca @BotFather', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Envía /newbot y sigue las instrucciones', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Copia el token HTTP API que te da BotFather', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Envía /setprivacy y selecciona Disabled', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Para obtener el Chat ID: envía un mensaje a tu bot, luego visita:', 'convoca-publisher'); ?>
                    <br><code>https://api.telegram.org/bot&lt;TU_TOKEN&gt;/getUpdates</code></li>
                <li><?php echo esc_html__('Copia el "chat":{"id":...} que aparece en la respuesta', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Pega token y chat ID en Ajustes → Telegram', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Token del Bot, Chat ID', 'convoca-publisher'); ?></p>
            <p><a href="https://core.telegram.org/bots/api" target="_blank">📄 <?php echo esc_html__('Documentación oficial de Telegram Bot API', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- Mastodon -->
        <div class="cp-settings-section">
            <h2>🐘 <?php echo esc_html__('Mastodon', 'convoca-publisher'); ?></h2>
            <p><strong><?php echo esc_html__('Requiere:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Cuenta en un servidor de Mastodon', 'convoca-publisher'); ?></p>
            <ol>
                <li><?php echo esc_html__('Inicia sesión en tu instancia de Mastodon (ej: mastodon.social)', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Ve a Preferencias → Desarrollo', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Haz clic en "Nueva aplicación"', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Asigna un nombre (ej: Convoca Publisher) y marca el permiso "write:statuses"', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Copia el "Access Token" que se genera', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Anota también la URL base de tu servidor (ej: https://mastodon.social)', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Pega ambos en Ajustes → Mastodon', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Servidor (URL base), Access Token, Visibilidad (opcional)', 'convoca-publisher'); ?></p>
            <p><a href="https://docs.joinmastodon.org/api/" target="_blank">📄 <?php echo esc_html__('Documentación oficial de Mastodon API', 'convoca-publisher'); ?></a></p>
        </div>
        <?php
    }
}
