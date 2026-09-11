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
        add_action('admin_action_cp_retry_log', [self::class, 'handle_retry_log']);
        add_action('admin_post_cp_verify_channel', [self::class, 'handle_verify_channel']);
        add_action('admin_post_cp_save_account', [self::class, 'handle_save_account']);
        add_action('admin_post_cp_delete_account', [self::class, 'handle_delete_account']);
        add_action('admin_post_cp_queue_reschedule', [self::class, 'handle_queue_reschedule']);
        add_action('admin_post_cp_queue_cancel', [self::class, 'handle_queue_cancel']);
        add_action('admin_post_cp_queue_spacing', [self::class, 'handle_queue_spacing']);
        add_action('admin_post_cp_queue_send_stuck', [self::class, 'handle_queue_send_stuck']);
        add_action('admin_post_cp_share_now', [self::class, 'handle_share_now']);
        add_filter('post_row_actions', [self::class, 'row_action'], 10, 2);
        add_action('admin_notices', [self::class, 'shared_notice']);
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
                    // `wp_kses_post` y no `sanitize_text_field`: la segunda borra los saltos
                    // de línea, así que una plantilla de varias líneas volvía hecha una sola.
                    register_setting('convoca_publisher_settings', $key, [
                        'type'              => 'string',
                        'sanitize_callback' => 'wp_kses_post',
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

        register_setting('convoca_publisher_settings', 'convoca_publisher_email_alerts', [
            'type'              => 'string',
            'sanitize_callback' => [self::class, 'sanitize_checkbox'],
            'show_in_rest'      => false,
            'default'           => '1',
        ]);
        register_setting('convoca_publisher_settings', 'convoca_publisher_queue_interval', [
            'type'              => 'integer',
            'sanitize_callback' => [self::class, 'sanitize_queue_interval'],
            'show_in_rest'      => false,
            'default'           => Queue::DEFAULT_INTERVAL,
        ]);
        register_setting('convoca_publisher_settings', 'convoca_publisher_message_template', [
            'type'              => 'string',
            'sanitize_callback' => 'wp_kses_post',
            'default'           => '{title} — {url} {hashtags}',
        ]);
        register_setting('convoca_publisher_settings', 'convoca_publisher_test_channel', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_key',
            'show_in_rest'      => false,
            'default'           => '',
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
    /**
     * Casilla de ajustes: '1' o '' según venga marcada.
     */
    public static function sanitize_checkbox($value): string
    {
        return empty($value) ? '' : '1';
    }

    /**
     * Ajuste: cada cuánto como mínimo entre envíos (en segundos; 0 = sin espaciado).
     */
    public static function sanitize_queue_interval($value): int
    {
        // OJO: un saneador NO escribe la opción que está saneando.
        //
        // Antes esto llamaba a `Queue::save_interval()`, que guarda con `update_option()`. Al
        // guardar, WordPress pasa otra vez por `sanitize_option` y vuelve a llamar a este
        // método: el saneador se llamaba a sí mismo sin fin hasta reventar la pila, y el
        // guardado terminaba en un 500. Pasaba guardando una plantilla porque el grupo de
        // ajustes se guarda entero desde esa pestaña, aunque el intervalo no se toque.
        return Queue::clamp_interval((int) $value);
    }

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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Función de saneado del API de ajustes: el nonce de la pantalla lo comprueba options.php antes de llamarla.
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

        // El JS necesita saber a dónde llamar para la vista previa. Los manejadores AJAX
        // existían pero nunca se usaron desde la pantalla (el botón usaba un POST normal).
        wp_localize_script('convoca-publisher-admin', 'convocaPublisher', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('convoca_publisher_preview'),
            'i18n'    => [
                'quedan'    => __('Quedan %s caracteres', 'convoca-publisher'),
                'pasado'    => __('Te pasas por %s caracteres: se recortará antes de enviar. Esto es lo que se mandaría:', 'convoca-publisher'),
                'error'     => __('No se pudo generar la vista previa.', 'convoca-publisher'),
            ],
        ]);
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
            'queue'      => __('Cola', 'convoca-publisher'),
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
                case 'queue':
                    self::render_queue_tab();
                    break;
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
            <h1 class="wp-heading-inline"><?php echo Icons::svg((string) $network_id, 24); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio.?><?php echo esc_html($nombre); ?></h1>
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

                <?php /* Solo en la pantalla de una cuenta: en la del canal no hay plantilla propia que enseñar (la de la red se edita en la pestaña Plantillas) y salía un campo vacío que parecía no hacer nada. */ ?>
                <?php if (isset($fields[$template_key]) && $account) : ?>
                    <div class="cp-section">
                        <h2><?php echo esc_html__('Plantilla de esta cuenta', 'convoca-publisher'); ?></h2>
                        <div class="cp-field">
                            <label for="cuenta_plantilla"><?php echo esc_html__('Mensaje', 'convoca-publisher'); ?></label>
                            <textarea
                                id="cuenta_plantilla"
                                name="cuenta_plantilla"
                                rows="3"
                                class="cp-input cp-input--wide"
                                placeholder="<?php echo esc_attr__('Usar la plantilla de la red', 'convoca-publisher'); ?>"
                            ><?php echo esc_textarea($account->get_template()); ?></textarea>
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Solo se usa para decidir si se muestra el aviso de «guardado»; el nonce ya lo comprobó options.php.
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
                        <th scope="row"><?php echo esc_html__('Avisos por correo', 'convoca-publisher'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="convoca_publisher_email_alerts" value="1" <?php checked(get_option('convoca_publisher_email_alerts', '1'), '1'); ?> />
                                <?php echo esc_html__('Escribir a quien administra el sitio cuando un envío no salga tras varios intentos', 'convoca-publisher'); ?>
                            </label>
                            <p class="description"><?php echo esc_html__('En pantalla el aviso solo lo ve quien entra a mirar; el correo llega aunque nadie entre.', 'convoca-publisher'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="convoca_publisher_queue_interval"><?php echo esc_html__('Espaciado entre envíos', 'convoca-publisher'); ?></label></th>
                        <td>
                            <select name="convoca_publisher_queue_interval" id="convoca_publisher_queue_interval">
                                <?php foreach ([0 => __('Sin espaciado', 'convoca-publisher'), 900 => __('15 minutos', 'convoca-publisher'), 1800 => __('30 minutos', 'convoca-publisher'), 3600 => __('1 hora', 'convoca-publisher'), 7200 => __('2 horas', 'convoca-publisher')] as $segundos => $etiqueta) : ?>
                                    <option value="<?php echo esc_attr((string) $segundos); ?>" <?php selected(Queue::interval(), $segundos); ?>><?php echo esc_html($etiqueta); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__('Cuánto tiene que pasar, como mínimo, entre dos publicaciones, para no soltar varias de golpe. Lo que caiga dentro se recoloca solo.', 'convoca-publisher'); ?></p>
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

    /**
     * Botones de variables, vuelta a la de fábrica y panel de vista previa de un campo.
     *
     * @param string $campo   Selector del área de texto a la que afectan.
     * @param string $red     Red de la que se comprueban los límites ('' = sin contador).
     * @param string $fabrica Plantilla de fábrica a la que vuelve el botón.
     */
    private static function render_template_tools(string $campo, string $red, string $fabrica = ''): void
    {
        ?>
        <p class="cp-plantilla-botones">
            <?php foreach (Publisher::variables() as $variable => $para_que) : ?>
                <button
                    type="button"
                    class="button button-small"
                    data-cp-insert="<?php echo esc_attr($variable); ?>"
                    data-cp-into="<?php echo esc_attr($campo); ?>"
                    title="<?php echo esc_attr($para_que); ?>"
                ><?php echo esc_html($variable); ?></button>
            <?php endforeach; ?>
            <?php if ('' !== $fabrica) : ?>
                <button
                    type="button"
                    class="button button-small cp-plantilla-fabrica"
                    data-cp-reset="<?php echo esc_attr($campo); ?>"
                    data-cp-factory="<?php echo esc_attr($fabrica); ?>"
                ><?php echo esc_html__('Volver a la de fábrica', 'convoca-publisher'); ?></button>
            <?php endif; ?>
        </p>
        <?php if ('' !== $red) : ?>
            <div class="cp-preview" data-cp-preview data-cp-network="<?php echo esc_attr($red); ?>" data-cp-into="<?php echo esc_attr($campo); ?>"></div>
        <?php endif; ?>
        <?php
    }

    private static function render_templates_tab(): void
    {
        // Plantillas por RED (es lo que lee el publicador cuando la cuenta no tiene la
        // suya). Las de cuenta se editan en la pantalla de cada cuenta: aquí no pintan.
        $networks = Plugin::networks();
        ?>
        <div class="cp-section">
            <h2><?php echo esc_html__('Plantillas de mensaje', 'convoca-publisher'); ?></h2>
            
            <div class="cp-help">
                <p><strong><?php echo esc_html__('Variables disponibles:', 'convoca-publisher'); ?></strong></p>
                <p>
                    <?php foreach (Publisher::variables() as $variable => $para_que) : ?>
                        <code><?php echo esc_html($variable); ?></code> — <?php echo esc_html($para_que); ?><br>
                    <?php endforeach; ?>
                </p>

                <?php $candidatas = self::test_candidates(); ?>
                <?php if ($candidatas) : ?>
                    <p class="cp-field">
                        <label for="cp-plantilla-entrada"><?php echo esc_html__('Ver con la entrada', 'convoca-publisher'); ?></label>
                        <select id="cp-plantilla-entrada">
                            <?php foreach ($candidatas as $candidata) : ?>
                                <option value="<?php echo esc_attr((string) $candidata['id']); ?>"><?php echo esc_html($candidata['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="description"><?php echo esc_html__('La vista previa y el contador de cada plantilla usan esta entrada.', 'convoca-publisher'); ?></span>
                    </p>
                <?php endif; ?>
                <p><?php echo esc_html__('Pulsa una variable para insertarla donde tengas el cursor. La vista previa se actualiza sola y te dice si el mensaje cabe en esa red o si se recortará.', 'convoca-publisher'); ?></p>
                <p><?php echo esc_html__('Se usa la primera que esté puesta, de lo más concreto a lo más general: lo que se escribe para un envío concreto, lo de esa entrada, la plantilla de la cuenta (en su pantalla), la de su red y la global. Si no hay ninguna, la de fábrica de cada red, que se ve bajo cada campo.', 'convoca-publisher'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('convoca_publisher_settings'); ?>
                
                <h3><?php echo esc_html__('Plantilla global', 'convoca-publisher'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Mensaje por defecto', 'convoca-publisher'); ?></th>
                        <td>
                            <textarea id="cp-plantilla-global" name="convoca_publisher_message_template" rows="3" class="cp-input cp-input--wide"><?php echo esc_textarea((string) get_option('convoca_publisher_message_template', '{title} — {url} {hashtags}')); ?></textarea>
                            <?php self::render_template_tools('#cp-plantilla-global', ''); ?>
                            <p class="description"><?php echo esc_html__('Se usa cuando un canal no tiene su propia plantilla.', 'convoca-publisher'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h3><?php echo esc_html__('Plantillas por red', 'convoca-publisher'); ?></h3>
                <p><?php echo esc_html__('Déjalo vacío para usar la plantilla global. Cada cuenta puede tener la suya propia en su pantalla.', 'convoca-publisher'); ?></p>

                <?php foreach ($networks as $channel):
                    $tkey = 'convoca_publisher_' . $channel->get_id() . '_template';
                    $tval = get_option($tkey, '');
                    ?>
                <div class="cp-channel-template">
                    <h4><?php echo esc_html($channel->get_name()); ?></h4>
                    <textarea id="cp-plantilla-<?php echo esc_attr($channel->get_id()); ?>" name="<?php echo esc_attr($tkey); ?>" rows="3" class="cp-input cp-input--wide" placeholder="<?php echo esc_attr__('Usar plantilla global', 'convoca-publisher'); ?>"><?php echo esc_textarea((string) $tval); ?></textarea>
                    <?php
                    self::render_template_tools(
                        '#cp-plantilla-' . $channel->get_id(),
                        (string) $channel->get_id(),
                        Publisher::factory_templates()[$channel->get_id()] ?? '{title} — {url}'
                    );
                    ?>
                    <p class="description">
                        <?php if ('' === trim((string) $tval)) : ?>
                            <?php echo esc_html__('Ahora mismo se usa la de fábrica para esta red:', 'convoca-publisher'); ?>
                        <?php else : ?>
                            <?php echo esc_html__('Se usa esta en vez de la global. La de fábrica para esta red es:', 'convoca-publisher'); ?>
                        <?php endif; ?>
                        <code><?php echo esc_html(Publisher::factory_templates()[$channel->get_id()] ?? '{title} — {url}'); ?></code>
                    </p>
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

        // Verificada, sí, pero ¿cuándo? Las redes con token que caduca solo (Facebook y
        // LinkedIn, 60 días) fallan un día sin que nadie haya tocado nada: con la fecha de la
        // última comprobación se avisa antes, en vez de enterarse por un envío perdido.
        $verificado_en = 0;

        if (isset($estado['time']) && is_string($estado['time']) && '' !== $estado['time']) {
            $fecha         = new \DateTimeImmutable($estado['time'], wp_timezone());
            $verificado_en = $fecha->getTimestamp();
        }

        $red = $channel instanceof Channel_Profile ? $channel->get_channel_id() : $channel->get_id();

        if (Credential_Health::needs_attention($red, $verificado_en)) {
            $caducada = 'caducada' === Credential_Health::state($red, $verificado_en);

            return [
                'key'    => $caducada ? 'reconnect' : 'expiring',
                'class'  => 'cp-status--warn',
                'icon'   => $caducada ? '🔑' : '⏳',
                'label'  => $caducada
                    ? __('Puede haber caducado', 'convoca-publisher')
                    : __('Caduca pronto', 'convoca-publisher'),
                'detail' => Credential_Health::message($red, $verificado_en),
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
                    <h2><?php echo Icons::svg((string) $network_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG propio.?><?php echo esc_html($network->get_name()); ?></h2>
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

    /**
     * Entradas recientes que se pueden usar en la prueba de publicación.
     *
     * Devuelve el id y la etiqueta ya formateada (título y fecha) para que la pantalla no
     * consulte nada y esta regla se pueda probar aislada.
     *
     * Ojo: aquí NO vale `wp_dropdown_pages()`. Esa función trabaja con `get_pages()`, que
     * solo devuelve tipos jerárquicos: con `post_type => 'post'` devuelve `false` y no pinta
     * nada, así que el desplegable salía vacío y parecía que el plugin no tenía nada que probar.
     *
     * @param int $limit Cuántas entradas traer.
     * @return array<int, array{id: int, label: string}>
     */
    public static function test_candidates(int $limit = 30): array
    {
        $ids = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        $candidatas = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            $candidatas[] = [
                'id'    => $id,
                'label' => sprintf('%s — %s', get_the_title($id), get_the_date('d/m/Y', $id)),
            ];
        }

        return $candidatas;
    }

    private static function render_test_tab(): void
    {
        $channels = convoca_publisher()->get_channels();
        ?>
        <div class="cp-section">
            <h2><?php echo esc_html__('Prueba de publicación', 'convoca-publisher'); ?></h2>
            <p><?php echo esc_html__('Selecciona una entrada reciente y haz clic en "Publicar en redes" para probar la integración.', 'convoca-publisher'); ?></p>

            <?php $canal_de_pruebas = (string) get_option('convoca_publisher_test_channel', ''); ?>
            <form method="post" action="options.php">
                <?php settings_fields('convoca_publisher_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Canal de pruebas', 'convoca-publisher'); ?></th>
                        <td>
                            <select name="convoca_publisher_test_channel">
                                <option value=""><?php echo esc_html__('Ninguno: prueba en las redes configuradas', 'convoca-publisher'); ?></option>
                                <?php foreach (Plugin::accounts() as $cuenta_id => $cuenta) : ?>
                                    <option value="<?php echo esc_attr((string) $cuenta_id); ?>" <?php selected($canal_de_pruebas, (string) $cuenta_id); ?>>
                                        <?php echo esc_html($cuenta->get_name() . ' — ' . $cuenta->get_channel_id()); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php echo esc_html__('Si eliges uno, el botón de abajo publica SOLO ahí: es la forma de probar sin que la entrada salga en las redes de verdad. Elige un canal que no sea público (por ejemplo tu Telegram).', 'convoca-publisher'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Guardar canal de pruebas', 'convoca-publisher'), 'secondary'); ?>
            </form>
            
            <form method="post">
                <?php wp_nonce_field('convoca_publisher_test_publish', 'convoca_publisher_test_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Entrada', 'convoca-publisher'); ?></th>
                        <td>
                            <?php
                            $candidatas = self::test_candidates();
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Solo para recordar la entrada elegida en el desplegable de la pestaña Probar.
        $elegida = isset($_POST['convoca_publisher_test_post_id']) ? intval($_POST['convoca_publisher_test_post_id']) : 0;
        ?>
                            <select name="convoca_publisher_test_post_id" id="convoca_publisher_test_post_id">
                                <option value=""><?php echo esc_html__('Seleccionar entrada...', 'convoca-publisher'); ?></option>
                                <?php foreach ($candidatas as $candidata) : ?>
                                    <option value="<?php echo esc_attr((string) $candidata['id']); ?>" <?php selected($elegida, $candidata['id']); ?>>
                                        <?php echo esc_html($candidata['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$candidatas) : ?>
                                <p class="description"><?php echo esc_html__('No hay entradas publicadas que probar todavía: publica una y vuelve a esta pestaña.', 'convoca-publisher'); ?></p>
                            <?php endif; ?>
                        </td>
                        </td>
                    </tr>
                </table>
                <p class="sp-test-publish">
                    <button type="submit" name="convoca_publisher_do_test" class="button button-primary">
                        <?php
                        echo '' !== $canal_de_pruebas
                            ? esc_html__('🚀 Publicar solo en el canal de pruebas', 'convoca-publisher')
                            : esc_html__('🚀 Publicar en redes', 'convoca-publisher');
        ?>
                    </button>
                    <?php if ('' === $canal_de_pruebas) : ?>
                        <span class="description"><?php echo esc_html__('Sin canal de pruebas, esto publica en las redes configuradas.', 'convoca-publisher'); ?></span>
                    <?php endif; ?>
                </p>
            </form>
            
            <?php
            if (isset($_POST['convoca_publisher_do_test']) && check_admin_referer('convoca_publisher_test_publish', 'convoca_publisher_test_nonce')) {
                $post_id = isset($_POST['convoca_publisher_test_post_id']) ? intval($_POST['convoca_publisher_test_post_id']) : 0;
                if ($post_id > 0) {
                    echo '<h3>' . esc_html__('Resultado:', 'convoca-publisher') . '</h3>';
                    $publisher = Publisher::instance();
                    if ($publisher) {
                        // Con canal de pruebas se manda SOLO ahí: probar no puede publicar en
                        // las redes reales sin querer.
                        $results = '' !== $canal_de_pruebas
                            ? [$canal_de_pruebas => $publisher->publish_test($post_id, $canal_de_pruebas)]
                            : $publisher->publish_post($post_id, true);
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

    /**
     * Historial de publicaciones: con filtros por red, cuenta y estado, y reintento de lo que falló.
     */
    public static function render_log_page(): void
    {
        $logs = get_option('convoca_publisher_publish_log', []);
        $logs = is_array($logs) ? $logs : [];

        $redes   = [];
        $nombres = [];

        foreach (Plugin::accounts() as $id => $cuenta) {
            $redes[(string) $id]   = $cuenta->get_channel_id();
            $nombres[(string) $id] = $cuenta->get_name();
        }

        // Solo se lee para filtrar la vista: no cambia nada, así que no hay nada que verificar.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $red    = isset($_GET['cp-red']) ? sanitize_key(wp_unslash((string) $_GET['cp-red'])) : '';
        $cuenta = isset($_GET['cp-cuenta']) ? sanitize_text_field(wp_unslash((string) $_GET['cp-cuenta'])) : '';
        $estado = isset($_GET['cp-estado']) ? sanitize_key(wp_unslash((string) $_GET['cp-estado'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $datos = Admin\Log_View::facets($logs, $redes);
        $vista = Admin\Log_View::filter($logs, [
            'network'  => $red,
            'account'  => $cuenta,
            'status'   => $estado,
            'networks' => $redes,
        ]);

        // Se pagina de 25 en 25: el historial guarda hasta 200 filas y volcarlas todas de una vez
        // deja una pantalla larga de leer.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo se lee para paginar.
        $pagina   = isset($_GET['cp-pagina']) ? max(1, (int) $_GET['cp-pagina']) : 1;
        $paginado = Admin\Log_View::page($vista, $pagina, 25);

        $aviso = get_transient('convoca_publisher_queue_notice_' . get_current_user_id());
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Historial de publicaciones', 'convoca-publisher'); ?></h1>

            <?php if (is_string($aviso) && '' !== $aviso): ?>
                <div class="notice notice-info is-dismissible"><p><?php echo esc_html($aviso); ?></p></div>
                <?php delete_transient('convoca_publisher_queue_notice_' . get_current_user_id()); ?>
            <?php endif; ?>

            <?php if (0 === $datos['total']): ?>
                <p><?php echo esc_html__('No hay publicaciones registradas todavía.', 'convoca-publisher'); ?></p>
            <?php else: ?>
                <form method="get" class="cp-filters">
                    <input type="hidden" name="page" value="convoca-publisher-log" />
                    <label for="cp-red"><?php echo esc_html__('Red', 'convoca-publisher'); ?></label>
                    <select name="cp-red" id="cp-red">
                        <option value=""><?php echo esc_html__('Todas', 'convoca-publisher'); ?></option>
                        <?php foreach ($datos['networks'] as $id_red): ?>
                            <option value="<?php echo esc_attr($id_red); ?>" <?php selected($red, $id_red); ?>><?php echo esc_html($id_red); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="cp-cuenta"><?php echo esc_html__('Cuenta', 'convoca-publisher'); ?></label>
                    <select name="cp-cuenta" id="cp-cuenta">
                        <option value=""><?php echo esc_html__('Todas', 'convoca-publisher'); ?></option>
                        <?php foreach ($datos['accounts'] as $id_cuenta => $etiqueta): ?>
                            <option value="<?php echo esc_attr($id_cuenta); ?>" <?php selected($cuenta, (string) $id_cuenta); ?>><?php echo esc_html(Admin\Log_View::label((string) $id_cuenta, $redes, $nombres)); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="cp-estado"><?php echo esc_html__('Resultado', 'convoca-publisher'); ?></label>
                    <select name="cp-estado" id="cp-estado">
                        <option value=""><?php echo esc_html__('Todos', 'convoca-publisher'); ?></option>
                        <option value="ok" <?php selected($estado, 'ok'); ?>><?php echo esc_html__('Salió', 'convoca-publisher'); ?> (<?php echo (int) $datos['statuses']['ok']; ?>)</option>
                        <option value="fail" <?php selected($estado, 'fail'); ?>><?php echo esc_html__('Falló', 'convoca-publisher'); ?> (<?php echo (int) $datos['statuses']['fail']; ?>)</option>
                    </select>

                    <button type="submit" class="button"><?php echo esc_html__('Filtrar', 'convoca-publisher'); ?></button>
                    <?php if ('' !== $red || '' !== $cuenta || '' !== $estado): ?>
                        <a class="button-link" href="<?php echo esc_url(admin_url('admin.php?page=convoca-publisher-log')); ?>"><?php echo esc_html__('Quitar filtros', 'convoca-publisher'); ?></a>
                    <?php endif; ?>
                </form>

                <p class="cp-filters__count">
                    <?php
                    printf(
                        /* translators: 1: primera fila que se ve, 2: última, 3: total, 4: página, 5: páginas. */
                        esc_html__('Mostrando %1$d-%2$d de %3$d (página %4$d de %5$d).', 'convoca-publisher'),
                        count($paginado['entries']) > 0 ? (($paginado['page'] - 1) * 25) + 1 : 0,
                        (($paginado['page'] - 1) * 25) + count($paginado['entries']),
                        (int) $datos['total'],
                        (int) $paginado['page'],
                        (int) $paginado['pages']
                    );
        ?>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=convoca_publisher_delete_log'), 'convoca_publisher_delete_log')); ?>" class="button" onclick="return confirm('<?php echo esc_js(__('¿Borrar todo el historial?', 'convoca-publisher')); ?>');">
                        <?php echo esc_html__('Limpiar historial', 'convoca-publisher'); ?>
                    </a>
                </p>

                <?php if ([] === $vista): ?>
                    <p><?php echo esc_html__('Con esos filtros no hay nada.', 'convoca-publisher'); ?></p>
                <?php else: ?>
                    <div class="cp-log__scroll">
                        <?php foreach ($paginado['entries'] as $log): ?>
                            <div class="cp-log__row">
                                <span class="cp-status <?php echo !empty($log['success']) ? 'ok' : 'fail'; ?>">
                                    <?php echo !empty($log['success']) ? 'OK' : 'FAIL'; ?>
                                </span>
                                <span class="cp-log__time"><?php echo isset($log['time']) ? esc_html((string) $log['time']) : ''; ?></span>
                                <span class="cp-log__channel"><?php echo esc_html(Admin\Log_View::label((string) ($log['channel'] ?? ''), $redes, $nombres)); ?></span>
                                <span class="cp-log__title"><?php echo isset($log['title']) ? esc_html((string) $log['title']) : ''; ?></span>
                                <?php if (!empty($log['test'])): ?>
                                    <span class="cp-log__test"><?php echo esc_html__('prueba', 'convoca-publisher'); ?></span>
                                <?php endif; ?>
                                <span class="cp-log__id"><?php echo esc_html__('Entrada', 'convoca-publisher') . ' #' . (int) ($log['post_id'] ?? 0); ?></span>
                                <?php if (Admin\Log_View::retryable($log)): ?>
                                    <a class="cp-log__retry button-link" href="<?php
                            echo esc_url(wp_nonce_url(
                                admin_url('admin.php?action=convoca_publisher_retry_log&post=' . (int) $log['post_id'] . '&channel=' . rawurlencode((string) $log['channel'])),
                                'convoca_publisher_retry_log_' . (int) $log['post_id'] . '_' . (string) $log['channel']
                            ));
                                    ?>"><?php echo esc_html__('Reintentar', 'convoca-publisher'); ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($paginado['pages'] > 1): ?>
                        <p class="cp-paginacion">
                            <?php
                            // Los filtros se conservan al cambiar de página: perderlos al paginar es
                            // de las cosas que más molesta.
                            $enlace = static function (int $destino) use ($red, $cuenta, $estado): string {
                                return add_query_arg(
                                    array_filter([
                                        'page'      => 'convoca-publisher-log',
                                        'cp-red'    => $red,
                                        'cp-cuenta' => $cuenta,
                                        'cp-estado' => $estado,
                                        'cp-pagina' => $destino,
                                    ]),
                                    admin_url('admin.php')
                                );
                            };
                        ?>
                            <?php if ($paginado['page'] > 1): ?>
                                <a class="button" href="<?php echo esc_url($enlace($paginado['page'] - 1)); ?>">&laquo; <?php echo esc_html__('Anterior', 'convoca-publisher'); ?></a>
                            <?php endif; ?>
                            <span class="cp-paginacion__actual">
                                <?php
                            printf(
                                /* translators: 1: página actual, 2: total de páginas. */
                                esc_html__('Página %1$d de %2$d', 'convoca-publisher'),
                                (int) $paginado['page'],
                                (int) $paginado['pages']
                            );
                        ?>
                            </span>
                            <?php if ($paginado['page'] < $paginado['pages']): ?>
                                <a class="button" href="<?php echo esc_url($enlace($paginado['page'] + 1)); ?>"><?php echo esc_html__('Siguiente', 'convoca-publisher'); ?> &raquo;</a>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

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

    /**
     * Reintentar un envío que falló, desde el historial.
     */
    public static function handle_retry_log(): void
    {
        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        $canal   = isset($_GET['channel']) ? sanitize_text_field(wp_unslash((string) $_GET['channel'])) : '';

        if ($post_id <= 0 || '' === $canal || !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }

        check_admin_referer('convoca_publisher_retry_log_' . $post_id . '_' . $canal);

        $publicador = Publisher::instance();
        $resultado  = $publicador ? $publicador->publish_to_accounts($post_id, [$canal], true, true) : [];
        $salio      = !empty($resultado[$canal]['success']);

        foreach (Plugin::accounts() as $id => $cuenta) {
            if ((string) $id === $canal) {
                $nombre = $cuenta->get_name();
                break;
            }
        }

        set_transient(
            'convoca_publisher_queue_notice_' . get_current_user_id(),
            $salio
                /* translators: %s: nombre de la cuenta. */
                ? sprintf(__('Reenviado a %s.', 'convoca-publisher'), $nombre ?? $canal)
                /* translators: %s: nombre de la cuenta. */
                : sprintf(__('No se pudo reenviar a %s. El motivo está en el historial.', 'convoca-publisher'), $nombre ?? $canal),
            30
        );
        wp_safe_redirect(admin_url('admin.php?page=convoca-publisher-log'));
        exit;
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
            <h2><?php echo esc_html__('📖 Cómo funciona esto', 'convoca-publisher'); ?></h2>
            <ul class="cp-steps">
                <li><strong><?php echo esc_html__('Una red puede tener varias cuentas.', 'convoca-publisher'); ?></strong> <?php echo esc_html__('La página y el grupo de Facebook son dos cuentas, cada una con sus credenciales y su plantilla. Se les puede poner nombre para reconocerlas (hasta 5 por red).', 'convoca-publisher'); ?></li>
                <li><strong><?php echo esc_html__('El mensaje se decide de lo concreto a lo general:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('lo que escribas para un envío, el mensaje de esa entrada, la plantilla de la cuenta, la de la red y la global. En el editor ves cómo queda y cuánto ocupa para cada red.', 'convoca-publisher'); ?></li>
                <li><strong><?php echo esc_html__('Lo que admite cada red se comprueba antes de enviar:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('si el mensaje no cabe, se recorta conservando el enlace y se te dice; no falla al enviar en silencio.', 'convoca-publisher'); ?></li>
                <li><strong><?php echo esc_html__('La pestaña Cola es donde se ve todo lo que va a salir:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('calendario de mes y semana (arrastra un envío para reprogramarlo), lo que espera turno, lo que se quedó atrás y lo último que salió.', 'convoca-publisher'); ?></li>
                <li><strong><?php echo esc_html__('Puedes compartir a mano en una sola cuenta', 'convoca-publisher'); ?></strong> <?php echo esc_html__('desde el editor (un botón por cuenta) o desde el listado de entradas, sin tocar las demás.', 'convoca-publisher'); ?></li>
                <li><strong><?php echo esc_html__('Si algo falla, no se pierde en silencio:', 'convoca-publisher'); ?></strong> <?php echo esc_html__('se reintenta con espera creciente, y si se agotan los intentos se te avisa por correo y queda a la vista en la cola para darle salida a mano.', 'convoca-publisher'); ?></li>
            </ul>
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

    /**
     * La cola: calendario de lo que va a salir y lista con lo que espera, lo que falló y lo
     * que ya salió. Se puede reprogramar (o arrastrar a otro día) y quitar de la cola sin
     * entrar en la entrada.
     */
    private static function render_queue_tab(): void
    {
        $aviso = get_transient('convoca_publisher_queue_notice_' . get_current_user_id());

        if (false !== $aviso) {
            delete_transient('convoca_publisher_queue_notice_' . get_current_user_id());
            ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html((string) $aviso); ?></p></div>
            <?php
        }

        $vista = isset($_GET['vista']) ? sanitize_key(wp_unslash((string) $_GET['vista'])) : 'mes';
        $hoy   = new \DateTimeImmutable('now', wp_timezone());
        $mes   = isset($_GET['mes']) ? max(1, min(12, (int) $_GET['mes'])) : (int) $hoy->format('n');
        $anio  = isset($_GET['anio']) ? max(2020, min(2100, (int) $_GET['anio'])) : (int) $hoy->format('Y');
        $dia   = isset($_GET['dia']) ? max(1, min(31, (int) $_GET['dia'])) : (int) $hoy->format('j');
        ?>
        <p class="description">
            <?php echo esc_html__('Una entrada puede salir en varias cuentas: cada una es un envío. Arrastra un envío a otro día para reprogramarlo, o hazlo desde la lista de abajo.', 'convoca-publisher'); ?>
        </p>

        <div class="cp-cal__barra">
            <a class="button" href="<?php echo esc_url(self::queue_url($vista, $anio, $mes, $dia, -1)); ?>">&larr;</a>
            <strong class="cp-cal__titulo">
                <?php echo esc_html('semana' === $vista ? __('Semana del ', 'convoca-publisher') . self::queue_week_start($anio, $mes, $dia)->format('d/m/Y') : self::queue_month_name($anio, $mes)); ?>
            </strong>
            <a class="button" href="<?php echo esc_url(self::queue_url($vista, $anio, $mes, $dia, 1)); ?>">&rarr;</a>
            <a class="button" href="<?php echo esc_url(self::tab_url('queue', ['vista' => 'mes', 'anio' => (int) $hoy->format('Y'), 'mes' => (int) $hoy->format('n'), 'dia' => (int) $hoy->format('j')])); ?>"><?php echo esc_html__('Hoy', 'convoca-publisher'); ?></a>
            <span class="cp-cal__vistas">
                <a class="button <?php echo 'mes' === $vista ? 'button-primary' : ''; ?>" href="<?php echo esc_url(self::tab_url('queue', ['vista' => 'mes', 'anio' => $anio, 'mes' => $mes, 'dia' => $dia])); ?>"><?php echo esc_html__('Mes', 'convoca-publisher'); ?></a>
                <a class="button <?php echo 'semana' === $vista ? 'button-primary' : ''; ?>" href="<?php echo esc_url(self::tab_url('queue', ['vista' => 'semana', 'anio' => $anio, 'mes' => $mes, 'dia' => $dia])); ?>"><?php echo esc_html__('Semana', 'convoca-publisher'); ?></a>
            </span>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cp-cal__recolocar">
                <input type="hidden" name="action" value="cp_queue_spacing" />
                <?php wp_nonce_field('convoca_publisher_queue_spacing'); ?>
                <button type="submit" class="button"><?php echo esc_html__('Recolocar ahora', 'convoca-publisher'); ?></button>
            </form>
        </div>

        <form id="cp-cal-form" class="cp-hidden" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="cp_queue_reschedule" />
            <input type="hidden" name="cp_envio" value="" />
            <input type="hidden" name="cp_dia" value="" />
            <?php wp_nonce_field('convoca_publisher_queue_reschedule'); ?>
        </form>

        <?php
        if ('semana' === $vista) {
            self::render_queue_week($anio, $mes, $dia);
        } else {
            self::render_queue_month($anio, $mes);
        }

        self::render_queue_stuck();
        self::render_queue_list();
    }

    /**
     * Envíos de un rango de días, agrupados por día natural del sitio.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function queue_by_day(int $from, int $to): array
    {
        $entradas = Queue::scheduled_entries($from, $to);

        foreach (Queue::retry_entries() as $reintento) {
            if (($reintento['time'] ?? 0) >= $from && ($reintento['time'] ?? 0) <= $to) {
                $entradas[] = $reintento;
            }
        }

        foreach (Queue::sent_entries() as $salio) {
            $cuando = (int) strtotime($salio['time']);

            if ($cuando >= $from && $cuando <= $to) {
                $entradas[] = [
                    'kind'         => 'sent',
                    'time'         => $cuando,
                    'title'        => $salio['title'],
                    'account_name' => $salio['account'],
                    'success'      => $salio['success'],
                ];
            }
        }

        $porDia = [];

        foreach ($entradas as $entrada) {
            $porDia[wp_date('Y-m-d', (int) $entrada['time'])][] = $entrada;
        }

        return $porDia;
    }

    /**
     * Rejilla del calendario (la usan la vista de mes y la de semana).
     *
     * @param array<int, \DateTimeImmutable> $dias
     */
    private static function render_queue_grid(array $dias, array $porDia): void
    {
        $hoy = wp_date('Y-m-d');
        ?>
        <table class="cp-cal">
            <thead>
                <tr>
                    <?php foreach ($dias as $indice => $dia) : ?>
                        <?php if ($indice < 7) : ?>
                            <th scope="col"><?php echo esc_html(wp_date('D', $dia->getTimestamp())); ?></th>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_chunk($dias, 7) as $semana) : ?>
                    <tr>
                        <?php foreach ($semana as $dia) : ?>
                            <?php
                            $fecha = $dia->format('Y-m-d');
                            $clase = 'cp-cal__dia';

                            if ($fecha === $hoy) {
                                $clase .= ' cp-cal__hoy';
                            }
                            ?>
                            <td class="<?php echo esc_attr($clase); ?>" data-cp-dia="<?php echo esc_attr($fecha); ?>">
                                <div class="cp-cal__day-number"><?php echo esc_html($dia->format('j')); ?></div>
                                <?php foreach ($porDia[$fecha] ?? [] as $envio) : ?>
                                    <?php echo self::queue_entry_html($envio); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- marcado propio, ya escapado.?>
                                <?php endforeach; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Un envío dentro del calendario (movible si todavía no ha salido).
     *
     * @param array<string, mixed> $envio
     */
    private static function queue_entry_html(array $envio): string
    {
        $kind  = (string) ($envio['kind'] ?? 'schedule');
        $clase = 'cp-cal__envio';
        $ref   = '';
        $pista = '';

        if ('sent' === $kind) {
            $clase .= !empty($envio['success']) ? ' cp-cal__envio--hecho' : ' cp-cal__envio--fallo';
        } elseif ('retry' === $kind && 'failed' === ($envio['status'] ?? '')) {
            $clase      .= ' cp-cal__envio--fallo';
            $ref         = 'retry:' . (int) ($envio['id'] ?? 0);
            $pista       = (string) ($envio['error'] ?? '');
        } else {
            if ('retry' === $kind) {
                $ref = 'retry:' . (int) ($envio['id'] ?? 0);
            } else {
                $ref = 'schedule:' . (int) ($envio['post_id'] ?? 0);
            }
        }

        $texto = sprintf(
            '%s · %s — %s',
            wp_date('H:i', (int) ($envio['time'] ?? 0)),
            (string) ($envio['account_name'] ?? ''),
            (string) ($envio['title'] ?? '')
        );

        return sprintf(
            '<span class="%1$s"%2$s draggable="%3$s" title="%4$s">%5$s</span>',
            esc_attr($clase),
            '' !== $ref ? ' data-cp-envio="' . esc_attr($ref) . '"' : '',
            '' !== $ref ? 'true' : 'false',
            esc_attr(trim($texto . ('' !== $pista ? ' — ' . $pista : ''))),
            esc_html($texto)
        );
    }

    private static function render_queue_month(int $year, int $month): void
    {
        $tz    = wp_timezone();
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $tz);
        $start = $start->modify('-' . ((int) $start->format('N') - 1) . ' days');

        $dias = [];

        for ($i = 0; $i < 42; ++$i) {
            $dias[] = $start->modify('+' . $i . ' days');
        }

        $porDia = self::queue_by_day($start->getTimestamp(), $start->modify('+42 days')->getTimestamp());

        self::render_queue_grid($dias, $porDia);
    }

    private static function render_queue_week(int $year, int $month, int $day): void
    {
        $start = self::queue_week_start($year, $month, $day);

        $dias = [];

        for ($i = 0; $i < 7; ++$i) {
            $dias[] = $start->modify('+' . $i . ' days');
        }

        $porDia = self::queue_by_day($start->getTimestamp(), $start->modify('+7 days')->getTimestamp());

        self::render_queue_grid($dias, $porDia);
    }

    /**
     * El lunes de la semana a la que pertenece un día (el día manda: sin él, la vista de
     * semana no podría avanzar, siempre caería en la semana del día que se le pase).
     */
    private static function queue_week_start(int $year, int $month, int $day): \DateTimeImmutable
    {
        $ref = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, max(1, min(31, $day))), wp_timezone());

        return $ref->modify('-' . ((int) $ref->format('N') - 1) . ' days')->setTime(0, 0);
    }

    private static function queue_month_name(int $year, int $month): string
    {
        $fecha = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), wp_timezone());

        return wp_date('F Y', $fecha->getTimestamp());
    }

    /**
     * Enlace del calendario (mes anterior o siguiente, según el paso).
     */
    private static function queue_url(string $vista, int $year, int $month, int $day, int $paso): string
    {
        if ('semana' === $vista) {
            $ref = self::queue_week_start($year, $month, $day)->modify($paso > 0 ? '+7 days' : '-7 days');
        } else {
            $ref = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), wp_timezone()))
                ->modify($paso > 0 ? '+1 month' : '-1 month');
        }

        return self::tab_url('queue', [
            'vista' => $vista,
            'anio'  => (int) $ref->format('Y'),
            'mes'   => (int) $ref->format('n'),
            'dia'   => (int) $ref->format('j'),
        ]);
    }

    /**
     * La lista de la cola: lo que espera turno, lo que falló y lo último que salió.
     */
    /**
     * Lo que se quedó atrás: ni salió ni está esperando turno. Aquí es donde se ve lo que el
     * cron no llegó a intentar, y donde se le puede dar salida a mano.
     */
    private static function render_queue_stuck(): void
    {
        $atrasados = Queue::overdue();
        $ayuda     = Queue::needs_help();

        if ([] === $atrasados && [] === $ayuda) {
            return;
        }
        ?>
        <h2><?php echo esc_html__('Se quedó atrás', 'convoca-publisher'); ?></h2>
        <p class="description">
            <?php echo esc_html__('Programados cuya hora ya pasó y no han salido: el cron puede no haber llegado a intentarlo. Se les puede dar salida ahora.', 'convoca-publisher'); ?>
        </p>
        <?php if ([] !== $atrasados) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cp-acciones">
                <input type="hidden" name="action" value="cp_queue_send_stuck" />
                <input type="hidden" name="cp_todos" value="1" />
                <?php wp_nonce_field('convoca_publisher_queue_send_stuck'); ?>
                <button type="submit" class="button button-primary"><?php echo esc_html__('Enviar todo lo atrasado', 'convoca-publisher'); ?></button>
            </form>
        <?php endif; ?>
        <?php
        self::render_stuck_table($atrasados, (string) __('Atrasado', 'convoca-publisher'));

        if ([] !== $ayuda) {
            $filas = [];

            foreach ($ayuda as $post_id) {
                $filas[] = [
                    'post_id'      => $post_id,
                    'time'         => (int) get_post_meta($post_id, Queue::HELP_META, true),
                    'title'        => get_the_title($post_id),
                    'account_name' => '',
                    'attempts'     => Queue::attempts($post_id),
                ];
            }

            echo '<h2>' . esc_html__('Ya no se insiste solo', 'convoca-publisher') . '</h2>';
            echo '<p class="description">' . esc_html__('Se intentó varias veces sin éxito. Se avisó por correo; desde aquí se puede reintentar cuando esté resuelto.', 'convoca-publisher') . '</p>';
            self::render_stuck_table($filas, (string) __('Se dejó de insistir', 'convoca-publisher'));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $filas
     */
    private static function render_stuck_table(array $filas, string $estado): void
    {
        ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Cuándo', 'convoca-publisher'); ?></th>
                    <th scope="col"><?php echo esc_html__('Entrada', 'convoca-publisher'); ?></th>
                    <th scope="col"><?php echo esc_html__('Intentos', 'convoca-publisher'); ?></th>
                    <th scope="col"><?php echo esc_html__('Qué hacer', 'convoca-publisher'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $fila) : ?>
                    <tr>
                        <td><?php echo esc_html(wp_date('d/m/Y H:i', (int) $fila['time'])); ?></td>
                        <td><?php echo esc_html((string) $fila['title']); ?></td>
                        <td><?php echo esc_html(sprintf('%d / %d', (int) ($fila['attempts'] ?? 0), Queue::MAX_ATTEMPTS)); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cp-acciones">
                                <input type="hidden" name="action" value="cp_queue_send_stuck" />
                                <input type="hidden" name="cp_post" value="<?php echo esc_attr((string) $fila['post_id']); ?>" />
                                <?php wp_nonce_field('convoca_publisher_queue_send_stuck'); ?>
                                <button type="submit" class="button"><?php echo esc_html__('Enviar ahora', 'convoca-publisher'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Dar salida a mano a lo atrasado (una entrada o todas).
     */
    public static function handle_queue_send_stuck(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }

        check_admin_referer('convoca_publisher_queue_send_stuck');

        $todos   = !empty($_POST['cp_todos']);
        $post_id = isset($_POST['cp_post']) ? (int) $_POST['cp_post'] : 0;
        $ids     = $todos ? array_map(static fn(array $fila): int => (int) $fila['post_id'], array_merge(Queue::overdue(), array_map(static fn(int $id): array => ['post_id' => $id], Queue::needs_help()))) : [$post_id];
        $hechos  = 0;

        foreach (array_filter($ids) as $id) {
            $publicador = Publisher::instance();

            if (!$publicador) {
                continue;
            }

            $resultado = $publicador->publish_to_accounts((int) $id, [], true, true);
            $salio     = false;

            foreach ($resultado as $cuenta => $envio) {
                if ('_' !== $cuenta[0] && !empty($envio['success'])) {
                    $salio = true;
                }
            }

            if ($salio) {
                Queue::forget_attempts((int) $id);
                delete_post_meta((int) $id, Queue::SCHEDULE_META);
                ++$hechos;
                continue;
            }

            if (Queue::count_attempt((int) $id) >= Queue::MAX_ATTEMPTS) {
                Queue::give_up((int) $id);
                Notifications::ask_for_help((int) $id, Queue::MAX_ATTEMPTS);
            }
        }

        set_transient(
            'convoca_publisher_queue_notice_' . get_current_user_id(),
            sprintf(
                /* translators: %d: envíos que han salido */
                _n('%d envío recuperado.', '%d envíos recuperados.', $hechos, 'convoca-publisher'),
                $hechos
            ),
            30
        );
        wp_safe_redirect(self::tab_url('queue'));
        exit;
    }

    private static function render_queue_list(): void
    {
        $ahora = time();
        $proximos = array_merge(Queue::scheduled_entries($ahora - DAY_IN_SECONDS, $ahora + (90 * DAY_IN_SECONDS)), Queue::retry_entries());
        $futuros  = array_values(array_filter($proximos, static fn(array $e): bool => (int) ($e['time'] ?? 0) >= $ahora));
        $fallidos = array_values(array_filter($proximos, static fn(array $e): bool => (int) ($e['time'] ?? 0) < $ahora));

        usort($futuros, static fn(array $a, array $b): int => ($a['time'] ?? 0) <=> ($b['time'] ?? 0));
        usort($fallidos, static fn(array $a, array $b): int => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
        ?>
        <h2><?php echo esc_html__('Lo que espera turno', 'convoca-publisher'); ?></h2>
        <?php self::render_queue_table($futuros, false); ?>

        <?php if ([] !== $fallidos) : ?>
            <h2><?php echo esc_html__('Atascado: toca revisarlo', 'convoca-publisher'); ?></h2>
            <?php self::render_queue_table($fallidos, true); ?>
        <?php endif; ?>

        <h2><?php echo esc_html__('Lo último que salió', 'convoca-publisher'); ?></h2>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Cuándo', 'convoca-publisher'); ?></th>
                    <th scope="col"><?php echo esc_html__('Cuenta', 'convoca-publisher'); ?></th>
                    <th scope="col"><?php echo esc_html__('Entrada', 'convoca-publisher'); ?></th>
                    <th scope="col"><?php echo esc_html__('Resultado', 'convoca-publisher'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice(Queue::sent_entries(20), 0, 20) as $salio) : ?>
                    <tr>
                        <td><?php echo esc_html($salio['time']); ?></td>
                        <td><?php echo esc_html($salio['account']); ?></td>
                        <td><?php echo esc_html($salio['title']); ?></td>
                        <td>
                            <?php if ($salio['success']) : ?>
                                <span class="cp-status cp-status--ok">✅ <?php echo esc_html__('Enviado', 'convoca-publisher'); ?></span>
                            <?php else : ?>
                                <span class="cp-status cp-status--fail">❌ <?php echo esc_html((string) $salio['detail']); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param array<int, array<string, mixed>> $envios
     */
    private static function render_queue_table(array $envios, bool $atascados): void
    {
        if ([] === $envios) {
            ?>
            <p class="description"><?php echo esc_html__('No hay nada en la cola.', 'convoca-publisher'); ?></p>
            <?php

            return;
        }
        ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Cuándo', 'convoca-publisher'); ?></th>
                    <th scope="col"><?php echo esc_html__('Cuenta', 'convoca-publisher'); ?></th>
                    <th scope="col"><?php echo esc_html__('Entrada', 'convoca-publisher'); ?></th>
                    <th scope="col"><?php echo esc_html__('Reprogramar', 'convoca-publisher'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($envios as $envio) : ?>
                    <?php
                    $kind = (string) ($envio['kind'] ?? 'schedule');
                    $ref  = 'retry' === $kind ? 'retry:' . (int) ($envio['id'] ?? 0) : 'schedule:' . (int) ($envio['post_id'] ?? 0);
                    ?>
                    <tr>
                        <td>
                            <?php echo esc_html(wp_date('d/m/Y H:i', (int) ($envio['time'] ?? 0))); ?>
                            <?php if ($atascados) : ?>
                                <div class="description"><?php echo esc_html((string) ($envio['error'] ?? '')); ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) ($envio['account_name'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($envio['title'] ?? '')); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cp-acciones">
                                <input type="hidden" name="action" value="cp_queue_reschedule" />
                                <input type="hidden" name="cp_envio" value="<?php echo esc_attr($ref); ?>" />
                                <?php wp_nonce_field('convoca_publisher_queue_reschedule'); ?>
                                <input type="datetime-local" name="cp_cuando" value="<?php echo esc_attr(wp_date('Y-m-d\TH:i', (int) ($envio['time'] ?? 0))); ?>" />
                                <button type="submit" class="button"><?php echo esc_html__('Mover', 'convoca-publisher'); ?></button>
                            </form>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cp-acciones">
                                <input type="hidden" name="action" value="cp_queue_cancel" />
                                <input type="hidden" name="cp_envio" value="<?php echo esc_attr($ref); ?>" />
                                <?php wp_nonce_field('convoca_publisher_queue_cancel'); ?>
                                <button type="submit" class="button" data-cp-confirm="<?php echo esc_attr__('¿Quitar este envío de la cola?', 'convoca-publisher'); ?>"><?php echo esc_html__('Quitar', 'convoca-publisher'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * «Compartir ahora» en el listado de entradas, sin abrir el editor: va a las cuentas que
     * tenga marcadas esa entrada. Para elegir una cuenta concreta, el editor.
     *
     * @param array<string, string> $acciones
     *
     * @return array<string, string>
     */
    public static function row_action(array $acciones, \WP_Post $post): array
    {
        if ('post' !== $post->post_type || !current_user_can('edit_post', $post->ID) || [] === Plugin::accounts()) {
            return $acciones;
        }

        $acciones['convoca_compartir'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url(self::share_now_url($post->ID)),
            esc_html__('Compartir ahora', 'convoca-publisher')
        );

        return $acciones;
    }

    private static function share_now_url(int $post_id): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=cp_share_now&post=' . $post_id),
            'convoca_publisher_share_now_' . $post_id
        );
    }

    /**
     * Compartir desde el listado: lanza la publicación y vuelve a la lista.
     */
    public static function handle_share_now(): void
    {
        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;

        if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }

        check_admin_referer('convoca_publisher_share_now_' . $post_id);

        $publicador = Publisher::instance();
        $enviados   = $publicador ? $publicador->publish_to_accounts($post_id, [], true, true) : [];

        set_transient(
            'convoca_publisher_queue_notice_' . get_current_user_id(),
            [] === $enviados
                ? __('No había ninguna cuenta a la que enviar esta entrada.', 'convoca-publisher')
                : __('Compartido. Mirando la cola, lo tienes.', 'convoca-publisher'),
            30
        );
        wp_safe_redirect(self::tab_url('queue'));
        exit;
    }

    /**
     * Aviso tras compartir desde el listado.
     */
    public static function shared_notice(): void
    {
        if (!isset($_GET['convoca_compartido'])) {
            return;
        }

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html__('Compartido. El detalle está en la cola.', 'convoca-publisher')
        );
    }

    /**
     * Reprogramar un envío (desde la lista o arrastrándolo en el calendario).
     */
    public static function handle_queue_reschedule(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }

        check_admin_referer('convoca_publisher_queue_reschedule');

        $ref  = isset($_POST['cp_envio']) ? sanitize_text_field(wp_unslash((string) $_POST['cp_envio'])) : '';
        $dia  = isset($_POST['cp_dia']) ? sanitize_text_field(wp_unslash((string) $_POST['cp_dia'])) : '';
        $cuando = isset($_POST['cp_cuando']) ? sanitize_text_field(wp_unslash((string) $_POST['cp_cuando'])) : '';

        $sello = self::queue_parse_when($dia, $cuando);

        if ($sello > 0) {
            self::queue_move($ref, $sello);
            set_transient('convoca_publisher_queue_notice_' . get_current_user_id(), __('Envío reprogramado.', 'convoca-publisher'), 30);
        }

        wp_safe_redirect(self::tab_url('queue'));
        exit;
    }

    /**
     * Quitar un envío de la cola.
     */
    public static function handle_queue_cancel(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }

        check_admin_referer('convoca_publisher_queue_cancel');

        $ref = isset($_POST['cp_envio']) ? sanitize_text_field(wp_unslash((string) $_POST['cp_envio'])) : '';
        self::queue_remove($ref);

        set_transient('convoca_publisher_queue_notice_' . get_current_user_id(), __('Envío quitado de la cola.', 'convoca-publisher'), 30);
        wp_safe_redirect(self::tab_url('queue'));
        exit;
    }

    /**
     * Recolocar ahora mismo, sin esperar al cron.
     */
    public static function handle_queue_spacing(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No tienes permisos.', 'convoca-publisher'));
        }

        check_admin_referer('convoca_publisher_queue_spacing');

        $movidos = Queue::apply_spacing();

        set_transient(
            'convoca_publisher_queue_notice_' . get_current_user_id(),
            sprintf(
                /* translators: %d: envíos recolocados */
                _n('%d envío recolocado.', '%d envíos recolocados.', $movidos, 'convoca-publisher'),
                $movidos
            ),
            30
        );
        wp_safe_redirect(self::tab_url('queue'));
        exit;
    }

    /**
     * Referencia de un envío («schedule:12» o «retry:5»).
     */
    private static function queue_move(string $ref, int $when): bool
    {
        [$kind, $id] = array_pad(explode(':', $ref, 2), 2, '');
        $id          = (int) $id;

        if ($id <= 0) {
            return false;
        }

        return 'retry' === $kind ? Queue::reschedule_retry($id, $when) : Queue::reschedule_schedule($id, $when);
    }

    /**
     * Quitar un envío de la cola.
     */
    private static function queue_remove(string $ref): bool
    {
        [$kind, $id] = array_pad(explode(':', $ref, 2), 2, '');
        $id          = (int) $id;

        if ($id <= 0) {
            return false;
        }

        return 'retry' === $kind ? Queue::cancel_retry($id) : Queue::cancel_schedule($id);
    }

    /**
     * Del formulario a un sello de tiempo del sitio.
     *
     * Dos formas de decir cuándo: el día desde el que se suelta un envío en el calendario
     * (`$dia`, a las 9:00, que es lo que se puede elegir arrastrando) o una fecha y hora
     * completas (`$cuando`, de la lista de la cola). Todo en la zona horaria del sitio.
     */
    public static function queue_parse_when(string $dia, string $cuando): int
    {
        $tz = wp_timezone();

        if ('' !== $cuando) {
            $fecha = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $cuando, $tz);

            return $fecha instanceof \DateTimeImmutable ? $fecha->getTimestamp() : 0;
        }

        if ('' !== $dia) {
            $fecha = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $dia . ' 09:00', $tz);

            return $fecha instanceof \DateTimeImmutable ? $fecha->getTimestamp() : 0;
        }

        return 0;
    }

}
