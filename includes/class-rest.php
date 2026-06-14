<?php

namespace ConvocaPublisher;

defined('ABSPATH') || exit;

class Rest
{
    private const NAMESPACE = 'convoca-publisher/v1';

    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/test/(?P<channel>[a-z_]+)', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'test_channel'],
            'permission_callback' => [self::class, 'check_permission'],
            'args' => [
                'channel' => ['required' => true, 'type' => 'string'],
                'post_id' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/status', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_status'],
            'permission_callback' => [self::class, 'check_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/publish/(?P<post_id>\d+)', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'publish_post'],
            'permission_callback' => [self::class, 'check_permission'],
            'args' => [
                'dry_run'  => ['type' => 'boolean', 'default' => false],
                'channels' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ]);
    }

    public static function check_permission(\WP_REST_Request $request): bool
    {
        return current_user_can('manage_options');
    }

    public static function test_channel(\WP_REST_Request $request): \WP_REST_Response
    {
        $channel_id = $request->get_param('channel');
        $post_id = $request->get_param('post_id');

        $channels = convoca_publisher()->get_channels();
        if (!isset($channels[$channel_id])) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => __('Canal no encontrado o no configurado.', 'convoca-publisher'),
            ], 404);
        }

        $post = get_post($post_id);
        if (!$post) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => __('Post no encontrado.', 'convoca-publisher'),
            ], 404);
        }

        $publisher = Publisher::instance();
        if (!$publisher) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => __('Publisher no inicializado.', 'convoca-publisher'),
            ], 500);
        }

        $results = $publisher->publish_post($post_id, true);
        $result = $results[$channel_id] ?? ['success' => false, 'error' => __('Canal no procesado.', 'convoca-publisher')];

        return new \WP_REST_Response($result, !empty($result['success']) ? 200 : 500);
    }

    public static function get_status(): \WP_REST_Response
    {
        $channels = convoca_publisher()->get_channels();
        $data = [];

        foreach ($channels as $id => $ch) {
            $data[$id] = [
                'name'      => $ch->get_name(),
                'available' => $ch->is_available(),
                'settings'  => array_keys($ch->get_settings_fields()),
            ];
        }

        $retry_stats = class_exists(Retry::class) ? Retry::get_queue_stats() : [];

        return new \WP_REST_Response([
            'channels'    => $data,
            'retry_queue' => $retry_stats,
            'version'     => CP_VERSION,
        ]);
    }

    public static function publish_post(\WP_REST_Request $request): \WP_REST_Response
    {
        $post_id = $request->get_param('post_id');
        $dry_run = $request->get_param('dry_run');
        $channels_filter = $request->get_param('channels');

        $post = get_post($post_id);
        if (!$post || $post->post_status !== 'publish') {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => __('Post no encontrado o no publicado.', 'convoca-publisher'),
            ], 404);
        }

        if ($dry_run) {
            $all_channels = convoca_publisher()->get_channels();
            $results = [];
            foreach ($all_channels as $id => $ch) {
                if ($channels_filter && !in_array($id, $channels_filter, true)) {
                    continue;
                }
                $results[$id] = [
                    'success'  => true,
                    'dry_run'  => true,
                    'message'  => sprintf(
                        __('Simulación: se publicaría en %s', 'convoca-publisher'),
                        $ch->get_name()
                    ),
                    'channel'  => $ch->get_name(),
                    'post_url' => get_permalink($post),
                ];
            }
            return new \WP_REST_Response(['success' => true, 'dry_run' => true, 'results' => $results]);
        }

        $publisher = Publisher::instance();
        if (!$publisher) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => __('Publisher no inicializado.', 'convoca-publisher'),
            ], 500);
        }

        if (!empty($channels_filter)) {
            $results = [];
            foreach ($channels_filter as $channel_id) {
                $channels = convoca_publisher()->get_channels();
                $channel = $channels[$channel_id] ?? null;
                if ($channel && $channel->is_available()) {
                    $url = get_permalink($post);
                    $hashtags = self::get_hashtags($post);
                    $image_url = '';
                    $thumb_id = get_post_thumbnail_id($post);
                    if ($thumb_id) {
                        $image = wp_get_attachment_image_src($thumb_id, 'large');
                        $image_url = $image ? $image[0] : '';
                    }

                    $tkey = 'cp_' . $channel->get_id() . '_template';
                    $template = get_option($tkey, get_option('cp_message_template', '{title} — {url}'));
                    $message = str_replace(
                        ['{title}', '{url}', '{excerpt}', '{hashtags}', '{date}', '{author}'],
                        [$post->post_title, $url, wp_trim_words($post->post_excerpt ?: $post->post_title, 20), $hashtags, get_the_date('', $post), get_the_author_meta('display_name', (int) $post->post_author)],
                        $template
                    );

                    $result = $channel->publish($post_id, $message, $url, $image_url);
                    $results[$channel_id] = $result;
                }
            }
            return new \WP_REST_Response(['success' => true, 'results' => $results]);
        }

        $results = $publisher->publish_post($post_id, true);
        return new \WP_REST_Response(['success' => true, 'results' => $results]);
    }

    private static function get_hashtags(\WP_Post $post): string
    {
        $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);
        if (empty($tags)) {
            return '';
        }
        $tags = array_slice($tags, 0, 5);
        $hashtags = array_map(function (string $tag): string {
            $tag = sanitize_title($tag);
            return '#' . str_replace(['-', '_', ' '], '', $tag);
        }, $tags);
        return implode(' ', $hashtags);
    }
}
