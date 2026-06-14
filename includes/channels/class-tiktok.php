<?php

namespace ConvocaPublisher\Channels;

defined('ABSPATH') || exit;

class Tiktok implements ChannelInterface
{
    public function get_id(): string
    {
        return 'tiktok';
    }

    public function get_name(): string
    {
        return __('TikTok', 'convoca-publisher');
    }

    public function is_available(): bool
    {
        return !empty(get_option('cp_tiktok_token', '')) && !empty(get_option('cp_tiktok_open_id', ''));
    }

    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
    {
        $token = get_option('cp_tiktok_token', '');
        $open_id = get_option('cp_tiktok_open_id', '');

        if (empty($token) || empty($open_id)) {
            return ['success' => false, 'error' => __('Token u Open ID de TikTok no configurados.', 'convoca-publisher')];
        }

        $post_url = $url;
        $title = html_entity_decode(wp_trim_words($message, 15));

        $body = [
            'open_id' => $open_id,
            'access_token' => $token,
            'post_info' => [
                'title'             => mb_substr($title, 0, 100),
                'privacy_level'     => 'PUBLIC',
                'disable_duet'      => false,
                'disable_stitch'    => false,
                'disable_comment'   => false,
                'brand_organic_opt_in' => false,
            ],
            'source_info' => [
                'source'   => 'PULL_FROM_URL',
                'video_url' => '',
            ],
        ];

        $response = wp_remote_post('https://open-api.tiktok.com/share/video/publish/', [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $resp_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($http_code >= 200 && $http_code < 300 && isset($resp_body['data']['publish_id'])) {
            return ['success' => true, 'post_id' => $resp_body['data']['publish_id']];
        }

        $error = $resp_body['message'] ?? $resp_body['data']['error']['message'] ?? __('Error desconocido de TikTok API.', 'convoca-publisher');
        return ['success' => false, 'error' => $error];
    }

    public function get_settings_fields(): array
    {
        return [
            'cp_tiktok_token' => [
                'title'       => __('Access Token', 'convoca-publisher'),
                'type'        => 'password',
                'description' => __('Token OAuth 2.0 de TikTok con permisos video.publish.', 'convoca-publisher'),
            ],
            'cp_tiktok_open_id' => [
                'title'       => __('Open ID', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('Open ID del usuario de TikTok que publicará.', 'convoca-publisher'),
            ],
            'cp_tiktok_template' => [
                'title'       => __('Título del video', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('{title} — máximo 100 caracteres. Por defecto: {title}', 'convoca-publisher'),
            ],
        ];
    }

    public function validate_settings(array $settings): array
    {
        $errors = [];
        if (empty($settings['cp_tiktok_token'])) {
            $errors[] = __('El token de TikTok es obligatorio.', 'convoca-publisher');
        }
        if (empty($settings['cp_tiktok_open_id'])) {
            $errors[] = __('El Open ID de TikTok es obligatorio.', 'convoca-publisher');
        }
        return $errors;
    }
}
