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
        add_action('admin_post_cp_save_account', [self::class, 'handle_save_account']);
        add_action('admin_post_cp_delete_account', [self::class, 'handle_delete_account']);
        add_action('admin_post_cp_approve_review', [self::class, 'handle_approve_review']);
        add_action('admin_post_cp_reject_review', [self::class, 'handle_reject_review']);
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
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => [self::class, 'sanitize_privacy_ack'],
        ]);
        register_setting('convoca_publisher_settings', 'convoca_publisher_moderation', [
            'type'              => 'string',
            'sanitize_callback' => [self::class, 'sanitize_moderation_mode'],
            'show_in_rest'      => false,
            'default'           => 'off',
        ]);
        register_setting('convoca_publisher_settings', 'convoca_publisher_moderation_channels', [
            'type'              => 'array',
            'sanitize_callback' => [self::class, 'sanitize_moderation_channels'],
            'show_in_rest'      => false,
            'default'           => [],
        ]);
    }

    /**
     * Sanitizar el modo de moderación (off|all|canal).
     *
     * @param mixed $value
     */
    public static function sanitize_moderation_mode($value): string
    {
        $value = (string) $value;

        return in_array($value, ['off', 'all', 'canal'], true) ? $value : 'off';
    }

    /**
     * Sanitize the privacy acknowledgement.
     *
     * El aviso vive solo en la pestaña Configuración, pero el grupo de opciones se
     * guarda también desde otras pestañas (Plantillas). Si el campo no viene en
     * el POST, se conserva el valor guardado en lugar de desmarcarlo.
     *
     * @param mixed $value Valor enviado.
     * @return bool
     */
    public static function sanitize_privacy_ack($value): bool
    {
        if (!isset($_POST['convoca_publisher_privacy_acknowledged'])) {
            return (bool) get_option('convoca_publisher_privacy_acknowledged', false);
        }

        return !empty($value);
    }

    /**
     * Sanitizar la lista de canales con moderación previa.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    public static function sanitize_moderation_channels($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $valid = array_keys(convoca_publisher()->get_channels());
        $clean = [];
        foreach ($value as $channel) {
            $channel = sanitize_key((string) $channel);
            if ($channel !== '' && in_array($channel, $valid, true)) {
                $clean[] = $channel;
            }
        }

        return $clean;
    }

    public static function enqueue_assets(string $hook): void
    {
        $es_pantalla_del_plugin = str_contains($hook, 'convoca-publisher');
        $es_editor              = in_array($hook, ['post.php', 'post-new.php'], true);

        if (!$es_pantalla_del_plugin && !$es_editor) {
            return;
        }

        // Ficheros de verdad, encolados solo donde hacen falta (antes iban en un
        // `wp_add_inline_style` colgado de `dashicons`: dependía de que WordPress
        // encolara ese fichero y el estilo no se podía cachear).
        wp_enqueue_style(
            'convoca-publisher-admin',
            CONVOCA_PUBLISHER_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CONVOCA_PUBLISHER_VERSION
        );

        if (!$es_pantalla_del_plugin) {
            return;
        }

        wp_enqueue_script(
            'convoca-publisher-admin',
            CONVOCA_PUBLISHER_PLUGIN_URL . 'assets/js/admin.js',
            [],
            CONVOCA_PUBLISHER_VERSION,
            true
        );
    }

    public static function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $accounts   = convoca_publisher()->get_channels();
        $networks   = Plugin::networks();
        $channel_id = isset($_GET['canal']) ? sanitize_key(wp_unslash((string) $_GET['canal'])) : '';

        // La pantalla de una cuenta manda sobre las pestañas. Si el id es de una red que
        // todavía no tiene cuenta, es el alta de una cuenta nueva de esa red.
        if ('' !== $channel_id) {
            if (isset($accounts[$channel_id])) {
                self::render_channel_screen($accounts[$channel_id]);

                return;
            }

            if (isset($networks[$channel_id])) {
                self::render_channel_screen($networks[$channel_id]);

                return;
            }
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : 'channels';

        $tabs = [
            'channels'   => __('Canales', 'convoca-publisher'),
            'settings'   => __('Configuración', 'convoca-publisher'),
            'templates'  => __('Plantillas', 'convoca-publisher'),
            'test'       => __('Probar', 'convoca-publisher'),
            'moderation' => __('Moderación', 'convoca-publisher'),
            'guide'      => __('Guía', 'convoca-publisher'),
        ];
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Convoca Publisher', 'convoca-publisher'); ?></h1>

            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $slug => $label) : ?>
                    <a href="<?php echo esc_url(self::tab_url($slug)); ?>" class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php
            switch ($active_tab) {
                case 'settings':
                    self::render_settings_tab();
                    break;
                case 'templates':
                    self::render_templates_tab();
                    break;
                case 'test':
                    self::render_test_tab();
                    break;
                case 'moderation':
                    self::render_moderation_tab();
                    break;
                case 'guide':
                    self::render_guide_tab();
                    break;
                case 'channels':
                default:
                    self::render_channels_tab();
                    break;
            }
        ?>
        </div>
        <?php
    }

    /**
     * URL de una pestaña del panel (una sola forma de construirla en todo el plugin).
     */
    public static function tab_url(string $tab, array $extra = []): string
    {
        return add_query_arg(
            array_merge(
                [
                    'page' => 'convoca-publisher',
                    'tab'  => $tab,
                ],
                $extra
            ),
            admin_url('admin.php')
        );
    }

    /**
     * Pantalla de un canal: estado, campos, verificación, plantilla y su guía, todo
     * junto. Es el sitio único de un canal: no hay que ir a otra pestaña a buscar nada.
     */
    private static function render_channel_screen(object $channel): void
    {
        $account  = $channel instanceof Channel_Profile ? $channel : null;
        $networks = Plugin::networks();
        $network_id = $account ? $account->get_channel_id() : $channel->get_id();
        $network    = $networks[$network_id] ?? $channel;
        $fields     = $network->get_settings_fields();
        $template_key = 'convoca_publisher_' . $network_id . '_template';
        $settings   = $account ? $account->get_settings() : [];
        $status     = $account ? self::channel_status($account) : [];
        $guardado   = isset($_GET['guardado']);
        $new_account = !$account;

        $nombre = $account
            ? $account->get_name()
            : sprintf(
                /* translators: %s: nombre de la red */
                __('%s — cuenta nueva', 'convoca-publisher'),
                $network->get_name()
            );
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($nombre); ?></h1>
            <?php if ($account) : ?>
                <?php echo self::status_badge(self::channel_status($account)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado.?>
            <?php endif; ?>

            <p>
                <a href="<?php echo esc_url(self::tab_url('channels')); ?>">&larr; <?php echo esc_html__('Todos los canales', 'convoca-publisher'); ?></a>
            </p>

            <?php if ($guardado) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Cuenta guardada.', 'convoca-publisher'); ?></p></div>
            <?php endif; ?>

            <?php if (!$account || !$account->is_available()) : ?>
                <div class="cp-notice cp-notice--warn">
                    <p><?php echo esc_html__('Mientras falten datos, esta cuenta no publicará nada. Rellénalos, guarda y usa «Verificar conexión».', 'convoca-publisher'); ?></p>
                </div>
            <?php elseif (!empty($status['detail'])) : ?>
                <div class="cp-notice cp-notice--warn">
                    <p><?php echo esc_html($status['detail']); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="cp_save_account" />
                <input type="hidden" name="red" value="<?php echo esc_attr($network_id); ?>" />
                <input type="hidden" name="cuenta" value="<?php echo esc_attr($account ? $account->get_id() : ''); ?>" />
                <?php wp_nonce_field('convoca_publisher_save_account'); ?>

                <div class="cp-section">
                    <h2><?php echo esc_html__('Esta cuenta', 'convoca-publisher'); ?></h2>
                    <div class="cp-field">
                        <label for="cuenta_nombre"><?php echo esc_html__('Nombre', 'convoca-publisher'); ?></label>
                        <input type="text" id="cuenta_nombre" name="cuenta_nombre" value="<?php echo esc_attr($nombre); ?>" class="cp-input regular-text" />
                        <p class="description"><?php echo esc_html__('Con qué nombre la reconoces. Por ejemplo: «Telegram — Centro Social», «Facebook — Grupo».', 'convoca-publisher'); ?></p>
                    </div>

                    <?php if ($account) : ?>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: identificador de la cuenta */
                                esc_html__('Identificador: %s (es el que usan la cola y el historial).', 'convoca-publisher'),
                                esc_html($account->get_id())
                            );
                        ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="cp-section">
                    <h2><?php echo esc_html__('Credenciales y datos', 'convoca-publisher'); ?></h2>
                    <?php if (empty($fields)) : ?>
                        <p><?php echo esc_html__('Esta red no necesita configuración.', 'convoca-publisher'); ?></p>
                    <?php endif; ?>

                    <?php foreach ($fields as $key => $field) : ?>
                        <?php if ($key === $template_key) : ?>
                            <?php continue; ?>
                        <?php endif; ?>

                        <?php
                        $type  = ($field['type'] ?? 'text') === 'password' ? 'password' : 'text';
                        $value = (string) ($settings[$key] ?? '');
                        ?>
                        <div class="cp-field">
                            <label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($field['title'] ?? $key); ?></label>

                            <input
                                type="<?php echo esc_attr($type); ?>"
                                id="<?php echo esc_attr($key); ?>"
                                name="<?php echo esc_attr($key); ?>"
                                value="<?php echo esc_attr($value); ?>"
                                class="cp-input regular-text"
                            />

                            <?php if ('password' === $type) : ?>
                                <button
                                    type="button"
                                    class="button-link"
                                    data-cp-toggle="#<?php echo esc_attr($key); ?>"
                                    data-cp-show="<?php echo esc_attr__('Mostrar', 'convoca-publisher'); ?>"
                                    data-cp-hide="<?php echo esc_attr__('Ocultar', 'convoca-publisher'); ?>"
                                    aria-pressed="false"
                                ><?php echo esc_html__('Mostrar', 'convoca-publisher'); ?></button>
                            <?php endif; ?>

                            <?php if (!empty($field['description'])) : ?>
                                <p class="description"><?php echo esc_html($field['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (isset($fields[$template_key])) : ?>
                    <div class="cp-section">
                        <h2><?php echo esc_html__('Plantilla de esta cuenta', 'convoca-publisher'); ?></h2>
                        <div class="cp-field">
                            <label for="cuenta_plantilla"><?php echo esc_html__('Mensaje', 'convoca-publisher'); ?></label>
                            <input
                                type="text"
                                id="cuenta_plantilla"
                                name="cuenta_plantilla"
                                value="<?php echo esc_attr($account ? $account->get_template() : ''); ?>"
                                class="cp-input cp-input--wide"
                                placeholder="<?php echo esc_attr__('Usar la plantilla de la red', 'convoca-publisher'); ?>"
                            />
                            <p class="description">
                                <?php echo esc_html__('Déjalo vacío para usar la plantilla de la red (pestaña Plantillas). Variables: {title}, {excerpt}, {url}, {hashtags}, {date}, {author}.', 'convoca-publisher'); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php submit_button($new_account ? __('Crear cuenta', 'convoca-publisher') : __('Guardar cambios', 'convoca-publisher')); ?>
            </form>

            <?php if ($account) : ?>
                <div class="cp-section">
                    <h2><?php echo esc_html__('Verificar la conexión', 'convoca-publisher'); ?></h2>
                    <p><?php echo esc_html__('Pregunta a la red si la credencial de esta cuenta sigue sirviendo. No publica nada ni gasta cuota de envío.', 'convoca-publisher'); ?></p>
                    <p><?php echo self::verify_button($account); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado.?></p>
                </div>
            <?php endif; ?>

            <div class="cp-section">
                <h2><?php echo esc_html__('Cómo conseguir estas credenciales', 'convoca-publisher'); ?></h2>
                <p>
                    <a href="<?php echo esc_url(self::tab_url('guide') . '#canal-' . $network_id); ?>">
                        <?php
                        printf(
                            /* translators: %s: nombre de la red social */
                            esc_html__('Guía paso a paso de %s', 'convoca-publisher'),
                            esc_html($network->get_name())
                        );
        ?>
                    </a>
                </p>
            </div>

            <?php if ($account) : ?>
                <div class="cp-section">
                    <h2><?php echo esc_html__('Borrar esta cuenta', 'convoca-publisher'); ?></h2>
                    <p class="description"><?php echo esc_html__('Se borra su configuración. Las entradas ya publicadas y el historial se quedan como están.', 'convoca-publisher'); ?></p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="cp_delete_account" />
                        <input type="hidden" name="cuenta" value="<?php echo esc_attr($account->get_id()); ?>" />
                        <?php wp_nonce_field('convoca_publisher_delete_account'); ?>
                        <button
                            type="submit"
                            class="button button-link-delete"
                            data-cp-confirm="<?php echo esc_attr__('¿Borrar esta cuenta?', 'convoca-publisher'); ?>"
                        ><?php echo esc_html__('Borrar cuenta', 'convoca-publisher'); ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_settings_tab(): void
    {
        if (isset($_POST['submit'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cambios guardados.', 'convoca-publisher') . '</p></div>';
        }
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('convoca_publisher_settings'); ?>
            
            <!-- Aviso de privacidad -->
            <div class="cp-notice cp-notice--warn">
                <h3><?php echo esc_html__('🔐 Aviso de privacidad', 'convoca-publisher'); ?></h3>
                <p><?php echo esc_html__('Este plugin envía el título, extracto, URL, imagen destacada y etiquetas de tus entradas a APIs de terceros (Meta, LinkedIn, Twitter/X, TikTok, Google, Telegram, Mastodon). Los tokens de acceso se almacenan cifrados en la base de datos de WordPress (AES-256-GCM).', 'convoca-publisher'); ?></p>
                <p>
                    <label>
                        <input type="hidden" name="convoca_publisher_privacy_acknowledged" value="0" />
                        <input type="checkbox" name="convoca_publisher_privacy_acknowledged" value="1" <?php checked(get_option('convoca_publisher_privacy_acknowledged', false)); ?> />
                        <?php echo esc_html__('He leído y acepto este aviso', 'convoca-publisher'); ?>
                    </label>
                </p>
            </div>
            
            <div class="cp-section">
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
            
            <div class="cp-notice">
                <p>
                    <?php echo esc_html__('Los datos y las plantillas de cada red viven en su propia pantalla.', 'convoca-publisher'); ?>
                    <a href="<?php echo esc_url(self::tab_url('channels')); ?>"><?php echo esc_html__('Ir a Canales', 'convoca-publisher'); ?></a>
                </p>
            </div>

        <div class="cp-section">
            <h2><?php echo esc_html__('Moderación previa', 'convoca-publisher'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="convoca_publisher_moderation"><?php echo esc_html__('Modo de moderación', 'convoca-publisher'); ?></label></th>
                    <td>
                        <select name="convoca_publisher_moderation" id="convoca_publisher_moderation">
                            <option value="off" <?php selected(get_option('convoca_publisher_moderation', 'off'), 'off'); ?>><?php echo esc_html__('Desactivada (publicación automática)', 'convoca-publisher'); ?></option>
                            <option value="all" <?php selected(get_option('convoca_publisher_moderation', 'off'), 'all'); ?>><?php echo esc_html__('Todas las publicaciones requieren revisión', 'convoca-publisher'); ?></option>
                            <option value="canal" <?php selected(get_option('convoca_publisher_moderation', 'off'), 'canal'); ?>><?php echo esc_html__('Solo canales seleccionados', 'convoca-publisher'); ?></option>
                        </select>
                        <p class="description"><?php echo esc_html__('Cuando está activa, las publicaciones quedan pendientes de revisión en la pestaña Moderación antes de enviarse a las redes.', 'convoca-publisher'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Canales con moderación', 'convoca-publisher'); ?></th>
                    <td>
                        <input type="hidden" name="convoca_publisher_moderation_channels[]" value="" />
                        <?php foreach (convoca_publisher()->get_channels() as $channel) : ?>
                            <?php $checked = in_array($channel->get_id(), (array) get_option('convoca_publisher_moderation_channels', []), true) ? 'checked' : ''; ?>
                            <label class="cp-check" data-cp-moderation-group>
                                <input type="checkbox" name="convoca_publisher_moderation_channels[]" value="<?php echo esc_attr($channel->get_id()); ?>" data-cp-moderation-channel <?php echo esc_attr($checked); ?>> <?php echo esc_html($channel->get_name()); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php echo esc_html__('Se aplican cuando el modo es "Solo canales seleccionados".', 'convoca-publisher'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
        </form>
        <?php
    }

    private static function render_templates_tab(): void
    {
        $channels = convoca_publisher()->get_channels();
        ?>
        <div class="cp-section">
            <h2><?php echo esc_html__('Plantillas de mensaje', 'convoca-publisher'); ?></h2>
            
            <div class="cp-help">
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
                            <input type="text" name="convoca_publisher_message_template" value="<?php echo esc_attr(get_option('convoca_publisher_message_template', '{title} — {url}')); ?>" class="cp-input cp-input--wide" />
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
                    <input type="text" name="<?php echo esc_attr($tkey); ?>" value="<?php echo esc_attr($tval); ?>" class="cp-input cp-input--wide" placeholder="<?php echo esc_attr__('Usar plantilla global', 'convoca-publisher'); ?>" />
                    <p class="description"><?php echo esc_html__('Plantilla específica para ', 'convoca-publisher') . esc_html($channel->get_name()); ?></p>
                </div>
                <?php endforeach; ?>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Estado de un canal, tal y como se ve en su tarjeta y en su pantalla.
     *
     * ✅ Configurado · ❌ Falta token · ⚠️ Error al verificar · 🔑 Necesita reconexión.
     * El resultado de la última verificación solo cuenta si la configuración no ha
     * cambiado desde entonces: si el token es otro, el estado viejo no vale.
     *
     * @return array{key: string, class: string, icon: string, label: string, detail?: string}
     */
    public static function channel_status(object $channel): array
    {
        $channel_id = $channel->get_id();

        if (!$channel->is_available()) {
            return [
                'key'   => 'missing',
                'class' => 'cp-status--off',
                'icon'  => '❌',
                'label' => __('Falta token', 'convoca-publisher'),
            ];
        }

        $estados = (array) get_option('convoca_publisher_verify_status', []);
        $estado  = isset($estados[$channel_id]) ? (array) $estados[$channel_id] : [];

        if ($estado && ($estado['fingerprint'] ?? '') !== self::channel_fingerprint($channel)) {
            $estado = []; // La configuración cambió: el resultado anterior ya no dice nada.
        }

        if ($estado && empty($estado['success'])) {
            $detalle    = (string) ($estado['message'] ?? '');
            $reconectar = self::looks_like_auth_error($detalle);

            return [
                'key'    => $reconectar ? 'reconnect' : 'error',
                'class'  => $reconectar ? 'cp-status--warn' : 'cp-status--fail',
                'icon'   => $reconectar ? '🔑' : '⚠️',
                'label'  => $reconectar
                    ? __('Necesita reconexión', 'convoca-publisher')
                    : __('Error al verificar', 'convoca-publisher'),
                'detail' => $detalle,
            ];
        }

        return [
            'key'   => 'ok',
            'class' => 'cp-status--ok',
            'icon'  => '✅',
            'label' => __('Configurado', 'convoca-publisher'),
        ];
    }

    /**
     * Huella de la configuración de un canal: sirve para saber si el resultado de una
     * verificación sigue siendo válido.
     */
    public static function channel_fingerprint(object $channel): string
    {
        if ($channel instanceof Channel_Profile) {
            return md5((string) wp_json_encode($channel->get_settings()));
        }

        $valores = [];

        foreach (array_keys($channel->get_settings_fields()) as $key) {
            $valores[$key] = (string) get_option($key, '');
        }

        return md5((string) wp_json_encode($valores));
    }

    /**
     * ¿El error suena a credencial caducada o sin permiso?
     */
    public static function looks_like_auth_error(string $message): bool
    {
        $agujas = ['token', '401', '403', 'expired', 'caduc', 'invalid', 'unauthorized', 'permission', 'forbidden', 'oauth'];

        foreach ($agujas as $aguja) {
            if (false !== stripos($message, $aguja)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Etiqueta de estado (marcado ya escapado: no volver a escaparla).
     */
    public static function status_badge(array $status): string
    {
        return sprintf(
            '<span class="cp-status %1$s">%2$s %3$s</span>',
            esc_attr($status['class']),
            esc_html($status['icon']),
            esc_html($status['label'])
        );
    }

    /**
     * Botón de «Verificar conexión» de un canal (llama a su API y guarda el resultado).
     */
    public static function verify_button(object $channel): string
    {
        $url = wp_nonce_url(
            admin_url('admin-post.php?action=cp_verify_channel&channel=' . $channel->get_id()),
            'convoca_publisher_verify_channel'
        );

        return sprintf(
            '<a href="%1$s" class="button">%2$s %3$s</a>',
            esc_url($url),
            esc_html('🔍'),
            esc_html__('Verificar conexión', 'convoca-publisher')
        );
    }

    private static function render_channels_tab(): void
    {
        $networks = Plugin::networks();
        $accounts = convoca_publisher()->get_channels();

        $verify_result = get_transient('convoca_publisher_verify_result_' . get_current_user_id());

        if (false !== $verify_result) {
            delete_transient('convoca_publisher_verify_result_' . get_current_user_id());
            ?>
            <div class="notice <?php echo !empty($verify_result['success']) ? 'notice-success' : 'notice-error'; ?> is-dismissible">
                <p><?php echo esc_html((!empty($verify_result['success']) ? '✅ ' : '❌ ') . ($verify_result['message'] ?? '')); ?></p>
            </div>
            <?php
        }

        if (empty($networks)) {
            ?>
            <div class="cp-notice cp-notice--warn">
                <p><?php echo esc_html__('No se ha cargado ninguna red. Es un fallo del plugin, no de tu configuración: revisa que la carpeta includes/channels esté completa.', 'convoca-publisher'); ?></p>
            </div>
            <?php

            return;
        }

        if (empty($accounts)) {
            ?>
            <div class="cp-notice">
                <h2><?php echo esc_html__('Por dónde empezar', 'convoca-publisher'); ?></h2>
                <p><?php echo esc_html__('Todavía no hay ninguna cuenta configurada: mientras no la haya, el plugin no publica en ninguna red. El orden que menos guerra da:', 'convoca-publisher'); ?></p>
                <ol class="cp-steps">
                    <li>
                        <strong><?php echo esc_html__('Telegram', 'convoca-publisher'); ?></strong> —
                        <?php echo esc_html__('un bot con BotFather y el identificador del chat. Dos minutos y se verifica en un clic.', 'convoca-publisher'); ?>
                    </li>
                    <li>
                        <strong><?php echo esc_html__('Mastodon', 'convoca-publisher'); ?></strong> —
                        <?php echo esc_html__('la dirección de tu instancia y un token de acceso. No caduca por sí solo.', 'convoca-publisher'); ?>
                    </li>
                    <li>
                        <strong><?php echo esc_html__('El resto', 'convoca-publisher'); ?></strong> —
                        <?php echo esc_html__('Facebook, LinkedIn, Twitter/X, TikTok y Google piden cuenta de desarrollador y tokens que caducan cada cierto tiempo.', 'convoca-publisher'); ?>
                    </li>
                </ol>
                <?php if (isset($networks['telegram'])) : ?>
                    <p><a class="button button-primary" href="<?php echo esc_url(self::tab_url('channels', ['canal' => 'telegram', 'nueva' => 1])); ?>"><?php echo esc_html__('Empezar por Telegram', 'convoca-publisher'); ?></a></p>
                <?php endif; ?>
            </div>
            <?php
        }

        ?>
        <p class="description">
            <?php echo esc_html__('Una cuenta es una cuenta: puedes tener varias de la misma red (la página y el grupo de Facebook, dos canales de Telegram) cada una con sus credenciales y su plantilla.', 'convoca-publisher'); ?>
        </p>

        <?php foreach ($networks as $network_id => $network) : ?>
            <?php
            $own = array_values(array_filter(
                $accounts,
                static fn(object $account): bool => $account instanceof Channel_Profile && $account->get_channel_id() === $network_id
            ));
            $free = Profile_Store::LIMIT_PER_NETWORK - count($own);
            ?>
            <div class="cp-section">
                <div class="cp-card__head">
                    <h2><?php echo esc_html($network->get_name()); ?></h2>
                    <?php if (empty($own)) : ?>
                        <?php echo self::status_badge(['key' => 'missing', 'class' => 'cp-status--off', 'icon' => '❌', 'label' => __('Falta token', 'convoca-publisher')]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado.?>
                    <?php else : ?>
                        <span class="cp-status cp-status--ok">
                            <?php
                            printf(
                                /* translators: %d: número de cuentas de esa red */
                                esc_html(_n('%d cuenta', '%d cuentas', count($own), 'convoca-publisher')),
                                (int) count($own)
                            );
                        ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (empty($own)) : ?>
                    <p class="description"><?php echo esc_html__('Esta red no publica todavía: no tiene ninguna cuenta configurada.', 'convoca-publisher'); ?></p>
                <?php else : ?>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo esc_html__('Cuenta', 'convoca-publisher'); ?></th>
                                <th scope="col"><?php echo esc_html__('Estado', 'convoca-publisher'); ?></th>
                                <th scope="col"><?php echo esc_html__('Acciones', 'convoca-publisher'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($own as $account) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($account->get_name()); ?></strong>
                                        <div class="description"><?php echo esc_html($account->get_id()); ?></div>
                                    </td>
                                    <td>
                                        <?php echo self::status_badge(self::channel_status($account)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado.?>
                                    </td>
                                    <td>
                                        <a class="button" href="<?php echo esc_url(self::tab_url('channels', ['canal' => $account->get_id()])); ?>">
                                            <?php echo esc_html__('Configurar', 'convoca-publisher'); ?>
                                        </a>
                                        <?php echo self::verify_button($account); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado.?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php if ($free > 0) : ?>
                    <p>
                        <a class="button" href="<?php echo esc_url(self::tab_url('channels', ['canal' => $network_id, 'nueva' => 1])); ?>">
                            <?php echo esc_html__('Añadir cuenta', 'convoca-publisher'); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %d: límite de cuentas por red */
                            esc_html__('Límite alcanzado: %d cuentas por red. Borra una para añadir otra.', 'convoca-publisher'),
                            (int) Profile_Store::LIMIT_PER_NETWORK
                        );
                    ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php
    }

    private static function render_test_tab(): void
    {
        $channels = convoca_publisher()->get_channels();
        ?>
        <div class="cp-section">
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
                <div class="cp-log__scroll">
                    <?php foreach (array_reverse($logs) as $log): ?>
                        <div class="cp-log__row">
                            <span class="cp-status <?php echo !empty($log['success']) ? 'ok' : 'fail'; ?>">
                                <?php echo !empty($log['success']) ? 'OK' : 'FAIL'; ?>
                            </span>
                            <span class="cp-log__time"><?php echo isset($log['time']) ? esc_html($log['time']) : ''; ?></span>
                            <span class="cp-log__channel"><?php echo isset($log['channel']) ? esc_html($log['channel']) : ''; ?></span>
                            <span class="cp-log__title"><?php echo isset($log['title']) ? esc_html($log['title']) : ''; ?></span>
                            <span class="cp-log__id"><?php echo esc_html__('Post #', 'convoca-publisher') . (isset($log['post_id']) ? intval($log['post_id']) : ''); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- Retry queue stats -->
            <?php if (class_exists(Retry::class)):
                $stats = Retry::get_queue_stats();
                if ($stats['pending'] > 0 || $stats['failed'] > 0): ?>
                <div class="cp-section">
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
            <div class="cp-section">
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

        // El resultado se guarda por canal (no solo como aviso de un momento): es lo
        // que alimenta el estado ✅/⚠️/🔑 de la tarjeta. La huella evita enseñar un
        // error viejo cuando la credencial ya es otra.
        $estados              = (array) get_option('convoca_publisher_verify_status', []);
        $estados[$channel_id] = [
            'success'     => !empty($result['success']),
            'message'     => (string) $result['message'],
            'time'        => current_time('mysql'),
            'fingerprint' => self::channel_fingerprint($channel),
        ];

        update_option('convoca_publisher_verify_status', $estados, false);

        set_transient('convoca_publisher_verify_result_' . get_current_user_id(), $result, 30);
        wp_safe_redirect(add_query_arg('convoca_publisher_verified', $channel_id, wp_get_referer()));
        exit;
    }

    /**
     * Render the moderation queue tab: listado de pendientes con Aprobar/Rechazar.
     */
    private static function render_moderation_tab(): void
    {
        $items = Retry::get_review_items();

        $notice = get_transient('convoca_publisher_review_notice');
        if (false !== $notice) {
            delete_transient('convoca_publisher_review_notice');
            $class = !empty($notice['success']) ? 'notice-success' : 'notice-error';
            $icon = !empty($notice['success']) ? '✅' : '❌';
            $message = $notice['message'] ?? $notice['error'] ?? '';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($icon) . ' ' . esc_html($message) . '</p></div>';
        }
        ?>
        <div class="cp-section">
            <h2><?php echo esc_html__('Cola de moderación', 'convoca-publisher'); ?></h2>
            <?php if (empty($items)): ?>
                <p><?php echo esc_html__('No hay publicaciones pendientes de revisión.', 'convoca-publisher'); ?></p>
            <?php else: ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php echo esc_html__('Entrada', 'convoca-publisher'); ?></th>
                            <th><?php echo esc_html__('Canal', 'convoca-publisher'); ?></th>
                            <th><?php echo esc_html__('Fecha', 'convoca-publisher'); ?></th>
                            <th><?php echo esc_html__('Acciones', 'convoca-publisher'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item):
                        $review_id = (int) $item->id;
                        $post = get_post((int) $item->post_id);
                        $approve_url = wp_nonce_url(admin_url('admin-post.php?action=cp_approve_review&id=' . $review_id), 'convoca_publisher_review_' . $review_id);
                        $reject_url = wp_nonce_url(admin_url('admin-post.php?action=cp_reject_review&id=' . $review_id), 'convoca_publisher_review_' . $review_id);
                        ?>
                        <tr>
                            <td>
                                <?php if ($post): ?>
                                    <a href="<?php echo esc_url(get_edit_post_link((int) $item->post_id)); ?>"><?php echo esc_html($post->post_title); ?></a>
                                <?php else: ?>
                                    <?php echo esc_html__('(entrada eliminada)', 'convoca-publisher') . ' #' . intval($item->post_id); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) $item->channel); ?></td>
                            <td><?php echo esc_html((string) ($item->created_at ?? '')); ?></td>
                            <td>
                                <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary"><?php echo esc_html__('Aprobar', 'convoca-publisher'); ?></a>
                                <a href="<?php echo esc_url($reject_url); ?>" class="button"><?php echo esc_html__('Rechazar', 'convoca-publisher'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Aprobar una publicación pendiente de moderación.
     */
    public static function handle_approve_review(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        check_admin_referer('convoca_publisher_review_' . $id);

        $result = Retry::approve($id);
        set_transient('convoca_publisher_review_notice', [
            'success' => !empty($result['success']),
            'message' => !empty($result['success']) ? __('Publicación aprobada y enviada.', 'convoca-publisher') : ($result['error'] ?? __('No se pudo publicar.', 'convoca-publisher')),
        ], 30);

        wp_safe_redirect(admin_url('admin.php?page=convoca-publisher&tab=moderation'));
        exit;
    }

    /**
     * Rechazar una publicación pendiente de moderación.
     */
    public static function handle_reject_review(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        check_admin_referer('convoca_publisher_review_' . $id);

        Retry::reject($id);
        set_transient('convoca_publisher_review_notice', [
            'success' => true,
            'message' => __('Publicación rechazada.', 'convoca-publisher'),
        ], 30);

        wp_safe_redirect(admin_url('admin.php?page=convoca-publisher&tab=moderation'));
        exit;
    }

    /**
     * Render the setup guide tab with step-by-step instructions for each channel.
     */
    private static function render_guide_tab(): void
    {
        ?>
        <div class="cp-section">
            <h2><?php echo esc_html__('📖 Guía de configuración', 'convoca-publisher'); ?></h2>
            <p><?php echo esc_html__('Sigue estos pasos para configurar cada red social. Necesitarás una cuenta de desarrollador en cada plataforma para obtener los tokens de acceso.', 'convoca-publisher'); ?></p>
        </div>

        <!-- Facebook / Instagram -->
        <div class="cp-section" id="canal-facebook">
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
                <li><?php echo esc_html__('Pega ambos en la pantalla del canal: Canales → Facebook / Instagram', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Page Access Token, Page ID, Instagram Business ID (opcional)', 'convoca-publisher'); ?></p>
            <p><a href="https://developers.facebook.com/docs/pages/publishing/" target="_blank">📄 <?php echo esc_html__('Documentación oficial de Meta', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- LinkedIn -->
        <div class="cp-section" id="canal-linkedin">
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
                <li><?php echo esc_html__('Copia el token y pégalo en la pantalla del canal: Canales → LinkedIn', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Para la URN: usa tu perfil (urn:li:person:...) o página de empresa (urn:li:organization:...)', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Access Token (OAuth 2.0), URN de perfil/página', 'convoca-publisher'); ?></p>
            <p><a href="https://learn.microsoft.com/en-us/linkedin/marketing/" target="_blank">📄 <?php echo esc_html__('Documentación oficial de LinkedIn', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- Twitter / X -->
        <div class="cp-section" id="canal-twitter">
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
                <li><?php echo esc_html__('Copia el Bearer Token y pégalo en la pantalla del canal: Canales → Twitter / X', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Bearer Token (OAuth 2.0)', 'convoca-publisher'); ?></p>
            <p><a href="https://developer.twitter.com/en/docs/twitter-api" target="_blank">📄 <?php echo esc_html__('Documentación oficial de X API', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- TikTok -->
        <div class="cp-section" id="canal-tiktok">
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
                <li><?php echo esc_html__('Pega ambos en la pantalla del canal: Canales → TikTok', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Access Token, Open ID', 'convoca-publisher'); ?></p>
            <p><a href="https://developers.tiktok.com/documentation" target="_blank">📄 <?php echo esc_html__('Documentación oficial de TikTok', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- Google My Business -->
        <div class="cp-section" id="canal-googlemybusiness">
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
                <li><?php echo esc_html__('Pega ambos en la pantalla del canal: Canales → Google My Business', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Access Token (OAuth 2.0), Location ID', 'convoca-publisher'); ?></p>
            <p><a href="https://developers.google.com/my-business" target="_blank">📄 <?php echo esc_html__('Documentación oficial de GMB API', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- Telegram -->
        <div class="cp-section" id="canal-telegram">
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
                <li><?php echo esc_html__('Pega token y chat ID en la pantalla del canal: Canales → Telegram', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Token del Bot, Chat ID', 'convoca-publisher'); ?></p>
            <p><a href="https://core.telegram.org/bots/api" target="_blank">📄 <?php echo esc_html__('Documentación oficial de Telegram Bot API', 'convoca-publisher'); ?></a></p>
        </div>

        <!-- Mastodon -->
        <div class="cp-section" id="canal-mastodon">
            <h2>🐘 <?php echo esc_html__('Mastodon', 'convoca-publisher'); ?></h2>
            <p><strong><?php echo esc_html__('Requiere:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Cuenta en un servidor de Mastodon', 'convoca-publisher'); ?></p>
            <ol>
                <li><?php echo esc_html__('Inicia sesión en tu instancia de Mastodon (ej: mastodon.social)', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Ve a Preferencias → Desarrollo', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Haz clic en "Nueva aplicación"', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Asigna un nombre (ej: Convoca Publisher) y marca el permiso "write:statuses"', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Copia el "Access Token" que se genera', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Anota también la URL base de tu servidor (ej: https://mastodon.social)', 'convoca-publisher'); ?></li>
                <li><?php echo esc_html__('Pega ambos en la pantalla del canal: Canales → Mastodon', 'convoca-publisher'); ?></li>
            </ol>
            <p><strong><?php echo esc_html__('Campos necesarios:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('Servidor (URL base), Access Token, Visibilidad (opcional)', 'convoca-publisher'); ?></p>
            <p><a href="https://docs.joinmastodon.org/api/" target="_blank">📄 <?php echo esc_html__('Documentación oficial de Mastodon API', 'convoca-publisher'); ?></a></p>
        </div>
        <?php
    }

    /**
     * Guardar (o crear) una cuenta.
     */
    public static function handle_save_account(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }

        check_admin_referer('convoca_publisher_save_account');

        $network_id = isset($_POST['red']) ? sanitize_key(wp_unslash((string) $_POST['red'])) : '';
        $account_id = isset($_POST['cuenta']) ? sanitize_key(wp_unslash((string) $_POST['cuenta'])) : '';
        $name       = isset($_POST['cuenta_nombre']) ? sanitize_text_field(wp_unslash((string) $_POST['cuenta_nombre'])) : '';
        $template   = isset($_POST['cuenta_plantilla']) ? wp_kses_post(wp_unslash((string) $_POST['cuenta_plantilla'])) : '';
        $networks   = Plugin::networks();

        if (!isset($networks[$network_id])) {
            wp_die(esc_html__('Esa red no existe.', 'convoca-publisher'));
        }

        $settings = [];

        foreach (array_keys($networks[$network_id]->get_settings_fields()) as $option) {
            if (str_ends_with($option, '_template') || !isset($_POST[$option])) {
                continue;
            }

            $settings[$option] = sanitize_text_field(wp_unslash((string) $_POST[$option]));
        }

        if ('' !== $account_id && Profile_Store::find($account_id)) {
            Profile_Store::update($account_id, ['name' => $name, 'template' => $template, 'settings' => $settings]);
            $destino = $account_id;
        } else {
            $cuenta = Profile_Store::create($network_id, $name, $settings, $template);

            if (false === $cuenta) {
                wp_safe_redirect(self::tab_url('channels'));

                return;
            }

            $destino = $cuenta['id'];
        }

        wp_safe_redirect(self::tab_url('channels', ['canal' => $destino, 'guardado' => 1]));
        exit;
    }

    /**
     * Borrar una cuenta.
     */
    public static function handle_delete_account(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }

        check_admin_referer('convoca_publisher_delete_account');

        $account_id = isset($_POST['cuenta']) ? sanitize_key(wp_unslash((string) $_POST['cuenta'])) : '';

        Profile_Store::delete($account_id);

        wp_safe_redirect(self::tab_url('channels'));
        exit;
    }
}
