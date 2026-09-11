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

class Metabox
{
    public static function init(): void
    {
        add_action('add_meta_boxes', [self::class, 'register']);
        add_action('save_post', [self::class, 'save']);
        add_action('wp_ajax_cp_republish', [self::class, 'ajax_republish']);
        add_action('wp_ajax_cp_share', [self::class, 'ajax_share']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    /**
     * Lo que necesita el editor: su JS y los datos para actualizar la vista previa.
     */
    public static function enqueue(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $post = get_post();
        if (!$post) {
            return;
        }

        wp_enqueue_script(
            'convoca-publisher-metabox',
            CONVOCA_PUBLISHER_PLUGIN_URL . 'assets/js/metabox.js',
            [],
            CONVOCA_PUBLISHER_VERSION,
            true
        );

        $reglas = [];

        foreach (Plugin::accounts() as $cuenta) {
            $reglas[$cuenta->get_channel_id()] = Platform_Rules::rules($cuenta->get_channel_id());
        }

        wp_localize_script('convoca-publisher-metabox', 'convocaPublisherMessage', [
            'redes'   => $reglas,
            'valores' => [
                'title'          => $post->post_title,
                'excerpt'        => get_the_excerpt($post),
                'url'            => (string) get_permalink($post),
                'permalink'      => (string) get_permalink($post),
                'hashtags'       => '',
                'date'           => get_the_date('', $post),
                'author'         => get_the_author_meta('display_name', (int) $post->post_author),
                'featured_image' => '',
            ],
            'compartir' => [
                'ajax'  => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('convoca_publisher_share'),
                'texto' => __('Compartido. Recargando para ver el resultado…', 'convoca-publisher'),
            ],
            'textos'  => [
                /* translators: %d: caracteres de más */
                'recorta' => __('No cabe: se recortarán %d caracteres al enviar.', 'convoca-publisher'),
            ],
        ]);
    }

    public static function register(): void
    {
        add_meta_box(
            'convoca-publisher',
            esc_html__('Publicar en RRSS', 'convoca-publisher'),
            [self::class, 'render'],
            'post',
            'side',
            'high'
        );
    }

    public static function render(\WP_Post $post): void
    {
        wp_nonce_field('convoca_publisher_metabox', 'convoca_publisher_metabox_nonce');

        $results = get_post_meta($post->ID, '_convoca_publisher_publish_results', true) ?: [];
        $published = get_post_meta($post->ID, '_convoca_publisher_published', true);
        $disabled = get_post_meta($post->ID, '_convoca_publisher_disabled_channels', true) ?: [];
        $channels = convoca_publisher()->get_channels();

        echo '<div class="cp-meta">';

        if ($published) {
            echo '<p class="cp-meta__ok"><strong>✅ ' . esc_html__('Publicado en redes', 'convoca-publisher') . '</strong></p>';
            foreach ($results as $channel_id => $result) {
                if ($channel_id === '_warnings') {
                    continue;
                }
                $icon = !empty($result['success']) ? '✅' : '❌';
                echo '<p class="cp-meta__row">' . esc_html($icon) . ' <strong>' . esc_html($channel_id) . '</strong>: ';
                if (!empty($result['success'])) {
                    echo '<span class="cp-meta__ok">' . esc_html($result['post_id'] ?? 'OK') . '</span>';
                } else {
                    echo '<span class="cp-meta__fail">' . esc_html($result['error'] ?? __('Error', 'convoca-publisher')) . '</span>';
                }
                echo '</p>';
            }
            // Mostrar warnings si existen
            if (!empty($results['_warnings'])) {
                foreach ($results['_warnings'] as $w) {
                    echo '<p class="cp-meta__warn">⚠️ ' . esc_html($w) . '</p>';
                }
            }
            echo '<p><button type="button" class="button button-small cp-republish" data-post-id="' . esc_attr((string) $post->ID) . '">'
                . esc_html__('↻ Republicar', 'convoca-publisher') . '</button></p>';
        } elseif (get_option('convoca_publisher_auto_publish', true)) {
            echo '<p>' . esc_html__('Se publicará automáticamente al guardar.', 'convoca-publisher') . '</p>';
        } else {
            echo '<p>' . esc_html__('No se publica solo: usa «Compartir ahora» cuando quieras publicarlo.', 'convoca-publisher') . '</p>';
        }

        if (!empty($channels)) {
            echo '<hr><p><strong>' . esc_html__('Canales:', 'convoca-publisher') . '</strong></p>';
            foreach ($channels as $id => $ch) {
                $checked = in_array($id, $disabled, true) ? '' : 'checked';
                echo '<label class="cp-meta__check">';
                echo '<input type="checkbox" name="convoca_publisher_channels[' . esc_attr($id) . ']" value="1" ' . esc_attr($checked) . '> ';
                echo esc_html($ch->get_name());
                echo '</label>';
            }
        }

        // Programar publicación
        $schedule_ts  = (int) get_post_meta($post->ID, '_convoca_publisher_schedule_time', true);
        $schedule_val = $schedule_ts ? wp_date('Y-m-d\TH:i', $schedule_ts) : '';
        echo '<hr><p><strong>' . esc_html__('Programar publicación:', 'convoca-publisher') . '</strong></p>';
        echo '<label class="cp-meta__label">';
        echo '<input type="datetime-local" name="convoca_publisher_schedule_time" value="' . esc_attr($schedule_val) . '" class="cp-meta__input">';
        echo '<p class="description cp-meta__help">' . esc_html__('Déjalo vacío para publicar al guardar el post.', 'convoca-publisher') . '</p>';
        echo '</label>';

        self::render_message($post);

        echo '</div>';
    }

    /**
     * El mensaje propio de esta entrada, y cómo queda en cada cuenta.
     *
     * La vista previa no es una aproximación: es el mensaje que se va a enviar (el mismo
     * camino que usa el publicador), con el recuento que hace la red.
     */
    private static function render_message(\WP_Post $post): void
    {
        $propio = (string) get_post_meta($post->ID, Publisher::MESSAGE_META, true);
        $cuentas = Plugin::accounts();

        echo '<hr><p><strong>' . esc_html__('Mensaje de esta entrada', 'convoca-publisher') . '</strong></p>';
        echo '<textarea id="convoca-publisher-message" name="convoca_publisher_message" rows="4" class="widefat" placeholder="' . esc_attr__('{title} — {url}', 'convoca-publisher') . '">' . esc_textarea($propio) . '</textarea>';
        echo '<p class="description cp-meta__help">' . esc_html__('Vacío = la plantilla de la cuenta. Puedes usar {title}, {excerpt}, {url}, {hashtags}, {date}, {author}.', 'convoca-publisher') . '</p>';

        if ([] === $cuentas) {
            echo '<p class="description">' . esc_html__('Todavía no hay ninguna cuenta configurada.', 'convoca-publisher') . '</p>';

            return;
        }

        $publicador = Publisher::instance();

        foreach ($cuentas as $id => $cuenta) {
            $red     = $cuenta->get_channel_id();
            $mensaje = $publicador ? $publicador->preview_message($post->ID, (string) $id) : '';
            $cuenta_chars = Platform_Rules::count($red, $mensaje);
            $limite       = Platform_Rules::limit($red);
            $clase        = $cuenta_chars > $limite ? 'cp-contador cp-contador--pasado' : ($cuenta_chars > (int) ($limite * 0.9) ? 'cp-contador cp-contador--justo' : 'cp-contador');
            ?>
            <div class="cp-cuenta" data-cp-resumen data-cp-red="<?php echo esc_attr($red); ?>" data-cp-plantilla="<?php echo esc_attr($mensaje); ?>">
                <p class="cp-cuenta__nombre">
                    <strong><?php echo esc_html($cuenta->get_name()); ?></strong>
                    <span class="cp-cuenta__red"><?php echo esc_html($red); ?></span>
                    <span class="<?php echo esc_attr($clase); ?>" data-cp-contador><?php echo esc_html($cuenta_chars . ' / ' . $limite); ?></span>
                </p>
                <p class="cp-previa" data-cp-previa><?php echo esc_html($mensaje); ?></p>
                <p class="cp-aviso" data-cp-aviso hidden></p>
                <p>
                    <button type="button" class="button button-small cp-compartir" data-post-id="<?php echo esc_attr((string) $post->ID); ?>" data-cuenta="<?php echo esc_attr((string) $id); ?>">
                        <?php echo esc_html__('Compartir ahora en esta cuenta', 'convoca-publisher'); ?>
                    </button>
                </p>
            </div>
            <?php
        }
    }

    public static function save(int $post_id): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['convoca_publisher_metabox_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['convoca_publisher_metabox_nonce'])), 'convoca_publisher_metabox')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $disabled = [];
        $channels = convoca_publisher()->get_channels();
        foreach (array_keys($channels) as $id) {
            if (!isset($_POST['convoca_publisher_channels'][$id])) {
                $disabled[] = $id;
            }
        }
        update_post_meta($post_id, '_convoca_publisher_disabled_channels', $disabled);

        // Mensaje propio de esta entrada (vacío = la plantilla de la cuenta).
        $mensaje = isset($_POST['convoca_publisher_message']) ? sanitize_textarea_field(wp_unslash((string) $_POST['convoca_publisher_message'])) : '';
        if ('' !== trim($mensaje)) {
            update_post_meta($post_id, Publisher::MESSAGE_META, $mensaje);
        } else {
            delete_post_meta($post_id, Publisher::MESSAGE_META);
        }

        // Guardar programación
        $schedule_raw = sanitize_text_field(wp_unslash($_POST['convoca_publisher_schedule_time'] ?? ''));
        if ($schedule_raw) {
            $schedule_ts = strtotime($schedule_raw);
            if ($schedule_ts > time()) {
                update_post_meta($post_id, '_convoca_publisher_schedule_time', $schedule_ts);
            }
        } else {
            delete_post_meta($post_id, '_convoca_publisher_schedule_time');
        }
    }

    /**
     * Compartir en una cuenta concreta (el botón de cada tarjeta del editor).
     */
    public static function ajax_share(): void
    {
        check_ajax_referer('convoca_publisher_share', '_wpnonce');

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $cuenta  = isset($_POST['cuenta']) ? sanitize_text_field(wp_unslash((string) $_POST['cuenta'])) : '';

        if ($post_id <= 0 || '' === $cuenta || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('No se pudo compartir.', 'convoca-publisher')], 403);
        }

        $publicador = Publisher::instance();
        if (!$publicador) {
            wp_send_json_error(['message' => __('El publicador no está disponible.', 'convoca-publisher')], 500);
        }

        $resultado = $publicador->publish_to_accounts($post_id, [$cuenta], true, true);
        $envio     = $resultado[$cuenta] ?? [];
        $ok        = !empty($envio['success']);

        wp_send_json([
            'success'  => $ok,
            'cuenta'   => $cuenta,
            'message'  => $ok
                ? __('Compartido. Recargando para ver el resultado…', 'convoca-publisher')
                : esc_html((string) ($envio['error'] ?? __('No se pudo compartir.', 'convoca-publisher'))),
            'warnings' => array_merge((array) ($envio['warnings'] ?? []), isset($envio['trimmed']) ? [__('Se recortó para que cupiera en esa red.', 'convoca-publisher')] : []),
        ]);
    }

    public static function ajax_republish(): void
    {
        check_ajax_referer('convoca_publisher_republish', '_wpnonce');
        if (!current_user_can('edit_posts')) {
            wp_die('-1');
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if ($post_id) {
            $publisher = Publisher::instance();
            if ($publisher) {
                $publisher->publish_post($post_id, true);
            }
        }
        wp_send_json(['success' => true]);
    }
}
