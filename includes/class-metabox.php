<?php

namespace ConvocaPublisher;

defined('ABSPATH') || exit;

class Metabox
{
    public static function init(): void
    {
        add_action('add_meta_boxes', [self::class, 'register']);
        add_action('save_post', [self::class, 'save']);
        add_action('wp_ajax_cp_republish', [self::class, 'ajax_republish']);
    }

    public static function register(): void
    {
        add_meta_box(
            'convoca-publisher',
            esc_html__('Convoca Publisher', 'convoca-publisher'),
            [self::class, 'render'],
            'post',
            'side',
            'high'
        );
    }

    public static function render(\WP_Post $post): void
    {
        wp_nonce_field('cp_metabox', 'cp_metabox_nonce');

        $results = get_post_meta($post->ID, '_cp_publish_results', true) ?: [];
        $published = get_post_meta($post->ID, '_cp_published', true);
        $disabled = get_post_meta($post->ID, '_cp_disabled_channels', true) ?: [];
        $channels = convoca_publisher()->get_channels();

        echo '<div style="margin: 8px 0;">';

        if ($published) {
            echo '<p style="color:#46b450;"><strong>✅ ' . esc_html__('Publicado en redes', 'convoca-publisher') . '</strong></p>';
            foreach ($results as $channel_id => $result) {
                $icon = !empty($result['success']) ? '✅' : '❌';
                echo '<p style="margin:4px 0;font-size:12px;">' . $icon . ' <strong>' . esc_html($channel_id) . '</strong>: ';
                if (!empty($result['success'])) {
                    echo '<span style="color:#46b450;">' . esc_html($result['post_id'] ?? 'OK') . '</span>';
                } else {
                    echo '<span style="color:#dc3232;">' . esc_html($result['error'] ?? __('Error', 'convoca-publisher')) . '</span>';
                }
                echo '</p>';
            }
            echo '<p><button type="button" class="button button-small cp-republish" data-post-id="' . esc_attr((string) $post->ID) . '">'
                . esc_html__('↻ Republicar', 'convoca-publisher') . '</button></p>';
        } else {
            echo '<p>' . esc_html__('Se publicará automáticamente al guardar.', 'convoca-publisher') . '</p>';
        }

        if (!empty($channels)) {
            echo '<hr><p><strong>' . esc_html__('Canales:', 'convoca-publisher') . '</strong></p>';
            foreach ($channels as $id => $ch) {
                $checked = in_array($id, $disabled, true) ? '' : 'checked';
                echo '<label style="display:block;margin:4px 0;font-size:12px;">';
                echo '<input type="checkbox" name="cp_channels[' . esc_attr($id) . ']" value="1" ' . $checked . '> ';
                echo esc_html($ch->get_name());
                echo '</label>';
            }
        }

        // Programar publicación
        $schedule_ts = (int) get_post_meta($post->ID, '_cp_schedule_time', true);
        $schedule_val = $schedule_ts ? wp_date('Y-m-d\TH:i', $schedule_ts) : '';
        echo '<hr><p><strong>' . esc_html__('Programar publicación:', 'convoca-publisher') . '</strong></p>';
        echo '<label style="font-size:12px;">';
        echo '<input type="datetime-local" name="cp_schedule_time" value="' . esc_attr($schedule_val) . '" style="width:100%;">';
        echo '<p class="description" style="font-size:11px;margin:4px 0;">' . esc_html__('Déjalo vacío para publicar al guardar el post.', 'convoca-publisher') . '</p>';
        echo '</label>';

        echo '</div>';

        // Inline JS for republish button
        $nonce = wp_create_nonce('cp_republish');
        echo '<script>
jQuery(function($) {
    $(".cp-republish").on("click", function() {
        var btn = $(this);
        btn.text("' . esc_js(__('Publicando...', 'convoca-publisher')) . '").prop("disabled", true);
        $.post(ajaxurl, {
            action: "cp_republish",
            post_id: btn.data("post-id"),
            _wpnonce: "' . esc_js($nonce) . '"
        }, function() { location.reload(); });
    });
});
</script>';
    }

    public static function save(int $post_id): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['cp_metabox_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cp_metabox_nonce'])), 'cp_metabox')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $disabled = [];
        $channels = convoca_publisher()->get_channels();
        foreach (array_keys($channels) as $id) {
            if (!isset($_POST['cp_channels'][$id])) {
                $disabled[] = $id;
            }
        }
        update_post_meta($post_id, '_cp_disabled_channels', $disabled);

        // Guardar programación
        $schedule_raw = sanitize_text_field($_POST['cp_schedule_time'] ?? '');
        if ($schedule_raw) {
            $schedule_ts = strtotime($schedule_raw);
            if ($schedule_ts > time()) {
                update_post_meta($post_id, '_cp_schedule_time', $schedule_ts);
            }
        } else {
            delete_post_meta($post_id, '_cp_schedule_time');
        }
    }

    public static function ajax_republish(): void
    {
        check_ajax_referer('cp_republish', '_wpnonce');
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
