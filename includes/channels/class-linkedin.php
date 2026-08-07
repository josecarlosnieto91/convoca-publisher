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

class Linkedin implements ChannelInterface
{
    public function get_id(): string
    {
        return 'linkedin';
    }

    public function get_name(): string
    {
        return __('LinkedIn', 'convoca-publisher');
    }

    public function is_available(): bool
    {
        return !empty(get_option('convoca_publisher_linkedin_token', '')) && !empty(get_option('convoca_publisher_linkedin_urn', ''));
    }

    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
    {
        $token = get_option('convoca_publisher_linkedin_token', '');
        $urn = get_option('convoca_publisher_linkedin_urn', '');

        if (empty($token) || empty($urn)) {
            return ['success' => false, 'error' => __('Token o URN de LinkedIn no configurados.', 'convoca-publisher')];
        }

        $body = [
            'author' => $urn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [
                        'text' => substr($message, 0, 3000),
                    ],
                    'shareMediaCategory' => 'ARTICLE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ];

        $body['specificContent']['com.linkedin.ugc.ShareContent']['media'][] = [
            'status' => 'READY',
            'description' => [
                'text' => substr(wp_trim_words($message, 30), 0, 256),
            ],
            'originalUrl' => $url,
        ];

        $response = wp_remote_post('https://api.linkedin.com/v2/ugcPosts', [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type'  => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => $response->get_error_message()];
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $body_resp = json_decode(wp_remote_retrieve_body($response), true);

        if ($http_code >= 200 && $http_code < 300) {
            $activity = $body_resp['id'] ?? '';
            return ['success' => true, 'post_id' => $activity];
        }

        $error = $body_resp['message'] ?? __('Error desconocido de LinkedIn API.', 'convoca-publisher');
        return ['success' => false, 'error' => $error];
    }

    public function get_settings_fields(): array
    {
        return [
            'convoca_publisher_linkedin_token' => [
                'title'       => __('Access Token (OAuth 2.0)', 'convoca-publisher'),
                'type'        => 'password',
                'description' => __('Token de LinkedIn con permisos w_organization_social o w_member_social.', 'convoca-publisher'),
            ],
            'convoca_publisher_linkedin_urn' => [
                'title'       => __('URN de perfil/página', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('Ej: urn:li:person:abc123 o urn:li:organization:xyz456', 'convoca-publisher'),
            ],
            'convoca_publisher_linkedin_template' => [
                'title'       => __('Plantilla del mensaje', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('{title}, {excerpt}, {url}, {hashtags}, {date}, {author}. LinkedIn usa ARTICLE format.', 'convoca-publisher'),
            ],
        ];
    }

    public function validate_settings(array $settings): array
    {
        $errors = [];
        if (empty($settings['convoca_publisher_linkedin_token'])) {
            $errors[] = __('El token de LinkedIn es obligatorio.', 'convoca-publisher');
        }
        if (empty($settings['convoca_publisher_linkedin_urn'])) {
            $errors[] = __('La URN de LinkedIn es obligatoria.', 'convoca-publisher');
        }
        return $errors;
    }

    public function verify_connection(): array
    {
        $token = get_option('convoca_publisher_linkedin_token', '');

        if (empty($token)) {
            return ['success' => false, 'message' => __('Token de LinkedIn no configurado.', 'convoca-publisher')];
        }

        $resp = wp_remote_get('https://api.linkedin.com/v2/me', [
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

        if ($http_code >= 200 && $http_code < 300 && isset($body['sub'])) {
            $name = $body['localizedLastName'] ?? $body['sub'] ?? '';
            return [
                'success' => true,
                'message' => sprintf(
                    /* translators: %s: LinkedIn user/profile name */
                    __('✅ Conexión correcta. Perfil: %s', 'convoca-publisher'),
                    $name
                ),
            ];
        }

        $error_msg = $body['message'] ?? $body['error_description'] ?? __('Error desconocido de LinkedIn API.', 'convoca-publisher');
        return ['success' => false, 'message' => $error_msg];
    }
}
