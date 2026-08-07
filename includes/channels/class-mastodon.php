<?php

/**
 * Convoca Publisher
 *
 * @package    Convoca\Publisher
 * @subpackage Channels
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */


namespace ConvocaPublisher\Channels;

defined('ABSPATH') || exit;

class Mastodon implements ChannelInterface
{
    public function get_id(): string
    {
        return 'mastodon';
    }

    public function get_name(): string
    {
        return __('Mastodon', 'convoca-publisher');
    }

    public function is_available(): bool
    {
        return !empty(get_option('convoca_publisher_mastodon_token', '')) && !empty(get_option('convoca_publisher_mastodon_server', ''));
    }

    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
    {
        $token = get_option('convoca_publisher_mastodon_token', '');
        $server = rtrim(get_option('convoca_publisher_mastodon_server', ''), '/');
        $visibility = get_option('convoca_publisher_mastodon_visibility', 'public');

        if (empty($token) || empty($server)) {
            return ['success' => false, 'error' => __('Token o servidor de Mastodon no configurados.', 'convoca-publisher')];
        }

        $text = html_entity_decode($message);
        $text .= "\n\n" . $url;

        $body = [
            'status'     => mb_substr($text, 0, 500),
            'visibility' => $visibility,
        ];

        if (!empty($image_url)) {
            $image_data = wp_remote_get($image_url, ['timeout' => 15]);
            if (!is_wp_error($image_data) && wp_remote_retrieve_response_code($image_data) === 200) {
                $boundary = wp_generate_password(24, false);
                $media_body = "--{$boundary}\r\n"
                    . 'Content-Disposition: form-data; name="file"; filename="image.jpg"' . "\r\n"
                    . "Content-Type: image/jpeg\r\n\r\n"
                    . wp_remote_retrieve_body($image_data) . "\r\n"
                    . "--{$boundary}--";

                $media_resp = wp_remote_post("{$server}/api/v2/media", [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                        'Content-Type'  => "multipart/form-data; boundary={$boundary}",
                    ],
                    'body'    => $media_body,
                    'timeout' => 30,
                ]);

                if (!is_wp_error($media_resp)) {
                    $media_data = json_decode(wp_remote_retrieve_body($media_resp), true);
                    if (!empty($media_data['id'])) {
                        $body['media_ids'] = [$media_data['id']];
                    }
                }
            }
        }

        $response = wp_remote_post("{$server}/api/v1/statuses", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json',
                'Idempotency-Key' => wp_generate_password(32, false),
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $resp_body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code >= 200 && $http_code < 300 && isset($resp_body['id'])) {
            return ['success' => true, 'post_id' => $resp_body['id'], 'url' => $resp_body['url'] ?? ''];
        }

        $error = $resp_body['error'] ?? __('Error desconocido de Mastodon API.', 'convoca-publisher');
        return ['success' => false, 'error' => $error];
    }

    public function get_settings_fields(): array
    {
        return [
            'convoca_publisher_mastodon_server' => [
                'title'       => __('Servidor', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('Ej: https://mastodon.social', 'convoca-publisher'),
            ],
            'convoca_publisher_mastodon_token' => [
                'title'       => __('Access Token', 'convoca-publisher'),
                'type'        => 'password',
                'description' => __('Token de acceso de Mastodon (Desarrollo → Nuevo token → write:statuses).', 'convoca-publisher'),
            ],
            'convoca_publisher_mastodon_visibility' => [
                'title'       => __('Visibilidad', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('public, unlisted, private, direct (por defecto public).', 'convoca-publisher'),
            ],
            'convoca_publisher_mastodon_template' => [
                'title'       => __('Plantilla del mensaje', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('{title}, {excerpt}, {url}, {hashtags}. Máximo 500 caracteres. Por defecto: {title} — {url} {hashtags}', 'convoca-publisher'),
            ],
        ];
    }

    public function validate_settings(array $settings): array
    {
        $errors = [];
        if (empty($settings['convoca_publisher_mastodon_token'])) {
            $errors[] = __('El token de Mastodon es obligatorio.', 'convoca-publisher');
        }
        if (empty($settings['convoca_publisher_mastodon_server'])) {
            $errors[] = __('El servidor de Mastodon es obligatorio.', 'convoca-publisher');
        }
        return $errors;
    }

    public function verify_connection(): array
    {
        $token = get_option('convoca_publisher_mastodon_token', '');
        $server = rtrim(get_option('convoca_publisher_mastodon_server', ''), '/');

        if (empty($token) || empty($server)) {
            return ['success' => false, 'message' => __('Token o servidor de Mastodon no configurados.', 'convoca-publisher')];
        }

        $resp = wp_remote_get("{$server}/api/v1/accounts/verify_credentials", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($resp)) {
            return ['success' => false, 'message' => __('Error de conexión: ', 'convoca-publisher') . $resp->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $http_code = wp_remote_retrieve_response_code($resp);

        if ($http_code >= 200 && $http_code < 300 && isset($body['id'])) {
            $acct = $body['acct'] ?? $body['username'] ?? 'desconocido';
            return [
                'success' => true,
                'message' => sprintf(
                    /* translators: %s: Mastodon account handle */
                    __('✅ Conexión correcta. Cuenta: @%s', 'convoca-publisher'),
                    $acct
                ),
            ];
        }

        $error_msg = $body['error'] ?? __('Error desconocido de Mastodon API.', 'convoca-publisher');
        return ['success' => false, 'message' => $error_msg];
    }
}
