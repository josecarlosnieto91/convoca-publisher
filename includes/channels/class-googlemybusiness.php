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

class Googlemybusiness implements ChannelInterface
{
    public function get_id(): string
    {
        return 'googlemybusiness';
    }

    public function get_name(): string
    {
        return __('Google My Business', 'convoca-publisher');
    }

    public function is_available(): bool
    {
        return !empty(get_option('cp_gmb_token', '')) && !empty(get_option('cp_gmb_location', ''));
    }

    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array
    {
        $token = get_option('cp_gmb_token', '');
        $location = get_option('cp_gmb_location', '');

        if (empty($token) || empty($location)) {
            return ['success' => false, 'error' => __('Token o ubicación de GMB no configurados.', 'convoca-publisher')];
        }

        $summary = html_entity_decode(wp_trim_words($message, 50));

        $body = [
            'summary'      => mb_substr($summary, 0, 1500),
            'callToAction' => [
                'actionType' => 'LEARN_MORE',
                'url'        => $url,
            ],
            'topicType'    => 'STANDARD',
            'alertType'    => 'UNSPECIFIED_ALERT_TYPE',
        ];

        if (!empty($image_url)) {
            $body['media'][] = [
                'mediaFormat' => 'PHOTO',
                'sourceUrl'   => $image_url,
            ];
        }

        $api_url = "https://mybusiness.googleapis.com/v4/{$location}/localPosts";

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => "Bearer {$token}",
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

        if ($http_code >= 200 && $http_code < 300 && isset($resp_body['name'])) {
            return ['success' => true, 'post_id' => $resp_body['name']];
        }

        $error = $resp_body['error']['message'] ?? __('Error desconocido de GMB API.', 'convoca-publisher');
        return ['success' => false, 'error' => $error];
    }

    public function get_settings_fields(): array
    {
        return [
            'cp_gmb_token' => [
                'title'       => __('Access Token (OAuth 2.0)', 'convoca-publisher'),
                'type'        => 'password',
                'description' => __('Token de Google con permisos https://www.googleapis.com/auth/business.manage.', 'convoca-publisher'),
            ],
            'cp_gmb_location' => [
                'title'       => __('Location ID', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('Formato: accounts/{accountId}/locations/{locationId}', 'convoca-publisher'),
            ],
            'cp_gmb_template' => [
                'title'       => __('Plantilla del post', 'convoca-publisher'),
                'type'        => 'text',
                'description' => __('{title}, {excerpt}, {url}. GMB limita a 1500 caracteres. Por defecto: {excerpt} — {url}', 'convoca-publisher'),
            ],
        ];
    }

    public function validate_settings(array $settings): array
    {
        $errors = [];
        if (empty($settings['cp_gmb_token'])) {
            $errors[] = __('El token de Google es obligatorio.', 'convoca-publisher');
        }
        if (empty($settings['cp_gmb_location'])) {
            $errors[] = __('El Location ID de GMB es obligatorio.', 'convoca-publisher');
        }
        return $errors;
    }

    public function verify_connection(): array
    {
        $token = get_option('cp_gmb_token', '');
        $location = get_option('cp_gmb_location', '');

        if (empty($token) || empty($location)) {
            return ['success' => false, 'message' => __('Token o Location ID de GMB no configurados.', 'convoca-publisher')];
        }

        // Validate token format: JWT-like or Google access token
        if (strlen($token) < 20 || strlen($token) > 4096) {
            return ['success' => false, 'message' => __('El token de Google no tiene un formato válido.', 'convoca-publisher')];
        }

        // Validate location format: accounts/{id}/locations/{id}
        if (!preg_match('#^accounts/[^/]+/locations/[^/]+$#', $location)) {
            return ['success' => false, 'message' => __('El Location ID debe tener el formato: accounts/{accountId}/locations/{locationId}', 'convoca-publisher')];
        }

        // Try a lightweight API call to verify the token and location
        $resp = wp_remote_get("https://mybusiness.googleapis.com/v4/{$location}/localPosts?pageSize=1", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($resp)) {
            return ['success' => false, 'message' => __('Error de conexión: ', 'convoca-publisher') . $resp->get_error_message()];
        }

        $http_code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if ($http_code >= 200 && $http_code < 300) {
            return [
                'success' => true,
                'message' => __('✅ Conexión correcta. Location ID válido y accesible.', 'convoca-publisher'),
            ];
        }

        $error_msg = $body['error']['message'] ?? __('Error desconocido de GMB API.', 'convoca-publisher');
        return ['success' => false, 'message' => $error_msg];
    }
}
