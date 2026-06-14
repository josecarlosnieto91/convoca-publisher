<?php

namespace ConvocaPublisher\Channels;

defined('ABSPATH') || exit;

class Twitter implements ChannelInterface
{
    public function get_id(): string
    {
        return 'twitter';
    }

    public function get_name(): string
    {
        return __('Twitter / X', 'convoca-publisher');
    }

    public function is_available(): bool
    {
        $bearer = get_option('cp_twitter_bearer_token', '');
        return !empty($bearer);
    }

    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
    {
        $bearer = get_option('cp_twitter_bearer_token', '');

        if (empty($bearer)) {
            return ['success' => false, 'error' => __('Bearer token de Twitter no configurado.', 'convoca-publisher')];
        }

        $tweet_text = html_entity_decode(wp_trim_words($message, 25));
        if (mb_strlen($tweet_text . ' ' . $url) > 280) {
            $tweet_text = mb_substr($tweet_text, 0, 260 - mb_strlen($url)) . '…';
        }
        $tweet = $tweet_text . ' ' . $url;

        $body = ['text' => mb_substr($tweet, 0, 280)];

        if (!empty($image_url)) {
            $media_id = $this->upload_media($image_url, $bearer);
            if ($media_id) {
                $body['media'] = ['media_ids' => [$media_id]];
            }
        }

        $response = wp_remote_post('https://api.twitter.com/2/tweets', [
            'headers' => [
                'Authorization' => "Bearer {$bearer}",
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $resp_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($http_code >= 200 && $http_code < 300 && isset($resp_body['data']['id'])) {
            return ['success' => true, 'post_id' => $resp_body['data']['id']];
        }

        $error = $resp_body['title'] ?? $resp_body['detail'] ?? __('Error desconocido de Twitter API.', 'convoca-publisher');
        return ['success' => false, 'error' => $error];
    }

    private function upload_media(string $image_url, string $bearer): ?string
    {
        $image_data = wp_remote_get($image_url, ['timeout' => 15]);
        if (is_wp_error($image_data) || wp_remote_retrieve_response_code($image_data) !== 200) {
            return null;
        }

        $boundary = wp_generate_password(24, false);
        $body = "--{$boundary}\r\n"
              . "Content-Disposition: form-data; name=\"media\"; filename=\"image.jpg\"\r\n"
              . "Content-Type: image/jpeg\r\n\r\n"
              . wp_remote_retrieve_body($image_data) . "\r\n"
              . "--{$boundary}--";

        $response = wp_remote_post('https://upload.twitter.com/1.1/media/upload.json', [
            'headers' => [
                'Authorization' => "Bearer {$bearer}",
                'Content-Type'  => "multipart/form-data; boundary={$boundary}",
            ],
            'body'    => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);
        return $result['media_id_string'] ?? null;
    }

    public function get_settings_fields(): array
    {
        return [
            'cp_twitter_bearer_token' => [
                'title'       => __('Bearer Token (OAuth 2.0)', 'convoca-publisher'),
                'type'        => 'password',
                'description' => __('Token OAuth 2.0 de la app de Twitter/X con permisos tweet.read y tweet.write.', 'convoca-publisher'),
            ],
            'cp_twitter_template' => [
                'title'       => __('Plantilla del tweet', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('{title}, {excerpt}, {url}, {hashtags}. Máximo 280 caracteres total. Por defecto: {title} {url} {hashtags}', 'convoca-publisher'),
            ],
        ];
    }

    public function validate_settings(array $settings): array
    {
        if (empty($settings['cp_twitter_bearer_token'])) {
            return [__('El Bearer Token de Twitter es obligatorio.', 'convoca-publisher')];
        }
        return [];
    }
}
